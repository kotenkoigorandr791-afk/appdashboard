#!/usr/bin/env node
import { mkdirSync, writeFileSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";
import { ALL_TOOLS } from "../dist/tools/index.js";
import { groupForTool } from "../dist/tools/groups.js";
import { TOOL_MIN_ROLE } from "../dist/roles.js";

const HERE = dirname(fileURLToPath(import.meta.url));
const tools = ALL_TOOLS.map((t) => {
  const props = t.inputSchema?.properties ?? {};
  return {
    name: t.name,
    group: groupForTool(t.name),
    min_role: TOOL_MIN_ROLE[t.name] ?? t.minRole,
    description: t.description,
    required: Array.isArray(t.inputSchema?.required) ? t.inputSchema.required : [],
    properties: Object.keys(props),
  };
});

const groups = {};
for (const tool of tools) {
  groups[tool.group] ??= { tool_count: 0, min_role: 2, tools: [] };
  groups[tool.group].tool_count++;
  groups[tool.group].min_role = Math.min(groups[tool.group].min_role, tool.min_role);
  groups[tool.group].tools.push(tool.name);
}

const out = {
  generated_at: new Date().toISOString(),
  total: tools.length,
  unique_names: new Set(tools.map((t) => t.name)).size,
  groups,
  tools,
};
mkdirSync(join(HERE, "results"), { recursive: true });
const path = join(HERE, "results", "tool-inventory.json");
writeFileSync(path, JSON.stringify(out, null, 2) + "\n");
console.log(`[inventory] ${tools.length} tools, ${Object.keys(groups).length} groups -> ${path}`);
for (const [id, g] of Object.entries(groups)) console.log(`${id.padEnd(12)} ${String(g.tool_count).padStart(3)} tools  min_role=${g.min_role}`);
