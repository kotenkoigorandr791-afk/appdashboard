<?php

namespace Kanboard\Plugin\AppDashboard;

use Kanboard\Core\Plugin\Base;
use Kanboard\Core\Security\AuthenticationManager;
use Kanboard\Core\Security\Role;
use Kanboard\Notification\WebNotification;
use Kanboard\Plugin\AppDashboard\Auth\DashboardAuth;
use Kanboard\Plugin\AppDashboard\Notification\ShellNotification;
use Kanboard\Plugin\AppDashboard\User\Avatar\ShellAvatarProvider;

class Plugin extends Base
{
    public function initialize()
    {
        $this->authenticationManager->register(new DashboardAuth($this->container));
        $this->avatarManager->register(new ShellAvatarProvider($this->container));
        $this->userNotificationTypeModel->setType(
            WebNotification::TYPE,
            'Shell',
            ShellNotification::class,
            true
        );

        $this->template->setTemplateOverride('layout', 'appDashboard:layout');
        $this->template->setTemplateOverride('header/title', 'appDashboard:header/title');
        $this->template->setTemplateOverride('header/user_dropdown', 'appDashboard:header/user_dropdown');
        $this->template->setTemplateOverride('header/user_notifications', 'appDashboard:header/user_notifications');
        $this->template->setTemplateOverride('header/board_selector', 'appDashboard:header/board_selector');
        $this->template->setTemplateOverride('header/creation_dropdown', 'appDashboard:header/creation_dropdown');
        $this->template->setTemplateOverride('user_view/sidebar', 'appDashboard:user_view/sidebar');
        $this->template->setTemplateOverride('user_view/show', 'appDashboard:user_view/show');
        $this->template->setTemplateOverride('dashboard/overview', 'appDashboard:dashboard/overview');
        $this->template->setTemplateOverride('project/dropdown', 'appDashboard:project/dropdown');
        $this->template->setTemplateOverride('project_header/dropdown', 'appDashboard:project_header/dropdown');
        $this->template->setTemplateOverride('project_header/search', 'appDashboard:project_header/search');
        $this->template->setTemplateOverride('project_list/project_title', 'appDashboard:project_list/project_title');
        $this->template->setTemplateOverride('project_list/sort_menu', 'appDashboard:project_list/sort_menu');
        $this->template->setTemplateOverride('app/filters_helper', 'appDashboard:app/filters_helper');
        $this->template->setTemplateOverride('project_creation/create', 'appDashboard:project_creation/create');
        $this->template->setTemplateOverride('project_view/show', 'appDashboard:project_view/show');
        $this->template->setTemplateOverride('project/sidebar', 'appDashboard:project/sidebar');

        $this->hook->on('template:layout:css', ['template' => 'plugins/AppDashboard/Asset/shell.css']);
        $this->hook->on('template:layout:js', ['template' => 'plugins/AppDashboard/Asset/shell.js']);
        $this->hook->on('template:layout:head', ['template' => 'appDashboard:layout/head']);

        $admin = Role::APP_ADMIN;
        $this->applicationAccessMap->add('UserModificationController', '*', $admin);
        $this->applicationAccessMap->add('UserApiAccessController', '*', $admin);
        $this->applicationAccessMap->add('UserCredentialController', '*', $admin);
        $this->applicationAccessMap->add('TwoFactorController', '*', $admin);
        $this->applicationAccessMap->add('AvatarFileController', ['upload', 'remove'], $admin);
        $this->applicationAccessMap->add('WebNotificationController', '*', $admin);
        $this->applicationAccessMap->add('UserViewController', [
            'lastLogin',
            'sessions',
            'removeSession',
            'passwordReset',
            'notifications',
            'integrations',
            'external',
            'timesheet',
        ], $admin);

        $this->dispatcher->addListener(AuthenticationManager::EVENT_SUCCESS, function () {
            $this->syncShellPreferences(true);
            $this->enableShellNotifications();
        });
    }

    private function enableShellNotifications(): void
    {
        if (!$this->userSession->isLogged()) {
            return;
        }
        $userId = (int)$this->userSession->getId();
        if ($userId <= 0) {
            return;
        }
        $this->userModel->update([
            'id' => $userId,
            'notifications_enabled' => 1,
        ]);
        $types = $this->userNotificationTypeModel->getSelectedTypes($userId);
        if (!in_array(WebNotification::TYPE, $types, true)) {
            $types[] = WebNotification::TYPE;
            $this->userNotificationTypeModel->saveSelectedTypes($userId, $types);
        }
    }

    private function syncShellPreferences(bool $persist = false): void
    {
        if (!$this->userSession->isLogged()) {
            return;
        }
        $locale = (string)($GLOBALS['APPDASHBOARD_KB_LOCALE'] ?? '');
        $theme = (string)($GLOBALS['APPDASHBOARD_KB_THEME'] ?? '');
        $user = session_get('user');
        if (!is_array($user)) {
            return;
        }
        $changed = false;
        if ($locale !== '' && ($user['language'] ?? '') !== $locale) {
            $user['language'] = $locale;
            $changed = true;
        }
        if ($theme === 'device') {
            $theme = 'auto';
        }
        if (in_array($theme, ['light', 'dark', 'auto'], true) && ($user['theme'] ?? '') !== $theme) {
            $user['theme'] = $theme;
            $changed = true;
        }
        if (!$changed) {
            return;
        }
        session_set('user', $user);
        if ($persist && !empty($user['id'])) {
            $this->userModel->update([
                'id' => (int)$user['id'],
                'language' => $user['language'] ?? 'en_US',
                'theme' => $user['theme'] ?? 'light',
            ]);
        }
    }

    public function getPluginName()
    {
        return 'AppDashboard';
    }

    public function getPluginDescription()
    {
        return 'Shell auth, theme and UI integration';
    }

    public function getPluginAuthor()
    {
        return 'AppDashboard';
    }

    public function getPluginVersion()
    {
        return '1.1.0';
    }

    public function getCompatibleVersion()
    {
        return '>=1.2.20';
    }
}
