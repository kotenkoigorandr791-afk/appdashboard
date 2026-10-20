<?php
require_once __DIR__ . '/views/error.php';

if (!sp_is_admin()) {
    echo sp_render_status_page('not_found', null, 404);
    exit;
}

$repo = new ShareRepository();

if (isset($_GET['api'])) {
    $api = strtolower((string)$_GET['api']);
    if ($api === 'stats') {
        sp_json_response(['ok' => true, 'stats' => $repo->stats()]);
    }
    if ($api === 'list') {
        $filters = [
            'resource_app' => (string)($_GET['resource_app'] ?? 'all'),
            'resource_type' => (string)($_GET['resource_type'] ?? 'all'),
            'status' => (string)($_GET['status'] ?? ''),
        ];
        $shares = $repo->all($filters);
        $service = new ShareService($repo);
        $rows = [];
        foreach ($shares as $share) {
            $row = $share->toArray();
            $row['link'] = sp_public_url($share->token);
            $row['status'] = $share->isRevoked() ? 'revoked' : ($share->isExpired() ? 'expired' : 'active');
            $row['app_label'] = ShareRegistry::appMeta($share->resource_app)['label'];
            $row['app_icon'] = ShareRegistry::appMeta($share->resource_app)['icon'];
            $row['entity_label'] = ShareRegistry::entityCatalog()[$share->entity_type] ?? $share->entity_type;
            $rows[] = $row;
        }
        sp_json_response(['ok' => true, 'shares' => $rows]);
    }
    if ($api === 'action') {
        if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
            sp_json_response(['ok' => false, 'error' => 'method_not_allowed'], 405);
        }
        $csrf = (string)($_SERVER['HTTP_X_SHAREPOINT_CSRF'] ?? '');
        if (!sp_verify_csrf($csrf)) {
            sp_json_response(['ok' => false, 'error' => 'invalid_request'], 403);
        }
        $input = sp_request_body();
        $op = (string)($input['op'] ?? '');
        $ids = $input['ids'] ?? [];
        if (!is_array($ids)) $ids = [$ids];
        if ($op === 'revoke') {
            $repo->bulkRevoke($ids);
        } elseif ($op === 'delete') {
            $repo->bulkDelete($ids);
        } elseif ($op === 'delete_app') {
            $repo->deleteByApp((string)($input['app'] ?? ''));
        } else {
            sp_json_response(['ok' => false, 'error' => 'unsupported_action'], 400);
        }
        sp_json_response(['ok' => true]);
    }
    sp_json_response(['ok' => false, 'error' => 'not_found'], 404);
}

$catalog = ShareRegistry::appCatalog();
$chips = '<label class="md3-chip md3-chip--filter md3-chip--selected" data-filter="app" data-value="all"><input type="radio" name="app_filter" value="all" checked style="display:none">All</label>';
foreach ($catalog as $appId => $meta) {
    $chips .= '<label class="md3-chip md3-chip--filter" data-filter="app" data-value="' . sp_h($appId) . '"><input type="radio" name="app_filter" value="' . sp_h($appId) . '" style="display:none"><span class="material-symbols-outlined">' . sp_h($meta['icon']) . '</span>' . sp_h($meta['label']) . '</label>';
}
$typeChips = '<label class="md3-chip md3-chip--filter md3-chip--selected" data-filter="type" data-value="all"><input type="radio" name="type_filter" value="all" checked style="display:none">All</label>'
    . '<label class="md3-chip md3-chip--filter" data-filter="type" data-value="file"><span class="material-symbols-outlined">insert_drive_file</span>File</label>'
    . '<label class="md3-chip md3-chip--filter" data-filter="type" data-value="folder"><span class="material-symbols-outlined">folder</span>Folder</label>'
    . '<label class="md3-chip md3-chip--filter" data-filter="type" data-value="app"><span class="material-symbols-outlined">apps</span>App</label>';
$statusChips = '<label class="md3-chip md3-chip--filter md3-chip--selected" data-filter="status" data-value=""><input type="radio" name="status_filter" value="" checked style="display:none">All</label>'
    . '<label class="md3-chip md3-chip--filter" data-filter="status" data-value="active"><span class="material-symbols-outlined">check_circle</span>Active</label>'
    . '<label class="md3-chip md3-chip--filter" data-filter="status" data-value="expired"><span class="material-symbols-outlined">schedule</span>Expired</label>'
    . '<label class="md3-chip md3-chip--filter" data-filter="status" data-value="revoked"><span class="material-symbols-outlined">block</span>Revoked</label>';

$html = '<!DOCTYPE html>
<html lang="' . sp_h(sp_lang()) . '">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="sp-csrf" content="' . sp_h(sp_csrf_token()) . '">
<title>Shared links · SharePoint</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap" rel="stylesheet">
<link rel="stylesheet" href="' . sp_h(sp_asset('css/md3-tokens.css')) . '">
<link rel="stylesheet" href="' . sp_h(sp_asset('css/md3-components.css')) . '">
<link rel="stylesheet" href="' . sp_h(sp_asset('css/md3-layout.css')) . '">
<link rel="stylesheet" href="' . sp_h(sp_asset('css/sharepoint.css')) . '">
<style>
  .sp-admin-head{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:16px}
  .sp-admin-filters{display:grid;gap:12px;padding:16px;margin-bottom:16px}
  .sp-admin-table-wrap{overflow-x:auto}
  .sp-admin-table td{white-space:nowrap}
  .sp-admin-table td.sp-admin-title{max-width:320px;white-space:normal}
  .sp-admin-bulk{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:10px 16px;border-top:1px solid var(--md-sys-color-outline-variant);display:none}
  .sp-admin-bulk--visible{display:flex}
  .sp-row-actions{display:flex;gap:2px}
  .sp-admin-empty{padding:48px;text-align:center;color:var(--md-sys-color-on-surface-variant)}
</style>
</head>
<body class="sp-body">
<header class="md3-top-app-bar">
  <div class="sp-top-bar__lead"><span class="sp-app-badge material-symbols-outlined">hub</span>
    <div class="sp-top-bar__titles"><h1 class="md3-top-app-bar__title">Shared links</h1><span class="sp-top-bar__subtitle">Universal SharePoint administration</span></div>
  </div>
  <div class="sp-top-bar__actions">
    <a class="md3-btn md3-btn--text" href="/main"><span class="material-symbols-outlined">arrow_back</span>Dashboard</a>
  </div>
</header>
<main class="sp-main">
  <div class="sp-admin-head">
    <div class="md3-flex md3-gap-2 md3-flex--wrap">
      <span class="md3-chip md3-chip--assist">Total <b data-stat="total">0</b></span>
      <span class="md3-chip md3-chip--assist">Active <b data-stat="active">0</b></span>
      <span class="md3-chip md3-chip--filter">Expired <b data-stat="expired">0</b></span>
      <span class="md3-chip md3-chip--filter">Revoked <b data-stat="revoked">0</b></span>
    </div>
    <div class="md3-flex md3-gap-2">
      <button class="md3-btn md3-btn--outlined" data-admin-refresh><span class="material-symbols-outlined">refresh</span>Refresh</button>
      <button class="md3-btn md3-btn--filled" data-md3-menu-trigger="admin-bulk-menu"><span class="material-symbols-outlined">more_vert</span>Bulk actions</button>
    </div>
  </div>

  <nav id="admin-bulk-menu" class="md3-menu" role="menu">
    <button class="md3-menu__item" data-admin-bulk="revoke"><span class="material-symbols-outlined">block</span>Revoke selected</button>
    <button class="md3-menu__item" data-admin-bulk="delete"><span class="material-symbols-outlined">delete</span>Delete selected</button>
    <div class="md3-menu__divider"></div>
    <button class="md3-menu__item" data-admin-delete-app="cloud"><span class="material-symbols-outlined">cloud</span>Delete all cloud shares</button>
    <button class="md3-menu__item" data-admin-delete-app="recipes"><span class="material-symbols-outlined">restaurant_menu</span>Delete all recipe shares</button>
    <button class="md3-menu__item" data-admin-delete-app="spends"><span class="material-symbols-outlined">pie_chart</span>Delete all spends shares</button>
    <button class="md3-menu__item" data-admin-delete-app="steps"><span class="material-symbols-outlined">directions_walk</span>Delete all steps shares</button>
    <button class="md3-menu__item" data-admin-delete-app="films"><span class="material-symbols-outlined">movie</span>Delete all film shares</button>
    <button class="md3-menu__item" data-admin-delete-app="smart_home"><span class="material-symbols-outlined">home_automation</span>Delete all smart home shares</button>
  </nav>

  <section class="md3-card md3-card--outlined sp-admin-filters">
    <div><span class="md3-typescale-label-large">App</span><div class="sp-chips" data-admin-filter-group="app">' . $chips . '</div></div>
    <div><span class="md3-typescale-label-large">Resource type</span><div class="sp-chips" data-admin-filter-group="type">' . $typeChips . '</div></div>
    <div><span class="md3-typescale-label-large">Status</span><div class="sp-chips" data-admin-filter-group="status">' . $statusChips . '</div></div>
  </section>

  <section class="md3-card md3-card--outlined">
    <div class="sp-admin-table-wrap">
      <table class="md3-data-table sp-admin-table">
        <thead><tr>
          <th style="width:34px"><input class="md3-checkbox" type="checkbox" data-admin-select-all aria-label="Select all"></th>
          <th>App</th><th>Type</th><th>Entity</th><th>Title</th><th>Owner</th><th>Created</th><th>Expires</th><th>Status</th><th>Data</th><th>Actions</th>
        </tr></thead>
        <tbody data-admin-rows><tr><td colspan="11" class="sp-admin-empty">Loading…</td></tr></tbody>
      </table>
    </div>
    <div class="sp-admin-bulk" data-admin-bulk-bar>
      <span class="md3-typescale-body-medium"><b data-admin-selected>0</b> selected</span>
      <div class="md3-flex md3-gap-2">
        <button class="md3-btn md3-btn--tonal" data-admin-bulk="revoke">Revoke</button>
        <button class="md3-btn md3-btn--text" data-admin-bulk="delete" style="color:var(--md-sys-color-error)">Delete</button>
      </div>
    </div>
  </section>
</main>
<script src="' . sp_h(sp_asset('js/md3-components.js')) . '" defer></script>
<script>
(function(){
  var csrf = document.querySelector(\'meta[name="sp-csrf"]\').content;
  var filters = { app: "all", type: "all", status: "" };
  function api(params){ var q = new URLSearchParams(params); return fetch(location.pathname + "?api=" + params.api + "&" + q.toString()).then(function(r){return r.json();}); }
  function post(body){ return fetch(location.pathname + "?api=action", {method:"POST", headers:{"Content-Type":"application/json","X-SharePoint-CSRF":csrf}, body:JSON.stringify(body)}).then(function(r){return r.json();}); }
  function esc(v){ return String(v==null?"":v).replace(/[&<>"]/g, function(c){return({"&":"&amp;","<":"&lt;",">":"&gt;","\\"":"&quot;"})[c];}); }
  function loadStats(){ fetch(location.pathname+"?api=stats").then(function(r){return r.json();}).then(function(d){ if(!d.ok) return; Object.keys(d.stats).forEach(function(k){ var el=document.querySelector(\'[data-stat="\'+k+\'"]\'); if(el) el.textContent=d.stats[k]; }); }); }
  function loadRows(){
    var q = new URLSearchParams({resource_app:filters.app, resource_type:filters.type, status:filters.status});
    fetch(location.pathname+"?api=list&"+q.toString()).then(function(r){return r.json();}).then(function(d){
      var tbody=document.querySelector("[data-admin-rows]");
      if(!d.ok || !d.shares.length){ tbody.innerHTML=\'<tr><td colspan="11" class="sp-admin-empty">No shared links</td></tr>\'; return; }
      tbody.innerHTML = d.shares.map(function(s){
        var chip = s.status==="active"?"md3-chip--assist":"md3-chip--filter";
        return \'<tr data-id="\'+s.id+\'"><td><input type="checkbox" class="md3-checkbox" data-admin-row value="\'+s.id+\'"></td>\'+
          \'<td><span class="md3-chip md3-chip--assist"><span class="material-symbols-outlined">\'+esc(s.app_icon)+\'</span>\'+esc(s.app_label)+\'</span></td>\'+
          \'<td>\'+esc(s.resource_type)+\'</td><td>\'+esc(s.entity_label||"—")+\'</td>\'+
          \'<td class="sp-admin-title"><a href="\'+esc(s.link)+\'" target="_blank" rel="noopener">\'+esc(s.title||"(untitled)")+\'</a></td>\'+
          \'<td>\'+esc(s.owner_email)+\'</td><td>\'+esc((s.created_at||"").slice(0,16))+\'</td>\'+
          \'<td>\'+esc(s.expires_at?s.expires_at.slice(0,16):"Never")+\'</td>\'+
          \'<td><span class="md3-chip \'+chip+\'">\'+esc(s.status)+\'</span></td>\'+
          \'<td>\'+esc(s.data_mode)+\'</td>\'+
          \'<td><div class="sp-row-actions"><a class="md3-btn md3-btn--icon md3-btn--text" href="\'+esc(s.link)+\'" target="_blank" title="Open"><span class="material-symbols-outlined">open_in_new</span></a>\'+
          \'<button class="md3-btn md3-btn--icon md3-btn--text" data-admin-action="revoke" data-id="\'+s.id+\'" title="Revoke"><span class="material-symbols-outlined">block</span></button>\'+
          \'<button class="md3-btn md3-btn--icon md3-btn--text" data-admin-action="delete" data-id="\'+s.id+\'" title="Delete" style="color:var(--md-sys-color-error)"><span class="material-symbols-outlined">delete</span></button></div></td></tr>\';
      }).join("");
    });
  }
  document.querySelectorAll("[data-admin-filter-group]").forEach(function(group){
    group.querySelectorAll(".md3-chip").forEach(function(chip){
      chip.addEventListener("click", function(){
        group.querySelectorAll(".md3-chip").forEach(function(c){c.classList.remove("md3-chip--selected");});
        chip.classList.add("md3-chip--selected");
        filters[group.dataset.adminFilterGroup]=chip.dataset.value;
        loadRows();
      });
    });
  });
  document.querySelectorAll("[data-admin-refresh]").forEach(function(b){b.addEventListener("click", function(){loadStats();loadRows();});});
  document.querySelectorAll("[data-admin-action]").forEach(function(b){b.addEventListener("click", function(){ if(!confirm("Apply this action?")) return; post({op:b.dataset.adminAction, ids:[Number(b.dataset.id)]}).then(function(){loadStats();loadRows();}); });});
  document.addEventListener("change", function(e){ if(e.target.matches("[data-admin-select-all]")){ document.querySelectorAll("[data-admin-row]").forEach(function(c){c.checked=e.target.checked;}); syncBulk(); } if(e.target.matches("[data-admin-row]")) syncBulk(); });
  function selectedIds(){ return Array.prototype.slice.call(document.querySelectorAll("[data-admin-row]:checked")).map(function(c){return Number(c.value);}); }
  function syncBulk(){ var n=selectedIds().length; var bar=document.querySelector("[data-admin-bulk-bar]"); if(bar) bar.classList.toggle("sp-admin-bulk--visible", n>0); var label=document.querySelector("[data-admin-selected]"); if(label) label.textContent=n; }
  document.querySelectorAll("[data-admin-bulk]").forEach(function(b){b.addEventListener("click", function(){ var ids=selectedIds(); if(!ids.length){alert("Select at least one link");return;} if(!confirm("Apply this action to "+ids.length+" link(s)?")) return; post({op:b.dataset.adminBulk, ids:ids}).then(function(){loadStats();loadRows();}); });});
  document.querySelectorAll("[data-admin-delete-app]").forEach(function(b){b.addEventListener("click", function(){ if(!confirm("Delete ALL shares for this app?")) return; post({op:"delete_app", app:b.dataset.adminDeleteApp}).then(function(){loadStats();loadRows();}); });});
  loadStats(); loadRows();
})();
</script>
</body>
</html>';
echo $html;
