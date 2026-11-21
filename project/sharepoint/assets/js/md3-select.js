/* Material Design 3 — custom dropdown for every .md3-select.
 * The native <select> stays in the DOM (value, change events, forms) but is
 * visually replaced by a themed trigger and a paged option menu. */
(function () {
  'use strict';

  var PAGE_SIZE = 10;
  var open = null;

  function t(key, fallback) {
    var dict = window.SP_I18N || {};
    return dict[key] || fallback;
  }

  function closeAll(keep) {
    if (open && open !== keep) open.close();
  }

  function optionsOf(select) {
    return Array.prototype.slice.call(select.options);
  }

  function syncLabel(state) {
    var selected = state.select.selectedOptions[0];
    state.label.textContent = selected ? selected.textContent.trim() : '';
    state.trigger.disabled = state.select.disabled;
  }

  function enhance(select) {
    if (!select || select.dataset.spSelect === '1' || select.multiple) return;
    if (select.getAttribute('aria-hidden') === 'true' || select.hidden) return;
    select.dataset.spSelect = '1';

    var wrap = document.createElement('span');
    wrap.className = 'sp-select';
    select.parentNode.insertBefore(wrap, select);
    wrap.appendChild(select);
    select.classList.add('sp-select__native');

    var trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'sp-select__trigger';
    trigger.setAttribute('aria-haspopup', 'listbox');
    trigger.setAttribute('aria-expanded', 'false');

    var label = document.createElement('span');
    label.className = 'sp-select__label';
    var icon = document.createElement('span');
    icon.className = 'material-symbols-outlined sp-select__icon';
    icon.setAttribute('aria-hidden', 'true');
    icon.textContent = 'expand_more';
    trigger.appendChild(label);
    trigger.appendChild(icon);
    wrap.appendChild(trigger);

    var menu = document.createElement('div');
    menu.className = 'sp-select__menu';
    menu.hidden = true;
    var list = document.createElement('div');
    list.className = 'sp-select__list';
    list.setAttribute('role', 'listbox');
    var pager = document.createElement('div');
    pager.className = 'sp-select__pager';
    pager.hidden = true;
    menu.appendChild(list);
    menu.appendChild(pager);
    // On <body>, so a transformed/filtered ancestor cannot offset the menu.
    document.body.appendChild(menu);

    var state = {
      select: select,
      wrap: wrap,
      trigger: trigger,
      label: label,
      menu: menu,
      list: list,
      pager: pager,
      page: 0,
      close: function () {
        wrap.classList.remove('is-open');
        trigger.setAttribute('aria-expanded', 'false');
        menu.hidden = true;
        if (open === state) open = null;
      },
      position: function () {
        var rect = trigger.getBoundingClientRect();
        var margin = 8;
        var preferred = Math.min(320, window.innerHeight * 0.5);
        var below = window.innerHeight - rect.bottom - margin;
        var above = rect.top - margin;
        var up = below < Math.min(180, preferred) && above > below;
        var available = Math.max(140, up ? above : below);
        menu.style.maxHeight = Math.min(preferred, available) + 'px';
        menu.style.width = rect.width + 'px';
        menu.style.left = Math.max(margin, Math.min(rect.left, window.innerWidth - rect.width - margin)) + 'px';
        menu.style.top = up
          ? Math.max(margin, rect.top - Math.min(menu.offsetHeight || preferred, available) - 6) + 'px'
          : (rect.bottom + 6) + 'px';
      },
      render: function (focusIndex) {
        var options = optionsOf(select);
        var pages = Math.max(1, Math.ceil(options.length / PAGE_SIZE));
        state.page = Math.max(0, Math.min(state.page, pages - 1));
        var start = state.page * PAGE_SIZE;
        list.replaceChildren();
        options.slice(start, start + PAGE_SIZE).forEach(function (option, index) {
          var item = document.createElement('button');
          item.type = 'button';
          item.className = 'sp-select__option';
          item.setAttribute('role', 'option');
          item.setAttribute('aria-selected', option.value === select.value ? 'true' : 'false');
          item.disabled = option.disabled;
          var text = document.createElement('span');
          text.className = 'sp-select__option-label';
          text.textContent = option.textContent.trim();
          item.appendChild(text);
          if (option.value === select.value) {
            var check = document.createElement('span');
            check.className = 'material-symbols-outlined sp-select__check';
            check.setAttribute('aria-hidden', 'true');
            check.textContent = 'check';
            item.appendChild(check);
          }
          item.addEventListener('click', function () {
            if (select.value !== option.value) {
              select.value = option.value;
              select.dispatchEvent(new Event('change', { bubbles: true }));
            }
            syncLabel(state);
            state.close();
            trigger.focus();
          });
          if (focusIndex !== undefined && start + index === focusIndex) item.classList.add('is-active');
          list.appendChild(item);
        });
        pager.hidden = pages <= 1;
        if (pages > 1) {
          pager.replaceChildren();
          var prev = document.createElement('button');
          prev.type = 'button';
          prev.className = 'sp-select__page';
          prev.textContent = '‹';
          prev.setAttribute('aria-label', t('previous_page', 'Previous page'));
          prev.disabled = state.page === 0;
          prev.addEventListener('click', function () { state.page--; state.render(); });
          var status = document.createElement('span');
          status.className = 'sp-select__status';
          status.textContent = (state.page + 1) + ' / ' + pages;
          var next = document.createElement('button');
          next.type = 'button';
          next.className = 'sp-select__page';
          next.textContent = '›';
          next.setAttribute('aria-label', t('next_page', 'Next page'));
          next.disabled = state.page === pages - 1;
          next.addEventListener('click', function () { state.page++; state.render(); });
          pager.appendChild(prev);
          pager.appendChild(status);
          pager.appendChild(next);
        }
      },
      open: function () {
        closeAll(state);
        var options = optionsOf(select);
        var selectedIndex = options.findIndex(function (option) { return option.value === select.value; });
        state.page = selectedIndex >= 0 ? Math.floor(selectedIndex / PAGE_SIZE) : 0;
        wrap.classList.add('is-open');
        trigger.setAttribute('aria-expanded', 'true');
        menu.hidden = false;
        state.render(selectedIndex);
        state.position();
        requestAnimationFrame(function () {
          if (wrap.classList.contains('is-open')) state.position();
        });
        var active = list.querySelector('.sp-select__option.is-active');
        (active || trigger).focus({ preventScroll: true });
        open = state;
      }
    };

    trigger.addEventListener('click', function (event) {
      event.stopPropagation();
      if (wrap.classList.contains('is-open')) state.close(); else state.open();
    });
    trigger.addEventListener('keydown', function (event) {
      if (['ArrowDown', 'ArrowUp', 'Enter', ' '].indexOf(event.key) !== -1) {
        event.preventDefault();
        state.open();
      }
    });
    menu.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') {
        event.preventDefault();
        event.stopPropagation();
        state.close();
        trigger.focus();
      } else if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        event.preventDefault();
        var items = Array.prototype.slice.call(list.querySelectorAll('.sp-select__option:not(:disabled)'));
        var current = items.indexOf(document.activeElement);
        var next = Math.max(0, Math.min(items.length - 1, current + (event.key === 'ArrowDown' ? 1 : -1)));
        if (items[next]) items[next].focus();
      }
    });
    select.addEventListener('change', function () { syncLabel(state); });
    new MutationObserver(function () { syncLabel(state); })
      .observe(select, { childList: true, subtree: true, characterData: true });

    syncLabel(state);
  }

  function init() {
    document.querySelectorAll('.md3-select').forEach(enhance);
    new MutationObserver(function (mutations) {
      mutations.forEach(function (mutation) {
        mutation.addedNodes.forEach(function (node) {
          if (node.nodeType !== 1) return;
          if (node.matches && node.matches('.md3-select')) enhance(node);
          if (node.querySelectorAll) node.querySelectorAll('.md3-select').forEach(enhance);
        });
      });
    }).observe(document.body, { childList: true, subtree: true });
    document.addEventListener('mousedown', function (event) {
      if (!event.target.closest || (!event.target.closest('.sp-select') && !event.target.closest('.sp-select__menu'))) closeAll(null);
    }, true);
    window.addEventListener('scroll', function (event) {
      if (event.target instanceof Element && event.target.closest && event.target.closest('.sp-select__menu')) return;
      closeAll(null);
    }, true);
    window.addEventListener('resize', function () { closeAll(null); });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
