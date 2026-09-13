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
        add_action('wp_ajax_akpp_client_agree_lead', [$this, 'ajax_client_agree_lead']);
        add_action('wp_ajax_akpp_client_disagree_lead', [$this, 'ajax_client_disagree_lead']);
        add_action('wp_ajax_akpp_get_lead_agree_status', [$this, 'ajax_get_lead_agree_status']);
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
                    <a href="?section=deals" class="<?php echo $section === 'deals' ? 'active' : ''; ?>">🚗 Мои сделки</a>
                    <a href="?section=cart" class="<?php echo $section === 'cart' ? 'active' : ''; ?>">🛒 Корзина</a>
                    <a href="<?php echo wp_logout_url(home_url()); ?>">🚪 Выйти</a>
                </nav>
            </aside>

            <main class="akpp-account-content">
                <?php
                switch ($section) {
                    case 'dashboard': $this->render_dashboard($current_user); break;
                    case 'profile': $this->render_profile($current_user); break;
                    case 'orders':
                        if (isset($_GET['view']) && intval($_GET['view']) > 0) { $this->render_order_detail($current_user, intval($_GET['view'])); }
                        else { $this->render_orders($current_user); }
                        break;
                    case 'leads': $this->render_leads($current_user); break;
                    case 'deals': $this->render_deals($current_user); break;
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
                <div id="akpp-lead-agree-block" style="padding:12px 16px;background:#2d3748;border-top:1px solid #4a5568;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap">
                    <span id="akpp-lead-agree-status" style="font-size:13px;color:#a0aec0">Согласование условий ремонта</span>
                    <button type="button" id="akpp-lead-agree-btn" class="akpp-btn" style="padding:8px 16px;font-size:13px;white-space:nowrap">✅ Я согласен с условиями</button>
                </div>
                <form id="akpp-lead-chat-form" style="display:flex;gap:12px;padding:16px;background:#2d3748;border-radius:0 0 8px 8px">
                    <input type="text" id="akpp-lead-chat-message" placeholder="Напишите сообщение..." style="flex:1;padding:12px 16px;background:#1a1f2e;border:1px solid #4a5568;border-radius:8px;color:#fff">
                    <button type="submit" class="akpp-btn">📤 Отправить</button>
                </form>
            </div>
        </div>

        <style>
        .edit-link{display:none!important}
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
        /* === LK-REDESIGN-B: каркас, слои, палитра статусов, типографика, адаптив, движение === */
.akpp-account-wrap{--lk-surface:#141a28;--lk-surface-2:#1a2233;--lk-surface-3:#212b40;--lk-line:#2a3550;--lk-line-soft:rgba(255,255,255,.05);--lk-text:#eef2f9;--lk-muted:#8a96ad;--lk-accent:#00ff88;--lk-accent-deep:#00b86a;--lk-amber:#f5b544;--lk-cyan:#41d2e2;--lk-violet:#9b8cff;position:relative;isolation:isolate}
.akpp-account-wrap::before{content:'';position:fixed;inset:0;z-index:-2;pointer-events:none;background:radial-gradient(900px 500px at 12% -5%,rgba(0,255,136,.07),transparent 60%),radial-gradient(800px 600px at 100% 0%,rgba(65,210,226,.05),transparent 55%)}
.akpp-account-wrap::after{content:'';position:fixed;inset:0;z-index:-2;pointer-events:none;opacity:.5;background-image:linear-gradient(rgba(255,255,255,.022) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.022) 1px,transparent 1px);background-size:46px 46px;mask-image:radial-gradient(circle at 50% 30%,#000,transparent 85%)}
.akpp-account-sidebar{background:linear-gradient(180deg,var(--lk-surface-2),var(--lk-surface));border:1px solid var(--lk-line);box-shadow:0 24px 60px -28px rgba(0,0,0,.8),inset 0 1px 0 var(--lk-line-soft)}
.akpp-account-user img{box-shadow:0 0 0 4px rgba(0,255,136,.12),0 8px 24px -8px rgba(0,255,136,.4)}
.akpp-account-info h3{letter-spacing:.2px}
.akpp-account-info p{letter-spacing:.3px}
.akpp-account-nav a{position:relative;border:1px solid transparent;border-left:3px solid transparent;border-radius:10px;overflow:hidden}
.akpp-account-nav a::after{content:'';position:absolute;left:0;top:0;bottom:0;width:0;background:linear-gradient(90deg,rgba(0,255,136,.16),transparent);transition:width .35s ease}
.akpp-account-nav a:hover{background:var(--lk-surface-3);border-left-color:rgba(0,255,136,.5);transform:translateX(3px)}
.akpp-account-nav a:hover::after{width:100%}
.akpp-account-nav a.active{background:linear-gradient(135deg,rgba(0,255,136,.18),rgba(0,255,136,.05));border:1px solid rgba(0,255,136,.35);border-left:3px solid var(--lk-accent);color:var(--lk-text);box-shadow:0 10px 30px -16px rgba(0,255,136,.6),inset 0 1px 0 rgba(255,255,255,.06)}
.akpp-account-content{background:linear-gradient(180deg,var(--lk-surface-2),var(--lk-surface));border:1px solid var(--lk-line);box-shadow:0 30px 80px -40px rgba(0,0,0,.85),inset 0 1px 0 var(--lk-line-soft);position:relative;overflow:hidden}
.akpp-account-content>h2{position:relative;padding-left:18px;font-size:clamp(22px,3vw,30px);letter-spacing:-.3px;display:flex;align-items:center;gap:10px}
.akpp-account-content>h2::before{content:'';position:absolute;left:0;top:.12em;bottom:.12em;width:4px;border-radius:4px;background:linear-gradient(180deg,var(--lk-accent),var(--lk-accent-deep));box-shadow:0 0 14px rgba(0,255,136,.6)}
.akpp-stats-grid{grid-template-columns:repeat(auto-fit,minmax(180px,1fr))}
.akpp-stat-card{position:relative;background:linear-gradient(160deg,var(--lk-surface-3),var(--lk-surface-2));border:1px solid var(--lk-line);border-radius:14px;text-align:left;padding:22px 22px 20px;overflow:hidden;transition:transform .3s ease,box-shadow .3s ease,border-color .3s ease}
.akpp-stat-card::before{content:'';position:absolute;left:0;top:0;bottom:0;width:3px;background:var(--bar,var(--lk-accent))}
.akpp-stat-card:nth-child(2){--bar:var(--lk-cyan)}
.akpp-stat-card:nth-child(3){--bar:var(--lk-amber)}
.akpp-stat-card:hover{transform:translateY(-4px);border-color:rgba(255,255,255,.12);box-shadow:0 18px 40px -22px rgba(0,0,0,.9)}
.akpp-stat-card h3{font-size:clamp(30px,4vw,42px);font-weight:800;letter-spacing:-1px;font-variant-numeric:tabular-nums;line-height:1;margin:0 0 10px}
.akpp-stat-card p{text-transform:uppercase;letter-spacing:1.4px;font-size:11px;font-weight:700;color:var(--lk-muted);margin:0}
.akpp-btn{box-shadow:0 10px 26px -12px rgba(0,255,136,.65)}
.akpp-btn:active{transform:translateY(0) scale(.98)}
.akpp-account-content>*{animation:lk-rise .55s cubic-bezier(.2,.7,.2,1) both}
.akpp-account-content>*:nth-child(2){animation-delay:.05s}
.akpp-account-content>*:nth-child(3){animation-delay:.1s}
.akpp-account-content>*:nth-child(4){animation-delay:.15s}
.akpp-account-content>*:nth-child(5){animation-delay:.2s}
@keyframes lk-rise{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:none}}
@media (max-width:980px){
  .akpp-account-wrap{grid-template-columns:1fr;gap:18px;padding:14px}
  .akpp-account-sidebar{position:static}
  .akpp-account-nav{flex-direction:row;overflow-x:auto;padding-bottom:6px;-webkit-overflow-scrolling:touch}
  .akpp-account-nav a{white-space:nowrap;flex:0 0 auto}
  .akpp-account-nav a:hover{transform:none}
  .akpp-account-content{padding:22px}
}
@media (prefers-reduced-motion:reduce){.akpp-account-content>*{animation:none}.akpp-account-nav a,.akpp-stat-card{transition:none}}
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
                    url: '<?php echo admin_url("admin-ajax.php"); ?>',
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
                loadAgreeStatus();
            });

            function loadLeadMessages() {
                if (!currentLeadId) return;
                $.ajax({
                    url: '<?php echo admin_url("admin-ajax.php"); ?>',
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
                    url: '<?php echo admin_url("admin-ajax.php"); ?>',
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

            // === Согласование условий клиентом (Этап 6b) ===
            function loadAgreeStatus() {
                if (!currentLeadId) return;
                $.ajax({
                    url: '<?php echo admin_url("admin-ajax.php"); ?>', type: 'POST',
                    data: { action: 'akpp_get_lead_agree_status', lead_id: currentLeadId, nonce: '<?php echo wp_create_nonce('akpp_lead_chat_nonce'); ?>' },
                    success: function(r) {
                        if (r.success) {
                            var agreed = parseInt(r.data.client_agreed);
                            var $btn = $('#akpp-lead-agree-btn');
                            var $status = $('#akpp-lead-agree-status');
                            if (agreed === 1) {
                                $btn.text('✅ Согласие подтверждено').prop('disabled', true).css({opacity:0.6, cursor:'not-allowed', background:'#00ff88', color:'#1a1f2e'});
                                $status.html('✅ <strong style="color:#00ff88">Условия согласованы</strong>' + (r.data.agreed_at ? ' · ' + r.data.agreed_at : ''));
                            } else {
                                $btn.text('✅ Я согласен с условиями').css({background:'',color:''});
                                $status.text('Согласование условий ремонта');
                            }
                        }
                    }
                });
            }
            $(document).on('click', '#akpp-lead-agree-btn', function() {
                if (!currentLeadId) return;
                var $btn = $(this).prop('disabled', true);
                // Отзыв согласия клиенту недоступен (согласие окончательное)
                $.ajax({
                    url: '<?php echo admin_url("admin-ajax.php"); ?>', type: 'POST',
                    data: { action: 'akpp_client_agree_lead', lead_id: currentLeadId, nonce: '<?php echo wp_create_nonce('akpp_lead_chat_nonce'); ?>' },
                    success: function(r) {
                        if (r.success) { loadAgreeStatus(); }
                        else { alert(r.data.message || 'Ошибка'); }
                        $btn.prop('disabled', false);
                    },
                    error: function() { alert('Ошибка соединения'); $btn.prop('disabled', false); }
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
        $p = $wpdb->prefix;
        $cid = (int) $user->ID;
        $email = $user->user_email;

        $deals = $wpdb->get_results($wpdb->prepare(
            "SELECT d.*, e.name AS employee_name, v.make, v.model, v.year, v.vin
             FROM {$p}akpp_deals d
             LEFT JOIN {$p}akpp_employees e ON d.employee_id = e.id
             LEFT JOIN {$p}akpp_vehicles v ON d.vehicle_id = v.id
             WHERE d.client_id = %d
             ORDER BY FIELD(d.status,'in_work','waiting_parts','diagnostic','new','completed','cancelled'), d.created_at DESC",
            $cid));
        $active = null; $last_done = null;
        foreach ($deals as $d) {
            if (!$active && !in_array($d->status, ['completed','cancelled'], true)) $active = $d;
            if (!$last_done && $d->status === 'completed') $last_done = $d;
        }
        $focus = $active ?: $last_done;

        $cars = $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT v.id, v.make, v.model, v.year, v.vin
             FROM {$p}akpp_deals d JOIN {$p}akpp_vehicles v ON d.vehicle_id = v.id
             WHERE d.client_id = %d AND d.vehicle_id > 0 ORDER BY v.id DESC LIMIT 6", $cid));
        $leads = $wpdb->get_results($wpdb->prepare(
            "SELECT id, status, car_brand, created_at FROM {$p}akpp_leads
             WHERE client_id = %d OR client_email = %s ORDER BY created_at DESC LIMIT 4", $cid, $email));

        $m_active = 0; foreach ($deals as $d) if (!in_array($d->status, ['completed','cancelled'], true)) $m_active++;
        $m_leads = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$p}akpp_leads WHERE client_id=%d OR client_email=%s", $cid, $email));
        $m_cars = count($cars);

        $steps = [
            'new'           => ['t' => 'Принято',     'i' => '📥'],
            'diagnostic'    => ['t' => 'Диагностика', 'i' => '🔍'],
            'in_work'       => ['t' => 'В работе',    'i' => '🔧'],
            'waiting_parts' => ['t' => 'Запчасти',    'i' => '📦'],
            'completed'     => ['t' => 'Готово',      'i' => '✅'],
        ];
        $order = array_keys($steps);
        $cur = $focus ? $focus->status : null;
        $cancelled = $focus && $focus->status === 'cancelled';
        $cur_idx = $cur ? array_search($cur, $order, true) : false;
        ?>
        <h2>👋 <?php echo esc_html($user->display_name); ?></h2>

        <div class="lk-metrics">
            <div class="lk-chip" style="--bar:var(--lk-accent)"><span class="lk-chip-n"><?php echo $m_active; ?></span><span class="lk-chip-l">активных ремонтов</span></div>
            <div class="lk-chip" style="--bar:var(--lk-cyan)"><span class="lk-chip-n"><?php echo $m_leads; ?></span><span class="lk-chip-l">заявок</span></div>
            <div class="lk-chip" style="--bar:var(--lk-amber)"><span class="lk-chip-n"><?php echo $m_cars; ?></span><span class="lk-chip-l">авто в базе</span></div>
        </div>

        <?php if ($focus): ?>
        <section class="lk-repair">
            <div class="lk-repair-top">
                <div class="lk-repair-car">
                    <span class="lk-repair-eyebrow"><?php echo $active ? 'Сейчас в работе' : 'Последний ремонт'; ?></span>
                    <h3 class="lk-repair-title"><?php echo esc_html(trim(($focus->make ?: '') . ' ' . ($focus->model ?: '') . ' ' . ($focus->year ?: '')) ?: 'Автомобиль'); ?></h3>
                    <?php if (!empty($focus->vin)): ?><span class="lk-repair-vin">VIN <?php echo esc_html($focus->vin); ?></span><?php endif; ?>
                </div>
                <div class="lk-repair-sum">
                    <span class="lk-repair-amount"><?php echo number_format((float) $focus->total_amount, 0, '.', ' '); ?></span>
                    <span class="lk-repair-cur">₽</span>
                    <span class="lk-repair-meta"><?php echo esc_html($focus->employee_name ? 'мастер · ' . $focus->employee_name : 'мастер назначен'); ?></span>
                </div>
            </div>

            <?php if ($cancelled): ?>
                <div class="lk-track-cancelled">❌ Ремонт отменён · заказ #<?php echo (int) $focus->id; ?></div>
            <?php else: ?>
            <ol class="lk-track">
                <?php foreach ($order as $i => $key):
                    $state = ($cur_idx !== false && $i < $cur_idx) ? 'done' : (($i === $cur_idx) ? 'now' : 'todo'); ?>
                    <li class="lk-step lk-<?php echo $state; ?>">
                        <span class="lk-node"><?php echo ($state === 'done') ? '✓' : $steps[$key]['i']; ?></span>
                        <span class="lk-step-t"><?php echo esc_html($steps[$key]['t']); ?></span>
                    </li>
                <?php endforeach; ?>
            </ol>
            <?php endif; ?>

            <div class="lk-repair-foot">
                <span class="lk-repair-id">Заказ #<?php echo (int) $focus->id; ?> · <?php echo esc_html($focus->problem_description ? wp_trim_words($focus->problem_description, 8) : 'без описания'); ?></span>
                <a class="lk-link" href="?section=deals">Все мои ремонты →</a>
            </div>
        </section>
        <?php else: ?>
        <section class="lk-empty">
            <div class="lk-empty-glow"></div>
            <p class="lk-empty-k">Пока нет активного ремонта</p>
            <p class="lk-empty-s">Оставьте заявку — мастер свяжется в течение часа и заведёт заказ, а здесь появится живой трекер его статуса от приёмки до выдачи.</p>
            <button type="button" class="akpp-btn open-booking-modal">📝 Записаться на ремонт</button>
        </section>
        <?php endif; ?>

        <div class="lk-grid2">
            <section class="lk-panel">
                <h4 class="lk-panel-h">🚘 Мои автомобили</h4>
                <?php if ($cars): ?>
                    <div class="lk-cars">
                        <?php foreach ($cars as $c): ?>
                            <div class="lk-car">
                                <span class="lk-car-name"><?php echo esc_html(trim($c->make . ' ' . $c->model)); ?></span>
                                <span class="lk-car-year"><?php echo (int) $c->year; ?></span>
                                <?php if (!empty($c->vin)): ?><span class="lk-car-vin"><?php echo esc_html(substr($c->vin, 0, 8)); ?>…</span><?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="lk-panel-empty">Авто появятся здесь автоматически после первой заявки.</p>
                <?php endif; ?>
            </section>
            <section class="lk-panel">
                <h4 class="lk-panel-h">📨 Последние заявки</h4>
                <?php if ($leads): ?>
                    <ul class="lk-leads">
                        <?php foreach ($leads as $l): ?>
                            <li><span class="lk-lead-dot lk-lead-<?php echo esc_attr($l->status); ?>"></span><span class="lk-lead-car"><?php echo esc_html($l->car_brand ?: 'Заявка #' . $l->id); ?></span><span class="lk-lead-date"><?php echo date_i18n('d.m', strtotime($l->created_at)); ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="lk-panel-empty">Заявок пока нет.</p>
                <?php endif; ?>
            </section>
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
                <thead><tr><th>№</th><th>Дата</th><th>Сумма</th><th>Статус</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($orders as $order): $m = AKPP_Shop::status_meta($order->status); ?>
                    <tr>
                        <td>#<?php echo intval($order->id); ?></td>
                        <td><?php echo date_i18n('d.m.Y H:i', strtotime($order->created_at)); ?></td>
                        <td><?php echo number_format($order->total ?? 0, 0, ',', ' '); ?> ₽</td>
                        <td><span class="akpp-status" style="background:<?php echo esc_attr($m['color']); ?>;color:#fff;white-space:nowrap"><?php echo esc_html($m['icon'] . ' ' . $m['label']); ?></span></td>
                        <td><a href="?section=orders&view=<?php echo intval($order->id); ?>" class="akpp-btn" style="padding:7px 15px;font-size:13px;white-space:nowrap">Открыть →</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        <?php
    }

    private function render_order_detail($user, $order_id) {
        global $wpdb;
        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}akpp_shop_orders WHERE id = %d AND client_email = %s",
            $order_id, $user->user_email));
        if (!$order) { echo '<div style="padding:40px 0;text-align:center;color:#a0aec0"><p>Заказ не найден.</p><a href="?section=orders" class="akpp-btn" style="margin-top:14px;display:inline-block">← Все заказы</a></div>'; return; }
        $items = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}akpp_shop_order_items WHERE order_id = %d ORDER BY id ASC", $order_id));
        $m = AKPP_Shop::status_meta($order->status);
        $delivery = (($order->delivery_type ?? 'pickup') === 'delivery') ? 'delivery' : 'pickup';
        $steps = [
            ['label' => 'Оформлен',   'icon' => '🆕'],
            ['label' => 'Оплата',     'icon' => '💳'],
            ['label' => 'Подтверждена','icon' => '✅'],
            ['label' => 'Сборка',     'icon' => '📦'],
            ['label' => $delivery === 'delivery' ? 'Готов к отправке' : 'Готов к выдаче', 'icon' => $delivery === 'delivery' ? '📤' : '🏁'],
            ['label' => $delivery === 'delivery' ? 'Отправлен' : 'Выдан', 'icon' => $delivery === 'delivery' ? '🚚' : '✔️'],
        ];
        $cur = (int) $m['step'];
        $aborted = ($cur < 0);
        $pay = ['cash'=>'Наличные','card'=>'Карта','transfer'=>'Перевод','online'=>'Онлайн','sbp'=>'СБП','gateway'=>'Эквайринг','crypto'=>'Криптовалюта'];
        $chat_nonce = wp_create_nonce('akpp_shop_order_chat_nonce');
        ?>
        <style>
        .od-wrap{max-width:960px;margin:0 auto}
        .od-back{display:inline-block;margin-bottom:18px;color:#a0aec0;text-decoration:none;font-weight:600;transition:color .2s}
        .od-back:hover{color:#00ff88}
        .od-head{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:24px}
        .od-head h2{margin:0;font-family:var(--font-display,'Unbounded',sans-serif);font-size:clamp(22px,3.5vw,32px);font-weight:800;letter-spacing:-.5px;color:#fff}
        .od-head p{margin:6px 0 0;color:#a0aec0;font-size:13.5px}
        .od-status{padding:9px 16px;border-radius:11px;font-weight:800;font-size:13.5px;color:#fff;white-space:nowrap;box-shadow:0 8px 22px -12px rgba(0,0,0,.7)}
        .od-abort{padding:16px 20px;border-radius:12px;background:rgba(252,129,129,.12);border:1px solid rgba(252,129,129,.35);color:#fc8181;font-weight:700;margin-bottom:24px}
        .od-track{list-style:none;display:flex;margin:0 0 26px;padding:0;overflow-x:auto}
        .od-step{position:relative;flex:1 1 0;min-width:86px;display:flex;flex-direction:column;align-items:center;text-align:center;padding-top:2px}
        .od-step::before{content:'';position:absolute;top:23px;right:50%;width:100%;height:3px;border-radius:3px;background:#2d3748;z-index:0}
        .od-step:first-child::before{display:none}
        .od-step.od-done::before,.od-step.od-now::before{background:linear-gradient(90deg,#00ff88,#00cc6a)}
        .od-node{position:relative;z-index:1;width:46px;height:46px;border-radius:50%;display:grid;place-items:center;font-size:19px;border:2px solid #2d3748;background:#1a1f2e;color:#a0aec0;transition:all .3s}
        .od-step.od-done .od-node{background:linear-gradient(135deg,#00ff88,#00cc6a);border-color:transparent;color:#08120c;font-weight:800;box-shadow:0 8px 22px -10px rgba(0,255,136,.7)}
        .od-step.od-now .od-node{border-color:#00ff88;color:#00ff88;background:rgba(0,255,136,.08);animation:od-pulse 2s infinite}
        .od-step-t{margin-top:9px;font-size:11.5px;font-weight:700;color:#a0aec0;line-height:1.25}
        .od-step.od-done .od-step-t{color:#e2e8f0}
        .od-step.od-now .od-step-t{color:#00ff88}
        @keyframes od-pulse{0%{box-shadow:0 0 0 0 rgba(0,255,136,.45)}70%{box-shadow:0 0 0 11px rgba(0,255,136,0)}100%{box-shadow:0 0 0 0 rgba(0,255,136,0)}}
        .od-grid{display:grid;grid-template-columns:1.3fr 1fr;gap:18px;margin-bottom:18px}
        .od-panel{background:#1a1f2e;border:1px solid #2d3748;border-radius:16px;padding:22px}
        .od-h{position:relative;padding-left:13px;margin:0 0 16px;font-size:14.5px;font-weight:800;letter-spacing:.3px;color:#fff}
        .od-h::before{content:'';position:absolute;left:0;top:.1em;bottom:.1em;width:3px;border-radius:3px;background:linear-gradient(180deg,#00ff88,#00cc6a)}
        .od-item{display:flex;justify-content:space-between;gap:14px;padding:11px 0;border-bottom:1px solid #2d3748}
        .od-item:last-of-type{border-bottom:none}
        .od-item-nm{font-weight:700;color:#e2e8f0;font-size:14px}
        .od-item-sku{color:#a0aec0;font-size:11.5px;letter-spacing:.5px;margin-top:3px;font-variant-numeric:tabular-nums}
        .od-item-sum{font-weight:800;color:#fff;font-variant-numeric:tabular-nums;white-space:nowrap}
        .od-total{display:flex;justify-content:space-between;align-items:baseline;margin-top:16px;padding-top:14px;border-top:1px solid #2d3748}
        .od-total span:first-child{color:#a0aec0;font-weight:700}
        .od-total-val{font-family:var(--font-display,'Unbounded',sans-serif);font-size:clamp(22px,3.5vw,30px);font-weight:800;letter-spacing:-.5px;color:#00ff88;font-variant-numeric:tabular-nums}
        .od-info{display:flex;justify-content:space-between;gap:12px;padding:9px 0;border-bottom:1px solid #2d3748;font-size:13.5px}
        .od-info:last-child{border-bottom:none}
        .od-info span{color:#a0aec0}
        .od-info b{color:#e2e8f0;font-weight:700;text-align:right}
        .od-note{margin-top:12px;padding:12px 14px;border-radius:10px;background:#0f1419;border:1px solid #2d3748}
        .od-note span{display:block;color:#a0aec0;font-size:11.5px;letter-spacing:.4px;margin-bottom:6px}
        .od-note p{margin:0;color:#e2e8f0;font-size:13.5px;line-height:1.5}
        .od-chat-msgs{max-height:340px;overflow-y:auto;padding:16px;background:#0f1419;border:1px solid #2d3748;border-radius:12px;margin-bottom:14px}
        .od-chat-empty{color:#a0aec0;text-align:center;margin:20px 0;font-size:13.5px}
        .od-msg{margin-bottom:12px;display:flex;flex-direction:column;animation:od-fade .3s ease both}
        .od-msg.client{align-items:flex-end}
        .od-msg.manager{align-items:flex-start}
        .od-msg-bubble{max-width:74%;padding:10px 14px;border-radius:14px;font-size:14px;line-height:1.5;word-break:break-word}
        .od-msg.client .od-msg-bubble{background:#00ff88;color:#08120c;border-bottom-right-radius:4px}
        .od-msg.manager .od-msg-bubble{background:#2d3748;color:#fff;border-bottom-left-radius:4px}
        .od-msg-time{font-size:10.5px;color:#a0aec0;margin-top:4px;font-variant-numeric:tabular-nums}
        .od-chat-form{display:flex;gap:10px}
        .od-chat-form input{flex:1;padding:12px 16px;background:#0f1419;border:1px solid #2d3748;border-radius:11px;color:#fff;font-size:14px;transition:border-color .2s}
        .od-chat-form input:focus{outline:none;border-color:#00ff88}
        .od-chat-form button{border:none;cursor:pointer;padding:12px 22px;border-radius:11px;font-weight:800;background:linear-gradient(135deg,#00ff88,#00cc6a);color:#08120c;transition:transform .2s,box-shadow .25s}
        .od-chat-form button:hover{transform:translateY(-2px);box-shadow:0 12px 26px -12px rgba(0,255,136,.7)}
        @keyframes od-fade{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:none}}
        @media (max-width:760px){.od-grid{grid-template-columns:1fr}}
        @media (prefers-reduced-motion:reduce){.od-node,.od-msg{animation:none}}
        </style>

        <div class="od-wrap">
            <a href="?section=orders" class="od-back">← Все заказы</a>
            <div class="od-head">
                <div>
                    <h2>Заказ #<?php echo intval($order->id); ?></h2>
                    <p>от <?php echo date_i18n('d.m.Y H:i', strtotime($order->created_at)); ?> · <?php echo esc_html($order->order_number ?? ''); ?></p>
                </div>
                <span class="od-status" style="background:<?php echo esc_attr($m['color']); ?>"><?php echo esc_html($m['icon'] . ' ' . $m['label']); ?></span>
            </div>

            <?php if ($aborted): ?>
                <div class="od-abort"><?php echo esc_html($m['icon'] . ' Заказ ' . mb_strtolower($m['label'])); ?></div>
            <?php else: ?>
                <ol class="od-track">
                    <?php foreach ($steps as $i => $st): $state = ($i < $cur) ? 'done' : ($i === $cur ? 'now' : 'todo'); ?>
                        <li class="od-step od-<?php echo $state; ?>">
                            <span class="od-node"><?php echo $state === 'done' ? '✓' : $st['icon']; ?></span>
                            <span class="od-step-t"><?php echo esc_html($st['label']); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>

            <div class="od-grid">
                <div class="od-panel">
                    <h3 class="od-h">Позиции заказа</h3>
                    <?php foreach ($items as $it): ?>
                        <div class="od-item">
                            <div>
                                <div class="od-item-nm"><?php echo esc_html($it->product_name); ?></div>
                                <div class="od-item-sku">Арт. <?php echo esc_html($it->product_sku); ?> · ×<?php echo (int) $it->quantity; ?> · <?php echo number_format($it->price, 0, ',', ' '); ?> ₽/шт</div>
                            </div>
                            <div class="od-item-sum"><?php echo number_format($it->total, 0, ',', ' '); ?> ₽</div>
                        </div>
                    <?php endforeach; ?>
                    <div class="od-total"><span>Итого</span><span class="od-total-val"><?php echo number_format($order->total, 0, ',', ' '); ?> ₽</span></div>
                </div>
                <div class="od-panel">
                    <h3 class="od-h">Информация</h3>
                    <div class="od-info"><span>Способ оплаты</span><b><?php echo esc_html($pay[$order->payment_method] ?? $order->payment_method); ?></b></div>
                    <div class="od-info"><span>Статус оплаты</span><b><?php echo ($order->payment_status === 'paid') ? '✅ Оплачено' : '⏳ Ожидает оплаты'; ?></b></div>
                    <div class="od-info"><span>Доставка</span><b><?php echo $delivery === 'delivery' ? '🚚 Транспортной компанией' : '📍 Самовывоз'; ?></b></div>
                    <?php if (!empty($order->client_address)): ?><div class="od-info"><span>Адрес / пункт выдачи</span><b><?php echo esc_html($order->client_address); ?></b></div><?php endif; ?>
                    <?php if (!empty($order->notes)): ?><div class="od-note"><span>Комментарий</span><p><?php echo esc_html($order->notes); ?></p></div><?php endif; ?>
                </div>
            </div>

            <div class="od-panel">
                <h3 class="od-h">💬 Переписка по заказу</h3>
                <div class="od-chat-msgs" id="od-chat-msgs"><p class="od-chat-empty">Загрузка…</p></div>
                <form class="od-chat-form" id="od-chat-form">
                    <input type="text" id="od-chat-input" placeholder="Напишите сообщение менеджеру…" autocomplete="off">
                    <button type="submit">Отправить</button>
                </form>
            <form id="od-pay-form" style="margin-top:14px;padding:16px;background:#0f1419;border:1px solid #2d3748;border-radius:12px">
                <div style="font-weight:800;color:#fff;margin-bottom:12px;font-size:13.5px">💳 Подтвердить оплату</div>
                <input type="file" id="od-pay-file" accept="image/*" style="width:100%;padding:10px;background:#1a1f2e;border:1px solid #2d3748;border-radius:10px;color:#fff;margin-bottom:10px">
                <input type="text" id="od-pay-hash" placeholder="Хеш транзакции (для крипты, необязательно)" style="width:100%;padding:11px 14px;background:#1a1f2e;border:1px solid #2d3748;border-radius:10px;color:#fff;margin-bottom:10px;box-sizing:border-box">
                <button type="submit" style="width:100%;border:none;cursor:pointer;padding:12px;border-radius:10px;font-weight:800;background:linear-gradient(135deg,#00ff88,#00cc6a);color:#08120c">📎 Отправить подтверждение</button>
            </form>
            </div>
        </div>

        <script>
        (function(){
            var orderId = <?php echo intval($order->id); ?>;
            var nonce = '<?php echo esc_js($chat_nonce); ?>';
            var AJAX = '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';
            var box = document.getElementById('od-chat-msgs');
            var form = document.getElementById('od-chat-form');
            var input = document.getElementById('od-chat-input');
            var payForm = document.getElementById('od-pay-form');
            var last = -1;
            function esc(t){ var d=document.createElement('div'); d.textContent=t; return d.innerHTML; }
            function render(list){
                if(!list.length){ box.innerHTML='<p class="od-chat-empty">Сообщений пока нет — напишите первым.</p>'; return; }
                box.innerHTML = list.map(function(m){
                    var t=(m.created_at||'').replace('T',' ').substring(0,16);
                    var h='<div class="od-msg od-'+m.sender_type+'"><div class="od-msg-bubble">';
                    if(m.msg_type==='payment_proof') h+='<div style="font-weight:800;margin-bottom:6px">💳 Подтверждение оплаты</div>';
                    if(m.message) h+=esc(m.message);
                    if(m.attachment) h+='<a href="'+esc(m.attachment)+'" target="_blank" style="display:block;margin-top:8px"><img src="'+esc(m.attachment)+'" style="max-width:100%;max-height:230px;border-radius:10px;border:1px solid rgba(255,255,255,.15)"></a>';
                    if(m.tx_hash) h+='<div style="margin-top:8px;font-size:11.5px;word-break:break-all;opacity:.85">🔗 '+esc(m.tx_hash)+'</div>';
                    h+='</div><div class="od-msg-time">'+esc(t)+'</div></div>';
                    return h;
                }).join('');
                box.scrollTop=box.scrollHeight;
            }
            function load(){
                var fd=new FormData(); fd.append('action','akpp_shop_get_order_messages'); fd.append('order_id',orderId); fd.append('nonce',nonce);
                fetch(AJAX,{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json();}).then(function(res){ if(res&&res.success&&res.data.messages.length!==last){ last=res.data.messages.length; render(res.data.messages);} }).catch(function(){});
            }
            form.addEventListener('submit',function(e){ e.preventDefault(); var msg=input.value.trim(); if(!msg)return; var fd=new FormData(); fd.append('action','akpp_shop_send_order_message'); fd.append('order_id',orderId); fd.append('message',msg); fd.append('nonce',nonce); input.value=''; fetch(AJAX,{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json();}).then(function(res){ if(res&&res.success)load(); else input.value=msg; }).catch(function(){input.value=msg;}); });
            if(payForm) payForm.addEventListener('submit',function(e){
                e.preventDefault();
                var file=document.getElementById('od-pay-file').files[0];
                var hash=document.getElementById('od-pay-hash').value.trim();
                if(!file && !hash){ alert('Прикрепите скрин оплаты или укажите хеш транзакции'); return; }
                var fd=new FormData(); fd.append('action','akpp_shop_send_order_message'); fd.append('order_id',orderId); fd.append('nonce',nonce); fd.append('tx_hash',hash); fd.append('message','Подтверждение оплаты');
                if(file) fd.append('attachment', file);
                var btn=payForm.querySelector('button'); btn.disabled=true; btn.textContent='⏳ Отправка…';
                fetch(AJAX,{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json();}).then(function(res){ btn.disabled=false; btn.textContent='📎 Отправить подтверждение'; if(res&&res.success){ payForm.reset(); load(); } else alert(res&&res.message?res.message:'Ошибка'); }).catch(function(){ btn.disabled=false; btn.textContent='📎 Отправить подтверждение'; });
            });
            load(); setInterval(load,8000);
        })();
        </script>
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

    private function render_deals($user) {
        global $wpdb;
        $deals = $wpdb->get_results($wpdb->prepare(
            "SELECT d.*, e.name AS employee_name
             FROM {$wpdb->prefix}akpp_deals d
             LEFT JOIN {$wpdb->prefix}akpp_employees e ON d.employee_id = e.id
             WHERE d.client_id = %d
             ORDER BY d.created_at DESC LIMIT 50",
            $user->ID
        ));
        $statuses = [
            'new' => ['label' => '🆕 Новая', 'color' => '#4299e1'],
            'diagnostic' => ['label' => '🔍 Диагностика', 'color' => '#ed8936'],
            'in_work' => ['label' => '🔧 В работе', 'color' => '#f6ad55'],
            'waiting_parts' => ['label' => '📦 Ожидание запчастей', 'color' => '#fc8181'],
            'completed' => ['label' => '✅ Выполнено', 'color' => '#00ff88'],
            'cancelled' => ['label' => '❌ Отменено', 'color' => '#a0aec0'],
        ];
        ?>
        <h2>🚗 Мои сделки</h2>
        <?php if (empty($deals)): ?>
            <div style="text-align:center;padding:60px 20px;color:#a0aec0"><p style="font-size:48px;margin:0 0 16px 0">🚗</p><p>У вас пока нет сделок по ремонту</p></div>
        <?php else: ?>
            <table class="akpp-orders-table">
                <thead><tr><th>№</th><th>Дата</th><th>Проблема</th><th>Мастер</th><th>Статус</th></tr></thead>
                <tbody>
                <?php foreach ($deals as $deal):
                    $st = $statuses[$deal->status] ?? ['label' => ucfirst($deal->status), 'color' => '#a0aec0'];
                ?>
                    <tr>
                        <td>#<?php echo intval($deal->id); ?></td>
                        <td><?php echo date_i18n('d.m.Y H:i', strtotime($deal->created_at)); ?></td>
                        <td><?php echo esc_html(wp_trim_words($deal->problem_description ?: '—', 10)); ?></td>
                        <td><?php echo esc_html($deal->employee_name ?: '—'); ?></td>
                        <td><span class="akpp-status" style="background:<?php echo esc_attr($st['color']); ?>;color:#fff"><?php echo esc_html($st['label']); ?></span></td>
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

    // === Согласование условий клиентом (Этап 6b) ===

    public function ajax_get_lead_agree_status() {
        if (!is_user_logged_in()) wp_send_json_error(['message' => 'Необходимо войти']);
        check_ajax_referer('akpp_lead_chat_nonce', 'nonce');
        global $wpdb;
        $lead_id = intval($_POST['lead_id'] ?? 0);
        $user_id = get_current_user_id();
        $lead = $wpdb->get_row($wpdb->prepare(
            "SELECT id, client_agreed, agreed_at FROM {$wpdb->prefix}akpp_leads WHERE id = %d AND (client_id = %d OR client_email = %s)",
            $lead_id, $user_id, $wpdb->get_var($wpdb->prepare("SELECT user_email FROM {$wpdb->users} WHERE ID = %d", $user_id))
        ), ARRAY_A);
        if (!$lead) wp_send_json_error(['message' => 'Лид не найден']);
        wp_send_json_success([
            'client_agreed' => intval($lead['client_agreed'] ?? 0),
            'agreed_at' => !empty($lead['agreed_at']) ? date_i18n('d.m.Y H:i', strtotime($lead['agreed_at'])) : ''
        ]);
    }

    public function ajax_client_agree_lead() {
        if (!is_user_logged_in()) wp_send_json_error(['message' => 'Необходимо войти']);
        check_ajax_referer('akpp_lead_chat_nonce', 'nonce');
        global $wpdb;
        $lead_id = intval($_POST['lead_id'] ?? 0);
        $user_id = get_current_user_id();
        $lead = $wpdb->get_row($wpdb->prepare(
            "SELECT id, deal_id, client_name, client_phone, client_email FROM {$wpdb->prefix}akpp_leads WHERE id = %d AND (client_id = %d OR client_email = %s)",
            $lead_id, $user_id, $wpdb->get_var($wpdb->prepare("SELECT user_email FROM {$wpdb->users} WHERE ID = %d", $user_id))
        ), ARRAY_A);
        if (!$lead) wp_send_json_error(['message' => 'Лид не найден']);
        if (!empty($lead['deal_id']) && $lead['deal_id'] > 0) wp_send_json_error(['message' => 'Заявка уже конвертирована в сделку']);
        $wpdb->update($wpdb->prefix . 'akpp_leads', [
            'client_agreed' => 1, 'agreed_at' => current_time('mysql'),
            'status' => 'agreed', 'updated_at' => current_time('mysql')
        ], ['id' => $lead_id]);
        // Автоподпись оферты при согласии в чате (Этап 📜)
        if (function_exists('akpp_record_agreement')) {
            akpp_record_agreement($lead['client_name'] ?? '', $lead['client_phone'] ?? '', $lead['client_email'] ?? '', 'lead_agree', 0);
        }
        wp_send_json_success(['message' => '✅ Вы согласились с условиями. Менеджер создаст сделку.']);
    }

    public function ajax_client_disagree_lead() {
        if (!is_user_logged_in()) wp_send_json_error(['message' => 'Необходимо войти']);
        check_ajax_referer('akpp_lead_chat_nonce', 'nonce');
        global $wpdb;
        $lead_id = intval($_POST['lead_id'] ?? 0);
        $user_id = get_current_user_id();
        $lead = $wpdb->get_row($wpdb->prepare(
            "SELECT id, deal_id FROM {$wpdb->prefix}akpp_leads WHERE id = %d AND (client_id = %d OR client_email = %s)",
            $lead_id, $user_id, $wpdb->get_var($wpdb->prepare("SELECT user_email FROM {$wpdb->users} WHERE ID = %d", $user_id))
        ), ARRAY_A);
        if (!$lead) wp_send_json_error(['message' => 'Лид не найден']);
        if (!empty($lead['deal_id']) && $lead['deal_id'] > 0) wp_send_json_error(['message' => 'Заявка уже конвертирована, отозвать согласие нельзя']);
        $wpdb->update($wpdb->prefix . 'akpp_leads', [
            'client_agreed' => 0, 'agreed_at' => null,
            'status' => 'in_work', 'updated_at' => current_time('mysql')
        ], ['id' => $lead_id]);
        wp_send_json_success(['message' => 'Согласие отозвано']);
    }
}

AKPP_Account::get_instance();
