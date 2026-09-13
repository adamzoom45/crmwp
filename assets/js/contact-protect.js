/* АКПП45 — защита контактов от спама + email -> ЛК (заявка/сделка) */
(function () {
  'use strict';
  function rev(s){ return s.split('').reverse().join(''); }
  function fmtPhone(d){
    if (d.length === 11 && d.charAt(0) === '7')
      return '+7 (' + d.substr(1,3) + ') ' + d.substr(4,3) + '-' + d.substr(7,2) + '-' + d.substr(9,2);
    return '+' + d;
  }
  function init(){
    document.querySelectorAll('.js-tel').forEach(function (a){
      var r = a.getAttribute('data-r'); if (!r) return;
      var d = rev(r);
      a.setAttribute('href', 'tel:+' + d);
      var t = a.querySelector('.js-tel-text'); if (t) t.textContent = fmtPhone(d);
    });
    document.querySelectorAll('.js-tg').forEach(function (a){
      var r = a.getAttribute('data-tg-rev'); if (!r) return;
      var name = rev(r);
      a.setAttribute('href', 'https://t.me/' + name);
      var t = a.querySelector('.js-tg-text'); if (t) t.textContent = '@' + name;
    });
    document.querySelectorAll('.js-email-to-lk').forEach(function (a){
      a.addEventListener('click', function (e){
        e.preventDefault();
        window.location.href = (a.getAttribute('data-logged') === '1')
          ? a.getAttribute('data-leads') : a.getAttribute('data-login');
      });
    });
    /* email из шапки/контактов: автооткрытие формы заявки в ЛК при ?new=1 */
    if (new URLSearchParams(window.location.search).get('new') === '1') {
      var open = function () {
        var btn = document.querySelector('.akpp-open-modal[data-target="#akpp-new-lead-modal"]');
        if (btn) { btn.click(); return true; }
        var m = document.getElementById('akpp-new-lead-modal');
        if (m) { m.classList.add('active'); document.body.style.overflow = 'hidden'; return true; }
        return false;
      };
      if (!open()) setTimeout(open, 350);
    }
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
