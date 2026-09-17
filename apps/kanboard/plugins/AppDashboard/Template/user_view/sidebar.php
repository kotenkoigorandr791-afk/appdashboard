<div class="sidebar">
    <div class="sidebar-title">
        <h2><?= t('Information') ?></h2>
    </div>
    <ul>
        <li <?= $this->app->checkMenuSelection('UserViewController', 'show') ?>>
            <?= $this->url->link(t('Summary'), 'UserViewController', 'show', array('user_id' => $user['id'])) ?>
        </li>
        <li>
            <?= $this->url->link(t('User dashboard'), 'DashboardController', 'show', array('user_id' => $user['id'])) ?>
        </li>
        <?php if ($this->user->hasAccess('UserViewController', 'timesheet')): ?>
            <li <?= $this->app->checkMenuSelection('UserViewController', 'timesheet') ?>>
                <?= $this->url->link(t('Time tracking'), 'UserViewController', 'timesheet', array('user_id' => $user['id'])) ?>
            </li>
        <?php endif ?>
        <?= $this->hook->render('template:user:sidebar:information', array('user' => $user)) ?>
    </ul>
</div>
