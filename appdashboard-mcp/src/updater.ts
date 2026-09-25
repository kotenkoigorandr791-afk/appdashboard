#!/usr/bin/env node
/**
 * Auto-update for appdashboard-mcp.
 *
 * On server start (background, never blocking) compares the local package
 * fingerprint with the one published at APPDASHBOARD_URL/mcp/info.
 * If they differ, downloads the new tgz into a cache dir, swaps the
 * entry script there and re-execs itself (guard: APPDASHBOARD_MCP_UPDATED=1).
 *
 * Enable/disable with APPDASHBOARD_MCP_AUTOUPDATE (default: on).
 */
import { existsSync, mkdirSync, readFileSync, readdirSync, statSync, rmSync, writeFileSync, symlinkSync } from "node:fs";
import { homedir, tmpdir } from "node:os";
import { createHash } from "node:crypto";
import { join, dirname } from "node:path";
import { fileURLToPath } from "node:url";
import { spawn } from "node:child_process";

const CHECK_INTERVAL_MS = 6 * 60 * 60 * 1000; // 6 hours
const HTTP_TIMEOUT_MS = 15_000;

function log(msg: string): void {
  console.error(`[appdashboard-mcp] updater: ${msg}`);
}

function currentPackageRoot(): string {
  // <pkg>/dist/updater.js -> <pkg>
  return dirname(dirname(fileURLToPath(import.meta.url)));
}

function currentEntry(): string {
  return join(currentPackageRoot(), "dist", "index.js");
}

function readdirRecursive(dir: string): string[] {
  const out: string[] = [];
  for (const entry of readdirSafe(dir)) {
    const full = join(dir, entry);
    try {
      if (statIsDir(full)) out.push(...readdirRecursive(full));
      else out.push(full);
    } catch {
      /* ignore */
    }
  }
  return out;
}

function readdirSafe(dir: string): string[] {
  try {
    return readdirSync(dir);
  } catch {
    return [];
  }
}

function statIsDir(p: string): boolean {
  try {
    return statSync(p).isDirectory();
  } catch {
    return false;
  }
}

function cacheDir(): string {
  return process.env.APPDASHBOARD_MCP_CACHE_DIR || join(homedir(), ".cache", "appdashboard-mcp");
}

/** Path of the installed-package fingerprint file (written by setup / update). */
function fingerprintPath(): string {
  return join(homedir(), ".config", "appdashboard-mcp", "package.json");
}

function readLocalFingerprint(): { sha256?: string; version?: string } {
  try {
    return JSON.parse(readFileSync(fingerprintPath(), "utf8"));
  } catch {
    return {};
  }
}

export function writeLocalFingerprint(sha256: string, version: string): void {
  try {
    mkdirSync(dirname(fingerprintPath()), { recursive: true });
    writeFileSync(fingerprintPath(), JSON.stringify({ sha256, version, updated_at: new Date().toISOString() }, null, 2));
  } catch {
    /* ignore */
  }
}

async function fetchJson(url: string, timeoutMs = HTTP_TIMEOUT_MS): Promise<any | null> {
  try {
    const res = await fetch(url, { signal: AbortSignal.timeout(timeoutMs) });
    if (!res.ok) return null;
    return await res.json();
  } catch {
    return null;
  }
}

export function autoupdateEnabled(): boolean {
  const raw = (process.env.APPDASHBOARD_MCP_AUTOUPDATE ?? "").trim().toLowerCase();
  return raw === "" || raw === "1" || raw === "true" || raw === "yes" || raw === "on";
}

/**
 * Downloads the published package, extracts it and re-execs the new entry
 * with the same argv/env. Resolves only if the update is not possible.
 */
async function performUpdate(host: string, remoteSha: string, cacheKey: string): Promise<void> {
  const dir = cacheDir();
  rmSync(dir, { recursive: true, force: true });
  mkdirSync(dir, { recursive: true });

  const tgzPath = join(dir, "package.tgz");
  const url = `${host}/mcp/download?v=${encodeURIComponent(cacheKey)}`;
  const res = await fetch(url, { signal: AbortSignal.timeout(60_000) });
  if (!res.ok) {
    log(`download failed: http ${res.status}`);
    return;
  }
  const buf = Buffer.from(await res.arrayBuffer());
  const sha = createHash("sha256").update(buf).digest("hex");
  if (remoteSha && sha !== remoteSha) {
    log("downloaded package sha mismatch; aborting update");
    return;
  }
  writeFileSync(tgzPath, buf);
  writeLocalFingerprint(sha, String(process.env.npm_package_version || ""));

  // Extract with tar (available on all supported platforms with node 18+ shells).
  const { execFile } = await import("node:child_process");
  const { promisify } = await import("node:util");
  const execFileP = promisify(execFile);
  try {
    await execFileP("tar", ["-xzf", tgzPath, "-C", dir]);
  } catch (err) {
    log(`tar extraction failed: ${err instanceof Error ? err.message : err}`);
    return;
  }

  const newEntry = join(dir, "package", "dist", "index.js");
  if (!existsSync(newEntry)) {
    log("extracted package has no dist/index.js; aborting update");
    return;
  }

  // ESM ignores NODE_PATH, so make production deps resolvable inside the cache copy:
  // symlink the current install's node_modules (fast, offline) or npm-install as fallback.
  const pkgDir = join(dir, "package");
  const cacheNodeModules = join(pkgDir, "node_modules");
  const localNodeModules = join(currentPackageRoot(), "node_modules");
  if (!existsSync(cacheNodeModules)) {
    if (existsSync(localNodeModules)) {
      try {
        symlinkSync(localNodeModules, cacheNodeModules, "dir");
      } catch (err) {
        log(`symlink node_modules failed: ${err instanceof Error ? err.message : err}`);
      }
    }
  }
  log(`updating to sha ${sha.slice(0, 12)} and restarting...`);
  const child = spawn(process.execPath, [newEntry, ...process.argv.slice(2)], {
    stdio: ["inherit", "inherit", "inherit"],
    env: { ...process.env, APPDASHBOARD_MCP_UPDATED: "1" },
    detached: false,
  });
  child.on("error", (err) => log(`re-exec failed: ${err.message}`));
  // Give the child a moment to start; then exit this process.
  setTimeout(() => process.exit(0), 500);
}

/**
 * Starts background auto-update loop. Never throws, never blocks the caller.
 * Skipped when running from the updated cache copy already (guard env) or when disabled.
 */
export function startAutoUpdater(): void {
  if (!autoupdateEnabled()) return;
  if (process.env.APPDASHBOARD_MCP_UPDATED === "1") {
    log("running updated copy; background checks continue");
  }
  const host = (process.env.APPDASHBOARD_URL || "").trim().replace(/\/+$/, "");
  if (!host) return;

  let running = false;
  const tick = async (): Promise<void> => {
    if (running) return;
    running = true;
    try {
      const info = await fetchJson(`${host}/mcp/info`);
      if (!info?.ok || !info?.sha256) return;
      if (info.ready === false) return;
      // Fingerprint of the installed package (sha256 of its tgz), recorded by setup/updater.
      const local = readLocalFingerprint().sha256 || "";
      if (!local || local === String(info.sha256)) return;
      log(`new version available (remote ${String(info.sha256).slice(0, 12)}, local ${local.slice(0, 12)})`);
      await performUpdate(host, String(info.sha256), String(info.cache_key || ""));
    } catch (err) {
      log(`check failed: ${err instanceof Error ? err.message : err}`);
    } finally {
      running = false;
    }
  };

  // First check shortly after start, then periodically.
  setTimeout(() => void tick(), 3_000).unref?.();
  setInterval(() => void tick(), CHECK_INTERVAL_MS).unref?.();
}

/** Public helper used by the mcp_check_update tool. */
export async function checkForUpdateNow(): Promise<{ up_to_date: boolean; local_sha?: string; remote_sha?: string; version?: string; updated?: boolean; error?: string }> {
  const host = (process.env.APPDASHBOARD_URL || "").trim().replace(/\/+$/, "");
  if (!host) return { up_to_date: false, error: "APPDASHBOARD_URL not set" };
  const info = await fetchJson(`${host}/mcp/info`);
  if (!info?.ok || !info?.sha256) return { up_to_date: false, error: "mcp_info_unavailable" };
  const local = readLocalFingerprint().sha256 || "";
  if (!local) return { up_to_date: false, error: "no_local_fingerprint_run_setup" };
  if (local === String(info.sha256)) {
    return { up_to_date: true, local_sha: local, remote_sha: String(info.sha256), version: String(info.version || "") };
  }
  if (process.env.APPDASHBOARD_MCP_UPDATED === "1") {
    return { up_to_date: false, local_sha: local, remote_sha: String(info.sha256), version: String(info.version || ""), error: "updated_copy_running_please_restart_agent" };
  }
  await performUpdate(host, String(info.sha256), String(info.cache_key || ""));
  return { up_to_date: false, updated: true, local_sha: local, remote_sha: String(info.sha256), version: String(info.version || "") };
}

export const checkUpdateToolSchema = {
  name: "mcp_check_update",
  description:
    "Check if a newer version of the AppDashboard MCP package is available and auto-update if so. " +
    "Call when the user asks to update the MCP server, or when a newly added tool is missing.",
  inputSchema: { type: "object", properties: {} },
} as const;
