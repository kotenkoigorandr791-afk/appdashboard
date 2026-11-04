<?php
/**
 * Read-only SharePoint sharing for Kanboard boards.
 *
 * Board data is read straight from Kanboard's schema, so a public link never
 * exposes Kanboard's own readonly URL/token: the visitor only ever sees the
 * SharePoint page and its password/expiry/revocation rules.
 */
final class KanboardBoardShareHandler extends BaseAppHandler
{
    public function getAppId(): string
    {
        return 'kanboard';
    }

    public function getEntityType(): string
    {
        return 'kanboard_board';
    }

    public function getSupportedDataModes(): array
    {
        return ['live'];
    }

    public function getDefaultPermissions(): array
    {
        return ['view'];
    }

    public function getEditableActions(): array
    {
        return [];
    }

    public function getPublicEndpoints(): array
    {
        return ['refresh'];
    }

    public function resolveResource(string $resourceId, array $resourceData, string $ownerEmail): ?Resource
    {
        $projectId = (int)$resourceId;
        if ($projectId <= 0) {
            return null;
        }
        $pdo = $this->pdo();
        if (!$pdo) {
            return null;
        }
        try {
            $stmt = $pdo->prepare('SELECT id, name, description, is_active, is_private, owner_id FROM projects WHERE id = :id LIMIT 1');
            $stmt->bindValue(':id', $projectId, PDO::PARAM_INT);
            $stmt->execute();
            $project = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$project) {
                return null;
            }
            if (!$this->ownerCanAccessProject($pdo, $ownerEmail, $project)) {
                return null;
            }
            return new Resource(
                'app',
                (string)$projectId,
                'kanboard',
                (string)$project['name'],
                ['owner_email' => $ownerEmail, 'project_id' => $projectId]
            );
        } catch (Throwable $e) {
            error_log('sharepoint kanboard resolve: ' . $e->getMessage());
            return null;
        }
    }

    public function loadData(Share $share, Resource $resource, array $filters = []): array
    {
        $projectId = (int)($resource->get('project_id', $resource->id));
        $pdo = $this->pdo();
        if (!$pdo) {
            return [];
        }
        try {
            $stmt = $pdo->prepare('SELECT id, name, description, is_active FROM projects WHERE id = :id LIMIT 1');
            $stmt->bindValue(':id', $projectId, PDO::PARAM_INT);
            $stmt->execute();
            $project = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

            $columns = [];
            $stmt = $pdo->prepare('SELECT id, title FROM columns WHERE project_id = :id ORDER BY position ASC, id ASC');
            $stmt->bindValue(':id', $projectId, PDO::PARAM_INT);
            $stmt->execute();
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $columns[(int)$row['id']] = [
                    'id' => (int)$row['id'],
                    'title' => (string)$row['title'],
                ];
            }

            $swimlanes = [];
            $stmt = $pdo->prepare('SELECT id, name FROM swimlanes WHERE project_id = :id AND is_active = TRUE ORDER BY position ASC, id ASC');
            $stmt->bindValue(':id', $projectId, PDO::PARAM_INT);
            $stmt->execute();
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $swimlanes[(int)$row['id']] = [
                    'id' => (int)$row['id'],
                    'name' => (string)$row['name'],
                    'columns' => [],
                ];
            }

            $stmt = $pdo->prepare(
                'SELECT t.id, t.title, t.description, t.color_id, t.column_id, t.swimlane_id,
                        t.reference, t.date_due, t.priority, t.time_estimated,
                        u.name AS assignee_name, u.username AS assignee_username
                 FROM tasks t
                 LEFT JOIN users u ON u.id = t.owner_id
                 WHERE t.project_id = :id AND t.is_active = TRUE
                 ORDER BY t.position ASC, t.id ASC'
            );
            $stmt->bindValue(':id', $projectId, PDO::PARAM_INT);
            $stmt->execute();
            $tasks = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $assignee = trim((string)($row['assignee_name'] ?? ''));
                if ($assignee === '') {
                    $assignee = (string)($row['assignee_username'] ?? '');
                }
                $tasks[] = [
                    'id' => (int)$row['id'],
                    'title' => (string)$row['title'],
                    'description' => (string)($row['description'] ?? ''),
                    'color_id' => (string)($row['color_id'] ?? ''),
                    'column_id' => (int)$row['column_id'],
                    'swimlane_id' => (int)$row['swimlane_id'],
                    'reference' => (string)($row['reference'] ?? ''),
                    'date_due' => (int)($row['date_due'] ?? 0),
                    'priority' => (int)($row['priority'] ?? 0),
                    'time_estimated' => (float)($row['time_estimated'] ?? 0),
                    'assignee' => $assignee,
                ];
            }

            foreach ($swimlanes as $swimlaneId => &$swimlane) {
                foreach ($columns as $columnId => $column) {
                    $swimlane['columns'][] = [
                        'id' => $columnId,
                        'title' => $column['title'],
                        'tasks' => [],
                    ];
                }
            }
            unset($swimlane);

            foreach ($tasks as $task) {
                $swimlaneId = (int)$task['swimlane_id'];
                if (!isset($swimlanes[$swimlaneId])) {
                    continue;
                }
                foreach ($swimlanes[$swimlaneId]['columns'] as &$column) {
                    if ($column['id'] === (int)$task['column_id']) {
                        $column['tasks'][] = $task;
                        break;
                    }
                }
                unset($column);
            }

            return [
                'project' => [
                    'id' => (int)($project['id'] ?? $projectId),
                    'name' => (string)($project['name'] ?? ''),
                    'description' => (string)($project['description'] ?? ''),
                    'is_active' => (bool)($project['is_active'] ?? true),
                ],
                'swimlanes' => array_values($swimlanes),
                'task_count' => count($tasks),
                'data_mode' => $share->dataMode(),
                'share_id' => $share->id,
                'permissions' => $share->permissions,
            ];
        } catch (Throwable $e) {
            error_log('sharepoint kanboard load: ' . $e->getMessage());
            return [];
        }
    }

    private function pdo(): ?PDO
    {
        if (!function_exists('kanboard_pdo')) {
            $bootstrap = dirname(__DIR__, 3) . '/apps/kanboard/integration/bootstrap.php';
            if (!is_file($bootstrap)) {
                return null;
            }
            require_once $bootstrap;
        }
        return function_exists('kanboard_pdo') ? kanboard_pdo() : null;
    }

    private function ownerCanAccessProject(PDO $pdo, string $ownerEmail, array $project): bool
    {
        $email = strtolower(trim($ownerEmail));
        if ($email === '') {
            return false;
        }
        $stmt = $pdo->prepare('SELECT id, role FROM users WHERE LOWER(email) = :email LIMIT 1');
        $stmt->bindValue(':email', $email, PDO::PARAM_STR);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            return false;
        }
        if (in_array((string)$user['role'], ['app-admin', 'app-manager'], true)) {
            return true;
        }
        if ((int)($project['owner_id'] ?? 0) === (int)$user['id']) {
            return true;
        }
        $stmt = $pdo->prepare('SELECT 1 FROM project_has_users WHERE project_id = :project AND user_id = :user LIMIT 1');
        $stmt->bindValue(':project', (int)$project['id'], PDO::PARAM_INT);
        $stmt->bindValue(':user', (int)$user['id'], PDO::PARAM_INT);
        $stmt->execute();
        if ($stmt->fetchColumn()) {
            return true;
        }
        $stmt = $pdo->prepare(
            'SELECT 1 FROM project_has_groups pg
             JOIN group_has_users gu ON gu.group_id = pg.group_id
             WHERE pg.project_id = :project AND gu.user_id = :user LIMIT 1'
        );
        $stmt->bindValue(':project', (int)$project['id'], PDO::PARAM_INT);
        $stmt->bindValue(':user', (int)$user['id'], PDO::PARAM_INT);
        $stmt->execute();
        if ($stmt->fetchColumn()) {
            return true;
        }
        return empty($project['is_private']);
    }

    protected function getRendererClass(): ?string
    {
        return KanboardBoardRenderer::class;
    }
}
