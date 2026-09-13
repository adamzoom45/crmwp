(function($){
    'use strict';
    var pollTimer=null, curJob=null, pendingRest=[];
    function el(id){ return document.getElementById(id); }
    function log(html, cls){
        var box=el('akpp-upd-log'); if(!box) return;
        if(box.querySelector('em')) box.innerHTML='';
        var d=document.createElement('div'); if(cls) d.className=cls; d.innerHTML=html;
        box.appendChild(d); box.scrollTop=box.scrollHeight;
    }
    function state(t){ var s=el('akpp-upd-state'); if(s) s.textContent=t||''; }
    function params(){
        return {
            version: (el('akpp-upd-version')||{}).value || (window.AKPP_UPD&&AKPP_UPD.ver) || '',
            note:    (el('akpp-upd-note')||{}).value || '',
            migrate: (el('akpp-upd-migrate')||{}).checked ? 1 : 0,
            canary:  (el('akpp-upd-canary')||{}).checked ? 1 : 0
        };
    }
    function checkedTenants(){
        var t=[]; document.querySelectorAll('input[name="akpp_upd_tenant[]"]:checked').forEach(function(c){ t.push(c.value); });
        return t;
    }
    function renderResults(res){
        var rows=Object.keys(res||{}); if(!rows.length) return '';
        var h='<table><tr><th>tenant</th><th>статус</th><th>версия</th><th>HTTP</th><th>бэкап</th><th>ошибка</th></tr>';
        rows.forEach(function(d){
            var r=res[d], cls=(r.status==='OK'?'ok':(r.status==='running'?'run':'fail'));
            h+='<tr><td>'+d+'</td><td class="'+cls+'">'+(r.status||'?')+'</td><td>'+(r.version_before||'?')+' → '+(r.version_after||'…')+'</td><td>'+(r.http_code||'')+'</td><td>'+(r.backup_file||'')+'</td><td class="fail">'+(r.error||'')+'</td></tr>';
        });
        return h+'</table>';
    }
    function setRunning(dis){
        var b=el('akpp-upd-run-all'); if(b) b.disabled=dis;
    }
    function poll(id){
        if(pollTimer) clearInterval(pollTimer);
        pollTimer=setInterval(function(){
            var fd=new FormData(); fd.append('action','akpp_updater_poll'); fd.append('nonce',AKPP_UPD.nonce); fd.append('job_id',id);
            fetch(AKPP_UPD.ajaxurl,{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json();}).then(function(j){
                if(!j.success){ log('poll error: '+(j.data||'?'),'fail'); clearInterval(pollTimer); setRunning(false); return; }
                var d=j.data, box=el('akpp-upd-log');
                var tbl=box.querySelector('table'); if(tbl) tbl.remove();
                box.insertAdjacentHTML('beforeend', renderResults(d.results||{}));
                box.scrollTop=box.scrollHeight;
                if(d.status==='done'||d.status==='done_with_errors'){
                    clearInterval(pollTimer); pollTimer=null; setRunning(false);
                    var ok=(d.status==='done');
                    log((ok?'✅ ЗАДАНИЕ ЗАВЕРШЕНО':'⚠️ ЗАВЕРШЕНО С ОШИБКАМИ')+' · '+(d.finished_at||''), ok?'ok':'fail');
                    state(ok?'готово':'есть ошибки');
                    if(ok && pendingRest.length){ var c=el('akpp-upd-continue'); if(c) c.style.display='inline-block'; state('канарейка прошла · в ожидании: '+pendingRest.join(', ')); }
                }
            }).catch(function(e){ log('poll fetch fail: '+e,'fail'); });
        },2000);
    }
    function start(tenants, extra){
        if(!tenants || !tenants.length){ alert('Выберите хотя бы одного арендатора'); return; }
        setRunning(true); var c=el('akpp-upd-continue'); if(c) c.style.display='none';
        var p=params();
        var fd=new FormData();
        fd.append('action','akpp_updater_start'); fd.append('nonce',AKPP_UPD.nonce);
        fd.append('version',p.version); fd.append('note',p.note);
        fd.append('migrate', (extra&&extra.forceNoMigrate)?0:p.migrate);
        fd.append('canary',  (extra&&extra.forceNoCanary)?0:p.canary);
        tenants.forEach(function(t){ fd.append('tenants[]',t); });
        if(extra&&extra.append){ fd.append('append','1'); fd.append('job_id',extra.job_id); }
        state('запуск…');
        fetch(AKPP_UPD.ajaxurl,{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json();}).then(function(j){
            if(!j.success){ log('❌ start error: '+(j.data||'?'),'fail'); state('ошибка'); setRunning(false); return; }
            curJob=j.data.id; pendingRest=j.data.pending_rest||[];
            log('▶ задание <b>'+curJob+'</b> запущено (pid '+j.data.pid+') · tenant\'ы: '+j.data.tenants.join(', ')+(pendingRest.length?' · канарейка, ждут: '+pendingRest.join(', '):''),'run');
            poll(curJob);
        }).catch(function(e){ log('❌ start fetch fail: '+e,'fail'); state('ошибка'); setRunning(false); });
    }
    $(document).ready(function(){
        if(!el('akpp-upd-log')) return; // блока нет (например, tenant) — выходим
        var all=el('akpp-upd-run-all');
        if(all) all.addEventListener('click', function(){ start(checkedTenants(), null); });
        var cont=el('akpp-upd-continue');
        if(cont) cont.addEventListener('click', function(){
            if(!curJob||!pendingRest.length) return;
            var rest=pendingRest.slice(); pendingRest=[];
            log('▶ продолжение на остальных: '+rest.join(', '),'run');
            start(rest, {append:true, job_id:curJob, forceNoCanary:1});
        });
        // индивидуальные кнопки 🔄 в реестре (делегирование)
        $(document).on('click', '.akpp-upd-one', function(){
            var dom=this.getAttribute('data-domain'); if(!dom) return;
            if(!confirm('Обновить CRM на '+dom+'?\nБудет создан бэкап файлов и БД, затем автооткат при сбое.')) return;
            log('▶ индивидуальное обновление: '+dom,'run');
            start([dom], {forceNoCanary:1, forceNoMigrate:1});
        });
    });
})(jQuery);
