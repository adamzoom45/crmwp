<?php if (!defined('ABSPATH')) exit; ?>
<?php if (function_exists('akpp_tpl_skin_active') && akpp_tpl_skin_active()): $d = akpp_tpl_data(); ?>
<footer class="tpl-footer" id="footer"><div class="tpl-footer-in">
    <div><?php echo esc_html($d['footer']); ?></div>
    <nav class="tpl-footer-links">
        <a href="<?php echo esc_url(home_url('/#services')); ?>">Услуги</a>
        <a href="<?php echo esc_url(home_url('/shop/')); ?>">Магазин</a>
        <a href="<?php echo esc_url(home_url('/#warranty')); ?>">Гарантия</a>
        <a href="<?php echo esc_url(home_url('/#faq')); ?>">Вопросы</a>
        <a href="<?php echo esc_url(home_url('/#contacts')); ?>">Контакты</a>
        <a href="<?php echo esc_url(home_url('/lk/')); ?>">Кабинет</a>
    </nav>
</div></footer>
<?php endif; ?>
