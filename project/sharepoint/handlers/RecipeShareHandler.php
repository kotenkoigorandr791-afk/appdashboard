<?php
final class RecipeShareHandler extends BaseAppHandler
{
    public function getAppId(): string
    {
        return 'recipes';
    }

    public function getEntityType(): string
    {
        return 'recipe';
    }

    public function getSupportedDataModes(): array
    {
        return ['live', 'snapshot'];
    }

    public function resolveResource(string $resourceId, array $resourceData, string $ownerEmail): ?Resource
    {
        $recipe = $this->loadRecipe($resourceId);
        if (!$recipe) {
            return null;
        }
        return new Resource('app', $resourceId, 'recipes', (string)($recipe['title']['en'] ?? 'Recipe'), [
            'recipe' => $recipe,
            'owner_email' => $ownerEmail,
        ]);
    }

    public function loadData(Share $share, Resource $resource, array $filters = []): array
    {
        if ($share->snapshot_data !== null) {
            return $share->snapshot_data;
        }
        $recipe = $resource->get('recipe');
        if (!is_array($recipe)) {
            $recipe = $this->loadRecipe($resource->id) ?? [];
        }
        $lang = strtolower((string)($filters['lang'] ?? sp_lang()));
        if (!in_array($lang, ['en', 'uk', 'ru', 'be'], true)) {
            $lang = 'en';
        }
        $title = $recipe['title'][$lang] ?? ($recipe['title']['en'] ?? '');
        $steps = [];
        foreach (($recipe['steps'] ?? []) as $index => $step) {
            $content = $step['content'] ?? '';
            if (is_string($content)) {
                $decoded = json_decode($content, true);
                if (is_array($decoded)) {
                    $content = $decoded[$lang] ?? ($decoded['en'] ?? reset($decoded));
                }
            } elseif (is_array($content)) {
                $content = $content[$lang] ?? ($content['en'] ?? reset($content));
            }
            $steps[] = [
                'index' => $index,
                'type' => $step['type'] ?? 'instruction',
                'content' => (string)$content,
                'quantity' => $step['quantity'] ?? null,
                'timer' => $step['timer'] ?? null,
            ];
        }
        return [
            'recipe' => [
                'id' => $recipe['id'] ?? $resource->id,
                'title' => (string)$title,
                'image' => $recipe['image'] ?? '',
                'steps' => $steps,
            ],
            'lang' => $lang,
            'share_id' => $share->id,
            'permissions' => $share->permissions,
            'data_mode' => $share->dataMode(),
        ];
    }

    public function getContent(Share $share, Resource $resource, string $path): ?array
    {
        if ($path !== 'image' && $path !== '') {
            return null;
        }
        $recipe = $resource->get('recipe');
        if (!is_array($recipe)) {
            $recipe = [];
        }
        $image = (string)($recipe['image'] ?? '');
        if ($image === '') {
            return null;
        }
        $candidates = [
            dirname(__DIR__, 3) . '/apps/recipes/recipes/' . $resource->id . '/' . $image,
            dirname(__DIR__, 3) . '/apps/recipes/' . ltrim($image, '/'),
        ];
        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return ['path' => $candidate, 'mime' => sp_file_mime($candidate, $image), 'name' => basename($candidate)];
            }
        }
        return null;
    }

    private function loadRecipe(string $id): ?array
    {
        try {
            require_once dirname(__DIR__, 3) . '/apps/recipes/core/db.php';
            $db = getDB();
            $stmt = $db->prepare('SELECT * FROM ' . RECIPES_DB_TABLE_PREFIX . 'data WHERE id = :id LIMIT 1');
            $stmt->bindValue(':id', $id, PDO::PARAM_STR);
            $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                return null;
            }
            $recipe = [
                'id' => $row['id'],
                'title' => ['en' => $row['title_en'], 'uk' => $row['title_uk'], 'ru' => $row['title_ru'], 'be' => $row['title_be']],
                'image' => $row['image'] ?? '',
                'steps' => [],
            ];
            $stmt = $db->prepare('SELECT * FROM ' . RECIPES_STEPS_TABLE . ' WHERE recipe_id = :id ORDER BY step_order');
            $stmt->bindValue(':id', $id, PDO::PARAM_STR);
            $result = $stmt->execute();
            while ($step = $result->fetch(PDO::FETCH_ASSOC)) {
                $entry = ['type' => $step['step_type'], 'timer' => null];
                if ($step['step_type'] === 'product') {
                    $entry['content'] = $step['content'];
                    if ($step['quantity_value'] !== null) {
                        $entry['quantity'] = ['value' => $step['quantity_value'], 'unit' => $step['quantity_unit']];
                    }
                } else {
                    $entry['content'] = json_decode((string)$step['content'], true);
                }
                if ($step['timer_hours'] !== null || $step['timer_minutes'] !== null) {
                    $entry['timer'] = ['hours' => $step['timer_hours'], 'minutes' => $step['timer_minutes']];
                }
                $recipe['steps'][] = $entry;
            }
            return $recipe;
        } catch (Throwable $e) {
            error_log('sharepoint recipe load: ' . $e->getMessage());
            return null;
        }
    }

    protected function getRendererClass(): ?string
    {
        return RecipeRenderer::class;
    }
}
