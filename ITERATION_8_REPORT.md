# Логическая итерация 8 — локальное SEO и регрессия

Дата: 10 октября 2026 года.

Статус: **ЛОКАЛЬНАЯ ЧАСТЬ ГОТОВА; ВНЕШНИЕ СЕРВИСЫ ОЖИДАЮТ ДОСТУПЫ**.

## Техническое SEO

- favicon 32/192 и Apple Touch Icon существуют и отдаются с HTTP 200;
- canonical, description, Open Graph и Twitter metadata присутствуют;
- WordPress indexing включён (`blog_public=1`);
- `/wp-sitemap.xml` отвечает 200, `/sitemap.xml` совместимо перенаправляется на него;
- `robots.txt` разрешает frontend, закрывает `/wp-admin/` и теперь динамически публикует `Sitemap:`;
- privacy page из cookie banner отвечает 200.

## Полная локальная регрессия

PASS:

- inventory: encryption, masking, dedupe, audit;
- product/variation mapping и shared pool;
- MANUAL/AUTO order lifecycle, repeated callback, public number, email/resend, My Account;
- локальная mock payment без денег;
- classic/Blocks checkout и повтор e-mail;
- cart `1→2→1`, plus/minus и `1→0`;
- search S01–S40 families, numeric precision, synonyms, product-first;
- account create/auth/reset/logout с удалением временного пользователя;
- responsive catalog/article/account/cookie на 320–1024 px.

Тема повышена до версии `1.1.0`; заявленная локально проверенная совместимость — WordPress 7.1.

## Внешние блокеры

- настоящий тестовый платёж ЮKassa: нужен публичный HTTPS staging и test `shopId/secretKey`;
- SMTP/SPF/DKIM/DMARC: нужны почтовый provider и DNS-доступ;
- Telegram end-to-end: нужен новый bot token, webhook secret и публичный HTTPS staging; старые секреты использовать нельзя;
- Jivo country/phone: нужен доступ к кабинету и тест разных гео;
- Яндекс.Вебмастер и фактическая production indexing: нужен доступ к кабинету/production;
- финальная device-приёмка: физические Android Chrome и iOS Safari.

Локальный mock gateway не является доказательством работы ЮKassa и не должен включаться на production.
