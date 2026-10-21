<?php
/**
 * Legacy bridge for the cloud share_file action. Deliberately dependency-free
 * so it can be included from the existing cloud router without loading the
 * whole SharePoint bootstrap.
 */
if (!function_exists('sharepoint_mirror_legacy_cloud')) {
    function sharepoint_mirror_legacy_cloud(
        AppDatabase $db,
        string $token,
        string $name,
        string $path,
        bool $isFolder,
        string $email,
        ?int $expires,
        string $expiresOption,
        string $passwordHash,
        bool $allowDownload,
        int $maxViews = 0
    ): void {
        try {
            $permissions = $allowDownload ? ['view', 'download'] : ['view'];
            $data = [
                'path' => $path,
                'is_folder' => $isFolder,
                'name' => $name,
                'cloud_root' => 'main',
            ];
            $stmt = $db->prepare(
                'INSERT INTO shares (token, token_hash, resource_type, entity_type, resource_id, resource_app,
                    resource_data, title, description, owner_email, access_mode, permissions, password_hash,
                    expires_at, expires_option, max_views, is_active)
                 VALUES (:token, \'\', :rtype, \'\', :rid, \'cloud\', CAST(:data AS jsonb), :title, \'\', :email,
                    \'public\', CAST(:permissions AS jsonb), :password,
                    CASE WHEN :expires IS NULL THEN NULL ELSE to_timestamp(:expires) END,
                    :expires_option, :max_views, TRUE)
                 ON CONFLICT (token) DO UPDATE SET
                    resource_type = EXCLUDED.resource_type,
                    resource_id = EXCLUDED.resource_id,
                    resource_data = EXCLUDED.resource_data,
                    title = EXCLUDED.title,
                    permissions = EXCLUDED.permissions,
                    password_hash = EXCLUDED.password_hash,
                    expires_at = EXCLUDED.expires_at,
                    expires_option = EXCLUDED.expires_option,
                    max_views = EXCLUDED.max_views,
                    is_active = TRUE,
                    revoked_at = NULL'
            );
            $stmt->bindValue(':token', $token, PDO::PARAM_STR);
            $stmt->bindValue(':rtype', $isFolder ? 'folder' : 'file', PDO::PARAM_STR);
            $stmt->bindValue(':rid', $path, PDO::PARAM_STR);
            $stmt->bindValue(':data', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PDO::PARAM_STR);
            $stmt->bindValue(':title', $name, PDO::PARAM_STR);
            $stmt->bindValue(':email', strtolower($email), PDO::PARAM_STR);
            $stmt->bindValue(':permissions', json_encode($permissions), PDO::PARAM_STR);
            $stmt->bindValue(':password', $passwordHash !== '' ? $passwordHash : null, $passwordHash !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $stmt->bindValue(':expires', $expires, $expires === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(':expires_option', $expiresOption !== '' ? $expiresOption : 'never', PDO::PARAM_STR);
            $stmt->bindValue(':max_views', max(0, $maxViews), PDO::PARAM_INT);
            $stmt->execute();
        } catch (Throwable $e) {
            error_log('sharepoint legacy bridge: ' . $e->getMessage());
        }
    }
}

if (!function_exists('sharepoint_public_link')) {
    function sharepoint_public_link(string $token): string
    {
        // Use the request origin so links created on localhost stay local and
        // links created on the public domain stay public.
        $scheme = 'http';
        if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https') {
            $scheme = 'https';
        }
        $host = trim(explode(',', (string)($_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? ''))[0]);
        $base = $host !== '' ? $scheme . '://' . $host : (function_exists('app_public_url') ? app_public_url('') : '');
        if ($base === '') {
            $base = $scheme . '://localhost';
        }
        return rtrim($base, '/') . '/s/' . rawurlencode($token);
    }
}
