import test from "node:test";
import assert from "node:assert/strict";

const { SlidingWindowLimiter } = await import("../dist/rate_limiter.js");

test("allows exactly max hits and reports Retry-After", () => {
  const limiter = new SlidingWindowLimiter(1000, 3);
  assert.equal(limiter.hit("a").allowed, true);
  assert.equal(limiter.hit("a").allowed, true);
  assert.equal(limiter.hit("a").allowed, true);
  const blocked = limiter.hit("a");
  assert.equal(blocked.allowed, false);
  assert.ok(blocked.retryAfterMs > 0);
  assert.equal(limiter.hit("b").allowed, true);
});

test("expires hits after the window", async () => {
  const limiter = new SlidingWindowLimiter(40, 1);
  assert.equal(limiter.hit("a").allowed, true);
  assert.equal(limiter.hit("a").allowed, false);
  await new Promise((resolve) => setTimeout(resolve, 60));
  assert.equal(limiter.hit("a").allowed, true);
});

test("large limits keep every slot and compact without shifting", () => {
  const max = 200_000;
  const limiter = new SlidingWindowLimiter(60_000, max);
  for (let i = 0; i < max; i++) {
    if (!limiter.hit("a").allowed) throw new Error(`unexpected rejection at ${i}`);
  }
  assert.equal(limiter.hit("a").allowed, false);
});
