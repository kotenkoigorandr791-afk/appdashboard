#!/usr/bin/env node
import { Client } from "@modelcontextprotocol/sdk/client/index.js";
import { StdioClientTransport } from "@modelcontextprotocol/sdk/client/stdio.js";
import { fileURLToPath } from "node:url";
import { readFileSync } from "node:fs";
import { homedir } from "node:os";
import { join } from "node:path";
const PROJECT = "MCP Demo";
const SEED = [
  { title: "Подготовить отчёт за сентябрь", priority: 3, color_id: "red", date_due: "2026-10-01", tags: ["report"] },
  { title: "Созвон с командой", priority: 1, color_id: "blue", date_due: "2026-09-29" },
  { title: "Обновить документацию MCP", priority: 2, color_id: "green", tags: ["docs", "mcp"] },
  { title: "Разобрать входящие задачи", priority: 0, color_id: "yellow" },
];
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
  record("tools_load kanboard", /kanboard_update_task/.test(loadedText), `${(loadedText.match(/"kanboard_\w+"/g) || []).length} kanboard tools`);
  const me = await call("kanboard_get_me");
  record("get_me hides secrets", !("api_access_token" in me) && !("twofactor_secret" in me), Object.keys(me).join(","));
  let project = (await call("kanboard_list_projects")).find((p) => p.name === PROJECT);
  if (!project) {
    const { project_id } = await call("kanboard_create_project", { name: PROJECT, description: "Тестовый проект для проверки MCP" });
    project = { id: project_id };
  }
  const board = await call("kanboard_get_board", { project_id: project.id });
  const columns = board.swimlanes[0].columns;
  record("board columns", columns.length >= 2, columns.map((c) => `${c.id}:${c.title}`).join(", "));
  const existing = await call("kanboard_list_tasks", { project_id: project.id });
  const created = [];
  for (const seed of SEED) {
    const found = existing.find((t) => t.title === seed.title);
    created.push(found ?? (await call("kanboard_create_task", { project_id: project.id, description: `Описание: ${seed.title}`, ...seed })));
  }
  record("seeded tasks assigned to me", created.every((t) => t.owner_id === me.id), created.map((t) => `#${t.id}`).join(" "));
  const target = created[0];
  const moved = await call("kanboard_update_task", { task_id: target.id, column_id: columns[1].id, priority: 3 });
  record("move to column", moved.column_id === columns[1].id, `${moved.column}`);
  await call("kanboard_add_comment", { task_id: target.id, content: "Комментарий из MCP-теста" });
  const details = await call("kanboard_get_task", { task_id: target.id });
  record("comment visible", details.comments.some((c) => c.content === "Комментарий из MCP-теста"), details.comments.length);
  const scratchTitle = "Временная задача (закрыть)";
  const closedBefore = await call("kanboard_list_tasks", { project_id: project.id, status: "closed" });
  let scratch = closedBefore.find((t) => t.title === scratchTitle);
  if (scratch) {
    const reopened = await call("kanboard_update_task", { task_id: scratch.id, status: "open" });
    record("reopen task", reopened.status === "open", reopened.status);
  } else {
    scratch = await call("kanboard_create_task", { project_id: project.id, title: scratchTitle });
  }
  record("task url", /\/apps\/kanboard\/\?controller=/.test(scratch.url), scratch.url);
  const closed = await call("kanboard_update_task", { task_id: scratch.id, status: "closed" });
  record("close task", closed.status === "closed", closed.status);
  const closedList = await call("kanboard_list_tasks", { project_id: project.id, status: "closed" });
  record("closed list", closedList.some((t) => t.id === scratch.id), closedList.length);
  const mine = await call("kanboard_list_tasks");
  record("my tasks", created.every((t) => mine.some((m) => m.id === t.id)), mine.length);
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
