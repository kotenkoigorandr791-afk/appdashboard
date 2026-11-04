<?php

namespace Kanboard\Plugin\AppDashboard\Controller;

use Kanboard\Controller\BaseController;
use Kanboard\Core\Controller\AccessForbiddenException;

/**
 * Creates (or reuses) a SharePoint share for a Kanboard board and sends the
 * user to the public SharePoint page. The Kanboard readonly URL/token is never
 * exposed: the board itself is rendered by SharePoint from the Kanboard schema.
 */
class SharepointController extends BaseController
{
    public function share()
    {
        $projectId = (int)$this->request->getIntegerParam('project_id');
        $project = $projectId > 0 ? $this->projectModel->getById($projectId) : array();

        if (empty($project)) {
            throw new AccessForbiddenException();
        }
        if (!$this->helper->user->hasProjectAccess('ProjectViewController', 'show', $projectId)) {
            throw new AccessForbiddenException();
        }

        $userId = (int)$this->userSession->getId();
        $user = $userId > 0 ? $this->userModel->getById($userId) : array();
        $email = strtolower(trim((string)($user['email'] ?? '')));
        if ($email === '' || !str_contains($email, '@')) {
            $this->flash->failure(t('Unable to share this board: the account has no email address.'));
            $this->response->redirect($this->helper->url->to('ProjectViewController', 'show', array('project_id' => $projectId)));
            return;
        }

        $root = dirname(__DIR__, 5);
        if (!is_file($root . '/project/sharepoint/bootstrap.php')) {
            $root = dirname(__DIR__, 6);
        }
        $bootstrap = $root . '/project/sharepoint/bootstrap.php';
        if (!is_file($bootstrap)) {
            $this->flash->failure(t('SharePoint is not available.'));
            $this->response->redirect($this->helper->url->to('ProjectViewController', 'show', array('project_id' => $projectId)));
            return;
        }
        require_once $bootstrap;

        try {
            $repo = new \ShareRepository();
            foreach ($repo->findByOwner($email, array('resource_app' => 'kanboard', 'status' => 'active')) as $candidate) {
                if ((string)$candidate->resource_id === (string)$projectId) {
                    $this->response->redirect(\sp_public_url($candidate->token), true);
                    return;
                }
            }

            $service = new \ShareService($repo);
            $share = $service->create(array(
                'resource_type' => 'app',
                'resource_app' => 'kanboard',
                'resource_id' => (string)$projectId,
                'resource_data' => array('project_id' => $projectId),
                'permissions' => array('view'),
                'access_mode' => 'public',
                'expires_option' => '1m',
                'title' => (string)$project['name'],
                'description' => (string)($project['description'] ?? ''),
            ), $email);

            $this->flash->success(t('SharePoint link created.'));
            $this->response->redirect(\sp_public_url($share->token), true);
        } catch (\Throwable $e) {
            error_log('kanboard sharepoint share: ' . $e->getMessage());
            $this->flash->failure(t('Unable to create a SharePoint link for this board.'));
            $this->response->redirect($this->helper->url->to('ProjectViewController', 'show', array('project_id' => $projectId)));
        }
    }
}
