import { getHost } from "../auth.js";
import { apiFormPost, apiGet, apiJsonPost, apiUpload, textResult } from "../api.js";
import type { ToolDef } from "./types.js";

const CLOUD = "/public-api/cloud";
const TRANSCRIBE = "/public-api/cloud/transcription_api.php";
const TRANSLATE = "/public-api/cloud/translate_api_simple.php";
const TTS = "/public-api/cloud/tts_api.php";

const ROOT =
  "Storage root: main (default), documents, photos, archive, temp, trash, pdf, clientsync, or group_<id>.";

function rootOf(args: Record<string, unknown>, fallback = "main"): string {
  return args.root != null && String(args.root) !== "" ? String(args.root) : fallback;
}

function pathOf(args: Record<string, unknown>): string {
  return args.path != null ? String(args.path).replace(/^\/+/, "") : "";
}

function jsonField(value: unknown): string | undefined {
  if (value === undefined || value === null) return undefined;
  return typeof value === "string" ? value : JSON.stringify(value);
}

function dirnamePath(p: string): string {
  const i = p.lastIndexOf("/");
  return i <= 0 ? "" : p.slice(0, i);
}

async function cloudGet(action: string, query: Record<string, string | number | boolean | undefined | null> = {}) {
  return apiGet(CLOUD, { action, ...query });
}

async function cloudForm(action: string, fields: Record<string, string | number | boolean | undefined | null | string[]>) {
  return apiFormPost(CLOUD, fields, { action });
}

function streamUrl(action: string, root: string, path: string): string {
  const base = (getHost() || "").replace(/\/+$/, "");
  const q = new URLSearchParams({ action, root, path });
  return `${base}${CLOUD}?${q.toString()}`;
}

const rootProp = { type: "string", description: ROOT };
const pathProp = { type: "string", description: "Path relative to root; no leading /" };

export const cloudTools: ToolDef[] = [
  {
    name: "cloud_list",
    description: "List Cloud Storage directory contents.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: { root: rootProp, path: { ...pathProp, description: "Directory path (default: root)" } },
    },
    handler: async (args) =>
      textResult(await cloudGet("list", { root: rootOf(args), path: pathOf(args) || undefined })),
  },
  {
    name: "cloud_open",
    description: "Open a Cloud file for preview/metadata (text content when available).",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: { root: rootProp, path: pathProp },
      required: ["path"],
    },
    handler: async (args) =>
      textResult(await cloudGet("open", { root: rootOf(args), path: pathOf(args) })),
  },
  {
    name: "cloud_mkdir",
    description: "Create a directory in Cloud Storage.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: { root: rootProp, path: pathProp },
      required: ["path"],
    },
    handler: async (args) =>
      textResult(await cloudForm("mkdir", { root: rootOf(args), path: pathOf(args) })),
  },
  {
    name: "cloud_rename",
    description: "Rename a Cloud file or folder (new_name is basename only).",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        root: rootProp,
        path: { type: "string", description: "Current relative path (alias: old_path)" },
        old_path: { type: "string" },
        new_name: { type: "string", description: "New basename only" },
      },
      required: ["new_name"],
    },
    handler: async (args) => {
      const oldPath = String(args.old_path ?? args.path ?? "").replace(/^\/+/, "");
      return textResult(
        await cloudForm("rename", { root: rootOf(args), old_path: oldPath, new_name: String(args.new_name) }),
      );
    },
  },
  {
    name: "cloud_delete",
    description: "Delete Cloud file(s)/folder(s). Permanent for non-trash roots.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        root: rootProp,
        path: pathProp,
        items: { type: "array", items: { type: "string" }, description: "Batch paths (optional)" },
      },
    },
    handler: async (args) => {
      const items = Array.isArray(args.items)
        ? (args.items as unknown[]).map((p) => String(p).replace(/^\/+/, ""))
        : pathOf(args)
          ? [pathOf(args)]
          : [];
      if (!items.length) return textResult({ error: "path or items required" });
      const results = [];
      for (const path of items) {
        results.push(await cloudForm("delete", { root: rootOf(args), path }));
      }
      return textResult(results.length === 1 ? results[0] : { ok: true, results });
    },
  },
  {
    name: "cloud_move",
    description: "Move a Cloud file/folder. Prefer source_path+target_dir; items[{path,dest}] also accepted.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        root: rootProp,
        source_path: { type: "string" },
        target_dir: { type: "string", description: "Destination directory (empty = root)" },
        target_root: { type: "string" },
        items: {
          type: "array",
          items: { type: "object", properties: { path: { type: "string" }, dest: { type: "string" } } },
        },
      },
    },
    handler: async (args) => {
      const root = rootOf(args);
      if (Array.isArray(args.items) && args.items.length) {
        const results = [];
        for (const raw of args.items as Array<Record<string, unknown>>) {
          const source_path = String(raw.path ?? "").replace(/^\/+/, "");
          const dest = String(raw.dest ?? "").replace(/^\/+/, "");
          results.push(
            await cloudForm("move", {
              root,
              source_path,
              target_dir: dirnamePath(dest),
              target_root: args.target_root != null ? String(args.target_root) : undefined,
            }),
          );
        }
        return textResult({ ok: true, results });
      }
      return textResult(
        await cloudForm("move", {
          root,
          source_path: String(args.source_path ?? "").replace(/^\/+/, ""),
          target_dir: args.target_dir != null ? String(args.target_dir).replace(/^\/+/, "") : "",
          target_root: args.target_root != null ? String(args.target_root) : undefined,
        }),
      );
    },
  },
  {
    name: "cloud_copy",
    description: "Copy a Cloud file/folder. Prefer source_path+target_dir; items[{path,dest}] also accepted.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        root: rootProp,
        source_path: { type: "string" },
        target_dir: { type: "string" },
        target_root: { type: "string" },
        items: {
          type: "array",
          items: { type: "object", properties: { path: { type: "string" }, dest: { type: "string" } } },
        },
      },
    },
    handler: async (args) => {
      const root = rootOf(args);
      if (Array.isArray(args.items) && args.items.length) {
        const results = [];
        for (const raw of args.items as Array<Record<string, unknown>>) {
          const source_path = String(raw.path ?? "").replace(/^\/+/, "");
          const dest = String(raw.dest ?? "").replace(/^\/+/, "");
          results.push(
            await cloudForm("copy", {
              root,
              source_path,
              target_dir: dirnamePath(dest),
              target_root: args.target_root != null ? String(args.target_root) : undefined,
            }),
          );
        }
        return textResult({ ok: true, results });
      }
      return textResult(
        await cloudForm("copy", {
          root,
          source_path: String(args.source_path ?? "").replace(/^\/+/, ""),
          target_dir: args.target_dir != null ? String(args.target_dir).replace(/^\/+/, "") : "",
          target_root: args.target_root != null ? String(args.target_root) : undefined,
        }),
      );
    },
  },
  {
    name: "cloud_create_file",
    description: "Create a new Cloud text file; optional content is written via save_file.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: { root: rootProp, path: pathProp, content: { type: "string" } },
      required: ["path"],
    },
    handler: async (args) => {
      const root = rootOf(args);
      const path = pathOf(args);
      const created = await cloudForm("create_file", { root, path });
      if (args.content != null) {
        const saved = await cloudForm("save_file", { root, path, content: String(args.content) });
        return textResult({ created, saved });
      }
      return textResult(created);
    },
  },
  {
    name: "cloud_save_file",
    description: "Save/update Cloud text file content (optional conflict fields).",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        root: rootProp,
        path: pathProp,
        content: { type: "string" },
        base_revision: { type: "string" },
        base_content: { type: "string" },
      },
      required: ["path", "content"],
    },
    handler: async (args) =>
      textResult(
        await cloudForm("save_file", {
          root: rootOf(args),
          path: pathOf(args),
          content: String(args.content),
          base_revision: args.base_revision != null ? String(args.base_revision) : undefined,
          base_content: args.base_content != null ? String(args.base_content) : undefined,
        }),
      ),
  },
  {
    name: "cloud_upload",
    description: "Upload a local file to Cloud (multipart). path is destination file path including name.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        root: rootProp,
        path: { type: "string", description: "Destination path including filename" },
        file_path: { type: "string", description: "Local filesystem path to upload" },
        init_upload: { type: "boolean", description: "If true, only init chunked upload session" },
        upload_id: { type: "string" },
        chunk_index: { type: "number" },
        chunk_total: { type: "number" },
        file_size: { type: "number" },
        file_name: { type: "string" },
      },
      required: ["path"],
    },
    handler: async (args) => {
      const fields: Record<string, string | number | boolean | undefined | null> = {
        root: rootOf(args),
        path: pathOf(args),
      };
      if (args.init_upload) {
        fields.init_upload = "true";
        return textResult(await apiUpload(CLOUD, fields, [], { action: "upload" }));
      }
      if (args.upload_id != null) fields.upload_id = String(args.upload_id);
      if (args.chunk_index != null) fields.chunk_index = Number(args.chunk_index);
      if (args.chunk_total != null) fields.chunk_total = Number(args.chunk_total);
      if (args.file_size != null) fields.file_size = Number(args.file_size);
      if (args.file_name != null) fields.file_name = String(args.file_name);
      const local = args.file_path != null ? String(args.file_path) : "";
      if (!local) return textResult({ error: "file_path required for upload" });
      return textResult(await apiUpload(CLOUD, fields, [{ field: "file", filePath: local }], { action: "upload" }));
    },
  },
  {
    name: "cloud_download",
    description: "Get authenticated download URL and file properties for a Cloud file.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: { root: rootProp, path: pathProp },
      required: ["path"],
    },
    handler: async (args) => {
      const root = rootOf(args);
      const path = pathOf(args);
      const props = await cloudGet("get_properties", { root, path }).catch(() => null);
      return textResult({
        url: streamUrl("download", root, path),
        name: path.split("/").pop(),
        root,
        path,
        auth: "Authorization: Bearer <token> + X-Device-Id",
        properties: props,
      });
    },
  },
  {
    name: "cloud_move_to_trash",
    description: "Move Cloud path(s) to trash.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        root: rootProp,
        path: pathProp,
        items: { type: "array", items: { type: "string" } },
      },
    },
    handler: async (args) => {
      const items = Array.isArray(args.items)
        ? (args.items as unknown[]).map((p) => String(p).replace(/^\/+/, ""))
        : pathOf(args)
          ? [pathOf(args)]
          : [];
      if (!items.length) return textResult({ error: "path or items required" });
      const results = [];
      for (const path of items) {
        results.push(await cloudForm("move_to_trash", { root: rootOf(args), path }));
      }
      return textResult(results.length === 1 ? results[0] : { ok: true, results });
    },
  },
  {
    name: "cloud_restore_from_trash",
    description: "Restore item(s) from trash by trash id/path.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        path: { type: "string", description: "Trash item id/path" },
        items: { type: "array", items: { type: "string" } },
      },
    },
    handler: async (args) => {
      const items = Array.isArray(args.items)
        ? (args.items as unknown[]).map(String)
        : args.path != null
          ? [String(args.path)]
          : [];
      if (!items.length) return textResult({ error: "path or items required" });
      const results = [];
      for (const path of items) {
        results.push(await cloudForm("restore_from_trash", { path }));
      }
      return textResult(results.length === 1 ? results[0] : { ok: true, results });
    },
  },
  {
    name: "cloud_empty_trash",
    description: "Permanently empty Cloud trash. DESTRUCTIVE.",
    minRole: 0,
    inputSchema: { type: "object", properties: {} },
    handler: async () => textResult(await cloudForm("empty_trash", {})),
  },
  {
    name: "cloud_share_file",
    description: "Create a public share link for a Cloud file.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        root: rootProp,
        path: pathProp,
        expires_option: { type: "string", description: "never|1h|24h|7d|30d|custom" },
        expires: { type: "number", description: "Unix timestamp when expires_option=custom" },
        password: { type: "string" },
        view_only: { type: "boolean" },
        disable_download: { type: "boolean" },
        disable_copy: { type: "boolean" },
      },
      required: ["path"],
    },
    handler: async (args) =>
      textResult(
        await cloudForm("share_file", {
          root: rootOf(args),
          path: pathOf(args),
          expires_option: args.expires_option != null ? String(args.expires_option) : undefined,
          expires: args.expires != null ? Number(args.expires) : undefined,
          password: args.password != null ? String(args.password) : undefined,
          view_only: args.view_only != null ? (args.view_only ? "1" : "0") : undefined,
          disable_download: args.disable_download != null ? (args.disable_download ? "1" : "0") : undefined,
          disable_copy: args.disable_copy != null ? (args.disable_copy ? "1" : "0") : undefined,
        }),
      ),
  },
  {
    name: "cloud_create_archive",
    description: "Create an archive from selected Cloud items.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        root: rootProp,
        items: { type: "array", items: { type: "string" } },
        archive_name: { type: "string" },
        format: { type: "string", description: "zip|tar|..." },
        password: { type: "string" },
        async: { type: "boolean" },
      },
      required: ["items"],
    },
    handler: async (args) =>
      textResult(
        await cloudForm("create_archive", {
          root: rootOf(args),
          items: jsonField(args.items),
          archive_name: args.archive_name != null ? String(args.archive_name) : undefined,
          format: args.format != null ? String(args.format) : undefined,
          password: args.password != null ? String(args.password) : undefined,
          async: args.async ? "1" : undefined,
        }),
      ),
  },
  {
    name: "cloud_extract_archive",
    description: "Extract a Cloud archive.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        root: rootProp,
        path: pathProp,
        archive_path: { type: "string", description: "Alias for path" },
        extract_to: { type: "string" },
        password: { type: "string" },
      },
      required: ["path"],
    },
    handler: async (args) =>
      textResult(
        await cloudForm("extract_archive", {
          root: rootOf(args),
          archive_path: String(args.archive_path ?? args.path ?? "").replace(/^\/+/, ""),
          extract_to: args.extract_to != null ? String(args.extract_to).replace(/^\/+/, "") : undefined,
          password: args.password != null ? String(args.password) : undefined,
        }),
      ),
  },
  {
    name: "cloud_archive_job_status",
    description: "Poll async Cloud archive job status.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: { job_id: { type: "string" } },
      required: ["job_id"],
    },
    handler: async (args) => textResult(await cloudGet("archive_job_status", { job_id: String(args.job_id) })),
  },
  {
    name: "cloud_extract_text",
    description: "Extract text/markdown from PDF or Office file in Cloud.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: { root: rootProp, path: pathProp },
      required: ["path"],
    },
    handler: async (args) =>
      textResult(await cloudGet("ai_extract_text", { root: rootOf(args), path: pathOf(args) })),
  },
  {
    name: "cloud_transcribe",
    description: "Start transcription of a Cloud audio file (path in storage). Optional local file_path uploads first.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        audio_path: { type: "string", description: "Cloud-relative audio path" },
        path: { type: "string", description: "Alias for audio_path" },
        file_path: { type: "string", description: "Local file to upload then transcribe" },
        root: rootProp,
        language: { type: "string" },
        user: { type: "string" },
      },
    },
    handler: async (args) => {
      const root = rootOf(args);
      let audioPath = String(args.audio_path ?? args.path ?? "").replace(/^\/+/, "");
      if (!audioPath && args.file_path) {
        const local = String(args.file_path);
        const name = local.split(/[/\\]/).pop() || `audio_${Date.now()}`;
        audioPath = `temp/${name}`;
        await apiUpload(
          CLOUD,
          { root, path: audioPath },
          [{ field: "file", filePath: local }],
          { action: "upload" },
        );
      }
      if (!audioPath) return textResult({ error: "audio_path or file_path required" });
      let user = args.user != null ? String(args.user) : "";
      if (!user) {
        try {
          const state = await cloudGet("get_state");
          user = String(state?.active || state?.active_email || "default");
        } catch {
          user = "default";
        }
      }
      return textResult(
        await apiFormPost(TRANSCRIBE, {
          action: "start_transcription",
          audio_path: audioPath,
          root,
          language: args.language != null ? String(args.language) : "en",
          user,
        }),
      );
    },
  },
  {
    name: "cloud_translate",
    description: "Translate text to one or more languages.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        text: { type: "string" },
        source_lang: { type: "string" },
        target_langs: { type: "array", items: { type: "string" } },
      },
      required: ["text", "source_lang"],
    },
    handler: async (args) =>
      textResult(
        await apiJsonPost(TRANSLATE, {
          text: String(args.text),
          source_lang: String(args.source_lang),
          target_langs: Array.isArray(args.target_langs) ? args.target_langs : ["en"],
        }),
      ),
  },
  {
    name: "cloud_tts",
    description: "Text-to-speech synthesis (starts async job; returns progress_id).",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        text: { type: "string" },
        lang: { type: "string", description: "Alias for language" },
        language: { type: "string" },
        speed: { type: "number" },
        text_path: { type: "string" },
        user: { type: "string" },
      },
      required: ["text"],
    },
    handler: async (args) =>
      textResult(
        await apiFormPost(TTS, {
          action: "generate_tts",
          text: String(args.text),
          language: String(args.language ?? args.lang ?? "en"),
          speed: args.speed != null ? Number(args.speed) : 1.0,
          text_path: args.text_path != null ? String(args.text_path) : "",
          user: args.user != null ? String(args.user) : "default",
        }),
      ),
  },
  {
    name: "cloud_get_state",
    description: "Get Cloud user state (quota, settings, folder list).",
    minRole: 0,
    inputSchema: { type: "object", properties: {} },
    handler: async () => textResult(await cloudGet("get_state")),
  },
  {
    name: "cloud_get_storage_usage",
    description: "Get Cloud storage usage stats.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: { refresh: { type: "boolean", description: "Force refresh" } },
    },
    handler: async (args) =>
      textResult(await cloudGet(args.refresh ? "refresh_storage_usage" : "get_storage_usage")),
  },
];
