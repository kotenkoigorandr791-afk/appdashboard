import { getToken, fetchMe, getCachedRole, getCachedRoleForToken, getStore } from "../auth.js";
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
import { geolocationTools } from "./geolocation.js";
import { immichTools } from "./immich.js";
import { giteaTools } from "./gitea.js";
import { kanboardTools } from "./kanboard.js";
import { newsTools } from "./news.js";
import { sentryTools } from "./sentry.js";
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
  ...geolocationTools,
  ...immichTools,
  ...giteaTools,
  ...kanboardTools,
  ...newsTools,
  ...sentryTools,
];
const byName = new Map(ALL_TOOLS.map((t) => [t.name, t]));

// Role visibility is static for the lifetime of the process; compute each list
// once instead of filtering all 146 tools on every tools/list and tools_call.
const VISIBLE_BY_ROLE: Record<Role, ToolDef[]> = {
  0: filterToolsByRole(ALL_TOOLS, 0),
  1: filterToolsByRole(ALL_TOOLS, 1),
  2: filterToolsByRole(ALL_TOOLS, 2),
};

export async function resolveRole(): Promise<Role | null> {
  const token = getToken();
  if (!token) return null;
  const me = await fetchMe();
  if (me) return me.role;
  // HTTP requests must never fall back to the shared on-disk role. Use only
  // this token's cached identity, or fail closed.
  if (getStore()) return getCachedRoleForToken(token);
  return getCachedRole();
}

const CATALOG_MODE_ENV = "MCP_GROUPED_TOOLS";

function groupedModeEnabled(): boolean {
  const raw = (process.env[CATALOG_MODE_ENV] ?? "").trim().toLowerCase();
  return raw === "" || raw === "1" || raw === "true" || raw === "yes" || raw === "on";
}

const catalogVisible = async (): Promise<ToolDef[]> => {
  const role = await resolveRole();
  return role === null ? [] : VISIBLE_BY_ROLE[role];
};

// Catalog handlers are static; create the seven catalog tools once. `callTool`
// is a hoisted function declaration, so the invoke closure is valid here.
const CATALOG_TOOLS = buildCatalogTools(catalogVisible, callTool);
const CATALOG_BY_NAME = new Map(CATALOG_TOOLS.map((t) => [t.name, t]));

export async function listToolsForCurrentUser() {
  const role = await resolveRole();
  if (role === null) {
    return [];
  }
  const visible = VISIBLE_BY_ROLE[role];
  if (!groupedModeEnabled()) {
    return [...CATALOG_TOOLS, ...visible].map(toMcpTool);
  }
  return CATALOG_TOOLS.map(toMcpTool);
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
  const catalogTool = CATALOG_BY_NAME.get(name);
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
