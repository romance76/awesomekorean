<!-- 전체창 배너 — 화면 전체를 덮는 안내(소프트 오픈 등). PopupBanner(실제 표시)와 관리자 미리보기가 같이 쓴다.
     banner.extra (관리자 › 팝업 배너 › 전체창 형식에서 편집) 로 두 가지로 만든다:
     ① 그림형: img_desktop(PC)·img_mobile(휴대폰) 완성 그림을 화면에 꽉 맞춰 보여 주고, 그림 위의 버튼 자리에 눌리는 영역(hotspots)을 얹는다.
        hotspots_desktop / hotspots_mobile = [{ type: close|cta|link|check, x, y, w, h (그림 크기 대비 %), link }]
          close 닫기 · cta 버튼(cta_link 로 이동, 없으면 닫기) · link 이동 · check "오늘 하루 열지 않음"
        그림에 닫기/체크가 없으면 코드가 직접 ✕ 와 아래 체크칸을 붙인다. 그림이 안 불러와지면 ②로 대신 보여 준다.
     ② 글자형: eyebrow·headline·subtitle·show_open_event(오픈 이벤트 혜택 실시간)·features_title/features_text("아이콘|이름|주소|설명")·note·cta_label/cta_link -->
<template>
<Teleport to="body">
  <!-- ───────── ① 그림형 ───────── -->
  <div v-if="imgUrl && !imgFailed" class="fixed inset-0 z-[9998] fb-bg flex flex-col text-white" role="dialog" aria-modal="true" :aria-label="x.headline || '안내'"
    @keydown.esc="$emit('close', hideToday)" tabindex="-1" ref="rootEl">
    <div ref="stageEl" class="relative flex-1 min-h-0 flex items-center justify-center"
      :style="{ paddingTop: 'env(safe-area-inset-top, 0px)' }">
      <div class="relative shadow-2xl" :style="boxStyle">
        <img :src="imgUrl" alt="" class="block w-full h-full select-none" draggable="false" @load="onLoad" @error="imgFailed = true" />
        <!-- 눌리는 영역 -->
        <template v-for="(h, i) in spots" :key="i">
          <button v-if="h.type !== 'check'" type="button" @click="spotClick(h)"
            class="absolute fb-spot" :style="spotStyle(h)" :aria-label="spotLabel(h)" :class="debug ? 'fb-debug' : ''"></button>
          <button v-else type="button" @click="hideToday = !hideToday" role="checkbox" :aria-checked="hideToday"
            class="absolute fb-spot" :style="spotStyle(h)" aria-label="오늘 하루 이 창을 열지 않음" :class="debug ? 'fb-debug' : ''">
            <span v-if="hideToday" class="absolute left-[2%] top-0 bottom-0 flex items-center text-white font-black leading-none drop-shadow" :style="{ fontSize: checkSize }">✓</span>
          </button>
        </template>
      </div>
    </div>

    <!-- 그림에 닫기가 없으면 코드가 붙인다 -->
    <button v-if="!hasSpot('close')" type="button" @click="$emit('close', hideToday)" aria-label="닫기"
      class="absolute right-3 w-10 h-10 rounded-full bg-black/25 hover:bg-black/40 text-white flex items-center justify-center text-xl leading-none backdrop-blur-sm"
      :style="{ top: 'calc(env(safe-area-inset-top, 0px) + 12px)' }">✕</button>

    <!-- 그림에 "오늘 하루 열지 않음"이 없으면 코드가 아래에 붙인다 (하루 1회 배너일 때만) -->
    <label v-if="!hasSpot('check') && banner.display_mode === 'once_per_day'"
      class="shrink-0 flex items-center justify-center gap-2 py-2.5 text-[13px] text-white/95 select-none cursor-pointer bg-black/10"
      :style="{ paddingBottom: 'calc(env(safe-area-inset-bottom, 0px) + 10px)' }">
      <input type="checkbox" v-model="hideToday" class="w-4 h-4 accent-pink-500" />
      오늘 하루 이 창을 열지 않음
    </label>
  </div>

  <!-- ───────── ② 글자형 (그림이 없거나 못 불러올 때) ───────── -->
  <div v-else class="fixed inset-0 z-[9998] overflow-y-auto text-white fb-bg" role="dialog" aria-modal="true" :aria-label="x.headline || '안내'"
    @keydown.esc="$emit('close', hideToday)" tabindex="-1" ref="rootEl">
    <div aria-hidden="true" class="pointer-events-none absolute -top-24 -right-24 w-72 h-72 rounded-full bg-white/10 blur-2xl"></div>
    <div aria-hidden="true" class="pointer-events-none absolute -bottom-28 -left-20 w-80 h-80 rounded-full bg-pink-300/20 blur-3xl"></div>

    <div class="relative min-h-full flex flex-col" :style="{ paddingTop: 'env(safe-area-inset-top, 0px)', paddingBottom: 'env(safe-area-inset-bottom, 0px)' }">
      <div class="flex items-center justify-between px-5 pt-4">
        <img v-if="logo" :src="logo" alt="AwesomeKorean" class="h-9 w-auto object-contain drop-shadow" />
        <span v-else class="font-black tracking-tight text-lg">AWESOME KOREAN</span>
        <button type="button" @click="$emit('close', hideToday)" aria-label="닫기"
          class="w-10 h-10 rounded-full bg-white/20 hover:bg-white/30 flex items-center justify-center text-xl leading-none">✕</button>
      </div>

      <div class="flex-1 w-full max-w-lg lg:max-w-3xl mx-auto px-5 py-6 flex flex-col justify-center">
        <div v-if="x.eyebrow" class="self-center mb-3 px-3 py-1 rounded-full bg-white/20 text-[12px] font-bold tracking-[0.2em]">{{ x.eyebrow }}</div>
        <h1 class="text-center text-[30px] sm:text-4xl lg:text-5xl font-black leading-tight break-keep">{{ x.headline }}</h1>
        <p v-if="x.subtitle" class="mt-3 text-center text-[15px] sm:text-base lg:text-lg leading-relaxed text-white/90 break-keep">{{ x.subtitle }}</p>

        <div v-if="x.show_open_event && event" class="mt-6 rounded-2xl bg-white text-ink p-4 shadow-xl">
          <div class="flex items-center justify-between gap-2">
            <div class="font-bold text-[15px] text-pink-600">🎉 {{ event.headline }}</div>
            <div v-if="endLabel" class="text-[11px] text-ink-muted">{{ endLabel }}까지</div>
          </div>
          <div v-if="event.perks && event.perks.length" class="mt-2.5 flex flex-wrap gap-1.5">
            <span v-for="p in event.perks.slice(0, 6)" :key="p" class="px-2.5 py-1 rounded-full bg-amber-50 text-amber-700 text-[12px] font-bold">{{ p }}</span>
            <span v-if="event.perks.length > 6" class="px-2.5 py-1 rounded-full bg-gray-100 text-ink-muted text-[12px] font-bold">외 {{ event.perks.length - 6 }}가지</span>
          </div>
          <div v-else-if="event.subline" class="mt-2 text-[13px] text-ink-light">{{ event.subline }}</div>
        </div>

        <div v-if="features.length" class="mt-6">
          <div v-if="x.features_title" class="mb-2 text-center text-[13px] font-bold text-white/80">{{ x.features_title }}</div>
          <div class="grid grid-cols-3 gap-2.5">
            <component :is="f.link ? 'button' : 'div'" v-for="(f, i) in features" :key="i" type="button" @click="f.link && go(f.link)"
              class="rounded-2xl bg-white/15 backdrop-blur-sm border border-white/20 py-3.5 px-1 flex flex-col items-center gap-1 text-center"
              :class="f.link ? 'active:scale-95 transition hover:bg-white/25' : ''">
              <span class="text-[26px] leading-none">{{ f.icon }}</span>
              <span class="text-[12.5px] font-bold leading-tight break-keep">{{ f.title }}</span>
              <span v-if="f.desc" class="hidden lg:block text-[11.5px] leading-snug text-white/85 break-keep">{{ f.desc }}</span>
            </component>
          </div>
        </div>

        <p v-if="x.note" class="mt-6 text-center text-[12.5px] leading-relaxed text-white/80 break-keep">
          {{ x.note }}
          <button type="button" @click="go('/contact')" class="underline font-bold text-white ml-1">문의하기</button>
        </p>
      </div>

      <div class="sticky bottom-0 w-full fb-bottom">
        <div class="max-w-lg mx-auto px-5 pb-5 pt-6 space-y-3">
          <button type="button" @click="clickCta"
            class="w-full min-h-[54px] rounded-2xl bg-white text-pink-600 text-[17px] font-black shadow-xl active:scale-[0.98] transition">{{ x.cta_label || '둘러보기' }}</button>
          <label v-if="banner.display_mode === 'once_per_day'" class="flex items-center justify-center gap-2 text-[13px] text-white/90 select-none cursor-pointer">
            <input type="checkbox" v-model="hideToday" class="w-4 h-4 accent-pink-500" />
            오늘 하루 이 창을 열지 않음
          </label>
        </div>
      </div>
    </div>
  </div>
</Teleport>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'
import { useSiteStore } from '../stores/site'

const props = defineProps({
  banner: { type: Object, required: true },
  preview: { type: Boolean, default: false },   // 관리자 미리보기
})
const emit = defineEmits(['close'])

const router = useRouter()
const siteStore = useSiteStore()
const rootEl = ref(null)
const stageEl = ref(null)
const hideToday = ref(false)
const event = ref(null)
const imgFailed = ref(false)
const natural = ref({ w: 0, h: 0 })
const stage = ref({ w: 0, h: 0 })
// 눌리는 영역 위치 확인용: 주소 끝에 ?bannerdebug=1
const debug = typeof location !== 'undefined' && /[?&]bannerdebug=1/.test(location.search)

const x = computed(() => props.banner.extra || {})
const logo = computed(() => siteStore.logoUrl || '')

// 화면 모양: 가로로 넓으면(PC·태블릿 가로) PC 그림, 아니면 휴대폰 그림. 한쪽만 있으면 그걸 쓴다.
const wide = ref(typeof window !== 'undefined' ? window.innerWidth >= 900 && window.innerWidth / Math.max(1, window.innerHeight) > 1.15 : false)
const imgUrl = computed(() => {
  const d = x.value.img_desktop || '', m = x.value.img_mobile || ''
  return wide.value ? (d || m) : (m || d)
})
const spots = computed(() => {
  const useDesktop = imgUrl.value && imgUrl.value === x.value.img_desktop && (wide.value || !x.value.img_mobile)
  const list = useDesktop ? x.value.hotspots_desktop : x.value.hotspots_mobile
  return Array.isArray(list) ? list.filter(h => h && Number.isFinite(+h.x) && Number.isFinite(+h.y) && Number.isFinite(+h.w) && Number.isFinite(+h.h)) : []
})
const hasSpot = t => spots.value.some(h => h.type === t)

// 그림을 화면에 꽉 차게 맞추되 비율은 그대로(contain). 눌리는 영역이 그림 위치와 어긋나지 않도록 그림 크기를 직접 계산한다.
const ratio = computed(() => natural.value.w && natural.value.h ? natural.value.w / natural.value.h : 0)
const boxStyle = computed(() => {
  if (!ratio.value || !stage.value.w || !stage.value.h) return { width: '100%', visibility: 'hidden' }
  let w = stage.value.w, h = w / ratio.value
  if (h > stage.value.h) { h = stage.value.h; w = h * ratio.value }
  return { width: Math.floor(w) + 'px', height: Math.floor(h) + 'px' }
})
const checkSize = computed(() => Math.max(12, Math.round((parseFloat(boxStyle.value.height) || 600) * 0.03)) + 'px')

function spotStyle(h) {
  return { left: h.x + '%', top: h.y + '%', width: h.w + '%', height: h.h + '%' }
}
function spotLabel(h) {
  if (h.type === 'close') return '닫기'
  if (h.type === 'cta') return x.value.cta_label || '둘러보기'
  return h.label || '바로가기'
}
function spotClick(h) {
  if (h.type === 'close') emit('close', hideToday.value)
  else if (h.type === 'cta') clickCta()
  else if (h.type === 'link') go(h.link)
}

function onLoad(e) {
  natural.value = { w: e.target.naturalWidth, h: e.target.naturalHeight }
  measure()
}
function measure() {
  const el = stageEl.value
  if (el) stage.value = { w: el.clientWidth, h: el.clientHeight }
}
function onResize() {
  wide.value = window.innerWidth >= 900 && window.innerWidth / Math.max(1, window.innerHeight) > 1.15
  nextTick(measure)
}

// 한 줄에 "아이콘|이름|이동 주소(선택)|설명(선택)" — 글자형에서 쓴다
const features = computed(() => String(x.value.features_text || '').split('\n').map(l => l.trim()).filter(Boolean).slice(0, 12).map(l => {
  const [icon, title, link, desc] = l.split('|').map(s => (s || '').trim())
  return { icon: icon || '•', title: title || '', link: link || '', desc: desc || '' }
}).filter(f => f.title))

const endLabel = computed(() => {
  const m = /^\d{4}-(\d{2})-(\d{2})$/.exec(event.value?.ends_on || '')
  return m ? `${Number(m[1])}월 ${Number(m[2])}일` : ''
})

// 주소로 이동하면 창을 닫는다. http(s)는 새 탭
function go(link) {
  if (!link) return
  emit('close', hideToday.value)
  if (/^https?:\/\//i.test(link)) window.open(link, '_blank', 'noopener')
  else if (link.startsWith('/') && !link.startsWith('//')) router.push(link)
}
function clickCta() {
  const link = x.value.cta_link
  if (link) go(link)
  else emit('close', hideToday.value)
}

let prevOverflow = ''
onMounted(async () => {
  prevOverflow = document.body.style.overflow
  document.body.style.overflow = 'hidden'   // 뒤쪽 화면이 같이 스크롤되지 않게
  rootEl.value?.focus()
  window.addEventListener('resize', onResize)
  nextTick(measure)
  if (x.value.show_open_event) {
    try {
      const { data } = await axios.get('/api/open-event')
      if (data?.data?.active) event.value = data.data
    } catch {}
  }
})
onBeforeUnmount(() => {
  document.body.style.overflow = prevOverflow
  window.removeEventListener('resize', onResize)
})
</script>

<style scoped>
/* 사이트 버튼과 같은 주황 → 핑크 계열 (그림 둘레 빈 곳, 글자형 배경) */
.fb-bg { background-color: #ff7a5c; background-image: linear-gradient(160deg, #ff9a4d 0%, #ff6a5c 45%, #ec3f78 100%); outline: none; }
.fb-bottom { background: linear-gradient(to top, #ec3f78 55%, rgba(236, 63, 120, 0)); }
/* 눌리는 영역: 눈에는 안 보이고, 키보드로 이동하면 테두리가 보인다 */
.fb-spot { background: transparent; border: 0; padding: 0; cursor: pointer; border-radius: 14px; -webkit-tap-highlight-color: transparent; }
.fb-spot:focus-visible { outline: 3px solid #fff; outline-offset: 2px; }
.fb-debug { outline: 2px dashed #00e5ff; background: rgba(0, 229, 255, 0.12); }
</style>
