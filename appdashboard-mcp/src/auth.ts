import { randomBytes } from "node:crypto";
import { AsyncLocalStorage } from "node:async_hooks";
import { existsSync, mkdirSync, readFileSync, writeFileSync, unlinkSync } from "node:fs";
import { homedir } from "node:os";
import { dirname, join } from "node:path";
import { normalizeRole, type Role } from "./roles.js";

export type Credentials = {
  host: string;
  token: string;
  device_id: string;
  role: Role;
  cached_at: number;
  expires_at?: number;
};

export type AuthContext = { token: string; deviceId: string };

export const authStorage = new AsyncLocalStorage<AuthContext>();

const CREDENTIALS_DIR = join(homedir(), ".config", "appdashboard-mcp");
const CREDENTIALS_PATH = join(CREDENTIALS_DIR, "credentials.json");
const POLL_INTERVAL_MS = 3000;
const BROWSER_AUTH_TIMEOUT_MS = 5 * 60 * 1000;

let memoryCreds: Credentials | null = null;
let browserPollPromise: Promise<Credentials | null> | null = null;

export function credentialsPath(): string {
  return CREDENTIALS_PATH;
}

export function getHost(): string {
  const fromEnv = (process.env.APPDASHBOARD_URL || "").trim().replace(/\/+$/, "");
  if (fromEnv) return fromEnv;
  const creds = loadCredentials();
  return (creds?.host || "").replace(/\/+$/, "");
}

export function generateDeviceId(): string {
  return randomBytes(32).toString("hex");
}

export function loadCredentials(): Credentials | null {
  if (memoryCreds) return memoryCreds;
  try {
    if (!existsSync(CREDENTIALS_PATH)) return null;
    const raw = JSON.parse(readFileSync(CREDENTIALS_PATH, "utf8")) as Credentials;
    if (!raw || typeof raw !== "object") return null;
    if (!raw.device_id) raw.device_id = generateDeviceId();
    raw.role = normalizeRole(raw.role);
    memoryCreds = raw;
    return memoryCreds;
  } catch {
    return null;
  }
}

export function saveCredentials(creds: Credentials): void {
  mkdirSync(CREDENTIALS_DIR, { recursive: true });
  const next: Credentials = {
    host: creds.host.replace(/\/+$/, ""),
    token: creds.token,
    device_id: creds.device_id || generateDeviceId(),
    role: normalizeRole(creds.role),
    cached_at: creds.cached_at || Math.floor(Date.now() / 1000),
  };
  if (creds.expires_at) next.expires_at = creds.expires_at;
  writeFileSync(CREDENTIALS_PATH, JSON.stringify(next, null, 2), { mode: 0o600 });
  memoryCreds = next;
}

export function clearToken(): void {
  const creds = loadCredentials();
  if (!creds) {
    memoryCreds = null;
    return;
  }
  const next = { ...creds, token: "", cached_at: Math.floor(Date.now() / 1000) };
  try {
    if (!next.token && !existsSync(CREDENTIALS_PATH)) {
      memoryCreds = next;
      return;
    }
    saveCredentials(next);
  } catch {
    memoryCreds = next;
  }
}

export function clearCredentialsFile(): void {
  memoryCreds = null;
  try {
    if (existsSync(CREDENTIALS_PATH)) unlinkSync(CREDENTIALS_PATH);
  } catch {
    /* ignore */
  }
}

export function ensureDeviceId(): string {
  const creds = loadCredentials();
  if (creds?.device_id) return creds.device_id;
  const deviceId = generateDeviceId();
  const host = getHost();
  saveCredentials({
    host,
    token: creds?.token || "",
    device_id: deviceId,
    role: normalizeRole(creds?.role),
    cached_at: Math.floor(Date.now() / 1000),
    expires_at: creds?.expires_at,
  });
  return deviceId;
}

export function getEnvToken(): string {
  return (process.env.APPDASHBOARD_TOKEN || "").trim();
}

export function getToken(): string {
  return authStorage.getStore()?.token || getEnvToken() || loadCredentials()?.token || "";
}

export function getCachedRole(): Role {
  return normalizeRole(loadCredentials()?.role);
}

export function setCachedRole(role: Role): void {
  const creds = loadCredentials();
  if (!creds) return;
  saveCredentials({ ...creds, role: normalizeRole(role) });
}

export function saveTokenCredentials(host: string, token: string, role?: Role, expiresAt?: number): Credentials {
  const creds: Credentials = {
    host: host.replace(/\/+$/, ""),
    token: token.trim(),
    device_id: loadCredentials()?.device_id || generateDeviceId(),
    role: normalizeRole(role),
    cached_at: Math.floor(Date.now() / 1000),
    expires_at: expiresAt,
  };
  saveCredentials(creds);
  return creds;
}

async function fetchJson(url: string, init: RequestInit): Promise<{ status: number; data: any }> {
  const res = await fetch(url, init);
  let data: any = null;
  const text = await res.text();
  try {
    data = text ? JSON.parse(text) : null;
  } catch {
    const start = text.search(/[{[]/);
    if (start >= 0) {
      try {
        data = JSON.parse(text.slice(start));
      } catch {
        data = { raw: text };
      }
    } else {
      data = { raw: text };
    }
  }
  return { status: res.status, data };
}

export async function refreshToken(): Promise<Credentials> {
  const creds = loadCredentials();
  const base = getHost();
  if (!creds?.token || !base) throw new Error("not_authenticated");
  const { status, data } = await fetchJson(`${base}/public-api/auth/refresh`, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      Authorization: `Bearer ${creds.token}`,
      "X-Device-Id": creds.device_id,
    },
    body: JSON.stringify({ device_name: "MCP Client", platform: "mcp" }),
  });
  if (status >= 400 || !data?.ok || !data?.token) {
    clearToken();
    throw new Error(data?.error || `refresh_failed_${status}`);
  }
  const next: Credentials = {
    ...creds,
    host: base,
    token: String(data.token),
    cached_at: Math.floor(Date.now() / 1000),
    expires_at: data.expires_at ? Number(data.expires_at) : undefined,
  };
  saveCredentials(next);
  return next;
}

export async function fetchMe(): Promise<{ email: string; name?: string; role: Role } | null> {
  const context = authStorage.getStore();
  const base = getHost();
  const token = context?.token || getToken();
  const deviceId = context ? context.deviceId : ensureDeviceId();
  if (!token || !base) return null;
  const headers: Record<string, string> = {
    Authorization: `Bearer ${token}`,
    "Content-Type": "application/json",
  };
  if (deviceId) headers["X-Device-Id"] = deviceId;
  const { status, data } = await fetchJson(`${base}/public-api/auth/me`, {
    method: "GET",
    headers,
  });
  if (status === 401 && !context) {
    try {
      await refreshToken();
      return fetchMe();
    } catch {
      return null;
    }
  }
  if (status >= 400 || !data?.ok || !data?.user) return null;
  const role = normalizeRole(data.user.role);
  setCachedRole(role);
  return { email: String(data.user.email || ""), name: data.user.name, role };
}

export async function startBrowserAuth(host?: string): Promise<{ auth_url: string; request_id: string; poll_token: string; expires_at?: number }> {
  const base = (host || getHost()).replace(/\/+$/, "");
  if (!base) throw new Error("APPDASHBOARD_URL is not set");
  const deviceId = ensureDeviceId();
  const { status, data } = await fetchJson(`${base}/public-api/auth/client_auth/request`, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      "X-Device-Id": deviceId,
    },
    body: JSON.stringify({ device_name: "MCP Client", platform: "mcp" }),
  });
  if (status >= 400 || !data?.ok) {
    throw new Error(data?.error || `client_auth_request_failed_${status}`);
  }
  return {
    auth_url: String(data.auth_url || data.browser_url || ""),
    request_id: String(data.request_id || ""),
    poll_token: String(data.poll_token || ""),
    expires_at: data.expires_at ? Number(data.expires_at) : undefined,
  };
}

export async function pollBrowserAuth(
  requestId: string,
  pollToken: string,
  host?: string,
  timeoutMs = BROWSER_AUTH_TIMEOUT_MS,
): Promise<Credentials | null> {
  const base = (host || getHost()).replace(/\/+$/, "");
  if (!base) throw new Error("APPDASHBOARD_URL is not set");
  const deviceId = ensureDeviceId();
  const deadline = Date.now() + timeoutMs;
  while (Date.now() < deadline) {
    const { status, data } = await fetchJson(`${base}/public-api/auth/client_auth/poll`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-Device-Id": deviceId,
      },
      body: JSON.stringify({ request_id: requestId, poll_token: pollToken }),
    });
    if (status === 404 || status === 409) {
      throw new Error(data?.error || `poll_failed_${status}`);
    }
    if (data?.status === "expired") {
      console.error("[appdashboard-mcp] Browser auth request expired.");
      return null;
    }
    if (data?.ok && (data.approved || data.status === "approved") && data.token) {
      const creds: Credentials = {
        host: base,
        token: String(data.token),
        device_id: deviceId,
        role: normalizeRole(data?.user?.role),
        cached_at: Math.floor(Date.now() / 1000),
        expires_at: data.expires_at ? Number(data.expires_at) : undefined,
      };
      saveCredentials(creds);
      return creds;
    }
    await new Promise((r) => setTimeout(r, POLL_INTERVAL_MS));
  }
  console.error("[appdashboard-mcp] Browser auth timed out. Run setup with --token or set APPDASHBOARD_TOKEN.");
  return null;
}

export function startBrowserAuthInBackground(): void {
  if (browserPollPromise) return;
  if (!getHost()) {
    console.error("[appdashboard-mcp] Set APPDASHBOARD_URL to authenticate.");
    return;
  }
  browserPollPromise = (async () => {
    try {
      const req = await startBrowserAuth();
      console.error(`[appdashboard-mcp] Open this URL to authenticate:\n${req.auth_url}`);
      return await pollBrowserAuth(req.request_id, req.poll_token);
    } catch (err) {
      console.error(`[appdashboard-mcp] Browser auth failed: ${err instanceof Error ? err.message : String(err)}`);
      return null;
    } finally {
      browserPollPromise = null;
    }
  })();
}

export async function ensureAuth(options?: { interactive?: boolean; timeoutMs?: number }): Promise<Credentials | null> {
  const interactive = options?.interactive !== false;
  const host = getHost();
  if (!host) {
    if (interactive) console.error("[appdashboard-mcp] APPDASHBOARD_URL is not set.");
    return null;
  }
  ensureDeviceId();
  const existing = loadCredentials();
  if (getToken()) {
    const me = await fetchMe();
    if (me) {
      const creds = loadCredentials();
      return creds?.token ? creds : saveTokenCredentials(host, getToken(), me.role);
    }
  }
  if (interactive) console.error("[appdashboard-mcp] Token is required. Run setup with --token or set APPDASHBOARD_TOKEN.");
  return loadCredentials()?.token ? loadCredentials() : null;
}

export function credentialsDir(): string {
  return dirname(CREDENTIALS_PATH);
}
