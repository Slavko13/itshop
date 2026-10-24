# IT Софт — этап 3

Источник требований: `IT Софт правки v3.docx`.

## Эталон

- Шапка: вариант с компактным текстовым логотипом `IT СОФТ`, круглым знаком 40 px, кнопкой каталога, поиском и тремя пользовательскими действиями.
- Старый увеличенный вариант логотипа и отдельные WooCommerce-шапки не используются.
- Карточка: контент заканчивается через 16 px после блока действий; искусственный резерв высоты после кнопок отсутствует.
- Оформление: магазин работает только с электронными товарами; физические адреса, способы доставки и наложенный платёж не показываются.

## Реестр проверяемых страниц

- Главная `/`
- Каталог `/shop/`
- Категория и пагинация `/product-category/.../`
- Метка `/product-tag/.../`
- Поиск с результатами и пустой поиск `/?s=...`
- Карточка простого и вариативного товара `/product/.../`
- Корзина `/cart/`
- Оформление `/checkout/`
- Личный кабинет `/my-account/`, заказы, ключи и личные данные
- Дистрибутивы: архив, категория и отдельный файл

## Автоматическая приёмка

```bash
node scripts/test-storefront-redesign.mjs
node scripts/test-product-redesign.mjs
node scripts/test-checkout-responsive.mjs http://localhost:8080/checkout/
ITSOFT_ACCOUNT_USER=<local-user> ITSOFT_ACCOUNT_PASSWORD=<local-password> node scripts/test-account-responsive.mjs
node scripts/test-empty-cart-responsive.mjs
node scripts/test-distribution-redesign.mjs
docker compose exec -T wordpress wp eval-file /workspace/scripts/test-mixed-delivery.php --allow-root
docker compose exec -T wordpress wp eval-file /workspace/scripts/test-checkout-progress.php --allow-root
docker compose exec -T wordpress wp eval-file /workspace/scripts/test-checkout-assurance.php --allow-root
```

Основные размеры: 375/393, 768, 1024/1280 и 1440 px. Скриншоты формируются тестами в `artifacts/storefront-redesign/` и в корне рабочей копии для оформления заказа.
