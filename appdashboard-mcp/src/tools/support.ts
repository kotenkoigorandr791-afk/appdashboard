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
  {
    name: "list_messenger_chats",
    description: "List existing messenger chats. Call this when a recipient is omitted, then ask the user which existing chat to use. A new peer recipient requires the user's full email address.",
    minRole: 1,
    inputSchema: {
      type: "object",
      properties: {},
    },
    handler: async () => textResult(await shellAction("list_messenger_chats", {})),
  },
  {
    name: "send_messenger_message",
    description: "Send a messenger message. Omit recipient_email only for support; when recipient is omitted for peer chat, call list_messenger_chats first, ask which existing chat to use, and require a full email for a new recipient.",
    minRole: 1,
    inputSchema: {
      type: "object",
      properties: {
        message: { type: "string" },
        recipient_email: { type: "string", description: "Full email of an existing registered user for peer chat. Omit for support." },
        chat_id: { type: "string", description: "Existing chat id from list_messenger_chats when available." },
      },
      required: ["message"],
    },
    handler: async (args) =>
      textResult(
        await shellAction("send_messenger_message", {
          message: args.message,
          recipient_email: args.recipient_email,
          chat_id: args.chat_id,
        }),
      ),
  },
  {
    name: "get_messenger_messages",
    description: "Get messenger messages. Omit recipient_email only for support; when recipient is omitted for peer chat, call list_messenger_chats first, ask which existing chat to use, and require a full email for a new recipient.",
    minRole: 1,
    inputSchema: {
      type: "object",
      properties: {
        recipient_email: { type: "string", description: "Full peer email. Omit for support." },
        chat_id: { type: "string", description: "Existing chat id from list_messenger_chats when available." },
      },
    },
    handler: async (args) =>
      textResult(
        await shellAction("get_messenger_messages", {
          recipient_email: args.recipient_email,
          chat_id: args.chat_id,
        }),
      ),
  },
];
