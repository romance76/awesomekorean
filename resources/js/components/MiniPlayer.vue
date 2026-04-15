<template>
<Teleport to="body">
  <!-- 🎵 최소화 버튼 -->
  <div v-if="showMiniBtn"
    class="fixed bottom-20 right-4 z-[9998] w-14 h-14 rounded-full bg-gradient-to-br from-indigo-600 to-purple-600 shadow-xl flex items-center justify-center cursor-pointer hover:scale-110 transition-all animate-pulse-slow"
    @click="expand">
    <span class="text-white text-xl">{{ music.isPlaying ? '🎵' : '⏸' }}</span>
  </div>

  <!-- 플레이어 UI (영상 제외) -->
  <div v-if="showPlayer"
    class="fixed z-[9998] bg-[#1a1a2e] rounded-2xl shadow-2xl border border-white/10 flex flex-col overflow-hidden"
    :class="isMusicPage && window_w >= 1024 ? 'w-[280px]' : 'w-[300px]'"
    :style="{ right: posRight + 'px', top: posTop + 'px', maxHeight: '75vh' }">

    <!-- 헤더 -->
    <div @mousedown="startDrag" @touchstart.passive="startDrag"
      class="px-3 py-2 flex items-center justify-between cursor-move bg-gradient-to-r from-indigo-700 to-purple-700 select-none flex-shrink-0">
      <div class="flex items-center gap-2 flex-1 min-w-0">
        <span class="text-sm">🎵</span>
        <p class="text-white text-xs font-bold truncate">{{ music.currentTrack?.title || '재생 대기 중' }}</p>
      </div>
      <div class="flex items-center gap-1 flex-shrink-0">
        <button @click.stop="minimize" class="w-6 h-6 rounded hover:bg-white/20 text-white/70 hover:text-white flex items-center justify-center text-sm" title="최소화">−</button>
        <button @click.stop="shutdown" class="w-6 h-6 rounded hover:bg-red-500/40 text-white/70 hover:text-white flex items-center justify-center text-xs" title="종료">✕</button>
      </div>
    </div>

    <!-- 영상 자리 (yt-anchor의 위치 참조용) -->
    <div ref="ytAnchor" class="aspect-video bg-black flex-shrink-0"></div>

    <!-- 컨트롤 -->
    <div class="px-3 py-2 flex items-center gap-2 flex-shrink-0">
      <button @click="doPrev" class="w-7 h-7 rounded-full text-gray-400 hover:text-white flex items-center justify-center text-xs">⏮</button>
      <button @click="togglePlay" class="w-9 h-9 rounded-full bg-indigo-600 text-white flex items-center justify-center hover:bg-indigo-500 text-sm">{{ music.isPlaying ? '⏸' : '▶' }}</button>
      <button @click="doNext" class="w-7 h-7 rounded-full text-gray-400 hover:text-white flex items-center justify-center text-xs">⏭</button>
      <div class="flex-1 h-1 bg-gray-700 rounded-full mx-1 cursor-pointer" @click="seekTo">
        <div class="h-full bg-indigo-500 rounded-full transition-all" :style="{ width: music.progress + '%' }"></div>
      </div>
      <span class="text-gray-500 text-[10px]">🔊</span>
      <input type="range" min="0" max="100" v-model="volume" @input="setVolume" class="w-12 h-1 accent-indigo-500" style="appearance:auto;" />
    </div>

    <!-- 플레이리스트 -->
    <div class="border-t border-white/10 flex-shrink-0">
      <button @click="showPL = !showPL" class="w-full px-3 py-1.5 text-[10px] text-gray-400 hover:text-gray-200 flex items-center justify-between">
        <span>다음 곡 ({{ music.playlist.length }}곡)</span>
        <span>{{ showPL ? '▲' : '▼' }}</span>
      </button>
    </div>
    <div v-if="showPL && music.playlist.length" class="overflow-y-auto max-h-[400px] px-1 pb-1 music-scroll">
      <div v-for="(track, idx) in music.playlist" :key="track.id || idx" @click="playFromList(track)"
        class="flex items-center gap-2 px-2 py-1 rounded cursor-pointer transition text-[11px]"
        :class="music.currentIndex === idx ? 'bg-indigo-900/50 text-indigo-300 font-semibold' : 'text-gray-400 hover:bg-white/5'">
        <span class="w-4 text-right text-gray-600">{{ idx + 1 }}</span>
        <span class="truncate flex-1">{{ track.title }}</span>
        <span v-if="music.currentIndex === idx && music.isPlaying" class="text-indigo-400">♪</span>
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

const isShortsPage = computed(() => route.path.startsWith('/shorts'))
const isMusicPage = computed(() => route.path.startsWith('/music'))
const showMiniBtn = computed(() => music.hasTrack && !isExpanded.value && !isShutdown.value && !isShortsPage.value)
const showPlayer = computed(() => (music.hasTrack || isMusicPage.value) && isExpanded.value && !isShutdown.value)

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
  ytPlayer = null; currentVideoId = null
  music.stop(); isExpanded.value = false; isShutdown.value = true
  updateYTPosition()
}

function togglePlay() {
  if (!ytPlayer) { if (music.currentTrack?.youtubeId) createPlayer(music.currentTrack.youtubeId, music.currentTime || 0); return }
  try { const s = ytPlayer.getPlayerState(); if (s === 1) ytPlayer.pauseVideo(); else ytPlayer.playVideo() } catch { createPlayer(music.currentTrack?.youtubeId, music.currentTime || 0) }
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
      onStateChange: (e) => {
        if (e.data === window.YT.PlayerState.ENDED) { music.next(); nextTick(() => { if (music.currentTrack?.youtubeId) loadVideo(music.currentTrack.youtubeId) }) }
        if (e.data === window.YT.PlayerState.PLAYING) music.isPlaying = true
        if (e.data === window.YT.PlayerState.PAUSED) music.isPlaying = false
      },
      onError: () => setTimeout(() => music.next(), 1000)
    }
  })
}

function loadVideo(videoId, startAt = 0) {
  try { if (ytPlayer?.loadVideoById && ytPlayer?.getPlayerState) { ytPlayer.loadVideoById({ videoId, startSeconds: startAt }); currentVideoId = videoId; return } } catch {}
  createPlayer(videoId, startAt)
}

function startProgressTimer() {
  if (progressTimer) clearInterval(progressTimer)
  progressTimer = setInterval(() => { try { const c = ytPlayer?.getCurrentTime(), d = ytPlayer?.getDuration(); if (d > 0) music.setProgress((c / d) * 100, c, d) } catch {} }, 1000)
}

// Watchers
watch(() => music.currentTrack?.youtubeId, (vid) => {
  if (!vid) return
  isShutdown.value = false; isExpanded.value = true
  if (isMusicPage.value) { posRight.value = calcMusicPageRight(); posTop.value = calcMusicPageTop() }
  else { posRight.value = 16; posTop.value = Math.max(window.innerHeight - 550, 80) }
  nextTick(() => { loadVideo(vid, 0); updateYTPosition() })
})

watch(isMusicPage, (isMp) => {
  if (isMp && !isShutdown.value) { isExpanded.value = true; posRight.value = calcMusicPageRight(); posTop.value = calcMusicPageTop(); nextTick(updateYTPosition) }
})
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
  if (isMusicPage.value && !isShutdown.value) { isExpanded.value = true; posRight.value = calcMusicPageRight(); posTop.value = calcMusicPageTop() }
  if (music.currentTrack?.youtubeId) { isExpanded.value = true; nextTick(() => createPlayer(music.currentTrack.youtubeId, music.currentTime || 0)) }
})
onUnmounted(() => { if (progressTimer) clearInterval(progressTimer); if (positionTimer) clearInterval(positionTimer); window.removeEventListener('resize', onResize) })
</script>

<style scoped>
@keyframes pulse-slow { 0%,100%{box-shadow:0 0 0 0 rgba(99,102,241,.4)} 50%{box-shadow:0 0 0 8px rgba(99,102,241,0)} }
.animate-pulse-slow { animation: pulse-slow 2s infinite; }
input[type="range"] { height: 4px; }
.music-scroll::-webkit-scrollbar { width: 4px; }
.music-scroll::-webkit-scrollbar-thumb { background: #4338ca; border-radius: 2px; }
</style>
