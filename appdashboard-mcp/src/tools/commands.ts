import { shellAction, textResult } from "../api.js";
import type { ToolDef } from "./types.js";

export const commandsTools: ToolDef[] = [
  {
    name: "list_quick_commands",
    description: "List quick commands (admin).",
    minRole: 2,
    inputSchema: { type: "object", properties: {} },
    handler: async () => textResult(await shellAction("list_quick_commands")),
  },
  {
    name: "create_quick_command",
    description: "Create a quick command (admin).",
    minRole: 2,
    inputSchema: {
      type: "object",
      properties: {
        name: { type: "string" },
        command: { type: "string" },
        directory: { type: "string" },
      },
      required: ["name", "command"],
    },
    handler: async (args) =>
      textResult(
        await shellAction("create_quick_command", {
          name: args.name,
          command: args.command,
          directory: args.directory,
        }),
      ),
  },
  {
    name: "update_quick_command",
    description: "Update a quick command (admin).",
    minRole: 2,
    inputSchema: {
      type: "object",
      properties: {
        id: { type: "string" },
        name: { type: "string" },
        command: { type: "string" },
        directory: { type: "string" },
      },
      required: ["id"],
    },
    handler: async (args) => textResult(await shellAction("update_quick_command", { ...args })),
  },
  {
    name: "delete_quick_command",
    description: "Delete a quick command (admin).",
    minRole: 2,
    inputSchema: {
      type: "object",
      properties: { id: { type: "string" } },
      required: ["id"],
    },
    handler: async (args) => textResult(await shellAction("delete_quick_command", { id: args.id })),
  },
];
