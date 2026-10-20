<?php
final class ShareTokenService
{
    public const TOKEN_BYTES = 32; // 256 bits of entropy -> 43 base64url chars
    public const HASH_ALGO = 'sha256';

    public static function generate(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(self::TOKEN_BYTES)), '+/', '-_'), '=');
    }

    public static function hash(string $token): string
    {
        return hash(self::HASH_ALGO, $token);
    }

    public static function verify(string $token, string $hash): bool
    {
        return $hash !== '' && hash_equals($hash, self::hash($token));
    }

    public static function isValidFormat(string $token): bool
    {
        return (bool)preg_match('/^[A-Za-z0-9_-]{16,128}$/', $token);
    }

    /** @return array{0:string,1:?string} [normalised option, SQL timestamp] */
    public static function resolveExpiration(string $option, ?int $from = null): array
    {
        $map = [
            'never' => null,
            '1h' => 3600,
            '1d' => 86400,
            '1w' => 7 * 86400,
            '1m' => 30 * 86400,
            '3m' => 90 * 86400,
            '6m' => 180 * 86400,
            '1y' => 365 * 86400,
        ];
        $option = array_key_exists($option, $map) ? $option : '1m';
        $ttl = $map[$option];
        $timestamp = $ttl === null ? null : date('Y-m-d H:i:sP', ($from ?? time()) + $ttl);
        return [$option, $timestamp];
    }

    public static function isRateLimited(string $tokenHash, string $ip): bool
    {
        try {
            $db = getDB();
            $since = time() - 3600;
            $stmt = $db->prepare('SELECT COUNT(*) AS c FROM share_unlock_attempts WHERE token_hash = :h AND ip = :ip AND attempted_at >= :since');
            $stmt->bindValue(':h', $tokenHash, PDO::PARAM_STR);
            $stmt->bindValue(':ip', $ip, PDO::PARAM_STR);
            $stmt->bindValue(':since', $since, PDO::PARAM_INT);
            $row = $stmt->execute()->fetch(PDO::FETCH_ASSOC);
            return (int)($row['c'] ?? 0) >= 5;
        } catch (Throwable $e) {
            return false;
        }
    }

    public static function recordFailedAttempt(string $tokenHash, string $ip): void
    {
        try {
            $db = getDB();
            $stmt = $db->prepare('INSERT INTO share_unlock_attempts (token_hash, ip, attempted_at) VALUES (:h, :ip, :at)');
            $stmt->bindValue(':h', $tokenHash, PDO::PARAM_STR);
            $stmt->bindValue(':ip', $ip, PDO::PARAM_STR);
            $stmt->bindValue(':at', time(), PDO::PARAM_INT);
            $stmt->execute();
            $stmt = $db->prepare('DELETE FROM share_unlock_attempts WHERE attempted_at < :cutoff');
            $stmt->bindValue(':cutoff', time() - 86400, PDO::PARAM_INT);
            $stmt->execute();
        } catch (Throwable $e) {
        }
    }

    public static function clearAttempts(string $tokenHash, string $ip): void
    {
        try {
            $db = getDB();
            $stmt = $db->prepare('DELETE FROM share_unlock_attempts WHERE token_hash = :h AND ip = :ip');
            $stmt->bindValue(':h', $tokenHash, PDO::PARAM_STR);
            $stmt->bindValue(':ip', $ip, PDO::PARAM_STR);
            $stmt->execute();
        } catch (Throwable $e) {
        }
    }
}
