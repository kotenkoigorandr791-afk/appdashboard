import test from "node:test";
import assert from "node:assert/strict";

const { ALL_TOOLS } = await import("../dist/tools/index.js");
const { buildCatalogTools } = await import("../dist/tools/catalog.js");
const { filterToolsByRole } = await import("../dist/roles.js");
const { groupForTool, parseGroupList } = await import("../dist/tools/groups.js");
const { textResult } = await import("../dist/api.js");

test("tool inventory has unique names and object input schemas", () => {
  const names = new Set();
  for (const tool of ALL_TOOLS) {
    assert.equal(typeof tool.name, "string");
    assert.ok(tool.name.length > 0);
    assert.ok(!names.has(tool.name), `duplicate tool name: ${tool.name}`);
    names.add(tool.name);
    assert.equal(typeof tool.description, "string");
    assert.equal(tool.inputSchema && typeof tool.inputSchema, "object");
    assert.equal(tool.inputSchema.type, "object");
    assert.equal(typeof tool.handler, "function");
  }
});

test("tools_load all returns the whole visible catalog and groups stay scoped", async () => {
  const catalog = buildCatalogTools(async () => ALL_TOOLS);
  const byName = new Map(catalog.map((t) => [t.name, t]));
  const load = byName.get("tools_load");
  assert.ok(load);

  const all = JSON.parse((await load.handler({ groups: ["all"] })).content[0].text);
  assert.equal(all.tools.length, ALL_TOOLS.length);

  const core = JSON.parse((await load.handler({ groups: ["core"] })).content[0].text);
  assert.deepEqual(
    core.tools.map((t) => t.name).sort(),
    ["delete_shared_file", "get_current_user", "get_shared_files", "list_apps", "update_share_expiration"],
  );
});

test("role filtering hides privileged tools from lower roles", () => {
  const guest = filterToolsByRole(ALL_TOOLS, 0).map((t) => t.name);
  const manager = filterToolsByRole(ALL_TOOLS, 1).map((t) => t.name);
  const admin = filterToolsByRole(ALL_TOOLS, 2).map((t) => t.name);
  assert.ok(guest.includes("list_apps"));
  assert.ok(!guest.includes("list_containers"));
  assert.ok(manager.includes("list_containers"));
  assert.ok(!manager.includes("fm_read_file"));
  assert.ok(admin.includes("fm_read_file"));
  assert.ok(guest.length < manager.length && manager.length < admin.length);
});

test("group filter parsing and tool-to-group mapping are stable", () => {
  const filter = parseGroupList("core, docker,,");
  assert.deepEqual([...filter].sort(), ["core", "docker"]);
  assert.equal(groupForTool("get_current_user"), "core");
  assert.equal(groupForTool("fm_read_file"), "files");
  assert.equal(groupForTool("cloud_list"), "cloud");
});

test("catalog search and compact JSON output work", async () => {
  const catalog = buildCatalogTools(async () => ALL_TOOLS);
  const byName = new Map(catalog.map((t) => [t.name, t]));
  const search = JSON.parse((await byName.get("tools_search").handler({ query: "docker", limit: 5 })).content[0].text);
  assert.ok(search.total > 0);
  assert.ok(search.tools.length <= 5);

  const compact = textResult({ ok: true, items: [1, 2, 3] });
  assert.equal(compact.content[0].text, '{"ok":true,"items":[1,2,3]}');
});
