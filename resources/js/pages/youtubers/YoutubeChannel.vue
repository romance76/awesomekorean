<template>
<div class="min-h-screen">
  <div class="max-w-5xl mx-auto px-4 py-5">
    <RouterLink to="/youtubers" class="inline-flex items-center gap-1 text-[13px] font-semibold text-ink-muted hover:text-amber-700 mb-3">← 한인 유튜버</RouterLink>

    <div v-if="loading" class="py-16 text-center text-ink-muted text-sm">불러오는 중...</div>
    <div v-else-if="!ch" class="py-16 text-center text-ink-muted text-sm">채널을 찾을 수 없어요.</div>

    <template v-else>
      <!-- 채널 머리 -->
      <div class="card overflow-hidden mb-4">
        <div v-if="ch.banner_url" class="h-24 sm:h-36 bg-surface"><img :src="ch.banner_url" alt="" class="w-full h-full object-cover" @error="e => e.target.style.display = 'none'" /></div>
        <div class="p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center gap-4">
          <img v-if="ch.thumbnail_url" :src="ch.thumbnail_url" alt="" class="w-20 h-20 rounded-full object-cover bg-surface shrink-0" @error="e => e.target.style.display = 'none'" />
          <div class="min-w-0 flex-1">
            <h1 class="text-xl font-extrabold text-ink break-words flex items-center gap-2 flex-wrap">
              {{ ch.title }}
              <span v-if="ch.verified" class="inline-flex items-center gap-1 text-[11.5px] font-bold text-green-700 bg-green-50 px-2 py-0.5 rounded-full" title="채널 주인이 직접 인증했어요"><AppIcon name="check" :size="12" />인증된 채널</span>
            </h1>
            <div class="mt-1 text-[13px] text-ink-muted">
              <span v-if="ch.handle">{{ ch.handle }} · </span>구독자 {{ fmtCount(ch.subscriber_count) }} · 영상 {{ fmtCount(ch.video_count) }}개
              <span class="ml-1 badge-gray">{{ ch.category_label }}</span>
            </div>
            <p v-if="ch.intro" class="mt-2 text-[13.5px] text-ink-light break-words">{{ ch.intro }}</p>
          </div>
          <!-- 인증된 채널만 '구독하기' 버튼, 아니면 '유튜브에서 보기' -->
          <a v-if="ch.verified" :href="ch.url + '?sub_confirmation=1'" target="_blank" rel="noopener noreferrer" class="btn-primary shrink-0 inline-flex items-center justify-center gap-1.5 px-5 py-2.5 text-sm">
            <AppIcon name="external-link" :size="14" />구독하기
          </a>
          <a v-else :href="ch.url" target="_blank" rel="noopener noreferrer" class="btn-secondary shrink-0 inline-flex items-center justify-center gap-1.5 px-5 py-2.5 text-sm">
            <AppIcon name="external-link" :size="14" />유튜브에서 보기
          </a>
        </div>
      </div>

      <!-- 주인 전용 -->
      <div v-if="ch.is_owner" class="card p-4 mb-4 border border-green-100 bg-green-50/40">
        <div class="text-[13px] font-extrabold text-green-800 mb-2">내 채널 관리 <span class="font-normal text-green-700">(주인 인증 혜택)</span></div>
        <div class="flex flex-wrap items-center gap-2 mb-3">
          <button @click="bump" :disabled="busy || !!ch.bump_available_at" class="btn-primary px-4 py-2 text-[13px] disabled:opacity-50">
            {{ ch.bump_available_at ? '오늘은 이미 올렸어요' : '목록 맨 위로 끌어올리기 (하루 1번)' }}
          </button>
          <span v-if="ch.bump_available_at" class="text-[12px] text-ink-muted">{{ String(ch.bump_available_at).slice(5, 16).replace('T', ' ') }} 이후 다시 가능</span>
        </div>
        <div class="flex flex-col sm:flex-row gap-2">
          <input v-model.trim="editIntro" type="text" maxlength="300" class="input-soft flex-1" placeholder="한 줄 소개 (최대 300자)" />
          <select v-model="editCat" class="input-soft sm:!w-40"><option v-for="c in cats" :key="c.key" :value="c.key">{{ c.label }}</option></select>
          <button @click="saveOwner" :disabled="busy" class="btn-secondary px-4 py-2 text-[13px]">저장</button>
        </div>
      </div>

      <!-- 내 채널이신가요? -->
      <div v-else-if="!ch.verified" class="card p-4 mb-4 border border-amber-100 bg-amber-50/50">
        <div class="flex flex-col sm:flex-row sm:items-center gap-3">
          <div class="flex-1 min-w-0">
            <div class="text-[14px] font-extrabold text-ink">내 채널이신가요?</div>
            <p class="text-[12.5px] text-ink-light mt-0.5">주인 인증을 하면 <b>인증 배지 · 구독하기 버튼 · 영상/쇼츠 이전·다음 보기 · 목록 끌어올리기 · 소개 직접 수정</b>을 쓸 수 있어요. 무료예요.</p>
          </div>
          <button @click="openClaim" class="btn-primary shrink-0 px-5 py-2.5 text-sm">내 채널 인증하기</button>
        </div>
      </div>

      <!-- 탭 -->
      <div class="flex gap-1 border-b border-gray-100 mb-4">
        <button v-for="t in tabs" :key="t.key" @click="setTab(t.key)" class="px-4 py-2.5 text-sm font-semibold border-b-2 -mb-px transition-colors"
          :class="tab === t.key ? 'border-amber-500 text-amber-700' : 'border-transparent text-ink-muted hover:text-ink-light'">{{ t.label }}<span v-if="t.count != null" class="ml-1 text-[11px] text-ink-faint">{{ t.count }}</span></button>
      </div>

      <!-- 영상·쇼츠 -->
      <template v-if="tab !== 'about'">
        <div v-if="vLoading && !videos.length" class="py-12 text-center text-ink-muted text-sm">불러오는 중...</div>
        <div v-else-if="!videos.length" class="py-12 text-center text-ink-muted text-sm">{{ tab === 'short' ? '쇼츠가 아직 없어요.' : '영상이 아직 없어요.' }}</div>
        <div v-else :class="tab === 'short' ? 'grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3' : 'grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4'">
          <button v-for="v in videos" :key="v.id" @click="play = v" class="text-left group">
            <div class="relative bg-surface rounded-xl overflow-hidden" :class="tab === 'short' ? 'aspect-[9/16]' : 'aspect-video'">
              <img v-if="v.thumbnail_url" :src="v.thumbnail_url" alt="" loading="lazy" class="w-full h-full object-cover" @error="e => e.target.style.display = 'none'" />
              <span class="absolute inset-0 grid place-items-center opacity-0 group-hover:opacity-100 transition-opacity bg-black/25"><AppIcon name="play" :size="34" class="text-white" /></span>
              <span v-if="v.duration_seconds" class="absolute right-1.5 bottom-1.5 text-[11px] font-bold bg-black/70 text-white px-1.5 py-0.5 rounded">{{ fmtDur(v.duration_seconds) }}</span>
            </div>
            <div class="mt-1.5 text-[13px] font-semibold text-ink line-clamp-2 break-words">{{ v.title }}</div>
            <div class="text-[11.5px] text-ink-muted">조회 {{ fmtCount(v.view_count) }}<template v-if="v.published_at"> · {{ ago(v.published_at) }}</template></div>
          </button>
        </div>
        <div v-if="vPage < vLast" class="text-center mt-6">
          <button @click="loadVideos(vPage + 1)" :disabled="vLoading" class="btn-secondary px-6 py-2 text-sm">{{ vLoading ? '불러오는 중...' : '더 보기' }}</button>
        </div>
      </template>

      <!-- 소개 -->
      <div v-else class="card p-5 text-[14px] text-ink-light whitespace-pre-wrap break-words leading-relaxed">{{ ch.description || '채널 소개가 없어요.' }}</div>
    </template>
  </div>

  <!-- 재생창: 유튜브 공식 임베드. 인증된 채널만 이전·다음 보기 -->
  <div v-if="play" class="fixed inset-0 z-[80] bg-black/80 flex items-center justify-center p-3" @click.self="play = null">
    <div class="w-full relative" :class="tab === 'short' ? 'max-w-[360px]' : 'max-w-4xl'">
      <button @click="play = null" class="absolute -top-10 right-0 text-white p-2" aria-label="닫기"><AppIcon name="x" :size="22" /></button>
      <div class="bg-black rounded-xl overflow-hidden" :class="tab === 'short' ? 'aspect-[9/16] max-h-[80vh]' : 'aspect-video'">
        <iframe :key="play.video_id" :src="`https://www.youtube-nocookie.com/embed/${play.video_id}?autoplay=1&rel=0`" class="w-full h-full" allow="autoplay; encrypted-media; picture-in-picture; fullscreen" allowfullscreen title="유튜브 영상"></iframe>
      </div>
      <div class="mt-2 text-white text-[13px] font-semibold line-clamp-2">{{ play.title }}</div>
      <div v-if="ch?.verified" class="mt-2 flex items-center justify-between gap-2">
        <button @click="step(-1)" :disabled="!hasPrev" class="px-4 py-2 rounded-lg text-[13px] font-bold bg-white/15 text-white disabled:opacity-30">← 이전</button>
        <span class="text-[12px] text-white/60">{{ playIdx + 1 }} / {{ videos.length }}{{ vPage < vLast ? '+' : '' }}</span>
        <button @click="step(1)" :disabled="!hasNext" class="px-4 py-2 rounded-lg text-[13px] font-bold bg-white/15 text-white disabled:opacity-30">다음 →</button>
      </div>
      <div v-else-if="ch" class="mt-2 text-[11.5px] text-white/50">이전·다음 보기는 주인 인증을 한 채널에서 쓸 수 있어요.</div>
    </div>
  </div>

  <!-- 주인 인증 안내 -->
  <div v-if="showClaim" class="fixed inset-0 z-[80] bg-black/60 flex items-center justify-center p-3" @click.self="showClaim = false">
    <div class="card w-full max-w-lg p-5 max-h-[90vh] overflow-y-auto">
      <div class="flex items-center justify-between mb-3">
        <h2 class="text-lg font-extrabold text-ink">내 채널 인증하기</h2>
        <button @click="showClaim = false" class="text-ink-muted p-1" aria-label="닫기"><AppIcon name="x" :size="20" /></button>
      </div>
      <p class="text-[13px] text-ink-light mb-3">채널 설명은 채널 주인만 고칠 수 있어요. 그래서 <b>인증 코드를 채널 설명에 넣으면</b> 주인이라고 확인해 드려요. 비밀번호나 로그인 정보는 필요 없어요.</p>
      <ol class="text-[13px] text-ink space-y-2 mb-4 list-decimal pl-5">
        <li>아래 <b>인증 코드</b>를 복사해요.</li>
        <li><a :href="studioUrl" target="_blank" rel="noopener noreferrer" class="text-amber-700 font-bold underline">유튜브 스튜디오 ↗</a> → <b>맞춤설정 → 기본 정보 → 설명</b> 맨 위에 코드를 붙여 넣고 <b>게시</b>를 눌러요.</li>
        <li>여기로 돌아와 <b>인증 확인하기</b>를 눌러요. 확인되면 코드는 지워도 돼요.</li>
      </ol>
      <div v-if="claimLoading" class="text-center text-sm text-ink-muted py-3">코드를 만드는 중...</div>
      <div v-else-if="claimCode" class="flex items-center gap-2 bg-surface rounded-xl px-3 py-2.5 mb-3">
        <code class="flex-1 text-[15px] font-bold text-ink break-all select-all">{{ claimCode }}</code>
        <button @click="copyCode" class="btn-secondary px-3 py-1.5 text-[12.5px] shrink-0">{{ copied ? '복사됨' : '복사' }}</button>
      </div>
      <div v-if="claimMsg" class="text-[13px] rounded-lg px-3 py-2 mb-3" :class="claimOk ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-600'">{{ claimMsg }}</div>
      <button @click="verify" :disabled="!claimCode || claimBusy" class="btn-primary w-full py-2.5 text-sm disabled:opacity-50">{{ claimBusy ? '확인 중...' : '인증 확인하기' }}</button>
      <p class="mt-2 text-[11.5px] text-ink-muted">코드는 3일 동안 유효해요. 이메일 인증을 마친 로그인 회원만 신청할 수 있어요. 본인 채널인데 인증이 안 되면 문의해 주세요.</p>
    </div>
  </div>
</div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import axios from 'axios'
import AppIcon from '../../components/AppIcon.vue'
import { useAuthStore } from '../../stores/auth'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const ch = ref(null)
const loading = ref(true)
const tab = ref('video')
const videos = ref([])
const vPage = ref(1)
const vLast = ref(1)
const vLoading = ref(false)
const play = ref(null)
const busy = ref(false)
const editIntro = ref('')
const editCat = ref('etc')
const cats = [
  { key: 'life', label: '생활·이민' }, { key: 'food', label: '먹방·맛집' }, { key: 'family', label: '육아·교육' }, { key: 'money', label: '재테크·세금' },
  { key: 'realestate', label: '부동산' }, { key: 'travel', label: '여행' }, { key: 'beauty', label: '뷰티·패션' }, { key: 'vlog', label: '일상 vlog' }, { key: 'etc', label: '기타' },
]

const tabs = computed(() => [
  { key: 'video', label: '영상', count: ch.value?.videos_total },
  { key: 'short', label: '쇼츠', count: ch.value?.shorts_total },
  { key: 'about', label: '소개', count: null },
])

function fmtCount(n) {
  n = Number(n || 0)
  if (n >= 10000) return (n / 10000).toFixed(n >= 100000 ? 0 : 1).replace(/\.0$/, '') + '만'
  if (n >= 1000) return (n / 1000).toFixed(1).replace(/\.0$/, '') + '천'
  return String(n)
}
function fmtDur(s) {
  s = Number(s || 0)
  const h = Math.floor(s / 3600), m = Math.floor((s % 3600) / 60), r = s % 60
  return (h ? h + ':' + String(m).padStart(2, '0') : m) + ':' + String(r).padStart(2, '0')
}
function ago(d) {
  const t = new Date(d).getTime()
  if (!t) return ''
  const days = Math.floor((Date.now() - t) / 86400000)
  if (days < 1) return '오늘'
  if (days < 30) return days + '일 전'
  if (days < 365) return Math.floor(days / 30) + '개월 전'
  return Math.floor(days / 365) + '년 전'
}

async function loadVideos(p) {
  vLoading.value = true
  try {
    const { data } = await axios.get(`/api/youtubers/${route.params.id}/videos`, { params: { type: tab.value === 'short' ? 'short' : 'video', page: p } })
    const d = data.data || {}
    videos.value = p === 1 ? (d.items || []) : videos.value.concat(d.items || [])
    vPage.value = d.meta?.current_page || p
    vLast.value = d.meta?.last_page || 1
  } catch { /* 영상이 안 떠도 화면 유지 */ } finally { vLoading.value = false }
}
function setTab(k) {
  tab.value = k
  if (k !== 'about') { videos.value = []; loadVideos(1) }
}

async function loadChannel(keepTab = false) {
  loading.value = !keepTab
  try {
    const { data } = await axios.get(`/api/youtubers/${route.params.id}`)
    ch.value = data.data
    document.title = `${ch.value.title} | 어썸코리안`
    editIntro.value = ch.value.intro || ''
    editCat.value = ch.value.category || 'etc'
    if (!keepTab) {
      if (!ch.value.videos_total && ch.value.shorts_total) tab.value = 'short'   // 쇼츠만 올리는 채널은 쇼츠 탭부터
      await loadVideos(1)
    }
  } catch { ch.value = null } finally { loading.value = false }
}

// ── 이전·다음 보기 (주인 인증된 채널) ──
const playIdx = computed(() => play.value ? videos.value.findIndex(v => v.id === play.value.id) : -1)
const hasPrev = computed(() => playIdx.value > 0)
const hasNext = computed(() => playIdx.value >= 0 && (playIdx.value < videos.value.length - 1 || vPage.value < vLast.value))
async function step(dir) {
  const i = playIdx.value + dir
  if (i < 0) return
  if (i >= videos.value.length && vPage.value < vLast.value) await loadVideos(vPage.value + 1)
  if (videos.value[i]) play.value = videos.value[i]
}
function onKey(e) {
  if (!play.value) return
  if (e.key === 'Escape') play.value = null
  else if (ch.value?.verified && e.key === 'ArrowRight') step(1)
  else if (ch.value?.verified && e.key === 'ArrowLeft') step(-1)
}

// ── 주인 인증 ──
const showClaim = ref(false)
const claimCode = ref('')
const claimMsg = ref('')
const claimOk = ref(false)
const claimLoading = ref(false)
const claimBusy = ref(false)
const copied = ref(false)
const studioUrl = computed(() => `https://studio.youtube.com/channel/${ch.value?.channel_id || ''}/editing/details`)

async function openClaim() {
  if (!auth.isLoggedIn) { router.push({ path: '/login', query: { redirect: route.fullPath } }); return }
  showClaim.value = true
  claimMsg.value = ''
  claimLoading.value = true
  try {
    const { data } = await axios.post(`/api/youtubers/${route.params.id}/claim`)
    claimCode.value = data.data?.code || ''
  } catch (e) {
    claimCode.value = ''
    claimOk.value = false
    claimMsg.value = e.response?.data?.message || '코드를 만들지 못했어요. 로그인과 이메일 인증을 확인해 주세요.'
  } finally { claimLoading.value = false }
}
async function copyCode() {
  try { await navigator.clipboard.writeText(claimCode.value); copied.value = true; setTimeout(() => { copied.value = false }, 1500) } catch { /* 길게 눌러 직접 복사 */ }
}
async function verify() {
  claimBusy.value = true
  claimMsg.value = ''
  try {
    const { data } = await axios.post(`/api/youtubers/${route.params.id}/claim/verify`)
    claimOk.value = true
    claimMsg.value = data.message || '인증이 끝났어요!'
    await loadChannel(true)
    setTimeout(() => { showClaim.value = false }, 1800)
  } catch (e) {
    claimOk.value = false
    claimMsg.value = e.response?.data?.message || '확인하지 못했어요. 잠시 후 다시 눌러 주세요.'
  } finally { claimBusy.value = false }
}

// ── 주인 혜택 ──
async function bump() {
  busy.value = true
  try { const { data } = await axios.post(`/api/youtubers/${route.params.id}/bump`); window.alert(data.message || '올렸어요!'); await loadChannel(true) }
  catch (e) { window.alert(e.response?.data?.message || '지금은 올릴 수 없어요.') }
  finally { busy.value = false }
}
async function saveOwner() {
  busy.value = true
  try { await axios.put(`/api/youtubers/${route.params.id}/owner`, { intro: editIntro.value || null, category: editCat.value }); await loadChannel(true) }
  catch (e) { window.alert(e.response?.data?.message || '저장하지 못했어요.') }
  finally { busy.value = false }
}

watch(() => route.params.id, (v, o) => { if (v && v !== o) { tab.value = 'video'; loadChannel() } })
onMounted(() => { loadChannel(); window.addEventListener('keydown', onKey) })
onUnmounted(() => window.removeEventListener('keydown', onKey))
</script>
