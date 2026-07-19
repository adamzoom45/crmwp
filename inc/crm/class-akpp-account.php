<?php
/**
 * АКПП45 Account - Личный кабинет пользователя
 * @package AKPP_CRM
 * @version 1.3.0
 */
if (!defined('ABSPATH')) exit;

class AKPP_Account {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->ensure_tables();
        
        add_shortcode('akpp_account', [$this, 'shortcode_account']);
        
        add_action('wp_ajax_akpp_account_update_profile', [$this, 'ajax_update_profile']);
        add_action('wp_ajax_akpp_account_change_password', [$this, 'ajax_change_password']);
        add_action('wp_ajax_akpp_save_lead', [$this, 'ajax_save_lead']);
        add_action('wp_ajax_akpp_get_lead_messages', [$this, 'ajax_get_lead_messages']);
        add_action('wp_ajax_akpp_send_lead_message', [$this, 'ajax_send_lead_message']);
    }

    /**
     * Создаём таблицы если их нет
     */
    private function ensure_tables() {
        global $wpdb;
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        $charset = $wpdb->get_charset_collate();
        
        // Таблица сообщений по лидам
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}akpp_lead_messages (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            lead_id INT(11) NOT NULL,
            user_id INT(11) DEFAULT 0,
            sender_type ENUM('client','manager') NOT NULL DEFAULT 'client',
            message TEXT NOT NULL,
            is_read TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_lead (lead_id),
            KEY idx_unread (lead_id, sender_type, is_read)
        ) $charset;";
        
        dbDelta($sql);
    }

    public function shortcode_account($atts = []) {
        if (!is_user_logged_in()) {
            return '<div class="akpp-account-login"><p>Для доступа к личному кабинету необходимо войти.</p><a href="' . wp_login_url(get_permalink()) . '" class="button">Войти</a></div>';
        }

        $current_user = wp_get_current_user();
        $section = isset($_GET['section']) ? sanitize_text_field($_GET['section']) : 'dashboard';

        ob_start();
        ?>
        <div class="akpp-account-wrap">
            <aside class="akpp-account-sidebar">
                <div class="akpp-account-user">
                    <?php echo get_avatar($current_user->ID, 80); ?>
                    <div class="akpp-account-info">
                        <h3><?php echo esc_html($current_user->display_name); ?></h3>
                        <p><?php echo esc_html($current_user->user_email); ?></p>
                    </div>
                </div>
                <nav class="akpp-account-nav">
                    <a href="?section=dashboard" class="<?php echo $section === 'dashboard' ? 'active' : ''; ?>">📊 Главная</a>
                    <a href="?section=profile" class="<?php echo $section === 'profile' ? 'active' : ''; ?>">👤 Профиль</a>
                    <a href="?section=orders" class="<?php echo $section === 'orders' ? 'active' : ''; ?>">📦 Мои заказы</a>
                    <a href="?section=leads" class="<?php echo $section === 'leads' ? 'active' : ''; ?>">📨 Мои заявки</a>
                    <a href="?section=cart" class="<?php echo $section === 'cart' ? 'active' : ''; ?>"> Корзина</a>
                    <a href="<?php echo wp_logout_url(home_url()); ?>">🚪 Выйти</a>
                </nav>
            </aside>

            <main class="akpp-account-content">
                <?php
                switch ($section) {
                    case 'dashboard': $this->render_dashboard($current_user); break;
                    case 'profile': $this->render_profile($current_user); break;
                    case 'orders': $this->render_orders($current_user); break;
                    case 'leads': $this->render_leads($current_user); break;
                    case 'cart': echo do_shortcode('[akpp_shop_cart]'); break;
                    default: $this->render_dashboard($current_user);
                }
                ?>
            </main>
        </div>

        <!-- Модальное окно создания заявки (ВСЕГДА на странице) -->
        <div id="akpp-new-lead-modal" class="akpp-modal">
            <div class="akpp-modal-content">
                <div class="akpp-modal-header">
                    <h3>📝 Новая заявка</h3>
                    <button type="button" class="akpp-modal-close">&times;</button>
                </div>
                <form class="akpp-ajax-form" data-action="akpp_save_lead">
                    <?php wp_nonce_field('akpp45_nonce', 'nonce'); ?>
                    <div class="akpp-form-group">
                        <label>Марка автомобиля</label>
                        <input type="text" name="car_brand" placeholder="Toyota Camry 2020">
                    </div>
                    <div class="akpp-form-group">
                        <label>Опишите проблему</label>
                        <textarea name="problem" rows="5" placeholder="Например: не переключается передача..."></textarea>
                    </div>
                    <div class="akpp-form-group">
                        <label>Телефон для связи *</label>
                        <input type="tel" name="client_phone" value="<?php echo esc_attr(get_user_meta($current_user->ID, 'phone', true)); ?>" required>
                    </div>
                    <div style="display:flex;gap:12px;justify-content:flex-end;margin-top:20px">
                        <button type="button" class="akpp-modal-cancel">Отмена</button>
                        <button type="submit" class="akpp-btn"> Отправить заявку</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Модальное окно переписки по лиду -->
        <div id="akpp-lead-chat-modal" class="akpp-modal">
            <div class="akpp-modal-content" style="max-width:700px;display:flex;flex-direction:column;height:80vh">
                <div class="akpp-modal-header">
                    <div>
                        <h3 style="margin:0">💬 Переписка</h3>
                        <p id="akpp-lead-chat-title" style="margin:4px 0 0 0;font-size:13px;color:#a0aec0"></p>
                    </div>
                    <button type="button" class="akpp-modal-close">&times;</button>
                </div>
                <div id="akpp-lead-chat-messages" style="flex:1;overflow-y:auto;padding:20px;background:#0f1419;border-radius:8px"></div>
                <form id="akpp-lead-chat-form" style="display:flex;gap:12px;padding:16px;background:#2d3748;border-radius:0 0 8px 8px">
                    <input type="text" id="akpp-lead-chat-message" placeholder="Напишите сообщение..." style="flex:1;padding:12px 16px;background:#1a1f2e;border:1px solid #4a5568;border-radius:8px;color:#fff">
                    <button type="submit" class="akpp-btn">📤 Отправить</button>
                </form>
            </div>
        </div>

        <style>
        .akpp-account-wrap{display:grid;grid-template-columns:280px 1fr;gap:30px;max-width:1400px;margin:0 auto;padding:20px}
        .akpp-account-sidebar{background:#1a1f2e;border:1px solid #2d3748;border-radius:12px;padding:24px;height:fit-content;position:sticky;top:20px}
        .akpp-account-user{display:flex;align-items:center;gap:15px;margin-bottom:24px;padding-bottom:24px;border-bottom:1px solid #2d3748}
        .akpp-account-user img{border-radius:50%;border:3px solid #00ff88}
        .akpp-account-info h3{margin:0 0 5px 0;color:#00ff88;font-size:16px}
        .akpp-account-info p{margin:0;color:#a0aec0;font-size:13px}
        .akpp-account-nav{display:flex;flex-direction:column;gap:8px}
        .akpp-account-nav a{display:flex;align-items:center;gap:10px;padding:12px 16px;background:transparent;border:1px solid transparent;border-radius:8px;color:#e2e8f0;text-decoration:none;transition:all 0.3s;font-weight:500}
        .akpp-account-nav a:hover{background:#2d3748}
        .akpp-account-nav a.active{background:linear-gradient(135deg,#00ff88 0%,#00cc6a 100%);color:#1a1f2e;font-weight:600}
        .akpp-account-content{background:#1a1f2e;border:1px solid #2d3748;border-radius:12px;padding:30px;min-height:600px}
        .akpp-account-content h2{color:#00ff88;margin:0 0 24px 0;font-size:24px}
        .akpp-stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:20px;margin-bottom:30px}
        .akpp-stat-card{background:#2d3748;border:1px solid #4a5568;border-radius:12px;padding:20px;text-align:center}
        .akpp-stat-card h3{font-size:32px;color:#00ff88;margin:0 0 8px 0}
        .akpp-stat-card p{color:#a0aec0;margin:0;font-size:14px}
        .akpp-form-group{margin-bottom:20px}
        .akpp-form-group label{display:block;margin-bottom:8px;color:#a0aec0;font-weight:600;font-size:14px}
        .akpp-form-group input,.akpp-form-group textarea{width:100%;padding:12px 16px;background:#2d3748;border:2px solid #4a5568;border-radius:8px;color:#fff;font-size:14px;box-sizing:border-box}
        .akpp-form-group input:focus,.akpp-form-group textarea:focus{outline:none;border-color:#00ff88}
        .akpp-btn{background:linear-gradient(135deg,#00ff88 0%,#00cc6a 100%);color:#1a1f2e;border:none;padding:12px 24px;border-radius:8px;font-weight:600;cursor:pointer;transition:all 0.3s}
        .akpp-btn:hover{transform:translateY(-2px);box-shadow:0 4px 12px rgba(0,255,136,0.3)}
        .akpp-btn:disabled{opacity:0.6;cursor:not-allowed;transform:none}
        .akpp-orders-table{width:100%;border-collapse:collapse}
        .akpp-orders-table th{background:#2d3748;color:#00ff88;padding:12px;text-align:left;font-weight:600}
        .akpp-orders-table td{padding:12px;border-bottom:1px solid #2d3748;color:#e2e8f0}
        .akpp-status{display:inline-block;padding:4px 12px;border-radius:12px;font-size:12px;font-weight:600}
        .akpp-status-new{background:#4299e1;color:#fff}
        .akpp-status-contacted{background:#ed8936;color:#fff}
        .akpp-status-in_work{background:#f6ad55;color:#1a1f2e}
        .akpp-status-completed{background:#00ff88;color:#1a1f2e}
        .akpp-status-converted{background:#48bb78;color:#fff}
        .akpp-badge{background:#fc8181;color:#fff;padding:2px 8px;border-radius:12px;font-size:12px;margin-left:5px}
        .akpp-modal{display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.85);z-index:999999;align-items:center;justify-content:center}
        .akpp-modal.active{display:flex}
        .akpp-modal-content{background:#1a1f2e;border:1px solid #2d3748;border-radius:12px;padding:30px;max-width:600px;width:90%;max-height:90vh;overflow-y:auto}
        .akpp-modal-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px}
        .akpp-modal-header h3{color:#00ff88;margin:0}
        .akpp-modal-close{background:none;border:none;color:#a0aec0;font-size:24px;cursor:pointer}
        .akpp-chat-msg{margin-bottom:12px;display:flex}
        .akpp-chat-msg.client{justify-content:flex-end}
        .akpp-chat-msg.manager{justify-content:flex-start}
        .akpp-chat-msg-bubble{max-width:70%;padding:10px 14px;border-radius:12px}
        .akpp-chat-msg.client .akpp-chat-msg-bubble{background:#00ff88;color:#1a1f2e}
        .akpp-chat-msg.manager .akpp-chat-msg-bubble{background:#2d3748;color:#fff}
        .akpp-chat-msg-time{font-size:11px;opacity:0.6;margin-top:4px}
        </style>

        <script>
        jQuery(document).ready(function($) {
            // Открытие модалок
            $(document).on('click', '.akpp-open-modal', function(e) {
                e.preventDefault();
                var target = $(this).data('target');
                if (target) $(target).addClass('active');
            });
            // Закрытие модалок
            $(document).on('click', '.akpp-modal-close, .akpp-modal-cancel', function() {
                $(this).closest('.akpp-modal').removeClass('active');
            });
            $(document).on('click', '.akpp-modal', function(e) {
                if ($(e.target).hasClass('akpp-modal')) $(this).removeClass('active');
            });

            // AJAX формы (создание заявки, профиль, пароль)
            $(document).on('submit', '.akpp-ajax-form', function(e) {
                e.preventDefault();
                var $form = $(this);
                var action = $form.data('action');
                var $btn = $form.find('button[type="submit"]');
                var originalText = $btn.text();
                
                $btn.prop('disabled', true).text('Отправка...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: $form.serialize() + '&action=' + action,
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            alert(response.data.message);
                            $form.closest('.akpp-modal').removeClass('active');
                            $form[0].reset();
                            location.reload();
                        } else {
                            alert(response.data.message || 'Ошибка');
                            $btn.prop('disabled', false).text(originalText);
                        }
                    },
                    error: function() {
                        alert('Ошибка соединения');
                        $btn.prop('disabled', false).text(originalText);
                    }
                });
            });

            // Переписка по лидам
            var currentLeadId = 0;
            var chatModal = $('#akpp-lead-chat-modal');
            var chatMessages = $('#akpp-lead-chat-messages');
            var chatForm = $('#akpp-lead-chat-form');
            var messageInput = $('#akpp-lead-chat-message');

            $(document).on('click', '.akpp-open-lead-chat', function() {
                currentLeadId = $(this).data('lead-id');
                $('#akpp-lead-chat-title').text($(this).data('lead-car'));
                chatModal.addClass('active');
                loadLeadMessages();
            });

            function loadLeadMessages() {
                if (!currentLeadId) return;
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'akpp_get_lead_messages',
                        lead_id: currentLeadId,
                        nonce: '<?php echo wp_create_nonce('akpp_lead_chat_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success && response.data.messages) {
                            chatMessages.html('');
                            if (response.data.messages.length === 0) {
                                chatMessages.html('<div style="text-align:center;color:#718096;padding:40px"><p style="font-size:48px;margin:0">💬</p><p>История переписки пуста</p></div>');
                            } else {
                                response.data.messages.forEach(function(msg) {
                                    var isClient = msg.sender_type === 'client';
                                    chatMessages.append(
                                        '<div class="akpp-chat-msg ' + (isClient ? 'client' : 'manager') + '">' +
                                        '<div class="akpp-chat-msg-bubble">' +
                                        '<div style="font-size:13px;opacity:0.8">' + (isClient ? 'Вы' : 'Менеджер') + '</div>' +
                                        '<div>' + msg.message + '</div>' +
                                        '<div class="akpp-chat-msg-time">' + msg.created_at + '</div>' +
                                        '</div></div>'
                                    );
                                });
                                chatMessages.scrollTop(chatMessages[0].scrollHeight);
                            }
                        }
                    }
                });
            }

            chatForm.on('submit', function(e) {
                e.preventDefault();
                var message = messageInput.val().trim();
                if (!message || !currentLeadId) return;
                
                var sendBtn = chatForm.find('button[type="submit"]');
                sendBtn.prop('disabled', true).text('Отправка...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'akpp_send_lead_message',
                        lead_id: currentLeadId,
                        message: message,
                        nonce: '<?php echo wp_create_nonce('akpp_lead_chat_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            messageInput.val('');
                            loadLeadMessages();
                        } else {
                            alert(response.data.message || 'Ошибка');
                        }
                        sendBtn.prop('disabled', false).text('📤 Отправить');
                    },
                    error: function() {
                        alert('Ошибка соединения');
                        sendBtn.prop('disabled', false).text('📤 Отправить');
                    }
                });
            });

            setInterval(function() {
                if (currentLeadId && chatModal.hasClass('active')) loadLeadMessages();
            }, 5000);
        });
        </script>
        <?php
        return ob_get_clean();
    }

    private function render_dashboard($user) {
        global $wpdb;
        $orders_count = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}akpp_shop_orders WHERE client_email = %s", $user->user_email));
        $leads_count = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}akpp_leads WHERE client_id = %d OR client_email = %s", $user->ID, $user->user_email));
        ?>
        <h2>📊 Добро пожаловать, <?php echo esc_html($user->display_name); ?>!</h2>
        <div class="akpp-stats-grid">
            <div class="akpp-stat-card"><h3><?php echo $orders_count; ?></h3><p>📦 Заказов</p></div>
            <div class="akpp-stat-card"><h3><?php echo $leads_count; ?></h3><p>📨 Заявок</p></div>
            <div class="akpp-stat-card"><h3>0</h3><p>🛒 В корзине</p></div>
        </div>
        <div style="background:#2d3748;border-radius:12px;padding:24px">
            <h3 style="color:#00ff88;margin:0 0 16px 0">🚀 Быстрые действия</h3>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px">
                <a href="?section=orders" class="akpp-btn" style="text-align:center;text-decoration:none">📦 Мои заказы</a>
                <a href="?section=leads" class="akpp-btn" style="text-align:center;text-decoration:none">📨 Мои заявки</a>
                <a href="<?php echo home_url('/shop/'); ?>" class="akpp-btn" style="text-align:center;text-decoration:none">🛍️ Каталог</a>
            </div>
        </div>
        <?php
    }

    private function render_profile($user) {
        ?>
        <h2>👤 Мой профиль</h2>
        <form class="akpp-ajax-form" data-action="akpp_account_update_profile">
            <?php wp_nonce_field('akpp45_nonce', 'nonce'); ?>
            <div class="akpp-form-group"><label>Имя</label><input type="text" name="display_name" value="<?php echo esc_attr($user->display_name); ?>" required></div>
            <div class="akpp-form-group"><label>Email</label><input type="email" name="user_email" value="<?php echo esc_attr($user->user_email); ?>" required></div>
            <div class="akpp-form-group"><label>Телефон</label><input type="tel" name="phone" value="<?php echo esc_attr(get_user_meta($user->ID, 'phone', true)); ?>"></div>
            <button type="submit" class="akpp-btn">💾 Сохранить</button>
        </form>
        <hr style="margin:40px 0;border-color:#2d3748">
        <h3 style="color:#00ff88"> Изменить пароль</h3>
        <form class="akpp-ajax-form" data-action="akpp_account_change_password">
            <?php wp_nonce_field('akpp45_nonce', 'nonce'); ?>
            <div class="akpp-form-group"><label>Текущий пароль</label><input type="password" name="current_password" required></div>
            <div class="akpp-form-group"><label>Новый пароль</label><input type="password" name="new_password" required minlength="6"></div>
            <div class="akpp-form-group"><label>Повторите пароль</label><input type="password" name="confirm_password" required minlength="6"></div>
            <button type="submit" class="akpp-btn">🔐 Изменить пароль</button>
        </form>
        <?php
    }

    private function render_orders($user) {
        global $wpdb;
        $orders = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}akpp_shop_orders WHERE client_email = %s ORDER BY created_at DESC LIMIT 50", $user->user_email));
        ?>
        <h2>📦 Мои заказы</h2>
        <?php if (empty($orders)): ?>
            <div style="text-align:center;padding:60px 20px;color:#a0aec0"><p style="font-size:48px;margin:0 0 16px 0">📭</p><p>У вас пока нет заказов</p></div>
        <?php else: ?>
            <table class="akpp-orders-table">
                <thead><tr><th>№</th><th>Дата</th><th>Сумма</th><th>Статус</th></tr></thead>
                <tbody>
                <?php foreach ($orders as $order): ?>
                    <tr>
                        <td>#<?php echo intval($order->id); ?></td>
                        <td><?php echo date_i18n('d.m.Y H:i', strtotime($order->created_at)); ?></td>
                        <td><?php echo number_format($order->total ?? $order->total_amount ?? 0, 0, ',', ' '); ?> ₽</td>
                        <td><span class="akpp-status akpp-status-<?php echo esc_attr($order->status); ?>"><?php echo esc_html($order->status); ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        <?php
    }

    private function render_leads($user) {
        global $wpdb;
        $leads = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}akpp_leads WHERE client_id = %d OR client_email = %s ORDER BY created_at DESC LIMIT 50", $user->ID, $user->user_email));
        ?>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px">
            <h2 style="margin:0">📨 Мои заявки</h2>
            <button type="button" class="akpp-btn akpp-open-modal" data-target="#akpp-new-lead-modal">➕ Создать заявку</button>
        </div>
        <?php if (empty($leads)): ?>
            <div style="text-align:center;padding:60px 20px;color:#a0aec0">
                <p style="font-size:48px;margin:0 0 16px 0"></p>
                <p>У вас пока нет заявок</p>
                <button type="button" class="akpp-btn akpp-open-modal" data-target="#akpp-new-lead-modal" style="margin-top:20px">➕ Создать первую заявку</button>
            </div>
        <?php else: ?>
            <table class="akpp-orders-table">
                <thead><tr><th>№</th><th>Дата</th><th>Автомобиль</th><th>Проблема</th><th>Статус</th><th>Переписка</th></tr></thead>
                <tbody>
                <?php foreach ($leads as $lead):
                    $unread = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}akpp_lead_messages WHERE lead_id = %d AND sender_type = 'manager' AND is_read = 0", $lead->id));
                ?>
                    <tr>
                        <td>#<?php echo intval($lead->id); ?></td>
                        <td><?php echo date_i18n('d.m.Y H:i', strtotime($lead->created_at)); ?></td>
                        <td><?php echo esc_html($lead->car_brand ?: '—'); ?></td>
                        <td><?php echo esc_html(wp_trim_words($lead->problem, 10)); ?></td>
                        <td><span class="akpp-status akpp-status-<?php echo esc_attr($lead->status); ?>"><?php echo esc_html($lead->status); ?></span></td>
                        <td>
                            <button type="button" class="akpp-btn akpp-open-lead-chat" data-lead-id="<?php echo intval($lead->id); ?>" data-lead-car="<?php echo esc_attr($lead->car_brand ?: 'Заявка #' . $lead->id); ?>" style="padding:6px 12px;font-size:13px">
                                💬 Написать<?php if ($unread > 0): ?><span class="akpp-badge"><?php echo $unread; ?></span><?php endif; ?>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        <?php
    }

    public function ajax_update_profile() {
        if (!is_user_logged_in()) wp_send_json_error(['message' => 'Необходимо войти']);
        check_ajax_referer('akpp45_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        $result = wp_update_user([
            'ID' => $user_id,
            'display_name' => sanitize_text_field($_POST['display_name']),
            'user_email' => sanitize_email($_POST['user_email'])
        ]);
        
        if (is_wp_error($result)) wp_send_json_error(['message' => $result->get_error_message()]);
        
        update_user_meta($user_id, 'phone', sanitize_text_field($_POST['phone']));
        wp_send_json_success(['message' => '✅ Профиль обновлён']);
    }

    public function ajax_change_password() {
        if (!is_user_logged_in()) wp_send_json_error(['message' => 'Необходимо войти']);
        check_ajax_referer('akpp45_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        $user = get_user_by('ID', $user_id);
        
        if (!wp_check_password($_POST['current_password'], $user->user_pass, $user_id)) {
            wp_send_json_error(['message' => '❌ Неверный текущий пароль']);
        }
        if ($_POST['new_password'] !== $_POST['confirm_password']) {
            wp_send_json_error(['message' => '❌ Пароли не совпадают']);
        }
        if (strlen($_POST['new_password']) < 6) {
            wp_send_json_error(['message' => '❌ Минимум 6 символов']);
        }
        
        wp_set_password($_POST['new_password'], $user_id);
        wp_send_json_success(['message' => '✅ Пароль изменён']);
    }

    public function ajax_save_lead() {
        if (!is_user_logged_in()) wp_send_json_error(['message' => 'Необходимо войти']);
        check_ajax_referer('akpp45_nonce', 'nonce');
        
        global $wpdb;
        $user = wp_get_current_user();
        
        $client_phone = sanitize_text_field($_POST['client_phone'] ?? '');
        $car_brand = sanitize_text_field($_POST['car_brand'] ?? '');
        $problem = sanitize_textarea_field($_POST['problem'] ?? '');
        
        if (empty($client_phone)) wp_send_json_error(['message' => 'Телефон обязателен']);
        
        // Нормализация телефона
        $phone_digits = preg_replace('/[^\d]/', '', $client_phone);
        if (strlen($phone_digits) === 11 && $phone_digits[0] === '8') $phone_digits = '7' . substr($phone_digits, 1);
        if (strlen($phone_digits) === 10) $phone_digits = '7' . $phone_digits;
        $client_phone = '+' . $phone_digits;
        
        // Проверка дублей
        $duplicate = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}akpp_leads 
             WHERE REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(client_phone,' ',''),'-',''),'(',''),')',''),'+','') = %s
             AND status NOT IN ('converted','cancelled','rejected')
             AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
             LIMIT 1",
            $phone_digits
        ));
        if ($duplicate) wp_send_json_error(['message' => '⚠️ Активная заявка с таким телефоном уже есть (ID: ' . $duplicate . ')']);
        
        $wpdb->insert($wpdb->prefix . 'akpp_leads', [
            'client_id' => $user->ID,
            'client_name' => $user->display_name,
            'client_phone' => $client_phone,
            'client_email' => $user->user_email,
            'car_brand' => $car_brand,
            'problem' => $problem,
            'status' => 'new',
            'source' => 'lk',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ]);
        
        wp_send_json_success(['message' => '✅ Заявка #' . $wpdb->insert_id . ' создана']);
    }

    public function ajax_get_lead_messages() {
        if (!is_user_logged_in()) wp_send_json_error(['message' => 'Необходимо войти']);
        check_ajax_referer('akpp_lead_chat_nonce', 'nonce');
        
        global $wpdb;
        $lead_id = intval($_POST['lead_id'] ?? 0);
        $user_id = get_current_user_id();
        $table = $wpdb->prefix . 'akpp_lead_messages';
        
        // Проверка что лид принадлежит пользователю
        $lead = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}akpp_leads WHERE id = %d AND (client_id = %d OR client_email = %s)",
            $lead_id, $user_id, $wpdb->get_var($wpdb->prepare("SELECT user_email FROM {$wpdb->users} WHERE ID = %d", $user_id))
        ));
        if (!$lead) wp_send_json_error(['message' => 'Лид не найден']);
        
        $messages = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE lead_id = %d ORDER BY created_at ASC LIMIT 200", $lead_id));
        
        $formatted = [];
        foreach ($messages as $msg) {
            $formatted[] = [
                'id' => $msg->id,
                'message' => $msg->message,
                'sender_type' => $msg->sender_type,
                'created_at' => date_i18n('H:i', strtotime($msg->created_at))
            ];
        }
        
        $wpdb->update($table, ['is_read' => 1], ['lead_id' => $lead_id, 'sender_type' => 'manager', 'is_read' => 0], ['%d'], ['%d', '%s', '%d']);
        
        wp_send_json_success(['messages' => $formatted]);
    }

    public function ajax_send_lead_message() {
        if (!is_user_logged_in()) wp_send_json_error(['message' => 'Необходимо войти']);
        check_ajax_referer('akpp_lead_chat_nonce', 'nonce');
        
        global $wpdb;
        $lead_id = intval($_POST['lead_id'] ?? 0);
        $user_id = get_current_user_id();
        $message = sanitize_textarea_field($_POST['message'] ?? '');
        
        if (empty($message)) wp_send_json_error(['message' => 'Сообщение пустое']);
        
        $lead = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}akpp_leads WHERE id = %d AND (client_id = %d OR client_email = %s)",
            $lead_id, $user_id, $wpdb->get_var($wpdb->prepare("SELECT user_email FROM {$wpdb->users} WHERE ID = %d", $user_id))
        ));
        if (!$lead) wp_send_json_error(['message' => 'Лид не найден']);
        
        $wpdb->insert($wpdb->prefix . 'akpp_lead_messages', [
            'lead_id' => $lead_id,
            'user_id' => $user_id,
            'sender_type' => 'client',
            'message' => $message,
            'is_read' => 0,
            'created_at' => current_time('mysql')
        ]);
        
        wp_send_json_success(['message' => 'Сообщение отправлено']);
    }
}

AKPP_Account::get_instance();