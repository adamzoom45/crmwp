/**
 * AKPP45 Main JavaScript
 *
 * @package AKPP45
 * @version 5.0
 */

(function($) {
    'use strict';

    // ==================================================================
    // SMOOTH SCROLLING
    // ==================================================================
    $('a[href^="#"]').on('click', function(event) {
        var target = $(this.getAttribute('href'));
        if (target.length) {
            event.preventDefault();
            $('html, body').stop().animate({
                scrollTop: target.offset().top - 80
            }, 800, 'swing');
        }
    });

    // ==================================================================
    // HEADER SCROLL EFFECT
    // ==================================================================
    $(window).on('scroll', function() {
        var scrollTop = $(this).scrollTop();
        var header = $('.site-header');
        
        if (scrollTop > 100) {
            header.addClass('scrolled');
        } else {
            header.removeClass('scrolled');
        }
    });

    // ==================================================================
    // MOBILE MENU
    // ==================================================================
    $('.mobile-menu-btn').on('click', function() {
        $(this).toggleClass('active');
        $('.mobile-menu').toggleClass('active');
        $('body').toggleClass('menu-open');
    });

    // Close mobile menu on link click
    $('.mobile-menu a').on('click', function() {
        $('.mobile-menu-btn').removeClass('active');
        $('.mobile-menu').removeClass('active');
        $('body').removeClass('menu-open');
    });

    // ==================================================================
    // ANIMATIONS ON SCROLL
    // ==================================================================
    function animateOnScroll() {
        $('.service-card, .contact-card, .advantage-card').each(function() {
            var elementTop = $(this).offset().top;
            var elementBottom = elementTop + $(this).outerHeight();
            var viewportTop = $(window).scrollTop();
            var viewportBottom = viewportTop + $(window).height();

            if (elementBottom > viewportTop && elementTop < viewportBottom) {
                $(this).addClass('animated');
            }
        });
    }

    $(window).on('scroll resize load', animateOnScroll);

    // ==================================================================
    // FORM VALIDATION
    // ==================================================================
    $('form').on('submit', function(e) {
        var isValid = true;
        
        $(this).find('[required]').each(function() {
            if (!$(this).val().trim()) {
                isValid = false;
                $(this).addClass('error');
            } else {
                $(this).removeClass('error');
            }
        });

        if (!isValid) {
            e.preventDefault();
            alert('Пожалуйста, заполните все обязательные поля.');
        }
    });

    // ==================================================================
    // PHONE INPUT MASK
    // ==================================================================
    $('input[type="tel"]').on('input', function() {
        var value = $(this).val().replace(/\D/g, '');
        var formattedValue = '';
        
        if (value.length > 0) {
            formattedValue = '+7';
            if (value.length > 1) {
                formattedValue += ' (' + value.substring(1, 4);
            }
            if (value.length > 4) {
                formattedValue += ') ' + value.substring(4, 7);
            }
            if (value.length > 7) {
                formattedValue += '-' + value.substring(7, 9);
            }
            if (value.length > 9) {
                formattedValue += '-' + value.substring(9, 11);
            }
        }
        
        $(this).val(formattedValue);
    });

    // ==================================================================
    // LAZY LOADING IMAGES
    // ==================================================================
    if ('IntersectionObserver' in window) {
        var imageObserver = new IntersectionObserver(function(entries, observer) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    var image = entry.target;
                    image.src = image.dataset.src;
                    image.classList.remove('lazy');
                    imageObserver.unobserve(image);
                }
            });
        });

        document.querySelectorAll('img.lazy').forEach(function(img) {
            imageObserver.observe(img);
        });
    } else {
        // Fallback for browsers without IntersectionObserver
        document.querySelectorAll('img.lazy').forEach(function(img) {
            img.src = img.dataset.src;
            img.classList.remove('lazy');
        });
    }

    // ==================================================================
    // BACK TO TOP BUTTON
    // ==================================================================
    $(window).on('scroll', function() {
        if ($(this).scrollTop() > 300) {
            $('.back-to-top').fadeIn();
        } else {
            $('.back-to-top').fadeOut();
        }
    });

    $('.back-to-top').on('click', function(e) {
        e.preventDefault();
        $('html, body').animate({
            scrollTop: 0
        }, 800);
    });

    // ==================================================================
    // CONSOLE LOGO
    // ==================================================================
    console.log('%cАКПП Курган', 'color: #00ff88; font-size: 24px; font-weight: bold;');
    console.log('%cПрофессиональный ремонт АКПП', 'color: #9ca3af; font-size: 14px;');

})(jQuery);