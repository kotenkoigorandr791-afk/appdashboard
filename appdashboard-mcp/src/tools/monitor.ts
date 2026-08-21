import { apiGet, shellAction, textResult } from "../api.js";
import type { ToolDef } from "./types.js";

export const monitorTools: ToolDef[] = [
  {
    name: "server_monitor",
    description: "Get server CPU, RAM, and disk metrics (admin).",
    minRole: 2,
    inputSchema: { type: "object", properties: {} },
    handler: async () => textResult(await apiGet("/public-api/performance/monitor")),
  },
  {
    name: "list_alert_rules",
    description: "List monitor alert rules (admin).",
    minRole: 2,
    inputSchema: { type: "object", properties: {} },
    handler: async () => textResult(await shellAction("list_alert_rules")),
  },
  {
    name: "create_alert_rule",
    description: "Create a monitor alert rule (admin).",
    minRole: 2,
    inputSchema: {
      type: "object",
      properties: {
        name: { type: "string" },
        metric: { type: "string" },
        operator: { type: "string" },
        threshold: { type: "number" },
        enabled: { type: "boolean" },
      },
      required: ["name", "metric", "operator", "threshold"],
    },
    handler: async (args) => textResult(await shellAction("create_alert_rule", { ...args })),
  },
  {
    name: "delete_alert_rule",
    description: "Delete a monitor alert rule (admin).",
    minRole: 2,
    inputSchema: {
      type: "object",
      properties: { id: { type: "string" } },
      required: ["id"],
    },
    handler: async (args) => textResult(await shellAction("delete_alert_rule", { id: args.id })),
  },
  {
    name: "list_alerts",
    description: "List triggered monitor alerts (admin).",
    minRole: 2,
    inputSchema: { type: "object", properties: {} },
    handler: async () => textResult(await shellAction("list_alerts")),
  },
  {
    name: "get_metrics_hourly",
    description: "Get hourly monitor metrics (admin).",
    minRole: 2,
    inputSchema: {
      type: "object",
      properties: {
        metric: { type: "string" },
        hours: { type: "number" },
      },
    },
    handler: async (args) => {
      const params: Record<string, unknown> = {};
      if (args.metric != null && String(args.metric) !== "") params.metric = String(args.metric);
      if (args.hours != null && Number(args.hours) > 0) {
        params.since = Math.floor(Date.now() / 1000) - Math.floor(Number(args.hours) * 3600);
      } else if (args.since != null) {
        params.since = Number(args.since);
      }
      return textResult(await shellAction("get_metrics_hourly", params));
    },
  },
];
