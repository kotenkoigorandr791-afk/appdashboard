<div class="page-header kb-search-header">
    <h2><?= t('Search') ?></h2>
</div>

<div class="filter-box margin-bottom kb-search-filter">
    <form method="get" action="<?= $this->url->dir() ?>" class="search">
        <?= $this->form->hidden('controller', $values) ?>
        <?= $this->form->hidden('action', $values) ?>
        <div class="input-addon">
            <?= $this->form->text('search', $values, array(), array(empty($values['search']) ? 'autofocus' : '', 'placeholder="'.t('Search').'"', 'aria-label="'.t('Search').'"'), 'input-addon-field') ?>
            <div class="input-addon-item">
                <?= $this->render('app/filters_helper') ?>
            </div>
        </div>
    </form>
</div>

<?php if (empty($values['search'])): ?>
    <?php $tiles = $this->ProjectTilesHelper->forCurrentUser(1); ?>
    <div class="kb-project-tiles" id="kb-project-tiles">
        <?php if (empty($tiles)): ?>
            <p class="kb-cycle-empty"><?= t('There is nothing assigned to you.') ?></p>
        <?php else: ?>
            <?php foreach ($tiles as $tile): ?>
                <article class="kb-project-tile" data-project-id="<?= (int) $tile['id'] ?>">
                    <button type="button" class="kb-project-tile-head" aria-expanded="false">
                        <span class="kb-project-tile-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3.5 7.5a2 2 0 0 1 2-2h5l2 2h6a2 2 0 0 1 2 2v7a2 2 0 0 1-2 2h-13a2 2 0 0 1-2-2z"/></svg></span>
                        <span class="kb-project-tile-name"><?= $this->text->e($tile['name']) ?></span>
                        <span class="kb-project-tile-meta"><?= (int) $tile['open'] ?> <?= t('Open tasks') ?></span>
                        <span class="kb-project-tile-caret" aria-hidden="true"></span>
                    </button>
                    <div class="kb-project-tile-body" hidden>
                        <?php foreach ($tile['groups'] as $group): ?>
                            <?php $groupIcon = match ($group['status']) {
                                'backlog' => '<path d="M4 6h16v12H4z"/><path d="M8 11h8"/>',
                                'todo' => '<circle cx="12" cy="12" r="8.5"/><path d="M9 12h6"/>',
                                'progress' => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7v5l3 2"/>',
                                default => '<circle cx="12" cy="12" r="8.5"/><path d="m8.5 12 2.2 2.2 4.8-4.8"/>',
                            }; ?>
                            <div class="kb-project-tile-group is-<?= $this->text->e($group['status']) ?>">
                                <div class="kb-project-tile-group-title">
                                    <span class="kb-project-tile-group-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><?= $groupIcon ?></svg></span>
                                    <span><?= $this->text->e($group['title']) ?></span>
                                    <span class="kb-issue-group-count"><?= (int) $group['count'] ?></span>
                                </div>
                                <?php if (!empty($group['tasks'])): ?>
                                    <?php foreach ($group['tasks'] as $task): ?>
                                        <a class="kb-project-tile-task" href="<?= $this->url->href('TaskViewController', 'show', ['task_id' => $task['id']]) ?>">
                                            <?= $this->text->e($task['title']) ?>
                                        </a>
                                    <?php endforeach ?>
                                <?php else: ?>
                                    <span class="kb-project-tile-empty"><?= t('No tasks') ?></span>
                                <?php endif ?>
                            </div>
                        <?php endforeach ?>
                        <a class="kb-project-tile-open" href="<?= $this->text->e($tile['board_url']) ?>"><?= t('Open board') ?></a>
                    </div>
                </article>
            <?php endforeach ?>
        <?php endif ?>
    </div>
<?php elseif (! empty($values['search']) && $paginator->isEmpty()): ?>
    <p class="alert"><?= t('Nothing found.') ?></p>
<?php elseif (! $paginator->isEmpty()): ?>
    <?= $this->render('search/results', array(
        'paginator' => $paginator,
    )) ?>
<?php endif ?>
