#!/usr/bin/env node
import { Server } from "@modelcontextprotocol/sdk/server/index.js";
import { StdioServerTransport } from "@modelcontextprotocol/sdk/server/stdio.js";
import { StreamableHTTPServerTransport } from "@modelcontextprotocol/sdk/server/streamableHttp.js";
import { CallToolRequestSchema, ListToolsRequestSchema } from "@modelcontextprotocol/sdk/types.js";
import express from "express";
import { randomUUID } from "node:crypto";
import { authStorage, ensureAuth, getHost, getToken, startBrowserAuthInBackground } from "./auth.js";
import { callTool, listToolsForCurrentUser } from "./tools/index.js";
import { runSetup } from "./setup.js";

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
    console.error("[appdashboard-mcp] No token yet — starting browser auth (non-blocking). Only login tool is listed until authenticated.");
    startBrowserAuthInBackground();
  } else if (!httpMode) {
    void ensureAuth({ interactive: false }).catch(() => undefined);
  }

  if (httpMode) {
    const app = express();
    const transports = new Map<string, { server: Server; transport: StreamableHTTPServerTransport }>();
    app.disable("x-powered-by");
    app.use(express.json({ limit: "10mb", type: ["application/json", "application/*+json"] }));
    app.all("/mcp", async (req, res) => {
      const authHeader = req.header("authorization") || "";
      const token = authHeader.match(/^Bearer\s+(.+)$/i)?.[1]?.trim() || "";
      if (!token) {
        res.status(401).json({ error: "missing_bearer_token" });
        return;
      }
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
      await authStorage.run({ token, deviceId: "mcp_external" }, async () => {
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
  const raw = argv[index].includes("=") ? argv[index].split("=", 2)[1] : argv[index + 1];
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
