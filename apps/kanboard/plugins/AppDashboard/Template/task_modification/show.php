<div class="kb-plane-create">
    <div class="kb-plane-create-head">
        <div class="kb-plane-head-text">
            <h2 class="kb-plane-create-title"><?= t('Edit task') ?></h2>
            <div class="kb-plane-crumbs" aria-label="<?= t('Breadcrumb') ?>">
                <span class="kb-plane-crumb kb-plane-crumb-project">
                    <svg viewBox="0 0 16 16" width="13" height="13" aria-hidden="true"><rect x="2.5" y="4" width="11" height="9" rx="1.5" fill="none" stroke="currentColor" stroke-width="1.3"/><path d="M5 4V3.2A1.2 1.2 0 0 1 6.2 2h3.6A1.2 1.2 0 0 1 11 3.2V4" fill="none" stroke="currentColor" stroke-width="1.3"/></svg>
                    <span><?= $this->text->e($project['name']) ?></span>
                </span>
                <span class="kb-plane-crumb-sep" aria-hidden="true">
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="m6 3 5 5-5 5"/></svg>
                </span>
                <span class="kb-plane-crumb"><span class="kb-plane-crumb-label">#<?= (int) $task['id'] ?></span></span>
            </div>
        </div>
        <button type="button" class="kb-plane-close" data-kb-plane-discard="1" aria-label="<?= t('Close') ?>" title="<?= t('Close') ?>">
            <svg viewBox="0 0 16 16" width="16" height="16" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><path d="m4 4 8 8"/><path d="m12 4-8 8"/></svg>
        </button>
    </div>
    <form method="post" action="<?= $this->url->href('TaskModificationController', 'update', array('task_id' => $task['id'])) ?>" autocomplete="off" class="kb-plane-form">
        <?= $this->form->csrf() ?>
        <div class="kb-plane-fields">
            <?= $this->task->renderTitleField($values, $errors) ?>
            <div class="kb-plane-desc">
                <?= $this->task->renderDescriptionField($values, $errors) ?>
            </div>
            <?= $this->hook->render('template:task:form:first-column', array('values' => $values, 'errors' => $errors)) ?>
        </div>
        <div class="kb-plane-props">
            <div class="kb-plane-prop"><?= $this->task->renderPriorityField($project, $values) ?></div>
            <div class="kb-plane-prop"><?= $this->task->renderAssigneeField($users_list, $values, $errors) ?></div>
            <div class="kb-plane-prop"><?= $this->task->renderColorField($values) ?></div>
            <?php if (!empty($categories_list) && count($categories_list) > 1): ?>
                <div class="kb-plane-prop"><?= $this->task->renderCategoryField($categories_list, $values, $errors) ?></div>
            <?php endif ?>
            <div class="kb-plane-prop kb-plane-prop-date"><?= $this->task->renderStartDateField($values, $errors) ?></div>
            <div class="kb-plane-prop kb-plane-prop-date"><?= $this->task->renderDueDateField($values, $errors) ?></div>
            <div class="kb-plane-prop"><?= $this->task->renderTimeEstimatedField($values, $errors) ?></div>
            <div class="kb-plane-prop"><?= $this->task->renderTimeSpentField($values, $errors) ?></div>
            <div class="kb-plane-prop"><?= $this->task->renderScoreField($values, $errors) ?></div>
            <div class="kb-plane-prop"><?= $this->task->renderReferenceField($values, $errors) ?></div>
            <div class="kb-plane-prop kb-plane-prop-tags"><?= $this->task->renderTagField($project, $tags) ?></div>
            <?= $this->hook->render('template:task:form:second-column', array('values' => $values, 'errors' => $errors)) ?>
            <?= $this->hook->render('template:task:form:third-column', array('values' => $values, 'errors' => $errors)) ?>
        </div>
        <div class="kb-plane-footer">
            <span></span>
            <div class="kb-plane-actions">
                <a href="#" class="kb-plane-discard" data-kb-plane-discard="1"><?= t('Cancel') ?></a>
                <button type="submit" class="kb-plane-save"><?= t('Save') ?></button>
            </div>
        </div>
    </form>
</div>
