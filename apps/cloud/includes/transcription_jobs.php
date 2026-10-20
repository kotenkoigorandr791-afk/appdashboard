<?php
declare(strict_types=1);

/**
 * Transcription job storage.
 *
 * The speech-to-text pipeline used to keep its job state in PostgreSQL through a
 * Python helper (transcription_db_bridge.py). Speech recognition now runs through
 * the AI hub, so the job table is written and read from PHP directly; both the API
 * endpoint and the background worker use this file.
 */

require_once dirname(__DIR__, 3) . '/lib/app_db.php';

const TRANSCRIPTION_JOB_STALE_SECONDS = 120;

function transcription_jobs_db(): ?AppDatabase
{
    static $db = null;
    if ($db instanceof AppDatabase) {
        return $db;
    }
    try {
        $db = app_main_db();
        $db->enableExceptions(true);
        $db->busyTimeout(3000);
        transcription_jobs_ensure_table($db);
        return $db;
    } catch (Throwable $e) {
        if (function_exists('cloud_safe_error_log')) {
            cloud_safe_error_log('transcription_jobs_db: ' . $e->getMessage());
        } else {
            error_log('transcription_jobs_db: ' . $e->getMessage());
        }
        return null;
    }
}

function transcription_jobs_ensure_table(AppDatabase $db): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $db->exec("CREATE TABLE IF NOT EXISTS cloud_transcription_jobs (
        progress_id TEXT PRIMARY KEY,
        user_name TEXT NOT NULL DEFAULT '',
        audio_path TEXT NOT NULL DEFAULT '',
        language TEXT NOT NULL DEFAULT 'pl',
        status TEXT NOT NULL DEFAULT 'processing',
        percentage INTEGER NOT NULL DEFAULT 0,
        message TEXT NOT NULL DEFAULT '',
        transcription TEXT NOT NULL DEFAULT '',
        error_text TEXT NOT NULL DEFAULT '',
        started_at INTEGER NOT NULL DEFAULT (extract(epoch from now())::bigint),
        updated_at INTEGER NOT NULL DEFAULT (extract(epoch from now())::bigint)
    )");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_cloud_transcription_jobs_status ON cloud_transcription_jobs(status, updated_at DESC)");
    $done = true;
}

function transcription_jobs_create(string $progressId, string $user, string $audioPath, string $language): bool
{
    $db = transcription_jobs_db();
    if (!$db || $progressId === '') {
        return false;
    }
    try {
        $stmt = $db->prepare(
            "INSERT INTO cloud_transcription_jobs
                (progress_id, user_name, audio_path, language, status, percentage, message, transcription, error_text, started_at, updated_at)
             VALUES (:id, :user, :audio, :lang, 'processing', 0, 'Preparing startup...', '', '', :now, :now)"
        );
        $stmt->bindValue(':id', $progressId, PDO::PARAM_STR);
        $stmt->bindValue(':user', $user, PDO::PARAM_STR);
        $stmt->bindValue(':audio', $audioPath, PDO::PARAM_STR);
        $stmt->bindValue(':lang', $language, PDO::PARAM_STR);
        $stmt->bindValue(':now', time(), PDO::PARAM_INT);
        $stmt->execute();
        return true;
    } catch (Throwable $e) {
        transcription_jobs_log('transcription_jobs_create: ' . $e->getMessage());
        return false;
    }
}

function transcription_jobs_update(
    string $progressId,
    int $percentage,
    string $message,
    ?string $transcription = null,
    string $status = 'processing',
    ?string $errorText = null
): bool {
    $db = transcription_jobs_db();
    if (!$db || $progressId === '') {
        return false;
    }
    try {
        // An empty transcription keeps the previous text, so the UI never loses
        // partial results while the progress heartbeats.
        $stmt = $db->prepare(
            "UPDATE cloud_transcription_jobs
                SET percentage = :pct,
                    message = :msg,
                    transcription = CASE WHEN :txt <> '' THEN :txt ELSE transcription END,
                    status = :status,
                    error_text = :err,
                    updated_at = :now
              WHERE progress_id = :id"
        );
        $stmt->bindValue(':pct', $percentage, PDO::PARAM_INT);
        $stmt->bindValue(':msg', $message, PDO::PARAM_STR);
        $stmt->bindValue(':txt', (string)$transcription, PDO::PARAM_STR);
        $stmt->bindValue(':status', $status, PDO::PARAM_STR);
        $stmt->bindValue(':err', (string)$errorText, PDO::PARAM_STR);
        $stmt->bindValue(':now', time(), PDO::PARAM_INT);
        $stmt->bindValue(':id', $progressId, PDO::PARAM_STR);
        $stmt->execute();
        return true;
    } catch (Throwable $e) {
        transcription_jobs_log('transcription_jobs_update: ' . $e->getMessage());
        return false;
    }
}

/**
 * @return array{found: bool, progress_id?: string, progress?: int, percentage?: int, message?: string, transcription?: string, status?: string, error?: string, timestamp?: int, stale_seconds?: int, isProcessing?: bool, isActive?: bool}
 */
function transcription_jobs_get(string $progressId): array
{
    $db = transcription_jobs_db();
    if (!$db || $progressId === '') {
        return ['found' => false];
    }
    try {
        $stmt = $db->prepare(
            "SELECT progress_id, percentage, message, transcription, error_text, status, updated_at
               FROM cloud_transcription_jobs
              WHERE progress_id = :id
              LIMIT 1"
        );
        $stmt->bindValue(':id', $progressId, PDO::PARAM_STR);
        $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        transcription_jobs_log('transcription_jobs_get: ' . $e->getMessage());
        return ['found' => false];
    }
    if (!$row) {
        return ['found' => false];
    }

    $status = (string)($row['status'] ?? 'processing');
    $percentage = (int)($row['percentage'] ?? 0);
    $message = (string)($row['message'] ?? '');
    $errorText = (string)($row['error_text'] ?? '');
    $updatedAt = (int)($row['updated_at'] ?? 0);
    $staleSeconds = max(0, time() - $updatedAt);
    $isError = $status === 'error'
        || str_contains($message, 'Error:')
        || str_contains($errorText, 'Error:');
    $isActive = !$isError && $status === 'processing' && $percentage < 100 && $staleSeconds < TRANSCRIPTION_JOB_STALE_SECONDS;

    return [
        'found' => true,
        'progress_id' => (string)$row['progress_id'],
        'percentage' => $percentage,
        'progress' => $percentage,
        'message' => $message,
        'transcription' => (string)($row['transcription'] ?? ''),
        'timestamp' => $updatedAt,
        'stale_seconds' => $staleSeconds,
        'status' => $status,
        'error' => $errorText,
        'isProcessing' => $isActive,
        'isActive' => $isActive,
    ];
}

function transcription_jobs_find_active(string $user): array
{
    $db = transcription_jobs_db();
    if (!$db) {
        return ['success' => false, 'active_session' => null];
    }
    try {
        $stmt = $db->prepare(
            "SELECT progress_id, percentage, message, transcription, updated_at
               FROM cloud_transcription_jobs
              WHERE user_name = :user AND status = 'processing' AND percentage < 100
              ORDER BY updated_at DESC
              LIMIT 1"
        );
        $stmt->bindValue(':user', $user, PDO::PARAM_STR);
        $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        transcription_jobs_log('transcription_jobs_find_active: ' . $e->getMessage());
        return ['success' => false, 'active_session' => null];
    }
    if (!$row) {
        return ['success' => true, 'active_session' => null];
    }
    $staleSeconds = max(0, time() - (int)($row['updated_at'] ?? 0));
    if ($staleSeconds >= TRANSCRIPTION_JOB_STALE_SECONDS) {
        return ['success' => true, 'active_session' => null];
    }
    return [
        'success' => true,
        'active_session' => [
            'progress_id' => (string)$row['progress_id'],
            'progress' => (int)($row['percentage'] ?? 0),
            'message' => (string)($row['message'] ?? ''),
            'transcription' => (string)($row['transcription'] ?? ''),
        ],
    ];
}

function transcription_jobs_stop(string $progressId): bool
{
    $db = transcription_jobs_db();
    if (!$db || $progressId === '') {
        return false;
    }
    try {
        $stmt = $db->prepare(
            "UPDATE cloud_transcription_jobs
                SET status = 'stopped', error_text = 'Stopped by user', updated_at = :now
              WHERE progress_id = :id"
        );
        $stmt->bindValue(':now', time(), PDO::PARAM_INT);
        $stmt->bindValue(':id', $progressId, PDO::PARAM_STR);
        $stmt->execute();
        return true;
    } catch (Throwable $e) {
        transcription_jobs_log('transcription_jobs_stop: ' . $e->getMessage());
        return false;
    }
}

function transcription_jobs_log(string $message): void
{
    if (function_exists('cloud_safe_error_log')) {
        cloud_safe_error_log($message);
        return;
    }
    error_log($message);
}
