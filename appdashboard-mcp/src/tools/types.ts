import type { Role } from "../roles.js";

export type ToolResult = {
  content: Array<{ type: "text"; text: string }>;
  isError?: boolean;
};

export type ToolDef = {
  name: string;
  description: string;
  inputSchema: Record<string, unknown>;
  minRole: Role;
  handler: (args: Record<string, unknown>) => Promise<ToolResult>;
};
