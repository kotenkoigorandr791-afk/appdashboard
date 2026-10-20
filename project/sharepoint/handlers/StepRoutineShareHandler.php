<?php
final class StepRoutineShareHandler extends BaseAppHandler
{
    public function getAppId(): string
    {
        return 'steps';
    }

    public function getEntityType(): string
    {
        return 'step_routine';
    }

    public function getSupportedDataModes(): array
    {
        return ['live', 'snapshot'];
    }

    public function resolveResource(string $resourceId, array $resourceData, string $ownerEmail): ?Resource
    {
        try {
            $this->userDb($ownerEmail);
        } catch (Throwable $e) {
            return null;
        }
        return new Resource('app', $resourceId, 'steps', 'Step routine', ['owner_email' => $ownerEmail]);
    }

    public function loadData(Share $share, Resource $resource, array $filters = []): array
    {
        if ($share->snapshot_data !== null) {
            return $share->snapshot_data;
        }
        $owner = (string)($resource->get('owner_email') ?: $share->owner_email);
        $udb = $this->userDb($owner);
        $days = max(7, min(180, (int)($filters['days'] ?? $resource->get('days', 30))));
        $settings = [];
        try {
            $result = $udb->query('SELECT key, value FROM settings');
            while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
                $settings[(string)$row['key']] = (string)$row['value'];
            }
        } catch (Throwable $e) {
        }
        $rows = [];
        try {
            $stmt = $udb->prepare('SELECT date, steps FROM steps ORDER BY date DESC LIMIT :limit');
            $stmt->bindValue(':limit', $days, PDO::PARAM_INT);
            $result = $stmt->execute();
            while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
                $rows[] = ['date' => (string)$row['date'], 'steps' => (int)$row['steps']];
            }
        } catch (Throwable $e) {
        }
        $rows = array_reverse($rows);
        $total = array_sum(array_column($rows, 'steps'));
        $goal = (int)($settings['daily_goal'] ?? $settings['goal'] ?? 10000);
        return [
            'days' => $rows,
            'total' => $total,
            'average' => $rows ? (int)round($total / count($rows)) : 0,
            'best' => $rows ? max(array_column($rows, 'steps')) : 0,
            'today' => $rows ? (int)end($rows)['steps'] : 0,
            'goal' => max(1, $goal),
            'data_mode' => $share->dataMode(),
            'share_id' => $share->id,
            'permissions' => $share->permissions,
        ];
    }

    private function userDb(string $ownerEmail): AppDatabase
    {
        require_once dirname(__DIR__, 3) . '/lib/user_schema_pg.php';
        return app_user_db_connect('steps', strtolower($ownerEmail));
    }

    protected function getRendererClass(): ?string
    {
        return StepRoutineRenderer::class;
    }
}
