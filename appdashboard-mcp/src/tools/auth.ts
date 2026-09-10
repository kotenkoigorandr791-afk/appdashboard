import { apiGet, shellAction, textResult } from "../api.js";
import { setCachedRole } from "../auth.js";
import { normalizeRole } from "../roles.js";
import type { ToolDef } from "./types.js";

const empty: Record<string, unknown> = { type: "object", properties: {} };

export const authTools: ToolDef[] = [
  {
    name: "get_current_user",
    description: "Get the currently authenticated AppDashboard user.",
    minRole: 0,
    inputSchema: empty,
    handler: async () => {
      const data = await apiGet("/public-api/auth/me");
      if (data?.user?.role !== undefined) {
        const role = normalizeRole(data.user.role);
        setCachedRole(role);
      }
      return textResult(data);
    },
  },
  {
    name: "change_password",
    description: "Change the current user's password.",
    minRole: 1,
    inputSchema: {
      type: "object",
      properties: {
        current_password: { type: "string" },
        new_password: { type: "string" },
        confirm_password: { type: "string" },
      },
      required: ["current_password", "new_password", "confirm_password"],
    },
    handler: async (args) =>
      textResult(
        await shellAction("change_password", {
          current_password: args.current_password,
          new_password: args.new_password,
          confirm_password: args.confirm_password,
        }),
      ),
  },
  {
    name: "change_avatar",
    description: "Change the current user's avatar from a base64 image.",
    minRole: 1,
    inputSchema: {
      type: "object",
      properties: {
        photo_base64: { type: "string", description: "Base64-encoded image data" },
      },
      required: ["photo_base64"],
    },
    handler: async (args) => textResult(await shellAction("change_photo", { photo_base64: args.photo_base64 })),
  },
  {
    name: "reset_avatar",
    description: "Reset the current user's avatar to the default.",
    minRole: 1,
    inputSchema: empty,
    handler: async () => textResult(await shellAction("reset_photo")),
  },
  {
    name: "save_settings",
    description: "Save user settings (language, theme, color).",
    minRole: 1,
    inputSchema: {
      type: "object",
      properties: {
        language: { type: "string" },
        theme: { type: "string" },
        color: { type: "string" },
      },
    },
    handler: async (args) =>
      textResult(
        await shellAction("settings", {
          language: args.language,
          theme: args.theme,
          color: args.color,
        }),
      ),
  },
];
