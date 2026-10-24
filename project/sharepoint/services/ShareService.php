<?php
final class ShareService
{
    private ShareRepository $repo;

    public function __construct(?ShareRepository $repo = null)
    {
        $this->repo = $repo ?? new ShareRepository();
    }

    /**
     * @throws InvalidArgumentException
     */
    public function create(array $input, string $ownerEmail): Share
    {
        $ownerEmail = strtolower(trim($ownerEmail));
        if ($ownerEmail === '' || !filter_var($ownerEmail, FILTER_VALIDATE_EMAIL) && !str_contains($ownerEmail, '@')) {
            throw new InvalidArgumentException('Owner is required');
        }

        $resourceType = strtolower(trim((string)($input['resource_type'] ?? 'file')));
        if (!in_array($resourceType, ['file', 'folder', 'app'], true)) {
            throw new InvalidArgumentException('Unsupported resource type');
        }
        $resourceApp = strtolower(trim((string)($input['resource_app'] ?? $input['app'] ?? 'cloud')));
        if (!preg_match('/^[a-z0-9_]{2,32}$/', $resourceApp)) {
            throw new InvalidArgumentException('Unsupported app');
        }
        $resourceId = trim((string)($input['resource_id'] ?? ''));
        if ($resourceId === '') {
            throw new InvalidArgumentException('Resource id is required');
        }
        $resourceData = $input['resource_data'] ?? [];
        if (is_string($resourceData)) {
            $decoded = json_decode($resourceData, true);
            $resourceData = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($resourceData)) {
            $resourceData = [];
        }

        $permissions = $input['permissions'] ?? null;
        if (is_string($permissions)) {
            $decoded = json_decode($permissions, true);
            $permissions = is_array($decoded) ? $decoded : explode(',', $permissions);
        }
        if (!is_array($permissions) || $permissions === []) {
            $handler = $resourceType === 'app' ? ShareRegistry::getAppHandler($resourceApp) : ShareRegistry::getHandler($resourceType);
            $permissions = $handler ? $handler->getPermissions() : ['view', 'download'];
        }
        $permissions = array_values(array_unique(array_intersect(
            array_map('strval', $permissions),
            ShareAccessPolicy::PERMISSIONS
        )));
        if (!in_array('view', $permissions, true)) {
            array_unshift($permissions, 'view');
        }

        $accessMode = strtolower(trim((string)($input['access_mode'] ?? 'public')));
        if (!in_array($accessMode, ShareAccessPolicy::ACCESS_MODES, true)) {
            $accessMode = 'public';
        }

        [$expiresOption, $expiresAt] = ShareTokenService::resolveExpiration(
            (string)($input['expires_option'] ?? '1m')
        );

        $maxViews = max(0, (int)($input['max_views'] ?? 0));
        $password = trim((string)($input['password'] ?? ''));
        $passwordHash = $password !== '' ? password_hash($password, PASSWORD_DEFAULT) : null;

        $title = trim((string)($input['title'] ?? ''));
        $description = trim((string)($input['description'] ?? ''));

        $entityType = trim((string)($input['entity_type'] ?? ''));
        $handler = $resourceType === 'app' ? ShareRegistry::getAppHandler($resourceApp) : ShareRegistry::getHandler($resourceType);
        if ($resourceType === 'app' && !$handler) {
            throw new InvalidArgumentException('Unsupported app resource');
        }
        if ($resourceType === 'app' && $handler instanceof AppHandlerInterface && $entityType === '') {
            $entityType = $handler->getEntityType();
        }
        if ($handler && $title === '') {
            try {
                $resolved = $handler->resolveResource($resourceId, $resourceData, $ownerEmail);
                if ($resolved) {
                    $title = $resolved->getTitle();
                }
            } catch (Throwable $e) {
            }
        }
        if ($title === '') {
            $title = basename($resourceId);
        }

        $dataMode = strtolower((string)($input['data_mode'] ?? 'live'));
        $snapshot = null;
        $snapshotAt = null;
        if ($dataMode === 'snapshot' && $handler instanceof AppHandlerInterface) {
            $resolved = $handler->resolveResource($resourceId, $resourceData, $ownerEmail);
            if (!$resolved) {
                throw new InvalidArgumentException('Resource is not available');
            }
            $snapshot = $handler->loadData(new Share(), $resolved, []);
            $snapshotAt = date('Y-m-d H:i:sP');
        }

        do {
            $token = ShareTokenService::generate();
        } while ($this->repo->findByToken($token) !== null);

        $share = new Share();
        $share->token = $token;
        $share->token_hash = ShareTokenService::hash($token);
        $share->resource_type = $resourceType;
        $share->entity_type = $entityType;
        $share->resource_id = $resourceId;
        $share->resource_app = $resourceApp;
        $share->resource_data = $resourceData;
        $share->title = $title;
        $share->description = $description;
        $share->owner_email = $ownerEmail;
        $share->access_mode = $accessMode;
        $share->permissions = $permissions;
        $share->resource_data['allowed_emails'] = $this->parseEmails($input['allowed_emails'] ?? []);
        $share->password_hash = $passwordHash;
        $share->expires_at = $expiresAt;
        $share->expires_option = $expiresOption;
        $share->snapshot_data = $snapshot;
        $share->snapshot_created_at = $snapshotAt;
        $share->max_views = $maxViews;
        $share->is_active = true;

        return $this->repo->create($share);
    }

    private function parseEmails($value): array
    {
        $list = is_array($value) ? $value : preg_split('/[\s,;]+/', (string)$value);
        return array_values(array_unique(array_filter(array_map(static fn($e) => strtolower(trim((string)$e)), $list), static fn($e) => $e !== '' && str_contains($e, '@'))));
    }

    public function applyUpdate(Share $share, array $input): void
    {
        $permissions = array_values(array_unique(array_intersect(array_map('strval', (array)($input['permissions'] ?? [])), ShareAccessPolicy::PERMISSIONS)));
        if (!in_array('view', $permissions, true)) {
            array_unshift($permissions, 'view');
        }
        $accessMode = strtolower((string)($input['access_mode'] ?? 'public'));
        $title = trim((string)($input['title'] ?? ''));
        [$option, $expiresAt] = ShareTokenService::resolveExpiration((string)($input['expires_option'] ?? '1m'));
        $share->permissions = $permissions;
        $share->access_mode = in_array($accessMode, ShareAccessPolicy::ACCESS_MODES, true) ? $accessMode : 'public';
        $share->title = $title !== '' ? $title : $share->title;
        $share->expires_option = $option;
        $share->expires_at = $expiresAt;
        $share->max_views = max(0, (int)($input['max_views'] ?? 0));
        $share->resource_data['allowed_emails'] = $this->parseEmails($input['allowed_emails'] ?? []);
        $this->repo->update($share);
        $password = trim((string)($input['password'] ?? ''));
        if ($password !== '') {
            $this->repo->setPassword($share->id, password_hash($password, PASSWORD_DEFAULT));
        }
    }

    public function publicLink(Share $share): string
    {
        return sp_public_url($share->token);
    }

    public function describe(Share $share): array
    {
        return [
            'id' => $share->id,
            'token' => $share->token,
            'link' => $this->publicLink($share),
            'resource_type' => $share->resource_type,
            'entity_type' => $share->entity_type,
            'resource_app' => $share->resource_app,
            'title' => $share->title,
            'access_mode' => $share->access_mode,
            'permissions' => $share->permissions,
            'expires_at' => $share->expires_at,
            'expires_option' => $share->expires_option,
            'data_mode' => $share->dataMode(),
            'created_at' => $share->created_at,
            'max_views' => $share->max_views,
            'resource_id' => $share->resource_id,
            'allowed_emails' => $share->resource_data['allowed_emails'] ?? [],
            'password_protected' => $share->isPasswordProtected(),
        ];
    }
}
