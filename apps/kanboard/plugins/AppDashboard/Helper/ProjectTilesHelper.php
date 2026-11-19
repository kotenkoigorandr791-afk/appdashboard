<?php

namespace Kanboard\Plugin\AppDashboard\Helper;

use Kanboard\Core\Base;
use Kanboard\Model\ColumnModel;
use Kanboard\Model\TaskModel;

class ProjectTilesHelper extends Base
{
    public function forCurrentUser(int $limitPerColumn = 1): array
    {
        $userId = (int) $this->userSession->getId();
        if ($userId <= 0) {
            return [];
        }
        $projects = $this->projectUserRoleModel->getActiveProjectsByUser($userId);
        if (!is_array($projects) || !$projects) {
            return [];
        }

        $projectIds = array_values(array_filter(array_map('intval', array_keys($projects)), static function (int $id): bool {
            return $id > 0;
        }));

        if (empty($projectIds)) {
            return [];
        }

        $limit = max(1, $limitPerColumn);
        $in = implode(', ', array_fill(0, count($projectIds), '?'));
        $pdo = $this->db->getConnection();

        // 1) Columns for every project in a single query.
        $stmt = $pdo->prepare('SELECT id, project_id, title, position FROM '.ColumnModel::TABLE
            .' WHERE project_id IN ('.$in.') ORDER BY project_id ASC, position ASC, id ASC');
        $stmt->execute($projectIds);
        $columnsByProject = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $column) {
            $columnsByProject[(int) $column['project_id']][] = $column;
        }

        // 2) Open task counts per column with one GROUP BY.
        $stmt = $pdo->prepare('SELECT project_id, column_id, COUNT(*) AS nb_open FROM '.TaskModel::TABLE
            .' WHERE project_id IN ('.$in.') AND is_active = ? GROUP BY project_id, column_id');
        $values = $projectIds;
        $values[] = TaskModel::STATUS_OPEN;
        $stmt->execute($values);
        $counts = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $counts[(int) $row['project_id']][(int) $row['column_id']] = (int) $row['nb_open'];
        }

        // 3) Preview tasks for every column in one window query.
        $stmt = $pdo->prepare('SELECT id, project_id, column_id, title FROM ('
            .' SELECT id, project_id, column_id, title, ROW_NUMBER() OVER (PARTITION BY project_id, column_id ORDER BY position ASC, id ASC) AS rn'
            .' FROM '.TaskModel::TABLE.' WHERE project_id IN ('.$in.') AND is_active = ?'
            .') preview WHERE rn <= ?');
        $values = $projectIds;
        $values[] = TaskModel::STATUS_OPEN;
        $values[] = $limit;
        $stmt->execute($values);
        $previews = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $task) {
            $previews[(int) $task['project_id']][(int) $task['column_id']][] = [
                'id' => (int) $task['id'],
                'title' => (string) $task['title'],
            ];
        }

        $out = [];

        foreach ($projects as $projectId => $projectName) {
            $pid = (int) $projectId;
            if ($pid <= 0) {
                continue;
            }

            $columns = $columnsByProject[$pid] ?? [];
            $groups = [];
            $totalOpen = 0;
            $columnCount = count($columns);

            foreach ($columns as $columnIndex => $column) {
                $cid = (int) ($column['id'] ?? 0);
                if ($cid <= 0) {
                    continue;
                }

                $count = (int) ($counts[$pid][$cid] ?? 0);
                $totalOpen += $count;

                $normalizedTitle = mb_strtolower((string) ($column['title'] ?? ''));
                $status = match (true) {
                    str_contains($normalizedTitle, 'backlog') => 'backlog',
                    str_contains($normalizedTitle, 'done'), str_contains($normalizedTitle, 'closed'), str_contains($normalizedTitle, 'complete') => 'done',
                    str_contains($normalizedTitle, 'progress'), str_contains($normalizedTitle, 'doing'), str_contains($normalizedTitle, 'review') => 'progress',
                    str_contains($normalizedTitle, 'todo'), str_contains($normalizedTitle, 'to do') => 'todo',
                    $columnIndex === 0 => 'backlog',
                    $columnIndex === $columnCount - 1 => 'done',
                    $columnIndex === 1 => 'todo',
                    default => 'progress',
                };

                $groups[] = [
                    'id' => $cid,
                    'title' => (string) ($column['title'] ?? ''),
                    'status' => $status,
                    'count' => $count,
                    'tasks' => $previews[$pid][$cid] ?? [],
                ];
            }

            $out[] = [
                'id' => $pid,
                'name' => (string) $projectName,
                'open' => $totalOpen,
                'groups' => $groups,
                'board_url' => $this->helper->url->to('BoardViewController', 'show', ['project_id' => $pid, 'view' => 'list']),
            ];
        }

        usort($out, static function ($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });

        return $out;
    }
}
