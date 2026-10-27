<?php

namespace Kanboard\Plugin\AppDashboard\Helper;

use Kanboard\Core\Base;
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
        $out = [];
        foreach ($projects as $projectId => $projectName) {
            $pid = (int) $projectId;
            if ($pid <= 0) {
                continue;
            }
            $columns = $this->columnModel->getAll($pid);
            $groups = [];
            $totalOpen = 0;
            $columnCount = count($columns);
            foreach ($columns as $columnIndex => $column) {
                $cid = (int) ($column['id'] ?? 0);
                if ($cid <= 0) {
                    continue;
                }
                $tasks = $this->db->table(TaskModel::TABLE)
                    ->eq('project_id', $pid)
                    ->eq('column_id', $cid)
                    ->eq('is_active', TaskModel::STATUS_OPEN)
                    ->asc('position')
                    ->limit(max(1, $limitPerColumn))
                    ->findAll();
                $count = (int) $this->db->table(TaskModel::TABLE)
                    ->eq('project_id', $pid)
                    ->eq('column_id', $cid)
                    ->eq('is_active', TaskModel::STATUS_OPEN)
                    ->count();
                $totalOpen += $count;
                $preview = [];
                foreach ($tasks as $task) {
                    $preview[] = [
                        'id' => (int) $task['id'],
                        'title' => (string) $task['title'],
                    ];
                }
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
                    'tasks' => $preview,
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
