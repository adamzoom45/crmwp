/**
 * АКПП45 - Основные скрипты
 */
(function($) {
    'use strict';
    
    $(document).ready(function() {
        console.log(' АКПП45: Скрипты загружены');
        
        // ============================================================
        // МОДАЛКИ - ОТКРЫТИЕ
        // ============================================================
        $(document).on('click', '.open-booking-modal', function(e) {
            e.preventDefault();
            console.log('📝 Открытие модалки записи');
            $('#booking-modal').addClass('active');
            $('body').css('overflow', 'hidden');
        });
        
        $(document).on('click', '.open-auth-modal', function(e) {
            e.preventDefault();
            console.log('🔐 Открытие модалки авторизации');
            $('#auth-modal').addClass('active');
            $('body').css('overflow', 'hidden');
        });
        
        // ============================================================
        // МОДАЛКИ - ЗАКРЫТИЕ
        // ============================================================
        $(document).on('click', '.modal-close, .modal-overlay', function() {
            $(this).closest('.modal').removeClass('active');
            $('body').css('overflow', '');
        });
        
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape') {
                $('.modal').removeClass('active');
                $('body').css('overflow', '');
            }
        });
        
        // ============================================================
        // АВТОРИЗАЦИЯ - ПЕРЕКЛЮЧЕНИЕ ВКЛАДОК
        // ============================================================
        $(document).on('click', '.auth-tab', function() {
            var tab = $(this).data('tab');
            $('.auth-tab').removeClass('active');
            $(this).addClass('active');
            
            if (tab === 'login') {
                $('#login-form').removeClass('hidden');
                $('#register-form').addClass('hidden');
            } else {
                $('#login-form').addClass('hidden');
                $('#register-form').removeClass('hidden');
            }
        });
        
        // ============================================================
        // РЕГИСТРАЦИЯ - ВЫБОР РОЛИ
        // ============================================================
        $(document).on('change', 'input[name="role"]', function() {
            var role = $(this).val();
            if (role === 'repair') {
                $('.repair-fields').removeClass('hidden');
                $('.buyer-fields').addClass('hidden');
            } else {
                $('.repair-fields').addClass('hidden');
                $('.buyer-fields').removeClass('hidden');
            }
        });
        
                // ============================================================
    // ФОРМА ЗАПИСИ - ОТПРАВКА (с защитой от дублей)
    // ============================================================
    var bookingSubmitting = false;
    
    $('#booking-form').off('submit').on('submit', function(e) {
        e.preventDefault();
        
        if (bookingSubmitting) {
            console.log('⏳ Форма уже отправляется, ждите...');
            return;
        }
        bookingSubmitting = true;
        
        console.log('📤 Отправка формы записи');
        
        var $form = $(this);
        var $btn = $form.find('button[type="submit"]');
        var originalText = $btn.html();
        
        $btn.prop('disabled', true).html('⏳ Отправка...');
        
        var formData = $form.serializeArray();
        formData.push({name: 'action', value: 'akpp_booking_request'});
        
        $.ajax({
            url: '/wp-admin/admin-ajax.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                console.log('✅ Ответ:', response);
                if (response.success) {
                    showNotice(response.data.message || '✅ Заявка принята!', 'success');
                    $form[0].reset();
                    $('#booking-modal').removeClass('active');
                    $('body').css('overflow', '');
                } else {
                    showNotice(response.data.message || ' Ошибка', 'error');
                }
                $btn.prop('disabled', false).html(originalText);
                bookingSubmitting = false;
            },
            error: function(xhr, status, error) {
                console.error('❌ Ошибка AJAX:', status, error);
                showNotice('❌ Ошибка соединения', 'error');
                $btn.prop('disabled', false).html(originalText);
                bookingSubmitting = false;
            }
        });
    });
        
        // ============================================================
        // ФОРМА ВХОДА
        // ============================================================
        $('#login-form').on('submit', function(e) {
            e.preventDefault();
            console.log('🔐 Вход...');
            
            var $form = $(this);
            var $btn = $form.find('button[type="submit"]');
            var originalText = $btn.html();
            
            $btn.prop('disabled', true).html('⏳ Вход...');
            
            var formData = $form.serializeArray();
            formData.push({name: 'action', value: 'akpp_login_client'});
            
            $.ajax({
                url: '/wp-admin/admin-ajax.php',
                type: 'POST',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showNotice(response.data.message || '✅ Вход выполнен!', 'success');
                        setTimeout(function() {
                            window.location.href = response.data.redirect || '/';
                        }, 1000);
                    } else {
                        showNotice(response.data.message || '❌ Ошибка входа', 'error');
                        $btn.prop('disabled', false).html(originalText);
                    }
                },
                error: function() {
                    showNotice('❌ Ошибка соединения', 'error');
                    $btn.prop('disabled', false).html(originalText);
                }
            });
        });
        
        // ============================================================
        // ФОРМА РЕГИСТРАЦИИ
        // ============================================================
        $('#register-form').on('submit', function(e) {
            e.preventDefault();
            console.log('📝 Регистрация...');
            
            var $form = $(this);
            var $btn = $form.find('button[type="submit"]');
            var originalText = $btn.html();
            
            $btn.prop('disabled', true).html('⏳ Регистрация...');
            
            var formData = $form.serializeArray();
            formData.push({name: 'action', value: 'akpp_register_client'});
            
            $.ajax({
                url: '/wp-admin/admin-ajax.php',
                type: 'POST',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showNotice(response.data.message || '✅ Регистрация успешна!', 'success');
                        setTimeout(function() {
                            window.location.href = response.data.redirect || '/';
                        }, 1000);
                    } else {
                        showNotice(response.data.message || '❌ Ошибка регистрации', 'error');
                        $btn.prop('disabled', false).html(originalText);
                    }
                },
                error: function() {
                    showNotice('❌ Ошибка соединения', 'error');
                    $btn.prop('disabled', false).html(originalText);
                }
            });
        });
        
        // ============================================================
        // МОБИЛЬНОЕ МЕНЮ
        // ============================================================
        var $mobileToggle = $('.mobile-menu-toggle');
        var $mobileMenu = $('#mobileMenu');
        
        $mobileToggle.on('click', function() {
            $mobileMenu.toggleClass('active');
            var isExpanded = $mobileMenu.hasClass('active');
            $mobileToggle.attr('aria-expanded', isExpanded);
            
            if (isExpanded) {
                $('body').css('overflow', 'hidden');
            } else {
                $('body').css('overflow', '');
            }
        });
        
        $mobileMenu.on('click', 'a', function() {
            $mobileMenu.removeClass('active');
            $mobileToggle.attr('aria-expanded', 'false');
            $('body').css('overflow', '');
        });
        
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.site-header, #mobileMenu').length) {
                $mobileMenu.removeClass('active');
                $('body').css('overflow', '');
            }
        });
        
        // ============================================================
        // ПЛАВНАЯ ПРОКРУТКА
        // ============================================================
        $('a[href^="#"]').on('click', function(e) {
            var target = $(this.getAttribute('href'));
            if (target.length) {
                e.preventDefault();
                $('html, body').stop().animate({
                    scrollTop: target.offset().top - 100
                }, 600);
            }
        });
        
        // ============================================================
        // ЗАЩИТА ТЕЛЕФОНА ОТ БОТОВ
        // ============================================================
        $(document).on('click', '.phone-protected', function(e) {
            e.preventDefault();
            var phone = $(this).data('phone');
            if (phone) {
                window.location.href = 'tel:' + phone;
            }
        });
        
        // ============================================================
        // ЗАЩИТА EMAIL ОТ БОТОВ
        // ============================================================
        $(document).on('click', '.email-protected', function(e) {
            e.preventDefault();
            var user = $(this).data('user');
            var domain = $(this).data('domain');
            if (user && domain) {
                window.location.href = 'mailto:' + user + '@' + domain;
            }
        });
        
        // ============================================================
        // МАСКА ТЕЛЕФОНА
        // ============================================================
        $(document).on('input', 'input[type="tel"]', function() {
            var value = $(this).val().replace(/\D/g, '');
            if (value.startsWith('8')) value = '7' + value.substring(1);
            
            if (value.startsWith('7')) {
                var formatted = '+7';
                if (value.length > 1) formatted += ' (' + value.substring(1, 4);
                if (value.length >= 5) formatted += ') ' + value.substring(4, 7);
                if (value.length >= 8) formatted += '-' + value.substring(7, 9);
                if (value.length >= 10) formatted += '-' + value.substring(9, 11);
                $(this).val(formatted);
            }
        });
        
        // ============================================================
        // УВЕДОМЛЕНИЯ
        // ============================================================
        function showNotice(message, type) {
            var bgColor = type === 'success' ? '#00ff88' : '#fc8181';
            var textColor = type === 'success' ? '#0a0f1c' : '#fff';
            var $notice = $('<div style="position:fixed;top:20px;right:20px;background:' + bgColor + ';color:' + textColor + ';padding:16px 24px;border-radius:8px;box-shadow:0 4px 20px rgba(0,0,0,0.3);z-index:99999;font-weight:600;max-width:400px;">' + message + '</div>');
            $('body').append($notice);
            setTimeout(function() {
                $notice.fadeOut(300, function() { $(this).remove(); });
            }, 4000);
        }
        
        console.log('✅ Все обработчики зарегистрированы');
    });
    
})(jQuery);