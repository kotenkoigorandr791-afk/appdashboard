import { apiGet, apiPost, textResult } from "../api.js";
import type { ToolDef } from "./types.js";

export const appsTools: ToolDef[] = [
  {
    name: "list_apps",
    description: "List applications available to the current user.",
    minRole: 0,
    inputSchema: { type: "object", properties: {} },
    handler: async () => textResult(await apiGet("/public-api/apps")),
  },
  {
    name: "get_app_access_levels",
    description: "Get min_role access levels for all apps (admin).",
    minRole: 2,
    inputSchema: { type: "object", properties: {} },
    handler: async () => textResult(await apiGet("/public-api/apps/access_levels")),
  },
  {
    name: "set_app_access_level",
    description: "Set min_role for an application (admin).",
    minRole: 2,
    inputSchema: {
      type: "object",
      properties: {
        app_id: { type: "string" },
        min_role: { type: "number", description: "0, 1, or 2" },
      },
      required: ["app_id", "min_role"],
    },
    handler: async (args) =>
      textResult(
        await apiPost("/public-api/apps/access_levels", {
          app_id: args.app_id,
          min_role: args.min_role,
        }),
      ),
  },
];
