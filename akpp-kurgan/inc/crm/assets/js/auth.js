/**
 * CRM АКПП45 - Скрипты авторизации и регистрации
 * 
 * @package AKPP45_CRM
 */

(function($) {
    'use strict';
    
    var AKPP_Auth = {
        
        /**
         * Инициализация
         */
        init: function() {
            this.initRegisterForm();
            this.initLoginForm();
            this.initPasswordReset();
            this.initPhoneMask();
        },
        
        /**
         * Инициализация формы регистрации
         */
        initRegisterForm: function() {
            var self = this;
            
            $('#akpp-register-form').on('submit', function(e) {
                e.preventDefault();
                
                var form = $(this);
                var submitBtn = $('#register-btn');
                var messageDiv = $('#register-message');
                
                var name = $('#name').val().trim();
                var phone = $('#phone').val().trim();
                var email = $('#email').val().trim();
                var car_brand = $('#car_brand').val().trim();
                var problem = $('#problem').val().trim();
                
                // Валидация
                if (!name) {
                    self.showMessage('Введите ФИО', 'error', messageDiv);
                    return;
                }
                
                if (!phone) {
                    self.showMessage('Введите номер телефона', 'error', messageDiv);
                    return;
                }
                
                if (phone.length < 10) {
                    self.showMessage('Введите корректный номер телефона', 'error', messageDiv);
                    return;
                }
                
                if (!email) {
                    self.showMessage('Введите email', 'error', messageDiv);
                    return;
                }
                
                if (!self.validateEmail(email)) {
                    self.showMessage('Введите корректный email адрес', 'error', messageDiv);
                    return;
                }
                
                submitBtn.prop('disabled', true).text('⏳ Регистрация...');
                messageDiv.hide();
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'akpp_register',
                        name: name,
                        phone: phone,
                        email: email,
                        car_brand: car_brand,
                        problem: problem,
                        nonce: $('#akpp_register_nonce').val()
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            self.showMessage(response.data.message, 'success', messageDiv);
                            form[0].reset();
                            setTimeout(function() {
                                window.location.href = '/crm-login';
                            }, 2000);
                        } else {
                            self.showMessage(response.data.message, 'error', messageDiv);
                            submitBtn.prop('disabled', false).text('📝 Зарегистрироваться');
                        }
                    },
                    error: function() {
                        self.showMessage('Ошибка соединения с сервером', 'error', messageDiv);
                        submitBtn.prop('disabled', false).text('📝 Зарегистрироваться');
                    }
                });
            });
        },
        
        /**
         * Инициализация формы входа
         */
        initLoginForm: function() {
            var self = this;
            
            $('#akpp-login-form').on('submit', function(e) {
                e.preventDefault();
                
                var form = $(this);
                var submitBtn = $('#login-btn');
                var messageDiv = $('#login-message');
                
                var email = $('#email').val().trim();
                var password = $('#password').val();
                var remember = $('#remember').is(':checked') ? 1 : 0;
                
                if (!email) {
                    self.showMessage('Введите email', 'error', messageDiv);
                    $('#email').focus();
                    return;
                }
                
                if (!password) {
                    self.showMessage('Введите пароль', 'error', messageDiv);
                    $('#password').focus();
                    return;
                }
                
                submitBtn.prop('disabled', true).text('⏳ Вход...');
                messageDiv.hide();
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'akpp_login',
                        email: email,
                        password: password,
                        remember: remember,
                        nonce: $('#akpp_login_nonce').val()
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            self.showMessage(response.data.message, 'success', messageDiv);
                            
                            if (remember) {
                                document.cookie = "akpp_remember_email=" + encodeURIComponent(email) + "; path=/; max-age=" + (30 * 24 * 60 * 60);
                            } else {
                                document.cookie = "akpp_remember_email=; path=/; expires=Thu, 01 Jan 1970 00:00:00 UTC";
                            }
                            
                            setTimeout(function() {
                                window.location.href = response.data.redirect_url || '/crm-profile';
                            }, 1500);
                        } else {
                            self.showMessage(response.data.message, 'error', messageDiv);
                            submitBtn.prop('disabled', false).text('🚀 Войти в CRM');
                            $('#password').val('').focus();
                        }
                    },
                    error: function() {
                        self.showMessage('Ошибка соединения с сервером', 'error', messageDiv);
                        submitBtn.prop('disabled', false).text('🚀 Войти в CRM');
                    }
                });
            });
            
            // Вход по Enter
            $('#password').on('keypress', function(e) {
                if (e.which === 13) {
                    $('#akpp-login-form').submit();
                }
            });
            
            // Автофокус
            if (!$('#email').val()) {
                $('#email').focus();
            } else {
                $('#password').focus();
            }
        },
        
        /**
         * Инициализация восстановления пароля
         */
        initPasswordReset: function() {
            var self = this;
            
            $('#reset-password-btn').on('click', function(e) {
                e.preventDefault();
                
                var email = $('#reset-email').val().trim();
                var messageDiv = $('#reset-message');
                
                if (!email) {
                    self.showMessage('Введите email', 'error', messageDiv);
                    return;
                }
                
                if (!self.validateEmail(email)) {
                    self.showMessage('Введите корректный email', 'error', messageDiv);
                    return;
                }
                
                var btn = $(this);
                btn.prop('disabled', true).text('⏳ Отправка...');
                messageDiv.hide();
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'akpp_reset_password',
                        email: email,
                        nonce: $('#akpp_reset_nonce').val()
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            self.showMessage(response.data.message, 'success', messageDiv);
                            setTimeout(function() {
                                window.location.href = '/crm-login';
                            }, 3000);
                        } else {
                            self.showMessage(response.data.message, 'error', messageDiv);
                        }
                        btn.prop('disabled', false).text('📧 Отправить пароль');
                    },
                    error: function() {
                        self.showMessage('Ошибка соединения', 'error', messageDiv);
                        btn.prop('disabled', false).text('📧 Отправить пароль');
                    }
                });
            });
        },
        
        /**
         * Маска для телефона
         */
        initPhoneMask: function() {
            $('#phone').on('input', function() {
                var value = $(this).val().replace(/\D/g, '');
                if (value.length > 11) value = value.slice(0, 11);
                
                if (value.length === 11) {
                    value = '+7 (' + value.slice(1, 4) + ') ' + value.slice(4, 7) + '-' + value.slice(7, 9) + '-' + value.slice(9, 11);
                } else if (value.length > 0) {
                    value = '+7 ' + value;
                }
                
                $(this).val(value);
            });
        },
        
        /**
         * Валидация email
         */
        validateEmail: function(email) {
            var re = /^[^\s@]+@([^\s@]+\.)+[^\s@]+$/;
            return re.test(email);
        },
        
        /**
         * Показ сообщения
         */
        showMessage: function(msg, type, container) {
            var messageDiv = container || $('#auth-message');
            var className = type === 'success' ? 'notice-success' : (type === 'error' ? 'notice-error' : 'notice-warning');
            messageDiv.removeClass('notice-success notice-error notice-warning').addClass('notice ' + className).html('<p>' + msg + '</p>').show();
            
            setTimeout(function() {
                messageDiv.fadeOut();
            }, 5000);
        }
    };
    
    // Инициализация при загрузке
    $(document).ready(function() {
        AKPP_Auth.init();
    });
    
    window.AKPP_Auth = AKPP_Auth;
    
})(jQuery);
