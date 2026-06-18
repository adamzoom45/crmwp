<?php
/**
 * The template for displaying 404 pages (not found)
 *
 * @package AKPP45
 * @version 5.0
 */

get_header();
?>

<main class="site-main">
    <div class="container">
        <section class="error-404 not-found">
            <div class="error-content">
                <h1 class="error-title">404</h1>
                <h2 class="error-subtitle">Страница не найдена</h2>
                <p class="error-description">
                    К сожалению, запрашиваемая страница не существует или была перемещена.
                </p>
                
                <div class="error-actions">
                    <a href="<?php echo esc_url(home_url('/')); ?>" class="btn btn-primary">
                        Вернуться на главную
                    </a>
                    <a href="#contact" class="btn btn-outline">
                        Связаться с нами
                    </a>
                </div>

                <div class="error-search">
                    <h3>Возможно, вы искали:</h3>
                    <?php get_search_form(); ?>
                </div>
            </div>
        </section>
    </div>
</main>

<style>
.error-404 {
    min-height: 80vh;
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;
    padding: 100px 0;
}

.error-content {
    max-width: 600px;
    margin: 0 auto;
}

.error-title {
    font-size: 120px;
    font-weight: 700;
    color: var(--accent);
    text-shadow: 0 0 30px rgba(0, 255, 136, 0.5);
    margin-bottom: 20px;
    line-height: 1;
}

.error-subtitle {
    font-size: 32px;
    margin-bottom: 20px;
    color: var(--text-main);
}

.error-description {
    font-size: 18px;
    color: var(--text-muted);
    margin-bottom: 40px;
    line-height: 1.6;
}

.error-actions {
    display: flex;
    gap: 16px;
    justify-content: center;
    flex-wrap: wrap;
    margin-bottom: 60px;
}

.error-search {
    margin-top: 40px;
    padding: 30px;
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 16px;
    backdrop-filter: blur(10px);
}

.error-search h3 {
    font-size: 20px;
    margin-bottom: 20px;
    color: var(--text-main);
}

.error-search .search-form {
    display: flex;
    gap: 10px;
    max-width: 400px;
    margin: 0 auto;
}

.error-search input[type="search"] {
    flex: 1;
    padding: 12px 16px;
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: 8px;
    color: var(--text-main);
    font-size: 16px;
}

.error-search input[type="submit"] {
    padding: 12px 24px;
    background: var(--accent);
    color: var(--bg-primary);
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.error-search input[type="submit"]:hover {
    background: var(--accent-dark);
    box-shadow: 0 0 20px rgba(0, 255, 136, 0.4);
}

@media (max-width: 768px) {
    .error-title {
        font-size: 80px;
    }
    
    .error-subtitle {
        font-size: 24px;
    }
    
    .error-description {
        font-size: 16px;
    }
    
    .error-actions {
        flex-direction: column;
    }
    
    .error-actions .btn {
        width: 100%;
    }
}
</style>

<?php
get_footer();