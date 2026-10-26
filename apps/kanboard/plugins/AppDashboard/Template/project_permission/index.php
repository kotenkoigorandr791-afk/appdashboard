<div class="page-header kb-perm-header">
    <h2><?= t('Allowed Users') ?></h2>
</div>

<?= $this->render('project_permission/users', array(
    'project' => $project,
    'roles' => $roles,
    'users' => $users,
    'errors' => $errors,
    'values' => $values,
)) ?>
