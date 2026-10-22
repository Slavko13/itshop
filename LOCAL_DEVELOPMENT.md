# Локальный запуск ITSOFT

## Назначение

Локальная среда запускает предоставленную файловую копию WordPress в Docker с отдельной MariaDB и Mailpit. Production `wp-config.php`, SMTP, Telegram, Jivo и платёжные интеграции не используются.

Ядро WordPress и сторонние плагины при первом запуске один раз копируются в быстрый Linux-volume `app_code`. Это устраняет многосекундные задержки Windows bind-mount. Тема `itsoft` и плагин `itsoft-license-fulfillment` монтируются read-only напрямую и подхватывают рабочие изменения без пересборки. База и рабочая копия uploads хранятся в отдельных Docker volumes. При первом запуске `uploads-init` один раз копирует предоставленные медиафайлы в локальный volume и исключает каталог Telegram-журналов.
Локальный must-use plugin задаёт валидный технический адрес отправителя `wordpress@itsoft.local`; все письма всё равно остаются внутри Mailpit.

## Требования

- Docker Desktop с запущенным Docker Engine.
- Свободные порты `8080` и `8025` либо другие значения в локальном `.env`.

## Первый запуск

```powershell
Copy-Item .env.example .env
docker compose up -d --build
docker compose ps
```

Первое заполнение `app_code` может занять несколько минут из-за большого количества файлов. Последующие запуски используют готовый volume и проходят быстро.

Открыть:

- WordPress: <http://localhost:8080>
- Mailpit: <http://localhost:8025>

В локальной среде доступен способ оплаты **«Тестовая оплата (без денег)»**. Он никогда не регистрируется на staging/production, не обращается во внешние API и помечает transaction ID префиксом `MOCK-`.

При пустой БД WordPress покажет стандартную установку. Используйте только тестовые данные и тестового администратора.

Для воспроизводимой установки из консоли:

```powershell
docker compose exec wordpress wp core install --allow-root --url=http://localhost:8080 --title="ITSOFT Local" --admin_user=itsoft_admin --admin_password="local-only-change-me" --admin_email=admin@itsoft.local --skip-email
docker compose exec wordpress wp theme activate itsoft --allow-root
docker compose exec wordpress wp plugin activate woocommerce advanced-custom-fields-pro --allow-root
docker compose exec wordpress wp eval-file /workspace/scripts/local-seed.php --allow-root
```

Пароль в примере предназначен исключительно для изолированной локальной среды и не должен использоваться на staging/production.
Seed создаёт штатные WooCommerce-страницы, включает только локальный COD и добавляет один синтетический виртуальный товар `ITSOFT Local Test Product` стоимостью 100 ₽.

После импорта рабочей обезличенной БД можно создать воспроизводимый fixture для проверки продажи Windows при пустом пуле:

```powershell
docker compose exec wordpress wp eval-file /workspace/scripts/configure-local-acceptance.php --allow-root --skip-plugins=advanced-custom-fields-pro,curio-seo-ai,yookassa
```

Скрипт работает только локально и связывает товар `Windows 11 Enterprise 2024 LTSC (Корпоративная)` с явно помеченным пустым MANUAL-пулом. Оплаченный мок-заказ должен перейти в `Ожидает ключ`.

## Остановка и повторный запуск

```powershell
docker compose stop
docker compose start
```

Данные сохраняются в volumes `itsoft-local_db_data`, `itsoft-local_local_uploads` и `itsoft-local_app_code`.

Если вручную обновлялись ядро WordPress или сторонний плагин в `www/itsoft.store`, кеш кода можно обновить так:

```powershell
docker compose run --rm --no-deps code-init sh -c "rm -f /target/.itsoft-code-seeded"
docker compose up -d wordpress
```

Для изменений темы `itsoft` и плагина выдачи ключей обновление `app_code` не требуется.

## Логи

```powershell
docker compose logs --no-color --tail 100 wordpress
docker compose logs --no-color --tail 100 db
docker compose logs --no-color --tail 100 mailpit
```

## Импорт обезличенной БД

Импорт выполняется только после получения согласованного dump без production secrets и персональных данных. Перед импортом необходимо проверить префикс таблиц `wpnr_`, заменить production URLs на `ITSOFT_LOCAL_URL` через WP-CLI с поддержкой serialized data и повторно убедиться, что внешние интеграции отключены.

Если получен production dump с персональными данными, сразу после URL replacement выполнить:

```powershell
docker compose exec wordpress wp eval-file /workspace/scripts/local-anonymize.php --allow-root
```

Скрипт работает только при `WP_ENVIRONMENT_TYPE=local`, обезличивает пользователей/заказы/комментарии и удаляет импортированные API keys, webhooks, payment tokens, sessions и фоновые задания.

## Безопасность

- Не копируйте production `.env` или `wp-config.php` в Docker.
- Не используйте реальные ключи лицензий, платежи и адреса покупателей.
- Все PHP-письма перехватываются Mailpit.
- Исходящие WordPress HTTP-запросы заблокированы.
- Удаление volumes уничтожает локальную БД и uploads; эта команда намеренно не включена в обычный workflow.
