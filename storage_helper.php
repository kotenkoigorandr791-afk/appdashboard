<?php
declare(strict_types=1);
function appdata_base_dir(): string {
    $base = getenv('APPDATA_BASE_DIR');
    if (!is_string($base) || trim($base) === '') $base = '/opt/ApplicationData';
    return rtrim($base, '/');
}
function appdata_uploads_base_dir(): string {
    $base = getenv('UPLOADS_BASE_DIR');
    if (!is_string($base) || trim($base) === '') $base = appdata_base_dir() . '/dashboard/uploads';
    return rtrim($base, '/');
}
function appdata_data_dir(): string {
    $base = getenv('APPDATA_DATA_DIR');
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
    $map = ['main' => '', 'archive' => '.archive', 'temp' => '.temp_files', 'documents' => '.documents', 'photos' => '.photos', 'pdf' => '.pdf', 'trash' => '.trash', 'clientsync' => '.clientsync'];
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
    foreach (['archive', 'temp', 'documents', 'photos', 'pdf', 'trash', 'clientsync'] as $rk) {
        $sub = appdata_root_subdir($rk);
        if ($sub === '') continue;
        $dir = $base . '/' . $sub;
        if ($ensure && !is_dir($dir)) @mkdir($dir, 0775, true);
    }
    if ($rootKey === 'main') return $base;
    $sub = appdata_root_subdir($rootKey);
    return $sub === '' ? $base : ($base . '/' . $sub);
}
function appdata_user_full_path(string $appKey, string $user, string $rootKey, string $relativePath, bool $ensureParent = false): string {
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
