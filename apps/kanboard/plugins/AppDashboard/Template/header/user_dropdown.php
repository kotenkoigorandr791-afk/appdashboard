<div class="dropdown">
    <a href="#" class="dropdown-menu dropdown-menu-link-icon"><?= $this->avatar->currentUserSmall('avatar-inline') ?><i class="fa fa-caret-down"></i></a>
    <ul>
        <li class="no-hover"><strong><?= $this->text->e($this->user->getFullname()) ?></strong></li>
        <li>
            <?= $this->url->icon('tachometer', t('My dashboard'), 'DashboardController', 'show', array('user_id' => $this->user->getId())) ?>
        </li>
        <li>
            <?= $this->url->icon('folder', t('Projects management'), 'ProjectListController', 'show') ?>
        </li>
        <?= $this->hook->render('template:header:dropdown') ?>
    </ul>
</div>
