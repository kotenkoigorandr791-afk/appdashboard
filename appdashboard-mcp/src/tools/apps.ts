import { apiGet, textResult } from "../api.js";
import type { ToolDef } from "./types.js";

export const appsTools: ToolDef[] = [
  {
    name: "list_apps",
    description: "List applications available to the current user.",
    minRole: 0,
    inputSchema: { type: "object", properties: {} },
    handler: async () => textResult(await apiGet("/public-api/apps")),
  },
];
