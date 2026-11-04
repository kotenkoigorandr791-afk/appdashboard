<?php

namespace Kanboard\Controller;

/**
 * Activity Controller
 *
 * @package Kanboard\Controller
 * @author  Frederic Guillot
 */
class ActivityController extends BaseController
{
    /**
     * Activity page for a user
     *
     * @access public
     */
    public function user()
    {
        $user = $this->getUser();
        $limit = 30;
        $offset = max(0, $this->request->getIntegerParam('offset', 0));
        $events = $this->helper->projectActivity->getProjectsEvents($this->projectPermissionModel->getActiveProjectIds($user['id']), $limit + 1, $offset);
        $hasMore = count($events) > $limit;
        $events = array_slice($events, 0, $limit);
        $params = array(
            'title'    => t('Activity stream for %s', $this->helper->user->getFullname($user)),
            'events'   => $events,
            'user'     => $user,
            'has_more' => $hasMore,
            'next_url' => $this->helper->url->href('ActivityController', 'user', array('user_id' => $user['id'], 'offset' => $offset + $limit, 'chunk' => 1)),
            'prev_ts'  => $this->request->getIntegerParam('prev_ts', 0),
        );

        if ($this->request->getIntegerParam('chunk', 0) === 1) {
            $this->response->html($this->template->render('event/events_chunk', $params));
        } elseif ($this->request->isAjax()) {
            $this->response->html($this->template->render('activity/user', $params));
        } else {
            $this->response->html($this->helper->layout->app('activity/user', $params));
        }
    }

    /**
     * Activity page for a project
     *
     * @access public
     */
    public function project()
    {
        $project = $this->getProject();

        $this->response->html($this->helper->layout->app('activity/project', array(
            'title'   => t('%s\'s activity', $project['name']),
            'events'  => $this->helper->projectActivity->getProjectEvents($project['id']),
            'project' => $project,
        )));
    }

    /**
     * Display task activities
     *
     * @access public
     */
    public function task()
    {
        $task = $this->getTask();

        $this->response->html($this->helper->layout->task('activity/task', array(
            'title'   => $task['title'],
            'task'    => $task,
            'project' => $this->projectModel->getById($task['project_id']),
            'events'  => $this->helper->projectActivity->getTaskEvents($task['id']),
            'tags'    => $this->taskTagModel->getTagsByTask($task['id']),
        )));
    }
}
