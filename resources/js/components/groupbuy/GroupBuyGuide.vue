<template>
  <!-- 접힌 안내: 목록 위 한 줄 -->
  <div v-if="variant === 'compact'" class="gbg mb-3">
    <button type="button" @click="open = !open" :aria-expanded="open"
      class="w-full flex items-center justify-between gap-2 px-3.5 py-2.5 rounded-xl border border-lime-100 bg-lime-50/70 hover:bg-lime-50 text-left transition">
      <span class="flex items-center gap-2 text-sm font-bold text-lime-800">
        <AppIcon name="info" :size="15" />공동구매 이용안내 — 참여 방법 · 열기 · 결제 · 환불
      </span>
      <AppIcon :name="open ? 'chevron-up' : 'chevron-down'" :size="16" class="text-lime-600" />
    </button>
  </div>

  <section v-if="variant !== 'compact' || open" class="gbg space-y-4" :class="variant === 'compact' ? 'mb-4' : ''">
    <div class="card overflow-hidden">
      <div class="px-5 py-7 sm:px-8 sm:py-9 text-center bg-gradient-to-b from-lime-50 to-white">
        <div class="icon-chip w-14 h-14 mx-auto mb-3 bg-lime-100 text-lime-600"><AppIcon name="shopping-bag" :size="28" /></div>
        <p class="text-xs font-bold tracking-wide text-lime-700">공동구매 이용안내</p>
        <h2 class="mt-1.5 text-xl sm:text-2xl font-extrabold text-ink leading-snug">함께 사면 더 싸요</h2>
        <p class="mt-2.5 text-sm text-ink-light leading-relaxed max-w-md mx-auto">
          같은 물건을 사려는 이웃이 모이면 인원수에 따라 가격이 내려가는 장터예요. 참여하는 방법과 직접 여는 방법, 결제·환불 규칙을 한눈에 정리했어요.
        </p>
        <div class="mt-5 flex flex-col sm:flex-row gap-2 justify-center">
          <RouterLink v-if="auth.isLoggedIn" to="/groupbuy/create" class="btn-primary px-5 py-3 rounded-xl text-sm justify-center"><AppIcon name="edit" :size="15" />공동구매 열기</RouterLink>
          <RouterLink v-else :to="{ path: '/login', query: { redirect: '/groupbuy/create' } }" class="btn-primary px-5 py-3 rounded-xl text-sm justify-center"><AppIcon name="log-in" :size="15" />로그인하고 열기</RouterLink>
        </div>
      </div>
    </div>

    <!-- 탭 -->
    <div class="flex gap-1.5">
      <button v-for="t in tabs" :key="t.key" type="button" @click="tab = t.key"
        class="flex-1 py-2.5 rounded-xl text-sm font-bold border transition-colors"
        :class="tab === t.key ? 'bg-lime-50 text-lime-800 border-lime-300' : 'border-gray-200 text-ink-light hover:bg-gray-50'">{{ t.label }}</button>
    </div>

    <template v-for="sec in [current]" :key="tab">
      <div class="card p-5 sm:p-6">
        <h3 class="flex items-center gap-2 text-base font-extrabold text-ink mb-4">
          <span class="icon-chip w-7 h-7 bg-lime-50 text-lime-600"><AppIcon name="list" :size="15" /></span>{{ sec.stepsTitle }}
        </h3>
        <ol class="grid gap-4 sm:grid-cols-2">
          <li v-for="(s, i) in sec.steps" :key="i" class="flex gap-3">
            <span class="step-no">{{ i + 1 }}</span>
            <div class="min-w-0"><div class="text-sm font-bold text-ink">{{ s.title }}</div><p class="text-[13px] text-ink-light leading-relaxed mt-0.5">{{ s.body }}</p></div>
          </li>
        </ol>
      </div>

      <div class="card p-5 sm:p-6">
        <h3 class="flex items-center gap-2 text-base font-extrabold text-ink mb-3">
          <span class="icon-chip w-7 h-7 bg-sky-50 text-sky-600"><AppIcon name="dollar-sign" :size="15" /></span>{{ sec.costTitle }}
        </h3>
        <ul class="space-y-2">
          <li v-for="(c, i) in sec.costs" :key="i" class="flex gap-2 text-[13px] text-ink-light leading-relaxed">
            <AppIcon name="check" :size="15" class="text-sky-500 mt-0.5 flex-shrink-0" />
            <span><b class="text-ink">{{ c.k }}</b> — {{ c.v }}</span>
          </li>
        </ul>
      </div>
    </template>

    <div class="card p-5 sm:p-6">
      <h3 class="flex items-center gap-2 text-base font-extrabold text-ink mb-3">
        <span class="icon-chip w-7 h-7 bg-emerald-50 text-emerald-600"><AppIcon name="sparkles" :size="15" /></span>자주 묻는 질문
      </h3>
      <dl class="space-y-3">
        <div v-for="(f, i) in faqs" :key="i">
          <dt class="text-sm font-bold text-ink">Q. {{ f.q }}</dt>
          <dd class="text-[13px] text-ink-light leading-relaxed mt-0.5">{{ f.a }}</dd>
        </div>
      </dl>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3.5 flex gap-2.5">
      <AppIcon name="shield" :size="17" class="text-gray-500 mt-0.5 flex-shrink-0" />
      <p class="text-xs text-ink-light leading-relaxed">
        <b class="text-ink">안전하게 이용하세요.</b>
        공동구매는 어썸코리안이 상품을 파는 것이 아니라 회원이 여는 모임이에요. 소액 공동구매가 아닌 경우 사업자 등록증을 확인해 승인된 것만 목록에 나오지만,
        참여 전에 상품 설명·배송/픽업 방법을 꼭 읽어 보세요. 사이트 밖 계좌로 따로 송금하라는 요구는 따르지 마세요.
      </p>
    </div>
  </section>
</template>

<script setup>
import { computed, ref } from 'vue'
import { useAuthStore } from '../../stores/auth'
import AppIcon from '../AppIcon.vue'

defineProps({ variant: { type: String, default: 'full' } })   // 'full' | 'compact'
const auth = useAuthStore()
const open = ref(false)
const tab = ref('join')
const tabs = [{ key: 'join', label: '🛒 참여하기' }, { key: 'open', label: '📣 열기(개설)' }]

// 규칙은 GroupBuyController(참여·취소·삭제·승인) 와 GroupBuyCreate 기준
const SECTIONS = {
  join: {
    stepsTitle: '이렇게 참여해요',
    costTitle: '얼마를 내나요? (결제 안내)',
    steps: [
      { title: '로그인 · 이메일 인증', body: '참여하려면 로그인이 필요해요. 목록 구경은 로그인 없이도 돼요.' },
      { title: '공동구매 고르기', body: '목록에서 상품, 현재 가격, 남은 기간, 모인 인원을 확인해요. "모집중" 표시가 있는 것만 참여할 수 있어요.' },
      { title: '수량 정하고 참여', body: '상세 화면에서 수량을 고르고 참여 버튼을 눌러요. 결제 방식은 공동구매마다 달라요(아래 참고).' },
      { title: '인원이 모이면 확정', body: '인원이 모이면 "확정"이 되고, 주최자가 안내한 방법(배송·픽업)으로 상품을 받아요.' },
    ],
    costs: [
      { k: '참여비', v: '공동구매마다 달라요. 주최자가 "참가비 없음 / 포인트" 중에서 정해요. 상세 화면에서 확인하세요.' },
      { k: '포인트 결제', v: '$1 = 100P 로 계산돼요. 예) 현재가 $20 × 2개 = 4,000P가 참여 즉시 차감돼요. 포인트가 모자라면 참여할 수 없어요.' },
      { k: '인원이 늘면 더 싸져요', v: '주최자가 정한 할인 단계(예: 10명 이상 10% 할인)에 맞춰 현재 가격이 내려가요. 상세 화면에 "다음 할인까지 N명" 이 표시돼요.' },
      { k: '참여 보너스', v: '결제까지 마친 참여에는 하루 한도 안에서 보너스 포인트가 지급될 수 있어요.' },
      { k: '취소·환불', v: '"확정" 전에는 참여를 취소할 수 있고, 포인트로 낸 금액은 바로 돌려받아요. 확정된 뒤에는 취소할 수 없어요. 카드 결제 취소는 문의해 주세요.' },
    ],
  },
  open: {
    stepsTitle: '이렇게 열어요',
    costTitle: '얼마가 드나요? (등록·승인 안내)',
    steps: [
      { title: '로그인 · 이메일 인증', body: '공동구매를 열려면 로그인하고 이메일 인증을 마쳐야 해요.' },
      { title: '"등록" 누르고 정보 입력', body: '제목, 카테고리(식품·뷰티·전자제품·생활용품·패션·건강·교육·기타), 상세 설명, 원래 가격과 공동구매 최저가를 적어요. 사진은 최대 5장까지 올려요.' },
      { title: '인원·마감·종료 방식', body: '최소/최대 인원과 마감일을 정하고, 종료 방식을 골라요: ①목표 인원 달성 시 자동 종료 ②기한 종료 시 현재 인원으로 진행 ③마감일까지 최소 인원 미달이면 자동 취소.' },
      { title: '결제 방식·할인 단계', body: '참가비 없음 / 포인트 중에서 고르고, 원하면 "10명 이상 10%"처럼 할인 단계를 만들어요.' },
      { title: '소액이면 바로, 아니면 승인', body: '예상 최대 총액(가격 × 최대 인원)이 $500 미만이면 소액 공동구매예요. 최대 50명·50개·마감 14일 이내이고, 사업자 등록증 없이 바로 목록에 나와요. 개설자는 동시에 공동구매를 1개만 열 수 있어요.' },
      { title: '$500 이상은 사업자 등록증', body: '예상 총액이 $500 이상이거나 인원이 50명을 넘으면 사업자 등록증(PDF·JPG·PNG, 10MB 이하)을 첨부하고 관리자 승인을 받아야 해요. 마감은 1개월 이내예요. 승인되면 알림이 오고, 반려되면 사유와 함께 알림이 와요.' },
    ],
    costs: [
      { k: '등록비', v: '무료예요. 등록할 때 결제나 포인트 차감이 없어요.' },
      { k: '승인 보상', v: '승인되면 글 등록 포인트가 지급돼요(스팸 방지를 위해 승인 시점에 지급).' },
      { k: '필요한 것', v: '로그인 · 이메일 인증 · (총액 $500 이상일 때) 사업자 등록증.' },
      { k: '수정·삭제', v: '승인되기 전에만 수정할 수 있어요. 삭제하면 이미 결제한 참여자에게 포인트가 자동 환불되고, 확정된 공동구매는 삭제할 수 없어요.' },
      { k: '참여자 결제 금액', v: '포인트로 받는 참여비는 공동구매 상품 대금이에요. 정확한 가격·수량·배송비는 상세 설명에 분명히 적어 주세요.' },
    ],
  },
}
const current = computed(() => SECTIONS[tab.value])

const faqs = [
  { q: '"모집중"인데 참여가 안 돼요.', a: '마감일이 지났거나 정원이 찬 경우예요. 목록에서 "마감"·"확정" 표시를 확인해 주세요.' },
  { q: '인원이 안 모이면 어떻게 되나요?', a: '주최자가 고른 종료 방식에 따라요. "최소 인원 미달 시 자동 취소"라면 마감일에 취소돼요.' },
  { q: '내가 참여 중인 공동구매는 어디서 보나요?', a: '목록에서 "참여중" 표시가 붙어 있어요.' },
  { q: '승인에는 얼마나 걸리나요?', a: '소액($500 미만) 공동구매는 승인 없이 바로 올라가요. 그 이상은 관리자가 사업자 등록증을 확인하는 대로 처리하고, 승인·반려 결과는 알림으로 와요.' },
]
</script>

<style scoped>
.gbg, .gbg * { word-break: keep-all; overflow-wrap: break-word; }
.step-no { flex-shrink: 0; width: 28px; height: 28px; border-radius: 9999px; display: inline-flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 800; color: #fff; background: #84cc16; }
</style>
