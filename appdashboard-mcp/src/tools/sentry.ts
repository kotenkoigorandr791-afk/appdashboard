import { sentryFetch } from "../integrations.js";
import { textResult } from "../api.js";
import type { ToolDef } from "./types.js";

function compactIssue(issue: any) {
  return {
    id: String(issue.id),
    shortId: issue.shortId,
    title: issue.title,
    culprit: issue.culprit,
    level: issue.level,
    status: issue.status,
    count: issue.count,
    userCount: issue.userCount,
    firstSeen: issue.firstSeen,
    lastSeen: issue.lastSeen,
    permalink: issue.permalink,
  };
}

export const sentryTools: ToolDef[] = [
  {
    name: "sentry_projects_list",
    description: "List Sentry/GlitchTip projects.",
    minRole: 0,
    inputSchema: { type: "object", properties: {} },
    handler: async () => {
      const projects = await sentryFetch("GET", "/api/0/projects/");
      return textResult(
        (Array.isArray(projects) ? projects : []).map((project: any) => ({
          id: String(project.id),
          slug: project.slug,
          name: project.name,
          organization: project.organization?.slug,
          platform: project.platform,
        })),
      );
    },
  },
  {
    name: "sentry_issues_list",
    description: "List errors/issues for a Sentry/GlitchTip project.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        organization: { type: "string" },
        project: { type: "string" },
        query: { type: "string", description: "Optional issue search query" },
        statsPeriod: { type: "string", description: "Example: 24h, 14d" },
        limit: { type: "number" },
      },
      required: ["organization", "project"],
    },
    handler: async (args) => {
      const data = await sentryFetch(
        "GET",
        `/api/0/projects/${encodeURIComponent(String(args.organization))}/${encodeURIComponent(String(args.project))}/issues/`,
        {
          query: {
            query: args.query,
            statsPeriod: args.statsPeriod,
            limit: args.limit != null ? Number(args.limit) : 25,
          },
        },
      );
      return textResult((Array.isArray(data) ? data : []).map(compactIssue));
    },
  },
  {
    name: "sentry_issue_resolve",
    description: "Mark a Sentry/GlitchTip issue as resolved.",
    minRole: 0,
    inputSchema: {
      type: "object",
      properties: {
        issueId: { type: "string" },
      },
      required: ["issueId"],
    },
    handler: async (args) =>
      textResult(await sentryFetch("PUT", `/api/0/issues/${encodeURIComponent(String(args.issueId))}/`, { body: { status: "resolved" } })),
  },
];
