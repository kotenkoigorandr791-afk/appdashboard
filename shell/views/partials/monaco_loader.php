<script>
(function(){
  if (typeof Event !== 'undefined' && Event.prototype && typeof Event.prototype.getModifierState !== 'function') {
    Event.prototype.getModifierState = function(){ return false; };
  }
  if (typeof window.apLoadMonaco === 'function') return;
  var MONACO_VS = 'https://cdn.jsdelivr.net/npm/monaco-editor@0.52.2/min/vs';
  window.apLoadMonaco = function(){
    if (window.monaco) return Promise.resolve(window.monaco);
    if (window.__apMonacoPromise) return window.__apMonacoPromise;
    function loadScript(src){
      return new Promise(function(resolve, reject){
        var existing = document.querySelector('script[src="'+src+'"]');
        var s = existing || document.createElement('script');
        s.addEventListener('load', function(){ s.dataset.apLoaded = '1'; resolve(); }, { once: true });
        s.addEventListener('error', function(){ reject(new Error('Failed to load '+src)); }, { once: true });
        if (existing && existing.dataset.apLoaded === '1') setTimeout(resolve, 0);
        else if (!existing) { s.src = src; s.async = false; document.head.appendChild(s); }
      });
    }
    function loadEditor(vsPath){
      return new Promise(function(resolve, reject){
        try {
          require.config({ paths: { vs: vsPath }, ignoreDuplicateModules: ['vs/editor/editor.main'] });
          require(['vs/editor/editor.main'], function(){ resolve(window.monaco); }, reject);
        } catch (e) { reject(e); }
      });
    }
    window.__apMonacoPromise = loadScript(MONACO_VS + '/loader.js').then(function(){ return loadEditor(MONACO_VS); });
    return window.__apMonacoPromise;
  };
})();
</script>
