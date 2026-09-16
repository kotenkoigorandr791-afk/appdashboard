import { kanboardRpc } from "../integrations.js";
import { textResult } from "../api.js";
import type { ToolDef } from "./types.js";

export const kanboardTools: ToolDef[] = [
  {
    name: "kanboard_get_me",
    description: "Get the authenticated Kanboard user profile.",
    minRole: 0,
    inputSchema: { type: "object", properties: {} },
    handler: async () => textResult(await kanboardRpc("getMe")),
  },
  {
    name: "kanboard_list_projects",
    description: "List Kanboard projects visible to the current user.",
    minRole: 0,
    inputSchema: { type: "object", properties: {} },
    handler: async () => textResult(await kanboardRpc("getMyProjects")),
  },
  {
    name: "kanboard_create_project",
    description: "Create a Kanboard project. Returns the new project id.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        name: { type: "string" },
        description: { type: "string" },
      },
      required: ["name"],
    },
    handler: async (args) =>
      textResult(
        await kanboardRpc("createProject", [
          String(args.name),
          args.description ? String(args.description) : "",
        ]),
      ),
  },
  {
    name: "kanboard_get_project",
    description: "Get a Kanboard project by id.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: { project_id: { type: "number" } },
      required: ["project_id"],
    },
    handler: async (args) => textResult(await kanboardRpc("getProjectById", [Number(args.project_id)])),
  },
  {
    name: "kanboard_list_tasks",
    description: "List active tasks for a Kanboard project.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: { project_id: { type: "number" } },
      required: ["project_id"],
    },
    handler: async (args) =>
      textResult(await kanboardRpc("getAllTasks", [Number(args.project_id), 1])),
  },
  {
    name: "kanboard_create_task",
    description: "Create a task on a Kanboard board.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        project_id: { type: "number" },
        title: { type: "string" },
        description: { type: "string" },
        column_id: { type: "number" },
      },
      required: ["project_id", "title"],
    },
    handler: async (args) => {
      const payload: Record<string, unknown> = {
        project_id: Number(args.project_id),
        title: String(args.title),
      };
      if (args.description) payload.description = String(args.description);
      if (args.column_id) payload.column_id = Number(args.column_id);
      return textResult(await kanboardRpc("createTask", [payload]));
    },
  },
  {
    name: "kanboard_move_task",
    description: "Move a Kanboard task to another column/position.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        project_id: { type: "number" },
        task_id: { type: "number" },
        column_id: { type: "number" },
        position: { type: "number" },
        swimlane_id: { type: "number" },
      },
      required: ["project_id", "task_id", "column_id"],
    },
    handler: async (args) =>
      textResult(
        await kanboardRpc("moveTaskPosition", [
          Number(args.project_id),
          Number(args.task_id),
          Number(args.column_id),
          Number(args.position || 1),
          Number(args.swimlane_id || 0),
        ]),
      ),
  },
];
