<?php
$projectName = (string) ($project['name'] ?? '');
$columnId = (int) ($values['column_id'] ?? 0);
$columnLabel = $columns_list[$columnId] ?? reset($columns_list) ?: t('Backlog');
?>
<div class="kb-plane-create">
    <div class="kb-plane-create-head">
        <div class="kb-plane-head-text">
            <h2 class="kb-plane-create-title"><?= t('New task') ?></h2>
            <div class="kb-plane-crumbs" aria-label="<?= t('Breadcrumb') ?>">
                <span class="kb-plane-crumb kb-plane-crumb-project">
                    <svg viewBox="0 0 16 16" width="13" height="13" aria-hidden="true"><rect x="2.5" y="4" width="11" height="9" rx="1.5" fill="none" stroke="currentColor" stroke-width="1.3"/><path d="M5 4V3.2A1.2 1.2 0 0 1 6.2 2h3.6A1.2 1.2 0 0 1 11 3.2V4" fill="none" stroke="currentColor" stroke-width="1.3"/></svg>
                    <span><?= $this->text->e($projectName) ?></span>
                </span>
                <span class="kb-plane-crumb-sep" aria-hidden="true">
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="m6 3 5 5-5 5"/></svg>
                </span>
                <button type="button" class="kb-plane-crumb kb-plane-crumb-column" aria-haspopup="listbox" aria-expanded="false">
                    <span class="kb-plane-crumb-ico" aria-hidden="true">
                        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"><rect x="2.5" y="3" width="3.5" height="10" rx="1"/><rect x="6.25" y="3" width="3.5" height="7" rx="1"/><rect x="10" y="3" width="3.5" height="10" rx="1"/></svg>
                    </span>
                    <span class="kb-plane-crumb-label"><?= $this->text->e((string) $columnLabel) ?></span>
                    <span class="kb-plane-crumb-caret" aria-hidden="true">
                        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="m4 6 4 4 4-4"/></svg>
                    </span>
                </button>
            </div>
        </div>
        <button type="button" class="kb-plane-close" data-kb-plane-discard="1" aria-label="<?= t('Close') ?>" title="<?= t('Close') ?>">
            <svg viewBox="0 0 16 16" width="16" height="16" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><path d="m4 4 8 8"/><path d="m12 4-8 8"/></svg>
        </button>
    </div>
    <form method="post" action="<?= $this->url->href('TaskCreationController', 'save', array('project_id' => $project['id'])) ?>" autocomplete="off" class="kb-plane-form">
        <?= $this->form->csrf() ?>
        <div class="kb-plane-fields">
            <?= $this->task->renderTitleField($values, $errors) ?>
            <div class="kb-plane-desc">
                <?= $this->task->renderDescriptionField($values, $errors) ?>
            </div>
        </div>
        <div class="kb-plane-props">
            <div class="kb-plane-prop"><?= $this->task->renderColumnField($columns_list, $values, $errors) ?></div>
            <div class="kb-plane-prop"><?= $this->task->renderPriorityField($project, $values) ?></div>
            <div class="kb-plane-prop kb-plane-prop-assignee">
                <label for="kb-assignee-search"><?= t('Assignee') ?></label>
                <?= $this->form->hidden('owner_id', $values + ['owner_id' => $values['owner_id'] ?? 0]) ?>
                <input type="text"
                       id="kb-assignee-search"
                       class="autocomplete kb-assignee-search"
                       placeholder="<?= $this->text->e(t('Type email…')) ?>"
                       value=""
                       data-dst-field="owner_id"
                       data-search-url="<?= $this->url->href('ProjectMemberController', 'searchAssignable', ['plugin' => 'AppDashboard', 'project_id' => $project['id']]) ?>"
                       autocomplete="off">
            </div>
            <?php if (!empty($categories_list) && count($categories_list) > 1): ?>
                <div class="kb-plane-prop"><?= $this->task->renderCategoryField($categories_list, $values, $errors) ?></div>
            <?php endif ?>
            <div class="kb-plane-prop kb-plane-prop-date"><?= $this->task->renderStartDateField($values, $errors) ?></div>
            <div class="kb-plane-prop kb-plane-prop-date"><?= $this->task->renderDueDateField($values, $errors) ?></div>
            <?php if (!empty($swimlanes_list) && count($swimlanes_list) > 1): ?>
                <div class="kb-plane-prop"><?= $this->task->renderSwimlaneField($swimlanes_list, $values, $errors) ?></div>
            <?php elseif (!empty($values['swimlane_id'])): ?>
                <?= $this->form->hidden('swimlane_id', $values) ?>
            <?php endif ?>
            <div class="kb-plane-prop kb-plane-prop-tags"><?= $this->task->renderTagField($project) ?></div>
        </div>
        <div class="kb-plane-footer">
            <?php if (!isset($duplicate)): ?>
                <label class="kb-plane-more">
                    <?= $this->form->checkbox('another_task', '', 1, isset($values['another_task']) && $values['another_task'] == 1, '', array('tabindex' => '16')) ?>
                    <span><?= t('Create more') ?></span>
                </label>
            <?php else: ?>
                <span></span>
            <?php endif ?>
            <div class="kb-plane-actions">
                <a href="#" class="kb-plane-discard" data-kb-plane-discard="1"><?= t('Cancel') ?></a>
                <button type="submit" class="kb-plane-save" tabindex="15"><?= t('Save') ?></button>
            </div>
        </div>
    </form>
</div>
