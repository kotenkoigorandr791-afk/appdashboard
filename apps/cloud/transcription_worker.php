<?php
declare(strict_types=1);

/**
 * Background transcription worker.
 *
 * Started by transcription_api.php after a job row is created. It sends the
 * audio file to the AI hub (speech_model from the AI settings) and writes the
 * progress and the recognized text back into cloud_transcription_jobs, which is
 * what the transcription editor streams through SSE.
 *
 * Usage: php transcription_worker.php <progress_id> <audio_file> <language>
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit(1);
}

$progressId = (string)($argv[1] ?? '');
$audioFile = (string)($argv[2] ?? '');
$language = (string)($argv[3] ?? '');

require_once __DIR__ . '/includes/transcription_jobs.php';
require_once __DIR__ . '/includes/ai_speech.php';

if ($progressId === '' || $audioFile === '') {
    fwrite(STDERR, "usage: transcription_worker.php <progress_id> <audio_file> <language>\n");
    exit(1);
}

$job = transcription_jobs_get($progressId);
if (($job['found'] ?? false) !== true || ($job['status'] ?? '') !== 'processing') {
    // Nothing to do: the job was removed or already finished/stopped.
    exit(0);
}

function transcription_worker_progress(string $progressId, int $percentage, string $message): void
{
    transcription_jobs_update($progressId, max(0, min(100, $percentage)), $message);
}

function transcription_worker_is_running(string $progressId): bool
{
    $current = transcription_jobs_get($progressId);
    return ($current['found'] ?? false) === true && ($current['status'] ?? '') === 'processing';
}

transcription_worker_progress($progressId, 5, 'Preparing audio...');

$startedAt = microtime(true);

$result = cloud_ai_transcribe_file($audioFile, $language, [
    'timeout' => CLOUD_AI_SPEECH_TIMEOUT_SECONDS,
    'on_tick' => static function (int $percentage, string $message) use ($progressId, $startedAt) {
        if (!transcription_worker_is_running($progressId)) {
            return false;
        }
        // The hub only reports the upload part, so keep the bar moving while the
        // recognition runs, up to 90% (the last steps are the result saving).
        $elapsed = (int)(microtime(true) - $startedAt);
        $creep = 25 + min(65, intdiv($elapsed, 2));
        transcription_worker_progress($progressId, max($percentage, $creep), $message);
        return true;
    },
]);

if (($result['ok'] ?? false) === true) {
    transcription_worker_progress($progressId, 95, 'Saving result...');
    transcription_jobs_update($progressId, 100, 'Completed', (string)$result['text'], 'completed', '');
    exit(0);
}

$errorMessage = trim((string)($result['error'] ?? 'Transcription failed'));
if ($errorMessage === '') {
    $errorMessage = 'Transcription failed';
}

// The stop button already marked the job as stopped; do not overwrite it with an
// error caused by the aborted request.
if (!transcription_worker_is_running($progressId)) {
    exit(0);
}

$safeError = function_exists('transcription_safe_message')
    ? transcription_safe_message($errorMessage)
    : $errorMessage;
transcription_jobs_update($progressId, 0, 'Error: ' . $safeError, '', 'error', $safeError);
fwrite(STDERR, 'transcription failed: ' . $safeError . "\n");
exit(1);
