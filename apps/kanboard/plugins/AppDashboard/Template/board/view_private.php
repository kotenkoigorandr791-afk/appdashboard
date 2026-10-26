<?php
$cycle = $this->CircleCycleHelper->buildFromSwimlanes($project, $swimlanes);
$stats = $cycle['stats'];
$view = isset($_GET['view']) ? (string) $_GET['view'] : 'list';
if (!in_array($view, ['list', 'board', 'table'], true)) {
    $view = 'list';
}
$searchQ = $this->projectHeader->getSearchQuery($project);
$listUrl = $this->url->href('BoardViewController', 'show', ['project_id' => $project['id'], 'search' => $searchQ, 'view' => 'list']);
$boardUrl = $this->url->href('BoardViewController', 'show', ['project_id' => $project['id'], 'search' => $searchQ, 'view' => 'board']);
$tableUrl = $this->url->href('BoardViewController', 'show', ['project_id' => $project['id'], 'search' => $searchQ, 'view' => 'table']);
$statusIcons = [
    'backlog' => '<svg class="kb-st-icon kb-st-backlog" viewBox="0 0 16 16" aria-hidden="true"><circle cx="8" cy="8" r="5.25" fill="none" stroke="currentColor" stroke-width="1.5" stroke-dasharray="2.2 2"/></svg>',
    'todo' => '<svg class="kb-st-icon kb-st-todo" viewBox="0 0 16 16" aria-hidden="true"><circle cx="8" cy="8" r="5.25" fill="none" stroke="currentColor" stroke-width="1.5"/></svg>',
    'progress' => '<svg class="kb-st-icon kb-st-progress" viewBox="0 0 16 16" aria-hidden="true"><circle cx="8" cy="8" r="5.25" fill="none" stroke="currentColor" stroke-width="1.5"/><path d="M8 2.75a5.25 5.25 0 0 1 0 10.5Z" fill="currentColor"/></svg>',
    'review' => '<svg class="kb-st-icon kb-st-review" viewBox="0 0 16 16" aria-hidden="true"><circle cx="8" cy="8" r="5.25" fill="none" stroke="currentColor" stroke-width="1.5"/><path d="m5.6 8.2 1.7 1.7 3.2-3.4" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    'done' => '<svg class="kb-st-icon kb-st-done" viewBox="0 0 16 16" aria-hidden="true"><circle cx="8" cy="8" r="6" fill="currentColor"/><path d="m5.4 8.1 1.8 1.8 3.5-3.7" fill="none" stroke="#0f0f12" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>',
];
$prioIcon = static function (int $priority, int $min, int $max): string {
    $span = max(1, $max - $min);
    $level = (int) round((($priority - $min) / $span) * 3);
    $level = max(0, min(3, $level));
    $colors = ['#94a3b8', '#3b82f6', '#f59e0b', '#ef4444'];
    $color = $colors[$level];
    $bars = '';
    for ($i = 0; $i < 3; $i++) {
        $h = 4 + $i * 3;
        $y = 14 - $h;
        $x = 3 + $i * 4;
        $fill = $i <= $level - 1 || ($level === 0 && $i === 0 && $priority === $min && $min === $max)
            ? $color
            : ($i < $level ? $color : 'rgba(148,163,184,0.35)');
        if ($level === 0) {
            $fill = 'rgba(148,163,184,0.35)';
        } elseif ($i < $level) {
            $fill = $color;
        } else {
            $fill = 'rgba(148,163,184,0.28)';
        }
        $bars .= '<rect x="'.$x.'" y="'.$y.'" width="2.5" height="'.$h.'" rx="0.6" fill="'.$fill.'"/>';
    }
    return '<svg class="kb-prio-icon kb-prio-'.$level.'" viewBox="0 0 16 16" width="14" height="14" aria-hidden="true">'.$bars.'</svg>';
};
$ring = static function (int $pct, int $size = 64, string $color = '#6771c5'): string {
    $r = ($size / 2) - 4;
    $c = 2 * M_PI * $r;
    $offset = $c * (1 - max(0, min(100, $pct)) / 100);
    $cx = $size / 2;
    return '<svg class="kb-capacity-ring" width="'.$size.'" height="'.$size.'" viewBox="0 0 '.$size.' '.$size.'" aria-hidden="true">'
        .'<circle cx="'.$cx.'" cy="'.$cx.'" r="'.$r.'" fill="none" stroke="rgba(255,255,255,0.12)" stroke-width="3"/>'
        .'<circle cx="'.$cx.'" cy="'.$cx.'" r="'.$r.'" fill="none" stroke="'.$color.'" stroke-width="3" stroke-linecap="round" '
        .'stroke-dasharray="'.round($c, 2).'" stroke-dashoffset="'.round($offset, 2).'" transform="rotate(-90 '.$cx.' '.$cx.')"/>'
        .'</svg>';
};
$smallRing = static function (int $pct, string $color = '#6771c5') use ($ring): string {
    return $ring($pct, 16, $color);
};
$flatTasks = [];
foreach ($cycle['groups'] as $g) {
    foreach ($g['tasks'] as $t) {
        $t['_status_key'] = $g['status_key'];
        $t['_status_title'] = $g['title'];
        $t['_column_id'] = (int) $g['id'];
        $flatTasks[] = $t;
    }
}
$columnOptions = [];
foreach ($cycle['groups'] as $g) {
    if ((int) $g['id'] <= 0) {
        continue;
    }
    $columnOptions[] = [
        'id' => (int) $g['id'],
        'title' => (string) $g['title'],
        'status_key' => (string) $g['status_key'],
    ];
}
$prioStart = (int) ($project['priority_start'] ?? 0);
$prioEnd = (int) ($project['priority_end'] ?? 3);
$priorities = range($prioStart, $prioEnd);
$canEdit = $this->user->hasProjectAccess('TaskModificationController', 'update', $project['id']);
$quickUrl = $this->url->href('TaskQuickUpdateController', 'updateField', [
    'plugin' => 'AppDashboard',
    'csrf_token' => $this->app->getToken()->getReusableCSRFToken(),
]);
$boardStatusMap = [];
foreach ($cycle['groups'] as $g) {
    if ((int) $g['id'] > 0) {
        $boardStatusMap[(int) $g['id']] = (string) $g['status_key'];
    }
}
?>
<section id="main" class="kb-cycle-main" data-view="<?= $this->text->e($view) ?>" data-quick-url="<?= $this->text->e($quickUrl) ?>">
    <div class="kb-cycle-workspace">
        <div class="kb-cycle-center">
            <header class="kb-cycle-header">
                <div class="kb-cycle-header-left">
                    <h1 class="kb-cycle-title"><?= $this->text->e($cycle['cycle_name']) ?></h1>
                    <span class="kb-cycle-meta"><?= (int) $stats['effort'] ?></span>
                    <div class="kb-cycle-filter-wrap">
                        <?= $this->render('project_header/search', [
                            'project' => $project,
                            'filters' => [
                                'controller' => 'BoardViewController',
                                'action' => 'show',
                                'project_id' => $project['id'],
                                'search' => $searchQ,
                                'plugin' => '',
                            ],
                            'custom_filters_list' => [],
                            'users_list' => [],
                            'categories_list' => [],
                        ]) ?>
                    </div>
                </div>
                <div class="kb-cycle-header-right">
                    <div class="kb-cycle-header-ring" title="<?= (int) $stats['percent'] ?>%">
                        <?= $ring((int) $stats['percent'], 28) ?>
                        <span><?= (int) $stats['percent'] ?>%</span>
                    </div>
                    <div class="kb-cycle-views" role="tablist">
                        <a href="<?= $listUrl ?>" class="kb-cycle-view-link<?= $view === 'list' ? ' is-active' : '' ?>"><svg viewBox="0 0 16 16" aria-hidden="true"><path d="M2 3h12M2 8h12M2 13h12"/></svg><?= t('List') ?></a>
                        <a href="<?= $boardUrl ?>" class="kb-cycle-view-link<?= $view === 'board' ? ' is-active' : '' ?>"><svg viewBox="0 0 16 16" aria-hidden="true"><rect x="2" y="2" width="5" height="5" rx="1"/><rect x="9" y="2" width="5" height="5" rx="1"/><rect x="2" y="9" width="5" height="5" rx="1"/><rect x="9" y="9" width="5" height="5" rx="1"/></svg><?= t('Board') ?></a>
                        <a href="<?= $tableUrl ?>" class="kb-cycle-view-link<?= $view === 'table' ? ' is-active' : '' ?>"><svg viewBox="0 0 16 16" aria-hidden="true"><rect x="2" y="2" width="12" height="12" rx="1"/><path d="M2 6h12M2 10h12M6 2v12M10 2v12"/></svg><?= t('Table') ?></a>
                    </div>
                    <?php if ($this->user->hasProjectAccess('TaskCreationController', 'show', $project['id'])): ?>
                        <?= $this->url->link('<span class="kb-plus">+</span> '.t('New task'), 'TaskCreationController', 'show', ['project_id' => $project['id']], false, 'js-modal-large kb-cycle-new-task') ?>
                    <?php endif ?>
                </div>
            </header>

            <?php if ($view === 'board'): ?>
                <script type="application/json" id="kb-board-status-map"><?= json_encode($boardStatusMap, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?></script>
                <div class="kb-view-board" id="kb-view-board">
                    <?= $this->render('board/table_container', [
                        'project' => $project,
                        'swimlanes' => $swimlanes,
                        'board_private_refresh_interval' => $board_private_refresh_interval,
                        'board_highlight_period' => $board_highlight_period,
                    ]) ?>
                </div>
            <?php elseif ($view === 'table'): ?>
                <div class="kb-view-table-wrap">
                    <table class="kb-view-table" id="kb-view-table" data-sortable="1">
                        <thead>
                            <tr>
                                <th data-sort="id" class="is-sortable"><?= t('Id') ?></th>
                                <th data-sort="title" class="is-sortable"><?= t('Title') ?></th>
                                <th data-sort="status" class="is-sortable"><?= t('Status') ?></th>
                                <th data-sort="assignee" class="is-sortable"><?= t('Assignee') ?></th>
                                <th data-sort="priority" class="is-sortable"><?= t('Priority') ?></th>
                                <th data-sort="due" class="is-sortable"><?= t('Due Date') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($flatTasks)): ?>
                                <tr><td colspan="6" class="kb-table-empty"><?= t('There is nothing assigned to you.') ?></td></tr>
                            <?php else: ?>
                                <?php foreach ($flatTasks as $task): ?>
                                    <?php
                                    $prio = isset($task['priority']) ? (int) $task['priority'] : $prioStart;
                                    $assignee = ($task['assignee_name'] ?? '') ?: ($task['assignee_username'] ?? '');
                                    $dueTs = !empty($task['date_due']) ? (int) $task['date_due'] : 0;
                                    $colId = (int) ($task['column_id'] ?? $task['_column_id'] ?? 0);
                                    ?>
                                    <tr class="kb-table-row status-<?= $this->text->e($task['_status_key']) ?> kb-tint-<?= $this->text->e($task['_status_key']) ?>"
                                        data-task-id="<?= (int) $task['id'] ?>"
                                        data-id="<?= (int) $task['id'] ?>"
                                        data-title="<?= $this->text->e($task['title']) ?>"
                                        data-status="<?= $this->text->e($task['_status_title']) ?>"
                                        data-status-key="<?= $this->text->e($task['_status_key']) ?>"
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
                                        <td class="kb-table-status" data-value="<?= $this->text->e(mb_strtolower($task['_status_title'])) ?>">
                                            <div class="kb-inline-select kb-inline-status">
                                                <span class="kb-inline-icon"><?= $statusIcons[$task['_status_key']] ?? $statusIcons['todo'] ?></span>
                                                <?php if ($canEdit && $colId > 0): ?>
                                                    <select class="kb-quick-select" data-field="column_id" data-task-id="<?= (int) $task['id'] ?>" aria-label="<?= t('Status') ?>">
                                                        <?php foreach ($columnOptions as $opt): ?>
                                                            <option value="<?= (int) $opt['id'] ?>" data-status-key="<?= $this->text->e($opt['status_key']) ?>"<?= $colId === (int) $opt['id'] ? ' selected' : '' ?>>
                                                                <?= $this->text->e($opt['title']) ?>
                                                            </option>
                                                        <?php endforeach ?>
                                                    </select>
                                                <?php else: ?>
                                                    <span><?= $this->text->e($task['_status_title']) ?></span>
                                                <?php endif ?>
                                            </div>
                                        </td>
                                        <td data-value="<?= $this->text->e(mb_strtolower($assignee !== '' ? $assignee : 'zzz')) ?>">
                                            <?= $this->text->e($assignee !== '' ? $assignee : '—') ?>
                                        </td>
                                        <td class="kb-table-priority" data-value="<?= $prio ?>">
                                            <div class="kb-inline-select kb-inline-priority">
                                                <span class="kb-inline-icon"><?= $prioIcon($prio, $prioStart, $prioEnd) ?></span>
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
                            <?php endif ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="kb-cycle-issues" id="kb-cycle-issues" data-can-edit="<?= $canEdit ? '1' : '0' ?>">
                    <?php foreach ($cycle['groups'] as $group): ?>
                        <?php if ((int) $group['id'] <= 0 && (int) $group['count'] === 0) continue; ?>
                        <section class="kb-issue-group kb-tint-<?= $this->text->e($group['status_key']) ?>" data-status="<?= $this->text->e($group['status_key']) ?>" data-column-id="<?= (int) $group['id'] ?>">
                            <div class="kb-issue-group-head">
                                <div class="kb-issue-group-title">
                                    <?= $statusIcons[$group['status_key']] ?? $statusIcons['todo'] ?>
                                    <span><?= $this->text->e($group['title']) ?></span>
                                    <span class="kb-issue-group-count"><?= (int) $group['count'] ?></span>
                                </div>
                                <?php if ($this->user->hasProjectAccess('TaskCreationController', 'show', $project['id']) && (int) $group['id'] > 0): ?>
                                    <a class="js-modal-large kb-issue-group-add" href="<?= $this->url->href('TaskCreationController', 'show', ['project_id' => $project['id'], 'column_id' => $group['id']]) ?>" title="<?= t('New task') ?>">+</a>
                                <?php endif ?>
                            </div>
                            <div class="kb-issue-rows" data-column-id="<?= (int) $group['id'] ?>" data-status-key="<?= $this->text->e($group['status_key']) ?>">
                                <?php if (empty($group['tasks'])): ?>
                                    <div class="kb-issue-empty kb-drop-hint"><?= t('Drop tasks here') ?></div>
                                <?php endif ?>
                                <?php foreach ($group['tasks'] as $task): ?>
                                    <?php
                                    $due = $this->CircleCycleHelper->formatDue(!empty($task['date_due']) ? (int) $task['date_due'] : null);
                                    $initials = $this->CircleCycleHelper->initials($task);
                                    $tags = $task['tags'] ?? [];
                                    $category = trim((string) ($task['category_name'] ?? ''));
                                    ?>
                                    <div class="kb-issue-row <?= ((int) ($task['is_active'] ?? 1) === 0 || $group['status_key'] === 'done') ? 'is-done' : '' ?>" data-task-id="<?= (int) $task['id'] ?>" data-column-id="<?= (int) $group['id'] ?>"<?= $canEdit && (int) $group['id'] > 0 ? ' draggable="true"' : '' ?>>
                                        <span class="kb-issue-grip" aria-hidden="true"></span>
                                        <span class="kb-issue-status">
                                            <?= $statusIcons[$group['status_key']] ?? $statusIcons['todo'] ?>
                                        </span>
                                        <a class="kb-issue-name" href="<?= $this->url->href('TaskViewController', 'show', ['task_id' => $task['id']]) ?>">
                                            <?= $this->text->e($task['title']) ?>
                                        </a>
                                        <div class="kb-issue-trail">
                                            <?php if (isset($task['priority'])): ?>
                                                <span class="kb-issue-prio" title="P<?= (int) $task['priority'] ?>"><?= $prioIcon((int) $task['priority'], $prioStart, $prioEnd) ?></span>
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
                            </div>
                        </section>
                    <?php endforeach ?>
                </div>
            <?php endif ?>
        </div>

        <?php if ($view === 'list'): ?>
        <aside class="kb-cycle-panel" id="kb-cycle-panel" aria-label="<?= t('Analytics') ?>">
            <div class="kb-cycle-panel-head">
                <h2>Cycle Details</h2>
                <span class="kb-cycle-panel-sub"><?= $this->text->e($project['name']) ?> · <?= $this->text->e($cycle['cycle_name']) ?></span>
            </div>
            <div class="kb-cycle-stats">
                <div class="kb-cycle-stat kb-cycle-stat-ring">
                    <?= $ring((int) $stats['percent'], 72) ?>
                    <div class="kb-cycle-stat-ring-label">
                        <strong><?= (int) $stats['percent'] ?>%</strong>
                        <span>done</span>
                    </div>
                </div>
                <div class="kb-cycle-stat">
                    <strong><?= (int) $stats['effort'] ?></strong>
                    <span>effort</span>
                </div>
                <div class="kb-cycle-stat">
                    <strong><?= (int) $stats['weekdays_left'] ?></strong>
                    <span>weekdays left</span>
                </div>
            </div>
            <div class="kb-cycle-chart-block">
                <div class="kb-cycle-chart-legend">
                    <span><i style="background:#6771c5"></i>Effort</span>
                    <span><i style="background:#c4c8d0"></i>Scope</span>
                    <span><i style="background:#facc15"></i>Started</span>
                </div>
                <script type="application/json" id="kb-burnup-data"><?= json_encode($cycle['burnup'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?></script>
                <canvas id="kb-burnup-chart" class="kb-burnup-chart" width="360" height="160"></canvas>
            </div>
            <div class="kb-cycle-assignees">
                <h3>Progress on effort</h3>
                <?php if (empty($cycle['assignees'])): ?>
                    <p class="kb-cycle-empty"><?= t('There is nothing assigned to you.') ?></p>
                <?php else: ?>
                    <ul>
                        <?php foreach ($cycle['assignees'] as $row): ?>
                            <li>
                                <span class="kb-issue-avatar"><?= $this->text->e($row['initials']) ?></span>
                                <span class="kb-assignee-name"><?= $this->text->e($row['name']) ?></span>
                                <span class="kb-assignee-meta">
                                    <?= $smallRing((int) $row['percent']) ?>
                                    <?= (int) $row['percent'] ?>% / <?= (int) $row['total'] ?>
                                </span>
                            </li>
                        <?php endforeach ?>
                    </ul>
                <?php endif ?>
            </div>
        </aside>
        <?php endif ?>
    </div>
</section>
