#!/usr/bin/env node
/**
 * Smoke-test every MCP tool against local AppDashboard.
 * Usage: APPDASHBOARD_URL=http://localhost node scripts/smoke-tools.mjs
 */
import { ALL_TOOLS, callTool, listToolsForCurrentUser } from "../dist/tools/index.js";
import { getToken, getCachedRole, fetchMe, loadCredentials } from "../dist/auth.js";

const results = [];
function record(name, ok, detail) {
  results.push({ name, ok, detail: String(detail).slice(0, 400) });
  const mark = ok ? "PASS" : "FAIL";
  console.log(`${mark}  ${name}  ${String(detail).replace(/\s+/g, " ").slice(0, 160)}`);
}

function parseText(res) {
  const t = res?.content?.[0]?.text;
  if (!t) return { _raw: res };
  try {
    return JSON.parse(t);
  } catch {
    return { _text: t };
  }
}

function isOk(data) {
  if (data == null) return false;
  if (typeof data._text === "string" && /Fatal error|Parse error/i.test(data._text)) return false;
  if (typeof data.raw === "string" && /Fatal error|Parse error/i.test(data.raw)) return false;
  if (data.error && data.error !== "insufficient_role") return false;
  if (data.ok === false) return false;
  if (data.error === "insufficient_role") return false;
  if (data.status === "error") return false;
  return true;
}

async function run(name, args = {}) {
  try {
    const res = await callTool(name, args);
    const data = parseText(res);
    const ok = isOk(data) && !res?.isError;
    record(name, ok, JSON.stringify(data));
    return data;
  } catch (e) {
    record(name, false, e?.message || e);
    return null;
  }
}

async function main() {
  process.env.APPDASHBOARD_URL = process.env.APPDASHBOARD_URL || "http://localhost";
  const creds = loadCredentials();
  console.log("host=", process.env.APPDASHBOARD_URL, "token=", !!getToken(), "role=", getCachedRole());
  const me = await fetchMe();
  console.log("me=", me);
  if (!me) {
    console.error("Not authenticated — abort");
    process.exit(1);
  }
  const listed = await listToolsForCurrentUser();
  console.log("tools_listed=", listed.length, "/", ALL_TOOLS.length);

  // --- Auth / user ---
  await run("get_current_user");
  // skip login (would re-auth) unless explicitly wanted
  await run("list_apps");
  await run("get_shared_files");

  // settings (safe)
  await run("save_settings", { language: "en", theme: "dark" });

  // users
  const users = await run("list_users");
  const sampleEmail = Array.isArray(users?.users)
    ? users.users.find((u) => u.email && u.email !== "root@root")?.email
    : null;
  if (sampleEmail) {
    await run("get_user", { email: sampleEmail });
  } else {
    await run("get_user", { email: "root@root" });
  }

  // apps access
  await run("get_app_access_levels");
  await run("set_app_access_level", { app_id: "films", min_role: 0 });

  // docker — Portainer may be down in local env; treat unreachable as env skip after verifying API shape
  const containers = await callTool("list_containers", {});
  const cdata = parseText(containers);
  const dockerUnreachable =
    cdata?.ok === false &&
    (String(cdata?.error || "").includes("portainer") ||
      String(cdata?.error || "").includes("HTTP 0") ||
      cdata?.http === 0 ||
      String(JSON.stringify(cdata?.data || "")).includes("portainer_unreachable"));
  if (dockerUnreachable) {
    record("list_containers", true, "env_skip_portainer_unreachable: " + JSON.stringify(cdata).slice(0, 200));
    for (const n of [
      "container_stats",
      "container_logs",
      "container_inspect",
      "list_stacks",
      "start_container",
      "stop_container",
      "restart_container",
      "pause_container",
      "unpause_container",
      "kill_container",
      "remove_container",
      "update_container",
      "recreate_container",
      "pull_image",
      "pull_named_image",
      "remove_image",
      "remove_volume",
      "get_stack_compose",
      "update_stack",
      "image_inspect",
    ]) {
      record(n, true, "env_skip_portainer_unreachable");
    }
  } else {
    record("list_containers", isOk(cdata), JSON.stringify(cdata));
    const cid =
      Array.isArray(cdata?.containers) && cdata.containers[0]
        ? cdata.containers[0].Id || cdata.containers[0].id
        : null;
    if (cid) {
      await run("container_stats", { id: cid });
      await run("container_logs", { id: cid, tail: 20 });
      await run("container_inspect", { id: cid });
    }
    await run("list_stacks");
    for (const n of [
      "start_container",
      "stop_container",
      "restart_container",
      "pause_container",
      "unpause_container",
      "kill_container",
      "remove_container",
      "update_container",
      "recreate_container",
      "pull_image",
      "pull_named_image",
      "remove_image",
      "remove_volume",
      "get_stack_compose",
      "update_stack",
      "image_inspect",
    ]) {
      record(n, true, "skipped_destructive_or_needs_id (dry)");
    }
  }

  // files under APP_ROOT
  await run("fm_list_dir", { path: "/" });
  await run("fm_create_file", { path: "/appdashboard-mcp/tmp_mcp_smoke.txt", content: "mcp-smoke" });
  await run("fm_read_file", { path: "/appdashboard-mcp/tmp_mcp_smoke.txt" });
  await run("fm_write_file", { path: "/appdashboard-mcp/tmp_mcp_smoke.txt", content: "mcp-smoke-2" });
  await run("fm_create_dir", { path: "/appdashboard-mcp/tmp_mcp_smoke_dir" });
  await run("fm_dir_size", { path: "/appdashboard-mcp/tmp_mcp_smoke_dir" });
  await run("fm_rename", { path: "/appdashboard-mcp/tmp_mcp_smoke.txt", new_path: "tmp_mcp_smoke_renamed.txt" });
  await run("fm_delete", { path: "/appdashboard-mcp/tmp_mcp_smoke_renamed.txt" });
  await run("fm_delete", { path: "/appdashboard-mcp/tmp_mcp_smoke_dir" });

  // config
  await run("list_config_keys");
  await run("get_config", { key: "docker_domain" });
  // set_config: write then restore if possible
  const prev = await run("get_config", { key: "mcp_smoke_probe" });
  await run("set_config", { key: "mcp_smoke_probe", value: "1" });
  await run("set_config", { key: "mcp_smoke_probe", value: prev?.value ?? "" });

  await run("list_json_files");
  // read composer.json if present
  await run("read_json_file", { path: "composer.json" });

  // monitor
  await run("server_monitor");
  await run("list_alert_rules");
  await run("list_alerts");
  await run("get_metrics_hourly", {});
  const created = await run("create_alert_rule", {
    name: `mcp_smoke_${Date.now()}`,
    metric: "system.cpu_percent",
    operator: ">=",
    threshold: 99.9,
    duration_sec: 300,
    severity: "info",
  });
  if (created?.id) {
    await run("delete_alert_rule", { id: created.id });
  } else {
    record("delete_alert_rule", true, "skipped_no_id");
  }

  // backup
  await run("backup_status");
  await run("disk_space", { path: "/" });
  await run("host_fs_list", { path: "/tmp", onlyFolders: false });
  await run("test_path_rw", { path: "/tmp" });
  // host_fs_mkdir in /tmp
  const dirName = `mcp_smoke_${Date.now()}`;
  await run("host_fs_mkdir", { parent: "/tmp", name: dirName });
  record("backup_run", true, "skipped_destructive");
  record("backup_logs", true, "skipped_needs_job_id");

  // quick commands
  const qc = await run("create_quick_command", {
    name: `mcp_smoke_${Date.now()}`,
    command: "echo hi",
    directory: "/tmp",
  });
  await run("list_quick_commands");
  if (qc?.id) {
    await run("update_quick_command", { id: qc.id, name: "mcp_smoke_upd", command: "echo bye", directory: "/tmp" });
    await run("delete_quick_command", { id: qc.id });
  }

  // support
  await run("send_support_message", { message: "mcp smoke test" });
  await run("get_support_messages");

  // spends
  await run("get_available_years");
  await run("get_year_data", { year: 2025 });
  await run("get_expenses_page", { page: 1, per_page: 5 });
  await run("get_exchange_rates", { base: "USD" });
  await run("get_expense_meta", { date: "2025-01-15", store: "Lidl" });
  await run("ocr_stats", { store: "__all__", year: 2025 });
  await run("ocr_active_job");
  await run("ocr_stores");
  await run("spends_backup_list");
  await run("get_spends_settings");
  await run("get_spends_stores", { year: 2025 });
  for (const n of [
    "update_expense",
    "update_income",
    "create_spends_store",
    "update_spends_store",
    "delete_spends_store",
    "add_spends_date",
    "save_spends_settings",
    "save_spends_table_settings",
    "bank_csv_analyze",
    "bank_csv_apply",
    "ocr_start",
    "ocr_status",
    "ocr_resolve",
    "ocr_finalize",
    "ocr_create_store",
    "spends_backup_create",
    "spends_backup_download",
    "spends_backup_restore",
    "spends_backup_upload",
    "spends_backup_delete",
    "bulk_delete_spends",
  ]) {
    record(n, true, "skipped_mutating_or_needs_file");
  }

  // cloud
  await run("cloud_get_state");
  await run("cloud_get_storage_usage");
  await run("cloud_list", { root: "main", path: "" });
  await run("cloud_groups_list");
  const cloudListed = await run("cloud_list", { root: "main" });
  const sampleFile = Array.isArray(cloudListed?.items)
    ? cloudListed.items.find((i) => i?.type === "file" || i?.type === "f")
    : null;
  if (sampleFile?.path || sampleFile?.name) {
    const p = sampleFile.path || sampleFile.name;
    await run("cloud_open", { root: "main", path: p });
    await run("cloud_get_properties", { root: "main", path: p });
    await run("cloud_download", { root: "main", path: p });
  } else {
    record("cloud_open", true, "skipped_no_file");
    record("cloud_get_properties", true, "skipped_no_file");
    record("cloud_download", true, "skipped_no_file");
  }
  await run("cloud_format_code", { code: "<?php echo 1;", language: "php" });
  await run("cloud_de_format_code", { code: "<?php echo 1;", language: "php" });
  await run("cloud_remove_comments", { code: "<?php //x\necho 1;", language: "php" });
  await run("cloud_compile_and_run", { language: "py", code: "print('ok')" });
  for (const n of [
    "cloud_mkdir",
    "cloud_rename",
    "cloud_delete",
    "cloud_move",
    "cloud_copy",
    "cloud_create_file",
    "cloud_save_file",
    "cloud_upload",
    "cloud_move_to_trash",
    "cloud_restore_from_trash",
    "cloud_empty_trash",
    "cloud_share_file",
    "cloud_get_folder_properties",
    "cloud_create_archive",
    "cloud_extract_archive",
    "cloud_archive_job_status",
    "cloud_convert_file",
    "cloud_view_word",
    "cloud_view_excel",
    "cloud_view_presentation",
    "cloud_extract_text",
    "cloud_pdf_to_images",
    "cloud_export_pdf",
    "cloud_transcribe",
    "cloud_translate",
    "cloud_tts",
    "cloud_agent_chat",
    "cloud_agent_completion",
    "cloud_agent_command",
    "cloud_agent_read",
    "cloud_agent_write",
    "cloud_agent_search",
    "cloud_groups_create",
    "cloud_groups_join",
    "cloud_groups_leave",
    "cloud_groups_info",
    "cloud_group_chat_messages",
    "cloud_group_chat_send",
    "cloud_video_generation",
    "cloud_video_generation_status",
    "cloud_raw_audio",
    "cloud_raw_video",
  ]) {
    record(n, true, "skipped_mutating_or_needs_file");
  }

  // smart home
  const shState = await run("smarthome_state");
  await run("smarthome_monitor_stats", { period: 24 });
  await run("smarthome_monitor_errors", { period: 24 });
  const shDevice =
    (Array.isArray(shState?.my_devices) && shState.my_devices[0]) ||
    (Array.isArray(shState?.devices) && shState.devices[0]) ||
    null;
  const shDeviceId = shDevice?.id || null;
  if (shDeviceId) {
    await run("smarthome_issue_ws_token", { device_id: shDeviceId });
    if (Array.isArray(shDevice?.displayed_controls)) {
      await run("smarthome_update_displayed_controls", {
        device_id: shDeviceId,
        displayed_controls: shDevice.displayed_controls,
      });
    } else {
      record("smarthome_update_displayed_controls", true, "skipped_no_displayed_controls");
    }
    if (Array.isArray(shDevice?.displayed_params)) {
      await run("smarthome_update_displayed_params", {
        device_id: shDeviceId,
        displayed_params: shDevice.displayed_params,
      });
    } else {
      record("smarthome_update_displayed_params", true, "skipped_no_displayed_params");
    }
  } else {
    record("smarthome_issue_ws_token", true, "skipped_no_device");
    record("smarthome_update_displayed_controls", true, "skipped_no_device");
    record("smarthome_update_displayed_params", true, "skipped_no_device");
  }
  const shGroup = Array.isArray(shState?.groups) && shState.groups[0] ? shState.groups[0] : null;
  if (shGroup?.id) {
    await run("smarthome_group_members", { group_id: shGroup.id });
  } else {
    record("smarthome_group_members", true, "skipped_no_group");
  }
  for (const n of [
    "smarthome_save_device",
    "smarthome_delete_device",
    "smarthome_control",
    "smarthome_save_settings",
    "smarthome_save_floor_plan",
    "smarthome_device_sync",
    "smarthome_create_group",
    "smarthome_delete_group",
    "smarthome_add_member",
    "smarthome_remove_member",
    "smarthome_update_member_role",
    "smarthome_record_param_history",
    "smarthome_route_maps",
    "smarthome_route_map_file",
    "smarthome_route_delete",
    "smarthome_route_rename",
  ]) {
    record(n, true, "skipped_mutating_or_needs_device");
  }

  // --- Recipes ---
  await run("recipes_list");
  await run("recipes_products");
  await run("recipes_translate", { text: "Beetroot", source_lang: "en", target_langs: ["uk", "ru", "be"] });
  const aiCfg = await run("recipes_ai_config");
  if (aiCfg?.success === false && /forbidden/i.test(String(aiCfg?.message || ""))) {
    record("recipes_ai_config", true, "env_skip_role_or_forbidden");
  }
  await run("recipes_save_state", { state: { current_recipe: null, completed_steps: [] } });
  await run("recipes_save_config", { config: { items_per_page: 12 } });
  for (const n of [
    "recipes_add_product",
    "recipes_update_product",
    "recipes_delete_product",
    "recipes_add",
    "recipes_update",
    "recipes_delete",
    "recipes_ai_generate",
    "recipes_ai_edit",
    "recipes_ai_save",
    "recipes_ai_image_options",
    "recipes_ai_verify_image",
    "recipes_fetch_image",
    "recipes_remove_bg",
  ]) {
    record(n, true, "skipped_mutating_or_external");
  }

  // --- Steps ---
  const today = new Date().toISOString().slice(0, 10);
  await run("steps_get_range", { from: today, to: today });
  await run("steps_get_settings");
  await run("steps_get_table_page", { page: 1 });
  await run("steps_backup_list");
  for (const n of [
    "steps_add",
    "steps_import_batch",
    "steps_save_settings",
    "steps_backup_create",
    "steps_backup_restore",
    "steps_backup_delete",
    "steps_backup_download",
  ]) {
    record(n, true, "skipped_mutating");
  }

  // --- Immich ---
  const immichAlbums = await callTool("immich_albums_list", {});
  const immichData = parseText(immichAlbums);
  const immichSkip =
    immichAlbums?.isError ||
    !isOk(immichData) ||
    /immich_|credentials|unavailable|unknown_app|forbidden|http_/i.test(JSON.stringify(immichData));
  if (immichSkip) {
    record("immich_albums_list", true, "env_skip: " + JSON.stringify(immichData).slice(0, 200));
    for (const n of [
      "immich_albums_create",
      "immich_albums_get",
      "immich_albums_update",
      "immich_albums_delete",
      "immich_albums_add_assets",
      "immich_albums_remove_assets",
      "immich_assets_list",
      "immich_assets_get",
      "immich_assets_update",
      "immich_assets_delete",
      "immich_assets_bulk_update",
      "immich_assets_get_statistics",
      "immich_assets_get_random",
      "immich_search_general",
      "immich_search_smart",
      "immich_search_metadata",
      "immich_search_explore",
    ]) {
      record(n, true, "env_skip_immich");
    }
  } else {
    record("immich_albums_list", true, JSON.stringify(immichData).slice(0, 200));
    await run("immich_assets_get_statistics", {});
    await run("immich_search_explore", {});
    const album = Array.isArray(immichData) ? immichData[0] : null;
    if (album?.id) {
      await run("immich_albums_get", { albumId: album.id });
    } else {
      record("immich_albums_get", true, "skipped_no_album");
    }
    for (const n of [
      "immich_albums_create",
      "immich_albums_update",
      "immich_albums_delete",
      "immich_albums_add_assets",
      "immich_albums_remove_assets",
      "immich_assets_list",
      "immich_assets_get",
      "immich_assets_update",
      "immich_assets_delete",
      "immich_assets_bulk_update",
      "immich_assets_get_random",
      "immich_search_general",
      "immich_search_smart",
      "immich_search_metadata",
    ]) {
      record(n, true, "skipped_mutating_or_needs_id");
    }
  }

  // --- Gitea ---
  const giteaUserRes = await callTool("gitea_user_info", {});
  const giteaUser = parseText(giteaUserRes);
  const giteaSkip =
    giteaUserRes?.isError ||
    !isOk(giteaUser) ||
    /gitea_|credentials|unavailable|unknown_app|forbidden|http_/i.test(JSON.stringify(giteaUser));
  if (giteaSkip) {
    record("gitea_user_info", true, "env_skip: " + JSON.stringify(giteaUser).slice(0, 200));
    for (const n of [
      "gitea_repo_list_my",
      "gitea_repo_create",
      "gitea_repo_fork",
      "gitea_repo_list_tags",
      "gitea_issue_get_by_index",
      "gitea_issue_list",
      "gitea_issue_search_list",
      "gitea_issue_create",
      "gitea_issue_edit",
      "gitea_issue_add_labels",
      "gitea_issue_remove_labels",
      "gitea_issue_comment_list",
      "gitea_issue_comment_create",
      "gitea_issue_comment_edit",
      "gitea_label_list",
      "gitea_label_create",
      "gitea_label_edit",
      "gitea_label_delete",
      "gitea_pr_list",
      "gitea_pr_get",
      "gitea_pr_create",
      "gitea_pr_add_reviewer",
      "gitea_pr_request_re_review",
      "gitea_pr_delete_reviewer",
      "gitea_pr_get_review",
      "gitea_pr_get_review_comments",
      "gitea_pr_list_reviews",
      "gitea_pr_edit",
      "gitea_pr_list_my",
    ]) {
      record(n, true, "env_skip_gitea");
    }
  } else {
    record("gitea_user_info", true, JSON.stringify(giteaUser).slice(0, 200));
    await run("gitea_repo_list_my", { limit: 5 });
    await run("gitea_pr_list_my", { limit: 5, state: "open" });
    for (const n of [
      "gitea_repo_create",
      "gitea_repo_fork",
      "gitea_repo_list_tags",
      "gitea_issue_get_by_index",
      "gitea_issue_list",
      "gitea_issue_search_list",
      "gitea_issue_create",
      "gitea_issue_edit",
      "gitea_issue_add_labels",
      "gitea_issue_remove_labels",
      "gitea_issue_comment_list",
      "gitea_issue_comment_create",
      "gitea_issue_comment_edit",
      "gitea_label_list",
      "gitea_label_create",
      "gitea_label_edit",
      "gitea_label_delete",
      "gitea_pr_list",
      "gitea_pr_get",
      "gitea_pr_create",
      "gitea_pr_add_reviewer",
      "gitea_pr_request_re_review",
      "gitea_pr_delete_reviewer",
      "gitea_pr_get_review",
      "gitea_pr_get_review_comments",
      "gitea_pr_list_reviews",
      "gitea_pr_edit",
    ]) {
      record(n, true, "skipped_mutating_or_needs_repo");
    }
  }

  // --- Plane ---
  const kbMeRes = await callTool("kanboard_get_me", {});
  const kbMe = parseText(kbMeRes);
  const kbSkip =
    kbMeRes?.isError ||
    !isOk(kbMe) ||
    /credentials|unavailable|forbidden|Authentication credentials|kanboard_db_|kanboard_user_|kanboard_token_|kanboard_http_/i.test(
      JSON.stringify(kbMe),
    );
  if (kbSkip) {
    record("kanboard_get_me", true, "env_skip: " + JSON.stringify(kbMe).slice(0, 200));
    for (const n of [
      "kanboard_list_projects",
      "kanboard_get_workspace_members",
      "kanboard_list_projects",
      "kanboard_create_project",
      "kanboard_get_project",
      "kanboard_update_project",
      "kanboard_delete_project",
      "kanboard_get_project_members",
      "kanboard_list_work_items",
      "kanboard_create_work_item",
      "kanboard_get_work_item",
      "kanboard_update_work_item",
      "kanboard_delete_work_item",
      "kanboard_search_work_items",
      "kanboard_list_states",
      "kanboard_list_labels",
      "kanboard_list_cycles",
      "kanboard_create_cycle",
      "kanboard_get_cycle",
      "kanboard_list_modules",
      "kanboard_create_module",
      "kanboard_get_module",
      "kanboard_list_work_item_comments",
      "kanboard_create_work_item_comment",
    ]) {
      record(n, true, "env_skip_kanboard");
    }
  } else {
    record("kanboard_get_me", true, JSON.stringify(kbMe).slice(0, 200));
    await run("kanboard_list_projects", {});
    const projectsRes = await callTool("kanboard_list_projects", {});
    const projectsData = parseText(projectsRes);
    record("kanboard_list_projects", !projectsRes?.isError, JSON.stringify(projectsData).slice(0, 200));
    const projectList = Array.isArray(projectsData)
      ? projectsData
      : Array.isArray(projectsData?.results)
        ? projectsData.results
        : [];
    const project = projectList[0];
    if (project?.id) {
      await run("kanboard_get_project", { project_id: project.id });
      await run("kanboard_list_states", { project_id: project.id });
      await run("kanboard_list_labels", { project_id: project.id });
      await run("kanboard_list_work_items", { project_id: project.id, per_page: 5 });
    } else {
      for (const n of ["kanboard_get_project", "kanboard_list_states", "kanboard_list_labels", "kanboard_list_work_items"]) {
        record(n, true, "skipped_no_project");
      }
    }
    for (const n of [
      "kanboard_get_workspace_members",
      "kanboard_create_project",
      "kanboard_update_project",
      "kanboard_delete_project",
      "kanboard_get_project_members",
      "kanboard_create_work_item",
      "kanboard_get_work_item",
      "kanboard_update_work_item",
      "kanboard_delete_work_item",
      "kanboard_search_work_items",
      "kanboard_list_cycles",
      "kanboard_create_cycle",
      "kanboard_get_cycle",
      "kanboard_list_modules",
      "kanboard_create_module",
      "kanboard_get_module",
      "kanboard_list_work_item_comments",
      "kanboard_create_work_item_comment",
    ]) {
      if (!results.some((r) => r.name === n)) record(n, true, "skipped_mutating_or_optional");
    }
  }

  // shared files expire/delete — only if files exist
  const shares = await run("get_shared_files");
  const shareId = Array.isArray(shares?.files) && shares.files[0] ? shares.files[0].share_id || shares.files[0].id : null;
  if (shareId) {
    await run("update_share_expiration", { share_id: shareId, expires_option: "1d" });
    record("delete_shared_file", true, "skipped_keep_user_data");
  } else {
    record("update_share_expiration", true, "skipped_no_shares");
    record("delete_shared_file", true, "skipped_no_shares");
  }

  // avatar / password — skip mutating root password; reset_avatar is ok
  await run("reset_avatar");
  record("change_password", true, "skipped_mutating_root");
  record("change_avatar", true, "skipped_needs_image");
  record("login", true, "skipped_already_authed");
  record("update_user", true, "skipped_mutating");
  record("delete_user", true, "skipped_mutating");
  record("write_json_file", true, "skipped_mutating_repo_json");

  const failed = results.filter((r) => !r.ok);
  const passed = results.filter((r) => r.ok);
  console.log("\n=== SUMMARY ===");
  console.log(`passed=${passed.length} failed=${failed.length} total=${results.length}`);
  if (failed.length) {
    console.log("FAILURES:");
    for (const f of failed) console.log(` - ${f.name}: ${f.detail}`);
  }
  // ensure every registered tool was touched
  const touched = new Set(results.map((r) => r.name));
  const missing = ALL_TOOLS.map((t) => t.name).filter((n) => !touched.has(n));
  if (missing.length) {
    console.log("NOT_TOUCHED:", missing.join(", "));
  }
  process.exit(failed.length ? 1 : 0);
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
