import { expect, test } from '@playwright/test';
import { hasAuthUser } from './helpers/shell';

const DEVICE = 'a'.repeat(32);
const email = process.env.E2E_USER_EMAIL || '';
const password = process.env.E2E_USER_PASSWORD || '';

async function apiLogin(request: import('@playwright/test').APIRequestContext) {
  const res = await request.post('/public-api/auth/login', {
    headers: { 'Content-Type': 'application/json', 'X-Device-Id': DEVICE },
    data: { email, password, device_name: 'e2e-kanboard', platform: 'mobile' },
  });
  const body = await res.json();
  expect(res.ok(), JSON.stringify(body)).toBeTruthy();
  expect(body.token).toBeTruthy();
  return String(body.token);
}

function auth(token: string) {
  return {
    Authorization: `Bearer ${token}`,
    'X-Device-Id': DEVICE,
    'Content-Type': 'application/json',
  };
}

test.describe('kanboard public API (mobile)', () => {
  test.skip(!hasAuthUser(), 'E2E credentials required');
  test.describe.configure({ timeout: 90000 });

  test('projects board tasks stats and comments cover mobile flows', async ({ request }) => {
    const token = await apiLogin(request);
    const headers = auth(token);

    const index = await request.get('/public-api/kanboard', { headers });
    expect(index.ok()).toBeTruthy();
    const indexBody = await index.json();
    expect(indexBody.ok).toBeTruthy();
    expect(indexBody.endpoints.length).toBeGreaterThan(5);

    const me = await request.get('/public-api/kanboard/me', { headers });
    expect(me.ok()).toBeTruthy();
    const meBody = await me.json();
    expect(meBody.user?.id).toBeTruthy();

    const projectsRes = await request.get('/public-api/kanboard/projects', { headers });
    expect(projectsRes.ok()).toBeTruthy();
    const projectsBody = await projectsRes.json();
    expect(Array.isArray(projectsBody.projects)).toBeTruthy();
    expect(projectsBody.projects.length).toBeGreaterThan(0);
    const projectId = projectsBody.projects[0].id as number;

    const patch = await request.patch(`/public-api/kanboard/projects/${projectId}`, {
      headers,
      data: { description: `mobile api ${Date.now()}` },
    });
    expect(patch.ok()).toBeTruthy();
    expect((await patch.json()).project?.id).toBe(projectId);

    const board = await request.get(`/public-api/kanboard/projects/${projectId}/board`, { headers });
    expect(board.ok()).toBeTruthy();
    const boardBody = await board.json();
    expect(boardBody.swimlanes?.length).toBeGreaterThan(0);

    const columns = await request.get(`/public-api/kanboard/projects/${projectId}/columns`, { headers });
    expect(columns.ok()).toBeTruthy();
    const colId = (await columns.json()).columns?.[0]?.id;

    const stats = await request.get(`/public-api/kanboard/projects/${projectId}/stats`, { headers });
    expect(stats.ok()).toBeTruthy();
    const statsBody = await stats.json();
    expect(typeof statsBody.stats?.open).toBe('number');

    const title = `API Task ${Date.now()}`;
    const create = await request.post(`/public-api/kanboard/projects/${projectId}/tasks`, {
      headers,
      data: { title, description: 'mobile', priority: 2, column_id: colId },
    });
    expect(create.status()).toBe(201);
    const created = await create.json();
    const taskId = created.task?.id as number;
    expect(taskId).toBeTruthy();

    const getTask = await request.get(`/public-api/kanboard/tasks/${taskId}`, { headers });
    expect(getTask.ok()).toBeTruthy();
    expect((await getTask.json()).task?.title).toBe(title);

    const update = await request.patch(`/public-api/kanboard/tasks/${taskId}`, {
      headers,
      data: { priority: 3, status: 'open' },
    });
    expect(update.ok()).toBeTruthy();
    expect((await update.json()).task?.priority).toBe(3);

    const comment = await request.post(`/public-api/kanboard/tasks/${taskId}/comments`, {
      headers,
      data: { content: 'api comment' },
    });
    expect(comment.status()).toBe(201);

    const comments = await request.get(`/public-api/kanboard/tasks/${taskId}/comments`, { headers });
    expect(comments.ok()).toBeTruthy();
    expect((await comments.json()).comments?.some((c: { content: string }) => c.content === 'api comment')).toBeTruthy();

    const members = await request.get(`/public-api/kanboard/projects/${projectId}/members`, { headers });
    expect(members.ok()).toBeTruthy();

    const myTasks = await request.get('/public-api/kanboard/my-tasks', { headers });
    expect(myTasks.ok()).toBeTruthy();

    const del = await request.delete(`/public-api/kanboard/tasks/${taskId}`, { headers });
    expect(del.ok()).toBeTruthy();
    expect((await del.json()).deleted).toBe(taskId);
  });
});
