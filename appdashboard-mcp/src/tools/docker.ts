import { apiGet, textResult } from "../api.js";
import type { ToolDef } from "./types.js";

const idSchema = {
  type: "object",
  properties: { id: { type: "string", description: "Container id" } },
  required: ["id"],
};

export const dockerTools: ToolDef[] = [
  {
    name: "list_containers",
    description: "List Docker containers via Docker Engine API.",
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
];
