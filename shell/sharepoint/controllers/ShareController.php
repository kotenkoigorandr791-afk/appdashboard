<?php
final class ShareController
{
    private ShareRepository $repo;

    public function __construct(?ShareRepository $repo = null)
    {
        $this->repo = $repo ?? new ShareRepository();
    }

    public function handle(): void
    {
        // Shared pages are private, token-scoped and may show live data; never
        // let browsers or intermediaries reuse a stale copy.
        header('Cache-Control: no-store, max-age=0');
        header('Pragma: no-cache');

        $token = (string)($_GET['share_token'] ?? '');
        $share = $this->repo->findByToken($token);
        if (!$share) {
            require_once SHAREPOINT_ROOT . '/views/error.php';
            echo sp_render_status_page('not_found', null, 404);
            return;
        }

        $context = [
            'subpath' => (string)($_GET['subpath'] ?? ''),
            'file' => (string)($_GET['file'] ?? ''),
            'embed' => (isset($_GET['mode']) && $_GET['mode'] === 'embed'),
        ];
        // Path traversal protection for folder children.
        if ($share->resource_type === 'folder') {
            $handler = ShareRegistry::getHandler('folder');
            foreach ([$context['subpath'], $context['file']] as $child) {
                if ($child === '') continue;
                if (!$handler || !$handler->checkChildAccess($share, $child)) {
                    require_once SHAREPOINT_ROOT . '/views/error.php';
                    echo sp_render_status_page('not_found', $share, 404);
                    return;
                }
            }
        }

        $access = ShareAccessPolicy::check($share, 'view', $context);
        if (!$access->isAllowed()) {
            require_once SHAREPOINT_ROOT . '/views/password.php';
            require_once SHAREPOINT_ROOT . '/views/error.php';
            if ($access->reason === 'password_required') {
                echo sp_render_password_page($share);
            } else {
                echo sp_render_status_page($access->reason, $share, AccessResult::httpStatus($access->reason));
            }
            return;
        }

        $resource = $access->resource;
        $handler = ResourceResolver::handler($share);
        if (!$handler || !$resource) {
            echo (new class($share, new Resource($share->resource_type, $share->resource_id, $share->resource_app, $share->title), $context) extends BaseRenderer {
                public function render(): string { return ''; }
            })->renderErrorPage('resource_unavailable', 404);
            return;
        }

        $this->trackView($share);

        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('X-Robots-Tag: noindex, nofollow');
        header('X-Content-Type-Options: nosniff');
        echo $handler->render($share, $resource, $context);
    }

    private function trackView(Share $share): void
    {
        if (!empty($_SESSION['share_viewed'][$share->id])) {
            return;
        }
        try {
            $this->repo->incrementViewCount($share->id);
            $_SESSION['share_viewed'][$share->id] = time();
        } catch (Throwable $e) {
        }
    }
}
