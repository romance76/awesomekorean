<template>
<div v-if="music.hasTrack" ref="playerContainer"
  class="fixed z-[9998] transition-shadow"
  :style="{ left: pos.x + 'px', top: pos.y + 'px' }"
  :class="hideUI ? 'w-0 h-0 overflow-hidden' : ''">

  <!-- ═══ 최소화 상태: 플로팅 버튼 ═══ -->
  <div v-if="state === 'mini' && !hideUI" @click="state = 'normal'"
    class="w-14 h-14 rounded-full bg-gradient-to-br from-indigo-600 to-purple-600 shadow-xl flex items-center justify-center cursor-pointer hover:scale-110 transition-all animate-pulse-slow relative">
    <span class="text-white text-xl">{{ music.isPlaying ? '🎵' : '⏸' }}</span>
    <svg class="absolute inset-0 w-14 h-14 -rotate-90 pointer-events-none" viewBox="0 0 56 56">
      <circle cx="28" cy="28" r="26" fill="none" stroke="rgba(255,255,255,0.15)" stroke-width="2" />
      <circle cx="28" cy="28" r="26" fill="none" stroke="#a78bfa" stroke-width="2.5"
        :stroke-dasharray="163" :stroke-dashoffset="163 - (163 * music.progress / 100)" stroke-linecap="round" />
    </svg>
  </div>

  <!-- ═══ 일반 상태: 영상 + 컨트롤 + 플레이리스트 ═══ -->
  <div v-else-if="state === 'normal' && !hideUI"
    class="w-[340px] bg-[#1a1a2e] rounded-2xl shadow-2xl overflow-hidden border border-white/10 flex flex-col"
    style="max-height: 80vh;">

    <!-- 드래그 핸들 + 헤더 -->
    <div @mousedown="startDrag" @touchstart.passive="startDrag"
      class="px-3 py-2 flex items-center justify-between cursor-move bg-gradient-to-r from-indigo-700 to-purple-700 select-none flex-shrink-0">
      <div class="flex items-center gap-2 flex-1 min-w-0">
        <span class="text-white text-sm">🎵</span>
        <p class="text-white text-xs font-bold truncate">{{ music.currentTrack?.title }}</p>
      </div>
      <div class="flex items-center gap-0.5 flex-shrink-0">
        <button @click.stop="state = 'mini'" class="w-6 h-6 rounded hover:bg-white/20 text-white/70 hover:text-white flex items-center justify-center text-xs" title="최소화">−</button>
        <button @click.stop="music.stop(); state = 'mini'" class="w-6 h-6 rounded hover:bg-white/20 text-white/70 hover:text-white flex items-center justify-center text-xs" title="닫기">✕</button>
      </div>
    </div>

    <!-- YouTube 영상 -->
    <div class="aspect-video bg-black flex-shrink-0">
      <div ref="ytPlayerEl" id="yt-mini-player" class="w-full h-full"></div>
    </div>

    <!-- 진행 바 -->
    <div class="h-1 bg-gray-800 flex-shrink-0">
      <div class="h-full bg-gradient-to-r from-indigo-500 to-purple-500 transition-all" :style="{ width: music.progress + '%' }"></div>
    </div>

    <!-- 컨트롤 -->
    <div class="px-3 py-2 flex items-center justify-between flex-shrink-0">
      <p class="text-gray-400 text-[10px]">{{ music.currentTrack?.artist }}</p>
      <div class="flex items-center gap-1">
        <button @click="music.prev()" class="w-7 h-7 rounded-full text-gray-400 hover:text-white flex items-center justify-center text-xs">⏮</button>
        <button @click="music.toggle()" class="w-9 h-9 rounded-full bg-indigo-600 text-white flex items-center justify-center hover:bg-indigo-500">{{ music.isPlaying ? '⏸' : '▶' }}</button>
        <button @click="music.next()" class="w-7 h-7 rounded-full text-gray-400 hover:text-white flex items-center justify-center text-xs">⏭</button>
      </div>
      <div class="flex items-center gap-1">
        <span class="text-gray-500 text-[10px]">🔊</span>
        <input type="range" min="0" max="100" v-model="volume" @input="setVolume" @click.stop
          class="w-16 h-1 accent-indigo-500" style="appearance:auto;" />
      </div>
    </div>

    <!-- 플레이리스트 -->
    <div v-if="music.playlist.length" class="border-t border-white/10 flex-shrink-0">
      <button @click="showPlaylist = !showPlaylist" class="w-full px-3 py-1.5 text-[10px] text-gray-500 hover:text-gray-300 flex items-center justify-between">
        <span>다음 곡 ({{ music.playlist.length }}곡)</span>
        <span>{{ showPlaylist ? '▲' : '▼' }}</span>
      </button>
    </div>
    <div v-if="showPlaylist && music.playlist.length" class="overflow-y-auto max-h-[200px] px-1 pb-1">
      <div v-for="(track, idx) in music.playlist" :key="track.id || idx"
        @click="music.play(track); initPlayer(track.youtubeId, 0)"
        class="flex items-center gap-2 px-2 py-1.5 rounded-lg cursor-pointer transition"
        :class="music.currentIndex === idx ? 'bg-indigo-900/50' : 'hover:bg-white/5'">
        <span class="text-gray-500 text-[10px] w-4 text-right">{{ idx + 1 }}</span>
        <div class="flex-1 min-w-0">
          <p class="text-xs truncate" :class="music.currentIndex === idx ? 'text-indigo-400 font-semibold' : 'text-gray-300'">{{ track.title }}</p>
        </div>
        <span v-if="music.currentIndex === idx && music.isPlaying" class="text-indigo-400 text-xs">♪</span>
      </div>
    </div>
  </div>
</div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { useRoute } from 'vue-router'
import { useMusicStore } from '../stores/music'

const music = useMusicStore()
const route = useRoute()
const state = ref('mini')
const volume = ref(80)
const showPlaylist = ref(false)
const ytPlayerEl = ref(null)
const playerContainer = ref(null)
let ytPlayer = null
let progressTimer = null
let currentVideoId = null

// 드래그 위치
const pos = ref({ x: window.innerWidth - 360, y: window.innerHeight - 100 })
let dragging = false
let dragStart = { x: 0, y: 0 }
let posStart = { x: 0, y: 0 }

const isShortsPage = computed(() => route.path.startsWith('/shorts'))
const hideUI = computed(() => isShortsPage.value)

function startDrag(e) {
  dragging = true
  const ev = e.touches ? e.touches[0] : e
  dragStart = { x: ev.clientX, y: ev.clientY }
  posStart = { ...pos.value }
  document.addEventListener('mousemove', onDrag)
  document.addEventListener('mouseup', stopDrag)
  document.addEventListener('touchmove', onDrag, { passive: false })
  document.addEventListener('touchend', stopDrag)
}

function onDrag(e) {
  if (!dragging) return
  const ev = e.touches ? e.touches[0] : e
  pos.value = {
    x: Math.max(0, Math.min(window.innerWidth - 100, posStart.x + ev.clientX - dragStart.x)),
    y: Math.max(0, Math.min(window.innerHeight - 100, posStart.y + ev.clientY - dragStart.y)),
  }
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
    if (document.getElementById('yt-api-script')) { window.onYouTubeIframeAPIReady = resolve; return }
    const tag = document.createElement('script')
    tag.id = 'yt-api-script'
    tag.src = 'https://www.youtube.com/iframe_api'
    document.head.appendChild(tag)
    window.onYouTubeIframeAPIReady = resolve
  })
}

async function initPlayer(videoId, startAt = 0) {
  if (!videoId) return
  await loadYTApi()
  if (ytPlayer && ytPlayer.loadVideoById) {
    if (currentVideoId === videoId) { if (startAt > 0) ytPlayer.seekTo(startAt, true); return }
    ytPlayer.loadVideoById({ videoId, startSeconds: startAt })
    currentVideoId = videoId
    return
  }
  // 엘리먼트 확인
  const el = document.getElementById('yt-mini-player')
  if (!el) { setTimeout(() => initPlayer(videoId, startAt), 300); return }
  ytPlayer = new window.YT.Player('yt-mini-player', {
    width: '100%', height: '100%',
    videoId,
    playerVars: { autoplay: 1, controls: 1, modestbranding: 1, rel: 0, start: Math.floor(startAt) },
    events: {
      onReady: (e) => { e.target.setVolume(volume.value); currentVideoId = videoId },
      onStateChange: (e) => {
        if (e.data === window.YT.PlayerState.ENDED) music.next()
        if (e.data === window.YT.PlayerState.PLAYING) music.isPlaying = true
        if (e.data === window.YT.PlayerState.PAUSED) music.isPlaying = false
      }
    }
  })
}

function startProgressTimer() {
  if (progressTimer) return
  progressTimer = setInterval(() => {
    if (!ytPlayer?.getCurrentTime || !ytPlayer?.getDuration) return
    const cur = ytPlayer.getCurrentTime()
    const dur = ytPlayer.getDuration()
    if (dur > 0) music.setProgress((cur / dur) * 100, cur, dur)
  }, 1000)
}

watch(() => music.currentTrack?.youtubeId, async (vid) => {
  if (!vid) { if (ytPlayer?.stopVideo) ytPlayer.stopVideo(); return }
  if (state.value === 'mini') state.value = 'normal'
  await initPlayer(vid, 0)
  startProgressTimer()
}, { immediate: false })

watch(() => music.isPlaying, (playing) => {
  if (!ytPlayer?.playVideo) return
  if (playing) ytPlayer.playVideo()
  else ytPlayer.pauseVideo()
})

function setVolume() {
  if (ytPlayer?.setVolume) ytPlayer.setVolume(volume.value)
}

watch(isShortsPage, (isShorts) => {
  if (isShorts && music.isPlaying) music.pause()
})

// 윈도우 리사이즈 시 위치 보정
function onResize() {
  pos.value = {
    x: Math.min(pos.value.x, window.innerWidth - 60),
    y: Math.min(pos.value.y, window.innerHeight - 60),
  }
}

onMounted(() => {
  window.addEventListener('resize', onResize)
  pos.value = { x: window.innerWidth - 360, y: window.innerHeight - 100 }
  if (music.currentTrack?.youtubeId) {
    state.value = 'normal'
    setTimeout(() => {
      initPlayer(music.currentTrack.youtubeId, music.currentTime || 0)
      startProgressTimer()
    }, 300)
  }
})

onUnmounted(() => {
  if (progressTimer) clearInterval(progressTimer)
  window.removeEventListener('resize', onResize)
})
</script>

<style scoped>
@keyframes pulse-slow {
  0%, 100% { box-shadow: 0 0 0 0 rgba(99,102,241,0.4); }
  50% { box-shadow: 0 0 0 8px rgba(99,102,241,0); }
}
.animate-pulse-slow { animation: pulse-slow 2s infinite; }
input[type="range"] { height: 4px; }
</style>
