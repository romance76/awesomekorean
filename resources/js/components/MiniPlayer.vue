<template>
<Teleport to="body">
  <!-- ═══ 최소화 🎵 버튼 ═══ -->
  <div v-if="music.hasTrack && !hideUI && !isExpanded"
    class="fixed z-[9998] cursor-pointer hover:scale-110 transition-all animate-pulse-slow"
    :style="miniStyle"
    @click="expand">
    <div class="w-14 h-14 rounded-full bg-gradient-to-br from-indigo-600 to-purple-600 shadow-xl flex items-center justify-center relative">
      <span class="text-white text-xl">{{ music.isPlaying ? '🎵' : '⏸' }}</span>
      <svg class="absolute inset-0 w-14 h-14 -rotate-90 pointer-events-none" viewBox="0 0 56 56">
        <circle cx="28" cy="28" r="26" fill="none" stroke="rgba(255,255,255,0.15)" stroke-width="2" />
        <circle cx="28" cy="28" r="26" fill="none" stroke="#a78bfa" stroke-width="2.5"
          :stroke-dasharray="163" :stroke-dashoffset="163 - (163 * music.progress / 100)" stroke-linecap="round" />
      </svg>
    </div>
  </div>

  <!-- ═══ 플레이어 (항상 DOM에 존재, 위치로 숨김/표시) ═══ -->
  <div v-if="music.hasTrack || isMusicPage"
    class="fixed z-[9998] w-[320px] bg-[#1a1a2e] rounded-2xl shadow-2xl border border-white/10 flex flex-col overflow-hidden transition-all duration-300"
    :style="playerStyle" style="max-height:75vh;">

    <!-- 헤더 -->
    <div @mousedown="startDrag" @touchstart.passive="startDrag"
      class="px-3 py-2 flex items-center justify-between cursor-move bg-gradient-to-r from-indigo-700 to-purple-700 select-none flex-shrink-0">
      <div class="flex items-center gap-2 flex-1 min-w-0">
        <span class="text-sm">🎵</span>
        <p class="text-white text-xs font-bold truncate">{{ music.currentTrack?.title || '재생 대기 중' }}</p>
      </div>
      <div class="flex items-center gap-0.5 flex-shrink-0">
        <button @click.stop="isExpanded = false" class="w-6 h-6 rounded hover:bg-white/20 text-white/70 hover:text-white flex items-center justify-center text-sm" title="최소화">−</button>
        <button @click.stop="closePlayer" class="w-6 h-6 rounded hover:bg-white/20 text-white/70 hover:text-white flex items-center justify-center text-xs" title="닫기">✕</button>
      </div>
    </div>

    <!-- YouTube 영상 -->
    <div class="aspect-video bg-black flex-shrink-0">
      <div id="yt-mini-player" class="w-full h-full"></div>
    </div>

    <!-- 컨트롤 -->
    <div class="px-3 py-2 flex items-center gap-2 flex-shrink-0">
      <button @click="doPrev" class="w-7 h-7 rounded-full text-gray-400 hover:text-white flex items-center justify-center text-xs">⏮</button>
      <button @click="togglePlay" class="w-9 h-9 rounded-full bg-indigo-600 text-white flex items-center justify-center hover:bg-indigo-500 text-sm">{{ music.isPlaying ? '⏸' : '▶' }}</button>
      <button @click="doNext" class="w-7 h-7 rounded-full text-gray-400 hover:text-white flex items-center justify-center text-xs">⏭</button>
      <div class="flex-1 h-1 bg-gray-700 rounded-full mx-1 cursor-pointer" @click="seekTo">
        <div class="h-full bg-indigo-500 rounded-full transition-all" :style="{ width: music.progress + '%' }"></div>
      </div>
      <span class="text-gray-500 text-[10px]">🔊</span>
      <input type="range" min="0" max="100" v-model="volume" @input="setVolume"
        class="w-12 h-1 accent-indigo-500" style="appearance:auto;" />
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
let ytPlayer = null
let progressTimer = null
let currentVideoId = null

const isShortsPage = computed(() => route.path.startsWith('/shorts'))
const isMusicPage = computed(() => route.path.startsWith('/music'))
const hideUI = computed(() => isShortsPage.value)

const posRight = ref(16)
const posTop = ref(170)

function calcMusicPageRight() {
  const maxW = 1280
  const viewW = window.innerWidth
  return Math.max((viewW - maxW) / 2, 16)
}

// 핵심: 펼침/숨김을 위치로 제어 (display:none 안 씀 → YouTube 안 죽음)
const playerStyle = computed(() => {
  if (!isExpanded.value) {
    return { right: '-9999px', top: '-9999px', opacity: 0, pointerEvents: 'none' }
  }
  return { right: posRight.value + 'px', top: posTop.value + 'px', opacity: 1, pointerEvents: 'auto' }
})

const miniStyle = computed(() => {
  if (isMusicPage.value) return { right: calcMusicPageRight() + 'px', top: '170px' }
  return { right: '16px', bottom: '80px' }
})

let dragging = false
let dragStart = { x: 0, y: 0 }
let posStart = { right: 0, top: 0 }

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

// YouTube IFrame API
function loadYTApi() {
  if (window.YT?.Player) return Promise.resolve()
  return new Promise(resolve => {
    if (document.getElementById('yt-api-script')) {
      const check = setInterval(() => { if (window.YT?.Player) { clearInterval(check); resolve() } }, 100)
      return
    }
    const tag = document.createElement('script')
    tag.id = 'yt-api-script'
    tag.src = 'https://www.youtube.com/iframe_api'
    document.head.appendChild(tag)
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
    width: '100%', height: '100%',
    videoId,
    playerVars: { autoplay: 1, controls: 1, modestbranding: 1, rel: 0, playsinline: 1 },
    events: {
      onReady: (e) => {
        e.target.setVolume(volume.value)
        if (startAt > 0) e.target.seekTo(startAt, true)
        e.target.playVideo()
        currentVideoId = videoId
        music.isPlaying = true
        startProgressTimer()
      },
      onStateChange: (e) => {
        if (e.data === window.YT.PlayerState.ENDED) {
          music.next()
          nextTick(() => { if (music.currentTrack?.youtubeId) loadVideo(music.currentTrack.youtubeId) })
        }
        if (e.data === window.YT.PlayerState.PLAYING) music.isPlaying = true
        if (e.data === window.YT.PlayerState.PAUSED) music.isPlaying = false
      },
      onError: () => { setTimeout(() => { music.next() }, 1000) }
    }
  })
}

function loadVideo(videoId, startAt = 0) {
  try {
    if (ytPlayer?.loadVideoById && ytPlayer?.getPlayerState) {
      ytPlayer.loadVideoById({ videoId, startSeconds: startAt })
      currentVideoId = videoId
      return
    }
  } catch {}
  createPlayer(videoId, startAt)
}

function expand() {
  isExpanded.value = true
  if (isMusicPage.value) { posRight.value = calcMusicPageRight(); posTop.value = 170 }
  // Player 복구
  if (music.currentTrack?.youtubeId && music.isPlaying) {
    nextTick(() => {
      try { ytPlayer?.getPlayerState(); ytPlayer?.playVideo() } catch { createPlayer(music.currentTrack.youtubeId, music.currentTime || 0) }
    })
  }
}

function closePlayer() {
  // ✕ = 일시정지 + 숨김 (hasTrack 유지 → 🎵 버튼 남음)
  try { ytPlayer?.pauseVideo() } catch {}
  music.pause()
  isExpanded.value = false
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
  const pct = (e.clientX - rect.left) / rect.width
  ytPlayer.seekTo(pct * ytPlayer.getDuration(), true)
}
function setVolume() { try { ytPlayer?.setVolume(volume.value) } catch {} }

function startProgressTimer() {
  if (progressTimer) clearInterval(progressTimer)
  progressTimer = setInterval(() => {
    try {
      if (!ytPlayer?.getCurrentTime || !ytPlayer?.getDuration) return
      const cur = ytPlayer.getCurrentTime()
      const dur = ytPlayer.getDuration()
      if (dur > 0) music.setProgress((cur / dur) * 100, cur, dur)
    } catch {}
  }, 1000)
}

// 음악 페이지 진입 → 펼침
watch(isMusicPage, (isMp) => {
  if (isMp) {
    isExpanded.value = true
    posRight.value = calcMusicPageRight()
    posTop.value = 170
  }
})

// 음악 페이지 떠남 → 최소화
watch(isMusicPage, (isMp, wasMp) => {
  if (!isMp && wasMp) isExpanded.value = false
})

// 곡 변경
watch(() => music.currentTrack?.youtubeId, (vid) => {
  if (!vid) return
  isExpanded.value = true
  if (isMusicPage.value) { posRight.value = calcMusicPageRight(); posTop.value = 170 }
  else { posRight.value = 16; posTop.value = Math.max(window.innerHeight - 550, 80) }
  nextTick(() => loadVideo(vid, 0))
})

watch(isShortsPage, (isShorts) => {
  if (isShorts) { try { ytPlayer?.pauseVideo() } catch {}; music.isPlaying = false }
})

onMounted(() => {
  if (isMusicPage.value) {
    isExpanded.value = true
    posRight.value = calcMusicPageRight()
    posTop.value = 170
  }
  if (music.currentTrack?.youtubeId) {
    isExpanded.value = true
    nextTick(() => createPlayer(music.currentTrack.youtubeId, music.currentTime || 0))
  }
})
onUnmounted(() => { if (progressTimer) clearInterval(progressTimer) })
</script>

<style scoped>
@keyframes pulse-slow { 0%,100%{box-shadow:0 0 0 0 rgba(99,102,241,.4)} 50%{box-shadow:0 0 0 8px rgba(99,102,241,0)} }
.animate-pulse-slow { animation: pulse-slow 2s infinite; }
input[type="range"] { height: 4px; }
.music-scroll::-webkit-scrollbar { width: 4px; }
.music-scroll::-webkit-scrollbar-thumb { background: #4338ca; border-radius: 2px; }
</style>
