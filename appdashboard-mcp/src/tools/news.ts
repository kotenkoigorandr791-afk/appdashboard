import { apiGet, textResult } from "../api.js";
import type { ToolDef } from "./types.js";

const NEWS = "/main";

export const newsTools: ToolDef[] = [
  {
    name: "news_list",
    description:
      "List news articles (drafts, layout and total included when authenticated as Manager+). Paginated with limit/offset.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        limit: { type: "number", minimum: 1, maximum: 200, description: "Page size (default 20)" },
        offset: { type: "number", minimum: 0, description: "Pagination offset (default 0)" },
      },
    },
    handler: async (args) =>
      textResult(
        await apiGet(NEWS, {
          api: "news",
          action: "list",
          limit: Math.max(1, Math.min(200, Number(args.limit) || 20)),
          offset: Math.max(0, Number(args.offset) || 0),
        }),
      ),
  },
];
