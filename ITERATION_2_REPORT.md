# Логическая итерация 2 — production DB baseline и фундамент склада

Дата: 10 октября 2026 года.

Статус: **ГОТОВО К РУЧНОЙ ПРОВЕРКЕ**.

Логическая итерация объединяет технический фундамент первоначальных Iteration 1 и часть Iteration 2: импорт реальной структуры магазина, безопасную модель individual keys и минимальный административный склад.

## Что сделано

### Локальная production-копия

- Дамп `u3246696_wp842.sql` подтверждён как база `itsoft.store`: префикс `wpnr_`, 68 таблиц, WooCommerce, HPOS и Action Scheduler.
- Дамп импортирован только в локальную MariaDB.
- Production URL заменён на `http://localhost:8080` с поддержкой serialized data.
- Обезличены 1402 пользователя, HPOS/legacy-заказы, адреса, customer lookup, комментарии и download permissions.
- Удалены импортированные webhooks, Woo API keys, payment tokens, sessions, логи провайдеров и фоновые задания.
- Все payment gateways локально отключены, кроме безопасного COD.
- Создан отдельный известный локальный администратор.
- 2970 медиафайлов скопированы в локальный uploads volume; Telegram logs исключены.

### Custom plugin

Создан `itsoft-license-fulfillment` версии `0.1.0`:

- versioned migration `1.0.0`;
- отдельная capability `manage_itsoft_license_inventory` для Administrator и Shop Manager;
- пять custom tables: pools, keys, product mapping, assignments и immutable-style events;
- только модель `INDIVIDUAL`; reusable keys не реализованы;
- шифрование значения Sodium `secretbox`;
- отдельный HMAC fingerprint и database unique constraint против дубликатов;
- маскированное отображение — полный ключ не попадает в общую таблицу;
- audit events без полного значения ключа;
- создание пула;
- bulk paste, TXT и CSV import до 5000 строк/2 МБ;
- дедупликация внутри файла и по всей базе;
- nonce + server-side capability для admin actions;
- отсутствует публичный REST endpoint и export/reveal.

## Изменённые файлы

- `wp-content/plugins/itsoft-license-fulfillment/itsoft-license-fulfillment.php`.
- `includes/class-installer.php` — schema/capability.
- `includes/class-crypto.php` — encryption/fingerprint/masking.
- `includes/class-inventory-repository.php` — pools/import/events.
- `includes/class-admin.php` — admin UI/actions.
- `includes/class-plugin.php` — bootstrap.
- `scripts/local-anonymize.php` — local-only sanitization.
- `scripts/test-license-inventory.php` — integration smoke.
- `compose.yaml`, `wp-config.local.php`, `LOCAL_DEVELOPMENT.md` — DB/media/secret configuration.

## Database impact

| Table | Назначение |
|---|---|
| `wpnr_itsoft_license_pools` | Пулы ключей |
| `wpnr_itsoft_license_keys` | Зашифрованные individual keys и статусы |
| `wpnr_itsoft_license_product_pools` | Будущая связь Woo product → pool и AUTO/MANUAL |
| `wpnr_itsoft_license_assignments` | Будущие назначения order/item/unit → individual key |
| `wpnr_itsoft_license_events` | Журнал операций без plaintext key |

Production encryption key в БД не хранится. Локальная среда имеет отдельный заведомо тестовый ключ. Для staging/production позже потребуется независимый случайный base64-ключ из 32 байт в secret-backed `wp-config.php`/environment.

## Автоматическая проверка

| Проверка | Результат |
|---|---|
| SQL import и 68 исходных таблиц | PASS |
| URL replacement с serialized data | PASS |
| Users/orders/addresses/download emails anonymized | PASS, остаток 0 |
| Webhooks/API keys/payment tokens/sessions | PASS, остаток 0 |
| PHP lint всех plugin-файлов | PASS |
| Plugin activation и повторная migration | PASS |
| Ровно 5 custom tables | PASS |
| Administrator/Shop Manager capability | PASS |
| Encryption/decryption | PASS |
| Plaintext не сохранён в ciphertext | PASS |
| Masking | PASS |
| Duplicate import | PASS |
| Audit events | PASS |
| Admin authentication/page/forms | PASS |

Известные notices ACF 6.1.8 и Curio SEO относятся к исходным плагинам и не вызваны новым plugin.

## Минимальные ручные тесты

Используйте только тестовые ключи.

1. Войти в `http://localhost:8080/wp-admin/` как `itsoft_admin`.
2. Открыть меню **Склад ключей** — ожидаются таблицы «Пулы» и «Последние ключи».
3. Создать пул `Ручная проверка` — ожидается сообщение «Пул создан».
4. Выбрать пул и вставить строки:

   ```text
   TEST-LOCAL-0001
   TEST-LOCAL-0002
   TEST-LOCAL-0001
   ```

5. Нажать «Импортировать» — ожидается: добавлено `2`, дубликаты `1`, ошибки `0`.
6. Проверить список — видны только маски с окончаниями `0001` и `0002`, полные ключи не отображаются.
7. Повторить тот же импорт — ожидается: добавлено `0`, дубликаты `3`.
8. По желанию сохранить эти же строки в `.txt` или `.csv` и проверить файловый импорт в новый пул.

Ответить результатами `1 PASS … 8 PASS` либо указать номер и фактическую ошибку.

## Что не входило

- Reusable keys.
- Связь товаров с пулом в Woo admin.
- AUTO/MANUAL fulfillment.
- Резервирование/назначение ключа заказу.
- Payment callback, email и My Account.
- Reveal/export полного ключа.
- XLSX import.

## Блокеры

Для текущей локальной итерации блокеров нет. Telegram token не требуется.

До staging/production по-прежнему нужны hosting/staging access, проверенный backup/restore и безопасная настройка production encryption key.

## Rollback

- Деактивация plugin останавливает его UI/логику, но намеренно сохраняет таблицы и данные.
- Возврат кода выполняется удалением/откатом каталога plugin только после backup.
- Удаление пяти таблиц является отдельной разрушительной операцией и в автоматический uninstall не включено.
