<?php
final class FilmShareHandler extends BaseAppHandler
{
    public function getAppId(): string
    {
        return 'films';
    }

    public function getEntityType(): string
    {
        return 'film';
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
        $movie = $this->findMovie($resourceId, $resourceData);
        if (!$movie) {
            return null;
        }
        return new Resource('app', $resourceId, 'films', $this->localized($movie['title'] ?? 'Film'), [
            'movie' => $movie,
            'owner_email' => $ownerEmail,
        ]);
    }

    public function loadData(Share $share, Resource $resource, array $filters = []): array
    {
        if ($share->snapshot_data !== null) {
            return $share->snapshot_data;
        }
        $movie = $resource->get('movie');
        if (!is_array($movie)) {
            $movie = $this->findMovie($resource->id, $resource->data) ?? [];
        }
        $normalized = [
            'id' => $movie['id'] ?? $resource->id,
            'title' => $this->localized($movie['title'] ?? $resource->getTitle()),
            'year' => $movie['year'] ?? ($movie['release_year'] ?? ''),
            'genre' => $this->localizedList($movie['genre'] ?? ($movie['genres'] ?? '')),
            'rating' => $movie['overall_rating'] ?? ($movie['rating'] ?? null),
            'description' => $this->localized($movie['description'] ?? ($movie['plot'] ?? ($movie['overview'] ?? ''))),
            'director' => $this->localized($movie['director'] ?? ''),
            'cast' => $this->localizedList($movie['cast'] ?? ($movie['actors'] ?? '')),
            'duration' => $movie['duration'] ?? ($movie['runtime'] ?? ''),
            'trailer' => $movie['trailer'] ?? ($movie['trailer_url'] ?? ''),
            'poster' => $movie['poster'] ?? ($movie['poster_url'] ?? ($movie['image'] ?? '')),
            'data_mode' => $share->dataMode(),
            'share_id' => $share->id,
            'permissions' => $share->permissions,
        ];
        return ['film' => $normalized];
    }

    public function getContent(Share $share, Resource $resource, string $path): ?array
    {
        if ($path !== 'poster') {
            return null;
        }
        $movie = $resource->get('movie');
        if (!is_array($movie)) {
            return null;
        }
        $poster = (string)($movie['poster'] ?? ($movie['poster_url'] ?? ($movie['image'] ?? '')));
        if ($poster === '') {
            return null;
        }
        if (str_starts_with($poster, 'data:')) {
            return ['data_uri' => $poster];
        }
        $base = $this->filmsDir();
        $candidates = [
            $base . '/uploads/' . basename($poster),
            $base . '/' . ltrim($poster, '/'),
            dirname(__DIR__, 3) . '/apps/films/uploads/' . basename($poster),
            dirname(__DIR__, 3) . '/apps/films/' . ltrim($poster, '/'),
        ];
        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return ['path' => $candidate, 'mime' => sp_file_mime($candidate, basename($candidate)), 'name' => basename($candidate)];
            }
        }
        return null;
    }

    private function filmsDir(): string
    {
        $helper = dirname(__DIR__, 3) . '/storage_helper.php';
        if (is_file($helper)) {
            require_once $helper;
            if (function_exists('appdata_app_base_dir')) {
                try {
                    return rtrim(appdata_app_base_dir('films'), '/');
                } catch (Throwable $e) {
                }
            }
        }
        return dirname(__DIR__, 3) . '/apps/films';
    }

    private function findMovie(string $id, array $data): ?array
    {
        $movies = [];
        foreach ([$this->filmsDir() . '/movies.json', dirname(__DIR__, 3) . '/apps/films/movies.json'] as $file) {
            if (is_file($file)) {
                $decoded = json_decode((string)file_get_contents($file), true);
                if (is_array($decoded)) {
                    $movies = $decoded;
                    break;
                }
            }
        }
        if (isset($movies['movies']) && is_array($movies['movies'])) {
            $movies = $movies['movies'];
        }
        if (!array_is_list($movies)) {
            $list = [];
            foreach ($movies as $key => $movie) {
                if (is_array($movie) && !isset($movie['id'])) {
                    $movie['id'] = (string)$key;
                }
                $list[] = $movie;
            }
            $movies = $list;
        }
        foreach ($movies as $movie) {
            if (!is_array($movie)) continue;
            if ((string)($movie['id'] ?? '') === $id || (string)($movie['file_path'] ?? '') === $id) {
                return $movie;
            }
        }
        if (!empty($data['movie']) && is_array($data['movie'])) {
            return $data['movie'];
        }
        return null;
    }

    private function localized(mixed $value): string
    {
        if (is_array($value)) {
            $lang = sp_lang();
            return (string)($value[$lang] ?? $value['en'] ?? reset($value) ?? '');
        }
        return (string)$value;
    }

    private function localizedList(mixed $value): string
    {
        if (is_array($value)) {
            $out = [];
            foreach ($value as $item) {
                $out[] = is_array($item) ? (string)($item['name'] ?? reset($item) ?? '') : (string)$item;
            }
            return implode(', ', array_filter($out, static fn(string $item): bool => $item !== ''));
        }
        return (string)$value;
    }

    protected function getRendererClass(): ?string
    {
        return FilmRenderer::class;
    }
}
