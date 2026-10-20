<?php
final class ShareRepository
{
    public function findByToken(string $token): ?Share
    {
        if (!ShareTokenService::isValidFormat($token)) {
            return null;
        }
        $db = getDB();
        $hash = ShareTokenService::hash($token);
        $stmt = $db->prepare('SELECT * FROM shares WHERE token_hash = :hash LIMIT 1');
        $stmt->bindValue(':hash', $hash, PDO::PARAM_STR);
        $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            // Legacy rows are migrated with token = share_id and an empty hash.
            $stmt = $db->prepare('SELECT * FROM shares WHERE token = :token LIMIT 1');
            $stmt->bindValue(':token', $token, PDO::PARAM_STR);
            $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
            if ($row && empty($row['token_hash'])) {
                try {
                    $upd = $db->prepare('UPDATE shares SET token_hash = :hash WHERE id = :id');
                    $upd->bindValue(':hash', $hash, PDO::PARAM_STR);
                    $upd->bindValue(':id', (int)$row['id'], PDO::PARAM_INT);
                    $upd->execute();
                    $row['token_hash'] = $hash;
                } catch (Throwable $e) {
                }
            }
        }
        return $row ? Share::fromRow($row) : null;
    }

    public function findById(int $id): ?Share
    {
        $stmt = getDB()->prepare('SELECT * FROM shares WHERE id = :id LIMIT 1');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
        return $row ? Share::fromRow($row) : null;
    }

    public function findByOwner(string $email, array $filters = []): array
    {
        return $this->query(['owner_email' => strtolower($email)], $filters);
    }

    public function all(array $filters = []): array
    {
        return $this->query([], $filters);
    }

    private function query(array $baseWhere, array $filters): array
    {
        $db = getDB();
        $where = [];
        $params = [];
        foreach ($baseWhere as $column => $value) {
            $where[] = $column . ' = :' . $column;
            $params[':' . $column] = [$value, PDO::PARAM_STR];
        }
        if (!empty($filters['resource_app']) && $filters['resource_app'] !== 'all') {
            $where[] = 'resource_app = :resource_app';
            $params[':resource_app'] = [(string)$filters['resource_app'], PDO::PARAM_STR];
        }
        if (!empty($filters['resource_type']) && $filters['resource_type'] !== 'all') {
            $where[] = 'resource_type = :resource_type';
            $params[':resource_type'] = [(string)$filters['resource_type'], PDO::PARAM_STR];
        }
        if (!empty($filters['entity_type']) && $filters['entity_type'] !== 'all') {
            $where[] = 'entity_type = :entity_type';
            $params[':entity_type'] = [(string)$filters['entity_type'], PDO::PARAM_STR];
        }
        if (!empty($filters['status'])) {
            $status = (string)$filters['status'];
            if ($status === 'active') {
                $where[] = 'is_active = TRUE AND revoked_at IS NULL AND (expires_at IS NULL OR expires_at > NOW())';
            } elseif ($status === 'expired') {
                $where[] = 'expires_at IS NOT NULL AND expires_at <= NOW() AND revoked_at IS NULL';
            } elseif ($status === 'revoked') {
                $where[] = '(revoked_at IS NOT NULL OR is_active = FALSE)';
            }
        }
        $sql = 'SELECT * FROM shares' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY created_at DESC LIMIT 500';
        $stmt = $db->prepare($sql);
        foreach ($params as $name => [$value, $type]) {
            $stmt->bindValue($name, $value, $type);
        }
        $out = [];
        $result = $stmt->execute();
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            $out[] = Share::fromRow($row);
        }
        return $out;
    }

    public function create(Share $share): Share
    {
        $db = getDB();
        $stmt = $db->prepare(
            'INSERT INTO shares (token, token_hash, resource_type, entity_type, resource_id, resource_app,
                resource_data, title, description, owner_email, access_mode, permissions, password_hash,
                expires_at, expires_option, snapshot_data, snapshot_created_at, max_views, is_active)
             VALUES (:token, :token_hash, :resource_type, :entity_type, :resource_id, :resource_app,
                CAST(:resource_data AS jsonb), :title, :description, :owner_email, :access_mode,
                CAST(:permissions AS jsonb), :password_hash, :expires_at, :expires_option,
                CAST(:snapshot_data AS jsonb), :snapshot_created_at, :max_views, TRUE)
             RETURNING *'
        );
        $stmt->bindValue(':token', $share->token, PDO::PARAM_STR);
        $stmt->bindValue(':token_hash', $share->token_hash, PDO::PARAM_STR);
        $stmt->bindValue(':resource_type', $share->resource_type, PDO::PARAM_STR);
        $stmt->bindValue(':entity_type', $share->entity_type, PDO::PARAM_STR);
        $stmt->bindValue(':resource_id', $share->resource_id, PDO::PARAM_STR);
        $stmt->bindValue(':resource_app', $share->resource_app, PDO::PARAM_STR);
        $stmt->bindValue(':resource_data', json_encode($share->resource_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PDO::PARAM_STR);
        $stmt->bindValue(':title', $share->title, PDO::PARAM_STR);
        $stmt->bindValue(':description', $share->description, PDO::PARAM_STR);
        $stmt->bindValue(':owner_email', $share->owner_email, PDO::PARAM_STR);
        $stmt->bindValue(':access_mode', $share->access_mode, PDO::PARAM_STR);
        $stmt->bindValue(':permissions', json_encode($share->permissions), PDO::PARAM_STR);
        $stmt->bindValue(':password_hash', $share->password_hash, $share->password_hash === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':expires_at', $share->expires_at, $share->expires_at === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':expires_option', $share->expires_option, PDO::PARAM_STR);
        $stmt->bindValue(':snapshot_data', $share->snapshot_data === null ? null : json_encode($share->snapshot_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $share->snapshot_data === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':snapshot_created_at', $share->snapshot_created_at, $share->snapshot_created_at === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':max_views', $share->max_views, PDO::PARAM_INT);
        $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
        return $row ? Share::fromRow($row) : $share;
    }

    public function update(Share $share): void
    {
        $stmt = getDB()->prepare(
            'UPDATE shares SET title = :title, description = :description, permissions = CAST(:permissions AS jsonb),
                expires_at = :expires_at, expires_option = :expires_option,
                snapshot_data = CAST(:snapshot_data AS jsonb), snapshot_created_at = :snapshot_created_at
             WHERE id = :id'
        );
        $stmt->bindValue(':title', $share->title, PDO::PARAM_STR);
        $stmt->bindValue(':description', $share->description, PDO::PARAM_STR);
        $stmt->bindValue(':permissions', json_encode($share->permissions), PDO::PARAM_STR);
        $stmt->bindValue(':expires_at', $share->expires_at, $share->expires_at === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':expires_option', $share->expires_option, PDO::PARAM_STR);
        $stmt->bindValue(':snapshot_data', $share->snapshot_data === null ? null : json_encode($share->snapshot_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $share->snapshot_data === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':snapshot_created_at', $share->snapshot_created_at, $share->snapshot_created_at === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':id', $share->id, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function revoke(int $id): void
    {
        $stmt = getDB()->prepare('UPDATE shares SET is_active = FALSE, revoked_at = NOW() WHERE id = :id');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function delete(int $id): void
    {
        $stmt = getDB()->prepare('DELETE FROM shares WHERE id = :id');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function deleteByToken(string $token): void
    {
        $stmt = getDB()->prepare('DELETE FROM shares WHERE token = :token');
        $stmt->bindValue(':token', $token, PDO::PARAM_STR);
        $stmt->execute();
    }

    public function bulkRevoke(array $ids): int
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if ($ids === []) return 0;
        $in = implode(',', $ids);
        return (int)getDB()->exec("UPDATE shares SET is_active = FALSE, revoked_at = NOW() WHERE id IN ($in)");
    }

    public function bulkDelete(array $ids): int
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if ($ids === []) return 0;
        $in = implode(',', $ids);
        return (int)getDB()->exec("DELETE FROM shares WHERE id IN ($in)");
    }

    public function deleteByApp(string $app): int
    {
        $stmt = getDB()->prepare('DELETE FROM shares WHERE resource_app = :app');
        $stmt->bindValue(':app', $app, PDO::PARAM_STR);
        $stmt->execute();
        return (int)getDB()->changes();
    }

    public function incrementViewCount(int $id): int
    {
        $stmt = getDB()->prepare('UPDATE shares SET view_count = view_count + 1 WHERE id = :id RETURNING view_count');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
        return (int)($row['view_count'] ?? 0);
    }

    public function cleanupExpired(int $graceDays = 30): int
    {
        $stmt = getDB()->prepare(
            "DELETE FROM shares WHERE (expires_at IS NOT NULL AND expires_at < NOW() - (:days || ' days')::interval)
             OR (revoked_at IS NOT NULL AND revoked_at < NOW() - (:days || ' days')::interval)"
        );
        $stmt->bindValue(':days', (string)max(1, $graceDays), PDO::PARAM_STR);
        $stmt->execute();
        return (int)getDB()->changes();
    }

    public function stats(): array
    {
        $row = getDB()->query(
            "SELECT COUNT(*) AS total,
                    COUNT(*) FILTER (WHERE is_active = TRUE AND revoked_at IS NULL AND (expires_at IS NULL OR expires_at > NOW())) AS active,
                    COUNT(*) FILTER (WHERE expires_at IS NOT NULL AND expires_at <= NOW() AND revoked_at IS NULL) AS expired,
                    COUNT(*) FILTER (WHERE revoked_at IS NOT NULL OR is_active = FALSE) AS revoked
             FROM shares"
        )->fetch(PDO::FETCH_ASSOC);
        return [
            'total' => (int)($row['total'] ?? 0),
            'active' => (int)($row['active'] ?? 0),
            'expired' => (int)($row['expired'] ?? 0),
            'revoked' => (int)($row['revoked'] ?? 0),
        ];
    }
}
