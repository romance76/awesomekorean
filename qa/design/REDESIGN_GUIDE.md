# AwesomeKorean "Warm Modern" 디자인 리뉴얼 가이드 (2026-06)

모든 리뉴얼 작업자는 이 문서의 규칙을 그대로 따른다. 임의로 다른 스타일을 만들지 않는다.

## 0. 절대 규칙
- **로직(script) 변경 금지** — template 의 클래스/마크업과 style 블록만 수정. API 호출, computed, 메서드, props, emits 는 절대 건드리지 않는다.
- v-if / v-for / :class 바인딩 조건은 유지 (클래스 문자열 내용만 교체 가능).
- 기능 제거 금지 — 버튼/링크/입력 요소를 빠뜨리지 않는다.
- DB·백엔드·라우트 변경 금지.

## 1. 토큰 (이미 적용됨 — tailwind.config.js / app.css)
- **주색**: `amber-*` 팔레트가 오렌지로 오버라이드됨. `bg-amber-400` = #FF6B2C (메인), `amber-50` = 파스텔 배경, `amber-600/700` = 강조 텍스트.
- **페이지 배경**: body 가 `#F7F8FA` (gray 가 아님). 페이지 래퍼에 `bg-gray-100` 같은 배경을 깔지 말 것 — body 배경을 그대로 쓴다.
- **텍스트**: 제목 `text-ink`(#191F28), 본문 보조 `text-ink-light`(#4E5968), 메타 `text-ink-muted`(#8B95A1). 기존 `text-gray-800/600/400` 은 각각 이걸로 교체.
- **폰트**: Pretendard (자동 적용, 변경 불필요).
- **그림자**: `shadow-card`(기본), `shadow-lift`(호버), `shadow-btn`(주 버튼).

## 2. 공용 클래스 (app.css 에 정의됨 — 반드시 이걸 사용)
| 용도 | Before (제거 대상) | After |
|---|---|---|
| 카드 컨테이너 | `bg-white rounded-xl shadow-sm border border-gray-200` | `card` (클릭 가능한 카드는 `card card-hover`) |
| 주 버튼 | `bg-amber-400 text-amber-900 font-bold px-? py-? rounded-lg hover:bg-amber-500` | `btn-primary` |
| 보조 버튼 | `bg-white border text-gray-500 ...` | `btn-secondary` |
| 연한 액션 버튼 | `bg-amber-50 text-amber-700 ...` | `btn-soft` |
| 텍스트 버튼 | `text-gray-500 hover:text-amber-600` | `btn-ghost` |
| 입력/셀렉트/textarea | `border border-gray-300 rounded-lg px-? py-? focus:ring-2 focus:ring-amber-400` | `input-soft` |
| 폼 라벨 | `text-sm font-semibold text-gray-700` | `input-label` |
| 뱃지 | `text-xs bg-?-100 text-?-700 px-2 py-0.5 rounded-full` | `badge-primary` `badge-blue` `badge-green` `badge-red` `badge-purple` `badge-gray` |
| 리스트 행 | `px-4 py-3 border-b border-gray-50 hover:bg-...` | `list-row` + 행 사이는 `divide-y divide-gray-50` 를 부모에 |

- 카드 내부 행 리스트(게시판 목록)는: 부모 `card overflow-hidden divide-y divide-gray-50`, 각 행 `list-row`.
- 독립 카드 나열(부동산/마켓 카드형)은: 각 카드 `card card-hover overflow-hidden`.

## 3. 이모지 → AppIcon 교체
`resources/js/components/AppIcon.vue` 사용:
```vue
import AppIcon from '@/components/AppIcon.vue'  // 경로 alias 없으면 상대경로
<AppIcon name="briefcase" :size="18" />
```
**UI 구조 이모지만 교체** (페이지 헤더·메뉴·버튼·빈 상태 아이콘). 사용자 콘텐츠/장식성 텍스트 안의 이모지는 유지.

매핑표: 💬→message-circle ❓→help-circle 💼→briefcase 🛒→shopping-cart 🏠/🏡→home 🏢→building 🏪/업소→store 👥/동호회→users 📰→newspaper 🍳/레시피→utensils 🎮→gamepad 🎵→music 🎬/숏츠→video 📅/이벤트→calendar 🔍→search 🔔→bell ⭐→star ❤️→heart 👁→eye 📍→map-pin 🕐→clock 📷→camera ➕→plus ✏️→edit 🗑→trash 📤/공유→share 🔖→bookmark 🏆→trophy 💰/포인트→coins 🎁→gift 📞→phone 🌐→globe 🔥→flame 👍→thumbs-up ⚠️→alert-circle 🛡→shield 📊→chart-bar 🔑→key ✨→sparkles 📢→megaphone 👶→baby 🎓→graduation-cap 🚗→car 💊/의료→stethoscope ✂️/미용→scissors 🔨/건설→hammer 💻/IT→monitor 📚→book-open 🗣/통역→languages 💵→dollar 🏷→tag 👤→user 🤝→heart-handshake

페이지 헤더 패턴 (모든 리스트 페이지 통일):
```html
<h1 class="flex items-center gap-2.5 text-xl font-bold text-ink">
  <span class="icon-chip w-9 h-9 bg-amber-50 text-amber-600"><AppIcon name="briefcase" :size="20" /></span>
  구인구직
</h1>
```
아이콘 칩 색은 메뉴별로: 커뮤니티 blue-50/blue-600, Q&A amber, 구인구직 amber, 중고장터 emerald-50/emerald-600, 부동산 violet-50/violet-600, 업소록 pink-50/pink-600, 동호회 teal-50/teal-600, 뉴스 sky-50/sky-600, 레시피 orange-50/orange-600, 공동구매 lime-50/lime-600, 그 외 amber.

## 4. 타이포 / 간격 규칙
- 최소 글씨 `text-xs`(12px). `text-[10px]`, `text-[9px]` 는 전부 `text-xs` 로 올린다 (단 공간이 정말 좁은 곳은 `text-[11px]` 허용).
- 리스트 제목: `text-sm font-semibold text-ink` (카드형 큰 카드면 `text-base`).
- 메타 정보: `text-xs text-ink-muted`.
- 카드 내부 패딩: 리스트 행 `px-4 py-3`, 카드형 `p-4`, 섹션 헤더 `px-4 py-3`.
- 카드 사이 간격: `gap-3` (모바일) / `gap-4` (데스크톱).
- `font-black` 은 가격·포인트 숫자에만. 제목은 `font-bold`, 본문 강조는 `font-semibold`.

## 5. 인터랙션
- 모든 클릭 가능한 카드/행: hover 반응 필수 (`card-hover` 또는 `list-row` 가 이미 처리).
- 버튼은 공용 클래스가 트랜지션 포함. 커스텀 버튼 만들지 말 것.
- 진한 원색 그라데이션 (`from-orange-500 to-red-500` 등) → 흰 카드 + 파스텔 아이콘칩으로 교체. 그라데이션은 히어로 배너와 포인트 카드 정도만 유지 (오렌지 계열로: `from-amber-400 to-amber-500`).

## 6. 이미지 placeholder
이미지 없을 때 노란 배경+이모지 → 다음 패턴:
```html
<div class="w-full h-full bg-gray-100 flex items-center justify-center text-gray-300">
  <AppIcon name="image" :size="28" :stroke-width="1.5" />
</div>
```
(부동산은 name="home", 업소록은 "store", 구인은 "briefcase")

## 7. 자주 하는 변환 예시
헤더 토글(구인/구직 등 세그먼트)은 기존 마크업 유지하되:
- 컨테이너: `bg-gray-100 rounded-xl p-1`
- 활성 탭: `bg-white text-ink font-semibold rounded-lg shadow-sm`
- 비활성 탭: `text-ink-muted`

페이지네이션: 활성 `bg-amber-400 text-white rounded-lg w-8 h-8`, 비활성 `text-ink-muted hover:bg-gray-100 rounded-lg w-8 h-8`.

빈 상태(게시글 없음): 
```html
<div class="py-16 text-center">
  <div class="icon-chip w-14 h-14 bg-gray-100 text-gray-300 mx-auto mb-3"><AppIcon name="..." :size="28" :stroke-width="1.5" /></div>
  <p class="text-sm text-ink-muted">아직 게시글이 없습니다</p>
</div>
```

## 8. 검증
- 수정 후 해당 파일에 `text-amber-900 bg-amber-400` 조합(노란버튼+갈색글씨)이 남아있으면 `btn-primary` 로 교체했는지 확인.
- `border border-gray-200` 가 카드에 남아있으면 `card` 로 교체.
- script 블록 diff 가 0줄인지 확인 (로직 무변경 증명).
