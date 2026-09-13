(function($){
    'use strict';
    var editor=null, curFile=null, curPreviewUrl='', dirty=false, original='';

    function status(msg, cls){
        var s=$('#akpp-ed-status').show();
        s.css({background: cls==='err' ? 'rgba(252,129,129,.08)' : 'rgba(0,255,136,.08)',
               color: cls==='err' ? '#fc8181' : '#00ff88',
               borderBottom: '1px solid ' + (cls==='err' ? 'rgba(252,129,129,.2)' : 'rgba(0,255,136,.2)')}).text(msg);
    }
    function modeFor(rel){
        var ext=(rel.split('.').pop()||'').toLowerCase();
        if(ext==='php') return 'text/x-php';
        if(ext==='css') return 'text/css';
        if(ext==='js')  return 'text/javascript';
        return 'text/html';
    }
    function refreshPreview(){
        if(!curPreviewUrl) return;
        var iframe=$('#akpp-ed-preview');
        iframe.attr('src', curPreviewUrl + '&t=' + Date.now());
    }
    function openModal(tplFile, name){
        $('#akpp-ed-file').text(name + ' → ' + tplFile);
        $('#akpp-editor-modal').fadeIn(150);
        
        // Определяем ID шаблона для предпросмотра
        var tplId = tplFile.replace('template-saas-', '').replace('.php', '');
        if(tplFile === 'index.php') tplId = 'impulse'; // главная = импульс
        curPreviewUrl = window.location.origin + '/?akpp_tpl_preview=' + tplId;
        
        load(tplFile);
        setTimeout(refreshPreview, 500); // загружаем предпросмотр
    }
    function closeModal(){
        if(dirty && !confirm('Есть несохранённые изменения. Закрыть?')) return;
        $('#akpp-editor-modal').fadeOut(150);
        curFile=null; dirty=false;
    }
    function load(rel){
        var fd=new FormData(); fd.append('action','akpp_editor_load'); fd.append('nonce',AKPP_ED.nonce); fd.append('file',rel);
        status('Загрузка '+rel+'…','ok');
        fetch(AKPP_ED.ajaxurl,{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json();}).then(function(j){
            if(!j.success){ status('Ошибка: '+(j.data&&j.data.message||'неизвестно'),'err'); return; }
            curFile=rel; original=j.data.content;
            editor.setValue(j.data.content);
            editor.setOption('mode', modeFor(rel));
            editor.clearHistory();
            dirty=false;
            status('Загружено: '+rel+' ('+j.data.size+' байт)','ok');
            loadHistory(rel);
        }).catch(function(e){ status('Сеть: '+e,'err'); });
    }
    function save(){
        if(!curFile){ status('Нет файла','err'); return; }
        var content=editor.getValue();
        if(content===original){ status('Нет изменений','ok'); return; }
        if(!confirm('Сохранить '+curFile+'?\n\nБудет создан автобэкап. Ядро CRM редактировать нельзя.')) return;
        var fd=new FormData(); fd.append('action','akpp_editor_save'); fd.append('nonce',AKPP_ED.nonce);
        fd.append('file',curFile); fd.append('content',content);
        status('Сохранение…','ok');
        fetch(AKPP_ED.ajaxurl,{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json();}).then(function(j){
            if(!j.success){
                var d=j.data||{}; var m='Ошибка: '+(d.message||'неизвестно');
                if(d.lint) m+='\n\n'+d.lint;
                status(m,'err'); return;
            }
            original=content; dirty=false;
            status('✅ '+j.data.message+' — предпросмотр обновлён','ok');
            loadHistory(curFile);
            refreshPreview(); // автообновление предпросмотра после сохранения
        }).catch(function(e){ status('Сеть: '+e,'err'); });
    }
    function loadHistory(rel){
        var fd=new FormData(); fd.append('action','akpp_editor_history'); fd.append('nonce',AKPP_ED.nonce); fd.append('file',rel);
        fetch(AKPP_ED.ajaxurl,{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json();}).then(function(j){
            var h=$('#akpp-ed-history').empty();
            if(!j.success || !j.data.items || !j.data.items.length){ h.html('<em>История пуста</em>'); return; }
            j.data.items.forEach(function(it){
                var row=$('<div style="background:#1a1f2e;border:1px solid #2d3748;border-radius:7px;padding:7px 10px;margin-bottom:5px;"></div>');
                row.append('<div style="color:#41d2e2;font-family:ui-monospace,monospace;font-weight:700;font-size:11px;">'+it.ts+'</div>');
                row.append('<div style="color:#718096;font-size:10px;margin-bottom:4px;">'+it.time+' · '+Math.round(it.size/1024)+' КБ</div>');
                var btn=$('<button type="button" style="background:#2d3748;color:#cbd5e0;border:1px solid #4a5568;border-radius:5px;padding:3px 8px;font-size:10.5px;cursor:pointer;">↩ откатить</button>');
                btn.on('click', function(){ rollback(rel, it.file); });
                row.append(btn);
                h.append(row);
            });
        });
    }
    function rollback(rel, backup){
        if(!confirm('Откатить '+rel+' к версии '+backup+'?')) return;
        var fd=new FormData(); fd.append('action','akpp_editor_rollback'); fd.append('nonce',AKPP_ED.nonce);
        fd.append('file',rel); fd.append('backup',backup);
        status('Откат…','ok');
        fetch(AKPP_ED.ajaxurl,{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json();}).then(function(j){
            if(!j.success){ status('Откат не удался: '+(j.data||''),'err'); return; }
            status('✅ '+j.data.message,'ok');
            load(rel);
            refreshPreview();
        }).catch(function(e){ status('Сеть: '+e,'err'); });
    }
    $(document).ready(function(){
        var ta=document.getElementById('akpp-ed-code');
        if(!ta) return;
        if(typeof wp !== 'undefined' && wp.codeEditor && wp.codeEditor.initialize){
            var init=wp.codeEditor.initialize(ta, {
                codemirror: { mode:'text/x-php', theme:'default', lineNumbers:true, matchBrackets:true, autoCloseBrackets:true, indentUnit:4, tabSize:4 }
            });
            editor=init.codemirror;
            editor.on('change', function(){ dirty = (editor.getValue()!==original); });
        } else {
            editor={
                getValue:function(){return ta.value;},
                setValue:function(v){ta.value=v;},
                setOption:function(){},
                clearHistory:function(){}
            };
            $(ta).on('input', function(){ dirty=(ta.value!==original); });
        }

        $(document).on('click', '.akpp-edit-tpl-btn', function(e){
            e.preventDefault();
            var tpl=$(this).data('tpl');
            var name=$(this).data('name');
            openModal(tpl, name);
        });
        $('#akpp-ed-close').on('click', closeModal);
        $('#akpp-ed-save').on('click', save);
        $('#akpp-ed-reload').on('click', function(){ if(curFile) load(curFile); });
        $('#akpp-ed-refresh-preview').on('click', refreshPreview);
        $(document).on('keydown', function(e){
            if(e.key==='Escape' && $('#akpp-editor-modal').is(':visible')) closeModal();
            if((e.ctrlKey||e.metaKey) && e.which===83 && $('#akpp-editor-modal').is(':visible')){ e.preventDefault(); save(); }
        });
    });
})(jQuery);
