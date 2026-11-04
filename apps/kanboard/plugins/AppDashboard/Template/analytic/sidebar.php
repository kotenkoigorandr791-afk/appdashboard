<?php
$analyticIcon = static function (string $name): string {
    $paths = [
        'distribution' => '<path d="M12 3a9 9 0 1 0 9 9h-9V3Z"/><path d="M14.5 3.6A8.5 8.5 0 0 1 20.4 9.5H14.5V3.6Z"/>',
        'users' => '<circle cx="9" cy="8" r="3.2"/><path d="M3.5 19a5.5 5.5 0 0 1 11 0"/><circle cx="17" cy="9.5" r="2.4"/><path d="M15.4 19a4.2 4.2 0 0 1 5.6-2.6"/>',
        'flow' => '<path d="M3 16.5 9.5 10l4 4L21 6.5"/><path d="M3 20h18"/><path d="M3 12h4.5"/>',
        'burndown' => '<polyline points="22 17 13.5 8.5 8.5 13.5 2 7"/><polyline points="16 17 22 17 22 11"/>',
        'average' => '<rect x="3" y="11" width="4" height="9" rx="1"/><rect x="10" y="4" width="4" height="16" rx="1"/><rect x="17" y="14" width="4" height="6" rx="1"/>',
        'timer' => '<circle cx="12" cy="13" r="7.5"/><path d="M12 9.5V13l2.6 2.6"/><path d="M9 2.5h6"/>',
        'scale' => '<path d="M12 3v18"/><path d="M5 7h14"/><path d="M7 7l-3 6a3 3 0 0 0 6 0L7 7Z"/><path d="M17 7l-3 6a3 3 0 0 0 6 0l-3-6Z"/>',
        'compare' => '<rect x="3" y="12" width="4" height="8" rx="1"/><rect x="10" y="7" width="4" height="13" rx="1"/><rect x="17" y="4" width="4" height="16" rx="1"/><path d="M3 20h18"/>',
    ];
    $path = $paths[$name] ?? $paths['distribution'];
    return '<svg class="kb-sidebar-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $path . '</svg>';
};
$analyticLink = function (string $action, string $label, string $icon) use ($project, $analyticIcon): string {
    return '<a href="' . $this->url->href('AnalyticController', $action, array('project_id' => $project['id'])) . '" class="js-modal-replace">'
        . $analyticIcon($icon)
        . '<span>' . $this->text->e($label) . '</span>'
        . '</a>';
};
?>
<div class="sidebar">
    <ul>
        <li <?= $this->app->checkMenuSelection('AnalyticController', 'taskDistribution') ?>>
            <?= $analyticLink('taskDistribution', t('Task distribution'), 'distribution') ?>
        </li>
        <li <?= $this->app->checkMenuSelection('AnalyticController', 'userDistribution') ?>>
            <?= $analyticLink('userDistribution', t('User repartition'), 'users') ?>
        </li>
        <li <?= $this->app->checkMenuSelection('AnalyticController', 'cfd') ?>>
            <?= $analyticLink('cfd', t('Cumulative flow diagram'), 'flow') ?>
        </li>
        <li <?= $this->app->checkMenuSelection('AnalyticController', 'burndown') ?>>
            <?= $analyticLink('burndown', t('Burndown chart'), 'burndown') ?>
        </li>
        <li <?= $this->app->checkMenuSelection('AnalyticController', 'averageTimeByColumn') ?>>
            <?= $analyticLink('averageTimeByColumn', t('Average time into each column'), 'average') ?>
        </li>
        <li <?= $this->app->checkMenuSelection('AnalyticController', 'leadAndCycleTime') ?>>
            <?= $analyticLink('leadAndCycleTime', t('Lead and cycle time'), 'timer') ?>
        </li>
        <li <?= $this->app->checkMenuSelection('AnalyticController', 'timeComparison') ?>>
            <?= $analyticLink('timeComparison', t('Estimated vs actual time'), 'scale') ?>
        </li>
        <li <?= $this->app->checkMenuSelection('AnalyticController', 'estimatedVsActualByColumn') ?>>
            <?= $analyticLink('estimatedVsActualByColumn', t('Estimated vs actual time per column'), 'compare') ?>
        </li>

        <?= $this->hook->render('template:analytic:sidebar', array('project' => $project)) ?>
    </ul>
</div>
