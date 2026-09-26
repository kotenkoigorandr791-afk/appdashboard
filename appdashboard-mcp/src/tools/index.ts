import { getToken, fetchMe, getCachedRole } from "../auth.js";
import { filterToolsByRole, insufficientRoleError, TOOL_MIN_ROLE, type Role } from "../roles.js";
import { errorResult, textResult } from "../api.js";
import { authTools } from "./auth.js";
import { appsTools } from "./apps.js";
import { dockerTools } from "./docker.js";
import { filesTools } from "./files.js";
import { backupTools } from "./backup.js";
import { supportTools } from "./support.js";
import { spendsTools } from "./spends.js";
import { cloudTools } from "./cloud.js";
import { smarthomeTools } from "./smarthome.js";
import { recipesTools } from "./recipes.js";
import { stepsTools } from "./steps.js";
import { immichTools } from "./immich.js";
import { giteaTools } from "./gitea.js";
import { kanboardTools } from "./kanboard.js";
import { newsTools } from "./news.js";
import { groupForTool, parseGroupList } from "./groups.js";
import { buildCatalogTools } from "./catalog.js";
import type { ToolDef } from "./types.js";

export type { ToolDef } from "./types.js";

export const ALL_TOOLS: ToolDef[] = [
  ...authTools,
  ...appsTools,
  ...dockerTools,
  ...filesTools,
  ...backupTools,
  ...supportTools,
  ...spendsTools,
  ...cloudTools,
  ...smarthomeTools,
  ...recipesTools,
  ...stepsTools,
  ...immichTools,
  ...giteaTools,
  ...kanboardTools,
  ...newsTools,
];
const byName = new Map(ALL_TOOLS.map((t) => [t.name, t]));

export async function resolveRole(): Promise<Role | null> {
  if (!getToken()) return null;
  const me = await fetchMe();
  if (me) return me.role;
  return getToken() ? getCachedRole() : null;
}

const CATALOG_MODE_ENV = "MCP_GROUPED_TOOLS";

function groupedModeEnabled(): boolean {
  const raw = (process.env[CATALOG_MODE_ENV] ?? "").trim().toLowerCase();
  return raw === "" || raw === "1" || raw === "true" || raw === "yes" || raw === "on";
}

export async function listToolsForCurrentUser() {
  const role = await resolveRole();
  if (role === null) {
    return [];
  }
  const visible = filterToolsByRole(ALL_TOOLS, role);
  const catalog = buildCatalogTools(() => Promise.resolve(visible));
  if (!groupedModeEnabled()) {
    return [...catalog, ...visible].map(toMcpTool);
  }
  return catalog.map(toMcpTool);
}

function toMcpTool(t: ToolDef) {
  return {
    name: t.name,
    description: t.description,
    inputSchema: t.inputSchema,
  };
}

export async function callTool(name: string, args: Record<string, unknown>) {
  const role = await resolveRole();
  if (role === null) {
    return textResult({ error: "not_authenticated", message: "Run setup with --token or set APPDASHBOARD_TOKEN." });
  }
  const catalog = buildCatalogTools(async () => filterToolsByRole(ALL_TOOLS, role), callTool);
  const catalogTool = catalog.find((t) => t.name === name);
  if (catalogTool) {
    try {
      return await catalogTool.handler(args || {});
    } catch (err) {
      return errorResult(err);
    }
  }
  const tool = byName.get(name);
  if (!tool) {
    return textResult({ error: "unknown_tool", name, hint: "Use tools_catalog / tools_load / tools_search to discover tools." });
  }
  const groupFilter = parseGroupList(process.env.MCP_GROUPS);
  if (groupFilter.size && !groupFilter.has("all")) {
    const gid = groupForTool(name);
    if (!groupFilter.has(gid)) {
      return textResult({ error: "group_not_allowed", group: gid, hint: `Group '${gid}' is disabled via MCP_GROUPS env.` });
    }
  }
  const required = TOOL_MIN_ROLE[name] ?? tool.minRole;
  if (required > role) {
    return textResult(insufficientRoleError(required, role));
  }
  try {
    return await tool.handler(args || {});
  } catch (err) {
    return errorResult(err);
  }
}
