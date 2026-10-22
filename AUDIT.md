# Технический аудит ITSOFT

Дата аудита: 9 октября 2026 года.

Область аудита: файловая копия `J:\itshop\www\itsoft.store` и безопасные read-only проверки публичного сайта `https://itsoft.store`.

## Краткий вывод

Сайт является действующим WordPress/WooCommerce-магазином с собственной темой `itsoft`. Товарный каталог, карточки товаров, стандартная корзина, checkout и My Account опираются на WooCommerce. Значительная часть пользовательского интерфейса и интеграция Telegram размещены прямо в родительской теме.

Ключевой контур нового ТЗ отсутствует: нет сущностей пула и лицензионного ключа, склада, связи пулов с SKU, режимов AUTO/MANUAL, атомарного резервирования, отдельного статуса выдачи, выдачи после оплаты, повторной отправки ключа, вывода ключа в My Account и журнала операций с ключами.

До начала разработки необходимы резервная копия БД, staging-копия и доступ к административной панели/БД. В предоставленном наборе нет дампа БД и локально исполняемой среды PHP/MySQL, поэтому активные настройки плагинов, способы оплаты, SMTP, WooCommerce pages/options, HPOS, Action Scheduler и реальные статусы заказов нельзя подтвердить только по файлам.

Выявлен критический инцидент с секретами: Telegram bot token и пароль подписки находятся в исходном коде темы. Их значения в этом отчёте намеренно не приводятся. Токен и пароль нужно считать скомпрометированными, ротировать и вынести из репозитория до дальнейшей разработки.

# 1. Архитектура проекта

- Корень сайта: `www/itsoft.store`.
- CMS: WordPress 7.1.3 (`wp-includes/version.php:19`).
- Интернет-магазин: WooCommerce 10.7.0 (`wp-content/plugins/woocommerce/woocommerce.php:6`).
- Активная на публичном сайте тема: `itsoft`; подтверждено подключением ресурсов `/wp-content/themes/itsoft/...` на главной странице.
- Тема самостоятельная, не child theme: в `wp-content/themes/itsoft/style.css` отсутствует заголовок `Template`.
- Версия темы: 1.0.0; заявлено `Tested up to: 6.5`, хотя установлен WordPress 7.1.3; заявлено `Requires PHP: 8.4`.
- Вторая установленная тема: `twentytwentyfive`.
- Кодовая база не находится в Git-репозитории. История изменений и отличие темы/плагинов от эталонных пакетов недоступны.
- `WP_DEBUG` отключён (`wp-config.php:88`).
- Кеширующего drop-in, `mu-plugins`, `advanced-cache.php`, `object-cache.php`, `db.php` и `sunrise.php` не найдено.
- WordPress Core и WooCommerce Core в ходе аудита не изменялись.

Рекомендуемая архитектура новой бизнес-логики: отдельный custom plugin, а не дальнейшее расширение `functions.php` родительской темы. Он должен владеть схемой данных, миграциями, доменной моделью ключей, admin UI, выдачей, email и журналом. Тема должна отвечать только за представление.

# 2. Структура каталогов

Основные каталоги:

- `www/itsoft.store/wp-admin`, `wp-includes` — WordPress Core.
- `www/itsoft.store/wp-content/plugins` — 17 установленных plugin-каталогов.
- `www/itsoft.store/wp-content/themes/itsoft` — основная кастомная тема.
- `www/itsoft.store/wp-content/themes/itsoft/acf-json` — 11 локальных ACF field groups.
- `www/itsoft.store/wp-content/themes/itsoft/inc` — контроллеры карточки товара, каталога, отзывов и CPT дистрибутивов.
- `www/itsoft.store/wp-content/themes/itsoft/woocommerce` — WooCommerce template overrides и кастомные partials.
- `www/itsoft.store/wp-content/themes/itsoft/telegram-bot` — самописная Telegram-интеграция.
- `www/itsoft.store/wp-content/uploads/telegram` — журнал Telegram-интеграции.

В корне рабочей копии нет тестового каркаса, Composer-конфигурации, CI и Git metadata.

# 3. Активная тема

Тема `IT Soft` (`wp-content/themes/itsoft/style.css`) подтверждена на production. Основная загрузка кода идёт из `functions.php:7-13`:

- `inc/woocommerce/product-card-controller.php` — `Itsoft_Product_Card_Controller`, подготовка вариаций, цен, атрибутов и cart context;
- `inc/woocommerce/product-single-controller.php` — контекст страницы товара;
- `inc/woocommerce/product-reviews.php` — AJAX-пагинация отзывов товара;
- `inc/woocommerce/catalog-template.php` — дерево категорий;
- `inc/all-reviews.php` — AJAX-пагинация всех отзывов;
- `inc/distribution-post-type.php` — CPT `distribution` и taxonomy `download`;
- `customize.php` — настройки Customizer.

`inc/admin/product-attributes-report.php` существует, но не подключается из `functions.php`; его admin page фактически недостижима, если файл не подключается из БД/другого кода, которого в копии нет.

Тема содержит много WooCommerce overrides. Два override уже отстают от шаблонов WooCommerce 10.7.0:

- `woocommerce/single-product/product-image.php`: тема `@version 9.7.0`, core template `10.5.0`;
- `woocommerce/single-product/add-to-cart/variation-add-to-cart-button.php`: тема `10.2.0`, core `10.5.2`.

Это отдельный регрессионный риск для карточек и вариативных товаров.

# 4. Используемые плагины

Найдены следующие установленные плагины:

| Каталог | Плагин | Версия из файлов | Статус активности |
|---|---|---:|---|
| `advanced-custom-fields-pro` | Advanced Custom Fields PRO | 6.1.8 | Использование подтверждено темой и ACF JSON; активность БД нужно подтвердить |
| `akismet` | Akismet Anti-spam | 5.7 | Подтверждён namespace `akismet/v1` |
| `classic-editor` | Classic Editor | 1.6.7 | Требует проверки БД |
| `cookie-notice` | Compliance / Cookie Notice | 3.1.2 | Подтверждён namespace `cookie-notice/v1` |
| `curio-seo-ai` | Curio SEO AI PRO | 2.3.23 | Требует проверки БД |
| `custom-permalinks` | Custom Permalinks | 3.1.2 | Требует проверки БД |
| `cyr2lat` | Cyr-To-Lat | 6.7.0 | Требует проверки БД |
| `gtranslate` | GTranslate | 3.0.10 | Ресурс плагина подключён на production |
| `limit-login-attempts-reloaded` | Limit Login Attempts Reloaded | 3.2.1 | Подтверждён namespace `llar/v1` |
| `rearrange-woocommerce-products` | Rearrange Products for WooCommerce | 6.0.1 | Требует проверки БД |
| `regenerate-thumbnails` | Regenerate Thumbnails | 3.1.6 | Подтверждён namespace `regenerate-thumbnails/v1` |
| `show-current-template` | Show Current Template | 0.5.4 | Требует проверки БД; production-use нежелателен |
| `simple-local-avatars` | Simple Local Avatars | 2.8.6 | Требует проверки БД |
| `smart-slider-3` | Smart Slider 3 | 3.5.1.34 | Ресурсы подключены на production |
| `taxonomy-terms-order` | Category Order and Taxonomy Terms Order | 1.9.5 | Требует проверки БД |
| `tinymce-advanced` | Advanced Editor Tools | 5.9.2 | Требует проверки БД |
| `woocommerce` | WooCommerce | 10.7.0 | Подтверждён публичным сайтом и REST namespaces |

Отдельного SMTP-плагина и отдельного payment gateway/YooKassa-плагина в файловой копии нет. Файл `wp-content/yookassa-debug.log` существует, но имеет старую дату и сам по себе не подтверждает текущую интеграцию. Источник фактического payment callback остаётся `NEEDS_INVESTIGATION`.

# 5. Кастомный код

Основной кастомный код находится в теме:

- `functions.php:18-390` — настройка темы, assets, каталог и ACF JSON;
- `functions.php:394-474` — обязательные email/phone для classic checkout и phone для Store API checkout;
- `functions.php:999-1005` — Jivo widget;
- `functions.php:1009-1013` — Telegram-конфигурация и загрузка бота;
- `inc/woocommerce/product-card-controller.php` — серверная модель карточки товара;
- `assets/js/product-page.js` — Store API add/update/remove cart item, вариации и quantity controls;
- `assets/js/cart-badge.js` — синхронизация счётчика корзины;
- `page-cart.php` — стандартный shortcode `[woocommerce_cart]`;
- `template-parts/header/search-form.php` — поиск на `/search/?s=...`;
- `telegram-bot/*.php` — webhook, отправка заказа, API client и журнал.

Сущностей или функций, относящихся к складу лицензионных ключей, не найдено.

# 6. WooCommerce hooks и filters

Собственные WooCommerce hooks темы:

| Hook/filter | Callback | Файл | Назначение |
|---|---|---|---|
| `woocommerce_checkout_fields` | `itsoft_require_checkout_contact_fields` | `functions.php:394` | Делает email/phone обязательными в classic checkout |
| `option_woocommerce_checkout_phone_field` | `itsoft_force_required_checkout_phone_field` | `functions.php:416` | Принудительно требует телефон |
| `woocommerce_checkout_process` | `itsoft_validate_required_checkout_contacts` | `functions.php:421` | Проверяет наличие email/phone |
| `woocommerce_store_api_checkout_update_order_from_request` | `itsoft_validate_store_api_required_phones` | `functions.php:451` | Проверяет phone для Store API checkout |
| `loop_shop_per_page` | `itsoft_catalog_products_per_page` | `functions.php:384` | 9 товаров на страницу |
| `loop_shop_columns` | `itsoft_catalog_loop_columns` | `functions.php:389` | 3 колонки |
| `woocommerce_checkout_order_processed` | `Itsoft_Tg_Order_Handler::process_order` | `telegram-bot/OrderHandler.php:17` | Telegram-сигнал при checkout |
| `woocommerce_new_order` | тот же | `telegram-bot/OrderHandler.php:18` | Telegram-сигнал при создании заказа |
| `woocommerce_order_status_processing` | тот же | `telegram-bot/OrderHandler.php:19` | Telegram-сигнал при processing |

Тема также заменяет стандартную WooCommerce gallery через `init` (`functions.php:104-108`) и активно вызывает стандартные template hooks в archive/single templates.

Hook успешной оплаты (`woocommerce_payment_complete`) в кастомном коде не используется. Это означает, что точка безопасной выдачи ключа пока отсутствует.

# 7. Логика checkout

Подтверждено:

- Classic checkout расширен фильтром полей и server-side проверкой наличия billing email/phone.
- Store API checkout отдельно проверяет phone.
- Формат email, повтор email, исправление распространённых доменных опечаток и полноценная симметрия classic/Blocks не реализованы в теме.
- Покупка без регистрации зависит от WooCommerce options в БД; по файлам не подтверждается.
- Публичный `/checkout/` при пустой корзине перенаправляет на `/cart/`, что является штатным поведением.

Риск: `itsoft_validate_store_api_required_phones()` считает любой непустой `shipping_address` основанием требовать shipping phone; это нужно проверить на реальном Blocks checkout.

# 8. Логика оплаты

- В кастомной теме нет класса `WC_Payment_Gateway`, обработчика payment webhook или callback.
- В файловой копии нет отдельного payment plugin.
- Способ, которым production подтверждает оплату и записывает transaction ID, не установлен.
- Telegram сейчас может уведомить уже на `woocommerce_new_order`/`checkout_order_processed`, то есть до оплаты. Meta `_itsoft_tg_notified` после успешной отправки не даст повторить уведомление при последующем статусе `processing`. Это не соответствует будущему требованию «уведомлять об оплаченном ручном заказе».

До Iteration 5 необходимо получить список активных gateways, настройки webhook/callback и примеры жизненного цикла тестового заказа из БД/админки.

# 9. Логика заказов

Сейчас используются стандартная модель заказа и стандартные статусы WooCommerce. В кастомном коде не найдены:

- отдельный payment status и issue status;
- публичный 10-значный номер;
- key/order relation;
- поиск по публичному номеру, transaction ID и товару;
- admin action ручной выдачи;
- action повторной отправки ключа;
- технический журнал выдачи.

Telegram message использует внутренний `$order->get_id()` (`telegram-bot/OrderHandler.php:44-53`), а не отдельный публичный номер.

# 10. Email

- Кастомных `WC_Email` классов, `wp_mail()` вызовов, email templates для ключа и SMTP hooks в теме нет.
- Стандартные WooCommerce email доступны через core, но их включённость и deliverability требуют проверки в БД и тестовой отправкой.
- SMTP plugin в файловой копии отсутствует.
- Доменная почта, SPF/DKIM/DMARC и поведение после статуса Completed не проверены.
- Повторная отправка уже выданного ключа отсутствует.

# 11. Личный кабинет

- Header ведёт на стандартную WooCommerce My Account page (`template-parts/header/user-nav.php:24,34`).
- Публичная `/my-account/` отдаёт стандартную WooCommerce login form.
- Theme overrides для `woocommerce/myaccount/*` отсутствуют.
- Вывод публичного номера, отдельного статуса выдачи и ключа отсутствует.
- Исправление задания пароля, mobile registration, logout UX и вход по одноразовой ссылке/коду в коде не найдены.

# 12. Корзина

- Карточка товара использует WooCommerce Store API из `assets/js/product-page.js` и умеет add/update/remove item, плюс/минус и переход в корзину.
- Серверный markup и параметры количества формирует `woocommerce/partials/product-cart.php` и `Itsoft_Product_Card_Controller::prepare_cart_context()`.
- Отдельная страница корзины — стандартный shortcode `[woocommerce_cart]` (`page-cart.php:30`); автоматического обновления корзины и специальной кнопки «Купить» в теме не найдено.
- На 9 октября 2026 года публичный Store API показывает несколько Windows-позиций как `is_in_stock=false`; такие карточки получают «Подробнее» вместо добавления в корзину. Это объясняет как минимум часть жалобы «Windows не добавляется» и конфликтует с ТЗ: отсутствие ключей не должно запрещать оплату, а должно переводить заказ в ручную выдачу.
- Требование для `quantity > 1` является открытым бизнес-решением и должно быть принято до изменения cart rules.

# 13. Поиск

Поиск использует стандартный WordPress/WooCommerce параметр `s`; собственных `pre_get_posts`, `posts_search`, synonyms, transliteration, keyboard-layout или fuzzy ranking hooks в теме нет.

Read-only проверка публичного WooCommerce Store API 9 октября 2026 года:

| Запрос | Результат |
|---|---|
| `Windows` | товары найдены |
| `Microsoft` | товары найдены |
| `Office` | товары найдены |
| `Виндовс`, `Виндоус`, `Винда` | 0 |
| `Win 11`, `Windows11` | 0 |
| `Майкрософт`, `Офис` | 0 |

Следовательно, требования поиска по русским вариантам, слитному написанию, синонимам, опечаткам и приоритету товара над статьями сейчас не выполнены.

# 14. Mobile

- Responsive стили сосредоточены в `assets/css/main.css`; точный аудит визуальных дефектов требует матрицы устройств и скриншотного/regression прогона.
- Mobile menu/search управляются `assets/js/main.js` и markup в `template-parts/header/catalog-nav.php`/`mobile-nav.php`.
- В обработчике mobile menu клик обрабатывается только если `.mobile-nav__icon-svg` уже имеет класс `active`; это подозрительное условие для заявленного дефекта открытия категорий.
- Product page, cart badge и catalog infinite loading имеют отдельный JS.
- Автоматических browser/mobile tests нет.

# 15. Существующие проблемы

1. Полностью отсутствует склад и выдача ключей.
2. Нет надёжной точки запуска по подтверждённой оплате.
3. Часть цифровых Windows-товаров блокируется стандартным Woo stock.
4. Поиск не поддерживает требуемые варианты ввода.
5. Корзина на отдельной странице остаётся стандартной и не покрывает весь UX из ТЗ.
6. My Account не расширен данными выдачи.
7. Нет custom email и SMTP tooling.
8. Payment plugin/callback не представлен в файловой копии.
9. Два WooCommerce override устарели относительно 10.7.0.
10. Тема заявлена tested up to WordPress 6.5 при установленном 7.1.3.
11. Версия темы фиксирована как 1.0.0 и используется для cache busting; изменения assets могут не сбрасывать клиентский кеш.
12. Самописный Telegram-код расположен в теме и содержит секреты.
13. Telegram отправляет PII и данные заказа при создании, не только после оплаты.
14. В Telegram API client используются синхронный `file_get_contents`, до 5 попыток и `sleep(1..4)` в web request; это способно задерживать checkout/status transition.
15. Webhook публичный (`permission_callback => __return_true`), принимает GET/POST и не проверяет Telegram secret token/header.
16. При неверной авторизации бот пишет пользовательский текст в log, что создаёт риск утечки секретов/PII в журнал.
17. `inc/admin/product-attributes-report.php` не подключён.
18. Нет Git, CI, automated tests и воспроизводимого dev environment.

# 16. Риски

## Критические

- Компрометация Telegram bot token и пароля подписки. Нужна ротация, удаление из истории/копий и хранение через environment/secret config.
- Двойная выдача при наивной реализации. Нужны уникальные ограничения и атомарный server-side claim.
- Неверная выдача до фактической оплаты из-за неизвестного gateway lifecycle.
- Потеря/повторная выдача при retries webhook или повторных status hooks без идемпотентности.
- Работа напрямую на production без staging и backup.

## Высокие

- Нет дампа БД: неизвестны HPOS, active plugins, checkout type, payment gateway, guest checkout, mail settings и реальные статусы.
- Бизнес-логика в родительской теме зависит от активации темы и плохо подходит для миграций/фоновых задач.
- Устаревшие Woo templates могут конфликтовать с WooCommerce 10.7.0.
- Стандартный Woo stock сейчас блокирует покупки, хотя будущий key inventory не должен блокировать их.
- Синхронный Telegram может ухудшить checkout и повторно обрабатывать PII.

## Средние

- ACF fields зависят от старого ACF PRO package; совместимость с текущими WordPress/PHP требует проверки.
- Нет наблюдаемости email delivery и бизнес-операций.
- Нет формализованного rollback и миграционной версии custom code.

# 17. Какие части ТЗ уже реализованы

- Действующий WordPress/WooCommerce-магазин и существующая тема.
- Товары, категории, карточки и Store API cart operations.
- Standard checkout/order/account foundation.
- Обязательность email/phone в classic checkout и phone в Store API checkout.
- Показываемый счётчик корзины.
- Telegram bot infrastructure и generic order notifications (но не нужные события/безопасность).
- ACF infrastructure и JSON field groups.
- Jivo widget.
- Каталог с сортировкой и infinite loading.
- Базовая серверная sanitize/escape практика в значительной части темы.

# 18. Какие части реализованы частично

- Корзина: product page controls есть, отдельная cart page не соответствует полному UX.
- Email validation: обязательность есть, повтор адреса/доменные подсказки отсутствуют.
- Уведомления: Telegram есть, но события и безопасность не соответствуют ТЗ; email fallback для событий склада отсутствует.
- Admin order data/search: стандартный WooCommerce присутствует, специализированных полей и поиска нет.
- My Account: стандартный WooCommerce присутствует, ключей и issue status нет.
- Каталог/UX/mobile: кастомная реализация есть, перечисленные дефекты требуют отдельных проверок и исправлений.
- Журналирование: есть только Telegram debug log, нет audit trail ключей.

# 19. Какие части отсутствуют

- Пулы и физические ключи со статусами.
- Import TXT/CSV/XLSX и deduplication.
- Связь одного пула с несколькими SKU.
- AUTO/MANUAL setting у товара.
- Атомарное резервирование и защита от race condition.
- Fallback в waiting key при нулевом остатке.
- Остатки и edge-triggered notifications 1/0.
- Отдельные payment/issue statuses.
- Публичный номер заказа.
- Ручная выдача, resend и безопасное исправление email.
- Email с ключом и My Account key view.
- Специализированный поиск заказов.
- Synonym/fuzzy/layout/transliteration product search.
- Automated acceptance/regression suite для T01-T10.

# 20. Предлагаемый способ реализации

Создать отдельный plugin, условное имя `itsoft-license-fulfillment`, со следующими слоями:

1. Versioned schema migrations и custom tables для pools, keys, pool-product links и immutable operation log.
2. Repository/service layer без прямой зависимости UI от SQL.
3. Atomic claim одной свободной строки с DB constraint и идемпотентностью по order item.
4. Отдельное состояние выдачи на уровне order item, агрегированное на order level.
5. WooCommerce product data fields для режима и pool mapping.
6. Admin pages склада и order actions с capability checks/nonces.
7. Payment adapter, запускаемый только из подтверждённого lifecycle фактического gateway.
8. Один fulfillment service для AUTO, MANUAL, resend, email и My Account, чтобы исключить расхождение сценариев.
9. Custom `WC_Email` и Action Scheduler для повторяемых внешних отправок.
10. Telegram adapter с асинхронной доставкой, email fallback и дедупликацией событий.
11. Integration tests для T01-T10 и concurrency test T05.

Конкретная схема и hooks фиксируются после решений по открытым сценариям и проверки production DB/gateway. Реализация в ходе этого аудита не начиналась.

## Необходимые доступы перед Iteration 0/1

- свежий дамп БД или staging с доступом WP Admin;
- способ создания полного backup и подтверждённый rollback;
- список активных plugins и WooCommerce status/system report;
- доступ к настройкам payment gateway и тестовому merchant environment;
- SMTP/dns/mailbox access для тестов;
- Telegram bot owner access для ротации token;
- Яндекс.Вебмастер и, при необходимости, Jivo admin access;
- перечень репрезентативных заказов без публикации персональных данных.

## Открытые решения, которые нельзя принимать молча

1. Выдача для заказа с несколькими цифровыми позициями и частичные ошибки.
2. Поведение при quantity > 1.
3. Возврат/отмена после выдачи и возможность возврата ключа в пул.
4. Шифрование в БД и маскирование в admin/logs/backups.
5. Статус и retry при ошибке email после успешной выдачи.
6. Точный payment event для каждого активного gateway.
7. Telegram fallback при сетевой недоступности.
8. Окончательная модель общего пула нескольких SKU.

# 21. Дополнительный аудит: payment lifecycle

## Подтверждено

- В файловой поставке нет отдельного payment gateway/YooKassa plugin.
- Публичный WooCommerce Store API на момент read-only проверки объявлял только `cod` (оплата при получении/ручной способ), а не online card gateway.
- `wp-content/yookassa-debug.log` имеет старую дату и фиксирует прежнюю активацию/деактивацию, но не является исполняемой интеграцией.
- В коде темы нет проверенной реализации подписи payment callback, transaction dedupe или назначения ключа.

## Не подтверждено без доступа

- active gateways в Woo options, test/prod mode, webhook URL/secret, signature verification;
- transaction ID/order meta, фактический момент `payment_complete`, webhook retry и idempotency provider plugin;
- были ли gateway-файлы исключены из переданной копии или удалены с production.

Вывод: аудит существующей оплаты входит в scope, но подключение provider с нуля является `CHANGE_REQUEST`. Независимо от выбранного gateway выдача должна иметь собственную идемпотентность: повтор одного paid event не создаёт второй assignment и не меняет остаток повторно.

# 22. Дополнительный аудит: distributions / storage

Текущая модель:

1. Тема регистрирует публичный CPT `distribution` и публичную taxonomy `download`.
2. ACF group `group_69b2cd2db11b3` хранит repeater `links`, где каждая строка содержит `flag`, `version` и поле `link` типа URL.
3. `single-distribution.php` получает `get_sub_field('link')` и выводит значение непосредственно в `href` публичной кнопки «Скачать».
4. Товар связывается с записью дистрибутива полем `distribution_link`; это ссылка на landing page, а не защищённая выдача файла.
5. Счётчик `download_count` является обычным ACF number и сам по себе не защищает и не лимитирует скачивание.

В локальном `wp-content/uploads` установочные архивы/образы с расширениями ZIP/RAR/7Z/EXE/MSI/ISO/DMG/PKG/APK/DEB/RPM не найдены. Без БД отсутствуют фактические значения `links[].link`, поэтому нельзя определить, ведут они на другой web server, облачное хранилище/CDN или отсутствующие локальные файлы.

Текущая защита скачивания: **не обнаружена**. URL присутствует в HTML, не имеет локально реализованного TTL, entitlement check, подписанного токена или revoke/regenerate lifecycle. Атрибут HTML `download` не является контролем доступа, а для cross-origin URL может игнорироваться браузером.

Результат классификации:

- установление фактического storage — `IN_SCOPE`, но зависимо от DB/WP Admin;
- режим permanent link соответствует текущей архитектуре публичного URL;
- temporary link 24 часа и regenerate — `CHANGE_REQUEST`; конкретное решение нельзя выбирать до получения реальных URL и возможностей storage/CDN.

# 23. Дополнительный аудит: схема ключей и Woo availability

Сущностей pool/key/assignment, отдельных таблиц, key meta, import handlers и истории операций не найдено. Следовательно, мигрировать существующий склад не требуется, но данные поставщика/ключи могли храниться вне переданной системы — это нужно подтвердить организационно.

Для individual keys базовая предлагаемая схема остаётся: отдельная строка ключа, уникальный fingerprint/constraint, status, pool, assignment и immutable event log. Полное значение не выводится в общих списках, logs, JS или публичном REST. Доступ к reveal/import/export/assign/resend проверяется capability на сервере; nonce используется дополнительно, а не вместо authorization.

Reusable keys меняют cardinality и concurrency: нужен атомарный счётчик `used < allowed_uses`, иной расчёт remaining, иные duplicate/import semantics и журнал каждой выдачи. Это не является небольшим переключателем существующей модели и имеет статус `CHANGE_REQUEST`.

Текущая покупаемость товаров определяется стандартным WooCommerce stock/status и product type. Именно это сейчас блокирует часть Windows-карточек. Рекомендуемый путь для управляемых режимов — максимально использовать штатные `manage_stock`, `stock_status`, backorders/purchasability и только синхронизировать их с pool policy. Параллельную независимую availability-систему создавать не следует. `CONTINUE_SALES/STOP_SALES` и ручной toggle остаются `CHANGE_REQUEST` до определения правил при cart race, quantity, shared pool и уже открытом checkout.

# 24. Дополнительный аудит: email, SMTP и DNS

- В поставке нет SMTP plugin и project-level SMTP transport.
- WordPress/WooCommerce могут использовать стандартный `wp_mail`, но способ доставки и его работоспособность без runtime/options/mail logs не подтверждены.
- Provider, sending domain, envelope-from, bounce handling, SPF, DKIM, DMARC и DNS owner неизвестны.
- Существующий локальный `mail.log` не заменяет проверку production transport/delivery и не используется как источник секретов.

До любых DNS-изменений нужен read-only lookup/доступ, точный перечень записей от выбранного provider и подтверждение владельца домена. Затем отдельно фиксируются `sent`, provider accepted/delivered, inbox/spam и ограничения. DNS не изменяется в ходе аудита.

# 25. Дополнительный аудит: anti-bot и формы

| Поверхность | Nonce/CSRF | CAPTCHA | Rate limiting | Spam protection | Итог |
|---|---|---|---|---|---|
| Login | WordPress auth flow; runtime hardening не проверен | Не найдено | Limit Login Attempts Reloaded установлен и публично выглядит активным; настройки/IP detection неизвестны | Нет отдельного подтверждения | Частично, `NEEDS_AUDIT` |
| Registration | Woo/WordPress flow; настройки account creation неизвестны | Не найдено | Не подтверждено | Не подтверждено | `NEEDS_AUDIT` |
| Password Reset | Woo/WordPress reset token flow; runtime не проверен | Не найдено | Не подтверждено | Не применимо/не подтверждено | `NEEDS_AUDIT` |
| Checkout | WooCommerce server flow и его request tokens; classic/Blocks вариант не подтверждён | Не найдено | Не подтверждено | Не подтверждено | `NEEDS_AUDIT` |
| Comments/reviews | WordPress comments; custom AJAX pagination имеет nonce, но это только чтение | Не найдено | Не подтверждено | Akismet установлен и публично выглядит активным; API/config неизвестны | Частично |
| Custom Telegram webhook | Нет достаточной authentication: публичный endpoint | Не применимо | Не найдено | Не применимо | Critical defect |

Nonce защищает от CSRF, но не является CAPTCHA или достаточным rate limit. Автоматически устанавливать security plugin нельзя. Варианты с плюсами, минусами, стоимостью и внешними зависимостями перечислены в `DEPENDENCIES.md`.

# 26. Артефакты аудита и границы результата

Созданы/обновлены:

- `AUDIT.md` — фактическое состояние и технические выводы;
- `PRE_EXISTING_ISSUES.md` — baseline исходных дефектов;
- `IMPLEMENTATION_PLAN.md` — основной план и статусная матрица дополнительных требований;
- `DEPENDENCIES.md` — технические/коммерческие зависимости и блокеры;
- `SEARCH_TEST_CASES.md` — 40 контрольных запросов для согласования;
- `MOBILE_TEST_MATRIX.md` — Android/iOS/breakpoint baseline;
- `TEST_PROTOCOL.md` — TC-01…TC-20 и инварианты;
- `DELIVERY.md` — шаблон передачи release;
- `ACCEPTANCE_CRITERIA.md` — порядок итерационной приёмки и ручных проверок.

Никакой функциональный код, конфигурация сайта, БД, DNS или внешние сервисы на этом этапе не изменялись. Следующий шаг возможен только после явного утверждения пунктов к реализации.

