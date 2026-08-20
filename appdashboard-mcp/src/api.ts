import { clearToken, getHost, getToken, ensureDeviceId, refreshToken } from "./auth.js";

export class ApiError extends Error {
  status: number;
  data: unknown;
  constructor(message: string, status: number, data?: unknown) {
    super(message);
    this.name = "ApiError";
    this.status = status;
    this.data = data;
  }
}

function buildUrl(path: string, query?: Record<string, string | number | boolean | undefined | null>): string {
  const base = getHost();
  if (!base) throw new Error("APPDASHBOARD_URL is not set");
  const url = new URL(path.startsWith("http") ? path : `${base}${path.startsWith("/") ? "" : "/"}${path}`);
  if (query) {
    for (const [k, v] of Object.entries(query)) {
      if (v === undefined || v === null || v === "") continue;
      url.searchParams.set(k, String(v));
    }
  }
  return url.toString();
}

async function parseBody(res: Response): Promise<any> {
  const text = await res.text();
  if (!text) return null;
  try {
    return JSON.parse(text);
  } catch {
    return { raw: text };
  }
}

async function apiCall(
  method: string,
  path: string,
  body?: unknown,
  query?: Record<string, string | number | boolean | undefined | null>,
  retried = false,
): Promise<any> {
  const token = getToken();
  const deviceId = ensureDeviceId();
  const headers: Record<string, string> = {
    "Content-Type": "application/json",
    "X-Device-Id": deviceId,
  };
  if (token) headers.Authorization = `Bearer ${token}`;
  const res = await fetch(buildUrl(path, query), {
    method,
    headers,
    body: body === undefined ? undefined : JSON.stringify(body),
  });
  const data = await parseBody(res);
  if (res.status === 401 && !retried && token) {
    try {
      await refreshToken();
      return apiCall(method, path, body, query, true);
    } catch {
      clearToken();
      throw new ApiError(data?.error || "unauthorized", 401, data);
    }
  }
  if (res.status === 401) {
    clearToken();
    throw new ApiError(data?.error || "unauthorized", 401, data);
  }
  if (!res.ok) {
    throw new ApiError(data?.error || `http_${res.status}`, res.status, data);
  }
  return data;
}

export function apiGet(path: string, query?: Record<string, string | number | boolean | undefined | null>) {
  return apiCall("GET", path, undefined, query);
}

export function apiPost(path: string, body?: unknown, query?: Record<string, string | number | boolean | undefined | null>) {
  return apiCall("POST", path, body ?? {}, query);
}

export function apiPut(path: string, body?: unknown, query?: Record<string, string | number | boolean | undefined | null>) {
  return apiCall("PUT", path, body ?? {}, query);
}

export function apiDelete(path: string, body?: unknown, query?: Record<string, string | number | boolean | undefined | null>) {
  return apiCall("DELETE", path, body, query);
}

type FormValue = string | number | boolean | undefined | null | string[];

function appendFormFields(params: URLSearchParams, fields: Record<string, FormValue>) {
  for (const [k, v] of Object.entries(fields)) {
    if (v === undefined || v === null) continue;
    if (Array.isArray(v)) {
      for (const item of v) params.append(k, String(item));
      continue;
    }
    params.set(k, String(v));
  }
}

async function apiFormCall(
  path: string,
  fields: Record<string, FormValue>,
  query?: Record<string, string | number | boolean | undefined | null>,
  retried = false,
): Promise<any> {
  const token = getToken();
  const deviceId = ensureDeviceId();
  const headers: Record<string, string> = {
    "Content-Type": "application/x-www-form-urlencoded",
    "X-Device-Id": deviceId,
  };
  if (token) headers.Authorization = `Bearer ${token}`;
  const body = new URLSearchParams();
  appendFormFields(body, fields);
  const res = await fetch(buildUrl(path, query), { method: "POST", headers, body });
  const data = await parseBody(res);
  if (res.status === 401 && !retried && token) {
    try {
      await refreshToken();
      return apiFormCall(path, fields, query, true);
    } catch {
      clearToken();
      throw new ApiError(data?.error || "unauthorized", 401, data);
    }
  }
  if (res.status === 401) {
    clearToken();
    throw new ApiError(data?.error || "unauthorized", 401, data);
  }
  if (!res.ok) {
    throw new ApiError(data?.error || `http_${res.status}`, res.status, data);
  }
  return data;
}

export function apiFormPost(
  path: string,
  fields: Record<string, FormValue>,
  query?: Record<string, string | number | boolean | undefined | null>,
) {
  return apiFormCall(path, fields, query);
}

export type UploadFile = { field: string; filePath: string };

async function apiUploadCall(
  path: string,
  fields: Record<string, FormValue>,
  files: UploadFile[],
  query?: Record<string, string | number | boolean | undefined | null>,
  retried = false,
): Promise<any> {
  const { readFileSync } = await import("node:fs");
  const { basename } = await import("node:path");
  const token = getToken();
  const deviceId = ensureDeviceId();
  const headers: Record<string, string> = { "X-Device-Id": deviceId };
  if (token) headers.Authorization = `Bearer ${token}`;
  const form = new FormData();
  for (const [k, v] of Object.entries(fields)) {
    if (v === undefined || v === null) continue;
    if (Array.isArray(v)) {
      for (const item of v) form.append(k, String(item));
      continue;
    }
    form.append(k, String(v));
  }
  for (const f of files) {
    const content = readFileSync(f.filePath);
    form.append(f.field, new Blob([content]), basename(f.filePath));
  }
  const res = await fetch(buildUrl(path, query), { method: "POST", headers, body: form });
  const data = await parseBody(res);
  if (res.status === 401 && !retried && token) {
    try {
      await refreshToken();
      return apiUploadCall(path, fields, files, query, true);
    } catch {
      clearToken();
      throw new ApiError(data?.error || "unauthorized", 401, data);
    }
  }
  if (res.status === 401) {
    clearToken();
    throw new ApiError(data?.error || "unauthorized", 401, data);
  }
  if (!res.ok) {
    throw new ApiError(data?.error || `http_${res.status}`, res.status, data);
  }
  return data;
}

export function apiUpload(
  path: string,
  fields: Record<string, FormValue>,
  files: UploadFile[],
  query?: Record<string, string | number | boolean | undefined | null>,
) {
  return apiUploadCall(path, fields, files, query);
}

export function shellAction(action: string, params: Record<string, unknown> = {}) {
  return apiPost("/public-api/shell", { action, ...params });
}

export function textResult(data: unknown) {
  return {
    content: [{ type: "text" as const, text: typeof data === "string" ? data : JSON.stringify(data, null, 2) }],
  };
}

export function errorResult(err: unknown) {
  if (err instanceof ApiError) {
    return {
      isError: true as const,
      content: [{ type: "text" as const, text: JSON.stringify(err.data ?? { error: err.message, status: err.status }, null, 2) }],
    };
  }
  const message = err instanceof Error ? err.message : String(err);
  return {
    isError: true as const,
    content: [{ type: "text" as const, text: JSON.stringify({ error: message }, null, 2) }],
  };
}
