<?php
interface AppHandlerInterface extends ResourceHandlerInterface
{
    public function getAppId(): string;
    public function getEntityType(): string;
    public function getSupportedDataModes(): array;
    public function getDefaultPermissions(): array;
    public function getEditableActions(): array;
    public function loadData(Share $share, Resource $resource, array $filters = []): array;
    public function getPublicEndpoints(): array;
}
