<?php
/**
 * The template for displaying all pages
 *
 * @package AKPP45
 * @version 5.0
 */

get_header();
?>

<main class="site-main">
    <div class="container">
        
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title"><?php the_title(); ?></h1>
            
            <!-- Breadcrumbs (optional) -->
            <?php if (function_exists('akpp_breadcrumbs')) : ?>
                <div class="breadcrumbs">
                    <?php akpp_breadcrumbs(); ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Page Content -->
        <div class="page-content">
            <?php
            while (have_posts()) :
                the_post();
                ?>
                
                <article id="post-<?php the_ID(); ?>" <?php post_class('page-article'); ?>>
                    <?php if (has_post_thumbnail()) : ?>
                        <div class="page-thumbnail">
                            <?php the_post_thumbnail('large'); ?>
                        </div>
                    <?php endif; ?>

                    <div class="entry-content">
                        <?php
                        the_content();

                        wp_link_pages([
                            'before' => '<div class="page-links">' . esc_html__('Страницы:', 'akpp45'),
                            'after'  => '</div>',
                        ]);
                        ?>
                    </div>

                    <?php if (get_edit_post_link()) : ?>
                        <footer class="entry-footer">
                            <?php
                            edit_post_link(
                                sprintf(
                                    wp_kses(
                                        __('Редактировать <span class="sr-only">%s</span>', 'akpp45'),
                                        [
                                            'span' => [
                                                'class' => [],
                                            ],
                                        ]
                                    ),
                                    wp_kses_post(get_the_title())
                                ),
                                '<span class="edit-link">',
                                '</span>'
                            );
                            ?>
                        </footer>
                    <?php endif; ?>
                </article>

                <?php
                // If comments are open or we have at least one comment, load up the comment template.
                if (comments_open() || get_comments_number()) :
                    comments_template();
                endif;

            endwhile;
            ?>
        </div>

    </div>
</main>

<?php
get_footer();