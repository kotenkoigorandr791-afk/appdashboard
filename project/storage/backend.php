<?php
/**
 * Storage Manager — JSON API and standalone page shell.
 */
declare(strict_types=1);

function storageJsonResponse($data, int $code = 200): void
{
    if (!headers_sent()) {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function storageRequestData(): array
{
    $raw = file_get_contents('php://input');
    if (is_string($raw) && trim($raw) !== '') {
        $data = json_decode($raw, true);
        if (is_array($data)) return $data;
    }
    return is_array($_POST) ? $_POST : [];
}

function storageApiGuard(): void
{
    if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'GET') return;
    if (function_exists('apSecRequireSameOrigin')) apSecRequireSameOrigin();
    $token = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if ($token === '') {
        $input = storageRequestData();
        $token = (string)($input['_csrf'] ?? '');
    }
    $expected = (string)($_SESSION['_csrf_token'] ?? '');
    if ($expected !== '' && $token !== '' && !hash_equals($expected, $token)) {
        storageJsonResponse(['ok' => false, 'error' => 'csrf_forbidden'], 403);
    }
    if ($expected !== '' && $token === '') {
        storageJsonResponse(['ok' => false, 'error' => 'csrf_missing'], 403);
    }
}

function storageDiskMap(): array
{
    $map = [];
    foreach (StorageDb::disks() as $disk) $map[(int)$disk['id']] = $disk;
    return $map;
}

function storageEnrichEvents(array $events): array
{
    $disks = storageDiskMap();
    foreach ($events as &$event) {
        $event['disk_name'] = isset($disks[(int)($event['disk_id'] ?? 0)]) ? (string)$disks[(int)$event['disk_id']]['name'] : '';
    }
    unset($event);
    return $events;
}

function storageProfilesPublic(): array
{
    $out = [];
    foreach (StorageBackupManager::profiles() as $profile) {
        if (!is_array($profile)) continue;
        $out[] = [
            'id' => (string)($profile['id'] ?? ''),
            'name' => (string)($profile['name'] ?? ''),
            'repository' => (string)($profile['repository'] ?? ''),
            'has_password' => (string)($profile['password'] ?? '') !== '',
            'sources' => array_values((array)($profile['sources'] ?? [])),
        ];
    }
    return $out;
}

function storageBootstrapPayload(bool $scan = false): array
{
    if ($scan) StorageManager::detectAndSync();
    $summary = StorageMonitor::summary();
    $summary['bootstrap'] = StorageManager::bootstrapStatus();
    $summary['migrations'] = StorageDb::migrations(20);
    $summary['events'] = storageEnrichEvents(StorageDb::events(50));
    $summary['backup_profiles'] = storageProfilesPublic();
    return $summary;
}

function storageHandleApi(string $api): void
{
    storageApiGuard();
    $input = storageRequestData();
    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    try {
        switch ($api) {
            case 'bootstrap':
            case 'status':
                storageJsonResponse(['ok' => true] + storageBootstrapPayload(isset($_GET['scan']) && $_GET['scan'] === '1'));
                return;

            case 'scan':
                $stats = StorageManager::detectAndSync();
                StorageManager::requestReconcile();
                storageJsonResponse(['ok' => true, 'scan' => $stats] + storageBootstrapPayload());
                return;

            case 'disks':
                storageJsonResponse(['ok' => true, 'disks' => StorageMonitor::disks()]);
                return;

            case 'disk_check':
                $id = (int)($input['id'] ?? $_GET['id'] ?? 0);
                $disk = StorageDb::diskById($id);
                if ($disk === null) throw new InvalidArgumentException('disk_not_found');
                $result = DiskHealthChecker::check($disk);
                StorageManager::requestReconcile();
                StorageDb::addEvent('disk_health_check', 'Health check: ' . $result['status'], $id, $result['report']);
                storageJsonResponse(['ok' => true, 'result' => $result, 'disks' => StorageMonitor::disks()]);
                return;

            case 'migration_check':
                $id = (int)($input['id'] ?? $_GET['id'] ?? 0);
                $precheck = DiskMigration::precheck($id);
                storageJsonResponse(['ok' => true, 'precheck' => $precheck]);
                return;

            case 'migration_start':
                if ($method !== 'POST') throw new InvalidArgumentException('post_required');
                $id = (int)($input['id'] ?? 0);
                if (empty($input['confirm'])) throw new InvalidArgumentException('confirmation_required');
                $result = DiskMigration::start($id);
                storageJsonResponse(['ok' => true, 'migration' => $result['migration']], 202);
                return;

            case 'migration_resume':
                if ($method !== 'POST') throw new InvalidArgumentException('post_required');
                $id = (string)($input['id'] ?? '');
                storageJsonResponse(['ok' => true, 'migration' => DiskMigration::resume($id)], 202);
                return;

            case 'migration_cancel':
                if ($method !== 'POST') throw new InvalidArgumentException('post_required');
                $id = (string)($input['id'] ?? '');
                DiskMigration::cancel($id);
                storageJsonResponse(['ok' => true]);
                return;

            case 'migration_status':
                $id = (string)($_GET['id'] ?? $input['id'] ?? '');
                $migration = StorageDb::migration($id);
                if ($migration === null) throw new InvalidArgumentException('migration_not_found');
                $counts = [];
                foreach (StorageDb::migrationFiles($id) as $file) {
                    $status = (string)($file['status'] ?? 'pending');
                    $counts[$status] = ($counts[$status] ?? 0) + 1;
                }
                $events = [];
                foreach (StorageDb::events(100) as $event) {
                    if ((string)($event['migration_id'] ?? '') === $id) $events[] = $event;
                }
                storageJsonResponse(['ok' => true, 'migration' => $migration, 'file_counts' => $counts, 'events' => $events]);
                return;

            case 'migrations':
                storageJsonResponse(['ok' => true, 'migrations' => StorageDb::migrations(50)]);
                return;

            case 'events':
                storageJsonResponse(['ok' => true, 'events' => storageEnrichEvents(StorageDb::events((int)($_GET['limit'] ?? 100)))]);
                return;

            case 'settings_save':
                if ($method !== 'POST') throw new InvalidArgumentException('post_required');
                $settings = storage_save_settings($input);
                storageJsonResponse(['ok' => true, 'settings' => $settings]);
                return;

            case 'reconcile':
                StorageManager::requestReconcile();
                $uid = function_exists('posix_geteuid') ? (int)posix_geteuid() : -1;
                $result = $uid === 0 ? MergerFSManager::reconcile() : ['ok' => true, 'action' => 'requested'];
                storageJsonResponse(['ok' => true, 'reconcile' => $result, 'mergerfs' => MergerFSManager::status()]);
                return;

            case 'backup_attach':
                if ($method !== 'POST') throw new InvalidArgumentException('post_required');
                $profileId = (string)($input['profile'] ?? '');
                if ($profileId === '') throw new InvalidArgumentException('profile_required');
                $profile = StorageBackupManager::attachStorageToProfile($profileId);
                storageJsonResponse(['ok' => true, 'profile' => ['id' => (string)($profile['id'] ?? ''), 'sources' => $profile['sources'] ?? []]]);
                return;

            case 'backup_now':
                if ($method !== 'POST') throw new InvalidArgumentException('post_required');
                $spawn = StorageBackupManager::spawnBackup('storage');
                if (!$spawn['ok']) throw new RuntimeException($spawn['error']);
                storageJsonResponse(['ok' => true, 'job' => $spawn['job']], 202);
                return;

            default:
                storageJsonResponse(['ok' => false, 'error' => 'unknown_action'], 404);
        }
    } catch (InvalidArgumentException $e) {
        storageJsonResponse(['ok' => false, 'error' => $e->getMessage()], 400);
    } catch (RuntimeException $e) {
        storageJsonResponse(['ok' => false, 'error' => $e->getMessage()], 409);
    } catch (Throwable $e) {
        storageJsonResponse(['ok' => false, 'error' => $e->getMessage()], 500);
    }
}

$storageApi = isset($_GET['api']) ? (string)$_GET['api'] : '';
if ($storageApi !== '') {
    storageHandleApi($storageApi);
    exit;
}

$GLOBALS['storageCsrf'] = (string)($_SESSION['_csrf_token'] ?? '');
ob_start();
require __DIR__ . '/../views/modals/storage_manager.php';
$storagePanelHtml = (string)ob_get_clean();
$theme = ($_GET['theme'] ?? 'dark') === 'light' ? 'light' : 'dark';
if (!headers_sent()) {
    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: no-store');
}
echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">'
    . '<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">'
    . '<meta name="csrf-token" content="' . htmlspecialchars($GLOBALS['storageCsrf'], ENT_QUOTES, 'UTF-8') . '">'
    . '<title>Storage</title></head><body class="stg-body stg-theme-' . htmlspecialchars($theme, ENT_QUOTES, 'UTF-8') . '">'
    . $storagePanelHtml
    . '</body></html>';
