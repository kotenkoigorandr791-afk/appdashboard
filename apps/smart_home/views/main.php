<!DOCTYPE html>
<html lang="<?=sh_h($lang)?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?=sh_h($t['app_title'])?></title>
<link rel="icon" type="image/svg+xml" href="icon.svg">
<link rel="preload" as="image" href="<?=sh_h($theme === 'light' ? 'background_light_opt.jpg' : 'background_dark_opt.jpg')?>" fetchpriority="high">
<link rel="stylesheet" href="assets/css/app.css?v=20261023e">
<?php require_once dirname(__DIR__, 3) . '/lib/sentry_bootstrap.php'; app_sentry_browser_script('smart_home'); ?>
</head>
<body class="<?= $theme === 'light' ? 'light' : '' ?>">
<div id="app-bg" aria-hidden="true"></div>
<script>
(function(){var s=document.body.classList.contains('light')?'background_light_opt.jpg':'background_dark_opt.jpg',el=document.getElementById('app-bg'),im=new Image();im.decoding='async';im.onload=function(){el.style.backgroundImage='url('+s+')';el.classList.add('ready')};im.src=s})();
</script>

<!-- App Bar -->
<header class="app-bar">
  <h1 id="appTitle"><?=sh_h($t['app_title'])?></h1>
  <span class="badge-total" id="totalBadge" style="display:none">0</span>
  
  <!-- View Toggle: Tiles / Map -->
  <div class="view-toggle" id="viewToggle">
    <?php $homeViewIsMap = ($settings['home_view'] ?? 'tiles') === 'map'; ?>
    <button class="<?=$homeViewIsMap ? '' : 'active'?>" data-view="tiles" title="<?=sh_h($t['map_tiles'])?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
      <span><?=sh_h($t['map_tiles'])?></span>
    </button>
    <button class="<?=$homeViewIsMap ? 'active' : ''?>" data-view="map" title="<?=sh_h($t['map_view'])?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
      <span><?=sh_h($t['map_view'])?></span>
    </button>
  </div>

  <div style="flex:1"></div>

  <button class="menu-btn" id="addDeviceBtn" aria-label="Add device" title="<?=sh_h($t['add_device'])?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24" height="24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
  </button>
  <button class="menu-btn" id="menuToggle" aria-label="Menu">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24" height="24"><circle cx="12" cy="5" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="12" cy="19" r="1.5"/></svg>
  </button>
</header>

<!-- Drawer Overlay -->
<div class="drawer-overlay" id="drawerOverlay"></div>

<!-- Drawer -->
<nav class="drawer" id="drawer">
  <div class="drawer-header">
    <h2><?=sh_h($t['app_title'])?></h2>
    <p><?=sh_h($userEmail)?></p>
  </div>
  <div class="drawer-nav">
    <button class="drawer-item active" data-page="home">
      <span class="icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg></span> <?=sh_h($t['home'])?>
    </button>
    <button class="drawer-item" data-page="devices">
      <span class="icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg></span> <?=sh_h($t['devices'])?>
    </button>
    <button class="drawer-item" data-page="groups">
      <span class="icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg></span> <?=sh_h($t['groups'])?>
    </button>
    <button class="drawer-item" data-page="statistics">
      <span class="icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 20V10"/><path d="M12 20V4"/><path d="M6 20v-6"/></svg></span> <?=sh_h($t['statistics'])?>
    </button>
    <button class="drawer-item" data-page="errors">
      <span class="icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></span> <?=sh_h($t['logs'])?>
    </button>
    <button class="drawer-item" data-page="map">
      <span class="icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></span> <?=sh_h($t['floor_plan'])?>
    </button>
    <button class="drawer-item" data-page="settings">
      <span class="icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 01-2.83 2.83l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.32 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg></span> <?=sh_h($t['settings'])?>
    </button>
    <button class="drawer-item" data-page="share">
      <span class="icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.6" y1="10.7" x2="15.4" y2="6.3"/><line x1="8.6" y1="13.3" x2="15.4" y2="17.7"/></svg></span> Share
    </button>
  </div>
  <div class="drawer-footer">
    <button class="drawer-item" type="button" id="openApiDocsBtn">
      <span class="icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg></span> <?=sh_h($t['api_docs'])?>
    </button>
  </div>
</nav>

<!-- Main Content -->
<main class="main-content" id="mainContent">

  <!-- Home Page -->
  <div class="page-section active" id="page-home">
    <div class="section-stack">
      <div id="roomsContainer"></div>
    </div>
    <div id="homeEmptyState" class="empty-state" style="display:none">
      <div class="icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="width:48px;height:48px;margin-bottom:16px;opacity:.4"><path d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg></div>
      <p><?=sh_h($t['no_devices'])?></p>
    </div>
    <!-- Stats summary -->
    <div class="stats-strip">
      <div class="stat-card"><div class="stat-value" id="statTotal">0</div><div class="stat-label"><?=sh_h($t['devices_count'])?></div></div>
      <div class="stat-card"><div class="stat-value" id="statOnline" style="color:var(--success)">0</div><div class="stat-label"><?=sh_h($t['online_count'])?></div></div>
      <div class="stat-card"><div class="stat-value" id="statOffline" style="color:var(--danger)">0</div><div class="stat-label"><?=sh_h($t['offline_count'])?></div></div>
      <div class="stat-card"><div class="stat-value" id="statUnknown" style="color:var(--text-secondary)">0</div><div class="stat-label"><?=sh_h($t['unknown_count'])?></div></div>
    </div>
  </div>

  <!-- Devices Page -->
  <div class="page-section" id="page-devices">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; gap:12px; flex-wrap:wrap">
      <h2 style="font-size:18px; font-weight:500"><?=sh_h($t['devices'])?></h2>
      <div style="display:flex; gap:8px; flex-wrap:wrap">
        <input type="text" id="deviceSearch" placeholder="<?=sh_h($t['search'])?>..." style="padding:8px 14px; border:1px solid var(--line); border-radius:var(--radius-sm); background:var(--surface-2); color:var(--text); font-size:13px; outline:none; width:200px">
        <button class="btn btn-primary btn-sm" id="deviceTableAdd">+ <?=sh_h($t['add_device'])?></button>
      </div>
    </div>
    <div style="overflow-x:auto">
      <table class="data-table" id="deviceTable">
        <thead>
          <tr>
            <th></th>
            <th><?=sh_h($t['device_name'])?></th>
            <th><?=sh_h($t['device_category'])?></th>
            <th><?=sh_h($t['room'])?></th>
            <th><?=sh_h($t['device_type'])?></th>
            <th><?=sh_h($t['status'])?></th>
            <th><?=sh_h($t['mqtt_topic'])?></th>
            <th><?=sh_h($t['actions'])?></th>
          </tr>
        </thead>
        <tbody id="deviceTableBody"></tbody>
      </table>
    </div>
  </div>

  <!-- Groups Page -->
  <div class="page-section" id="page-groups">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px">
      <h2 style="font-size:18px; font-weight:500"><?=sh_h($t['groups'])?></h2>
      <button class="btn btn-primary btn-sm" id="createGroupBtn">+ <?=sh_h($t['create_group'])?></button>
    </div>
    <div id="groupsContainer"></div>
  </div>

  <!-- Statistics Page -->
  <div class="page-section" id="page-statistics">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px">
      <h2 style="font-size:18px; font-weight:500"><?=sh_h($t['statistics'])?></h2>
      <div style="display:flex; gap:8px; align-items:center">
        <label style="font-size:13px; color:var(--text-secondary)"><?=sh_h($t['period'])?>:</label>
        <select id="statsPeriod" style="padding:6px 12px; border:1px solid var(--line); border-radius:8px; background:var(--surface-2); color:var(--text); font-size:13px">
          <option value="1">1</option>
          <option value="6">6</option>
          <option value="24" selected>24</option>
          <option value="168">168 (7d)</option>
          <option value="720">720 (30d)</option>
        </select>
        <button class="btn btn-secondary btn-sm" id="refreshStats" style="width:36px;height:36px;padding:0;display:flex;align-items:center;justify-content:center"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M1 4v6h6M23 20v-6h-6"/><path d="M20.49 9A9 9 0 005.64 5.64L1 10m22 4l-4.64 4.36A9 9 0 013.51 15"/></svg></button>
      </div>
    </div>
    <div class="stats-grid" id="statsCards"></div>
    <div class="usage-card" id="usageCard">
      <h3 class="usage-title"><?=sh_h($t['usage_chart'])?></h3>
      <div id="usageChart"></div>
      <h4 class="usage-sub"><?=sh_h($t['usage_top'])?></h4>
      <div id="usageTop"></div>
    </div>
    <div style="margin-top:16px">
      <h3 style="font-size:15px; font-weight:500; margin-bottom:12px;display:flex;align-items:center;gap:8px">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18" style="color:var(--text-secondary)"><path d="M3 3v18h18"/><path d="M7 16l4-8 4 4 4-6"/></svg>
      <?=sh_h($t['param_history'])?></h3>
      <div id="paramHistoryContainer"></div>
    </div>
  </div>

  <!-- Errors Page -->
  <div class="page-section" id="page-errors">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px">
      <h2 style="font-size:18px; font-weight:500"><?=sh_h($t['logs'])?></h2>
      <div style="display:flex; gap:8px; align-items:center">
        <label style="font-size:13px; color:var(--text-secondary)"><?=sh_h($t['period'])?>:</label>
        <select id="errorsPeriod" style="padding:6px 12px; border:1px solid var(--line); border-radius:8px; background:var(--surface-2); color:var(--text); font-size:13px">
          <option value="24">24</option>
          <option value="168" selected>168 (7d)</option>
          <option value="720">720 (30d)</option>
        </select>
        <button class="btn btn-secondary btn-sm" id="refreshErrors" style="width:36px;height:36px;padding:0;display:flex;align-items:center;justify-content:center"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M1 4v6h6M23 20v-6h-6"/><path d="M20.49 9A9 9 0 005.64 5.64L1 10m22 4l-4.64 4.36A9 9 0 013.51 15"/></svg></button>
      </div>
    </div>
    <div class="log-filter" id="logFilter"></div>
    <div class="event-list log-list" id="errorsList"></div>
  </div>

  <div class="page-section" id="page-share"><div id="shareHost"></div></div>

  <!-- Settings Page -->
  <div class="page-section" id="page-settings">
    <h2 style="font-size:18px; font-weight:500; margin-bottom:20px"><?=sh_h($t['settings'])?></h2>
    <div class="settings-layout">
      <div class="settings-card">
        <p class="settings-card-title"><?=sh_h($t['home_scope_label'])?></p>
        <div class="form-group">
          <label><?=sh_h($t['home_scope_label'])?></label>
          <select id="settingHomeScope">
            <option value="own"<?=($settings['home_scope']??'own')!=='group'?' selected':''?>><?=sh_h($t['home_scope_own'])?></option>
            <option value="group"<?=($settings['home_scope']??'')==='group'?' selected':''?>><?=sh_h($t['home_scope_group'])?></option>
          </select>
        </div>
        <div class="form-group" id="settingHomeGroupWrap" style="display:<?=($settings['home_scope']??'')==='group'?'block':'none'?>">
          <label><?=sh_h($t['home_group_label'])?></label>
          <select id="settingHomeGroup"><option value="">—</option></select>
        </div>
      </div>
      <div class="settings-card">
        <p class="settings-card-title"><?=sh_h($t['home_view_label'])?></p>
        <div class="form-group">
          <label><?=sh_h($t['home_view_label'])?></label>
          <select id="settingHomeView">
            <option value="tiles"<?=($settings['home_view']??'tiles')!=='map'?' selected':''?>><?=sh_h($t['map_tiles'])?></option>
            <option value="map"<?=($settings['home_view']??'')==='map'?' selected':''?>><?=sh_h($t['map_view'])?></option>
          </select>
        </div>
      </div>
      <div class="settings-card" id="settingsDomainsWrap">
        <p class="settings-card-title"><?=sh_h($t['server_domain'])?></p>
        <div class="form-group">
          <div class="copy-field">
            <input type="text" id="settingServerDomain" readonly value="<?=sh_h(function_exists('app_base_domain')?app_base_domain():'localhost')?>">
            <button type="button" class="copy-field__btn" id="copyServerDomain" aria-label="Copy" title="Copy">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h10"/></svg>
            </button>
          </div>
        </div>
      </div>
      <aside class="map-props-panel" id="mapPropsPanel">
        <h3 id="mapPropsTitle"><?=sh_h($t['props_title'])?></h3>
        <div id="mapPropsBody" class="map-props-empty"><?=sh_h($t['props_none'])?></div>
        <div class="map-props-actions map-editor-only" id="mapPropsActions" style="display:none">
          <button type="button" class="btn btn-danger btn-sm" id="mapPropsDelete"><?=sh_h($t['props_delete'])?></button>
        </div>
      </aside>
    </div>
      </div>
</main>

<!-- Mobile map device popup (outside page sections for reliable stacking) -->
<div class="map-view-device-popup" id="mapDevicePopup" role="dialog" aria-modal="true" aria-labelledby="mapDevicePopupTitle">
  <div class="popup-backdrop" onclick="fpCloseDevicePopup()"></div>
  <div class="popup-content">
    <div class="popup-handle" aria-hidden="true"></div>
    <button type="button" class="popup-close" onclick="fpCloseDevicePopup()" aria-label="<?=sh_h($t['cancel'])?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
    <div id="mapDevicePopupBody"></div>
  </div>
</div>

<!-- Modal -->
<div class="modal-overlay" id="modalOverlay">
  <div class="modal" id="modalContent">
    <div class="modal-corner-slot" aria-hidden="true"></div>
    <div class="modal-topbar" id="modalTopBar" aria-hidden="true">
      <span class="modal-topbar-title" id="modalTopBarTitle"></span>
      <button class="modal-close modal-close--topbar" id="modalCloseTop" aria-label="<?=sh_h($t['cancel'])?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
    </div>
    <div class="modal-dialog-bar">
      <span class="modal-dialog-bar-title" id="modalDialogBarTitle"></span>
      <button class="modal-close modal-close--dialog" id="modalClose" aria-label="<?=sh_h($t['cancel'])?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
    </div>
    <button class="modal-close modal-close--apidocs" id="modalCloseApi" aria-label="<?=sh_h($t['cancel'])?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
    <div id="modalBody"></div>
  </div>
</div>
