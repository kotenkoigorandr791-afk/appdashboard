<?php
final class ShareFileHandler implements ResourceHandlerInterface
{
    public function getResourceType(): string
    {
        return 'file';
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
        if ($resolved === '' || !is_file($resolved)) {
            return null;
        }
        $name = (string)($resourceData['name'] ?? basename($resolved));
        return new Resource('file', $resourceId, 'cloud', $name, [
            'path' => $resolved,
            'name' => $name,
            'owner_email' => $ownerEmail,
        ]);
    }

    public function getMetadata(Share $share): array
    {
        $resource = $this->resolveResource($share->resource_id, $share->resource_data, $share->owner_email);
        if (!$resource) {
            return ['error' => 'resource_unavailable'];
        }
        $path = (string)$resource->get('path');
        return [
            'name' => $resource->getTitle(),
            'type' => sp_detect_file_type($path),
            'mime' => sp_file_mime($path, $resource->getTitle()),
            'size' => is_file($path) ? (int)filesize($path) : 0,
        ];
    }

    public function render(Share $share, Resource $resource, array $context): string
    {
        return (new FileRenderer($share, $resource, $context))->render();
    }

    public function handleAction(Share $share, Resource $resource, string $action, array $payload): array
    {
        return ['ok' => false, 'error' => 'unsupported_action'];
    }

    public function checkChildAccess(Share $share, string $childPath): bool
    {
        return false;
    }

    public static function stream(Share $share, Resource $resource, bool $download): void
    {
        $path = (string)$resource->get('path');
        $bytes = sp_file_bytes($path, $share->owner_email);
        if ($bytes === false) {
            http_response_code(404);
            echo 'File not found';
            exit;
        }
        $name = basename((string)$resource->getTitle());
        $mime = $download ? 'application/octet-stream' : sp_file_mime($path, $name);
        sp_stream_bytes($bytes, $mime, $name, $download);
        exit;
    }
}
