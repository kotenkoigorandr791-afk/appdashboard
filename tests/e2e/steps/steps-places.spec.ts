import { expect, test, type Page } from '@playwright/test';
import { execSync } from 'node:child_process';
import { randomUUID } from 'node:crypto';
import { login } from '../helpers/shell';

const USER_EMAIL = 'root@root';
const STEPS_PATH = '/apps/steps/index.php?lang=en&theme=light&color=%23007bff';
const MARK = 'qa-e2e-place';

function sql(query: string): string {
  return execSync(
    `docker exec appdashboard-db psql -U appdashboard -d appdashboard -t -A -c "${query.replace(/"/g, '\\"')}"`,
    { stdio: 'pipe' },
  ).toString().trim();
}

function cleanup() {
  sql(`delete from geo_visit_candidates where visit_id in (select id from geo_visits where user_email='${USER_EMAIL}' and display_name like '${MARK}%')`);
  sql(`delete from geo_visits where user_email='${USER_EMAIL}' and (display_name like '${MARK}%' or display_name='QA Bakery E2E')`);
  sql(`delete from geo_stops where user_email='${USER_EMAIL}' and override_json->>'qa'='${MARK}'`);
  sql(`delete from geo_places where user_email='${USER_EMAIL}' and name like '${MARK}%'`);
}

async function openMap(page: Page) {
  await login(page);
  await page.goto(STEPS_PATH, { waitUntil: 'domcontentloaded' });
  await expect(page.locator('#calendar')).toBeVisible({ timeout: 20000 });
  await page.locator('[data-mode="map"]').first().click();
  await expect(page.locator('#map-view')).toBeVisible();
  await expect(page.locator('#map-view .leaflet-container')).toBeVisible({ timeout: 20000 });
}

test.describe('steps map place detection', () => {
  test.beforeAll(() => {
    cleanup();
    const stop = randomUUID();
    const visit = randomUUID();
    const cand1 = randomUUID();
    const cand2 = randomUUID();
    sql(`insert into geo_stops(id,user_email,started_at,ended_at,position,radius_m,confidence,state,override_json) values('${stop}','${USER_EMAIL}',now()-interval '2 hours',now()-interval '1 hour',ST_SetSRID(ST_MakePoint(19.945,50.0647),4326),50,0.8,'ready','{"qa":"${MARK}"}')`);
    sql(`insert into geo_visits(id,user_email,stop_id,arrived_at,departed_at,confidence,state,display_name,category,detection_status,detection_method,osm_type,osm_id) values('${visit}','${USER_EMAIL}','${stop}',now()-interval '2 hours',now()-interval '1 hour',0.55,'ready','${MARK} cafe','cafe','provisional','osm_overpass','node',990000001)`);
    sql(`insert into geo_visit_candidates(id,visit_id,user_email,osm_type,osm_id,rank,score,selected,details_json) values('${cand1}','${visit}','${USER_EMAIL}','node',990000001,1,0.55,true,'{"name":"${MARK} cafe","category":"cafe","distance_m":5}'),('${cand2}','${visit}','${USER_EMAIL}','node',990000002,2,0.4,false,'{"name":"QA Bakery E2E","category":"bakery","distance_m":20}')`);
  });
  test.afterAll(cleanup);

  test('right click menu, place creation and visit correction', async ({ page }) => {
    await openMap(page);
    const container = page.locator('#map-view .leaflet-container').first();
    await container.click({ button: 'right', position: { x: 200, y: 200 } });
    const menu = page.locator('.map-ctx-menu');
    await expect(menu).toBeVisible();
    await expect(menu.locator('button')).toHaveCount(4);
    await menu.getByRole('menuitem', { name: /Add place here/ }).click();
    const dialog = page.locator('.visit-modal-box');
    await expect(dialog).toBeVisible();
    await dialog.locator('input[type="text"]').fill(`${MARK} home`);
    await dialog.locator('select').selectOption('favorite');
    await dialog.getByRole('button', { name: 'Save' }).click();
    await expect(dialog).toBeHidden();
    await expect.poll(() => sql(`select count(*) from geo_places where user_email='${USER_EMAIL}' and name='${MARK} home' and tag='favorite'`)).toBe('1');

    await container.click({ button: 'right', position: { x: 220, y: 220 } });
    await menu.getByRole('menuitem', { name: /Select points nearby/ }).click();
    await expect(page.locator('#ma-area-info')).toBeVisible();
    await page.locator('#ma-area-cancel').click();

    const row = page.locator('#ma-visits-list .visit-row', { hasText: `${MARK} cafe` });
    await expect(row).toBeVisible({ timeout: 15000 });
    await row.click();
    await expect(dialog).toBeVisible();
    await expect(dialog.locator('.visit-cand')).toHaveCount(2);
    await dialog.locator('.visit-cand', { hasText: 'QA Bakery E2E' }).click();
    await expect(dialog).toBeHidden();
    await expect.poll(() => sql(`select detection_status||'|'||display_name from geo_visits where user_email='${USER_EMAIL}' and display_name='QA Bakery E2E'`)).toBe('manual|QA Bakery E2E');
  });
});
