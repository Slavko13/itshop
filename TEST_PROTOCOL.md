# Test Protocol ITSOFT

Версия: audit draft от 9 октября 2026 года. Протокол исполняется локально/staging после утверждения соответствующей функциональности.

## Общие предусловия

- Проверенный backup/restore и изолированный staging.
- Test merchant, test mailbox и тестовые ключи без production secrets.
- Заказы помечаются `TEST`; логи не содержат полных ключей/PII.
- Для каждого кейса фиксируются build/commit, входные данные, expected, actual, PASS/FAIL и evidence.

| ID | Сценарий | Expected | Автоматизация / ручная проверка | Статус готовности |
|---|---|---|---|---|
| TC-01 | Payment successful + available individual key | Paid один раз → один key assignment → email → тот же ключ в My Account | Integration + E2E | BLOCKED payment/staging |
| TC-02A | Paid + 0 keys, CONTINUE_SALES | Заказ оплачен, `waiting_key`, admin уведомлён | Integration + E2E | CHANGE_REQUEST sale mode |
| TC-02B | 0 keys, STOP_SALES | Новый checkout/add-to-cart заблокирован штатным согласованным способом | Integration + E2E | CHANGE_REQUEST sale mode |
| TC-03 | Manual issue | Уполномоченный admin назначает один ключ; журнал заполнен | Integration + manual admin | MISSING implementation |
| TC-04 | Resend existing key | Отправляется существующий assignment; новый ключ не расходуется | Integration + mailbox | MISSING implementation |
| TC-05 | Duplicate payment callback | Повтор одного transaction/event не меняет заказ повторно и не расходует ключ | Integration with repeated payload | BLOCKED provider |
| TC-06 | Concurrency: last individual key | Только один order получает ключ, остальные переходят в предусмотренное состояние | DB integration | NEEDS_AUDIT infrastructure |
| TC-06B | 20 simultaneous attempts + 1 key | Ровно 1 assignment, 19 ожидаемых fallback, 0 duplicates | Load harness только после DB/runtime анализа | NEEDS_AUDIT; не обещан до анализа |
| TC-07 | Import TXT | Valid добавлены; invalid отклонены с причиной; duplicates не добавлены | Unit/integration + admin | MISSING implementation |
| TC-08 | Import CSV | Аналогично TC-07, корректны delimiter/encoding/quoted rows | Unit/integration + admin | MISSING implementation |
| TC-09 | Import XLSX | Valid/invalid/duplicates; безопасный размер/формула/тип ячеек | Unit/integration + admin | Dependency decision required |
| TC-10 | Reusable key limit | `allowed_uses` не превышен; после лимита выдача прекращается | Concurrency integration | CHANGE_REQUEST; не выполнять |
| TC-11 | Email correction + resend | Billing email исправлен с audit; отправлен тот же ключ | Integration + mailbox | MISSING implementation |
| TC-12 | Product search | Прогон всего `SEARCH_TEST_CASES.md`; products выше content | Automated HTTP/browser + manual relevance | Test set needs approval |
| TC-13 | Cart quantity | `-`, input, `+`, recalculation, last-unit behavior | Browser E2E | Decision on quantity required |
| TC-14 | Registration/Login/Logout | Happy path, invalid input, session logout, mobile parity | Browser E2E + rate protection | BLOCKED staging/email |
| TC-15 | Guest checkout | Покупка без account работает согласно settings | Browser E2E | BLOCKED DB/payment |
| TC-16 | Admin notifications | 1/0 key, waiting/error отправляются один раз; retry безопасен | Integration + provider sandbox | External dependency |
| TC-17 | SMTP/system email | Sent, delivered, spam/not spam и provider response зафиксированы | Mailbox test | BLOCKED provider/DNS |
| TC-18 | Favicon/sitemap/indexing | Корректные assets, sitemap response, robots/indexing settings | HTTP + Webmaster manual | External dependency |
| TC-19 | Temporary download URL | До 24h доступ; после TTL отказ; regenerated URL работает, old policy согласована | Time-controlled integration/E2E | CHANGE_REQUEST; не выполнять |
| TC-20 | Backup + restore | Из backup восстановлены БД+files, smoke проходит, время/steps записаны | Restore drill | BLOCKED hosting access |

## Обязательные инварианты

1. Один `order_item` не получает два разных assignment из-за retry.
2. Один individual key не принадлежит двум заказам.
3. Повтор callback, cron, admin action или email retry не расходует новый ключ.
4. Полный ключ отсутствует в application logs, REST responses общего назначения и JS console.
5. Операции выдачи требуют server-side authorization/capability; nonce не заменяет capability.
6. Payment status и issue status хранятся отдельно.

## Протокол результата

Для каждого TC: `PASS | FAIL | BLOCKED | NOT_RUN`, дата/среда, tester, build, evidence URL/path, фактический результат и defect ID. Итерация не принимается при Critical/High FAIL в изменённом сценарии.
