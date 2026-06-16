</main>

<footer class="site-footer">
    <div class="container">
        <div class="footer-inner">
            <div class="footer-section">
                <h4>АКПП Курган</h4>
                <p class="text-muted">Профессиональный ремонт автоматических коробок передач в Кургане с гарантией качества.</p>
            </div>

            <div class="footer-section">
                <h4>Навигация</h4>
                <?php
                wp_nav_menu(array(
                    'theme_location' => 'footer',
                    'container'      => false,
                    'fallback_cb'    => 'akpp_fallback_menu',
                ));
                ?>
            </div>

            <div class="footer-section">
                <h4>Контакты</h4>
                <ul>
                    <li>
                        <a href="tel:<?php echo esc_attr(str_replace(array(' ', '(', ')', '-'), '', akpp_get_option('phone_1', '+79638669996'))); ?>">
                            <?php echo esc_html(akpp_get_option('phone_1', '+7 (963) 866-99-96')); ?>
                        </a>
                    </li>
                    <li>
                        <a href="https://t.me/<?php echo esc_attr(str_replace('@', '', akpp_get_option('telegram', 'akppkgn'))); ?>" target="_blank" rel="noopener">
                            <?php echo esc_html(akpp_get_option('telegram', '@akppkgn')); ?>
                        </a>
                    </li>
                    <li><?php echo esc_html(akpp_get_option('address', 'г. Курган')); ?></li>
                </ul>
            </div>
        </div>

        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> АКПП Курган. Все права защищены.</p>
        </div>
    </div>
</footer>

<div class="chat-widget">
    <a href="https://t.me/<?php echo esc_attr(str_replace('@', '', akpp_get_option('telegram', 'akppkgn'))); ?>" 
       target="_blank" 
       rel="noopener" 
       class="chat-btn" 
       aria-label="Написать в Telegram">
        <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21.198 2.433a2.242 2.242 0 0 0-1.022.215l-16.5 7.5a2.25 2.25 0 0 0 .126 4.073l3.9 1.205 1.765 5.6a1.5 1.5 0 0 0 2.55.502l2.422-2.879 4.283 3.166a2.25 2.25 0 0 0 3.502-1.272l3.25-15.5a2.25 2.25 0 0 0-2.276-2.61z"></path>
        </svg>
    </a>
</div>

<?php wp_footer(); ?>
</body>
</html>