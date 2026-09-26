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
  color_id: { type: "string", description: "yellow, blue, green, purple, red, orange, grey, brown, deep_orange, dark_grey, pink, teal, cyan, lime, light_green, amber" },
  priority: { type: "number", description: "0 (low) .. 3 (high)" },
  date_due: { type: "string", description: "YYYY-MM-DD or YYYY-MM-DD HH:MM, empty string clears" },
  tags: { type: "array", items: { type: "string" } },
};
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
let appUrl = "";
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
async function taskDetails(taskId: number, withComments: boolean) {
  const task = await rpc("getTask", { task_id: taskId });
  const [columns, comments] = await Promise.all([
    projectColumns(Number(task.project_id)),
    withComments ? rpc("getAllComments", { task_id: taskId }) : Promise.resolve(undefined),
  ]);
  return {
    ...compactTask(task, columns),
    description: task.description,
    comments: (comments as any[] | undefined)?.map((c) => ({
      id: Number(c.id),
      author: c.name || c.username,
      date: isoDate(c.date_creation),
      content: c.comment,
    })),
  };
}
export const kanboardTools: ToolDef[] = [
  {
    name: "kanboard_get_me",
    description: "Get the current Kanboard user (id, username, role). Use the id as owner_id to assign tasks.",
    minRole: 0,
    inputSchema: { type: "object", properties: {} },
    handler: async () => textResult(await me()),
  },
  {
    name: "kanboard_list_projects",
    description: "List Kanboard projects the current user is a member of.",
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
    name: "kanboard_create_project",
    description: "Create a Kanboard project owned by the current user (private project for regular users). Returns project_id.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: { name: { type: "string" }, description: { type: "string" } },
      required: ["name"],
    },
    handler: async (args) => {
      const user = await me();
      const params = { name: String(args.name), description: args.description ? String(args.description) : null };
      const projectId =
        user.role === "app-user"
          ? await rpc("createMyPrivateProject", params)
          : await rpc("createProject", { ...params, owner_id: user.id });
      return textResult({ project_id: Number(projectId) });
    },
  },
  {
    name: "kanboard_get_board",
    description: "Get a project board: swimlanes, columns (ids for moving tasks) and open tasks in each column.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: { project_id: { type: "number" } },
      required: ["project_id"],
    },
    handler: async (args) => {
      const projectId = Number(args.project_id);
      const [project, board] = await Promise.all([
        rpc("getProjectById", { project_id: projectId }),
        rpc("getBoard", { project_id: projectId }),
      ]);
      return textResult({
        project: { id: Number(project.id), name: project.name, description: project.description || undefined },
        swimlanes: (board as any[]).map((s) => ({
          id: Number(s.id),
          name: s.name,
          columns: (s.columns as any[]).map((c) => ({
            id: Number(c.id),
            title: c.title,
            task_limit: Number(c.task_limit) || undefined,
            tasks: (c.tasks as any[]).map((t) => compactTask({ ...t, column_title: c.title })),
          })),
        })),
      });
    },
  },
  {
    name: "kanboard_list_tasks",
    description: "List tasks. With project_id: all tasks of the project (status open|closed). Without project_id: open tasks assigned to the current user across all projects.",
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
    name: "kanboard_get_task",
    description: "Get a Kanboard task with description and comments.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: { task_id: { type: "number" } },
      required: ["task_id"],
    },
    handler: async (args) => textResult(await taskDetails(Number(args.task_id), true)),
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
      return textResult(await taskDetails(Number(taskId), false));
    },
  },
  {
    name: "kanboard_update_task",
    description: "Update a task: edit fields, move to another column/swimlane, and/or close or reopen it. Only given fields change.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        task_id: { type: "number" },
        ...TASK_PROPS,
        column_id: { type: "number", description: "Move to this column (see kanboard_get_board)" },
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
      return textResult(await taskDetails(id, false));
    },
  },
  {
    name: "kanboard_add_comment",
    description: "Add a comment (Markdown) to a task as the current user.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: { task_id: { type: "number" }, content: { type: "string" } },
      required: ["task_id", "content"],
    },
    handler: async (args) => {
      const commentId = await rpc("createComment", {
        task_id: Number(args.task_id),
        user_id: (await me()).id,
        content: String(args.content),
      });
      return textResult({ comment_id: Number(commentId) });
    },
  },
];
