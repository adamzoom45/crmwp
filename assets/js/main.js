/**
 * АКПП45 - Основные скрипты
 */
(function($) {
    'use strict';
    
    $(document).ready(function() {
        
        // Мобильное меню
        var $mobileToggle = $('.mobile-menu-toggle');
        var $mobileMenu = $('#mobileMenu');
        var $hamburger = $('.hamburger');
        
        $mobileToggle.on('click', function() {
            $mobileMenu.toggleClass('active');
            $hamburger.toggleClass('active');
            
            var isExpanded = $mobileMenu.hasClass('active');
            $mobileToggle.attr('aria-expanded', isExpanded);
            
            // Блокируем скролл body когда меню открыто
            if (isExpanded) {
                $('body').css('overflow', 'hidden');
            } else {
                $('body').css('overflow', '');
            }
        });
        
        // Закрытие мобильного меню при клике на ссылку
        $mobileMenu.on('click', 'a', function() {
            $mobileMenu.removeClass('active');
            $hamburger.removeClass('active');
            $mobileToggle.attr('aria-expanded', 'false');
            $('body').css('overflow', '');
        });
        
        // Закрытие мобильного меню при клике вне его
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.site-header').length && !$(e.target).closest('#mobileMenu').length) {
                $mobileMenu.removeClass('active');
                $hamburger.removeClass('active');
                $mobileToggle.attr('aria-expanded', 'false');
                $('body').css('overflow', '');
            }
        });
        
        // Плавная прокрутка к якорям
        $('a[href^="#"]').on('click', function(e) {
            var target = $(this.getAttribute('href'));
            if (target.length) {
                e.preventDefault();
                $('html, body').stop().animate({
                    scrollTop: target.offset().top - 100
                }, 600);
            }
        });
        
        // Фиксация шапки при скролле
        var $header = $('.site-header');
        var lastScroll = 0;
        
        $(window).on('scroll', function() {
            var currentScroll = $(this).scrollTop();
            
            if (currentScroll > 100) {
                $header.addClass('scrolled');
            } else {
                $header.removeClass('scrolled');
            }
            
            lastScroll = currentScroll;
        });
        
        // Защита email от спама
        $(document).on('click', '.email-protected', function() {
            var user = $(this).data('user');
            var domain = $(this).data('domain');
            if (user && domain) {
                window.location.href = 'mailto:' + user + '@' + domain;
            }
        });
        
        // Маска телефона для всех полей tel
        $(document).on('input', 'input[type="tel"]', function() {
            var value = $(this).val().replace(/\D/g, '');
            
            if (value.startsWith('8')) {
                value = '7' + value.substring(1);
            }
            
            if (value.startsWith('7')) {
                var formatted = '+7';
                
                if (value.length > 1) {
                    formatted += ' (' + value.substring(1, 4);
                }
                if (value.length >= 5) {
                    formatted += ') ' + value.substring(4, 7);
                }
                if (value.length >= 8) {
                    formatted += '-' + value.substring(7, 9);
                }
                if (value.length >= 10) {
                    formatted += '-' + value.substring(9, 11);
                }
                
                $(this).val(formatted);
            }
        });
        
    });
    
})(jQuery);