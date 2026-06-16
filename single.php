<?php
/**
 * Single Post Template
 *
 * @package AKPP_Kurgan
 */

get_header();
?>

<section class="single-content" style="padding: 8rem 1rem 4rem;">
    <div class="container" style="max-width: 800px;">
        <?php while (have_posts()) : the_post(); ?>
            <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
                <header class="entry-header" style="margin-bottom: 2rem;">
                    <h1 class="entry-title"><?php the_title(); ?></h1>
                    <div class="entry-meta" style="color: var(--color-text-muted); font-size: 0.875rem; margin-top: 0.5rem;">
                        <time datetime="<?php echo get_the_date('c'); ?>">
                            <?php echo get_the_date(); ?>
                        </time>
                    </div>
                </header>

                <?php if (has_post_thumbnail()) : ?>
                    <div class="entry-thumbnail" style="margin-bottom: 2rem; border-radius: 1rem; overflow: hidden;">
                        <?php the_post_thumbnail('large', array('style' => 'width: 100%; height: auto;')); ?>
                    </div>
                <?php endif; ?>

                <div class="entry-content" style="line-height: 1.8;">
                    <?php the_content(); ?>
                </div>

                <footer class="entry-footer" style="margin-top: 3rem; padding-top: 2rem; border-top: 1px solid var(--color-border);">
                    <a href="<?php echo esc_url(home_url('/')); ?>" class="btn btn-outline">
                        &larr; Вернуться на главную
                    </a>
                </footer>
            </article>
        <?php endwhile; ?>
    </div>
</section>

<?php get_footer(); ?>
