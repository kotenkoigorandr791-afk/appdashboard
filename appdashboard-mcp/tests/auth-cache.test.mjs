import test from "node:test";
import assert from "node:assert/strict";

process.env.APPDASHBOARD_URL = "http://bench.local";
process.env.MCP_AUTH_CACHE_TTL_MS = "30000";
process.env.MCP_AUTH_FAILURE_TTL_MS = "5000";

const { authStorage, fetchMe, resetAuthCacheForTests, getCachedRoleForToken } = await import("../dist/auth.js");

const originalFetch = globalThis.fetch;
let calls = [];

globalThis.fetch = async (_url, init) => {
  const auth = new Headers(init.headers).get("authorization") || "";
  const token = auth.replace(/^Bearer\s+/i, "");
  calls.push(token);
  await new Promise((resolve) => setTimeout(resolve, 15));
  if (token === "bad") {
    return new Response(JSON.stringify({ ok: false, error: "invalid_token" }), {
      status: 401,
      headers: { "content-type": "application/json" },
    });
  }
  const role = token === "admin" ? 2 : 1;
  return new Response(JSON.stringify({ ok: true, user: { email: `${token}@example.test`, role } }), {
    status: 200,
    headers: { "content-type": "application/json" },
  });
};

test.after(() => {
  globalThis.fetch = originalFetch;
});

function asUser(token, fn) {
  return authStorage.run({ token, deviceId: "bench-device" }, fn);
}

test("concurrent and repeated fetchMe calls share one auth request per token", async () => {
  resetAuthCacheForTests();
  calls = [];
  const results = await Promise.all(
    Array.from({ length: 20 }, () => asUser("admin", () => fetchMe())),
  );
  assert.equal(calls.length, 1);
  assert.ok(results.every((r) => r && r.role === 2 && r.email === "admin@example.test"));

  for (let i = 0; i < 10; i++) await asUser("admin", () => fetchMe());
  assert.equal(calls.length, 1, "cache hit must not call /auth/me again");
  assert.equal(getCachedRoleForToken("admin"), 2);
});

test("different tokens never share cached identity", async () => {
  resetAuthCacheForTests();
  calls = [];
  const [admin, manager] = await Promise.all([
    asUser("admin", () => fetchMe()),
    asUser("manager", () => fetchMe()),
  ]);
  assert.equal(calls.length, 2);
  assert.equal(admin?.role, 2);
  assert.equal(manager?.role, 1);
  assert.equal(getCachedRoleForToken("admin"), 2);
  assert.equal(getCachedRoleForToken("manager"), 1);
});

test("authentication failures are negatively cached only briefly", async () => {
  resetAuthCacheForTests();
  calls = [];
  assert.equal(await asUser("bad", () => fetchMe()), null);
  assert.equal(await asUser("bad", () => fetchMe()), null);
  assert.equal(calls.length, 1, "concurrent/repeated failure must be deduplicated");
});

test("reset drops cached roles", async () => {
  resetAuthCacheForTests();
  calls = [];
  await asUser("admin", () => fetchMe());
  assert.equal(calls.length, 1);
  resetAuthCacheForTests();
  await asUser("admin", () => fetchMe());
  assert.equal(calls.length, 2);
});
