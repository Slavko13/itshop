# Логическая итерация 1 — локальная среда и security baseline

Дата: 10 октября 2026 года.

Статус: **ГОТОВО К РУЧНОЙ ПРОВЕРКЕ**. Production backup/staging и ротация внешнего Telegram token остаются заблокированы отсутствием доступов.

## Что сделано

- Добавлен Docker Compose: WordPress/PHP 8.4, MariaDB 11.4 и Mailpit.
- Production `wp-config.php` не используется контейнером и исключён из Docker build context.
- Исходники сайта монтируются read-only; БД и локальные uploads находятся в отдельных volumes.
- Создан безопасный локальный `wp-config`: `WP_ENVIRONMENT_TYPE=local`, заблокирован внешний WordPress HTTP, отключены file modifications и внешние интеграции.
- Добавлен WP-CLI и воспроизводимый local seed: WooCommerce pages, guest checkout, локальный COD и один синтетический виртуальный товар.
- PHP-письма направлены в Mailpit через msmtp.
- Jivo и Telegram не загружаются локально.
- Hardcoded Telegram bot token и subscription password удалены из темы.
- Telegram webhook переведён на POST-only и secret-header authentication; введённый пароль больше не попадает в log.

## Изменённые файлы

- `compose.yaml` — сервисы и volumes.
- `docker/wordpress/Dockerfile` — PHP 8.4 runtime, WP-CLI и msmtp.
- `docker/wordpress/wp-config.local.php` — изолированная локальная конфигурация.
- `docker/wordpress/msmtprc`, `php-local.ini` — локальный mail transport/PHP.
- `scripts/local-seed.php` — идемпотентный тестовый WooCommerce seed.
- `www/itsoft.store/wp-content/mu-plugins/itsoft-local-safety.php` — local-only mail identity.
- `.env.example`, `.gitignore`, `.dockerignore`, `LOCAL_DEVELOPMENT.md`.
- `functions.php`, `telegram-bot/init.php`, `telegram-bot/Webhook.php` — удаление секретов, local guard и webhook hardening.

## Автоматическая проверка

| Проверка | Результат |
|---|---|
| `docker compose config` | PASS |
| WordPress/MariaDB/Mailpit health | PASS |
| WordPress 7.1.3, PHP 8.4.26, environment `local` | PASS |
| Тема `itsoft`, WooCommerce и ACF активны | PASS |
| PHP lint трёх изменённых theme-файлов | PASS |
| Homepage, shop, product, cart, checkout, My Account HTTP | PASS |
| Добавление synthetic product в cart и переход на checkout | PASS |
| `wp_mail` → Mailpit | PASS |
| Сохранение DB option после restart | PASS |
| WordPress external HTTP blocked | PASS |
| Jivo отсутствует в local HTML | PASS |
| Telegram REST namespace отсутствует локально | PASS |

Найдено исходное compatibility notice: ACF 6.1.8 слишком рано загружает textdomain на WordPress 7.1.3. Fatal error нет, страницы отвечают 200. Обновление ACF требует проверки лицензии и отдельной регрессии.

## Что требуется настроить вручную

Для локальной проверки ничего донастраивать не нужно. Контейнеры запущены:

- сайт: `http://localhost:8080`;
- admin: `http://localhost:8080/wp-admin/`;
- Mailpit: `http://localhost:8025`;
- локальный пользователь: `itsoft_admin`;
- локальный пароль: `local-only-change-me`.

Эти данные предназначены только для изолированной локальной среды.

## Минимальные ручные тесты

1. Открыть главную — ожидается тема ITSOFT без fatal error и без Jivo.
2. Открыть `/shop/` — виден `ITSOFT Local Test Product` за 100 ₽.
3. Открыть товар и добавить в корзину — товар появляется в `/cart/`.
4. Перейти к checkout — форма оформления открывается; выбрать «Локальная тестовая оплата».
5. Открыть `/my-account/` — доступны стандартные формы WooCommerce.
6. Войти в `/wp-admin/` указанным локальным пользователем.
7. Открыть Mailpit — видно письмо `ITSOFT local smoke`; production SMTP не используется.
8. Обновить страницы после перезапуска Docker — установка и данные сохраняются.

Зафиксируйте для каждого пункта `PASS` или `FAIL` и короткий комментарий.

## Блокеры

### Production backup и staging

**BLOCKER:** нет hosting/SSH/SFTP/control-panel и production DB.

**WHY:** невозможно сделать полный backup и доказать восстановление реального магазина.

**IMPACT:** оставшаяся часть Iteration 0 и любой deployment.

**NEEDED:** hosting backup access, свежий обезличенный DB dump или staging.

### Telegram rotation/configuration

**BLOCKER:** нет Telegram bot owner access.

**WHY:** значение, ранее находившееся в исходниках, необходимо отозвать; новый token, webhook secret и subscription password должны храниться вне кода.

**IMPACT:** production Telegram остаётся небезопасным до ротации и конфигурации.

**NEEDED:** владелец бота отзывает старый token и задаёт `ITSOFT_TG_BOT_TOKEN`, `ITSOFT_TG_WEBHOOK_SECRET`, `ITSOFT_TG_SUBSCRIPTION_PASSWORD` через secret-backed `wp-config.php`/environment.

## Rollback

- Локальная среда останавливается `docker compose stop`; production не затрагивается.
- Theme security changes можно откатить только возвратом перечисленных файлов из проверенного baseline/версии Git.
- До production deployment обязательно настроить новые Telegram secrets: старые значения в код не возвращать.
- Docker volumes не удалять: их удаление уничтожит локальную БД/uploads.
