import test from "node:test";
import assert from "node:assert/strict";

process.env.APPDASHBOARD_URL = "http://bench.local";
process.env.MCP_GROUPS = "core";

const { authStorage, resetAuthCacheForTests } = await import("../dist/auth.js");
const { callTool } = await import("../dist/tools/index.js");

globalThis.fetch = async (url) => {
  const href = String(url);
  if (href.endsWith("/public-api/auth/me")) {
    return new Response(JSON.stringify({ ok: true, user: { email: "admin@example.test", role: 2 } }), {
      status: 200,
      headers: { "content-type": "application/json" },
    });
  }
  if (href.endsWith("/public-api/docker/containers")) {
    return new Response(JSON.stringify({ ok: true, containers: [] }), {
      status: 200,
      headers: { "content-type": "application/json" },
    });
  }
  throw new Error(`unexpected fetch: ${href}`);
};

function asAdmin(fn) {
  return authStorage.run({ token: "group-admin-token", deviceId: "bench-device" }, fn);
}

test("MCP_GROUPS rejects a disabled group before the handler runs", async () => {
  process.env.MCP_GROUPS = "core";
  resetAuthCacheForTests();
  const result = await asAdmin(() => callTool("list_containers", {}));
  const data = JSON.parse(result.content[0].text);
  assert.equal(data.error, "group_not_allowed");
  assert.equal(data.group, "docker");
});

test("MCP_GROUPS=all allows the same group", async () => {
  process.env.MCP_GROUPS = "all";
  resetAuthCacheForTests();
  const result = await asAdmin(() => callTool("list_containers", {}));
  const data = JSON.parse(result.content[0].text);
  assert.equal(data.ok, true);
  assert.deepEqual(data.containers, []);
});
