#!/usr/bin/env node
import { createInterface } from "node:readline/promises";
import { stdin as input, stdout as output } from "node:process";
import { existsSync, mkdirSync, readFileSync, writeFileSync } from "node:fs";
import { homedir, platform } from "node:os";
import { dirname, join, resolve } from "node:path";
import { fileURLToPath } from "node:url";

type Agent = "claude" | "cursor" | "vscode" | "opencode";

function packageRoot(): string {
  const here = dirname(fileURLToPath(import.meta.url));
  return resolve(here, "..");
}

function entryScript(): string {
  return join(packageRoot(), "dist", "index.js");
}

function claudeConfigPath(): string {
  const p = platform();
  if (p === "darwin") return join(homedir(), "Library", "Application Support", "Claude", "claude_desktop_config.json");
  if (p === "win32") {
    const appdata = process.env.APPDATA || join(homedir(), "AppData", "Roaming");
    return join(appdata, "Claude", "claude_desktop_config.json");
  }
  return join(homedir(), ".config", "Claude", "claude_desktop_config.json");
}

function agentConfigPath(agent: Agent): string {
  switch (agent) {
    case "claude":
      return claudeConfigPath();
    case "cursor":
      return join(homedir(), ".cursor", "mcp.json");
    case "vscode":
      return join(process.cwd(), ".vscode", "mcp.json");
    case "opencode":
      return join(process.cwd(), "opencode.json");
  }
}

function readJson(path: string): Record<string, any> {
  if (!existsSync(path)) return {};
  try {
    return JSON.parse(readFileSync(path, "utf8")) as Record<string, any>;
  } catch {
    return {};
  }
}

function writeJson(path: string, data: unknown): void {
  mkdirSync(dirname(path), { recursive: true });
  writeFileSync(path, JSON.stringify(data, null, 2) + "\n", "utf8");
}

function parseArgs(argv: string[]): { agent: Agent; url?: string; packageUrl?: string; remove: boolean } {
  let agent: Agent = "cursor";
  let url: string | undefined;
  let packageUrl: string | undefined;
  let remove = false;
  for (let i = 0; i < argv.length; i++) {
    const a = argv[i];
    if ((a === "--agent" || a === "-a") && argv[i + 1]) agent = argv[++i] as Agent;
    else if ((a === "--url" || a === "-u") && argv[i + 1]) url = argv[++i];
    else if ((a === "--package" || a === "--package-url" || a === "-p") && argv[i + 1]) packageUrl = argv[++i];
    else if (a === "--remove" || a === "remove" || a === "uninstall") remove = true;
    else if (a.startsWith("--agent=")) agent = a.slice("--agent=".length) as Agent;
    else if (a.startsWith("--url=")) url = a.slice("--url=".length);
    else if (a.startsWith("--package=")) packageUrl = a.slice("--package=".length);
    else if (a.startsWith("--package-url=")) packageUrl = a.slice("--package-url=".length);
  }
  if (!["claude", "cursor", "vscode", "opencode"].includes(agent)) {
    throw new Error(`Unknown agent: ${agent}. Use claude|cursor|vscode|opencode`);
  }
  return { agent, url, packageUrl, remove };
}

function serverEntry(url: string, packageUrl?: string): Record<string, unknown> {
  const env = { APPDASHBOARD_URL: url };
  if (packageUrl) {
    return {
      command: "npx",
      args: ["-y", packageUrl],
      env,
    };
  }
  return {
    command: "node",
    args: [entryScript()],
    env,
  };
}

function opencodeServerEntry(url: string, packageUrl?: string): Record<string, unknown> {
  const command = packageUrl ? ["npx", "-y", packageUrl] : ["node", entryScript()];
  return {
    enabled: true,
    type: "local",
    command,
    environment: { APPDASHBOARD_URL: url },
  };
}

const SERVER_KEY = "appdashboard-mcp";

function mergeServerEntry(agent: Agent, config: Record<string, any>, url: string, packageUrl?: string): Record<string, any> {
  const baseEntry = serverEntry(url, packageUrl);
  const entry = agent === "opencode" ? opencodeServerEntry(url, packageUrl) : baseEntry;
  const next = { ...config };
  const stripLegacy = (bag: Record<string, unknown> | undefined, removeCurrent = false) => {
    if (!bag || typeof bag !== "object") return {};
    const out = { ...bag };
    for (const k of Object.keys(out)) {
      if ((removeCurrent && k === SERVER_KEY) || k === "appdashboard" || k === "appdashboard-cloud-mcp" || k === "appdashboard-smarthome-mcp" || k === "appdashboard-recipes-mcp") {
        delete out[k];
      }
    }
    return out;
  };
  if (agent === "vscode") {
    next.servers = { ...stripLegacy(next.servers), [SERVER_KEY]: entry };
  } else if (agent === "opencode") {
    next.mcp = { ...stripLegacy(next.mcp, true), [SERVER_KEY]: entry };
  } else {
    next.mcpServers = { ...stripLegacy(next.mcpServers), [SERVER_KEY]: entry };
  }
  return next;
}

function removeServerEntry(agent: Agent, config: Record<string, any>): Record<string, any> {
  const next = { ...config };
  if (agent === "vscode") {
    const servers = { ...(next.servers || {}) };
    delete servers[SERVER_KEY];
    next.servers = servers;
  } else if (agent === "opencode") {
    const mcp = { ...(next.mcp || {}) };
    delete mcp[SERVER_KEY];
    next.mcp = mcp;
  } else {
    const mcpServers = { ...(next.mcpServers || {}) };
    delete mcpServers[SERVER_KEY];
    next.mcpServers = mcpServers;
  }
  return next;
}

export async function runSetup(argv: string[] = process.argv.slice(2)): Promise<void> {
  const { agent, url: urlArg, packageUrl: pkgArg, remove } = parseArgs(argv.filter((a) => a !== "setup"));
  const interactive = !urlArg && !process.env.APPDASHBOARD_URL;
  const rl = interactive ? createInterface({ input, output }) : null;
  try {
    const path = agentConfigPath(agent);
    const existing = readJson(path);
    if (remove) {
      writeJson(path, removeServerEntry(agent, existing));
      console.log(`Removed ${SERVER_KEY} from ${path}`);
      console.log("Restart the agent to unload the MCP server.");
      return;
    }
    let url = (urlArg || process.env.APPDASHBOARD_URL || "").trim();
    if (!url && rl) {
      url = (await rl.question("AppDashboard URL (e.g. https://example.com): ")).trim();
    }
    url = url.replace(/\/+$/, "");
    if (!url) throw new Error("URL is required (--url or APPDASHBOARD_URL)");
    let packageUrl = (pkgArg || process.env.APPDASHBOARD_MCP_PACKAGE_URL || "").trim();
    if (!packageUrl) packageUrl = `${url}/mcp/download`;
    const merged = mergeServerEntry(agent, existing, url, packageUrl);
    writeJson(path, merged);
    console.log(`Updated ${path}`);
    console.log(`APPDASHBOARD_URL=${url}`);
    console.log(`Package: ${packageUrl}`);
    console.log("No local build needed - npx downloads the prebuilt package.");
    console.log("Login is required: after restart, open the browser auth URL printed by the MCP server or run the login tool.");
    console.log("Restart the agent to load the MCP server.");
  } finally {
    rl?.close();
  }
}

export { agentConfigPath, entryScript, serverEntry };
