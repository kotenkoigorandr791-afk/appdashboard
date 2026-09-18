<?php

namespace Kanboard\Plugin\AppDashboard\Helper;

use Kanboard\Core\Base;
use Kanboard\Model\TaskModel;

class OverviewCalendarHelper extends Base
{
    public function getTasksPayload(array $project)
    {
        $tasks = $this->taskFinderModel->getAll($project['id'], TaskModel::STATUS_OPEN);
        $payload = array();
        foreach ($tasks as $task) {
            $payload[] = array(
                'id' => (int) $task['id'],
                'title' => $task['title'],
                'date_due' => (int) ($task['date_due'] ?? 0),
                'date_started' => (int) ($task['date_started'] ?? 0),
                'date_creation' => (int) ($task['date_creation'] ?? 0),
                'url' => $this->helper->url->to('TaskViewController', 'show', array('task_id' => $task['id'])),
            );
        }
        return $payload;
    }

    public function getConfigJson(array $project)
    {
        $config = array(
            'tasks' => $this->getTasksPayload($project),
            'csrf' => $this->token->getReusableCSRFToken(),
            'updateUrl' => $this->helper->url->to(
                'OverviewCalendarController',
                'updateDates',
                array(
                    'plugin' => 'AppDashboard',
                    'project_id' => $project['id'],
                    'csrf_token' => $this->token->getReusableCSRFToken(),
                )
            ),
        );
        return json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
