<?php if (empty($users)): ?>
    <div class="alert kb-perm-empty"><?= t('No specific user has been allowed.') ?></div>
<?php else: ?>
    <table class="table-scrolling kb-perm-table">
        <tr>
            <th class="column-50"><?= t('User') ?></th>
            <th><?= t('Role') ?></th>
            <?php if ($project['is_private'] == 0): ?>
                <th class="column-15"><?= t('Actions') ?></th>
            <?php endif ?>
        </tr>
        <?php foreach ($users as $user): ?>
            <tr>
                <td>
                    <div class="kb-perm-user">
                        <strong><?= $this->text->e($user['name'] ?: $user['username']) ?></strong>
                        <?php if (!empty($user['email'])): ?>
                            <span class="kb-perm-email"><?= $this->text->e($user['email']) ?></span>
                        <?php endif ?>
                    </div>
                </td>
                <td>
                    <?= $this->app->component('project-select-role', array(
                        'roles' => $roles,
                        'role' => $user['role'],
                        'id' => $user['id'],
                        'ariaLabel' => t('Role'),
                        'url' => $this->url->to('ProjectPermissionController', 'changeUserRole', array('project_id' => $project['id'], 'csrf_token' => $this->app->getToken()->getReusableCSRFToken())),
                    )) ?>
                </td>
                <td>
                    <?php if (! $this->user->isCurrentUser($user['id'])): ?>
                        <?= $this->url->icon('trash-o', t('Remove'), 'ProjectPermissionController', 'removeUser', array('project_id' => $project['id'], 'user_id' => $user['id']), true) ?>
                    <?php endif ?>
                </td>
            </tr>
        <?php endforeach ?>
    </table>
<?php endif ?>

<?php if ($project['is_private'] == 0): ?>
    <div class="panel kb-perm-add">
        <form method="post" action="<?= $this->url->href('ProjectMemberController', 'add', array('plugin' => 'AppDashboard', 'project_id' => $project['id'])) ?>" autocomplete="off" class="form-inline kb-member-add-form">
            <?= $this->form->csrf() ?>
            <?= $this->form->hidden('user_id', $values) ?>
            <?= $this->form->hidden('username', $values) ?>
            <?= $this->form->hidden('email', $values) ?>
            <?= $this->form->hidden('display_name', $values) ?>
            <?= $this->form->hidden('external_id', $values) ?>
            <?= $this->form->hidden('external_id_column', $values) ?>

            <?= $this->form->label(t('Email'), 'name') ?>
            <?= $this->form->text('name', $values, $errors, array(
                    'required',
                    'placeholder="'.t('Type email to find user...').'"',
                    'title="'.t('Type email to find user...').'"',
                    'data-dst-field="user_id"',
                    'data-dst-extra-fields="external_id,external_id_column,username,email,display_name"',
                    'data-search-url="'.$this->url->href('ProjectMemberController', 'search', array('plugin' => 'AppDashboard')).'"',
                ),
                'autocomplete kb-member-email') ?>

            <?= $this->form->select('role', $roles, $values, $errors, array('aria-label="'.t('Role').'"')) ?>

            <button type="submit" class="btn btn-blue"><?= t('Add') ?></button>
        </form>
        <p class="form-help"><?= t('Start typing an email address. Users appear only after you type.') ?></p>
    </div>
<?php endif ?>
