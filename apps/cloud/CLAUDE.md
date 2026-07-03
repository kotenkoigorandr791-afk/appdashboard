## graphify

This project has a knowledge graph at graphify-out/ with god nodes, community structure, and cross-file relationships.

Rules:
- ALWAYS read graphify-out/GRAPH_REPORT.md before reading any source files, running grep/glob searches, or answering codebase questions. The graph is your primary map of the codebase.
- IF graphify-out/wiki/index.md EXISTS, navigate it instead of reading raw files
- For cross-module "how does X relate to Y" questions, prefer `graphify query "<question>"`, `graphify path "<A>" "<B>"`, or `graphify explain "<concept>"` over grep — these traverse the graph's EXTRACTED + INFERRED edges instead of scanning files
- After modifying code, run `graphify update .` to keep the graph current (AST-only, no API cost).

## AI assistant tools

The built-in AI assistant should expose these workspace tools:
- `web_search`: search the internet for current information.
- `list_directory`: list files in the current folder or a subfolder; recursive listing should be supported.
- `create_file`: create a new file with optional initial content.
- `make_directory`: create directories.
- `copy_file`: copy a file.
- `rename_file`: rename or move a file.
- `move_path`: move or rename a file or directory.
- `delete_file`: delete a file only after an explicit user confirmation dialog.
- `delete_directory`: delete a directory only after an explicit user confirmation dialog.
- `stat_path`: inspect metadata for a file or folder.
- `search_by_regex`: regex search in text files.
- `replace_by_regex`: regex replace in text files with staged preview semantics.
- `diff_file`: show textual diffs.
- `run_command`: execute only whitelisted workspace-safe commands.
- `open_url_fetch`: fetch a specific URL as text.
- `download_file`: download a remote file into the workspace.
- `archive_extract`: extract ZIP archives.
- `archive_create`: create ZIP archives.
- `json_edit`: edit JSON by key path operations.
- `csv_preview`: preview CSV/TSV data.

Deletion safety rule:
- `delete_file` must never execute silently or from model intent alone.
- A real confirmation dialog must be shown to the user for each deletion attempt.
- If the user cancels, the file must remain unchanged.
- `delete_directory` must follow the same confirmation rule.

UI and logging safety rules:
- Never show an absolute filesystem path in any user-visible message, debug panel, toast, modal, status text, or browser console message.
- Never write an absolute filesystem path to generated logs, temp logs, or persisted project logs.
- If a file reference must be shown, use only a safe relative path or the basename.
- When editors create a new file, the file tree must highlight the new node immediately after save.
