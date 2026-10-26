<div class="page-header kb-inbox-header">
    <h2><?= t('Inbox') ?></h2>
</div>
<div class="kb-activity-stream">
    <?= $this->render('event/events', array('events' => $events)) ?>
</div>
