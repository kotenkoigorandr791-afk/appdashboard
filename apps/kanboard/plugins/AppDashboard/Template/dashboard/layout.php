<section id="main" class="kb-main kb-dashboard-main">
    <div class="kb-page-toolbar">
        <div class="kb-page-toolbar-left">
            <h2 class="kb-page-title"><?= $this->text->e($title ?? t('Dashboard')) ?></h2>
        </div>
        <div class="kb-page-toolbar-right">
            <?= $this->hook->render('template:dashboard:page-header:menu', array('user' => $user)) ?>
        </div>
    </div>
    <section class="kb-dashboard-content" id="dashboard">
        <?= $content_for_sublayout ?>
    </section>
</section>
