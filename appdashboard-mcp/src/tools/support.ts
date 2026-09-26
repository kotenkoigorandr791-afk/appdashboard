import { shellAction, textResult } from "../api.js";
import type { ToolDef } from "./types.js";

export const supportTools: ToolDef[] = [
  {
    name: "send_support_message",
    description: "Send a support chat message. Alias for sending to the support conversation.",
    minRole: 1,
    inputSchema: {
      type: "object",
      properties: {
        message: { type: "string" },
        user_email: { type: "string", description: "Admin only: target user email" },
      },
      required: ["message"],
    },
    handler: async (args) =>
      textResult(
        await shellAction("send_support_message", {
          message: args.message,
          user_email: args.user_email,
        }),
      ),
  },
  {
    name: "get_support_messages",
    description: "Get support chat history. Alias for reading the support conversation.",
    minRole: 1,
    inputSchema: {
      type: "object",
      properties: {
        user_email: { type: "string", description: "Admin only: target user email" },
      },
    },
    handler: async (args) =>
      textResult(await shellAction("get_support_messages", { user_email: args.user_email })),
  },
];
