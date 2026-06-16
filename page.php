<?php
/**
 * Page Template
 *
 * @package AKPP_Kurgan
 */

get_header();
?>

<section class="page-content" style="padding: 8rem 1rem 4rem;">
    <div class="container">
        <?php while (have_posts()) : the_post(); ?>
            <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
                <header class="entry-header" style="margin-bottom: 2rem;">
                    <h1 class="entry-title"><?php the_title(); ?></h1>
                </header>

                <div class="entry-content">
                    <?php the_content(); ?>
                </div>
            </article>
        <?php endwhile; ?>
    </div>
</section>

<?php get_footer(); ?>
