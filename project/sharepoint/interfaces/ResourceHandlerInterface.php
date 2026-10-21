<?php
interface ResourceHandlerInterface
{
    public function getResourceType(): string;
    public function getSupportedApps(): array;
    public function resolveResource(string $resourceId, array $resourceData, string $ownerEmail): ?Resource;
    public function getPermissions(): array;
    public function getMetadata(Share $share): array;
    public function render(Share $share, Resource $resource, array $context): string;
    public function handleAction(Share $share, Resource $resource, string $action, array $payload): array;
    public function checkChildAccess(Share $share, string $childPath): bool;
}
