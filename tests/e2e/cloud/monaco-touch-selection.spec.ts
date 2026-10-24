import { test, expect, type Page } from '@playwright/test';
import { hasAuthUser, login, gotoShell } from '../helpers/shell';

const ROOT_KEY = 'temp';
const CONTENT = 'alpha bravo charlie delta echo\n\nzeta yankee xray\n';

type CloudFrame = ReturnType<Page['frameLocator']>;

test.describe.configure({ mode: 'serial' });

/**
 * Custom long-press selection on coarse pointers.
 *
 * Run with the mobile project so hasTouch/isMobile are on:
 *   npm run test -- --project=mobile-chrome-pixel-5 e2e/cloud/monaco-touch-selection.spec.ts
 */
test.describe('Monaco touch long-press selection', () => {
  const skipWithoutAuth = () => test.skip(!hasAuthUser(), 'Needs E2E_USER_EMAIL/PASSWORD');

  async function openEditorWithFixture(page: Page): Promise<{ frame: CloudFrame; fileName: string }> {
    const fileName = `e2e-touchsel-${Date.now()}-${Math.floor(Math.random() * 1e6)}.txt`;
    await login(page);
    await gotoShell(page, '/main?app=cloud');
    await page.waitForSelector('#app-iframe', { timeout: 30000 });
    const frame = page.frameLocator('#app-iframe');
    await frame.locator('#tree .node').first().waitFor({ timeout: 30000 });

    await frame.locator('body').evaluate(async (_el, { name, root, content }) => {
      const api = (window as any).api;
      await api('create_file', { path: name, root });
      await api('save_file', { path: name, root, content });
      await api('invalidate_file_tree', {}, 'POST');
    }, { name: fileName, root: ROOT_KEY, content: CONTENT });

    await frame.locator('body').evaluate((_el, root) => (window as any).switchCloudRoot(root), ROOT_KEY);
    const node = frame.locator(`#tree .node[data-path="${fileName}"]`).first();
    await node.waitFor({ timeout: 30000 });
    await node.click();
    await frame.locator('.monaco-editor textarea').first().waitFor({ timeout: 30000 });
    // The fixture content is already in the model; just let it render.
    await page.waitForTimeout(2500);

    return { frame, fileName };
  }

  /**
   * Long press at viewport coordinates: touchstart, hold past the delay, touchend.
   * The word selection happens in the touchstart timer, so no contextmenu is involved.
   */
  async function longPress(page: Page, frame: CloudFrame, x: number, y: number): Promise<{ started: boolean; ended: boolean; menuAllowed: boolean }> {
    const fire = (kind: 'touchstart' | 'touchend') =>
      frame.locator('body').evaluate((_el, payload) => {
        const { x, y, type } = payload as { x: number; y: number; type: string };
        const target = document.elementFromPoint(x, y) as HTMLElement | null;
        if (!target) return false;
        const touch = new Touch({
          identifier: 1,
          target,
          clientX: x,
          clientY: y,
          pageX: x,
          pageY: y,
          screenX: x,
          screenY: y,
        });
        if (type === 'touchstart') {
          target.dispatchEvent(
            new TouchEvent('touchstart', {
              bubbles: true,
              cancelable: true,
              touches: [touch],
              targetTouches: [touch],
              changedTouches: [touch],
            })
          );
        } else {
          target.dispatchEvent(
            new TouchEvent('touchend', {
              bubbles: true,
              cancelable: true,
              touches: [],
              targetTouches: [],
              changedTouches: [touch],
            })
          );
        }
        return true;
      }, { x, y, type: kind });

    const started = await fire('touchstart');
    // The editor requires a 400ms hold before it selects.
    await page.waitForTimeout(700);
    const ended = await fire('touchend');

    // The platform opens its menu on the contextmenu that follows a long press.
    // If the editor called preventDefault on it, the menu can never appear.
    const menuAllowed = await frame.locator('body').evaluate((_el, pt) => {
      const target = document.elementFromPoint(pt.x, pt.y) as HTMLElement | null;
      if (!target) return { allowed: false, why: 'no target' };
      const chain: string[] = [];
      // Record which element suppresses the menu.
      const probe = (e: Event) => { if (e.defaultPrevented) chain.push('prevented'); };
      target.addEventListener('contextmenu', probe, true);
      target.addEventListener('contextmenu', probe, false);
      const evt = new MouseEvent('contextmenu', {
        bubbles: true,
        cancelable: true,
        clientX: pt.x,
        clientY: pt.y,
      });
      target.dispatchEvent(evt);
      target.removeEventListener('contextmenu', probe, true);
      target.removeEventListener('contextmenu', probe, false);
      return {
        allowed: !evt.defaultPrevented,
        why: evt.defaultPrevented ? 'preventDefault by someone' : 'ok',
        tag: target.tagName,
        cls: (target.className || '').toString().slice(0, 40),
      };
    }, { x, y });

    return { started, ended, menuAllowed };
  }

  /**
   * Screen coordinates of a word inside the rendered editor.
   * Monaco emits one span per token, so the word is matched across spans
   * using the full line text and its character offsets.
   */
  function wordPoint(frame: CloudFrame, word: string) {
    return frame.locator('body').evaluate((_el, w) => {
      // wordWrap is on, so the word can sit on any rendered line.
      const lines = Array.from(
        document.querySelectorAll('.monaco-editor .view-lines .view-line')
      ) as HTMLElement[];
      for (const line of lines) {
        const idx = (line.textContent || '').indexOf(w);
        if (idx < 0) continue;

        const walker = document.createTreeWalker(line, NodeFilter.SHOW_TEXT);
        let offset = 0;
        let startNode: Text | null = null;
        let startOffset = 0;
        while (walker.nextNode()) {
          const node = walker.currentNode as Text;
          const len = node.textContent ? node.textContent.length : 0;
          if (startNode === null && idx < offset + len) {
            startNode = node;
            startOffset = idx - offset;
            break;
          }
          offset += len;
        }
        if (!startNode) continue;

        const range = document.createRange();
        range.setStart(startNode, startOffset);
        range.setEnd(startNode, startOffset + w.length);
        const rect = range.getBoundingClientRect();
        if (!rect.width) continue;
        return { x: rect.left + rect.width / 2, y: rect.top + rect.height / 2 };
      }
      return null;
    }, word);
  }

  function selectionState(frame: CloudFrame, fileName: string) {
    return frame.locator('body').evaluate((_el, name) => {
      const models = (window as any).monaco?.editor.getModels() || [];
      const model = models.find((m: any) => m.uri.path.endsWith(name));

      // Find the live editor instance bound to this model.
      const domNode = document.querySelector('.monaco-editor');
      let selectedText = '';
      let isEmpty = true;
      if (model && domNode) {
        const ed = (window as any).monaco.editor.getEditors
          ? (window as any).monaco.editor.getEditors().find((e: any) => e.getModel() === model)
          : null;
        if (ed) {
          const sel = ed.getSelection();
          isEmpty = sel ? sel.isEmpty() : true;
          if (sel) selectedText = model.getValueInRange(sel);
        }
      }

      const handles = document.querySelectorAll('.monaco-touch-selection-handles .word-handle');
      const visible = Array.from(handles).filter((h) => (h as HTMLElement).style.display !== 'none');
      return {
        selectedText,
        isEmpty,
        handleCount: handles.length,
        visibleHandles: visible.length,
      };
    }, fileName);
  }

  test('long press on a word selects it and shows custom handles', async ({ page }) => {
    skipWithoutAuth();
    const { frame, fileName } = await openEditorWithFixture(page);

    const pt = await wordPoint(frame, 'charlie');
    expect(pt, 'must locate "charlie" on screen').not.toBeNull();

    const press = await longPress(page, frame, pt!.x, pt!.y);
    expect(press.started, 'touchstart must reach the editor').toBe(true);
    await page.waitForTimeout(300);

    const state = await selectionState(frame, fileName);
    test.info().annotations.push({ type: 'note', description: JSON.stringify(state) });

    expect(state.isEmpty, 'selection must not be empty after long press').toBe(false);
    expect(state.selectedText).toContain('charlie');
    expect(state.visibleHandles, 'custom handles must be visible').toBeGreaterThan(0);
  });

  test('long press on an existing selection keeps it and does not re-select', async ({ page }) => {
    skipWithoutAuth();
    const { frame, fileName } = await openEditorWithFixture(page);

    // First long press creates a selection on "charlie".
    const pt = await wordPoint(frame, 'charlie');
    expect(pt, 'must locate "charlie" on screen').not.toBeNull();
    await longPress(page, frame, pt!.x, pt!.y);
    await page.waitForTimeout(300);

    const first = await selectionState(frame, fileName);
    expect(first.isEmpty, 'first long press must select').toBe(false);
    expect(first.selectedText).toContain('charlie');

    // Long press again on the same spot: the existing selection must survive,
    // and the editor must not hijack the gesture into a fresh word selection.
    const again = await longPress(page, frame, pt!.x, pt!.y);
    expect(again.started).toBe(true);
    await page.waitForTimeout(300);

    const second = await selectionState(frame, fileName);
    test.info().annotations.push({ type: 'note', description: JSON.stringify(second) });

    expect(second.selectedText, 'existing selection must be preserved').toBe(first.selectedText);
    expect(second.isEmpty, 'selection must not be dropped on the second press').toBe(false);
  });

  test('long press outside a word does not create a selection', async ({ page }) => {
    skipWithoutAuth();
    const { frame, fileName } = await openEditorWithFixture(page);

    // Target an empty line below the text: no word under the finger, but the
    // point is still inside the editor surface.
    const pt = await frame.locator('body').evaluate((_el, w) => {
      const lines = Array.from(
        document.querySelectorAll('.monaco-editor .view-lines .view-line')
      ) as HTMLElement[];
      if (!lines.some((l) => (l.textContent || '').includes(w))) return null;

      // Pick a rendered line that is completely empty (blank line in the file).
      const blank = lines.find((l) => (l.textContent || '').trim() === '');
      if (!blank) return null;
      const rect = blank.getBoundingClientRect();
      if (!rect.width || !rect.height) return null;
      return { x: rect.left + 4, y: rect.top + rect.height / 2 };
    }, 'charlie');
    test.skip(!pt, 'could not resolve an empty line');

    const wordUnder = await frame.locator('body').evaluate((_el, p) => {
      const els = (window as any).monaco.editor.getEditors ? (window as any).monaco.editor.getEditors() : [];
      const ed = els[0];
      if (!ed) return null;
      const h = ed.getTargetAtClientPoint(p.x, p.y);
      if (!h) return null;
      const w = ed.getModel().getWordAtPosition(h.position);
      return w ? ed.getModel().getValueInRange(w) : null;
    }, pt);
    expect(wordUnder, 'press point must be over whitespace').toBeNull();

    const press = await longPress(page, frame, pt!.x, pt!.y);
    test.skip(!press.started, 'press point is outside the editor surface');
    await page.waitForTimeout(300);

    const state = await selectionState(frame, fileName);
    expect(state.isEmpty, 'must not select when pressing outside a word').toBe(true);
    const menu = press.menuAllowed as unknown as { allowed: boolean; why: string; tag: string; cls: string };
    test.info().annotations.push({ type: 'note', description: JSON.stringify(menu) });
    console.log('MENU_PROBE', JSON.stringify(menu));
    expect(menu.allowed, `pressing outside a word must not suppress the native menu (${menu.why} on ${menu.tag}.${menu.cls})`).toBe(true);
  });
});