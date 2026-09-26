import { apiFormPost, apiGet, apiPost, apiUpload, textResult } from "../api.js";
import type { ToolDef } from "./types.js";

const SPENDS = "/public-api/spends";
const OCR = "/public-api/spends/utils/expense_ocr_app.php";

let ratesCache: { key: string; at: number; data: unknown } | null = null;
const RATES_TTL_MS = 60 * 60 * 1000;

function extractStores(yearData: any): string[] {
  const set = new Set<string>();
  const expenses = yearData?.expenses ?? {};
  for (const day of Object.values(expenses) as Array<Record<string, unknown>>) {
    if (day && typeof day === "object") {
      for (const store of Object.keys(day)) set.add(store);
    }
  }
  return [...set].sort();
}

export const spendsTools: ToolDef[] = [
  {
    name: "get_expenses_page",
    description: "Get paginated expenses table with search, sort, and date filter.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        page: { type: "number", description: "Page number (default 1)" },
        per_page: { type: "number", description: "Rows per page (default 25)" },
        search: { type: "string" },
        sort: { type: "string", description: "date_asc or date_desc" },
        start_date: { type: "string", description: "YYYY-MM-DD" },
      },
    },
    handler: async (args) =>
      textResult(
        await apiGet(SPENDS, {
          get_expenses_page: 1,
          page: (args.page as number) ?? 1,
          per_page: (args.per_page as number) ?? 25,
          search: args.search != null ? String(args.search) : undefined,
          sort: args.sort != null ? String(args.sort) : "date_asc",
          start_date: args.start_date != null ? String(args.start_date) : undefined,
        }),
      ),
  },
  {
    name: "get_year_data",
    description: "Get all expenses and incomes for a specific year (used for charts and analytics).",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: { year: { type: "number", description: "Year (2000-2100)" } },
      required: ["year"],
    },
    handler: async (args) => textResult(await apiGet(SPENDS, { year_data: args.year as number })),
  },
  {
    name: "get_expense_meta",
    description: "Get metadata for a specific expense entry (source: manual/ocr, raw store name).",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        date: { type: "string", description: "YYYY-MM-DD" },
        store: { type: "string" },
      },
      required: ["date", "store"],
    },
    handler: async (args) =>
      textResult(
        await apiGet(SPENDS, {
          get_expense_meta: 1,
          date: String(args.date),
          store: String(args.store),
        }),
      ),
  },
  {
    name: "update_expense",
    description:
      "Add or update an expense entry (upsert by date+store). Store must exist — create with create_spends_store first if needed.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        date: { type: "string", description: "YYYY-MM-DD" },
        store: { type: "string" },
        amount: { type: "number" },
      },
      required: ["date", "store", "amount"],
    },
    handler: async (args) =>
      textResult(
        await apiFormPost(SPENDS, {
          update_expense: "1",
          date: String(args.date),
          store: String(args.store),
          amount: String(args.amount),
        }),
      ),
  },
  {
    name: "update_income",
    description: "Add or update an income entry (upsert by date).",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        date: { type: "string", description: "YYYY-MM-DD" },
        amount: { type: "number" },
      },
      required: ["date", "amount"],
    },
    handler: async (args) =>
      textResult(
        await apiFormPost(SPENDS, {
          update_income: "1",
          date: String(args.date),
          amount: String(args.amount),
        }),
      ),
  },
  {
    name: "get_spends_stores",
    description: "List spending stores/categories for a year (unique store keys from year data).",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: { year: { type: "number", description: "Year (default: current)" } },
    },
    handler: async (args) => {
      const year = (args.year as number) || new Date().getFullYear();
      const data = await apiGet(SPENDS, { year_data: year });
      return textResult({ stores: extractStores(data) });
    },
  },
  {
    name: "create_spends_store",
    description: "Create a new spending store/category. Creates zero-amount expense rows for existing dates.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: { store_name: { type: "string" } },
      required: ["store_name"],
    },
    handler: async (args) =>
      textResult(
        await apiFormPost(SPENDS, {
          add_edit_store: "1",
          store_id: String(Date.now()),
          store_name: String(args.store_name),
        }),
      ),
  },
  {
    name: "update_spends_store",
    description: "Rename an existing store.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        store_id: { type: "string" },
        store_name: { type: "string" },
      },
      required: ["store_id", "store_name"],
    },
    handler: async (args) =>
      textResult(
        await apiFormPost(SPENDS, {
          add_edit_store: "1",
          store_id: String(args.store_id),
          store_name: String(args.store_name),
        }),
      ),
  },
  {
    name: "delete_spends_store",
    description: "Delete a store and ALL its expenses. DESTRUCTIVE.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        store_id: { type: "string" },
        store_name: { type: "string" },
      },
      required: ["store_id", "store_name"],
    },
    handler: async (args) =>
      textResult(
        await apiFormPost(SPENDS, {
          delete_store: "1",
          store_id: String(args.store_id),
          store_name: String(args.store_name),
        }),
      ),
  },
  {
    name: "add_spends_date",
    description: "Add a date entry. Creates zero-amount rows for all stores.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: { date: { type: "string", description: "YYYY-MM-DD" } },
      required: ["date"],
    },
    handler: async (args) =>
      textResult(await apiFormPost(SPENDS, { add_date_ajax: "1", date: String(args.date) })),
  },
  {
    name: "get_available_years",
    description: "Get list of years that have expense or income data.",
    minRole: 0,
    inputSchema: { type: "object", properties: {} },
    handler: async () => textResult(await apiGet(SPENDS, { get_available_years: 1 })),
  },
  {
    name: "get_spends_settings",
    description: "Get current spending settings.",
    minRole: 0,
    inputSchema: { type: "object", properties: {} },
    handler: async () => textResult(await apiGet(SPENDS, { get_settings: 1 })),
  },
  {
    name: "save_spends_settings",
    description: "Save spending settings.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        monthly_budget: { type: "number" },
        currency: { type: "string" },
        show_incomes: { type: "boolean" },
        show_trend: { type: "boolean" },
        trim_empty_months: { type: "boolean" },
      },
    },
    handler: async (args) => {
      const fields: Record<string, string> = { settings: "1" };
      if (args.monthly_budget != null) fields.monthly_budget = String(args.monthly_budget);
      if (args.currency != null) fields.currency = String(args.currency);
      if (args.show_incomes) fields.show_incomes = "1";
      if (args.show_trend) fields.show_trend = "1";
      if (args.trim_empty_months) fields.trim_empty_months = "1";
      return textResult(await apiFormPost(SPENDS, fields));
    },
  },
  {
    name: "bank_csv_analyze",
    description:
      "Analyze a bank CSV statement (delimiter, columns, merchant groups, duplicates). Two-phase: analyze → review → bank_csv_apply.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: { file_path: { type: "string", description: "Local path to CSV file" } },
      required: ["file_path"],
    },
    handler: async (args) => {
      const filePath = args.file_path != null ? String(args.file_path).trim() : "";
      if (!filePath) return textResult({ error: "file_path required" });
      return textResult(
        await apiUpload(SPENDS, { bank_csv_analyze: "1" }, [{ field: "bank_csv_file", filePath }]),
      );
    },
  },
  {
    name: "bank_csv_apply",
    description:
      "Apply analyzed bank CSV data. Requires token from bank_csv_analyze. Maps merchant groups to stores.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        token: { type: "string" },
        exclude_group_keys: { type: "array", items: { type: "string" } },
        store_map: { type: "object", additionalProperties: { type: "string" } },
        conflict_mode: { type: "string", description: "skip or replace" },
        conflict_dates: { type: "array", items: { type: "string" } },
      },
      required: ["token"],
    },
    handler: async (args) =>
      textResult(
        await apiFormPost(SPENDS, {
          bank_csv_apply: JSON.stringify({
            token: String(args.token),
            exclude_group_keys: Array.isArray(args.exclude_group_keys) ? args.exclude_group_keys : [],
            store_map: args.store_map && typeof args.store_map === "object" ? args.store_map : {},
            conflict_mode: args.conflict_mode === "replace" ? "replace" : "skip",
            conflict_dates: Array.isArray(args.conflict_dates) ? args.conflict_dates : [],
          }),
        }),
      ),
  },
  {
    name: "ocr_start",
    description: "Start OCR processing for receipt images (multipart photos[]).",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        photos: { type: "array", items: { type: "string" }, description: "Local image paths" },
      },
      required: ["photos"],
    },
    handler: async (args) => {
      const photos = Array.isArray(args.photos) ? (args.photos as string[]) : [];
      return textResult(
        await apiUpload(
          OCR,
          {},
          photos.map((p) => ({ field: "photos[]", filePath: p })),
          { api: "start" },
        ),
      );
    },
  },
  {
    name: "ocr_status",
    description: "Get OCR job status and results.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: { job_id: { type: "string" } },
      required: ["job_id"],
    },
    handler: async (args) =>
      textResult(await apiGet(OCR, { api: "status", job_id: String(args.job_id) })),
  },
  {
    name: "ocr_active_job",
    description: "Get the currently active OCR job ID.",
    minRole: 0,
    inputSchema: { type: "object", properties: {} },
    handler: async () => textResult(await apiGet(OCR, { api: "active_job" })),
  },
  {
    name: "ocr_resolve",
    description: "Map unresolved OCR merchant names to existing stores.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        job_id: { type: "string" },
        map: { type: "object", additionalProperties: { type: "string" } },
        skip: { type: "array", items: { type: "string" } },
      },
      required: ["job_id"],
    },
    handler: async (args) =>
      textResult(
        await apiPost(
          OCR,
          {
            job_id: String(args.job_id),
            map: args.map && typeof args.map === "object" ? args.map : {},
            skip: Array.isArray(args.skip) ? args.skip : [],
          },
          { api: "resolve" },
        ),
      ),
  },
  {
    name: "ocr_finalize",
    description: "Finalize OCR job and clean up temp files.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: { job_id: { type: "string" } },
      required: ["job_id"],
    },
    handler: async (args) =>
      textResult(await apiPost(OCR, { job_id: String(args.job_id) }, { api: "finalize" })),
  },
  {
    name: "ocr_create_store",
    description: "Create a store from OCR endpoint.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: { name: { type: "string" } },
      required: ["name"],
    },
    handler: async (args) =>
      textResult(await apiPost(OCR, { name: String(args.name) }, { api: "create_store" })),
  },
  {
    name: "ocr_stores",
    description: "Get store list for OCR mapping.",
    minRole: 0,
    inputSchema: { type: "object", properties: {} },
    handler: async () => textResult(await apiGet(OCR, { api: "stores" })),
  },
  {
    name: "ocr_stats",
    description: "Get OCR analytics — expenses grouped by store and month.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        store: { type: "string", description: "Store name or __all__" },
        year: { type: "number" },
      },
    },
    handler: async (args) =>
      textResult(
        await apiGet(SPENDS, {
          get_ocr_stats: 1,
          store: args.store != null ? String(args.store) : "__all__",
          year: args.year as number | undefined,
        }),
      ),
  },
  {
    name: "get_exchange_rates",
    description: "Get current exchange rates (cached in-memory for 1 hour).",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: { base: { type: "string", description: "Base currency (default USD)" } },
    },
    handler: async (args) => {
      const base = String(args.base ?? "USD");
      const now = Date.now();
      if (ratesCache && ratesCache.key === base && now - ratesCache.at < RATES_TTL_MS) {
        return textResult(ratesCache.data);
      }
      const data = await apiGet(SPENDS, { get_exchange_rates: 1, base });
      ratesCache = { key: base, at: now, data };
      return textResult(data);
    },
  },
];
