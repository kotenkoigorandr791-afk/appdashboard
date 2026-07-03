function initTopbarUI(i18n) {
  const infoModal = document.getElementById('info-modal');
  const aboutButton = document.getElementById('btn-about-filespace');
  const chatButton = document.getElementById('btn-group-chat');
  const infoCloseButton = document.getElementById('btn-close-info');
  const searchInput = document.getElementById('search-input');
  const clearSearchButton = document.getElementById('btn-clear-search');
  const settingsButton = document.getElementById('btn-settings');
  const settingsMenu = document.getElementById('settings-menu');
  let settingsModal = document.getElementById('settings-modal');
  if (settingsMenu && !settingsModal) {
    settingsModal = document.createElement('div');
    settingsModal.id = 'settings-modal';
    settingsModal.className = 'modal hidden';
    document.body.appendChild(settingsModal);
    settingsMenu.classList.remove('hidden');
    settingsModal.appendChild(settingsMenu);
  }
  const closeSettingsButton = document.getElementById('btn-close-settings-menu');
  function closeSettingsMenu() {
    settingsModal?.classList.add('hidden');
  }
  window.closeSettingsMenu = closeSettingsMenu;
  const hideLangSwitcherToggle = document.getElementById('hide-lang-switcher-toggle');
  const topbarSearchHost = document.getElementById('topbar-search-host');
  const topbarActions = document.getElementById('topbar-actions');
  const desktopTreeSearchHost = document.getElementById('desktop-tree-search-host');
  const desktopRootToolbarFooter = document.getElementById('desktop-root-toolbar-footer');
  const searchContainer = document.querySelector('.search-container');
  const langSwitcher = document.querySelector('.lang-switcher');
  const settingsContainer = settingsButton?.closest('.settings');

  function syncResponsiveChrome() {
    const isDesktop = window.innerWidth >= 769;
    if (searchContainer && topbarSearchHost && desktopTreeSearchHost) {
      const searchTarget = isDesktop ? desktopTreeSearchHost : topbarSearchHost;
      if (searchContainer.parentElement !== searchTarget) {
        searchTarget.appendChild(searchContainer);
      }
    }

    if (topbarActions && desktopRootToolbarFooter) {
      const actionTarget = isDesktop ? desktopRootToolbarFooter : topbarActions;
      [langSwitcher, chatButton, settingsContainer].forEach((node) => {
        if (node && node.parentElement !== actionTarget) {
          actionTarget.appendChild(node);
        }
      });
    }
  }

  function showInfo() {
    closeSettingsMenu();
    infoModal?.querySelectorAll('.info-tile.expanded').forEach((tile) => tile.classList.remove('expanded'));
    infoModal?.querySelector('.modal-body')?.scrollTo?.({ top: 0 });
    infoModal?.classList.remove('hidden');
  }

  function closeInfo() {
    infoModal?.classList.add('hidden');
  }

  infoModal?.addEventListener('click', (event) => {
    if (event.target === infoModal) {
      closeInfo();
    }
  });
  aboutButton?.addEventListener('click', showInfo);
  infoCloseButton?.addEventListener('click', closeInfo);
  searchInput?.addEventListener('input', handleSearch);
  clearSearchButton?.addEventListener('click', clearSearch);
  syncResponsiveChrome();
  window.addEventListener('resize', syncResponsiveChrome);

  document.querySelectorAll('.info-tile-header').forEach((header) => {
    header.addEventListener('click', () => {
      const tile = header.closest('.info-tile');
      const willOpen = !tile?.classList.contains('expanded');
      document.querySelectorAll('#info-modal .info-tile.expanded').forEach((other) => {
        if (other !== tile) other.classList.remove('expanded');
      });
      tile?.classList.toggle('expanded', willOpen);
    });
    header.addEventListener('keydown', (event) => {
      if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        header.click();
      }
    });
  });

  if (window.initSyncLogModal) {
    window.initSyncLogModal(i18n);
  }
  if (window.initSyncTokenModal) {
    window.initSyncTokenModal(i18n);
  }

  settingsButton?.addEventListener('click', (event) => {
    event.stopPropagation();
    event.preventDefault();
    if (!settingsModal) return;
    const isHidden = settingsModal.classList.contains('hidden');
    closeAllDropdowns();
    if (!isHidden) {
      closeSettingsMenu();
      return;
    }
    settingsModal.classList.remove('hidden');
    syncSettingsControls();
  });
  closeSettingsButton?.addEventListener('click', closeSettingsMenu);
  settingsModal?.addEventListener('click', (event) => {
    if (event.target === settingsModal) closeSettingsMenu();
  });
  document.addEventListener('click', (event) => {
    if (settingsModal && !settingsModal.classList.contains('hidden') && !settingsModal.contains(event.target) && !settingsButton?.contains(event.target)) {
      closeSettingsMenu();
    }
  });
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && settingsModal && !settingsModal.classList.contains('hidden')) {
      closeSettingsMenu();
    }
  });

  if (hideLangSwitcherToggle) {
    hideLangSwitcherToggle.addEventListener('change', updateLangSwitcherVisibilitySetting);
  }
}
