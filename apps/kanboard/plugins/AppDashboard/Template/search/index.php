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
                        <span class="kb-project-tile-name"><?= $this->text->e($tile['name']) ?></span>
                        <span class="kb-project-tile-meta"><?= (int) $tile['open'] ?> <?= t('Open tasks') ?></span>
                        <span class="kb-project-tile-caret" aria-hidden="true"></span>
                    </button>
                    <div class="kb-project-tile-body" hidden>
                        <?php foreach ($tile['groups'] as $group): ?>
                            <div class="kb-project-tile-group">
                                <div class="kb-project-tile-group-title">
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
