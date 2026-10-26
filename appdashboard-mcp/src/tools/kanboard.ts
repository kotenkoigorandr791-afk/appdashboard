import { getKanboardCredentials, kanboardRpc } from "../integrations.js";
import { ApiError, textResult } from "../api.js";
import type { ToolDef } from "./types.js";

type Args = Record<string, unknown>;

const TASK_FIELDS: Record<string, "string" | "number" | "array"> = {
  title: "string",
  description: "string",
  owner_id: "number",
  color_id: "string",
  priority: "number",
  date_due: "string",
  tags: "array",
};

const TASK_PROPS = {
  title: { type: "string" },
  description: { type: "string", description: "Markdown" },
  owner_id: { type: "number", description: "Assignee user id, 0 = unassigned" },
  color_id: {
    type: "string",
    description:
      "yellow, blue, green, purple, red, orange, grey, brown, deep_orange, dark_grey, pink, teal, cyan, lime, light_green, amber",
  },
  priority: { type: "number", description: "0 (low) .. 3 (high)" },
  date_due: { type: "string", description: "YYYY-MM-DD or YYYY-MM-DD HH:MM, empty string clears" },
  tags: { type: "array", items: { type: "string" } },
};

let appUrl = "";

async function rpc(method: string, params: unknown[] | Args = []): Promise<any> {
  const result = await kanboardRpc(method, params);
  appUrl = (await getKanboardCredentials()).url;
  if (result === false || result === null) {
    throw new ApiError(`kanboard_${method}_failed`, 400, { error: `kanboard_${method}_failed`, params });
  }
  return result;
}

async function me() {
  const u = await rpc("getMe");
  return { id: Number(u.id), username: u.username, name: u.name, email: u.email, role: u.role };
}

function link(url: unknown): string | undefined {
  if (typeof url !== "string" || !url) return undefined;
  const query = url.indexOf("?");
  return query >= 0 && appUrl ? `${appUrl}/${url.slice(query)}` : url;
}

function isoDate(ts: unknown): string | null {
  const n = Number(ts);
  return n > 0 ? new Date(n * 1000).toISOString().slice(0, 10) : null;
}

function compactTask(t: any, columns?: Map<number, string>) {
  return {
    id: Number(t.id),
    title: t.title,
    project_id: Number(t.project_id),
    project: t.project_name ?? undefined,
    column_id: Number(t.column_id),
    column: t.column_name ?? t.column_title ?? columns?.get(Number(t.column_id)) ?? undefined,
    swimlane_id: Number(t.swimlane_id),
    owner_id: Number(t.owner_id) || 0,
    assignee: t.assignee_name || t.assignee_username || undefined,
    priority: Number(t.priority || 0),
    color: t.color_id,
    due: isoDate(t.date_due),
    status: Number(t.is_active) === 1 ? "open" : "closed",
    url: link(t.url),
  };
}

function taskFields(args: Args): Args {
  const out: Args = {};
  for (const [key, type] of Object.entries(TASK_FIELDS)) {
    const v = args[key];
    if (v === undefined || v === null) continue;
    out[key] = type === "number" ? Number(v) : type === "array" ? (v as unknown[]).map(String) : String(v);
  }
  return out;
}

async function projectColumns(projectId: number): Promise<Map<number, string>> {
  const columns: any[] = await rpc("getColumns", { project_id: projectId });
  return new Map(columns.map((c) => [Number(c.id), String(c.title)]));
}

async function taskDetails(taskId: number) {
  const task = await rpc("getTask", { task_id: taskId });
  const columns = await projectColumns(Number(task.project_id));
  return {
    ...compactTask(task, columns),
    description: task.description,
  };
}

async function projectStats(projectId: number) {
  const [columnsRaw, open, closed] = await Promise.all([
    rpc("getColumns", { project_id: projectId }),
    rpc("getAllTasks", { project_id: projectId, status_id: 1 }),
    rpc("getAllTasks", { project_id: projectId, status_id: 0 }),
  ]);
  const columns = (columnsRaw as any[]).map((c) => ({
    id: Number(c.id),
    title: String(c.title),
    position: Number(c.position || 0),
  }));
  const byColumn = new Map<number, { column_id: number; column: string; open: number }>();
  for (const c of columns) byColumn.set(c.id, { column_id: c.id, column: c.title, open: 0 });
  let overdue = 0;
  let unassigned = 0;
  const now = Date.now() / 1000;
  for (const t of open as any[]) {
    const cid = Number(t.column_id);
    const row = byColumn.get(cid) || { column_id: cid, column: String(t.column_name || cid), open: 0 };
    row.open += 1;
    byColumn.set(cid, row);
    if (!Number(t.owner_id)) unassigned += 1;
    const due = Number(t.date_due || 0);
    if (due > 0 && due < now) overdue += 1;
  }
  return {
    project_id: projectId,
    open: (open as any[]).length,
    closed: (closed as any[]).length,
    overdue,
    unassigned,
    columns,
    by_column: [...byColumn.values()],
  };
}

export const kanboardTools: ToolDef[] = [
  {
    name: "kanboard_list_projects",
    description: "List Kanboard projects the current user can access (ids for update_project / create_task / stats).",
    minRole: 0,
    inputSchema: { type: "object", properties: {} },
    handler: async () => {
      const projects: any[] = await rpc("getMyProjects");
      return textResult(
        projects.map((p) => ({
          id: Number(p.id),
          name: p.name,
          description: p.description || undefined,
          is_private: Number(p.is_private) === 1,
          url: link(p.url?.board),
        })),
      );
    },
  },
  {
    name: "kanboard_update_project",
    description: "Edit an existing Kanboard project (name, description, active flag). Does not create projects.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        project_id: { type: "number" },
        name: { type: "string" },
        description: { type: "string" },
        is_active: { type: "boolean", description: "false archives/disables the project" },
      },
      required: ["project_id"],
    },
    handler: async (args) => {
      const id = Number(args.project_id);
      const fields: Args = { project_id: id };
      if (args.name !== undefined) fields.name = String(args.name);
      if (args.description !== undefined) fields.description = String(args.description);
      if (Object.keys(fields).length === 1 && args.is_active === undefined) {
        throw new ApiError("kanboard_update_project_no_fields", 400, { error: "no_fields" });
      }
      if (Object.keys(fields).length > 1) await rpc("updateProject", fields);
      if (args.is_active !== undefined) {
        await rpc(args.is_active ? "enableProject" : "disableProject", { project_id: id });
      }
      const project = await rpc("getProjectById", { project_id: id });
      return textResult({
        id: Number(project.id),
        name: project.name,
        description: project.description || undefined,
        is_private: Number(project.is_private) === 1,
        is_active: Number(project.is_active) === 1,
        url: link(project.url?.board),
      });
    },
  },
  {
    name: "kanboard_list_tasks",
    description:
      "List tasks. With project_id: project tasks (status open|closed). Without project_id: open tasks assigned to the current user.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        project_id: { type: "number" },
        status: { type: "string", enum: ["open", "closed"], description: "Default open" },
      },
    },
    handler: async (args) => {
      if (!args.project_id) {
        const tasks: any[] = await rpc("getMyDashboard");
        return textResult(tasks.map((t) => compactTask(t)));
      }
      const projectId = Number(args.project_id);
      const [tasks, columns] = await Promise.all([
        rpc("getAllTasks", { project_id: projectId, status_id: args.status === "closed" ? 0 : 1 }),
        projectColumns(projectId),
      ]);
      return textResult((tasks as any[]).map((t) => compactTask(t, columns)));
    },
  },
  {
    name: "kanboard_create_task",
    description: "Create a task. Assigned to the current user unless owner_id is given. Default column is the first one.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        project_id: { type: "number" },
        ...TASK_PROPS,
        column_id: { type: "number" },
        swimlane_id: { type: "number" },
      },
      required: ["project_id", "title"],
    },
    handler: async (args) => {
      const fields = taskFields(args);
      if (fields.owner_id === undefined) fields.owner_id = (await me()).id;
      const params: Args = { ...fields, project_id: Number(args.project_id) };
      if (args.column_id) params.column_id = Number(args.column_id);
      if (args.swimlane_id) params.swimlane_id = Number(args.swimlane_id);
      const taskId = await rpc("createTask", params);
      return textResult(await taskDetails(Number(taskId)));
    },
  },
  {
    name: "kanboard_update_task",
    description: "Update a task: fields, move column/swimlane, and/or open|closed. Only given fields change.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        task_id: { type: "number" },
        ...TASK_PROPS,
        column_id: { type: "number", description: "Move to this column (ids from kanboard_stats)" },
        swimlane_id: { type: "number" },
        position: { type: "number", description: "Position in column, 1 = top (default)" },
        status: { type: "string", enum: ["open", "closed"] },
      },
      required: ["task_id"],
    },
    handler: async (args) => {
      const id = Number(args.task_id);
      const fields = taskFields(args);
      if (Object.keys(fields).length) await rpc("updateTask", { id, ...fields });
      if (args.column_id !== undefined || args.swimlane_id !== undefined) {
        const task = await rpc("getTask", { task_id: id });
        const target = {
          column_id: Number(args.column_id ?? task.column_id),
          swimlane_id: Number(args.swimlane_id ?? task.swimlane_id),
          position: Number(args.position ?? 1),
        };
        const unchanged =
          target.column_id === Number(task.column_id) &&
          target.swimlane_id === Number(task.swimlane_id) &&
          (args.position === undefined || target.position === Number(task.position));
        if (!unchanged) await rpc("moveTaskPosition", { project_id: Number(task.project_id), task_id: id, ...target });
      }
      if (args.status === "closed") await rpc("closeTask", { task_id: id });
      if (args.status === "open") await rpc("openTask", { task_id: id });
      return textResult(await taskDetails(id));
    },
  },
  {
    name: "kanboard_delete_task",
    description: "Permanently delete a Kanboard task.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: { task_id: { type: "number" } },
      required: ["task_id"],
    },
    handler: async (args) => {
      const taskId = Number(args.task_id);
      await rpc("removeTask", { task_id: taskId });
      return textResult({ deleted: taskId });
    },
  },
  {
    name: "kanboard_stats",
    description:
      "Quick project stats for agents: open/closed/overdue/unassigned counts, columns (with ids), and open tasks per column.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: { project_id: { type: "number" } },
      required: ["project_id"],
    },
    handler: async (args) => textResult(await projectStats(Number(args.project_id))),
  },
];
