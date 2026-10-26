<div class="table-list-details kb-issue-meta">
    <?php if (!empty($task['column_name'])): ?>
        <span class="kb-issue-badge kb-issue-column"><?= $this->text->e($task['column_name']) ?></span>
    <?php endif ?>
    <?php if (!empty($task['project_name'])): ?>
        <span class="kb-issue-badge kb-issue-project"><?= $this->text->e($task['project_name']) ?></span>
    <?php endif ?>
    <?php if (!empty($task['category_id'])): ?>
        <span class="table-list-category kb-issue-badge <?= $task['category_color_id'] ? "color-{$task['category_color_id']}" : '' ?>">
            <?php if ($this->user->hasProjectAccess('TaskModificationController', 'edit', $task['project_id'])): ?>
                <?= $this->url->link(
                    $this->text->e($task['category_name']),
                    'TaskModificationController',
                    'edit',
                    array('task_id' => $task['id']),
                    false,
                    'js-modal-medium' . (! empty($task['category_description']) ? ' tooltip' : ''),
                    t('Change category')
                ) ?>
                <?php if (! empty($task['category_description'])): ?>
                    <?= $this->app->tooltipMarkdown($task['category_description']) ?>
                <?php endif ?>
            <?php else: ?>
                <?= $this->text->e($task['category_name']) ?>
            <?php endif ?>
        </span>
    <?php endif ?>
    <?php foreach ($task['tags'] as $tag): ?>
        <span class="table-list-category task-list-tag kb-issue-badge <?= $tag['color_id'] ? "color-{$tag['color_id']}" : '' ?>">
            <?= $this->text->e($tag['name']) ?>
        </span>
    <?php endforeach ?>
</div>
