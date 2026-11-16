import { apiJsonPost, textResult } from "../api.js";
import type { ToolDef } from "./types.js";

const ENDPOINT = "/apps/steps/api/mcp_read.php";

async function geoRead(tool: string, args: Record<string, unknown>) {
  const payload: Record<string, unknown> = { tool };
  for (const [key, value] of Object.entries(args)) {
    if (value !== undefined && value !== null && value !== "") payload[key] = value;
  }
  return textResult(await apiJsonPost(ENDPOINT, payload));
}

const target = {
  type: "string",
  description: "Optional owner email whose location was explicitly shared with you. Omit for your own data.",
};
const range = {
  from: { type: "string", description: "Start of the interval (ISO 8601 or YYYY-MM-DD). Maximum 31 days." },
  to: { type: "string", description: "End of the interval (ISO 8601 or YYYY-MM-DD). Maximum 31 days." },
};
const detail = {
  type: "boolean",
  description: "Request the most precise coordinates the active sharing grant allows. Defaults to rounded summaries.",
};
const limit = {
  type: "number",
  description: "Maximum results, 1-100. Defaults to 50.",
};

export const geolocationTools: ToolDef[] = [
  {
    name: "geolocation_get_current_location",
    description:
      "Read the latest accepted location. Read-only; requires an active family grant when target is another person. Coordinates are rounded unless detail=true and the grant permits precision.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: { target, detail },
    },
    handler: async (args) => geoRead("get_current_location", args),
  },
  {
    name: "geolocation_search_places",
    description:
      "Search saved places and visit counts in the last 30 days. Read-only; never exposes another tenant's unvisited places.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        target,
        q: { type: "string", description: "Case-insensitive name or tag filter" },
        place_id: { type: "string", description: "Exact place UUID" },
        ...range,
        detail,
        limit,
      },
    },
    handler: async (args) => geoRead("search_places", args),
  },
  {
    name: "geolocation_search_visits",
    description: "Search visit events in a bounded interval. Read-only; returned without coordinates.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        target,
        place_id: { type: "string", description: "Exact place UUID" },
        ...range,
        limit,
      },
    },
    handler: async (args) => geoRead("search_visits", args),
  },
  {
    name: "geolocation_get_timeline",
    description:
      "Read timeline entries (trips, stops, visits and gaps) for a bounded interval. Read-only; map bounds are removed unless detail=true.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        target,
        include: {
          type: "array",
          items: { type: "string", enum: ["trips", "stops", "visits", "gaps"] },
          description: "Entry kinds to include (places-only grants always return visits only)",
        },
        timezone: { type: "string", description: "IANA timezone for local timestamps, e.g. Europe/Kyiv" },
        q: { type: "string", description: "Filter by title/detail" },
        ...range,
        detail,
        limit,
      },
    },
    handler: async (args) => geoRead("get_timeline", args),
  },
  {
    name: "geolocation_get_trips",
    description: "Read trip summaries in a bounded interval. Read-only; route bounds require detail=true and a granting scope.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: { target, ...range, detail, limit },
    },
    handler: async (args) => geoRead("get_trips", args),
  },
  {
    name: "geolocation_get_statistics",
    description:
      "Read aggregated distance, coverage, countries and cities for a bounded interval. Read-only; unavailable for places-only grants.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        target,
        timezone: { type: "string", description: "IANA timezone, e.g. Europe/Kyiv" },
        ...range,
      },
    },
    handler: async (args) => geoRead("get_statistics", args),
  },
  {
    name: "geolocation_get_immich_photos",
    description:
      "Read matched Immich photo metadata for your own account only. Read-only; family grants never expose photo libraries.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        bbox: { type: "string", description: "Optional minLon,minLat,maxLon,maxLat filter" },
        ...range,
        detail,
        limit,
      },
    },
    handler: async (args) => geoRead("get_immich_photos", args),
  },
];
