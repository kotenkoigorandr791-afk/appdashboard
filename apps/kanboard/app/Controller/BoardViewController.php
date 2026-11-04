<?php

namespace Kanboard\Controller;

use Kanboard\Core\Controller\AccessForbiddenException;
use Kanboard\Model\TaskModel;

/**
 * Board controller
 *
 * @package  Kanboard\Controller
 * @author   Frederic Guillot
 */
class BoardViewController extends BaseController
{
    /**
     * Display the public version of a board
     * Access checked by a simple token, no user login, read only, auto-refresh
     *
     * @access public
     */
    public function readonly()
    {
        $token = $this->request->getStringParam('token');
        $project = $this->projectModel->getByToken($token);

        if (empty($project)) {
            throw AccessForbiddenException::getInstance()->withoutLayout();
        }

        $this->taskFinderModel->withLightQuery();

        $query = $this->taskFinderModel
            ->getExtendedQuery()
            ->eq(TaskModel::TABLE.'.is_active', TaskModel::STATUS_OPEN);

        $this->response->html($this->helper->layout->app('board/view_public', array(
            'project' => $project,
            'swimlanes' => $this->boardFormatter
                ->withProjectId($project['id'])
                ->withQuery($query)
                ->withTaskLimit(PHP_INT_MAX)
                ->format(),
            'title' => $project['name'],
            'description' => $project['description'],
            'no_layout' => true,
            'not_editable' => true,
            'board_public_refresh_interval' => $this->configModel->get('board_public_refresh_interval'),
            'board_private_refresh_interval' => $this->configModel->get('board_private_refresh_interval'),
            'board_highlight_period' => $this->configModel->get('board_highlight_period'),
        )));
    }

    /**
     * Show a board for a given project
     *
     * @access public
     */
    public function show()
    {
        $project = $this->getProject();
        $search = $this->helper->projectHeader->getSearchQuery($project);
        $view = isset($_GET['view']) ? (string) $_GET['view'] : 'list';
        $this->taskFinderModel->withLightQuery();

        $params = array(
            'project' => $project,
            'title' => $project['name'],
            'description' => $this->helper->projectHeader->getDescription($project),
            'board_private_refresh_interval' => $this->configModel->get('board_private_refresh_interval'),
            'board_highlight_period' => $this->configModel->get('board_highlight_period'),
            'swimlanes' => $this->taskLexer
                ->build($search)
                ->format($this->boardFormatter
                    ->withProjectId($project['id'])
                    ->withPartitionBySwimlane($view === 'board')),
        );

        if ($view === 'table') {
            $table_limit = 50;
            $params['table_limit'] = $table_limit;
            $this->taskFinderModel->withLightQuery();
            $params['table_tasks'] = $this->taskFinderModel
                ->getExtendedQuery()
                ->eq('tasks.project_id', $project['id'])
                ->asc('column_position')
                ->asc('tasks.position')
                ->limit($table_limit)
                ->findAll();
            $params['table_has_more'] = count($params['table_tasks']) === $table_limit;
            $params['table_columns'] = $this->columnModel->getAll($project['id']);
        }

        $this->response->html($this->helper->layout->app('board/view_private', $params));
    }
}
