<?php
final class SmartHomeSceneRenderer extends BaseRenderer implements AppRendererInterface
{
    public function render(): string
    {
        return $this->renderPage($this->share, $this->resource, $this->context);
    }

    public function renderPage(Share $share, Resource $resource, array $context): string
    {
        $handler = new SmartHomeSceneShareHandler();
        $data = $handler->loadData($share, $resource, $context);
        $devices = $data['devices'] ?? [];
        $online = count(array_filter($devices, static fn(array $d): bool => ($d['status'] ?? '') === 'online'));
        $rooms = [];
        foreach ($devices as $device) {
            $rooms[(string)($device['room'] ?: 'Other')][] = $device;
        }
        $deviceHtml = '';
        foreach ($rooms as $room => $items) {
            $deviceHtml .= '<section class="sp-room"><h3 class="md3-typescale-title-medium">' . sp_h((string)$room) . '</h3><div class="sp-device-grid">';
            foreach ($items as $device) {
                $status = (string)($device['status'] ?? 'unknown');
                $chipClass = $status === 'online' ? 'md3-chip--assist' : 'md3-chip--filter';
                $params = '';
                foreach (array_slice($device['params'] ?? [], 0, 4) as $param) {
                    if (!is_array($param)) continue;
                    $params .= '<span class="md3-chip md3-chip--suggestion">' . sp_h((string)($param['label'] ?? $param['id'] ?? 'param')) . ': '
                        . sp_h((string)($param['value'] ?? '')) . '</span>';
                }
                $deviceHtml .= '<article class="md3-card md3-card--outlined sp-device">
  <div class="md3-card__content">
    <div class="sp-device__head">
      <span class="material-symbols-outlined sp-device__icon">' . sp_h($this->deviceIcon((string)($device['type'] ?? ''))) . '</span>
      <div><h4 class="md3-typescale-title-medium">' . sp_h((string)$device['name']) . '</h4><span class="md3-typescale-body-small sp-muted">' . sp_h((string)($device['type'] ?? '')) . '</span></div>
      <span class="md3-chip ' . $chipClass . '"><span class="material-symbols-outlined">' . ($status === 'online' ? 'wifi' : 'wifi_off') . '</span>' . sp_h(sp_t($status === 'online' ? 'online' : 'offline')) . '</span>
    </div>
    <div class="sp-chips">' . $params . '</div>
  </div>
</article>';
            }
            $deviceHtml .= '</div></section>';
        }
        $content = '<div class="sp-app-page sp-smart-home" data-share-id="' . (int)$share->id . '">
  <section class="sp-app-head md3-card md3-card--elevated">
    <div>
      <div class="sp-chips">' . $this->dataModeBadge() . '<span class="md3-chip md3-chip--assist"><span class="material-symbols-outlined">devices</span>' . count($devices) . ' ' . sp_h(sp_t('devices')) . '</span><span class="md3-chip md3-chip--filter"><span class="material-symbols-outlined">wifi</span>' . $online . ' ' . sp_h(sp_t('online')) . '</span></div>
      <h2 class="md3-typescale-headline-medium">' . sp_h($this->pageTitle()) . '</h2>
      ' . ($share->description !== '' ? '<p class="md3-typescale-body-large sp-muted">' . nl2br(sp_h($share->description)) . '</p>' : '') . '
      ' . $this->permissionChips() . '
      ' . (($data['read_only'] ?? true) ? '<p class="md3-typescale-body-medium sp-muted"><span class="material-symbols-outlined sp-inline-icon">lock</span>' . sp_h(sp_t('read_only')) . '</p>' : '') . '
    </div>
    <div class="sp-app-head__actions"><button class="md3-btn md3-btn--text" data-sp-print><span class="material-symbols-outlined">print</span>' . sp_h(sp_t('print')) . '</button></div>
  </section>
  ' . ($deviceHtml !== '' ? $deviceHtml : '<div class="sp-empty sp-empty--inline"><span class="material-symbols-outlined sp-empty__icon">devices_other</span><p class="md3-typescale-body-large">' . sp_h(sp_t('no_items')) . '</p></div>') . '
</div>';
        return $this->layout($content, ['title' => $this->pageTitle(), 'body_class' => 'sp-app-page-body']);
    }

    private function deviceIcon(string $type): string
    {
        $type = strtolower($type);
        return match (true) {
            str_contains($type, 'light') => 'lightbulb',
            str_contains($type, 'thermo') => 'thermostat',
            str_contains($type, 'lock') => 'lock',
            str_contains($type, 'camera') => 'videocam',
            str_contains($type, 'sensor') => 'sensors',
            str_contains($type, 'switch') => 'toggle_on',
            default => 'smart_toy',
        };
    }
}
