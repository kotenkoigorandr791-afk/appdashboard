import { apiGet, apiPost, textResult } from "../api.js";
import type { ToolDef } from "./types.js";

export const backupTools: ToolDef[] = [
  {
    name: "backup_status",
    description: "Get Duplicati backup job status. Returns configured:false when Duplicati is not set up.",
    minRole: 1,
    inputSchema: { type: "object", properties: {} },
    handler: async () => {
      try {
        return textResult(await apiGet("/public-api/backup/status"));
      } catch (err) {
        const message = err instanceof Error ? err.message : String(err);
        if (/duplicati/i.test(message)) {
          return textResult({ configured: false, reason: "duplicati_not_configured", message: "Duplicati is not configured on this instance; backup management unavailable." });
        }
        throw err;
      }
    },
  },
  {
    name: "backup_run",
    description: "Run a Duplicati backup job (admin).",
    minRole: 2,
    inputSchema: {
      type: "object",
      properties: { id: { type: "string" } },
      required: ["id"],
    },
    handler: async (args) =>
      textResult(await apiPost(`/public-api/backup/run/${encodeURIComponent(String(args.id))}`)),
  },
  {
    name: "backup_logs",
    description: "Get logs for a Duplicati backup job (admin).",
    minRole: 2,
    inputSchema: {
      type: "object",
      properties: { id: { type: "string" } },
      required: ["id"],
    },
    handler: async (args) =>
      textResult(await apiGet(`/public-api/backup/logs/${encodeURIComponent(String(args.id))}`)),
  },
  {
    name: "disk_space",
    description: "Get disk free/total space for a path.",
    minRole: 1,
    inputSchema: {
      type: "object",
      properties: { path: { type: "string", description: "Filesystem path" } },
      required: ["path"],
    },
    handler: async (args) => textResult(await apiGet("/public-api/backup/disk", { path: String(args.path) })),
  },
];
