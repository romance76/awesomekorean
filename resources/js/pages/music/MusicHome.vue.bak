<template>
  <div class="min-h-screen bg-gray-900 text-white">
    <!-- Category Tabs -->
    <div class="bg-gray-800 border-b border-gray-700 sticky top-0 z-20">
      <div class="max-w-6xl mx-auto px-4">
        <div class="flex items-center gap-2 py-3 overflow-x-auto scrollbar-hide">
          <span class="text-lg flex-shrink-0 mr-1">🎵</span>
          <button v-for="cat in categories" :key="cat.id" @click="selectCategory(cat)"
            :class="['flex-shrink-0 px-4 py-1.5 rounded-full text-sm font-medium transition-all',
              selectedCat?.id === cat.id ? 'bg-purple-600 text-white' : 'bg-gray-700 text-gray-300 hover:bg-gray-600']">
            {{ cat.icon }} {{ cat.name }}
          </button>
          <button v-if="authStore.isLoggedIn" @click="showMyPlaylists = !showMyPlaylists"
            :class="['flex-shrink-0 px-4 py-1.5 rounded-full text-sm font-medium transition-all border',
              showMyPlaylists ? 'bg-pink-600 text-white border-pink-500' : 'border-gray-600 text-gray-400 hover:border-gray-500']">
            ⭐ 내 뮤직함
          </button>
        </div>
      </div>
    </div>

    <div class="max-w-6xl mx-auto px-4 py-4">
      <!-- My Playlists Panel -->
      <div v-if="showMyPlaylists && authStore.isLoggedIn" class="mb-4">
        <div class="bg-gray-800 rounded-2xl p-4">
          <div class="flex items-center justify-between mb-3">
            <h3 class="font-bold text-white">내 플레이리스트</h3>
            <button @click="showCreatePlaylist = true" class="px-3 py-1 bg-purple-600 hover:bg-purple-700 text-white rounded-lg text-sm">+ 새 목록</button>
          </div>
          <div v-if="playlists.length === 0" class="text-gray-400 text-sm py-2">아직 플레이리스트가 없어요.</div>
          <div v-else class="flex gap-2 overflow-x-auto scrollbar-hide pb-1">
            <button v-for="pl in playlists" :key="pl.id" @click="openPlaylist(pl)"
              :class="['flex-shrink-0 flex items-center gap-2 px-3 py-2 rounded-xl text-sm transition-all',
                activePlaylist?.id === pl.id ? 'bg-purple-600 text-white' : 'bg-gray-700 text-gray-300 hover:bg-gray-600']">
              <span>🎵</span>
              <span class="max-w-32 truncate">{{ pl.name }}</span>
              <span class="text-xs opacity-70">({{ pl.tracks_count }})</span>
              <button @click.stop="deletePlaylist(pl.id)" class="ml-1 opacity-60 hover:opacity-100 hover:text-red-400">×</button>
            </button>
          </div>
        </div>
      </div>

      <div class="flex flex-col lg:flex-row gap-4">
        <!-- Left: Player -->
        <div class="lg:w-80 xl:w-96 flex-shrink-0">
          <div class="bg-gray-800 rounded-2xl overflow-hidden shadow-2xl">
            <div v-if="currentTrack">
              <div class="relative" style="padding-top: 56.25%">
                <div id="yt-player" class="absolute inset-0 w-full h-full"></div>
              </div>
              <div class="p-4">
                <div class="font-bold text-white leading-snug mb-1 line-clamp-2 text-sm">{{ currentTrack.title }}</div>
                <div class="text-xs text-gray-400 mb-3">{{ currentTrack.artist || '' }}</div>
                <div class="flex items-center justify-between">
                  <button @click="toggleShuffle"
                    :class="['px-3 py-1.5 rounded-full text-xs font-medium transition-all',
                      isShuffle ? 'bg-purple-600 text-white' : 'bg-gray-700 text-gray-400 hover:bg-gray-600']">
                    🔀 셔플
                  </button>
                  <button @click="prevTrack" class="p-2 text-gray-400 hover:text-white">
                    <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M6 6h2v12H6zm3.5 6l8.5 6V6z"/></svg>
                  </button>
                  <button @click="togglePlay" class="p-3 bg-white text-gray-900 rounded-full hover:bg-gray-200">
                    <svg v-if="isPlaying" class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/></svg>
                    <svg v-else class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                  </button>
                  <button @click="nextTrack" class="p-2 text-gray-400 hover:text-white">
                    <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M6 18l8.5-6L6 6v12zM16 6v12h2V6h-2z"/></svg>
                  </button>
                  <button @click="toggleRepeat"
                    :class="['px-3 py-1.5 rounded-full text-xs font-medium transition-all',
                      isRepeat ? 'bg-purple-600 text-white' : 'bg-gray-700 text-gray-400 hover:bg-gray-600']">
                    🔁 반복
                  </button>
                </div>
                <div class="text-center text-xs text-gray-600 mt-2">{{ currentIndex + 1 }} / {{ currentPlaylist.length }}</div>
              </div>
            </div>
            <div v-else class="flex flex-col items-center justify-center py-16 text-gray-500">
              <div class="text-5xl mb-3">🎵</div>
              <p class="text-sm">곡을 선택하거나 전체재생을 누르세요</p>
            </div>
          </div>
        </div>

        <!-- Right: Track List -->
        <div class="flex-1 min-w-0">
          <div class="bg-gray-800 rounded-2xl overflow-hidden">
            <!-- Header -->
            <div class="px-4 py-3 border-b border-gray-700">
              <div class="flex items-center justify-between mb-2">
                <h3 class="font-bold text-white">
                  {{ activePlaylist ? activePlaylist.name : (selectedCat ? selectedCat.name + ' 곡 목록' : '곡 목록') }}
                </h3>
                <div class="flex items-center gap-2">
                  <span class="text-sm text-gray-400">{{ currentPlaylist.length }}곡</span>
                  <button v-if="activePlaylist" @click="showAddToPlaylist = true" class="px-2 py-1 bg-purple-600 text-white rounded-lg text-xs hover:bg-purple-700">+ 추가</button>
                  <button v-if="activePlaylist" @click="activePlaylist = null; currentIndex = -1" class="px-2 py-1 bg-gray-700 text-gray-300 rounded-lg text-xs hover:bg-gray-600">← 카테고리</button>
                </div>
              </div>
              <!-- Play control buttons -->
              <div class="flex flex-wrap gap-2">
                <button @click="playAll" class="flex items-center gap-1.5 px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-xl text-sm font-medium">
                  <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                  전체재생
                </button>
                <button @click="playShuffleAll" class="flex items-center gap-1.5 px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-xl text-sm font-medium">
                  <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M10.59 9.17L5.41 4 4 5.41l5.17 5.17 1.42-1.41zM14.5 4l2.04 2.04L4 18.59 5.41 20 17.96 7.46 20 9.5V4h-5.5zm.33 9.41l-1.41 1.41 3.13 3.13L14.5 20H20v-5.5l-2.04 2.04-3.13-3.13z"/></svg>
                  랜덤재생
                </button>
                <button v-if="authStore.isLoggedIn" @click="showAddAllToPlaylist = true" class="flex items-center gap-1.5 px-4 py-2 bg-gray-700 hover:bg-gray-600 text-gray-300 rounded-xl text-sm font-medium">
                  <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
                  전체 담기
                </button>
              </div>
            </div>
            <!-- Track rows -->
            <div v-if="tracksLoading" class="flex justify-center py-12">
              <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-purple-500"></div>
            </div>
            <div v-else-if="currentPlaylist.length === 0" class="text-center py-12 text-gray-500">
              <div class="text-3xl mb-2">🎵</div>
              <p class="text-sm">아직 곡이 없습니다</p>
            </div>
            <div v-else class="divide-y divide-gray-700/50">
              <div v-for="(track, index) in currentPlaylist" :key="track.id"
                :class="['flex items-center gap-3 px-4 py-2.5 hover:bg-gray-700/50 transition-all',
                  currentIndex === index ? 'bg-purple-900/40 border-l-2 border-purple-500' : '']">
                <!-- Thumbnail -->
                <div @click="playTrack(track, index)" class="relative flex-shrink-0 w-14 h-10 rounded-lg overflow-hidden bg-gray-700 cursor-pointer">
                  <img v-if="track.thumbnail" :src="track.thumbnail" :alt="track.title" class="w-full h-full object-cover">
                  <div v-else class="w-full h-full flex items-center justify-center text-gray-500">♪</div>
                  <div v-if="currentIndex === index && isPlaying" class="absolute inset-0 bg-purple-600/60 flex items-center justify-center">
                    <div class="flex items-end gap-0.5 h-5">
                      <div v-for="b in 4" :key="b" class="w-0.5 bg-white rounded-full animate-bar" :style="`animation-delay:${b*0.15}s`"></div>
                    </div>
                  </div>
                  <div v-else-if="currentIndex === index" class="absolute inset-0 bg-purple-600/60 flex items-center justify-center">
                    <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                  </div>
                </div>
                <!-- Info -->
                <div @click="playTrack(track, index)" class="flex-1 min-w-0 cursor-pointer">
                  <div :class="['text-sm font-medium truncate', currentIndex === index ? 'text-purple-300' : 'text-white']">{{ track.title }}</div>
                  <div class="text-xs text-gray-500 truncate mt-0.5">{{ track.artist || '아티스트' }}</div>
                </div>
                <!-- Actions -->
                <div class="flex items-center gap-1 flex-shrink-0 relative">
                  <!-- Add to playlist button -->
                  <button v-if="authStore.isLoggedIn" @click.stop="toggleAddMenu(track.id)"
                    class="p-1.5 text-gray-500 hover:text-purple-400 rounded-lg hover:bg-gray-700" title="플레이리스트에 추가">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
                  </button>
                  <!-- Dropdown -->
                  <div v-if="addMenuTrackId === track.id" class="absolute right-0 top-8 z-30 bg-gray-800 border border-gray-600 rounded-xl shadow-2xl py-1 min-w-48">
                    <div class="px-3 py-1.5 text-xs text-gray-400 border-b border-gray-700">플레이리스트에 추가</div>
                    <div v-if="playlists.length === 0" class="px-3 py-3 text-xs text-gray-500 text-center">
                      <p>플레이리스트가 없어요</p>
                      <button @click="showCreatePlaylist = true; addMenuTrackId = null" class="mt-1 text-purple-400">+ 새로 만들기</button>
                    </div>
                    <button v-for="pl in playlists" :key="pl.id" @click.stop="addTrackToSpecificPlaylist(track, pl)"
                      class="w-full text-left px-3 py-2 text-sm text-gray-200 hover:bg-gray-700 flex items-center gap-2">
                      <span class="text-xs">🎵</span>
                      <span class="truncate flex-1">{{ pl.name }}</span>
                      <span class="text-xs text-gray-500">({{ pl.tracks_count }})</span>
                    </button>
                    <button @click="pendingTrack = track; showCreatePlaylist = true; addMenuTrackId = null"
                      class="w-full text-left px-3 py-2 text-sm text-purple-400 hover:bg-gray-700 flex items-center gap-2">
                      <span>+</span> 새 목록에 추가
                    </button>
                  </div>
                  <!-- Number -->
                  <div class="w-6 text-right">
                    <span v-if="currentIndex !== index" class="text-xs text-gray-600">{{ index + 1 }}</span>
                    <span v-else class="text-xs text-purple-400">▶</span>
                  </div>
                  <!-- Remove from playlist -->
                  <button v-if="activePlaylist" @click.stop="removeTrack(track.id)" class="p-1.5 text-gray-600 hover:text-red-400">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Overlay to close dropdown -->
    <div v-if="addMenuTrackId" @click="addMenuTrackId = null" class="fixed inset-0 z-20"></div>

    <!-- Create Playlist Modal -->
    <div v-if="showCreatePlaylist" class="fixed inset-0 z-50 bg-black/70 flex items-center justify-center p-4">
      <div class="bg-gray-800 rounded-2xl w-full max-w-sm p-6 shadow-2xl">
        <h3 class="text-lg font-bold text-white mb-4">새 플레이리스트</h3>
        <input v-model="newPlaylistName" type="text" placeholder="이름" maxlength="100"
          @keyup.enter="createPlaylist"
          class="w-full bg-gray-700 border border-gray-600 rounded-xl px-4 py-3 text-white placeholder-gray-500 mb-4 focus:outline-none focus:ring-2 focus:ring-purple-500">
        <div class="flex gap-2">
          <button @click="showCreatePlaylist = false; pendingTrack = null" class="flex-1 py-3 bg-gray-700 hover:bg-gray-600 text-gray-300 rounded-xl">취소</button>
          <button @click="createPlaylist" :disabled="!newPlaylistName.trim()" class="flex-1 py-3 bg-purple-600 hover:bg-purple-700 disabled:opacity-40 text-white rounded-xl font-medium">만들기</button>
        </div>
      </div>
    </div>

    <!-- Add All to Playlist Modal -->
    <div v-if="showAddAllToPlaylist" class="fixed inset-0 z-50 bg-black/70 flex items-center justify-center p-4">
      <div class="bg-gray-800 rounded-2xl w-full max-w-sm p-6 shadow-2xl">
        <h3 class="text-lg font-bold text-white mb-2">전체 담기</h3>
        <p class="text-sm text-gray-400 mb-4">{{ currentPlaylist.length }}곡을 어느 플레이리스트에 담을까요?</p>
        <div v-if="playlists.length === 0" class="text-center py-4 text-gray-400 text-sm">
          <p>플레이리스트가 없어요</p>
          <button @click="showAddAllToPlaylist = false; showCreatePlaylist = true" class="mt-2 text-purple-400">+ 새로 만들기</button>
        </div>
        <div v-else class="space-y-2 mb-4 max-h-48 overflow-y-auto">
          <button v-for="pl in playlists" :key="pl.id" @click="addAllToPlaylist(pl)"
            class="w-full text-left px-4 py-3 bg-gray-700 hover:bg-gray-600 rounded-xl text-white text-sm flex items-center gap-3">
            <span>🎵</span><span class="flex-1 truncate">{{ pl.name }}</span>
            <span class="text-xs text-gray-400">({{ pl.tracks_count }}곡)</span>
          </button>
        </div>
        <button @click="showAddAllToPlaylist = false" class="w-full py-3 bg-gray-700 hover:bg-gray-600 text-gray-300 rounded-xl text-sm">취소</button>
      </div>
    </div>

    <!-- Add Track (playlist view) -->
    <div v-if="showAddToPlaylist && activePlaylist" class="fixed inset-0 z-50 bg-black/70 flex items-center justify-center p-4">
      <div class="bg-gray-800 rounded-2xl w-full max-w-sm p-6 shadow-2xl">
        <h3 class="text-lg font-bold text-white mb-1">곡 추가</h3>
        <p class="text-sm text-gray-400 mb-4">{{ activePlaylist.name }}</p>
        <input v-model="addTrackUrl" type="text" placeholder="YouTube URL"
          @keyup.enter="addTrackToPlaylist"
          class="w-full bg-gray-700 border border-gray-600 rounded-xl px-4 py-3 text-white placeholder-gray-500 mb-2 focus:outline-none focus:ring-2 focus:ring-purple-500">
        <div v-if="previewAddId" class="flex items-center gap-3 bg-gray-700 rounded-xl p-2 mb-4">
          <img :src="`https://img.youtube.com/vi/${previewAddId}/mqdefault.jpg`" class="w-16 h-11 rounded-lg object-cover">
          <span class="text-xs text-gray-300">미리보기</span>
        </div>
        <div v-else class="mb-2"></div>
        <div class="flex gap-2">
          <button @click="showAddToPlaylist = false; addTrackUrl = ''" class="flex-1 py-3 bg-gray-700 hover:bg-gray-600 text-gray-300 rounded-xl">취소</button>
          <button @click="addTrackToPlaylist" :disabled="!addTrackUrl.trim() || addingTrack" class="flex-1 py-3 bg-purple-600 hover:bg-purple-700 disabled:opacity-40 text-white rounded-xl font-medium">
            {{ addingTrack ? '추가 중...' : '추가' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue'
import axios from 'axios'
import { useAuthStore } from '@/stores/auth'
import { useMusicStore } from '@/stores/music'

const authStore = useAuthStore()
const musicStore = useMusicStore()
const categories = ref([])
const selectedCat = ref(null)
const tracks = ref([])
const tracksLoading = ref(false)
const currentTrack = ref(null)
const currentIndex = ref(-1)
const isPlaying = ref(false)
const isShuffle = ref(false)
const isRepeat = ref(false)
const showMyPlaylists = ref(false)
const playlists = ref([])
const activePlaylist = ref(null)
const showCreatePlaylist = ref(false)
const newPlaylistName = ref('')
const showAddToPlaylist = ref(false)
const addTrackUrl = ref('')
const addingTrack = ref(false)
const showAddAllToPlaylist = ref(false)
const addMenuTrackId = ref(null)
const pendingTrack = ref(null)

let ytPlayer = null
let ytApiReady = false
let pendingPlay = null

const currentPlaylist = computed(() => {
  if (activePlaylist.value?.tracks) return activePlaylist.value.tracks
  return tracks.value
})

const previewAddId = computed(() => {
  const m = addTrackUrl.value.match(/(?:youtube\.com\/(?:watch\?v=|embed\/|shorts\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/)
  return m ? m[1] : null
})

function loadYouTubeAPI() {
  if (window.YT && window.YT.Player) { ytApiReady = true; return }
  const tag = document.createElement('script')
  tag.src = 'https://www.youtube.com/iframe_api'
  document.head.appendChild(tag)
  window.onYouTubeIframeAPIReady = () => {
    ytApiReady = true
    if (pendingPlay) { createPlayer(pendingPlay); pendingPlay = null }
  }
}

function createPlayer(videoId) {
  if (ytPlayer) { ytPlayer.loadVideoById(videoId); return }
  if (!document.getElementById('yt-player')) return
  ytPlayer = new window.YT.Player('yt-player', {
    videoId,
    playerVars: { autoplay: 1, rel: 0, modestbranding: 1, playsinline: 1 },
    events: {
      onStateChange: onPlayerStateChange,
      onReady: (e) => e.target.playVideo()
    }
  })
}

function onPlayerStateChange(event) {
  const YT = window.YT
  if (event.data === YT.PlayerState.PLAYING) isPlaying.value = true
  else if (event.data === YT.PlayerState.PAUSED) isPlaying.value = false
  else if (event.data === YT.PlayerState.ENDED) {
    isPlaying.value = false
    if (isRepeat.value) playTrack(currentTrack.value, currentIndex.value)
    else if (isShuffle.value) {
      const idx = Math.floor(Math.random() * currentPlaylist.value.length)
      playTrack(currentPlaylist.value[idx], idx)
    } else nextTrack()
  }
}

function playTrack(track, index) {
  currentTrack.value = track
  currentIndex.value = index
  isPlaying.value = true
  try { axios.post(`/api/music/tracks/${track.id}/play`) } catch (e) {}
  // Sync to global music store for MiniPlayer
  musicStore.play({
    id: track.id,
    title: track.title,
    artist: track.artist || track.channel || "",
    thumbnail: track.thumbnail,
    youtubeId: track.youtube_id
  }, currentPlaylist.value.map(t => ({
    id: t.id,
    title: t.title,
    artist: t.artist || t.channel || "",
    thumbnail: t.thumbnail,
    youtubeId: t.youtube_id
  })))
  nextTick(() => {
    if (ytApiReady) createPlayer(track.youtube_id)
    else pendingPlay = track.youtube_id
  })
}

function playAll() {
  if (!currentPlaylist.value.length) return
  isShuffle.value = false
  playTrack(currentPlaylist.value[0], 0)
}

function playShuffleAll() {
  if (!currentPlaylist.value.length) return
  isShuffle.value = true
  const idx = Math.floor(Math.random() * currentPlaylist.value.length)
  playTrack(currentPlaylist.value[idx], idx)
}

function togglePlay() {
  if (!ytPlayer) return
  isPlaying.value ? ytPlayer.pauseVideo() : ytPlayer.playVideo()
}

function prevTrack() {
  const list = currentPlaylist.value
  if (!list.length) return
  const idx = currentIndex.value > 0 ? currentIndex.value - 1 : list.length - 1
  playTrack(list[idx], idx)
}

function nextTrack() {
  const list = currentPlaylist.value
  if (!list.length) return
  if (isShuffle.value) {
    const idx = Math.floor(Math.random() * list.length)
    playTrack(list[idx], idx)
    return
  }
  const idx = currentIndex.value < list.length - 1 ? currentIndex.value + 1 : 0
  playTrack(list[idx], idx)
}

function toggleShuffle() { isShuffle.value = !isShuffle.value }
function toggleRepeat() { isRepeat.value = !isRepeat.value }

function toggleAddMenu(trackId) {
  if (addMenuTrackId.value === trackId) { addMenuTrackId.value = null; return }
  if (authStore.isLoggedIn && !playlists.value.length) loadPlaylists()
  addMenuTrackId.value = trackId
}

async function addTrackToSpecificPlaylist(track, pl) {
  addMenuTrackId.value = null
  try {
    await axios.post(`/api/music/playlists/${pl.id}/tracks`, { youtube_url: track.youtube_url })
    const found = playlists.value.find(p => p.id === pl.id)
    if (found) found.tracks_count = (found.tracks_count || 0) + 1
    alert(`"${pl.name}"에 추가되었습니다!`)
  } catch (e) { alert(e.response?.data?.message || '추가 실패') }
}

async function addAllToPlaylist(pl) {
  showAddAllToPlaylist.value = false
  let added = 0
  for (const track of currentPlaylist.value) {
    try { await axios.post(`/api/music/playlists/${pl.id}/tracks`, { youtube_url: track.youtube_url }); added++ } catch (e) {}
  }
  alert(`${added}곡을 "${pl.name}"에 추가했습니다!`)
  await loadPlaylists()
}

async function loadCategories() {
  try {
    const { data } = await axios.get('/api/music/categories')
    categories.value = data
    if (data.length > 0) await selectCategory(data[0])
  } catch (e) {}
}

async function selectCategory(cat) {
  selectedCat.value = cat
  activePlaylist.value = null
  tracksLoading.value = true
  try {
    const { data } = await axios.get(`/api/music/categories/${cat.id}/tracks`)
    tracks.value = data.tracks
  } catch (e) { tracks.value = [] }
  tracksLoading.value = false
}

async function loadPlaylists() {
  if (!authStore.isLoggedIn) return
  try {
    const { data } = await axios.get('/api/music/playlists')
    playlists.value = data
  } catch (e) {}
}

async function createPlaylist() {
  if (!newPlaylistName.value.trim()) return
  try {
    const { data } = await axios.post('/api/music/playlists', { name: newPlaylistName.value })
    const saved = pendingTrack.value
    newPlaylistName.value = ''
    showCreatePlaylist.value = false
    pendingTrack.value = null
    await loadPlaylists()
    if (saved) await addTrackToSpecificPlaylist(saved, data)
  } catch (e) {}
}

async function deletePlaylist(id) {
  if (!confirm('삭제하시겠습니까?')) return
  try {
    await axios.delete(`/api/music/playlists/${id}`)
    if (activePlaylist.value?.id === id) activePlaylist.value = null
    await loadPlaylists()
  } catch (e) {}
}

async function openPlaylist(pl) {
  try {
    const { data } = await axios.get(`/api/music/playlists/${pl.id}`)
    activePlaylist.value = data
    currentIndex.value = -1
  } catch (e) {}
}

async function addTrackToPlaylist() {
  if (!addTrackUrl.value.trim() || !activePlaylist.value) return
  addingTrack.value = true
  try {
    await axios.post(`/api/music/playlists/${activePlaylist.value.id}/tracks`, { youtube_url: addTrackUrl.value })
    addTrackUrl.value = ''
    showAddToPlaylist.value = false
    await openPlaylist(activePlaylist.value)
    await loadPlaylists()
  } catch (e) { alert(e.response?.data?.message || '추가 실패') }
  addingTrack.value = false
}

async function removeTrack(trackId) {
  if (!activePlaylist.value) return
  try {
    await axios.delete(`/api/music/playlists/${activePlaylist.value.id}/tracks/${trackId}`)
    await openPlaylist(activePlaylist.value)
    await loadPlaylists()
  } catch (e) {}
}

watch(showMyPlaylists, (val) => { if (val) loadPlaylists() })
onMounted(() => { loadYouTubeAPI(); loadCategories(); if (authStore.isLoggedIn) loadPlaylists() })
onUnmounted(() => { if (ytPlayer) { try { ytPlayer.destroy() } catch (e) {} } })
</script>

<style scoped>
@keyframes bar { 0%, 100% { height: 4px; } 50% { height: 18px; } }
.animate-bar { animation: bar 0.7s ease-in-out infinite; }
.scrollbar-hide::-webkit-scrollbar { display: none; }
.scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
.line-clamp-2 { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
</style>
