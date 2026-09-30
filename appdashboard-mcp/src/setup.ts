#!/usr/bin/env node
import { createInterface } from "node:readline/promises";
import { stdin as input, stdout as output } from "node:process";
import { existsSync, mkdirSync, readFileSync, writeFileSync } from "node:fs";
import { homedir, platform } from "node:os";
import { dirname, join, resolve } from "node:path";
import { fileURLToPath } from "node:url";
import { saveTokenCredentials } from "./auth.js";
import { normalizeRole, type Role } from "./roles.js";
import { writeLocalFingerprint } from "./updater.js";

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
      return opencodeConfigPath();
  }
}

function opencodeConfigPath(): string {
  const dir = join(homedir(), ".config", "opencode");
  const jsonc = join(dir, "opencode.jsonc");
  if (existsSync(jsonc)) return jsonc;
  return join(dir, "opencode.json");
}

function readJson(path: string): Record<string, any> {
  if (!existsSync(path)) return {};
  try {
    return JSON.parse(stripJsonComments(readFileSync(path, "utf8"))) as Record<string, any>;
  } catch {
    return {};
  }
}

function stripJsonComments(input: string): string {
  return input
    .replace(/^\uFEFF/, "")
    .replace(/\/\*[\s\S]*?\*\//g, "")
    .replace(/(^|[^:])\/\/.*$/gm, "$1");
}

function writeJson(path: string, data: unknown): void {
  mkdirSync(dirname(path), { recursive: true });
  writeFileSync(path, JSON.stringify(data, null, 2) + "\n", "utf8");
}

function parseArgs(argv: string[]): { agent: Agent; url?: string; packageUrl?: string; token?: string; remove: boolean } {
  let agent: Agent = "cursor";
  let url: string | undefined;
  let packageUrl: string | undefined;
  let token: string | undefined;
  let remove = false;
  for (let i = 0; i < argv.length; i++) {
    const a = argv[i];
    if ((a === "--agent" || a === "-a") && argv[i + 1]) agent = argv[++i] as Agent;
    else if ((a === "--url" || a === "-u") && argv[i + 1]) url = argv[++i];
    else if ((a === "--package" || a === "--package-url" || a === "-p") && argv[i + 1]) packageUrl = argv[++i];
    else if ((a === "--token" || a === "-t") && argv[i + 1]) token = argv[++i];
    else if (a === "--remove" || a === "remove" || a === "uninstall") remove = true;
    else if (a.startsWith("--agent=")) agent = a.slice("--agent=".length) as Agent;
    else if (a.startsWith("--url=")) url = a.slice("--url=".length);
    else if (a.startsWith("--token=")) token = a.slice("--token=".length);
    else if (a.startsWith("--package=")) packageUrl = a.slice("--package=".length);
    else if (a.startsWith("--package-url=")) packageUrl = a.slice("--package-url=".length);
  }
  if (!["claude", "cursor", "vscode", "opencode"].includes(agent)) {
    throw new Error(`Unknown agent: ${agent}. Use claude|cursor|vscode|opencode`);
  }
  return { agent, url, packageUrl, token, remove };
}

function serverEntry(url: string, token: string, packageUrl?: string): Record<string, unknown> {
  const env = { APPDASHBOARD_URL: url, APPDASHBOARD_TOKEN: token };
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

function opencodeServerEntry(url: string, token: string, packageUrl?: string): Record<string, unknown> {
  const command = packageUrl ? ["npx", "-y", packageUrl] : ["node", entryScript()];
  return {
    enabled: true,
    type: "local",
    command,
    environment: { APPDASHBOARD_URL: url, APPDASHBOARD_TOKEN: token },
  };
}

const SERVER_KEY = "appdashboard-mcp";

function mergeServerEntry(agent: Agent, config: Record<string, any>, url: string, token: string, packageUrl?: string): Record<string, any> {
  const baseEntry = serverEntry(url, token, packageUrl);
  const entry = agent === "opencode" ? opencodeServerEntry(url, token, packageUrl) : baseEntry;
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
  const { agent, url: urlArg, packageUrl: pkgArg, token: tokenArg, remove } = parseArgs(argv.filter((a) => a !== "setup"));
  const interactive = (!urlArg && !process.env.APPDASHBOARD_URL) || (!tokenArg && !process.env.APPDASHBOARD_TOKEN);
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
    let token = (tokenArg || process.env.APPDASHBOARD_TOKEN || "").trim();
    if (!token && rl) {
      token = (await rl.question("AppDashboard token: ")).trim();
    }
    if (!token) throw new Error("Token is required (--token or APPDASHBOARD_TOKEN)");
    const role = await validateToken(url, token);
    saveTokenCredentials(url, token, role);
    // Record package fingerprint so background auto-update can detect new versions.
    try {
      const info = await fetch(`${url}/mcp/info`, { signal: AbortSignal.timeout(10_000) });
      const meta = info.ok ? await info.json() : null;
      if (meta?.ok && meta?.sha256) writeLocalFingerprint(String(meta.sha256), String(meta.version || ""));
    } catch {
      /* non-fatal */
    }
    let packageUrl = (pkgArg || process.env.APPDASHBOARD_MCP_PACKAGE_URL || "").trim();
    if (!packageUrl) packageUrl = `${url}/mcp/download`;
    const merged = mergeServerEntry(agent, existing, url, token, packageUrl);
    writeJson(path, merged);
    console.log(`Updated ${path}`);
    console.log(`APPDASHBOARD_URL=${url}`);
    console.log(`Package: ${packageUrl}`);
    console.log("No local build needed - npx downloads the prebuilt package.");
    console.log("Token authentication configured.");
    console.log("Restart the agent to load the MCP server.");
  } finally {
    rl?.close();
  }
}

async function validateToken(url: string, token: string): Promise<Role> {
  const res = await fetch(`${url}/public-api/auth/me`, {
    headers: {
      Authorization: `Bearer ${token}`,
      "Content-Type": "application/json",
    },
  });
  let data: any = null;
  try {
    data = await res.json();
  } catch {
  }
  if (!res.ok || !data?.ok || !data?.user) {
    throw new Error(data?.error || `token_validation_failed_${res.status}`);
  }
  return normalizeRole(data.user.role);
}

export { agentConfigPath, entryScript, serverEntry };
