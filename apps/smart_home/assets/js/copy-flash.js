(function () {
  const OK = '<svg class="cf__ic cf__ic--ok" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 4.5 4.5L19 7"/></svg>';
  window.copyFlash = function (btn) {
    if (!btn || btn._cfBusy) return;
    btn._cfBusy = true;
    const svg = btn.querySelector('svg');
    const done = () => { btn.classList.remove('copied'); btn._cfBusy = false; };
    if (!svg) {
      const prev = btn.textContent;
      btn.textContent = (window.SHAREPOINT_SHARE_I18N && window.SHAREPOINT_SHARE_I18N.copied) || 'Copied';
      btn.classList.add('copied');
      setTimeout(() => { btn.textContent = prev; done(); }, 1500);
      return;
    }
    if (!btn._cfInit) {
      btn._cfInit = true;
      (svg.closest('.sp-share-icon') || svg).classList.add('cf__ic', 'cf__ic--copy');
      btn.classList.add('cf');
      if (getComputedStyle(btn).position === 'static') btn.style.position = 'relative';
      btn.insertAdjacentHTML('beforeend', OK);
    }
    btn.classList.add('copied');
    setTimeout(done, 1500);
  };
})();
