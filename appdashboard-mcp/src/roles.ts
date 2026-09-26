export type Role = 0 | 1 | 2;

export const ROLE_NAMES: Record<Role, string> = {
  0: "Guest",
  1: "Manager",
  2: "Admin",
};

export const TOOL_MIN_ROLE: Record<string, Role> = {
  get_current_user: 0,
  list_apps: 0,
  get_shared_files: 0,
  delete_shared_file: 0,
  update_share_expiration: 0,
  list_containers: 1,
  container_stats: 1,
  container_logs: 1,
  backup_status: 1,
  disk_space: 1,
  send_support_message: 1,
  get_support_messages: 1,
  backup_run: 2,
  backup_logs: 2,
  fm_list_dir: 2,
  fm_read_file: 2,
  fm_write_file: 2,
  fm_create_dir: 2,
  fm_create_file: 2,
  fm_delete: 2,
  fm_rename: 2,
  fm_dir_size: 2,
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
