<?php
/**
 * Модальное окно регистрации в CRM
 * 
 * @package AKPP45_CRM
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div id="register-modal" class="akpp-modal-overlay">
    <div class="akpp-modal-container">
        <div class="akpp-modal-header">
            <div class="akpp-modal-icon">📝</div>
            <h2>Регистрация в CRM</h2>
            <button class="akpp-modal-close">&times;</button>
        </div>
        
        <div class="akpp-modal-body">
            <div id="register-message" class="akpp-message"></div>
            
            <form id="akpp-modal-register-form">
                <?php wp_nonce_field('akpp_client_register_nonce', 'akpp_register_nonce'); ?>
                
                <div class="akpp-form-row">
                    <div class="akpp-form-group">
                        <label for="modal-name">ФИО <span class="required">*</span></label>
                        <input type="text" id="modal-name" name="name" placeholder="Иванов Иван Иванович" required>
                    </div>
                    
                    <div class="akpp-form-group">
                        <label for="modal-phone">Телефон <span class="required">*</span></label>
                        <input type="tel" id="modal-phone" name="phone" placeholder="+7 (___) ___-__-__" required>
                    </div>
                </div>
                
                <div class="akpp-form-row">
                    <div class="akpp-form-group">
                        <label for="modal-email">Email <span class="required">*</span></label>
                        <input type="email" id="modal-email" name="email" placeholder="ivan@example.com" required>
                    </div>
                    
                    <div class="akpp-form-group">
                        <label for="modal-car">Марка автомобиля</label>
                        <input type="text" id="modal-car" name="car_brand" placeholder="Toyota, BMW...">
                    </div>
                </div>
                
                <div class="akpp-form-group">
                    <label for="modal-problem">Опишите проблему с АКПП</label>
                    <textarea id="modal-problem" name="problem" rows="3" placeholder="Рывки при переключении, шум, отсутствие передач..."></textarea>
                </div>
                
                <button type="submit" class="akpp-btn akpp-btn-primary" id="modal-register-btn">
                    📝 Зарегистрироваться
                </button>
            </form>
        </div>
        
        <div class="akpp-modal-footer">
            <p>Уже есть аккаунт? <a href="#" id="show-login">Войти в CRM</a></p>
        </div>
    </div>
</div>
