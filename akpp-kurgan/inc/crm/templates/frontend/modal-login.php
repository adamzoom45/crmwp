<?php
/**
 * Модальное окно входа в CRM
 * 
 * @package AKPP45_CRM
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div id="login-modal" class="akpp-modal-overlay">
    <div class="akpp-modal-container">
        <div class="akpp-modal-header">
            <div class="akpp-modal-icon">🔐</div>
            <h2>Вход в CRM</h2>
            <button class="akpp-modal-close">&times;</button>
        </div>
        
        <div class="akpp-modal-body">
            <div id="login-message" class="akpp-message"></div>
            
            <form id="akpp-modal-login-form">
                <?php wp_nonce_field('akpp_client_login_nonce', 'akpp_login_nonce'); ?>
                
                <div class="akpp-form-group">
                    <label for="modal-email">Email</label>
                    <input type="email" id="modal-email" name="email" placeholder="ivan@example.com" required>
                </div>
                
                <div class="akpp-form-group">
                    <label for="modal-password">Пароль</label>
                    <input type="password" id="modal-password" name="password" placeholder="••••••••" required>
                </div>
                
                <div class="akpp-form-actions">
                    <label class="akpp-checkbox">
                        <input type="checkbox" name="remember" value="1">
                        <span>Запомнить меня</span>
                    </label>
                    <a href="#" class="akpp-forgot-link" id="show-forgot">Забыли пароль?</a>
                </div>
                
                <button type="submit" class="akpp-btn akpp-btn-primary" id="modal-login-btn">
                    🚀 Войти
                </button>
            </form>
            
            <!-- Форма восстановления пароля (скрыта) -->
            <form id="akpp-modal-forgot-form" style="display: none;">
                <?php wp_nonce_field('akpp_reset_password_nonce', 'akpp_reset_nonce'); ?>
                <div class="akpp-form-group">
                    <label for="reset-email">Email</label>
                    <input type="email" id="reset-email" name="reset_email" placeholder="ivan@example.com" required>
                </div>
                <button type="submit" class="akpp-btn akpp-btn-primary">
                    📧 Восстановить пароль
                </button>
                <button type="button" class="akpp-btn-link" id="back-to-login">
                    ← Вернуться ко входу
                </button>
            </form>
        </div>
        
        <div class="akpp-modal-footer">
            <p>Нет аккаунта? <a href="#" id="show-register">Зарегистрироваться</a></p>
        </div>
    </div>
</div>
