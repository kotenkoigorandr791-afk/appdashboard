#!/usr/bin/env node
import { Server } from "@modelcontextprotocol/sdk/server/index.js";
import { StdioServerTransport } from "@modelcontextprotocol/sdk/server/stdio.js";
import { CallToolRequestSchema, ListToolsRequestSchema } from "@modelcontextprotocol/sdk/types.js";
import { ensureAuth, getHost, getToken, startBrowserAuthInBackground } from "./auth.js";
import { callTool, listToolsForCurrentUser } from "./tools/index.js";
import { runSetup } from "./setup.js";

async function runServer(): Promise<void> {
  if (!getHost()) {
    console.error("[appdashboard-mcp] Warning: APPDASHBOARD_URL is not set.");
  } else if (!getToken()) {
    console.error("[appdashboard-mcp] No token yet — starting browser auth (non-blocking). Only login tool is listed until authenticated.");
    startBrowserAuthInBackground();
  } else {
    void ensureAuth({ interactive: false }).catch(() => undefined);
  }

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

  const transport = new StdioServerTransport();
  await server.connect(transport);
}

async function main(): Promise<void> {
  const argv = process.argv.slice(2);
  if (argv[0] === "setup") {
    await runSetup(argv);
    return;
  }
  await runServer();
}

main().catch((err) => {
  console.error("[appdashboard-mcp] Fatal:", err);
  process.exit(1);
});
