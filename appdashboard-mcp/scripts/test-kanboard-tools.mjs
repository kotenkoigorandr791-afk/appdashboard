#!/usr/bin/env node
import { Client } from "@modelcontextprotocol/sdk/client/index.js";
import { StdioClientTransport } from "@modelcontextprotocol/sdk/client/stdio.js";
import { fileURLToPath } from "node:url";
import { readFileSync } from "node:fs";
import { homedir } from "node:os";
import { join } from "node:path";

const PROJECT = "MCP Demo";
const results = [];
function record(name, ok, detail) {
  results.push({ name, ok });
  console.log(`${ok ? "PASS" : "FAIL"}  ${name}  ${String(detail).replace(/\s+/g, " ").slice(0, 200)}`);
}
const client = new Client({ name: "kanboard-test", version: "1.0.0" });
async function call(name, args = {}) {
  const res = await client.callTool({ name: "tools_call", arguments: { name, arguments: args } });
  const text = res.content?.[0]?.text ?? "";
  let data;
  try {
    data = JSON.parse(text);
  } catch {
    data = text;
  }
  const ok = !res.isError && !(data && typeof data === "object" && data.error);
  record(name, ok, text);
  if (!ok) throw new Error(`${name} failed: ${text}`);
  return data;
}
function serverParams() {
  if (!process.argv.includes("--installed")) {
    return {
      command: process.execPath,
      args: [fileURLToPath(new URL("../dist/index.js", import.meta.url))],
      env: { ...process.env, APPDASHBOARD_MCP_AUTOUPDATE: "0" },
    };
  }
  const entry = JSON.parse(readFileSync(join(homedir(), ".cursor", "mcp.json"), "utf8")).mcpServers["appdashboard-mcp"];
  return { command: entry.command, args: entry.args, env: { ...process.env, ...entry.env } };
}
async function main() {
  const transport = new StdioClientTransport({ ...serverParams(), stderr: "inherit" });
  await client.connect(transport);
  const listed = await client.listTools();
  record("tools/list", listed.tools.some((t) => t.name === "tools_call"), listed.tools.map((t) => t.name).join(","));
  const loaded = await client.callTool({ name: "tools_load", arguments: { groups: ["kanboard"] } });
  const loadedText = loaded.content?.[0]?.text ?? "";
  const toolNames = loadedText.match(/"kanboard_\w+"/g) || [];
  record(
    "tools_load kanboard",
    /kanboard_update_project/.test(loadedText) &&
      /kanboard_delete_task/.test(loadedText) &&
      /kanboard_stats/.test(loadedText) &&
      !/kanboard_create_project/.test(loadedText),
    `${toolNames.length} tools`,
  );

  const projects = await call("kanboard_list_projects");
  let project = projects.find((p) => p.name === PROJECT) || projects[0];
  if (!project) throw new Error("no project available for MCP test (create one in UI first)");
  record("has project", !!project.id, project.name);

  const renamed = `${PROJECT} ${Date.now().toString().slice(-4)}`;
  const updated = await call("kanboard_update_project", {
    project_id: project.id,
    description: "MCP updated description",
  });
  record("update project", updated.id === project.id, updated.description);
  await call("kanboard_update_project", { project_id: project.id, name: project.name });

  const stats = await call("kanboard_stats", { project_id: project.id });
  record("stats columns", Array.isArray(stats.columns) && stats.columns.length >= 1, JSON.stringify(stats).slice(0, 120));
  const columnId = stats.columns?.[1]?.id || stats.columns?.[0]?.id;

  const title = `MCP Task ${Date.now()}`;
  const created = await call("kanboard_create_task", {
    project_id: project.id,
    title,
    description: "from MCP test",
    priority: 2,
  });
  record("create task", created.title === title, `#${created.id}`);

  const moved = await call("kanboard_update_task", {
    task_id: created.id,
    column_id: columnId,
    priority: 3,
  });
  record("update task", moved.id === created.id && moved.priority === 3, moved.column);

  const listedTasks = await call("kanboard_list_tasks", { project_id: project.id });
  record("list tasks", listedTasks.some((t) => t.id === created.id), listedTasks.length);

  const deleted = await call("kanboard_delete_task", { task_id: created.id });
  record("delete task", deleted.deleted === created.id, deleted.deleted);
  const after = await call("kanboard_list_tasks", { project_id: project.id });
  record("deleted gone", !after.some((t) => t.id === created.id), after.length);

  await client.close();
  const failed = results.filter((r) => !r.ok);
  console.log(`\npassed=${results.length - failed.length} failed=${failed.length}`);
  process.exit(failed.length ? 1 : 0);
}
main().catch(async (e) => {
  console.error(e.message);
  await client.close().catch(() => {});
  process.exit(1);
});
