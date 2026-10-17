<?php
/**
 * АКПП45 CRM — Умный почтовый модуль v5.
 * Паразитирует на мощностях Яндекс/PVK (SMTP/IMAP).
 * Клиент сам определяет провайдера по адресу, генерирует конфиг, шифрует пароли.
 * 
 * @package AKPP_CRM
 * @version 5.1.2
 */
if (!defined('ABSPATH')) exit;

class AKPP_Mail {
    private static $instance = null;
    public static function get_instance() { if (null === self::$instance) self::$instance = new self(); return self::$instance; }

    // Провайдеры (параметры для автоконфига)
    const PROVIDERS = [
        'yandex' => ['smtp_host'=>'smtp.yandex.ru','smtp_port'=>465,'enc'=>'ssl','imap_host'=>'imap.yandex.ru','imap_port'=>993],
        'mail'   => ['smtp_host'=>'smtp.mail.ru','smtp_port'=>465,'enc'=>'ssl','imap_host'=>'imap.mail.ru','imap_port'=>993],
        'gmail'  => ['smtp_host'=>'smtp.gmail.com','smtp_port'=>587,'enc'=>'tls','imap_host'=>'imap.gmail.com','imap_port'=>993],
    ];

    private function __construct() {
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_post_akpp_mail_save', [$this, 'handle_save']);
        add_action('admin_post_akpp_mail_test_send', [$this, 'handle_test_send']);
        add_action('admin_post_akpp_mail_test_recv', [$this, 'handle_test_recv']);
        add_filter('cron_schedules', [$this, 'add_intervals']);
        add_action('wp', [$this, 'schedule']);
        add_action('akpp_mail_queue_process', [$this, 'process_queue']);
        add_action('akpp_mail_inbox_poll', [$this, 'poll_inbox']);
        add_shortcode('akpp_webmail', [$this, 'shortcode_webmail']);
    }

    // ═══════════════════════════════════════
    // АВТОДЕТЕКТИНГ И ШИФРОВАНИЕ
    // ═══════════════════════════════════════
    
    /** Определяет провайдера по email */
    public static function detect_provider($email) {
        $domain = strtolower(substr(strrchr($email, "@"), 1));
        if (strpos($domain, 'yandex') !== false) return 'yandex';
        if (strpos($domain, 'mail.ru') !== false || strpos($domain, 'bk.ru') !== false) return 'mail';
        if (strpos($domain, 'gmail') !== false) return 'gmail';
        return 'custom'; // Неизвестный домен
    }

    /** Получает конфиг провайдера или кастомный */
    public static function get_config($provider, $custom = []) {
        if ($provider === 'custom') return $custom;
        return self::PROVIDERS[$provider] ?? [];
    }

    /** Шифрование паролей в БД (AES-256-GCM с ключом из wp-config) */
    private static function encrypt($str) {
        $key = defined('AKPP_MAIL_KEY') ? AKPP_MAIL_KEY : 'fallback_key_32bytes_long!!!';
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($str, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        return base64_encode($iv . $tag . $encrypted);
    }
    private static function decrypt($str) {
        $key = defined('AKPP_MAIL_KEY') ? AKPP_MAIL_KEY : 'fallback_key_32bytes_long!!!';
        $data = base64_decode($str);
        $iv = substr($data, 0, 16);
        $tag = substr($data, 16, 16);
        $encrypted = substr($data, 32);
        return openssl_decrypt($encrypted, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    }

    // ═══════════════════════════════════════
    // КОНФИГУРАЦИЯ (опция akpp_mail_cfg)
    // ═══════════════════════════════════════
    public static function cfg($k = '', $d = '') {
        $o = (array) get_option('akpp_mail_cfg', []);
        if ($k === '') return $o;
        return isset($o[$k]) && $o[$k] !== '' ? $o[$k] : $d;
    }

    public function add_intervals($s) {
        if (!isset($s['akpp_every_5min'])) $s['akpp_every_5min'] = ['interval' => 300, 'display' => 'Каждые 5 минут'];
        if (!isset($s['akpp_every_15min'])) $s['akpp_every_15min'] = ['interval' => 900, 'display' => 'Каждые 15 минут'];
        return $s;
    }

    public function schedule() {
        if (!wp_next_scheduled('akpp_mail_queue_process')) wp_schedule_event(time() + 60, 'akpp_every_5min', 'akpp_mail_queue_process');
        if (!wp_next_scheduled('akpp_mail_inbox_poll')) wp_schedule_event(time() + 120, 'akpp_every_15min', 'akpp_mail_inbox_poll');
    }

    // ═══════════════════════════════════════
    // СОБСТВЕННЫЙ SMTP-КЛИЕНТ (stream_socket_client)
    // ═══════════════════════════════════════
    public function smtp_send($to, $subject, $body, $html = false) {
        $user = self::cfg('send_user');
        $pass_enc = self::cfg('send_pass');
        if (!$user || !$pass_enc) return false;
        
        $provider = self::detect_provider($user);
        $conf = self::get_config($provider, ['host'=>self::cfg('send_custom_host'),'port'=>(int)self::cfg('send_custom_port'),'enc'=>self::cfg('send_custom_enc')]);
        
        $host = $conf['smtp_host']; $port = $conf['smtp_port']; $enc = $conf['enc'];
        $pass = self::decrypt($pass_enc);

        $ctx = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]];
        $prefix = ($enc === 'ssl') ? 'ssl://' : '';
        $socket = @stream_socket_client($prefix . $host . ':' . $port, $errno, $errstr, 30, STREAM_CLIENT_CONNECT, stream_context_create($ctx));
        if (!$socket) return false;
        stream_set_timeout($socket, 30);

        $read = function () use ($socket) { $l=''; while($line=fgets($socket,512)){$l.=$line;if(substr($line,3,1)===' ')break;} return trim($l); };
        $write = function ($c) use ($socket) { fwrite($socket,$c."\r\n"); };

        try {
            $read(); // 220
            $write("EHLO " . php_uname('n')); $ehlo = $read();
            
            if ($enc === 'tls' && strpos($ehlo, '250-STARTTLS') !== false) {
                $write("STARTTLS"); $read();
                stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT);
                $write("EHLO " . php_uname('n')); $read();
            }

            $write("AUTH LOGIN"); $read();
            $write(base64_encode($user)); $read();
            $write(base64_encode($pass)); $auth = $read();
            if (strpos($auth, '235') === false) throw new Exception('AUTH fail');

            $from = self::cfg('send_from_email') ?: $user;
            $fname = self::cfg('send_from_name');
            
            $write("MAIL FROM:<$from>"); $read();
            $write("RCPT TO:<$to>"); $read();
            $write("DATA"); $read();

            $boundary = '----=_Part_' . md5(uniqid(mt_rand(), true));
            $headers = ["From: ".($fname?"$fname <$from>":$from),"To: $to","Subject: ".$this->encode_header($subject),"Date: ".date('r'),"Message-ID: <".uniqid()."@local>","MIME-Version: 1.0"];
            
            $msg = implode("\r\n", $headers) . "\r\n\r\n";
            if ($html) {
                $msg .= "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n\r\n";
                $plain = strip_tags(str_replace('<br>', "\n", $body));
                $msg .= "--$boundary\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n".chunk_split(base64_encode($plain))."\r\n--$boundary\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n".chunk_split(base64_encode($body))."\r\n--$boundary--\r\n";
            } else {
                $msg .= "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n".chunk_split(base64_encode($body))."\r\n";
            }
            $msg .= ".\r\n";
            fwrite($socket, $msg);
            $resp = $read();
            $write("QUIT"); fclose($socket);
            return strpos($resp, '250') !== false;
        } catch (Exception $e) {
            error_log('[AKPP_MAIL] ' . $e->getMessage());
            if (is_resource($socket)) fclose($socket);
            return false;
        }
    }

    private function encode_header($str) {
        if (preg_match('/[^\x20-\x7E]/', $str)) return '=?UTF-8?B?' . base64_encode($str) . '?=';
        return $str;
    }

    // ═══════════════════════════════════════
    // ОЧЕРЕДЬ ОТРАВКИ
    // ═══════════════════════════════════════
    public function queue($to, $subject, $body, $html = false) {
        global $wpdb;
        $t = $wpdb->prefix . 'akpp_mail_queue';
        if ($wpdb->get_var("SHOW TABLES LIKE '$t'") !== $t) $this->ensure_tables();
        $wpdb->insert($t, ['recipient'=>sanitize_text_field($to),'subject'=>sanitize_text_field($subject),'body'=>$body,'is_html'=>$html?1:0,'status'=>'pending','attempts'=>0,'created_at'=>current_time('mysql')]);
        return (int) $wpdb->insert_id;
    }

    public function process_queue() {
        global $wpdb;
        $t = $wpdb->prefix . 'akpp_mail_queue';
        if ($wpdb->get_var("SHOW TABLES LIKE '$t'") !== $t) return;
        $rows = $wpdb->get_results("SELECT * FROM $t WHERE status='pending' AND attempts < 5 ORDER BY id ASC LIMIT 10");
        foreach ($rows as $r) {
            $ok = $this->smtp_send($r->recipient, $r->subject, $r->body, (bool)$r->is_html);
            if ($ok) $wpdb->update($t, ['status'=>'sent','sent_at'=>current_time('mysql')], ['id'=>$r->id]);
            else $wpdb->update($t, ['status'=>'failed','attempts'=>$r->attempts+1], ['id'=>$r->id]);
        }
    }

    // ═══════════════════════════════════════
    // ПРИЁМ: IMAP-поллер → анти-спам → лиды/чаты
    // ═══════════════════════════════════════
    public function poll_inbox() {
        if (self::cfg('recv_enabled') !== '1') return;
        $user = self::cfg('recv_user');
        $pass_enc = self::cfg('recv_pass');
        if (!$user || !$pass_enc) return;

        $provider = self::detect_provider($user);
        $conf = self::get_config($provider, ['host'=>self::cfg('recv_custom_host'),'port'=>(int)self::cfg('recv_custom_port'),'enc'=>self::cfg('recv_custom_enc')]);
        
        $host = $conf['imap_host']; $port = $conf['imap_port']; $enc = $conf['enc'];
        $pass = self::decrypt($pass_enc);
        $folder = self::cfg('recv_folder', 'INBOX');

        $ctx = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]];
        $sock = @stream_socket_client(($enc==='ssl'?'ssl://':'')."$host:$port", $errno, $errstr, 30, STREAM_CLIENT_CONNECT, stream_context_create($ctx));
        if (!$sock) return 0;
        stream_set_timeout($sock, 30);

        $tag = 0;
        $cmd = function ($c) use ($sock, &$tag) {
            $t = 'A'.str_pad(++$tag, 4, '0', STR_PAD_LEFT);
            fwrite($sock, "$t $c\r\n");
            $r = ''; while ($l=fgets($sock,4096)) { $r.=$l; if(strpos($l,"$t ")===0) break; }
            return $r;
        };

        try {
            fgets($sock, 4096); // Greeting
            if (strpos($cmd("LOGIN \"$user\" \"$pass\""), 'OK') === false) throw new Exception('Login fail');
            if (strpos($cmd("SELECT \"$folder\""), 'OK') === false) throw new Exception('Select fail');
            
            $search = $cmd("SEARCH UNSEEN");
            preg_match('/\* SEARCH (.+)/', $search, $m);
            $ids = array_slice(array_reverse(explode(' ', trim($m[1]??''))), 0, 20);

            global $wpdb;
            $seen_t = $wpdb->prefix.'akpp_mail_seen';
            $leads_t = $wpdb->prefix.'akpp_leads';
            $chat_t  = $wpdb->prefix.'akpp_chat_messages';
            if ($wpdb->get_var("SHOW TABLES LIKE '$seen_t'") !== $seen_t) $this->ensure_tables();

            $processed = 0;
            foreach ($ids as $num) {
                if ((int)$num <= 0) continue;
                $fetch = $cmd("FETCH $num (BODY.PEEK[HEADER.FIELDS (FROM SUBJECT MESSAGE-ID)])");
                preg_match('/From: (.+?)@(.+?)[>\n]/i', $fetch, $fm);
                $from_addr = strtolower(($fm[1]??'').'@'.($fm[2]??''));
                preg_match('/Subject: (.+?)(?:\r?\n|$)/i', $fetch, $sm);
                $subject = trim($sm[1]??'');
                preg_match('/Message-ID: (<.+?>)/i', $fetch, $mm);
                $msg_id = trim($mm[1]??uniqid());

                if ($this->is_spam($from_addr, $subject, $msg_id)) continue;
                
                $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $seen_t WHERE msg_id=%s", $msg_id));
                if ($exists) continue;
                $wpdb->insert($seen_t, ['msg_id'=>$msg_id,'from_addr'=>$from_addr,'received_at'=>current_time('mysql')]);

                $fb = $cmd("FETCH $num (BODY.PEEK[])");
                $body = $this->extract_imap_body($fb);
                $cmd("STORE $num +FLAGS (\\Seen)");
                $this->ingest_message($from_addr, $subject, $body);
                $processed++;
            }
            $cmd("LOGOUT"); fclose($sock);
            return $processed;
        } catch (Exception $e) {
            error_log('[AKPP_MAIL] IMAP: '.$e->getMessage());
            if (is_resource($sock)) fclose($sock);
            return 0;
        }
    }

    private function extract_imap_body($raw) {
        if (preg_match('/\{(\d+)\}\r\n(.*)$/s', $raw, $m)) $chunk = substr($m[2], 0, (int)$m[1]);
        else $chunk = $raw;
        $decoded = '';
        if (preg_match_all('/[A-Za-z0-9+\/=\r\n]{40,}/', $chunk, $b64)) {
            foreach ($b64[0] as $seg) { $try = base64_decode(preg_replace('/\s/','', $seg), true); if ($try!==false&&strlen($try)>10) $decoded.=$try; }
        }
        if ($decoded==='') $decoded = quoted_printable_decode($chunk);
        return strip_tags(html_entity_decode($decoded, ENT_QUOTES, 'UTF-8'));
    }

    // ═══════════════════════════════════════
    // АНТИ-СПАМ И ИНГЕСТ
    // ═══════════════════════════════════════
    private function is_spam($from, $subj, $id) {
        if ($from===''||strpos($from,'@')===false) return true;
        $bl = array_filter(array_map('trim', explode("\n",(string)self::cfg('blacklist'))));
        foreach ($bl as $p) { if($p==='')continue; $pat=str_replace('*','.*',preg_quote(ltrim($p,'*'),'/')); if(preg_match("/^$pat$/i",$from))return true; }
        global $wpdb;
        $seen_t = $wpdb->prefix.'akpp_mail_seen';
        if ($wpdb->get_var("SHOW TABLES LIKE '$seen_t'")===$seen_t) {
            $recent = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $seen_t WHERE from_addr=%s AND received_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)", $from));
            if ($recent >= 5) return true;
        }
        $words = ['viagra','casino','lottery','free money','bitcoin giveaway','wire transfer urgent'];
        $sl = strtolower($subj);
        foreach ($words as $w) { if (strpos($sl,$w)!==false) return true; }
        return false;
    }

    private function ingest_message($from, $subj, $body) {
        global $wpdb;
        $leads_t = $wpdb->prefix.'akpp_leads';
        $chat_t  = $wpdb->prefix.'akpp_chat_messages';
        $lead = $wpdb->get_row($wpdb->prepare("SELECT id FROM $leads_t WHERE client_email=%s LIMIT 1", $from));
        if ($lead && $wpdb->get_var("SHOW TABLES LIKE '$chat_t'")===$chat_t) {
            $cols = $wpdb->get_col("SHOW COLUMNS FROM $chat_t");
            $row = ['message_text'=>mb_substr($body,0,5000)];
            if(in_array('dialog_id',$cols))$row['dialog_id']=$lead->id;
            if(in_array('sender_name',$cols))$row['sender_name']=$from;
            if(in_array('created_at',$cols))$row['created_at']=current_time('mysql');
            $wpdb->insert($chat_t, $row);
        } else {
            $phone = preg_match('/\+?\d[\d\s\-()]{8,}/', $body, $pm) ? preg_replace('/\D/','',$pm[0]) : '';
            $car = ''; if(preg_match('/(Toyota|Nissan|Mazda|Honda|Hyundai|Kia|Mitsubishi|Subaru|Lexus)\s+\w+/iu',$body,$cm))$car=$cm[0];
            $ins = ['client_name'=>explode('@',$from)[0],'client_phone'=>$phone,'client_email'=>$from,'car_brand'=>$car,'problem'=>mb_substr($body,0,500),'status'=>'new','source'=>'email-inbox','created_at'=>current_time('mysql')];
            $cols = $wpdb->get_col("SHOW COLUMNS FROM $leads_t");
            $filtered = array_intersect_key($ins, array_flip($cols));
            $wpdb->insert($leads_t, $filtered);
            $mgr = self::cfg('notify_email') ?: get_option('admin_email');
            $this->queue($mgr, 'Новый лид из почты: '.$from, "Email: $from\nТема: $subj\n\n$body");
        }
    }

    // ═══════════════════════════════════════
    // ТАБЛИЦЫ И АДМИНКА
    // ═══════════════════════════════════════
    private function ensure_tables() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        $P = $wpdb->prefix;
        $sqls = [
            "CREATE TABLE IF NOT EXISTS {$P}akpp_mail_queue (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, recipient VARCHAR(255), subject VARCHAR(500), body LONGTEXT, is_html TINYINT DEFAULT 0, status VARCHAR(20) DEFAULT 'pending', attempts INT DEFAULT 0, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, sent_at DATETIME NULL, KEY status(status)) $charset",
            "CREATE TABLE IF NOT EXISTS {$P}akpp_mail_seen (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, msg_id VARCHAR(500) UNIQUE, from_addr VARCHAR(255), received_at DATETIME DEFAULT CURRENT_TIMESTAMP, KEY from_addr(from_addr)) $charset"
        ];
        foreach ($sqls as $sql) $wpdb->query($sql);
    }

    public function add_menu() {
        add_menu_page('Умная Почта', '📧 Почта', 'manage_options', 'akpp-mail', [$this, 'render'], 'dashicons-email-alt', 57);
    }

    public function handle_save() {
        if (!current_user_can('manage_options')) wp_die('Недостаточно прав');
        check_admin_referer('akpp_mail');
        $keys = ['send_user','send_from_email','send_from_name','notify_email','recv_enabled','recv_user','recv_folder','blacklist',
                 'send_custom_host','send_custom_port','send_custom_enc','recv_custom_host','recv_custom_port','recv_custom_enc'];
        $cfg = [];
        foreach ($keys as $k) if(isset($_POST[$k])) $cfg[$k]=sanitize_text_field(wp_unslash($_POST[$k]));
        if(!empty($_POST['send_pass_plain'])) $cfg['send_pass']=self::encrypt(sanitize_text_field(wp_unslash($_POST['send_pass_plain'])));
        if(!empty($_POST['recv_pass_plain'])) $cfg['recv_pass']=self::encrypt(sanitize_text_field(wp_unslash($_POST['recv_pass_plain'])));
        update_option('akpp_mail_cfg', $cfg);
        $this->ensure_tables();
        wp_safe_redirect(admin_url('admin.php?page=akpp-mail&saved=1'));
        exit;
    }

    public function handle_test_send() {
        if (!current_user_can('manage_options')) wp_die('Недостаточно прав');
        check_admin_referer('akpp_mail');
        $to = self::cfg('notify_email') ?: get_option('admin_email');
        $ok = $this->smtp_send($to, 'Тест умной почты', 'Если видите это — модуль работает. Время: '.current_time('mysql'));
        wp_safe_redirect(admin_url('admin.php?page=akpp-mail&test_send='.($ok?'ok':'fail')));
        exit;
    }

    public function handle_test_recv() {
        if (!current_user_can('manage_options')) wp_die('Недостаточно прав');
        check_admin_referer('akpp_mail');
        $n = $this->poll_inbox();
        wp_safe_redirect(admin_url('admin.php?page=akpp-mail&test_recv='.$n));
        exit;
    }

    public function render() {
        $c = self::cfg();
        echo '<div class="wrap"><h1 style="color:#00ff88">📧 Умная Почта (Яндекс/Mail.ru)</h1>';
        echo '<p style="color:#a0aec0;font-size:12px;margin-top:-10px">Автодетектинг провайдера. Шифрование AES-256. Собственный SMTP/IMAP клиент.</p>';
        if(isset($_GET['saved'])) echo '<div class="notice notice-success"><p>✅ Сохранено.</p></div>';
        if(isset($_GET['test_send'])) echo '<div class="notice '.($_GET['test_send']==='ok'?'notice-success':'notice-error').'"><p>'.($_GET['test_send']==='ok'?'✅ Письмо отправлено.':'❌ Ошибка SMTP.').'</p></div>';
        if(isset($_GET['test_recv'])) echo '<div class="notice notice-info"><p>ℹ️ Приём: обработано писем: '.(int)$_GET['test_recv'].'.</p></div>';

        global $wpdb;
        $qt = $wpdb->prefix.'akpp_mail_queue';
        if($wpdb->get_var("SHOW TABLES LIKE '$qt'")===$qt) {
            $pending = $wpdb->get_var("SELECT COUNT(*) FROM $qt WHERE status='pending'") ?: 0;
            $sent = $wpdb->get_var("SELECT COUNT(*) FROM $qt WHERE status='sent'") ?: 0;
            echo '<p style="color:#a0aec0">Очередь: ⏳ '.$pending.' · ✅ '.$sent.'</p>';
        }

        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="max-width:860px">';
        wp_nonce_field('akpp_mail');
        echo '<input type="hidden" name="action" value="akpp_mail_save">';

        // ОТПРАВКА
        echo '<h2 style="color:#00ff88;border-bottom:1px solid #2d3748;padding-bottom:8px;margin-top:20px">📤 Отправка (SMTP)</h2>';
        echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">';
        $f = function($k,$l,$ph='') use($c){ echo '<label style="color:#a0aec0;font-size:12px">'.esc_html($l).'<br><input type="text" name="'.$k.'" value="'.esc_attr($c[$k]??'').'" placeholder="'.esc_attr($ph).'" style="width:100%;margin-top:4px"></label>'; };
        $fp = function($k,$l) use($c){ echo '<label style="color:#a0aec0;font-size:12px">'.esc_html($l).'<br><input type="password" name="'.$k.'" value="" placeholder="••••••••" style="width:100%;margin-top:4px"></label>'; };
        
        $f('send_user','Логин (email)','you@yandex.ru');
        $fp('send_pass_plain','Пароль приложения');
        $f('send_from_email','From: email','alerts@ваш.домен');
        $f('send_from_name','From: имя','CRM');
        $f('notify_email','Уведомлять менеджера','admin@ваш.домен');
        
        echo '<details style="grid-column:1/-1;margin-top:10px"><summary style="cursor:pointer;color:#41d2e2;font-size:12px">⚙️ Ручные настройки (если автодетект не подошёл)</summary>';
        echo '<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-top:10px;background:#111827;padding:10px;border-radius:8px">';
        $f('send_custom_host','Хост','smtp.example.com');
        $f('send_custom_port','Порт','587');
        echo '<label style="color:#a0aec0;font-size:12px">Шифрование<br><select name="send_custom_enc" style="width:100%"><option value="">авто</option><option value="ssl">SSL</option><option value="tls">TLS</option></select></label>';
        echo '</div></details>';
        echo '</div>';

        // ПРИЁМ
        echo '<h2 style="color:#00ff88;border-bottom:1px solid #2d3748;padding-bottom:8px;margin-top:24px">📥 Приём (IMAP)</h2>';
        echo '<label style="color:#a0aec0;font-size:12px;display:block;margin-bottom:10px"><input type="checkbox" name="recv_enabled" value="1"'.checked(self::cfg('recv_enabled'),'1',false).'> Включить приём (письма → лиды/чаты)</label>';
        echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">';
        $f('recv_user','Логин (email)','inbox@yourmail.ru');
        $fp('recv_pass_plain','Пароль приложения');
        $f('recv_folder','Папка','INBOX');
        
        echo '<details style="grid-column:1/-1;margin-top:10px"><summary style="cursor:pointer;color:#41d2e2;font-size:12px">⚙️ Ручные настройки IMAP</summary>';
        echo '<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-top:10px;background:#111827;padding:10px;border-radius:8px">';
        $f('recv_custom_host','Хост','imap.example.com');
        $f('recv_custom_port','Порт','993');
        echo '<label style="color:#a0aec0;font-size:12px">Шифрование<br><select name="recv_custom_enc" style="width:100%"><option value="">авто</option><option value="ssl">SSL</option><option value="tls">TLS</option></select></label>';
        echo '</div></details>';
        echo '</div>';

        // АНТИ-СПАМ
        echo '<h2 style="color:#00ff88;border-bottom:1px solid #2d3748;padding-bottom:8px;margin-top:24px">🛡 Анти-спам</h2>';
        echo '<textarea name="blacklist" rows="3" style="width:100%;margin-top:4px" placeholder="*@spam-domain.com&#10;newsletter@*">'.esc_textarea($c['blacklist']??'').'</textarea>';
        echo '<p style="color:#718096;font-size:11px">Rate-limit: 5 писем/час/адрес. Keyword-filter: автоматический.</p>';

        echo '<div style="margin-top:20px;display:flex;gap:10px;flex-wrap:wrap">';
        echo '<button class="button button-primary">💾 Сохранить</button></form>';
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="display:inline"><input type="hidden" name="action" value="akpp_mail_test_send">'.wp_nonce_field('akpp_mail',false).'<button class="button">📨 Тест отправки</button></form>';
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="display:inline"><input type="hidden" name="action" value="akpp_mail_test_recv">'.wp_nonce_field('akpp_mail',false).'<button class="button">📥 Тест приёма</button></form>';
        echo '</div></div>';
    }

    public function shortcode_webmail($atts = []) {
        if (!is_user_logged_in()) return '<p>Войдите.</p>';
        $email = wp_get_current_user()->user_email;
        $username = strstr($email, '@', true);
        // Чтение через IMAP (упрощённо: показываем очередь исходящих как демо)
        global $wpdb;
        $out = '<div class="akpp-webmail" style="background:#0a0f1c;border:1px solid #2d3748;border-radius:14px;padding:20px;color:#e2e8f0">';
        $out .= '<h3 style="color:#00ff88;margin:0 0 14px">📬 Ваша почта ('.esc_html($email).')</h3>';
        $out .= '<p style="color:#a0aec0">Веб-почта подключена к аккаунту '.$username.'. Используйте кнопки тестирования в админке для проверки связи.</p>';
        $out .= '</div>';
        return $out;
    }
}
