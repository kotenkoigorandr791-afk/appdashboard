import { apiGet, ApiError } from "./api.js";
import { getToken } from "./auth.js";

export type ImmichCredentials = { url: string; api_key: string };
export type GiteaCredentials = { url: string; access_token: string };
export type KanboardCredentials = { url: string; username: string; api_token: string };
export type SentryCredentials = { url: string; token: string };

const immichCache = new Map<string, ImmichCredentials>();
const giteaCache = new Map<string, GiteaCredentials>();
const kanboardCache = new Map<string, KanboardCredentials>();
const sentryCache = new Map<string, SentryCredentials>();
const immichPromises = new Map<string, Promise<ImmichCredentials>>();
const giteaPromises = new Map<string, Promise<GiteaCredentials>>();
const kanboardPromises = new Map<string, Promise<KanboardCredentials>>();
const sentryPromises = new Map<string, Promise<SentryCredentials>>();

function cacheKey(): string {
  return getToken() || "default";
}

function clearImmichCache(key = cacheKey()): void {
  immichCache.delete(key);
  immichPromises.delete(key);
}

function clearGiteaCache(key = cacheKey()): void {
  giteaCache.delete(key);
  giteaPromises.delete(key);
}

function clearKanboardCache(key = cacheKey()): void {
  kanboardCache.delete(key);
  kanboardPromises.delete(key);
}

function clearSentryCache(key = cacheKey()): void {
  sentryCache.delete(key);
  sentryPromises.delete(key);
}

export function clearIntegrationCaches(): void {
  immichCache.clear();
  giteaCache.clear();
  kanboardCache.clear();
  sentryCache.clear();
  immichPromises.clear();
  giteaPromises.clear();
  kanboardPromises.clear();
  sentryPromises.clear();
}

function envImmichOverride(): ImmichCredentials | null {
  const url = (process.env.IMMICH_URL || "").trim();
  const apiKey = (process.env.IMMICH_API_KEY || "").trim();
  return url && apiKey ? { url: url.replace(/\/$/, ""), api_key: apiKey } : null;
}

export async function getImmichCredentials(): Promise<ImmichCredentials> {
  const override = envImmichOverride();
  if (override) return override;
  const key = cacheKey();
  const cached = immichCache.get(key);
  if (cached) return cached;
  let promise = immichPromises.get(key);
  if (!promise) {
    promise = (async () => {
      const data = await apiGet("/public-api/integrations/immich");
      if (!data?.ok || !data.api_key || !data.url) {
        throw new ApiError(data?.error || data?.message || "immich_credentials_failed", 502, data);
      }
      const creds = { url: String(data.url).replace(/\/$/, ""), api_key: String(data.api_key) };
      immichCache.set(key, creds);
      return creds;
    })().finally(() => {
      immichPromises.delete(key);
    });
    immichPromises.set(key, promise);
  }
  return promise;
}

async function refreshImmichCredentials(): Promise<ImmichCredentials> {
  const key = cacheKey();
  clearImmichCache(key);
  const data = await apiGet("/public-api/integrations/immich", { refresh: 1 });
  if (!data?.ok || !data.api_key || !data.url) {
    throw new ApiError(data?.error || data?.message || "immich_credentials_failed", 502, data);
  }
  const creds = { url: String(data.url).replace(/\/$/, ""), api_key: String(data.api_key) };
  immichCache.set(key, creds);
  return creds;
}

function envGiteaOverride(): GiteaCredentials | null {
  const url = (process.env.GITEA_URL || "").trim();
  const token = (process.env.GITEA_TOKEN || "").trim();
  return url && token ? { url: url.replace(/\/$/, ""), access_token: token } : null;
}

export async function getGiteaCredentials(): Promise<GiteaCredentials> {
  const override = envGiteaOverride();
  if (override) return override;
  const key = cacheKey();
  const cached = giteaCache.get(key);
  if (cached) return cached;
  let promise = giteaPromises.get(key);
  if (!promise) {
    promise = (async () => {
      const data = await apiGet("/public-api/integrations/gitea");
      if (!data?.ok || !data.access_token || !data.url) {
        throw new ApiError(data?.error || data?.message || "gitea_credentials_failed", 502, data);
      }
      const creds = {
        url: String(data.url).replace(/\/$/, ""),
        access_token: String(data.access_token),
      };
      giteaCache.set(key, creds);
      return creds;
    })().finally(() => {
      giteaPromises.delete(key);
    });
    giteaPromises.set(key, promise);
  }
  return promise;
}

async function refreshGiteaCredentials(): Promise<GiteaCredentials> {
  const key = cacheKey();
  clearGiteaCache(key);
  const data = await apiGet("/public-api/integrations/gitea", { refresh: 1 });
  if (!data?.ok || !data.access_token || !data.url) {
    throw new ApiError(data?.error || data?.message || "gitea_credentials_failed", 502, data);
  }
  const creds = {
    url: String(data.url).replace(/\/$/, ""),
    access_token: String(data.access_token),
  };
  giteaCache.set(key, creds);
  return creds;
}

function envKanboardOverride(): KanboardCredentials | null {
  const url = (process.env.KANBOARD_URL || "").trim();
  const username = (process.env.KANBOARD_USERNAME || "").trim();
  const apiToken = (process.env.KANBOARD_API_TOKEN || "").trim();
  return url && username && apiToken ? { url: url.replace(/\/$/, ""), username, api_token: apiToken } : null;
}

function envSentryOverride(): SentryCredentials | null {
  const url = (process.env.SENTRY_URL || "").trim();
  const token = (process.env.SENTRY_TOKEN || "").trim();
  return url && token ? { url: url.replace(/\/$/, ""), token } : null;
}

export async function getKanboardCredentials(): Promise<KanboardCredentials> {
  const override = envKanboardOverride();
  if (override) return override;
  const key = cacheKey();
  const cached = kanboardCache.get(key);
  if (cached) return cached;
  let promise = kanboardPromises.get(key);
  if (!promise) {
    promise = (async () => {
      const data = await apiGet("/public-api/integrations/kanboard");
      if (!data?.ok || !data.api_token || !data.url || !data.username) {
        throw new ApiError(data?.error || data?.message || "kanboard_credentials_failed", 502, data);
      }
      const creds = {
        url: String(data.url).replace(/\/$/, ""),
        username: String(data.username),
        api_token: String(data.api_token),
      };
      kanboardCache.set(key, creds);
      return creds;
    })().finally(() => {
      kanboardPromises.delete(key);
    });
    kanboardPromises.set(key, promise);
  }
  return promise;
}

async function refreshKanboardCredentials(): Promise<KanboardCredentials> {
  const key = cacheKey();
  clearKanboardCache(key);
  const data = await apiGet("/public-api/integrations/kanboard", { refresh: 1 });
  if (!data?.ok || !data.api_token || !data.url || !data.username) {
    throw new ApiError(data?.error || data?.message || "kanboard_credentials_failed", 502, data);
  }
  const creds = {
    url: String(data.url).replace(/\/$/, ""),
    username: String(data.username),
    api_token: String(data.api_token),
  };
  kanboardCache.set(key, creds);
  return creds;
}

export async function getSentryCredentials(): Promise<SentryCredentials> {
  const override = envSentryOverride();
  if (override) return override;
  const key = cacheKey();
  const cached = sentryCache.get(key);
  if (cached) return cached;
  let promise = sentryPromises.get(key);
  if (!promise) {
    promise = (async () => {
      const data = await apiGet("/public-api/integrations/sentry");
      if (!data?.ok || !data.token || !data.url) {
        throw new ApiError(data?.error || data?.message || "sentry_credentials_failed", 502, data);
      }
      const creds = { url: String(data.url).replace(/\/$/, ""), token: String(data.token) };
      sentryCache.set(key, creds);
      return creds;
    })().finally(() => {
      sentryPromises.delete(key);
    });
    sentryPromises.set(key, promise);
  }
  return promise;
}

async function refreshSentryCredentials(): Promise<SentryCredentials> {
  const key = cacheKey();
  clearSentryCache(key);
  const data = await apiGet("/public-api/integrations/sentry", { refresh: 1 });
  if (!data?.ok || !data.token || !data.url) {
    throw new ApiError(data?.error || data?.message || "sentry_credentials_failed", 502, data);
  }
  const creds = { url: String(data.url).replace(/\/$/, ""), token: String(data.token) };
  sentryCache.set(key, creds);
  return creds;
}

function shouldRefreshExternal(status: number): boolean {
  return status === 401 || status === 403;
}

async function parseJson(res: Response): Promise<any> {
  const text = await res.text();
  if (!text) return null;
  try {
    return JSON.parse(text);
  } catch {
    const start = text.search(/[{[]/);
    if (start >= 0) {
      try {
        return JSON.parse(text.slice(start));
      } catch {
      }
    }
    return { raw: text };
  }
}

export async function immichFetch(
  method: string,
  path: string,
  opts: { query?: Record<string, unknown>; body?: unknown } = {},
  retried = false,
): Promise<any> {
  const creds = await getImmichCredentials();
  const url = new URL(path.startsWith("http") ? path : `${creds.url}${path.startsWith("/") ? "" : "/"}${path}`);
  if (opts.query) {
    for (const [k, v] of Object.entries(opts.query)) {
      if (v === undefined || v === null || v === "") continue;
      url.searchParams.set(k, String(v));
    }
  }
  const headers: Record<string, string> = {
    Accept: "application/json",
    "x-api-key": creds.api_key,
  };
  let body: string | undefined;
  if (opts.body !== undefined) {
    headers["Content-Type"] = "application/json";
    body = JSON.stringify(opts.body);
  }
  const res = await fetch(url.toString(), { method, headers, body });
  const data = await parseJson(res);
  if (!res.ok && !retried && shouldRefreshExternal(res.status)) {
    await refreshImmichCredentials();
    return immichFetch(method, path, opts, true);
  }
  if (!res.ok) {
    const msg =
      (typeof data?.message === "string" && data.message) ||
      (typeof data?.error === "string" && data.error) ||
      `immich_http_${res.status}`;
    throw new ApiError(msg, res.status, data);
  }
  return data;
}

export async function giteaFetch(
  method: string,
  path: string,
  opts: { query?: Record<string, unknown>; body?: unknown } = {},
  retried = false,
): Promise<any> {
  const creds = await getGiteaCredentials();
  const base = `${creds.url}/api/v1`;
  const url = new URL(path.startsWith("http") ? path : `${base}${path.startsWith("/") ? "" : "/"}${path}`);
  if (opts.query) {
    for (const [k, v] of Object.entries(opts.query)) {
      if (v === undefined || v === null || v === "") continue;
      if (Array.isArray(v)) {
        for (const item of v) url.searchParams.append(k, String(item));
      } else {
        url.searchParams.set(k, String(v));
      }
    }
  }
  const headers: Record<string, string> = {
    Accept: "application/json",
    Authorization: `token ${creds.access_token}`,
  };
  let body: string | undefined;
  if (opts.body !== undefined) {
    headers["Content-Type"] = "application/json";
    body = JSON.stringify(opts.body);
  }
  const res = await fetch(url.toString(), { method, headers, body });
  const data = await parseJson(res);
  if (!res.ok && !retried && shouldRefreshExternal(res.status)) {
    await refreshGiteaCredentials();
    return giteaFetch(method, path, opts, true);
  }
  if (!res.ok) {
    const msg =
      (typeof data?.message === "string" && data.message) ||
      (typeof data?.error === "string" && data.error) ||
      (data?.raw ? String(data.raw) : `gitea_http_${res.status}`);
    throw new ApiError(msg, res.status, data);
  }
  return data;
}

export async function kanboardRpc(
  method: string,
  params: unknown[] | Record<string, unknown> = [],
  retried = false,
): Promise<any> {
  const creds = await getKanboardCredentials();
  const endpoint = `${creds.url}/jsonrpc.php`;
  const auth = Buffer.from(`${creds.username}:${creds.api_token}`).toString("base64");
  const res = await fetch(endpoint, {
    method: "POST",
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
      Authorization: `Basic ${auth}`,
    },
    body: JSON.stringify({ jsonrpc: "2.0", method, id: Date.now(), params }),
  });
  const data = await parseJson(res);
  if ((!res.ok || data?.error) && !retried && (shouldRefreshExternal(res.status) || data?.error?.code === 401)) {
    await refreshKanboardCredentials();
    return kanboardRpc(method, params, true);
  }
  if (!res.ok) {
    throw new ApiError(data?.error?.message || `kanboard_http_${res.status}`, res.status, data);
  }
  if (data?.error) {
    throw new ApiError(String(data.error.message || data.error), 502, data);
  }
  return data?.result;
}

export async function sentryFetch(
  method: string,
  path: string,
  opts: { query?: Record<string, unknown>; body?: unknown } = {},
  retried = false,
): Promise<any> {
  const creds = await getSentryCredentials();
  const url = new URL(path.startsWith("http") ? path : `${creds.url}${path.startsWith("/") ? "" : "/"}${path}`);
  if (opts.query) {
    for (const [k, v] of Object.entries(opts.query)) {
      if (v === undefined || v === null || v === "") continue;
      url.searchParams.set(k, String(v));
    }
  }
  const headers: Record<string, string> = { Accept: "application/json", Authorization: `Bearer ${creds.token}` };
  let body: string | undefined;
  if (opts.body !== undefined) {
    headers["Content-Type"] = "application/json";
    body = JSON.stringify(opts.body);
  }
  const res = await fetch(url.toString(), { method, headers, body });
  const data = await parseJson(res);
  if (!res.ok && !retried && shouldRefreshExternal(res.status)) {
    await refreshSentryCredentials();
    return sentryFetch(method, path, opts, true);
  }
  if (!res.ok) {
    const msg =
      (typeof data?.detail === "string" && data.detail) ||
      (typeof data?.message === "string" && data.message) ||
      (typeof data?.error === "string" && data.error) ||
      `sentry_http_${res.status}`;
    throw new ApiError(msg, res.status, data);
  }
  return data;
}
