# Логическая итерация 4 — заказы, выдача, коммуникации и платёжный контур

Дата: 10 октября 2026 года.

Статус: **ГОТОВО К ОТЛОЖЕННОЙ РУЧНОЙ ПРОВЕРКЕ**. Реальный тест ЮKassa заблокирован только внешними реквизитами и публичным HTTPS staging.

Точная маркировка платежей вынесена в `PAYMENT_TEST_STATUS.md`: локальный mock — `PASS`, YooKassa test E2E — `NOT TESTED`, боевой платёж — `NOT TESTED`.

## Реализовано

### Модель заказа

- Независимые статусы оплаты и выдачи: `pending_payment`, `waiting_key`, `partially_issued`, `issued`.
- Статус хранится на позиции и агрегируется на заказ.
- Публичный неизменяемый 10-значный номер с unique constraint.
- Номер применяется через стандартный WooCommerce order-number filter в admin, email, thank-you и My Account.
- HPOS/CPT-поиск по публичному номеру и товару; штатный HPOS-поиск сохраняет email и transaction ID.
- Колонка «Выдача ключа» в списке заказов.

### Ручная и автоматическая выдача

- В заказе появился metabox «Лицензионные ключи».
- Кнопка назначает только недостающие ключи и доступна после подтверждённой оплаты.
- Принято правило: одна купленная единица mapped product = один individual key.
- При quantity > 1 назначается по одному ключу на каждый unit.
- При пустом пуле оплаченный заказ остаётся `waiting_key`.
- Mapped products не блокируются стандартным Woo stock: ключи могут закупаться после продажи.
- AUTO запускается только на стандартном `woocommerce_payment_complete` и имеет отдельный глобальный выключатель.
- Выключатель по умолчанию `OFF`; production нельзя включать до YooKassa E2E.
- Повтор `payment_complete` не создаёт новое назначение и не уменьшает остаток повторно.

### Конкурентность

- Claim выполняется в DB transaction с блокирующим row lock: ожидающий конкурент получает следующий свободный ключ после commit либо корректный empty после исчерпания пула.
- Unique constraints защищают physical key и `(order_item_id, unit_index)`.
- Реальный тест запускал 20 независимых WP/PHP процессов с отдельными DB connections на один ключ.
- Итог: `1 won`, `19 empty`, одна assignment, один assigned key, один success audit event.

### Email и My Account

- Добавлен отдельный `WC_Email` «ITSOFT — лицензионные ключи» с HTML/plain шаблонами.
- Письмо содержит только фактически назначенные заказу ключи.
- Email status и last attempt сохраняются в order meta.
- В заказе можно исправить billing email и повторно отправить те же ключи.
- Resend не вызывает allocation path и не списывает новый ключ.
- В My Account полный ключ видит только авторизованный владелец заказа.
- Другой пользователь и гость ключ не получают.
- Локальные письма доставлены в Mailpit.

### Остатки и уведомления

- Переход к остатку `1` создаёт одно событие `inventory.low` и одно email-уведомление.
- Переход к `0` создаёт одно событие `inventory.empty` и одно email-уведомление.
- Повторные действия на том же уровне не создают спам.
- После пополнения уровень сбрасывается и следующий переход снова уведомит администратора.

### ЮKassa

- С WordPress.org установлен официальный plugin `yookassa` версии `2.17.3`, издатель `yoomoney`.
- Plugin активирован только локально.
- Зарегистрированы gateway IDs `yookassa_widget`, `yookassa_b2b_sberbank`, `yookassa_electronic_certificate`, `yookassa_sber_bnpl`.
- Все YooKassa gateways выключены, credentials отсутствуют, debug-log выключен.
- Локальный WordPress блокирует внешние HTTP-запросы.
- Проверен код plugin: успешный YooKassa payment вызывает `$order->payment_complete($payment_id)`, то есть подключается к реализованному AUTO orchestration без отдельного небезопасного webhook-кода.

## Версии и база

- `itsoft-license-fulfillment`: `0.6.0`.
- Schema: `1.2.0`.
- Новая таблица: `wpnr_itsoft_license_order_numbers`.
- В `wpnr_itsoft_license_pools` добавлено поле `notification_level`.
- Assignments используют ранее созданную таблицу с unique keys.
- Order/item meta имеют префикс `_itsoft_lf_`.

## Автоматические проверки

| Проверка | Результат |
|---|---|
| PHP lint plugin/scripts/templates | PASS |
| Повторная migration 1.2.0 | PASS |
| 10-значный номер и уникальность | PASS |
| Unpaid → pending payment | PASS |
| Paid → waiting key | PASS |
| MANUAL quantity 2 → два разных key | PASS |
| Повтор ручного действия | PASS, 0 новых |
| AUTO после payment_complete | PASS |
| Повтор payment callback | PASS, 0 новых |
| Пустой пул | PASS, waiting key |
| Mapped product при Woo stock=0 | PASS, purchasable/backorder path |
| 20 процессов / 1 ключ | PASS, ровно 1 assignment |
| Custom email в Mailpit | PASS |
| Исправление email + resend | PASS, assignment count неизменен |
| My Account owner | PASS, ключ виден |
| Другой пользователь | PASS, ключ не виден |
| Уведомления 1/0 | PASS, по одному |
| HPOS search number/product | PASS |
| HPOS admin list/order edit/settings | PASS, HTTP 200 |
| YooKassa plugin 2.17.3 active locally | PASS |

Все test orders, products, users, pools, assignments и concurrency fixtures удалены после проверок. Автовыдача возвращена в `OFF`.

## Отложенная ручная проверка

Её можно выполнить позже одним блоком:

1. Связать тестовый товар с пулом и выбрать `MANUAL`.
2. Создать/отметить локальный заказ оплаченным — статус выдачи `Ожидает ключ`.
3. Нажать «Назначить недостающие ключи» — статус `Ключ выдан`, остаток уменьшается.
4. Повторить — новый ключ не списывается.
5. Проверить письмо в `http://localhost:8025`.
6. Исправить email в metabox и resend — приходит тот же ключ.
7. Войти владельцем заказа — ключ виден в My Account; другим аккаунтом — не виден.
8. Проверить поиск заказа по 10-значному номеру и названию товара.
9. Для товара AUTO включить глобальную настройку только в локальной среде и повторить payment-complete test.

## Что нужно для настоящего YooKassa E2E

1. Тестовый магазин ЮKassa.
2. Его `shopId` и secret key — вводить непосредственно в защищённой admin-форме, не отправлять в чат/файл проекта.
3. Публичный staging-домен с HTTPS: localhost недоступен для HTTP notifications ЮKassa.
4. Решения по чекам: юридический статус, система налогообложения, ставка НДС и используемая онлайн-касса/«Чеки от ЮKassa».
5. После тестов: проверить success/cancel/duplicate callback и только затем включить `AUTO`.

Официальные страницы: plugin WordPress.org и инструкция ЮKassa для WooCommerce указаны в итоговом сообщении текущей итерации.

## Безопасные правила

- Настоящий secret key не сохраняется в Git, отчётах или сообщениях.
- YooKassa debug-log остаётся выключенным, кроме краткой диагностики на staging с последующим удалением.
- Возврат/отмена уже выданного ключа не возвращает individual key в пул автоматически.
- AUTO не включается до подтверждённого тестового callback.

## Rollback

- Выключить AUTO в **Склад ключей → Автоматическая выдача**.
- Деактивировать `itsoft-license-fulfillment`: данные сохраняются, выдача прекращается.
- Деактивировать `yookassa`: YooKassa checkout/callback прекращается, заказы сохраняются.
- Schema downgrade и удаление assignment/order-number rows автоматически не выполняются; для production требуется backup.
