/**
 * АКПП45 CRM - Frontend Auth JavaScript
 * Управляет AJAX-отправкой форм регистрации, входа и обновления профиля.
 */

jQuery(document).ready(function($) {
    'use strict';

    const ajaxUrl = typeof akppCRM !== 'undefined' ? akppCRM.ajax_url : '/wp-admin/admin-ajax.php';
    const nonce = typeof akppCRM !== 'undefined' ? akppCRM.nonce : '';

    // ==========================================================================
    // 1. Утилиты для форм
    // ==========================================================================

    function showFormMessage($form, message, type) {
        const $msgBox = $form.find('.akpp-form-message');
        $msgBox.removeClass('success error').addClass(type).text(message).slideDown(300);
        
        // Автоматически скрыть сообщение через 5 секунд
        setTimeout(() => {
            $msgBox.slideUp(300);
        }, 5000);
    }

    function toggleFormLoading($form, isLoading) {
        const $btn = $form.find('button[type="submit"]');
        if (isLoading) {
            $btn.data('original-text', $btn.text())
                .prop('disabled', true)
                .html('<span class="akpp-loading" style="width:16px; height:16px; border-width:2px; vertical-align:middle; margin-right:8px;"></span> Обработка...');
            $form.find('input, textarea, select').prop('disabled', true);
        } else {
            $btn.prop('disabled', false).text($btn.data('original-text') || 'Отправить');
            $form.find('input, textarea, select').prop('disabled', false);
        }
    }

    // ==========================================================================
    // 2. Обработка формы регистрации
    // ==========================================================================

    $('#akpp-register-form').on('submit', function(e) {
        e.preventDefault();
        const $form = $(this);
        toggleFormLoading($form, true);
        $form.find('.akpp-form-message').hide();

        const formData = new FormData(this);
        formData.append('action', 'akpp_register_user');

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    showFormMessage($form, response.data.message, 'success');
                    setTimeout(() => {
                        window.location.href = response.data.redirect || '/profile/';
                    }, 1500);
                } else {
                    showFormMessage($form, response.data.message, 'error');
                }
            },
            error: function() {
                showFormMessage($form, 'Ошибка сети. Попробуйте позже.', 'error');
            },
            complete: function() {
                toggleFormLoading($form, false);
            }
        });
    });

    // ==========================================================================
    // 3. Обработка формы входа
    // ==========================================================================

    $('#akpp-login-form').on('submit', function(e) {
        e.preventDefault();
        const $form = $(this);
        toggleFormLoading($form, true);
        $form.find('.akpp-form-message').hide();

        const formData = new FormData(this);
        formData.append('action', 'akpp_login_user');

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    showFormMessage($form, response.data.message, 'success');
                    setTimeout(() => {
                        window.location.href = response.data.redirect || '/profile/';
                    }, 1000);
                } else {
                    showFormMessage($form, response.data.message, 'error');
                }
            },
            error: function() {
                showFormMessage($form, 'Ошибка сети. Попробуйте позже.', 'error');
            },
            complete: function() {
                toggleFormLoading($form, false);
            }
        });
    });

    // ==========================================================================
    // 4. Обработка формы обновления профиля
    // ==========================================================================

    $('#akpp-profile-form').on('submit', function(e) {
        e.preventDefault();
        const $form = $(this);
        toggleFormLoading($form, true);
        $form.find('.akpp-form-message').hide();

        const formData = new FormData(this);
        formData.append('action', 'akpp_update_profile');
        // Nonce уже есть в форме как скрытое поле, но добавим для надежности
        if (!formData.has('nonce')) {
            formData.append('nonce', nonce);
        }

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    showFormMessage($form, response.data.message, 'success');
                } else {
                    showFormMessage($form, response.data.message, 'error');
                }
            },
            error: function() {
                showFormMessage($form, 'Ошибка сети при сохранении профиля.', 'error');
            },
            complete: function() {
                toggleFormLoading($form, false);
            }
        });
    });

    // ==========================================================================
    // 5. Обработка кнопки выхода
    // ==========================================================================

    $('#akpp-logout-btn').on('click', function(e) {
        e.preventDefault();
        const $btn = $(this);
        
        if (!confirm('Вы действительно хотите выйти из личного кабинета?')) {
            return;
        }

        $btn.prop('disabled', true).text('Выход...');

        $.post(ajaxUrl, {
            action: 'akpp_logout_user',
            nonce: nonce
        }, function(response) {
            if (response.success) {
                window.location.href = response.data.redirect || '/login/';
            } else {
                alert('Ошибка при выходе из системы.');
                $btn.prop('disabled', false).text('Выйти');
            }
        }).fail(function() {
            alert('Ошибка сети.');
            $btn.prop('disabled', false).text('Выйти');
        });
    });

});
