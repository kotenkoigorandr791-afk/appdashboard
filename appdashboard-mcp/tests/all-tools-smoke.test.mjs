import test from "node:test";
import assert from "node:assert/strict";
import { mkdirSync, writeFileSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";

process.env.APPDASHBOARD_URL = "http://bench.local";
process.env.MCP_GROUPS = "";

const { ALL_TOOLS } = await import("../dist/tools/index.js");
const { callTool } = await import("../dist/tools/index.js");
const { authStorage, resetAuthCacheForTests } = await import("../dist/auth.js");

const HERE = dirname(fileURLToPath(import.meta.url));
const generic = {
  ok: true,
  result: [],
  items: [],
  data: [],
  files: [],
  folders: [],
  containers: [],
  expenses: [],
  incomes: [],
  stores: [],
  categories: [],
  recipes: [],
  products: [],
  steps: [],
  locations: [],
  albums: [],
  assets: [],
  repos: [],
  issues: [],
  tasks: [],
  projects: [],
  columns: [],
  articles: [],
  projects_list: [],
  events: [],
  devices: [],
  scenes: [],
  rooms: [],
  messages: [],
  snapshots: [],
  jobs: [],
  shares: [],
  users: [],
  user: { email: "smoke@example.test", name: "Smoke", role: 2 },
  url: "http://integration.test",
  api_key: "test-key",
  access_token: "test-token",
  api_token: "test-token",
  token: "test-token",
  username: "smoke",
  status: "ok",
};

globalThis.fetch = async (url) => {
  const href = String(url);
  if (href.endsWith("/public-api/auth/me")) {
    return json({ ...generic, user: { email: "smoke@example.test", name: "Smoke", role: 2 } });
  }
  if (href.includes("/public-api/integrations/immich")) {
    return json({ ...generic, url: "http://immich.test", api_key: "test-key" });
  }
  if (href.includes("/public-api/integrations/gitea")) {
    return json({ ...generic, url: "http://gitea.test", access_token: "test-token" });
  }
  if (href.includes("/public-api/integrations/kanboard")) {
    return json({ ...generic, url: "http://kanboard.test", username: "smoke", api_token: "test-token" });
  }
  if (href.includes("/public-api/integrations/sentry")) {
    return json({ ...generic, url: "http://sentry.test", token: "test-token" });
  }
  return json(generic);
};

function json(body) {
  return new Response(JSON.stringify(body), { status: 200, headers: { "content-type": "application/json" } });
}

function sampleArgs(tool) {
  const props = tool.inputSchema?.properties ?? {};
  const required = Array.isArray(tool.inputSchema?.required) ? tool.inputSchema.required : [];
  const out = {};
  for (const key of required) out[key] = sampleValue(key, props[key]);
  return out;
}

function sampleValue(key, schema) {
  const type = schema?.type;
  if (key === "groups") return ["core"];
  if (type === "array") return [];
  if (type === "number" || type === "integer") return 1;
  if (type === "boolean") return true;
  if (type === "object") return {};
  if (/local_path|file_path|image_path|photo_path/.test(key)) return ".bench-does-not-exist";
  if (/date|from|until/.test(key)) return "2026-01-01";
  return "1";
}

test("every registered tool returns a valid MCP result shape", async () => {
  const rows = [];
  let invalid = 0;
  for (const tool of ALL_TOOLS) {
    const result = await authStorage.run({ token: "smoke-admin", deviceId: "smoke-device" }, () =>
      callTool(tool.name, sampleArgs(tool)),
    );
    const valid = Array.isArray(result?.content) && result.content.length > 0 &&
      result.content.every((c) => c && (c.type === "text" || c.type === "image"));
    if (!valid) invalid++;
    rows.push({
      name: tool.name,
      valid,
      is_error: Boolean(result?.isError),
      error: result?.isError ? String(result.content?.[0]?.text ?? "").slice(0, 240) : undefined,
    });
  }
  mkdirSync(join(HERE, "..", "benchmarks", "results"), { recursive: true });
  writeFileSync(
    join(HERE, "..", "benchmarks", "results", "all-tools-smoke.json"),
    JSON.stringify({ generated_at: new Date().toISOString(), total: ALL_TOOLS.length, invalid, rows }, null, 2) + "\n",
  );
  console.log(`[smoke] exercised ${rows.length} tools, invalid_result_shapes=${invalid}, tool_errors=${rows.filter((r) => r.is_error).length}`);
  assert.equal(invalid, 0);
});
