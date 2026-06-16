/**
 * Скрипты для модальных окон авторизации
 * 
 * @package AKPP45_CRM
 */

(function($) {
    'use strict';
    
    var ModalAuth = {
        
        init: function() {
            this.bindEvents();
            this.initPhoneMask();
        },
        
        bindEvents: function() {
            var self = this;
            
            // Открытие модального окна входа
            $('.btn-login, .open-login-modal, .btn-diagnostic').on('click', function(e) {
                e.preventDefault();
                self.showLoginModal();
            });
            
            // Открытие модального окна регистрации
            $('.btn-register, .open-register-modal').on('click', function(e) {
                e.preventDefault();
                self.showRegisterModal();
            });
            
            // Кнопка "Записаться на диагностику"
            $('.hero-btn, .btn-diagnostic, .book-diagnostic').on('click', function(e) {
                e.preventDefault();
                self.showLoginModal();
            });
            
            // Закрытие модальных окон
            $(document).on('click', '.akpp-modal-close, .akpp-modal-overlay', function(e) {
                if ($(e.target).hasClass('akpp-modal-close') || $(e.target).hasClass('akpp-modal-overlay')) {
                    $('.akpp-modal-overlay').removeClass('active');
                }
            });
            
            // Переключение между формами
            $('#show-register').on('click', function(e) {
                e.preventDefault();
                self.showRegisterModal();
            });
            
            $('#show-login').on('click', function(e) {
                e.preventDefault();
                self.showLoginModal();
            });
            
            $('#show-forgot').on('click', function(e) {
                e.preventDefault();
                $('#akpp-modal-login-form').hide();
                $('#akpp-modal-forgot-form').show();
            });
            
            $('#back-to-login').on('click', function(e) {
                e.preventDefault();
                $('#akpp-modal-forgot-form').hide();
                $('#akpp-modal-login-form').show();
            });
            
            // Отправка форм
            $('#akpp-modal-login-form').on('submit', function(e) {
                e.preventDefault();
                self.login();
            });
            
            $('#akpp-modal-register-form').on('submit', function(e) {
                e.preventDefault();
                self.register();
            });
            
            $('#akpp-modal-forgot-form').on('submit', function(e) {
                e.preventDefault();
                self.forgotPassword();
            });
        },
        
        showLoginModal: function() {
            $('#register-modal').removeClass('active');
            $('#login-modal').addClass('active');
            $('#akpp-modal-login-form').show();
            $('#akpp-modal-forgot-form').hide();
            $('#login-message').hide().removeClass('success error').empty();
        },
        
        showRegisterModal: function() {
            $('#login-modal').removeClass('active');
            $('#register-modal').addClass('active');
            $('#register-message').hide().removeClass('success error').empty();
        },
        
        initPhoneMask: function() {
            $(document).on('input', '#modal-phone', function() {
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
        
        login: function() {
            var email = $('#modal-email').val().trim();
            var password = $('#modal-password').val();
            var remember = $('input[name="remember"]').is(':checked') ? 1 : 0;
            var messageDiv = $('#login-message');
            var btn = $('#modal-login-btn');
            
            if (!email || !password) {
                this.showMessage('Заполните все поля', 'error', messageDiv);
                return;
            }
            
            btn.prop('disabled', true).text('⏳ Вход...');
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
                        ModalAuth.showMessage(response.data.message, 'success', messageDiv);
                        setTimeout(function() {
                            window.location.href = response.data.redirect_url;
                        }, 1500);
                    } else {
                        ModalAuth.showMessage(response.data.message, 'error', messageDiv);
                        btn.prop('disabled', false).text('🚀 Войти');
                    }
                },
                error: function() {
                    ModalAuth.showMessage('Ошибка соединения', 'error', messageDiv);
                    btn.prop('disabled', false).text('🚀 Войти');
                }
            });
        },
        
        register: function() {
            var name = $('#modal-name').val().trim();
            var phone = $('#modal-phone').val().trim();
            var email = $('#modal-email').val().trim();
            var car_brand = $('#modal-car').val().trim();
            var problem = $('#modal-problem').val().trim();
            var messageDiv = $('#register-message');
            var btn = $('#modal-register-btn');
            
            if (!name) {
                this.showMessage('Введите ФИО', 'error', messageDiv);
                return;
            }
            
            if (!phone) {
                this.showMessage('Введите телефон', 'error', messageDiv);
                return;
            }
            
            if (!email) {
                this.showMessage('Введите email', 'error', messageDiv);
                return;
            }
            
            btn.prop('disabled', true).text('⏳ Регистрация...');
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
                        ModalAuth.showMessage(response.data.message, 'success', messageDiv);
                        setTimeout(function() {
                            ModalAuth.showLoginModal();
                            $('#register-message').hide();
                        }, 2000);
                    } else {
                        ModalAuth.showMessage(response.data.message, 'error', messageDiv);
                        btn.prop('disabled', false).text('📝 Зарегистрироваться');
                    }
                },
                error: function() {
                    ModalAuth.showMessage('Ошибка соединения', 'error', messageDiv);
                    btn.prop('disabled', false).text('📝 Зарегистрироваться');
                }
            });
        },
        
        forgotPassword: function() {
            var email = $('#reset-email').val().trim();
            var messageDiv = $('#login-message');
            var btn = $('#akpp-modal-forgot-form button[type="submit"]');
            
            if (!email) {
                this.showMessage('Введите email', 'error', messageDiv);
                return;
            }
            
            btn.prop('disabled', true).text('⏳ Отправка...');
            
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
                        ModalAuth.showMessage(response.data.message, 'success', messageDiv);
                        setTimeout(function() {
                            $('#akpp-modal-forgot-form').hide();
                            $('#akpp-modal-login-form').show();
                            $('#reset-email').val('');
                        }, 3000);
                    } else {
                        ModalAuth.showMessage(response.data.message, 'error', messageDiv);
                    }
                    btn.prop('disabled', false).text('📧 Восстановить пароль');
                },
                error: function() {
                    ModalAuth.showMessage('Ошибка соединения', 'error', messageDiv);
                    btn.prop('disabled', false).text('📧 Восстановить пароль');
                }
            });
        },
        
        showMessage: function(msg, type, container) {
            container.removeClass('success error').addClass(type).html('<p>' + msg + '</p>').show();
            setTimeout(function() {
                container.fadeOut();
            }, 5000);
        }
    };
    
    $(document).ready(function() {
        ModalAuth.init();
    });
    
    window.ModalAuth = ModalAuth;
    
})(jQuery);
