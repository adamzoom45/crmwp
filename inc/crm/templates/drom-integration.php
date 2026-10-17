<div class="wrap akpp-crm-wrap">
<h1>🚗 Drom/Farpost — выгрузка прайса запчастей</h1>
<?php if (isset($_GET['saved'])): ?><div class="notice notice-success"><p>✅ Настройки сохранены.</p></div><?php endif; ?>
<?php if (isset($_GET['sent']) && $_GET['sent'] === '1'): ?><div class="notice notice-success"><p>✅ Прайс отправлен на Drom (200 OK).</p></div><?php endif; ?>
<?php if (isset($_GET['sent']) && $_GET['sent'] === '0'): ?><div class="notice notice-error"><p>❌ Отправка не прошла — смотри журнал ниже.</p></div><?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin:20px 0;">
  <div style="background:#1a1f2e;border:1px solid #2d3748;border-radius:14px;padding:22px;">
    <h2 style="margin-top:0;">⚙️ Настройки</h2>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
      <?php wp_nonce_field('akpp_drom_save'); ?>
      <input type="hidden" name="action" value="akpp_drom_save">
      <p><label style="display:block;font-weight:700;color:#a0aec0;font-size:12px;text-transform:uppercase;">Packet ID</label>
      <input type="text" name="packet_id" value="<?php echo esc_attr($s['packet_id']); ?>" style="width:100%;" placeholder="55359"></p>
      <p><label style="display:block;font-weight:700;color:#a0aec0;font-size:12px;text-transform:uppercase;">Секретный ключ (sha512 считается сам)</label>
      <input type="password" name="secret" value="" style="width:100%;" placeholder="<?php echo $s['secret'] !== '' ? 'ключ сохранён — оставь пустым' : 'ключ от кабинета Drom'; ?>"></p>
      <p><label><input type="checkbox" name="enabled" value="1" <?php checked($s['enabled']); ?>> Автосинхронизация (раз в сутки)</label></p>
      <button class="button button-primary">💾 Сохранить</button>
    </form>
  </div>
  <div style="background:#1a1f2e;border:1px solid #2d3748;border-radius:14px;padding:22px;">
    <h2 style="margin-top:0;">📤 Отправка</h2>
    <p style="color:#a0aec0;">Товаров в прайсе сейчас: <strong style="color:#00ff88;"><?php echo (int) $dry['count']; ?></strong></p>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('Отправить прайс на Drom?');">
      <?php wp_nonce_field('akpp_drom_send'); ?>
      <input type="hidden" name="action" value="akpp_drom_send">
      <button class="button button-primary">🚀 Отправить прайс сейчас</button>
    </form>
    <details style="margin-top:14px;color:#a0aec0;"><summary>Предпросмотр XML</summary>
    <textarea readonly style="width:100%;height:220px;background:#0a0f1c;color:#718096;border:1px solid #2d3748;border-radius:8px;margin-top:8px;"><?php echo esc_html(mb_substr($dry['xml'], 0, 3000)); ?></textarea></details>
  </div>
</div>

<div style="background:#1a1f2e;border:1px solid #2d3748;border-radius:14px;padding:22px;">
<h2 style="margin-top:0;">📜 Журнал отправок</h2>
<?php if (empty($logs)): ?><p style="color:#718096;">Отправок ещё не было.</p>
<?php else: ?>
<table class="widefat striped"><thead><tr><th>Дата</th><th>Статус</th><th>HTTP</th><th>Товаров</th><th>Ответ</th></tr></thead><tbody>
<?php foreach ($logs as $l): ?>
<tr><td><?php echo esc_html($l->created_at); ?></td>
<td><?php echo $l->status === 'ok' ? '<span style="color:#00ff88">✅ OK</span>' : '<span style="color:#ff6b6b">❌ ' . esc_html($l->status) . '</span>'; ?></td>
<td><?php echo (int) $l->http_code ?: '—'; ?></td><td><?php echo (int) $l->products; ?></td>
<td style="max-width:420px;overflow:hidden;text-overflow:ellipsis;"><?php echo esc_html($l->response); ?></td></tr>
<?php endforeach; ?></tbody></table><?php endif; ?>
</div>
</div>
