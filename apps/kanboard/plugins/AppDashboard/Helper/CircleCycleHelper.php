<?php

namespace Kanboard\Plugin\AppDashboard\Helper;

use Kanboard\Core\Base;
use Kanboard\Model\TaskModel;
use Kanboard\Plugin\AppDashboard\Cache\CycleCache;

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
                        'total' => max((int) ($column['nb_unfiltered_tasks_across_swimlane'] ?? 0), (int) ($column['column_nb_open_tasks'] ?? 0)),
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
            $loaded = count($column['tasks']);
            $total = max((int) $column['total'], $loaded);
            $groups[] = [
                'id' => $column['id'],
                'title' => $column['title'],
                'status_key' => $this->statusKey($column['title'], $column['position'], count($columns)),
                'count' => $loaded,
                'total' => $total,
                'has_more' => $total > $loaded,
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

        foreach ($groups as &$group) {
            $group['count'] = count($group['tasks']);
            $group['total'] = max((int) ($group['total'] ?? 0), $group['count']);
            $group['has_more'] = $group['total'] > $group['count'];
            foreach ($group['tasks'] as &$groupTask) {
                $groupTask['_status_key'] = $group['status_key'];
                $groupTask['_column_id'] = $group['id'];
            }
            unset($groupTask);
        }
        unset($group);

        $keyMap = [];
        foreach ($groups as $g) {
            $keyMap[(int) $g['id']] = $g['status_key'];
        }
        $panel = $this->buildPanelData((int) $project['id'], $keyMap);
        $stats = $panel['stats'];
        $burnup = $panel['burnup'];
        $assignees = $panel['assignees'];

        return [
            'cycle_name' => $this->cycleName($project),
            'groups' => $groups,
            'stats' => $stats,
            'burnup' => $burnup,
            'assignees' => $assignees,
            'task_count' => count($tasks),
        ];
    }

    private function buildPanelData(int $projectId, array $keyMap): array
    {
        $fingerprint = $this->taskFingerprint($projectId);
        $cached = CycleCache::get($projectId);

        if (is_array($cached)
            && isset($cached['stats'], $cached['burnup'], $cached['assignees'])
            && ($cached['fingerprint'] ?? null) === $fingerprint
        ) {
            return $cached;
        }

        $statTasks = $this->loadStatTasks($projectId);
        $panel = [
            'fingerprint' => $fingerprint,
            'stats' => $this->computeStats($statTasks, $keyMap),
            'burnup' => $this->buildBurnupSql($projectId, 14, $statTasks),
            'assignees' => $this->buildAssigneeProgress($statTasks),
        ];

        CycleCache::set($projectId, $panel);

        return $panel;
    }

    /**
     * Cheap project fingerprint used to invalidate the cached panel as soon as
     * a task is created, edited, moved or closed.
     */
    private function taskFingerprint(int $projectId): array
    {
        $row = $this->db->table(TaskModel::TABLE)
            ->columns(
                'COUNT(*) AS nb_tasks',
                'COALESCE(SUM(CASE WHEN is_active THEN 1 ELSE 0 END), 0) AS nb_open',
                'COALESCE(SUM(score), 0) AS score_sum',
                'COALESCE(SUM(position), 0) AS position_sum',
                'COALESCE(MAX(date_modification), 0) AS max_modified',
                'COALESCE(MAX(date_moved), 0) AS max_moved',
                'COALESCE(MAX(date_completed), 0) AS max_completed'
            )
            ->eq('project_id', $projectId)
            ->findOne();

        if (! is_array($row)) {
            return [0, 0, 0, 0, 0, 0, 0];
        }

        return array_map('intval', array_values($row));
    }

    /**
     * Build the 14-day burnup without the days×tasks cross join: every task is
     * reduced once to the day it enters scope, starts and is completed, then
     * the 14 points are three cumulative sums over those buckets.
     */
    private function buildBurnupSql(int $projectId, int $days, array $statTasks): array
    {
        $sql = "
            WITH days AS (
                SELECT gs::date AS day
                FROM generate_series(
                    date_trunc('day', now()) - (? - 1) * interval '1 day',
                    date_trunc('day', now()),
                    interval '1 day'
                ) gs
            ),
            effort AS (
                SELECT x.e, x.c_day,
                       CASE WHEN x.moved THEN x.c_day
                            WHEN x.s_ts IS NULL THEN NULL
                            WHEN x.s_ts <= 0 THEN x.c_day
                            ELSE GREATEST(x.c_day, date_trunc('day', to_timestamp(x.s_ts))::date) END AS s_day,
                       CASE WHEN x.d_ts IS NULL THEN NULL
                            WHEN x.d_ts <= 0 THEN x.c_day
                            ELSE GREATEST(x.c_day, date_trunc('day', to_timestamp(x.d_ts))::date) END AS d_day
                FROM (
                    SELECT CASE WHEN t.score > 0 THEN t.score
                                WHEN t.time_estimated > 0 THEN GREATEST(1, ROUND(t.time_estimated)::int)
                                ELSE 1 END AS e,
                           CASE WHEN t.date_creation IS NULL OR t.date_creation = 0 THEN DATE '0001-01-01'
                                ELSE date_trunc('day', to_timestamp(t.date_creation))::date END AS c_day,
                           (c.position > 1) AS moved,
                           LEAST(NULLIF(t.date_started, 0),
                                 CASE WHEN t.date_completed > 0 THEN t.date_completed
                                      WHEN t.is_active = false AND t.date_completed = 0 THEN 0
                                      ELSE NULL END) AS s_ts,
                           CASE WHEN t.date_completed > 0 THEN t.date_completed
                                WHEN t.is_active = false AND t.date_completed = 0 THEN 0
                                ELSE NULL END AS d_ts
                    FROM ".TaskModel::TABLE.' t'.
                    ' LEFT JOIN columns c ON c.id = t.column_id'.
                    ' WHERE t.project_id = ?'.
                "
            ),
            buckets AS (
                SELECT 'scope' AS kind, c_day AS bday, SUM(e) AS e FROM effort GROUP BY c_day
                UNION ALL
                SELECT 'started', s_day, SUM(e) FROM effort WHERE s_day IS NOT NULL GROUP BY s_day
                UNION ALL
                SELECT 'completed', d_day, SUM(e) FROM effort WHERE d_day IS NOT NULL GROUP BY d_day
            )
            SELECT d.day,
                   (SELECT COALESCE(SUM(b.e), 0) FROM buckets b WHERE b.kind = 'scope' AND b.bday <= d.day)::int AS scope,
                   (SELECT COALESCE(SUM(b.e), 0) FROM buckets b WHERE b.kind = 'started' AND b.bday <= d.day)::int AS started,
                   (SELECT COALESCE(SUM(b.e), 0) FROM buckets b WHERE b.kind = 'completed' AND b.bday <= d.day)::int AS completed
            FROM days d
            ORDER BY d.day";

        try {
            $statement = $this->db->getConnection()->prepare($sql);
            $statement->execute([(int) $days, (int) $projectId]);
            $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return $this->buildBurnup($statTasks, $days);
        }

        if (empty($rows)) {
            return $this->buildBurnup($statTasks, $days);
        }

        $scopeFinal = (int) end($rows)['scope'];
        $points = [];
        $count = count($rows);

        foreach (array_values($rows) as $i => $row) {
            $ts = strtotime($row['day'].' 00:00:00');
            $t = $count > 1 ? $i / ($count - 1) : 1;
            $points[] = [
                'date' => $row['day'],
                'label' => date('M j', $ts),
                'scope' => (int) $row['scope'],
                'started' => (int) $row['started'],
                'completed' => (int) $row['completed'],
                'ideal' => (int) round($scopeFinal * $t),
            ];
        }

        return $points;
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
            ->left('columns', 'c', 'id', TaskModel::TABLE, 'column_id')
            ->left('users', 'u', 'id', TaskModel::TABLE, 'owner_id')
            ->eq(TaskModel::TABLE.'.project_id', $projectId)
            ->findAll();
        return is_array($rows) ? $rows : [];
    }

    public function statusIcons(): array
    {
        return [
            'backlog' => '<svg class="kb-st-icon kb-st-backlog" viewBox="0 0 16 16" aria-hidden="true"><circle cx="8" cy="8" r="5.25" fill="none" stroke="currentColor" stroke-width="1.5" stroke-dasharray="2.2 2"/></svg>',
            'todo' => '<svg class="kb-st-icon kb-st-todo" viewBox="0 0 16 16" aria-hidden="true"><circle cx="8" cy="8" r="5.25" fill="none" stroke="currentColor" stroke-width="1.5"/></svg>',
            'progress' => '<svg class="kb-st-icon kb-st-progress" viewBox="0 0 16 16" aria-hidden="true"><circle cx="8" cy="8" r="5.25" fill="none" stroke="currentColor" stroke-width="1.5"/><path d="M8 2.75a5.25 5.25 0 0 1 0 10.5Z" fill="currentColor"/></svg>',
            'review' => '<svg class="kb-st-icon kb-st-review" viewBox="0 0 16 16" aria-hidden="true"><circle cx="8" cy="8" r="5.25" fill="none" stroke="currentColor" stroke-width="1.5"/><path d="m5.6 8.2 1.7 1.7 3.2-3.4" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            'done' => '<svg class="kb-st-icon kb-st-done" viewBox="0 0 16 16" aria-hidden="true"><circle cx="8" cy="8" r="6" fill="currentColor"/><path d="m5.4 8.1 1.8 1.8 3.5-3.7" fill="none" stroke="#0f0f12" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        ];
    }

    public function priorityIconSvg(int $priority, int $min, int $max): string
    {
        $span = max(1, $max - $min);
        $level = (int) round((($priority - $min) / $span) * 3);
        $level = max(0, min(3, $level));
        $colors = ['#94a3b8', '#3b82f6', '#f59e0b', '#ef4444'];
        $color = $colors[$level];
        $bars = '';
        for ($i = 0; $i < 3; $i++) {
            $h = 4 + $i * 3;
            $y = 14 - $h;
            $x = 3 + $i * 4;
            if ($level === 0) {
                $fill = 'rgba(148,163,184,0.35)';
            } elseif ($i < $level) {
                $fill = $color;
            } else {
                $fill = 'rgba(148,163,184,0.28)';
            }
            $bars .= '<rect x="'.$x.'" y="'.$y.'" width="2.5" height="'.$h.'" rx="0.6" fill="'.$fill.'"/>';
        }
        return '<svg class="kb-prio-icon kb-prio-'.$level.'" viewBox="0 0 16 16" width="14" height="14" aria-hidden="true">'.$bars.'</svg>';
    }

    public function taskStatusKey(array $task, int $totalColumns = 1): string
    {
        if (! empty($task['_status_key'])) {
            return (string) $task['_status_key'];
        }
        return $this->statusKey(
            (string) ($task['column_name'] ?? ''),
            (int) ($task['column_position'] ?? 0),
            $totalColumns
        );
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
        if (preg_match('/done|complete|closed|закрыт|готово|зроблен|сконча|заверш|гатов[ао]/', $t)) {
            return 'done';
        }
        if (preg_match('/review|qa|test|проверк|праверк|рэцэнз|тэсц/', $t)) {
            return 'review';
        }
        if (preg_match('/progress|doing|wip|работ|у працы|прац|выконва/', $t)) {
            return 'progress';
        }
        if (preg_match('/backlog|бэклог|чарг/', $t)) {
            return 'backlog';
        }
        if (preg_match('/todo|ready|to.?do|ожид|гатов|чакан/', $t)) {
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
        return (bool) preg_match('/done|complete|closed|готово|закрыт|зроблен|сконча|заверш|гатов[ао]/', $name);
    }
}
