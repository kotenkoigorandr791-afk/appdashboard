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

function parseArgs(argv: string[]): { agent: Agent; url?: string; packageUrl?: string } {
  let agent: Agent = "cursor";
  let url: string | undefined;
  let packageUrl: string | undefined;
  for (let i = 0; i < argv.length; i++) {
    const a = argv[i];
    if ((a === "--agent" || a === "-a") && argv[i + 1]) agent = argv[++i] as Agent;
    else if ((a === "--url" || a === "-u") && argv[i + 1]) url = argv[++i];
    else if ((a === "--package" || a === "--package-url" || a === "-p") && argv[i + 1]) packageUrl = argv[++i];
    else if (a.startsWith("--agent=")) agent = a.slice("--agent=".length) as Agent;
    else if (a.startsWith("--url=")) url = a.slice("--url=".length);
    else if (a.startsWith("--package=")) packageUrl = a.slice("--package=".length);
    else if (a.startsWith("--package-url=")) packageUrl = a.slice("--package-url=".length);
  }
  if (!["claude", "cursor", "vscode", "opencode"].includes(agent)) {
    throw new Error(`Unknown agent: ${agent}. Use claude|cursor|vscode|opencode`);
  }
  return { agent, url, packageUrl };
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

function mergeServerEntry(agent: Agent, config: Record<string, any>, url: string, packageUrl?: string): Record<string, any> {
  const entry = serverEntry(url, packageUrl);
  const next = { ...config };
  if (agent === "vscode") {
    next.servers = { ...(next.servers || {}), appdashboard: entry };
  } else if (agent === "opencode") {
    next.mcp = { ...(next.mcp || {}), appdashboard: entry };
  } else {
    next.mcpServers = { ...(next.mcpServers || {}), appdashboard: entry };
  }
  return next;
}

export async function runSetup(argv: string[] = process.argv.slice(2)): Promise<void> {
  const { agent, url: urlArg, packageUrl: pkgArg } = parseArgs(argv.filter((a) => a !== "setup"));
  const interactive = !urlArg && !process.env.APPDASHBOARD_URL;
  const rl = interactive ? createInterface({ input, output }) : null;
  try {
    let url = (urlArg || process.env.APPDASHBOARD_URL || "").trim();
    if (!url && rl) {
      url = (await rl.question("AppDashboard URL (e.g. https://example.com): ")).trim();
    }
    url = url.replace(/\/+$/, "");
    if (!url) throw new Error("URL is required (--url or APPDASHBOARD_URL)");
    let packageUrl = (pkgArg || process.env.APPDASHBOARD_MCP_PACKAGE_URL || "").trim();
    if (!packageUrl) packageUrl = `${url}/mcp/download`;
    const path = agentConfigPath(agent);
    const existing = readJson(path);
    const merged = mergeServerEntry(agent, existing, url, packageUrl);
    writeJson(path, merged);
    console.log(`Updated ${path}`);
    console.log(`APPDASHBOARD_URL=${url}`);
    console.log(`Package: ${packageUrl}`);
    console.log("No local build needed — npx downloads the prebuilt package.");
    console.log("On first use, open the browser auth URL printed by the MCP server (or use the login tool).");
    console.log("Restart the agent to load the MCP server.");
  } finally {
    rl?.close();
  }
}

export { agentConfigPath, entryScript, serverEntry };
