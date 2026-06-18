<?php
/**
 * Template for displaying search form
 *
 * @package AKPP45
 * @version 5.0
 */

?>

<form role="search" method="get" class="search-form" action="<?php echo esc_url(home_url('/')); ?>">
    <label class="sr-only" for="search-field"><?php esc_html_e('Поиск:', 'akpp45'); ?></label>
    <input 
        type="search" 
        id="search-field" 
        class="search-field" 
        placeholder="<?php esc_attr_e('Поиск...', 'akpp45'); ?>" 
        value="<?php echo get_search_query(); ?>" 
        name="s" 
        required
    />
    <button type="submit" class="search-submit">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="11" cy="11" r="8"></circle>
            <path d="m21 21-4.35-4.35"></path>
        </svg>
        <span class="sr-only"><?php esc_html_e('Найти', 'akpp45'); ?></span>
    </button>
</form>

<style>
.search-form {
    display: flex;
    gap: 10px;
    max-width: 100%;
}

.search-field {
    flex: 1;
    padding: 12px 16px;
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 8px;
    color: var(--text-main);
    font-size: 16px;
    transition: all 0.3s ease;
}

.search-field:focus {
    outline: none;
    border-color: var(--accent);
    box-shadow: 0 0 10px rgba(0, 255, 136, 0.2);
}

.search-submit {
    padding: 12px 20px;
    background: var(--accent);
    color: var(--bg-primary);
    border: none;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
}

.search-submit:hover {
    background: var(--accent-dark);
    box-shadow: 0 0 20px rgba(0, 255, 136, 0.4);
    transform: translateY(-2px);
}
</style>