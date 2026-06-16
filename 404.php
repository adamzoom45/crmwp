<?php
/**
 * 404 Page Template
 *
 * @package AKPP_Kurgan
 */

get_header();
?>

<section class="error-404" style="padding: 8rem 1rem; min-height: 60vh; display: flex; align-items: center;">
    <div class="container" style="text-align: center;">
        <h1 style="font-size: 6rem; color: var(--color-primary); margin-bottom: 1rem;">404</h1>
        <h2 style="margin-bottom: 1rem;">Страница не найдена</h2>
        <p style="color: var(--color-text-muted); margin-bottom: 2rem;">
            К сожалению, запрашиваемая страница не существует или была перемещена.
        </p>
        <a href="<?php echo esc_url(home_url('/')); ?>" class="btn btn-primary">
            Вернуться на главную
        </a>
    </div>
</section>

<?php get_footer(); ?>
