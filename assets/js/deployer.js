(function($){
    'use strict';
    var pollTimer=null;
    function el(id){ return document.getElementById(id); }
    function state(t){ var s=el('dep-state'); if(s) s.textContent=t||''; }
    function genPass(){ var c='abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789!@#$%'; var p=''; for(var i=0;i<14;i++) p+=c[Math.floor(Math.random()*c.length)]; return p; }
    function renderLog(log){
        var box=el('dep-log'); if(!box) return;
        if(!log || !log.length){ box.innerHTML='<em>Запуск установки…</em>'; return; }
        var h=''; log.forEach(function(s){ h+='<div class="step '+s.s+'"><span class="t">'+s.t+'</span><span class="s">'+s.s+'</span><span class="m">'+s.m+'</span></div>'; });
        box.innerHTML=h; box.scrollTop=box.scrollHeight;
    }
    function renderResult(j){
        var box=el('dep-log'); if(!box) return;
        var old=box.querySelector('.akpp-dep-result'); if(old) old.remove();
        if(j.status==='done'){
            var url=j.admin_url||('https://'+j.domain+'/wp-admin');
            box.insertAdjacentHTML('beforeend','<div class="akpp-dep-result done">✅ CRM установлена на <b>'+j.domain+'</b><br>Админка: <a href="'+url+'" target="_blank" style="color:#00ff88">'+url+'</a> · логин: <b>'+(j.admin_user||'admin')+'</b><br>Лицензия VALID · полная схема CRM (41 таблица) · реквизиты заданы</div>');
        } else if(j.status==='failed'){
            var last=(j.log&&j.log.length)?j.log[j.log.length-1].m:'неизвестная ошибка';
            box.insertAdjacentHTML('beforeend','<div class="akpp-dep-result failed">❌ Установка не завершена: '+last+'</div>');
        }
        box.scrollTop=box.scrollHeight;
    }
    function poll(id){
        if(pollTimer) clearInterval(pollTimer);
        pollTimer=setInterval(function(){
            var fd=new FormData(); fd.append('action','akpp_deploy_poll'); fd.append('nonce',AKPP_DEP.nonce); fd.append('job_id',id);
            fetch(AKPP_DEP.ajaxurl,{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json();}).then(function(j){
                if(!j.success){ state('ошибка опроса'); clearInterval(pollTimer); el('dep-run').disabled=false; return; }
                var d=j.data; renderLog(d.log||[]);
                if(d.status==='done'||d.status==='failed'){ clearInterval(pollTimer); pollTimer=null; renderResult(d); state(d.status==='done'?'готово':'ошибка'); el('dep-run').disabled=false; }
            }).catch(function(){});
        },2000);
    }
    function collect(){
        return { domain:el('dep-domain').value.trim(), docroot:el('dep-docroot').value.trim(), siteuser:el('dep-siteuser').value.trim(),
            db_host:el('dep-db-host').value.trim()||'127.0.0.1', db_name:el('dep-db-name').value.trim(), db_user:el('dep-db-user').value.trim(),
            db_pass:el('dep-db-pass').value, db_prefix:el('dep-db-prefix').value.trim()||'wp_',
            wp_admin_user:el('dep-admin-user').value.trim()||'admin', wp_admin_pass:el('dep-admin-pass').value,
            wp_admin_email:el('dep-admin-email').value.trim(), site_title:el('dep-site-title').value.trim(),
            plan_months:el('dep-plan').value, unlimited:el('dep-plan').value==='1200'?1:0, note:el('dep-note').value.trim(),
            company_name:el('dep-company-name').value.trim(), company_site:el('dep-company-site').value.trim(),
            company_city:el('dep-company-city').value.trim(), avito_ad_id:el('dep-avito').value.trim() };
    }
    function start(){
        var c=collect();
        if(!c.domain||!c.docroot||!c.siteuser||!c.db_name||!c.db_user||!c.db_pass||!c.wp_admin_pass){ alert('Заполните обязательные поля (*): домен, путь, пользователь, БД (имя/юзер/пароль), пароль админа.'); return; }
        if(!confirm('Установить CRM на '+c.domain+'?\n\nБудет развёрнута полная CRM (41 таблица), выпущена лицензия, заданы реквизиты.\nУбедитесь, что сайт и БД созданы в CloudPanel.')) return;
        el('dep-run').disabled=true; state('запуск…'); renderLog([]);
        var fd=new FormData(); fd.append('action','akpp_deploy_start'); fd.append('nonce',AKPP_DEP.nonce);
        Object.keys(c).forEach(function(k){ fd.append(k, c[k]); });
        fetch(AKPP_DEP.ajaxurl,{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json();}).then(function(j){
            if(!j.success){ alert('Ошибка запуска: '+(j.data||'?')); state('ошибка'); el('dep-run').disabled=false; return; }
            state('установка запущена (pid '+j.data.pid+')…'); poll(j.data.id);
        }).catch(function(e){ alert('Ошибка сети: '+e); state('ошибка'); el('dep-run').disabled=false; });
    }
    $(document).ready(function(){
        if(!el('dep-run')) return;
        el('dep-run').addEventListener('click', start);
        var gen=el('dep-gen-pass'); if(gen) gen.addEventListener('click', function(){ var p=el('dep-admin-pass'); p.value=genPass(); p.type='text'; setTimeout(function(){p.type='password';},1500); });
        var dom=el('dep-domain');
        if(dom) dom.addEventListener('blur', function(){
            var d=dom.value.trim(); if(!d) return; var u=el('dep-siteuser').value.trim();
            if(!el('dep-docroot').value && u) el('dep-docroot').value='/home/'+u+'/htdocs/'+d;
            if(!el('dep-company-site').value) el('dep-company-site').value='https://'+d;
            if(!el('dep-admin-email').value) el('dep-admin-email').value='admin@'+d;
            if(!el('dep-site-title').value) el('dep-site-title').value=d+' — CRM';
        });
    });
})(jQuery);
