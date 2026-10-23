# Статус проверки платежей ITSOFT

Последнее обновление: 23 октября 2026 года.

## Краткий статус

| Контур | Статус | Что это означает |
|---|---|---|
| Локальная mock-оплата | `IMPLEMENTED / E2E PASS` | Денег и внешних запросов нет; успешно пройден реальный Blocks/Store API checkout и WooCommerce вызывает настоящий внутренний `payment_complete` |
| Fulfillment после оплаты | `AUTOMATED TEST PASS` | AUTO назначает один ключ, повтор события не списывает второй, пустой пул остаётся `waiting_key` |
| Официальный plugin ЮKassa | `INSTALLED LOCALLY` | Версия 2.17.3, gateways выключены, credentials отсутствуют |
| HTTPS staging | `IMPLEMENTED / SERVER CHECK PENDING` | Добавлены Caddy, автоматический сертификат, proxy-aware WordPress и миграция URL; нужен DNS тестового домена |
| Установка ЮKassa на staging | `IMPLEMENTED / SERVER CHECK PENDING` | Скрипт активирует официальный plugin и разрешает только необходимые внешние хосты |
| Тестовый платёж ЮKassa | `NOT TESTED` | Нет test shopId/secret и публичного HTTPS staging callback |
| Боевой платёж | `NOT TESTED / NOT REQUIRED YET` | Реальные деньги не использовались |

## Ограничения mock

Mock подтверждает нашу внутреннюю логику WooCommerce: заказ, `payment_complete`, transaction ID, статусы, атомарную выдачу, email и идемпотентность. Он не подтверждает:

- создание платежа через API ЮKassa;
- форму/redirect и тестовую карту;
- подпись и доставку HTTP notification;
- реальные `payment.succeeded` / `payment.canceled`;
- retry со стороны ЮKassa;
- настройку чеков и онлайн-кассы;
- работу DNS/HTTPS/firewall staging.

Поэтому mock PASS нельзя переименовывать в YooKassa PASS.

## Выполненная локальная E2E-проверка

- Несовпадающие основной и повторный e-mail остановили checkout с HTTP `400` и кодом `itsoft_checkout_email_mismatch`.
- Повторный запрос с совпадающими адресами создал заказ через WooCommerce Store API.
- Gateway `itsoft_lf_mock` вернул payment status `success`; заказ перешёл в `processing`.
- Transaction ID получил явный префикс `MOCK-`.
- Товар с намеренно пустым MANUAL-пулом остался в корректном состоянии `waiting_key`.
- Повторный e-mail после проверки удалён из order meta и не хранится как лишняя копия PII.
- Созданный E2E-заказ удалён после проверки.

## Защита от production

- Gateway `itsoft_lf_mock` регистрируется только при `WP_ENVIRONMENT_TYPE=local`.
- В staging/production класс оплаты недоступен независимо от настройки WooCommerce.
- Mock не использует API, токены, карты или внешние URL.
- AUTO остаётся выключенным после автоматических тестов.

## Что закрывает будущий YooKassa E2E

После появления тестового магазина и HTTPS staging выполнить: success, cancel/fail, закрытие браузера до возврата, duplicate callback, empty pool, два заказа/один ключ и письмо. Только после этого статус меняется на `YOOKASSA TEST PASS`.
