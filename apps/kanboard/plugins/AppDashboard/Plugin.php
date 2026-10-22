<?php

namespace Kanboard\Plugin\AppDashboard;

use Kanboard\Core\Plugin\Base;
use Kanboard\Core\Security\AuthenticationManager;
use Kanboard\Core\Security\Role;
use Kanboard\Model\ColumnModel;
use Kanboard\Notification\WebNotification;
use Kanboard\Plugin\AppDashboard\Auth\DashboardAuth;
use Kanboard\Plugin\AppDashboard\Notification\ShellNotification;
use Kanboard\Plugin\AppDashboard\User\Avatar\ShellAvatarProvider;
use Kanboard\Plugin\AppDashboard\User\ShellDirectoryBackendUserProvider;

class Plugin extends Base
{
    public function initialize()
    {
        $this->authenticationManager->register(new DashboardAuth($this->container));
        $this->avatarManager->register(new ShellAvatarProvider($this->container));
        $this->userManager->register(new ShellDirectoryBackendUserProvider($this->container));
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
        $this->template->setTemplateOverride('dashboard/layout', 'appDashboard:dashboard/layout');
        $this->template->setTemplateOverride('project/dropdown', 'appDashboard:project/dropdown');
        $this->template->setTemplateOverride('project_header/header', 'appDashboard:project_header/header');
        $this->template->setTemplateOverride('project_header/dropdown', 'appDashboard:project_header/dropdown');
        $this->template->setTemplateOverride('project_header/search', 'appDashboard:project_header/search');
        $this->template->setTemplateOverride('project_list/project_title', 'appDashboard:project_list/project_title');
        $this->template->setTemplateOverride('project_list/sort_menu', 'appDashboard:project_list/sort_menu');
        $this->template->setTemplateOverride('app/filters_helper', 'appDashboard:app/filters_helper');
        $this->template->setTemplateOverride('project_creation/create', 'appDashboard:project_creation/create');
        $this->template->setTemplateOverride('project_view/show', 'appDashboard:project_view/show');
        $this->template->setTemplateOverride('project/sidebar', 'appDashboard:project/sidebar');
        $this->template->setTemplateOverride('project_overview/show', 'appDashboard:project_overview/show');
        $this->template->setTemplateOverride('task_list/task_title', 'appDashboard:task_list/task_title');
        $this->template->setTemplateOverride('task_list/task_details', 'appDashboard:task_list/task_details');
        $this->template->setTemplateOverride('board/view_private', 'appDashboard:board/view_private');
        $this->template->setTemplateOverride('task_creation/show', 'appDashboard:task_creation/show');
        $this->template->setTemplateOverride('task/show', 'appDashboard:task/show');
        $this->template->setTemplateOverride('task/sidebar', 'appDashboard:task/sidebar');
        $this->template->setTemplateOverride('project_permission/index', 'appDashboard:project_permission/index');
        $this->template->setTemplateOverride('project_permission/users', 'appDashboard:project_permission/users');
        $this->template->setTemplateOverride('analytic/burndown', 'appDashboard:analytic/burndown');
        $this->template->setTemplateOverride('analytic/cfd', 'appDashboard:analytic/cfd');
        $this->template->setTemplateOverride('analytic/lead_cycle_time', 'appDashboard:analytic/lead_cycle_time');
        $this->template->setTemplateOverride('analytic/task_distribution', 'appDashboard:analytic/task_distribution');
        $this->template->setTemplateOverride('search/index', 'appDashboard:search/index');
        $this->template->setTemplateOverride('search/activity', 'appDashboard:search/activity');
        $this->template->setTemplateOverride('activity/user', 'appDashboard:activity/user');
        $this->template->setTemplateOverride('task/details', 'appDashboard:task/details');

        $this->hook->on('template:layout:css', ['template' => 'plugins/AppDashboard/Asset/shell.css']);
        $this->hook->on('template:layout:css', ['template' => 'plugins/AppDashboard/Asset/circle.css']);
        $this->hook->on('template:layout:css', ['template' => 'plugins/AppDashboard/Asset/mobile-pairing.css']);
        $this->hook->on('template:layout:js', ['template' => 'plugins/AppDashboard/Asset/shell.js']);
        $this->hook->on('template:layout:js', ['template' => 'plugins/AppDashboard/Asset/circle-cycle.js']);
        $this->hook->on('template:layout:js', ['template' => 'plugins/AppDashboard/Asset/qr-generator.js']);
        $this->hook->on('template:layout:js', ['template' => 'plugins/AppDashboard/Asset/mobile-pairing.js']);
        $this->hook->on('template:layout:head', ['template' => 'appDashboard:layout/head']);
        $this->hook->on('template:layout:bottom', ['template' => 'appDashboard:mobile/pairing_modal']);

        $this->projectAccessMap->add('OverviewCalendarController', 'updateDates', Role::PROJECT_MEMBER);
        $this->projectAccessMap->add('TaskQuickUpdateController', 'updateField', Role::PROJECT_MEMBER);
        $this->projectAccessMap->add('ProjectMemberController', 'searchAssignable', Role::PROJECT_MEMBER);
        $this->projectAccessMap->add('ProjectMemberController', 'add', Role::PROJECT_MANAGER);
        $this->applicationAccessMap->add('ProjectMemberController', 'search', Role::APP_USER);
        $this->renameReadyColumns();
        $this->ensurePerformanceIndexes();

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
            $this->userSession->setPostAuthenticationAsValidated();
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
        return '1.5.1';
    }

    public function getCompatibleVersion()
    {
        return '>=1.2.20';
    }

    public function getHelpers()
    {
        return array(
            'Plugin\\AppDashboard\\Helper' => array(
                'OverviewCalendarHelper',
                'CircleCycleHelper',
                'ProjectTilesHelper',
                'MobilePairingHelper',
            ),
        );
    }

    private function renameReadyColumns(): void
    {
        if ($this->configModel->get('appdashboard_todo_column_migrated') === '1') {
            return;
        }
        $titles = array('Ready', 'Согласованные', 'Відібрані', 'Připraveno', 'Gotowe');
        try {
            foreach ($titles as $title) {
                $this->db->table(ColumnModel::TABLE)->eq('title', $title)->update(array('title' => 'todo'));
            }
            $this->configModel->save(array('appdashboard_todo_column_migrated' => '1'));
        } catch (\Throwable $e) {
        }
    }

    private function ensurePerformanceIndexes(): void
    {
        if ($this->configModel->get('appdashboard_perf_indexes_v2') === '1') {
            return;
        }
        $statements = [
            'CREATE INDEX IF NOT EXISTS users_email_lower_idx ON kanboard.users (lower(email))',
            'CREATE INDEX IF NOT EXISTS project_has_users_user_idx ON kanboard.project_has_users (user_id)',
            'CREATE INDEX IF NOT EXISTS tasks_owner_idx ON kanboard.tasks (owner_id)',
            'CREATE INDEX IF NOT EXISTS tasks_column_active_idx ON kanboard.tasks (column_id, is_active)',
            'CREATE INDEX IF NOT EXISTS tasks_swimlane_column_idx ON kanboard.tasks (swimlane_id, column_id)',
            'CREATE INDEX IF NOT EXISTS project_has_users_project_role_idx ON kanboard.project_has_users (project_id, role)',
        ];
        try {
            foreach ($statements as $sql) {
                $this->db->getConnection()->exec($sql);
            }
            $this->configModel->save(['appdashboard_perf_indexes_v2' => '1']);
        } catch (\Throwable $e) {
        }
    }
}
