import { apiPost, textResult } from "../api.js";
import type { ToolDef } from "./types.js";

const SH = "/public-api/smart_home";

async function shApi(action: string, body: Record<string, unknown> = {}) {
  return apiPost(SH, body, { api: action });
}

function stripSecrets(data: unknown): unknown {
  if (Array.isArray(data)) return data.map(stripSecrets);
  if (data && typeof data === "object") {
    const out: Record<string, unknown> = {};
    for (const [k, v] of Object.entries(data as Record<string, unknown>)) {
      if (k === "device_token" || k === "api_docs") continue;
      out[k] = stripSecrets(v);
    }
    return out;
  }
  return data;
}

async function shResult(action: string, body: Record<string, unknown> = {}) {
  return textResult(stripSecrets(await shApi(action, body)));
}

export const smarthomeTools: ToolDef[] = [
  {
    name: "smarthome_state",
    description:
      "Get full Smart Home dashboard state: settings, devices, my_devices, groups, floor_plan, home_scope, and permissions.",
    minRole: 0,
    inputSchema: { type: "object", properties: {} },
    handler: async () => shResult("state"),
  },
  {
    name: "smarthome_control",
    description:
      "Send a control command to a device (switch=boolean, range=integer, select=string). May return queued:true if offline.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        device_id: { type: "string" },
        control_id: { type: "string" },
        value: { description: "Control value (boolean | number | string)" },
      },
      required: ["device_id", "control_id", "value"],
    },
    handler: async (args) =>
      shResult("control", {
        device_id: String(args.device_id),
        control_id: String(args.control_id),
        value: args.value,
      }),
  },
];
