import { expect, test, type Page, type FrameLocator } from '@playwright/test';
import { autoAcceptCookies, gotoShell, hasAuthUser, login } from './helpers/shell';

async function kbFrame(page: Page): Promise<FrameLocator> {
  const iframe = page.locator('#app-iframe');
  const ready = async () => {
    const src = (await iframe.getAttribute('src').catch(() => '')) || '';
    const visible = await iframe.isVisible().catch(() => false);
    return visible && /\/apps\/kanboard/.test(src);
  };
  for (let attempt = 0; attempt < 4; attempt++) {
    if (await ready()) break;
    await gotoShell(page, '/main?app=kanboard');
    const tile = page.locator('a.app-tile-link[href*="app=kanboard"]').first();
    if (await tile.isVisible().catch(() => false)) await tile.click().catch(() => {});
    await page.waitForTimeout(1000 * (attempt + 1));
  }
  if (!(await ready())) {
    // Direct iframe bootstrap as last resort.
    await page.evaluate(() => {
      const el = document.querySelector('#app-iframe') as HTMLIFrameElement | null;
      if (el) el.src = '/apps/kanboard/?controller=DashboardController&action=show';
    });
    await page.waitForTimeout(2000);
  }
  await expect
    .poll(async () => ((await ready()) ? 'ok' : 'wait'), { timeout: 60000 })
    .toBe('ok');
  const frame = page.frameLocator('#app-iframe');
  await expect(frame.locator('#kb-app-sidebar')).toBeVisible({ timeout: 60000 });
  return frame;
}

async function openFirstBoard(frame: FrameLocator) {
  const boardLinks = frame.locator('#kb-app-sidebar a[href*="BoardViewController"]');
  if (!(await boardLinks.count())) {
    const plus = frame.locator('header .header-creation-menu > a').first();
    if (await plus.isVisible().catch(() => false)) await plus.click();
    await frame.getByRole('link', { name: /New project/i }).first().click({ timeout: 15000 });
    await expect(frame.locator('#modal-content #project-creation-form')).toBeVisible({ timeout: 15000 });
    await frame.locator('#modal-content input[name="name"]').fill(`E2E Cycle ${Date.now()}`);
    const privateCb = frame.locator('#modal-content input[name="is_private"]');
    if (await privateCb.count()) {
      await privateCb.uncheck().catch(() => {});
    }
    await frame.locator('#modal-content button[type="submit"]').click();
    await expect(frame.locator('#kb-app-sidebar a[href*="BoardViewController"]').first()).toBeVisible({
      timeout: 45000,
    });
  }
  const href = await boardLinks.first().getAttribute('href');
  expect(href).toBeTruthy();
  await frame.locator('body').evaluate((_, h) => {
    location.assign(h as string);
  }, href);
  await expect(frame.locator('.kb-cycle-workspace').first()).toBeVisible({ timeout: 60000 });
}

test.describe('kanboard cycle workflows', () => {
  test.skip(!hasAuthUser(), 'E2E credentials required');
  test.describe.configure({ timeout: 120000 });

  test.beforeEach(async ({ page }) => {
    await autoAcceptCookies(page);
    await login(page);
    await gotoShell(page, '/main?app=kanboard');
    if (/\/login/.test(page.url())) {
      await login(page);
      await gotoShell(page, '/main?app=kanboard');
    }
  });

  test('views soft-nav tints spacing and dashboard chrome', async ({ page }) => {
    const frame = await kbFrame(page);
    const sidebar = frame.locator('#kb-app-sidebar');
    await expect(sidebar).toBeVisible();
    await openFirstBoard(frame);
    await expect(frame.locator('.kb-cycle-views')).toBeVisible();

    await sidebar.evaluate((el) => el.setAttribute('data-e2e-side', 'keep'));
    await frame.locator('a.kb-cycle-view-link[href*="view=table"]').click();
    await expect(frame.locator('#kb-view-table')).toBeVisible({ timeout: 20000 });
    await expect(frame.locator('#kb-view-table tr.kb-table-row[class*="kb-tint-"]').first()).toBeVisible();
    await expect(frame.locator('#kb-view-table .kb-st-icon').first()).toBeVisible();
    await expect(frame.locator('#kb-view-table .kb-prio-icon').first()).toBeVisible();
    await expect(frame.locator('#kb-view-table th.is-sortable')).toHaveCount(6);
    await expect(sidebar).toHaveAttribute('data-e2e-side', 'keep');

    const gaps = await frame.locator('#kb-view-table').evaluate((table) => {
      const wrap = table.closest('.kb-view-table-wrap') as HTMLElement | null;
      const side = document.getElementById('kb-app-sidebar');
      const cs = wrap ? getComputedStyle(wrap) : null;
      return {
        pad: cs ? parseFloat(cs.paddingLeft) + parseFloat(cs.paddingRight) : 0,
        sidePad: side ? parseFloat(getComputedStyle(side).paddingLeft) : 0,
        wrapWidth: wrap?.clientWidth || 0,
      };
    });
    expect(gaps.pad).toBeGreaterThanOrEqual(16);
    expect(gaps.sidePad).toBeGreaterThanOrEqual(4);
    expect(gaps.wrapWidth).toBeGreaterThan(200);

    await frame.locator('a.kb-cycle-view-link[href*="view=board"]').click();
    await expect(frame.locator('#kb-view-board')).toBeVisible({ timeout: 20000 });
    await expect(frame.locator('#kb-view-board .board-column-header[class*="kb-tint-"]').first()).toBeVisible({
      timeout: 10000,
    });
    await expect(sidebar).toHaveAttribute('data-e2e-side', 'keep');

    await frame.locator('a.kb-cycle-view-link[href*="view=list"]').click();
    await expect(frame.locator('.kb-issue-group, .kb-cycle-issues').first()).toBeVisible({ timeout: 20000 });

    const projectLinks = frame.locator('#kb-app-sidebar a[href*="BoardViewController"]');
    if ((await projectLinks.count()) >= 2) {
      await projectLinks.nth(1).click();
      await expect(frame.locator('.kb-cycle-workspace').first()).toBeVisible({ timeout: 30000 });
      await expect(sidebar).toHaveAttribute('data-e2e-side', 'keep');
    }

    await frame.locator('#kb-app-sidebar a[href*="DashboardController"][href*="action=show"]').first().click();
    await expect(frame.locator('.kb-page-toolbar, #dashboard').first()).toBeVisible({ timeout: 30000 });
    await expect(frame.locator('.kb-page-toolbar a:has-text("New project")')).toHaveCount(0);
    await expect(frame.locator('a.kb-sidebar-create')).toHaveCount(0);
    await expect(frame.locator('header .board-selector-container')).toBeHidden();
  });

  test('plane create modal and quick status/priority edits', async ({ page }) => {
    const frame = await kbFrame(page);
    await openFirstBoard(frame);
    await frame.locator('a.kb-cycle-view-link[href*="view=list"]').click();
    await expect(frame.locator('a.kb-cycle-new-task')).toBeVisible({ timeout: 20000 });
    await frame.locator('a.kb-cycle-new-task').click();
    const plane = frame.locator('#modal-content .kb-plane-create, .kb-plane-create');
    await expect(plane).toBeVisible({ timeout: 20000 });
    await expect(frame.locator('.kb-assignee-search')).toBeVisible();
    const title = `E2E Soft Task ${Date.now()}`;
    await frame.locator('.kb-plane-create input[name="title"], .kb-plane-create #form-title').first().fill(title);
    await frame.locator('.kb-plane-save').click();
    await expect(frame.getByText(title).first()).toBeVisible({ timeout: 45000 });

    await frame.locator('a.kb-cycle-view-link[href*="view=table"]').click();
    await expect(frame.locator('#kb-view-table')).toBeVisible({ timeout: 20000 });
    const statusSel = frame.locator('#kb-view-table select.kb-quick-select[data-field="column_id"]').first();
    if (await statusSel.isVisible().catch(() => false)) {
      const cur = await statusSel.inputValue();
      const opts = await statusSel.locator('option').evaluateAll((os) =>
        os.map((o) => (o as HTMLOptionElement).value),
      );
      const next = opts.find((v) => v !== cur) || cur;
      await statusSel.selectOption(next);
      await expect.poll(async () => statusSel.inputValue(), { timeout: 10000 }).toBe(next);
    }
  });

  test('member email autocomplete, assignable API, and search latency', async ({ page }) => {
    const frame = await kbFrame(page);
    await openFirstBoard(frame);
    const projectId = await frame.locator('body').evaluate(() => new URL(location.href).searchParams.get('project_id'));
    expect(projectId).toBeTruthy();

    const shortRes = await page.request.get(
      '/apps/kanboard/?controller=ProjectMemberController&action=search&plugin=AppDashboard&term=a',
      { headers: { 'X-Requested-With': 'XMLHttpRequest' } },
    );
    expect(shortRes.ok()).toBeTruthy();
    expect(await shortRes.json()).toEqual([]);

    const t0 = Date.now();
    const longRes = await page.request.get(
      '/apps/kanboard/?controller=ProjectMemberController&action=search&plugin=AppDashboard&term=codex-peer1',
      { headers: { 'X-Requested-With': 'XMLHttpRequest' } },
    );
    const long = await longRes.json();
    expect(longRes.ok()).toBeTruthy();
    expect(Array.isArray(long) ? long.length : -1).toBeGreaterThan(0);
    expect(Date.now() - t0).toBeLessThan(2000);

    await frame.locator('body').evaluate((_, id) => {
      location.assign(`/apps/kanboard/?controller=ProjectPermissionController&action=index&project_id=${id}`);
    }, projectId);
    await expect(frame.locator('input.kb-member-email').first()).toBeVisible({ timeout: 45000 });
    const input = frame.locator('input.kb-member-email').first();
    await input.click();
    await input.fill('a');
    await page.waitForTimeout(700);
    await expect(frame.locator('.ui-autocomplete:visible .ui-menu-item')).toHaveCount(0);
    if (!(await frame.locator('.kb-perm-table').textContent())?.includes('codex-peer1@example.com')) {
      await input.fill('codex-peer1');
      await expect(frame.locator('.ui-autocomplete:visible .ui-menu-item').first()).toBeVisible({ timeout: 15000 });
      await frame.locator('.ui-autocomplete:visible .ui-menu-item').first().click();
      await frame.locator('.kb-member-add-form button[type="submit"]').click();
    }
    await expect(frame.locator('.kb-perm-table')).toContainText(/codex-peer1@example.com/i, { timeout: 20000 });

    const emptyAssignable = await page.request.get(
      `/apps/kanboard/?controller=ProjectMemberController&action=searchAssignable&plugin=AppDashboard&project_id=${projectId}&term=a`,
      { headers: { 'X-Requested-With': 'XMLHttpRequest' } },
    );
    expect(emptyAssignable.ok()).toBeTruthy();
    expect(await emptyAssignable.json()).toEqual([]);

    const t1 = Date.now();
    const hitAssignable = await page.request.get(
      `/apps/kanboard/?controller=ProjectMemberController&action=searchAssignable&plugin=AppDashboard&project_id=${projectId}&term=codex-peer1`,
      { headers: { 'X-Requested-With': 'XMLHttpRequest' } },
    );
    const hit = await hitAssignable.json();
    expect(hitAssignable.ok()).toBeTruthy();
    expect(Array.isArray(hit) ? hit.length : 0).toBeGreaterThan(0);
    expect(Date.now() - t1).toBeLessThan(2000);
  });
});
