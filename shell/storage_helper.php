<?php
declare(strict_types=1);
/**
 * Recover an env var that the entrypoint resolved but a bypassing process
 * (e.g. `docker exec appdashboard php script.php` with no login shell, so
 * /etc/profile.d never ran) did not inherit. The entrypoint persists its
 * resolved roots to this fixed, volume-backed path on every boot
 * (docker-entrypoint.sh). getenv() always wins; this only fills a gap, it
 * never overrides an explicitly-set env var, and it is not a hardcoded
 * directory default — it is the same value the live app actually uses.
 */
function storage_runtime_env_fallback(string $key): ?string {
    static $cache = null;
    if ($cache === null) {
        $path = '/opt/AppDashboard/data/storage/runtime-env.json';
        $raw = is_readable($path) ? @file_get_contents($path) : false;
        $data = is_string($raw) ? json_decode($raw, true) : null;
        $cache = is_array($data) ? $data : [];
    }
    $value = $cache[$key] ?? null;
    return is_string($value) && trim($value) !== '' ? $value : null;
}
/**
 * docker-compose.yml bakes a legacy default into the container's static env
 * (APPDATA_BASE_DIR=/opt/ApplicationData etc.) so getenv() is *never* empty —
 * the entrypoint's runtime override only reaches supervisord's own process
 * tree, not `docker exec`. The runtime file, rewritten by the entrypoint on
 * every boot, is the authoritative "what this boot actually resolved to", so
 * it takes priority over a getenv() value whenever both are present.
 */
function storage_resolved_env(string $key): ?string {
    $value = storage_runtime_env_fallback($key);
    if ($value === null) {
        $env = getenv($key);
        $value = (is_string($env) && trim($env) !== '') ? $env : null;
    }
    return $value;
}
function appdata_base_dir(): string {
    $base = storage_resolved_env('APPDATA_BASE_DIR');
    if (!is_string($base) || trim($base) === '') $base = '/opt/ApplicationData';
    return rtrim($base, '/');
}
function appdata_uploads_base_dir(): string {
    $base = storage_resolved_env('UPLOADS_BASE_DIR');
    if (!is_string($base) || trim($base) === '') $base = appdata_base_dir() . '/dashboard/uploads';
    return rtrim($base, '/');
}
function appdata_data_dir(): string {
    $base = storage_resolved_env('APPDATA_DATA_DIR');
    if (!is_string($base) || trim($base) === '') $base = '/opt/AppDashboard/data';
    return rtrim($base, '/');
}
function appdata_app_base_dir(string $appKey): string {
    $appKey = preg_replace('/[^a-zA-Z0-9_\-]/', '', $appKey);
    if ($appKey === '') throw new RuntimeException('Invalid appKey');
    return appdata_base_dir() . '/' . $appKey;
}
function appdata_root_subdir(string $rootKey): string {
    $rootKey = strtolower(trim($rootKey));
    $map = ['main' => '', 'archive' => '.archive', 'temp' => '.temp_files', 'documents' => '.documents', 'photos' => '.photos', 'pdf' => '.pdf', 'trash' => '.trash'];
    return $map[$rootKey] ?? '';
}
function appdata_normalize_relpath(string $relPath): string {
    $relPath = str_replace('\\', '/', $relPath);
    $relPath = trim($relPath);
    $relPath = ltrim($relPath, '/');
    $relPath = preg_replace('#/+#', '/', $relPath);
    if ($relPath === '' || $relPath === '.') return '';
    $parts = explode('/', $relPath);
    $out = [];
    foreach ($parts as $p) {
        if ($p === '' || $p === '.') continue;
        if ($p === '..') throw new RuntimeException('Invalid path');
        $out[] = $p;
    }
    return implode('/', $out);
}
function appdata_root_relative_path(string $rootKey, string $relativePath): string {
    $rel = appdata_normalize_relpath($relativePath);
    if ($rel === '') return '';
    $sub = appdata_root_subdir($rootKey);
    if ($sub === '') return $rel;
    $prefix = ltrim($sub, '.');
    if (str_starts_with($rel, $sub . '/')) return substr($rel, strlen($sub) + 1);
    if ($rel === $sub) return '';
    if (str_starts_with($rel, $prefix . '/')) return substr($rel, strlen($prefix) + 1);
    if ($rel === $prefix) return '';
    return $rel;
}
function appdata_user_root_dir(string $appKey, string $user, string $rootKey = 'main', bool $ensure = true): string {
    $user = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $user);
    $base = appdata_app_base_dir($appKey) . '/' . $user;
    if ($ensure && !is_dir($base)) @mkdir($base, 0775, true);
    foreach (['archive', 'temp', 'documents', 'photos', 'pdf', 'trash'] as $rk) {
        $sub = appdata_root_subdir($rk);
        if ($sub === '') continue;
        $dir = $base . '/' . $sub;
        if ($ensure && !is_dir($dir)) @mkdir($dir, 0775, true);
    }
    if ($rootKey === 'main') return $base;
    $sub = appdata_root_subdir($rootKey);
    return $sub === '' ? $base : ($base . '/' . $sub);
}
function app_storage_root_path(): string {
    $root = storage_resolved_env('STORAGE_ROOT');
    if (!is_string($root) || trim($root) === '') $root = '/storage';
    return rtrim($root, '/');
}
function app_storage_pool_active(): bool {
    $root = app_storage_root_path();
    if (!is_dir($root)) return false;
    clearstatcache(true, $root . '/.mergerfs');
    return @stat($root . '/.mergerfs') !== false;
}
function app_storage_merged_path(string $relative = ''): string {
    $rel = appdata_normalize_relpath($relative);
    $root = app_storage_root_path();
    return $rel === '' ? $root : ($root . '/' . $rel);
}
/**
 * Refuse application writes while a disk migration owns the pool.
 * Reads stay available; only write paths call this.
 */
function app_storage_write_guard(): void {
    if (getenv('STORAGE_WRITE_GUARD') === '0') return;
    $stateDir = getenv('STORAGE_STATE_DIR');
    if (is_string($stateDir) && trim($stateDir) !== '') {
        $lockPath = rtrim($stateDir, '/') . '/.write-lock';
    } else {
        $base = getenv('APPDATA_DATA_DIR');
        if (!is_string($base) || trim($base) === '') $base = '/opt/AppDashboard/data';
        $lockPath = rtrim($base, '/') . '/storage/.write-lock';
    }
    if (!is_file($lockPath)) return;
    $raw = @file_get_contents($lockPath);
    $lock = is_string($raw) ? json_decode($raw, true) : null;
    if (!is_array($lock)) return;
    $heartbeat = (int)($lock['heartbeat'] ?? 0);
    $pid = (int)($lock['pid'] ?? 0);
    $alive = false;
    if ($pid > 0 && function_exists('posix_kill')) $alive = @posix_kill($pid, 0);
    if ($heartbeat > 0 && (time() - $heartbeat) > 600 && !$alive) return;
    throw new RuntimeException('storage_maintenance_in_progress');
}
function appdata_user_full_path(string $appKey, string $user, string $rootKey, string $relativePath, bool $ensureParent = false): string {
    if ($ensureParent) app_storage_write_guard();
    $rootDir = appdata_user_root_dir($appKey, $user, $rootKey, true);
    $rel = appdata_root_relative_path($rootKey, $relativePath);
    if ($rel === '') return $rootDir;
    $full = $rootDir . '/' . $rel;
    if ($ensureParent) {
        $parent = dirname($full);
        if (!is_dir($parent)) @mkdir($parent, 0775, true);
    }
    return $full;
}
