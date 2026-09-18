<?php if (! $is_ajax): ?>
    <div class="page-header">
        <h2><?= t('Task distribution') ?></h2>
    </div>
<?php endif ?>

<?php if (empty($metrics)): ?>
    <p class="alert"><?= t('Not enough data to show the graph.') ?></p>
<?php else: ?>
    <?= $this->app->component('chart-project-task-distribution', array(
        'metrics' => $metrics,
    )) ?>

    <div class="kb-table-card">
        <table class="table-striped kb-data-table">
            <thead>
                <tr>
                    <th><?= t('Column') ?></th>
                    <th><?= t('Number of tasks') ?></th>
                    <th><?= t('Percentage') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($metrics as $metric): ?>
                <tr>
                    <td><?= $this->text->e($metric['column_title']) ?></td>
                    <td><?= $metric['nb_tasks'] ?></td>
                    <td>
                        <div class="kb-pct">
                            <span class="kb-pct-bar" style="--kb-pct: <?= (float) $metric['percentage'] ?>%"></span>
                            <span class="kb-pct-label"><?= n($metric['percentage']) ?>%</span>
                        </div>
                    </td>
                </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
<?php endif ?>
