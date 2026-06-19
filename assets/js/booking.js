/**
 * АКПП45 - Запись на ремонт
 */
(function($) {
    'use strict';
    
    $(document).ready(function() {
        
        // Открытие модалки записи
        $(document).on('click', '.open-booking-modal', function(e) {
            e.preventDefault();
            $('#booking-modal').addClass('active');
            $('body').css('overflow', 'hidden');
        });
        
        // Открытие модалки авторизации
        $(document).on('click', '.open-auth-modal', function(e) {
            e.preventDefault();
            $('#auth-modal').addClass('active');
            $('body').css('overflow', 'hidden');
        });
        
        // Закрытие модалок
        $(document).on('click', '.modal-close, .modal-overlay', function() {
            $(this).closest('.modal').removeClass('active');
            $('body').css('overflow', '');
        });
        
        // Закрытие по Escape
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape') {
                $('.modal').removeClass('active');
                $('body').css('overflow', '');
            }
        });
        
        // Переключение вкладок в авторизации
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
        
        // Переключение типа регистрации
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
        
        // Отправка формы записи
        $('#booking-form').on('submit', function(e) {
            e.preventDefault();
            
            var $form = $(this);
            var $btn = $form.find('button[type="submit"]');
            var originalText = $btn.text();
            
            $btn.prop('disabled', true).text('Отправка...');
            
            var formData = $form.serializeArray();
            formData.push({name: 'action', value: 'akpp_booking_request'});
            
            $.ajax({
                url: '/wp-admin/admin-ajax.php',
                type: 'POST',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showSuccess('Заявка отправлена! Свяжусь в течение часа.');
                        $form[0].reset();
                        $('#booking-modal').removeClass('active');
                        $('body').css('overflow', '');
                    } else {
                        showError(response.data.message || 'Ошибка отправки');
                    }
                    $btn.prop('disabled', false).text(originalText);
                },
                error: function() {
                    showError('Ошибка соединения');
                    $btn.prop('disabled', false).text(originalText);
                }
            });
        });
        
        // Уведомления
        function showSuccess(message) {
            showNotice(message, 'success');
        }
        
        function showError(message) {
            showNotice(message, 'error');
        }
        
        function showNotice(message, type) {
            var bgColor = type === 'success' ? '#00ff88' : '#fc8181';
            var textColor = type === 'success' ? '#0a0f1c' : '#fff';
            var $notice = $('<div style="position:fixed;top:20px;right:20px;background:' + bgColor + ';color:' + textColor + ';padding:16px 24px;border-radius:8px;box-shadow:0 4px 20px rgba(0,0,0,0.3);z-index:99999;font-weight:600;max-width:400px;">' + message + '</div>');
            $('body').append($notice);
            setTimeout(function() {
                $notice.fadeOut(300, function() { $(this).remove(); });
            }, 4000);
        }
        
        // Защита email от спама
        $(document).on('click', '.email-protected', function() {
            var user = $(this).data('user');
            var domain = $(this).data('domain');
            window.location.href = 'mailto:' + user + '@' + domain;
        });
        
        // Маска телефона
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
    });
    
})(jQuery);