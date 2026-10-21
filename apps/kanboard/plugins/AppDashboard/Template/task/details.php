<?php
$statusIcons = [
    'backlog' => '<svg class="kb-st-icon kb-st-backlog" viewBox="0 0 16 16" aria-hidden="true"><circle cx="8" cy="8" r="5.25" fill="none" stroke="currentColor" stroke-width="1.5" stroke-dasharray="2.2 2"/></svg>',
    'todo' => '<svg class="kb-st-icon kb-st-todo" viewBox="0 0 16 16" aria-hidden="true"><circle cx="8" cy="8" r="5.25" fill="none" stroke="currentColor" stroke-width="1.5"/></svg>',
    'progress' => '<svg class="kb-st-icon kb-st-progress" viewBox="0 0 16 16" aria-hidden="true"><circle cx="8" cy="8" r="5.25" fill="none" stroke="currentColor" stroke-width="1.5"/><path d="M8 2.75a5.25 5.25 0 0 1 0 10.5Z" fill="currentColor"/></svg>',
    'review' => '<svg class="kb-st-icon kb-st-review" viewBox="0 0 16 16" aria-hidden="true"><circle cx="8" cy="8" r="5.25" fill="none" stroke="currentColor" stroke-width="1.5"/><path d="m5.6 8.2 1.7 1.7 3.2-3.4" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    'done' => '<svg class="kb-st-icon kb-st-done" viewBox="0 0 16 16" aria-hidden="true"><circle cx="8" cy="8" r="6" fill="currentColor"/><path d="m5.4 8.1 1.8 1.8 3.5-3.7" fill="none" stroke="#0f0f12" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>',
];
$columnOptions = $this->CircleCycleHelper->columnsWithStatus((int) $project['id']);
$currentStatusKey = 'todo';
foreach ($columnOptions as $opt) {
    if ((int) $opt['id'] === (int) $task['column_id']) {
        $currentStatusKey = $opt['status_key'];
        break;
    }
}
$prioStart = (int) ($project['priority_start'] ?? 0);
$prioEnd = (int) ($project['priority_end'] ?? 3);
$priorities = range($prioStart, $prioEnd);
$quickUrl = $this->url->href('TaskQuickUpdateController', 'updateField', [
    'plugin' => 'AppDashboard',
    'csrf_token' => $this->app->getToken()->getReusableCSRFToken(),
]);
$canQuick = !empty($editable) && $this->user->hasProjectAccess('TaskModificationController', 'update', $project['id']);
?>
<section id="task-summary" class="kb-task-summary" data-quick-url="<?= $this->text->e($quickUrl) ?>">
    <h2><?= $this->text->e($task['title']) ?></h2>

    <?= $this->hook->render('template:task:details:top', array('task' => $task)) ?>

    <div class="task-summary-container color-<?= $task['color_id'] ?>">
        <div class="task-summary-columns">
            <div class="task-summary-column">
                <ul class="no-bullet">
                    <li class="kb-task-status-row">
                        <strong><?= t('Status:') ?></strong>
                        <?php if ($canQuick): ?>
                            <div class="kb-inline-select kb-inline-status">
                                <span class="kb-inline-icon"><?= $statusIcons[$currentStatusKey] ?? $statusIcons['todo'] ?></span>
                                <select class="kb-quick-select" data-field="column_id" data-task-id="<?= (int) $task['id'] ?>" aria-label="<?= t('Status') ?>">
                                    <?php foreach ($columnOptions as $opt): ?>
                                        <option value="<?= (int) $opt['id'] ?>" data-status-key="<?= $this->text->e($opt['status_key']) ?>"<?= (int) $task['column_id'] === (int) $opt['id'] ? ' selected' : '' ?>>
                                            <?= $this->text->e($opt['title']) ?>
                                        </option>
                                    <?php endforeach ?>
                                </select>
                            </div>
                        <?php else: ?>
                            <span><?= $this->text->e($task['column_title']) ?></span>
                        <?php endif ?>
                    </li>
                    <li class="kb-task-priority-row">
                        <strong><?= t('Priority:') ?></strong>
                        <?php if ($canQuick): ?>
                            <div class="kb-inline-select kb-inline-priority">
                                <select class="kb-quick-select" data-field="priority" data-task-id="<?= (int) $task['id'] ?>" aria-label="<?= t('Priority') ?>">
                                    <?php foreach ($priorities as $p): ?>
                                        <option value="<?= (int) $p ?>"<?= (int) $task['priority'] === (int) $p ? ' selected' : '' ?>>
                                            <?= (int) $p === 0 ? t('None') : 'P'.(int) $p ?>
                                        </option>
                                    <?php endforeach ?>
                                </select>
                            </div>
                        <?php else: ?>
                            <span><?= (int) $task['priority'] ?></span>
                        <?php endif ?>
                    </li>
                    <?php if (! empty($task['reference'])): ?>
                        <li>
                            <strong><?= t('Reference:') ?></strong> <span><?= $this->task->renderReference($task) ?></span>
                        </li>
                    <?php endif ?>
                    <?php if (! empty($task['score'])): ?>
                        <li>
                            <strong><?= t('Complexity:') ?></strong> <span><?= $this->text->e($task['score']) ?></span>
                        </li>
                    <?php endif ?>
                    <?php if ($project['is_public']): ?>
                    <li>
                        <small>
                            <?= $this->url->icon('external-link', t('Public link'), 'TaskViewController', 'readonly', array('task_id' => $task['id'], 'token' => $project['token']), false, '', '', true) ?>
                        </small>
                    </li>
                    <?php endif ?>
                    <?php if ($project['is_public'] && !$editable): ?>
                    <li>
                        <small>
                            <?= $this->url->icon('th', t('Back to the board'), 'BoardViewController', 'readonly', array('token' => $project['token'])) ?>
                        </small>
                    </li>
                    <?php endif ?>

                    <?= $this->hook->render('template:task:details:first-column', array('task' => $task)) ?>
                </ul>
            </div>
            <div class="task-summary-column">
                <ul class="no-bullet">
                    <?php if (! empty($task['category_name'])): ?>
                        <li>
                            <strong><?= t('Category:') ?></strong>
                            <span><?= $this->text->e($task['category_name']) ?></span>
                        </li>
                    <?php endif ?>
                    <?php if (! empty($task['swimlane_name'])): ?>
                        <li>
                            <strong><?= t('Swimlane:') ?></strong>
                            <span><?= $this->text->e($task['swimlane_name']) ?></span>
                        </li>
                    <?php endif ?>
                    <li>
                        <strong><?= t('Position:') ?></strong>
                        <span><?= $task['position'] ?></span>
                    </li>

                    <?= $this->hook->render('template:task:details:second-column', array('task' => $task)) ?>
                </ul>
            </div>
            <div class="task-summary-column">
                <ul class="no-bullet">
                    <li>
                        <strong><?= t('Assignee:') ?></strong>
                        <span>
                        <?php if ($task['assignee_username']): ?>
                            <?= $this->text->e($task['assignee_name'] ?: $task['assignee_username']) ?>
                        <?php else: ?>
                            <?= t('not assigned') ?>
                        <?php endif ?>
                        </span>
                        <?php if ($editable && $task['owner_id'] != $this->user->getId()): ?>
                            - <span><?= $this->url->link(t('Assign to me'), 'TaskModificationController', 'assignToMe', ['task_id' => $task['id'], 'csrf_token' => $this->app->getToken()->getReusableCSRFToken()]) ?></span>
                        <?php endif ?>
                    </li>
                    <?php if ($task['creator_username']): ?>
                        <li>
                            <strong><?= t('Creator:') ?></strong>
                            <span><?= $this->text->e($task['creator_name'] ?: $task['creator_username']) ?></span>
                        </li>
                    <?php endif ?>
                    <?php if ($task['time_estimated']): ?>
                    <li>
                        <strong><?= t('Time estimated:') ?></strong>
                        <span><?= t('%s hours', $task['time_estimated']) ?></span>
                    </li>
                    <?php endif ?>
                    <?php if ($task['time_spent']): ?>
                    <li>
                        <strong><?= t('Time spent:') ?></strong>
                        <span><?= t('%s hours', $task['time_spent']) ?></span>
                    </li>
                    <?php endif ?>

                    <?= $this->hook->render('template:task:details:third-column', array('task' => $task)) ?>
                </ul>
            </div>
            <div class="task-summary-column">
                <ul class="no-bullet">
                    <?php if ($task['date_due']): ?>
                        <li>
                            <strong><?= t('Due date:') ?></strong>
                            <span><?= $this->dt->datetime($task['date_due']) ?></span>
                        </li>
                    <?php endif ?>
                    <li>
                        <strong><?= t('Started:') ?></strong>
                        <?php if ($task['date_started']): ?>
                            <span><?= $this->dt->datetime($task['date_started']) ?></span>
                        <?php elseif ($editable): ?>
                            <span><?= $this->url->link(t('Start now'), 'TaskModificationController', 'start', ['task_id' => $task['id'], 'csrf_token' => $this->app->getToken()->getReusableCSRFToken()]) ?></span>
                        <?php endif ?>
                    </li>
                    <li>
                        <strong><?= t('Created:') ?></strong>
                        <span><?= $this->dt->datetime($task['date_creation']) ?></span>
                    </li>
                    <li>
                        <strong><?= t('Modified:') ?></strong>
                        <span><?= $this->dt->datetime($task['date_modification']) ?></span>
                    </li>
                    <?php if ($task['date_completed']): ?>
                    <li>
                        <strong><?= t('Completed:') ?></strong>
                        <span><?= $this->dt->datetime($task['date_completed']) ?></span>
                    </li>
                    <?php endif ?>
                    <?php if ($task['date_moved']): ?>
                    <li>
                        <strong><?= t('Moved:') ?></strong>
                        <span><?= $this->dt->datetime($task['date_moved']) ?></span>
                    </li>
                    <?php endif ?>

                    <?= $this->hook->render('template:task:details:fourth-column', array('task' => $task)) ?>
                </ul>
            </div>
        </div>
        <?php if (! empty($tags)): ?>
            <div class="task-tags">
                <ul>
                    <?php foreach ($tags as $tag): ?>
                        <li class="task-tag <?= $tag['color_id'] ? "color-{$tag['color_id']}" : '' ?>"><?= $this->text->e($tag['name']) ?></li>
                    <?php endforeach ?>
                </ul>
            </div>
        <?php endif ?>
    </div>

    <?php if (! empty($task['external_uri']) && ! empty($task['external_provider'])): ?>
        <?= $this->app->component('external-task-view', array(
            'url' => $this->url->href('ExternalTaskViewController', 'show', array('task_id' => $task['id'])),
        )) ?>
    <?php endif ?>

    <?= $this->hook->render('template:task:details:bottom', array('task' => $task)) ?>
</section>
