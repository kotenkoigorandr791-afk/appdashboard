<?php

namespace Kanboard\Plugin\AppDashboard\Controller;

use Kanboard\Controller\BaseController;
use Kanboard\Core\Controller\AccessForbiddenException;

class OverviewCalendarController extends BaseController
{
    public function updateDates()
    {
        $this->checkReusableGETCSRFParam();

        if (! $this->request->isAjax()) {
            throw new AccessForbiddenException();
        }

        $values = $this->request->getJson();
        $taskId = (int) ($values['task_id'] ?? 0);
        if ($taskId <= 0) {
            $this->response->json(array('ok' => false, 'message' => 'Invalid task'), 400);
            return;
        }

        $task = $this->taskFinderModel->getById($taskId);
        if (empty($task)) {
            $this->response->json(array('ok' => false, 'message' => 'Task not found'), 404);
            return;
        }

        if (! $this->helper->user->hasProjectAccess('TaskModificationController', 'update', $task['project_id'])) {
            throw new AccessForbiddenException();
        }

        if (! $this->helper->projectRole->canUpdateTask($task)) {
            throw new AccessForbiddenException(t('You are not allowed to update tasks assigned to someone else.'));
        }

        $dateStarted = isset($values['date_started']) ? (int) $values['date_started'] : 0;
        $dateDue = isset($values['date_due']) ? (int) $values['date_due'] : 0;
        if ($dateDue < 0) {
            $dateDue = 0;
        }
        if ($dateStarted < 0) {
            $dateStarted = 0;
        }

        $ok = $this->taskModificationModel->update(array(
            'id' => $taskId,
            'date_started' => $dateStarted > 0 ? (string) $dateStarted : '',
            'date_due' => $dateDue > 0 ? (string) $dateDue : '',
        ));

        if (! $ok) {
            $this->response->json(array('ok' => false), 400);
            return;
        }

        $this->response->json(array('ok' => true));
    }
}
