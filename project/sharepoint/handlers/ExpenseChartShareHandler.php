<?php
final class ExpenseChartShareHandler extends BaseAppHandler
{
    public function getAppId(): string
    {
        return 'spends';
    }

    public function getEntityType(): string
    {
        return 'expense_chart';
    }

    public function getSupportedDataModes(): array
    {
        return ['live', 'snapshot'];
    }

    public function getDefaultPermissions(): array
    {
        return ['view', 'interact'];
    }

    public function resolveResource(string $resourceId, array $resourceData, string $ownerEmail): ?Resource
    {
        try {
            $this->userDb($ownerEmail);
        } catch (Throwable $e) {
            return null;
        }
        return new Resource('app', $resourceId, 'spends', 'Expense chart', ['owner_email' => $ownerEmail]);
    }

    public function loadData(Share $share, Resource $resource, array $filters = []): array
    {
        if ($share->snapshot_data !== null) {
            return $share->snapshot_data;
        }
        $owner = (string)($resource->get('owner_email') ?: $share->owner_email);
        $udb = $this->userDb($owner);
        $settings = [];
        try {
            $result = $udb->query('SELECT key, value FROM settings');
            while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
                $settings[(string)$row['key']] = (string)$row['value'];
            }
        } catch (Throwable $e) {
        }

        $monthly = [];
        $result = $udb->query("SELECT substr(date, 1, 7) AS ym, ROUND(SUM(amount)::numeric, 2) AS total FROM expenses WHERE amount > 0 GROUP BY ym ORDER BY ym");
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            $monthly[] = ['month' => (string)$row['ym'], 'total' => (float)$row['total']];
        }

        $categories = [];
        $result = $udb->query("SELECT COALESCE(NULLIF(s.category, ''), 'other') AS category, ROUND(SUM(e.amount)::numeric, 2) AS total
            FROM expenses e LEFT JOIN stores s ON s.name = e.store WHERE e.amount > 0 GROUP BY 1 ORDER BY total DESC");
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            $categories[] = ['category' => (string)$row['category'], 'total' => (float)$row['total']];
        }

        $daily = [];
        $result = $udb->query("SELECT date, ROUND(SUM(amount)::numeric, 2) AS total FROM expenses WHERE amount > 0 GROUP BY date ORDER BY date DESC LIMIT 120");
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            $daily[] = ['date' => (string)$row['date'], 'total' => (float)$row['total']];
        }
        $daily = array_reverse($daily);

        $total = array_sum(array_column($monthly, 'total'));
        return [
            'monthly' => $monthly,
            'categories' => $categories,
            'daily' => $daily,
            'total' => round($total, 2),
            'currency' => $settings['currency'] ?? 'USD',
            'monthly_budget' => (float)($settings['monthly_budget'] ?? 0),
            'data_mode' => $share->dataMode(),
            'share_id' => $share->id,
            'permissions' => $share->permissions,
        ];
    }

    private function userDb(string $ownerEmail): AppDatabase
    {
        require_once dirname(__DIR__, 3) . '/lib/user_schema_pg.php';
        return app_user_db_connect('spends', strtolower($ownerEmail));
    }

    protected function getRendererClass(): ?string
    {
        return ExpenseChartRenderer::class;
    }
}
