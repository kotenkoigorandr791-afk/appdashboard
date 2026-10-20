(function () {
  'use strict';

  var CHEVRON = '<svg class="ui-select-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>';
  var openMenu = null;
  var openButton = null;

  function closeOpenMenu(restoreFocus) {
    if (!openMenu) return;
    openMenu.hidden = true;
    if (openButton) {
      openButton.setAttribute('aria-expanded', 'false');
      if (restoreFocus) openButton.focus();
    }
    openMenu = null;
    openButton = null;
  }

  function positionMenu(button, menu) {
    var rect = button.getBoundingClientRect();
    var gap = 6;
    var width = Math.max(rect.width, 160);
    var left = Math.min(rect.left, window.innerWidth - width - 8);
    if (left < 8) left = 8;
    menu.style.width = width + 'px';
    menu.style.left = left + 'px';
    menu.style.top = (rect.bottom + gap) + 'px';
    var menuRect = menu.getBoundingClientRect();
    if (rect.bottom + gap + menuRect.height > window.innerHeight - 8) {
      var above = rect.top - gap - menuRect.height;
      menu.style.top = (above >= 8 ? above : Math.max(8, window.innerHeight - menuRect.height - 8)) + 'px';
    }
  }

  function buildSelect(select) {
    if (select.dataset.uiSelectReady === '1') return;
    if (select.multiple || select.size > 1) return;
    var options = Array.prototype.slice.call(select.options || []);
    if (options.length < 2) return;
    select.dataset.uiSelectReady = '1';

    var wrapper = document.createElement('span');
    wrapper.className = 'ui-select';
    if (select.className) wrapper.className += ' ' + select.className;
    select.parentNode.insertBefore(wrapper, select);
    wrapper.appendChild(select);
    select.setAttribute('tabindex', '-1');
    select.setAttribute('aria-hidden', 'true');

    var button = document.createElement('button');
    button.type = 'button';
    button.className = 'ui-select-btn';
    button.setAttribute('aria-haspopup', 'listbox');
    button.setAttribute('aria-expanded', 'false');
    var label = document.createElement('span');
    label.className = 'ui-select-label';
    button.appendChild(label);
    button.insertAdjacentHTML('beforeend', CHEVRON);
    wrapper.insertBefore(button, select);

    var menu = document.createElement('div');
    menu.className = 'ui-select-menu';
    menu.setAttribute('role', 'listbox');
    menu.hidden = true;
    document.body.appendChild(menu);

    function syncLabel() {
      var selected = select.options[select.selectedIndex];
      label.textContent = selected ? selected.textContent.trim() : '';
      menu.querySelectorAll('.ui-select-item').forEach(function (item) {
        item.setAttribute('aria-selected', item.dataset.value === select.value ? 'true' : 'false');
      });
    }

    function renderOptions() {
      menu.innerHTML = '';
      Array.prototype.forEach.call(select.options, function (option) {
        var item = document.createElement('button');
        item.type = 'button';
        item.className = 'ui-select-item';
        item.setAttribute('role', 'option');
        item.dataset.value = option.value;
        item.textContent = option.textContent.trim();
        item.addEventListener('click', function () {
          select.value = option.value;
          select.dispatchEvent(new Event('change', { bubbles: true }));
          syncLabel();
          closeOpenMenu(true);
        });
        menu.appendChild(item);
      });
      syncLabel();
    }

    renderOptions();

    button.addEventListener('click', function (event) {
      event.stopPropagation();
      if (!menu.hidden) {
        closeOpenMenu(false);
        return;
      }
      closeOpenMenu(false);
      menu.hidden = false;
      button.setAttribute('aria-expanded', 'true');
      openMenu = menu;
      openButton = button;
      positionMenu(button, menu);
      var active = menu.querySelector('.ui-select-item[aria-selected="true"]') || menu.querySelector('.ui-select-item');
      if (active) active.focus();
    });

    button.addEventListener('keydown', function (event) {
      if (event.key !== 'ArrowDown' && event.key !== 'ArrowUp') return;
      event.preventDefault();
      button.click();
    });

    menu.addEventListener('keydown', function (event) {
      var items = Array.prototype.slice.call(menu.querySelectorAll('.ui-select-item'));
      var index = items.indexOf(document.activeElement);
      if (event.key === 'Escape') {
        event.preventDefault();
        closeOpenMenu(true);
        return;
      }
      if (event.key !== 'ArrowDown' && event.key !== 'ArrowUp' && event.key !== 'Home' && event.key !== 'End') return;
      event.preventDefault();
      if (!items.length) return;
      if (event.key === 'Home') index = 0;
      else if (event.key === 'End') index = items.length - 1;
      else index = (index + (event.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length;
      items[index].focus();
    });

    select.addEventListener('change', syncLabel);

    var proto = Object.getPrototypeOf(select);
    try {
      var descriptor = Object.getOwnPropertyDescriptor(proto, 'value');
      if (descriptor && descriptor.get && descriptor.set) {
        Object.defineProperty(select, 'value', {
          configurable: true,
          get: function () { return descriptor.get.call(this); },
          set: function (value) {
            descriptor.set.call(this, value);
            syncLabel();
          }
        });
      }
    } catch (_) {}
  }

  function initSelects() {
    document.querySelectorAll('select:not([data-native-select])').forEach(buildSelect);
  }

  document.addEventListener('click', function (event) {
    if (!openMenu) return;
    if (openMenu.contains(event.target)) return;
    closeOpenMenu(false);
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && openMenu) closeOpenMenu(true);
  });

  window.addEventListener('resize', function () {
    if (openMenu && openButton) positionMenu(openButton, openMenu);
  });
  window.addEventListener('scroll', function () {
    if (openMenu && openButton) positionMenu(openButton, openMenu);
  }, true);

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initSelects);
  } else {
    initSelects();
  }
  window.initEditorUi = initSelects;
})();
