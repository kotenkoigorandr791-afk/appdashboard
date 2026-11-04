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
$statusIcons = $this->CircleCycleHelper->statusIcons();
$prioIcon = function (int $priority, int $min, int $max): string {
    return $this->CircleCycleHelper->priorityIconSvg($priority, $min, $max);
};
$totalColumns = max(1, count($cycle['groups']));
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
        <header class="kb-cycle-header">
            <div class="kb-cycle-header-left">
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
                <div class="menus-container kb-cycle-menus">
                    <?= $this->render('header/user_notifications') ?>
                    <?= $this->render('header/creation_dropdown') ?>
                    <?= $this->render('header/user_dropdown') ?>
                </div>
            </div>
        </header>
        <div class="kb-cycle-center">

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
                            <?php $tableTasks = isset($table_tasks) ? $table_tasks : $flatTasks; ?>
                            <?php if (empty($tableTasks)): ?>
                                <tr><td colspan="6" class="kb-table-empty"><?= t('There is nothing assigned to you.') ?></td></tr>
                            <?php else: ?>
                                <?= $this->render('appDashboard:board/task_rows_table', [
                                    'tasks' => $tableTasks,
                                    'canEdit' => $canEdit,
                                    'prioStart' => $prioStart,
                                    'prioEnd' => $prioEnd,
                                    'columnOptions' => $columnOptions,
                                    'totalColumns' => $totalColumns,
                                    'project' => $project,
                                ]) ?>
                                <?php if (!empty($table_has_more)): ?>
                                    <tr class="kb-chunk-more-row"
                                        data-kb-board-more="1"
                                        data-kb-target="table"
                                        data-kb-url="<?= $this->url->href('BoardAjaxController', 'tasks', [
                                            'project_id' => $project['id'],
                                            'offset' => count($tableTasks),
                                            'limit' => (int) ($table_limit ?? 50),
                                            'mode' => 'table',
                                        ]) ?>"
                                        data-kb-offset="<?= count($tableTasks) ?>"><td colspan="6"></td></tr>
                                <?php endif ?>
                            <?php endif ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="kb-cycle-issues" id="kb-cycle-issues" data-can-edit="<?= $canEdit ? '1' : '0' ?>" data-prio-min="<?= (int) $prioStart ?>" data-prio-max="<?= (int) $prioEnd ?>" data-prio-labels="<?= $this->text->e(json_encode([t('Low'), t('Normal'), t('High'), t('Urgent')])) ?>">
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
                                <?= $this->render('appDashboard:board/task_rows_list', [
                                    'tasks' => $group['tasks'],
                                    'canEdit' => $canEdit,
                                    'prioStart' => $prioStart,
                                    'prioEnd' => $prioEnd,
                                    'totalColumns' => $totalColumns,
                                ]) ?>
                                <?php if (!empty($group['has_more'])): ?>
                                    <div class="kb-chunk-more-row"
                                         data-kb-board-more="1"
                                         data-kb-target="list"
                                         data-kb-url="<?= $this->url->href('BoardAjaxController', 'tasks', [
                                             'project_id' => $project['id'],
                                             'column_id' => $group['id'],
                                             'offset' => (int) $group['count'],
                                             'limit' => 50,
                                             'mode' => 'list',
                                         ]) ?>"
                                         data-kb-offset="<?= (int) $group['count'] ?>"
                                         data-kb-total="<?= (int) $group['total'] ?>"></div>
                                <?php endif ?>
                            </div>
                        </section>
                    <?php endforeach ?>
                </div>
            <?php endif ?>
        </div>

        <div class="kb-panel-resizer kb-panel-resizer-cycle" data-kb-resizer="cycle" role="separator" aria-orientation="vertical" aria-label="<?= t('Resize panel') ?>" title="<?= t('Resize panel') ?>"></div>
        <aside class="kb-cycle-panel" id="kb-cycle-panel" aria-label="<?= t('Analytics') ?>">
            <div class="kb-cycle-panel-head">
                <h2>Cycle Details</h2>
                <span class="kb-cycle-panel-sub"><?= $this->text->e($project['name']) ?></span>
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
    </div>
</section>
