<section id="main">
    <?= $this->projectHeader->render($project, 'ProjectOverviewController', 'show') ?>
    <link rel="stylesheet" href="<?= $this->url->dir() ?>plugins/AppDashboard/Asset/dayflow-calendar.css?<?= filemtime(__DIR__.'/../../Asset/dayflow-calendar.css') ?>">
    <div class="kb-overview-calendar-wrap">
        <script type="application/json" id="kb-dayflow-config"><?= $this->OverviewCalendarHelper->getConfigJson($project) ?></script>
        <div id="kb-dayflow-calendar" class="kb-dayflow-calendar"></div>
    </div>
    <script defer src="<?= $this->url->dir() ?>plugins/AppDashboard/Asset/dayflow-calendar.js?<?= filemtime(__DIR__.'/../../Asset/dayflow-calendar.js') ?>"></script>
</section>
