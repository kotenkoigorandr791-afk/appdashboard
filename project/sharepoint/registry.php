<?php
final class ShareRegistry
{
    /** @var array<string,ResourceHandlerInterface> */
    private static array $handlers = [];
    /** @var array<string,AppHandlerInterface> */
    private static array $appHandlers = [];

    public static function register(string $resourceType, ResourceHandlerInterface $handler): void
    {
        self::$handlers[$resourceType] = $handler;
    }

    public static function registerApp(AppHandlerInterface $handler): void
    {
        self::$appHandlers[$handler->getAppId()] = $handler;
    }

    public static function getHandler(string $resourceType): ?ResourceHandlerInterface
    {
        return self::$handlers[$resourceType] ?? null;
    }

    public static function getAppHandler(string $appId): ?AppHandlerInterface
    {
        return self::$appHandlers[strtolower($appId)] ?? null;
    }

    public static function getHandlerForShare(Share $share): ?ResourceHandlerInterface
    {
        if ($share->resource_type === 'app') {
            return self::getAppHandler($share->resource_app);
        }
        return self::getHandler($share->resource_type);
    }

    public static function getAllHandlers(): array
    {
        return self::$handlers;
    }

    public static function getAllAppHandlers(): array
    {
        return self::$appHandlers;
    }

    /** @return array<string,array{label:string,icon:string}> */
    public static function appCatalog(): array
    {
        return [
            'cloud' => ['label' => 'Cloud', 'icon' => 'cloud'],
            'recipes' => ['label' => 'Recipes', 'icon' => 'restaurant_menu'],
            'spends' => ['label' => 'Spends', 'icon' => 'pie_chart'],
            'steps' => ['label' => 'Steps', 'icon' => 'directions_walk'],
            'films' => ['label' => 'Films', 'icon' => 'movie'],
            'smart_home' => ['label' => 'Smart Home', 'icon' => 'home'],
        ];
    }

    public static function appMeta(string $appId): array
    {
        $catalog = self::appCatalog();
        return $catalog[$appId] ?? ['label' => ucfirst($appId), 'icon' => 'apps'];
    }

    public static function entityCatalog(): array
    {
        return [
            'recipe' => 'Recipe',
            'expense_chart' => 'Expense Chart',
            'step_routine' => 'Step Routine',
            'film' => 'Film',
            'smart_home_scene' => 'Smart Home Scene',
        ];
    }
}
