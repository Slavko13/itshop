# Реестр исходных проблем ITSOFT

Дата фиксации baseline: 9 октября 2026 года.

Этот файл отделяет дефекты, существовавшие до начала разработки, от будущих изменений. Код проекта в ходе аудита не менялся.

## 1. Defects existed before development

| ID | Описание | Воспроизведение / доказательство | Область | Влияние на новое ТЗ | Предлагаемое решение | Приоритет |
|---|---|---|---|---|---|---|
| PRE-01 | Telegram token и пароль подписки зашиты в теме | Просмотр конфигурации Telegram в теме; значения намеренно не публикуются | Security / Telegram | Прямой риск компрометации и утечки заказов | Немедленно ротировать, вынести в secret storage, проверить журналы | Critical |
| PRE-02 | Telegram webhook публичный, принимает GET/POST и не проверяет secret header | Статический аудит `telegram-bot/Webhook.php` | Security / API | Возможны злоупотребления и повторная обработка | POST-only, secret header, rate limiting, идемпотентность | Critical |
| PRE-03 | Telegram-вызов синхронный, содержит повторы и `sleep` в web request | Статический аудит API-клиента темы | Checkout / performance | Может задерживать заказ и дублировать внешние действия | Перенести доставку в Action Scheduler с dedupe | High |
| PRE-04 | Часть Windows-товаров недоступна для покупки из-за Woo stock | Публичные карточки показывают «нет в наличии» и не позволяют добавить товар | Product / cart | Блокирует основной покупательский сценарий | После решения о sale mode согласовать Woo stock и склад ключей | High |
| PRE-05 | Поиск не находит русские варианты, транслитерацию и слитные формы | `Виндовс`, `Виндоус`, `Винда`, `Windows11`, `Офис` возвращают 0; `Windows`, `Microsoft`, `Office` работают | Search | Не выполнены требования поиска | Индекс с синонимами/нормализацией и согласованный набор из 30+ запросов | High |
| PRE-06 | Два WooCommerce template override устарели | `product-image.php` 9.7 против core 10.5; `variation-add-to-cart-button.php` 10.2 против core 10.5.2 | Woo templates | Риск регрессий карточек/вариаций | Diff с актуальными шаблонами и regression test | High |
| PRE-07 | Прямые постоянные ссылки дистрибутивов публикуются в HTML | ACF `links[].link` типа URL выводится как `<a href>` в `single-distribution.php` | Downloads / security | Не поддерживает TTL и отзыв ссылок | Не менять до решения; выбрать storage-aware схему после доступа к данным | High |
| PRE-08 | В локальной поставке нет файлов дистрибутивов и дампа с ACF values | Поиск архивов/инсталляторов в uploads не дал результатов; БД отсутствует | Downloads | Нельзя установить фактический storage/provider и проверить ссылки | Получить обезличенный DB dump или WP Admin/export записей | High |
| PRE-09 | Отсутствует текущий payment gateway module | В plugin-каталогах gateway нет; Store API production показывает только `cod`; старый YooKassa log не подтверждает работу | Payment | Нельзя связать выдачу с достоверным paid event | Получить доступ к admin/provider и принять решение по gateway | Critical |
| PRE-10 | Нет подтверждённого SMTP | SMTP plugin отсутствует; provider/DNS/options неизвестны без БД и внешних доступов | Email | Нельзя гарантировать доставку ключей | Аудит provider, transport, журналов и DNS; test delivery | High |
| PRE-11 | Нет CAPTCHA/Turnstile для критических форм в поставке | Plugin и custom hooks не найдены | Login / registration / reset / checkout | Повышает риск bot abuse | Сначала проверить WAF/CDN и активные настройки; затем выбрать вариант | High |
| PRE-12 | Защита ограничена отдельными компонентами | Limit Login Attempts и Akismet присутствуют; их DB-конфигурация неизвестна | Security | Login частично защищён, checkout/registration/reset — не подтверждены | Проверить активацию, настройки, proxy/IP detection и покрытие форм | High |
| PRE-13 | Нет Git, CI и воспроизводимой среды | В поставке отсутствуют Git metadata, тестовый каркас и Docker-конфигурация | Delivery | Нет надёжного diff, rollback и автоматической регрессии | Iteration 0: Git baseline, Docker, staging, backup/restore | Critical |
| PRE-14 | Тема заявлена tested up to WordPress 6.5 при core 7.1.3 | Заголовок темы и `wp-includes/version.php` | Compatibility | Возможны скрытые несовместимости | Проверить staging smoke/regression и обновить metadata после подтверждения | Medium |
| PRE-15 | Нет склада ключей и журнала выдач | В коде/таблицах поставки соответствующая реализация не найдена | Fulfillment | Основная функция проекта отсутствует | Реализовать отдельным custom plugin после утверждения модели | Critical |
| PRE-16 | Mobile-дефекты не имеют воспроизводимого baseline | Есть подозрительное условие открытия mobile menu, но нет device evidence | Mobile UX | Нельзя точно оценить исправление и регрессию | Заполнить `MOBILE_TEST_MATRIX.md` на реальных устройствах/staging | Medium |
| PRE-17 | ACF 6.1.8 вызывает early textdomain notice на WordPress 7.1.3 | Воспроизводится локально после активации ACF; `_load_textdomain_just_in_time` | Plugin compatibility/logs | Загрязняет diagnostics, может скрывать более важные события | Проверить лицензию и обновление ACF на staging с регрессией полей | Medium |

## 2. Defects introduced by our changes

На дату baseline: **нет**. Разработка не начиналась. Новые дефекты после каждой итерации добавляются сюда с commit/build, шагами воспроизведения и ответственным исправлением.

## 3. Required technical changes

- Создать воспроизводимую локальную среду и staging; проверить backup/restore.
- Ротировать и вынести секреты из исходников.
- Создать отдельный custom plugin для доменной логики ключей.
- Ввести versioned migrations, capability model, audit trail и атомарное назначение.
- Подтвердить payment lifecycle, HPOS, Action Scheduler, SMTP и активные настройки WooCommerce.
- Устранить устаревшие Woo overrides с обязательной регрессией.
- Ввести автоматические проверки и формальный test protocol.

## 4. New functionality

- Пулы и индивидуальные ключи, импорт, связь pool ↔ SKU.
- AUTO/MANUAL выдача, `waiting_key`, resend и My Account.
- 10-значный публичный номер заказа и расширенный admin search.
- Улучшенный поиск, cart/checkout UX и уведомления.
- Повторно используемые ключи, управляемые sale modes и временные download URL — только после отдельного подтверждения как change requests.

## Правило обновления

Исходный дефект не переносится в раздел «introduced by our changes» только потому, что его обнаружили позже. Для классификации используются baseline, воспроизводимость на неизменённой копии и история изменений.

## 5. Статус устранения исходных проблем

| ID | Локальный статус на 10.10.2026 | Остаток |
|---|---|---|
| PRE-04 | Репрезентативный mapped Windows SKU покупаем и проходит cart/mock checkout | Политика всех SKU и production inventory остаётся ручной проверкой |
| PRE-05 | Исправлено; search families S01–S40 проходят, кроме N/A отсутствующих SKU | Ручная оценка коммерческой релевантности |
| PRE-09 | Официальный YooKassa plugin установлен, локальный mock E2E проходит | Реальный test merchant/staging E2E не выполнен |
| PRE-15 | Исправлено custom plugin 0.10.0, schema 1.2.0 | Production migration dry run |
| PRE-16 | Дефекты menu/article воспроизведены и исправлены; 320–1024 browser tests PASS | Physical Android/iOS evidence |
| PRE-17 | Воспроизводится; аналогичный notice обнаружен у Curio SEO | Обновление совместимых коммерческих plugins на staging |
