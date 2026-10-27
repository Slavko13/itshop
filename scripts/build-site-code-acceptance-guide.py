from pathlib import Path

from reportlab.lib import colors
from reportlab.lib.enums import TA_CENTER, TA_LEFT
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import ParagraphStyle, getSampleStyleSheet
from reportlab.lib.units import mm
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.platypus import (
    BaseDocTemplate,
    Frame,
    KeepTogether,
    PageBreak,
    PageTemplate,
    Paragraph,
    Spacer,
    Table,
    TableStyle,
)


ROOT = Path(__file__).resolve().parents[1]
OUTPUT = ROOT / "output" / "pdf" / "Website_Code_Acceptance_Guide_RU.pdf"
OUTPUT.parent.mkdir(parents=True, exist_ok=True)

FONT_DIR = Path("C:/Windows/Fonts")
pdfmetrics.registerFont(TTFont("Arial", str(FONT_DIR / "arial.ttf")))
pdfmetrics.registerFont(TTFont("Arial-Bold", str(FONT_DIR / "arialbd.ttf")))
pdfmetrics.registerFont(TTFont("Arial-Italic", str(FONT_DIR / "ariali.ttf")))
pdfmetrics.registerFontFamily(
    "Arial",
    normal="Arial",
    bold="Arial-Bold",
    italic="Arial-Italic",
    boldItalic="Arial-Bold",
)

PAGE_W, PAGE_H = A4
PURPLE = colors.HexColor("#6C43FF")
PURPLE_DARK = colors.HexColor("#24194F")
PURPLE_PALE = colors.HexColor("#F0ECFF")
INK = colors.HexColor("#11131D")
TEXT = colors.HexColor("#555D72")
MUTED = colors.HexColor("#81899D")
LINE = colors.HexColor("#E2E5EE")
BG = colors.HexColor("#F7F8FC")
GREEN = colors.HexColor("#08785B")
GREEN_BG = colors.HexColor("#E5F8F1")
AMBER = colors.HexColor("#A35B00")
AMBER_BG = colors.HexColor("#FFF1D9")
RED = colors.HexColor("#B62443")
RED_BG = colors.HexColor("#FFE5EB")
WHITE = colors.white


styles = getSampleStyleSheet()
styles.add(ParagraphStyle(
    name="BodyRU", fontName="Arial", fontSize=9.2, leading=13.2,
    textColor=TEXT, spaceAfter=2.5 * mm,
))
styles.add(ParagraphStyle(
    name="SmallRU", fontName="Arial", fontSize=7.6, leading=10.5,
    textColor=MUTED,
))
styles.add(ParagraphStyle(
    name="H1RU", fontName="Arial-Bold", fontSize=27, leading=30,
    textColor=INK, spaceAfter=5 * mm,
))
styles.add(ParagraphStyle(
    name="H2RU", fontName="Arial-Bold", fontSize=19, leading=22,
    textColor=INK, spaceAfter=4 * mm,
))
styles.add(ParagraphStyle(
    name="H3RU", fontName="Arial-Bold", fontSize=11.5, leading=14,
    textColor=INK, spaceAfter=1.5 * mm,
))
styles.add(ParagraphStyle(
    name="EyebrowRU", fontName="Arial-Bold", fontSize=7.6, leading=9,
    textColor=PURPLE, tracking=1.1, spaceAfter=2 * mm,
))
styles.add(ParagraphStyle(
    name="CoverH1RU", fontName="Arial-Bold", fontSize=32, leading=35,
    textColor=WHITE, spaceAfter=6 * mm,
))
styles.add(ParagraphStyle(
    name="CoverLeadRU", fontName="Arial", fontSize=12, leading=17,
    textColor=colors.HexColor("#DDD8F4"), spaceAfter=7 * mm,
))
styles.add(ParagraphStyle(
    name="CellRU", fontName="Arial", fontSize=7.8, leading=10.2,
    textColor=TEXT,
))
styles.add(ParagraphStyle(
    name="CellBoldRU", fontName="Arial-Bold", fontSize=8, leading=10.4,
    textColor=INK,
))
styles.add(ParagraphStyle(
    name="CenteredRU", fontName="Arial-Bold", fontSize=10, leading=13,
    alignment=TA_CENTER, textColor=INK,
))


def P(text, style="BodyRU"):
    return Paragraph(text, styles[style])


def bullet(text):
    return Paragraph(f'<font color="#6C43FF">●</font>&nbsp;&nbsp;{text}', styles["BodyRU"])


def pill(text, background=PURPLE_PALE, foreground=PURPLE):
    table = Table([[P(text, "CellBoldRU")]], colWidths=[44 * mm])
    table.setStyle(TableStyle([
        ("BACKGROUND", (0, 0), (-1, -1), background),
        ("TEXTCOLOR", (0, 0), (-1, -1), foreground),
        ("BOX", (0, 0), (-1, -1), 0, background),
        ("LEFTPADDING", (0, 0), (-1, -1), 4 * mm),
        ("RIGHTPADDING", (0, 0), (-1, -1), 4 * mm),
        ("TOPPADDING", (0, 0), (-1, -1), 2.2 * mm),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 2.2 * mm),
        ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
    ]))
    return table


def info_box(title, text, color=PURPLE, background=PURPLE_PALE):
    content = [[P(title, "H3RU")], [P(text, "BodyRU")]]
    table = Table(content, colWidths=[174 * mm])
    table.setStyle(TableStyle([
        ("BACKGROUND", (0, 0), (-1, -1), background),
        ("LINEBEFORE", (0, 0), (0, -1), 3, color),
        ("LEFTPADDING", (0, 0), (-1, -1), 5 * mm),
        ("RIGHTPADDING", (0, 0), (-1, -1), 5 * mm),
        ("TOPPADDING", (0, 0), (0, 0), 4 * mm),
        ("BOTTOMPADDING", (0, 0), (0, 0), 1 * mm),
        ("TOPPADDING", (0, 1), (0, 1), 0),
        ("BOTTOMPADDING", (0, 1), (0, 1), 3.5 * mm),
    ]))
    return table


def metric_table(rows, widths=None):
    widths = widths or [43 * mm, 39 * mm, 39 * mm, 53 * mm]
    data = [[P(c, "CellBoldRU") for c in rows[0]]]
    for row in rows[1:]:
        data.append([P(c, "CellRU") for c in row])
    table = Table(data, colWidths=widths, repeatRows=1)
    table.setStyle(TableStyle([
        ("BACKGROUND", (0, 0), (-1, 0), PURPLE_DARK),
        ("TEXTCOLOR", (0, 0), (-1, 0), WHITE),
        ("GRID", (0, 0), (-1, -1), 0.55, LINE),
        ("BACKGROUND", (0, 1), (-1, -1), WHITE),
        ("ROWBACKGROUNDS", (0, 1), (-1, -1), [WHITE, colors.HexColor("#FBFBFD")]),
        ("LEFTPADDING", (0, 0), (-1, -1), 3 * mm),
        ("RIGHTPADDING", (0, 0), (-1, -1), 3 * mm),
        ("TOPPADDING", (0, 0), (-1, -1), 2.6 * mm),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 2.6 * mm),
        ("VALIGN", (0, 0), (-1, -1), "TOP"),
    ]))
    return table


def section_title(kicker, title, text=None):
    parts = [P(kicker.upper(), "EyebrowRU"), P(title, "H2RU")]
    if text:
        parts.append(P(text, "BodyRU"))
    return parts


def page_background(canvas, doc):
    page = canvas.getPageNumber()
    canvas.saveState()
    if page == 1:
        canvas.setFillColor(PURPLE_DARK)
        canvas.rect(0, 0, PAGE_W, PAGE_H, stroke=0, fill=1)
        canvas.setFillColor(PURPLE)
        canvas.circle(PAGE_W - 15 * mm, 25 * mm, 70 * mm, stroke=0, fill=1)
    else:
        canvas.setFillColor(BG)
        canvas.rect(0, 0, PAGE_W, PAGE_H, stroke=0, fill=1)
        canvas.setStrokeColor(LINE)
        canvas.line(18 * mm, PAGE_H - 15 * mm, PAGE_W - 18 * mm, PAGE_H - 15 * mm)
        canvas.setFont("Arial-Bold", 8)
        canvas.setFillColor(PURPLE)
        canvas.drawString(18 * mm, PAGE_H - 11 * mm, "КРИТЕРИИ ПРИЁМКИ САЙТА")
        canvas.setFont("Arial", 8)
        canvas.setFillColor(MUTED)
        canvas.drawRightString(PAGE_W - 18 * mm, 10 * mm, str(page))
    canvas.restoreState()


doc = BaseDocTemplate(
    str(OUTPUT), pagesize=A4,
    leftMargin=18 * mm, rightMargin=18 * mm,
    topMargin=22 * mm, bottomMargin=16 * mm,
    title="Критерии приёмки сайта и кода простым языком",
    author="IT Софт",
)
frame = Frame(doc.leftMargin, doc.bottomMargin, doc.width, doc.height, id="main", showBoundary=0)
doc.addPageTemplates([PageTemplate(id="all", frames=[frame], onPage=page_background)])

story = []

# Page 1
story += [Spacer(1, 35 * mm)]
story.append(P("ПРАКТИЧЕСКАЯ ПАМЯТКА", "EyebrowRU"))
story.append(P("Как принять сайт<br/>и понять, тяжёлый он или лёгкий", "CoverH1RU"))
story.append(P(
    "Без сложных терминов: что измеряет браузер, какие цифры считать хорошими, "
    "что проверять кроме скорости и как сформулировать понятные критерии приёмки.",
    "CoverLeadRU",
))
cover_cards = Table([
    [P("СКОРОСТЬ", "CellBoldRU"), P("ВЕС", "CellBoldRU"), P("КАЧЕСТВО", "CellBoldRU")],
    [P("Когда появляется содержимое и реагируют кнопки", "SmallRU"),
     P("Сколько данных, запросов и JavaScript получает устройство", "SmallRU"),
     P("Ошибки, доступность, SEO, безопасность и работоспособность", "SmallRU")],
], colWidths=[56 * mm] * 3)
cover_cards.setStyle(TableStyle([
    ("BACKGROUND", (0, 0), (-1, -1), colors.Color(1, 1, 1, alpha=0.10)),
    ("TEXTCOLOR", (0, 0), (-1, -1), WHITE),
    ("BOX", (0, 0), (-1, -1), 0.6, colors.Color(1, 1, 1, alpha=0.25)),
    ("INNERGRID", (0, 0), (-1, -1), 0.3, colors.Color(1, 1, 1, alpha=0.18)),
    ("LEFTPADDING", (0, 0), (-1, -1), 4 * mm),
    ("RIGHTPADDING", (0, 0), (-1, -1), 4 * mm),
    ("TOPPADDING", (0, 0), (-1, -1), 4 * mm),
    ("BOTTOMPADDING", (0, 0), (-1, -1), 4 * mm),
    ("VALIGN", (0, 0), (-1, -1), "TOP"),
]))
story.append(Spacer(1, 7 * mm))
story.append(cover_cards)
story.append(Spacer(1, 36 * mm))
story.append(P(
    "Короткий ответ: сайт оценивает не один «браузерный судья». Lighthouse проводит лабораторный тест, "
    "Chrome и системы аналитики собирают реальные данные пользователей, поисковые системы проверяют "
    "техническую доступность, а приёмка проекта дополнительно проверяет бизнес-функции.",
    "CoverLeadRU",
))
story.append(PageBreak())

# Page 2
story += section_title(
    "Главная мысль",
    "«Лёгкий сайт» — это не просто маленький архив",
    "Размер исходников на сервере почти ничего не говорит о скорости. Важно, сколько данных браузер "
    "реально скачал, как быстро сервер ответил и сколько работы устройство выполнило после загрузки.",
)
story.append(info_box(
    "Простое определение",
    "Лёгкий сайт быстро показывает основной контент, сразу реагирует на действия, не прыгает при загрузке "
    "и не заставляет телефон скачивать и обрабатывать лишние файлы. Тяжёлый сайт может иметь красивый дизайн, "
    "но долго ждать сервер, загружать мегабайты изображений и JavaScript, тормозить при кликах и двигать элементы.",
))
story.append(Spacer(1, 5 * mm))
pipeline = [
    ["Этап", "Что происходит", "Что может тормозить"],
    ["1. Сервер", "Получает запрос и готовит HTML", "Медленная база, слабый хостинг, отсутствие кэша"],
    ["2. Сеть", "Передаёт HTML, CSS, JS, изображения и шрифты", "Большой вес, много запросов, нет сжатия и CDN"],
    ["3. Браузер", "Разбирает код и строит страницу", "Тяжёлый CSS, огромный DOM, блокирующие ресурсы"],
    ["4. JavaScript", "Запускает меню, корзину, поиск и аналитику", "Длинные задачи, плагины, сторонние виджеты"],
    ["5. Пользователь", "Видит страницу и нажимает кнопки", "Поздний контент, задержка реакции, скачки интерфейса"],
]
story.append(metric_table(pipeline, [31 * mm, 67 * mm, 76 * mm]))
story.append(Spacer(1, 5 * mm))
story.append(P("Кто и чем измеряет", "H3RU"))
for item in [
    "<b>Lighthouse</b> — искусственно загружает страницу в контролируемых условиях и выдаёт баллы и диагностику.",
    "<b>PageSpeed Insights / CrUX / аналитика</b> — показывают опыт реальных пользователей, если накоплено достаточно посещений.",
    "<b>Chrome DevTools</b> — показывает каждый запрос, переданный вес, время ожидания, выполнение JavaScript и сдвиги интерфейса.",
    "<b>Поисковые системы</b> — оценивают доступность страницы для робота, разметку, мобильность и множество факторов вне Lighthouse.",
    "<b>Тестировщик</b> — проверяет функции: покупку, формы, ошибки, адаптивность, авторизацию, письма и оплату.",
]:
    story.append(bullet(item))
story.append(PageBreak())

# Page 3
story += section_title(
    "Официальные метрики",
    "Что значит «быстро» для пользователя",
    "Core Web Vitals проверяются по реальным визитам. Хорошим считается результат, который выполняется "
    "минимум для 75% посещений отдельно на мобильных устройствах и компьютерах.",
)
vitals = [
    ["Метрика", "Простыми словами", "Хорошо", "Плохо"],
    ["LCP", "Когда появился главный крупный контент", "≤ 2,5 с", "> 4,0 с"],
    ["INP", "Как быстро страница отвечает на клик или ввод", "≤ 200 мс", "> 500 мс"],
    ["CLS", "Насколько сильно элементы неожиданно прыгают", "≤ 0,10", "> 0,25"],
    ["FCP", "Когда пользователь впервые увидел хоть что-то", "≤ 1,8 с", "> 3,0 с"],
    ["TTFB", "Как быстро сервер начал отвечать", "≤ 0,8 с", "> 1,8 с"],
    ["TBT (лаборатория)", "Сколько времени JavaScript блокировал страницу", "< 200 мс", "Чем больше, тем хуже"],
]
story.append(metric_table(vitals, [31 * mm, 72 * mm, 32 * mm, 39 * mm]))
story.append(Spacer(1, 6 * mm))
story.append(P("Баллы Lighthouse", "H3RU"))
score_cards = Table([
    [P("90–100", "CenteredRU"), P("50–89", "CenteredRU"), P("0–49", "CenteredRU")],
    [P("зелёная зона", "SmallRU"), P("нужно улучшать", "SmallRU"), P("плохой результат", "SmallRU")],
], colWidths=[58 * mm] * 3)
score_cards.setStyle(TableStyle([
    ("BACKGROUND", (0, 0), (0, -1), GREEN_BG),
    ("BACKGROUND", (1, 0), (1, -1), AMBER_BG),
    ("BACKGROUND", (2, 0), (2, -1), RED_BG),
    ("TEXTCOLOR", (0, 0), (0, 0), GREEN),
    ("TEXTCOLOR", (1, 0), (1, 0), AMBER),
    ("TEXTCOLOR", (2, 0), (2, 0), RED),
    ("BOX", (0, 0), (-1, -1), 0.6, LINE),
    ("INNERGRID", (0, 0), (-1, -1), 0.6, LINE),
    ("TOPPADDING", (0, 0), (-1, -1), 4 * mm),
    ("BOTTOMPADDING", (0, 0), (-1, -1), 4 * mm),
    ("ALIGN", (0, 0), (-1, -1), "CENTER"),
]))
story.append(score_cards)
story.append(Spacer(1, 6 * mm))
story.append(info_box(
    "Почему одного запуска недостаточно",
    "Баллы меняются из-за нагрузки сервера, маршрута сети, сторонних сервисов и мощности компьютера. "
    "Для приёмки делают минимум 3 запуска одной и той же страницы и берут медиану. Лабораторный Lighthouse "
    "нужен для поиска регрессий, а окончательное качество после запуска подтверждается реальными данными.",
    color=AMBER, background=AMBER_BG,
))
story.append(PageBreak())

# Page 4
story += section_title(
    "Практический бюджет",
    "Как отличить лёгкую страницу от тяжёлой",
    "Единого официального лимита веса не существует: фотокаталог и текстовая статья объективно различаются. "
    "Ниже — понятный рекомендуемый бюджет для коммерческого сайта без видео и тяжёлых 3D-сцен.",
)
budget = [
    ["Показатель первой загрузки", "Лёгкая", "Допустимая", "Тяжёлая"],
    ["Передано по сети", "≤ 0,7 МБ", "0,7–1,5 МБ", "> 2,5 МБ"],
    ["Количество запросов", "≤ 50", "51–90", "> 100"],
    ["JavaScript", "≤ 200 КБ", "200–350 КБ", "> 500 КБ"],
    ["Изображения", "≤ 500 КБ", "500 КБ–1 МБ", "> 1,5 МБ"],
    ["Шрифты", "≤ 100 КБ", "100–200 КБ", "> 300 КБ"],
    ["Сторонние сервисы", "0–3", "4–6", "Много и без контроля"],
]
story.append(metric_table(budget, [60 * mm, 38 * mm, 38 * mm, 38 * mm]))
story.append(Spacer(1, 5 * mm))
story.append(P("Что обязательно проверять в Network", "H3RU"))
network_checks = [
    "Столбец <b>Transferred</b>: сколько реально скачано после сжатия. Не путать с размером распакованных исходников.",
    "Изображения отдаются в WebP/AVIF и в размере, близком к отображаемому, а не 3000 px для карточки 300 px.",
    "Текстовые файлы передаются с gzip или Brotli; повторный визит использует кэш браузера.",
    "CSS и JavaScript, необходимые для первого экрана, не ждут длинную цепочку зависимостей.",
    "Нет 404/500, бесконечных запросов, повторной загрузки одного файла и тяжёлых ресурсов со сторонних доменов.",
]
for item in network_checks:
    story.append(bullet(item))
story.append(Spacer(1, 4 * mm))
story.append(info_box(
    "Важно",
    "Страница весом 500 КБ может быть медленной из-за сервера или тяжёлого JavaScript. Страница весом 2 МБ "
    "может выглядеть быстрой на домашнем Wi‑Fi, но стать тяжёлой на бюджетном телефоне. Поэтому вес всегда "
    "оценивают вместе с LCP, INP/TBT, CLS и TTFB.",
))
story.append(PageBreak())

# Page 5
story += section_title(
    "Не только скорость",
    "Полная приёмка качества сайта",
    "Lighthouse — полезный индикатор, но он не способен оформить заказ вместо человека или проверить всю бизнес-логику.",
)
quality = [
    ["Область", "Что принимаем", "Минимальный результат"],
    ["Функциональность", "Меню, поиск, фильтры, корзина, купоны, формы, вход, оплата, письма", "Критические сценарии проходят без ошибок"],
    ["Мобильная версия", "320–430 px, планшет, поворот экрана, клавиатура", "Нет горизонтального скролла и наложений"],
    ["Доступность", "Клавиатура, фокус, подписи полей, alt, контраст, масштаб 200%", "Lighthouse ≥ 95 и ручная проверка"],
    ["SEO", "Title, description, один H1, canonical, robots, sitemap, schema, статусы", "Lighthouse SEO = 100; нет блокировки индексации"],
    ["Best Practices", "HTTPS, консоль, устаревшие API, безопасные зависимости", "≥ 95; нет ошибок консоли"],
    ["Безопасность", "HTTPS, заголовки, права, секреты, бэкапы, обновления", "Нет секретов в Git; восстановление проверено"],
    ["Совместимость", "Chrome, Safari, Firefox, Edge; iOS и Android", "Ключевые сценарии работают одинаково"],
    ["Надёжность", "Ошибки сервера, таймауты, повторная отправка, пустые состояния", "Понятные сообщения; нет потери заказа"],
    ["Код", "Линтеры, тесты, структура, зависимости, сборка", "Сборка чистая; тесты проходят; нет мёртвого кода"],
]
story.append(metric_table(quality, [36 * mm, 83 * mm, 55 * mm]))
story.append(Spacer(1, 5 * mm))
story.append(info_box(
    "Что значит «качество кода» для заказчика",
    "Не количество ручного набора и не факт использования нейросети, а воспроизводимый результат: код читается, "
    "проверяется тестами, не содержит секретов и явного мусора, разворачивается по инструкции, обновляется без "
    "потери данных и не ломает пользовательские сценарии.",
))
story.append(PageBreak())

# Page 6
story += section_title(
    "Готовый регламент",
    "Критерии приёмки проекта",
    "Этот список можно включить в акт или техническое задание. Пороговые значения ниже — практический бюджет проекта; "
    "официальные Core Web Vitals отмечены отдельно.",
)
acceptance = [
    ["Проверка", "Критерий приёмки"],
    ["Lighthouse Mobile", "Performance ≥ 85; Accessibility ≥ 95; Best Practices ≥ 95; SEO = 100"],
    ["Lighthouse Desktop", "Performance ≥ 90; остальные категории — как для Mobile"],
    ["Core Web Vitals", "LCP ≤ 2,5 с; INP ≤ 200 мс; CLS ≤ 0,10 на 75-м процентиле реальных визитов"],
    ["Лабораторные метрики", "FCP ≤ 1,8 с; TBT < 200 мс; TTFB ориентировочно ≤ 0,8 с"],
    ["Вес", "Цель ≤ 0,7 МБ; допустимо до 1,5 МБ для типовой страницы; исключения объяснены"],
    ["Сеть", "Обычно ≤ 50 запросов; нет 404/500; включены сжатие и кэш статики"],
    ["Адаптивность", "Нет горизонтального скролла, обрезаний, наложений и недоступных кнопок"],
    ["Бизнес-сценарии", "Каталог → товар → корзина → оформление → оплата → заказ → письмо/ключ проходят полностью"],
    ["Ошибки", "Нет ошибок JavaScript/PHP в обычном сценарии; ошибки пользователя оформлены понятно"],
    ["Развёртывание", "Есть резервная копия, инструкция и проверенная команда обновления/восстановления"],
]
story.append(metric_table(acceptance, [54 * mm, 120 * mm]))
story.append(Spacer(1, 5 * mm))
story.append(P("Как проводить замер", "H3RU"))
for item in [
    "Выбрать минимум главную, каталог, карточку товара, корзину и оформление заказа.",
    "Проверять Mobile и Desktop в чистом профиле браузера, без расширений.",
    "Выполнить не менее 3 прогонов каждой страницы и сравнить медиану, а не лучший результат.",
    "Проверить холодную загрузку без кэша и повторную загрузку с кэшем.",
    "После запуска собрать реальные данные минимум за несколько недель и проверить 75-й процентиль.",
]:
    story.append(bullet(item))
story.append(PageBreak())

# Page 7
story += section_title(
    "Итог",
    "Когда сайт можно назвать лёгким и готовым",
)
summary = Table([
    [P("1", "CenteredRU"), P("Быстро показывает главное", "H3RU"), P("LCP, FCP и TTFB находятся в целевой зоне.", "BodyRU")],
    [P("2", "CenteredRU"), P("Сразу реагирует", "H3RU"), P("INP/TBT не показывают блокировку интерфейса.", "BodyRU")],
    [P("3", "CenteredRU"), P("Не прыгает", "H3RU"), P("CLS ≤ 0,10, изображения и блоки имеют заданные размеры.", "BodyRU")],
    [P("4", "CenteredRU"), P("Не тратит лишний трафик", "H3RU"), P("Вес, изображения, шрифты, JavaScript и запросы укладываются в бюджет.", "BodyRU")],
    [P("5", "CenteredRU"), P("Работает как продукт", "H3RU"), P("Покупка, формы, вход, оплата, письма и мобильная версия проверены вручную.", "BodyRU")],
    [P("6", "CenteredRU"), P("Можно поддерживать", "H3RU"), P("Есть тесты, бэкап, инструкция развёртывания и контроль после релиза.", "BodyRU")],
], colWidths=[14 * mm, 58 * mm, 102 * mm])
summary.setStyle(TableStyle([
    ("BACKGROUND", (0, 0), (-1, -1), WHITE),
    ("GRID", (0, 0), (-1, -1), 0.55, LINE),
    ("BACKGROUND", (0, 0), (0, -1), PURPLE_PALE),
    ("TEXTCOLOR", (0, 0), (0, -1), PURPLE),
    ("LEFTPADDING", (0, 0), (-1, -1), 3 * mm),
    ("RIGHTPADDING", (0, 0), (-1, -1), 3 * mm),
    ("TOPPADDING", (0, 0), (-1, -1), 3 * mm),
    ("BOTTOMPADDING", (0, 0), (-1, -1), 3 * mm),
    ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
]))
story.append(summary)
story.append(Spacer(1, 6 * mm))
story.append(info_box(
    "Финальная формула",
    "Лёгкий сайт = быстрый ответ сервера + небольшой и правильно сжатый набор ресурсов + минимум лишней работы "
    "для браузера + стабильный интерфейс + исправные пользовательские сценарии. Ни один отдельный балл не заменяет эту совокупность.",
    color=GREEN, background=GREEN_BG,
))
story.append(Spacer(1, 6 * mm))
story.append(P("Официальные источники", "H3RU"))
sources = [
    "Core Web Vitals: https://web.dev/articles/vitals",
    "Пороговые значения: https://web.dev/articles/defining-core-web-vitals-thresholds",
    "Расчёт Lighthouse Performance: https://developer.chrome.com/docs/lighthouse/performance/performance-scoring/",
    "Lighthouse в Chrome DevTools: https://developer.chrome.com/docs/devtools/lighthouse",
    "Network в Chrome DevTools: https://developer.chrome.com/docs/devtools/network/reference/",
    "FCP: https://web.dev/articles/fcp · TBT: https://web.dev/articles/tbt · TTFB: https://web.dev/articles/ttfb",
]
for source in sources:
    story.append(P(source, "SmallRU"))
story.append(Spacer(1, 5 * mm))
story.append(P(
    "Версия памятки: 27 октября 2026 года. Официальные пороги Core Web Vitals приведены по документации Chrome/web.dev. "
    "Лимиты веса, количества запросов и проектные баллы являются рекомендуемым бюджетом для приёмки коммерческого сайта, а не универсальным стандартом Google.",
    "SmallRU",
))

doc.build(story)
print(OUTPUT)
