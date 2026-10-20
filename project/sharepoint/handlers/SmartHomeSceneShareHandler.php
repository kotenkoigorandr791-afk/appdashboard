<?php
final class SmartHomeSceneShareHandler extends BaseAppHandler
{
    public function getAppId(): string
    {
        return 'smart_home';
    }

    public function getEntityType(): string
    {
        return 'smart_home_scene';
    }

    public function getSupportedDataModes(): array
    {
        return ['live', 'snapshot'];
    }

    public function getDefaultPermissions(): array
    {
        return ['view'];
    }

    public function resolveResource(string $resourceId, array $resourceData, string $ownerEmail): ?Resource
    {
        return new Resource('app', $resourceId, 'smart_home', 'Smart Home scene', [
            'owner_email' => $ownerEmail,
            'scene_id' => $resourceId,
            'group_id' => (string)($resourceData['group_id'] ?? ''),
        ]);
    }

    public function loadData(Share $share, Resource $resource, array $filters = []): array
    {
        if ($share->snapshot_data !== null) {
            return $share->snapshot_data;
        }
        $owner = (string)($resource->get('owner_email') ?: $share->owner_email);
        $devices = [];
        try {
            $db = getDB();
            $stmt = $db->prepare('SELECT * FROM smarthome_devices WHERE user_email = :email ORDER BY room, name');
            $stmt->bindValue(':email', $owner, PDO::PARAM_STR);
            $result = $stmt->execute();
            while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
                $devices[] = [
                    'id' => (string)$row['id'],
                    'name' => (string)$row['name'],
                    'type' => (string)$row['type'],
                    'room' => (string)$row['room'],
                    'status' => (string)$row['status'],
                    'icon' => (string)($row['icon'] ?? 'smart-device'),
                    'params' => json_decode((string)($row['params'] ?? '[]'), true) ?: [],
                    'controls' => json_decode((string)($row['controls'] ?? '[]'), true) ?: [],
                    'displayed_params' => json_decode((string)($row['displayed_params'] ?? '[]'), true) ?: [],
                ];
            }
        } catch (Throwable $e) {
            error_log('sharepoint smart home: ' . $e->getMessage());
        }
        return [
            'devices' => $devices,
            'scene' => $resource->get('scene_id'),
            'read_only' => !$share->hasPermission('edit'),
            'data_mode' => $share->dataMode(),
            'share_id' => $share->id,
            'permissions' => $share->permissions,
        ];
    }

    protected function getRendererClass(): ?string
    {
        return SmartHomeSceneRenderer::class;
    }
}
