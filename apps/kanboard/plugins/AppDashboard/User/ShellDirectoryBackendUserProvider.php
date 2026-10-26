<?php

namespace Kanboard\Plugin\AppDashboard\User;

use Kanboard\Core\Base;
use Kanboard\Core\User\UserBackendProviderInterface;
use Kanboard\Model\UserModel;

class ShellDirectoryBackendUserProvider extends Base implements UserBackendProviderInterface
{
    public function find($input)
    {
        $q = trim((string) $input);
        if (mb_strlen($q) < 2) {
            return [];
        }

        $shellUsers = $this->searchShellUsers($q);
        $kbByEmail = $this->searchKanboardUsersByEmail($q);
        $merged = [];

        foreach ($kbByEmail as $row) {
            $email = strtolower((string) ($row['email'] ?? ''));
            if ($email === '') {
                continue;
            }
            $merged[$email] = [
                'id' => (int) $row['id'],
                'username' => (string) $row['username'],
                'name' => (string) (($row['name'] ?? '') ?: $row['username']),
                'email' => (string) $row['email'],
            ];
        }

        foreach ($shellUsers as $row) {
            $email = strtolower((string) ($row['email'] ?? ''));
            if ($email === '' || isset($merged[$email])) {
                continue;
            }
            $username = $this->usernameFromEmail($email);
            $merged[$email] = [
                'id' => 0,
                'username' => $username,
                'name' => (string) (($row['name'] ?? '') !== '' ? $row['name'] : $username),
                'email' => (string) $row['email'],
            ];
        }

        $result = [];
        foreach ($merged as $user) {
            $result[] = new ShellDirectoryUserProvider($user);
        }
        return $result;
    }

    private function searchShellUsers(string $q): array
    {
        try {
            require_once dirname(__DIR__, 5) . '/lib/app_db.php';
            $db = app_main_db();
            // Prefer email prefix/contains; reuse existing lower(email) indexes.
            $st = $db->prepare(
                'SELECT email, name FROM public.users
                 WHERE lower(email) LIKE lower(:q)
                    OR (position(\'@\' in :raw) = 0 AND lower(name) LIKE lower(:q))
                 ORDER BY
                   CASE WHEN lower(email) LIKE lower(:prefix) THEN 0 ELSE 1 END,
                   email ASC
                 LIMIT 12'
            );
            $st->bindValue(':q', '%'.$q.'%');
            $st->bindValue(':raw', $q);
            $st->bindValue(':prefix', $q.'%');
            $res = $st->execute();
            $rows = [];
            while ($row = $res->fetch(\PDO::FETCH_ASSOC)) {
                if (is_array($row)) {
                    $rows[] = $row;
                }
            }
            return $rows;
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function searchKanboardUsersByEmail(string $q): array
    {
        return $this->db->table(UserModel::TABLE)
            ->columns(UserModel::TABLE.'.id', UserModel::TABLE.'.username', UserModel::TABLE.'.name', UserModel::TABLE.'.email')
            ->eq(UserModel::TABLE.'.is_active', 1)
            ->beginOr()
            ->ilike(UserModel::TABLE.'.email', '%'.$q.'%')
            ->ilike(UserModel::TABLE.'.username', '%'.$q.'%')
            ->ilike(UserModel::TABLE.'.name', '%'.$q.'%')
            ->closeOr()
            ->asc(UserModel::TABLE.'.email')
            ->limit(12)
            ->findAll();
    }

    private function usernameFromEmail(string $email): string
    {
        $local = explode('@', $email)[0] ?? 'user';
        $safe = preg_replace('/[^a-zA-Z0-9._-]+/', '_', $local) ?: 'user';
        return mb_substr($safe, 0, 50);
    }
}
