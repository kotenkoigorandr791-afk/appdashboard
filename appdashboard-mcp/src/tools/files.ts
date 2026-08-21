import { shellAction, textResult } from "../api.js";
import type { ToolDef } from "./types.js";

export const filesTools: ToolDef[] = [
  {
    name: "fm_list_dir",
    description: "List files in a directory under the app root (admin).",
    minRole: 2,
    inputSchema: {
      type: "object",
      properties: { path: { type: "string", description: "Relative path under APP_ROOT" } },
      required: ["path"],
    },
    handler: async (args) => textResult(await shellAction("fm_list_dir", { path: args.path })),
  },
  {
    name: "fm_read_file",
    description: "Read a file under the app root (admin).",
    minRole: 2,
    inputSchema: {
      type: "object",
      properties: { path: { type: "string" } },
      required: ["path"],
    },
    handler: async (args) => textResult(await shellAction("fm_get_file", { path: args.path })),
  },
  {
    name: "fm_write_file",
    description: "Write/save a file under the app root (admin).",
    minRole: 2,
    inputSchema: {
      type: "object",
      properties: {
        path: { type: "string" },
        content: { type: "string" },
      },
      required: ["path", "content"],
    },
    handler: async (args) => {
      const path = String(args.path ?? "");
      const content = String(args.content ?? "");
      const saved = await shellAction("fm_save_file", { path, content });
      if (saved && typeof saved === "object" && (saved as { error?: string }).error === "file_not_found") {
        const created = await shellAction("fm_mkfile", { path, content });
        return textResult(created);
      }
      return textResult(saved);
    },
  },
  {
    name: "fm_create_dir",
    description: "Create a directory under the app root (admin).",
    minRole: 2,
    inputSchema: {
      type: "object",
      properties: { path: { type: "string" } },
      required: ["path"],
    },
    handler: async (args) => textResult(await shellAction("fm_mkdir", { path: args.path })),
  },
  {
    name: "fm_create_file",
    description: "Create an empty file under the app root (admin).",
    minRole: 2,
    inputSchema: {
      type: "object",
      properties: {
        path: { type: "string" },
        content: { type: "string" },
      },
      required: ["path"],
    },
    handler: async (args) =>
      textResult(await shellAction("fm_mkfile", { path: args.path, content: args.content ?? "" })),
  },
  {
    name: "fm_delete",
    description: "Delete a file or directory under the app root (admin).",
    minRole: 2,
    inputSchema: {
      type: "object",
      properties: { path: { type: "string" } },
      required: ["path"],
    },
    handler: async (args) => textResult(await shellAction("fm_delete", { path: args.path })),
  },
  {
    name: "fm_rename",
    description: "Rename/move a file or directory under the app root (admin).",
    minRole: 2,
    inputSchema: {
      type: "object",
      properties: {
        path: { type: "string" },
        new_path: { type: "string" },
      },
      required: ["path", "new_path"],
    },
    handler: async (args) =>
      textResult(await shellAction("fm_rename", { path: args.path, new_path: args.new_path })),
  },
  {
    name: "fm_dir_size",
    description: "Get directory size under the app root (admin).",
    minRole: 2,
    inputSchema: {
      type: "object",
      properties: { path: { type: "string" } },
      required: ["path"],
    },
    handler: async (args) => textResult(await shellAction("fm_dir_size", { path: args.path })),
  },
  {
    name: "get_shared_files",
    description: "List shared files for the current user.",
    minRole: 0,
    inputSchema: { type: "object", properties: {} },
    handler: async () => textResult(await shellAction("get_shared_files")),
  },
  {
    name: "delete_shared_file",
    description: "Delete a shared file you own (or any if admin).",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: { share_id: { type: "string" } },
      required: ["share_id"],
    },
    handler: async (args) => textResult(await shellAction("delete_shared_file", { share_id: args.share_id })),
  },
  {
    name: "update_share_expiration",
    description: "Update shared file expiration.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        share_id: { type: "string" },
        expires_option: {
          type: "string",
          description: "never|1h|1d|1w|1m|3m|6m|1y (or custom with expires)",
        },
        expires: {
          type: "number",
          description: "Unix timestamp when expires_option=custom; must be in the future",
        },
      },
      required: ["share_id", "expires_option"],
    },
    handler: async (args) => {
      const option = String(args.expires_option ?? "");
      if (option === "custom" || args.expires != null) {
        const expires = Number(args.expires);
        if (!Number.isFinite(expires) || expires <= Math.floor(Date.now() / 1000)) {
          return textResult({
            ok: false,
            error: "expires_must_be_future",
            message: "Share expiration must be a unix timestamp in the future.",
          });
        }
      }
      return textResult(
        await shellAction("update_shared_file_expiration", {
          share_id: args.share_id,
          expires_option: option,
        }),
      );
    },
  },
];
