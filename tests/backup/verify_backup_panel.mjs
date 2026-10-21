/**
 * Verifies the restic backup panel is embedded inline in the Admin Panel
 * (no iframe) and that the main operations work through the UI.
 *
 * Requires a root/admin account (E2E_USER_EMAIL / E2E_USER_PASSWORD) whose
 * password also unlocks the Admin Panel gate.
 */
import { chromium } from 'playwright';
import { execSync } from 'node:child_process';
import fs from 'node:fs';

fs.mkdirSync('backup/artifacts', { recursive: true });

const BASE_URL = process.env.BASE_URL || 'http://localhost';
const EMAIL = process.env.E2E_USER_EMAIL || 'root@root';
const PASSWORD = process.env.E2E_USER_PASSWORD || '12345T';
const REPO = process.env.BACKUP_TEST_REPO || `/host/tmp/bkp-ui-repo-${Date.now()}`;
const SOURCE = '/host/tmp/bkp-ui-src';
const RESTORE = '/tmp/bkp-ui-restore';
const HOST_RESTORE = `/host${RESTORE}`;

let failures = 0;
function check(label, condition, detail = '') {
    if (condition) {
        console.log(`  ok   ${label}`);
    } else {
        failures++;
        console.log(`  FAIL ${label}${detail ? ` — ${detail}` : ''}`);
    }
}

const browser = await chromium.launch({ headless: true });
const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });

const page = await context.newPage();
page.on('dialog', async (dialog) => { await dialog.accept(); });
page.on('pageerror', (err) => console.error('  page error:', err.message));
page.on('response', async (res) => { if (res.status() >= 400) { let body = ''; try { body = (await res.text()).slice(0, 300); } catch (e) {} console.error('  http', res.status(), res.url(), body); } });

try {
    try {
        execSync(
            `docker exec appdashboard bash -lc ${JSON.stringify(
                `rm -rf ${REPO} ${HOST_RESTORE} && rm -f /var/www/AppDashboard/data/backup/jobs/* && mkdir -p ${SOURCE} && printf 'hello-from-host' > ${SOURCE}/hello.txt`
            )}`,
            { stdio: 'ignore' }
        );
    } catch (e) {
        console.error('setup failed:', e.message);
    }
    await page.goto(`${BASE_URL}/login`, { waitUntil: 'domcontentloaded' });
    await page.locator('#login-content input[name="email"]').fill(EMAIL);
    await page.locator('#login-password').fill(PASSWORD);
    await Promise.all([
        page.waitForURL((url) => !url.pathname.includes('/login'), { timeout: 30000 }),
        page.locator('#login-content button[name="login"]').click({ noWaitAfter: true }),
    ]);
    console.log('  ok   logged in');

    await page.goto(`${BASE_URL}/main`, { waitUntil: 'domcontentloaded' });
    // The cookie banner can re-appear mid-test and intercept clicks.
    await page.evaluate(() => {
        const kill = () => document.querySelectorAll('#cookie-consent-overlay,#cookie-consent-backdrop,#cookie-consent-modal').forEach((n) => n.remove());
        kill();
        new MutationObserver(kill).observe(document.documentElement, { childList: true, subtree: true });
    });
    await page.evaluate(() => document.getElementById('open-admin-panel-btn').click());
    await page.waitForSelector('#admin-panel-overlay.active', { timeout: 30000 });
    await page.waitForSelector('#ap-gate-password', { state: 'visible', timeout: 30000 });
    await page.fill('#ap-gate-password', PASSWORD);
    await page.click('#ap-gate-submit');
    await page.waitForSelector('#ap-dynamic-root [data-panel="ap-backup"]', { timeout: 30000 });
    await page.evaluate(() => {
        document.getElementById('cookie-consent-backdrop')?.remove();
        document.getElementById('cookie-consent-modal')?.remove();
    });
    await page.click('#ap-dynamic-root [data-panel="ap-backup"]');
    await page.waitForSelector('#ap-backup.active', { timeout: 30000 });
    await page.waitForSelector('#bdp-app', { timeout: 30000 });
    console.log('  ok   admin panel backup tab opened');

    check('backup panel is inline', (await page.locator('#ap-backup-frame').count()) === 0);
    check('iframe absent inside backup panel', (await page.locator('#ap-backup iframe').count()) === 0);
    check('title rendered', (await page.locator('#bdp-app .bdp-title').innerText()).trim().length > 0);
    const visibleText = await page.locator('#bdp-app').innerText();
    check('no untranslated backup_ keys', !/backup_[a-z_]+/.test(visibleText), (visibleText.match(/backup_[a-z_]+/g) || []).slice(0, 5).join(', '));
    const layout = await page.evaluate(() => {
        const app = document.querySelector('#bdp-app');
        const cs = getComputedStyle(app);
        return {
            padding: parseFloat(cs.paddingTop || '0'),
            scrollable: cs.overflowY === 'auto' && app.scrollHeight > app.clientHeight,
            noOverflowX: app.scrollWidth <= app.clientWidth + 1,
            pageFits: document.documentElement.scrollWidth <= window.innerWidth + 1,
        };
    });
    check('panel has inner spacing', layout.padding >= 12, String(layout.padding));
    check('panel scrolls vertically', layout.scrollable);
    check('panel does not overflow horizontally', layout.noOverflowX && layout.pageFits);
    await page.screenshot({ path: 'backup/artifacts/backup-e2e.png' });

    // Start from a clean slate: drop any configuration left by earlier runs.
    if ((await page.locator('[data-bdp-action="delete_config"]').count()) > 0) {
        await page.click('[data-bdp-action="delete_config"]');
        await page.waitForFunction(() => {
            const rows = document.querySelectorAll('#bdp-backups-body [data-bdp-profile]').length;
            return rows === 0;
        }, null, { timeout: 30000 }).catch(() => {});
    }

    // Repository (destination) configuration
    await page.fill('#bdp-repository', REPO);
    await page.fill('#bdp-repository-password', 'uipass');
    await page.fill('#bdp-host-label', 'ui-test');
    await page.click('[data-bdp-action="save_config"]');
    await page.waitForFunction(() => {
        const a = document.querySelector('#bdp-alert');
        return a && !a.hidden && /saved|сохран|zapis|ulož/i.test(a.textContent || '');
    }, null, { timeout: 30000 });
    check('settings saved via UI', true);

    // Initialize repository (confirm dialog auto-accepted)
    await page.click('[data-bdp-action="init"]');
    await page.waitForFunction(() => {
        const a = document.querySelector('#bdp-alert');
        return a && !a.hidden && /initial|инициализ|ініціал|ініцыял|zainicj|inicializ/i.test(a.textContent || '');
    }, null, { timeout: 60000 });
    check('repository initialized via UI', true);

    // Backups list is empty before the first wizard run
    check('backups list rendered', (await page.locator('#bdp-backups-card table').count()) > 0);

    // Create a backup through the step-by-step wizard
    await page.click('#bdp-backups-card [data-bdp-action="add_backup"]');
    await page.waitForFunction(() => {
        const w = document.querySelector('#bdp-wizard');
        return w && !w.hidden;
    }, null, { timeout: 30000 });
    check('wizard opened', true);
    check('wizard has 5 steps', (await page.locator('#bdp-wizard-steps .bdp-wizard-step').count()) === 5);

    // Step 1 — general
    await page.fill('#bdp-wz-name', 'UI test backup');
    await page.fill('#bdp-wz-description', 'created by e2e');
    await page.click('#bdp-wz-next');
    await page.waitForSelector('.bdp-wizard-panel.active[data-wizard-panel="1"]', { timeout: 10000 });
    check('wizard step 2 destination prefilled', (await page.inputValue('#bdp-wz-repository')) === REPO);
    await page.waitForFunction(() => document.querySelectorAll('[data-bdp-tree="dest"] .bdp-browse-item').length > 0, null, { timeout: 30000 });
    check('destination tree lists host folders', true);

    // Step 2 — destination
    await page.click('#bdp-wz-next');
    await page.waitForSelector('.bdp-wizard-panel.active[data-wizard-panel="2"]', { timeout: 10000 });
    await page.waitForFunction(() => document.querySelectorAll('[data-bdp-tree="src"] .bdp-browse-item').length > 0, null, { timeout: 30000 });
    check('source tree lists host folders', true);
    await page.fill('#bdp-wz-sources', SOURCE);
    await page.fill('#bdp-wz-excludes', '*.log');

    // Step 3 — schedule, Step 4 — options, then create
    await page.click('#bdp-wz-next');
    await page.waitForSelector('.bdp-wizard-panel.active[data-wizard-panel="3"]', { timeout: 10000 });
    await page.click('#bdp-wz-next');
    await page.waitForSelector('.bdp-wizard-panel.active[data-wizard-panel="4"]', { timeout: 10000 });
    check('wizard summary rendered', (await page.locator('#bdp-wizard-summary .bdp-summary-row').count()) > 0);
    await page.click('#bdp-wz-submit');
    await page.waitForFunction(() => {
        const rows = Array.from(document.querySelectorAll('#bdp-backups-body [data-bdp-profile]'));
        return rows.some((tr) => (tr.textContent || '').includes('UI test backup'));
    }, null, { timeout: 30000 });
    check('backup appears in the list', true);
    await page.screenshot({ path: 'backup/artifacts/backup-e2e-list.png' });

    // Run the backup from the list
    await page.click('#bdp-backups-body [data-bdp-action="run_backup"]');
    await page.waitForFunction(() => {
        const rows = Array.from(document.querySelectorAll('#bdp-jobs-body tr'));
        return rows.some((tr) => tr.textContent.includes('success') || tr.textContent.includes('Успешно') || tr.textContent.includes('Sukces') || tr.textContent.includes('Úspěch'));
    }, null, { timeout: 120000 });
    check('backup job completed via UI', true);

    // Snapshot listed
    await page.waitForFunction(() => document.querySelectorAll('#bdp-snapshots-body [data-snap-browse]').length > 0, null, { timeout: 30000 });
    check('snapshot listed via UI', true);

    // Browse snapshot contents (wait for the refresh timer to settle)
    await page.waitForTimeout(1200);
    await page.click('#bdp-snapshots-body [data-snap-browse]');
    await page.waitForFunction(() => {
        const modal = document.querySelector('#bdp-modal');
        return modal && !modal.hidden;
    }, null, { timeout: 30000 });
    await page.waitForFunction(() => document.querySelectorAll('#bdp-modal-body .bdp-file-row').length > 0, null, { timeout: 30000 });
    const modalText = await page.locator('#bdp-modal-body').innerText();
    check('snapshot browse shows file', modalText.includes('hello.txt'), modalText.slice(0, 200));

    // Restore from the browse modal
    await page.click('#bdp-modal-foot [data-bdp-action="restore_form"]');
    await page.waitForSelector('#bdp-restore-target', { timeout: 10000 });
    await page.fill('#bdp-restore-target', RESTORE);
    await page.click('[data-bdp-action="restore_submit"]');
    await page.waitForFunction(() => {
        const rows = Array.from(document.querySelectorAll('#bdp-jobs-body tr'));
        return rows.some((tr) => tr.textContent.includes('restore') && (tr.textContent.includes('success') || tr.textContent.includes('Успешно') || tr.textContent.includes('Sukces') || tr.textContent.includes('Úspěch')));
    }, null, { timeout: 120000 });
    let restored = '';
    try {
        restored = execSync(`docker exec appdashboard cat ${HOST_RESTORE}${SOURCE}/hello.txt`, { encoding: 'utf8' }).trim();
    } catch (e) {
        restored = '';
    }
    let hostRestored = false;
    try {
        execSync(`docker exec appdashboard test -f ${HOST_RESTORE}${SOURCE}/hello.txt`, { stdio: 'ignore' });
        hostRestored = true;
    } catch (e) {
        hostRestored = false;
    }
    check('restore target lands on the host filesystem', hostRestored, `${HOST_RESTORE}${SOURCE}/hello.txt`);
    check('restored file content matches', restored === 'hello-from-host', restored);

    // Restore a single file directly from the snapshot browser.
    const restoreRows = () => page.evaluate(() => Array.from(document.querySelectorAll('#bdp-jobs-body tr'))
        .filter((tr) => tr.textContent.includes('restore') && /success|успешно|sukces|úspěch/i.test(tr.textContent)).length);
    const baselineRestores = await restoreRows();
    await page.click('#bdp-snapshots-body [data-snap-browse]');
    await page.waitForFunction(() => {
        const modal = document.querySelector('#bdp-modal');
        return modal && !modal.hidden && modal.querySelectorAll('.bdp-file-row').length > 0;
    }, null, { timeout: 30000 });
    await page.click('#bdp-modal-body [data-snap-restore-file]');
    await page.waitForSelector('#bdp-restore-target', { timeout: 10000 });
    await page.fill('#bdp-restore-target', `${RESTORE}-file`);
    await page.click('[data-bdp-action="restore_submit"]');
    await page.waitForFunction((baseline) => {
        const done = Array.from(document.querySelectorAll('#bdp-jobs-body tr'))
            .filter((tr) => tr.textContent.includes('restore') && /success|успешно|sukces|úspěch/i.test(tr.textContent)).length;
        return done > baseline;
    }, baselineRestores, { timeout: 120000 });
    let singleFile = '';
    try {
        singleFile = execSync(`docker exec appdashboard cat ${HOST_RESTORE}-file${SOURCE}/hello.txt`, { encoding: 'utf8' }).trim();
    } catch (e) {
        singleFile = '';
    }
    check('single file restore from browser works', singleFile === 'hello-from-host', singleFile);

    // Delete the snapshot
    await page.click('#bdp-snapshots-body [data-snap-delete]');
    await page.waitForFunction(() => {
        const body = document.querySelector('#bdp-snapshots-body');
        return body && body.textContent.length > 0 && !body.querySelector('[data-snap-browse]');
    }, null, { timeout: 120000 });
    check('snapshot deleted via UI', true);

    // Stats + disk
    await page.click('[data-bdp-action="stats"]');
    await page.waitForFunction(() => document.querySelectorAll('#bdp-stats .bdp-stat').length >= 3, null, { timeout: 30000 });
    check('repository stats rendered', true);
    const disk = await page.locator('#bdp-disk-meta').innerText();
    check('disk space rendered', disk.length > 0, disk);

    // The backup profile can be removed from the list
    await page.click('#bdp-backups-body [data-bdp-action="delete_backup"]');
    await page.waitForFunction(() => document.querySelectorAll('#bdp-backups-body [data-bdp-profile]').length === 0, null, { timeout: 30000 });
    check('backup removed from the list', true);

    console.log(`\n${failures === 0 ? 'PASSED' : 'FAILED'} (${failures} failures)`);
    process.exitCode = failures === 0 ? 0 : 1;
} catch (err) {
    console.error('FAILED:', err);
    process.exitCode = 1;
} finally {
    await browser.close();
}
