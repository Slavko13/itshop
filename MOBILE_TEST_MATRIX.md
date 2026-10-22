# Матрица mobile-проверок ITSOFT

Статус: локальный responsive baseline и автоматическая проверка выполнены 10 октября 2026 года. Финальный прогон на физическом Android/iOS и публичном staging остаётся обязательным.

## Устройства и viewport

| Matrix ID | Platform | Browser | Viewport/device | Назначение |
|---|---|---|---|---|
| M-01 | Android | Chrome current | 360×800 | минимальная ширина Android |
| M-02 | Android | Chrome current | 393×873 | типичный современный Android |
| M-03 | iOS | Safari current | 375×812 | iPhone narrow |
| M-04 | iOS | Safari current | 390×844 | современный iPhone |
| M-05 | Desktop responsive | Chrome | 320×800 | нижняя граница CSS |
| M-06 | Desktop responsive | Chrome | 768×1024 | tablet/breakpoint |
| M-07 | Desktop responsive | Chrome | 1024×768 | tablet landscape |
| M-08 | Desktop | Chrome/Firefox | 1280×800 и 1440×900 | desktop regression |

Фактические CSS breakpoints темы перед исправлением извлекаются из `assets/css/main.css` и связываются с ближайшими viewport выше.

## Сценарии baseline

| ID | Страница/блок | Действие | Expected behavior | Current actual behavior | Devices | Evidence | Status |
|---|---|---|---|---|---|---|---|
| MT-01 | Header/mobile menu | Нажать кнопку меню | Меню открывается с первого нажатия, фокус внутри | Исправлено условие `active`, burger стал настоящим button, close восстанавливает scroll/ARIA | M-01, M-02, M-05…M-07 | `scripts/test-mobile-catalog.mjs`: PASS; screenshots в `bin-tmp` | LOCAL_PASS |
| MT-02 | Mobile catalog categories | Открыть/закрыть вложенную категорию | Категория переключается одним нажатием, ссылка остаётся доступной | Невалидный button внутри link устранён; link и toggle разделены | M-01, M-02, M-05…M-07 | browser click + ARIA: PASS | LOCAL_PASS |
| MT-03 | Header search | Ввести запрос и отправить | Поле, клавиатура и результаты не перекрываются | Responsive выдача и канонический submit проверены; экранная клавиатура требует physical device | M-01…M-07 | search S01–S40 PASS; device keyboard pending | PARTIAL |
| MT-04 | Product page | Выбрать вариацию и нажать «В корзину» | Вариация выбирается, кнопка работает, badge обновляется | Часть Windows-товаров блокируется stock | M-01…M-04 | pending | PRE_EXISTING |
| MT-05 | Cart | Нажать `-`, quantity, `+` | Количество изменяется без удаления всей позиции; totals обновляются | `1→2→1` и явный `1→0` проходят; controls/AJAX присутствуют | M-01…M-07 | `test-cart-flow.php`: PASS | LOCAL_PASS |
| MT-06 | Checkout | Заполнить email/phone и оплатить тестом | Поля доступны, ошибки видимы, клавиатура не закрывает CTA | Classic/Blocks validation и mock payment проходят; реальный YooKassa/device keyboard не проверены | M-01…M-07 | checkout + mock E2E PASS | PARTIAL |
| MT-07 | My Account | Registration/Login/Logout | Все действия доступны и завершаются ожидаемо | Формы login/register/reset доступны и помещаются на 320/393/768; domain flow PASS | M-01, M-02, M-05, M-06 | browser + `test-account-flow.php`: PASS | LOCAL_PASS |
| MT-08 | Password reset | Запросить reset и открыть письмо | Форма и сообщение работают без layout shift | Endpoint и reset-key валидны; внешняя SMTP-доставка не проверена | M-01, M-02, M-05, M-06 | account flow PASS; SMTP pending | PARTIAL |
| MT-09 | Catalog grid | Просмотреть карточки | Согласованные блоки идут по 2 элемента, без обрезки | Основная `.catalog-grid__list` выводит 2 карточки до 575px без horizontal scroll | M-01, M-02, M-05 | browser 320/360/393: PASS | LOCAL_PASS |
| MT-10 | Articles/listing | Открыть карточку и статью | Контент читаем, изображения/CTA не выходят за viewport | Исправлены min-content grid, большие изображения, meta wrap и ложные счётчики | M-01, M-02, M-05…M-07 | browser 320/393/768/1024: PASS | LOCAL_PASS |
| MT-11 | Distribution page | Нажать download | Ссылка работает согласно выбранному режиму, без горизонтального scroll | Сейчас прямой URL; реальные данные недоступны | M-01…M-04 | pending | BLOCKED |
| MT-12 | Cookie banner/Jivo | Принять/закрыть, открыть чат | Элементы не перекрывают checkout/menu CTA | Cookie banner/actions/accept PASS на 393; Jivo country detection требует внешнего сервиса | M-02 | browser PASS; Jivo pending | PARTIAL |

## Критерий прохождения

Нет горизонтального scroll, недоступных controls, перекрытия CTA, потери focus, двойного submit или отличий бизнес-результата между Android Chrome и iOS Safari. Один только desktop responsive emulator не заменяет финальную проверку на реальных устройствах.
