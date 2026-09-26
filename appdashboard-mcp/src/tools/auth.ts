import { apiGet, textResult } from "../api.js";
import { setCachedRole } from "../auth.js";
import { normalizeRole } from "../roles.js";
import type { ToolDef } from "./types.js";

export const authTools: ToolDef[] = [
  {
    name: "get_current_user",
    description: "Get the currently authenticated AppDashboard user.",
    minRole: 0,
    inputSchema: { type: "object", properties: {} },
    handler: async () => {
      const data = await apiGet("/public-api/auth/me");
      if (data?.user?.role !== undefined) {
        const role = normalizeRole(data.user.role);
        setCachedRole(role);
      }
      return textResult(data);
    },
  },
];
