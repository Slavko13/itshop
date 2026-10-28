from pathlib import Path

from reportlab.lib import colors
from reportlab.lib.enums import TA_CENTER
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import ParagraphStyle, getSampleStyleSheet
from reportlab.lib.units import mm
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.platypus import (
    BaseDocTemplate,
    Frame,
    PageBreak,
    PageTemplate,
    Paragraph,
    Spacer,
    Table,
    TableStyle,
)


ROOT = Path(__file__).resolve().parents[1]
OUTPUT = ROOT / "output" / "pdf" / "AI_Assisted_Web_Development_Arguments_RU.pdf"
OUTPUT.parent.mkdir(parents=True, exist_ok=True)

FONT_DIR = Path("C:/Windows/Fonts")
pdfmetrics.registerFont(TTFont("Arial", str(FONT_DIR / "arial.ttf")))
pdfmetrics.registerFont(TTFont("Arial-Bold", str(FONT_DIR / "arialbd.ttf")))
pdfmetrics.registerFont(TTFont("Arial-Italic", str(FONT_DIR / "ariali.ttf")))
pdfmetrics.registerFontFamily(
    "Arial", normal="Arial", bold="Arial-Bold", italic="Arial-Italic", boldItalic="Arial-Bold"
)

PAGE_W, PAGE_H = A4
PURPLE = colors.HexColor("#6C43FF")
PURPLE_DARK = colors.HexColor("#211747")
PURPLE_PALE = colors.HexColor("#F0ECFF")
INK = colors.HexColor("#11131D")
TEXT = colors.HexColor("#50586C")
MUTED = colors.HexColor("#828A9E")
LINE = colors.HexColor("#E1E4ED")
BG = colors.HexColor("#F7F8FC")
WHITE = colors.white
GREEN = colors.HexColor("#08785B")
GREEN_BG = colors.HexColor("#E6F7F1")
AMBER = colors.HexColor("#995700")
AMBER_BG = colors.HexColor("#FFF2DB")
RED = colors.HexColor("#AE2945")
RED_BG = colors.HexColor("#FFE8ED")

styles = getSampleStyleSheet()
styles.add(ParagraphStyle(
    name="Body", fontName="Arial", fontSize=9.2, leading=13.2,
    textColor=TEXT, spaceAfter=2.4 * mm,
))
styles.add(ParagraphStyle(
    name="Small", fontName="Arial", fontSize=7.5, leading=10.4,
    textColor=MUTED,
))
styles.add(ParagraphStyle(
    name="H1", fontName="Arial-Bold", fontSize=27, leading=31,
    textColor=INK, spaceAfter=4.5 * mm,
))
styles.add(ParagraphStyle(
    name="H2", fontName="Arial-Bold", fontSize=19, leading=22.5,
    textColor=INK, spaceAfter=3.5 * mm,
))
styles.add(ParagraphStyle(
    name="H3", fontName="Arial-Bold", fontSize=11.2, leading=14,
    textColor=INK, spaceAfter=1.2 * mm,
))
styles.add(ParagraphStyle(
    name="Eyebrow", fontName="Arial-Bold", fontSize=7.5, leading=9,
    textColor=PURPLE, tracking=1.1, spaceAfter=2 * mm,
))
styles.add(ParagraphStyle(
    name="CoverH1", fontName="Arial-Bold", fontSize=31, leading=34.5,
    textColor=WHITE, spaceAfter=6 * mm,
))
styles.add(ParagraphStyle(
    name="CoverLead", fontName="Arial", fontSize=12, leading=17,
    textColor=colors.HexColor("#E0DCF3"), spaceAfter=6 * mm,
))
styles.add(ParagraphStyle(
    name="Cell", fontName="Arial", fontSize=7.8, leading=10.3,
    textColor=TEXT,
))
styles.add(ParagraphStyle(
    name="CellBold", fontName="Arial-Bold", fontSize=8, leading=10.4,
    textColor=INK,
))
styles.add(ParagraphStyle(
    name="HeaderCell", fontName="Arial-Bold", fontSize=8, leading=10.4,
    textColor=WHITE,
))
styles.add(ParagraphStyle(
    name="CoverCell", fontName="Arial-Bold", fontSize=8, leading=10.4,
    textColor=WHITE,
))
styles.add(ParagraphStyle(
    name="CoverSmall", fontName="Arial", fontSize=7.5, leading=10.4,
    textColor=colors.HexColor("#E0DCF3"),
))
styles.add(ParagraphStyle(
    name="Center", fontName="Arial-Bold", fontSize=10, leading=13,
    alignment=TA_CENTER, textColor=INK,
))


def P(text, style="Body"):
    return Paragraph(text, styles[style])


def bullet(text, color="#6C43FF"):
    return Paragraph(f'<font color="{color}">●</font>&nbsp;&nbsp;{text}', styles["Body"])


def section(kicker, title, lead=None):
    blocks = [P(kicker.upper(), "Eyebrow"), P(title, "H2")]
    if lead:
        blocks.append(P(lead, "Body"))
    return blocks


def info_box(title, text, color=PURPLE, background=PURPLE_PALE):
    table = Table([[P(title, "H3")], [P(text, "Body")]], colWidths=[174 * mm])
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


def data_table(rows, widths, header=True):
    data = []
    for row_index, row in enumerate(rows):
        style = "HeaderCell" if header and row_index == 0 else "Cell"
        data.append([P(cell, style) for cell in row])
    table = Table(data, colWidths=widths, repeatRows=1 if header else 0)
    commands = [
        ("GRID", (0, 0), (-1, -1), 0.55, LINE),
        ("ROWBACKGROUNDS", (0, 1 if header else 0), (-1, -1), [WHITE, colors.HexColor("#FBFBFD")]),
        ("LEFTPADDING", (0, 0), (-1, -1), 3 * mm),
        ("RIGHTPADDING", (0, 0), (-1, -1), 3 * mm),
        ("TOPPADDING", (0, 0), (-1, -1), 2.6 * mm),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 2.6 * mm),
        ("VALIGN", (0, 0), (-1, -1), "TOP"),
    ]
    if header:
        commands += [
            ("BACKGROUND", (0, 0), (-1, 0), PURPLE_DARK),
            ("TEXTCOLOR", (0, 0), (-1, 0), WHITE),
        ]
    table.setStyle(TableStyle(commands))
    return table


def page_background(canvas, doc):
    page = canvas.getPageNumber()
    canvas.saveState()
    if page == 1:
        canvas.setFillColor(PURPLE_DARK)
        canvas.rect(0, 0, PAGE_W, PAGE_H, stroke=0, fill=1)
        canvas.setFillColor(PURPLE)
        canvas.circle(PAGE_W - 12 * mm, 22 * mm, 72 * mm, stroke=0, fill=1)
        canvas.setFillColor(colors.Color(1, 1, 1, alpha=0.06))
        canvas.circle(PAGE_W - 22 * mm, PAGE_H - 25 * mm, 48 * mm, stroke=0, fill=1)
    else:
        canvas.setFillColor(BG)
        canvas.rect(0, 0, PAGE_W, PAGE_H, stroke=0, fill=1)
        canvas.setStrokeColor(LINE)
        canvas.line(18 * mm, PAGE_H - 15 * mm, PAGE_W - 18 * mm, PAGE_H - 15 * mm)
        canvas.setFont("Arial-Bold", 8)
        canvas.setFillColor(PURPLE)
        canvas.drawString(18 * mm, PAGE_H - 11 * mm, "AI-АССИСТИРОВАННАЯ РАЗРАБОТКА")
        canvas.setFont("Arial", 8)
        canvas.setFillColor(MUTED)
        canvas.drawRightString(PAGE_W - 18 * mm, 10 * mm, str(page))
    canvas.restoreState()


doc = BaseDocTemplate(
    str(OUTPUT), pagesize=A4,
    leftMargin=18 * mm, rightMargin=18 * mm,
    topMargin=22 * mm, bottomMargin=16 * mm,
    title="Почему AI-ассистированная разработка может быть лучше только ручного кода",
    author="IT Софт",
)
frame = Frame(doc.leftMargin, doc.bottomMargin, doc.width, doc.height, id="main", showBoundary=0)
doc.addPageTemplates([PageTemplate(id="all", frames=[frame], onPage=page_background)])

story = []

# 1. Cover
story += [Spacer(1, 33 * mm)]
story.append(P("АРГУМЕНТЫ ДЛЯ ЗАКАЗЧИКА", "Eyebrow"))
story.append(P("Почему сайт с AI-ассистентом<br/>может быть лучше<br/>только ручной разработки", "CoverH1"))
story.append(P(
    "Нейтральное объяснение без рекламных обещаний: что именно улучшает нейросеть, "
    "какие риски она создаёт и по каким проверяемым критериям нужно принимать результат.",
    "CoverLead",
))
cover = Table([
    [P("НЕ СПОСОБ НАБОРА", "CoverCell"), P("А ПРОЦЕСС", "CoverCell"), P("И РЕЗУЛЬТАТ", "CoverCell")],
    [P("Браузер не знает, кто написал код", "CoverSmall"),
     P("Архитектура, ревью, тесты и контроль", "CoverSmall"),
     P("Скорость, надёжность, SEO и удобство", "CoverSmall")],
], colWidths=[56 * mm] * 3)
cover.setStyle(TableStyle([
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
story += [Spacer(1, 7 * mm), cover, Spacer(1, 32 * mm)]
story.append(P(
    "Главный тезис: качественный сайт создаёт не «нейросеть вместо человека». Его создаёт специалист, "
    "который использует AI как ускоритель анализа и реализации, а затем проверяет результат теми же или "
    "более строгими инженерными методами, что и при обычной разработке.",
    "CoverLead",
))
story.append(PageBreak())

# 2. Framing
story += section(
    "Что важно понять",
    "Браузер не различает «ручной» и «нейросетевой» код",
    "После публикации браузер получает обычные HTML, CSS, JavaScript, изображения и ответы сервера. "
    "Он измеряет скорость, стабильность и ошибки, но не происхождение строк кода.",
)
story.append(info_box(
    "Поэтому корректный вопрос звучит иначе",
    "Не «использовалась ли нейросеть?», а «соответствует ли сайт архитектуре, требованиям, тестам, "
    "безопасности, производительности и бизнес-сценариям?». Способ подготовки кода сам по себе не является "
    "ни гарантией качества, ни доказательством плохого результата.",
))
story += [Spacer(1, 5 * mm), P("Что действительно делает сайт тяжёлым", "H3")]
for item in [
    "слишком большие изображения, видео и шрифты;",
    "лишние плагины, аналитические скрипты и виджеты;",
    "дублирование CSS и JavaScript, тяжёлые библиотеки и неиспользуемый код;",
    "медленная база данных, отсутствие кэша, сжатия и оптимизации сервера;",
    "ошибочная загрузка всех ресурсов сразу вместо загрузки по необходимости.",
]:
    story.append(bullet(item))
story += [Spacer(1, 3 * mm), P("Что не делает сайт тяжёлым автоматически", "H3")]
for item in [
    "использование AI-ассистента;",
    "ручной или автоматический способ набора одинакового итогового кода;",
    "количество часов, потраченных разработчиком;",
    "сам факт применения WordPress, фреймворка или готовой библиотеки.",
]:
    story.append(bullet(item, "#08785B"))
story.append(Spacer(1, 4 * mm))
story.append(info_box(
    "Вывод",
    "Тяжесть и качество измеряются после сборки и публикации: весом ресурсов, количеством запросов, "
    "Core Web Vitals, ошибками, безопасностью и прохождением пользовательских сценариев.",
    color=GREEN, background=GREEN_BG,
))
story.append(PageBreak())

# 3. Benefits
story += section(
    "Почему результат может быть лучше",
    "AI расширяет возможности одного сильного специалиста",
    "При дисциплинированном процессе AI не заменяет экспертизу, а позволяет быстрее выполнять больше "
    "проверок, вариантов и однотипной работы.",
)
benefits = [
    ["Возможность", "Что меняется в работе", "Польза для сайта"],
    ["Быстрое сравнение вариантов", "Можно проверить несколько архитектурных и интерфейсных решений", "Меньше случайных решений; быстрее находится удачный вариант"],
    ["Единые компоненты", "AI помогает находить дубли и переносить правила в общие стили", "Меньше разнобоя, ниже вес и проще поддержка"],
    ["Автоматизация тестов", "Быстрее создаются проверки форм, корзины, мобильной версии и ошибок", "Регрессии обнаруживаются до публикации"],
    ["Многопрофильная проверка", "Код можно одновременно проверить на SEO, доступность, безопасность и скорость", "Меньше слепых зон узкой специализации"],
    ["Рефакторинг", "Проще разбирать большой объём повторяющегося кода небольшими шагами", "Чище структура без изменения функций"],
    ["Документация", "Инструкции по развёртыванию, резервному копированию и обновлению создаются вместе с кодом", "Проект меньше зависит от памяти одного разработчика"],
    ["Быстрый цикл обратной связи", "Замечание можно быстро исправить, проверить и сравнить", "Больше итераций в том же бюджете"],
]
story.append(data_table(benefits, [41 * mm, 66 * mm, 67 * mm]))
story += [Spacer(1, 5 * mm)]
story.append(info_box(
    "Ключевое преимущество",
    "В ручной разработке часть бюджета уходит на механическое написание шаблонного кода. AI позволяет "
    "перенести больше времени на архитектуру, приёмку, тестирование, анализ реального поведения и исправление "
    "деталей - то есть на работу, которая заметна пользователю и бизнесу.",
))
story.append(PageBreak())

# 4. Process
story += section(
    "Как делать правильно",
    "AI-разработка должна быть строже, а не бесконтрольнее",
    "Качественный процесс строится как конвейер с проверками. Нейросеть предлагает решение, но решение "
    "принимает специалист, а фактический результат подтверждают инструменты.",
)
process = [
    ["Этап", "Роль человека", "Роль AI", "Контрольный результат"],
    ["1. Требования", "Определяет цели, ограничения и сценарии", "Структурирует требования, находит противоречия", "Согласованные критерии приёмки"],
    ["2. Архитектура", "Выбирает стек, данные, границы компонентов", "Предлагает варианты и риски", "План без лишней сложности"],
    ["3. Реализация", "Делит работу на малые изменения и проверяет diff", "Пишет черновик, повторяющийся код и тесты", "Понятный код в системе контроля версий"],
    ["4. Автопроверки", "Задаёт обязательные пороги", "Помогает исправлять найденные ошибки", "Сборка, линтеры и тесты проходят"],
    ["5. Ручная приёмка", "Проходит реальные сценарии на устройствах", "Готовит чек-листы и диагностирует дефекты", "Функции работают для пользователя"],
    ["6. Публикация", "Контролирует бэкап, миграции и откат", "Автоматизирует повторяемые команды", "Воспроизводимое безопасное обновление"],
    ["7. Мониторинг", "Оценивает метрики и обращения", "Сводит данные и ищет регрессии", "Качество подтверждено после запуска"],
]
story.append(data_table(process, [27 * mm, 52 * mm, 48 * mm, 47 * mm]))
story += [Spacer(1, 5 * mm)]
story.append(P("Обязательные предохранители", "H3"))
for item in [
    "каждое изменение хранится в Git и может быть просмотрено или отменено;",
    "никакие секреты, пароли и реальные персональные данные не передаются в публичные модели;",
    "код проходит линтеры, тесты, проверку зависимостей и ручное ревью;",
    "оплата, авторизация, права доступа и обработка данных проверяются отдельно;",
    "резервная копия и откат проверяются до обновления рабочего сайта.",
]:
    story.append(bullet(item))
story.append(PageBreak())

# 5. Comparison and objections
story += section(
    "Сравнение подходов",
    "«Ручной код» не равен качеству, а AI не равен халтуре",
)
comparison = [
    ["Критерий", "Только ручной процесс", "AI без контроля", "AI + инженерный контроль"],
    ["Скорость работы", "Средняя или низкая", "Высокая", "Высокая"],
    ["Риск шаблонных ошибок", "Зависит от усталости и опыта", "Высокий", "Снижается тестами и ревью"],
    ["Последовательность интерфейса", "Зависит от дисциплины", "Часто хаотичная", "Высокая при общей дизайн-системе"],
    ["Тестовое покрытие", "Часто сокращается из-за бюджета", "Может отсутствовать", "Проще сделать широким"],
    ["Документация", "Часто откладывается", "Может быть недостоверной", "Создаётся и сверяется вместе с кодом"],
    ["Поддерживаемость", "От низкой до высокой", "Низкая", "Высокая при малых diff и правилах"],
    ["Итоговое качество", "Определяется процессом", "Обычно нестабильное", "Определяется процессом и измерениями"],
]
story.append(data_table(comparison, [38 * mm, 45 * mm, 42 * mm, 49 * mm]))
story += [Spacer(1, 5 * mm), P("Частые возражения", "H3")]
objections = [
    ("«Нейросеть делает тяжёлый код»", "Иногда - если принимать ответ без проверки. Но вес измеряется, а лишние зависимости, стили и ресурсы удаляются на этапе оптимизации."),
    ("«Ручная работа надёжнее»", "Надёжнее не ручной набор, а тесты, ревью, контроль версий, мониторинг и возможность отката. Человек тоже ошибается."),
    ("«AI просто собирает чужой код»", "Модель формирует новый ответ по изученным закономерностям. Для проекта всё равно обязательны проверка лицензий зависимостей и отсутствие слепого копирования."),
    ("«Такой сайт нельзя поддерживать»", "Нельзя поддерживать хаотичный проект. Если есть компоненты, правила, тесты, документация и Git, происхождение черновика не мешает сопровождению."),
    ("«Раз AI ускоряет работу, результат должен стоить почти бесплатно»", "Оплачивается не скорость печати, а ответственность за архитектуру, результат, проверку, исправления, инфраструктуру и риски."),
]
for title, answer in objections:
    story.append(P(title, "H3"))
    story.append(P(answer, "Body"))
story.append(PageBreak())

# 6. Acceptance criteria
story += section(
    "Как доказать качество",
    "Принимать нужно результат по измеримым критериям",
    "Это честнее и для заказчика, и для исполнителя: вместо спора о происхождении кода проверяется то, "
    "что получает пользователь и бизнес.",
)
acceptance = [
    ["Область", "Что предъявляется на приёмке"],
    ["Скорость", "Lighthouse в нескольких прогонах, Core Web Vitals после накопления реальных данных, вес и запросы Network"],
    ["Функции", "Проверка каталога, поиска, корзины, оформления, оплаты, писем, ключей, кабинета и ошибок"],
    ["Мобильная версия", "Нет горизонтального скролла, наложений и недоступных кнопок; проверка на реальных ширинах"],
    ["SEO", "Корректные title, description, H1, canonical, robots, sitemap, schema и HTTP-статусы"],
    ["Доступность", "Клавиатура, фокус, подписи полей, контраст, alt-тексты, масштабирование"],
    ["Безопасность", "HTTPS, права, отсутствие секретов в Git, обновлённые зависимости, защита форм и резервные копии"],
    ["Код", "Понятная структура, общие компоненты, линтеры, тесты, контролируемые зависимости, отсутствие мёртвого кода"],
    ["Эксплуатация", "Инструкция развёртывания, одна воспроизводимая команда обновления, бэкап и проверенный откат"],
]
story.append(data_table(acceptance, [46 * mm, 128 * mm]))
story += [Spacer(1, 5 * mm)]
story.append(info_box(
    "Правило объективной приёмки",
    "Если два сайта одинаково проходят функциональные тесты, имеют одинаковую безопасность, скорость, SEO "
    "и поддерживаемость, нельзя объявить один хуже только потому, что при его разработке использовался AI. "
    "Если AI-вариант проходит больше проверок, быстрее исправляется и лучше документирован, он объективно лучше.",
    color=GREEN, background=GREEN_BG,
))
story += [Spacer(1, 4 * mm), P("Что является красным флагом", "H3")]
for item in [
    "исполнитель не может объяснить архитектуру и назначение кода;",
    "изменения огромные, смешанные и не проверяются по частям;",
    "нет тестов, бэкапа, истории Git и понятной инструкции запуска;",
    "обещается идеальное качество только на основании использования AI;",
    "ошибки скрываются, а метрики показываются одним удачным запуском.",
]:
    story.append(bullet(item, "#AE2945"))
story.append(PageBreak())

# 7. Evidence and conclusion
story += section(
    "Доказательства и границы",
    "Что показывают исследования - и чего они не доказывают",
)
evidence = [
    ["Источник", "Что полезно из результата", "Ограничение"],
    ["GitHub, контролируемый эксперимент Copilot", "Участники завершили заданную задачу в среднем быстрее; в опубликованном исследовании сообщалось о 55% ускорении", "Это отдельная задача, а не доказательство качества любого готового сайта"],
    ["GitHub, исследование качества кода", "AI-ассистирование может улучшать прохождение тестов и вероятность одобрения кода при контролируемом процессе", "Результат зависит от задачи, модели и проверки человеком"],
    ["DORA 2024", "AI связан с ростом производительности и удовлетворённости отдельных разработчиков", "Организационные результаты неоднозначны; без процессов возможны проблемы со стабильностью поставки"],
    ["NIST SSDF", "Качество и безопасность строятся на требованиях, проверках, управлении изменениями и уязвимостями", "Стандарт не утверждает, что конкретный инструмент автоматически безопасен"],
]
story.append(data_table(evidence, [45 * mm, 77 * mm, 52 * mm]))
story += [Spacer(1, 5 * mm)]
story.append(info_box(
    "Честный итог",
    "AI не гарантирует хороший сайт. Но сильный разработчик с AI, тестами и ясными критериями способен сделать "
    "больше полезных итераций, шире проверить результат и быстрее устранить дефекты, чем тот же разработчик, "
    "который тратит время только на ручной набор. Преимущество создаёт связка «эксперт + AI + проверки».",
    color=PURPLE, background=PURPLE_PALE,
))
story += [Spacer(1, 5 * mm), P("Короткая формулировка для разговора", "H3")]
story.append(P(
    "«Мы не предлагаем заменить разработку генерацией. Мы используем нейросеть как инженерный инструмент, "
    "а качество подтверждаем код-ревью, тестами, Lighthouse, проверкой безопасности, мобильной версии и "
    "полного пользовательского сценария. Заказчик принимает не способ печати кода, а измеримый результат»."
))
story += [Spacer(1, 5 * mm), P("Источники", "H3")]
sources = [
    "GitHub - исследование продуктивности Copilot: https://github.blog/news-insights/research/research-quantifying-github-copilots-impact-on-developer-productivity-and-happiness/",
    "GitHub - исследование качества кода: https://github.blog/news-insights/research/does-github-copilot-improve-code-quality-heres-what-the-data-says/",
    "DORA 2024: https://dora.dev/research/2024/dora-report/",
    "NIST Secure Software Development Framework: https://csrc.nist.gov/pubs/sp/800/218/final",
    "OWASP Application Security Verification Standard: https://owasp.org/www-project-application-security-verification-standard/",
    "W3C Web Content Accessibility Guidelines 2.2: https://www.w3.org/TR/WCAG22/",
    "Core Web Vitals: https://web.dev/articles/vitals",
]
for source in sources:
    story.append(P(source, "Small"))
story += [Spacer(1, 4 * mm)]
story.append(P(
    "Версия документа: 28 октября 2026 года. Документ не утверждает, что любой AI-код лучше ручного. "
    "Он описывает условия, при которых AI-ассистированная разработка даёт проверяемое преимущество.",
    "Small",
))

doc.build(story)
print(OUTPUT)
