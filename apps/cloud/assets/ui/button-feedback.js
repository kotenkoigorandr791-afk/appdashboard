(function() {
  const PRESSABLE_SELECTOR = '.btn, .icon-btn, .root-toolbar-btn, .settings-action-btn';

  function setPressedState(target, pressed) {
    const button = target && target.closest ? target.closest(PRESSABLE_SELECTOR) : null;
    if (!button || button.disabled) return;
    button.classList.toggle('is-pressed', pressed);
  }

  function clearPressedState() {
    document.querySelectorAll(PRESSABLE_SELECTOR + '.is-pressed').forEach((button) => {
      button.classList.remove('is-pressed');
    });
  }

  document.addEventListener('pointerdown', (event) => {
    if (event.pointerType === 'mouse' && event.button !== 0) return;
    setPressedState(event.target, true);
  }, true);

  document.addEventListener('pointerup', clearPressedState, true);
  document.addEventListener('pointercancel', clearPressedState, true);
  document.addEventListener('dragstart', clearPressedState, true);
  window.addEventListener('blur', clearPressedState);
})();
