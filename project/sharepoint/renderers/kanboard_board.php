<?php
final class KanboardBoardRenderer extends BaseRenderer implements AppRendererInterface
{
    private const COLORS = [
        'yellow' => '#f1c40f',
        'blue' => '#3498db',
        'green' => '#2ecc71',
        'purple' => '#9b59b6',
        'red' => '#e74c3c',
        'orange' => '#f39c12',
        'grey' => '#95a5a6',
        'brown' => '#8b6f47',
        'deep_orange' => '#e67e22',
        'dark_grey' => '#5d6d7e',
        'pink' => '#fd79a8',
        'teal' => '#16a085',
        'cyan' => '#22b8cf',
        'lime' => '#a3cb38',
        'light_green' => '#7bed9f',
        'amber' => '#ffbe0b',
    ];

    public function render(): string
    {
        return $this->renderPage($this->share, $this->resource, $this->context);
    }

    public function renderPage(Share $share, Resource $resource, array $context): string
    {
        $handler = new KanboardBoardShareHandler();
        $data = $handler->loadData($share, $resource, $context);
        $project = $data['project'] ?? ['name' => $this->pageTitle(), 'description' => '', 'is_active' => true];
        $swimlanes = $data['swimlanes'] ?? [];
        $taskCount = (int)($data['task_count'] ?? 0);
        $showSwimlaneNames = count($swimlanes) > 1;

        $board = '';
        foreach ($swimlanes as $swimlane) {
            $board .= '<section class="sp-kb-swimlane">';
            if ($showSwimlaneNames) {
                $board .= '<h3 class="sp-kb-swimlane__title">' . sp_h($swimlane['name']) . '</h3>';
            }
            $board .= '<div class="sp-kb-columns">';
            foreach ($swimlane['columns'] as $column) {
                $board .= '<div class="sp-kb-column">'
                    . '<div class="sp-kb-column__head"><span class="sp-kb-column__title">' . sp_h($column['title']) . '</span>'
                    . '<span class="sp-kb-column__count">' . count($column['tasks']) . '</span></div>'
                    . '<div class="sp-kb-column__body">';
                foreach ($column['tasks'] as $task) {
                    $board .= $this->renderTask($task);
                }
                $board .= '</div></div>';
            }
            $board .= '</div></section>';
        }

        if ($board === '') {
            $board = '<section class="sp-empty md3-card md3-card--outlined">'
                . '<span class="material-symbols-outlined sp-empty__icon" aria-hidden="true">view_kanban</span>'
                . '<p class="md3-typescale-body-large">' . sp_h(sp_t('no_results')) . '</p></section>';
        }

        $status = !empty($project['is_active'])
            ? '<span class="md3-chip md3-chip--assist"><span class="material-symbols-outlined">check_circle</span>' . sp_h(sp_t('active')) . '</span>'
            : '<span class="md3-chip md3-chip--filter"><span class="material-symbols-outlined">block</span>' . sp_h(sp_t('closed')) . '</span>';

        $content = '<div class="sp-app-page sp-kb-page" data-share-id="' . (int)$share->id . '">
  <section class="sp-app-head md3-card md3-card--elevated">
    <div>
      <div class="sp-chips">' . $this->dataModeBadge() . $status
            . '<span class="md3-chip md3-chip--filter"><span class="material-symbols-outlined">task_alt</span>' . $taskCount . '</span></div>'
            . '<h2 class="md3-typescale-headline-medium">' . sp_h($project['name'] !== '' ? $project['name'] : $this->pageTitle()) . '</h2>'
            . (!empty($project['description']) ? '<p class="md3-typescale-body-large sp-muted">' . nl2br(sp_h($project['description'])) . '</p>' : '')
            . $this->permissionChips() . '
    </div>
  </section>
  <div class="sp-kb-board">' . $board . '</div>
</div>';

        return $this->layout($content, ['title' => $this->pageTitle(), 'body_class' => 'sp-app-page-body sp-kb-page-body']);
    }

    private function renderTask(array $task): string
    {
        $color = self::COLORS[$task['color_id']] ?? '';
        $style = $color !== '' ? ' style="--kb-task-color:' . sp_h($color) . '"' : '';
        $meta = '<span class="sp-kb-task__id">#' . (int)$task['id'] . '</span>';
        if ($task['reference'] !== '') {
            $meta .= '<span class="sp-kb-task__reference">' . sp_h($task['reference']) . '</span>';
        }
        $footer = '';
        if ($task['assignee'] !== '') {
            $footer .= '<span class="sp-kb-task__assignee"><span class="material-symbols-outlined">person</span>' . sp_h($task['assignee']) . '</span>';
        }
        if ($task['date_due'] > 0) {
            $footer .= '<span class="sp-kb-task__due"><span class="material-symbols-outlined">event</span>' . sp_h(date('M j, Y', (int)$task['date_due'])) . '</span>';
        }
        if ($task['priority'] > 0) {
            $footer .= '<span class="sp-kb-task__priority">P' . (int)$task['priority'] . '</span>';
        }
        return '<article class="sp-kb-task' . ($color !== '' ? ' has-color' : '') . '"' . $style . '>'
            . '<div class="sp-kb-task__meta">' . $meta . '</div>'
            . '<div class="sp-kb-task__title">' . sp_h($task['title']) . '</div>'
            . ($footer !== '' ? '<div class="sp-kb-task__footer">' . $footer . '</div>' : '')
            . '</article>';
    }
}
