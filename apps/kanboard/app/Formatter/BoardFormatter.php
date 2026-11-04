<?php

namespace Kanboard\Formatter;

use Kanboard\Core\Filter\FormatterInterface;
use Kanboard\Model\ProjectModel;
use Kanboard\Model\SwimlaneModel;
use Kanboard\Model\TaskModel;

/**
 * Board Formatter
 *
 * @package formatter
 * @author  Frederic Guillot
 */
class BoardFormatter extends BaseFormatter implements FormatterInterface
{
    /**
     * Project id
     *
     * @access protected
     * @var integer
     */
    protected $projectId;

    /**
     * Maximum number of tasks rendered per column (and swimlane)
     *
     * @access protected
     * @var integer
     */
    protected $taskLimit = 50;

    /**
     * Rank tasks per swimlane/column (board view) instead of per column (list/table views)
     *
     * @access protected
     * @var boolean
     */
    protected $partitionBySwimlane = false;

    /**
     * Set ProjectId
     *
     * @access public
     * @param  integer $projectId
     * @return $this
     */
    public function withProjectId($projectId)
    {
        $this->projectId = $projectId;
        return $this;
    }

    /**
     * Set the maximum number of tasks to load per column
     *
     * @access public
     * @param  integer $limit
     * @return $this
     */
    public function withTaskLimit($limit)
    {
        $this->taskLimit = max(1, (int) $limit);
        return $this;
    }

    /**
     * Rank tasks per swimlane and column instead of per column only
     *
     * @access public
     * @param  boolean $value
     * @return $this
     */
    public function withPartitionBySwimlane($value = true)
    {
        $this->partitionBySwimlane = (bool) $value;
        return $this;
    }

    /**
     * Apply formatter
     *
     * @access public
     * @return array
     */
    public function format()
    {
        $swimlanes = $this->swimlaneModel->getAllByStatus($this->projectId, SwimlaneModel::ACTIVE);
        $columns = $this->columnModel->getAllWithOpenedTaskCount($this->projectId);
        $task_count_by_swimlanes_and_columns = $this->getTaskCountBySwimlaneAndColumn();

        if (empty($swimlanes) || empty($columns)) {
            return array();
        }

        $this->hook->reference('formatter:board:query', $this->query);

        $tasks = $this->fetchTasks(0, $this->taskLimit);
        $this->attachTaskMetadata($tasks);
        $tags = $this->taskTagModel->getTagsByTaskIds(array_column($tasks, 'id'));

        return $this->boardSwimlaneFormatter
            ->withSwimlanes($swimlanes)
            ->withColumns($columns)
            ->withTasks($tasks)
            ->withTags($tags)
            ->withTaskCountBySwimlaneAndColumn($task_count_by_swimlanes_and_columns)
            ->format();
    }

    /**
     * Fetch one page of board tasks for a single column (used by ajax pagination)
     *
     * @access public
     * @param  integer $columnId
     * @param  integer $swimlaneId
     * @param  integer $offset
     * @param  integer $limit
     * @return array
     */
    public function formatTaskSlice($columnId, $swimlaneId, $offset, $limit)
    {
        $tasks = $this->fetchTasks((int) $offset, max(1, (int) $limit), (int) $columnId, (int) $swimlaneId);
        $this->attachTaskMetadata($tasks);

        return $this->boardTaskFormatter
            ->withTasks($tasks)
            ->withTags($this->taskTagModel->getTagsByTaskIds(array_column($tasks, 'id')))
            ->withSwimlaneId((int) $swimlaneId)
            ->withColumnId((int) $columnId)
            ->format();
    }

    /**
     * Fetch one page of tasks for a single column across all swimlanes (list/table views)
     *
     * @access public
     * @param  integer $columnId
     * @param  integer $offset
     * @param  integer $limit
     * @return array
     */
    public function formatColumnSlice($columnId, $offset, $limit)
    {
        $tasks = $this->fetchTasks((int) $offset, max(1, (int) $limit), (int) $columnId, null);
        $this->attachTaskMetadata($tasks);

        $tags = $this->taskTagModel->getTagsByTaskIds(array_column($tasks, 'id'));
        array_merge_relation($tasks, $tags, 'tags', 'id');

        foreach ($tasks as &$task) {
            $task['is_draggable'] = $this->helper->projectRole->isDraggable($task);
        }
        unset($task);

        return $tasks;
    }

    /**
     * Load a page of tasks using a window function to limit rows per column
     *
     * @access protected
     * @param  integer      $offset
     * @param  integer      $limit
     * @param  integer|null $columnId
     * @param  integer|null $swimlaneId
     * @return array
     */
    protected function fetchTasks($offset, $limit, $columnId = null, $swimlaneId = null)
    {
        $this->query
            ->eq(TaskModel::TABLE.'.project_id', $this->projectId)
            ->asc(TaskModel::TABLE.'.position')
            ->asc(TaskModel::TABLE.'.id');

        $baseSql = $this->query->buildSelectQuery();
        $values = $this->query->getConditionBuilder()->getValues();

        $partition = $this->partitionBySwimlane || $swimlaneId !== null
            ? 'kb_base.swimlane_id, kb_base.column_id'
            : 'kb_base.column_id';

        $rankedSql = 'SELECT kb_base.*, ROW_NUMBER() OVER (PARTITION BY '.$partition.' ORDER BY kb_base.position ASC, kb_base.id ASC) AS kb_row_number'
            .' FROM ('.$baseSql.') kb_base';

        $where = array();
        if ($columnId !== null) {
            $where[] = 'kb_ranked.column_id = ?';
            $values[] = (int) $columnId;
        }
        if ($swimlaneId !== null) {
            $where[] = 'kb_ranked.swimlane_id = ?';
            $values[] = (int) $swimlaneId;
        }

        $where[] = 'kb_ranked.kb_row_number > ?';
        $values[] = (int) $offset;
        $where[] = 'kb_ranked.kb_row_number <= ?';
        $values[] = (int) $offset + (int) $limit;

        $sql = 'SELECT * FROM ('.$rankedSql.') kb_ranked WHERE '.implode(' AND ', $where)
            .' ORDER BY kb_ranked.position ASC, kb_ranked.id ASC';

        $statement = $this->db->getConnection()->prepare($sql);
        $statement->execute($values);
        $tasks = $statement->fetchAll(\PDO::FETCH_ASSOC);

        return is_array($tasks) ? $tasks : array();
    }

    /**
     * Add batched counters to tasks when the light query was used
     *
     * @access protected
     * @param  array $tasks
     */
    protected function attachTaskMetadata(array &$tasks)
    {
        $taskIds = array_column($tasks, 'id');

        if (! empty($tasks) && ! array_key_exists('nb_comments', $tasks[0])) {
            $counters = $this->taskFinderModel->getBoardCounters($taskIds);
            foreach ($tasks as &$task) {
                $task += $counters[$task['id']];
            }
            unset($task);
        }
    }

    /**
     * Counters per swimlane and column (single GROUP BY query)
     *
     * @access protected
     * @return array
     */
    protected function getTaskCountBySwimlaneAndColumn()
    {
        $taskCountBySwimlaneAndColumn = array();

        foreach ($this->taskModel->getOpenTaskCountBySwimlaneAndColumn($this->projectId) as $task_count) {
            $taskCountBySwimlaneAndColumn[$task_count['swimlane_id']][$task_count['column_id']] = (int) $task_count['nb_open_tasks'];
        }

        return $taskCountBySwimlaneAndColumn;
    }
}
