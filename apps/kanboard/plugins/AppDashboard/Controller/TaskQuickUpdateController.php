<?php

namespace Kanboard\Plugin\AppDashboard\Controller;

use Kanboard\Controller\BaseController;
use Kanboard\Core\Controller\AccessForbiddenException;

class TaskQuickUpdateController extends BaseController
{
    public function updateField()
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

        $field = (string) ($values['field'] ?? '');
        if ($field === 'priority') {
            $priority = (int) ($values['value'] ?? 0);
            $ok = $this->taskModificationModel->update(array(
                'id' => $taskId,
                'priority' => $priority,
            ));
            if (! $ok) {
                $this->response->json(array('ok' => false), 400);
                return;
            }
            $this->response->json(array('ok' => true, 'priority' => $priority));
            return;
        }

        if ($field === 'column_id') {
            $dstColumnId = (int) ($values['value'] ?? 0);
            if ($dstColumnId <= 0) {
                $this->response->json(array('ok' => false, 'message' => 'Invalid column'), 400);
                return;
            }
            if (! $this->helper->projectRole->canMoveTask((int) $task['project_id'], (int) $task['column_id'], $dstColumnId)) {
                throw new AccessForbiddenException(e("You don't have the permission to move this task"));
            }
            $ok = $this->taskPositionModel->movePosition(
                (int) $task['project_id'],
                $taskId,
                $dstColumnId,
                1,
                (int) $task['swimlane_id']
            );
            if (! $ok) {
                $this->response->json(array('ok' => false), 400);
                return;
            }
            $this->response->json(array('ok' => true, 'column_id' => $dstColumnId));
            return;
        }

        $this->response->json(array('ok' => false, 'message' => 'Unsupported field'), 400);
    }
}
