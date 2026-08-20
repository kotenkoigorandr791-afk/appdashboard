import { apiGet, apiPut, shellAction, textResult } from "../api.js";
import type { ToolDef } from "./types.js";

export const configTools: ToolDef[] = [
  {
    name: "get_config",
    description: "Get a config value by key (admin).",
    minRole: 2,
    inputSchema: {
      type: "object",
      properties: { key: { type: "string" } },
      required: ["key"],
    },
    handler: async (args) =>
      textResult(await apiGet(`/public-api/config/${encodeURIComponent(String(args.key))}`)),
  },
  {
    name: "set_config",
    description: "Set a config value by key (admin).",
    minRole: 2,
    inputSchema: {
      type: "object",
      properties: {
        key: { type: "string" },
        value: { type: "string" },
      },
      required: ["key", "value"],
    },
    handler: async (args) =>
      textResult(
        await apiPut(`/public-api/config/${encodeURIComponent(String(args.key))}`, { value: args.value }),
      ),
  },
  {
    name: "list_config_keys",
    description: "List all config keys (admin).",
    minRole: 2,
    inputSchema: { type: "object", properties: {} },
    handler: async () => textResult(await apiGet("/public-api/config")),
  },
  {
    name: "list_json_files",
    description: "List JSON files in the app root (admin).",
    minRole: 2,
    inputSchema: { type: "object", properties: {} },
    handler: async () => textResult(await shellAction("list_json_files")),
  },
  {
    name: "read_json_file",
    description: "Read a JSON file under the app root (admin).",
    minRole: 2,
    inputSchema: {
      type: "object",
      properties: { path: { type: "string" } },
      required: ["path"],
    },
    handler: async (args) => textResult(await shellAction("get_json_file", { path: args.path })),
  },
  {
    name: "write_json_file",
    description: "Write a JSON file under the app root (admin).",
    minRole: 2,
    inputSchema: {
      type: "object",
      properties: {
        path: { type: "string" },
        content: {},
      },
      required: ["path", "content"],
    },
    handler: async (args) =>
      textResult(await shellAction("save_json_file", { path: args.path, content: args.content })),
  },
];
