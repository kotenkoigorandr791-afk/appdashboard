<?php if (empty($events)): ?>
    <p class="alert"><?= t('There is no activity yet.') ?></p>
<?php else: ?>
    <?php $lastMonth = !empty($prev_ts) ? date('Y-m', (int) $prev_ts) : ''; $lastDay = !empty($prev_ts) ? date('Y-m-d', (int) $prev_ts) : ''; ?>
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
<?php if (!empty($has_more) && !empty($next_url)): ?>
        <div class="kb-lazy-sentinel" data-kb-lazy-url="<?= $this->text->e($next_url) ?>" data-prev-ts="<?= (int) end($events)['date_creation'] ?>"></div>
    <?php endif ?>
<?php endif ?>
