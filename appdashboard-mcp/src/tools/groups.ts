import type { ToolDef } from "./types.js";

export type ToolGroup = {
  id: string;
  /** Short human description shown in the catalog */
  description: string;
  /** Prefixes that map a tool name to this group */
  prefixes: string[];
  /** Explicit tool names that belong to this group (besides prefix matches) */
  names?: string[];
};

export const TOOL_GROUPS: ToolGroup[] = [
  {
    id: "core",
    description: "Auth, current user, app list, shared files, settings",
    prefixes: [],
    names: [
      "login",
      "get_current_user",
      "list_apps",
      "get_app_access_levels",
      "set_app_access_level",
      "get_shared_files",
      "delete_shared_file",
      "update_share_expiration",
      "save_settings",
      "change_password",
      "change_avatar",
      "reset_avatar",
      "echo",
      "sleep",
    ],
  },
  {
    id: "users",
    description: "User management (admin)",
    prefixes: ["user_"],
    names: ["list_users", "get_user", "update_user", "delete_user"],
  },
  {
    id: "docker",
    description: "Docker containers, images, volumes, compose stacks (Portainer)",
    prefixes: ["container_", "image_", "pull_", "remove_"],
    names: ["list_containers", "list_stacks", "get_stack_compose", "update_stack", "remove_volume"],
  },
  {
    id: "files",
    description: "File manager under APP_ROOT (read/write/rename/delete)",
    prefixes: ["fm_"],
  },
  {
    id: "host_fs",
    description: "Host filesystem access (list, mkdir, read/write test)",
    prefixes: ["host_fs_", "test_path_"],
  },
  {
    id: "config",
    description: "AppDashboard config keys and JSON files",
    prefixes: ["get_config", "set_config", "list_config_keys", "list_json_files", "read_json_file", "write_json_file"],
  },
  {
    id: "monitor",
    description: "Server monitoring, metrics, alert rules",
    prefixes: ["server_monitor", "list_alert", "create_alert_rule", "delete_alert_rule", "get_metrics_"],
  },
  {
    id: "backup",
    description: "Backups, disk space",
    prefixes: ["backup_"],
    names: ["disk_space"],
  },
  {
    id: "commands",
    description: "Quick shell commands CRUD",
    prefixes: ["quick_command_", "shell_"],
    names: ["list_quick_commands", "create_quick_command", "update_quick_command", "delete_quick_command"],
  },
  {
    id: "support",
    description: "Support messages",
    prefixes: ["send_support_message", "get_support_messages"],
  },
  {
    id: "spends",
    description: "Spends app: expenses, incomes, stores, OCR, bank CSV, backups",
    prefixes: ["get_expense", "update_expense", "update_income", "bulk_delete_spends", "get_available_years", "get_year_data", "get_exchange_rates", "ocr_", "spends_", "get_spends_", "save_spends_", "create_spends_", "update_spends_", "delete_spends_", "add_spends_", "bank_csv_"],
  },
  {
    id: "cloud",
    description: "Cloud storage: files, folders, shares, archives, AI agents, code tools",
    prefixes: ["cloud_"],
  },
  {
    id: "smarthome",
    description: "Smart home devices, groups, routes, monitoring",
    prefixes: ["smarthome_"],
  },
  {
    id: "recipes",
    description: "Recipes app: recipes, products, AI generation, translations",
    prefixes: ["recipes_"],
  },
  {
    id: "steps",
    description: "Steps counter: daily steps, settings, backups",
    prefixes: ["steps_"],
  },
  {
    id: "immich",
    description: "Immich photo library: albums, assets, search",
    prefixes: ["immich_"],
  },
  {
    id: "gitea",
    description: "Gitea git forge: repos, issues, labels, pull requests",
    prefixes: ["gitea_"],
  },
  {
    id: "kanboard",
    description: "Kanboard task boards: projects, board columns, tasks (create, edit, move, close), comments",
    prefixes: ["kanboard_"],
  },
  {
    id: "news",
    description: "News portal: list and delete articles",
    prefixes: ["news_"],
  },
];

const nameToGroup = new Map<string, string>();
for (const g of TOOL_GROUPS) {
  for (const p of g.prefixes) nameToGroup.set(p, g.id);
  for (const n of g.names ?? []) nameToGroup.set(n, g.id);
}

/** Resolve group id for a tool name by longest matching prefix or exact name. */
export function groupForTool(toolName: string): string {
  if (nameToGroup.has(toolName)) return nameToGroup.get(toolName)!;
  let best = "";
  let bestLen = 0;
  for (const [prefix, gid] of nameToGroup) {
    if (toolName.startsWith(prefix) && prefix.length > bestLen) {
      best = gid;
      bestLen = prefix.length;
    }
  }
  return best || "core";
}

export function toolsInGroup(tools: ToolDef[], groupId: string): ToolDef[] {
  return tools.filter((t) => groupForTool(t.name) === groupId);
}

/** Groups that have at least one tool from the given list. */
export function availableGroups(tools: ToolDef[]): Array<{ id: string; description: string; tool_count: number }> {
  const counts = new Map<string, number>();
  for (const t of tools) {
    const gid = groupForTool(t.name);
    counts.set(gid, (counts.get(gid) ?? 0) + 1);
  }
  return TOOL_GROUPS.filter((g) => counts.has(g.id)).map((g) => ({
    id: g.id,
    description: g.description,
    tool_count: counts.get(g.id)!,
  }));
}

/** Split a comma-separated env value into a Set of group ids. */
export function parseGroupList(raw: string | undefined): Set<string> {
  return new Set(
    (raw || "")
      .split(",")
      .map((s) => s.trim().toLowerCase())
      .filter(Boolean),
  );
}
