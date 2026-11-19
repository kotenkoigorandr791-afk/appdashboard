<?php

namespace Kanboard\Plugin\AppDashboard\Notification;

use Kanboard\Core\Base;
use Kanboard\Event\GenericEvent;
use Kanboard\Model\ProjectUserRoleModel;
use Kanboard\Model\UserModel;

/**
 * Push a shell notification whenever a task is created.
 *
 * Kanboard's own notification pipeline only reaches users whose filter matches
 * the task (assignee/creator), so a new unassigned task never reaches the
 * shell. This listener notifies every active project member instead.
 */
class TaskCreatedNotifier extends Base
{
    public function handleEvent(GenericEvent $event): void
    {
        $eventData = $event->getAll();
        $task = isset($eventData['task']) && is_array($eventData['task']) ? $eventData['task'] : array();

        $taskId = (int) ($task['id'] ?? 0);
        $projectId = (int) ($task['project_id'] ?? 0);

        if ($taskId <= 0 || $projectId <= 0) {
            return;
        }

        $this->loadShellDb();

        if (! function_exists('app_notify') || ! function_exists('app_main_db')) {
            return;
        }

        // The author gets no notification for their own task, same as Kanboard.
        $authorId = (int) ($task['creator_id'] ?? 0);
        if ($authorId <= 0 && $this->userSession->isLogged()) {
            $authorId = (int) $this->userSession->getId();
        }

        $title = $task['title'] !== '' ? (string) $task['title'] : t('Task #%d', $taskId);
        $projectName = $this->projectModel->getById($projectId)['name'] ?? '';
        $message = trim($projectName . ': ' . $title, ': ');

        try {
            $db = app_main_db();
            $alreadyNotified = $db->prepare(
                "SELECT 1 FROM app_notifications WHERE user_email = :u AND event_type = 'task.create' AND context_json LIKE :c LIMIT 1"
            );
            $alreadyNotified->bindValue(':c', '%"task_id":' . $taskId . '%');

            foreach ($this->getProjectRecipients($projectId, $authorId) as $userId => $email) {
                $alreadyNotified->bindValue(':u', $email);
                if ($alreadyNotified->execute()->fetchColumn()) {
                    continue;
                }

                app_notify($email, 'kanboard', 'info', t('New task'), $message, 'task.create', array(
                    'project_id' => $projectId,
                    'task_id' => $taskId,
                    'user_id' => $userId,
                ));
            }
        } catch (\Throwable $e) {
            error_log('TaskCreatedNotifier: ' . $e->getMessage());
        }
    }

    /**
     * @return array [user_id => email]
     */
    private function getProjectRecipients(int $projectId, int $excludeUserId): array
    {
        $rows = $this->db
            ->table(ProjectUserRoleModel::TABLE)
            ->columns(UserModel::TABLE . '.id', UserModel::TABLE . '.email')
            ->join(UserModel::TABLE, 'id', 'user_id')
            ->eq(ProjectUserRoleModel::TABLE . '.project_id', $projectId)
            ->eq(UserModel::TABLE . '.is_active', 1)
            ->eq(UserModel::TABLE . '.notifications_enabled', 1)
            ->findAll();

        $recipients = array();

        foreach ($rows as $row) {
            $userId = (int) $row['id'];
            $email = trim((string) $row['email']);

            if ($userId === $excludeUserId || $email === '') {
                continue;
            }

            $recipients[$userId] = $email;
        }

        return $recipients;
    }

    private function loadShellDb(): void
    {
        if (function_exists('app_notify') && function_exists('app_main_db')) {
            return;
        }

        $path = dirname(__DIR__, 5) . '/lib/app_db.php';

        if (is_file($path)) {
            require_once $path;
        }
    }
}
