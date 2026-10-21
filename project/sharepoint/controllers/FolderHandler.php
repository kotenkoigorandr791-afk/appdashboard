<?php
final class ShareFolderHandler implements ResourceHandlerInterface
{
    public function getResourceType(): string
    {
        return 'folder';
    }

    public function getSupportedApps(): array
    {
        return ['cloud'];
    }

    public function getPermissions(): array
    {
        return ['view', 'download'];
    }

    public function resolveResource(string $resourceId, array $resourceData, string $ownerEmail): ?Resource
    {
        $path = (string)($resourceData['path'] ?? $resourceId);
        $resolved = sp_resolve_shared_path($path);
        if ($resolved === '' || !is_dir($resolved)) {
            return null;
        }
        $name = (string)($resourceData['name'] ?? basename($resolved));
        return new Resource('folder', $resourceId, 'cloud', $name, [
            'path' => $resolved,
            'name' => $name,
            'owner_email' => $ownerEmail,
        ]);
    }

    public function getMetadata(Share $share): array
    {
        $resource = $this->resolveResource($share->resource_id, $share->resource_data, $share->owner_email);
        return $resource ? ['name' => $resource->getTitle(), 'type' => 'folder'] : ['error' => 'resource_unavailable'];
    }

    public function render(Share $share, Resource $resource, array $context): string
    {
        return (new FolderRenderer($share, $resource, $context))->render();
    }

    public function handleAction(Share $share, Resource $resource, string $action, array $payload): array
    {
        return ['ok' => false, 'error' => 'unsupported_action'];
    }

    public function checkChildAccess(Share $share, string $childPath): bool
    {
        $resource = $this->resolveResource($share->resource_id, $share->resource_data, $share->owner_email);
        if (!$resource) {
            return false;
        }
        return sp_safe_join((string)$resource->get('path'), $childPath) !== null;
    }

    /** @return array<string,mixed> */
    public static function listItems(Resource $resource, string $subPath = ''): array
    {
        $base = (string)$resource->get('path');
        $dir = $subPath === '' ? $base : sp_safe_join($base, $subPath);
        if ($dir === null || !is_dir($dir)) {
            return ['subpath' => '', 'items' => [], 'error' => 'not_found'];
        }
        $items = [];
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || str_starts_with($entry, '.trash')) continue;
            $full = $dir . DIRECTORY_SEPARATOR . $entry;
            $items[] = [
                'name' => $entry,
                'path' => sp_relative_path($base, $full),
                'is_dir' => is_dir($full),
                'size' => is_file($full) ? (int)filesize($full) : 0,
                'mtime' => is_file($full) || is_dir($full) ? (int)filemtime($full) : 0,
            ];
        }
        usort($items, static function (array $a, array $b): int {
            if ($a['is_dir'] !== $b['is_dir']) {
                return $b['is_dir'] <=> $a['is_dir'];
            }
            return strcasecmp($a['name'], $b['name']);
        });
        return ['subpath' => $subPath, 'items' => $items, 'path' => $dir];
    }
}
