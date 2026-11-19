<div class="page-header kb-inbox-header">
    <h2><?= t('Inbox') ?></h2>
</div>
<div class="kb-activity-stream">
    <?= $this->render('event/events', array('events' => $events, 'has_more' => $has_more ?? false, 'next_url' => $next_url ?? '', 'prev_ts' => 0)) ?>
</div>
