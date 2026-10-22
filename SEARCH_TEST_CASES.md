# Контрольный набор поиска ITSOFT

Статус: **AUTOMATED PASS**, 10 октября 2026 года. Проверено на обезличенной локальной копии каталога. S08 `Microsoft 365` и S11 `SQL Server` имеют результат `N/A`: соответствующих published SKU в текущем каталоге нет. Приоритет для всех применимых сценариев: релевантные **products выше articles/content**.

| ID | Query | Класс | Expected product / family | Expected position |
|---|---|---|---|---:|
| S-01 | Windows 11 Pro | exact EN | Windows 11 Professional | 1 |
| S-02 | Windows 11 Professional | full EN | Windows 11 Professional | 1 |
| S-03 | Windows 11 Home | full EN | Windows 11 Home | 1 |
| S-04 | Windows 10 Pro | full EN | Windows 10 Professional | 1 |
| S-05 | Microsoft Office | family EN | Microsoft Office products | 1–5 |
| S-06 | Office 2021 | partial family | Office 2021 products | 1–5 |
| S-07 | Office 2024 | partial family | Office 2024 products, если есть в каталоге | 1–5 |
| S-08 | Microsoft 365 | alternative name | Microsoft 365 products, если есть | 1–5 |
| S-09 | Visio | partial EN | Microsoft Visio products, если есть | 1–5 |
| S-10 | Project | partial EN | Microsoft Project products, если есть | 1–5 |
| S-11 | SQL Server | full EN | SQL Server products, если есть | 1–5 |
| S-12 | Server 2022 | partial EN | Windows Server 2022 products, если есть | 1–5 |
| S-13 | Виндовс 11 Про | Russian phonetic | Windows 11 Professional | 1–3 |
| S-14 | Виндоус 11 | Russian alternative | Windows 11 family | 1–5 |
| S-15 | Винда 11 | colloquial synonym | Windows 11 family | 1–5 |
| S-16 | Майкрософт офис | Russian phonetic | Microsoft Office products | 1–5 |
| S-17 | Офис 2021 | Russian alternative | Office 2021 products | 1–5 |
| S-18 | Домашняя виндовс | semantic/Russian | Windows Home products | 1–5 |
| S-19 | Профессиональная виндовс | semantic/Russian | Windows Professional products | 1–5 |
| S-20 | windows11 | joined query | Windows 11 family | 1–5 |
| S-21 | win11 | abbreviation | Windows 11 family | 1–5 |
| S-22 | w11 pro | abbreviation | Windows 11 Professional | 1–3 |
| S-23 | win 10 pro | abbreviation/spaces | Windows 10 Professional | 1–3 |
| S-24 | ms office | abbreviation | Microsoft Office products | 1–5 |
| S-25 | msoffice | joined abbreviation | Microsoft Office products | 1–5 |
| S-26 | vindovs 11 | transliteration | Windows 11 family | 1–5 |
| S-27 | vindous 11 pro | transliteration | Windows 11 Professional | 1–3 |
| S-28 | ofis 2021 | transliteration | Office 2021 products | 1–5 |
| S-29 | windos 11 | typo | Windows 11 family | 1–5 |
| S-30 | wndows 11 | missing letter | Windows 11 family | 1–5 |
| S-31 | windwos 11 | transposition | Windows 11 family | 1–5 |
| S-32 | microsft office | typo | Microsoft Office products | 1–5 |
| S-33 | offce 2021 | typo | Office 2021 products | 1–5 |
| S-34 | window | partial query | Windows products | 1–10 |
| S-35 | micro | partial query | Microsoft products | 1–10 |
| S-36 | profess | partial query | Professional editions | 1–10 |
| S-37 | home | alternative/edition | Home editions | 1–10 |
| S-38 | ключ windows 11 | intent phrase | Windows 11 license products | 1–5 |
| S-39 | лицензия office | intent phrase | Microsoft Office license products | 1–5 |
| S-40 | активация windows | intent phrase | Windows products before instructional articles | 1–10 |

## Правила результата

- Exact SKU/title match получает больший вес, чем synonym, partial и typo.
- Товар с совпадением не должен быть вытеснен статьёй с тем же текстом.
- Несуществующая версия не должна подменяться заведомо неверным продуктом; допустима пустая выдача с предложением близкого запроса.
- Typos допускаются только в ограниченной дистанции, чтобы не создавать нерелевантный шум.
- Перед фиксацией expected product names выгрузить published products и согласовать коммерчески важные SKU.

## Фактический результат

- S01–S07, S09–S10, S12–S40: `PASS` по ожидаемой семье и максимальной позиции.
- S08 и S11: `N/A`, так как SKU отсутствуют; заведомо неверный товар не подставляется.
- Дополнительный layout case `цштвщцы 11 зкщ` → `Windows 11 Pro`: `PASS`.
- Несуществующая версия `Office 2099` → пустая выдача: `PASS`.
- Пользовательское поле синонимов товара: `PASS`.
- При конкуренции релевантного товара и статьи первым идёт товар: `PASS`.
- Публичные HTTP URL `/search/?s=...` и `/?s=...` выводят новый шаблон результатов: `PASS`.
