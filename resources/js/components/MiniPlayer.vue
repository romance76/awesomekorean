<template>
<Teleport to="body">
  <!-- 🎵 최소화 버튼 -->
  <div v-if="showMiniBtn"
    class="fixed bottom-20 right-4 z-[9998] w-14 h-14 rounded-full bg-gradient-to-br from-indigo-600 to-purple-600 shadow-xl flex items-center justify-center cursor-pointer hover:scale-110 transition-all animate-pulse-slow"
    @click="isExpanded = true">
    <span class="text-white text-xl">{{ music.isPlaying ? '🎵' : '⏸' }}</span>
  </div>

  <!-- 플레이어 패널 -->
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

    <!-- YouTube 영상 영역 (실제 Player는 별도) -->
    <div id="yt-slot" class="aspect-video bg-black flex-shrink-0"></div>

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

  <!-- YouTube Player — 항상 DOM에 존재 (v-if 밖) -->
  <div v-if="music.hasTrack && music.currentTrack?.youtubeId && !isShutdown"
    :style="ytContainerStyle" class="fixed z-[9997] overflow-hidden">
    <div id="yt-mini-player" class="w-full h-full"></div>
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
const isShutdown = ref(false) // 완전 종료 상태
const window_w = ref(window.innerWidth)

// YouTube container: 펼침이면 영상 슬롯 위에, 아니면 화면 밖 (오디오만)
const ytContainerStyle = computed(() => {
  if (isExpanded.value) {
    // yt-slot 위에 오버레이
    const el = document.getElementById('yt-slot')
    if (el) {
      const r = el.getBoundingClientRect()
      return { left: r.left + 'px', top: r.top + 'px', width: r.width + 'px', height: r.height + 'px' }
    }
    return { width: '280px', height: '158px', right: posRight.value + 'px', top: (posTop.value + 40) + 'px' }
  }
  // 숨김 — 화면 밖 (오디오 계속 재생)
  return { width: '1px', height: '1px', left: '-9999px', top: '-9999px' }
})
let ytPlayer = null
let progressTimer = null
let currentVideoId = null

const isShortsPage = computed(() => route.path.startsWith('/shorts'))
const isMusicPage = computed(() => route.path.startsWith('/music'))

// 🎵 버튼: 트랙 있고 + 펼침 아니고 + 종료 아니고 + 숏츠 아닐 때
const showMiniBtn = computed(() => music.hasTrack && !isExpanded.value && !isShutdown.value && !isShortsPage.value)

// 플레이어 패널: (트랙 있거나 음악페이지) + 펼침 상태 + 종료 아닐 때
const showPlayer = computed(() => (music.hasTrack || isMusicPage.value) && isExpanded.value && !isShutdown.value)

const posRight = ref(16)
const posTop = ref(170)

function calcMusicPageRight() {
  const viewW = window.innerWidth
  // 화면이 좁으면 (1024px 미만) 오른쪽 하단에 배치
  if (viewW < 1024) return 16
  return Math.max((viewW - 1280) / 2, 16)
}

function calcMusicPageTop() {
  // 화면이 좁으면 하단에 배치
  if (window.innerWidth < 1024) return window.innerHeight - 520
  return 170
}

// − 최소화: 🎵 버튼으로, 음악 계속
function minimize() {
  isExpanded.value = false
}

// ✕ 완전 종료: 음악 멈춤 + 플레이어 완전히 사라짐
function shutdown() {
  try { ytPlayer?.stopVideo() } catch {}
  try { ytPlayer?.destroy() } catch {}
  ytPlayer = null
  currentVideoId = null
  music.stop()
  isExpanded.value = false
  isShutdown.value = true
}

function togglePlay() {
  if (!ytPlayer) { if (music.currentTrack?.youtubeId) createPlayer(music.currentTrack.youtubeId, music.currentTime || 0); return }
  try {
    const st = ytPlayer.getPlayerState()
    if (st === 1) ytPlayer.pauseVideo()
    else ytPlayer.playVideo()
  } catch { createPlayer(music.currentTrack?.youtubeId, music.currentTime || 0) }
}

function doPrev() {
  music.prev()
  nextTick(() => { if (music.currentTrack?.youtubeId) loadVideo(music.currentTrack.youtubeId) })
}
function doNext() {
  music.next()
  nextTick(() => { if (music.currentTrack?.youtubeId) loadVideo(music.currentTrack.youtubeId) })
}
function playFromList(track) {
  music.play(track)
  loadVideo(track.youtubeId, 0)
}
function seekTo(e) {
  if (!ytPlayer?.getDuration) return
  const rect = e.currentTarget.getBoundingClientRect()
  ytPlayer.seekTo((e.clientX - rect.left) / rect.width * ytPlayer.getDuration(), true)
}
function setVolume() { try { ytPlayer?.setVolume(volume.value) } catch {} }

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
}
function stopDrag() {
  dragging = false
  document.removeEventListener('mousemove', onDrag)
  document.removeEventListener('mouseup', stopDrag)
  document.removeEventListener('touchmove', onDrag)
  document.removeEventListener('touchend', stopDrag)
}

// YouTube
function loadYTApi() {
  if (window.YT?.Player) return Promise.resolve()
  return new Promise(resolve => {
    if (document.getElementById('yt-api-script')) {
      const c = setInterval(() => { if (window.YT?.Player) { clearInterval(c); resolve() } }, 100)
      return
    }
    const t = document.createElement('script')
    t.id = 'yt-api-script'
    t.src = 'https://www.youtube.com/iframe_api'
    document.head.appendChild(t)
    window.onYouTubeIframeAPIReady = resolve
  })
}

async function createPlayer(videoId, startAt = 0) {
  await loadYTApi()
  await nextTick()
  const el = document.getElementById('yt-mini-player')
  if (!el) { setTimeout(() => createPlayer(videoId, startAt), 500); return }
  if (ytPlayer) { try { ytPlayer.destroy() } catch {}; ytPlayer = null }
  ytPlayer = new window.YT.Player('yt-mini-player', {
    width: '100%', height: '100%', videoId,
    playerVars: { autoplay: 1, controls: 1, modestbranding: 1, rel: 0, playsinline: 1 },
    events: {
      onReady: (e) => { e.target.setVolume(volume.value); if (startAt > 0) e.target.seekTo(startAt, true); e.target.playVideo(); currentVideoId = videoId; music.isPlaying = true; startProgressTimer() },
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

// 곡 변경 → 자동 펼침 + 종료 상태 해제
watch(() => music.currentTrack?.youtubeId, (vid) => {
  if (!vid) return
  isShutdown.value = false // 새 곡 선택 → 종료 해제
  isExpanded.value = true
  if (isMusicPage.value) { posRight.value = calcMusicPageRight(); posTop.value = calcMusicPageTop() }
  else { posRight.value = 16; posTop.value = Math.max(window.innerHeight - 550, 80) }
  nextTick(() => loadVideo(vid, 0))
})

// 음악 페이지 진입 → 펼침 (종료 상태 아닐 때)
watch(isMusicPage, (isMp) => {
  if (isMp && !isShutdown.value) {
    isExpanded.value = true
    posRight.value = calcMusicPageRight()
    posTop.value = 170
  }
})

// 음악 페이지 떠남 → 최소화
watch(isMusicPage, (isMp, wasMp) => {
  if (!isMp && wasMp) isExpanded.value = false
})

onMounted(() => {
  window.addEventListener('resize', onResize)
  if (isMusicPage.value && !isShutdown.value) {
    isExpanded.value = true
    posRight.value = calcMusicPageRight()
    posTop.value = calcMusicPageTop()
  }
  if (music.currentTrack?.youtubeId) {
    isExpanded.value = true
    nextTick(() => createPlayer(music.currentTrack.youtubeId, music.currentTime || 0))
  }
})
function onResize() {
  window_w.value = window.innerWidth
  if (isMusicPage.value && isExpanded.value) {
    posRight.value = calcMusicPageRight()
    posTop.value = calcMusicPageTop()
  }
}
onUnmounted(() => { if (progressTimer) clearInterval(progressTimer); window.removeEventListener('resize', onResize) })
</script>

<style scoped>
@keyframes pulse-slow { 0%,100%{box-shadow:0 0 0 0 rgba(99,102,241,.4)} 50%{box-shadow:0 0 0 8px rgba(99,102,241,0)} }
.animate-pulse-slow { animation: pulse-slow 2s infinite; }
input[type="range"] { height: 4px; }
.music-scroll::-webkit-scrollbar { width: 4px; }
.music-scroll::-webkit-scrollbar-thumb { background: #4338ca; border-radius: 2px; }
</style>
