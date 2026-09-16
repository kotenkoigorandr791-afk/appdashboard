<section id="main" class="kb-modal-panel">
    <div class="page-header kb-modal-header">
        <h2><?= $title ?></h2>
    </div>
    <form id="project-creation-form" class="kb-modal-form" method="post" action="<?= $this->url->href('ProjectCreationController', 'save') ?>" autocomplete="off">
        <?= $this->form->csrf() ?>
        <?= $this->form->hidden('is_private', $values) ?>
        <div class="kb-field">
            <?= $this->form->label(t('Name'), 'name') ?>
            <?= $this->form->text('name', $values, $errors, array('autofocus', 'required', 'placeholder="'.t('Project name').'"')) ?>
        </div>
        <div class="kb-field">
            <?= $this->form->label(t('Identifier'), 'identifier') ?>
            <?= $this->form->text('identifier', $values, $errors, array('placeholder="MYPROJECT"')) ?>
            <p class="form-help"><?= t('The project identifier is optional and must be alphanumeric, example: MYPROJECT.') ?></p>
        </div>
        <div class="kb-field kb-field-check">
            <?= $this->form->checkbox('per_swimlane_task_limits', t('Column task limits apply to each swimlane individually'), 1, false) ?>
        </div>
        <div class="kb-field kb-field-inline">
            <?= $this->form->label(t('Task limit'), 'task_limit') ?>
            <?= $this->form->number('task_limit', $values, $errors, array('min="0"', 'placeholder="0"')) ?>
        </div>
        <?php if (count($projects_list) > 1): ?>
            <div class="kb-field">
                <?= $this->form->label(t('Create from another project'), 'src_project_id') ?>
                <?= $this->form->select('src_project_id', $projects_list, $values, array(), array(), 'js-project-creation-select-options') ?>
            </div>
        <?php endif ?>
        <div class="js-project-creation-options kb-dup-options" <?= isset($values['src_project_id']) && $values['src_project_id'] > 0 ? '' : 'style="display: none"' ?>>
            <p class="alert"><?= t('Which parts of the project do you want to duplicate?') ?></p>
            <div class="kb-check-grid">
                <?php if (! $is_private): ?>
                    <?= $this->form->checkbox('projectPermissionModel', t('Permissions'), 1, true) ?>
                    <?= $this->form->checkbox('projectRoleModel', t('Custom roles'), 1, true) ?>
                <?php endif ?>
                <?= $this->form->checkbox('categoryModel', t('Categories'), 1, true) ?>
                <?= $this->form->checkbox('tagDuplicationModel', t('Tags'), 1, true) ?>
                <?= $this->form->checkbox('actionModel', t('Actions'), 1, true) ?>
                <?= $this->form->checkbox('customFilterModel', t('Custom filters'), 1, true) ?>
                <?= $this->form->checkbox('projectMetadataModel', t('Metadata'), 1, false) ?>
                <?= $this->form->checkbox('projectTaskDuplicationModel', t('Tasks'), 1, false) ?>
            </div>
        </div>
        <?= $this->hook->render('template:project:creation:form', array('values' => $values, 'errors' => $errors)) ?>
        <?= $this->modal->submitButtons() ?>
    </form>
    <?php if ($is_private): ?>
    <div class="alert alert-info">
        <p><?= t('There is no user management for personal projects.') ?></p>
    </div>
    <?php endif ?>
</section>
