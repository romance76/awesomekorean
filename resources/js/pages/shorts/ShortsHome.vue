<template>
<div ref="rootEl" class="fixed left-0 right-0 bg-black select-none" :class="isFs ? 'z-[2000]' : 'z-40'" :style="rootStyle">
  <div v-if="loading" class="absolute inset-0 flex items-center justify-center text-white">로딩중...</div>
  <div v-else-if="!shorts.length" class="absolute inset-0 flex items-center justify-center text-white text-sm">숏츠가 없습니다</div>
  <div v-else ref="areaEl" class="absolute inset-0 overflow-hidden flex items-center justify-center" style="overscroll-behavior:contain">
    <!-- 프레임: 9:16 (또는 꽉 채우기) 로 보이는 영역. 컨트롤은 전부 이 안쪽 -->
    <div data-frame class="relative overflow-hidden bg-black flex-shrink-0" :style="{ width: fw + 'px', height: fh + 'px', '--pb': isFs ? 'env(safe-area-inset-bottom, 0px)' : '0px', '--pt': isFs ? 'env(safe-area-inset-top, 0px)' : '0px' }">
      <!-- 스테이지: 항상 정확히 9:16 (플레이어는 100% 로 꽉 채움 → 늘어나지 않음) -->
      <div data-stage class="absolute bg-black" :style="{ width: sw + 'px', height: sh + 'px', left: ((fw - sw) / 2) + 'px', top: ((fh - sh) / 2) + 'px', pointerEvents: 'none' }">
        <div ref="playerHost" class="w-full h-full"></div>
      </div>

      <!-- 상단 컨트롤 -->
      <div class="absolute left-0 right-0 top-0 z-30 flex items-start justify-between px-2 pointer-events-none" style="padding-top:calc(8px + var(--pt))">
        <div class="pointer-events-auto">
          <button v-if="isFs" data-close @click="exitFs" class="h-11 px-4 rounded-full bg-black/55 text-white text-sm font-bold inline-flex items-center gap-1.5 backdrop-blur">✕ 닫기</button>
          <RouterLink v-else-if="auth.isLoggedIn" to="/shorts/upload" class="h-11 px-4 rounded-full bg-black/45 text-white text-sm font-bold inline-flex items-center gap-1 backdrop-blur"><AppIcon name="plus" :size="14" />업로드</RouterLink>
        </div>
        <div class="flex items-center gap-2 pointer-events-auto">
          <button data-mode @click="toggleFill" :title="fillMode ? '꽉 채우기 (좌우가 잘릴 수 있어요). 누르면 원본 비율' : '원본 비율 (9:16). 누르면 꽉 채우기 (좌우가 잘릴 수 있어요)'" :aria-label="fillMode ? '원본 비율로 보기' : '꽉 채워 보기'" class="w-11 h-11 rounded-full bg-black/55 text-white text-xs font-bold flex items-center justify-center backdrop-blur">{{ fillMode ? '꽉' : '9:16' }}</button>
          <button data-fs @click="toggleFs" :title="isFs ? '전체화면 종료' : '전체화면'" :aria-label="isFs ? '전체화면 종료' : '전체화면'" class="w-11 h-11 rounded-full bg-black/55 text-white text-xl leading-none flex items-center justify-center backdrop-blur">{{ isFs ? '⤡' : '⛶' }}</button>
        </div>
      </div>

      <!-- 오른쪽 액션 버튼 -->
      <div class="absolute flex flex-col items-center gap-4 z-20" style="right:8px;bottom:calc(76px + var(--pb))">
        <button @click="toggleLike" class="flex flex-col items-center">
          <div class="w-11 h-11 bg-black/40 backdrop-blur rounded-full flex items-center justify-center" :class="liked ? 'text-red-500' : 'text-white'"><AppIcon name="heart" :size="20" :filled="liked" /></div>
          <span class="text-white text-xs mt-0.5 drop-shadow">{{ current.like_count }}</span>
        </button>
        <button @click="showComments=!showComments" class="flex flex-col items-center">
          <div class="w-11 h-11 bg-black/40 backdrop-blur rounded-full flex items-center justify-center text-white"><AppIcon name="message-circle" :size="20" /></div>
          <span class="text-white text-xs mt-0.5 drop-shadow">{{ current.comment_count }}</span>
        </button>
        <button @click="shareShort" class="flex flex-col items-center">
          <div class="w-11 h-11 bg-black/40 backdrop-blur rounded-full flex items-center justify-center text-white"><AppIcon name="share" :size="20" /></div>
          <span class="text-white text-xs mt-0.5 drop-shadow">공유</span>
        </button>
      </div>

      <!-- 제목/채널 -->
      <div class="absolute z-20 pointer-events-none" style="left:12px;right:68px;bottom:calc(68px + var(--pb))">
        <div class="text-white font-bold text-sm drop-shadow line-clamp-2">{{ current.title }}</div>
        <div class="text-white/80 text-xs mt-1 drop-shadow">{{ current.user?.name || '익명' }}</div>
      </div>

      <!-- 하단 컨트롤 바: 이전 / 번호 / 다음 -->
      <div v-if="!sideNav" data-bar class="absolute left-1/2 z-30 flex items-center gap-3 bg-black/45 backdrop-blur rounded-full px-2 py-1" style="transform:translateX(-50%);bottom:calc(10px + var(--pb))">
        <button data-prev @click="prev" :disabled="idx <= 0" aria-label="이전 숏츠" class="w-11 h-11 rounded-full flex items-center justify-center text-white active:bg-white/30 disabled:opacity-30"><AppIcon name="chevron-up" :size="22" /></button>
        <span class="text-white text-xs min-w-[44px] text-center">{{ idx + 1 }} / {{ shorts.length }}</span>
        <button data-next @click="next" :disabled="idx >= shorts.length - 1" aria-label="다음 숏츠" class="w-11 h-11 rounded-full flex items-center justify-center text-white active:bg-white/30 disabled:opacity-30"><AppIcon name="chevron-down" :size="22" /></button>
      </div>

      <!-- 상태 표시 (터치를 막지 않음) -->
      <div class="absolute inset-0 z-[15] flex items-center justify-center pointer-events-none">
        <div v-if="needTap" data-needtap class="bg-black/60 text-white rounded-full px-5 py-3 text-sm font-bold">▶ 탭하여 재생</div>
        <div v-else-if="paused" class="bg-black/50 text-white rounded-full w-16 h-16 flex items-center justify-center text-3xl">▶</div>
        <div v-else-if="starting" class="text-white/70 text-xs">불러오는 중...</div>
      </div>
      <div v-if="toast" class="absolute left-1/2 -translate-x-1/2 z-30 bg-black/70 text-white text-xs rounded-full px-4 py-2 pointer-events-none whitespace-nowrap" style="top:calc(64px + var(--pt))">{{ toast }}</div>
      <button v-if="!soundOn && !needTap" @click.stop="enableSound" class="absolute z-30 bg-white/90 text-black text-xs font-bold rounded-full px-3 py-2 shadow" style="left:12px;top:calc(64px + var(--pt))">🔊 소리 켜기</button>
    </div>

    <!-- 제스처 레이어: iframe 이 터치를 먹지 않도록 영역 전체 위를 투명 레이어로 덮음 (탭=재생/일시정지, 위/아래 스와이프=이동). 버튼들은 z-20 이상이라 위에 있음 -->
    <div data-gesture class="absolute inset-0 z-10" style="touch-action:none;-webkit-tap-highlight-color:transparent"
      @touchstart.passive="onTouchStart" @touchmove="onTouchMove" @touchend="onTouchEnd" @touchcancel="onTouchCancel"
      @click="onLayerClick"></div>

    <!-- 데스크탑: 프레임 옆 여백이 충분하면 위/아래 버튼을 옆에 배치 -->
    <div v-if="sideNav" data-side class="absolute top-1/2 -translate-y-1/2 flex flex-col items-center gap-3 z-20" :style="{ left: 'calc(50% + ' + (fw / 2 + 16) + 'px)' }">
      <button data-prev @click="prev" :disabled="idx <= 0" aria-label="이전 숏츠" class="w-11 h-11 bg-white/20 backdrop-blur rounded-full flex items-center justify-center text-white hover:bg-white/40 disabled:opacity-20 transition"><AppIcon name="chevron-up" :size="20" /></button>
      <span class="text-white/70 text-xs">{{ idx + 1 }} / {{ shorts.length }}</span>
      <button data-next @click="next" :disabled="idx >= shorts.length - 1" aria-label="다음 숏츠" class="w-11 h-11 bg-white/20 backdrop-blur rounded-full flex items-center justify-center text-white hover:bg-white/40 disabled:opacity-20 transition"><AppIcon name="chevron-down" :size="20" /></button>
    </div>
  </div>

  <!-- 댓글 패널 -->
  <div v-if="showComments" data-comments class="absolute bottom-0 left-0 right-0 bg-white rounded-t-2xl z-50 max-h-[50vh] overflow-y-auto shadow-lift" style="overscroll-behavior:contain">
    <div class="flex items-center justify-between px-4 py-3 border-b border-gray-50 sticky top-0 bg-white">
      <span class="font-bold text-sm text-ink inline-flex items-center gap-1.5"><AppIcon name="message-circle" :size="15" class="text-amber-600" />댓글</span>
      <button @click="showComments=false" class="text-ink-muted hover:text-ink transition-colors"><AppIcon name="x" :size="16" /></button>
    </div>
    <div v-if="comments.length" class="divide-y divide-gray-50">
      <div v-for="c in comments" :key="c.id" class="px-4 py-2.5">
        <div class="flex items-center gap-2 mb-0.5"><span class="text-xs font-semibold text-ink-light">{{ c.user?.name }}</span><span class="text-xs text-ink-faint">{{ c.created_at?.slice(0,10) }}</span></div>
        <div class="text-sm text-ink-light">{{ c.content }}</div>
      </div>
    </div>
    <div v-else class="px-4 py-6 text-center text-sm text-ink-muted">아직 댓글이 없습니다</div>
    <div v-if="auth.isLoggedIn" class="px-4 py-3 border-t border-gray-50 flex gap-2 sticky bottom-0 bg-white">
      <VerifyGate message="이메일 인증 후 댓글을 쓸 수 있어요.">
      <input v-model="newComment" type="text" placeholder="댓글 입력..." class="input-soft flex-1 rounded-full px-3 py-1.5" @keyup.enter="submitComment" />
      <button @click="submitComment" class="btn-primary rounded-full px-4 py-1.5">등록</button>
      </VerifyGate>
    </div>
  </div>
</div>
</template>

<script setup>
import { ref, computed, watch, nextTick, onMounted, onUnmounted } from 'vue'
import { useAuthStore } from '../../stores/auth'
import AppIcon from '../../components/AppIcon.vue'
import VerifyGate from '../../components/VerifyGate.vue'
import axios from 'axios'

const auth = useAuthStore()
const shorts = ref([])
const idx = ref(0)
const loading = ref(true)
const liked = ref(false)
const showComments = ref(false)
const comments = ref([])
const newComment = ref('')

// 재생 상태
const rootEl = ref(null)
const areaEl = ref(null)
// 보기 모드: fit(원본 9:16 맞춤) / fill(꽉 채우기)
const fillMode = ref(false)
try { fillMode.value = localStorage.getItem('shorts_view_mode') === 'fill' } catch {}
// 레이아웃 계산값
const topInset = ref(0)
const bottomInset = ref(0)
const aw = ref(360)
const ah = ref(640)
const R = 9 / 16
const stage = computed(() => {
  const W = aw.value, H = ah.value
  const wide = W / H > R
  let sw, sh
  if (!fillMode.value) { if (wide) { sh = H; sw = H * R } else { sw = W; sh = W / R } }
  else { if (wide) { sw = W; sh = W / R } else { sh = H; sw = H * R } }
  return { sw, sh }
})
const sw = computed(() => stage.value.sw)
const sh = computed(() => stage.value.sh)
const fw = computed(() => Math.min(sw.value, aw.value))
const fh = computed(() => Math.min(sh.value, ah.value))
const sideNav = computed(() => (aw.value - fw.value) / 2 >= 84)
const playerHost = ref(null)
const paused = ref(false)     // 사용자가 일시정지한 상태
const starting = ref(false)   // 로딩/시작 대기
const needTap = ref(false)    // 자동재생 실패 → 탭 유도
const soundOn = ref(false)    // 첫 제스처 이후 true
const toast = ref('')
// 전체화면
const nativeFs = ref(false)
const pseudoFs = ref(false)
const isFs = computed(() => nativeFs.value || pseudoFs.value)

const rootStyle = computed(() => isFs.value
  ? 'top:0;bottom:0;height:100vh;height:100dvh;overscroll-behavior:contain'
  : `top:${topInset.value}px;bottom:${bottomInset.value}px;overscroll-behavior:contain`)

const current = computed(() => shorts.value[idx.value] || {})

let player = null
let playerReady = false
let destroyed = false
let retryTimer = null
let watchdog = null
let toastTimer = null
let retryCount = 0
let wasPlayingBeforeHide = false
let page = 1
let lastPage = 1
let loadingMore = false

function safe(fn) { try { return fn() } catch { return undefined } }

// ─── YouTube IFrame API (MiniPlayer 와 같은 script 태그 공유) ───
function loadYTApi() {
  return new Promise((resolve, reject) => {
    if (window.YT?.Player) return resolve()
    if (!document.getElementById('yt-api-script')) {
      const t = document.createElement('script')
      t.id = 'yt-api-script'; t.src = 'https://www.youtube.com/iframe_api'
      t.onerror = () => reject(new Error('yt api'))
      document.head.appendChild(t)
    }
    let n = 0
    const c = setInterval(() => {
      if (window.YT?.Player) { clearInterval(c); resolve() }
      else if (++n > 150) { clearInterval(c); reject(new Error('yt timeout')) }
    }, 100)
  })
}

function showToast(msg, ms = 2000) {
  toast.value = msg
  clearTimeout(toastTimer)
  toastTimer = setTimeout(() => { toast.value = '' }, ms)
}

function clearTimers() { clearTimeout(retryTimer); clearTimeout(watchdog) }

async function initPlayer() {
  if (player || !playerHost.value || !current.value.youtube_id) return
  try { await loadYTApi() } catch { starting.value = false; needTap.value = true; showToast('유튜브 플레이어를 불러오지 못했어요'); return }
  if (destroyed || player || !playerHost.value) return
  const el = document.createElement('div')
  playerHost.value.appendChild(el)
  starting.value = true
  player = new window.YT.Player(el, {
    width: '100%', height: '100%',
    videoId: current.value.youtube_id,
    playerVars: {
      autoplay: 1, mute: 1, playsinline: 1, controls: 0, modestbranding: 1, rel: 0,
      disablekb: 1, fs: 0, iv_load_policy: 3, enablejsapi: 1, origin: window.location.origin,
    },
    events: { onReady, onStateChange, onError },
  })
  startWatchdog()
}

function onReady(e) {
  playerReady = true
  if (!soundOn.value) safe(() => e.target.mute())
  else safe(() => e.target.unMute())
  safe(() => e.target.playVideo())
  startWatchdog()
}

function onStateChange(e) {
  const S = window.YT.PlayerState
  switch (e.data) {
    case S.PLAYING:
      clearTimers(); retryCount = 0
      starting.value = false; needTap.value = false; paused.value = false
      if (soundOn.value && safe(() => player.isMuted())) safe(() => player.unMute())
      break
    case S.PAUSED:
      // 우리가 의도한 일시정지(paused=true)가 아니면 재시작 시도
      if (!paused.value && !document.hidden) scheduleRetry()
      break
    case S.UNSTARTED:
    case S.CUED:
      scheduleRetry()
      break
    case S.ENDED:
      // 반복 재생
      safe(() => { player.seekTo(0, true); player.playVideo() })
      break
  }
}

function onError(e) {
  // 2: 잘못된 ID, 5: HTML5 오류, 100: 삭제/비공개, 101/150: 임베드 차단
  clearTimers()
  starting.value = false
  if ([2, 5, 100, 101, 150].includes(e.data)) {
    showToast('재생할 수 없는 영상이라 다음으로 넘어가요')
    setTimeout(() => {
      if (destroyed) return
      if (idx.value < shorts.value.length - 1) next(); else if (idx.value > 0) prev()
    }, 1200)
  } else {
    needTap.value = true
  }
}

function scheduleRetry() {
  clearTimeout(retryTimer)
  if (retryCount >= 3) return
  retryCount++
  retryTimer = setTimeout(() => { if (!paused.value) safe(() => player.playVideo()) }, 500 * retryCount)
}

function startWatchdog() {
  clearTimeout(watchdog)
  watchdog = setTimeout(() => {
    const st = safe(() => player.getPlayerState())
    if (st !== window.YT?.PlayerState?.PLAYING && !paused.value) { starting.value = false; needTap.value = true }
  }, 2500)
}

function loadCurrent() {
  const id = current.value.youtube_id
  if (!id) return
  paused.value = false; needTap.value = false; starting.value = true; retryCount = 0
  clearTimers()
  if (!player || !playerReady) { initPlayer(); return }
  if (!soundOn.value) safe(() => player.mute())
  safe(() => player.loadVideoById({ videoId: id, startSeconds: 0 }))
  startWatchdog()
}

// 첫 사용자 제스처 → 소리 켜기 (제스처 핸들러 안에서 동기 호출해야 iOS 에서 허용됨)
function enableSound() {
  soundOn.value = true
  if (player && playerReady) {
    safe(() => player.unMute())
    safe(() => player.setVolume(100))
    safe(() => player.playVideo())
  }
}

function playNow() {
  paused.value = false; needTap.value = false
  retryCount = 0
  if (player && playerReady) {
    if (soundOn.value) safe(() => player.unMute())
    safe(() => player.playVideo())
    startWatchdog()
  } else initPlayer()
}

function togglePlay() {
  if (!player || !playerReady) { enableSound(); return }
  const S = window.YT.PlayerState
  const st = safe(() => player.getPlayerState())
  // 첫 제스처: 재생 중이면 소리만 켠다 (일시정지 하지 않음)
  if (!soundOn.value) {
    enableSound()
    if (st === S.PLAYING) return
  }
  if (needTap.value || st !== S.PLAYING) { playNow(); return }
  paused.value = true
  safe(() => player.pauseVideo())
}

// ─── 이동 ───
function next() {
  if (idx.value < shorts.value.length - 1) {
    idx.value++; liked.value = false; markViewed(); loadCurrent(); maybeLoadMore()
  }
}
function prev() {
  if (idx.value > 0) { idx.value--; liked.value = false; loadCurrent() }
}

async function fetchPage(p) {
  const { data } = await axios.get(`/api/shorts?per_page=50&page=${p}`)
  const d = data.data
  lastPage = d?.last_page || 1
  return d?.data || []
}

async function maybeLoadMore() {
  if (loadingMore || page >= lastPage || idx.value < shorts.value.length - 5) return
  loadingMore = true
  try {
    const more = await fetchPage(page + 1)
    page++
    const have = new Set(shorts.value.map(s => s.id))
    shorts.value.push(...more.filter(s => !have.has(s.id)))
  } catch {}
  loadingMore = false
}

async function markViewed() {
  if (!auth.isLoggedIn || !current.value.id) return
  try { await axios.post(`/api/shorts/${current.value.id}/viewed`) } catch {}
}

async function toggleLike() {
  if (!auth.isLoggedIn || !current.value.id) return
  try {
    const { data } = await axios.post(`/api/shorts/${current.value.id}/like`)
    liked.value = data.liked
    shorts.value[idx.value].like_count += data.liked ? 1 : -1
  } catch {}
}

function shareShort() {
  const url = `${window.location.origin}/shorts?v=${current.value.id}`
  if (navigator.share) { navigator.share({ title: current.value.title, url }).catch(() => {}) }
  else { navigator.clipboard.writeText(url); alert('링크가 복사되었습니다!') }
}

async function loadComments() {
  if (!current.value.id) return
  try { const { data } = await axios.get(`/api/comments/short/${current.value.id}`); comments.value = data.data || [] } catch { comments.value = [] }
}

watch(isFs, () => nextTick(() => { measure(); setTimeout(measure, 150) }))
watch([showComments, idx], ([open]) => { if (open) loadComments() })

async function submitComment() {
  if (!newComment.value.trim() || !current.value.id) return
  try {
    const { data } = await axios.post('/api/comments', { commentable_type: 'short', commentable_id: current.value.id, content: newComment.value })
    comments.value.push(data.data); newComment.value = ''
    shorts.value[idx.value].comment_count = (shorts.value[idx.value].comment_count || 0) + 1
  } catch {}
}

// ─── 전체화면 ───
function fsElement() { return document.fullscreenElement || document.webkitFullscreenElement || null }
function nativeFsSupported() {
  const el = rootEl.value
  return !!(el && (el.requestFullscreen || el.webkitRequestFullscreen)) &&
    document.fullscreenEnabled !== false && document.webkitFullscreenEnabled !== false
}

let fsPushed = false
async function enterFs() {
  const el = rootEl.value
  if (nativeFsSupported()) {
    try {
      const r = (el.requestFullscreen || el.webkitRequestFullscreen).call(el)
      if (r && r.then) await r
      return
    } catch { /* 실패 시 의사 전체화면으로 */ }
  }
  // iOS Safari 등: 헤더·하단 메뉴까지 덮는 의사 전체화면 (뒤로가기로도 복귀)
  pseudoFs.value = true
  try { history.pushState({ ...(history.state || {}), __shortsFs: 1 }, ''); fsPushed = true } catch { fsPushed = false }
  try { window.scrollTo(0, 0) } catch {}
}
function exitFs() {
  if (fsElement()) safe(() => (document.exitFullscreen || document.webkitExitFullscreen).call(document))
  if (pseudoFs.value) {
    if (fsPushed) { fsPushed = false; pseudoFs.value = false; try { history.back() } catch {} }
    else pseudoFs.value = false
  }
}
function toggleFs() { isFs.value ? exitFs() : enterFs() }
function onFsChange() { nativeFs.value = !!fsElement() }
function onPopState() {
  if (pseudoFs.value) { pseudoFs.value = false; fsPushed = false }
}
function toggleFill() {
  fillMode.value = !fillMode.value
  try { localStorage.setItem('shorts_view_mode', fillMode.value ? 'fill' : 'fit') } catch {}
}

// ─── 레이아웃 측정: 사이트 헤더(NavBar)와 하단 메뉴(BottomNav) 사이에만 배치 ───
function measure() {
  if (destroyed) return
  if (!isFs.value) {
    const nav = document.querySelector('nav.sticky')
    const tb = nav ? Math.max(0, Math.round(nav.getBoundingClientRect().bottom)) : 0
    const bn = document.querySelector('.fixed.bottom-0.left-0.right-0.border-t')
    let bb = 0
    if (bn && getComputedStyle(bn).display !== 'none') {
      const r = bn.getBoundingClientRect()
      if (r.height > 0) bb = Math.max(0, Math.round(window.innerHeight - r.top))
    }
    topInset.value = tb; bottomInset.value = bb
  }
  const a = areaEl.value
  if (a) { aw.value = a.clientWidth || aw.value; ah.value = a.clientHeight || ah.value }
}
let ro = null
let measureTimer = null

// ─── 키보드 / 휠 ───
function onKeydown(e) {
  const t = e.target
  if (t && (t.tagName === 'INPUT' || t.tagName === 'TEXTAREA' || t.isContentEditable)) return
  if (e.key === 'ArrowDown' || e.key === 'j') { e.preventDefault(); next() }
  else if (e.key === 'ArrowUp' || e.key === 'k') { e.preventDefault(); prev() }
  else if (e.key === ' ' || e.code === 'Space') { e.preventDefault(); togglePlay() }
  else if (e.key === 'f' || e.key === 'F') toggleFs()
  else if (e.key === 'Escape') {
    if (showComments.value) showComments.value = false
    else if (pseudoFs.value) exitFs()
  }
}

let scrollCooldown = false
function onWheel(e) {
  if (e.target?.closest?.('[data-comments]')) return
  if (Math.abs(e.deltaY) < 8 || scrollCooldown) return
  scrollCooldown = true
  if (e.deltaY > 0) next()
  else if (e.deltaY < 0) prev()
  setTimeout(() => { scrollCooldown = false }, 800)
}

function onVisibility() {
  if (!player || !playerReady) return
  if (document.hidden) {
    wasPlayingBeforeHide = safe(() => player.getPlayerState()) === window.YT.PlayerState.PLAYING
    safe(() => player.pauseVideo())
  } else if (wasPlayingBeforeHide && !paused.value) {
    safe(() => player.playVideo())
    startWatchdog()
  }
}

// ─── 제스처 레이어 (터치 스와이프 / 탭) ───
let touchActive = false
let tsX = 0, tsY = 0, tsT = 0, lastTouchEnd = 0
function onTouchStart(e) {
  if (e.touches.length !== 1) { touchActive = false; return }
  touchActive = true
  tsX = e.touches[0].clientX; tsY = e.touches[0].clientY; tsT = Date.now()
}
function onTouchCancel() { touchActive = false }
function onTouchMove(e) {
  // 페이지 뒤 스크롤 / 사파리 바운스 방지
  if (e.cancelable) e.preventDefault()
}
function onTouchEnd(e) {
  if (!touchActive) return
  touchActive = false
  const t = e.changedTouches[0]
  const dx = t.clientX - tsX
  const dy = tsY - t.clientY      // 양수 = 위로 스와이프
  const dt = Math.max(1, Date.now() - tsT)
  const adx = Math.abs(dx), ady = Math.abs(dy)
  lastTouchEnd = Date.now()
  if (e.cancelable) e.preventDefault()   // 합성 click 방지
  if (ady > adx * 1.2 && (ady > 50 || (ady > 28 && ady / dt > 0.5)) && dt < 1200) {
    dy > 0 ? next() : prev()
  } else if (adx < 12 && ady < 12 && dt < 600) {
    togglePlay()
  }
}
function onLayerClick() {
  // 터치로 이미 처리된 경우 합성 click 무시
  if (Date.now() - lastTouchEnd < 700) return
  togglePlay()
}

onMounted(async () => {
  try { shorts.value = await fetchPage(1) } catch {}
  loading.value = false
  window.addEventListener('keydown', onKeydown)
  window.addEventListener('wheel', onWheel, { passive: true })
  document.addEventListener('visibilitychange', onVisibility)
  document.addEventListener('fullscreenchange', onFsChange)
  document.addEventListener('webkitfullscreenchange', onFsChange)
  window.addEventListener('popstate', onPopState)
  window.addEventListener('resize', measure)
  window.addEventListener('orientationchange', measure)
  window.visualViewport && window.visualViewport.addEventListener('resize', measure)
  let n = 0
  measureTimer = setInterval(() => { measure(); if (++n > 12) { clearInterval(measureTimer); measureTimer = null } }, 400)
  document.documentElement.style.overscrollBehavior = 'none'
  document.body.style.overflow = 'hidden'
  await nextTick()
  measure()
  if (window.ResizeObserver && areaEl.value) { ro = new ResizeObserver(measure); ro.observe(areaEl.value) }
  initPlayer()
})

onUnmounted(() => {
  destroyed = true
  clearInterval(measureTimer); ro && ro.disconnect()
  window.removeEventListener('popstate', onPopState)
  window.removeEventListener('resize', measure)
  window.removeEventListener('orientationchange', measure)
  window.visualViewport && window.visualViewport.removeEventListener('resize', measure)
  clearTimers(); clearTimeout(toastTimer)
  window.removeEventListener('keydown', onKeydown)
  window.removeEventListener('wheel', onWheel)
  document.removeEventListener('visibilitychange', onVisibility)
  document.removeEventListener('fullscreenchange', onFsChange)
  document.removeEventListener('webkitfullscreenchange', onFsChange)
  if (fsElement()) safe(() => (document.exitFullscreen || document.webkitExitFullscreen).call(document))
  document.documentElement.style.overscrollBehavior = ''
  document.body.style.overflow = ''
  safe(() => player && player.destroy())
  player = null
})
</script>
