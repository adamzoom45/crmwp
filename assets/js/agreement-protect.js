(function(){
  'use strict';
  function protect(){
    var els = document.querySelectorAll('.agreement-full-text, .agreement-protected');
    if (!els.length) return;
    els.forEach(function(el){
      // Отключаем select/copy/drag/context-menu
      el.addEventListener('selectstart', function(e){ e.preventDefault(); });
      el.addEventListener('copy', function(e){ e.preventDefault(); alert('Копирование оферты запрещено.'); });
      el.addEventListener('cut', function(e){ e.preventDefault(); });
      el.addEventListener('dragstart', function(e){ e.preventDefault(); });
      el.addEventListener('contextmenu', function(e){ e.preventDefault(); });
      el.addEventListener('mousedown', function(e){
        if (e.button === 2) { e.preventDefault(); return; }
        // Разрешаем обычный клик, но не выделение
        if (window.getSelection) window.getSelection().removeAllRanges();
      });
    });
    // Глобально на странице оферты
    if (document.body.classList.contains('page-oferta') || document.location.pathname.indexOf('/oferta') !== -1) {
      document.addEventListener('copy', function(e){
        var sel = window.getSelection();
        if (sel && sel.toString().length > 0) { e.preventDefault(); }
      });
    }
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', protect);
  else protect();
  // Для SPA/динамического контента
  new MutationObserver(protect).observe(document.documentElement, { childList: true, subtree: true });
})();
