import { getPlaneCredentials, planeFetch } from "../integrations.js";
import { textResult } from "../api.js";
import type { ToolDef } from "./types.js";

function ws(slug: string, path: string): string {
  const p = path.startsWith("/") ? path : `/${path}`;
  return `/api/v1/workspaces/${encodeURIComponent(slug)}${p}`;
}

async function withWs(
  args: Record<string, unknown>,
  fn: (slug: string) => Promise<unknown>,
): Promise<ReturnType<typeof textResult>> {
  const creds = await getPlaneCredentials();
  const slug = String(args.workspace_slug || creds.workspace_slug || "").trim();
  if (!slug) throw new Error("workspace_slug_required");
  return textResult(await fn(slug));
}

function pick(args: Record<string, unknown>, keys: string[]): Record<string, unknown> {
  const out: Record<string, unknown> = {};
  for (const k of keys) {
    if (args[k] !== undefined && args[k] !== null && args[k] !== "") out[k] = args[k];
  }
  return out;
}

const wsSlugProp = {
  workspace_slug: {
    type: "string",
    description: "Plane workspace slug (defaults to auto-provisioned workspace)",
  },
};

export const planeTools: ToolDef[] = [
  {
    name: "plane_get_me",
    description: "Get the authenticated Plane user profile (UUID for assignees / cycle owners).",
    minRole: 0,
    inputSchema: { type: "object", properties: {} },
    handler: async () => textResult(await planeFetch("GET", "/api/v1/users/me/")),
  },
  {
    name: "plane_list_workspaces",
    description: "List Plane workspaces available to the current user.",
    minRole: 0,
    inputSchema: { type: "object", properties: {} },
    handler: async () => textResult(await planeFetch("GET", "/api/v1/users/me/workspaces/")),
  },
  {
    name: "plane_get_workspace_members",
    description: "List workspace members (user UUIDs for assignees).",
    minRole: 0,
    inputSchema: { type: "object", properties: { ...wsSlugProp } },
    handler: async (args) =>
      withWs(args, (slug) => planeFetch("GET", ws(slug, "/members/"))),
  },
  {
    name: "plane_list_projects",
    description: "List projects in a Plane workspace. Call first to obtain project_id.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        ...wsSlugProp,
        cursor: { type: "string" },
        per_page: { type: "number" },
        order_by: { type: "string" },
        expand: { type: "string" },
      },
    },
    handler: async (args) =>
      withWs(args, (slug) =>
        planeFetch("GET", ws(slug, "/projects/"), {
          query: pick(args, ["cursor", "per_page", "order_by", "expand"]),
        }),
      ),
  },
  {
    name: "plane_create_project",
    description: "Create a Plane project.",
    minRole: 0,
    inputSchema: {
      type: "object",
      required: ["name", "identifier"],
      properties: {
        ...wsSlugProp,
        name: { type: "string" },
        identifier: { type: "string", description: "Short project key, e.g. DEV" },
        description: { type: "string" },
        project_lead: { type: "string" },
        default_assignee: { type: "string" },
      },
    },
    handler: async (args) =>
      withWs(args, (slug) =>
        planeFetch("POST", ws(slug, "/projects/"), {
          body: pick(args, ["name", "identifier", "description", "project_lead", "default_assignee"]),
        }),
      ),
  },
  {
    name: "plane_get_project",
    description: "Retrieve a Plane project by ID.",
    minRole: 0,
    inputSchema: {
      type: "object",
      required: ["project_id"],
      properties: { ...wsSlugProp, project_id: { type: "string" } },
    },
    handler: async (args) =>
      withWs(args, (slug) =>
        planeFetch("GET", ws(slug, `/projects/${encodeURIComponent(String(args.project_id))}/`)),
      ),
  },
  {
    name: "plane_update_project",
    description: "Update a Plane project.",
    minRole: 0,
    inputSchema: {
      type: "object",
      required: ["project_id"],
      properties: {
        ...wsSlugProp,
        project_id: { type: "string" },
        name: { type: "string" },
        description: { type: "string" },
        project_lead: { type: "string" },
        default_assignee: { type: "string" },
      },
    },
    handler: async (args) =>
      withWs(args, (slug) =>
        planeFetch("PATCH", ws(slug, `/projects/${encodeURIComponent(String(args.project_id))}/`), {
          body: pick(args, ["name", "description", "project_lead", "default_assignee"]),
        }),
      ),
  },
  {
    name: "plane_delete_project",
    description: "Delete a Plane project.",
    minRole: 0,
    inputSchema: {
      type: "object",
      required: ["project_id"],
      properties: { ...wsSlugProp, project_id: { type: "string" } },
    },
    handler: async (args) =>
      withWs(args, (slug) =>
        planeFetch("DELETE", ws(slug, `/projects/${encodeURIComponent(String(args.project_id))}/`)),
      ),
  },
  {
    name: "plane_get_project_members",
    description: "List members of a Plane project.",
    minRole: 0,
    inputSchema: {
      type: "object",
      required: ["project_id"],
      properties: { ...wsSlugProp, project_id: { type: "string" } },
    },
    handler: async (args) =>
      withWs(args, (slug) =>
        planeFetch("GET", ws(slug, `/projects/${encodeURIComponent(String(args.project_id))}/members/`)),
      ),
  },
  {
    name: "plane_list_work_items",
    description:
      "List work items (issues) in a project. Filter by priority, state, assignees, labels, dates. Paginated via cursor.",
    minRole: 0,
    inputSchema: {
      type: "object",
      required: ["project_id"],
      properties: {
        ...wsSlugProp,
        project_id: { type: "string" },
        cursor: { type: "string" },
        per_page: { type: "number" },
        expand: { type: "string" },
        priority: { type: "string" },
        state: { type: "string" },
        state_group: { type: "string" },
        assignees: { type: "string" },
        labels: { type: "string" },
        order_by: { type: "string" },
      },
    },
    handler: async (args) =>
      withWs(args, (slug) =>
        planeFetch("GET", ws(slug, `/projects/${encodeURIComponent(String(args.project_id))}/issues/`), {
          query: pick(args, [
            "cursor",
            "per_page",
            "expand",
            "priority",
            "state",
            "state_group",
            "assignees",
            "labels",
            "order_by",
          ]),
        }),
      ),
  },
  {
    name: "plane_create_work_item",
    description:
      "Create a work item. Use plane_list_states / plane_list_labels / plane_get_workspace_members for UUIDs. Priority: urgent|high|medium|low|none.",
    minRole: 0,
    inputSchema: {
      type: "object",
      required: ["project_id", "name"],
      properties: {
        ...wsSlugProp,
        project_id: { type: "string" },
        name: { type: "string" },
        description_html: { type: "string" },
        priority: { type: "string" },
        state: { type: "string" },
        assignees: { type: "array", items: { type: "string" } },
        labels: { type: "array", items: { type: "string" } },
        start_date: { type: "string" },
        target_date: { type: "string" },
      },
    },
    handler: async (args) =>
      withWs(args, (slug) =>
        planeFetch("POST", ws(slug, `/projects/${encodeURIComponent(String(args.project_id))}/issues/`), {
          body: pick(args, [
            "name",
            "description_html",
            "priority",
            "state",
            "assignees",
            "labels",
            "start_date",
            "target_date",
          ]),
        }),
      ),
  },
  {
    name: "plane_get_work_item",
    description: "Retrieve a work item by UUID.",
    minRole: 0,
    inputSchema: {
      type: "object",
      required: ["project_id", "work_item_id"],
      properties: {
        ...wsSlugProp,
        project_id: { type: "string" },
        work_item_id: { type: "string" },
        expand: { type: "string" },
      },
    },
    handler: async (args) =>
      withWs(args, (slug) =>
        planeFetch(
          "GET",
          ws(
            slug,
            `/projects/${encodeURIComponent(String(args.project_id))}/issues/${encodeURIComponent(String(args.work_item_id))}/`,
          ),
          { query: pick(args, ["expand"]) },
        ),
      ),
  },
  {
    name: "plane_update_work_item",
    description: "Update a work item (only provided fields change).",
    minRole: 0,
    inputSchema: {
      type: "object",
      required: ["project_id", "work_item_id"],
      properties: {
        ...wsSlugProp,
        project_id: { type: "string" },
        work_item_id: { type: "string" },
        name: { type: "string" },
        description_html: { type: "string" },
        priority: { type: "string" },
        state: { type: "string" },
        assignees: { type: "array", items: { type: "string" } },
        labels: { type: "array", items: { type: "string" } },
        start_date: { type: "string" },
        target_date: { type: "string" },
      },
    },
    handler: async (args) =>
      withWs(args, (slug) =>
        planeFetch(
          "PATCH",
          ws(
            slug,
            `/projects/${encodeURIComponent(String(args.project_id))}/issues/${encodeURIComponent(String(args.work_item_id))}/`,
          ),
          {
            body: pick(args, [
              "name",
              "description_html",
              "priority",
              "state",
              "assignees",
              "labels",
              "start_date",
              "target_date",
            ]),
          },
        ),
      ),
  },
  {
    name: "plane_delete_work_item",
    description: "Delete a work item.",
    minRole: 0,
    inputSchema: {
      type: "object",
      required: ["project_id", "work_item_id"],
      properties: {
        ...wsSlugProp,
        project_id: { type: "string" },
        work_item_id: { type: "string" },
      },
    },
    handler: async (args) =>
      withWs(args, (slug) =>
        planeFetch(
          "DELETE",
          ws(
            slug,
            `/projects/${encodeURIComponent(String(args.project_id))}/issues/${encodeURIComponent(String(args.work_item_id))}/`,
          ),
        ),
      ),
  },
  {
    name: "plane_search_work_items",
    description: "Search work items by text across the workspace (optional project_id filter).",
    minRole: 0,
    inputSchema: {
      type: "object",
      required: ["search"],
      properties: {
        ...wsSlugProp,
        search: { type: "string" },
        project_id: { type: "string" },
        workspace_search: { type: "boolean" },
      },
    },
    handler: async (args) =>
      withWs(args, (slug) =>
        planeFetch("GET", ws(slug, "/issues/search/"), {
          query: {
            search: String(args.search),
            ...pick(args, ["project_id", "workspace_search"]),
          },
        }),
      ),
  },
  {
    name: "plane_list_states",
    description: "List workflow states for a project (backlog/unstarted/started/completed/cancelled).",
    minRole: 0,
    inputSchema: {
      type: "object",
      required: ["project_id"],
      properties: { ...wsSlugProp, project_id: { type: "string" } },
    },
    handler: async (args) =>
      withWs(args, (slug) =>
        planeFetch("GET", ws(slug, `/projects/${encodeURIComponent(String(args.project_id))}/states/`)),
      ),
  },
  {
    name: "plane_list_labels",
    description: "List labels in a project.",
    minRole: 0,
    inputSchema: {
      type: "object",
      required: ["project_id"],
      properties: { ...wsSlugProp, project_id: { type: "string" } },
    },
    handler: async (args) =>
      withWs(args, (slug) =>
        planeFetch("GET", ws(slug, `/projects/${encodeURIComponent(String(args.project_id))}/labels/`)),
      ),
  },
  {
    name: "plane_list_cycles",
    description: "List cycles (sprints) in a project.",
    minRole: 0,
    inputSchema: {
      type: "object",
      required: ["project_id"],
      properties: { ...wsSlugProp, project_id: { type: "string" } },
    },
    handler: async (args) =>
      withWs(args, (slug) =>
        planeFetch("GET", ws(slug, `/projects/${encodeURIComponent(String(args.project_id))}/cycles/`)),
      ),
  },
  {
    name: "plane_create_cycle",
    description: "Create a cycle. owned_by is a user UUID from plane_get_me / plane_get_workspace_members.",
    minRole: 0,
    inputSchema: {
      type: "object",
      required: ["project_id", "name", "owned_by"],
      properties: {
        ...wsSlugProp,
        project_id: { type: "string" },
        name: { type: "string" },
        owned_by: { type: "string" },
        description: { type: "string" },
        start_date: { type: "string" },
        end_date: { type: "string" },
      },
    },
    handler: async (args) =>
      withWs(args, (slug) =>
        planeFetch("POST", ws(slug, `/projects/${encodeURIComponent(String(args.project_id))}/cycles/`), {
          body: pick(args, ["name", "owned_by", "description", "start_date", "end_date"]),
        }),
      ),
  },
  {
    name: "plane_get_cycle",
    description: "Retrieve a cycle by ID.",
    minRole: 0,
    inputSchema: {
      type: "object",
      required: ["project_id", "cycle_id"],
      properties: {
        ...wsSlugProp,
        project_id: { type: "string" },
        cycle_id: { type: "string" },
      },
    },
    handler: async (args) =>
      withWs(args, (slug) =>
        planeFetch(
          "GET",
          ws(
            slug,
            `/projects/${encodeURIComponent(String(args.project_id))}/cycles/${encodeURIComponent(String(args.cycle_id))}/`,
          ),
        ),
      ),
  },
  {
    name: "plane_list_modules",
    description: "List modules in a project.",
    minRole: 0,
    inputSchema: {
      type: "object",
      required: ["project_id"],
      properties: { ...wsSlugProp, project_id: { type: "string" } },
    },
    handler: async (args) =>
      withWs(args, (slug) =>
        planeFetch("GET", ws(slug, `/projects/${encodeURIComponent(String(args.project_id))}/modules/`)),
      ),
  },
  {
    name: "plane_create_module",
    description: "Create a module in a project.",
    minRole: 0,
    inputSchema: {
      type: "object",
      required: ["project_id", "name"],
      properties: {
        ...wsSlugProp,
        project_id: { type: "string" },
        name: { type: "string" },
        description: { type: "string" },
        status: { type: "string" },
        lead: { type: "string" },
        start_date: { type: "string" },
        target_date: { type: "string" },
      },
    },
    handler: async (args) =>
      withWs(args, (slug) =>
        planeFetch("POST", ws(slug, `/projects/${encodeURIComponent(String(args.project_id))}/modules/`), {
          body: pick(args, ["name", "description", "status", "lead", "start_date", "target_date"]),
        }),
      ),
  },
  {
    name: "plane_get_module",
    description: "Retrieve a module by ID.",
    minRole: 0,
    inputSchema: {
      type: "object",
      required: ["project_id", "module_id"],
      properties: {
        ...wsSlugProp,
        project_id: { type: "string" },
        module_id: { type: "string" },
      },
    },
    handler: async (args) =>
      withWs(args, (slug) =>
        planeFetch(
          "GET",
          ws(
            slug,
            `/projects/${encodeURIComponent(String(args.project_id))}/modules/${encodeURIComponent(String(args.module_id))}/`,
          ),
        ),
      ),
  },
  {
    name: "plane_list_work_item_comments",
    description: "List comments on a work item.",
    minRole: 0,
    inputSchema: {
      type: "object",
      required: ["project_id", "work_item_id"],
      properties: {
        ...wsSlugProp,
        project_id: { type: "string" },
        work_item_id: { type: "string" },
        cursor: { type: "string" },
        per_page: { type: "number" },
      },
    },
    handler: async (args) =>
      withWs(args, (slug) =>
        planeFetch(
          "GET",
          ws(
            slug,
            `/projects/${encodeURIComponent(String(args.project_id))}/issues/${encodeURIComponent(String(args.work_item_id))}/comments/`,
          ),
          { query: pick(args, ["cursor", "per_page"]) },
        ),
      ),
  },
  {
    name: "plane_create_work_item_comment",
    description: "Add an HTML comment to a work item.",
    minRole: 0,
    inputSchema: {
      type: "object",
      required: ["project_id", "work_item_id", "comment_html"],
      properties: {
        ...wsSlugProp,
        project_id: { type: "string" },
        work_item_id: { type: "string" },
        comment_html: { type: "string" },
      },
    },
    handler: async (args) =>
      withWs(args, (slug) =>
        planeFetch(
          "POST",
          ws(
            slug,
            `/projects/${encodeURIComponent(String(args.project_id))}/issues/${encodeURIComponent(String(args.work_item_id))}/comments/`,
          ),
          { body: { comment_html: String(args.comment_html) } },
        ),
      ),
  },
];
