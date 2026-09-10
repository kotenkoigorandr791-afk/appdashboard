import type { Request, Response } from "express";
import { getHost } from "./auth.js";

export function publicBase(): string {
  return (process.env.MCP_PUBLIC_BASE || "http://localhost:8808").replace(/\/+$/, "");
}

export function appBase(): string {
  return (process.env.OAUTH_APP_BASE || "http://localhost").replace(/\/+$/, "");
}

export function protectedResourceMetadata(): Record<string, unknown> {
  return {
    resource: `${publicBase()}/mcp`,
    authorization_servers: [publicBase()],
  };
}

export function authorizationServerMetadata(): Record<string, unknown> {
  const base = publicBase();
  return {
    issuer: base,
    authorization_endpoint: `${base}/oauth/authorize`,
    token_endpoint: `${base}/oauth/token`,
    registration_endpoint: `${base}/oauth/register`,
    revocation_endpoint: `${base}/oauth/revoke`,
    response_types_supported: ["code"],
    grant_types_supported: ["authorization_code", "refresh_token"],
    token_endpoint_auth_methods_supported: ["client_secret_post", "client_secret_basic"],
    code_challenge_methods_supported: ["S256"],
    scopes_supported: ["mcp"],
    protected_resources: [`${base}/mcp`],
  };
}

function jsonOut(res: Response, status: number, body: unknown): void {
  res.status(status).json(body);
}

function validCodeChallenge(cc: string): boolean {
  return cc.length >= 43 && cc.length <= 128 && /^[A-Za-z0-9._~-]+$/.test(cc);
}

async function lookupClient(clientId: string, redirectUri: string): Promise<{ found: boolean; redirectOk: boolean } | null> {
  const base = getHost();
  if (!base) return null;
  const url = new URL(`${base}/public-api/oauth/client`);
  url.searchParams.set("id", clientId);
  url.searchParams.set("redirect_uri", redirectUri);
  try {
    const res = await fetch(url.toString(), { signal: AbortSignal.timeout(8000) });
    const data = await res.json();
    if (!res.ok || !data || typeof data !== "object") return null;
    return {
      found: Boolean((data as any).found),
      redirectOk: Boolean((data as any).redirect_ok),
    };
  } catch {
    return null;
  }
}

export async function handleAuthorize(req: Request, res: Response): Promise<void> {
  const q = req.query as Record<string, string | undefined>;
  const responseType = String(q.response_type || "code");
  const clientId = String(q.client_id || "").trim();
  const redirectUri = String(q.redirect_uri || "").trim();
  const scope = String(q.scope || "mcp").trim() || "mcp";
  const state = String(q.state || "").trim();
  const codeChallenge = String(q.code_challenge || "").trim();
  const method = String(q.code_challenge_method || "S256").trim();

  if (responseType !== "code") return jsonOut(res, 400, { error: "unsupported_response_type" });
  if (!clientId || !redirectUri) return jsonOut(res, 400, { error: "invalid_request" });
  if (method !== "S256" && method !== "plain") return jsonOut(res, 400, { error: "invalid_request" });
  if (!validCodeChallenge(codeChallenge)) return jsonOut(res, 400, { error: "invalid_request" });

  const lookup = await lookupClient(clientId, redirectUri);
  if (!lookup) return jsonOut(res, 503, { error: "auth_server_unavailable" });
  if (!lookup.found || !lookup.redirectOk) return jsonOut(res, 400, { error: "unauthorized_client" });

  const params = new URLSearchParams({
    client_id: clientId,
    redirect_uri: redirectUri,
    response_type: responseType,
    scope,
    code_challenge: codeChallenge,
    code_challenge_method: method,
  });
  if (state) params.set("state", state);
  res.redirect(302, `${appBase()}/oauth/approve?${params.toString()}`);
}

export function handleTokenHealth(req: Request, res: Response): void {
  jsonOut(res, 200, { ok: true });
}