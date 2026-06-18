<?php
/**
 * The template for displaying archive pages
 *
 * @package AKPP45
 * @version 5.0
 */

get_header();
?>

<main class="site-main">
    <div class="container">
        
        <header class="page-header">
            <?php
            the_archive_title('<h1 class="page-title">', '</h1>');
            the_archive_description('<div class="archive-description">', '</div>');
            ?>
        </header>

        <?php if (have_posts()) : ?>

            <div class="posts-grid">
                <?php
                while (have_posts()) :
                    the_post();
                    ?>
                    
                    <article id="post-<?php the_ID(); ?>" <?php post_class('post-card'); ?>>
                        
                        <?php if (has_post_thumbnail()) : ?>
                            <div class="post-card-thumbnail">
                                <a href="<?php the_permalink(); ?>">
                                    <?php the_post_thumbnail('medium'); ?>
                                </a>
                            </div>
                        <?php endif; ?>

                        <div class="post-card-content">
                            <header class="post-card-header">
                                <h2 class="post-card-title">
                                    <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                                </h2>
                                
                                <div class="post-card-meta">
                                    <span class="post-date"><?php echo get_the_date(); ?></span>
                                    <?php if (has_category()) : ?>
                                        <span class="post-category"><?php the_category(', '); ?></span>
                                    <?php endif; ?>
                                </div>
                            </header>

                            <div class="post-card-excerpt">
                                <?php the_excerpt(); ?>
                            </div>

                            <div class="post-card-footer">
                                <a href="<?php the_permalink(); ?>" class="read-more">
                                    Читать далее →
                                </a>
                            </div>
                        </div>

                    </article>

                <?php endwhile; ?>
            </div>

            <!-- Pagination -->
            <nav class="pagination">
                <?php
                the_posts_pagination([
                    'mid_size'  => 2,
                    'prev_text' => __('← Назад', 'akpp45'),
                    'next_text' => __('Вперед →', 'akpp45'),
                ]);
                ?>
            </nav>

        <?php else : ?>

            <div class="no-posts">
                <h2><?php esc_html_e('Ничего не найдено', 'akpp45'); ?></h2>
                <p><?php esc_html_e('К сожалению, по вашему запросу ничего не найдено.', 'akpp45'); ?></p>
                <?php get_search_form(); ?>
            </div>

        <?php endif; ?>

    </div>
</main>

<style>
.page-header {
    text-align: center;
    margin-bottom: 60px;
    padding: 40px 0;
}

.page-title {
    font-size: 42px;
    margin-bottom: 20px;
}

.archive-description {
    font-size: 18px;
    color: var(--text-muted);
    max-width: 600px;
    margin: 0 auto;
}

.posts-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 30px;
    margin-bottom: 60px;
}

.post-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 16px;
    overflow: hidden;
    transition: all 0.3s ease;
    backdrop-filter: blur(10px);
}

.post-card:hover {
    border-color: var(--accent);
    transform: translateY(-5px);
    box-shadow: 0 10px 40px rgba(0, 255, 136, 0.2);
}

.post-card-thumbnail {
    aspect-ratio: 16/9;
    overflow: hidden;
}

.post-card-thumbnail img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.post-card:hover .post-card-thumbnail img {
    transform: scale(1.05);
}

.post-card-content {
    padding: 24px;
}

.post-card-title {
    font-size: 22px;
    margin-bottom: 12px;
    line-height: 1.3;
}

.post-card-title a {
    color: var(--text-main);
}

.post-card-title a:hover {
    color: var(--accent);
}

.post-card-meta {
    display: flex;
    gap: 16px;
    font-size: 14px;
    color: var(--text-muted);
    margin-bottom: 16px;
}

.post-card-excerpt {
    color: var(--text-muted);
    font-size: 15px;
    line-height: 1.6;
    margin-bottom: 20px;
}

.post-card-footer {
    padding-top: 16px;
    border-top: 1px solid var(--border);
}

.read-more {
    color: var(--accent);
    font-weight: 600;
    font-size: 14px;
}

.read-more:hover {
    color: var(--accent-dark);
}

.pagination {
    display: flex;
    justify-content: center;
    gap: 8px;
    margin-top: 60px;
}

.pagination .page-numbers {
    padding: 10px 16px;
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 8px;
    color: var(--text-main);
    transition: all 0.3s ease;
}

.pagination .page-numbers.current,
.pagination .page-numbers:hover {
    background: var(--accent);
    color: var(--bg-primary);
    border-color: var(--accent);
}

.no-posts {
    text-align: center;
    padding: 80px 20px;
}

.no-posts h2 {
    font-size: 32px;
    margin-bottom: 20px;
}

.no-posts p {
    font-size: 18px;
    color: var(--text-muted);
    margin-bottom: 30px;
}

@media (max-width: 768px) {
    .posts-grid {
        grid-template-columns: 1fr;
    }
    
    .page-title {
        font-size: 32px;
    }
}
</style>

<?php
get_footer();