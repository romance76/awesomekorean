<template>
  <!-- 접힌 안내: 글이 있을 때 목록 위에 한 줄로 -->
  <div v-if="variant === 'compact'" class="jg mb-3">
    <button type="button" @click="open = !open" :aria-expanded="open"
      class="w-full flex items-center justify-between gap-2 px-3.5 py-2.5 rounded-xl border text-left transition"
      :class="isHiring ? 'bg-amber-50/70 border-amber-100 hover:bg-amber-50' : 'bg-blue-50/70 border-blue-100 hover:bg-blue-50'">
      <span class="flex items-center gap-2 text-sm font-bold" :class="isHiring ? 'text-amber-800' : 'text-blue-800'">
        <AppIcon name="info" :size="15" />처음이세요? {{ isHiring ? '구인' : '구직' }} 이용 방법 보기
      </span>
      <AppIcon :name="open ? 'chevron-up' : 'chevron-down'" :size="16" :class="isHiring ? 'text-amber-600' : 'text-blue-600'" />
    </button>
    <div v-if="open" class="mt-2 space-y-3">
      <div class="card p-4"><ol class="grid gap-3 sm:grid-cols-2"><li v-for="(s, i) in steps" :key="i" class="flex gap-3">
        <span class="step-no" :class="accentSolid">{{ i + 1 }}</span>
        <div class="min-w-0"><div class="text-sm font-bold text-ink">{{ s.title }}</div><p class="text-xs text-ink-light leading-relaxed mt-0.5">{{ s.body }}</p></div>
      </li></ol></div>
      <div class="flex flex-wrap gap-2">
        <RouterLink :to="writeTo" class="btn-primary px-4 py-2 rounded-lg text-sm"><AppIcon name="edit" :size="14" />{{ writeLabel }}</RouterLink>
      </div>
    </div>
  </div>

  <!-- 전체 안내: 글이 하나도 없을 때 -->
  <section v-else class="jg space-y-4" :aria-labelledby="'jobs-guide-title-' + postType">
    <div class="card overflow-hidden">
      <div class="px-5 py-7 sm:px-8 sm:py-9 text-center" :class="isHiring ? 'bg-gradient-to-b from-amber-50 to-white' : 'bg-gradient-to-b from-blue-50 to-white'">
        <div class="icon-chip w-14 h-14 mx-auto mb-3" :class="isHiring ? 'bg-amber-100 text-amber-600' : 'bg-blue-100 text-blue-600'">
          <AppIcon :name="isHiring ? 'briefcase' : 'user'" :size="28" />
        </div>
        <p class="text-xs font-bold tracking-wide" :class="isHiring ? 'text-amber-700' : 'text-blue-700'">
          {{ isHiring ? '구인 · 직원을 찾고 계신가요?' : '구직 · 일자리를 찾고 계신가요?' }}
        </p>
        <h2 :id="'jobs-guide-title-' + postType" class="mt-1.5 text-xl sm:text-2xl font-extrabold text-ink leading-snug">{{ title }}</h2>
        <p class="mt-2.5 text-sm text-ink-light leading-relaxed max-w-md mx-auto">{{ lead }}</p>

        <p v-if="auth.isLoggedIn && auth.needsVerification" class="mt-3 text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 inline-block">
          이메일 인증을 마치면 글을 올릴 수 있어요. 받은 편지함의 인증 메일을 확인해 주세요.
        </p>

        <div class="mt-5 flex flex-col sm:flex-row gap-2 justify-center items-stretch sm:items-center">
          <RouterLink v-if="auth.isLoggedIn" :to="writeTo" class="btn-primary px-5 py-3 rounded-xl text-sm justify-center">
            <AppIcon name="edit" :size="15" />{{ writeLabel }}
          </RouterLink>
          <template v-else>
            <RouterLink :to="{ path: '/login', query: { redirect: writeTo } }" class="btn-primary px-5 py-3 rounded-xl text-sm justify-center">
              <AppIcon name="log-in" :size="15" />로그인하고 {{ isHiring ? '공고 올리기' : '구직글 올리기' }}
            </RouterLink>
            <RouterLink to="/register" class="btn-secondary px-5 py-3 rounded-xl text-sm justify-center">
              <AppIcon name="user-plus" :size="15" />회원가입
            </RouterLink>
          </template>
          <button v-if="scope === 'local'" type="button" @click="$emit('show-nationwide')" class="btn-secondary px-5 py-3 rounded-xl text-sm justify-center">
            <AppIcon name="globe" :size="15" />전국으로 넓혀 보기
          </button>
        </div>

        <button type="button" @click="$emit('switch-type', isHiring ? 'seeking' : 'hiring')"
          class="mt-4 text-xs font-semibold underline underline-offset-2" :class="isHiring ? 'text-blue-600' : 'text-amber-600'">
          {{ isHiring ? '일자리를 찾고 계시다면 구직글 보러 가기' : '직원을 찾고 계시다면 구인 공고 보러 가기' }}
        </button>
      </div>
    </div>

    <div class="card p-5 sm:p-6">
      <h3 class="flex items-center gap-2 text-base font-extrabold text-ink mb-4">
        <span class="icon-chip w-7 h-7" :class="isHiring ? 'bg-amber-50 text-amber-600' : 'bg-blue-50 text-blue-600'"><AppIcon name="list" :size="15" /></span>
        이렇게 사용해요
      </h3>
      <ol class="grid gap-4 sm:grid-cols-2">
        <li v-for="(s, i) in steps" :key="i" class="flex gap-3">
          <span class="step-no" :class="accentSolid">{{ i + 1 }}</span>
          <div class="min-w-0">
            <div class="text-sm font-bold text-ink">{{ s.title }}</div>
            <p class="text-[13px] text-ink-light leading-relaxed mt-0.5">{{ s.body }}</p>
          </div>
        </li>
      </ol>
    </div>

    <div class="card p-5 sm:p-6">
      <h3 class="flex items-center gap-2 text-base font-extrabold text-ink mb-3">
        <span class="icon-chip w-7 h-7 bg-emerald-50 text-emerald-600"><AppIcon name="sparkles" :size="15" /></span>
        이렇게 쓰면 더 잘 보여요
      </h3>
      <ul class="space-y-2">
        <li v-for="(t, i) in tips" :key="i" class="flex gap-2 text-[13px] text-ink-light leading-relaxed">
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
        일을 시작하기 전에 돈을 먼저 보내라고 하는 곳은 조심해 주세요.
      </p>
    </div>
  </section>
</template>

<script setup>
import { computed, ref } from 'vue'
import { useAuthStore } from '../../stores/auth'
import AppIcon from '../AppIcon.vue'

const props = defineProps({
  postType: { type: String, default: 'hiring' },          // 'hiring' | 'seeking'
  variant: { type: String, default: 'full' },             // 'full'(글이 없을 때) | 'compact'(글이 있을 때 접힌 안내)
  scope: { type: String, default: 'all' },                // 'all'(전국/전체) | 'local'(지역 필터 때문에 비어 있음)
})
defineEmits(['switch-type', 'show-nationwide'])

const auth = useAuthStore()
const open = ref(false)
const isHiring = computed(() => props.postType !== 'seeking')
const accentSolid = computed(() => (isHiring.value ? 'bg-amber-400 text-white' : 'bg-blue-500 text-white'))

const writeTo = computed(() => `/jobs/write?type=${isHiring.value ? 'hiring' : 'seeking'}`)
const writeLabel = computed(() => (isHiring.value ? '구인 공고 올리기' : '구직글 올리기'))

const title = computed(() => {
  if (props.scope === 'local') return isHiring.value ? '이 지역에 첫 구인 공고를 올려 보세요' : '이 지역에 첫 구직글을 올려 보세요'
  return isHiring.value ? '첫 번째 구인 공고를 올려 보세요' : '첫 번째 구직글을 올려 보세요'
})
const lead = computed(() => {
  if (props.scope === 'local') {
    return isHiring.value
      ? '이 지역에는 아직 올라온 공고가 없어요. 전국으로 넓혀 보거나, 가장 먼저 올려 보세요. 공고 올리기는 무료예요.'
      : '이 지역에는 아직 올라온 구직글이 없어요. 전국으로 넓혀 보거나, 가장 먼저 올려 보세요. 글 올리기는 무료예요.'
  }
  return isHiring.value
    ? '아직 올라온 공고가 없어요. 지금 올리면 가까운 곳의 구직자에게 가장 먼저 보여요. 가게든 회사든, 공고 올리기는 무료예요.'
    : '아직 올라온 구직글이 없어요. 어떤 일을 찾는지 올려 두면, 직원이 필요한 사장님이 먼저 연락할 수 있어요. 글 올리기는 무료예요.'
})

const steps = computed(() => (isHiring.value
  ? [
      { title: '로그인하고 이메일 인증하기', body: '회원가입 후 이메일 인증을 마치면 글을 올릴 수 있어요.' },
      { title: '공고 쓰기', body: "등록 화면의 공고 유형에서 '구인'을 고르고, 제목·회사 이름·직종·근무 형태·급여를 적어요." },
      { title: '지역과 연락처 넣기', body: '우편번호(ZIP)를 넣으면 가까운 지역을 찾는 구직자에게 보여요. 연락받을 이메일이나 전화번호도 남겨 주세요.' },
      { title: '지원자 확인하고 연락하기', body: "구직자가 '지원하기'를 누르면 마이페이지 › 내 구인에서 지원자를 볼 수 있어요." },
    ]
  : [
      { title: '로그인하고 이메일 인증하기', body: '회원가입 후 이메일 인증을 마치면 글을 올릴 수 있어요.' },
      { title: '구직글 쓰기', body: "등록 화면의 공고 유형에서 '구직'을 고르고, 희망 직종·근무 형태·희망 급여·경력을 적어요." },
      { title: '이력서 만들어 두기', body: "마이페이지 › 이력서에 한 번 써 두면, 구인 공고에서 '이 이력서로 지원하기'로 바로 지원할 수 있어요." },
      { title: '연락 기다리거나 직접 지원하기', body: '사장님이 남긴 연락을 기다리거나, 마음에 드는 구인 공고에 직접 지원해 보세요.' },
    ]))

const tips = computed(() => (isHiring.value
  ? [
      '급여와 근무 시간을 적으면 지원하는 분이 판단하기 쉬워요.',
      '회사 로고를 올리면 목록에서 눈에 더 잘 띄어요.',
      '보험·식사 같은 복리후생을 골라 두면 공고가 더 믿음직해 보여요.',
      '우편번호를 꼭 넣어 주세요. 넣지 않으면 가까운 지역 검색에 나오지 않을 수 있어요.',
      "더 눈에 띄고 싶다면 '상위노출'(선택)을 쓸 수 있어요. 포인트나 카드로 결제해요.",
      '공고를 올리면 포인트가 적립돼요. (하루 적립 한도가 있어요)',
    ]
  : [
      '일할 수 있는 요일·시간, 경력, 영어 가능 여부를 적으면 사장님이 판단하기 쉬워요.',
      '희망 지역의 우편번호를 넣으면 근처 사장님에게 보여요.',
      '연락받을 이메일이나 전화번호를 꼭 남겨 주세요.',
      "이력서를 만들어 두면 구인 공고마다 '이 이력서로 지원하기' 한 번으로 끝나요.",
      '글을 올리면 포인트가 적립돼요. (하루 적립 한도가 있어요)',
    ]))
</script>

<style scoped>
/* 한글은 낱말 중간이 아니라 띄어쓰기에서 줄바꿈 (아주 긴 낱말만 예외로 꺾음) */
.jg, .jg * { word-break: keep-all; overflow-wrap: break-word; }
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
  margin-top: 1px;
}
</style>
