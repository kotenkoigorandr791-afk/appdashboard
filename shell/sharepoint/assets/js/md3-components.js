/* Material Design 3 — component behaviors */
(function () {
  'use strict';

  const Ripple = {
    init(element) {
      if (!element || element.dataset.md3Ripple === '1') return;
      element.dataset.md3Ripple = '1';
      element.addEventListener('pointerdown', function (event) {
        const rect = element.getBoundingClientRect();
        const size = Math.max(rect.width, rect.height);
        const ripple = document.createElement('span');
        ripple.className = 'md3-ripple';
        ripple.style.width = ripple.style.height = size + 'px';
        ripple.style.left = (event.clientX - rect.left - size / 2) + 'px';
        ripple.style.top = (event.clientY - rect.top - size / 2) + 'px';
        element.appendChild(ripple);
        ripple.addEventListener('animationend', function () { ripple.remove(); });
      });
    }
  };

  const Dialog = {
    open(selector) {
      const dialog = typeof selector === 'string' ? document.querySelector(selector) : selector;
      if (!dialog) return;
      dialog.classList.add('md3-dialog--open');
      document.body.style.overflow = 'hidden';
      const focusable = dialog.querySelector('[autofocus], input, button, select, textarea, a[href]');
      if (focusable) setTimeout(function () { focusable.focus(); }, 30);
    },
    close(selector) {
      const dialog = typeof selector === 'string' ? document.querySelector(selector) : selector;
      if (!dialog) return;
      dialog.classList.remove('md3-dialog--open');
      if (!document.querySelector('.md3-dialog--open')) document.body.style.overflow = '';
    },
    init() {
      document.querySelectorAll('.md3-dialog').forEach(function (dialog) {
        if (dialog.dataset.md3Dialog === '1') return;
        dialog.dataset.md3Dialog = '1';
        dialog.querySelectorAll('.md3-dialog__scrim, [data-md3-dialog-close]').forEach(function (target) {
          target.addEventListener('click', function () { Dialog.close(dialog); });
        });
        dialog.addEventListener('keydown', function (event) {
          if (event.key === 'Escape') Dialog.close(dialog);
        });
      });
      if (!document.__md3DialogEsc) {
        document.__md3DialogEsc = true;
        document.addEventListener('keydown', function (event) {
          if (event.key === 'Escape') {
            const open = document.querySelectorAll('.md3-dialog--open');
            if (open.length) Dialog.close(open[open.length - 1]);
          }
        });
      }
    }
  };

  const Menu = {
    toggle(trigger, menu) {
      const isOpen = menu.classList.contains('md3-menu--open');
      Menu.closeAll();
      if (isOpen) return;
      const rect = trigger.getBoundingClientRect();
      menu.style.position = 'fixed';
      menu.style.top = Math.min(window.innerHeight - 8, rect.bottom + 4) + 'px';
      const width = menu.offsetWidth || 220;
      menu.style.left = Math.max(8, Math.min(window.innerWidth - width - 8, rect.right - width)) + 'px';
      menu.classList.add('md3-menu--open');
      trigger.setAttribute('aria-expanded', 'true');
    },
    closeAll() {
      document.querySelectorAll('.md3-menu--open').forEach(function (menu) {
        menu.classList.remove('md3-menu--open');
      });
      document.querySelectorAll('[data-md3-menu-trigger][aria-expanded="true"]').forEach(function (trigger) {
        trigger.setAttribute('aria-expanded', 'false');
      });
    },
    init() {
      document.querySelectorAll('[data-md3-menu-trigger]').forEach(function (trigger) {
        if (trigger.dataset.md3MenuInit === '1') return;
        trigger.dataset.md3MenuInit = '1';
        trigger.setAttribute('aria-expanded', 'false');
        trigger.addEventListener('click', function (event) {
          event.preventDefault();
          event.stopPropagation();
          const menu = document.getElementById(trigger.dataset.md3MenuTrigger);
          if (menu) Menu.toggle(trigger, menu);
        });
      });
      document.addEventListener('click', function (event) {
        if (!event.target.closest('.md3-menu')) Menu.closeAll();
      });
      document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') Menu.closeAll();
      });
    }
  };

  function initAll() {
    document.querySelectorAll('.md3-btn, .md3-menu__item, .md3-navigation-drawer__item, .md3-top-app-bar__action, .md3-fab').forEach(Ripple.init);
    Dialog.init();
    Menu.init();
  }

  window.MD3Ripple = Ripple;
  window.MD3Dialog = Dialog;
  window.MD3Menu = Menu;
  window.MD3Init = initAll;

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAll);
  } else {
    initAll();
  }
})();
