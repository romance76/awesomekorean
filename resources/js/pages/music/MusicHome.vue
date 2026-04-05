<template>
  <div class="min-h-screen bg-gray-900 pb-16">
    <div class="max-w-[1200px] mx-auto px-4 pt-4">

      <!-- Banner -->
      <div class="bg-gradient-to-r from-purple-700 to-indigo-600 text-white px-6 py-5 rounded-2xl mb-4">
        <div class="flex items-center justify-between">
          <div>
            <h1 class="text-xl font-black">🎵 음악듣기</h1>
            <p class="text-sm opacity-80 mt-0.5">한인 커뮤니티 뮤직 스트리밍</p>
          </div>

        </div>
      </div>

      <!-- 3-Column Layout -->
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">

        <!-- Left: Categories (2 cols) -->
        <div class="lg:col-span-2">
          <div class="bg-gray-800 rounded-2xl p-3 sticky top-20">
            <h3 class="text-xs font-bold text-gray-500 uppercase px-2 mb-2">카테고리</h3>
            <div class="space-y-1">
              <button v-for="cat in categories" :key="cat.id"
                @click="selectCategory(cat); showMyPlaylists = false"
                class="w-full text-left px-3 py-2.5 rounded-xl text-sm font-medium transition flex items-center gap-2"
                :class="selectedCat?.id === cat.id && !showMyPlaylists ? 'bg-purple-600 text-white' : 'text-gray-400 hover:bg-gray-700 hover:text-white'">
                <span>{{ cat.emoji || '🎵' }}</span>
                <span class="truncate">{{ cat.name }}</span>
              </button>
              <div class="border-t border-gray-700 my-2"></div>
              <h3 class="text-[10px] font-bold text-gray-500 uppercase px-2 mb-1">내 뮤직함</h3>
              <div v-for="pl in playlists" :key="pl.id"
                @click="loadPlaylist(pl)"
                class="w-full text-left px-3 py-2 rounded-xl text-sm font-medium transition flex items-center justify-between cursor-pointer"
                :class="activePlaylist?.id === pl.id ? 'bg-yellow-600 text-white' : 'text-gray-400 hover:bg-gray-700 hover:text-white'">
                <span class="flex items-center gap-2 truncate"><span>⭐</span><span class="truncate">{{ pl.name }}</span></span>
                <span class="flex items-center gap-1">
                  <span class="text-xs opacity-60">{{ pl.tracks_count || pl.tracks?.length || 0 }}</span>
                  <span @click.stop="deletePlaylist(pl.id)" class="text-gray-600 hover:text-red-400 text-xs cursor-pointer ml-1">🗑</span>
                </span>
              </div>
              <button v-if="authStore.isLoggedIn" @click="showCreatePlaylist = true"
                class="w-full text-left px-3 py-2 rounded-xl text-xs text-gray-500 hover:text-purple-400 transition">
                + 새 뮤직함 만들기
              </button>
            </div>
          </div>
        </div>

        <!-- Center: Track List (6 cols) -->
        <div class="lg:col-span-6">
          <div class="bg-gray-800 rounded-2xl overflow-hidden">
            <!-- Header with search -->
            <div class="p-4 border-b border-gray-700">
              <div class="flex items-center justify-between mb-3">
                <h3 class="text-white font-bold text-sm">
                  {{ activePlaylist ? activePlaylist.name : (selectedCat ? selectedCat.name : '전체') }}
                  <span class="text-gray-500 font-normal ml-1">{{ currentPlaylist.length }}곡</span>
                </h3>
                <div class="flex items-center gap-2">
                  <button @click="playAll" class="px-3 py-1.5 bg-purple-600 hover:bg-purple-500 text-white rounded-lg text-xs font-semibold transition">▶ 전체재생</button>
                  <button @click="playShuffleAll" class="px-3 py-1.5 bg-gray-700 hover:bg-gray-600 text-white rounded-lg text-xs font-semibold transition">🔀 셔플</button>
                </div>
              </div>
              <!-- Search within category -->
              <div class="relative">
                <input v-model="trackSearch" type="text" placeholder="곡 검색..."
                  class="w-full bg-gray-700 text-white text-sm rounded-xl px-4 py-2.5 pl-9 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-purple-500" />
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 text-sm">🔍</span>
              </div>
            </div>

            <!-- Track list -->
            <div class="max-h-[60vh] overflow-y-auto">
              <div v-if="tracksLoading" class="text-center py-12 text-gray-500 text-sm">불러오는 중...</div>
              <div v-else-if="filteredTracks.length === 0" class="text-center py-12 text-gray-500 text-sm">곡이 없습니다</div>
              <div v-else>
                <div v-for="(track, idx) in filteredTracks" :key="track.id || idx"
                  class="flex items-center gap-3 px-4 py-2.5 hover:bg-gray-750 cursor-pointer transition group border-b border-gray-700/50 last:border-0"
                  :class="{ 'bg-purple-900/30': musicStore.currentTrack?.youtubeId === extractId(track) }">
                  <!-- Number -->
                  <span class="text-gray-600 text-xs w-5 text-right flex-shrink-0">{{ idx + 1 }}</span>
                  <!-- Thumbnail -->
                  <div @click="playTrack(track, idx)" class="relative w-12 h-12 rounded-lg overflow-hidden bg-gray-700 flex-shrink-0">
                    <img :src="'https://img.youtube.com/vi/' + extractId(track) + '/mqdefault.jpg'" class="w-full h-full object-cover" />
                    <div class="absolute inset-0 bg-black/30 flex items-center justify-center opacity-0 group-hover:opacity-100 transition">
                      <span class="text-white text-sm">▶</span>
                    </div>
                    <div v-if="musicStore.currentTrack?.youtubeId === extractId(track) && musicStore.isPlaying"
                      class="absolute inset-0 bg-black/40 flex items-center justify-center">
                      <span class="text-purple-400 animate-pulse">♫</span>
                    </div>
                  </div>
                  <!-- Info -->
                  <div @click="playTrack(track, idx)" class="flex-1 min-w-0">
                    <p class="text-sm text-white font-medium truncate">{{ track.title }}</p>
                    <p class="text-xs text-gray-500 truncate">{{ track.artist || track.channel || '' }}</p>
                  </div>
                  <!-- Star (add to my playlist) -->
                  <button @click.stop="toggleFavorite(track)"
                    class="text-lg flex-shrink-0 hover:scale-125 transition"
                    :class="isFavorite(track) ? 'text-yellow-400' : 'text-gray-600 hover:text-yellow-400'">
                    {{ isFavorite(track) ? '★' : '☆' }}
                  </button>
                  <!-- Delete from playlist -->
                  <button v-if="activePlaylist" @click.stop="removeFromPlaylist(track)"
                    class="text-gray-600 hover:text-red-400 text-sm flex-shrink-0 transition ml-1" title="삭제">
                    ✕
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Right: My Music Box + YouTube Search (4 cols) -->
        <div class="lg:col-span-4 space-y-4">

          <!-- YouTube Search & Add -->
          <div class="bg-gray-800 rounded-2xl p-4">
            <h3 class="text-white font-bold text-sm mb-3">🔍 YouTube에서 곡 추가</h3>
            <div class="flex gap-2">
              <input v-model="ytSearchQuery" type="text" placeholder="노래 제목, 가수 검색..."
                @keyup.enter="searchYouTube"
                class="flex-1 bg-gray-700 text-white text-sm rounded-xl px-3 py-2.5 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-purple-500" />
              <button @click="searchYouTube" :disabled="ytSearching" class="bg-purple-600 text-white px-4 py-2.5 rounded-xl text-sm font-semibold hover:bg-purple-500 disabled:opacity-50 flex-shrink-0">
                {{ ytSearching ? '...' : '검색' }}
              </button>
            </div>
            <!-- Search results -->
            <div v-if="ytResults.length" class="mt-3 max-h-[300px] overflow-y-auto space-y-2">
              <div v-for="r in ytResults" :key="r.id"
                class="flex items-center gap-2 p-2 bg-gray-700 rounded-xl hover:bg-gray-600 cursor-pointer transition">
                <img :src="r.thumbnail" class="w-10 h-10 rounded object-cover flex-shrink-0" />
                <div class="flex-1 min-w-0">
                  <p class="text-xs text-white truncate">{{ r.title }}</p>
                  <p class="text-[10px] text-gray-400 truncate">{{ r.channel }}</p>
                </div>
                <button @click="addYtToPlaylist(r)" class="text-purple-400 hover:text-purple-300 text-xs flex-shrink-0 font-semibold">+ 추가</button>
              </div>
            </div>
          </div>




        </div>

      </div>
    </div>

    <!-- Create Playlist Modal -->
    <div v-if="showCreatePlaylist" class="fixed inset-0 bg-black/70 z-50 flex items-center justify-center p-4" @click.self="showCreatePlaylist = false">
      <div class="bg-gray-800 rounded-2xl p-6 w-full max-w-sm">
        <h3 class="text-lg font-bold text-white mb-4">⭐ 새 뮤직함 만들기</h3>
        <input v-model="newPlaylistName" type="text" placeholder="뮤직함 이름..." @keyup.enter="createPlaylist"
          class="w-full bg-gray-700 text-white rounded-xl px-4 py-3 text-sm placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-purple-500 mb-4" />
        <div class="flex gap-2">
          <button @click="showCreatePlaylist = false" class="flex-1 py-3 bg-gray-700 text-gray-300 rounded-xl text-sm">취소</button>
          <button @click="createPlaylist" class="flex-1 py-3 bg-purple-600 text-white rounded-xl text-sm font-semibold">만들기</button>
        </div>
      </div>
    </div>

    <!-- YouTube 곡 추가 뮤직함 선택 -->
    <div v-if="showYtPicker" class="fixed inset-0 bg-black/70 z-50 flex items-center justify-center p-4" @click.self="showYtPicker = false">
      <div class="bg-gray-800 rounded-2xl p-6 w-full max-w-sm">
        <h3 class="text-lg font-bold text-white mb-2">어디에 추가할까요?</h3>
        <p class="text-sm text-gray-400 mb-4 truncate">{{ pendingYtTrack?.title }}</p>
        <div class="space-y-2">
          <button v-for="pl in playlists" :key="pl.id" @click="doAddYt(pendingYtTrack, pl)"
            class="w-full text-left p-3 bg-gray-700 rounded-xl text-sm text-white hover:bg-gray-600 transition">
            ⭐ {{ pl.name }} <span class="text-gray-500">({{ pl.tracks_count || pl.tracks?.length || 0 }}곡)</span>
          </button>
        </div>
        <button @click="showYtPicker = false" class="w-full mt-3 p-2 bg-gray-700 rounded-xl text-sm text-gray-400 hover:bg-gray-600">취소</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
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
const showMyPlaylists = ref(false)
const playlists = ref([])
const activePlaylist = ref(null)
const showAddToPlaylist = ref(false)
const addTrackUrl = ref('')
const addingTrack = ref(false)

const currentPlaylist = computed(() => {
  if (activePlaylist.value?.tracks) return activePlaylist.value.tracks
  return tracks.value
})

// Track search within category
const trackSearch = ref('')
const filteredTracks = computed(() => {
  if (!trackSearch.value.trim()) return currentPlaylist.value
  const q = trackSearch.value.toLowerCase()
  return currentPlaylist.value.filter(t =>
    (t.title || '').toLowerCase().includes(q) ||
    (t.artist || '').toLowerCase().includes(q) ||
    (t.channel || '').toLowerCase().includes(q)
  )
})

// YouTube search
const ytSearchQuery = ref('')
const ytResults = ref([])
const ytSearching = ref(false)

// Favorites
const showFavPicker = ref(false)
const favTrack = ref(null)
const favorites = ref([])

// Create/delete playlist
const showCreatePlaylist = ref(false)
const newPlaylistName = ref('')

function extractId(track) {
  if (track.youtube_id) return track.youtube_id
  if (track.url) {
    const m = track.url.match(/(?:youtube\.com\/(?:watch\?v=|embed\/|shorts\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/)
    return m ? m[1] : ''
  }
  return ''
}

function playTrack(track, index) {
  currentTrack.value = track
  currentIndex.value = index
  isPlaying.value = true

  const vid = extractId(track)
  if (!vid) return

  try { axios.post(`/api/music/tracks/${track.id}/play`) } catch (e) {}

  musicStore.play({
    id: track.id || vid,
    title: track.title,
    artist: track.artist || track.channel || '',
    thumbnail: 'https://img.youtube.com/vi/' + vid + '/mqdefault.jpg',
    youtubeId: vid
  }, currentPlaylist.value.map(t => ({
    id: t.id || extractId(t),
    title: t.title,
    artist: t.artist || t.channel || '',
    thumbnail: 'https://img.youtube.com/vi/' + extractId(t) + '/mqdefault.jpg',
    youtubeId: extractId(t)
  })))
}

function playAll() {
  if (!currentPlaylist.value.length) return
  playTrack(currentPlaylist.value[0], 0)
}

function playShuffleAll() {
  if (!currentPlaylist.value.length) return
  const idx = Math.floor(Math.random() * currentPlaylist.value.length)
  playTrack(currentPlaylist.value[idx], idx)
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
    playlists.value = data.data || data || []
  } catch (e) {}
}

async function loadPlaylist(pl) {
  selectedCat.value = null
  showMyPlaylists.value = true
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
    await loadPlaylist(activePlaylist.value)
    await loadPlaylists()
  } catch (e) { alert(e.response?.data?.message || '추가 실패') }
  addingTrack.value = false
}

async function searchYouTube() {
  if (!ytSearchQuery.value.trim()) return
  ytSearching.value = true
  try {
    const apiKey = 'AIzaSyB4nBh0k2In-vh_IeEe41uZ5OPy3MlFal0'
    const res = await fetch(`https://www.googleapis.com/youtube/v3/search?key=${apiKey}&q=${encodeURIComponent(ytSearchQuery.value)}&part=snippet&type=video&videoCategoryId=10&maxResults=10`)
    const data = await res.json()
    ytResults.value = (data.items || []).map(item => ({
      id: item.id.videoId,
      title: item.snippet.title,
      channel: item.snippet.channelTitle,
      thumbnail: item.snippet.thumbnails?.medium?.url || item.snippet.thumbnails?.default?.url
    }))
  } catch (e) {
    console.error('YouTube search failed', e)
  } finally {
    ytSearching.value = false
  }
}

const pendingYtTrack = ref(null)
const showYtPicker = ref(false)

async function addYtToPlaylist(result) {
  if (!authStore.isLoggedIn) { alert('로그인이 필요합니다'); return }

  // No playlists? Create one automatically
  if (playlists.value.length === 0) {
    try {
      await axios.post('/api/music/playlists', { name: '내 플레이리스트' })
      await loadPlaylists()
    } catch {}
  }

  // Only 1 playlist? Add directly
  if (playlists.value.length === 1) {
    await doAddYt(result, playlists.value[0])
    return
  }

  // Multiple? Show picker
  pendingYtTrack.value = result
  showYtPicker.value = true
}

async function doAddYt(result, playlist) {
  try {
    await axios.post('/api/music/playlists/' + playlist.id + '/tracks', {
      youtube_url: 'https://www.youtube.com/watch?v=' + result.id
    })
    showYtPicker.value = false
    await loadPlaylists()
    loadPlaylist(playlist)
    alert('"' + playlist.name + '"에 추가되었습니다!')
  } catch (e) {
    alert(e.response?.data?.message || '추가 실패')
  }
}

async function removeFromPlaylist(track) {
  if (!activePlaylist.value) return
  if (!confirm('이 곡을 삭제하시겠습니까?')) return
  try {
    await axios.delete(`/api/music/playlists/${activePlaylist.value.id}/tracks/${track.id}`)
    await loadPlaylist(activePlaylist.value)
    await loadPlaylists()
  } catch (e) {
    alert(e.response?.data?.message || '삭제 실패')
  }
}

function isFavorite(track) {
  return favorites.value.includes(extractId(track))
}

async function toggleFavorite(track) {
  if (!authStore.isLoggedIn) { alert('로그인이 필요합니다'); return }
  if (playlists.value.length === 0) {
    try {
      await axios.post('/api/music/playlists', { name: '내 플레이리스트' })
      await loadPlaylists()
    } catch {}
  }
  if (playlists.value.length === 1) {
    addToPlaylist(track, playlists.value[0])
    return
  }
  favTrack.value = track
  showFavPicker.value = true
}

async function addToPlaylist(track, playlist) {
  try {
    await axios.post(`/api/music/playlists/${playlist.id}/tracks`, {
      youtube_url: `https://www.youtube.com/watch?v=${extractId(track)}`
    })
    favorites.value.push(extractId(track))
    showFavPicker.value = false
    await loadPlaylists()
    alert(`"${playlist.name}"에 추가되었습니다!`)
  } catch (e) {
    alert(e.response?.data?.message || '추가 실패')
  }
}

async function createPlaylist() {
  if (!newPlaylistName.value.trim()) return
  try {
    await axios.post('/api/music/playlists', { name: newPlaylistName.value })
    newPlaylistName.value = ''
    showCreatePlaylist.value = false
    loadPlaylists()
  } catch (e) {
    alert(e.response?.data?.message || '생성 실패')
  }
}

async function deletePlaylist(id) {
  if (!confirm('정말 삭제하시겠습니까?')) return
  try {
    await axios.delete('/api/music/playlists/' + id)
    if (activePlaylist.value?.id === id) activePlaylist.value = null
    loadPlaylists()
  } catch {}
}

watch(showMyPlaylists, (val) => { if (val) loadPlaylists() })
onMounted(() => { loadCategories(); if (authStore.isLoggedIn) loadPlaylists() })
</script>

<style scoped>
.scrollbar-hide::-webkit-scrollbar { display: none; }
.scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
</style>
