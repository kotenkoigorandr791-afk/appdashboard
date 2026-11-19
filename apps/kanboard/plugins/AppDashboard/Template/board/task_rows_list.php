<?php
$canEdit = ! empty($canEdit);
$prioStart = isset($prioStart) ? (int) $prioStart : 0;
$prioEnd = isset($prioEnd) ? (int) $prioEnd : 3;
$statusIcons = $this->CircleCycleHelper->statusIcons();
$totalColumns = isset($totalColumns) ? (int) $totalColumns : 1;
?>
<?php foreach (($tasks ?? []) as $task): ?>
    <?php
    $due = $this->CircleCycleHelper->formatDue(!empty($task['date_due']) ? (int) $task['date_due'] : null);
    $initials = $this->CircleCycleHelper->initials($task);
    $tags = $task['tags'] ?? [];
    $category = trim((string) ($task['category_name'] ?? ''));
    $statusKey = $this->CircleCycleHelper->taskStatusKey($task, $totalColumns);
    $columnId = (int) ($task['_column_id'] ?? $task['column_id'] ?? 0);
    ?>
    <div class="kb-issue-row <?= ((int) ($task['is_active'] ?? 1) === 0 || $statusKey === 'done') ? 'is-done' : '' ?>"
         data-task-id="<?= (int) $task['id'] ?>"
         data-column-id="<?= $columnId ?>"
         <?= $canEdit && $columnId > 0 ? 'draggable="true"' : '' ?>>
        <span class="kb-issue-grip" aria-hidden="true"></span>
        <span class="kb-issue-status">
            <?= $statusIcons[$statusKey] ?? $statusIcons['todo'] ?>
        </span>
        <a class="kb-issue-name" href="<?= $this->url->href('TaskViewController', 'show', ['task_id' => $task['id']]) ?>">
            <?= $this->text->e($task['title']) ?>
        </a>
        <div class="kb-issue-trail">
            <?php if (isset($task['priority'])): ?>
                <span class="kb-issue-prio" data-priority="<?= (int) $task['priority'] ?>" title="P<?= (int) $task['priority'] ?>"><?= $this->CircleCycleHelper->priorityIconSvg((int) $task['priority'], $prioStart, $prioEnd) ?></span>
            <?php endif ?>
            <?php if ($category !== ''): ?>
                <span class="kb-issue-tag"><?= $this->text->e($category) ?></span>
            <?php endif ?>
            <?php foreach (array_slice($tags, 0, 2) as $tag): ?>
                <span class="kb-issue-tag"><?= $this->text->e($tag['name'] ?? '') ?></span>
            <?php endforeach ?>
            <?php if ($due !== ''): ?>
                <span class="kb-issue-date"><?= $this->text->e($due) ?></span>
            <?php endif ?>
            <?php if ($initials !== ''): ?>
                <span class="kb-issue-avatar" title="<?= $this->text->e(($task['assignee_name'] ?? '') ?: ($task['assignee_username'] ?? '')) ?>"><?= $this->text->e($initials) ?></span>
            <?php else: ?>
                <span class="kb-issue-avatar is-empty">?</span>
            <?php endif ?>
        </div>
    </div>
<?php endforeach ?>
