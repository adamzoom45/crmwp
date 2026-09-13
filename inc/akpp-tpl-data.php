<?php
if (!defined('ABSPATH')) exit;

function akpp_tpl_fields() {
    return [
        'brand'=>'Бренд / логотип','badge'=>'Бейдж над заголовком','hero_title'=>'Заголовок (1‑я часть)','hero_accent'=>'Заголовок (акцент)','hero_sub'=>'Подзаголовок',
        'phone_display'=>'Телефон (отображение)','phone_href'=>'Телефон (tel:)','telegram'=>'Telegram','email'=>'Email (пусто = скрыть)',
        'cta_nav'=>'Кнопка в шапке','cta1'=>'Кнопка №1','cta2'=>'Кнопка №2','lk_label'=>'Кнопка ЛК в шапке',
        'nav1'=>'Меню: услуги','nav2'=>'Меню: как работаем','nav3'=>'Меню: контакты',
        'services_title'=>'Секция услуг — заголовок',
        'b1_title'=>'Услуга 01 — заголовок','b1_text'=>'Услуга 01 — текст','b2_title'=>'Услуга 02 — заголовок','b2_text'=>'Услуга 02 — текст','b3_title'=>'Услуга 03 — заголовок','b3_text'=>'Услуга 03 — текст',
        'shop_title'=>'Магазин — заголовок','shop_sub'=>'Магазин — подзаголовок',
        'steps_title'=>'Шаги — заголовок','s1'=>'Шаг 01','s2'=>'Шаг 02','s3'=>'Шаг 03',
        'warranty_title'=>'Гарантия — заголовок','warranty_text'=>'Гарантия — текст',
        'faq1_q'=>'Вопрос 1','faq1_a'=>'Ответ 1','faq2_q'=>'Вопрос 2','faq2_a'=>'Ответ 2','faq3_q'=>'Вопрос 3','faq3_a'=>'Ответ 3',
        'address'=>'Адрес','hours'=>'Часы работы',
        'footer'=>'Подвал (строка)',
    ];
}

function akpp_tpl_defaults() {
    if (get_option('akpp_master_mode')) {
        return [
            'brand'=>'AKPP45.RU','badge'=>'⚡ Опыт 11 лет • Запчасти по себестоимости',
            'hero_title'=>'Ремонт АКПП Toyota и Lexus','hero_accent'=>'в Кургане',
            'hero_sub'=>'Специализированный сервис по восстановлению классических гидравлических трансмиссий. Также Hyundai, Kia, Mazda, Ford, Renault, Mitsubishi.',
            'phone_display'=>'+7 (963) 866-99-96','phone_href'=>'tel:+79638669996','telegram'=>'https://t.me/akppkgn','email'=>'',
            'cta_nav'=>'📞 Записаться','cta1'=>'📝 Записаться на ремонт','cta2'=>'💬 Telegram','lk_label'=>'👤 Кабинет',
            'nav1'=>'Услуги','nav2'=>'Как работаем','nav3'=>'Контакты',
            'services_title'=>'Наша специализация',
            'b1_title'=>'Диагностика и дефектовка','b1_text'=>'Полная разборка и оценка состояния до начала ремонта — вы знаете, за что платите.',
            'b2_title'=>'Ремонт под ключ','b2_text'=>'Гидротрансформатор, механика, электроника. Запчасти в наличии — без ожидания поставок.',
            'b3_title'=>'Гарантия на работы','b3_text'=>'Официальная гарантия на все виды работ и установленные запчасти.',
            'shop_title'=>'Магазин запчастей','shop_sub'=>'Каталог, корзина и заказы — прямо на сайте.',
            'steps_title'=>'Как мы работаем',
            's1'=>'Звонок или заявка → диагностика и дефектовка с понятной сметой.',
            's2'=>'Ремонт в согласованный срок, фото‑отчёты на каждом этапе.',
            's3'=>'Выдача с гарантийным талоном и рекомендациями по обслуживанию.',
            'warranty_title'=>(get_option('akpp_warranty_title','')!==''?get_option('akpp_warranty_title'):'Гарантия и договор'),'warranty_text'=>(get_option('akpp_warranty_text','')!==''?get_option('akpp_warranty_text'):'Работаем по договору‑оферте. Гарантия на работы и запчасти фиксируется в договоре.'),
            'faq1_q'=>'Сколько стоит ремонт АКПП?','faq1_a'=>'Точная цена — после диагностики. Дефектовку делаем до начала работ и согласовываем смету по телефону.',
            'faq2_q'=>'Сколько длится ремонт?','faq2_a'=>'В среднем 5–10 дней в зависимости от состояния коробки. Большинство запчастей — в наличии.',
            'faq3_q'=>'Какая гарантия?','faq3_a'=>'Гарантия на работы и запчасти; условия прописываются в договоре‑оферте.',
            'address'=>'г. Курган','hours'=>'Пн–Сб: 9:00–19:00',
            'footer'=>'© AKPP45.RU — ремонт АКПП в Кургане. Опыт 11 лет.',
        ];
    }
    return [
        'brand'=>get_bloginfo('name'),'badge'=>'CRM‑платформа под ключ',
        'hero_title'=>'Превращайте заявки в сделки','hero_accent'=>'автоматически',
        'hero_sub'=>'Единая система для продаж, клиентов, магазина и финансов. Разворачиваем на вашем домене за один день.',
        'phone_display'=>'','phone_href'=>'','telegram'=>'','email'=>'',
        'cta_nav'=>'Запросить демо','cta1'=>'Попробовать бесплатно →','cta2'=>'Смотреть возможности','lk_label'=>'👤 Кабинет',
        'nav1'=>'Возможности','nav2'=>'Запуск','nav3'=>'Вопросы',
        'services_title'=>'Возможности',
        'b1_title'=>'Все заявки в одном месте','b1_text'=>'Звонок, форма, мессенджер — ничего не теряется.',
        'b2_title'=>'Карточка клиента','b2_text'=>'История обращений и покупок под рукой.',
        'b3_title'=>'Понятная воронка','b3_text'=>'Видно, на каком этапе каждый клиент.',
        'shop_title'=>'Магазин','shop_sub'=>'Витрина товаров с корзиной и заказами.',
        'steps_title'=>'Запуск за 3 шага',
        's1'=>'Выбираете шаблон и заполняете данные компании.','s2'=>'Разворачиваем CRM на вашем домене за один день.','s3'=>'Команда работает, аналитика делает остальное.',
        'warranty_title'=>(get_option('akpp_warranty_title','')!==''?get_option('akpp_warranty_title'):'Договор и безопасность'),'warranty_text'=>(get_option('akpp_warranty_text','')!==''?get_option('akpp_warranty_text'):'Работаем по договору, данные принадлежат только вам.'),
        'faq1_q'=>'Как быстро запустимся?','faq1_a'=>'Развёртывание за один день, обучение команды — час.',
        'faq2_q'=>'Мои данные в безопасности?','faq2_a'=>'База на вашем домене, лицензия офлайн‑подписана.',
        'faq3_q'=>'Есть ли поддержка?','faq3_a'=>'Поддержка 24/7 и обновления без вашего участия.',
        'address'=>'','hours'=>'',
        'footer'=>'© '.get_bloginfo('name').' — CRM для роста бизнеса.',
    ];
}

function akpp_tpl_data() {
    $saved = get_option('akpp_tpl_data', []);
    if (!is_array($saved)) $saved = [];
    $saved = array_filter($saved, function ($v) { return is_string($v) ? trim($v) !== '' : true; });
    return array_merge(akpp_tpl_defaults(), $saved);
}

add_action('admin_post_akpp_tpl_save_data', function () {
    if (!current_user_can('manage_options')) wp_die('Нет прав');
    check_admin_referer('akpp_tpl_save_data');
    $new = [];
    foreach (array_keys(akpp_tpl_fields()) as $k) {
        $v = isset($_POST[$k]) ? sanitize_text_field(wp_unslash($_POST[$k])) : ''; if ($v !== '') $new[$k] = $v;
    }
    update_option('akpp_tpl_data', $new);
    wp_safe_redirect(admin_url('admin.php?page=akpp-site-templates&saved=1')); exit;
});

// === Скин подстраниц (ЛК, Магазин и др.) в стиле выбранного шаблона ===
function akpp_tpl_skin_class() {
    $cur = function_exists('akpp_current_site_template') ? akpp_current_site_template() : '';
    $map = ['impulse'=>'tpl-dark','minimal'=>'tpl-light','vitrina'=>'tpl-dark','conversion'=>'tpl-dark','corporate'=>'tpl-dark','light'=>'tpl-light tpl-light-center','clean'=>'tpl-light tpl-clean'];
    return isset($map[$cur]) ? $map[$cur] : 'tpl-dark';
}
function akpp_tpl_skin_active() { return !is_admin() && !is_front_page(); }
add_filter('body_class', function ($classes) {
    if (function_exists('akpp_tpl_skin_active') && akpp_tpl_skin_active()) {
        $classes[] = 'tpl-page';
        foreach (explode(' ', akpp_tpl_skin_class()) as $c) if ($c) $classes[] = $c;
    }
    return $classes;
});
add_action('wp_enqueue_scripts', function () {
    if (function_exists('akpp_tpl_skin_active') && akpp_tpl_skin_active()) {
        wp_enqueue_style('akpp-tpl-shared', get_template_directory_uri() . '/assets/css/saas-templates.css', [], '20260831a');
    }
});

// === Сохранение текстов оферты/гарантии с экрана «Реквизиты оферты» (мастер и tenant'ы) ===
add_action('admin_init', function () {
    if (!isset($_POST['action']) || $_POST['action'] !== 'akpp_save_requisites') return;
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'akpp_save_requisites')) return;
    if (!current_user_can('manage_options')) return;
        if (isset($_POST['akpp_warranty_title'])) update_option('akpp_warranty_title', sanitize_text_field(wp_unslash($_POST['akpp_warranty_title'])));
    if (isset($_POST['akpp_warranty_text']))  update_option('akpp_warranty_text',  wp_kses_post(wp_unslash($_POST['akpp_warranty_text'])));
    if (isset($_POST['akpp_custom_agreement'])) update_option('akpp_custom_agreement', wp_kses_post(wp_unslash($_POST['akpp_custom_agreement'])));
    if (isset($_POST['akpp_2gis_url'])) update_option('akpp_2gis_url', esc_url_raw(wp_unslash($_POST['akpp_2gis_url'])));
}, 5);

// Защита оферты от копирования
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_script('akpp-agreement-protect', get_template_directory_uri() . '/assets/js/agreement-protect.js', [], '20260907a', true);
}, 20);
