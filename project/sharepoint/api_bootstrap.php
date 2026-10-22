<?php
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__, 2));
}
require_once __DIR__ . '/bootstrap.php';

if (!function_exists('sharepoint_api_owner')) {
    function sharepoint_api_owner(): string
    {
        $user = sp_session_user();
        $email = strtolower(trim((string)($user['email'] ?? '')));
        if ($email === '') {
            sp_json_response(['success' => false, 'error' => 'not authenticated'], 401);
        }
        return $email;
    }
}

if (!function_exists('sharepoint_api_handle')) {
    function sharepoint_api_handle(string $appId): void
    {
        if (defined('APP_ROOT')) {
            require_once APP_ROOT . '/lib/sentry_bootstrap.php';
            app_sentry_bootstrap($appId . '-share');
        }
        if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
            sp_json_response(['success' => false, 'error' => 'method_not_allowed'], 405);
        }
        $owner = sharepoint_api_owner();
        $input = sp_request_body();
        $action = strtolower(trim((string)($input['action'] ?? 'create')));
        $repo = new ShareRepository();

        if ($action === 'create') {
            $input['resource_app'] = $input['resource_app'] ?? $appId;
            if (strtolower((string)$input['resource_app']) !== $appId && $appId !== 'cloud') {
                sp_json_response(['success' => false, 'error' => 'app mismatch'], 400);
            }
            try {
                $service = new ShareService($repo);
                $share = $service->create($input, $owner);
                sp_json_response(['success' => true, 'share' => $service->describe($share)]);
            } catch (InvalidArgumentException $e) {
                sp_json_response(['success' => false, 'error' => $e->getMessage()], 400);
            } catch (Throwable $e) {
                error_log('sharepoint create: ' . $e->getMessage());
                sp_json_response(['success' => false, 'error' => 'create_failed'], 500);
            }
        }

        if ($action === 'list') {
            $shares = $repo->findByOwner($owner, $input);
            $service = new ShareService($repo);
            sp_json_response(['success' => true, 'shares' => array_map([$service, 'describe'], $shares)]);
        }

        if ($action === 'revoke' || $action === 'delete') {
            $id = (int)($input['id'] ?? 0);
            $share = $repo->findById($id);
            if (!$share) {
                sp_json_response(['success' => false, 'error' => 'not_found'], 404);
            }
            if ($share->owner_email !== $owner && !sp_is_admin()) {
                sp_json_response(['success' => false, 'error' => 'forbidden'], 403);
            }
            if ($action === 'revoke') {
                $repo->revoke($id);
            } else {
                $repo->delete($id);
            }
            sp_json_response(['success' => true]);
        }

        sp_json_response(['success' => false, 'error' => 'unsupported_action'], 400);
    }
}
