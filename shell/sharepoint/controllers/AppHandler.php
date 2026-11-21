<?php
abstract class BaseAppHandler implements AppHandlerInterface
{
    public function getResourceType(): string
    {
        return 'app';
    }

    public function getSupportedApps(): array
    {
        return [$this->getAppId()];
    }

    public function getDefaultPermissions(): array
    {
        return ['view', 'interact'];
    }

    public function getEditableActions(): array
    {
        return [];
    }

    public function getPermissions(): array
    {
        return $this->getDefaultPermissions();
    }

    public function getPublicEndpoints(): array
    {
        return ['refresh', 'filter', 'export'];
    }

    public function getMetadata(Share $share): array
    {
        return [
            'app' => $this->getAppId(),
            'entity' => $this->getEntityType(),
            'data_mode' => $share->dataMode(),
        ];
    }

    public function render(Share $share, Resource $resource, array $context): string
    {
        $class = $this->getRendererClass();
        if (!$class || !class_exists($class)) {
            return (new class($share, $resource, $context) extends BaseRenderer {
                public function render(): string { return ''; }
            })->renderErrorPage('resource_unavailable');
        }
        /** @var AppRendererInterface $renderer */
        $renderer = new $class($share, $resource, $context);
        return $renderer->renderPage($share, $resource, $context);
    }

    public function handleAction(Share $share, Resource $resource, string $action, array $payload): array
    {
        if ($action === 'refresh') {
            return ['ok' => true, 'data' => $this->loadData($share, $resource, $payload)];
        }
        if ($action === 'filter') {
            return ['ok' => true, 'data' => $this->loadData($share, $resource, $payload)];
        }
        if ($action === 'export') {
            $data = $this->loadData($share, $resource, $payload);
            return ['ok' => true, 'download' => [
                'filename' => $this->getAppId() . '-' . date('Ymd-His') . '.json',
                'mime' => 'application/json',
                'content' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            ]];
        }
        return ['ok' => false, 'error' => 'unsupported_action'];
    }

    public function checkChildAccess(Share $share, string $childPath): bool
    {
        return true;
    }

    /** Optional binary content (image/poster) for app renderers. */
    public function getContent(Share $share, Resource $resource, string $path): ?array
    {
        return null;
    }

    abstract protected function getRendererClass(): ?string;
}
