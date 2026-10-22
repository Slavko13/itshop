# Состав передачи результата ITSOFT

Статус: шаблон, заполняемый по завершении каждой утверждённой итерации.

## 1. Release identity

- Версия/release tag:
- Commit hash:
- Дата сборки:
- Совместимые WordPress/WooCommerce/PHP/DB:
- Staging acceptance reference:

## 2. Изменённые артефакты

- Полный список изменённых файлов с назначением.
- Созданные custom plugins/modules и их версии.
- Текущий custom plugin: `itsoft-license-fulfillment` 0.10.0, schema 1.2.0.
- Текущая тема: `itsoft` 1.1.0; responsive/catalog/article/SEO changes входят в эту версию.
- Локальные acceptance scripts: `test-mobile-catalog.mjs`, `test-account-flow.php` и domain regression scripts из `scripts/`.
- Внешняя зависимость, зафиксированная в локальной поставке: официальный `yookassa` 2.17.3; production activation/configuration только после staging E2E.
- Исключения: WordPress Core и WooCommerce Core не должны содержать ручных правок.
- Перечень theme/template overrides и их upstream versions.

## 3. Database

- Migration version и порядок применения.
- Новые/изменённые tables, columns, indexes, constraints.
- Options, product/order meta и HPOS compatibility.
- Backfill/migration report и rollback ограничения.

## 4. Integration inventory

- WooCommerce hooks/filters.
- REST/AJAX/download endpoints и authentication model.
- Cron/Action Scheduler jobs, retry и deduplication policy.
- Payment callbacks/webhooks и transaction id mapping.
- Email classes/templates и notification events.

## 5. Third-party components

- Package/plugin, exact version, source, license и purpose.
- Коммерческая лицензия/renewal owner.
- External services, account owner, sandbox/production separation.

## 6. Configuration and secrets

- `.env.example`/documented `wp-config.php` constants без значений секретов.
- Где хранятся production passwords, API secrets и tokens.
- Rotation procedure и responsible owner.
- Подтверждение, что секреты отсутствуют в source, logs, database exports и документации передачи.

## 7. Deployment

- Preconditions и проверенный backup.
- Maintenance/traffic plan.
- Команды/шаги deployment и migrations.
- Cache/CDN/cron actions.
- Smoke tests и monitoring window.

## 8. Update instruction

- Как обновлять custom plugin/theme безопасно.
- Порядок обновления WordPress/WooCommerce/dependencies.
- Compatibility/regression checklist.
- Кто отвечает за renewals и security updates.

## 9. Rollback

- Триггеры rollback.
- Возврат к предыдущему release.
- DB down-migration либо restore point.
- Судьба заказов/назначений, появившихся после deployment.
- Проверка системы после rollback.

## 10. Acceptance package

- Заполненный `TEST_PROTOCOL.md`.
- Заполненный `MOBILE_TEST_MATRIX.md`.
- Результат `SEARCH_TEST_CASES.md`.
- Backup/restore evidence.
- Known issues и ограничения.
- Admin guide с минимальными ручными операциями.
