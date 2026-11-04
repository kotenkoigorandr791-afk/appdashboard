<div class="dropdown">
    <a href="#" class="dropdown-menu dropdown-menu-link-icon"><?= $this->avatar->currentUserSmall('avatar-inline') ?><i class="fa fa-caret-down"></i></a>
    <ul>
        <li class="no-hover"><strong><?= $this->text->e($this->user->getFullname()) ?></strong></li>
        <li>
            <?= $this->url->icon('tachometer', t('My dashboard'), 'DashboardController', 'show', array('user_id' => $this->user->getId())) ?>
        </li>
        <li>
            <a href="#" data-kb-open="mobile"><i class="fa fa-mobile fa-fw"></i><?= t('Mobile app') ?></a>
        </li>
        <li>
            <a href="#" data-kb-open="profile"><i class="fa fa-cog fa-fw"></i><?= t('Settings') ?></a>
        </li>
        <?= $this->hook->render('template:header:dropdown') ?>
    </ul>
</div>
