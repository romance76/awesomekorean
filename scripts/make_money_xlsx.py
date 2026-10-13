"""MONEY 내려받기용 엑셀 파일(.xlsx) 생성기.

실행: python scripts/make_money_xlsx.py   → public/downloads/money/*.xlsx

원칙
- 노란 칸 = 내가 숫자·글을 넣는 곳, 초록 칸 = 자동 계산(건드리지 않기), 회색 글 = 설명
- 계산 칸은 잠금(시트 보호, 비밀번호 없음), 노란 칸만 잠금 해제 → 실수로 수식을 지우지 않음
- 숫자 칸이 비어 있어도 #DIV/0! 같은 오류가 나지 않게 IF 로 감쌈
- 예시 숫자는 "예시" 라고 밝히고 바꿔 쓰게 함
"""
from pathlib import Path
from openpyxl import Workbook
from openpyxl.styles import Alignment, Border, Font, PatternFill, Protection, Side
from openpyxl.worksheet.datavalidation import DataValidation
from openpyxl.utils import get_column_letter
from openpyxl.worksheet.properties import PageSetupProperties

OUT = Path(__file__).resolve().parent.parent / 'public' / 'downloads' / 'money'
GREEN = '047857'
F_TITLE = Font(name='Malgun Gothic', size=18, bold=True, color=GREEN)
F_TEXT = Font(name='Malgun Gothic', size=12)
F_BOLD = Font(name='Malgun Gothic', size=12, bold=True)
F_HEAD = Font(name='Malgun Gothic', size=12, bold=True, color='FFFFFF')
F_NOTE = Font(name='Malgun Gothic', size=11, color='6B7280')
F_RES = Font(name='Malgun Gothic', size=13, bold=True, color='065F46')
F_INP = Font(name='Malgun Gothic', size=12, color='1D4ED8')
FILL_HEAD = PatternFill('solid', fgColor=GREEN)
FILL_IN = PatternFill('solid', fgColor='FFF9C4')
FILL_CALC = PatternFill('solid', fgColor='E8F5E9')
FILL_SEC = PatternFill('solid', fgColor='F3F4F6')
THIN = Side(style='thin', color='D1D5DB')
BORDER = Border(left=THIN, right=THIN, top=THIN, bottom=THIN)
WRAP = Alignment(wrap_text=True, vertical='center')
CENTER = Alignment(horizontal='center', vertical='center', wrap_text=True)
USD = '"$"#,##0.00'
USD0 = '"$"#,##0'
PCT = '0.0%'


class Sheet:
    def __init__(self, title, intro, widths, sheet_name='시트1'):
        self.wb = Workbook()
        self.ws = self.wb.active
        self.ws.title = sheet_name
        self.ncols = len(widths)
        for i, w in enumerate(widths, 1):
            self.ws.column_dimensions[get_column_letter(i)].width = w
        self.ws.sheet_view.showGridLines = False
        self.row = 1
        self.merged_row(title, F_TITLE, 34)
        self.merged_row(intro, F_TEXT, max(34, 20 * (1 + len(intro) // 70)))
        self.merged_row('노란 칸 = 내가 직접 넣는 곳    초록 칸 = 자동 계산 (건드리지 않아도 돼요)', F_NOTE, 22)
        self.row += 1

    def merged_row(self, text, font, height):
        ws = self.ws
        ws.merge_cells(start_row=self.row, start_column=1, end_row=self.row, end_column=self.ncols)
        c = ws.cell(self.row, 1, text)
        c.font = font
        c.alignment = WRAP
        ws.row_dimensions[self.row].height = height
        self.row += 1

    def head(self, labels, height=34):
        for i, t in enumerate(labels, 1):
            c = self.ws.cell(self.row, i, t)
            c.font, c.fill, c.alignment, c.border = F_HEAD, FILL_HEAD, CENTER, BORDER
        self.ws.row_dimensions[self.row].height = height
        self.row += 1
        return self.row - 1

    def section(self, text):
        for i in range(1, self.ncols + 1):
            c = self.ws.cell(self.row, i)
            c.fill, c.border = FILL_SEC, BORDER
        c = self.ws.cell(self.row, 1, text)
        c.font = F_BOLD
        self.ws.row_dimensions[self.row].height = 26
        self.row += 1

    def cell(self, col, value, kind='text', fmt=None, row=None, align=None):
        """kind: in(노란 입력) | calc(초록 계산) | text(설명·라벨) | note(회색 메모) | label(굵은 라벨)"""
        r = row or self.row
        c = self.ws.cell(r, col, value)
        c.border = BORDER
        c.alignment = align or WRAP
        if kind == 'in':
            c.fill, c.font = FILL_IN, F_INP
            c.protection = Protection(locked=False)
        elif kind == 'calc':
            c.fill, c.font = FILL_CALC, F_RES
        elif kind == 'note':
            c.font = F_NOTE
        elif kind == 'label':
            c.font = F_BOLD
        else:
            c.font = F_TEXT
        if fmt:
            c.number_format = fmt
        return c

    def note(self, text, height=None):
        self.row += 1
        self.merged_row(text, F_NOTE, height or max(24, 18 * (1 + len(text) // 80)))

    def dropdown(self, formula_list, ref):
        dv = DataValidation(type='list', formula1='"' + formula_list + '"', allow_blank=True)
        dv.error, dv.errorTitle = '목록에서 골라 주세요', '입력 오류'
        self.ws.add_data_validation(dv)
        dv.add(ref)

    def save(self, name, landscape=False):
        ws = self.ws
        ws.page_setup.orientation = 'landscape' if landscape else 'portrait'
        ws.page_setup.fitToWidth = 1
        ws.page_setup.fitToHeight = 0
        ws.sheet_properties.pageSetUpPr = PageSetupProperties(fitToPage=True)
        ws.print_options.horizontalCentered = True
        ws.protection.sheet = True          # 비밀번호 없는 보호: 노란 칸만 수정 가능
        ws.protection.formatColumns = False
        ws.protection.formatRows = False
        ws.protection.insertRows = True
        ws.protection.sort = False
        self.wb.save(OUT / name)
        print('saved', name)


def budget():
    s = Sheet('50/30/20 가계부', '한 달 수입(세금 낸 뒤 통장에 들어오는 돈)을 넣으면, 필수·선택·저축에 얼마씩 쓰면 좋은지 계산해 줘요. 지난달 쓴 돈을 "실제 쓴 돈"에 넣으면 목표와 비교해 줘요. 정답이 아니라 출발점이에요.',
              [28, 12, 16, 18, 14, 18, 22, 46])
    s.cell(1, '세후 월 수입 (USD)', 'label'); r = s.row
    s.cell(2, None, 'text'); s.cell(3, 5000, 'in', USD0)
    s.cell(4, '← 예시 $5,000. 내 숫자로 바꾸세요', 'note')
    s.ws.merge_cells(start_row=r, start_column=4, end_row=r, end_column=8)
    inc = f'$C${r}'
    s.row += 2
    s.head(['항목', '목표 비율', '목표 금액', '실제로 쓴 돈\n(지난달)', '실제 비율', '남은 돈(+) /\n넘친 돈(-)', '판정', '어떤 돈이 들어가나요?'], 44)
    items = [
        ('필수 지출', 0.5, '월세·모기지, 식비, 보험, 교통, 전기·수도·통신, 빚 최소 상환액', 'spend'),
        ('선택 지출', 0.3, '외식, 취미·여가, 구독, 쇼핑, 여행', 'spend'),
        ('저축·빚 갚기', 0.2, '비상금, 은퇴 저축(401k·IRA), 투자, 빚 더 갚기', 'save'),
    ]
    first = s.row
    for name, pct, desc, kind in items:
        r = s.row
        s.cell(1, name, 'label')
        s.cell(2, pct, 'in', '0%')
        s.cell(3, f'=IF({inc}="","",{inc}*B{r})', 'calc', USD0)
        s.cell(4, None, 'in', USD0)
        s.cell(5, f'=IF(OR(D{r}="",{inc}=""),"",D{r}/{inc})', 'calc', PCT)
        if kind == 'spend':
            s.cell(6, f'=IF(D{r}="","",C{r}-D{r})', 'calc', '+"$"#,##0;-"$"#,##0;"$"0')
            s.cell(7, f'=IF(D{r}="","(숫자를 넣어 보세요)",IF(D{r}<=C{r},"목표 안이에요 👍","목표보다 많이 썼어요"))', 'calc')
        else:
            s.cell(6, f'=IF(D{r}="","",D{r}-C{r})', 'calc', '+"$"#,##0;-"$"#,##0;"$"0')
            s.cell(7, f'=IF(D{r}="","(숫자를 넣어 보세요)",IF(D{r}>=C{r},"목표 달성 👍","목표보다 적게 모았어요"))', 'calc')
        s.cell(8, desc, 'note')
        s.ws.row_dimensions[r].height = 36
        s.row += 1
    r = s.row
    s.cell(1, '합계', 'label')
    s.cell(2, f'=SUM(B{first}:B{r-1})', 'calc', '0%')
    s.cell(3, f'=IF({inc}="","",SUM(C{first}:C{r-1}))', 'calc', USD0)
    s.cell(4, f'=IF(COUNT(D{first}:D{r-1})=0,"",SUM(D{first}:D{r-1}))', 'calc', USD0)
    s.cell(5, f'=IF(OR(D{r}="",{inc}=""),"",D{r}/{inc})', 'calc', PCT)
    s.cell(6, f'=IF(D{r}="","",{inc}-D{r})', 'calc', '+"$"#,##0;-"$"#,##0;"$"0')
    s.cell(7, '← 수입에서 쓴 돈을 뺀 나머지(+면 아직 안 쓴 돈)', 'note')
    s.ws.merge_cells(start_row=r, start_column=7, end_row=r, end_column=8)
    s.note('비율(노란 칸)은 내 형편에 맞게 바꿔도 돼요. 예) 월세가 비싸면 필수 60% · 선택 20% · 저축 20%. 합계가 100%가 되게 맞추세요.')
    s.save('budget-50-30-20.xlsx', landscape=True)


def card_compare():
    s = Sheet('신용카드 연회비 비교표', '연회비가 있는 카드가 정말 이득인지 "연회비 없는 2% 현금환급 카드"와 비교해요. 카드 A·B 칸에 내 카드 숫자를 넣으세요. 맨 아래 초록 칸에 결과가 나와요.',
              [38, 18, 18, 22])
    s.head(['항목', '카드 A', '카드 B', '기준: 2% 현금환급 카드\n(연회비 없음)'], 44)
    rows = [
        ('카드 이름', ('예: 여행 카드', '예: 마트 카드', '2% 현금환급'), 'in', None),
        ('연회비 (USD)', (395, 95, 0), 'in', USD0),
        ('1년에 이 카드로 쓸 금액 (USD)', (30000, 30000, 30000), 'in', USD0),
        ('내 소비 기준 평균 환급률\n(1.5% 라면 0.015)', (0.03, 0.02, 0.02), 'in', '0.0%'),
        ('실제로 쓰는 카드 혜택(크레딧) 연 합계 (USD)', (200, 0, 0), 'in', USD0),
        ('첫해만 받는 가입 보너스 (USD)', (750, 200, 200), 'in', USD0),
    ]
    start = s.row
    for label, vals, kind, fmt in rows:
        s.cell(1, label, 'label')
        for j, v in enumerate(vals):
            s.cell(2 + j, v, kind, fmt)
        s.ws.row_dimensions[s.row].height = 36
        s.row += 1
    fee, spend, rate, credit, bonus = [start + 1 + i for i in range(5)]
    s.section('자동 계산')
    calc = [
        ('포인트·환급으로 받는 돈 (1년)', lambda c: f'={c}{spend}*{c}{rate}', USD0),
        ('2년차 이후 1년 순이익\n= 환급 + 혜택 − 연회비', lambda c: f'={c}{s.row-1+0}+{c}{credit}-{c}{fee}', USD0),
    ]
    r_reward = s.row
    s.cell(1, calc[0][0], 'label')
    for j, c in enumerate('BCD'):
        s.cell(2 + j, f'={c}{spend}*{c}{rate}', 'calc', USD0)
    s.row += 1
    r_net = s.row
    s.cell(1, calc[1][0], 'label')
    for j, c in enumerate('BCD'):
        s.cell(2 + j, f'={c}{r_reward}+{c}{credit}-{c}{fee}', 'calc', USD0)
    s.ws.row_dimensions[s.row].height = 36
    s.row += 1
    r_first = s.row
    s.cell(1, '첫해 순이익 (가입 보너스 포함)', 'label')
    for j, c in enumerate('BCD'):
        s.cell(2 + j, f'={c}{r_net}+{c}{bonus}', 'calc', USD0)
    s.row += 1
    r_diff = s.row
    s.cell(1, '기준 카드와의 차이 (2년차 이후, 1년)\n+ 이면 이 카드가 이득', 'label')
    s.cell(2, f'=B{r_net}-$D${r_net}', 'calc', '+"$"#,##0;-"$"#,##0;"$"0')
    s.cell(3, f'=C{r_net}-$D${r_net}', 'calc', '+"$"#,##0;-"$"#,##0;"$"0')
    s.cell(4, '(기준)', 'note')
    s.ws.row_dimensions[s.row].height = 36
    s.row += 1
    s.cell(1, '결과', 'label')
    s.cell(2, f'=IF(B{r_diff}>0,"A가 이득 👍","2% 카드가 이득")', 'calc')
    s.cell(3, f'=IF(C{r_diff}>0,"B가 이득 👍","2% 카드가 이득")', 'calc')
    s.cell(4, '', 'note')
    s.note('※ 숫자는 모두 예시예요. 가입 보너스는 첫해에 한 번만 받으니, 오래 쓸 카드는 "2년차 이후" 줄로 판단하세요. 혜택(크레딧)은 내가 실제로 쓸 것만 넣으세요 — 안 쓰면 0원이에요.')
    s.save('card-compare.xlsx')


def card_tracker():
    s = Sheet('카드 혜택 사용 기록표', '연회비 카드의 크레딧(매달·분기·연 단위 혜택)을 안 쓰고 놓치면 손해예요. 혜택마다 한 줄씩 적고, 쓴 뒤에 "사용 여부"를 바꿔 두세요. 맨 아래에서 연회비를 넘겼는지 알려 줘요.',
              [26, 16, 14, 14, 16, 18, 14, 16, 30])
    s.head(['혜택 이름', '카드', '주기', '1회 금액\n(USD)', '1년에 받을 수\n있는 금액', '사용 기한', '사용했나요?', '실제로 쓴 금액\n(USD)', '메모'], 48)
    first = s.row
    ex = [('예: 외식 크레딧', '카드 A', '월', 10, '매월 말', '예', 10, '앱에서 먼저 등록해야 해요'),
          ('예: 항공 수수료 크레딧', '카드 A', '연', 200, '12월 31일', '아니오', 0, '')]
    for i in range(12):
        r = s.row
        e = ex[i] if i < len(ex) else (None,) * 8
        s.cell(1, e[0], 'in'); s.cell(2, e[1], 'in'); s.cell(3, e[2], 'in', align=CENTER)
        s.cell(4, e[3], 'in', USD0)
        s.cell(5, f'=IF(OR(D{r}="",C{r}=""),"",D{r}*IF(C{r}="월",12,IF(C{r}="분기",4,IF(C{r}="반기",2,1))))', 'calc', USD0)
        s.cell(6, e[4], 'in'); s.cell(7, e[5], 'in', align=CENTER); s.cell(8, e[6], 'in', USD0); s.cell(9, e[7], 'in')
        s.row += 1
    last = s.row - 1
    s.dropdown('월,분기,반기,연', f'C{first}:C{last}')
    s.dropdown('예,아니오', f'G{first}:G{last}')
    s.row += 1
    r = s.row
    s.cell(1, '1년에 받을 수 있는 혜택 합계', 'label'); s.cell(2, None, 'text'); s.cell(3, None, 'text'); s.cell(4, None, 'text')
    s.cell(5, f'=SUM(E{first}:E{last})', 'calc', USD0)
    s.row += 1
    s.cell(1, '실제로 쓴 금액 합계', 'label'); s.cell(8, f'=SUM(H{first}:H{last})', 'calc', USD0)
    r2 = s.row
    s.row += 1
    s.cell(1, '이 카드의 1년 연회비 (USD)', 'label'); s.cell(4, 395, 'in', USD0); s.cell(5, '← 예시 $395. 내 카드 연회비로 바꾸세요', 'note')
    r3 = s.row
    s.ws.merge_cells(start_row=r3, start_column=5, end_row=r3, end_column=9)
    s.row += 1
    s.cell(1, '결과', 'label')
    c = s.cell(2, f'=IF(H{r2}>=D{r3},"연회비를 넘게 썼어요 👍 (+$"&TEXT(H{r2}-D{r3},"#,##0")&")","아직 연회비보다 $"&TEXT(D{r3}-H{r2},"#,##0")&" 덜 썼어요")', 'calc')
    s.ws.merge_cells(start_row=s.row, start_column=2, end_row=s.row, end_column=9)
    s.note('※ 혜택 내용과 금액은 카드사가 수시로 바꿔요. 카드사 앱·사이트의 최신 안내대로 적고, 사용 기한 한 달 전에 휴대폰 알림을 걸어 두세요. "예:" 로 시작하는 두 줄은 예시니 지우고 쓰세요.')
    s.save('card-credit-tracker.xlsx', landscape=True)


def college():
    s = Sheet('대학 4년 비용 계획표', '학교 사이트의 "Net Price Calculator"(실제 부담액 계산기)에서 나온 숫자를 학년별로 넣으세요. 장학금·보조금(갚지 않는 돈)과 529 저축에서 쓸 돈을 빼면 대출·현금으로 채워야 할 돈이 나와요.',
              [38, 16, 16, 16, 16, 18])
    s.head(['항목 (1년 기준, USD)', '1학년', '2학년', '3학년', '4학년', '4년 합계'], 34)
    first = s.row
    for lab in ['등록금 (Tuition & Fees)', '기숙사·식비', '책·용품', '교통·용돈']:
        r = s.row
        s.cell(1, lab, 'label')
        for j in range(4):
            s.cell(2 + j, None, 'in', USD0)
        s.cell(6, f'=SUM(B{r}:E{r})', 'calc', USD0)
        s.row += 1
    tot = s.row
    s.cell(1, '총 비용', 'label')
    for j, c in enumerate('BCDEF'):
        s.cell(2 + j, f'=SUM({c}{first}:{c}{tot-1})', 'calc', USD0)
    s.row += 1
    s.section('줄어드는 돈 (갚지 않아도 되는 돈 + 내가 모아 둔 돈)')
    g = s.row
    s.cell(1, '장학금·보조금 (갚지 않는 돈)', 'label')
    for j in range(4): s.cell(2 + j, None, 'in', USD0)
    s.cell(6, f'=SUM(B{g}:E{g})', 'calc', USD0); s.row += 1
    sv = s.row
    s.cell(1, '529·저축에서 쓸 돈', 'label')
    for j in range(4): s.cell(2 + j, None, 'in', USD0)
    s.cell(6, f'=SUM(B{sv}:E{sv})', 'calc', USD0); s.row += 1
    gap = s.row
    s.cell(1, '부족액 (대출·현금으로 채울 돈)', 'label')
    for j, c in enumerate('BCDE'):
        s.cell(2 + j, f'={c}{tot}-{c}{g}-{c}{sv}', 'calc', USD0)
    s.cell(6, f'=SUM(B{gap}:E{gap})', 'calc', USD0); s.row += 1
    s.section('지금부터 모은다면?')
    m = s.row
    s.cell(1, '입학까지 남은 개월 수', 'label'); s.cell(2, 60, 'in'); s.cell(3, '← 예시 60개월(5년). 내 상황으로 바꾸세요', 'note')
    s.ws.merge_cells(start_row=m, start_column=3, end_row=m, end_column=6); s.row += 1
    s.cell(1, '부족액을 메우려면 매달 모을 돈', 'label')
    s.cell(2, f'=IF(OR(B{m}="",B{m}=0),"",F{gap}/B{m})', 'calc', USD0)
    s.cell(3, '(이자·수익은 빼고 단순 계산)', 'note'); s.ws.merge_cells(start_row=s.row, start_column=3, end_row=s.row, end_column=6)
    s.note('※ 부족액이 크게 나와도 놀라지 마세요. 학자금 대출은 연방(정부) 대출을 먼저 보고, 사립 대출은 마지막에 고려하세요. 실제 부담액은 학교마다 크게 달라요.')
    s.save('college-cost-planner.xlsx')


def grocery():
    s = Sheet('마트 단위 가격 비교표', '같은 물건을 여러 마트에서 비교할 때 "큰 포장이 싼가?"를 한눈에 알려 줘요. 가격과 용량을 넣으면 1 단위(oz·lb·개)당 가격이 나오고, 같은 상품끼리 제일 싼 곳에 👍 표시가 떠요.',
              [20, 16, 14, 14, 14, 18, 16, 28])
    s.head(['상품', '마트', '가격 (USD)', '용량 (숫자만)', '단위', '단위당 가격', '싼 순서\n(같은 상품끼리)', '비고 (세일 기간 등)'], 44)
    first = s.row
    ex = [('예: 쌀', 'A마트', 19.99, 10, 'lb', '10lb 포대'), ('예: 쌀', 'B마트', 31.99, 20, 'lb', '20lb 포대')]
    n = 24
    last = first + n - 1
    for i in range(n):
        r = s.row
        e = ex[i] if i < len(ex) else (None,) * 6
        s.cell(1, e[0], 'in'); s.cell(2, e[1], 'in'); s.cell(3, e[2], 'in', USD)
        s.cell(4, e[3], 'in'); s.cell(5, e[4], 'in', align=CENTER)
        s.cell(6, f'=IF(OR(C{r}="",D{r}="",D{r}=0),"",C{r}/D{r})', 'calc', '"$"#,##0.000')
        s.cell(7, f'=IF(F{r}="","",IF(COUNTIFS($A${first}:$A${last},A{r},$E${first}:$E${last},E{r},$F${first}:$F${last},"<"&F{r})=0,"👍 제일 싸요",COUNTIFS($A${first}:$A${last},A{r},$E${first}:$E${last},E{r},$F${first}:$F${last},"<"&F{r})+1&"번째"))', 'calc', align=CENTER)
        s.cell(8, e[5], 'in')
        s.row += 1
    s.dropdown('oz,lb,개,L,gal,ct,sheet', f'E{first}:E{last}')
    s.note('※ 같은 상품은 이름을 똑같이 적고, 단위(oz·lb 등)도 같게 맞춰야 비교돼요. 1lb = 16oz. 가장 싼 건 가격이 낮은 게 아니라 "단위당 가격"이 낮은 거예요. 유통기한 안에 다 못 먹으면 싸도 손해예요.')
    s.save('grocery-unit-price.xlsx', landscape=True)


def home_buying():
    s = Sheet('집 살 때 한 달·처음 필요한 돈 계산표', '집값과 대출 조건을 넣으면 "매달 실제로 나가는 돈"과 "처음 준비할 현금"을 계산해요. 노란 칸의 예시 숫자를 내가 보는 집·대출 견적서(Loan Estimate)의 숫자로 바꾸세요.',
              [34, 20, 62])
    s.head(['항목', '값', '설명'], 30)
    def line(label, val, kind, fmt, desc):
        s.cell(1, label, 'label'); s.cell(2, val, kind, fmt); s.cell(3, desc, 'note')
        s.ws.row_dimensions[s.row].height = 30
        s.row += 1
        return s.row - 1
    s.section('1. 집과 대출')
    price = line('집값 (USD)', 450000, 'in', USD0, '예시 금액이에요. 내가 보는 집 가격으로 바꾸세요')
    down = line('선납금 비율 (다운페이먼트)', 0.10, 'in', '0%', '10% 면 0.10. 20% 이상이면 PMI(대출 보험)가 필요 없어요')
    downamt = line('선납금 (USD)', f'=B{price}*B{down}', 'calc', USD0, '집값 × 선납금 비율')
    loan = line('대출액 (USD)', f'=B{price}-B{downamt}', 'calc', USD0, '집값 − 선납금')
    rate = line('연 금리', 0.065, 'in', '0.000%', '6.5% 면 0.065. 견적서에 적힌 금리를 넣으세요')
    yrs = line('대출 기간 (년)', 30, 'in', '0', '보통 30년 또는 15년')
    s.section('2. 매달 내는 돈')
    pi = line('월 원금+이자 (USD)', f'=IF(OR(B{rate}="",B{yrs}=0),"",IF(B{rate}=0,B{loan}/(B{yrs}*12),PMT(B{rate}/12,B{yrs}*12,-B{loan})))', 'calc', USD0, '매달 은행에 내는 원금과 이자')
    pmir = line('PMI 연 요율', 0.008, 'in', '0.00%', '0.8% 는 연 0.8%. 견적서에 PMI 금액이 있으면 그걸로 바꾸세요')
    pmi = line('월 PMI (USD)', f'=IF(B{down}>=0.2,0,B{loan}*B{pmir}/12)', 'calc', USD0, '선납금 20% 이상이면 0')
    tax = line('재산세 (1년, USD)', 6000, 'in', USD0, '카운티마다 크게 달라요. 카운티 재산세 사이트나 매물 정보에서 확인')
    taxm = line('월 재산세 (USD)', f'=B{tax}/12', 'calc', USD0, '')
    ins = line('주택보험 (1년, USD)', 1800, 'in', USD0, '보험사 견적으로 바꾸세요')
    insm = line('월 주택보험 (USD)', f'=B{ins}/12', 'calc', USD0, '')
    hoa = line('HOA 회비 (월, USD)', 0, 'in', USD0, '콘도·타운홈·단지는 회비가 있어요')
    total = line('매달 실제로 나가는 주거비 (USD)', f'=IF(B{pi}="","",B{pi}+B{pmi}+B{taxm}+B{insm}+B{hoa})', 'calc', USD0, '이 금액이 월 수입의 약 28% 안이면 무리가 적다고들 해요 (정답은 아니에요)')
    s.ws.cell(total, 2).font = Font(name='Malgun Gothic', size=14, bold=True, color='065F46')
    s.section('3. 처음 필요한 현금')
    ccr = line('Closing cost 비율', 0.03, 'in', '0.0%', '보통 집값의 2~5%. 견적서(Loan Estimate)의 숫자가 있으면 그 금액을 아래 칸에 직접 쓰세요')
    cc = line('Closing cost 예상 (USD)', f'=B{price}*B{ccr}', 'calc', USD0, '')
    cash = line('처음 필요한 현금 (USD)', f'=B{downamt}+B{cc}', 'calc', USD0, '선납금 + closing cost  (비상금은 따로 두세요!)')
    s.ws.cell(cash, 2).font = Font(name='Malgun Gothic', size=14, bold=True, color='065F46')
    s.section('4. 소득으로 따져 보기')
    inc = line('세전 월 수입 (USD)', 9000, 'in', USD0, '예시. 가족 전체 세전 월 수입')
    ratio = line('월 주거비 ÷ 월 수입', f'=IF(OR(B{inc}="",B{inc}=0,B{total}=""),"",B{total}/B{inc})', 'calc', '0.0%', '28% 이하면 여유, 36% 넘으면 빠듯해요 (대략적인 기준)')
    s.save('home-buying-cost-sheet.xlsx')


def insurance():
    s = Sheet('보험 견적 비교표', '같은 보장 조건으로 맞춘 뒤 비교해야 진짜 싼 곳을 알 수 있어요. 회사별 견적을 받으면 칸마다 적으세요. 월 보험료를 넣으면 1년 금액과 가장 싼 회사가 나와요.',
              [42, 24, 24, 24])
    s.head(['항목', '회사 A', '회사 B', '회사 C'], 30)
    first = s.row
    rows = [('회사 이름', 'in', None), ('보험 종류 (자동차·집·생명 등)', 'in', None),
            ('월 보험료 (USD)', 'in', USD), ('보험 기간 (6개월 / 12개월)', 'in', None)]
    for lab, kind, fmt in rows:
        s.cell(1, lab, 'label')
        for j in range(3): s.cell(2 + j, None, kind, fmt)
        s.row += 1
    name_r, type_r, mon_r = first, first + 1, first + 2
    r = s.row
    s.cell(1, '연 보험료로 바꾸면 (USD)', 'label')
    for j, c in enumerate('BCD'):
        s.cell(2 + j, f'=IF({c}{mon_r}="","",{c}{mon_r}*12)', 'calc', USD0)
    s.row += 1
    for lab in ['자기부담금 (Deductible)', '대인 배상 한도', '대물 배상 한도', '무보험 운전자 보장 (UM/UIM)', '렌터카·견인 보장', '할인 항목 (묶음·안전운전 등)', '고객 불만 평가\n(주 보험국 사이트에서 확인)', '메모']:
        s.cell(1, lab, 'label')
        for j in range(3): s.cell(2 + j, None, 'in')
        s.ws.row_dimensions[s.row].height = 32
        s.row += 1
    s.row += 1
    s.cell(1, '가장 싼 회사 (월 보험료 기준)', 'label')
    s.cell(2, f'=IF(COUNT(B{mon_r}:D{mon_r})=0,"",IF(INDEX(B{name_r}:D{name_r},MATCH(MIN(B{mon_r}:D{mon_r}),B{mon_r}:D{mon_r},0))="","(회사 이름을 적어 주세요)",INDEX(B{name_r}:D{name_r},MATCH(MIN(B{mon_r}:D{mon_r}),B{mon_r}:D{mon_r},0))))', 'calc')
    s.ws.merge_cells(start_row=s.row, start_column=2, end_row=s.row, end_column=4)
    s.row += 1
    s.cell(1, '제일 비싼 곳과의 1년 차이 (USD)', 'label')
    s.cell(2, f'=IF(COUNT(B{mon_r}:D{mon_r})<2,"",(MAX(B{mon_r}:D{mon_r})-MIN(B{mon_r}:D{mon_r}))*12)', 'calc', USD0)
    s.ws.merge_cells(start_row=s.row, start_column=2, end_row=s.row, end_column=4)
    s.note('※ 보험료만 보지 말고, 자기부담금과 배상 한도가 같은 조건인지 꼭 확인하세요. 싸다고 한도를 낮추면 사고 났을 때 내 돈으로 물어줘야 할 수 있어요.')
    s.save('insurance-quote-compare.xlsx')


def retirement():
    s = Sheet('은퇴 후 생활비 계획표', '지금 쓰는 돈과 은퇴 후 쓸 돈을 항목별로 적으면, 사회보장연금(Social Security)으로 부족한 돈과 대략 얼마를 모아야 하는지 계산해요.',
              [40, 20, 20, 50])
    s.head(['한 달 지출 항목 (USD)', '지금', '은퇴 후 예상', '참고'], 30)
    first = s.row
    items = [('주거 (모기지·월세·재산세)', '대출을 다 갚으면 크게 줄어요'), ('식비', ''),
             ('의료비·보험 (Medicare 보험료 포함)', '은퇴 후 오히려 늘 수 있어요'), ('교통·차량', ''), ('공과금·통신', ''),
             ('여가·여행', '은퇴 직후 몇 년은 늘기도 해요'), ('가족 지원·선물', ''), ('세금', 'Social Security·인출금에 세금이 붙을 수 있어요'), ('기타', '')]
    for lab, note in items:
        s.cell(1, lab, 'label'); s.cell(2, None, 'in', USD0); s.cell(3, None, 'in', USD0); s.cell(4, note, 'note')
        s.ws.row_dimensions[s.row].height = 28
        s.row += 1
    last = s.row - 1
    m = s.row
    s.cell(1, '월 합계', 'label'); s.cell(2, f'=SUM(B{first}:B{last})', 'calc', USD0); s.cell(3, f'=SUM(C{first}:C{last})', 'calc', USD0); s.cell(4, '', 'note'); s.row += 1
    y = s.row
    s.cell(1, '1년 합계', 'label'); s.cell(2, f'=B{m}*12', 'calc', USD0); s.cell(3, f'=C{m}*12', 'calc', USD0); s.cell(4, '', 'note'); s.row += 1
    s.section('나라에서 받는 돈과 부족한 돈')
    ss = s.row
    s.cell(1, '내 Social Security 예상 월 수령액 (USD)', 'label'); s.cell(2, None, 'text'); s.cell(3, None, 'in', USD0)
    s.cell(4, 'ssa.gov 의 "my Social Security" 계정에서 확인. 부부면 둘의 합', 'note'); s.row += 1
    other = s.row
    s.cell(1, '그 밖의 월 수입 (연금·임대·일 등, USD)', 'label'); s.cell(2, None, 'text'); s.cell(3, None, 'in', USD0); s.cell(4, '', 'note'); s.row += 1
    gap = s.row
    s.cell(1, '부족한 월 생활비 (USD)', 'label'); s.cell(2, None, 'text')
    s.cell(3, f'=MAX(0,C{m}-N(C{ss})-N(C{other}))', 'calc', USD0); s.cell(4, '은퇴 후 예상 지출 − 받는 돈', 'note'); s.row += 1
    gy = s.row
    s.cell(1, '부족한 1년 금액 (USD)', 'label'); s.cell(2, None, 'text'); s.cell(3, f'=C{gap}*12', 'calc', USD0); s.cell(4, '저축·투자 인출로 메워야 할 금액', 'note'); s.row += 1
    wr = s.row
    s.cell(1, '1년에 꺼내 쓸 비율 (인출률)', 'label'); s.cell(2, None, 'text'); s.cell(3, 0.04, 'in', '0.0%'); s.cell(4, '흔히 쓰는 "4% 규칙"은 어림값일 뿐 보장이 아니에요. 3.5~4% 로 바꿔 보세요', 'note'); s.row += 1
    need = s.row
    s.cell(1, '대략 필요한 저축·투자 총액 (USD)', 'label'); s.cell(2, None, 'text')
    s.cell(3, f'=IF(OR(C{wr}="",C{wr}=0),"",C{gy}/C{wr})', 'calc', USD0); s.cell(4, '부족한 1년 금액 ÷ 인출률', 'note')
    s.ws.cell(need, 3).font = Font(name='Malgun Gothic', size=14, bold=True, color='065F46')
    s.ws.row_dimensions[need].height = 30
    s.note('※ 물가 상승, 의료비, 오래 사는 경우를 생각해서 여유를 두세요. 큰 결정은 은퇴 설계 전문가(수수료만 받는 fee-only)와 상의하는 게 안전해요.')
    s.save('retirement-expenses.xlsx')


def savings():
    s = Sheet('예금·CD 이자 비교표', '은행마다 이율(APY)이 달라요. 넣을 돈과 이율을 넣으면 1년 이자가 나오고, 같은 돈일 때 어디가 많이 주는지 순서를 알려 줘요. 이율은 매일 바뀌니 은행 사이트의 오늘 숫자로 바꾸세요.',
              [18, 20, 14, 16, 12, 22, 18, 16, 18, 12])
    s.head(['상품', '금융기관', 'APY\n(연 이율)', '최소 예치금', '기간\n(개월)', '조기 인출 벌칙', '예금자 보호\n(FDIC/NCUA)', '넣을 돈 (USD)', '1년 이자 (대략)', '순서'], 48)
    first = s.row
    ex = [('예: 고금리 저축(HYSA)', '예: A은행', 0.04, 0, '없음', '없음', '예', 10000),
          ('예: CD', '예: B은행', 0.045, 500, 12, '있음(이자 일부)', '예', 10000)]
    n = 10
    last = first + n - 1
    for i in range(n):
        r = s.row
        e = ex[i] if i < len(ex) else (None,) * 8
        s.cell(1, e[0], 'in'); s.cell(2, e[1], 'in'); s.cell(3, e[2], 'in', '0.00%'); s.cell(4, e[3], 'in', USD0)
        s.cell(5, e[4], 'in', align=CENTER); s.cell(6, e[5], 'in'); s.cell(7, e[6], 'in', align=CENTER); s.cell(8, e[7], 'in', USD0)
        s.cell(9, f'=IF(OR(C{r}="",H{r}=""),"",H{r}*C{r})', 'calc', USD0)
        s.cell(10, f'=IF(I{r}="","",RANK(I{r},$I${first}:$I${last}))', 'calc', align=CENTER)
        s.row += 1
    s.dropdown('예,아니오,모름', f'G{first}:G{last}')
    s.note('※ 위 숫자는 모두 예시예요. 1년 이자는 1년 동안 이율이 그대로라는 단순 계산이에요. 한 은행당 FDIC 보호는 보통 한도 $250,000 이니 큰돈은 나눠 넣는 것도 방법이에요. 이자에도 세금이 붙을 수 있어요.')
    s.save('savings-compare.xlsx', landscape=True)


def side_income():
    s = Sheet('부수입 기록장', '플랫폼(Fiverr·Uber 등)에서 돈이 들어올 때마다 한 줄씩 적으세요. 비용(수수료·재료비)도 같이 적어 두면, 세금 낼 때 "순수익"이 바로 나와요. 영수증은 따로 사진으로 모아 두세요.',
              [16, 22, 14, 14, 24, 16, 16, 28])
    s.head(['날짜', '플랫폼 / 고객', '들어온 돈\n(USD)', '쓴 비용\n(USD)', '비용 내용', '순수익\n(자동)', '입금 방법', '메모'], 44)
    first = s.row
    n = 40
    last = first + n - 1
    for i in range(n):
        r = s.row
        e = ('2026-01-15', '예: Fiverr', 120, 24, '플랫폼 수수료', 'PayPal', '') if i == 0 else (None,) * 7
        s.cell(1, e[0], 'in'); s.cell(2, e[1], 'in'); s.cell(3, e[2], 'in', USD); s.cell(4, e[3], 'in', USD); s.cell(5, e[4], 'in')
        s.cell(6, f'=IF(C{r}="","",C{r}-N(D{r}))', 'calc', USD); s.cell(7, e[5], 'in'); s.cell(8, e[6], 'in')
        s.row += 1
    s.row += 1
    t = s.row
    s.cell(1, '합계', 'label'); s.cell(2, None, 'text')
    s.cell(3, f'=SUM(C{first}:C{last})', 'calc', USD); s.cell(4, f'=SUM(D{first}:D{last})', 'calc', USD); s.cell(5, None, 'text')
    s.cell(6, f'=SUM(F{first}:F{last})', 'calc', USD); s.row += 1
    s.cell(1, '세금으로 따로 떼어 둘 비율', 'label'); s.cell(2, None, 'text'); s.cell(3, 0.25, 'in', '0%'); s.cell(4, '← 예시 25%', 'note')
    rr = s.row
    s.ws.merge_cells(start_row=rr, start_column=4, end_row=rr, end_column=8); s.row += 1
    s.cell(1, '따로 떼어 둘 돈', 'label'); s.cell(2, None, 'text'); s.cell(3, f'=IF(F{t}<=0,0,F{t}*C{rr})', 'calc', USD); s.cell(4, '순수익 × 비율. 별도 통장에 모아 두면 세금 낼 때 편해요', 'note')
    s.ws.merge_cells(start_row=s.row, start_column=4, end_row=s.row, end_column=8)
    s.note('※ 부수입은 금액이 작아도 신고 대상일 수 있어요. 자영업자(1099)는 소득세 외에 자영업세(Self-Employment tax, 약 15.3%)가 붙어요. 세율은 사람마다 달라 25%는 어림값이에요 — 정확한 건 세금 신고 때 확인하세요.')
    s.save('side-income-log.xlsx', landscape=True)


def subscriptions():
    s = Sheet('구독 정리표', '넷플릭스·음악·앱·멤버십처럼 매달 자동으로 빠져나가는 돈을 모두 적어 보세요. 한 달에 거의 안 쓴 것은 "아니오"로 바꾸면, 해지하면 아낄 돈이 자동으로 계산돼요.',
              [30, 14, 14, 16, 18, 18, 22])
    s.head(['서비스 이름', '월 요금\n(USD)', '1년 합계', '결제일', '지난 한 달\n사용 횟수', '계속 쓸까요?', '한 번 쓸 때\n드는 돈'], 44)
    first = s.row
    n = 20
    last = first + n - 1
    for i in range(n):
        r = s.row
        e = ('예: 동영상 서비스', 15.99, '매월 5일', 8, '예') if i == 0 else (None,) * 5
        s.cell(1, e[0], 'in'); s.cell(2, e[1], 'in', USD)
        s.cell(3, f'=IF(B{r}="","",B{r}*12)', 'calc', USD0)
        s.cell(4, e[2], 'in'); s.cell(5, e[3], 'in', align=CENTER); s.cell(6, e[4], 'in', align=CENTER)
        s.cell(7, f'=IF(OR(B{r}="",E{r}=""),"",IF(E{r}=0,"한 번도 안 썼어요",B{r}/E{r}))', 'calc', USD)
        s.row += 1
    s.dropdown('예,아니오,고민 중', f'F{first}:F{last}')
    s.row += 1
    t = s.row
    s.cell(1, '지금 매달 나가는 돈 합계', 'label'); s.cell(2, f'=SUM(B{first}:B{last})', 'calc', USD); s.cell(3, f'=SUM(C{first}:C{last})', 'calc', USD0)
    s.row += 1
    s.cell(1, '"아니오"를 해지하면 1년에 아끼는 돈', 'label'); s.cell(2, f'=SUMIF(F{first}:F{last},"아니오",B{first}:B{last})', 'calc', USD)
    s.cell(3, f'=SUMIF(F{first}:F{last},"아니오",C{first}:C{last})', 'calc', USD0)
    s.ws.cell(s.row, 3).font = Font(name='Malgun Gothic', size=14, bold=True, color='065F46')
    s.ws.row_dimensions[s.row].height = 32
    s.note('※ 한 달에 한 번도 안 쓴 서비스는 해지 후보예요. 무료 체험이 끝나고 자동으로 유료가 된 것이 없는지 카드 명세서를 꼭 확인하세요. 연간 결제로 바꾸면 싸지는 경우도 있어요.')
    s.save('subscription-audit.xlsx', landscape=True)


def watchlist():
    s = Sheet('관심 종목 메모장', '관심 있는 종목·ETF를 적어 두고 공부할 때 쓰는 메모장이에요. 추천 목록이 아니에요. 사기 전에 반드시 직접 조사하고, 잃어도 되는 금액만 투자하세요.',
              [12, 28, 34, 18, 14, 18, 16, 30])
    s.head(['티커', '회사 / ETF', '내가 관심 갖는 이유', '다음 실적 발표일', '배당 여부', '배당락일', '메모 날짜', '더 알아볼 것'], 44)
    first = s.row
    n = 25
    for i in range(n):
        e = ('예: VOO', 'S&P 500 ETF', '분산 투자, 수수료가 낮음', '해당 없음', '예', '', '2026-01-15', '운용 수수료·세금 확인') if i == 0 else (None,) * 8
        for j in range(8):
            s.cell(1 + j, e[j], 'in', align=CENTER if j in (0, 4) else None)
        s.row += 1
    s.dropdown('예,아니오,모름', f'E{first}:E{first+n-1}')
    s.note('※ 관심 목록일 뿐 추천이 아니에요. 한 종목에 몰아서 사지 말고, 투자 원금은 잃을 수 있다는 걸 기억하세요. "예:" 로 시작하는 줄은 예시니 지우고 쓰세요.')
    s.save('watchlist.xlsx', landscape=True)


if __name__ == '__main__':
    OUT.mkdir(parents=True, exist_ok=True)
    for fn in (budget, card_compare, card_tracker, college, grocery, home_buying, insurance, retirement, savings, side_income, subscriptions, watchlist):
        fn()
