<?php if (empty($events)): ?>
    <p class="alert"><?= t('There is no activity yet.') ?></p>
<?php else: ?>
    <?php $lastMonth = ''; $lastDay = ''; ?>
    <?php foreach ($events as $event): ?>
        <?php
        $ts = (int) $event['date_creation'];
        $month = date('Y-m', $ts);
        $day = date('Y-m-d', $ts);
        ?>
        <?php if ($month !== $lastMonth): $lastMonth = $month; $lastDay = ''; ?>
            <h3 class="kb-activity-month"><?= t(date('F', $ts)) ?> <?= date('Y', $ts) ?></h3>
        <?php endif ?>
        <?php if ($day !== $lastDay): $lastDay = $day; ?>
            <div class="kb-activity-day"><?= $day === date('Y-m-d') ? t('Today') : ($day === date('Y-m-d', strtotime('-1 day')) ? t('Yesterday') : $this->dt->date($ts)) ?></div>
        <?php endif ?>
        <div class="activity-event">
            <?= $this->avatar->render(
                $event['creator_id'],
                $event['author_username'],
                $event['author_name'],
                $event['email'],
                $event['avatar_path']
            ) ?>

            <div class="activity-content">
                <?= $event['event_content'] ?>
            </div>
        </div>
    <?php endforeach ?>
<?php endif ?>