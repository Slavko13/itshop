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
OUTPUT = ROOT / "output" / "pdf" / "AI_Client_Dialogue_Guide_RU.pdf"
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
    name="Body", fontName="Arial", fontSize=9.3, leading=13.4,
    textColor=TEXT, spaceAfter=2.5 * mm,
))
styles.add(ParagraphStyle(
    name="Small", fontName="Arial", fontSize=7.6, leading=10.5,
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
    name="H3", fontName="Arial-Bold", fontSize=11.4, leading=14.2,
    textColor=INK, spaceAfter=1.4 * mm,
))
styles.add(ParagraphStyle(
    name="Eyebrow", fontName="Arial-Bold", fontSize=7.5, leading=9,
    textColor=PURPLE, tracking=1.1, spaceAfter=2 * mm,
))
styles.add(ParagraphStyle(
    name="CoverH1", fontName="Arial-Bold", fontSize=32, leading=35,
    textColor=WHITE, spaceAfter=6 * mm,
))
styles.add(ParagraphStyle(
    name="CoverLead", fontName="Arial", fontSize=12, leading=17,
    textColor=colors.HexColor("#E0DCF3"), spaceAfter=6 * mm,
))
styles.add(ParagraphStyle(
    name="Quote", fontName="Arial", fontSize=10, leading=14.5,
    textColor=INK,
))
styles.add(ParagraphStyle(
    name="Cell", fontName="Arial", fontSize=8, leading=10.5,
    textColor=TEXT,
))
styles.add(ParagraphStyle(
    name="HeaderCell", fontName="Arial-Bold", fontSize=8, leading=10.5,
    textColor=WHITE,
))
styles.add(ParagraphStyle(
    name="CoverSmall", fontName="Arial", fontSize=7.6, leading=10.5,
    textColor=colors.HexColor("#E0DCF3"),
))
styles.add(ParagraphStyle(
    name="Number", fontName="Arial-Bold", fontSize=11, leading=13,
    alignment=TA_CENTER, textColor=PURPLE,
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


def box(title, text, color=PURPLE, background=PURPLE_PALE):
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


def quote_box(label, text, background=WHITE, color=PURPLE):
    table = Table([[P(label.upper(), "Eyebrow")], [P(text, "Quote")]], colWidths=[174 * mm])
    table.setStyle(TableStyle([
        ("BACKGROUND", (0, 0), (-1, -1), background),
        ("BOX", (0, 0), (-1, -1), 0.7, LINE),
        ("LINEBEFORE", (0, 0), (0, -1), 4, color),
        ("LEFTPADDING", (0, 0), (-1, -1), 5 * mm),
        ("RIGHTPADDING", (0, 0), (-1, -1), 5 * mm),
        ("TOPPADDING", (0, 0), (0, 0), 3.5 * mm),
        ("BOTTOMPADDING", (0, 0), (0, 0), 1 * mm),
        ("TOPPADDING", (0, 1), (0, 1), 0),
        ("BOTTOMPADDING", (0, 1), (0, 1), 4 * mm),
    ]))
    return table


def data_table(rows, widths):
    data = []
    for row_index, row in enumerate(rows):
        style = "HeaderCell" if row_index == 0 else "Cell"
        data.append([P(value, style) for value in row])
    table = Table(data, colWidths=widths, repeatRows=1)
    table.setStyle(TableStyle([
        ("BACKGROUND", (0, 0), (-1, 0), PURPLE_DARK),
        ("GRID", (0, 0), (-1, -1), 0.55, LINE),
        ("ROWBACKGROUNDS", (0, 1), (-1, -1), [WHITE, colors.HexColor("#FBFBFD")]),
        ("LEFTPADDING", (0, 0), (-1, -1), 3 * mm),
        ("RIGHTPADDING", (0, 0), (-1, -1), 3 * mm),
        ("TOPPADDING", (0, 0), (-1, -1), 2.8 * mm),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 2.8 * mm),
        ("VALIGN", (0, 0), (-1, -1), "TOP"),
    ]))
    return table


def page_background(canvas, doc):
    page = canvas.getPageNumber()
    canvas.saveState()
    if page == 1:
        canvas.setFillColor(PURPLE_DARK)
        canvas.rect(0, 0, PAGE_W, PAGE_H, stroke=0, fill=1)
        canvas.setFillColor(PURPLE)
        canvas.circle(PAGE_W - 13 * mm, 23 * mm, 72 * mm, stroke=0, fill=1)
        canvas.setFillColor(colors.Color(1, 1, 1, alpha=0.06))
        canvas.circle(PAGE_W - 20 * mm, PAGE_H - 22 * mm, 48 * mm, stroke=0, fill=1)
    else:
        canvas.setFillColor(BG)
        canvas.rect(0, 0, PAGE_W, PAGE_H, stroke=0, fill=1)
        canvas.setStrokeColor(LINE)
        canvas.line(18 * mm, PAGE_H - 15 * mm, PAGE_W - 18 * mm, PAGE_H - 15 * mm)
        canvas.setFont("Arial-Bold", 8)
        canvas.setFillColor(PURPLE)
        canvas.drawString(18 * mm, PAGE_H - 11 * mm, "РАЗГОВОР С ЗАКАЗЧИКОМ ОБ AI")
        canvas.setFont("Arial", 8)
        canvas.setFillColor(MUTED)
        canvas.drawRightString(PAGE_W - 18 * mm, 10 * mm, str(page))
    canvas.restoreState()


doc = BaseDocTemplate(
    str(OUTPUT), pagesize=A4,
    leftMargin=18 * mm, rightMargin=18 * mm,
    topMargin=22 * mm, bottomMargin=16 * mm,
    title="Как аргументированно обсуждать AI-разработку с заказчиком",
    author="IT Софт",
)
frame = Frame(doc.leftMargin, doc.bottomMargin, doc.width, doc.height, id="main", showBoundary=0)
doc.addPageTemplates([PageTemplate(id="all", frames=[frame], onPage=page_background)])

story = []

# Page 1
story += [Spacer(1, 36 * mm)]
story.append(P("ПРАКТИЧЕСКАЯ ШПАРГАЛКА", "Eyebrow"))
story.append(P("Как спокойно и убедительно<br/>обсуждать AI-разработку<br/>с заказчиком", "CoverH1"))
story.append(P(
    "Готовые формулировки для разговора: как признать разумные опасения, не оправдываться, "
    "перевести спор к измеримым критериям и предложить честную проверку результата.",
    "CoverLead",
))
story += [Spacer(1, 9 * mm)]
cover = Table([
    [P("НЕ СПОРИТЬ", "HeaderCell"), P("НЕ ОПРАВДЫВАТЬСЯ", "HeaderCell"), P("ПОКАЗЫВАТЬ ФАКТЫ", "HeaderCell")],
    [P("Сначала признать опасение", "CoverSmall"), P("Спокойно объяснить процесс", "CoverSmall"), P("Метрики, тесты и приёмка", "CoverSmall")],
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
story.append(cover)
story += [Spacer(1, 38 * mm)]
story.append(P(
    "Цель разговора - не убедить заказчика «полюбить нейросети». Цель - согласовать, что качество "
    "оценивается по работоспособности, скорости, безопасности, поддерживаемости и выполнению требований.",
    "CoverLead",
))
story.append(PageBreak())

# Page 2
story += section(
    "Базовая стратегия",
    "Не защищайте AI. Защищайте проверяемый результат",
    "Если сразу отвечать «вы не правы», заказчик будет защищать свою позицию. Сильнее сначала признать "
    "разумную часть опасения, а затем предложить объективный способ проверки.",
)
steps = [
    [P("1", "Number"), P("Признать риск", "H3"), P("«Да, бесконтрольно сгенерированный код действительно может быть тяжёлым и нестабильным»."),],
    [P("2", "Number"), P("Уточнить процесс", "H3"), P("«Но у нас AI используется как инструмент, а решения, проверка и ответственность остаются на специалисте»."),],
    [P("3", "Number"), P("Сменить рамку", "H3"), P("«Предлагаю оценивать не способ набора строк, а фактическое качество сайта»."),],
    [P("4", "Number"), P("Назвать доказательства", "H3"), P("«Проверим скорость, вес, ошибки, мобильную версию, SEO, безопасность и полный сценарий покупки»."),],
    [P("5", "Number"), P("Предложить действие", "H3"), P("«Если найдём несоответствия, зафиксируем их и исправим по согласованным критериям»."),],
]
steps_table = Table(steps, colWidths=[14 * mm, 48 * mm, 112 * mm])
steps_table.setStyle(TableStyle([
    ("BACKGROUND", (0, 0), (-1, -1), WHITE),
    ("GRID", (0, 0), (-1, -1), 0.55, LINE),
    ("BACKGROUND", (0, 0), (0, -1), PURPLE_PALE),
    ("LEFTPADDING", (0, 0), (-1, -1), 3 * mm),
    ("RIGHTPADDING", (0, 0), (-1, -1), 3 * mm),
    ("TOPPADDING", (0, 0), (-1, -1), 3.2 * mm),
    ("BOTTOMPADDING", (0, 0), (-1, -1), 3.2 * mm),
    ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
]))
story.append(steps_table)
story += [Spacer(1, 6 * mm)]
story.append(box(
    "Главное правило",
    "Не обещайте, что AI всегда пишет лучше человека. Говорите точнее: AI позволяет быстрее выполнять "
    "механическую работу и больше времени уделять проверкам, но качество появляется только вместе с "
    "архитектурой, ревью, тестами и ручной приёмкой.",
    color=GREEN, background=GREEN_BG,
))
story.append(PageBreak())

# Page 3
story += section(
    "Как начать разговор",
    "Три готовых варианта вступления",
    "Выбирайте тон по ситуации. Все варианты признают опасение, но не принимают недоказанное утверждение "
    "о плохом качестве проекта.",
)
story.append(quote_box(
    "Мягкий вариант",
    "«Я понимаю ваше опасение: бесконтрольно сгенерированный код действительно может быть тяжёлым и "
    "плохо поддерживаемым. В нашем случае нейросеть не заменяет разработку. Она используется как инструмент, "
    "а архитектура, проверка кода, тестирование и ответственность остаются на исполнителе»."
))
story += [Spacer(1, 5 * mm)]
story.append(quote_box(
    "Уверенный деловой вариант",
    "«Предлагаю разделить инструмент разработки и качество результата. Браузер получает обычные HTML, CSS "
    "и JavaScript и оценивает их скорость, стабильность и ошибки. Давайте зафиксируем технические и "
    "функциональные критерии и примем сайт по ним»."
))
story += [Spacer(1, 5 * mm)]
story.append(quote_box(
    "Если разговор уже конфликтный",
    "«Я не предлагаю спорить о нейросетях на уровне мнений. Давайте выберем страницы и сценарии, проведём "
    "замеры, проверим мобильную версию, корзину, оплату, письма, кабинет и поддержку проекта. Все фактические "
    "несоответствия будут исправлены»."
))
story += [Spacer(1, 6 * mm)]
story.append(box(
    "Правильная интонация",
    "Спокойная и предметная. Не высмеивайте заказчика и не пытайтесь «победить». Ваша задача - показать, "
    "что вы слышите риск, умеете его проверить и готовы отвечать за результат.",
    color=AMBER, background=AMBER_BG,
))
story.append(PageBreak())

# Page 4
story += section(
    "Ответы на возражения",
    "Про тяжёлый код, надёжность и ручную работу",
)
objections_a = [
    ["Возражение", "Как отвечать"],
    ["«ИИ делает тяжёлые сайты»", "«Вес определяется изображениями, CSS, JavaScript, запросами, плагинами и настройкой сервера. Всё это измеряется через Lighthouse и Network. Предлагаю сравнить конкретные цифры»."],
    ["«Ручной код надёжнее»", "«Ручной набор сам по себе ничего не гарантирует. Надёжность обеспечивают Git, ревью, тесты, резервные копии, мониторинг и возможность отката»."],
    ["«Мы договаривались о ручной работе»", "«Я понимаю, что вы ожидали индивидуальную разработку, а не автоматически собранный шаблон. Сайт разрабатывается под проект. AI ускоряет отдельные операции, но не отменяет ручное проектирование, проверку и ответственность»."],
    ["«Нейросеть переделала всю тему»", "«Важно проверить не формулировку, а объём и качество изменений: какие файлы изменены, сохранена ли функциональность, есть ли тесты и можно ли безопасно откатить обновление»."],
]
story.append(data_table(objections_a, [53 * mm, 121 * mm]))
story += [Spacer(1, 6 * mm)]
story.append(box(
    "Если прямого запрета на AI не было",
    "«Если использование конкретных инструментов должно было быть ограничено, это следовало отдельно "
    "закрепить в договоре или техническом задании. При этом я готов подтвердить качество результата "
    "объективной приёмкой и исправить фактические несоответствия»."
))
story.append(PageBreak())

# Page 5
story += section(
    "Ответы на возражения",
    "Про поддержку, чужой код и стоимость",
)
objections_b = [
    ["Возражение", "Как отвечать"],
    ["«Такой код невозможно поддерживать»", "«Невозможно поддерживать хаотичный проект независимо от того, кем он написан. Поддерживаемость дают структура, общие компоненты, документация, Git, тесты и понятное развёртывание»."],
    ["«AI просто собирает чужой код»", "«Для проекта всё равно проверяются лицензии зависимостей и запрещается слепое копирование. AI создаёт черновик, но в проект попадает только просмотренное и проверенное изменение»."],
    ["«Вы просто нажали кнопку»", "«Оплачивается не скорость печати строк. В стоимость входят требования, архитектура, интеграции, тестирование, исправления, сервер, безопасность, публикация и ответственность за результат»."],
    ["«Я видел плохие сайты на нейросетях»", "«Такие сайты действительно есть, как и плохие сайты, написанные вручную. Отдельные примеры характеризуют процесс конкретного исполнителя. Предлагаю проверить именно наш проект по согласованным критериям»."],
    ["«Значит, работа должна стоить почти бесплатно»", "«Инструмент может ускорить часть операций, но не снимает ответственность, риски и объём проверки. Заказчик оплачивает работающий результат, а не количество нажатий на клавиатуру»."],
]
story.append(data_table(objections_b, [53 * mm, 121 * mm]))
story += [Spacer(1, 6 * mm)]
story.append(quote_box(
    "Самая сильная позиция",
    "«Давайте не будем доказывать качество словами. Зафиксируем страницы, пользовательские сценарии и "
    "технические пороги. Проведём замеры и проверку. Все обнаруженные несоответствия исправим. Это даст "
    "объективный ответ, а не спор о способе разработки»."
))
story.append(PageBreak())

# Page 6
story += section(
    "Что говорить нельзя",
    "Фразы, которые превращают обсуждение в конфликт",
)
bad_good = [
    ["Неудачная фраза", "Почему вредно", "Чем заменить"],
    ["«Сейчас все работают с AI»", "Не доказывает качество вашего проекта", "«Покажем процесс проверки и фактические результаты»."],
    ["«Ручная разработка устарела»", "Обесценивает ожидания заказчика", "«У каждого инструмента есть место; важны контроль и результат»."],
    ["«Вы просто не понимаете»", "Переводит спор на личности", "«Давайте разберём конкретные риски по пунктам»."],
    ["«AI пишет лучше программиста»", "Это невозможно гарантировать", "«AI ускоряет работу специалиста, но результат требует проверки»."],
    ["«Lighthouse зелёный, значит всё идеально»", "Lighthouse не проверяет всю бизнес-логику", "«Кроме метрик пройдём оплату, письма, кабинет и мобильные сценарии»."],
]
story.append(data_table(bad_good, [45 * mm, 61 * mm, 68 * mm]))
story += [Spacer(1, 6 * mm)]
story.append(P("Перед ответом проверьте", "H3"))
for item in [
    "есть ли в договоре прямой запрет на AI или обязательство писать всё вручную;",
    "какие конкретные дефекты называет заказчик, а какие выводы делает без измерений;",
    "есть ли актуальные Lighthouse-замеры, функциональные тесты, Git и бэкап;",
    "можете ли вы объяснить архитектуру и каждое существенное изменение;",
    "готовы ли вы спокойно признать и исправить реальные недостатки.",
]:
    story.append(bullet(item))
story += [Spacer(1, 4 * mm)]
story.append(box(
    "Граница, которую важно сохранить",
    "Не соглашайтесь с формулировкой «AI использовался, значит работа плохая». Соглашайтесь проверять "
    "каждое конкретное замечание. Если в договоре действительно обещан полностью ручной процесс, это уже "
    "вопрос исполнения договорённостей, а не технического качества сайта.",
    color=RED, background=RED_BG,
))
story.append(PageBreak())

# Page 7
story += section(
    "Готовое сообщение",
    "Текст, который можно отправить заказчику",
)
story.append(quote_box(
    "Сообщение",
    "«Я понимаю ваше опасение и частично с ним согласен: если бесконтрольно генерировать код и сразу "
    "публиковать его, результат действительно может быть тяжёлым и ненадёжным. Но в нашем проекте AI "
    "используется как вспомогательный инженерный инструмент, а не как замена разработчика. Решения "
    "проверяются, дорабатываются и тестируются вручную.<br/><br/>"
    "Предлагаю оценивать проект по объективным показателям: весу страниц, скорости загрузки, Core Web Vitals, "
    "отсутствию ошибок, качеству мобильной версии, SEO, безопасности и прохождению полного сценария покупки. "
    "Также можно проверить структуру проекта, обновление, резервное копирование и возможность дальнейшей "
    "поддержки.<br/><br/>"
    "Если по этим критериям будут обнаружены недостатки, мы их зафиксируем и исправим. Сам факт применения "
    "AI не означает, что сайт автоматически тяжелее или хуже: браузер получает обычные HTML, CSS и JavaScript "
    "и оценивает их фактическое качество. Такой подход позволит обсуждать конкретный результат, а не "
    "предположения о способе разработки»."
))
story += [Spacer(1, 7 * mm)]
story.append(box(
    "Финальная формула разговора",
    "Признать риск -> объяснить процесс -> назвать критерии -> показать доказательства -> предложить проверку "
    "и исправление. Не спорить о вере в AI. Обсуждать измеримый результат и договорённости.",
    color=GREEN, background=GREEN_BG,
))
story += [Spacer(1, 7 * mm)]
story.append(P("Короткая версия на одну фразу", "H3"))
story.append(P(
    "«Нейросеть не является доказательством качества, но и не является доказательством плохого кода; "
    "качество подтверждается архитектурой, тестами, метриками и работающим пользовательским сценарием»."
))
story += [Spacer(1, 10 * mm)]
story.append(P(
    "Версия памятки: 28 октября 2026 года. Материал предназначен для делового обсуждения и не заменяет "
    "анализ конкретного договора или технического задания.",
    "Small",
))

doc.build(story)
print(OUTPUT)
