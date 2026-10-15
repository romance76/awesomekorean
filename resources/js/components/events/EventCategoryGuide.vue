<template>
  <!-- 접힌 안내: 이벤트가 있을 때 목록 위에 한 줄로 -->
  <div v-if="variant === 'compact'" class="ecg mb-3">
    <button type="button" @click="open = !open" :aria-expanded="open"
      class="w-full flex items-center justify-between gap-2 px-3.5 py-2.5 rounded-xl border border-amber-100 bg-amber-50/70 hover:bg-amber-50 text-left transition">
      <span class="flex items-center gap-2 text-sm font-bold text-amber-800">
        <AppIcon name="info" :size="15" />{{ g.name }} 이벤트, 이렇게 열어요 (등록비·절차 안내)
      </span>
      <AppIcon :name="open ? 'chevron-up' : 'chevron-down'" :size="16" class="text-amber-600" />
    </button>
    <div v-if="open" class="mt-2 space-y-3">
      <div class="card p-4">
        <ol class="grid gap-3 sm:grid-cols-2">
          <li v-for="(s, i) in g.steps" :key="i" class="flex gap-3">
            <span class="step-no">{{ i + 1 }}</span>
            <div class="min-w-0"><div class="text-sm font-bold text-ink">{{ s.title }}</div><p class="text-xs text-ink-light leading-relaxed mt-0.5">{{ s.body }}</p></div>
          </li>
        </ol>
      </div>
      <div class="card p-4">
        <div class="text-sm font-bold text-ink mb-2">얼마가 드나요?</div>
        <ul class="space-y-1.5">
          <li v-for="(c, i) in costs" :key="i" class="text-xs text-ink-light leading-relaxed"><b class="text-ink">{{ c.k }}</b> — {{ c.v }}</li>
        </ul>
      </div>
      <RouterLink v-if="g.writable" :to="writeTo" class="btn-primary px-4 py-2 rounded-lg text-sm inline-flex"><AppIcon name="edit" :size="14" />{{ g.cta }}</RouterLink>
      <RouterLink v-else to="/sweepstakes/rules" class="btn-secondary px-4 py-2 rounded-lg text-sm inline-flex"><AppIcon name="file-text" :size="14" />경품 추첨 공식 규정 보기</RouterLink>
    </div>
  </div>

  <!-- 전체 안내: 이벤트가 하나도 없을 때 -->
  <section v-else class="ecg space-y-4" :aria-labelledby="'ecg-title-' + category">
    <div class="card overflow-hidden">
      <div class="px-5 py-7 sm:px-8 sm:py-9 text-center bg-gradient-to-b from-amber-50 to-white">
        <div class="text-4xl mb-2" aria-hidden="true">{{ g.emoji }}</div>
        <p class="text-xs font-bold tracking-wide text-amber-700">{{ g.name }} 이벤트</p>
        <h2 :id="'ecg-title-' + category" class="mt-1.5 text-xl sm:text-2xl font-extrabold text-ink leading-snug">{{ g.title }}</h2>
        <p class="mt-2.5 text-sm text-ink-light leading-relaxed max-w-md mx-auto">{{ g.lead }}</p>

        <p v-if="g.writable && auth.isLoggedIn && auth.needsVerification" class="mt-3 text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 inline-block">
          이메일 인증을 마치면 이벤트를 등록할 수 있어요. 받은 편지함의 인증 메일을 확인해 주세요.
        </p>

        <div class="mt-5 flex flex-col sm:flex-row gap-2 justify-center items-stretch sm:items-center">
          <template v-if="g.writable">
            <RouterLink v-if="auth.isLoggedIn" :to="writeTo" class="btn-primary px-5 py-3 rounded-xl text-sm justify-center">
              <AppIcon name="edit" :size="15" />{{ g.cta }}
            </RouterLink>
            <template v-else>
              <RouterLink :to="{ path: '/login', query: { redirect: writeTo } }" class="btn-primary px-5 py-3 rounded-xl text-sm justify-center">
                <AppIcon name="log-in" :size="15" />로그인하고 이벤트 등록
              </RouterLink>
              <RouterLink to="/register" class="btn-secondary px-5 py-3 rounded-xl text-sm justify-center">
                <AppIcon name="user-plus" :size="15" />회원가입
              </RouterLink>
            </template>
          </template>
          <RouterLink v-else to="/sweepstakes/rules" class="btn-secondary px-5 py-3 rounded-xl text-sm justify-center">
            <AppIcon name="file-text" :size="15" />경품 추첨 공식 규정 보기
          </RouterLink>
        </div>
      </div>
    </div>

    <div class="card p-5 sm:p-6">
      <h3 class="flex items-center gap-2 text-base font-extrabold text-ink mb-4">
        <span class="icon-chip w-7 h-7 bg-amber-50 text-amber-600"><AppIcon name="list" :size="15" /></span>
        {{ g.writable ? '이렇게 만들어요' : '이렇게 진행돼요' }}
      </h3>
      <ol class="grid gap-4 sm:grid-cols-2">
        <li v-for="(s, i) in g.steps" :key="i" class="flex gap-3">
          <span class="step-no">{{ i + 1 }}</span>
          <div class="min-w-0">
            <div class="text-sm font-bold text-ink">{{ s.title }}</div>
            <p class="text-[13px] text-ink-light leading-relaxed mt-0.5">{{ s.body }}</p>
          </div>
        </li>
      </ol>
    </div>

    <div class="card p-5 sm:p-6">
      <h3 class="flex items-center gap-2 text-base font-extrabold text-ink mb-3">
        <span class="icon-chip w-7 h-7 bg-sky-50 text-sky-600"><AppIcon name="dollar-sign" :size="15" /></span>
        {{ g.writable ? '얼마가 드나요? (결제 안내)' : '참여하려면 결제가 필요한가요?' }}
      </h3>
      <ul class="space-y-2">
        <li v-for="(c, i) in costs" :key="i" class="flex gap-2 text-[13px] text-ink-light leading-relaxed">
          <AppIcon name="check" :size="15" class="text-sky-500 mt-0.5 flex-shrink-0" />
          <span><b class="text-ink">{{ c.k }}</b> — {{ c.v }}</span>
        </li>
      </ul>
    </div>

    <div v-if="g.tips.length" class="card p-5 sm:p-6">
      <h3 class="flex items-center gap-2 text-base font-extrabold text-ink mb-3">
        <span class="icon-chip w-7 h-7 bg-emerald-50 text-emerald-600"><AppIcon name="sparkles" :size="15" /></span>
        이렇게 하면 더 잘 보여요
      </h3>
      <ul class="space-y-2">
        <li v-for="(t, i) in g.tips" :key="i" class="flex gap-2 text-[13px] text-ink-light leading-relaxed">
          <AppIcon name="check" :size="15" class="text-emerald-500 mt-0.5 flex-shrink-0" />
          <span>{{ t }}</span>
        </li>
      </ul>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3.5 flex gap-2.5">
      <AppIcon name="shield" :size="17" class="text-gray-500 mt-0.5 flex-shrink-0" />
      <p class="text-xs text-ink-light leading-relaxed">
        <b class="text-ink">안전하게 이용하세요.</b>
        주민등록번호·여권번호·계좌번호 같은 민감한 정보는 글에 적지 마세요.
        참가비를 미리 보내라고 하는 낯선 이벤트는 주최자를 먼저 확인해 주세요.
      </p>
    </div>
  </section>
</template>

<script setup>
import { computed, ref } from 'vue'
import { useAuthStore } from '../../stores/auth'
import AppIcon from '../AppIcon.vue'

const props = defineProps({
  category: { type: String, required: true },   // culture | networking | education | community | sports | food | awesomekorean | sweepstakes
  variant: { type: String, default: 'full' },   // 'full' | 'compact'
})
const auth = useAuthStore()
const open = ref(false)

// 일반 회원이 직접 여는 이벤트(문화·네트워킹·교육·커뮤니티·스포츠·음식)는 등록비 없음.
// 참가비 칸은 주최자가 현장에서 받는 금액을 알리는 표시일 뿐, 사이트가 대신 받지 않음.
const COMMON_STEPS_TAIL = [
  { title: '올리면 바로 목록에 나와요', body: '등록하면 바로 이 목록에 올라가고, 회원들이 "참가"를 눌러 신청해요. 수정·삭제는 내가 쓴 이벤트 화면에서 언제든 할 수 있어요.' },
]
const GUIDES = {
  culture: {
    name: '문화', emoji: '🎭', title: '우리 동네 문화 이벤트를 열어 보세요',
    lead: '공연·전시·명절 행사·전통문화 체험처럼 한인 이웃이 함께 즐길 수 있는 행사를 올리는 곳이에요.',
    steps: [
      { title: '로그인 · 이메일 인증', body: '이벤트를 올리려면 로그인하고 이메일 인증을 마쳐야 해요.' },
      { title: '"이벤트 등록" 누르기', body: '이 화면 오른쪽 위(모바일은 상단) 등록 버튼을 누르면 카테고리가 "문화"로 선택된 입력 화면이 열려요.' },
      { title: '제목·날짜·장소 입력', body: '제목, 시작/끝 일시, 장소와 주소를 적어요. 온라인이 아니라면 주소를 꼭 넣어야 지도·내 주변 검색에 나와요.' },
      { title: '사진과 상세 내용', body: '대표 사진 1장과, 프로그램 순서·준비물·주차 안내 같은 상세 내용을 적어요.' },
      ...COMMON_STEPS_TAIL,
    ],
    tips: ['행사 사진이나 포스터를 넣으면 클릭이 크게 늘어요.', '날짜·시간·장소는 제목이나 첫 줄에 한 번 더 적어 주세요.', '최대 참가자 수를 정해 두면 마감 관리가 쉬워요.'],
    cta: '문화 이벤트 등록하기',
  },
  networking: {
    name: '네트워킹', emoji: '🤝', title: '사람을 만나는 네트워킹 모임을 열어 보세요',
    lead: '직장인 모임, 창업·비즈니스 교류, 동문회, 취미 번개처럼 새로운 인연을 만드는 모임을 올리는 곳이에요.',
    steps: [
      { title: '로그인 · 이메일 인증', body: '이벤트를 올리려면 로그인하고 이메일 인증을 마쳐야 해요.' },
      { title: '"이벤트 등록" 누르기', body: '등록 버튼을 누르면 카테고리가 "네트워킹"으로 선택돼요.' },
      { title: '누가 오면 좋은지 적기', body: '대상(예: 애틀랜타 거주 30~40대 직장인), 모임 목적, 진행 방식을 구체적으로 적어요.' },
      { title: '인원·참가비 정하기', body: '최대 참가자 수와 참가비를 정해요. 참가비가 없으면 0 으로 두면 "무료"로 표시돼요.' },
      ...COMMON_STEPS_TAIL,
    ],
    tips: ['첫 모임은 소규모(10~20명)로 시작하면 분위기가 좋아요.', '모임 장소는 찾아가기 쉬운 공개 장소(카페·식당)를 추천해요.', '모임이 끝나면 댓글로 후기를 남겨 다음 모임으로 이어 보세요.'],
    cta: '네트워킹 이벤트 등록하기',
  },
  education: {
    name: '교육', emoji: '📚', title: '배우고 나누는 교육 이벤트를 열어 보세요',
    lead: '세미나·강좌·워크숍·영어/세금/이민 설명회처럼 정보를 나누는 행사를 올리는 곳이에요.',
    steps: [
      { title: '로그인 · 이메일 인증', body: '이벤트를 올리려면 로그인하고 이메일 인증을 마쳐야 해요.' },
      { title: '"이벤트 등록" 누르기', body: '등록 버튼을 누른 뒤 카테고리를 "교육/세미나"로 골라 주세요.' },
      { title: '강의 내용·강사 소개', body: '무엇을 배우는지, 누구에게 도움이 되는지, 강사 이력과 준비물을 상세 내용에 적어요.' },
      { title: '온라인/현장 · 참가비', body: '현장이면 주소를, 온라인이면 접속 방법(관련 URL)을 넣어요. 수강료가 있으면 가격 칸에 적어요.' },
      ...COMMON_STEPS_TAIL,
    ],
    tips: ['신청 마감일과 정원을 제목 또는 첫 줄에 적어 두세요.', '자료·강의 요약을 댓글로 공유하면 참석 못 한 분도 보게 돼요.', '유료 강의는 환불 규정을 상세 내용에 꼭 적어 주세요.'],
    cta: '교육 이벤트 등록하기',
  },
  community: {
    name: '커뮤니티', emoji: '👥', title: '이웃과 함께하는 커뮤니티 행사를 열어 보세요',
    lead: '교회·동호회·학부모 모임, 봉사활동, 바자회처럼 지역 한인이 함께 하는 행사를 올리는 곳이에요.',
    steps: [
      { title: '로그인 · 이메일 인증', body: '이벤트를 올리려면 로그인하고 이메일 인증을 마쳐야 해요.' },
      { title: '"이벤트 등록" 누르기', body: '등록 버튼을 누르면 카테고리를 "커뮤니티"로 고를 수 있어요.' },
      { title: '주최와 대상 적기', body: '주최 단체(또는 개인)를 적고, 누가 참여할 수 있는지 상세 내용에 밝혀 주세요.' },
      { title: '일시·장소·정원', body: '날짜, 장소, 최대 참가자 수를 정해요. 정원이 없으면 0(무제한)으로 두세요.' },
      ...COMMON_STEPS_TAIL,
    ],
    tips: ['정기 모임이라면 "매월 첫째 토요일"처럼 반복 일정을 본문에 적어 주세요.', '봉사·기부 행사는 사용처를 투명하게 알리면 참여가 늘어요.'],
    cta: '커뮤니티 이벤트 등록하기',
  },
  sports: {
    name: '스포츠', emoji: '⚽', title: '함께 뛰는 스포츠 이벤트를 열어 보세요',
    lead: '축구·골프·테니스·배드민턴·러닝 모임이나 친선 대회를 올리는 곳이에요.',
    steps: [
      { title: '로그인 · 이메일 인증', body: '이벤트를 올리려면 로그인하고 이메일 인증을 마쳐야 해요.' },
      { title: '"이벤트 등록" 누르기', body: '등록 버튼을 누른 뒤 카테고리를 "스포츠"로 골라 주세요.' },
      { title: '종목·수준·준비물', body: '종목, 실력 수준(초보 환영 등), 복장·준비물을 적어요.' },
      { title: '경기장 주소·정원', body: '경기장 주소와 최대 인원을 넣어요. 대관료를 나눠 낸다면 가격 칸에 1인 금액을 적어 알리세요.' },
      ...COMMON_STEPS_TAIL,
    ],
    tips: ['우천 시 일정(취소/연기)을 미리 적어 두면 문의가 줄어요.', '대회라면 참가 팀 수·상품·규칙을 본문에 정리해 주세요.'],
    cta: '스포츠 이벤트 등록하기',
  },
  food: {
    name: '음식', emoji: '🍽️', title: '맛으로 모이는 음식 이벤트를 열어 보세요',
    lead: '맛집 탐방, 쿠킹 클래스, 푸드 페스티벌, 장터·시식회 같은 음식 행사를 올리는 곳이에요.',
    steps: [
      { title: '로그인 · 이메일 인증', body: '이벤트를 올리려면 로그인하고 이메일 인증을 마쳐야 해요.' },
      { title: '"이벤트 등록" 누르기', body: '등록 버튼을 누른 뒤 카테고리를 "음식/맛집"으로 골라 주세요.' },
      { title: '메뉴·가격 알리기', body: '메뉴, 1인 가격, 예약 방법을 상세 내용에 적고 음식 사진을 넣어요.' },
      { title: '일시·장소·정원', body: '식당이나 행사장 주소와 최대 인원을 정해요.' },
      ...COMMON_STEPS_TAIL,
    ],
    tips: ['먹음직스러운 사진 한 장이 가장 큰 홍보예요.', '알레르기·채식 가능 여부를 적어 두면 좋아요.', '업소 홍보는 업소록 등록과 함께 올리면 더 많이 노출돼요.'],
    cta: '음식 이벤트 등록하기',
  },
  awesomekorean: {
    name: '어썸코리안', emoji: '⭐', title: '어썸코리안 공식 이벤트',
    lead: '운영진이 직접 여는 공식 이벤트예요. 새 이벤트가 열리면 이곳과 메인 배너에 가장 먼저 안내돼요.',
    writable: false,
    steps: [
      { title: '이벤트 확인', body: '목록에서 진행 중인 공식 이벤트를 열어 참여 방법과 기간을 확인해요.' },
      { title: '참여하기 누르기', body: '이벤트 안의 "참여하기" 버튼으로 해당 활동 화면으로 이동해요.' },
      { title: '완료 인증', body: '이벤트에서 안내하는 대로 활동을 마치면 인증 사진·파일을 내요.' },
      { title: '보상 받기', body: '운영진 확인 후 포인트 또는 Entry 가 지급돼요. 지급 내역은 마이페이지에서 볼 수 있어요.' },
    ],
    tips: ['알림을 켜 두면 새 공식 이벤트를 놓치지 않아요.'],
    cta: '',
  },
  sweepstakes: {
    name: '경품 추첨', emoji: '🎁', title: '어썸코리안 경품 추첨',
    lead: '사이트가 여는 경품 추첨이에요. 이벤트 완료 등으로 모은 Entry 로 응모하고, 추첨 시각에 당첨자가 발표돼요.',
    writable: false,
    steps: [
      { title: 'Entry 모으기', body: '이벤트 완료 인증, 출석 등 안내된 활동으로 Entry 를 모아요.' },
      { title: '경품 추첨 이벤트 열기', body: '목록에서 진행 중인 추첨을 열어 경품과 응모 마감 시각을 확인해요.' },
      { title: 'Entry 로 응모', body: '참가 버튼으로 가진 Entry 중 원하는 만큼 응모해요.' },
      { title: '추첨 · 당첨 안내', body: '추첨 시각에 화면에서 결과가 발표되고, 당첨되면 수령 절차가 안내돼요.' },
    ],
    tips: ['공식 규정(응모 자격·나이·지역·무료 응모 방법)은 규정 페이지에서 확인할 수 있어요.'],
    cta: '',
  },
}

const g = computed(() => {
  const base = GUIDES[props.category] || GUIDES.culture
  return { writable: true, ...base }
})

const costs = computed(() => {
  if (props.category === 'awesomekorean') return [
    { k: '참여비', v: '무료예요. 결제 없이 참여해요.' },
    { k: '보상', v: '이벤트마다 포인트 또는 Entry 가 지급돼요(이벤트 화면에 표시).' },
  ]
  if (props.category === 'sweepstakes') return [
    { k: '응모비', v: '돈을 내지 않아요. Entry 로만 응모하고, 결제 없이도 참여할 수 있어요(무료 응모 방법은 공식 규정 참고).' },
    { k: '추첨 이벤트 개설', v: '운영진만 열 수 있어요. 회원이 직접 열 수는 없어요.' },
  ]
  return [
    { k: '이벤트 등록비', v: '무료예요. 등록할 때 결제나 포인트 차감이 없어요.' },
    { k: '참가비', v: '가격 칸에 적는 금액은 주최자가 직접 받는 참가비를 알리는 표시예요. 사이트가 대신 받지 않아요. 0 으로 두면 "무료"로 표시돼요.' },
    { k: '완료 인증 보상', v: '참가자가 인증을 내면 운영진 확인 후 사이트가 Entry(기본 1개, 회원은 최대 3개까지 설정)를 지급해요. 주최자가 따로 내는 돈은 없어요.' },
    { k: '필요한 것', v: '로그인과 이메일 인증만 있으면 돼요.' },
  ]
})

const writeTo = computed(() => ({ path: '/events/create', query: { category: props.category } }))
</script>

<style scoped>
.ecg, .ecg * { word-break: keep-all; overflow-wrap: break-word; }
.step-no {
  flex-shrink: 0;
  width: 28px;
  height: 28px;
  border-radius: 9999px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 13px;
  font-weight: 800;
  color: #fff;
  background: #f59e0b;
}
</style>
