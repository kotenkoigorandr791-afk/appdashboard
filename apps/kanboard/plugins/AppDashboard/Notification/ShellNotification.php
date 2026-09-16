<?php

namespace Kanboard\Plugin\AppDashboard\Notification;

use Kanboard\Core\Base;
use Kanboard\Core\Notification\NotificationInterface;
use Kanboard\Notification\WebNotification;

class ShellNotification extends Base implements NotificationInterface
{
    const TYPE = WebNotification::TYPE;

    public function notifyUser(array $user, $event_name, array $event_data)
    {
        $email = trim((string)($user['email'] ?? ''));
        if ($email === '') {
            return;
        }
        $title = $this->notificationModel->getTitleWithoutAuthor($event_name, $event_data);
        if ($title === '') {
            $title = 'Kanboard';
        }
        $project = (string)($event_data['project_name'] ?? ($event_data['task']['project_name'] ?? ''));
        $task = (string)($event_data['task']['title'] ?? '');
        $message = trim($project . ($task !== '' ? ': ' . $task : ''));
        if (!function_exists('app_notify')) {
            require_once dirname(__DIR__, 5) . '/lib/app_db.php';
        }
        app_notify(
            $email,
            'kanboard',
            'info',
            $title,
            $message,
            (string)$event_name,
            [
                'project_id' => (int)($event_data['task']['project_id'] ?? ($event_data['project_id'] ?? 0)),
                'task_id' => (int)($event_data['task']['id'] ?? 0),
            ]
        );
    }

    public function notifyProject(array $project, $event_name, array $event_data)
    {
    }
}
