import { giteaFetch } from "../integrations.js";
import { textResult } from "../api.js";
import type { ToolDef } from "./types.js";
function ownerRepo(args: Record<string, unknown>): { owner: string; repo: string } {
    return { owner: String(args.owner), repo: String(args.repo) };
}
function pageQuery(args: Record<string, unknown>): Record<string, unknown> {
    const q: Record<string, unknown> = {};
    if (args.page != null)
        q.page = Number(args.page);
    if (args.limit != null)
        q.limit = Number(args.limit);
    return q;
}
export const giteaTools: ToolDef[] = [
    {
        name: "gitea_user_info",
        description: "Get the current Gitea user profile.",
        minRole: 0,
        inputSchema: { type: "object", properties: {} },
        handler: async () => textResult(await giteaFetch("GET", "/user")),
    },
    {
        name: "gitea_repo_list_my",
        description: "List repositories owned by the current Gitea user.",
        minRole: 0,
        inputSchema: {
            type: "object",
            properties: {
                page: { type: "number", description: "Page number" },
                limit: { type: "number", description: "Items per page" },
            },
        },
        handler: async (args: Record<string, unknown>) => textResult(await giteaFetch("GET", "/user/repos", { query: pageQuery(args) })),
    },
    {
        name: "gitea_repo_create",
        description: "Create a new Gitea repository for the current user.",
        minRole: 0,
        inputSchema: {
            type: "object",
            properties: {
                name: { type: "string", description: "Repository name" },
                description: { type: "string", description: "Repository description" },
                private: { type: "boolean", description: "Whether the repository is private" },
                auto_init: { type: "boolean", description: "Initialize with README" },
                gitignore_template: { type: "string" },
                license_template: { type: "string" },
                readme: { type: "string" },
                default_branch: { type: "string" },
            },
            required: ["name"],
        },
        handler: async (args: Record<string, unknown>) => {
            const body: Record<string, unknown> = { name: String(args.name) };
            for (const k of ["description", "gitignore_template", "license_template", "readme", "default_branch"]) {
                if (args[k] != null && args[k] !== "")
                    body[k] = args[k];
            }
            if (args.private !== undefined)
                body.private = Boolean(args.private);
            if (args.auto_init !== undefined)
                body.auto_init = Boolean(args.auto_init);
            return textResult(await giteaFetch("POST", "/user/repos", { body }));
        },
    },
    {
        name: "gitea_repo_fork",
        description: "Fork a Gitea repository.",
        minRole: 0,
        inputSchema: {
            type: "object",
            properties: {
                owner: { type: "string" },
                repo: { type: "string" },
                organization: { type: "string", description: "Org to fork into" },
            },
            required: ["owner", "repo"],
        },
        handler: async (args: Record<string, unknown>) => {
            const { owner, repo } = ownerRepo(args);
            const body: Record<string, unknown> = {};
            if (args.organization)
                body.organization = String(args.organization);
            return textResult(await giteaFetch("POST", `/repos/${encodeURIComponent(owner)}/${encodeURIComponent(repo)}/forks`, { body }));
        },
    },
    {
        name: "gitea_repo_list_tags",
        description: "List tags in a Gitea repository.",
        minRole: 0,
        inputSchema: {
            type: "object",
            properties: {
                owner: { type: "string" },
                repo: { type: "string" },
                page: { type: "number" },
                limit: { type: "number" },
            },
            required: ["owner", "repo"],
        },
        handler: async (args: Record<string, unknown>) => {
            const { owner, repo } = ownerRepo(args);
            return textResult(await giteaFetch("GET", `/repos/${encodeURIComponent(owner)}/${encodeURIComponent(repo)}/tags`, {
                query: pageQuery(args),
            }));
        },
    },
    {
        name: "gitea_issue_get_by_index",
        description: "Get a Gitea issue by index.",
        minRole: 0,
        inputSchema: {
            type: "object",
            properties: {
                owner: { type: "string" },
                repo: { type: "string" },
                index: { type: "number", description: "Issue index number" },
            },
            required: ["owner", "repo", "index"],
        },
        handler: async (args: Record<string, unknown>) => {
            const { owner, repo } = ownerRepo(args);
            return textResult(await giteaFetch("GET", `/repos/${encodeURIComponent(owner)}/${encodeURIComponent(repo)}/issues/${Number(args.index)}`));
        },
    },
    {
        name: "gitea_issue_list",
        description: "List issues for a repository with optional filtering.",
        minRole: 0,
        inputSchema: {
            type: "object",
            properties: {
                owner: { type: "string" },
                repo: { type: "string" },
                page: { type: "number" },
                limit: { type: "number" },
                state: { type: "string", enum: ["open", "closed", "all"] },
                labels: { type: "array", items: { type: "string" } },
                keyword: { type: "string" },
                created_by: { type: "string" },
                assigned_by: { type: "string" },
            },
            required: ["owner", "repo"],
        },
        handler: async (args: Record<string, unknown>) => {
            const { owner, repo } = ownerRepo(args);
            const query: Record<string, unknown> = { ...pageQuery(args) };
            if (args.state)
                query.state = String(args.state);
            if (Array.isArray(args.labels))
                query.labels = args.labels.map(String).join(",");
            if (args.keyword)
                query.q = String(args.keyword);
            if (args.created_by)
                query.created_by = String(args.created_by);
            if (args.assigned_by)
                query.assigned_by = String(args.assigned_by);
            return textResult(await giteaFetch("GET", `/repos/${encodeURIComponent(owner)}/${encodeURIComponent(repo)}/issues`, { query }));
        },
    },
    {
        name: "gitea_issue_search_list",
        description: "Search issues assigned to or created by the current user.",
        minRole: 0,
        inputSchema: {
            type: "object",
            properties: {
                page: { type: "number" },
                limit: { type: "number" },
                state: { type: "string", enum: ["open", "closed", "all"] },
                type: { type: "string", enum: ["assigned", "created"], description: "assigned or created" },
                labels: { type: "array", items: { type: "string" } },
                keyword: { type: "string" },
            },
            required: ["type"],
        },
        handler: async (args: Record<string, unknown>) => {
            const query: Record<string, unknown> = { ...pageQuery(args), type: "issues" };
            if (args.state)
                query.state = String(args.state);
            if (Array.isArray(args.labels))
                query.labels = args.labels.map(String).join(",");
            if (args.keyword)
                query.q = String(args.keyword);
            if (args.type === "assigned")
                query.assigned = true;
            if (args.type === "created")
                query.created = true;
            return textResult(await giteaFetch("GET", "/repos/issues/search", { query }));
        },
    },
    {
        name: "gitea_issue_create",
        description: "Create a Gitea issue.",
        minRole: 0,
        inputSchema: {
            type: "object",
            properties: {
                owner: { type: "string" },
                repo: { type: "string" },
                title: { type: "string" },
                body: { type: "string" },
                labels: { type: "array", items: { type: "number" }, description: "Label IDs" },
            },
            required: ["owner", "repo", "title"],
        },
        handler: async (args: Record<string, unknown>) => {
            const { owner, repo } = ownerRepo(args);
            const body: Record<string, unknown> = { title: String(args.title) };
            if (args.body != null)
                body.body = String(args.body);
            if (Array.isArray(args.labels))
                body.labels = args.labels.map(Number);
            return textResult(await giteaFetch("POST", `/repos/${encodeURIComponent(owner)}/${encodeURIComponent(repo)}/issues`, { body }));
        },
    },
    {
        name: "gitea_issue_edit",
        description: "Edit a Gitea issue (title, body, state).",
        minRole: 0,
        inputSchema: {
            type: "object",
            properties: {
                owner: { type: "string" },
                repo: { type: "string" },
                index: { type: "number" },
                title: { type: "string" },
                body: { type: "string" },
                state: { type: "string", enum: ["open", "closed"] },
            },
            required: ["owner", "repo", "index"],
        },
        handler: async (args: Record<string, unknown>) => {
            const { owner, repo } = ownerRepo(args);
            const body: Record<string, unknown> = {};
            if (args.title != null)
                body.title = String(args.title);
            if (args.body != null)
                body.body = String(args.body);
            if (args.state != null)
                body.state = String(args.state);
            return textResult(await giteaFetch("PATCH", `/repos/${encodeURIComponent(owner)}/${encodeURIComponent(repo)}/issues/${Number(args.index)}`, {
                body,
            }));
        },
    },
    {
        name: "gitea_issue_add_labels",
        description: "Add labels to a Gitea issue by label name.",
        minRole: 0,
        inputSchema: {
            type: "object",
            properties: {
                owner: { type: "string" },
                repo: { type: "string" },
                index: { type: "number" },
                labels: { type: "array", items: { type: "string" }, description: "Label names" },
            },
            required: ["owner", "repo", "index", "labels"],
        },
        handler: async (args: Record<string, unknown>) => {
            const { owner, repo } = ownerRepo(args);
            const all = (await giteaFetch("GET", `/repos/${encodeURIComponent(owner)}/${encodeURIComponent(repo)}/labels`, {
                query: { limit: 100 },
            }));
            const names = Array.isArray(args.labels) ? args.labels.map(String) : [];
            const labelIds = names
                .map((name: string) => all.find((l: { id: number; name: string }) => l.name === name)?.id)
                .filter((id: number | undefined): id is number => id != null);
            return textResult(await giteaFetch("POST", `/repos/${encodeURIComponent(owner)}/${encodeURIComponent(repo)}/issues/${Number(args.index)}/labels`, { body: { labels: labelIds } }));
        },
    },
    {
        name: "gitea_issue_remove_labels",
        description: "Remove labels from a Gitea issue by label name.",
        minRole: 0,
        inputSchema: {
            type: "object",
            properties: {
                owner: { type: "string" },
                repo: { type: "string" },
                index: { type: "number" },
                labels: { type: "array", items: { type: "string" } },
            },
            required: ["owner", "repo", "index", "labels"],
        },
        handler: async (args: Record<string, unknown>) => {
            const { owner, repo } = ownerRepo(args);
            const all = (await giteaFetch("GET", `/repos/${encodeURIComponent(owner)}/${encodeURIComponent(repo)}/labels`, {
                query: { limit: 100 },
            }));
            const names = Array.isArray(args.labels) ? args.labels.map(String) : [];
            const results = [];
            for (const name of names) {
                const found = all.find((l: { id: number; name: string }) => l.name === name);
                if (!found)
                    continue;
                results.push(await giteaFetch("DELETE", `/repos/${encodeURIComponent(owner)}/${encodeURIComponent(repo)}/issues/${Number(args.index)}/labels/${found.id}`));
            }
            return textResult({ status: "success", results });
        },
    },
    {
        name: "gitea_issue_comment_list",
        description: "List comments on a Gitea issue.",
        minRole: 0,
        inputSchema: {
            type: "object",
            properties: {
                owner: { type: "string" },
                repo: { type: "string" },
                index: { type: "number" },
            },
            required: ["owner", "repo", "index"],
        },
        handler: async (args: Record<string, unknown>) => {
            const { owner, repo } = ownerRepo(args);
            return textResult(await giteaFetch("GET", `/repos/${encodeURIComponent(owner)}/${encodeURIComponent(repo)}/issues/${Number(args.index)}/comments`));
        },
    },
    {
        name: "gitea_issue_comment_create",
        description: "Create a comment on a Gitea issue.",
        minRole: 0,
        inputSchema: {
            type: "object",
            properties: {
                owner: { type: "string" },
                repo: { type: "string" },
                index: { type: "number" },
                body: { type: "string" },
            },
            required: ["owner", "repo", "index", "body"],
        },
        handler: async (args: Record<string, unknown>) => {
            const { owner, repo } = ownerRepo(args);
            return textResult(await giteaFetch("POST", `/repos/${encodeURIComponent(owner)}/${encodeURIComponent(repo)}/issues/${Number(args.index)}/comments`, { body: { body: String(args.body) } }));
        },
    },
    {
        name: "gitea_issue_comment_edit",
        description: "Edit a Gitea issue comment.",
        minRole: 0,
        inputSchema: {
            type: "object",
            properties: {
                owner: { type: "string" },
                repo: { type: "string" },
                commentId: { type: "number" },
                body: { type: "string" },
            },
            required: ["owner", "repo", "commentId", "body"],
        },
        handler: async (args: Record<string, unknown>) => {
            const { owner, repo } = ownerRepo(args);
            return textResult(await giteaFetch("PATCH", `/repos/${encodeURIComponent(owner)}/${encodeURIComponent(repo)}/issues/comments/${Number(args.commentId)}`, { body: { body: String(args.body) } }));
        },
    },
    {
        name: "gitea_label_list",
        description: "List labels in a Gitea repository.",
        minRole: 0,
        inputSchema: {
            type: "object",
            properties: {
                owner: { type: "string" },
                repo: { type: "string" },
                page: { type: "number" },
                limit: { type: "number" },
            },
            required: ["owner", "repo"],
        },
        handler: async (args: Record<string, unknown>) => {
            const { owner, repo } = ownerRepo(args);
            return textResult(await giteaFetch("GET", `/repos/${encodeURIComponent(owner)}/${encodeURIComponent(repo)}/labels`, {
                query: pageQuery(args),
            }));
        },
    },
    {
        name: "gitea_label_create",
        description: "Create a repository label.",
        minRole: 0,
        inputSchema: {
            type: "object",
            properties: {
                owner: { type: "string" },
                repo: { type: "string" },
                name: { type: "string" },
                color: { type: "string", description: "Hex color without #" },
                description: { type: "string" },
            },
            required: ["owner", "repo", "name", "color"],
        },
        handler: async (args: Record<string, unknown>) => {
            const { owner, repo } = ownerRepo(args);
            const body: Record<string, unknown> = {
                name: String(args.name),
                color: String(args.color).replace(/^#/, ""),
                exclusive: String(args.name).includes("/"),
            };
            if (args.description != null)
                body.description = String(args.description);
            return textResult(await giteaFetch("POST", `/repos/${encodeURIComponent(owner)}/${encodeURIComponent(repo)}/labels`, { body }));
        },
    },
    {
        name: "gitea_label_edit",
        description: "Edit a repository label.",
        minRole: 0,
        inputSchema: {
            type: "object",
            properties: {
                owner: { type: "string" },
                repo: { type: "string" },
                labelId: { type: "number" },
                name: { type: "string" },
                color: { type: "string" },
                description: { type: "string" },
            },
            required: ["owner", "repo", "labelId"],
        },
        handler: async (args: Record<string, unknown>) => {
            const { owner, repo } = ownerRepo(args);
            const body: Record<string, unknown> = {};
            if (args.name != null) {
                body.name = String(args.name);
                body.exclusive = String(args.name).includes("/");
            }
            if (args.color != null)
                body.color = String(args.color).replace(/^#/, "");
            if (args.description != null)
                body.description = String(args.description);
            return textResult(await giteaFetch("PATCH", `/repos/${encodeURIComponent(owner)}/${encodeURIComponent(repo)}/labels/${Number(args.labelId)}`, { body }));
        },
    },
    {
        name: "gitea_label_delete",
        description: "Delete a repository label.",
        minRole: 0,
        inputSchema: {
            type: "object",
            properties: {
                owner: { type: "string" },
                repo: { type: "string" },
                labelId: { type: "number" },
            },
            required: ["owner", "repo", "labelId"],
        },
        handler: async (args: Record<string, unknown>) => {
            const { owner, repo } = ownerRepo(args);
            await giteaFetch("DELETE", `/repos/${encodeURIComponent(owner)}/${encodeURIComponent(repo)}/labels/${Number(args.labelId)}`);
            return textResult({ success: true, labelId: Number(args.labelId) });
        },
    },
    {
        name: "gitea_pr_list",
        description: "List pull requests in a repository.",
        minRole: 0,
        inputSchema: {
            type: "object",
            properties: {
                owner: { type: "string" },
                repo: { type: "string" },
                page: { type: "number" },
                limit: { type: "number" },
                state: { type: "string", enum: ["open", "closed", "all"] },
                sort: {
                    type: "string",
                    enum: ["oldest", "recentupdate", "recentclose", "leastupdate", "mostcomment", "leastcomment", "priority"],
                },
            },
            required: ["owner", "repo"],
        },
        handler: async (args: Record<string, unknown>) => {
            const { owner, repo } = ownerRepo(args);
            const query: Record<string, unknown> = { ...pageQuery(args) };
            if (args.state)
                query.state = String(args.state);
            if (args.sort)
                query.sort = String(args.sort);
            return textResult(await giteaFetch("GET", `/repos/${encodeURIComponent(owner)}/${encodeURIComponent(repo)}/pulls`, { query }));
        },
    },
    {
        name: "gitea_pr_get",
        description: "Get a pull request by index.",
        minRole: 0,
        inputSchema: {
            type: "object",
            properties: {
                owner: { type: "string" },
                repo: { type: "string" },
                index: { type: "number" },
            },
            required: ["owner", "repo", "index"],
        },
        handler: async (args: Record<string, unknown>) => {
            const { owner, repo } = ownerRepo(args);
            return textResult(await giteaFetch("GET", `/repos/${encodeURIComponent(owner)}/${encodeURIComponent(repo)}/pulls/${Number(args.index)}`));
        },
    },
    {
        name: "gitea_pr_create",
        description: "Create a pull request.",
        minRole: 0,
        inputSchema: {
            type: "object",
            properties: {
                owner: { type: "string" },
                repo: { type: "string" },
                title: { type: "string" },
                head: { type: "string", description: "Head branch" },
                base: { type: "string", description: "Base branch" },
                body: { type: "string" },
                milestones: { type: "array", items: { type: "number" } },
                labels: { type: "array", items: { type: "number" } },
            },
            required: ["owner", "repo", "title", "head", "base"],
        },
        handler: async (args: Record<string, unknown>) => {
            const { owner, repo } = ownerRepo(args);
            const body: Record<string, unknown> = {
                title: String(args.title),
                head: String(args.head),
                base: String(args.base),
            };
            if (args.body != null)
                body.body = String(args.body);
            if (Array.isArray(args.milestones))
                body.milestones = args.milestones.map(Number);
            if (Array.isArray(args.labels))
                body.labels = args.labels.map(Number);
            return textResult(await giteaFetch("POST", `/repos/${encodeURIComponent(owner)}/${encodeURIComponent(repo)}/pulls`, { body }));
        },
    },
    {
        name: "gitea_pr_add_reviewer",
        description: "Add reviewers to a pull request.",
        minRole: 0,
        inputSchema: {
            type: "object",
            properties: {
                owner: { type: "string" },
                repo: { type: "string" },
                index: { type: "number" },
                reviewers: { type: "array", items: { type: "string" } },
                team_reviewers: { type: "array", items: { type: "string" } },
            },
            required: ["owner", "repo", "index", "reviewers"],
        },
        handler: async (args: Record<string, unknown>) => {
            const { owner, repo } = ownerRepo(args);
            const body: Record<string, unknown> = {
                reviewers: Array.isArray(args.reviewers) ? args.reviewers.map(String) : [],
            };
            if (Array.isArray(args.team_reviewers))
                body.team_reviewers = args.team_reviewers.map(String);
            return textResult(await giteaFetch("POST", `/repos/${encodeURIComponent(owner)}/${encodeURIComponent(repo)}/pulls/${Number(args.index)}/requested_reviewers`, { body }));
        },
    },
    {
        name: "gitea_pr_request_re_review",
        description: "Request reviewers to re-review a pull request.",
        minRole: 0,
        inputSchema: {
            type: "object",
            properties: {
                owner: { type: "string" },
                repo: { type: "string" },
                index: { type: "number" },
                reviewers: { type: "array", items: { type: "string" } },
                team_reviewers: { type: "array", items: { type: "string" } },
            },
            required: ["owner", "repo", "index", "reviewers"],
        },
        handler: async (args: Record<string, unknown>) => {
            const { owner, repo } = ownerRepo(args);
            const body: Record<string, unknown> = {
                reviewers: Array.isArray(args.reviewers) ? args.reviewers.map(String) : [],
            };
            if (Array.isArray(args.team_reviewers))
                body.team_reviewers = args.team_reviewers.map(String);
            return textResult(await giteaFetch("POST", `/repos/${encodeURIComponent(owner)}/${encodeURIComponent(repo)}/pulls/${Number(args.index)}/requested_reviewers`, { body }));
        },
    },
    {
        name: "gitea_pr_delete_reviewer",
        description: "Remove a reviewer from a pull request.",
        minRole: 0,
        inputSchema: {
            type: "object",
            properties: {
                owner: { type: "string" },
                repo: { type: "string" },
                index: { type: "number" },
                reviewer: { type: "string" },
            },
            required: ["owner", "repo", "index", "reviewer"],
        },
        handler: async (args: Record<string, unknown>) => {
            const { owner, repo } = ownerRepo(args);
            return textResult(await giteaFetch("DELETE", `/repos/${encodeURIComponent(owner)}/${encodeURIComponent(repo)}/pulls/${Number(args.index)}/requested_reviewers`, { body: { reviewers: [String(args.reviewer)] } }));
        },
    },
    {
        name: "gitea_pr_get_review",
        description: "Get a specific pull request review.",
        minRole: 0,
        inputSchema: {
            type: "object",
            properties: {
                owner: { type: "string" },
                repo: { type: "string" },
                index: { type: "number" },
                reviewId: { type: "number" },
            },
            required: ["owner", "repo", "index", "reviewId"],
        },
        handler: async (args: Record<string, unknown>) => {
            const { owner, repo } = ownerRepo(args);
            return textResult(await giteaFetch("GET", `/repos/${encodeURIComponent(owner)}/${encodeURIComponent(repo)}/pulls/${Number(args.index)}/reviews/${Number(args.reviewId)}`));
        },
    },
    {
        name: "gitea_pr_get_review_comments",
        description: "Get inline comments for a pull request review.",
        minRole: 0,
        inputSchema: {
            type: "object",
            properties: {
                owner: { type: "string" },
                repo: { type: "string" },
                index: { type: "number" },
                reviewId: { type: "number" },
            },
            required: ["owner", "repo", "index", "reviewId"],
        },
        handler: async (args: Record<string, unknown>) => {
            const { owner, repo } = ownerRepo(args);
            return textResult(await giteaFetch("GET", `/repos/${encodeURIComponent(owner)}/${encodeURIComponent(repo)}/pulls/${Number(args.index)}/reviews/${Number(args.reviewId)}/comments`));
        },
    },
    {
        name: "gitea_pr_list_reviews",
        description: "List all reviews on a pull request.",
        minRole: 0,
        inputSchema: {
            type: "object",
            properties: {
                owner: { type: "string" },
                repo: { type: "string" },
                index: { type: "number" },
            },
            required: ["owner", "repo", "index"],
        },
        handler: async (args: Record<string, unknown>) => {
            const { owner, repo } = ownerRepo(args);
            return textResult(await giteaFetch("GET", `/repos/${encodeURIComponent(owner)}/${encodeURIComponent(repo)}/pulls/${Number(args.index)}/reviews`));
        },
    },
    {
        name: "gitea_pr_edit",
        description: "Edit a pull request (title, body, state, assignees, labels, milestone).",
        minRole: 0,
        inputSchema: {
            type: "object",
            properties: {
                owner: { type: "string" },
                repo: { type: "string" },
                index: { type: "number" },
                title: { type: "string" },
                body: { type: "string" },
                state: { type: "string", enum: ["open", "closed"] },
                base: { type: "string" },
                assignee: { type: "string" },
                assignees: { type: "array", items: { type: "string" } },
                milestone: { type: "number" },
                labels: { type: "array", items: { type: "number" } },
            },
            required: ["owner", "repo", "index"],
        },
        handler: async (args: Record<string, unknown>) => {
            const { owner, repo } = ownerRepo(args);
            const body: Record<string, unknown> = {};
            for (const k of ["title", "body", "state", "base", "assignee"]) {
                if (args[k] != null && args[k] !== "")
                    body[k] = args[k];
            }
            if (Array.isArray(args.assignees))
                body.assignees = args.assignees.map(String);
            if (args.milestone != null)
                body.milestone = Number(args.milestone);
            if (Array.isArray(args.labels))
                body.labels = args.labels.map(Number);
            return textResult(await giteaFetch("PATCH", `/repos/${encodeURIComponent(owner)}/${encodeURIComponent(repo)}/pulls/${Number(args.index)}`, {
                body,
            }));
        },
    },
    {
        name: "gitea_pr_list_my",
        description: "List pull requests created by the current user.",
        minRole: 0,
        inputSchema: {
            type: "object",
            properties: {
                page: { type: "number" },
                limit: { type: "number" },
                state: { type: "string", enum: ["open", "closed", "all"] },
                labels: { type: "string", description: "Comma-separated label names" },
                milestones: { type: "string", description: "Comma-separated milestone names" },
                q: { type: "string" },
                since: { type: "string", description: "RFC 3339" },
                before: { type: "string", description: "RFC 3339" },
            },
        },
        handler: async (args: Record<string, unknown>) => {
            const query: Record<string, unknown> = {
                ...pageQuery(args),
                type: "pulls",
                created: true,
            };
            for (const k of ["state", "labels", "milestones", "q", "since", "before"]) {
                if (args[k] != null && args[k] !== "")
                    query[k] = args[k];
            }
            return textResult(await giteaFetch("GET", "/repos/issues/search", { query }));
        },
    },
];
