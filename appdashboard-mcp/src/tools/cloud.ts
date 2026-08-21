import { getHost } from "../auth.js";
import { apiFormPost, apiGet, apiJsonPost, apiUpload, textResult } from "../api.js";
import type { ToolDef } from "./types.js";

const CLOUD = "/public-api/cloud";
const COMPILE = "/public-api/cloud/compile.php";
const FORMAT = "/public-api/cloud/format_code.php";
const DEFORMAT = "/public-api/cloud/de_format_code.php";
const REMOVE_COMMENTS = "/public-api/cloud/remove_comments.php";
const TRANSCRIBE = "/public-api/cloud/transcription_api.php";
const TRANSLATE = "/public-api/cloud/translate_api_simple.php";
const TTS = "/public-api/cloud/tts_api.php";
const AI = "/public-api/cloud/ai.php";

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

async function agentTool(tool: string, args: Record<string, unknown>, root?: string) {
  return cloudForm("ai_agent_tool", {
    tool,
    args: JSON.stringify(args),
    root: root || undefined,
  });
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
    name: "cloud_get_properties",
    description: "Get Cloud file properties.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: { root: rootProp, path: pathProp },
      required: ["path"],
    },
    handler: async (args) =>
      textResult(await cloudGet("get_properties", { root: rootOf(args), path: pathOf(args) })),
  },
  {
    name: "cloud_get_folder_properties",
    description: "Get Cloud folder properties (recursive size/count).",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: { root: rootProp, path: pathProp },
      required: ["path"],
    },
    handler: async (args) =>
      textResult(await cloudGet("get_folder_properties", { root: rootOf(args), path: pathOf(args) })),
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
    name: "cloud_convert_file",
    description: "Convert a Cloud Office/document file to another format (LibreOffice).",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        root: rootProp,
        source_path: { type: "string" },
        target_format: { type: "string" },
        output_path: { type: "string" },
        delete_original: { type: "boolean" },
      },
      required: ["source_path", "target_format"],
    },
    handler: async (args) => {
      const root = rootOf(args);
      const sourcePath = String(args.source_path).replace(/^\/+/, "");
      const targetFormat = String(args.target_format).toLowerCase();
      let outputPath =
        args.output_path != null && String(args.output_path) !== ""
          ? String(args.output_path).replace(/^\/+/, "")
          : undefined;
      if (!outputPath) {
        const base = sourcePath.includes("/") ? sourcePath.slice(0, sourcePath.lastIndexOf("/") + 1) : "";
        const stem = sourcePath.split("/").pop()?.replace(/\.[^.]+$/, "") || "converted";
        outputPath = `${base}${stem}.${targetFormat}`;
      }
      return textResult(
        await cloudForm("convert_file", {
          root,
          source_path: sourcePath,
          target_format: targetFormat,
          output_path: outputPath,
          delete_original: args.delete_original ? "1" : undefined,
        }),
      );
    },
  },
  {
    name: "cloud_view_word",
    description: "Preview Word document metadata/HTML.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: { root: rootProp, path: pathProp },
      required: ["path"],
    },
    handler: async (args) =>
      textResult(await cloudGet("view_word", { root: rootOf(args), path: pathOf(args) })),
  },
  {
    name: "cloud_view_excel",
    description: "Preview Excel spreadsheet data.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: { root: rootProp, path: pathProp, sheet: { type: "number" } },
      required: ["path"],
    },
    handler: async (args) =>
      textResult(
        await cloudGet("view_excel", {
          root: rootOf(args),
          path: pathOf(args),
          sheet: args.sheet != null ? Number(args.sheet) : undefined,
        }),
      ),
  },
  {
    name: "cloud_view_presentation",
    description: "Preview PowerPoint presentation.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: { root: rootProp, path: pathProp },
      required: ["path"],
    },
    handler: async (args) =>
      textResult(await cloudGet("view_presentation", { root: rootOf(args), path: pathOf(args) })),
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
    name: "cloud_pdf_to_images",
    description: "Get PDF preview/stream info (pages as images when available).",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: { root: rootProp, path: pathProp },
      required: ["path"],
    },
    handler: async (args) => {
      const root = rootOf(args);
      const path = pathOf(args);
      return textResult(await cloudForm("pdf_to_images", { root, path }));
    },
  },
  {
    name: "cloud_export_pdf",
    description: "Export markdown/code file to PDF.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: { root: rootProp, path: pathProp },
      required: ["path"],
    },
    handler: async (args) =>
      textResult(await cloudForm("export_pdf", { root: rootOf(args), path: pathOf(args) })),
  },
  {
    name: "cloud_compile_and_run",
    description:
      "Compile and run Cloud code via compile.php (languages: c, cpp, py/python, java, m). Requires a virtual file_path.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        language: { type: "string", description: "c|cpp|py|python|java|m" },
        code: { type: "string", description: "Source code (alias: file_content)" },
        file_content: { type: "string" },
        file_path: { type: "string", description: "Logical path/name for the source file" },
        input: { type: "string", description: "stdin (alias: program_input)" },
        program_input: { type: "string" },
        root: rootProp,
      },
      required: ["language"],
    },
    handler: async (args) => {
      let language = String(args.language).toLowerCase();
      if (language === "python" || language === "python3") language = "py";
      if (language === "c++") language = "cpp";
      const fileContent = String(args.file_content ?? args.code ?? "");
      const ext =
        language === "c"
          ? "c"
          : language === "cpp"
            ? "cpp"
            : language === "java"
              ? "java"
              : language === "m"
                ? "m"
                : "py";
      const filePath =
        args.file_path != null && String(args.file_path)
          ? String(args.file_path).replace(/^\/+/, "")
          : `temp/mcp_compile_${Date.now()}.${ext}`;
      return textResult(
        await apiFormPost(COMPILE, {
          action: "compile_run",
          language,
          file_content: fileContent,
          file_path: filePath,
          root: rootOf(args),
          program_input: String(args.program_input ?? args.input ?? ""),
        }),
      );
    },
  },
  {
    name: "cloud_format_code",
    description: "Format/prettify code (php, html, c, c++, javascript, python, powershell).",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: { code: { type: "string" }, language: { type: "string" } },
      required: ["code", "language"],
    },
    handler: async (args) =>
      textResult(
        await apiFormPost(FORMAT, {
          action: "format",
          code: String(args.code),
          language: String(args.language),
        }),
      ),
  },
  {
    name: "cloud_de_format_code",
    description: "Compact/minify code.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: { code: { type: "string" }, language: { type: "string" } },
      required: ["code", "language"],
    },
    handler: async (args) =>
      textResult(
        await apiFormPost(DEFORMAT, {
          action: "compact",
          code: String(args.code),
          language: String(args.language),
        }),
      ),
  },
  {
    name: "cloud_remove_comments",
    description: "Remove comments from code.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: { code: { type: "string" }, language: { type: "string" } },
      required: ["code", "language"],
    },
    handler: async (args) =>
      textResult(
        await apiFormPost(REMOVE_COMMENTS, {
          action: "remove_comments",
          code: String(args.code),
          language: String(args.language),
        }),
      ),
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
    name: "cloud_agent_chat",
    description: "Cloud AI chat (completions proxy) with optional file context path.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        messages: {
          type: "array",
          items: {
            type: "object",
            properties: { role: { type: "string" }, content: { type: "string" } },
            required: ["role", "content"],
          },
        },
        message: { type: "string", description: "Shortcut single user message" },
        file_path: { type: "string" },
        root: rootProp,
        model: { type: "string" },
        stream: { type: "boolean" },
      },
    },
    handler: async (args) => {
      let messages = Array.isArray(args.messages) ? (args.messages as Array<{ role: string; content: string }>) : [];
      if (!messages.length && args.message != null) {
        messages = [{ role: "user", content: String(args.message) }];
      }
      if (args.file_path) {
        const read = await agentTool(
          "read_file",
          { path: String(args.file_path).replace(/^\/+/, "") },
          rootOf(args),
        );
        const content = typeof read?.content === "string" ? read.content : JSON.stringify(read);
        messages = [
          { role: "system", content: `File context (${args.file_path}):\n${content}` },
          ...messages,
        ];
      }
      return textResult(
        await apiJsonPost(
          AI,
          {
            messages,
            model: String(args.model ?? "github/gpt-4o-mini"),
            stream: args.stream === true,
          },
          { action: "ai_completions" },
        ),
      );
    },
  },
  {
    name: "cloud_agent_completion",
    description: "AI code completion for a snippet.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        code: { type: "string" },
        language: { type: "string" },
        file_path: { type: "string" },
        root: rootProp,
        model: { type: "string" },
      },
      required: ["code"],
    },
    handler: async (args) => {
      const language = args.language != null ? String(args.language) : "text";
      return textResult(
        await apiJsonPost(
          AI,
          {
            stream: false,
            model: String(args.model ?? "github/gpt-4o-mini"),
            messages: [
              {
                role: "system",
                content: `Complete the following ${language} code. Return only the completion text.`,
              },
              {
                role: "user",
                content:
                  (args.file_path ? `File: ${args.file_path}\n` : "") +
                  "```" +
                  language +
                  "\n" +
                  String(args.code) +
                  "\n```",
              },
            ],
          },
          { action: "ai_completions" },
        ),
      );
    },
  },
  {
    name: "cloud_agent_command",
    description: "Run an allowed shell command in Cloud user storage context.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        command: { type: "string" },
        cwd: { type: "string" },
        root: rootProp,
        timeout_seconds: { type: "number" },
      },
      required: ["command"],
    },
    handler: async (args) =>
      textResult(
        await agentTool(
          "run_command",
          {
            command: String(args.command),
            cwd: args.cwd != null ? String(args.cwd) : "",
            timeout_seconds: args.timeout_seconds != null ? Number(args.timeout_seconds) : 20,
          },
          rootOf(args),
        ),
      ),
  },
  {
    name: "cloud_agent_read",
    description: "Read a Cloud file via AI agent tool (supports line slices).",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        root: rootProp,
        path: pathProp,
        offset_line: { type: "number" },
        line_count: { type: "number" },
        max_chars: { type: "number" },
      },
      required: ["path"],
    },
    handler: async (args) =>
      textResult(
        await agentTool(
          "read_file",
          {
            path: pathOf(args),
            offset_line: args.offset_line != null ? Number(args.offset_line) : 1,
            line_count: args.line_count != null ? Number(args.line_count) : 200,
            max_chars: args.max_chars != null ? Number(args.max_chars) : 32000,
          },
          rootOf(args),
        ),
      ),
  },
  {
    name: "cloud_agent_write",
    description: "Write a Cloud file via AI agent tool (overwrite/append/replace).",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        root: rootProp,
        path: pathProp,
        content: { type: "string" },
        mode: { type: "string", description: "overwrite|append|replace" },
        old_text: { type: "string" },
        new_text: { type: "string" },
        persist: { type: "boolean", description: "Persist immediately (default true)" },
      },
      required: ["path"],
    },
    handler: async (args) => {
      const root = rootOf(args);
      const path = pathOf(args);
      const mode = String(args.mode ?? "overwrite");
      if (mode === "replace") {
        return textResult(
          await agentTool(
            "search_replace",
            {
              path,
              old_text: String(args.old_text ?? ""),
              new_text: String(args.new_text ?? ""),
              persist: args.persist !== false,
            },
            root,
          ),
        );
      }
      let content = args.content != null ? String(args.content) : "";
      if (mode === "append") {
        const existing = await agentTool("read_file", { path, offset_line: 1, line_count: 100000 }, root);
        const prev = typeof existing?.content === "string" ? existing.content : "";
        content = prev + content;
      }
      return textResult(
        await agentTool("write_file", { path, content, persist: args.persist !== false }, root),
      );
    },
  },
  {
    name: "cloud_agent_search",
    description: "Search text inside Cloud files (paths required; use list/mentions to choose files).",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        root: rootProp,
        query: { type: "string" },
        paths: { type: "array", items: { type: "string" } },
        max_results: { type: "number" },
      },
      required: ["query", "paths"],
    },
    handler: async (args) => {
      const root = rootOf(args);
      const rawPaths = Array.isArray(args.paths) ? args.paths.map((p) => String(p).replace(/^\/+/, "")) : [];
      const filePaths: string[] = [];
      for (const p of rawPaths) {
        const listing = await cloudGet("list", { root, path: p });
        const items = Array.isArray(listing?.items) ? listing.items : null;
        if (items) {
          for (const it of items) {
            if (it?.type === "file" && it?.path) filePaths.push(String(it.path).replace(/^\/+/, ""));
          }
          continue;
        }
        if (p) filePaths.push(p);
      }
      return textResult(
        await agentTool(
          "search_in_files",
          {
            query: String(args.query),
            paths: filePaths.length ? filePaths : rawPaths,
            max_results: args.max_results != null ? Number(args.max_results) : 80,
          },
          root,
        ),
      );
    },
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
  {
    name: "cloud_groups_list",
    description: "List Cloud shared groups for the current user.",
    minRole: 0,
    inputSchema: { type: "object", properties: {} },
    handler: async () => textResult(await cloudGet("groups_list")),
  },
  {
    name: "cloud_groups_create",
    description: "Create a Cloud shared group.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: { name: { type: "string" } },
      required: ["name"],
    },
    handler: async (args) => textResult(await cloudForm("groups_create", { name: String(args.name) })),
  },
  {
    name: "cloud_groups_join",
    description: "Add a member to a group (admin). invite_code is treated as group_id when email is omitted.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        group_id: { type: "string" },
        invite_code: { type: "string", description: "Alias for group_id" },
        member_email: { type: "string" },
        email: { type: "string" },
        permission: { type: "string", description: "read|read_edit" },
      },
    },
    handler: async (args) => {
      const groupId = String(args.group_id ?? args.invite_code ?? "");
      const member = String(args.member_email ?? args.email ?? "");
      if (!groupId || !member) return textResult({ error: "group_id/invite_code and member_email required" });
      return textResult(
        await cloudForm("groups_add_member", {
          group_id: groupId,
          member_email: member,
          permission: args.permission != null ? String(args.permission) : "read_edit",
        }),
      );
    },
  },
  {
    name: "cloud_groups_leave",
    description: "Leave a Cloud shared group.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: { group_id: { type: "string" } },
      required: ["group_id"],
    },
    handler: async (args) =>
      textResult(await cloudForm("groups_leave", { group_id: String(args.group_id) })),
  },
  {
    name: "cloud_groups_delete",
    description: "Delete a Cloud shared group (admin of the group only).",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        group_id: { type: "string" },
        move_target_root: { type: "string", description: "Optional: main|archive|temp|documents|photos|pdf|clientsync" },
        move_target_path: { type: "string" },
      },
      required: ["group_id"],
    },
    handler: async (args) =>
      textResult(
        await cloudForm("groups_delete", {
          group_id: String(args.group_id),
          move_target_root: args.move_target_root != null ? String(args.move_target_root) : undefined,
          move_target_path: args.move_target_path != null ? String(args.move_target_path) : undefined,
        }),
      ),
  },
  {
    name: "cloud_groups_info",
    description: "Get Cloud group details/members (admin for full member list).",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: { group_id: { type: "string" } },
      required: ["group_id"],
    },
    handler: async (args) => {
      const groupId = String(args.group_id);
      const listed = await cloudGet("groups_list");
      const groups = Array.isArray(listed?.groups) ? listed.groups : [];
      const group = groups.find((g: any) => String(g.id ?? g.group_id) === groupId) ?? null;
      let members = null;
      try {
        members = await cloudGet("groups_members", { group_id: groupId });
      } catch (e) {
        members = { error: e instanceof Error ? e.message : String(e) };
      }
      return textResult({ ok: true, group, members });
    },
  },
  {
    name: "cloud_group_chat_messages",
    description: "Get Cloud group chat messages.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        group_id: { type: "string" },
        full: { type: "boolean" },
      },
      required: ["group_id"],
    },
    handler: async (args) =>
      textResult(
        await cloudGet("group_chat_messages", {
          group_id: String(args.group_id),
          full: args.full ? 1 : undefined,
        }),
      ),
  },
  {
    name: "cloud_group_chat_send",
    description: "Send a Cloud group chat message.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        group_id: { type: "string" },
        message: { type: "string" },
      },
      required: ["group_id", "message"],
    },
    handler: async (args) =>
      textResult(
        await cloudForm("group_chat_send", {
          group_id: String(args.group_id),
          message: String(args.message),
        }),
      ),
  },
  {
    name: "cloud_video_generation",
    description: "Start Cloud video generation from a folder/prompt.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        root: rootProp,
        folder: { type: "string" },
        prompt: { type: "string" },
        options: { type: "object", description: "fps, width, height, duration, model, assetIds" },
        fps: { type: "number" },
        width: { type: "number" },
        height: { type: "number" },
        duration: { type: "number" },
        model: { type: "string" },
      },
      required: ["prompt"],
    },
    handler: async (args) => {
      const opts =
        args.options && typeof args.options === "object" ? (args.options as Record<string, unknown>) : {};
      return textResult(
        await cloudForm("video_generation", {
          root: rootOf(args),
          folder: args.folder != null ? String(args.folder).replace(/^\/+/, "") : "",
          prompt: String(args.prompt),
          fps: Number(opts.fps ?? args.fps ?? 24),
          width: Number(opts.width ?? args.width ?? 720),
          height: Number(opts.height ?? args.height ?? 1280),
          duration: Number(opts.duration ?? args.duration ?? 12),
          model: String(opts.model ?? args.model ?? "") || undefined,
          assetIds: jsonField(opts.assetIds),
        }),
      );
    },
  },
  {
    name: "cloud_video_generation_status",
    description: "Get Cloud video generation job status.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: { job_id: { type: "string" } },
      required: ["job_id"],
    },
    handler: async (args) =>
      textResult(await cloudGet("video_generation_status", { job_id: String(args.job_id) })),
  },
  {
    name: "cloud_raw_audio",
    description: "Get Cloud audio stream URL + metadata.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: { root: rootProp, path: pathProp },
      required: ["path"],
    },
    handler: async (args) => {
      const root = rootOf(args);
      const path = pathOf(args);
      const info = await cloudGet("audio_info", { root, path }).catch(() => null);
      return textResult({
        url: streamUrl("raw_audio", root, path),
        auth: "Authorization: Bearer <token> + X-Device-Id",
        info,
      });
    },
  },
  {
    name: "cloud_raw_video",
    description: "Get Cloud video stream URL + metadata.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: { root: rootProp, path: pathProp },
      required: ["path"],
    },
    handler: async (args) => {
      const root = rootOf(args);
      const path = pathOf(args);
      const info = await cloudGet("video_info", { root, path }).catch(() => null);
      return textResult({
        url: streamUrl("raw_video", root, path),
        auth: "Authorization: Bearer <token> + X-Device-Id",
        info,
      });
    },
  },
];
