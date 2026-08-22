import { authStorage, clearToken, getHost, getToken, ensureDeviceId, refreshToken } from "./auth.js";

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

function getAuthHeaders(contentType?: string): { headers: Record<string, string>; token: string; canRefresh: boolean } {
  const context = authStorage.getStore();
  const token = context?.token || getToken();
  const headers: Record<string, string> = {};
  const deviceId = context ? context.deviceId : ensureDeviceId();
  if (deviceId) headers["X-Device-Id"] = deviceId;
  if (contentType) headers["Content-Type"] = contentType;
  if (token) headers.Authorization = `Bearer ${token}`;
  return { headers, token, canRefresh: !context?.token };
}

async function apiCall(
  method: string,
  path: string,
  body?: unknown,
  query?: Record<string, string | number | boolean | undefined | null>,
  retried = false,
): Promise<any> {
  const { headers, token, canRefresh } = getAuthHeaders("application/json");
  const res = await fetch(buildUrl(path, query), {
    method,
    headers,
    body: body === undefined ? undefined : JSON.stringify(body),
  });
  const data = await parseBody(res);
  if (res.status === 401 && !retried && token && canRefresh) {
    try {
      await refreshToken();
      return apiCall(method, path, body, query, true);
    } catch {
      clearToken();
      throw new ApiError(data?.error || "unauthorized", 401, data);
    }
  }
  if (res.status === 401) {
    if (!canRefresh) throw new ApiError(data?.error || "unauthorized", 401, data);
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

export function apiJsonPost(
  path: string,
  body?: unknown,
  query?: Record<string, string | number | boolean | undefined | null>,
) {
  return apiPost(path, body ?? {}, query);
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
  const { headers, token, canRefresh } = getAuthHeaders("application/x-www-form-urlencoded");
  const body = new URLSearchParams();
  appendFormFields(body, fields);
  const res = await fetch(buildUrl(path, query), { method: "POST", headers, body });
  const data = await parseBody(res);
  if (res.status === 401 && !retried && token && canRefresh) {
    try {
      await refreshToken();
      return apiFormCall(path, fields, query, true);
    } catch {
      clearToken();
      throw new ApiError(data?.error || "unauthorized", 401, data);
    }
  }
  if (res.status === 401) {
    if (!canRefresh) throw new ApiError(data?.error || "unauthorized", 401, data);
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
  const { headers, token, canRefresh } = getAuthHeaders();
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
  if (res.status === 401 && !retried && token && canRefresh) {
    try {
      await refreshToken();
      return apiUploadCall(path, fields, files, query, true);
    } catch {
      clearToken();
      throw new ApiError(data?.error || "unauthorized", 401, data);
    }
  }
  if (res.status === 401) {
    if (!canRefresh) throw new ApiError(data?.error || "unauthorized", 401, data);
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

/** JSON POST to a direct app PHP endpoint (Bearer auth; not Public API router-specific). */
export function apiJsonPostDirect(
  path: string,
  body?: unknown,
  query?: Record<string, string | number | boolean | undefined | null>,
) {
  return apiCall("POST", path, body ?? {}, query);
}

export type SseEvent = { event: string; data: unknown };

function parseSseChunk(buffer: string): { events: SseEvent[]; rest: string } {
  const events: SseEvent[] = [];
  const parts = buffer.split("\n\n");
  const rest = parts.pop() ?? "";
  for (const block of parts) {
    if (!block.trim()) continue;
    let event = "message";
    const dataLines: string[] = [];
    for (const line of block.split("\n")) {
      if (line.startsWith("event:")) event = line.slice(6).trim();
      else if (line.startsWith("data:")) dataLines.push(line.slice(5).trim());
    }
    const raw = dataLines.join("\n");
    let data: unknown = raw;
    try {
      data = raw ? JSON.parse(raw) : null;
    } catch {
      /* keep string */
    }
    events.push({ event, data });
  }
  return { events, rest };
}

/** POST JSON and collect SSE events until stream ends (or done/error/end). */
export async function apiSsePostDirect(
  path: string,
  body?: unknown,
  query?: Record<string, string | number | boolean | undefined | null>,
): Promise<{ events: SseEvent[]; done: unknown; error: unknown }> {
  const { headers, token, canRefresh } = getAuthHeaders("application/json");
  Object.assign(headers, {
    Accept: "text/event-stream",
  });
  const res = await fetch(buildUrl(path, query), {
    method: "POST",
    headers,
    body: JSON.stringify(body ?? {}),
  });
  if (res.status === 401 && token && canRefresh) {
    try {
      await refreshToken();
      return apiSsePostDirect(path, body, query);
    } catch {
      clearToken();
      throw new ApiError("unauthorized", 401);
    }
  }
  if (!res.ok) {
    const data = await parseBody(res);
    throw new ApiError(data?.error || data?.message || `http_${res.status}`, res.status, data);
  }
  const events: SseEvent[] = [];
  let done: unknown = null;
  let error: unknown = null;
  const contentType = res.headers.get("content-type") || "";
  if (!contentType.includes("text/event-stream")) {
    const data = await parseBody(res);
    return { events: [{ event: "done", data }], done: data, error: null };
  }
  const reader = res.body?.getReader();
  if (!reader) {
    const text = await res.text();
    const parsed = parseSseChunk(text + "\n\n");
    for (const ev of parsed.events) {
      events.push(ev);
      if (ev.event === "done") done = ev.data;
      if (ev.event === "error") error = ev.data;
    }
    return { events, done, error };
  }
  const decoder = new TextDecoder();
  let buf = "";
  while (true) {
    const { value, done: streamDone } = await reader.read();
    if (value) buf += decoder.decode(value, { stream: !streamDone });
    const { events: chunkEvents, rest } = parseSseChunk(buf);
    buf = rest;
    for (const ev of chunkEvents) {
      events.push(ev);
      if (ev.event === "done") done = ev.data;
      if (ev.event === "error") error = ev.data;
    }
    if (streamDone) break;
    if (done != null || error != null) {
      try {
        await reader.cancel();
      } catch {
        /* ignore */
      }
      break;
    }
  }
  if (buf.trim()) {
    const { events: tail } = parseSseChunk(buf + "\n\n");
    for (const ev of tail) {
      events.push(ev);
      if (ev.event === "done") done = ev.data;
      if (ev.event === "error") error = ev.data;
    }
  }
  return { events, done, error };
}

/** Binary (or JSON) POST response as base64 + content-type. */
export async function apiBinaryPostDirect(
  path: string,
  body?: unknown,
  query?: Record<string, string | number | boolean | undefined | null>,
): Promise<{ content_type: string; base64: string; json?: unknown }> {
  const { headers, token, canRefresh } = getAuthHeaders("application/json");
  const res = await fetch(buildUrl(path, query), {
    method: "POST",
    headers,
    body: JSON.stringify(body ?? {}),
  });
  if (res.status === 401 && token && canRefresh) {
    try {
      await refreshToken();
      return apiBinaryPostDirect(path, body, query);
    } catch {
      clearToken();
      throw new ApiError("unauthorized", 401);
    }
  }
  const contentType = res.headers.get("content-type") || "application/octet-stream";
  const buf = Buffer.from(await res.arrayBuffer());
  if (contentType.includes("application/json") || contentType.includes("text/")) {
    try {
      const json = JSON.parse(buf.toString("utf8"));
      if (!res.ok) throw new ApiError(json?.error || json?.message || `http_${res.status}`, res.status, json);
      return { content_type: contentType, base64: buf.toString("base64"), json };
    } catch (e) {
      if (e instanceof ApiError) throw e;
    }
  }
  if (!res.ok) throw new ApiError(`http_${res.status}`, res.status);
  return { content_type: contentType, base64: buf.toString("base64") };
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
