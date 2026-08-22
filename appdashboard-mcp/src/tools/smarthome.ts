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

function str(args: Record<string, unknown>, key: string): string | undefined {
  const v = args[key];
  if (v === undefined || v === null || v === "") return undefined;
  return String(v);
}

function memberEmail(args: Record<string, unknown>): string {
  return String(args.member_email ?? args.email ?? "");
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
    name: "smarthome_save_device",
    description:
      "Create or update a Smart Home device. Create requires device_token (secret — never logged/returned). Update requires id.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        id: { type: "string", description: "Device id (required for update)" },
        device_token: { type: "string", description: "Device auth token (required for create)" },
        name: { type: "string" },
        type: {
          type: "string",
          description: "smart_torch | sonoff_relay | generic_sensor | pending (or custom)",
        },
        room: { type: "string" },
        icon: { type: "string" },
        category: { type: "string" },
        notes: { type: "string" },
        local_url: { type: "string" },
        server_domain: { type: "string" },
        ap_domain: { type: "string" },
        mqtt_topic: { type: "string" },
        status: { type: "string" },
        icon_locked: { type: "boolean" },
        group_id: { type: "string" },
      },
    },
    handler: async (args) => {
      const body: Record<string, unknown> = {};
      for (const k of [
        "id",
        "device_token",
        "name",
        "type",
        "room",
        "icon",
        "category",
        "notes",
        "local_url",
        "server_domain",
        "ap_domain",
        "mqtt_topic",
        "status",
        "group_id",
      ]) {
        if (args[k] !== undefined && args[k] !== null && args[k] !== "") body[k] = args[k];
      }
      if (args.icon_locked !== undefined) body.icon_locked = Boolean(args.icon_locked);
      return shResult("save_device", body);
    },
  },
  {
    name: "smarthome_delete_device",
    description: "Delete a Smart Home device owned by the current user.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: { device_id: { type: "string" } },
      required: ["device_id"],
    },
    handler: async (args) => shResult("delete_device", { device_id: String(args.device_id) }),
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
  {
    name: "smarthome_save_settings",
    description:
      "Save Smart Home settings (home_scope own|group, home_view tiles|map, optional domains if can_edit_endpoints).",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        server_domain: { type: "string" },
        ap_domain: { type: "string" },
        home_scope: { type: "string", description: "own | group" },
        home_group_id: { type: "string" },
        home_view: { type: "string", description: "tiles | map" },
      },
    },
    handler: async (args) => {
      const body: Record<string, unknown> = {};
      for (const k of ["server_domain", "ap_domain", "home_scope", "home_group_id", "home_view"]) {
        if (args[k] !== undefined && args[k] !== null) body[k] = String(args[k]);
      }
      return shResult("save_settings", body);
    },
  },
  {
    name: "smarthome_save_floor_plan",
    description: "Save floor plan elements (requires can_edit_map). Elements are freeform JSON (room/device/etc).",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        elements: {
          type: "array",
          items: { type: "object" },
          description: "Floor plan elements, e.g. {type,x,y,width,height,label} or {type:device,device_id,x,y}",
        },
      },
      required: ["elements"],
    },
    handler: async (args) => shResult("save_floor_plan", { elements: args.elements }),
  },
  {
    name: "smarthome_update_displayed_controls",
    description: "Choose which device controls are shown on the dashboard.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        device_id: { type: "string" },
        displayed_controls: { type: "array", items: { type: "string" } },
      },
      required: ["device_id", "displayed_controls"],
    },
    handler: async (args) =>
      shResult("update_displayed_controls", {
        device_id: String(args.device_id),
        displayed_controls: args.displayed_controls,
      }),
  },
  {
    name: "smarthome_update_displayed_params",
    description: "Choose which device params are shown on the dashboard.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        device_id: { type: "string" },
        displayed_params: { type: "array", items: { type: "string" } },
      },
      required: ["device_id", "displayed_params"],
    },
    handler: async (args) =>
      shResult("update_displayed_params", {
        device_id: String(args.device_id),
        displayed_params: args.displayed_params,
      }),
  },
  {
    name: "smarthome_device_sync",
    description:
      "Device-side sync via device_token (updates controls/telemetry/status; optional definition for pending devices). Token is not returned.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        device_token: { type: "string" },
        controls: { type: "object", description: "Map of control_id → value" },
        telemetry: { type: "object", description: "Map of param_id → value" },
        status: { type: "string", description: "online | offline | unknown" },
        local_url: { type: "string" },
        name: { type: "string" },
        definition: { type: "object", description: "Optional type/icon/controls/params definition" },
        events: { type: "array", items: { type: "object" } },
      },
      required: ["device_token"],
    },
    handler: async (args) => {
      const body: Record<string, unknown> = { device_token: String(args.device_token) };
      for (const k of ["controls", "telemetry", "status", "local_url", "name", "definition", "events"]) {
        if (args[k] !== undefined && args[k] !== null) body[k] = args[k];
      }
      return shResult("device_sync", body);
    },
  },
  {
    name: "smarthome_issue_ws_token",
    description:
      "Issue a short-lived WebSocket session token (300s) and ws_url for dashboard auth. MCP does not keep WS connections.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: { device_id: { type: "string" } },
      required: ["device_id"],
    },
    handler: async (args) => shResult("issue_ws_token", { device_id: String(args.device_id) }),
  },
  {
    name: "smarthome_create_group",
    description: "Create a home group; creator becomes admin.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: { name: { type: "string" } },
      required: ["name"],
    },
    handler: async (args) => shResult("create_group", { name: String(args.name) }),
  },
  {
    name: "smarthome_delete_group",
    description: "Delete a home group (owner only).",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: { group_id: { type: "string" } },
      required: ["group_id"],
    },
    handler: async (args) => shResult("delete_group", { group_id: String(args.group_id) }),
  },
  {
    name: "smarthome_group_members",
    description: "List members of a home group.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: { group_id: { type: "string" } },
      required: ["group_id"],
    },
    handler: async (args) => shResult("group_members", { group_id: String(args.group_id) }),
  },
  {
    name: "smarthome_add_member",
    description: "Add a member to a group (admin only). role: guest | admin.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        group_id: { type: "string" },
        member_email: { type: "string", description: "Alias: email" },
        email: { type: "string" },
        role: { type: "string", description: "guest | admin" },
      },
      required: ["group_id"],
    },
    handler: async (args) =>
      shResult("add_member", {
        group_id: String(args.group_id),
        member_email: memberEmail(args),
        role: str(args, "role") || "guest",
      }),
  },
  {
    name: "smarthome_remove_member",
    description: "Remove a member from a group (admin only).",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        group_id: { type: "string" },
        member_email: { type: "string", description: "Alias: email" },
        email: { type: "string" },
      },
      required: ["group_id"],
    },
    handler: async (args) =>
      shResult("remove_member", {
        group_id: String(args.group_id),
        member_email: memberEmail(args),
      }),
  },
  {
    name: "smarthome_update_member_role",
    description: "Update a group member role (admin only). role: guest | admin.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        group_id: { type: "string" },
        member_email: { type: "string", description: "Alias: email" },
        email: { type: "string" },
        role: { type: "string", description: "guest | admin" },
      },
      required: ["group_id", "role"],
    },
    handler: async (args) =>
      shResult("update_member_role", {
        group_id: String(args.group_id),
        member_email: memberEmail(args),
        role: String(args.role),
      }),
  },
  {
    name: "smarthome_monitor_stats",
    description: "Get device stats, monitor events, and param history for a period (hours).",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: { period: { type: "number", description: "Hours (default 24)" } },
    },
    handler: async (args) =>
      shResult("monitor_stats", { period: args.period != null ? Number(args.period) : 24 }),
  },
  {
    name: "smarthome_monitor_errors",
    description: "Get Smart Home error logs for a period (hours, default 168, max 720).",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: { period: { type: "number", description: "Hours (default 168)" } },
    },
    handler: async (args) =>
      shResult("monitor_errors", { period: args.period != null ? Number(args.period) : 168 }),
  },
  {
    name: "smarthome_record_param_history",
    description: "Record parameter value snapshots for a device.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        device_id: { type: "string" },
        params: {
          type: "array",
          items: {
            type: "object",
            properties: {
              id: { type: "string" },
              label: { type: "string" },
              value: { type: "string" },
            },
          },
        },
      },
      required: ["device_id", "params"],
    },
    handler: async (args) =>
      shResult("record_param_history", {
        device_id: String(args.device_id),
        params: args.params,
      }),
  },
  {
    name: "smarthome_route_maps",
    description: "List route map HTML files stored on a device (via WS tunnel). Returns HTML.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: { device_id: { type: "string" } },
      required: ["device_id"],
    },
    handler: async (args) => shResult("route_maps", { device_id: String(args.device_id) }),
  },
  {
    name: "smarthome_route_map_file",
    description: "Get content of a route map HTML file from a device.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        device_id: { type: "string" },
        name: { type: "string", description: "Filename ending in .html" },
      },
      required: ["device_id", "name"],
    },
    handler: async (args) =>
      shResult("route_map_file", { device_id: String(args.device_id), name: String(args.name) }),
  },
  {
    name: "smarthome_route_delete",
    description: "Delete a route map HTML file on a device.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        device_id: { type: "string" },
        name: { type: "string" },
      },
      required: ["device_id", "name"],
    },
    handler: async (args) =>
      shResult("route_delete", { device_id: String(args.device_id), name: String(args.name) }),
  },
  {
    name: "smarthome_route_rename",
    description: "Rename a route map HTML file on a device.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        device_id: { type: "string" },
        old_name: { type: "string", description: "Alias: name" },
        name: { type: "string" },
        new_name: { type: "string" },
      },
      required: ["device_id", "new_name"],
    },
    handler: async (args) =>
      shResult("route_rename", {
        device_id: String(args.device_id),
        old_name: String(args.old_name ?? args.name ?? ""),
        new_name: String(args.new_name),
      }),
  },
];
