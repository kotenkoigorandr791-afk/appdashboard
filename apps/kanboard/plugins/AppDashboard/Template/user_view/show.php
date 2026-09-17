<div class="page-header">
    <h2><?= t('Summary') ?></h2>
</div>
<ul class="panel kb-summary-panel">
    <li><?= t('Full Name:') ?> <strong><?= $this->text->e($user['name']) ?: t('None') ?></strong></li>
    <li><?= t('Email:') ?> <strong><?= $this->text->e($user['email']) ?: t('None') ?></strong></li>
</ul>
<p class="alert alert-info"><?= t('Account settings are managed by the main dashboard.') ?></p>
