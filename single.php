<?php
/**
 * The template for displaying single posts
 *
 * @package AKPP45
 * @version 5.0
 */

get_header();
?>

<main class="site-main">
    <div class="container">
        
        <?php while (have_posts()) : the_post(); ?>

            <article id="post-<?php the_ID(); ?>" <?php post_class('single-post'); ?>>
                
                <!-- Post Header -->
                <header class="entry-header">
                    <?php the_title('<h1 class="entry-title">', '</h1>'); ?>
                    
                    <div class="entry-meta">
                        <span class="post-date">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                <line x1="16" y1="2" x2="16" y2="6"></line>
                                <line x1="8" y1="2" x2="8" y2="6"></line>
                                <line x1="3" y1="10" x2="21" y2="10"></line>
                            </svg>
                            <?php echo get_the_date(); ?>
                        </span>
                        
                        <span class="post-author">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                            <?php the_author(); ?>
                        </span>
                        
                        <?php if (has_category()) : ?>
                            <span class="post-category">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>
                                </svg>
                                <?php the_category(', '); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </header>

                <!-- Featured Image -->
                <?php if (has_post_thumbnail()) : ?>
                    <div class="post-thumbnail">
                        <?php the_post_thumbnail('large'); ?>
                    </div>
                <?php endif; ?>

                <!-- Post Content -->
                <div class="entry-content">
                    <?php
                    the_content();

                    wp_link_pages([
                        'before' => '<div class="page-links">' . esc_html__('Страницы:', 'akpp45'),
                        'after'  => '</div>',
                    ]);
                    ?>
                </div>

                <!-- Post Footer -->
                <footer class="entry-footer">
                    <?php if (has_tag()) : ?>
                        <div class="post-tags">
                            <strong>Теги:</strong>
                            <?php the_tags('<span class="tag-links">', '', '</span>'); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (get_edit_post_link()) : ?>
                        <div class="edit-link">
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
                        </div>
                    <?php endif; ?>
                </footer>

                <!-- Post Navigation -->
                <nav class="post-navigation">
                    <?php
                    the_post_navigation([
                        'prev_text' => '<span class="nav-subtitle">' . esc_html__('Предыдущая:', 'akpp45') . '</span> <span class="nav-title">%title</span>',
                        'next_text' => '<span class="nav-subtitle">' . esc_html__('Следующая:', 'akpp45') . '</span> <span class="nav-title">%title</span>',
                    ]);
                    ?>
                </nav>

                <!-- Comments -->
                <?php
                if (comments_open() || get_comments_number()) :
                    comments_template();
                endif;
                ?>

            </article>

        <?php endwhile; ?>

    </div>
</main>

<style>
.single-post {
    max-width: 800px;
    margin: 0 auto;
    padding: 60px 0;
}

.entry-header {
    margin-bottom: 40px;
}

.entry-title {
    font-size: 42px;
    margin-bottom: 20px;
    line-height: 1.2;
}

.entry-meta {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
    color: var(--text-muted);
    font-size: 14px;
}

.entry-meta span {
    display: flex;
    align-items: center;
    gap: 6px;
}

.post-thumbnail {
    margin-bottom: 40px;
    border-radius: 16px;
    overflow: hidden;
}

.post-thumbnail img {
    width: 100%;
    height: auto;
    display: block;
}

.entry-content {
    font-size: 18px;
    line-height: 1.8;
    color: var(--text-main);
}

.entry-content h2,
.entry-content h3,
.entry-content h4 {
    margin-top: 40px;
    margin-bottom: 20px;
}

.entry-content p {
    margin-bottom: 20px;
}

.entry-content img {
    max-width: 100%;
    height: auto;
    border-radius: 8px;
    margin: 20px 0;
}

.entry-content blockquote {
    border-left: 4px solid var(--accent);
    padding-left: 20px;
    margin: 30px 0;
    font-style: italic;
    color: var(--text-muted);
}

.entry-footer {
    margin-top: 40px;
    padding-top: 20px;
    border-top: 1px solid var(--border);
}

.post-tags {
    margin-bottom: 20px;
}

.tag-links {
    display: inline-flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-left: 10px;
}

.tag-links a {
    padding: 4px 12px;
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 20px;
    font-size: 14px;
    color: var(--accent);
}

.tag-links a:hover {
    background: var(--accent);
    color: var(--bg-primary);
}

.post-navigation {
    margin-top: 60px;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.post-navigation a {
    padding: 20px;
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 12px;
    transition: all 0.3s ease;
}

.post-navigation a:hover {
    border-color: var(--accent);
    transform: translateY(-2px);
}

.nav-subtitle {
    display: block;
    font-size: 12px;
    color: var(--text-muted);
    text-transform: uppercase;
    margin-bottom: 8px;
}

.nav-title {
    font-size: 16px;
    font-weight: 600;
    color: var(--text-main);
}

@media (max-width: 768px) {
    .entry-title {
        font-size: 32px;
    }
    
    .entry-content {
        font-size: 16px;
    }
    
    .post-navigation {
        grid-template-columns: 1fr;
    }
}
</style>

<?php
get_footer();