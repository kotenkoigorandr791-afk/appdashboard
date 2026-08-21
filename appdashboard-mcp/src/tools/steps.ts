import { apiFormPost, apiGet, textResult } from "../api.js";
import type { ToolDef } from "./types.js";

const STEPS = "/public-api/steps";

function str(v: unknown): string | undefined {
  if (v === undefined || v === null || v === "") return undefined;
  return String(v);
}

function isoToCsvDate(iso: string): string {
  const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(iso);
  if (!m) return iso;
  return `${m[3]}-${m[2]}-${m[1].slice(2)}`;
}

function toCsvDate(raw: string): string {
  const s = raw.trim();
  if (/^\d{2}-\d{2}-\d{2}$/.test(s)) return s;
  if (/^\d{4}-\d{2}-\d{2}$/.test(s)) return isoToCsvDate(s);
  throw new Error(`Invalid date format: ${raw} (expected YYYY-MM-DD or dd-mm-yy)`);
}

function normalizeRange(args: Record<string, unknown>): { from: string; to: string } {
  const date = str(args.date);
  let from = str(args.from);
  let to = str(args.to);
  if (date && !from && !to) {
    from = date;
    to = date;
  }
  if (date && from && !to) to = date;
  if (date && to && !from) from = date;
  if (!from || !to) throw new Error("from and to (YYYY-MM-DD) required, or pass date");
  return { from, to };
}

async function stepsForm(fields: Record<string, string | number | boolean | undefined | null>) {
  return apiFormPost(STEPS, fields);
}

export const stepsTools: ToolDef[] = [
  {
    name: "steps_get_range",
    description: "Get daily step counts for a date range (YYYY-MM-DD). Pass from+to, or a single date.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        from: { type: "string", description: "Start date YYYY-MM-DD" },
        to: { type: "string", description: "End date YYYY-MM-DD" },
        date: { type: "string", description: "Single day YYYY-MM-DD (sets from=to)" },
      },
    },
    handler: async (args) => {
      const { from, to } = normalizeRange(args);
      return textResult(await apiGet(STEPS, { from, to }));
    },
  },
  {
    name: "steps_add",
    description: "Add or update step count for one day (upsert).",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        date: { type: "string", description: "Date YYYY-MM-DD" },
        steps: { type: "number", description: "Step count (integer >= 0)" },
      },
      required: ["date", "steps"],
    },
    handler: async (args) =>
      textResult(
        await stepsForm({
          add_steps: "1",
          date: String(args.date),
          steps: String(Math.max(0, Math.floor(Number(args.steps)))),
        }),
      ),
  },
  {
    name: "steps_import_batch",
    description:
      "Batch-import step rows. Dates may be YYYY-MM-DD or dd-mm-yy; converted to dd-mm-yy for the backend.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        rows: {
          type: "array",
          description: 'Array of {date, steps} objects',
          items: {
            type: "object",
            properties: {
              date: { type: "string" },
              steps: { type: "number" },
            },
            required: ["date", "steps"],
          },
        },
      },
      required: ["rows"],
    },
    handler: async (args) => {
      const rowsIn = Array.isArray(args.rows) ? args.rows : [];
      const rows = rowsIn.map((r) => {
        const row = (r && typeof r === "object" ? r : {}) as Record<string, unknown>;
        return {
          date: toCsvDate(String(row.date ?? "")),
          steps: Math.max(0, Math.floor(Number(row.steps))),
        };
      });
      return textResult(
        await stepsForm({
          import_video_batch: "1",
          rows: JSON.stringify(rows),
        }),
      );
    },
  },
  {
    name: "steps_get_table_page",
    description: "Paginated steps table with optional numeric/date filters.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        page: { type: "number", description: "Page number (default 1)" },
        filter_op: {
          type: "string",
          enum: ["=", ">", "<", ">=", "<=", "range"],
          description: "Numeric filter operator",
        },
        filter_val: { type: "number", description: "Filter value" },
        filter_val_to: { type: "number", description: "Upper bound when filter_op=range" },
        date_from: { type: "string", description: "YYYY-MM-DD" },
        date_to: { type: "string", description: "YYYY-MM-DD" },
      },
    },
    handler: async (args) =>
      textResult(
        await stepsForm({
          get_table_page: "1",
          page: args.page != null ? Number(args.page) : 1,
          filter_op: str(args.filter_op),
          filter_val: args.filter_val != null ? Number(args.filter_val) : undefined,
          filter_val_to: args.filter_val_to != null ? Number(args.filter_val_to) : undefined,
          date_from: str(args.date_from),
          date_to: str(args.date_to),
        }),
      ),
  },
  {
    name: "steps_get_settings",
    description: "Get user Steps settings: goal, height, weight, stride_length, birth_date.",
    minRole: 0,
    inputSchema: { type: "object", properties: {} },
    handler: async () => textResult(await apiGet(STEPS, { settings: 1 })),
  },
  {
    name: "steps_save_settings",
    description:
      "Save Steps settings. All fields optional. goal_steps is absolute daily steps (e.g. 10000).",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        goal_steps: { type: "number", description: "Daily step goal (absolute count)" },
        height: { type: "number", description: "Height in cm" },
        weight: { type: "number", description: "Weight in kg" },
        stride_length: { type: "number", description: "Stride length in cm" },
        birth_date: { type: "string", description: "Birth date YYYY-MM-DD" },
      },
    },
    handler: async (args) => {
      const fields: Record<string, string> = { settings: "1" };
      if (args.goal_steps != null) {
        const g = Number(args.goal_steps);
        if (!Number.isFinite(g) || g < 0) throw new Error("goal_steps must be a non-negative number");
        fields.goal_steps = String(g >= 100 ? g / 1000 : g);
      } else {
        fields.goal_steps = "10";
      }
      if (args.height != null) {
        const h = Number(args.height);
        if (!Number.isFinite(h) || h < 0) throw new Error("height must be a non-negative number");
        fields.height = String(Math.floor(h));
      }
      if (args.weight != null) {
        const w = Number(args.weight);
        if (!Number.isFinite(w) || w < 0) throw new Error("weight must be a non-negative number");
        fields.weight = String(Math.floor(w));
      }
      if (args.stride_length != null) {
        const s = Number(args.stride_length);
        if (!Number.isFinite(s) || s < 0) throw new Error("stride_length must be a non-negative number");
        fields.stride_length = String(Math.floor(s));
      }
      if (args.birth_date != null) fields.birth_date = String(args.birth_date);
      return textResult(await stepsForm(fields));
    },
  },
  {
    name: "steps_backup_list",
    description: "List Steps CSV backups for the current user.",
    minRole: 0,
    inputSchema: { type: "object", properties: {} },
    handler: async () => textResult(await stepsForm({ get_backups_list: "1" })),
  },
  {
    name: "steps_backup_create",
    description: "Create a Steps backup CSV now.",
    minRole: 0,
    inputSchema: { type: "object", properties: {} },
    handler: async () => textResult(await stepsForm({ create_backup: "1" })),
  },
  {
    name: "steps_backup_restore",
    description: "Restore Steps data from a backup file name returned by steps_backup_list.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: { backup_file: { type: "string", description: "Backup file name" } },
      required: ["backup_file"],
    },
    handler: async (args) =>
      textResult(await stepsForm({ restore_backup: "1", backup_file: String(args.backup_file) })),
  },
  {
    name: "steps_backup_delete",
    description: "Delete a Steps backup file.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: { backup_file: { type: "string", description: "Backup file name" } },
      required: ["backup_file"],
    },
    handler: async (args) =>
      textResult(await stepsForm({ delete_backup: "1", backup_file: String(args.backup_file) })),
  },
  {
    name: "steps_backup_download",
    description: "Download current Steps data as CSV text (no binary).",
    minRole: 0,
    inputSchema: { type: "object", properties: {} },
    handler: async () => {
      const { getHost, getToken, ensureDeviceId, refreshToken, clearToken } = await import("../auth.js");
      const { ApiError } = await import("../api.js");
      const base = getHost();
      if (!base) throw new Error("APPDASHBOARD_URL is not set");
      const token = getToken();
      const deviceId = ensureDeviceId();
      const headers: Record<string, string> = {
        "Content-Type": "application/x-www-form-urlencoded",
        "X-Device-Id": deviceId,
      };
      if (token) headers.Authorization = `Bearer ${token}`;
      const body = new URLSearchParams({ download_backup: "1" });
      let res = await fetch(`${base}${STEPS}`, { method: "POST", headers, body });
      if (res.status === 401 && token) {
        try {
          await refreshToken();
          const t2 = getToken();
          if (t2) headers.Authorization = `Bearer ${t2}`;
          res = await fetch(`${base}${STEPS}`, { method: "POST", headers, body });
        } catch {
          clearToken();
          throw new ApiError("unauthorized", 401);
        }
      }
      const ct = res.headers.get("content-type") || "";
      const text = await res.text();
      if (!res.ok) {
        try {
          const j = JSON.parse(text);
          throw new ApiError(j?.message || j?.error || `http_${res.status}`, res.status, j);
        } catch (e) {
          if (e instanceof ApiError) throw e;
          throw new ApiError(`http_${res.status}`, res.status, { raw: text });
        }
      }
      if (ct.includes("application/json")) {
        try {
          return textResult(JSON.parse(text));
        } catch {
          /* fallthrough */
        }
      }
      return textResult({
        status: "success",
        content_type: "text/csv",
        filename: `Steps_backup_${new Date().toISOString().slice(0, 10)}.csv`,
        csv: text,
      });
    },
  },
];
