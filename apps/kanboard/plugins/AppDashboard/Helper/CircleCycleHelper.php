<?php

namespace Kanboard\Plugin\AppDashboard\Helper;

use Kanboard\Core\Base;
use Kanboard\Model\TaskModel;

class CircleCycleHelper extends Base
{
    public function buildFromSwimlanes(array $project, array $swimlanes): array
    {
        $columns = [];
        $tasks = [];
        foreach ($swimlanes as $swimlane) {
            if (empty($swimlane['columns']) || !is_array($swimlane['columns'])) {
                continue;
            }
            foreach ($swimlane['columns'] as $column) {
                $cid = (int) ($column['id'] ?? 0);
                if ($cid <= 0) {
                    continue;
                }
                if (!isset($columns[$cid])) {
                    $columns[$cid] = [
                        'id' => $cid,
                        'title' => (string) ($column['title'] ?? ''),
                        'position' => (int) ($column['position'] ?? 0),
                        'tasks' => [],
                    ];
                }
                foreach (($column['tasks'] ?? []) as $task) {
                    $task['column_id'] = $cid;
                    $task['column_name'] = $columns[$cid]['title'];
                    $task['column_position'] = $columns[$cid]['position'];
                    $tasks[] = $task;
                    $columns[$cid]['tasks'][] = $task;
                }
            }
        }

        uasort($columns, static function ($a, $b) {
            return $a['position'] <=> $b['position'];
        });

        $groups = [];
        foreach ($columns as $column) {
            $groups[] = [
                'id' => $column['id'],
                'title' => $column['title'],
                'status_key' => $this->statusKey($column['title'], $column['position'], count($columns)),
                'count' => count($column['tasks']),
                'tasks' => $column['tasks'],
            ];
        }

        $closed = $this->db->table(TaskModel::TABLE)
            ->eq('project_id', (int) $project['id'])
            ->eq('is_active', TaskModel::STATUS_CLOSED)
            ->desc('date_completed')
            ->limit(100)
            ->findAll();
        $doneGroup = null;
        foreach ($groups as &$group) {
            if ($group['status_key'] === 'done') {
                $doneGroup = &$group;
                break;
            }
        }
        unset($group);
        if ($doneGroup === null && !empty($closed)) {
            $doneTasks = [];
            foreach ($closed as $task) {
                $task['column_name'] = 'Done';
                $task['is_active'] = 0;
                $doneTasks[] = $task;
                $tasks[] = $task;
            }
            $groups[] = [
                'id' => 0,
                'title' => 'Done',
                'status_key' => 'done',
                'count' => count($doneTasks),
                'tasks' => $doneTasks,
            ];
        } elseif ($doneGroup !== null && !empty($closed)) {
            $existing = [];
            foreach ($doneGroup['tasks'] as $t) {
                $existing[(int) $t['id']] = true;
            }
            foreach ($closed as $task) {
                if (isset($existing[(int) $task['id']])) {
                    continue;
                }
                $task['column_name'] = $doneGroup['title'];
                $task['is_active'] = 0;
                $doneGroup['tasks'][] = $task;
                $doneGroup['count']++;
                $tasks[] = $task;
            }
        }

        $keyMap = [];
        foreach ($groups as $g) {
            $keyMap[(int) $g['id']] = $g['status_key'];
        }
        $statTasks = $this->loadStatTasks((int) $project['id']);
        $stats = $this->computeStats($statTasks, $keyMap);
        $burnup = $this->buildBurnup($statTasks, 14);
        $assignees = $this->buildAssigneeProgress($statTasks);

        return [
            'cycle_name' => $this->cycleName($project),
            'groups' => $groups,
            'stats' => $stats,
            'burnup' => $burnup,
            'assignees' => $assignees,
            'task_count' => count($tasks),
        ];
    }

    private function loadStatTasks(int $projectId): array
    {
        $rows = $this->db->table(TaskModel::TABLE)
            ->columns(
                TaskModel::TABLE.'.id',
                TaskModel::TABLE.'.score',
                TaskModel::TABLE.'.time_estimated',
                TaskModel::TABLE.'.date_creation',
                TaskModel::TABLE.'.date_completed',
                TaskModel::TABLE.'.date_started',
                TaskModel::TABLE.'.is_active',
                TaskModel::TABLE.'.owner_id',
                TaskModel::TABLE.'.column_id',
                'c.position AS column_position',
                'c.title AS column_name',
                'u.name AS assignee_name',
                'u.username AS assignee_username'
            )
            ->join('columns AS c', 'id', 'column_id')
            ->left('users AS u', 'id', 'owner_id', TaskModel::TABLE)
            ->eq(TaskModel::TABLE.'.project_id', $projectId)
            ->findAll();
        return is_array($rows) ? $rows : [];
    }

    public function formatDue(?int $ts): string
    {
        if (empty($ts)) {
            return '';
        }
        return date('M j', (int) $ts);
    }

    public function effort(array $task): int
    {
        $score = (int) ($task['score'] ?? 0);
        if ($score > 0) {
            return $score;
        }
        $est = (float) ($task['time_estimated'] ?? 0);
        if ($est > 0) {
            return (int) max(1, round($est));
        }
        return 1;
    }

    public function initials(array $task): string
    {
        $name = trim((string) (($task['assignee_name'] ?? '') ?: ($task['assignee_username'] ?? '')));
        if ($name === '') {
            return '';
        }
        $parts = preg_split('/\s+/', $name) ?: [];
        if (count($parts) >= 2) {
            return mb_strtoupper(mb_substr($parts[0], 0, 1).mb_substr($parts[1], 0, 1));
        }
        return mb_strtoupper(mb_substr($name, 0, 2));
    }

    public function columnsWithStatus(int $projectId): array
    {
        $columns = $this->columnModel->getAll($projectId);
        $total = max(1, count($columns));
        $out = [];
        foreach ($columns as $column) {
            $out[] = [
                'id' => (int) ($column['id'] ?? 0),
                'title' => (string) ($column['title'] ?? ''),
                'status_key' => $this->statusKey((string) ($column['title'] ?? ''), (int) ($column['position'] ?? 0), $total),
            ];
        }
        return $out;
    }

    public function statusKeyPublic(string $title, int $position = 0, int $total = 1): string
    {
        return $this->statusKey($title, $position, $total);
    }

    private function cycleName(array $project): string
    {
        $id = (int) ($project['id'] ?? 0);
        $n = max(1, ($id % 40) + 8);
        return 'Cycle '.$n;
    }

    private function statusKey(string $title, int $position, int $total): string
    {
        $t = mb_strtolower(trim($title));
        if (preg_match('/done|complete|closed|закрыт|готово/', $t)) {
            return 'done';
        }
        if (preg_match('/review|qa|test|проверк/', $t)) {
            return 'review';
        }
        if (preg_match('/progress|doing|wip|работ/', $t)) {
            return 'progress';
        }
        if (preg_match('/backlog|бэклог/', $t)) {
            return 'backlog';
        }
        if (preg_match('/todo|ready|to.?do|ожид/', $t)) {
            return 'todo';
        }
        if ($total <= 1) {
            return 'todo';
        }
        if ($position >= $total) {
            return 'done';
        }
        if ($position <= 1) {
            return 'todo';
        }
        if ($position === $total - 1) {
            return 'review';
        }
        return 'progress';
    }

    private function computeStats(array $tasks, array $keyMap): array
    {
        $scope = 0;
        $started = 0;
        $completed = 0;
        foreach ($tasks as $task) {
            $effort = $this->effort($task);
            $scope += $effort;
            $key = $keyMap[(int) ($task['column_id'] ?? 0)] ?? 'todo';
            if ($key === 'done' || (int) ($task['is_active'] ?? 1) === 0) {
                $completed += $effort;
            } elseif ($key === 'progress' || $key === 'review') {
                $started += $effort;
            }
        }
        $pct = $scope > 0 ? (int) round(($completed / $scope) * 100) : 0;
        $weekdaysLeft = $this->weekdaysLeft(7);
        return [
            'scope' => $scope,
            'started' => $started + $completed,
            'completed' => $completed,
            'percent' => $pct,
            'weekdays_left' => $weekdaysLeft,
            'effort' => $scope,
        ];
    }

    private function weekdaysLeft(int $horizon): int
    {
        $left = 0;
        $ts = time();
        for ($i = 0; $i < $horizon; $i++) {
            $d = (int) date('N', $ts + $i * 86400);
            if ($d < 6) {
                $left++;
            }
        }
        return max(1, $left);
    }

    private function buildBurnup(array $tasks, int $days): array
    {
        $points = [];
        $end = strtotime('today');
        $start = $end - ($days - 1) * 86400;
        $scopeFinal = 0;
        foreach ($tasks as $task) {
            $scopeFinal += $this->effort($task);
        }
        for ($i = 0; $i < $days; $i++) {
            $day = $start + $i * 86400;
            $dayEnd = $day + 86399;
            $scope = 0;
            $started = 0;
            $completed = 0;
            foreach ($tasks as $task) {
                $created = (int) ($task['date_creation'] ?? 0);
                if ($created > 0 && $created > $dayEnd) {
                    continue;
                }
                $effort = $this->effort($task);
                $scope += $effort;
                $doneAt = (int) ($task['date_completed'] ?? 0);
                $isClosed = (int) ($task['is_active'] ?? 1) === 0;
                if ($doneAt > 0 && $doneAt <= $dayEnd) {
                    $completed += $effort;
                    $started += $effort;
                    continue;
                }
                if ($isClosed && ($doneAt === 0 || $doneAt <= $dayEnd)) {
                    $completed += $effort;
                    $started += $effort;
                    continue;
                }
                $startedAt = (int) ($task['date_started'] ?? 0);
                $colPos = (int) ($task['column_position'] ?? 1);
                if (($startedAt > 0 && $startedAt <= $dayEnd) || $colPos > 1) {
                    $started += $effort;
                }
            }
            $t = $days > 1 ? $i / ($days - 1) : 1;
            $points[] = [
                'date' => date('Y-m-d', $day),
                'label' => date('M j', $day),
                'scope' => $scope,
                'started' => $started,
                'completed' => $completed,
                'ideal' => (int) round($scopeFinal * $t),
            ];
        }
        return $points;
    }

    private function buildAssigneeProgress(array $tasks): array
    {
        $buckets = [];
        foreach ($tasks as $task) {
            $uid = (int) ($task['owner_id'] ?? 0);
            $key = $uid > 0 ? (string) $uid : 'none';
            if (!isset($buckets[$key])) {
                $name = trim((string) (($task['assignee_name'] ?? '') ?: ($task['assignee_username'] ?? '')));
                $buckets[$key] = [
                    'key' => $key,
                    'name' => $uid > 0 ? ($name !== '' ? $name : ('#'.$uid)) : t('Unassigned'),
                    'initials' => $uid > 0 ? $this->initials($task) : '?',
                    'total' => 0,
                    'completed' => 0,
                ];
            }
            $effort = $this->effort($task);
            $buckets[$key]['total'] += $effort;
            if ((int) ($task['is_active'] ?? 1) === 0 || $this->isDoneTask($task)) {
                $buckets[$key]['completed'] += $effort;
            }
        }
        $rows = array_values($buckets);
        usort($rows, static function ($a, $b) {
            return $b['total'] <=> $a['total'];
        });
        foreach ($rows as &$row) {
            $row['percent'] = $row['total'] > 0 ? (int) round(($row['completed'] / $row['total']) * 100) : 0;
        }
        unset($row);
        return array_slice($rows, 0, 8);
    }

    private function isDoneTask(array $task): bool
    {
        $name = mb_strtolower((string) ($task['column_name'] ?? ''));
        return (bool) preg_match('/done|complete|closed|готово|закрыт/', $name);
    }
}
