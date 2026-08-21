export type Role = 0 | 1 | 2;

export const ROLE_NAMES: Record<Role, string> = {
  0: "Guest",
  1: "Manager",
  2: "Admin",
};

export const TOOL_MIN_ROLE: Record<string, Role> = {
  login: 0,
  get_current_user: 0,
  list_apps: 0,
  get_shared_files: 0,
  delete_shared_file: 0,
  update_share_expiration: 0,
  save_settings: 1,
  change_password: 1,
  change_avatar: 1,
  reset_avatar: 1,
  list_containers: 1,
  container_stats: 1,
  container_logs: 1,
  list_stacks: 1,
  backup_status: 1,
  disk_space: 1,
  send_support_message: 1,
  get_support_messages: 1,
  list_users: 2,
  get_user: 2,
  update_user: 2,
  delete_user: 2,
  get_app_access_levels: 2,
  set_app_access_level: 2,
  container_inspect: 2,
  start_container: 2,
  stop_container: 2,
  restart_container: 2,
  pause_container: 2,
  unpause_container: 2,
  kill_container: 2,
  remove_container: 2,
  update_container: 2,
  recreate_container: 2,
  pull_image: 2,
  pull_named_image: 2,
  remove_image: 2,
  remove_volume: 2,
  get_stack_compose: 2,
  update_stack: 2,
  image_inspect: 2,
  fm_list_dir: 2,
  fm_read_file: 2,
  fm_write_file: 2,
  fm_create_dir: 2,
  fm_create_file: 2,
  fm_delete: 2,
  fm_rename: 2,
  fm_dir_size: 2,
  get_config: 2,
  set_config: 2,
  list_config_keys: 2,
  list_json_files: 2,
  read_json_file: 2,
  write_json_file: 2,
  server_monitor: 2,
  list_alert_rules: 2,
  create_alert_rule: 2,
  delete_alert_rule: 2,
  list_alerts: 2,
  get_metrics_hourly: 2,
  list_quick_commands: 2,
  create_quick_command: 2,
  update_quick_command: 2,
  delete_quick_command: 2,
  backup_run: 2,
  backup_logs: 2,
  host_fs_list: 2,
  host_fs_mkdir: 2,
  test_path_rw: 2,
};

export type ToolLike = { name: string; minRole?: Role };

export function filterToolsByRole<T extends ToolLike>(tools: T[], userRole: Role): T[] {
  return tools.filter((t) => (TOOL_MIN_ROLE[t.name] ?? t.minRole ?? 2) <= userRole);
}

export function insufficientRoleError(required: Role, current: Role) {
  return {
    error: "insufficient_role",
    required,
    current,
    message: `This tool requires ${ROLE_NAMES[required]} role. Your role: ${ROLE_NAMES[current]}.`,
  };
}

export function normalizeRole(value: unknown): Role {
  const n = Number(value);
  if (n === 1) return 1;
  if (n === 2) return 2;
  return 0;
}
