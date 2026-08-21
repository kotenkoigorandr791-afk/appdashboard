import { apiGet, ApiError } from "./api.js";

export type ImmichCredentials = { url: string; api_key: string };
export type GiteaCredentials = { url: string; access_token: string };
export type PlaneCredentials = { url: string; api_key: string; workspace_slug: string };

let immichCache: ImmichCredentials | null = null;
let giteaCache: GiteaCredentials | null = null;
let planeCache: PlaneCredentials | null = null;
let immichPromise: Promise<ImmichCredentials> | null = null;
let giteaPromise: Promise<GiteaCredentials> | null = null;
let planePromise: Promise<PlaneCredentials> | null = null;

export function clearIntegrationCaches(): void {
  immichCache = null;
  giteaCache = null;
  planeCache = null;
  immichPromise = null;
  giteaPromise = null;
  planePromise = null;
}

export async function getImmichCredentials(): Promise<ImmichCredentials> {
  if (immichCache) return immichCache;
  if (!immichPromise) {
    immichPromise = (async () => {
      const data = await apiGet("/public-api/integrations/immich");
      if (!data?.ok || !data.api_key || !data.url) {
        throw new ApiError(data?.error || data?.message || "immich_credentials_failed", 502, data);
      }
      immichCache = { url: String(data.url).replace(/\/$/, ""), api_key: String(data.api_key) };
      return immichCache;
    })().finally(() => {
      immichPromise = null;
    });
  }
  return immichPromise;
}

export async function getGiteaCredentials(): Promise<GiteaCredentials> {
  if (giteaCache) return giteaCache;
  if (!giteaPromise) {
    giteaPromise = (async () => {
      const data = await apiGet("/public-api/integrations/gitea");
      if (!data?.ok || !data.access_token || !data.url) {
        throw new ApiError(data?.error || data?.message || "gitea_credentials_failed", 502, data);
      }
      giteaCache = {
        url: String(data.url).replace(/\/$/, ""),
        access_token: String(data.access_token),
      };
      return giteaCache;
    })().finally(() => {
      giteaPromise = null;
    });
  }
  return giteaPromise;
}

export async function getPlaneCredentials(): Promise<PlaneCredentials> {
  if (planeCache) return planeCache;
  if (!planePromise) {
    planePromise = (async () => {
      const data = await apiGet("/public-api/integrations/plane");
      if (!data?.ok || !data.api_key || !data.url) {
        throw new ApiError(data?.error || data?.message || "plane_credentials_failed", 502, data);
      }
      planeCache = {
        url: String(data.url).replace(/\/$/, ""),
        api_key: String(data.api_key),
        workspace_slug: String(data.workspace_slug || ""),
      };
      return planeCache;
    })().finally(() => {
      planePromise = null;
    });
  }
  return planePromise;
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
