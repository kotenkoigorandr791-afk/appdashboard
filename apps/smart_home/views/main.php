<!DOCTYPE html>
<html lang="<?=sh_h($lang)?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?=sh_h($t['app_title'])?></title>
<link rel="icon" type="image/svg+xml" href="icon.svg">
<link rel="preload" as="image" href="<?=sh_h($theme === 'light' ? 'background_light_opt.jpg' : 'background_dark_opt.jpg')?>" fetchpriority="high">
<link rel="stylesheet" href="assets/css/app.css?v=20260920c">
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
      <span class="icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></span> <?=sh_h($t['errors'])?>
    </button>
    <button class="drawer-item" data-page="map">
      <span class="icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></span> <?=sh_h($t['floor_plan'])?>
    </button>
    <button class="drawer-item" data-page="settings">
      <span class="icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 01-2.83 2.83l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.32 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg></span> <?=sh_h($t['settings'])?>
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
    <div style="margin-top:16px">
      <h3 style="font-size:15px; font-weight:500; margin-bottom:12px;display:flex;align-items:center;gap:8px">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18" style="color:var(--text-secondary)"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
      <?=sh_h($t['events'])?></h3>
      <div class="event-list" id="eventsList"></div>
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
      <h2 style="font-size:18px; font-weight:500"><?=sh_h($t['errors'])?></h2>
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
    <div class="event-list" id="errorsList"></div>
  </div>

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
          <label><?=sh_h($t['server_domain'])?></label>
          <input type="text" id="settingServerDomain" value="<?=sh_h($settings['server_domain']??(function_exists('sh_default_domain')?sh_default_domain():'localhost'))?>"<?=$canEditEndpoints?'':' disabled'?>">
        </div>
        <div class="form-group">
          <label><?=sh_h($t['ap_domain'])?></label>
          <input type="text" id="settingApDomain" value="<?=sh_h($settings['ap_domain']??(function_exists('sh_default_domain')?sh_default_domain():'localhost'))?>"<?=$canEditEndpoints?'':' disabled'?>">
        </div>
        <?php if (!$canEditEndpoints): ?>
        <p id="settingsEndpointsHint" style="font-size:12px;color:var(--text-secondary);margin:0"><?=sh_h($t['endpoints_locked'])?></p>
        <?php endif; ?>
      </div>
      <button class="btn btn-primary" id="saveSettingsBtn"><?=sh_h($t['save'])?></button>
    </div>
  </div>

  <!-- Map / Floor Plan Page -->
  <div class="page-section" id="page-map">
    <div class="map-editor-only" style="display:flex; justify-content:space-between; align-items:center; margin-top:16px; margin-bottom:16px; gap:12px; flex-wrap:wrap">
      <h2 style="font-size:18px; font-weight:500; display:flex; align-items:center; gap:8px">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20" style="color:var(--primary)"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
        <?=sh_h($t['floor_plan'])?>
      </h2>
      <div style="display:flex; gap:8px">
        <button class="btn btn-secondary btn-sm" id="mapClearBtn">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
          <?=sh_h($t['map_clear'])?>
        </button>
        <button class="btn btn-primary btn-sm" id="mapSaveBtn">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
          <?=sh_h($t['map_save'])?>
        </button>
      </div>
    </div>

    <!-- Toolbar -->
    <div class="map-toolbar" id="mapToolbar">
      <div class="tool-group map-editor-only">
        <button class="map-tool-btn active" data-tool="select">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6 4 L6 17.5 L9.5 14.8 L12 19.5 L14 18.5 L11.5 13.8 L17 13.3 Z"/></svg>
          <span class="tooltip"><?=sh_h($t['map_select'])?></span>
        </button>
        <button class="map-tool-btn" data-tool="wall">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="9" width="20" height="6" rx="1"/><line x1="2" y1="12" x2="22" y2="12"/></svg>
          <span class="tooltip"><?=sh_h($t['map_wall'])?></span>
        </button>
        <button class="map-tool-btn" data-tool="window">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="4" width="14" height="16" rx="1"/><line x1="12" y1="4" x2="12" y2="20"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          <span class="tooltip"><?=sh_h($t['map_window'])?></span>
        </button>
        <button class="map-tool-btn" data-tool="door">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4v16"/><path d="M4 4h14a2 2 0 012 2v12"/><circle cx="16" cy="12" r="1" fill="currentColor"/><path d="M4 20h16"/></svg>
          <span class="tooltip"><?=sh_h($t['map_door'])?></span>
        </button>
        <button class="map-tool-btn" data-tool="furniture">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="6" y="7" width="12" height="10" rx="0.8"/><path d="M4.5 7 L4.5 17 M3.7 7 L5.3 7 M3.7 17 L5.3 17" stroke-width="1.2"/><path d="M6 18.5 L18 18.5 M6 17.7 L6 19.3 M18 17.7 L18 19.3" stroke-width="1.2"/></svg>
          <span class="tooltip"><?=sh_h($t['map_furniture'])?></span>
        </button>
        <button class="map-tool-btn" data-tool="furniture-poly">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 15.5 L5 8.5 L9 6 L18 7.5 L18 16 L12.5 18 Z" stroke-dasharray="2.5 2.5"/><circle cx="5" cy="15.5" r="0.9" fill="currentColor" stroke="none"/><circle cx="5" cy="8.5" r="0.9" fill="currentColor" stroke="none"/><circle cx="9" cy="6" r="0.9" fill="currentColor" stroke="none"/><circle cx="18" cy="7.5" r="0.9" fill="currentColor" stroke="none"/><circle cx="18" cy="16" r="0.9" fill="currentColor" stroke="none"/><circle cx="12.5" cy="18" r="0.9" fill="currentColor" stroke="none"/><path d="M16 3.5 L20 3.5 M18 1.5 L18 5.5" stroke-width="1.3"/></svg>
          <span class="tooltip"><?=sh_h($t['map_furniture_poly'])?></span>
        </button>
        <button class="map-tool-btn" data-tool="room">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="5" width="14" height="14" rx="0.8" stroke-dasharray="2 2"/><path d="M3 5 L3 19 M2.2 5 L3.8 5 M2.2 19 L3.8 19" stroke-width="1.2"/><path d="M5 21 L19 21 M5 20.2 L5 21.8 M19 20.2 L19 21.8" stroke-width="1.2"/></svg>
          <span class="tooltip"><?=sh_h($t['map_room'])?></span>
        </button>
        <button class="map-tool-btn" data-tool="room-poly">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 15.5 L5 8.5 L9 6 L18 7.5 L18 16 L12.5 18 Z" stroke-dasharray="2.5 2.5"/><circle cx="5" cy="15.5" r="0.9" fill="currentColor" stroke="none"/><circle cx="5" cy="8.5" r="0.9" fill="currentColor" stroke="none"/><circle cx="9" cy="6" r="0.9" fill="currentColor" stroke="none"/><circle cx="18" cy="7.5" r="0.9" fill="currentColor" stroke="none"/><circle cx="18" cy="16" r="0.9" fill="currentColor" stroke="none"/><circle cx="12.5" cy="18" r="0.9" fill="currentColor" stroke="none"/></svg>
          <span class="tooltip"><?=sh_h($t['map_room_poly'])?></span>
        </button>
        <button class="map-tool-btn" data-tool="floor">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="14" height="14" rx="1"/><path d="M3 8.5 L17 8.5 M3 13 L17 13" stroke-width="1.1"/><path d="M8 3 L8 8.5 M12.5 8.5 L12.5 13 M8 13 L8 17" stroke-width="1.1"/><path d="M18 15.5 L23 20.5 L20.5 23 L15.5 18 Z" fill="currentColor" fill-opacity="0.12"/><path d="M15.5 18 L13 20.5 L15 22.5 L17.5 20" stroke-width="1.3"/></svg>
          <span class="tooltip"><?=sh_h($t['map_floor'])?></span>
        </button>
        <button class="map-tool-btn" data-tool="room-label">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 5h12"/><path d="M12 5v14"/><path d="M8 19h8"/></svg>
          <span class="tooltip"><?=sh_h($t['props_text'])?></span>
        </button>
        <button type="button" class="map-tool-btn" id="mapPlaceDeviceBtn">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M12 2v4M12 18v4M2 12h4M18 12h4"/></svg>
          <span class="tooltip"><?=sh_h($t['map_add_device'])?></span>
        </button>
      </div>
      <div class="tool-group map-editor-only">
        <button class="map-tool-btn" id="mapUndoBtn">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 102.13-9.36L1 10"/></svg>
          <span class="tooltip"><?=sh_h($t['map_undo'])?></span>
        </button>
        <button class="map-tool-btn" id="mapRedoBtn">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 11-2.12-9.36L23 10"/></svg>
          <span class="tooltip"><?=sh_h($t['map_redo'])?></span>
        </button>
      </div>
    </div>

    <div class="map-editor-layout">
      <div>
        <div class="canvas-wrap" id="canvasWrap">
          <div class="canvas-stage" id="canvasStage">
            <canvas id="floorPlanCanvas"></canvas>
          </div>
          <div class="map-zoom-stack" id="mapZoomStack">
            <button type="button" class="map-zoom-btn" id="mapZoomInBtn" title="+">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            </button>
            <button type="button" class="map-zoom-btn" id="mapZoomOutBtn" title="-">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"/></svg>
            </button>
          </div>
          <div class="map-statusbar map-editor-only">
            <span id="mapStatus"><?=sh_h($t['map_status_hint'])?></span>
            <span id="mapCoords">X: 0, Y: 0</span>
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
    </div>
    <button class="modal-close" id="modalClose"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
    <div id="modalBody"></div>
  </div>
</div>
