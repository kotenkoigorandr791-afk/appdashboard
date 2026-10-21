<?php

namespace Kanboard\Plugin\AppDashboard\Controller;

use Kanboard\Controller\BaseController;
use Kanboard\Core\Controller\AccessForbiddenException;
use Kanboard\Core\Security\Role;
use Kanboard\Model\UserModel;
use Kanboard\Plugin\AppDashboard\User\ShellDirectoryBackendUserProvider;

class ProjectMemberController extends BaseController
{
    public function search()
    {
        if (! $this->request->isAjax()) {
            throw new AccessForbiddenException();
        }
        $term = trim((string) $this->request->getStringParam('term'));
        if (mb_strlen($term) < 2) {
            $this->response->json([]);
            return;
        }
        // Prefer shell directory (email-first). Avoid DatabaseBackendUserProvider
        // which omits email and can dump broad username matches.
        $provider = new ShellDirectoryBackendUserProvider($this->container);
        $users = $provider->find($term);
        $out = [];
        foreach ($users as $user) {
            $email = (string) $user->getEmail();
            $name = (string) $user->getName();
            $username = (string) $user->getUsername();
            if ($email === '') {
                continue;
            }
            $label = $name !== '' ? $name.' <'.$email.'>' : $email;
            $out[] = [
                'id' => (int) $user->getInternalId(),
                'username' => $username,
                'email' => $email,
                'display_name' => $name !== '' ? $name : $username,
                'external_id' => $email,
                'external_id_column' => 'username',
                'value' => $email,
                'label' => $label,
            ];
        }
        $this->response->json($out);
    }

    public function searchAssignable()
    {
        if (! $this->request->isAjax()) {
            throw new AccessForbiddenException();
        }
        $projectId = $this->request->getIntegerParam('project_id');
        $term = trim((string) $this->request->getStringParam('term'));
        if ($projectId <= 0 || mb_strlen($term) < 2) {
            $this->response->json([]);
            return;
        }
        if (! $this->helper->user->hasProjectAccess('TaskModificationController', 'update', $projectId)) {
            throw new AccessForbiddenException();
        }
        $like = '%'.$term.'%';
        $rows = $this->db->table('project_has_users')
            ->columns(UserModel::TABLE.'.id', UserModel::TABLE.'.username', UserModel::TABLE.'.name', UserModel::TABLE.'.email')
            ->join(UserModel::TABLE, 'id', 'user_id')
            ->eq('project_has_users.project_id', $projectId)
            ->eq(UserModel::TABLE.'.is_active', 1)
            ->neq('project_has_users.role', Role::PROJECT_VIEWER)
            ->beginOr()
            ->ilike(UserModel::TABLE.'.email', $like)
            ->ilike(UserModel::TABLE.'.username', $like)
            ->ilike(UserModel::TABLE.'.name', $like)
            ->closeOr()
            ->asc(UserModel::TABLE.'.email')
            ->limit(12)
            ->findAll();
        $out = [];
        foreach ($rows as $row) {
            $email = (string) ($row['email'] ?? '');
            $name = (string) (($row['name'] ?? '') ?: $row['username']);
            $out[] = [
                'id' => (int) $row['id'],
                'value' => $email !== '' ? $email : $name,
                'label' => $email !== '' ? $name.' <'.$email.'>' : $name,
            ];
        }
        $this->response->json($out);
    }

    public function add()
    {
        $this->checkCSRFForm();
        $project = $this->getProject();
        if ((int) $project['is_private'] === 1) {
            throw new AccessForbiddenException();
        }
        $values = $this->request->getValues();
        $role = (string) ($values['role'] ?? Role::PROJECT_MEMBER);
        $userId = (int) ($values['user_id'] ?? 0);
        $email = strtolower(trim((string) ($values['email'] ?? $values['name'] ?? '')));

        if ($userId <= 0 && $email !== '') {
            $userId = $this->ensureUserIdByEmail($email, (string) ($values['username'] ?? ''), (string) ($values['display_name'] ?? ''));
        } elseif ($userId <= 0 && ! empty($values['external_id'])) {
            $emailGuess = strtolower(trim((string) $values['external_id']));
            if (str_contains($emailGuess, '@')) {
                $userId = $this->ensureUserIdByEmail($emailGuess, (string) ($values['username'] ?? ''), (string) ($values['display_name'] ?? $values['name'] ?? ''));
            }
        }

        if ($userId <= 0) {
            $this->flash->failure(t('User not found.'));
        } else {
            $existingRole = (string) $this->projectUserRoleModel->getUserRole($project['id'], $userId);
            if ($existingRole !== '' || $this->projectUserRoleModel->addUser($project['id'], $userId, $role)) {
                // Already a member counts as success (idempotent add).
                $this->flash->success(t('Project updated successfully.'));
            } else {
                $this->flash->failure(t('Unable to update this project.'));
            }
        }

        $this->response->redirect($this->helper->url->to('ProjectPermissionController', 'index', ['project_id' => $project['id']]));
    }

    private function ensureUserIdByEmail(string $email, string $username = '', string $name = ''): int
    {
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 0;
        }
        $existing = $this->userModel->getByEmail($email);
        if (is_array($existing) && ! empty($existing['id'])) {
            return (int) $existing['id'];
        }
        if ($username === '') {
            $local = explode('@', $email)[0] ?? 'user';
            $username = preg_replace('/[^a-zA-Z0-9._-]+/', '_', $local) ?: 'user';
            $username = mb_substr($username, 0, 50);
        }
        $base = $username;
        $i = 1;
        while ($this->userModel->getByUsername($username)) {
            $username = mb_substr($base, 0, 40).'_'.$i;
            $i++;
            if ($i > 50) {
                return 0;
            }
        }
        if ($name === '') {
            $name = $base;
        }
        $id = $this->userModel->create([
            'username' => $username,
            'name' => $name,
            'email' => $email,
            'role' => Role::APP_USER,
            'is_ldap_user' => 1,
            'disable_login_form' => 1,
            'notifications_enabled' => 1,
        ]);
        return (int) $id;
    }
}
