<?php
/**
 * Главный шаблон темы (шаблон по умолчанию)
 *
 * @package AKPP45
 */

get_header();
?>

<main class="site-main">
    <div class="container">
        
        <?php if (have_posts()) : ?>
            
            <header class="page-header">
                <h1 class="page-title">
                    <?php
                    if (is_home()) {
                        _e('Блог компании', 'akpp45');
                    } elseif (is_search()) {
                        printf(__('Результаты поиска: %s', 'akpp45'), '<span>' . get_search_query() . '</span>');
                    } elseif (is_archive()) {
                        the_archive_title('<h1 class="page-title">', '</h1>');
                        the_archive_description('<div class="archive-description">', '</div>');
                    } else {
                        single_post_title();
                    }
                    ?>
                </h1>
            </header>

            <div class="posts-grid">
                <?php while (have_posts()) : the_post(); ?>
                    <article id="post-<?php the_ID(); ?>" <?php post_class('post-card'); ?>>
                        <?php if (has_post_thumbnail()) : ?>
                            <div class="post-thumbnail">
                                <a href="<?php the_permalink(); ?>">
                                    <?php the_post_thumbnail('medium_large', ['class' => 'post-img']); ?>
                                </a>
                            </div>
                        <?php endif; ?>
                        
                        <div class="post-content">
                            <h2 class="post-title">
                                <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                            </h2>
                            
                            <div class="post-meta">
                                <span class="post-date">
                                    📅 <?php echo get_the_date('d.m.Y'); ?>
                                </span>
                                <span class="post-author">
                                    👤 <?php the_author(); ?>
                                </span>
                                <span class="post-category">
                                    📂 <?php the_category(', '); ?>
                                </span>
                            </div>
                            
                            <div class="post-excerpt">
                                <?php the_excerpt(); ?>
                            </div>
                            
                            <a href="<?php the_permalink(); ?>" class="read-more">
                                <?php _e('Читать далее →', 'akpp45'); ?>
                            </a>
                        </div>
                    </article>
                <?php endwhile; ?>
            </div>

            <?php
            // Пагинация
            the_posts_pagination([
                'mid_size' => 2,
                'prev_text' => '← ' . __('Назад', 'akpp45'),
                'next_text' => __('Вперед', 'akpp45') . ' →',
                'screen_reader_text' => ' ',
            ]);
            ?>

        <?php else : ?>
            <div class="nothing-found">
                <h2><?php _e('Ничего не найдено', 'akpp45'); ?></h2>
                <p><?php _e('Извините, но по вашему запросу ничего не найдено.', 'akpp45'); ?></p>
                <?php get_search_form(); ?>
            </div>
        <?php endif; ?>
        
    </div>
</main>

<style>
/* Основные стили */
.site-main {
    padding: 60px 0;
    min-height: 500px;
}

.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
}

/* Заголовок страницы */
.page-header {
    text-align: center;
    margin-bottom: 50px;
}

.page-title {
    font-size: 36px;
    color: #333;
    margin-bottom: 15px;
}

.archive-description {
    color: #666;
    font-size: 16px;
}

/* Сетка постов */
.posts-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 30px;
    margin-bottom: 50px;
}

/* Карточка поста */
.post-card {
    background: #fff;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
    transition: transform 0.3s, box-shadow 0.3s;
}

.post-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
}

.post-thumbnail {
    overflow: hidden;
    height: 220px;
}

.post-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s;
}

.post-card:hover .post-img {
    transform: scale(1.05);
}

.post-content {
    padding: 20px;
}

.post-title {
    font-size: 20px;
    margin-bottom: 12px;
}

.post-title a {
    color: #333;
    text-decoration: none;
    transition: color 0.2s;
}

.post-title a:hover {
    color: #667eea;
}

.post-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    font-size: 13px;
    color: #999;
    margin-bottom: 15px;
}

.post-meta a {
    color: #999;
    text-decoration: none;
}

.post-meta a:hover {
    color: #667eea;
}

.post-excerpt {
    color: #666;
    font-size: 14px;
    line-height: 1.6;
    margin-bottom: 15px;
}

.read-more {
    display: inline-block;
    color: #667eea;
    text-decoration: none;
    font-weight: 500;
    transition: color 0.2s;
}

.read-more:hover {
    color: #764ba2;
}

/* Пагинация */
.pagination {
    display: flex;
    justify-content: center;
    gap: 8px;
    margin-top: 30px;
}

.pagination .page-numbers {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 40px;
    height: 40px;
    padding: 0 10px;
    background: #fff;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    color: #333;
    text-decoration: none;
    transition: all 0.2s;
}

.pagination .page-numbers.current {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-color: transparent;
    color: #fff;
}

.pagination .page-numbers:hover:not(.current) {
    background: #f5f5f5;
    border-color: #667eea;
}

/* Сообщение "Ничего не найдено" */
.nothing-found {
    text-align: center;
    padding: 60px 20px;
    background: #fff;
    border-radius: 16px;
}

.nothing-found h2 {
    font-size: 28px;
    margin-bottom: 15px;
    color: #333;
}

.nothing-found p {
    color: #666;
    margin-bottom: 20px;
}

.search-form {
    display: flex;
    max-width: 400px;
    margin: 0 auto;
    gap: 10px;
}

.search-form input[type="search"] {
    flex: 1;
    padding: 12px 15px;
    border: 1px solid #e0e0e0;
    border-radius: 12px;
    font-size: 14px;
}

.search-form button {
    padding: 12px 25px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: #fff;
    border: none;
    border-radius: 12px;
    cursor: pointer;
}

/* Адаптивность */
@media (max-width: 768px) {
    .site-main {
        padding: 40px 0;
    }
    
    .page-title {
        font-size: 28px;
    }
    
    .posts-grid {
        grid-template-columns: 1fr;
        gap: 20px;
    }
    
    .post-meta {
        gap: 10px;
        font-size: 11px;
    }
}

@media (max-width: 480px) {
    .post-thumbnail {
        height: 180px;
    }
    
    .post-title {
        font-size: 18px;
    }
}
</style>

<?php get_footer(); ?>
