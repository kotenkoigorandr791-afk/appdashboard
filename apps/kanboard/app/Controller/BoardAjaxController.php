<?php

namespace Kanboard\Controller;

use Exception;
use Kanboard\Core\Controller\AccessForbiddenException;
use Kanboard\Core\Controller\PageNotFoundException;
use Kanboard\Formatter\BoardFormatter;
use Kanboard\Model\UserMetadataModel;

/**
 * Class BoardAjaxController
 *
 * @package Kanboard\Controller
 * @author  Frederic Guillot
 */
class BoardAjaxController extends BaseController
{
    /**
     * Save new task positions (Ajax request made by the drag and drop)
     *
     * @access public
     */
    public function save()
    {
        $this->checkReusableGETCSRFParam();
        $project_id = $this->request->getIntegerParam('project_id');

        if (! $project_id || ! $this->request->isAjax()) {
            throw new AccessForbiddenException();
        }

        $values = $this->request->getJson();

        if ($this->taskFinderModel->getProjectId($values['task_id']) !== $project_id) {
            throw new AccessForbiddenException(e("You don't have the permission to move this task"));
        }

        if (! $this->helper->projectRole->canMoveTask($project_id, $values['src_column_id'], $values['dst_column_id'])) {
            throw new AccessForbiddenException(e("You don't have the permission to move this task"));
        }

        try {
            $result =$this->taskPositionModel->movePosition(
                $project_id,
                $values['task_id'],
                $values['dst_column_id'],
                $values['position'],
                $values['swimlane_id']
            );

            if (! $result) {
                $this->response->status(400);
            } else {
                $this->response->html($this->renderBoard($project_id), 201);
            }
        } catch (Exception $e) {
            $this->response->html('<div class="alert alert-error">'.$e->getMessage().'</div>');
        }
    }

    /**
     * Check if the board have been changed
     *
     * @access public
     */
    public function check()
    {
        $project_id = $this->request->getIntegerParam('project_id');
        $timestamp = $this->request->getIntegerParam('timestamp');

        if (! $project_id || ! $this->request->isAjax()) {
            throw new AccessForbiddenException();
        } elseif (! $this->projectModel->isModifiedSince($project_id, $timestamp)) {
            $this->response->status(304);
        } else {
            $this->response->html($this->renderBoard($project_id));
        }
    }

    /**
     * Reload the board with new filters
     *
     * @access public
     */
    public function reload()
    {
        $project_id = $this->request->getIntegerParam('project_id');

        if (! $project_id || ! $this->request->isAjax()) {
            throw new AccessForbiddenException();
        }

        $values = $this->request->getJson();
        $this->userSession->setFilters($project_id, empty($values['search']) ? '' : $values['search']);

        $this->response->html($this->renderBoard($project_id));
    }

    /**
     * Load more tasks for a column (server-side board pagination)
     *
     * @access public
     */
    public function tasks()
    {
        $project_id = $this->request->getIntegerParam('project_id');
        $mode = $this->request->getStringParam('mode', 'board');

        if (! $project_id || ! $this->request->isAjax()) {
            throw new AccessForbiddenException();
        }

        $column_id = $this->request->getIntegerParam('column_id');
        $swimlane_id = $this->request->getIntegerParam('swimlane_id');
        $offset = max(0, $this->request->getIntegerParam('offset'));
        $limit = min(100, max(1, $this->request->getIntegerParam('limit', 50)));

        $this->taskFinderModel->withLightQuery();

        $query = $this->taskLexer
            ->build($this->userSession->getFilters($project_id))
            ->getQuery();

        $formatter = new BoardFormatter($this->container);
        $formatter->withProjectId($project_id)->withQuery($query);

        if ($mode === 'board') {
            $tasks = $formatter->withPartitionBySwimlane(true)
                ->formatTaskSlice($column_id, $swimlane_id, $offset, $limit);

            $this->response->html(minify_html($this->template->render('board/task_rows', array(
                'project' => $this->projectModel->getById($project_id),
                'tasks' => $tasks,
                'board_highlight_period' => $this->configModel->get('board_highlight_period'),
                'not_editable' => false,
            ))));
            return;
        }

        if ($mode === 'table') {
            $tasks = $this->taskFinderModel
                ->getExtendedQuery()
                ->eq('tasks.project_id', $project_id)
                ->asc('column_position')
                ->asc('tasks.position')
                ->limit($limit)
                ->offset($offset)
                ->findAll();

            $this->response->html($this->template->render('appDashboard:board/task_rows_table', array(
                'tasks' => $tasks,
                'project' => $this->projectModel->getById($project_id),
                'columns' => $this->columnModel->getAll($project_id),
            )));
            return;
        }

        $tasks = $formatter->formatColumnSlice($column_id, $offset, $limit);

        $this->response->html($this->template->render('appDashboard:board/task_rows_list', array(
            'tasks' => $tasks,
            'project' => $this->projectModel->getById($project_id),
            'column' => $this->columnModel->getById($column_id),
        )));
    }

    /**
     * Render the actions dropdown of one board card (loaded on demand)
     *
     * @access public
     */
    public function taskMenu()
    {
        if (! $this->request->isAjax()) {
            throw new AccessForbiddenException();
        }

        $task = $this->taskFinderModel->getById($this->request->getIntegerParam('task_id'));

        if (empty($task)) {
            throw new PageNotFoundException();
        }

        $this->getProject($task['project_id']);

        $this->response->html($this->template->render('task/dropdown', array(
            'task' => $task,
            'redirect' => 'board',
        )));
    }

    /**
     * Enable collapsed mode
     *
     * @access public
     */
    public function collapse()
    {
        $this->changeDisplayMode(1);
    }

    /**
     * Enable expanded mode
     *
     * @access public
     */
    public function expand()
    {
        $this->changeDisplayMode(0);
    }

    /**
     * Change display mode
     *
     * @access private
     * @param  int $mode
     */
    private function changeDisplayMode($mode)
    {
        $this->checkReusableGETCSRFParam();
        $project_id = $this->request->getIntegerParam('project_id');
        $this->userMetadataCacheDecorator->set(UserMetadataModel::KEY_BOARD_COLLAPSED.$project_id, $mode);

        if ($this->request->isAjax()) {
            $this->response->html($this->renderBoard($project_id));
        } else {
            $this->response->redirect($this->helper->url->to('BoardViewController', 'show', array('project_id' => $project_id)));
        }
    }

    /**
     * Render board
     *
     * @access protected
     * @param  integer $project_id
     * @return string
     */
    protected function renderBoard($project_id)
    {
        $this->taskFinderModel->withLightQuery();
        return minify_html($this->template->render('board/table_container', array(
            'project' => $this->projectModel->getById($project_id),
            'board_private_refresh_interval' => $this->configModel->get('board_private_refresh_interval'),
            'board_highlight_period' => $this->configModel->get('board_highlight_period'),
            'swimlanes' => $this->taskLexer
                ->build($this->userSession->getFilters($project_id))
                ->format($this->boardFormatter->withProjectId($project_id)->withPartitionBySwimlane(true)->withTaskLimit(BoardViewController::BOARD_COLUMN_PAGE_SIZE))
        )));
    }
}
