<?php

namespace Kanboard\Plugin\AppDashboard\User;

use Kanboard\Core\Security\Role;
use Kanboard\Core\User\UserProviderInterface;

class ShellDirectoryUserProvider implements UserProviderInterface
{
    private array $user;

    public function __construct(array $user)
    {
        $this->user = $user;
    }

    public function isUserCreationAllowed()
    {
        return empty($this->user['id']);
    }

    public function getInternalId()
    {
        return isset($this->user['id']) ? (int) $this->user['id'] : 0;
    }

    public function getExternalIdColumn()
    {
        return 'username';
    }

    public function getExternalId()
    {
        return (string) ($this->user['username'] ?? '');
    }

    public function getRole()
    {
        return Role::APP_USER;
    }

    public function getUsername()
    {
        return (string) ($this->user['username'] ?? '');
    }

    public function getName()
    {
        return (string) ($this->user['name'] ?? $this->getUsername());
    }

    public function getEmail()
    {
        return (string) ($this->user['email'] ?? '');
    }

    public function getExternalGroupIds()
    {
        return [];
    }

    public function getExtraAttributes()
    {
        return [
            'email' => $this->getEmail(),
            'is_ldap_user' => 1,
            'disable_login_form' => 1,
            'notifications_enabled' => 1,
        ];
    }
}
