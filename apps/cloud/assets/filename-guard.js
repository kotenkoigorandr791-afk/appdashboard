(function () {
  const invalidChars = /[\\/:*?"<>|\x00-\x1f]/g;
  const invalidCharTest = /[\\/:*?"<>|\x00-\x1f]/;
  const reservedNames = /^(con|prn|aux|nul|com[1-9]|lpt[1-9])(?:\..*)?$/i;
  function sanitize(value) {
    return String(value || '').replace(invalidChars, '');
  }
  function validate(value, options = {}) {
    const name = String(value || '');
    if (!name.trim()) return { ok: false, code: 'empty' };
    if (invalidCharTest.test(name)) return { ok: false, code: 'invalid_chars' };
    if (name === '.' || name === '..') return { ok: false, code: 'dot_name' };
    if (/[. ]$/.test(name)) return { ok: false, code: 'trailing_dot_space' };
    if (!options.allowReserved && reservedNames.test(name)) return { ok: false, code: 'reserved' };
    return { ok: true };
  }
  function attach(input) {
    if (!input || input.dataset.filenameGuardAttached === '1') return;
    input.dataset.filenameGuardAttached = '1';
    input.addEventListener('beforeinput', (event) => {
      if (!event.data || event.inputType !== 'insertText') return;
      if (sanitize(event.data) !== event.data) event.preventDefault();
    });
    input.addEventListener('input', () => {
      const current = input.value;
      const clean = sanitize(current);
      if (current === clean) return;
      const pos = input.selectionStart || clean.length;
      input.value = clean;
      const nextPos = Math.max(0, pos - (current.length - clean.length));
      input.setSelectionRange(nextPos, nextPos);
    });
    input.addEventListener('paste', (event) => {
      const text = event.clipboardData?.getData('text');
      if (typeof text !== 'string') return;
      const clean = sanitize(text);
      if (clean === text) return;
      event.preventDefault();
      document.execCommand('insertText', false, clean);
    });
    input.addEventListener('drop', (event) => {
      const text = event.dataTransfer?.getData('text');
      if (typeof text !== 'string') return;
      const clean = sanitize(text);
      if (clean === text) return;
      event.preventDefault();
      input.focus();
      document.execCommand('insertText', false, clean);
    });
    if (window.CloudTransliteration && typeof window.CloudTransliteration.attachToInput === 'function') {
      window.CloudTransliteration.attachToInput(input);
    }
  }
  window.CloudFilenameGuard = { sanitize, validate, attach };
})();
