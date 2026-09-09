#!/usr/bin/env node
import { Server } from "@modelcontextprotocol/sdk/server/index.js";
import { StdioServerTransport } from "@modelcontextprotocol/sdk/server/stdio.js";
import { StreamableHTTPServerTransport } from "@modelcontextprotocol/sdk/server/streamableHttp.js";
import { CallToolRequestSchema, ListToolsRequestSchema } from "@modelcontextprotocol/sdk/types.js";
import express from "express";
import { randomUUID } from "node:crypto";
import { authStorage, ensureAuth, getHost, getToken } from "./auth.js";
import { callTool, listToolsForCurrentUser } from "./tools/index.js";
import { runSetup } from "./setup.js";
import {
  authorizationServerMetadata,
  handleAuthorize,
  protectedResourceMetadata,
  publicBase,
} from "./oauth.js";

const BEARER_RE = /^[a-f0-9]{64}$/i;

class SlidingWindowLimiter {
  private hits = new Map<string, number[]>();
  constructor(private windowMs: number, private max: number) {}
  hit(key: string): { allowed: boolean; retryAfterMs: number } {
    const now = Date.now();
    let arr = this.hits.get(key);
    if (!arr) {
      arr = [];
      this.hits.set(key, arr);
    }
    while (arr.length > 0 && now - arr[0]! > this.windowMs) arr.shift();
    if (arr.length >= this.max) {
      return { allowed: false, retryAfterMs: Math.max(1, this.windowMs - (now - arr[0]!)) };
    }
    arr.push(now);
    if (this.hits.size > 10000) {
      for (const [k, v] of this.hits) {
        if (v.length === 0 || now - v[v.length - 1]! > this.windowMs * 2) this.hits.delete(k);
      }
    }
    return { allowed: true, retryAfterMs: 0 };
  }
}

function clientIp(req: express.Request): string {
  return (req.socket.remoteAddress || "unknown").replace(/^::ffff:/, "");
}

function createMcpServer(): Server {
  const server = new Server(
    { name: "appdashboard-mcp", version: "1.0.0" },
    { capabilities: { tools: {} } },
  );

  server.setRequestHandler(ListToolsRequestSchema, async () => ({
    tools: await listToolsForCurrentUser(),
  }));

  server.setRequestHandler(CallToolRequestSchema, async (request) => {
    const name = request.params.name;
    const args = (request.params.arguments || {}) as Record<string, unknown>;
    return callTool(name, args);
  });

  return server;
}

async function runServer(httpMode = false, port = 8808): Promise<void> {
  if (!getHost()) {
    console.error("[appdashboard-mcp] Warning: APPDASHBOARD_URL is not set.");
  } else if (!httpMode && !getToken()) {
    console.error("[appdashboard-mcp] No token configured. Run setup with --token or set APPDASHBOARD_TOKEN.");
  } else if (!httpMode) {
    void ensureAuth({ interactive: false }).catch(() => undefined);
  }

  if (httpMode) {
    const app = express();
    const transports = new Map<string, { server: Server; transport: StreamableHTTPServerTransport }>();
    const mcpLimiter = new SlidingWindowLimiter(60_000, 120);
    const oauthLimiter = new SlidingWindowLimiter(60_000, 40);
    const failLimiter = new SlidingWindowLimiter(60_000, 10);
    app.disable("x-powered-by");
    app.use((req, res, next) => {
      res.setHeader("X-Content-Type-Options", "nosniff");
      res.setHeader("Access-Control-Allow-Methods", "GET, POST, DELETE, OPTIONS");
      res.setHeader("Access-Control-Allow-Headers", "Authorization, Content-Type, X-Device-Id, X-Request-Id, MCP-Session-Id, Mcp-Session-Id, MCP-Protocol-Version, Mcp-Protocol-Version, Last-Event-ID");
      res.setHeader("Access-Control-Expose-Headers", "MCP-Session-Id, Mcp-Session-Id, MCP-Protocol-Version, Mcp-Protocol-Version");
      res.setHeader("Access-Control-Max-Age", "600");
      const origin = req.header("origin") || "";
      const allowedOrigins = (process.env.MCP_CORS_ORIGINS || "")
        .split(",")
        .map((o) => o.trim())
        .filter(Boolean)
        .concat(["http://localhost:8808", "http://127.0.0.1:8808"]);
      if (origin && allowedOrigins.includes(origin)) {
        res.setHeader("Access-Control-Allow-Origin", origin);
        res.setHeader("Vary", "Origin");
      }
      if (req.method === "OPTIONS") {
        res.status(204).end();
        return;
      }
      next();
    });
    app.use(express.json({ limit: "10mb", type: ["application/json", "application/*+json"] }));
    app.use(express.urlencoded({ extended: false, limit: "1mb" }));

    const rateLimit = (limiter: SlidingWindowLimiter) => (req: express.Request, res: express.Response, next: express.NextFunction) => {
      const { allowed, retryAfterMs } = limiter.hit(clientIp(req));
      if (!allowed) {
        res.setHeader("Retry-After", String(Math.ceil(retryAfterMs / 1000)));
        res.status(429).json({ error: "rate_limited" });
        return;
      }
      next();
    };

    app.get("/.well-known/oauth-protected-resource", (req, res) => {
      res.setHeader("Cache-Control", "no-store");
      res.json(protectedResourceMetadata());
    });
    app.get("/.well-known/oauth-protected-resource/mcp", (req, res) => {
      res.setHeader("Cache-Control", "no-store");
      res.json(protectedResourceMetadata());
    });
    app.get("/.well-known/mcp", (req, res) => {
      res.setHeader("Cache-Control", "no-store");
      res.json(protectedResourceMetadata());
    });
    app.get("/.well-known/oauth-authorization-server", (req, res) => {
      res.setHeader("Cache-Control", "no-store");
      res.json(authorizationServerMetadata());
    });
    app.get("/.well-known/openid-configuration", (req, res) => {
      res.setHeader("Cache-Control", "no-store");
      res.json(authorizationServerMetadata());
    });

    app.get("/oauth/authorize", rateLimit(oauthLimiter), handleAuthorize);

    const proxyOauth = async (action: string, req: express.Request, res: express.Response) => {
      const base = getHost();
      if (!base) {
        res.status(503).json({ error: "app_unreachable" });
        return;
      }
      const target = new URL(`${base}/public-api/oauth/${action}`);
      if (req.query && Object.keys(req.query).length > 0) {
        for (const [k, v] of Object.entries(req.query)) {
          if (typeof v === "string") target.searchParams.set(k, v);
        }
      }
      const headers: Record<string, string> = { "Content-Type": req.headers["content-type"] || "application/x-www-form-urlencoded" };
      if (req.headers.authorization) headers.Authorization = String(req.headers.authorization);
      const contentType = String(req.headers["content-type"] || "").toLowerCase();
      const body = ["GET", "HEAD"].includes(req.method.toUpperCase())
        ? undefined
        : contentType.includes("application/json")
          ? JSON.stringify(req.body ?? {})
          : new URLSearchParams(Object.entries(req.body ?? {}).map(([k, v]) => [k, typeof v === "string" ? v : JSON.stringify(v)])).toString();
      try {
        const upstream = await fetch(target.toString(), {
          method: req.method,
          headers,
          body,
        });
        const text = await upstream.text();
        res.status(upstream.status);
        res.setHeader("Content-Type", upstream.headers.get("content-type") || "application/json");
        res.setHeader("Cache-Control", "no-store");
        res.send(text);
      } catch {
        res.status(503).json({ error: "app_unreachable" });
      }
    };
    app.post("/oauth/register", rateLimit(oauthLimiter), (req, res) => void proxyOauth("register", req, res));
    app.post("/oauth/token", rateLimit(oauthLimiter), (req, res) => void proxyOauth("token", req, res));
    app.post("/oauth/revoke", rateLimit(oauthLimiter), (req, res) => void proxyOauth("revoke", req, res));
    app.get("/oauth/client", rateLimit(oauthLimiter), (req, res) => void proxyOauth("client", req, res));

    app.all("/mcp", rateLimit(mcpLimiter), async (req, res) => {
      const authHeader = req.header("authorization") || "";
      const token = authHeader.match(/^Bearer\s+(.+)$/i)?.[1]?.trim() || "";
      if (!token || !BEARER_RE.test(token)) {
        const fail = failLimiter.hit(`fail:${clientIp(req)}`);
        res.setHeader("WWW-Authenticate", `Bearer resource_metadata="${publicBase()}/.well-known/oauth-protected-resource"`);
        if (!fail.allowed) {
          res.setHeader("Retry-After", String(Math.ceil(fail.retryAfterMs / 1000)));
          res.status(429).json({ error: "rate_limited", error_description: "too many failed authentication attempts" });
          return;
        }
        res.status(401).json({ error: "missing_bearer_token" });
        return;
      }
      const deviceId = (req.header("x-device-id") || "").trim();
      res.setHeader("Cache-Control", "no-cache, no-transform");
      res.setHeader("X-Accel-Buffering", "no");
      const sessionId = req.header("mcp-session-id") || "";
      let entry = sessionId ? transports.get(sessionId) : undefined;
      if (sessionId && !entry) {
        res.status(404).json({ error: "session_not_found" });
        return;
      }
      if (!entry) {
        const server = createMcpServer();
        const transport = new StreamableHTTPServerTransport({ sessionIdGenerator: () => randomUUID() });
        transport.onerror = (err) => console.error("[appdashboard-mcp] HTTP transport error:", err);
        transport.onclose = () => {
          if (transport.sessionId) transports.delete(transport.sessionId);
          void server.close().catch(() => undefined);
        };
        await server.connect(transport);
        entry = { server, transport };
      }
      const activeEntry = entry;
      await authStorage.run({ token, deviceId }, async () => {
        await activeEntry.transport.handleRequest(req, res, req.body);
        if (activeEntry.transport.sessionId) transports.set(activeEntry.transport.sessionId, activeEntry);
      });
    });
    app.listen(port, "0.0.0.0", () => {
      console.error(`[appdashboard-mcp] HTTP transport listening on /mcp port ${port}`);
    });
    return;
  }

  const server = createMcpServer();
  const transport = new StdioServerTransport();
  await server.connect(transport);
}

function parsePort(argv: string[]): number {
  const index = argv.findIndex((arg) => arg === "--port" || arg.startsWith("--port="));
  if (index === -1) return 8808;
  const raw = argv[index]?.includes("=") ? argv[index].split("=", 2)[1] : argv[index + 1];
  if (!raw) return 8808;
  const port = Number(raw);
  return Number.isInteger(port) && port > 0 && port < 65536 ? port : 8808;
}

async function main(): Promise<void> {
  const argv = process.argv.slice(2);
  if (argv[0] === "setup") {
    await runSetup(argv);
    return;
  }
  await runServer(argv.includes("--http") || process.env.MCP_TRANSPORT === "http", parsePort(argv));
}

main().catch((err) => {
  console.error("[appdashboard-mcp] Fatal:", err);
  process.exit(1);
});
