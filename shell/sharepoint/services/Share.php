<?php
final class Share
{
    public int $id = 0;
    public string $token = '';
    public string $token_hash = '';
    public string $resource_type = 'file';
    public string $entity_type = '';
    public string $resource_id = '';
    public string $resource_app = 'cloud';
    public array $resource_data = [];
    public string $title = '';
    public string $description = '';
    public string $owner_email = '';
    public string $access_mode = 'public';
    public array $permissions = ['view'];
    public ?string $password_hash = null;
    public ?string $created_at = null;
    public ?string $expires_at = null;
    public string $expires_option = '1m';
    public ?string $revoked_at = null;
    public ?array $snapshot_data = null;
    public ?string $snapshot_created_at = null;
    public int $view_count = 0;
    public int $max_views = 0;
    public bool $is_active = true;

    public static function fromRow(array $row): self
    {
        $share = new self();
        $share->id = (int)($row['id'] ?? 0);
        $share->token = (string)($row['token'] ?? '');
        $share->token_hash = (string)($row['token_hash'] ?? '');
        $share->resource_type = (string)($row['resource_type'] ?? 'file');
        $share->entity_type = (string)($row['entity_type'] ?? '');
        $share->resource_id = (string)($row['resource_id'] ?? '');
        $share->resource_app = (string)($row['resource_app'] ?? 'cloud');
        $rawData = $row['resource_data'] ?? [];
        if (is_string($rawData)) {
            $rawData = json_decode($rawData, true) ?: [];
        }
        $share->resource_data = is_array($rawData) ? $rawData : [];
        $share->title = (string)($row['title'] ?? '');
        $share->description = (string)($row['description'] ?? '');
        $share->owner_email = strtolower((string)($row['owner_email'] ?? ''));
        $share->access_mode = (string)($row['access_mode'] ?? 'public');
        $rawPerms = $row['permissions'] ?? ['view'];
        if (is_string($rawPerms)) {
            $rawPerms = json_decode($rawPerms, true) ?: ['view'];
        }
        $share->permissions = array_values(array_intersect(
            is_array($rawPerms) ? $rawPerms : ['view'],
            ['view', 'download', 'interact', 'edit', 'share']
        ));
        if ($share->permissions === []) {
            $share->permissions = ['view'];
        }
        $share->password_hash = !empty($row['password_hash']) ? (string)$row['password_hash'] : null;
        $share->created_at = !empty($row['created_at']) ? (string)$row['created_at'] : null;
        $share->expires_at = !empty($row['expires_at']) ? (string)$row['expires_at'] : null;
        $share->expires_option = (string)($row['expires_option'] ?? '1m');
        $share->revoked_at = !empty($row['revoked_at']) ? (string)$row['revoked_at'] : null;
        $rawSnapshot = $row['snapshot_data'] ?? null;
        if (is_string($rawSnapshot) && $rawSnapshot !== '') {
            $rawSnapshot = json_decode($rawSnapshot, true);
        }
        $share->snapshot_data = is_array($rawSnapshot) ? $rawSnapshot : null;
        $share->snapshot_created_at = !empty($row['snapshot_created_at']) ? (string)$row['snapshot_created_at'] : null;
        $share->view_count = (int)($row['view_count'] ?? 0);
        $share->max_views = (int)($row['max_views'] ?? 0);
        $active = $row['is_active'] ?? true;
        if (is_bool($active)) {
            $share->is_active = $active;
        } elseif (is_int($active)) {
            $share->is_active = $active !== 0;
        } else {
            $share->is_active = !in_array(strtolower(trim((string)$active)), ['f', 'false', '0', ''], true);
        }
        return $share;
    }

    public function hasPermission(string $permission): bool
    {
        if ($permission === 'download' && in_array($this->resource_type, ['file', 'folder'], true) && in_array('view', $this->permissions, true)) {
            return true;
        }
        return in_array($permission, $this->permissions, true);
    }

    public function isExpired(?int $now = null): bool
    {
        if (!$this->expires_at) {
            return false;
        }
        $ts = strtotime($this->expires_at);
        return $ts !== false && $ts <= ($now ?? time());
    }

    public function isRevoked(): bool
    {
        return !$this->is_active || $this->revoked_at !== null;
    }

    public function isPasswordProtected(): bool
    {
        return !empty($this->password_hash);
    }

    public function dataMode(): string
    {
        return $this->snapshot_data !== null ? 'snapshot' : 'live';
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'token' => $this->token,
            'resource_type' => $this->resource_type,
            'entity_type' => $this->entity_type,
            'resource_id' => $this->resource_id,
            'resource_app' => $this->resource_app,
            'resource_data' => $this->resource_data,
            'title' => $this->title,
            'description' => $this->description,
            'owner_email' => $this->owner_email,
            'access_mode' => $this->access_mode,
            'permissions' => $this->permissions,
            'created_at' => $this->created_at,
            'expires_at' => $this->expires_at,
            'expires_option' => $this->expires_option,
            'revoked_at' => $this->revoked_at,
            'snapshot_created_at' => $this->snapshot_created_at,
            'view_count' => $this->view_count,
            'max_views' => $this->max_views,
            'is_active' => $this->is_active,
            'data_mode' => $this->dataMode(),
        ];
    }
}
