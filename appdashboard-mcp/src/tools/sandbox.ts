import { textResult } from "../api.js";
import type { ToolDef } from "./types.js";
import {
  listSandboxScripts,
  createSandboxScript,
  getSandboxScript,
  deleteSandboxScript,
  executeSandboxScript,
  getSupportedLanguages,
} from "../sandbox.js";

export const sandboxTools: ToolDef[] = [
  {
    name: "list_sandbox_scripts",
    description: "List all sandbox scripts",
    inputSchema: { type: "object", properties: {} },
    minRole: 2,
    handler: async () => {
      const result = await listSandboxScripts();
      return textResult({
        scripts: result.scripts,
        total: result.total,
      });
    },
  },
  {
    name: "get_sandbox_script",
    description: "Get details of a specific sandbox script",
    inputSchema: {
      type: "object",
      properties: {
        script_id: { type: "string" },
      },
      required: ["script_id"],
    },
    minRole: 2,
    handler: async (args: any) => {
      const script = await getSandboxScript(args.script_id);
      return textResult(script);
    },
  },
  {
    name: "create_sandbox_script",
    description: "Create a new sandbox script (bash, python, php, javascript)",
    inputSchema: {
      type: "object",
      properties: {
        name: { type: "string", description: "Script name" },
        filename: { type: "string", description: "Script filename" },
        content: { type: "string", description: "Script content" },
        language: {
          type: "string",
          description: "Script language",
          enum: ["bash", "python", "php", "javascript"],
        },
        description: { type: "string", description: "Script description" },
      },
      required: ["name", "filename", "content", "language"],
    },
    minRole: 2,
    handler: async (args: any) => {
      const script = await createSandboxScript({
        name: args.name,
        filename: args.filename,
        content: args.content,
        language: args.language as "bash" | "python" | "php" | "javascript",
        description: args.description,
        created_by: "user",
      });
      return textResult({
        success: true,
        script,
        message: "Script created successfully",
      });
    },
  },
  {
    name: "delete_sandbox_script",
    description: "Delete a sandbox script",
    inputSchema: {
      type: "object",
      properties: {
        script_id: { type: "string" },
      },
      required: ["script_id"],
    },
    minRole: 2,
    handler: async (args: any) => {
      await deleteSandboxScript(args.script_id);
      return textResult({
        success: true,
        message: "Script deleted successfully",
      });
    },
  },
  {
    name: "execute_sandbox_script",
    description: "Execute a sandbox script with parameters",
    inputSchema: {
      type: "object",
      properties: {
        script_id: { type: "string", description: "Script ID to execute" },
        params: {
          type: "object",
          additionalProperties: true,
          description: "Script parameters",
        },
      },
      required: ["script_id"],
    },
    minRole: 2,
    handler: async (args: any) => {
      const result = await executeSandboxScript(args.script_id as string, args.params || {});

      if (!result.success && result.error) {
        return textResult({
          success: false,
          error: result.error,
          stdout: result.stdout,
          stderr: result.stderr,
        });
      }

      return textResult({
        success: true,
        stdout: result.stdout,
        stderr: result.stderr,
        exitCode: result.exit_code,
      });
    },
  },
  {
    name: "get_sandbox_languages",
    description: "Get list of supported sandbox languages",
    inputSchema: { type: "object", properties: {} },
    minRole: 2,
    handler: async () => {
      return textResult({
        languages: getSupportedLanguages(),
        description: "These languages can be used in sandbox scripts. Parameters can be passed via $PARAM_NAME syntax.",
      });
    },
  },
];
