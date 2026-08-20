import { apiDelete, apiGet, apiPost, textResult } from "../api.js";
import type { ToolDef } from "./types.js";

const idSchema = {
  type: "object",
  properties: { id: { type: "string", description: "Container/image/volume/stack id" } },
  required: ["id"],
};

function containerAction(name: string, description: string, action: string): ToolDef {
  return {
    name,
    description,
    minRole: 2,
    inputSchema: idSchema,
    handler: async (args) =>
      textResult(await apiPost(`/public-api/docker/containers/${encodeURIComponent(String(args.id))}/${action}`)),
  };
}

export const dockerTools: ToolDef[] = [
  {
    name: "list_containers",
    description: "List Docker containers via Portainer.",
    minRole: 1,
    inputSchema: { type: "object", properties: {} },
    handler: async () => textResult(await apiGet("/public-api/docker/containers")),
  },
  {
    name: "container_stats",
    description: "Get stats for a container.",
    minRole: 1,
    inputSchema: idSchema,
    handler: async (args) =>
      textResult(await apiGet(`/public-api/docker/containers/${encodeURIComponent(String(args.id))}/stats`)),
  },
  {
    name: "container_logs",
    description: "Get logs for a container.",
    minRole: 1,
    inputSchema: {
      type: "object",
      properties: {
        id: { type: "string" },
        tail: { type: "number", description: "Tail lines (default 200)" },
      },
      required: ["id"],
    },
    handler: async (args) =>
      textResult(
        await apiGet(`/public-api/docker/containers/${encodeURIComponent(String(args.id))}/logs`, {
          tail: args.tail as number | undefined,
        }),
      ),
  },
  {
    name: "container_inspect",
    description: "Inspect a container (admin).",
    minRole: 2,
    inputSchema: idSchema,
    handler: async (args) =>
      textResult(await apiGet(`/public-api/docker/containers/${encodeURIComponent(String(args.id))}/inspect`)),
  },
  containerAction("start_container", "Start a container (admin).", "start"),
  containerAction("stop_container", "Stop a container (admin).", "stop"),
  containerAction("restart_container", "Restart a container (admin).", "restart"),
  containerAction("pause_container", "Pause a container (admin).", "pause"),
  containerAction("unpause_container", "Unpause a container (admin).", "unpause"),
  containerAction("kill_container", "Kill a container with SIGKILL (admin).", "kill"),
  containerAction("remove_container", "Remove a container (admin).", "remove"),
  containerAction("update_container", "Pull/update container image (admin).", "update"),
  containerAction("recreate_container", "Recreate a container (admin).", "recreate"),
  containerAction("pull_image", "Pull image for a container (admin).", "pull"),
  {
    name: "pull_named_image",
    description: "Pull a Docker image by name (admin).",
    minRole: 2,
    inputSchema: {
      type: "object",
      properties: { image: { type: "string" } },
      required: ["image"],
    },
    handler: async (args) => textResult(await apiPost("/public-api/docker/images/pull", { image: args.image })),
  },
  {
    name: "remove_image",
    description: "Remove a Docker image (admin).",
    minRole: 2,
    inputSchema: idSchema,
    handler: async (args) =>
      textResult(await apiDelete(`/public-api/docker/images/${encodeURIComponent(String(args.id))}`)),
  },
  {
    name: "remove_volume",
    description: "Remove a Docker volume (admin).",
    minRole: 2,
    inputSchema: idSchema,
    handler: async (args) =>
      textResult(await apiDelete(`/public-api/docker/volumes/${encodeURIComponent(String(args.id))}`)),
  },
  {
    name: "list_stacks",
    description: "List Portainer stacks.",
    minRole: 1,
    inputSchema: { type: "object", properties: {} },
    handler: async () => textResult(await apiGet("/public-api/docker/stacks")),
  },
  {
    name: "get_stack_compose",
    description: "Get compose file for a stack (admin).",
    minRole: 2,
    inputSchema: idSchema,
    handler: async (args) =>
      textResult(await apiGet(`/public-api/docker/stacks/${encodeURIComponent(String(args.id))}/compose`)),
  },
  {
    name: "update_stack",
    description: "Update a stack compose file (admin).",
    minRole: 2,
    inputSchema: {
      type: "object",
      properties: {
        id: { type: "string" },
        content: { type: "string", description: "Compose file content" },
        prune: { type: "boolean" },
        pull_image: { type: "boolean" },
      },
      required: ["id", "content"],
    },
    handler: async (args) =>
      textResult(
        await apiPost(`/public-api/docker/stacks/${encodeURIComponent(String(args.id))}`, {
          content: args.content,
          prune: args.prune,
          pull_image: args.pull_image,
        }),
      ),
  },
  {
    name: "image_inspect",
    description: "Inspect a Docker image (admin).",
    minRole: 2,
    inputSchema: idSchema,
    handler: async (args) =>
      textResult(await apiGet(`/public-api/docker/images/${encodeURIComponent(String(args.id))}/inspect`)),
  },
];
