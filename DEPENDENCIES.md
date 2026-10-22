# Зависимости и блокирующие доступы ITSOFT

Дата: 9 октября 2026 года.

## Технические зависимости

| Компонент | Текущее состояние | Для чего нужен | Статус/решение |
|---|---|---|---|
| WordPress 7.1.3 | Найден в поставке | CMS/runtime | Проверить совместимость темы на staging |
| WooCommerce 10.7.0 | Найден и работает публично | Каталог, заказ, оплата, account | Базовая зависимость; не модифицировать core |
| ACF PRO 6.1.8 | Файлы и ACF JSON найдены | Поля товара/дистрибутива | Проверить лицензию, активацию и совместимость |
| PHP | Версия production неизвестна | Runtime | `BLOCKED`: нужен hosting/system report |
| MySQL/MariaDB | Версия и schema/options неизвестны | Миграции, транзакции, concurrency | `BLOCKED`: нужен DB dump/system report |
| Action Scheduler | Поставляется с WooCommerce, состояние неизвестно | Асинхронные письма/уведомления/retry | Проверить таблицы и failed actions |
| YooKassa/payment provider | Официальный `yookassa` 2.17.3 установлен и активирован только локально; gateways/AUTO выключены, credentials отсутствуют | Реальный `payment.succeeded`, callback retry и transaction ID | Нужны тестовый shopId/secret, HTTPS staging и настройки чеков; секрет вводится только в admin/secret store |
| SMTP/email provider | Не определён, SMTP plugin отсутствует | Доставка ключей и system mail | `EXTERNAL_DEPENDENCY`; нужны provider credentials без передачи секретов в репозиторий |
| DNS provider | Не определён | SPF/DKIM/DMARC | `EXTERNAL_DEPENDENCY`; изменения только после отдельного согласования |
| Telegram | Самописная интеграция есть, секреты небезопасны | Admin notifications | Нужен owner access для ротации и webhook setup |
| Jivo | Widget присутствует | Внешний customer chat | Нужен Jivo admin для provider-side проверки |
| Яндекс.Вебмастер | Доступ не предоставлен | Sitemap/indexing | Внешний доступ в Iteration 8 |
| Git repository | Отсутствует | История, deployment, rollback | Создать/предоставить в Iteration 0 |
| Docker | Конфигурации ещё нет | Локальный запуск | Создание разрешается только после утверждения старта Iteration 0 |

## Коммерческие зависимости и лицензии

| Зависимость | Возможная стоимость | Комментарий |
|---|---|---|
| ACF PRO | Существующая/продлеваемая лицензия | Право на обновления необходимо подтвердить |
| YooKassa | Комиссия/договор эквайринга | Подключение с нуля требует коммерческого подтверждения |
| SMTP provider | Бесплатный лимит или платный тариф | Выбор зависит от объёма и требований deliverability |
| Cloudflare Turnstile | Обычно без отдельной платы | Требует Cloudflare account/site keys; не требует переноса DNS для базового виджета |
| Google reCAPTCHA/hCaptcha | Условия и лимиты провайдера | Внешняя передача технических данных, требует privacy review |
| Object storage/CDN signed URLs | Тариф storage/traffic/API | Нужен только если будет утверждён temporary download mode |
| XLSX parser | Open-source library возможна | Лицензию и вес зависимости проверить до включения |

## Security / anti-bot: варианты

| Вариант | Плюсы | Минусы | Стоимость | Внешняя зависимость |
|---|---|---|---|---|
| A. Донастроить текущие плагины | Меньше изменений; Limit Login Attempts уже установлен | Не покрывает автоматически checkout/registration/reset; DB-настройки ещё не проверены | Обычно без новой платы | Возможно облако поставщика плагина |
| B. Cloudflare WAF/rate limiting | Защита до WordPress, правила по endpoint/IP/bot signals | Нужны доступ и корректный real-IP setup; часть функций зависит от тарифа | Free/paid в зависимости от правил | Cloudflare/DNS/CDN |
| C. Turnstile/CAPTCHA | Видимая защита конкретных форм | UX/privacy/accessibility; требуется интеграция каждого flow и fail-open/closed policy | Обычно бесплатно, условия провайдера применяются | CAPTCHA provider |
| D. Custom rate limiting | Точный контроль endpoint и бизнес-правил | Нужно надёжное shared storage; риск блокировки NAT/proxy; поддержка ложится на проект | Разработка и инфраструктура | DB/Redis/WAF по выбранной схеме |

Рекомендация пока не утверждается: сначала подтвердить активность плагинов, reverse proxy/CDN, реальные атаки и допустимый UX. Статус — `NEEDS_AUDIT`.

## Блокеры

### BLK-01 — Production DB / обезличенный dump

**BLOCKER:** отсутствует база данных и значения options/meta.

**WHY:** без неё нельзя подтвердить active plugins, HPOS, checkout settings, реальные payment/email настройки, ACF download URLs и состояние Action Scheduler.

**IMPACT:** Iteration 0, payment integration, distributions, SMTP, anti-bot, Iterations 1–6.

**NEEDED:** свежий обезличенный dump либо read-only DB + WP Admin/system report.

### BLK-02 — WordPress Admin / staging

**BLOCKER:** нет административного доступа к безопасной тестовой копии.

**WHY:** нельзя проверить настройки, формы, guest checkout, письма и плагины без изменения production.

**IMPACT:** Iteration 0 и все acceptance-сценарии.

**NEEDED:** staging URL и временный admin account с необходимыми правами.

### BLK-03 — Payment provider

**BLOCKER:** активный онлайн-provider/module не обнаружен, merchant access отсутствует.

**WHY:** нельзя определить test/prod mode, signature verification, webhook retries, transaction ID и достоверный paid event.

**IMPACT:** Iteration 4, TC-01/02/05/06.

**NEEDED:** решение о provider, Woo gateway settings export и доступ к test merchant/webhook logs.

### BLK-04 — Hosting / backup / runtime

**BLOCKER:** нет SSH/SFTP/control-panel backup и сведений о PHP/DB/web server.

**WHY:** нельзя создать полный backup, выполнить restore drill и оценить DB locking/concurrency.

**IMPACT:** Iteration 0, deployment и 20-way concurrency test.

**NEEDED:** hosting panel или ограниченный SSH/SFTP, backup procedure, PHP/DB versions and limits.

### BLK-05 — Email and DNS

**BLOCKER:** provider, sending domain и DNS owner неизвестны.

**WHY:** нельзя подтвердить SMTP, SPF/DKIM/DMARC и deliverability.

**IMPACT:** Iteration 5, TC-11/17.

**NEEDED:** SMTP/provider admin, тестовый mailbox, DNS read access; write access только после согласования записей.

### BLK-06 — External services

**BLOCKER:** нет owner/admin access к Telegram, Jivo и Webmaster.

**WHY:** нельзя ротировать токен, подтвердить webhook/widget и исправить provider-side настройки.

**IMPACT:** Iterations 0, 5 и 8.

**NEEDED:** владельцы сервисов выполняют действия по инструкции либо выдают временный минимально необходимый доступ.
