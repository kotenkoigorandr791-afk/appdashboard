import { immichFetch, getImmichCredentials } from "../integrations.js";
import { textResult, imageResult, ApiError } from "../api.js";
import type { ToolDef } from "./types.js";

function pick(args: Record<string, unknown>, keys: string[]): Record<string, unknown> {
  const out: Record<string, unknown> = {};
  for (const k of keys) {
    if (args[k] !== undefined && args[k] !== null && args[k] !== "") out[k] = args[k];
  }
  return out;
}

export const immichTools: ToolDef[] = [
  {
    name: "immich_albums_list",
    description: "List Immich albums. Optional filters: shared, assetId.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        shared: { type: "boolean", description: "Filter shared albums" },
        assetId: { type: "string", description: "Albums containing this asset ID" },
      },
    },
    handler: async (args) => textResult(await immichFetch("GET", "/api/albums", { query: pick(args, ["shared", "assetId"]) })),
  },
  {
    name: "immich_albums_create",
    description: "Create an Immich album with optional asset IDs.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        albumName: { type: "string" },
        description: { type: "string" },
        assetIds: { type: "array", items: { type: "string" } },
      },
      required: ["albumName"],
    },
    handler: async (args) =>
      textResult(
        await immichFetch("POST", "/api/albums", {
          body: {
            albumName: String(args.albumName),
            description: args.description != null ? String(args.description) : "",
            assetIds: Array.isArray(args.assetIds) ? args.assetIds.map(String) : [],
          },
        }),
      ),
  },
  {
    name: "immich_albums_get",
    description: "Get Immich album details by ID.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: { albumId: { type: "string" } },
      required: ["albumId"],
    },
    handler: async (args) => textResult(await immichFetch("GET", `/api/albums/${encodeURIComponent(String(args.albumId))}`)),
  },
  {
    name: "immich_albums_update",
    description: "Update Immich album name/description.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        albumId: { type: "string" },
        albumName: { type: "string" },
        description: { type: "string" },
      },
      required: ["albumId"],
    },
    handler: async (args) => {
      const body = pick(args, ["albumName", "description"]);
      return textResult(await immichFetch("PATCH", `/api/albums/${encodeURIComponent(String(args.albumId))}`, { body }));
    },
  },
  {
    name: "immich_albums_delete",
    description: "Delete an Immich album.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: { albumId: { type: "string" } },
      required: ["albumId"],
    },
    handler: async (args) => {
      await immichFetch("DELETE", `/api/albums/${encodeURIComponent(String(args.albumId))}`);
      return textResult({ success: true, albumId: String(args.albumId) });
    },
  },
  {
    name: "immich_albums_add_assets",
    description: "Add assets to an Immich album.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        albumId: { type: "string" },
        assetIds: { type: "array", items: { type: "string" } },
      },
      required: ["albumId", "assetIds"],
    },
    handler: async (args) =>
      textResult(
        await immichFetch("PUT", `/api/albums/${encodeURIComponent(String(args.albumId))}/assets`, {
          body: { ids: Array.isArray(args.assetIds) ? args.assetIds.map(String) : [] },
        }),
      ),
  },
  {
    name: "immich_albums_remove_assets",
    description: "Remove assets from an Immich album.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        albumId: { type: "string" },
        assetIds: { type: "array", items: { type: "string" } },
      },
      required: ["albumId", "assetIds"],
    },
    handler: async (args) =>
      textResult(
        await immichFetch("DELETE", `/api/albums/${encodeURIComponent(String(args.albumId))}/assets`, {
          body: { ids: Array.isArray(args.assetIds) ? args.assetIds.map(String) : [] },
        }),
      ),
  },
  {
    name: "immich_assets_list",
    description: "List Immich assets via metadata search (pagination/filters).",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        page: { type: "number", description: "Page (default 1)" },
        size: { type: "number", description: "Page size (default 100, max 1000)" },
        userId: { type: "string" },
        isFavorite: { type: "boolean" },
        isArchived: { type: "boolean" },
        withStacked: { type: "boolean" },
        withPartners: { type: "boolean" },
        type: { type: "string", enum: ["IMAGE", "VIDEO", "AUDIO", "OTHER"] },
      },
    },
    handler: async (args) => {
      const body: Record<string, unknown> = {
        page: args.page != null ? Number(args.page) : 1,
        size: Math.min(1000, Math.max(1, args.size != null ? Number(args.size) : 100)),
      };
      Object.assign(body, pick(args, ["userId", "isFavorite", "isArchived", "withStacked", "withPartners", "type"]));
      return textResult(await immichFetch("POST", "/api/search/metadata", { body }));
    },
  },
  {
    name: "immich_assets_get",
    description: "Get Immich asset details by ID.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        assetId: { type: "string" },
        key: { type: "string", description: "Optional shared-link key" },
      },
      required: ["assetId"],
    },
    handler: async (args) =>
      textResult(
        await immichFetch("GET", `/api/assets/${encodeURIComponent(String(args.assetId))}`, {
          query: pick(args, ["key"]),
        }),
      ),
  },
  {
    name: "immich_assets_update",
    description: "Update Immich asset (favorite/archive/description).",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        assetId: { type: "string" },
        isFavorite: { type: "boolean" },
        isArchived: { type: "boolean" },
        description: { type: "string" },
      },
      required: ["assetId"],
    },
    handler: async (args) => {
      const body = pick(args, ["isFavorite", "isArchived", "description"]);
      return textResult(await immichFetch("PUT", `/api/assets/${encodeURIComponent(String(args.assetId))}`, { body }));
    },
  },
  {
    name: "immich_assets_delete",
    description: "Delete Immich asset(s) by ID.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        assetId: { type: "string", description: "Single asset ID" },
        assetIds: { type: "array", items: { type: "string" }, description: "Multiple asset IDs" },
        force: { type: "boolean", description: "Permanent delete" },
      },
    },
    handler: async (args) => {
      const ids = Array.isArray(args.assetIds)
        ? args.assetIds.map(String)
        : args.assetId
          ? [String(args.assetId)]
          : [];
      if (!ids.length) throw new Error("assetId or assetIds required");
      return textResult(
        await immichFetch("DELETE", "/api/assets", {
          body: { ids, force: Boolean(args.force) },
        }),
      );
    },
  },
  {
    name: "immich_assets_bulk_update",
    description: "Bulk-update Immich assets (favorite/archive/stack).",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        assetIds: { type: "array", items: { type: "string" } },
        isFavorite: { type: "boolean" },
        isArchived: { type: "boolean" },
        removeParent: { type: "boolean" },
        stackParentId: { type: "string" },
      },
      required: ["assetIds"],
    },
    handler: async (args) => {
      const body: Record<string, unknown> = {
        ids: Array.isArray(args.assetIds) ? args.assetIds.map(String) : [],
      };
      Object.assign(body, pick(args, ["isFavorite", "isArchived", "removeParent", "stackParentId"]));
      return textResult(await immichFetch("PUT", "/api/assets", { body }));
    },
  },
  {
    name: "immich_assets_get_statistics",
    description: "Get Immich asset statistics.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        isArchived: { type: "boolean" },
        isFavorite: { type: "boolean" },
      },
    },
    handler: async (args) =>
      textResult(await immichFetch("GET", "/api/assets/statistics", { query: pick(args, ["isArchived", "isFavorite"]) })),
  },
  {
    name: "immich_assets_get_random",
    description: "Get random Immich assets.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: { count: { type: "number", description: "Number of assets (1-100, default 1)" } },
    },
    handler: async (args) => {
      const count = Math.min(100, Math.max(1, args.count != null ? Number(args.count) : 1));
      return textResult(await immichFetch("POST", "/api/search/random", { body: { size: count } }));
    },
  },
  {
    name: "immich_search_general",
    description: "General Immich search via metadata endpoint (query text + filters).",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        q: { type: "string" },
        query: { type: "string" },
        isFavorite: { type: "boolean" },
        isArchived: { type: "boolean" },
        withArchived: { type: "boolean" },
        type: { type: "string", enum: ["IMAGE", "VIDEO", "AUDIO", "OTHER"] },
        page: { type: "number" },
        size: { type: "number" },
      },
    },
    handler: async (args) => {
      const body: Record<string, unknown> = {
        page: args.page != null ? Number(args.page) : 1,
        size: args.size != null ? Number(args.size) : 100,
      };
      const q = args.q ?? args.query;
      if (q != null) body.query = String(q);
      Object.assign(body, pick(args, ["isFavorite", "isArchived", "withArchived", "type"]));
      return textResult(await immichFetch("POST", "/api/search/metadata", { body }));
    },
  },
  {
    name: "immich_search_smart",
    description: "AI smart search by natural-language description.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        query: { type: "string", description: 'e.g. "beach sunset"' },
        city: { type: "string" },
        state: { type: "string" },
        country: { type: "string" },
        make: { type: "string" },
        model: { type: "string" },
        lensModel: { type: "string" },
        type: { type: "string", enum: ["IMAGE", "VIDEO"] },
        isFavorite: { type: "boolean" },
        isArchived: { type: "boolean" },
        page: { type: "number" },
        size: { type: "number" },
      },
      required: ["query"],
    },
    handler: async (args) => {
      const body: Record<string, unknown> = {
        query: String(args.query),
        page: args.page != null ? Number(args.page) : 1,
        size: args.size != null ? Number(args.size) : 100,
      };
      Object.assign(
        body,
        pick(args, ["city", "state", "country", "make", "model", "lensModel", "type", "isFavorite", "isArchived"]),
      );
      return textResult(await immichFetch("POST", "/api/search/smart", { body }));
    },
  },
  {
    name: "immich_search_metadata",
    description: "Search Immich assets by EXIF/location metadata.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        city: { type: "string" },
        state: { type: "string" },
        country: { type: "string" },
        make: { type: "string" },
        model: { type: "string" },
        lensModel: { type: "string" },
        type: { type: "string", enum: ["IMAGE", "VIDEO"] },
        withArchived: { type: "boolean" },
        page: { type: "number" },
        size: { type: "number" },
      },
    },
    handler: async (args) => {
      const body: Record<string, unknown> = {
        page: args.page != null ? Number(args.page) : 1,
        size: args.size != null ? Number(args.size) : 100,
      };
      Object.assign(
        body,
        pick(args, ["city", "state", "country", "make", "model", "lensModel", "type", "withArchived"]),
      );
      return textResult(await immichFetch("POST", "/api/search/metadata", { body }));
    },
  },
  {
    name: "immich_search_explore",
    description: "Explore Immich by detected objects/faces/places.",
    minRole: 0,
    inputSchema: { type: "object", properties: {} },
    handler: async () => textResult(await immichFetch("GET", "/api/search/explore")),
  },
  {
    name: "immich_assets_thumbnail",
    description:
      "Get a photo thumbnail and return it as a viewable image. " +
      "Use this when the user asks to see/show a photo. " +
      "Pass assetId (or use immich_assets_list / immich_search_smart first to find one).",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        assetId: { type: "string", description: "Asset ID to preview" },
        size: { type: "string", enum: ["thumbnail", "preview", "original"], description: "thumbnail (small) | preview (default) | original" },
      },
      required: ["assetId"],
    },
    handler: async (args) => {
      const assetId = String(args.assetId);
      const size = String(args.size || "preview");
      const path =
        size === "thumbnail"
          ? `/api/assets/${encodeURIComponent(assetId)}/thumbnail`
          : size === "original"
            ? `/api/assets/${encodeURIComponent(assetId)}/original`
            : `/api/assets/${encodeURIComponent(assetId)}/preview`;
      const creds = await getImmichCredentials();
      const res = await fetch(`${creds.url}${path}`, { headers: { "x-api-key": creds.api_key, Accept: "*/*" } });
      if (!res.ok) {
        let detail: unknown = null;
        const text = await res.text().catch(() => "");
        try {
          detail = text ? JSON.parse(text) : { raw: text.slice(0, 200) };
        } catch {
          detail = { raw: text.slice(0, 200) };
        }
        throw new ApiError(typeof (detail as any)?.message === "string" ? (detail as any).message : `immich_http_${res.status}`, res.status, detail);
      }
      const mimeType = res.headers.get("content-type") || "application/octet-stream";
      if (!/^(image|video)\//.test(mimeType)) {
        const text = await res.text().catch(() => "");
        throw new ApiError(`unexpected_content_type_${mimeType}`, 502, { raw: text.slice(0, 200) });
      }
      const buf = Buffer.from(await res.arrayBuffer());
      // videos may be large; only return images inline
      if (mimeType.startsWith("video/")) {
        return textResult({ ok: true, assetId, mimeType, size_bytes: buf.length, note: "Video cannot be rendered inline; use download via Immich UI." });
      }
      if (buf.length > 8 * 1024 * 1024) {
        return textResult({ ok: true, assetId, mimeType, size_bytes: buf.length, error: "image_too_large_max_8mb" });
      }
      return imageResult(buf.toString("base64"), mimeType, `Asset ${assetId} (${mimeType}, ${buf.length} bytes):`);
    },
  },
  {
    name: "immich_assets_download",
    description:
      "Download an Immich asset's original file and return base64 + metadata (for saving to disk). " +
      "For just viewing a photo use immich_assets_thumbnail instead.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: { assetId: { type: "string" } },
      required: ["assetId"],
    },
    handler: async (args) => {
      const assetId = String(args.assetId);
      const creds = await getImmichCredentials();
      const res = await fetch(`${creds.url}/api/assets/${encodeURIComponent(assetId)}/original`, {
        headers: { "x-api-key": creds.api_key, Accept: "*/*" },
      });
      if (!res.ok) {
        throw new ApiError(`immich_http_${res.status}`, res.status);
      }
      const mimeType = res.headers.get("content-type") || "application/octet-stream";
      const disposition = res.headers.get("content-disposition") || "";
      const nameMatch = disposition.match(/filename\*?=(?:UTF-8'')?"?([^";]+)/i);
      const buf = Buffer.from(await res.arrayBuffer());
      return textResult({
        ok: true,
        assetId,
        filename: nameMatch ? nameMatch[1] : `${assetId}.bin`,
        content_type: mimeType,
        size_bytes: buf.length,
        base64: buf.toString("base64"),
      });
    },
  },
];
