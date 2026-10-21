<?php
require_once APP_ROOT . '/lib/kanboard_client.php';

$email = (string) ($_SESSION['user']['email'] ?? '');
if ($email === '') {
    public_api_json(['ok' => false, 'error' => 'unauthorized'], 401);
}

$parts = public_api_parse_path();
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$body = public_api_body();
$resource = (string) ($parts[1] ?? '');
$id = isset($parts[2]) && ctype_digit((string) $parts[2]) ? (int) $parts[2] : 0;
$sub = (string) ($parts[3] ?? '');
$subId = isset($parts[4]) && ctype_digit((string) $parts[4]) ? (int) $parts[4] : 0;

function kb_api_fail(array $out): void
{
    public_api_json([
        'ok' => false,
        'error' => $out['error'] ?? 'kanboard_error',
        'message' => $out['message'] ?? null,
    ], (int) ($out['status'] ?? 502));
}

function kb_api_rpc(string $email, string $rpcMethod, $params = [])
{
    $out = kanboard_client_call($email, $rpcMethod, $params);
    if (empty($out['ok'])) {
        kb_api_fail($out);
    }
    return $out['result'];
}

function kb_api_iso($ts): ?string
{
    $n = (int) $ts;
    return $n > 0 ? gmdate('Y-m-d\TH:i:s\Z', $n) : null;
}

function kb_api_date($ts): ?string
{
    $n = (int) $ts;
    return $n > 0 ? gmdate('Y-m-d', $n) : null;
}

function kb_api_link(?array $creds, $url): ?string
{
    if (!is_string($url) || $url === '') {
        return null;
    }
    $base = rtrim((string) ($creds['url'] ?? ''), '/');
    $q = strpos($url, '?');
    if ($q !== false && $base !== '') {
        return $base . '/' . substr($url, $q);
    }
    return $url;
}

function kb_api_task(array $t, ?array $creds = null, ?array $columns = null): array
{
    $columnId = (int) ($t['column_id'] ?? 0);
    return [
        'id' => (int) ($t['id'] ?? 0),
        'title' => (string) ($t['title'] ?? ''),
        'description' => (string) ($t['description'] ?? ''),
        'project_id' => (int) ($t['project_id'] ?? 0),
        'project' => $t['project_name'] ?? null,
        'column_id' => $columnId,
        'column' => $t['column_name'] ?? $t['column_title'] ?? ($columns[$columnId] ?? null),
        'swimlane_id' => (int) ($t['swimlane_id'] ?? 0),
        'owner_id' => (int) ($t['owner_id'] ?? 0),
        'assignee' => $t['assignee_name'] ?? $t['assignee_username'] ?? null,
        'priority' => (int) ($t['priority'] ?? 0),
        'color' => $t['color_id'] ?? null,
        'score' => isset($t['score']) ? (int) $t['score'] : null,
        'category_id' => (int) ($t['category_id'] ?? 0) ?: null,
        'due' => kb_api_date($t['date_due'] ?? 0),
        'started' => kb_api_date($t['date_started'] ?? 0),
        'created_at' => kb_api_iso($t['date_creation'] ?? 0),
        'modified_at' => kb_api_iso($t['date_modification'] ?? 0),
        'status' => ((int) ($t['is_active'] ?? 1) === 1) ? 'open' : 'closed',
        'url' => kb_api_link($creds, $t['url'] ?? null),
        'tags' => isset($t['tags']) && is_array($t['tags']) ? array_values($t['tags']) : null,
    ];
}

function kb_api_project(array $p, ?array $creds = null): array
{
    $boardUrl = null;
    if (isset($p['url']) && is_array($p['url'])) {
        $boardUrl = kb_api_link($creds, $p['url']['board'] ?? null);
    } elseif (isset($p['url'])) {
        $boardUrl = kb_api_link($creds, $p['url']);
    }
    return [
        'id' => (int) ($p['id'] ?? 0),
        'name' => (string) ($p['name'] ?? ''),
        'description' => (string) ($p['description'] ?? ''),
        'is_private' => (int) ($p['is_private'] ?? 0) === 1,
        'is_active' => (int) ($p['is_active'] ?? 1) === 1,
        'last_modified' => kb_api_iso($p['last_modified'] ?? 0),
        'url' => $boardUrl,
    ];
}

function kb_api_columns(string $email, int $projectId): array
{
    $cols = kb_api_rpc($email, 'getColumns', ['project_id' => $projectId]);
    $map = [];
    $list = [];
    foreach ((array) $cols as $c) {
        $cid = (int) ($c['id'] ?? 0);
        $title = (string) ($c['title'] ?? '');
        $map[$cid] = $title;
        $list[] = [
            'id' => $cid,
            'title' => $title,
            'position' => (int) ($c['position'] ?? 0),
            'task_limit' => (int) ($c['task_limit'] ?? 0) ?: null,
        ];
    }
    return [$map, $list];
}

function kb_api_task_fields(array $src): array
{
    $out = [];
    foreach (['title' => 'string', 'description' => 'string', 'color_id' => 'string', 'date_due' => 'string', 'date_started' => 'string'] as $k => $type) {
        if (!array_key_exists($k, $src) || $src[$k] === null) {
            continue;
        }
        $out[$k] = (string) $src[$k];
    }
    foreach (['owner_id', 'priority', 'column_id', 'swimlane_id', 'category_id', 'score'] as $k) {
        if (!array_key_exists($k, $src) || $src[$k] === null) {
            continue;
        }
        $out[$k] = (int) $src[$k];
    }
    if (isset($src['tags']) && is_array($src['tags'])) {
        $out['tags'] = array_values(array_map('strval', $src['tags']));
    }
    return $out;
}

function kb_api_load_task(string $email, int $taskId, bool $withComments = false): array
{
    $credsOut = kanboard_client_credentials($email);
    if (empty($credsOut['ok'])) {
        kb_api_fail($credsOut + ['status' => 503]);
    }
    $task = kb_api_rpc($email, 'getTask', ['task_id' => $taskId]);
    if (!$task || !is_array($task)) {
        public_api_json(['ok' => false, 'error' => 'task_not_found'], 404);
    }
    [$colMap] = kb_api_columns($email, (int) $task['project_id']);
    $payload = kb_api_task($task, $credsOut, $colMap);
    if ($withComments) {
        $comments = kb_api_rpc($email, 'getAllComments', ['task_id' => $taskId]);
        $payload['comments'] = array_map(static function ($c) {
            return [
                'id' => (int) ($c['id'] ?? 0),
                'author' => $c['name'] ?? $c['username'] ?? null,
                'user_id' => (int) ($c['user_id'] ?? 0),
                'date' => kb_api_iso($c['date_creation'] ?? 0),
                'content' => (string) ($c['comment'] ?? ''),
            ];
        }, (array) $comments);
    }
    return $payload;
}

function kb_api_stats(string $email, int $projectId): array
{
    [$colMap, $colList] = kb_api_columns($email, $projectId);
    $open = (array) kb_api_rpc($email, 'getAllTasks', ['project_id' => $projectId, 'status_id' => 1]);
    $closed = (array) kb_api_rpc($email, 'getAllTasks', ['project_id' => $projectId, 'status_id' => 0]);
    $byColumn = [];
    foreach ($colList as $c) {
        $byColumn[$c['id']] = ['column_id' => $c['id'], 'column' => $c['title'], 'open' => 0];
    }
    $overdue = 0;
    $unassigned = 0;
    $now = time();
    foreach ($open as $t) {
        $cid = (int) ($t['column_id'] ?? 0);
        if (!isset($byColumn[$cid])) {
            $byColumn[$cid] = ['column_id' => $cid, 'column' => $colMap[$cid] ?? null, 'open' => 0];
        }
        $byColumn[$cid]['open']++;
        if ((int) ($t['owner_id'] ?? 0) === 0) {
            $unassigned++;
        }
        $due = (int) ($t['date_due'] ?? 0);
        if ($due > 0 && $due < $now) {
            $overdue++;
        }
    }
    $members = [];
    try {
        $members = (array) kb_api_rpc($email, 'getProjectUsers', ['project_id' => $projectId]);
    } catch (Throwable $e) {
        $members = [];
    }
    return [
        'project_id' => $projectId,
        'open' => count($open),
        'closed' => count($closed),
        'overdue' => $overdue,
        'unassigned' => $unassigned,
        'members' => count($members),
        'columns' => $colList,
        'by_column' => array_values($byColumn),
    ];
}

$credsProbe = kanboard_client_credentials($email);
if (empty($credsProbe['ok'])) {
    kb_api_fail($credsProbe + ['status' => 503]);
}

if ($resource === '' || $resource === 'index') {
    public_api_json([
        'ok' => true,
        'app' => 'kanboard',
        'endpoints' => [
            'GET /me',
            'GET /projects',
            'GET /projects/{id}',
            'PATCH /projects/{id}',
            'GET /projects/{id}/board',
            'GET /projects/{id}/columns',
            'GET /projects/{id}/members',
            'GET /projects/{id}/tasks',
            'POST /projects/{id}/tasks',
            'GET /projects/{id}/stats',
            'GET /my-tasks',
            'GET /tasks/{id}',
            'PATCH /tasks/{id}',
            'DELETE /tasks/{id}',
            'GET /tasks/{id}/comments',
            'POST /tasks/{id}/comments',
        ],
    ]);
}

if ($resource === 'me' && $method === 'GET') {
    $me = kb_api_rpc($email, 'getMe');
    public_api_json([
        'ok' => true,
        'user' => [
            'id' => (int) ($me['id'] ?? 0),
            'username' => $me['username'] ?? null,
            'name' => $me['name'] ?? null,
            'email' => $me['email'] ?? null,
            'role' => $me['role'] ?? null,
        ],
    ]);
}

if ($resource === 'my-tasks' && $method === 'GET') {
    $tasks = (array) kb_api_rpc($email, 'getMyDashboard');
    public_api_json([
        'ok' => true,
        'tasks' => array_map(static fn($t) => kb_api_task($t, $credsProbe), $tasks),
    ]);
}

if ($resource === 'projects') {
    if ($method === 'GET' && $id <= 0) {
        $projects = (array) kb_api_rpc($email, 'getMyProjects');
        public_api_json([
            'ok' => true,
            'projects' => array_map(static fn($p) => kb_api_project($p, $credsProbe), $projects),
        ]);
    }
    if ($id <= 0) {
        public_api_json(['ok' => false, 'error' => 'project_id_required'], 400);
    }
    if ($method === 'GET' && $sub === '') {
        $project = kb_api_rpc($email, 'getProjectById', ['project_id' => $id]);
        if (!$project) {
            public_api_json(['ok' => false, 'error' => 'project_not_found'], 404);
        }
        public_api_json(['ok' => true, 'project' => kb_api_project($project, $credsProbe)]);
    }
    if (($method === 'PATCH' || $method === 'PUT') && $sub === '') {
        $fields = [];
        if (isset($body['name'])) {
            $fields['name'] = (string) $body['name'];
        }
        if (array_key_exists('description', $body)) {
            $fields['description'] = (string) $body['description'];
        }
        if (!$fields && !array_key_exists('is_active', $body)) {
            public_api_json(['ok' => false, 'error' => 'no_fields'], 400);
        }
        if ($fields) {
            kb_api_rpc($email, 'updateProject', array_merge(['project_id' => $id], $fields));
        }
        if (array_key_exists('is_active', $body)) {
            kb_api_rpc($email, !empty($body['is_active']) ? 'enableProject' : 'disableProject', ['project_id' => $id]);
        }
        $project = kb_api_rpc($email, 'getProjectById', ['project_id' => $id]);
        public_api_json(['ok' => true, 'project' => kb_api_project($project, $credsProbe)]);
    }
    if ($method === 'GET' && $sub === 'board') {
        $project = kb_api_rpc($email, 'getProjectById', ['project_id' => $id]);
        $board = (array) kb_api_rpc($email, 'getBoard', ['project_id' => $id]);
        public_api_json([
            'ok' => true,
            'project' => kb_api_project($project, $credsProbe),
            'swimlanes' => array_map(static function ($s) use ($credsProbe) {
                return [
                    'id' => (int) ($s['id'] ?? 0),
                    'name' => (string) ($s['name'] ?? ''),
                    'columns' => array_map(static function ($c) use ($credsProbe) {
                        return [
                            'id' => (int) ($c['id'] ?? 0),
                            'title' => (string) ($c['title'] ?? ''),
                            'task_limit' => (int) ($c['task_limit'] ?? 0) ?: null,
                            'tasks' => array_map(
                                static fn($t) => kb_api_task($t + ['column_title' => $c['title'] ?? ''], $credsProbe),
                                (array) ($c['tasks'] ?? [])
                            ),
                        ];
                    }, (array) ($s['columns'] ?? [])),
                ];
            }, $board),
        ]);
    }
    if ($method === 'GET' && $sub === 'columns') {
        [, $list] = kb_api_columns($email, $id);
        public_api_json(['ok' => true, 'columns' => $list]);
    }
    if ($method === 'GET' && $sub === 'members') {
        $users = (array) kb_api_rpc($email, 'getProjectUsers', ['project_id' => $id]);
        $out = [];
        foreach ($users as $uid => $uname) {
            if (is_array($uname)) {
                $out[] = [
                    'id' => (int) ($uname['id'] ?? $uid),
                    'username' => $uname['username'] ?? null,
                    'name' => $uname['name'] ?? null,
                    'email' => $uname['email'] ?? null,
                    'role' => $uname['role'] ?? null,
                ];
            } else {
                $out[] = ['id' => (int) $uid, 'username' => (string) $uname, 'name' => (string) $uname, 'email' => null, 'role' => null];
            }
        }
        public_api_json(['ok' => true, 'members' => $out]);
    }
    if ($method === 'GET' && $sub === 'stats') {
        public_api_json(['ok' => true, 'stats' => kb_api_stats($email, $id)]);
    }
    if ($method === 'GET' && $sub === 'tasks') {
        $status = strtolower((string) ($_GET['status'] ?? 'open'));
        $statusId = $status === 'closed' ? 0 : 1;
        [$colMap] = kb_api_columns($email, $id);
        $tasks = (array) kb_api_rpc($email, 'getAllTasks', ['project_id' => $id, 'status_id' => $statusId]);
        public_api_json([
            'ok' => true,
            'tasks' => array_map(static fn($t) => kb_api_task($t, $credsProbe, $colMap), $tasks),
        ]);
    }
    if ($method === 'POST' && $sub === 'tasks') {
        $fields = kb_api_task_fields($body);
        if (($fields['title'] ?? '') === '') {
            public_api_json(['ok' => false, 'error' => 'title_required'], 400);
        }
        if (!isset($fields['owner_id'])) {
            $me = kb_api_rpc($email, 'getMe');
            $fields['owner_id'] = (int) ($me['id'] ?? 0);
        }
        $params = array_merge($fields, ['project_id' => $id]);
        $taskId = (int) kb_api_rpc($email, 'createTask', $params);
        public_api_json(['ok' => true, 'task' => kb_api_load_task($email, $taskId)], 201);
    }
}

if ($resource === 'tasks' && $id > 0) {
    if ($method === 'GET' && $sub === '') {
        public_api_json(['ok' => true, 'task' => kb_api_load_task($email, $id, true)]);
    }
    if (($method === 'PATCH' || $method === 'PUT') && $sub === '') {
        $fields = kb_api_task_fields($body);
        if ($fields) {
            kb_api_rpc($email, 'updateTask', array_merge(['id' => $id], $fields));
        }
        if (isset($body['column_id']) || isset($body['swimlane_id']) || isset($body['position'])) {
            $task = kb_api_rpc($email, 'getTask', ['task_id' => $id]);
            $target = [
                'column_id' => (int) ($body['column_id'] ?? $task['column_id']),
                'swimlane_id' => (int) ($body['swimlane_id'] ?? $task['swimlane_id']),
                'position' => (int) ($body['position'] ?? 1),
            ];
            $unchanged =
                $target['column_id'] === (int) $task['column_id']
                && $target['swimlane_id'] === (int) $task['swimlane_id']
                && (!isset($body['position']) || $target['position'] === (int) $task['position']);
            if (!$unchanged) {
                kb_api_rpc($email, 'moveTaskPosition', array_merge([
                    'project_id' => (int) $task['project_id'],
                    'task_id' => $id,
                ], $target));
            }
        }
        if (($body['status'] ?? null) === 'closed') {
            kb_api_rpc($email, 'closeTask', ['task_id' => $id]);
        }
        if (($body['status'] ?? null) === 'open') {
            kb_api_rpc($email, 'openTask', ['task_id' => $id]);
        }
        public_api_json(['ok' => true, 'task' => kb_api_load_task($email, $id)]);
    }
    if ($method === 'DELETE' && $sub === '') {
        kb_api_rpc($email, 'removeTask', ['task_id' => $id]);
        public_api_json(['ok' => true, 'deleted' => $id]);
    }
    if ($method === 'GET' && $sub === 'comments') {
        $comments = (array) kb_api_rpc($email, 'getAllComments', ['task_id' => $id]);
        public_api_json([
            'ok' => true,
            'comments' => array_map(static function ($c) {
                return [
                    'id' => (int) ($c['id'] ?? 0),
                    'author' => $c['name'] ?? $c['username'] ?? null,
                    'user_id' => (int) ($c['user_id'] ?? 0),
                    'date' => kb_api_iso($c['date_creation'] ?? 0),
                    'content' => (string) ($c['comment'] ?? ''),
                ];
            }, $comments),
        ]);
    }
    if ($method === 'POST' && $sub === 'comments') {
        $content = trim((string) ($body['content'] ?? ''));
        if ($content === '') {
            public_api_json(['ok' => false, 'error' => 'content_required'], 400);
        }
        $me = kb_api_rpc($email, 'getMe');
        $commentId = (int) kb_api_rpc($email, 'createComment', [
            'task_id' => $id,
            'user_id' => (int) ($me['id'] ?? 0),
            'content' => $content,
        ]);
        public_api_json(['ok' => true, 'comment_id' => $commentId], 201);
    }
}

public_api_json(['ok' => false, 'error' => 'not_found'], 404);
