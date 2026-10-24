# Приёмка унификации IT Софт v2

Дата проверки: 24 октября 2026.

## Что реализовано

- Общий слой дизайн-токенов для шрифтов, цветов, контейнера, заголовков, текста, полей и состояний фокуса.
- Единый контейнер 1222 px с полезной шириной 1190 px.
- Единая сетка товаров: 3 карточки с боковой панелью, 4 без неё, 3 на 768–1023 px, 2 на 480–767 px и 1 до 479 px.
- Единая карточка товара на главной, в каталоге, поиске и рекомендациях: квадратное изображение `contain`, полный заголовок, выровненный низ, одинаковые цены, кнопки и плашки.
- Единый формат цены с неразрывными пробелами: `2 190 ₽`; копейки выводятся только при их наличии.
- Одно отсортированное дерево категорий для шапки и боковой панели: Office, Windows, Project, Visio, Server, Server RDS, затем остальные категории. Годы Office и версии Windows идут от старых к новым; macOS остаётся отдельной веткой.
- Каталог закрывается крестиком 44 × 44 px, клавишей Escape и кликом вне панели.
- Подсказки категорий поиска появляются от двух символов через 250 мс и поддерживают мышь, стрелки, Enter и Escape.
- Баннер главной и самостоятельная переработка области над подвалом не изменялись согласно ТЗ.

## Проверенные адреса

| Шаблон | Локальный адрес |
|---|---|
| Главная | `http://localhost:8080/` |
| Общий каталог | `http://localhost:8080/shop/` |
| Категория Office | `http://localhost:8080/product-category/microsoft-office/office-2021/` |
| Вторая страница каталога | `http://localhost:8080/shop/page/2/` |
| Сортировка по цене | `http://localhost:8080/shop/?orderby=price` |
| Метка товара | `http://localhost:8080/product-tag/outlook/` |
| Поиск Windows | `http://localhost:8080/?s=Windows%2011` |
| Поиск без результатов | `http://localhost:8080/?s=codex-no-such-license-92841` |
| Товар | `http://localhost:8080/product/windows-11-iot-enterprise-2024-ltsc-korporativnaya/` |
| FAQ | `http://localhost:8080/faq/` |
| Дистрибутивы | `http://localhost:8080/download/` |
| Корзина | `http://localhost:8080/cart/` |
| Оформление | `http://localhost:8080/checkout/` |
| Аккаунт | `http://localhost:8080/my-account/` |
| 404 | `http://localhost:8080/404-test-codex/` |

## Скриншоты

Автоматические скриншоты сохраняются в игнорируемые Git каталоги:

- `artifacts/storefront-redesign/` — каталог 375, 768, 1024 и 1440 px, категории, меню, сортировка, вторая страница, поиск.
- `artifacts/distribution-redesign/` — раздел дистрибутивов и страница инструкции.
- корень проекта `account-*.png` — гостевой и авторизованный аккаунт.

## Автоматические проверки

- `node scripts/test-storefront-redesign.mjs`
- `node scripts/test-product-redesign.mjs`
- `node scripts/test-account-responsive.mjs` с временным локальным пользователем
- `node scripts/test-empty-cart-responsive.mjs`
- `node scripts/test-distribution-redesign.mjs`
- `node scripts/audit-english-copy.mjs`
- `wp eval-file /workspace/scripts/test-cart-flow.php`
- `wp eval-file /workspace/scripts/test-checkout-progress.php`
- `wp eval-file /workspace/scripts/test-checkout-assurance.php`

Проверены ширины 375, 768, 1024 и 1440 px; дополнительно сохранены прежние контрольные ширины 320, 393 и 1280 px для регрессии.
