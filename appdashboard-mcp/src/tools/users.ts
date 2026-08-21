import { apiDelete, apiGet, apiPut, textResult } from "../api.js";
import type { ToolDef } from "./types.js";

export const usersTools: ToolDef[] = [
  {
    name: "list_users",
    description: "List all AppDashboard users (admin).",
    minRole: 2,
    inputSchema: { type: "object", properties: {} },
    handler: async () => textResult(await apiGet("/public-api/admin/users")),
  },
  {
    name: "get_user",
    description: "Get a user by email (admin).",
    minRole: 2,
    inputSchema: {
      type: "object",
      properties: { email: { type: "string" } },
      required: ["email"],
    },
    handler: async (args) =>
      textResult(await apiGet(`/public-api/admin/users/${encodeURIComponent(String(args.email))}`)),
  },
  {
    name: "update_user",
    description: "Update a user's role, verified status, or name (admin).",
    minRole: 2,
    inputSchema: {
      type: "object",
      properties: {
        email: { type: "string" },
        role: { type: "number", description: "0 guest, 1 manager, 2 admin" },
        verified: { type: "boolean" },
        name: { type: "string" },
      },
      required: ["email"],
    },
    handler: async (args) => {
      const body: Record<string, unknown> = {};
      if (args.role !== undefined) body.role = args.role;
      if (args.verified !== undefined) body.verified = args.verified;
      if (args.name !== undefined) body.name = args.name;
      return textResult(await apiPut(`/public-api/admin/users/${encodeURIComponent(String(args.email))}`, body));
    },
  },
  {
    name: "delete_user",
    description: "Delete a user by email (admin).",
    minRole: 2,
    inputSchema: {
      type: "object",
      properties: { email: { type: "string" } },
      required: ["email"],
    },
    handler: async (args) =>
      textResult(await apiDelete(`/public-api/admin/users/${encodeURIComponent(String(args.email))}`)),
  },
];
