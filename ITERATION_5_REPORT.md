# Логическая итерация 5 — локальная оплата и сквозной paid lifecycle

Дата: 10 октября 2026 года.

Статус: **ЛОКАЛЬНЫЙ КОНТУР ГОТОВ К РУЧНОЙ ПРИЁМКЕ**. Тест ЮKassa остаётся отдельно помеченным `NOT TESTED`.

## Что сделано

- Добавлен local-only gateway `itsoft_lf_mock` с названием «Тестовая оплата (без денег)».
- Gateway недоступен при любом `WP_ENVIRONMENT_TYPE`, кроме `local`.
- Поддержаны classic checkout и WooCommerce Checkout Blocks.
- Исправлен порядок регистрации Blocks adapter; метод подтверждён в PaymentMethodRegistry.
- Mock использует настоящий `$order->payment_complete()`, создаёт transaction ID `MOCK-*` и не вызывает внешние API.
- AUTO-путь выдаёт один ключ; повторный callback остаётся идемпотентным.
- Пустой MANUAL-пул после оплаты корректно оставляет заказ в `waiting_key`.

## Автоматические результаты

| Проверка | Результат |
|---|---|
| PHP lint plugin | PASS |
| Classic gateway registration | PASS |
| Blocks gateway registration | PASS |
| Direct gateway smoke | PASS |
| Woo Store API checkout | PASS |
| Paid status + `MOCK-*` transaction | PASS |
| AUTO one-key issue | PASS |
| Duplicate callback | PASS, второй ключ не выдан |
| Empty MANUAL pool | PASS, `waiting_key` |

## Что намеренно не проверено

- test shopId/secret ЮKassa;
- redirect/виджет и тестовая карта ЮKassa;
- HTTPS notification `payment.succeeded`;
- provider retries, отмена и refund;
- чеки/онлайн-касса.

Эти пункты нельзя считать пройденными по результату mock. Полная маркировка находится в `PAYMENT_TEST_STATUS.md`.

## Минимальная ручная проверка

1. Открыть товар и добавить его в корзину.
2. На checkout выбрать «Тестовая оплата (без денег)».
3. Завершить заказ — банковская карта и реальные деньги не нужны.
4. В admin проверить: order status `processing`, transaction ID начинается с `MOCK-`.
5. Для пустого MANUAL-пула проверить статус выдачи `Ожидает ключ`.
6. Для AUTO-пула с тестовым ключом проверить выдачу одного ключа и письмо в Mailpit.

## Блокер настоящей ЮKassa

Нужны публичный HTTPS staging и test shopId/secret из кабинета ЮKassa. Secret вводится только в защищённой admin-форме и не передаётся в чат или файлы проекта.
