<?php
$canEdit = ! empty($canEdit);
$prioStart = isset($prioStart) ? (int) $prioStart : 0;
$prioEnd = isset($prioEnd) ? (int) $prioEnd : 3;
$priorities = range($prioStart, $prioEnd);
$statusIcons = $this->CircleCycleHelper->statusIcons();
$totalColumns = isset($totalColumns) ? (int) $totalColumns : 1;
$columns = $columns ?? [];
$columnOptions = [];
foreach ($columns as $column) {
    $columnOptions[] = [
        'id' => (int) $column['id'],
        'title' => (string) $column['title'],
        'status_key' => $this->CircleCycleHelper->statusKeyPublic(
            (string) $column['title'],
            (int) ($column['position'] ?? 0),
            max(1, count($columns))
        ),
    ];
}
?>
<?php foreach (($tasks ?? []) as $task): ?>
    <?php
    $prio = isset($task['priority']) ? (int) $task['priority'] : $prioStart;
    $assignee = ($task['assignee_name'] ?? '') ?: ($task['assignee_username'] ?? '');
    $dueTs = !empty($task['date_due']) ? (int) $task['date_due'] : 0;
    $colId = (int) ($task['column_id'] ?? 0);
    $statusKey = $this->CircleCycleHelper->taskStatusKey($task, $totalColumns);
    $statusTitle = (string) ($task['column_name'] ?? '');
    ?>
    <tr class="kb-table-row status-<?= $this->text->e($statusKey) ?> kb-tint-<?= $this->text->e($statusKey) ?>"
        data-task-id="<?= (int) $task['id'] ?>"
        data-id="<?= (int) $task['id'] ?>"
        data-title="<?= $this->text->e($task['title']) ?>"
        data-status="<?= $this->text->e($statusTitle) ?>"
        data-status-key="<?= $this->text->e($statusKey) ?>"
        data-assignee="<?= $this->text->e($assignee) ?>"
        data-priority="<?= $prio ?>"
        data-due="<?= $dueTs ?>"
        data-column-id="<?= $colId ?>"
        <?= $canEdit && $colId > 0 ? 'draggable="true"' : '' ?>>
        <td class="kb-mono" data-value="<?= (int) $task['id'] ?>"><span class="kb-issue-grip" aria-hidden="true"></span>#<?= (int) $task['id'] ?></td>
        <td data-value="<?= $this->text->e(mb_strtolower($task['title'])) ?>">
            <a href="<?= $this->url->href('TaskViewController', 'show', ['task_id' => $task['id']]) ?>">
                <?= $this->text->e($task['title']) ?>
            </a>
        </td>
        <td class="kb-table-status" data-value="<?= $this->text->e(mb_strtolower($statusTitle)) ?>">
            <div class="kb-inline-select kb-inline-status">
                <span class="kb-inline-icon"><?= $statusIcons[$statusKey] ?? $statusIcons['todo'] ?></span>
                <?php if ($canEdit && $colId > 0): ?>
                    <select class="kb-quick-select" data-field="column_id" data-task-id="<?= (int) $task['id'] ?>" aria-label="<?= t('Status') ?>">
                        <?php foreach ($columnOptions as $opt): ?>
                            <option value="<?= (int) $opt['id'] ?>" data-status-key="<?= $this->text->e($opt['status_key']) ?>"<?= $colId === (int) $opt['id'] ? ' selected' : '' ?>>
                                <?= $this->text->e($opt['title']) ?>
                            </option>
                        <?php endforeach ?>
                    </select>
                <?php else: ?>
                    <span><?= $this->text->e($statusTitle) ?></span>
                <?php endif ?>
            </div>
        </td>
        <td data-value="<?= $this->text->e(mb_strtolower($assignee !== '' ? $assignee : 'zzz')) ?>">
            <?= $this->text->e($assignee !== '' ? $assignee : '—') ?>
        </td>
        <td class="kb-table-priority" data-value="<?= $prio ?>">
            <div class="kb-inline-select kb-inline-priority">
                <span class="kb-inline-icon"><?= $this->CircleCycleHelper->priorityIconSvg($prio, $prioStart, $prioEnd) ?></span>
                <?php if ($canEdit): ?>
                    <select class="kb-quick-select" data-field="priority" data-task-id="<?= (int) $task['id'] ?>" aria-label="<?= t('Priority') ?>">
                        <?php foreach ($priorities as $p): ?>
                            <option value="<?= (int) $p ?>"<?= $prio === (int) $p ? ' selected' : '' ?>>
                                <?= (int) $p === 0 ? t('None') : 'P'.(int) $p ?>
                            </option>
                        <?php endforeach ?>
                    </select>
                <?php else: ?>
                    <span>P<?= $prio ?></span>
                <?php endif ?>
            </div>
        </td>
        <td data-value="<?= $dueTs ?>">
            <?= $dueTs > 0 ? $this->text->e($this->CircleCycleHelper->formatDue($dueTs)) : '—' ?>
        </td>
    </tr>
<?php endforeach ?>
