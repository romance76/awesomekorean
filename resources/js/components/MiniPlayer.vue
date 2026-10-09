<template>
<Teleport to="body">
  <!-- 음악 최소화 버튼 -->
  <div v-if="showMiniBtn && !isMobile"
    class="fixed bottom-20 right-4 z-[9998] w-14 h-14 rounded-full bg-gradient-to-br from-[#FF8A4D] to-[#FC226B] shadow-xl flex items-center justify-center cursor-pointer hover:scale-110 transition-all animate-pulse-slow"
    @click="expand">
    <span class="text-white"><AppIcon :name="music.isPlaying ? 'music' : 'play'" :size="22" :filled="!music.isPlaying" /></span>
  </div>

  <!-- 모바일 미니바 (하단 네비 바로 위) -->
  <div v-if="showMiniBtn && isMobile"
    class="fixed left-2 right-2 z-[9998] h-14 rounded-2xl bg-[#1a1a2e] text-white shadow-2xl flex items-center gap-2.5 px-2.5 overflow-hidden cursor-pointer"
    style="bottom: calc(64px + env(safe-area-inset-bottom))"
    @click="expand">
    <div class="w-10 h-10 rounded-lg flex-shrink-0 bg-gradient-to-br from-[#FF8A4D] to-[#FC226B] overflow-hidden flex items-center justify-center">
      <img v-if="music.currentTrack?.thumbnail" :src="music.currentTrack.thumbnail" class="w-full h-full object-cover" alt="" @error="$event.target.style.display='none'" />
      <span v-else class="text-white"><AppIcon name="music" :size="18" /></span>
    </div>
    <div class="flex-1 min-w-0">
      <p class="text-[13px] font-bold truncate">{{ music.currentTrack?.title }}</p>
      <p class="text-[11px] text-gray-400 truncate">{{ music.currentTrack?.artist || '' }}</p>
    </div>
    <button @click.stop="doPrev" class="w-9 h-9 rounded-full text-gray-300 flex items-center justify-center"><AppIcon name="chevron-left" :size="18" /></button>
    <button @click.stop="togglePlay" class="w-10 h-10 rounded-full bg-[#FC226B] text-white flex items-center justify-center">
      <span v-if="music.isPlaying" class="flex items-center" style="gap:3px"><span class="block bg-white rounded-sm" style="width:3px;height:13px"></span><span class="block bg-white rounded-sm" style="width:3px;height:13px"></span></span>
      <AppIcon v-else name="play" :size="16" :filled="true" />
    </button>
    <button @click.stop="doNext" class="w-9 h-9 rounded-full text-gray-300 flex items-center justify-center"><AppIcon name="chevron-right" :size="18" /></button>
    <div class="absolute left-0 bottom-0 h-[2px] bg-[#FF8A4D]" :style="{ width: music.progress + '%' }"></div>
  </div>

  <!-- 플레이어 UI (영상 제외) — 모바일은 전체화면 -->
  <div v-if="showPlayer"
    class="fixed z-[9998] bg-[#1a1a2e] flex flex-col overflow-hidden"
    :class="isMobile ? 'inset-0' : ['rounded-2xl shadow-2xl border border-white/10', isMusicPage && window_w >= 1024 ? 'w-[280px]' : 'w-[300px]']"
    :style="isMobile ? {} : { right: posRight + 'px', top: posTop + 'px', maxHeight: '75vh' }">

    <!-- 헤더 -->
    <div @mousedown="!isMobile && startDrag($event)" @touchstart.passive="!isMobile && startDrag($event)"
      class="px-3 flex items-center justify-between bg-gradient-to-r from-[#FF8A4D] to-[#FC226B] select-none flex-shrink-0"
      :class="isMobile ? 'py-3' : 'py-2 cursor-move'">
      <div class="flex items-center gap-2 flex-1 min-w-0">
        <span class="text-white/90"><AppIcon name="music" :size="14" /></span>
        <p class="text-white font-bold truncate" :class="isMobile ? 'text-[15px]' : 'text-xs'">{{ music.currentTrack?.title || '재생 대기 중' }}</p>
      </div>
      <div class="flex items-center gap-1 flex-shrink-0">
        <button @click.stop="minimize" class="w-6 h-6 rounded hover:bg-white/20 text-white/70 hover:text-white flex items-center justify-center transition-colors" title="최소화"><AppIcon name="chevron-down" :size="14" /></button>
        <button @click.stop="shutdown" class="w-6 h-6 rounded hover:bg-red-500/40 text-white/70 hover:text-white flex items-center justify-center transition-colors" title="종료"><AppIcon name="x" :size="13" /></button>
      </div>
    </div>

    <!-- 영상 자리 (yt-anchor의 위치 참조용) -->
    <!-- 가로로 넓고 낮은 화면(테슬라 등)에서도 아래 재생 목록이 보이도록 영상 높이를 화면의 38%까지만 -->
    <div ref="ytAnchor" class="aspect-video bg-black flex-shrink-0" :style="isMobile ? { width: 'min(100%, calc(38vh * 1.7778))', margin: '0 auto' } : {}"></div>

    <!-- 컨트롤 -->
    <div class="px-3 py-2 flex items-center gap-2 flex-shrink-0">
      <button @click="doPrev" class="w-7 h-7 rounded-full text-gray-400 hover:text-white flex items-center justify-center transition-colors"><AppIcon name="chevron-left" :size="16" /></button>
      <button @click="togglePlay" class="w-9 h-9 rounded-full bg-amber-400 text-white flex items-center justify-center hover:bg-amber-500 transition-colors">
        <span v-if="music.isPlaying" class="flex items-center" style="gap:3px"><span class="block bg-white rounded-sm" style="width:3px;height:13px"></span><span class="block bg-white rounded-sm" style="width:3px;height:13px"></span></span>
        <AppIcon v-else name="play" :size="15" :filled="true" />
      </button>
      <button @click="doNext" class="w-7 h-7 rounded-full text-gray-400 hover:text-white flex items-center justify-center transition-colors"><AppIcon name="chevron-right" :size="16" /></button>
      <div class="flex-1 h-1 bg-gray-700 rounded-full mx-1 cursor-pointer" @click="seekTo">
        <div class="h-full bg-amber-400 rounded-full transition-all" :style="{ width: music.progress + '%' }"></div>
      </div>
      <span class="text-gray-500"><AppIcon name="megaphone" :size="12" /></span>
      <input type="range" min="0" max="100" v-model="volume" @input="setVolume" class="w-12 h-1 accent-amber-500" style="appearance:auto;" />
    </div>

    <!-- 플레이리스트 -->
    <div class="border-t border-white/10 flex-shrink-0">
      <button @click="showPL = !showPL" class="w-full px-3 text-gray-400 hover:text-gray-200 flex items-center justify-between transition-colors" :style="isMobile ? 'padding-top:12px;padding-bottom:12px;font-size:14px' : 'padding-top:6px;padding-bottom:6px;font-size:11px'">
        <span>다음 곡 ({{ music.playlist.length }}곡)</span>
        <AppIcon :name="showPL ? 'chevron-up' : 'chevron-down'" :size="12" />
      </button>
    </div>
    <div v-if="showPL && music.playlist.length" class="overflow-y-auto px-1 pb-1 music-scroll"
      :class="isMobile ? 'flex-1 min-h-0 text-[13px]' : 'max-h-[400px]'">
      <div v-for="(track, idx) in music.playlist" :key="track.id || idx" @click="playFromList(track)"
        class="flex items-center gap-2 rounded cursor-pointer transition"
        :style="isMobile ? 'padding:12px 10px;font-size:15px;line-height:1.4' : 'padding:4px 8px;font-size:11px'"
        :class="music.currentIndex === idx ? 'bg-amber-500/15 text-amber-300 font-semibold' : (isMobile ? 'text-gray-200 hover:bg-white/5' : 'text-gray-400 hover:bg-white/5')">
        <span class="text-right text-gray-500 flex-shrink-0" :style="isMobile ? 'width:26px;font-size:13px' : 'width:16px'">{{ idx + 1 }}</span>
        <span class="flex-1 min-w-0" :class="isMobile ? 'line-clamp-2' : 'truncate'">{{ track.title }}</span>
        <span v-if="music.currentIndex === idx && music.isPlaying" class="text-amber-400 flex-shrink-0"><AppIcon name="music" :size="isMobile ? 16 : 11" /></span>
      </div>
    </div>
    <div v-else-if="showPL && !music.playlist.length" class="px-3 py-3 text-center text-gray-600 text-[11px]">
      곡을 클릭하면 여기에 재생 목록이 표시됩니다
    </div>
  </div>

  <!-- YouTube Player — v-if 밖, 항상 DOM에 존재, 위치만 이동 -->
  <div id="yt-float"
    class="fixed z-[9999] overflow-hidden"
    :style="ytFloatStyle">
    <div id="yt-mini-player" style="width:100%;height:100%"></div>
  </div>
</Teleport>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue'
import { useRoute } from 'vue-router'
import { useMusicStore } from '../stores/music'
import AppIcon from './AppIcon.vue'

const music = useMusicStore()
const route = useRoute()
const volume = ref(80)
const showPL = ref(true)
const isExpanded = ref(false)
const isShutdown = ref(false)
const window_w = ref(window.innerWidth)
const ytAnchor = ref(null)
let ytPlayer = null
let progressTimer = null
let positionTimer = null
let currentVideoId = null

const isMobile = computed(() => window_w.value < 1024)
let userPaused = false
let wakeLock = null
const isShortsPage = computed(() => route.path.startsWith('/shorts'))
const isMusicPage = computed(() => route.path.startsWith('/music'))
const showMiniBtn = computed(() => music.hasTrack && !isExpanded.value && !isShutdown.value && !isShortsPage.value)
const showPlayer = computed(() => (music.hasTrack || (isMusicPage.value && !isMobile.value)) && isExpanded.value && !isShutdown.value)

const posRight = ref(16)
const posTop = ref(170)

function calcMusicPageRight() {
  if (window.innerWidth < 1024) return 16
  return Math.max((window.innerWidth - 1280) / 2, 16)
}
function calcMusicPageTop() {
  return window.innerWidth < 1024 ? Math.max(window.innerHeight - 520, 80) : 170
}

// YouTube 플로팅 위치: 펼침이면 앵커 위치, 아니면 화면 밖 (오디오만)
const ytPos = ref({ x: -9999, y: -9999, w: 1, h: 1 })

const ytFloatStyle = computed(() => ({
  left: ytPos.value.x + 'px',
  top: ytPos.value.y + 'px',
  width: ytPos.value.w + 'px',
  height: ytPos.value.h + 'px',
}))

function updateYTPosition() {
  if (isExpanded.value && ytAnchor.value) {
    const r = ytAnchor.value.getBoundingClientRect()
    ytPos.value = { x: r.left, y: r.top, w: r.width, h: r.height }
  } else {
    ytPos.value = { x: -9999, y: -9999, w: 1, h: 1 }
  }
}

// 드래그
let dragging = false, dragStart = { x: 0, y: 0 }, posStart = { right: 0, top: 0 }
function startDrag(e) {
  dragging = true
  const ev = e.touches ? e.touches[0] : e
  dragStart = { x: ev.clientX, y: ev.clientY }
  posStart = { right: posRight.value, top: posTop.value }
  document.addEventListener('mousemove', onDrag)
  document.addEventListener('mouseup', stopDrag)
  document.addEventListener('touchmove', onDrag, { passive: false })
  document.addEventListener('touchend', stopDrag)
}
function onDrag(e) {
  if (!dragging) return
  const ev = e.touches ? e.touches[0] : e
  posRight.value = Math.max(0, Math.min(window.innerWidth - 340, posStart.right - (ev.clientX - dragStart.x)))
  posTop.value = Math.max(0, Math.min(window.innerHeight - 100, posStart.top + (ev.clientY - dragStart.y)))
  updateYTPosition()
}
function stopDrag() {
  dragging = false
  document.removeEventListener('mousemove', onDrag)
  document.removeEventListener('mouseup', stopDrag)
  document.removeEventListener('touchmove', onDrag)
  document.removeEventListener('touchend', stopDrag)
}

function expand() { isExpanded.value = true; nextTick(updateYTPosition) }
function minimize() { isExpanded.value = false; updateYTPosition() }
function shutdown() {
  try { ytPlayer?.stopVideo() } catch {}
  try { ytPlayer?.destroy() } catch {}
  ytPlayer = null; currentVideoId = null; preparing = false; pendingVideo = null
  music.stop(); isExpanded.value = false; isShutdown.value = true
  updateYTPosition()
}

function togglePlay() {
  if (!ytPlayer) { userPaused = false; if (music.currentTrack?.youtubeId) createPlayer(music.currentTrack.youtubeId, music.currentTime || 0); return }
  try { const s = ytPlayer.getPlayerState(); if (s === 1) { userPaused = true; ytPlayer.pauseVideo() } else { userPaused = false; ytPlayer.playVideo() } } catch { createPlayer(music.currentTrack?.youtubeId, music.currentTime || 0) }
}

// 재생 중 화면 꺼짐 방지 (Screen Wake Lock) — 지원 안 하는 기기는 조용히 무시
async function acquireWakeLock() {
  try {
    if (!('wakeLock' in navigator) || wakeLock) return
    wakeLock = await navigator.wakeLock.request('screen')
    wakeLock.addEventListener('release', () => { wakeLock = null })
  } catch { wakeLock = null }
}
function releaseWakeLock() { try { wakeLock?.release() } catch {}; wakeLock = null }

// 잠금화면/알림창 컨트롤 (Media Session)
function updateMediaSession() {
  if (!('mediaSession' in navigator)) return
  const t = music.currentTrack
  if (!t) { navigator.mediaSession.metadata = null; return }
  try {
    navigator.mediaSession.metadata = new window.MediaMetadata({
      title: t.title || '', artist: t.artist || '', album: 'Awesome Korean',
      artwork: t.thumbnail ? [{ src: t.thumbnail, sizes: '480x360', type: 'image/jpeg' }] : []
    })
  } catch {}
}
function setupMediaSessionHandlers() {
  if (!('mediaSession' in navigator)) return
  const set = (a, fn) => { try { navigator.mediaSession.setActionHandler(a, fn) } catch {} }
  set('play', () => { userPaused = false; try { ytPlayer?.playVideo() } catch {} })
  set('pause', () => { userPaused = true; try { ytPlayer?.pauseVideo() } catch {} })
  set('previoustrack', () => doPrev())
  set('nexttrack', () => doNext())
}

// 화면 복귀 시: 잠금 재획득 + 브라우저가 멈춘 재생 이어서 재생
function onVisibilityChange() {
  if (document.visibilityState !== 'visible') return
  if (music.hasTrack && !userPaused && !isShutdown.value) {
    acquireWakeLock()
    try { if (ytPlayer?.getPlayerState && ytPlayer.getPlayerState() !== 1) ytPlayer.playVideo() } catch {}
  }
}
function doPrev() { music.prev(); nextTick(() => { if (music.currentTrack?.youtubeId) loadVideo(music.currentTrack.youtubeId) }) }
function doNext() { music.next(); nextTick(() => { if (music.currentTrack?.youtubeId) loadVideo(music.currentTrack.youtubeId) }) }
function playFromList(track) { music.play(track); loadVideo(track.youtubeId, 0) }
function seekTo(e) { if (!ytPlayer?.getDuration) return; const r = e.currentTarget.getBoundingClientRect(); ytPlayer.seekTo((e.clientX - r.left) / r.width * ytPlayer.getDuration(), true) }
function setVolume() { try { ytPlayer?.setVolume(volume.value) } catch {} }

// YouTube API
function loadYTApi() {
  if (window.YT?.Player) return Promise.resolve()
  return new Promise(resolve => {
    if (document.getElementById('yt-api-script')) { const c = setInterval(() => { if (window.YT?.Player) { clearInterval(c); resolve() } }, 100); return }
    const t = document.createElement('script'); t.id = 'yt-api-script'; t.src = 'https://www.youtube.com/iframe_api'; document.head.appendChild(t); window.onYouTubeIframeAPIReady = resolve
  })
}

async function createPlayer(videoId, startAt = 0) {
  await loadYTApi()
  const el = document.getElementById('yt-mini-player')
  if (!el) { setTimeout(() => createPlayer(videoId, startAt), 500); return }
  if (ytPlayer) { try { ytPlayer.destroy() } catch {}; ytPlayer = null }
  ytPlayer = new window.YT.Player('yt-mini-player', {
    width: '100%', height: '100%', videoId,
    playerVars: { autoplay: 1, controls: 1, modestbranding: 1, rel: 0, playsinline: 1 },
    events: {
      onReady: (e) => { e.target.setVolume(volume.value); if (startAt > 0) e.target.seekTo(startAt, true); e.target.playVideo(); currentVideoId = videoId; music.isPlaying = true; startProgressTimer(); nextTick(updateYTPosition) },
      onStateChange: onPlayerStateChange,
      onError: () => setTimeout(() => music.next(), 1000)
    }
  })
}

function onPlayerStateChange(e) {
  if (e.data === window.YT.PlayerState.ENDED) { music.next(); nextTick(() => { if (music.currentTrack?.youtubeId) loadVideo(music.currentTrack.youtubeId) }) }
  if (e.data === window.YT.PlayerState.PLAYING) {
    music.isPlaying = true
    // 자동재생 차단 대비로 음소거 상태에서 시작했다면 재생이 시작된 뒤 소리 복구
    if (mutedFallback) { mutedFallback = false; setTimeout(() => { try { ytPlayer.unMute(); ytPlayer.setVolume(volume.value) } catch {} }, 300) }
  }
  if (e.data === window.YT.PlayerState.PAUSED) music.isPlaying = false
}

// 모바일: 사용자가 곡을 누르는 순간 바로 loadVideoById 를 호출할 수 있도록 빈 플레이어를 미리 만들어 둠
// (곡 클릭 후에 플레이어를 새로 만들면 모바일 자동재생 정책에 막혀 재생 버튼을 따로 눌러야 했음)
let mutedFallback = false
let pendingVideo = null
let preparing = false
async function prepareEmptyPlayer() {
  if (preparing) return
  preparing = true
  if (ytPlayer) return
  await loadYTApi()
  if (ytPlayer) return
  const el = document.getElementById('yt-mini-player')
  if (!el) { setTimeout(prepareEmptyPlayer, 500); return }
  ytPlayer = new window.YT.Player('yt-mini-player', {
    width: '100%', height: '100%',
    playerVars: { controls: 1, modestbranding: 1, rel: 0, playsinline: 1 },
    events: {
      onReady: (e) => {
        try { e.target.setVolume(volume.value) } catch {}
        startProgressTimer(); nextTick(updateYTPosition)
        preparing = false
        if (pendingVideo) { const p = pendingVideo; pendingVideo = null; loadVideo(p.videoId, p.startAt) }
      },
      onStateChange: onPlayerStateChange,
      onError: () => setTimeout(() => music.next(), 1000)
    }
  })
}

// 로드 후 2초 안에 재생이 시작되지 않으면(자동재생 차단) 음소거로 재시도 후 소리 복구
function ensureStarted() {
  setTimeout(() => {
    try {
      if (userPaused || !music.hasTrack || !ytPlayer?.getPlayerState) return
      const s = ytPlayer.getPlayerState()
      if (s === 1 || s === 3) return
      mutedFallback = true
      ytPlayer.mute(); ytPlayer.playVideo()
    } catch {}
  }, 2000)
}

function loadVideo(videoId, startAt = 0) {
  try { if (ytPlayer?.loadVideoById && ytPlayer?.getPlayerState) { ytPlayer.loadVideoById({ videoId, startSeconds: startAt }); currentVideoId = videoId; if (isMobile.value) ensureStarted(); return } } catch {}
  // 미리 만든 플레이어가 아직 준비 중이면 준비 완료 후 재생 (파괴·재생성 금지)
  if (preparing) { pendingVideo = { videoId, startAt }; return }
  createPlayer(videoId, startAt)
}

function startProgressTimer() {
  if (progressTimer) clearInterval(progressTimer)
  progressTimer = setInterval(() => { try { const c = ytPlayer?.getCurrentTime(), d = ytPlayer?.getDuration(); if (d > 0) music.setProgress((c / d) * 100, c, d) } catch {} }, 1000)
}

// Watchers
watch(() => music.currentTrack?.youtubeId, (vid) => {
  if (!vid) return
  isShutdown.value = false; userPaused = false
  if (!isMobile.value) isExpanded.value = true   // 모바일은 미니바로 시작
  updateMediaSession()
  if (isMusicPage.value) { posRight.value = calcMusicPageRight(); posTop.value = calcMusicPageTop() }
  else { posRight.value = 16; posTop.value = Math.max(window.innerHeight - 550, 80) }
  nextTick(() => { loadVideo(vid, 0); updateYTPosition() })
})

watch(isMusicPage, (isMp) => {
  if (isMp && !isShutdown.value && !isMobile.value) { isExpanded.value = true; posRight.value = calcMusicPageRight(); posTop.value = calcMusicPageTop(); nextTick(updateYTPosition) }
})
watch(isMusicPage, (isMp) => { if (isMp && isMobile.value && !ytPlayer && !isShutdown.value) prepareEmptyPlayer() })
watch(isMusicPage, (isMp, wasMp) => {
  if (!isMp && wasMp) { isExpanded.value = false; updateYTPosition() }
})
watch(isShortsPage, (is) => { if (is) { try { ytPlayer?.pauseVideo() } catch {}; music.isPlaying = false } })

function onResize() {
  window_w.value = window.innerWidth
  if (isMusicPage.value && isExpanded.value) { posRight.value = calcMusicPageRight(); posTop.value = calcMusicPageTop() }
  updateYTPosition()
}

onMounted(() => {
  window.addEventListener('resize', onResize)
  // 위치 동기화 타이머 (드래그 등에서 정확도 보장)
  positionTimer = setInterval(updateYTPosition, 500)
  if (isMusicPage.value && !isShutdown.value && !isMobile.value) { isExpanded.value = true; posRight.value = calcMusicPageRight(); posTop.value = calcMusicPageTop() }
  if (music.currentTrack?.youtubeId) { if (!isMobile.value) isExpanded.value = true; nextTick(() => createPlayer(music.currentTrack.youtubeId, music.currentTime || 0)) }
  document.addEventListener('visibilitychange', onVisibilityChange)
  setupMediaSessionHandlers()
  if (isMobile.value && isMusicPage.value && !music.currentTrack?.youtubeId) prepareEmptyPlayer()
})
watch(() => music.isPlaying, (p) => { if (p) acquireWakeLock(); else releaseWakeLock() })
watch(() => music.hasTrack, (h) => { if (!h) releaseWakeLock() })
onUnmounted(() => { document.removeEventListener('visibilitychange', onVisibilityChange); releaseWakeLock(); if (progressTimer) clearInterval(progressTimer); if (positionTimer) clearInterval(positionTimer); window.removeEventListener('resize', onResize) })
</script>

<style scoped>
@keyframes pulse-slow { 0%,100%{box-shadow:0 0 0 0 rgba(255,107,44,.4)} 50%{box-shadow:0 0 0 8px rgba(255,107,44,0)} }
.animate-pulse-slow { animation: pulse-slow 2s infinite; }
input[type="range"] { height: 4px; }
.music-scroll::-webkit-scrollbar { width: 4px; }
.music-scroll::-webkit-scrollbar-thumb { background: #FC226B; border-radius: 2px; }
</style>
