# План реализации ITSOFT

Основание: `AUDIT.md` и техническое задание от 8 октября 2026 года.

Текущий прогресс на 11 октября 2026 года:

- логическая итерация 1: локальный Docker и security baseline — готово к ручной приёмке;
- логическая итерация 2: обезличенная production DB, schema foundation, encrypted individual key storage и базовый admin import — принято;
- логическая итерация 3: связь product/variation → pool, общий пул для нескольких SKU и режимы AUTO/MANUAL — готово к ручной приёмке;
- логическая итерация 4: order lifecycle, ручная/AUTO-выдача, concurrency, email/resend, My Account и локальный модуль ЮKassa — готово; реальный test-payment ожидает staging и тестовые реквизиты;
- логическая итерация 5: локальная mock-оплата через classic/Blocks, сквозной paid lifecycle и регрессия коммуникаций — готово; не заменяет YooKassa test E2E;
- логическая итерация 6: guest checkout, проверка/повтор e-mail, подсказки доменных опечаток, Windows cart и AJAX quantity controls — готово к ручной desktop/mobile проверке; buy-now остаётся отдельным решением;
- логическая итерация 7A: поиск и выдача результатов — готово; S01–S40 пройдены, отсутствующие в каталоге Microsoft 365 и SQL Server помечены N/A;
- логические итерации 7B–7D: каталог, mobile menu, статьи, cookie и account — локально готовы; physical Android/iOS остаются ручной приёмкой;
- локальная часть итерации 8: favicon/canonical/sitemap/robots и полный regression — готово; внешние Webmaster/Jivo/SMTP/ЮKassa не проверены;
- extra-итерация H: вход, регистрация, восстановление пароля, обзор кабинета, заказы, ключи, профиль и адреса — реализовано и готово к ручной приёмке;
- extra-итерация I: каталог перенесён к логотипу, поиск стал центральным элементом header, пустая корзина получила самостоятельное адаптивное состояние — реализовано и готово к ручной приёмке;
- extra-итерация J: главная, каталог, категории и единая карточка товара переделаны в общем стиле; adaptive/no-JS/AJAX/regression проверки пройдены — реализовано локально и готово к ручной приёмке;
- production deployment не выполнялся.

Статусы текущей реализации в исходной матрице ниже:

- `READY` — требование уже выполнено и подтверждено;
- `PARTIAL` — есть только часть сценария;
- `MISSING` — реализации нет;
- `BROKEN` — текущее поведение противоречит ТЗ или подтверждённо не работает;
- `NEEDS_INVESTIGATION` — без БД/админки/внешнего сервиса нельзя сделать достоверный вывод.

Статусы согласования дополнительных требований заказчика:

- `IN_SCOPE` — входит в текущий объём и может быть реализовано после запуска соответствующей итерации;
- `NEEDS_AUDIT` — решение зависит от недостающего технического подтверждения;
- `CHANGE_REQUEST` — расширяет первоначальное ТЗ и требует отдельного подтверждения;
- `EXTERNAL_DEPENDENCY` — зависит от внешнего сервиса/инфраструктуры;
- `BLOCKED` — невозможно оценить или выполнить зависимую часть без доступа/решения.

Статусы двух матриц имеют разный смысл и не взаимозаменяются. Требования `CHANGE_REQUEST`, `NEEDS_AUDIT` и `BLOCKED` не реализуются без отдельного подтверждения.

## Матрица требований

| ID | Требование | Сейчас | Что нужно изменить | Основные файлы/компоненты | Риск | Итерация |
|---|---|---|---|---|---|---:|
| AUD-01 | Backup, staging, rollback | MISSING | Создать проверенный backup БД+files, staging, Git и restore test | инфраструктура, БД | Critical | 0 |
| SEC-01 | Секреты Telegram | BROKEN | Ротировать token/password, вынести в secret config, очистить небезопасные логи | `functions.php`, `telegram-bot/*`, config | Critical | 0 |
| AUD-02 | Active plugins/options/HPOS | NEEDS_INVESTIGATION | Снять Woo system report, options и schema inventory | WP Admin, БД | High | 0 |
| PAY-01 | Фактический payment callback | PARTIAL | Локальный mock E2E подтверждён; официальный YooKassa test E2E требует staging и test credentials | payment plugin, order hooks | Critical | 0/4 |
| KEY-01 | Сущность пула | MISSING | Добавить versioned schema и repository | новый custom plugin, custom tables | High | 1 |
| KEY-02 | Сущность физического ключа и статусы | MISSING | Хранить value, status, actor, timestamps, order/item relation | новый plugin, custom tables | Critical | 1 |
| KEY-03 | Уникальность/дубликаты | MISSING | Нормализация и unique fingerprint/constraint | migration, import service | Critical | 1 |
| KEY-04 | Журнал операций | MISSING | Immutable event log с result/error/timestamp | log table/service | High | 1 |
| KEY-05 | Шифрование/маскирование | NEEDS_INVESTIGATION | Реализовать выбранную после решения модель | key repository/admin UI | Critical | 1 |
| ADM-01 | Раздел «Склад ключей» | MISSING | List tables, counters, filters, safe reveal | admin controllers/views | High | 2 |
| ADM-02 | Добавление одного ключа | MISSING | Capability, nonce, validation, audit event | admin action/service | High | 2 |
| ADM-03 | Массовая вставка | MISSING | Preview, dedupe, import result | importer/admin UI | High | 2 |
| ADM-04 | TXT/CSV import | MISSING | Streaming parser, validation, report | importer | Medium | 2 |
| ADM-05 | XLSX import | MISSING | Сначала проверить допустимость зависимости; реализовать отдельным adapter | importer | Medium | 2 |
| MAP-01 | Связь pool ↔ несколько SKU | READY | Mapping table и product data UI; variation override/inheritance | schema, Woo product panel | Critical | 2 |
| MAP-02 | AUTO/MANUAL у товара | READY | Режим хранится в mapping; AUTO не запускается до payment iteration | product data hooks | High | 2 |
| MAN-01 | Waiting key после оплаты | READY | Issue status отдельно от payment status | order/item meta/service | Critical | 3 |
| MAN-02 | Ручная выдача | READY | Order action с ownership, audit и idempotency | admin order UI, service | Critical | 3 |
| MAN-03 | Продажа без заранее закупленного ключа | READY | Для mapped products Woo stock не блокирует продажу; один key на unit | product/cart validation | High | 3 |
| AUTO-01 | Автовыдача после оплаты | PARTIAL | Готово на Woo `payment_complete`, default OFF; нужен YooKassa E2E | payment adapter/service | Critical | 4 |
| AUTO-02 | 0 ключей → waiting key | READY | Paid order сохраняется в waiting key, admin alert | service/status/notifications | Critical | 4 |
| AUTO-03 | Idempotency повторного callback | READY | Unique assignment и повторяемый safe handler | DB constraints/service | Critical | 4 |
| RACE-01 | Два заказа, один ключ | READY | Atomic claim + 20-process concurrency integration test | repository/DB/tests | Critical | 4 |
| RACE-02 | Общий пул нескольких SKU | READY | Claim выполняется на уровне pool | repository/DB/tests | Critical | 4 |
| MAIL-01 | Email с фактически выданным ключом | READY | Custom `WC_Email`, escaping и delivery state | email class/templates | High | 5 |
| MAIL-02 | Повторная отправка того же ключа | READY | Order action без allocation path | admin action/email service | Critical | 5 |
| MAIL-03 | Исправление email | READY | Billing email сохраняется перед resend existing assignment | order admin/email service | High | 5 |
| MAIL-04 | SMTP/deliverability | NEEDS_INVESTIGATION | Настроить SMTP, test mailbox, SPF/DKIM/DMARC checks | external mail/WP config | High | 5 |
| NOTIF-01 | Остался 1/0 ключей | READY | Edge-triggered события с dedupe и email admin | inventory events | Medium | 5 |
| NOTIF-02 | Manual/error admin alerts | PARTIAL | Заменить generic new-order Telegram на domain events + email fallback | `telegram-bot/*` → plugin adapter | High | 5 |
| NOTIF-03 | Безопасный Telegram webhook | BROKEN | Secret header validation, POST only, rate protection, async sending | Telegram adapter/webhook | Critical | 5 |
| ORD-01 | Payment и issue statuses отдельно | READY | Order item state + aggregate admin display | order services/admin UI | High | 3 |
| ORD-02 | 10-значный публичный номер | READY | Unique table constraint, immutable meta, presentation filter | order service/hooks | High | 3 |
| ORD-03 | Единый номер во всех каналах | PARTIAL | Woo filter покрывает UI/email/account; gateway E2E ещё не выполнен | templates/filters/adapter | High | 3/5 |
| ORD-04 | Поиск по номеру/email/transaction/product | READY | HPOS/CPT-compatible search и admin column | admin orders integration | High | 3 |
| ACC-01 | Заказы и ключ в My Account | READY | Ownership-checked key view, statuses/public number | account endpoint/template | Critical | 5 |
| ACC-02 | Password/logout/mobile registration bugs | READY | Формы и responsive layout проверены; create/auth/reset/logout smoke покрыт тестом | Woo account/theme/tests | Medium | 5/7 |
| ACC-03 | Magic link/OTP | NEEDS_INVESTIGATION | Отдельное решение после оценки безопасности/трудозатрат | auth adapter | High | 5 |
| CHK-01 | Guest checkout | READY | Option подтверждён и покрыт regression test | Woo settings/tests | High | 6 |
| CHK-02 | Email format и очистка | READY | Единая classic/Blocks server validation и normalization | checkout hooks | Medium | 6 |
| CHK-03 | Повтор email | READY | Обязательное поле и server-side сравнение для classic/Blocks | checkout UI/server | Medium | 6 |
| CHK-04 | Подсказки опечаток домена | READY | Неблокирующая кликабельная подсказка типовых доменов | checkout UI/server | Low | 6 |
| CART-01 | Windows добавляются в корзину | READY | Mapped empty-pool Windows SKU проверен через Store API и HTTP cart | product/cart services | High | 6 |
| CART-02 | `- / quantity / +` в cart | READY | Контролы добавлены в фактически используемую classic cart page; один key на unit | cart plugin JS/CSS | High | 6 |
| CART-03 | Auto-update и уменьшение без удаления всей позиции | READY | Debounced штатный Woo AJAX; `2→1` сохраняет позицию, разрешённый `1→0` удаляет | cart JS | Medium | 6 |
| CART-04 | Кнопка «Купить» | MISSING | Согласовать semantics и добавить buy-now flow | product/cart JS | Medium | 6 |
| SRCH-01 | Поле синонимов | READY | ACF-independent product meta и поле в карточке товара | search plugin module | Medium | 7 |
| SRCH-02 | RU/EN/translit/layout/typos/partial | READY | Canonical aliases, joined tokens, keyboard variants и bounded fuzzy matching | search service/query hooks | High | 7 |
| SRCH-03 | Ранжирование и товары выше статей | READY | Weighted exact/prefix/fuzzy score, numeric precision и product-first result template | search query/template | High | 7 |
| CAT-01 | Порядок Office и связанных товаров | PARTIAL | Проверить taxonomy order/plugin settings и зафиксировать порядок | catalog/order settings | Medium | 7 |
| CAT-02 | SEO-текст под товарами, «Назад» | READY | Текст перенесён под grid, parent link явно подписан «Назад» | archive templates | Low | 7 |
| UX-01 | Header badges/errors/timezone/articles/cookie | READY | Dynamic links/year, UTC+3, responsive articles и cookie acceptance покрыты локально | theme CSS/JS/templates/tests | Medium | 7 |
| MOB-01 | Mobile menu/Android/articles/grid | PARTIAL | Эмуляция 320–1024 PASS; нужны physical Android Chrome и iOS Safari | `main.js`, `main.css`, templates/tests | High | 7 |
| EXT-01 | Jivo country detection | NEEDS_INVESTIGATION | Проверить payload/config, документировать vendor action | Jivo admin/theme | Medium | 8 |
| SEO-01 | Webmaster/favicon/sitemap/indexing | PARTIAL | Локальные favicon/canonical/robots/sitemap PASS; Webmaster и production indexing требуют доступа | Webmaster/SEO settings/theme | Medium | 8 |
| TEST-01 | T01-T10 | PARTIAL | Локальные integration/E2E пройдены; реальные gateway/SMTP/devices/staging остаются | tests/staging | Critical | 8 |
| REG-01 | Полный regression | PARTIAL | Полный local regression PASS; внешний staging/production regression остаётся | tests/staging | Critical | 8 |

## Матрица дополнительных требований заказчика

Ниже используется только утверждённая шкала `IN_SCOPE / NEEDS_AUDIT / CHANGE_REQUEST / EXTERNAL_DEPENDENCY / BLOCKED`. Оценка сложности: S — малая, M — средняя, L — высокая, XL — критически сложная/межсистемная; это относительная оценка, не календарное обещание.

| ID / Requirement | Current implementation | Status | Proposed solution | Files affected | Database impact | External dependencies | Risk | Estimated complexity |
|---|---|---|---|---|---|---|---|---|
| ADD-01 Backup и staging | Backup/staging/Git baseline в поставке отсутствуют | IN_SCOPE | Iteration 0: files+DB backup, restore drill, staging и rollback runbook | Docker/deploy docs; project config | Полный dump/restore, без schema change | Hosting/control panel | Работа без восстановления может повредить production | L |
| ADD-02 Аудит текущей оплаты | Активный online gateway не найден; Store API показывает COD; есть только старый YooKassa log | IN_SCOPE | Снять gateway settings/logs, webhook/signature/retry/transaction mapping и paid transition | Payment plugin/config, новый adapter только позже | Order/payment meta audit | WP Admin, merchant cabinet | Неверный paid event ведёт к выдаче без оплаты/повтору | L |
| ADD-02B Новый payment provider с нуля | Не реализован | CHANGE_REQUEST | Отдельно выбрать provider/plugin, договор и sandbox; интеграцию не начинать | Plugin/config/payment adapter | Gateway/order meta возможно | Merchant account, комиссия | Финансовые и webhook-риски | XL |
| ADD-03 Полный E2E buyer flow | Woo foundation есть; key assignment/email/account key отсутствуют | IN_SCOPE | Автоматический и ручной flow Product → Account по итерациям 3–8 | Theme, custom plugin, test suite | Orders, assignments, events | Gateway, SMTP, staging | Межсистемные отказы | XL |
| ADD-04 Reusable keys | Склад ключей отсутствует; исходная модель подразумевает individual | CHANGE_REQUEST | После решения расширить key model `allowed_uses/used`, atomic counter и import rules | Будущий custom plugin | Новые поля/constraints/events | Нет обязательной, но нужен DB runtime | Race condition и неверные остатки | XL |
| ADD-05 Безопасность ключей | Ключи пока не хранятся | IN_SCOPE | Capability model, masking, no-secret logs/REST/JS, protected reveal/export, audit events | Будущий custom plugin/config/tests | Encrypted/controlled value, fingerprint, audit log | Secret storage/backup policy | Утечка лицензий | XL |
| ADD-06 CONTINUE_SALES / STOP_SALES + manual toggle | Управляемых режимов нет; Woo stock уже блокирует часть товаров | CHANGE_REQUEST | Сначала использовать Woo purchasability/stock где возможно; pool override только как тонкий adapter | Custom plugin + Woo product admin | Product/pool setting, event log | Business decision | Overselling либо ошибочная блокировка | L |
| ADD-07 Audit distributions storage | CPT+ACF есть; публичный ACF URL выводится напрямую; файлов в uploads-копии нет | IN_SCOPE | Получить DB/admin data, классифицировать local/external/object storage и access policy | ACF JSON/templates; без правок на аудите | Read-only postmeta/attachment audit | DB/WP Admin/storage owner | Неверная гипотеза о storage | M |
| ADD-07B Temporary link TTL 24h/regenerate | Не реализовано | CHANGE_REQUEST | После storage audit выбрать signed URL или authenticated token endpoint; определить revoke/cache policy | Custom download module/template | Token/grant table или object metadata | Storage/CDN/API может потребоваться | URL leakage/cache/bypass | L–XL |
| ADD-08 Anti-bot forms | Limit Login Attempts и Akismet есть; CAPTCHA нет; coverage/settings неизвестны | NEEDS_AUDIT | Проверить login/register/reset/checkout, WAF/proxy и активные настройки; затем согласовать вариант A–D из `DEPENDENCIES.md` | Plugin config, возможно form hooks | Options/rate-limit store возможно | Cloudflare/CAPTCHA при выборе | Lockout, abuse, privacy/UX | M–L |
| ADD-09 Search control set 30+ | Создан draft из 40 запросов; текущий поиск часть классов не проходит | IN_SCOPE | Согласовать реальные product names/positions и использовать как regression set | `SEARCH_TEST_CASES.md`, будущий search module/tests | Search index/meta возможно | Нужен полный catalog dump | Нерелевантная выдача/ложный fuzzy | L |
| ADD-10 Mobile test matrix | Автотестов/device baseline нет | IN_SCOPE | Зафиксировать Android Chrome, iOS Safari и CSS breakpoints до/после fixes | `MOBILE_TEST_MATRIX.md`, theme/tests | Нет | Реальные устройства/browser service | Исправление без воспроизводимости | M |
| ADD-11 SMTP и SPF/DKIM/DMARC | SMTP plugin/provider/DNS records не определены | EXTERNAL_DEPENDENCY | Read-only DNS/provider audit → предложить точные records → менять только после approval → delivery test | WP mail config/docs | Options/log metadata возможно | SMTP и DNS accounts | Недоставка ключей, spam, DNS outage | L |
| ADD-12 Test protocol TC-01…20 | Создан audit draft; исполнение невозможно до реализации/staging | IN_SCOPE | Поддерживать протокол по итерациям и прикладывать evidence | `TEST_PROTOCOL.md`, tests | Test fixtures only | Staging/gateway/mail/devices | Ложная приёмка | L |
| ADD-12B 20 concurrent attempts / 1 key | Инфраструктура и DB engine неизвестны | NEEDS_AUDIT | После runtime audit выбрать process-level harness и независимые DB connections; не эмулировать последовательным loop | Future integration/load tests | Одноразовые test rows | Staging/DB/runner capacity | Непоказательный тест или нагрузка production | M–L |
| ADD-13 Delivery package | Создан шаблон, фактическое наполнение после итераций | IN_SCOPE | Вести release inventory, migrations, hooks/endpoints/jobs, deploy/update/rollback | `DELIVERY.md` | Документирует impacts | Owners внешних сервисов | Неповторимый deployment | M |
| ADD-14 Pre-existing issues register | Baseline создан; изменений кода ещё нет | IN_SCOPE | Обновлять четыре раздела и связывать defects с test evidence | `PRE_EXISTING_ISSUES.md` | Нет | Нет | Смешение исходных и новых дефектов | S |
| ADD-15 Блокирующие доступы | DB/admin/hosting/payment/SMTP/DNS/service accesses отсутствуют | BLOCKED | Выдавать минимальные scoped access или выполнять owner-assisted steps по `DEPENDENCIES.md`; блокировать только зависимую работу | Документация/config после доступа | Read-only audit, затем согласованные изменения | Владельцы всех систем | Ошибочные предположения и опасные production-действия | L |
| ADD-16 Updated first stage: audit only | Файловый/read-only публичный аудит выполнен; обязательные docs подготовлены | IN_SCOPE | Остановиться после документации и ждать списка утверждённых пунктов | Audit/planning `.md` only | Нет | Для закрытия открытых фактов нужны доступы | Преждевременная разработка | M |

## Сводка согласования

### READY TO IMPLEMENT

- После явного разрешения: Iteration 0 — локальный Docker, backup/staging/Git baseline и безопасный runtime audit.
- Архитектура individual key inventory, capability/security requirements, audit trail и базовый E2E/test documentation.
- Контрольные документы поиска, mobile, тестирования, передачи и исходных дефектов.

### NEEDS DECISION

- Reusable keys и лимит повторных выдач (`ADD-04`).
- Sale modes `CONTINUE_SALES/STOP_SALES` и ручное Enable/Disable (`ADD-06`).
- Temporary distribution URL TTL 24h (`ADD-07B`).
- Конкретный anti-bot вариант после аудита (`ADD-08`).
- Поведение quantity > 1, refunds/cancellations, partial fulfillment и email retry.

### NEEDS ACCESS

- DB dump/WP Admin/staging, hosting backup/runtime, payment cabinet, SMTP/DNS, Telegram/Jivo/Webmaster.
- Реальные ACF values дистрибутивов и полный published product catalog.
- Подробности приведены в `DEPENDENCIES.md`.

### NEEDS COMMERCIAL APPROVAL

- Подключение нового payment provider с нуля.
- Возможные SMTP, WAF/CDN, CAPTCHA и object storage paid plans.
- ACF PRO renewal и дополнительные коммерческие плагины, если будут выбраны.
- Любая реализация требований со статусом `CHANGE_REQUEST`.

## Предлагаемые итерации

### Iteration 0 — локальная среда, безопасность и подтверждение runtime

- Добавить Docker Compose для локального запуска WordPress, MySQL/MariaDB и локального перехватчика почты (Mailpit или эквивалент).
- Подготовить `.env.example`, безопасные локальные секреты, persistent volumes и команды запуска/остановки.
- Импортировать согласованную обезличенную копию production-БД и uploads либо подготовить чистый воспроизводимый seed.
- Исключить любые обращения локальной среды к production SMTP, Telegram, ЮKassa и другим внешним сервисам.
- Проверить локальные frontend, wp-admin, WooCommerce, cron и отправку писем в локальный mailbox.
- Полный production backup и проверка восстановления.
- Staging-копия, Git baseline и запрет прямой разработки на production.
- Ротация Telegram secrets и временное hardening webhook.
- WooCommerce system report, active plugins/options, HPOS, Action Scheduler, PHP/MySQL versions.
- Подтвердить фактический payment lifecycle, SMTP и guest checkout.
- Зафиксировать baseline smoke/regression checklist без изменения бизнес-функций.

### Iteration 1 — фундамент плагина и модель склада

- После решений по открытым сценариям создать custom plugin skeleton.
- Versioned migrations: pools, keys, product mapping, assignments/events.
- Уникальность физического ключа, статусы и audit trail.
- Решение по encryption, masking и backup handling.
- Автоматические repository/migration tests.

### Iteration 2 — административный склад, импорт и товары

- Пулы, counters, список ключей и безопасный просмотр.
- Ручное добавление, bulk paste, TXT/CSV; XLSX после проверки зависимости.
- Связь нескольких SKU с одним пулом.
- AUTO/MANUAL setting в карточке товара.
- Валидация дубликатов и несовместимых mappings.

### Iteration 3 — ручная выдача и модель заказа

- Отдельные payment/issue statuses на уровне позиции и заказа.
- Waiting key и ручное назначение ключа в admin.
- Публичный 10-значный номер, admin columns и поиск заказов.
- Разрешение покупки без key stock согласно принятому правилу quantity.
- Audit trail и идемпотентность ручных действий.

### Iteration 4 — подтверждённый payment provider, автоматическая и конкурентная выдача

- Использование существующего подтверждённого gateway либо установка согласованного модуля только после отдельного коммерческого подтверждения, сначала в test merchant.
- Интеграция только с подтверждённым `payment.succeeded`/WooCommerce payment event.
- Идемпотентный fulfillment и fallback при 0 keys/error.
- Atomic claim, DB constraints и тест двух параллельных заказов на один ключ.
- Проверка общего пула нескольких SKU и повторного callback.

### Iteration 5 — email, уведомления и личный кабинет

- Custom Woo email с фактически выданным ключом и публичным номером.
- Исправление email и resend того же assignment без нового списания.
- SMTP/deliverability и retry policy.
- Domain notifications: 1 key, 0 keys, waiting key, fulfillment error.
- Безопасный асинхронный Telegram adapter и email fallback.
- Safe key display, статусы и публичный номер в My Account.
- Исправление подтверждённых password/logout/mobile registration bugs.

### Iteration 6 — checkout и корзина

- Guest checkout regression.
- Единая classic/Blocks email validation, repeat email и typo suggestions.
- Исправление Windows и остальных product types в корзине.
- Quantity controls, auto-update и корректное уменьшение позиции.
- Buy-now flow после согласования semantics.

### Iteration 7 — поиск, каталог, UX и mobile

- Synonyms field и нормализованный поисковый индекс.
- RU/EN, transliteration, keyboard layout, typos, partial matching и weighted ranking.
- Product-first results, порядок каталога, SEO text и back links.
- Исправления header, badges, errors, timezone, articles и cookie banner.
- Mobile menu, Android, article layout, grids и основные mobile user flows.

### Iteration 8 — внешние сервисы, полная приёмка и rollout

- Jivo phone/country test и необходимые внешние действия.
- Яндекс.Вебмастер, favicon, sitemap и indexing.
- Автоматический и ручной прогон T01-T10.
- Payment/email/Telegram failure injection и полный regression.
- Migration dry run, production backup, monitoring, staged deployment и rollback drill.

## Порядок согласования

Перед каждой итерацией отдельно фиксируются scope, файлы, схема БД, риски, backward compatibility, необходимые действия в WordPress/ЮKassa/SMTP и rollback. Если требуется настройка в админке или внешний доступ, это выносится отдельным блоком с точными шагами и указанием, кто должен выполнить действие.

После каждой итерации выдаётся отчёт: результат, изменённые файлы, миграции, автоматические проверки, блокеры, действия в админке, минимальные ручные тесты и rollback. Работа останавливается до ручной проверки и явного подтверждения пользователя. Общие критерии описаны в `ACCEPTANCE_CRITERIA.md`.

Следующий допустимый шаг после этого плана — только Iteration 0: локальный Docker-запуск, backup/staging, безопасность и подтверждение runtime. Реализация бизнес-функций ещё не начиналась.
