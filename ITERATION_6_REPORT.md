# Логическая итерация 6 — checkout и корзина

Дата: 10 октября 2026 года.

Статус: **ГОТОВО К ОТЛОЖЕННОЙ РУЧНОЙ DESKTOP/MOBILE ПРОВЕРКЕ**.

## Checkout

- Guest checkout включён и подтверждён тестом.
- Для classic checkout и Checkout Blocks добавлено обязательное поле «Повторите e-mail».
- Формат, trim/lowercase normalization и совпадение проверяются на сервере.
- Несовпадение в реальном Store API запросе возвращает HTTP `400` с кодом `itsoft_checkout_email_mismatch`.
- Повторный адрес после проверки не хранится в order meta как лишняя копия PII.
- Для типовых ошибок `gamil.com`, `mail.ry`, `yandex.ry` и аналогов выводится неблокирующая кликабельная подсказка.
- Checkout Blocks реально загружает mock payment adapter и checkout email assets.

## Корзина

- Установлено, что тема фактически выводит classic shortcode cart через `page-cart.php`, даже если post content содержит Cart Block.
- В реальную classic cart добавлены кнопки `− / количество / +`.
- Изменения автоматически отправляются через штатный WooCommerce AJAX с debounce 350 мс.
- Правило количества: одна единица mapped product = один ключ.
- Переход `2 → 1` сохраняет позицию; разрешённый WooCommerce переход `1 → 0` удаляет позицию.
- Product #331 связан локально с явно помеченным пустым MANUAL-пулом #15 и остаётся покупаемым при нулевом остатке ключей.

## Автоматические результаты

| Проверка | Результат |
|---|---|
| Guest checkout option | PASS |
| Classic repeat-email field | PASS |
| Blocks repeat-email field | PASS |
| Invalid email rejected | PASS |
| Mismatching email rejected | PASS |
| Matching normalized email | PASS |
| Windows empty-pool add-to-cart | PASS |
| Quantity `1 → 2 → 1` | PASS |
| Plus/minus HTML + assets over HTTP | PASS |
| Explicit zero removal | PASS |
| Full Store API checkout + mock payment | PASS |

## Минимальная ручная проверка

1. Открыть `http://localhost:8080/product/windows-11-enterprise-2024-ltsc-korporativnaya/` и добавить товар в корзину.
2. В корзине нажать `+`, затем `−`: сумма меняется автоматически, при `2 → 1` строка остаётся.
3. Перейти в checkout как гость.
4. Ввести `buyer@gamil.com` — должна появиться подсказка `buyer@gmail.com`; нажать её.
5. В повторном поле ввести другой адрес — оформление должно быть остановлено.
6. Исправить повторный адрес, выбрать «Тестовая оплата (без денег)» и завершить заказ.
7. В admin убедиться, что заказ оплачен и имеет выдачу `Ожидает ключ`.
8. Повторить пункты 1–6 на ширине телефона.

## Осталось за рамками этой итерации

- `Купить сейчас`/buy-now не реализован: сначала нужно утвердить, должен ли он пропускать корзину или только добавлять товар и открывать её.
- Полная проверка настоящей ЮKassa выполняется только на staging.
- Финальная visual/mobile приёмка остаётся за пользователем по указанным восьми шагам.
