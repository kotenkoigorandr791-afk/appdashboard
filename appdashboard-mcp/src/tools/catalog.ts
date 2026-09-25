import { availableGroups, groupForTool, parseGroupList, TOOL_GROUPS, toolsInGroup } from "./groups.js";
import { textResult } from "../api.js";
import { checkForUpdateNow } from "../updater.js";
import type { ToolDef } from "./types.js";

export type CatalogTool = {
  name: string;
  description: string;
  minRole: ToolDef["minRole"];
  inputSchema: Record<string, unknown>;
  handler: (args: Record<string, unknown>) => Promise<{ content: Array<{ type: "text"; text: string }>; isError?: boolean }>;
};

export function buildCatalogTools(visible: () => Promise<ToolDef[]>): CatalogTool[] {
  const catalog: CatalogTool = {
    name: "tools_catalog",
    description:
      "List available AppDashboard tool groups with tool counts and short descriptions. " +
      "Call this FIRST instead of loading a huge flat tool list, then load only the groups you need with tools_load.",
    minRole: 0,
    inputSchema: { type: "object", properties: {} },
    handler: async () => {
      const tools = await visible();
      return textResult({
        groups: availableGroups(tools),
        hint:
          "Use tools_load {groups:[...]} to get full schemas for only the groups you need, " +
          "or tools_search {query} to find tools across groups by keyword.",
      });
    },
  };

  const load: CatalogTool = {
    name: "tools_load",
    description:
      "Load full tool definitions (name, description, schema) for one or more groups from tools_catalog. " +
      'Example: {"groups":["kanboard"]}. Load only groups relevant to the current task to save context.',
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        groups: { type: "array", items: { type: "string" }, description: "Group ids from tools_catalog" },
      },
      required: ["groups"],
    },
    handler: async (args) => {
      const tools = await visible();
      const requested = Array.isArray(args.groups) ? args.groups.map(String) : [];
      if (requested.includes("all")) {
        return textResult({ tools: tools.map(summarize) });
      }
      const groupFilter = parseGroupList(process.env.MCP_GROUPS);
      const valid = new Set(TOOL_GROUPS.map((g) => g.id));
      const allowed = (gid: string) => valid.has(gid) && (!groupFilter.size || groupFilter.has(gid) || groupFilter.has("all"));
      const unknown = requested.filter((g) => !allowed(g));
      const loaded: ToolDef[] = [];
      const seen = new Set<string>();
      for (const gid of requested.filter(allowed)) {
        for (const t of toolsInGroup(tools, gid)) {
          if (!seen.has(t.name)) {
            seen.add(t.name);
            loaded.push(t);
          }
        }
      }
      return textResult({
        groups: requested.filter(allowed),
        unknown_groups: unknown,
        tools: loaded.map(summarize),
      });
    },
  };

  const search: CatalogTool = {
    name: "tools_search",
    description:
      "Search all available AppDashboard tools by keyword in name or description. " +
      "Returns matching tool definitions with schemas. Use when you know what you need but not which group it belongs to.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        query: { type: "string", description: "Keyword, case-insensitive" },
        limit: { type: "number", description: "Max results (default 25)" },
      },
      required: ["query"],
    },
    handler: async (args) => {
      const tools = await visible();
      const q = String(args.query || "").toLowerCase();
      const limit = Number(args.limit || 25) || 25;
      const matches = tools.filter(
        (t) => t.name.toLowerCase().includes(q) || t.description.toLowerCase().includes(q),
      );
      return textResult({
        query: q,
        total: matches.length,
        tools: matches.slice(0, limit).map((t) => ({ ...summarize(t), group: groupForTool(t.name) })),
      });
    },
  };

  const checkUpdate = {
    name: "mcp_check_update",
    description:
      "Check if a newer version of the AppDashboard MCP package is available and auto-update if so. " +
      "Call when the user asks to update the MCP server, or when a tool the user mentions seems to be missing.",
    minRole: 0 as const,
    inputSchema: { type: "object", properties: {} },
    handler: async () => textResult(await checkForUpdateNow()),
  };

  return [catalog, load, search, checkUpdate];
}

function summarize(t: ToolDef) {
  return {
    name: t.name,
    description: t.description,
    inputSchema: t.inputSchema,
  };
}

export function catalogSummaries(): string[] {
  return buildCatalogTools(async () => []).map((t) => t.name);
}
