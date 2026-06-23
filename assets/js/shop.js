/**
 * АКПП45 Shop - JavaScript
 */
(function($) {
    'use strict';
    
    var AKPP_Shop = {
        init: function() {
            this.bindEvents();
        },
        
        bindEvents: function() {
            // Добавление в корзину
            $(document).on('click', '.btn-add-to-cart', this.addToCart);
            
            // Удаление из корзины
            $(document).on('click', '.btn-remove-from-cart', this.removeFromCart);
            
            // Обновление количества
            $(document).on('change', '.cart-quantity', this.updateQuantity);
            
            // Оформление заказа
            $(document).on('submit', '#akpp-checkout-form', this.checkout);
        },
        
        addToCart: function(e) {
            e.preventDefault();
            var productId = $(this).data('product-id');
            var $btn = $(this);
            
            $btn.prop('disabled', true).text('⏳ Добавление...');
            
            $.ajax({
                url: akpp_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'akpp_shop_add_to_cart',
                    product_id: productId,
                    quantity: 1,
                    nonce: akpp_ajax.nonce
                },
                dataType: 'json',
                success: function(res) {
                    if (res.success) {
                        AKPP_Shop.showNotice('✅ ' + res.message, 'success');
                        AKPP_Shop.updateCartCount();
                    } else {
                        AKPP_Shop.showNotice('❌ ' + res.message, 'error');
                    }
                    $btn.prop('disabled', false).text('В корзину');
                },
                error: function() {
                    AKPP_Shop.showNotice('❌ Ошибка соединения', 'error');
                    $btn.prop('disabled', false).text('В корзину');
                }
            });
        },
        
        removeFromCart: function(e) {
            e.preventDefault();
            var cartId = $(this).data('cart-id');
            
            if (!confirm('Удалить товар из корзины?')) return;
            
            $.ajax({
                url: akpp_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'akpp_shop_remove_from_cart',
                    cart_id: cartId,
                    nonce: akpp_ajax.nonce
                },
                dataType: 'json',
                success: function(res) {
                    if (res.success) {
                        location.reload();
                    } else {
                        AKPP_Shop.showNotice('❌ ' + res.message, 'error');
                    }
                }
            });
        },
        
        updateQuantity: function() {
            var cartId = $(this).data('cart-id');
            var quantity = $(this).val();
            
            $.ajax({
                url: akpp_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'akpp_shop_update_cart',
                    cart_id: cartId,
                    quantity: quantity,
                    nonce: akpp_ajax.nonce
                },
                dataType: 'json',
                success: function(res) {
                    if (res.success) {
                        location.reload();
                    }
                }
            });
        },
        
        checkout: function(e) {
            e.preventDefault();
            
            var $form = $(this);
            var $btn = $form.find('button[type="submit"]');
            
            $btn.prop('disabled', true).text('⏳ Оформление...');
            
            var formData = $form.serializeArray();
            formData.push({name: 'action', value: 'akpp_shop_checkout'});
            
            $.ajax({
                url: akpp_ajax.ajax_url,
                type: 'POST',
                data: formData,
                dataType: 'json',
                success: function(res) {
                    if (res.success) {
                        AKPP_Shop.showNotice('✅ Заказ оформлен! Номер: ' + res.order_number, 'success');
                        setTimeout(function() {
                            window.location.href = '/';
                        }, 2000);
                    } else {
                        AKPP_Shop.showNotice('❌ ' + res.message, 'error');
                        $btn.prop('disabled', false).text('Оформить заказ');
                    }
                },
                error: function() {
                    AKPP_Shop.showNotice(' Ошибка соединения', 'error');
                    $btn.prop('disabled', false).text('Оформить заказ');
                }
            });
        },
        
        updateCartCount: function() {
            $.ajax({
                url: akpp_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'akpp_shop_get_cart',
                    nonce: akpp_ajax.nonce
                },
                dataType: 'json',
                success: function(res) {
                    if (res.success) {
                        $('.cart-count').text(res.data.count);
                    }
                }
            });
        },
        
        showNotice: function(message, type) {
            var bgColor = type === 'success' ? '#00ff88' : '#fc8181';
            var textColor = type === 'success' ? '#0a0f1c' : '#fff';
            var $notice = $('<div style="position:fixed;top:20px;right:20px;background:' + bgColor + ';color:' + textColor + ';padding:16px 24px;border-radius:8px;box-shadow:0 4px 20px rgba(0,0,0,0.3);z-index:99999;font-weight:600;">' + message + '</div>');
            $('body').append($notice);
            setTimeout(function() { $notice.fadeOut(300, function() { $(this).remove(); }); }, 3000);
        }
    };
    
    $(document).ready(function() {
        AKPP_Shop.init();
    });
    
})(jQuery);