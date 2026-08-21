import { getToken, fetchMe, getCachedRole } from "../auth.js";
import { filterToolsByRole, insufficientRoleError, TOOL_MIN_ROLE, type Role } from "../roles.js";
import { errorResult, textResult } from "../api.js";
import { authTools } from "./auth.js";
import { usersTools } from "./users.js";
import { appsTools } from "./apps.js";
import { dockerTools } from "./docker.js";
import { filesTools } from "./files.js";
import { configTools } from "./config.js";
import { monitorTools } from "./monitor.js";
import { backupTools } from "./backup.js";
import { commandsTools } from "./commands.js";
import { supportTools } from "./support.js";
import { spendsTools } from "./spends.js";
import { cloudTools } from "./cloud.js";
import { smarthomeTools } from "./smarthome.js";
import type { ToolDef } from "./types.js";

export type { ToolDef } from "./types.js";

export const ALL_TOOLS: ToolDef[] = [
  ...authTools,
  ...usersTools,
  ...appsTools,
  ...dockerTools,
  ...filesTools,
  ...configTools,
  ...monitorTools,
  ...backupTools,
  ...commandsTools,
  ...supportTools,
  ...spendsTools,
  ...cloudTools,
  ...smarthomeTools,
];

const byName = new Map(ALL_TOOLS.map((t) => [t.name, t]));

export async function resolveRole(): Promise<Role | null> {
  if (!getToken()) return null;
  const me = await fetchMe();
  if (me) return me.role;
  return getToken() ? getCachedRole() : null;
}

export async function listToolsForCurrentUser() {
  const role = await resolveRole();
  if (role === null) {
    return ALL_TOOLS.filter((t) => t.name === "login").map(toMcpTool);
  }
  return filterToolsByRole(ALL_TOOLS, role).map(toMcpTool);
}

function toMcpTool(t: ToolDef) {
  return {
    name: t.name,
    description: t.description,
    inputSchema: t.inputSchema,
  };
}

export async function callTool(name: string, args: Record<string, unknown>) {
  const tool = byName.get(name);
  if (!tool) {
    return textResult({ error: "unknown_tool", name });
  }
  if (name !== "login") {
    const role = await resolveRole();
    if (role === null) {
      return textResult({ error: "not_authenticated", message: "Use the login tool or complete browser auth." });
    }
    const required = TOOL_MIN_ROLE[name] ?? tool.minRole;
    if (required > role) {
      return textResult(insufficientRoleError(required, role));
    }
  }
  try {
    return await tool.handler(args || {});
  } catch (err) {
    return errorResult(err);
  }
}
