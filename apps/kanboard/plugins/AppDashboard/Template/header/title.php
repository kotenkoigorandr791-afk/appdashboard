<h1>
    <span class="title<?= !empty($project) && empty($task) ? ' kb-project-title' : '' ?>">
        <?php if (! empty($project) && ! empty($task)): ?>
            <?= $this->url->link($this->text->e($project['name']), 'BoardViewController', 'show', array('project_id' => $project['id'])) ?>
        <?php elseif (! empty($project) && empty($task)): ?>
            <span class="kb-project-mark" aria-hidden="true"><i class="fa fa-columns"></i></span>
            <span class="kb-project-name"><?= $this->text->e($project['name'] ?: $title) ?></span>
            <?php if ($project['task_limit'] && array_key_exists('nb_active_tasks', $project)): ?>
              <span class="kb-project-limit">(<span><?= intval($project['nb_active_tasks']) ?></span> / <span title="<?= t('Task limit') ?>"><span class="ui-helper-hidden-accessible"><?= t('Task limit') ?> </span><?= $this->text->e($project['task_limit']) ?></span>)</span>
            <?php endif ?>
        <?php else: ?>
            <?= $this->text->e($title) ?>
        <?php endif ?>
    </span>
    <?php if (! empty($description)): ?>
        <?= $this->app->tooltipHTML($description) ?>
    <?php endif ?>
</h1>
