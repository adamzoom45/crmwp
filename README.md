```markdown
# 🚗 АКПП45 CRM — Система управления автосервисом

**Версия:** 4.3 | **Статус:** 🟢 Активная разработка | **Обновлено:** 16 июня 2026

**Сайт:** https://akpp45.ru  
**GitHub:** https://github.com/adamzoom45/crmwp

---

## 📌 О ПРОЕКТЕ

АКПП45 CRM — комплексная система управления автосервисом по ремонту АКПП:
- ✅ Учёт сделок и воронка продаж
- ✅ База автомобилей 4 рынков (Japan, Asia, Europe, USA)
- ✅ Каталог АКПП, запчастей и масел
- ✅ Складской учёт с авто-списанием
- ✅ **Интеграция с Авито API (двусторонний чат)** — ЗАВЕРШЕНА
- ✅ **Регистрация клиентов + чат с гидом** — ЗАВЕРШЕНО
- ⏳ Telegram-бот управления + VPN/SSH
- ⏳ Универсальный парсер с AI-анализом
- ⏳ Мобильное приложение (Android APK)

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

## 🏗️ СТРУКТУРА РЕПОЗИТОРИЯ

### Корневая структура
```
crmwp/
├── README.md                          # Документация проекта (обновлена)
├── 404.php                            # Страница ошибки
├── style.css                          # ✅ Файл стилей темы (создан)
├── functions.php                      # ✅ Функции темы (обновлены)
├── header.php                         # ✅ Шапка сайта (исправлена)
├── footer.php                         # ✅ Подвал сайта
├── index.php                          # ✅ Главная страница
├── page.php                           # ✅ Шаблон страницы
├── single.php                         # ✅ Шаблон записи
├── sw.js                              # Service Worker для Push
│
├── assets/                            # Общие ресурсы
│   ├── css/
│   │   ├── admin.css                  # Стили админки (12.9 KB)
│   │   ├── frontend.css               # Стили фронтенда
│   │   └── modal.css                  # Стили модальных окон
│   └── js/
│       ├── admin.js                   # Админка
│       ├── auth.js                    # Авторизация
│       ├── avito-chat.js              # Чат Авито
│       ├── chat.js                    # ✅ Внутренний чат CRM
│       ├── deal-calculator.js         # ✅ Калькулятор оплаты
│       ├── modal-auth.js              # Модальная авторизация
│       ├── parser.js                  # Парсер
│       ├── push.js                    # Push уведомления
│       └── vin-decoder.js             # ✅ VIN декодер
│
├── template-parts/                    # Части шаблонов
│   └── hero-section.php               # Hero-секция главной страницы
│
└── inc/crm/                           # 🚀 ОСНОВНАЯ CRM СИСТЕМА
    ├── class-akpp-crm.php             # ✅ Главный класс (Singleton + меню)
    ├── class-akpp-install.php         # Создание 16 таблиц БД
    ├── class-akpp-db.php              # ✅ Центральный CRUD-класс для БД
    ├── class-akpp-ajax.php            # Все AJAX-обработчики
    ├── class-akpp-auth.php            # Регистрация + авторизация
    ├── class-akpp-email.php           # Отправка email
    ├── class-akpp-push.php            # Push-уведомления (FCM)
    ├── class-akpp-avito.php           # Авито API (OAuth + чат)
    ├── class-akpp-webhook.php         # Webhook для Авито
    ├── class-akpp-telegram.php        # Telegram-бот
    ├── class-akpp-parser.php          # Универсальный парсер
    ├── class-akpp-cron.php            # Cron-задачи
    │
    ├── decoders/                      # Декодеры
    │   ├── class-vin-decoder.php      # ✅ NHTSA API + кэш (10 KB)
    │   ├── class-body-decoder.php     # Toyota/Lexus body number (8.7 KB)
    │   └── class-deal-calculator.php  # ✅ Формула оплаты (11.6 KB)
    │
    ├── ai/                            # AI-анализ
    │   └── class-ai-analyzer.php      # Анализ через OpenAI API (13.9 KB)
    │
    ├── tables/                        # WP_List_Table для админки (10 файлов)
    │   ├── class-deals-table.php      # Сделки (13.5 KB)
    │   ├── class-employees-table.php  # Сотрудники (12.7 KB)
    │   ├── class-vehicles-table.php   # Авто (11 KB)
    │   ├── class-transmissions-table.php # АКПП (11.5 KB)
    │   ├── class-leads-table.php      # Лиды (13.9 KB)
    │   ├── class-parts-table.php      # Запчасти (13.3 KB)
    │   ├── class-oils-table.php       # Масла (10.3 KB)
    │   ├── class-parser-table.php     # Парсер (16 KB)
    │   ├── class-users-table.php      # ✅ Пользователи (12.8 KB)
    │   └── class-avito-dialogs-table.php # ✅ Диалоги Авито (11.4 KB)
    │
    ├── assets/                        # Ассеты CRM
    │   ├── css/
    │   │   ├── admin.css              # Стили админки
    │   │   └── frontend.css           # Стили фронтенда
    │   └── js/
    │       ├── admin.js
    │       ├── auth.js
    │       ├── chat.js
    │       ├── vin-decoder.js
    │       ├── deal-calculator.js
    │       ├── parser.js
    │       ├── push.js
    │       └── avito-chat.js
    │
    └── templates/                     # Шаблоны CRM (17 файлов)
        ├── dashboard.php              # Панель + воронка (9 KB)
        ├── deals.php                  # Список сделок (2.4 KB)
        ├── deal-form.php              # Форма сделки (18.4 KB)
        ├── new-deal.php               # Новая сделка (17.6 KB)
        ├── employees.php              # Сотрудники (6 KB)
        ├── vehicles.php               # Авто (7.1 KB)
        ├── transmissions.php          # АКПП (6.5 KB)
        ├── parts.php                  # Склад (6.6 KB)
        ├── oils.php                   # Масла (6.3 KB)
        ├── parser.php                 # Парсер + AI (13.9 KB)
        ├── leads.php                  # Лиды (5.8 KB)
        ├── chat.php                   # ✅ Внутренний чат (9.8 KB)
        ├── avito-dialogs.php          # Диалоги Авито (9.2 KB)
        ├── avito-settings.php         # ✅ Настройки Авито (11.1 KB)
        ├── avito.php                  # Авито (5.8 KB)
        ├── telegram.php               # Telegram (14.1 KB)
        ├── users.php                  # Пользователи (5.1 KB)
        └── frontend/                  # 🌐 Фронтенд шаблоны
            ├── chat.php               # ✅ Клиентский чат (19.3 KB)
            ├── login.php              # Вход (15.2 KB)
            ├── register.php           # Регистрация (11.9 KB)
            ├── registration.php       # ✅ Форма регистрации (10.3 KB)
            ├── profile.php            # Профиль (19.1 KB)
            ├── modal-login.php        # Модальный вход (2.9 KB)
            └── modal-register.php     # Модальная регистрация (2.9 KB)
```

### Поддиректории CRM

#### 📁 `decoders/` — Декодеры и калькуляторы
```
decoders/
├── class-vin-decoder.php          # NHTSA API + кэш (10 KB)
├── class-body-decoder.php         # Toyota/Lexus body number (8.7 KB)
└── class-deal-calculator.php      # Формула оплаты (11.6 KB)
```

#### 📁 `ai/` — AI анализ
```
ai/
└── class-ai-analyzer.php          # AI анализ парсинга (13.9 KB)
```

#### 📁 `tables/` — WP_List_Table (10 файлов)
```
tables/
├── class-deals-table.php          # Сделки (13.5 KB)
├── class-employees-table.php      # Сотрудники (12.7 KB)
├── class-vehicles-table.php       # Авто (11 KB)
├── class-transmissions-table.php  # АКПП (11.5 KB)
├── class-leads-table.php          # Лиды (13.9 KB)
├── class-parts-table.php          # Запчасти (13.3 KB)
├── class-oils-table.php           # Масла (10.3 KB)
├── class-parser-table.php         # Парсер (16 KB)
├── class-users-table.php          # ✅ Пользователи (12.8 KB)
└── class-avito-dialogs-table.php  # ✅ Диалоги Авито (11.4 KB)
```

#### 📁 `templates/` — Шаблоны CRM (17 файлов)
```
templates/
├── dashboard.php                  # Панель + воронка (9 KB)
├── deals.php                      # Список сделок (2.4 KB)
├── deal-form.php                  # Форма сделки (18.4 KB)
├── new-deal.php                   # Новая сделка (17.6 KB)
├── employees.php                  # Сотрудники (6 KB)
├── vehicles.php                   # Авто (7.1 KB)
├── transmissions.php              # АКПП (6.5 KB)
├── parts.php                      # Склад (6.6 KB)
├── oils.php                       # Масла (6.3 KB)
├── parser.php                     # Парсер + AI (13.9 KB)
├── leads.php                      # Лиды (5.8 KB)
├── chat.php                       # ✅ Внутренний чат (9.8 KB)
├── avito-dialogs.php              # Диалоги Авито (9.2 KB)
├── avito-settings.php             # ✅ Настройки Авито (11.1 KB)
├── avito.php                      # Авито (5.8 KB)
├── telegram.php                   # Telegram (14.1 KB)
├── users.php                      # Пользователи (5.1 KB)
└── frontend/                      # 🌐 Фронтенд шаблоны
    ├── chat.php                   # ✅ Клиентский чат (19.3 KB)
    ├── login.php                  # Вход (15.2 KB)
    ├── register.php               # Регистрация (11.9 KB)
    ├── registration.php           # ✅ Форма регистрации (10.3 KB)
    ├── profile.php                # Профиль (19.1 KB)
    ├── modal-login.php            # Модальный вход (2.9 KB)
    └── modal-register.php         # Модальная регистрация (2.9 KB)
```

---

## 📊 МЕНЮ CRM (13 пунктов)

```
📊 Панель          → статистика + воронка + склад
📋 Сделки          → таблица сделок
➕ Новая           → форма с VIN + запчасти
👥 Сотрудники      → CRUD
🚗 Авто            → 4 рынка (Japan/Asia/Europe/USA)
⚙️ АКПП            → каталог + запчасти + масла
📦 Склад           → запчасти (авто-списание)
🛢️ Масла           → каталог масел
🔍 Парсер          → универсальный + AI
📨 Лиды            → заявки + чат + Авито
👥 Клиенты         → ✅ пользователи сайта (WP_List_Table)
💬 Авито чаты      → ✅ диалоги Авито (WP_List_Table)
📱 Telegram        → бот + VPN/SSH
```

---

## 🔑 КЛЮЧЕВЫЕ ФУНКЦИИ

### 💰 Расчёт оплаты сотрудников
```
Оплата = work_cost × (work_hours / standard_hours) × (percent / 100)
```
Пример: 80 000₽ × (8ч / 10ч) × 50% = **32 000₽**

**Реализация:** `assets/js/deal-calculator.js` + `decoders/class-deal-calculator.php`

### 🔄 Воронка продаж
```
lead → new → diagnostic → in_work → completed / rejected
```

### 🛒 Авто-списание запчастей
При сохранении сделки → автоматическое списание со склада → запись в `wp_akpp_deal_parts`.

### 🤖 AI-анализ парсинга
1. Парсер извлекает данные с любого сайта
2. AI определяет: тип проблемы, симптомы, причины, решения, нужные запчасти
3. Модератор одобряет → данные сохраняются

### 💬 Двусторонний чат с Авито — ✅ РЕАЛИЗОВАНО
- **OAuth 2.0** (`class-avito-api.php`) — получение и обновление токенов
- **Webhook** (`class-avito-webhook.php`) — мгновенное получение сообщений от Авито
- **API отправка** — отправка ответов через API (клиент видит в приложении Авито)
- **Cron backup** (`class-avito-cron.php`) — синхронизация каждые 15 минут на случай сбоя Webhook
- **WP_List_Table** (`class-avito-dialogs-table.php`) — удобный интерфейс со списками диалогов, фильтрами, поиском

### 👤 Регистрация клиентов — ✅ РЕАЛИЗОВАНО
- **Форма** (`templates/frontend/registration.php`) — с валидацией, honeypot защитой от спама
- **Обработчик** (`class-user-registration.php`) — создание пользователя WP + запись в кастомную таблицу
- **Email** — автоматическая отправка логина и пароля
- **Клиентский чат** (`templates/frontend/chat.php`) — общение с менеджером

### 🔍 VIN-декодер — ✅ РЕАЛИЗОВАНО
- **JS** (`assets/js/vin-decoder.js`) — валидация формата (17 символов, без I/O/Q), AJAX-запрос, автозаполнение
- **PHP** (`decoders/class-vin-decoder.php`) — обращение к NHTSA API, кэширование
- **Кэширование** — повторные запросы не обращаются к API
- **Автозаполнение** — марка, модель, год, body number

---

## 🚀 УСТАНОВКА

### Требования
- WordPress 6.0+
- PHP 8.0+
- MySQL 8.0+
- cURL включён

### Шаги
1. Скопировать папку `akpp-kurgan/` в `wp-content/themes/`
2. Активировать тему в WP Admin → Внешний вид → Темы
3. Перейти в WP Admin → CRM → Панель (таблицы создадутся автоматически)
4. Настроить Авито API: CRM → Настройки Авито → Client ID + Client Secret
5. Настроить Telegram: CRM → Telegram → Bot Token + Chat ID

---

## 📱 ANDROID APK

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

### ✅ ФАЗА 1: Фундамент — ЗАВЕРШЕНА
- [x] 16 таблиц БД
- [x] Главный класс + меню (обновлён)
- [x] CSS (стиль akpp45.ru)
- [x] Панель + воронка
- [x] WP_List_Table для пользователей и диалогов Авито
- [x] JavaScript калькулятор оплаты
- [x] Шаблоны чатов (внутренний + клиентский)
- [x] Настройки Авито API

### ✅ ФАЗА 2: Авито интеграция — ЗАВЕРШЕНА
- [x] OAuth 2.0 (`class-avito-api.php`)
- [x] Webhook обработчик (`class-avito-webhook.php`)
- [x] Двусторонний чат (`class-chat-ajax.php` + `chat.js`)
- [x] Cron синхронизация (`class-avito-cron.php`)

### ✅ ФАЗА 3: Регистрация + Чат — ЗАВЕРШЕНА
- [x] Форма регистрации (`templates/frontend/registration.php`)
- [x] Генерация пароля + email (`class-user-registration.php`)
- [x] Клиентский чат (`templates/frontend/chat.php`)
- [ ] Push уведомления (частично)

### 🔄 ФАЗА 4: Сделки + Склад — В ПРОЦЕССЕ
- [x] VIN-декодер JS (`assets/js/vin-decoder.js`)
- [x] VIN-декодер PHP (`decoders/class-vin-decoder.php`)
- [x] Форма сделки с VIN (`templates/new-deal.php`)
- [ ] Авто-списание запчастей (тестируется)
- [x] Калькулятор оплаты (`assets/js/deal-calculator.js`)

### ⏳ ФАЗА 5: Парсер + AI
- [x] Универсальный парсер (`class-akpp-parser.php`)
- [x] AI анализ (`ai/class-ai-analyzer.php`)
- [ ] Модерация (в разработке)

### ⏳ ФАЗА 6: Telegram + APK
- [x] Telegram бот (`class-akpp-telegram.php`)
- [ ] VPN/SSH настройки
- [ ] Android APK (отдельный репозиторий)

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

### Проверка Webhook
```bash
curl -X POST https://akpp45.ru/wp-json/akpp/v1/avito-webhook \
  -H "Content-Type: application/json" \
  -d '{
    "type": "message.created",
    "object": {
      "id": 12345,
      "dialog_id": 67890,
      "author_id": 111,
      "text": "Тестовое сообщение",
      "created_at": "2026-06-16T12:00:00Z"
    }
  }'
```

---

## 📊 СТАТИСТИКА ПРОЕКТА

| Показатель | Значение |
|-----------|----------|
| PHP классов | 25+ |
| JavaScript файлов | 9 |
| CSS файлов | 3 |
| Шаблонов | 24 |
| Таблиц БД | 16 |
| Размер кода | ~250 KB |
| Завершённость | **75%** (Фазы 1-3 ✅, Фаза 4 🔄, Фазы 5-6 ⏳) |

---

## 📞 ПОДДЕРЖКА

**Разработчик:** AKPP45 Team  
**Email:** adamzoom@bk.ru  
**Telegram:** @akppkgn

---

## ⚖️ ЛИЦЕНЗИЯ

Проприетарное ПО. Все права защищены © 2026 AKPP45.

---

**Версия документа:** 2.1 | **Обновлено:** 16 июня 2026
```
