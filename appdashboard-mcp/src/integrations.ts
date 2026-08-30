import { apiGet, ApiError } from "./api.js";
import { getToken } from "./auth.js";

export type ImmichCredentials = { url: string; api_key: string };
export type GiteaCredentials = { url: string; access_token: string };
export type PlaneCredentials = { url: string; api_key: string; workspace_slug: string };

const immichCache = new Map<string, ImmichCredentials>();
const giteaCache = new Map<string, GiteaCredentials>();
const planeCache = new Map<string, PlaneCredentials>();
const immichPromises = new Map<string, Promise<ImmichCredentials>>();
const giteaPromises = new Map<string, Promise<GiteaCredentials>>();
const planePromises = new Map<string, Promise<PlaneCredentials>>();

function cacheKey(): string {
  return getToken() || "default";
}

export function clearIntegrationCaches(): void {
  immichCache.clear();
  giteaCache.clear();
  planeCache.clear();
  immichPromises.clear();
  giteaPromises.clear();
  planePromises.clear();
}

export async function getImmichCredentials(): Promise<ImmichCredentials> {
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

export async function getGiteaCredentials(): Promise<GiteaCredentials> {
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

export async function getPlaneCredentials(): Promise<PlaneCredentials> {
  const key = cacheKey();
  const cached = planeCache.get(key);
  if (cached) return cached;
  let promise = planePromises.get(key);
  if (!promise) {
    promise = (async () => {
      const data = await apiGet("/public-api/integrations/plane");
      if (!data?.ok || !data.api_key || !data.url) {
        throw new ApiError(data?.error || data?.message || "plane_credentials_failed", 502, data);
      }
      const creds = {
        url: String(data.url).replace(/\/$/, ""),
        api_key: String(data.api_key),
        workspace_slug: String(data.workspace_slug || ""),
      };
      planeCache.set(key, creds);
      return creds;
    })().finally(() => {
      planePromises.delete(key);
    });
    planePromises.set(key, promise);
  }
  return promise;
}

async function parseJson(res: Response): Promise<any> {
  const text = await res.text();
  if (!text) return null;
  try {
    return JSON.parse(text);
  } catch {
    return { raw: text };
  }
}

export async function immichFetch(
  method: string,
  path: string,
  opts: { query?: Record<string, unknown>; body?: unknown } = {},
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
  if (!res.ok) {
    const msg =
      (typeof data?.message === "string" && data.message) ||
      (typeof data?.error === "string" && data.error) ||
      (data?.raw ? String(data.raw) : `gitea_http_${res.status}`);
    throw new ApiError(msg, res.status, data);
  }
  return data;
}

export async function planeFetch(
  method: string,
  path: string,
  opts: { query?: Record<string, unknown>; body?: unknown } = {},
): Promise<any> {
  const creds = await getPlaneCredentials();
  const url = new URL(path.startsWith("http") ? path : `${creds.url}${path.startsWith("/") ? "" : "/"}${path}`);
  if (opts.query) {
    for (const [k, v] of Object.entries(opts.query)) {
      if (v === undefined || v === null || v === "") continue;
      if (typeof v === "boolean") url.searchParams.set(k, v ? "true" : "false");
      else url.searchParams.set(k, String(v));
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
  if (!res.ok) {
    const msg =
      (typeof data?.detail === "string" && data.detail) ||
      (typeof data?.message === "string" && data.message) ||
      (typeof data?.error === "string" && data.error) ||
      (data?.raw ? String(data.raw) : `plane_http_${res.status}`);
    throw new ApiError(msg, res.status, data);
  }
  return data;
}
