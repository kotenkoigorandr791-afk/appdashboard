<?php

namespace Kanboard\Plugin\AppDashboard\Helper;

use Kanboard\Core\Base;

class MobilePairingHelper extends Base
{
    /**
     * Active Android pairing tokens for the signed-in account.
     *
     * @return array<int, array{label: string, platform: string, created_at: int, last_used_at: int, expires_at: int}>
     */
    public function tokens(): array
    {
        $bridge = function_exists('kanboard_bridge_user') ? kanboard_bridge_user() : null;
        $email = strtolower(trim((string) ($bridge['email'] ?? ($_SESSION['user']['email'] ?? ''))));
        if ($email === '') {
            return array();
        }

        try {
            $pdo = $this->db->getConnection();
            $stmt = $pdo->prepare(
                'SELECT device_name, platform, created_at, last_used_at, expires_at
                 FROM public.client_auth_tokens
                 WHERE email = :email AND app_id = :app AND revoked = 0 AND expires_at > :now
                 ORDER BY last_used_at DESC
                 LIMIT 10'
            );
            $stmt->bindValue(':email', $email, \PDO::PARAM_STR);
            $stmt->bindValue(':app', 'kanboard', \PDO::PARAM_STR);
            $stmt->bindValue(':now', time(), \PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return array();
        }

        $out = array();
        foreach ($rows as $row) {
            $platform = trim((string) ($row['platform'] ?? ''));
            $device = trim((string) ($row['device_name'] ?? ''));
            $out[] = array(
                'label' => $device !== '' ? $device : ($platform !== '' ? $platform : 'Android phone'),
                'platform' => $platform,
                'created_at' => (int) ($row['created_at'] ?? 0),
                'last_used_at' => (int) ($row['last_used_at'] ?? 0),
                'expires_at' => (int) ($row['expires_at'] ?? 0),
            );
        }
        return $out;
    }

    public function hasTokens(): bool
    {
        return count($this->tokens()) > 0;
    }
}
