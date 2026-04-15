<template>
<Teleport to="body">
  <!-- ═══ 최소화 버튼 (닫힌 상태) ═══ -->
  <div v-if="music.hasTrack && !hideUI && !isExpanded"
    class="fixed z-[9998] cursor-pointer hover:scale-110 transition-all animate-pulse-slow"
    :style="miniStyle"
    @click="expandAndRestore">
    <div class="w-14 h-14 rounded-full bg-gradient-to-br from-indigo-600 to-purple-600 shadow-xl flex items-center justify-center relative">
      <span class="text-white text-xl">{{ music.isPlaying ? '🎵' : '⏸' }}</span>
      <svg class="absolute inset-0 w-14 h-14 -rotate-90 pointer-events-none" viewBox="0 0 56 56">
        <circle cx="28" cy="28" r="26" fill="none" stroke="rgba(255,255,255,0.15)" stroke-width="2" />
        <circle cx="28" cy="28" r="26" fill="none" stroke="#a78bfa" stroke-width="2.5"
          :stroke-dasharray="163" :stroke-dashoffset="163 - (163 * music.progress / 100)" stroke-linecap="round" />
      </svg>
    </div>
  </div>

  <!-- ═══ 펼친 플레이어 (v-show로 최소화해도 YouTube 유지) ═══ -->
  <div v-show="(music.hasTrack || isMusicPage) && !hideUI && isExpanded"
    class="fixed z-[9998] w-[320px] bg-[#1a1a2e] rounded-2xl shadow-2xl border border-white/10 flex flex-col overflow-hidden"
    :style="expandedStyle" style="max-height:75vh;">

    <!-- 드래그 헤더 -->
    <div @mousedown="startDrag" @touchstart.passive="startDrag"
      class="px-3 py-2 flex items-center justify-between cursor-move bg-gradient-to-r from-indigo-700 to-purple-700 select-none flex-shrink-0">
      <div class="flex items-center gap-2 flex-1 min-w-0">
        <span class="text-sm">🎵</span>
        <p class="text-white text-xs font-bold truncate">{{ music.currentTrack?.title || '재생 대기 중' }}</p>
      </div>
      <div class="flex items-center gap-0.5 flex-shrink-0">
        <button @click.stop="isExpanded = false" class="w-6 h-6 rounded hover:bg-white/20 text-white/70 hover:text-white flex items-center justify-center text-sm" title="최소화">−</button>
        <button @click.stop="closePlayer" class="w-6 h-6 rounded hover:bg-white/20 text-white/70 hover:text-white flex items-center justify-center text-xs" title="숨기기">✕</button>
        <button @click.stop="stopCompletely" class="w-6 h-6 rounded hover:bg-red-500/50 text-white/70 hover:text-white flex items-center justify-center text-[10px]" title="완전 종료">⏹</button>
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

// 위치 — right/top 기반 (드래그로 변경 가능)
// 음악 페이지: 콘텐츠 영역 오른쪽에 맞춤
function calcMusicPageRight() {
  const maxW = 1280 // max-w-7xl
  const viewW = window.innerWidth
  const margin = Math.max((viewW - maxW) / 2, 16) // 중앙 정렬 마진
  return margin // 콘텐츠 영역의 오른쪽 끝
}
const posRight = ref(16)
const posTop = ref(80)
let dragging = false
let dragStart = { x: 0, y: 0 }
let posStart = { right: 0, top: 0 }

// 음악 페이지면 오른쪽 상단, 다른 페이지면 하단 오른쪽
const expandedStyle = computed(() => ({
  right: posRight.value + 'px',
  top: posTop.value + 'px',
}))

const miniStyle = computed(() => {
  if (isMusicPage.value) return { right: calcMusicPageRight() + 'px', top: '170px' }
  return { right: '16px', bottom: '80px' }
})

// 음악 페이지 진입 → 자동 펼침 + Player 복구
watch(isMusicPage, (isMp) => {
  if (isMp) {
    isExpanded.value = true
    posRight.value = calcMusicPageRight()
    posTop.value = 170
    // Player가 죽었으면 재생성
    if (music.currentTrack?.youtubeId) {
      nextTick(() => {
        if (!ytPlayer || !ytPlayer.getPlayerState) {
          createPlayer(music.currentTrack.youtubeId, music.currentTime || 0)
        }
      })
    }
  }
})

// 음악 페이지 떠남 → 최소화
watch(isMusicPage, (isMp, wasMp) => {
  if (!isMp && wasMp) {
    isExpanded.value = false
  }
})

// 곡 처음 재생 시 자동 펼침
watch(() => music.currentTrack?.youtubeId, (vid) => {
  if (!vid) return
  isExpanded.value = true
  if (isMusicPage.value) { posRight.value = calcMusicPageRight(); posTop.value = 170 }
  else { posRight.value = 16; posTop.value = Math.max(window.innerHeight - 550, 80) }

  nextTick(() => {
    try {
      if (ytPlayer?.loadVideoById && ytPlayer?.getPlayerState) {
        ytPlayer.loadVideoById({ videoId: vid, startSeconds: 0 })
        currentVideoId = vid
        return
      }
    } catch {}
    // Player 없거나 에러 → 재생성
    createPlayer(vid, 0)
  })
})

// 드래그
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
          nextTick(() => { if (music.currentTrack?.youtubeId) { ytPlayer.loadVideoById({ videoId: music.currentTrack.youtubeId }); currentVideoId = music.currentTrack.youtubeId } })
        }
        if (e.data === window.YT.PlayerState.PLAYING) music.isPlaying = true
        if (e.data === window.YT.PlayerState.PAUSED) music.isPlaying = false
      },
      onError: () => { setTimeout(() => { music.next() }, 1000) }
    }
  })
}

function togglePlay() {
  if (!ytPlayer) return
  try {
    const st = ytPlayer.getPlayerState()
    if (st === 1) ytPlayer.pauseVideo()
    else ytPlayer.playVideo()
  } catch {}
}

function doPrev() {
  music.prev()
  nextTick(() => { if (music.currentTrack?.youtubeId && ytPlayer?.loadVideoById) { ytPlayer.loadVideoById({ videoId: music.currentTrack.youtubeId }); currentVideoId = music.currentTrack.youtubeId } })
}
function doNext() {
  music.next()
  nextTick(() => { if (music.currentTrack?.youtubeId && ytPlayer?.loadVideoById) { ytPlayer.loadVideoById({ videoId: music.currentTrack.youtubeId }); currentVideoId = music.currentTrack.youtubeId } })
}
function playFromList(track) {
  music.play(track)
  if (ytPlayer?.loadVideoById) { ytPlayer.loadVideoById({ videoId: track.youtubeId }); currentVideoId = track.youtubeId }
  else createPlayer(track.youtubeId, 0)
}
function seekTo(e) {
  if (!ytPlayer?.getDuration) return
  const rect = e.currentTarget.getBoundingClientRect()
  const pct = (e.clientX - rect.left) / rect.width
  ytPlayer.seekTo(pct * ytPlayer.getDuration(), true)
}
function expandAndRestore() {
  isExpanded.value = true
  // Player가 죽었으면 재생성
  if (music.currentTrack?.youtubeId) {
    nextTick(() => {
      try { if (ytPlayer?.getPlayerState) return } catch {}
      // Player 없거나 에러 → 재생성
      createPlayer(music.currentTrack.youtubeId, music.currentTime || 0)
    })
  }
}

function closePlayer() {
  // ✕ = 일시정지 + 숨김 (🎵 버튼은 유지)
  if (ytPlayer?.pauseVideo) try { ytPlayer.pauseVideo() } catch {}
  music.pause()
  isExpanded.value = false
}

function stopCompletely() {
  // ⏹ = 완전 종료 (플레이어 완전히 사라짐)
  if (ytPlayer?.stopVideo) try { ytPlayer.stopVideo() } catch {}
  if (ytPlayer?.destroy) try { ytPlayer.destroy() } catch {}
  ytPlayer = null
  currentVideoId = null
  music.stop()
  isExpanded.value = false
}
function setVolume() { if (ytPlayer?.setVolume) ytPlayer.setVolume(volume.value) }

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

onMounted(() => {
  // 음악 페이지면 항상 펼침 (곡 선택 전에도)
  if (isMusicPage.value) {
    isExpanded.value = true
    posRight.value = calcMusicPageRight()
    posTop.value = 170
  }
  if (music.currentTrack?.youtubeId) {
    isExpanded.value = true
    if (!isMusicPage.value) { posRight.value = 16; posTop.value = Math.max(window.innerHeight - 550, 80) }
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
