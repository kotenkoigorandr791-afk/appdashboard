import type { ToolDef } from "./types.js";

export type ToolGroup = {
  id: string;
  description: string;
  prefixes: string[];
  names?: string[];
};

export const TOOL_GROUPS: ToolGroup[] = [
  {
    id: "core",
    description: "Auth, current user, app list, shared files",
    prefixes: [],
    names: [
      "get_current_user",
      "list_apps",
      "get_shared_files",
      "delete_shared_file",
      "update_share_expiration",
    ],
  },
  {
    id: "docker",
    description: "Docker containers read-only",
    prefixes: ["container_"],
    names: ["list_containers"],
  },
  {
    id: "files",
    description: "File manager under APP_ROOT (read/write/rename/delete)",
    prefixes: ["fm_"],
  },
  {
    id: "backup",
    description: "Backups, disk space",
    prefixes: ["backup_"],
    names: ["disk_space"],
  },
  {
    id: "support",
    description: "Support messages",
    prefixes: ["send_support_message", "get_support_messages"],
  },
  {
    id: "spends",
    description: "Spends app: expenses, incomes, stores, OCR, bank CSV",
    prefixes: [
      "get_expense",
      "update_expense",
      "update_income",
      "get_available_years",
      "get_year_data",
      "get_exchange_rates",
      "ocr_",
      "get_spends_",
      "save_spends_",
      "create_spends_",
      "update_spends_",
      "delete_spends_",
      "add_spends_",
      "bank_csv_",
    ],
  },
  {
    id: "cloud",
    description: "Cloud storage: files, folders, shares, archives, AI",
    prefixes: ["cloud_"],
  },
  {
    id: "smarthome",
    description: "Smart home state and device control",
    prefixes: ["smarthome_"],
  },
  {
    id: "recipes",
    description: "Recipes app: recipes and products CRUD",
    prefixes: ["recipes_"],
  },
  {
    id: "steps",
    description: "Steps counter: daily steps and settings",
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
    description: "Kanboard task boards: projects, tasks, comments",
    prefixes: ["kanboard_"],
  },
  {
    id: "sentry",
    description: "Sentry/GlitchTip projects and issues",
    prefixes: ["sentry_"],
  },
  {
    id: "news",
    description: "News portal: list articles",
    prefixes: ["news_"],
  },
];

const nameToGroup = new Map<string, string>();
for (const g of TOOL_GROUPS) {
  for (const p of g.prefixes) nameToGroup.set(p, g.id);
  for (const n of g.names ?? []) nameToGroup.set(n, g.id);
}

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

export function parseGroupList(raw: string | undefined): Set<string> {
  return new Set(
    (raw || "")
      .split(",")
      .map((s) => s.trim().toLowerCase())
      .filter(Boolean),
  );
}
