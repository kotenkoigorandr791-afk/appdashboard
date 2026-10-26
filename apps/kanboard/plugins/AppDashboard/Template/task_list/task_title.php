<div class="kb-issue-lead">
    <?php if ($this->user->hasProjectAccess('TaskModificationController', 'edit', $task['project_id'])): ?>
        <?php if (isset($show_items_selection)): ?>
            <input type="checkbox" data-list-item="selectable" name="tasks[]" value="<?= $task['id'] ?>" class="kb-issue-check">
        <?php endif ?>
        <span class="kb-issue-id">
            <?= $this->render('task/dropdown', array('task' => $task, 'redirect' => isset($redirect) ? $redirect : '')) ?>
        </span>
    <?php else: ?>
        <span class="kb-issue-id">#<?= (int) $task['id'] ?></span>
    <?php endif ?>
    <span class="kb-issue-status" title="<?= $this->text->e($task['column_name'] ?? '') ?>">
        <span class="kb-status-dot"></span>
    </span>
    <span class="table-list-title kb-issue-title <?= $task['is_active'] == 0 ? 'status-closed' : '' ?>">
        <?= $this->url->link($this->text->e($task['title']), 'TaskViewController', 'show', array('task_id' => $task['id'])) ?>
    </span>
</div>
