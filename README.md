# 🚗 АКПП45 CRM — Система управления автосервисом

**Версия:** 4.2 | **Статус:** 🟢 Активная разработка | **Обновлено:** Июнь 2026

**Сайт:** https://akpp45.ru  
**GitHub:** https://github.com/adamzoom45/crmwp

---

## 📌 О ПРОЕКТЕ

АКПП45 CRM — комплексная система управления автосервисом по ремонту АКПП:
- Учёт сделок и воронка продаж
- База автомобилей 4 рынков (Japan, Asia, Europe, USA)
- Каталог АКПП, запчастей и масел
- Складской учёт с авто-списанием
- Интеграция с Авито API (двусторонний чат)
- Telegram-бот управления + VPN/SSH
- Универсальный парсер с AI-анализом
- Мобильное приложение (Android APK)
- Регистрация клиентов + чат с гидом

---

## 🗄️ БАЗА ДАННЫХ (16 таблиц)

### Основные
| Таблица | Описание |
|---------|----------|
| `wp_akpp_vehicles` | База авто (4 рынка, 1990-2026) |
| `wp_akpp_transmissions` | Каталог АКПП |
| `wp_akpp_deals` | Сделки + расчёт оплаты |
| `wp_akpp_employees` | Сотрудники |
| `wp_akpp_leads` | Лиды (сайт, Авито, Telegram) |

### Склад
| Таблица | Описание |
|---------|----------|
| `wp_akpp_parts` | Запчасти (12 категорий) |
| `wp_akpp_oils` | Масла (ATF, CVT, DCT) |
| `wp_akpp_deal_parts` | Запчасти в сделке |

### Интеграции
| Таблица | Описание |
|---------|----------|
| `wp_akpp_avito_tokens` | OAuth токены Авито |
| `wp_akpp_avito_dialogs` | Диалоги Авито |
| `wp_akpp_avito_messages_cache` | Кэш сообщений Авито |
| `wp_akpp_chat_messages` | Сообщения чата CRM |

### Пользователи и парсер
| Таблица | Описание |
|---------|----------|
| `wp_akpp_site_users` | Пользователи сайта |
| `wp_akpp_parser_items` | Результаты парсинга + AI |
| `wp_akpp_vin_cache` | Кэш VIN-декодера |
| `wp_akpp_push_tokens` | Push-токены FCM |

---

## 🏗️ АРХИТЕКТУРА ФАЙЛОВ

```text
wp-content/themes/akpp-kurgan/inc/crm/
│
├── class-akpp-crm.php              # Главный класс (Singleton + меню)
├── class-akpp-install.php          # Создание 16 таблиц БД
├── class-akpp-ajax.php             # Все AJAX обработчики
├── class-akpp-telegram.php         # Telegram бот + VPN/SSH
├── class-akpp-avito.php            # Авито API (OAuth + чат)
├── class-akpp-parser.php           # Универсальный парсер
├── class-akpp-auth.php             # Регистрация + авторизация
├── class-akpp-email.php            # Отправка email
├── class-akpp-push.php             # Push уведомления (FCM)
├── class-akpp-webhook.php          # Webhook Авито
│
── decoders/
│   ├── class-vin-decoder.php       # NHTSA API + кэш
│   ├── class-body-decoder.php      # Toyota/Lexus body number
│   └── class-deal-calculator.php   # Формула оплаты
│
├── ai/
│   └── class-ai-analyzer.php       # AI анализ парсинга
│
├── tables/                         # WP_List_Table
│   ├── class-deals-table.php
│   ├── class-employees-table.php
│   ├── class-vehicles-table.php
│   ├── class-transmissions-table.php
│   ├── class-leads-table.php
│   ├── class-parts-table.php
│   ├── class-oils-table.php
│   ├── class-parser-table.php
│   ├── class-users-table.php
│   ── class-avito-dialogs-table.php
│
├── assets/
│   ├── css/
│   │   ├── admin.css               # Стили админки (akpp45.ru)
│   │   └── frontend.css            # Стили фронтенда
│   └── js/
│       ├── admin.js
│       ├── vin-decoder.js
│       ├── deal-calculator.js
│       ├── chat.js
│       ├── avito-chat.js
│       ├── parser.js
│       ├── auth.js
│       └── push.js
│
└── templates/
    ├── dashboard.php               # Панель + воронка
    ├── deals.php
    ├── deal-form.php               # VIN + запчасти со склада
    ├── employees.php
    ├── vehicles.php                # 4 рынка
    ├── transmissions.php
    ├── parts.php                   # Склад
    ├── oils.php                    # Масла
    ├── parser.php                  # Парсер + AI
    ├── leads.php
    ├── chat.php
    ├── avito-dialogs.php
    ├── avito-settings.php
    ├── telegram.php
    ├── users.php
    └── frontend/
        ├── register.php
        ├── login.php
        ├── profile.php
        └── chat.php
```

---

## 📊 МЕНЮ CRM (13 пунктов)

```text
📊 Панель         → статистика + воронка + склад
 Сделки         → таблица сделок
➕ Новая          → форма с VIN + запчасти
👥 Сотрудники     → CRUD
🚗 Авто           → 4 рынка (Japan/Asia/Europe/USA)
️ АКПП           → каталог + запчасти + масла
📦 Склад          → запчасти (авто-списание)
🛢️ Масла          → каталог масел
 Парсер         → универсальный + AI
 Лиды           → заявки + чат + Авито
👥 Пользователи   → пользователи сайта
💬 Авито чаты     → диалоги Авито
📱 Telegram       → бот + VPN/SSH
```

---

## 🔑 КЛЮЧЕВЫЕ ФУНКЦИИ

### 💰 Расчёт оплаты сотрудников
```text
Оплата = work_cost × (work_hours / standard_hours) × (percent / 100)
```
Пример: 80 000₽ × (8ч / 10ч) × 50% = **32 000₽**

### 🔄 Воронка продаж
```text
lead → new → diagnostic → in_work → completed / rejected
```

### 🛒 Авто-списание запчастей
При сохранении сделки → автоматическое списание со склада → запись в `wp_akpp_deal_parts`.

### 🤖 AI-анализ парсинга
1. Парсер извлекает данные с любого сайта
2. AI определяет: тип проблемы, симптомы, причины, решения, нужные запчасти
3. Модератор одобряет → данные сохраняются

### 💬 Двусторонний чат с Авито
- Webhook → мгновенное получение сообщений
- API → отправка ответов (клиент видит в приложении Авито)
- Cron каждые 5 минут → backup-синхронизация

---

## 🚀 УСТАНОВКА

### Требования
- WordPress 6.0+
- PHP 8.0+
- MySQL 8.0+
- cURL включён

### Шаги
1. Скопировать папку `crm/` в `wp-content/themes/akpp-kurgan/inc/`
2. В `functions.php` темы добавить:
```php
require_once get_template_directory() . '/inc/crm/class-akpp-crm.php';
```
3. Перейти в WP Admin → CRM → Панель (таблицы создадутся автоматически)
4. Настроить Авито API: CRM → Авито чаты → Настройки
5. Настроить Telegram: CRM → Telegram → Bot Token + Chat ID

---

##  ANDROID APK

**Репозиторий:** https://github.com/adamzoom45/akpp45-crm-app

**Функции:**
- WebView → CRM
- Push (Firebase)
- Кэш оффлайн
- Биометрия

**Сборка:**
```bash
cd akpp45-crm-app
./gradlew assembleRelease
```

---

## 📝 БИЗНЕС-ЛОГИКА

### Регистрация клиента
1. Клиент заполняет форму (ФИО, телефон, email, марка авто, проблема)
2. CRM генерирует пароль → отправляет на email
3. Создаётся лид → назначается гид (первый активный сотрудник)
4. Push-уведомление клиенту: "С вами свяжется специалист"

### Создание сделки
1. VIN-декодер заполняет данные авто
2. Выбор запчастей со склада (авто-списание)
3. Калькулятор считает оплату сотрудника
4. Статус движется по воронке

### Парсинг с AI
1. Ввод URL → парсер извлекает контент + картинки
2. AI анализирует текст и изображения
3. Определяет: АКПП, тип проблемы, запчасти
4. Модерация → сохранение в базу

---

## 🔄 ФАЗЫ РАЗРАБОТКИ

### ✅ ФАЗА 1: Фундамент
- [x] 16 таблиц БД
- [x] Главный класс + меню
- [x] CSS (стиль akpp45.ru)
- [x] Панель + воронка

### 🔄 ФАЗА 2: Авито интеграция
- [ ] OAuth 2.0
- [ ] Webhook обработчик
- [ ] Двусторонний чат
- [ ] Cron синхронизация

### ⏳ ФАЗА 3: Регистрация + Чат
- [ ] Форма регистрации
- [ ] Генерация пароля + email
- [ ] Push уведомления
- [ ] Клиентский чат

### ⏳ ФАЗА 4: Сделки + Склад
- [ ] Форма сделки с VIN
- [ ] Авто-списание запчастей
- [ ] Калькулятор оплаты

### ⏳ ФАЗА 5: Парсер + AI
- [ ] Универсальный парсер
- [ ] AI анализ
- [ ] Модерация

### ⏳ ФАЗА 6: Telegram + APK
- [ ] Telegram бот
- [ ] VPN/SSH настройки
- [ ] Android APK

---

## 🐛 ОТЛАДКА

### Логи WordPress
```php
// wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

### Просмотр логов
```bash
tail -f wp-content/debug.log | grep akpp
```

### Проверка Авито API
```bash
curl -X POST https://api.avito.ru/token \
  -d "grant_type=client_credentials" \
  -d "client_id=YOUR_ID" \
  -d "client_secret=YOUR_SECRET"
```

---

## 📞 ПОДДЕРЖКА

**Разработчик:** AKPP45 Team  
**Email:** adamzoom@bk.ru 
**Telegram:** @akppkgn

---

## ️ ЛИЦЕНЗИЯ

Проприетарное ПО. Все права защищены © 2026 AKPP45.

---

**Версия документа:** 1.0 | **Обновлено