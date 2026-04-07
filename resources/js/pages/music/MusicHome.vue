<template>
<div class="min-h-screen bg-gray-50">
  <div class="max-w-7xl mx-auto px-4 py-5">
    <h1 class="text-xl font-black text-gray-800 mb-4">🎵 음악듣기</h1>

    <div v-if="loading" class="text-center py-12 text-gray-400">로딩중...</div>
    <div v-else class="grid grid-cols-12 gap-4">

      <!-- 카테고리 + 내 플레이리스트 -->
      <div class="col-span-12 lg:col-span-3 space-y-3">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
          <div class="px-4 py-3 border-b font-bold text-sm text-amber-900">🎵 카테고리</div>
          <button v-for="cat in categories" :key="cat.id" @click="selectCategory(cat)"
            class="w-full text-left px-4 py-2.5 text-sm transition"
            :class="activeCat?.id === cat.id ? 'bg-amber-50 text-amber-700 font-bold' : 'text-gray-600 hover:bg-amber-50/50'">
            {{ cat.name }}
          </button>
        </div>

        <!-- 즐겨찾기 -->
        <div v-if="auth.isLoggedIn" class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
          <button @click="loadFavorites"
            class="w-full text-left px-4 py-3 border-b font-bold text-sm transition"
            :class="showFavorites ? 'bg-red-50 text-red-700' : 'text-amber-900'">
            ❤️ 즐겨찾기 {{ favoriteTracks.length }}곡
          </button>
        </div>

        <!-- 내 플레이리스트 -->
        <div v-if="auth.isLoggedIn" class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
          <div class="px-4 py-3 border-b font-bold text-sm text-amber-900 flex items-center justify-between">
            📋 내 플레이리스트
            <button @click="showCreatePL = true" class="text-amber-600 text-xs hover:text-amber-800">+ 새로 만들기</button>
          </div>
          <div v-if="!playlists.length" class="px-4 py-3 text-xs text-gray-400">플레이리스트가 없습니다</div>
          <div v-for="pl in playlists" :key="pl.id" class="flex items-center group">
            <button @click="selectPlaylist(pl)"
              class="flex-1 text-left px-4 py-2.5 text-sm transition flex items-center justify-between"
              :class="activePL?.id === pl.id ? 'bg-blue-50 text-blue-700 font-bold' : 'text-gray-600 hover:bg-blue-50/50'">
              <span class="truncate">{{ pl.name }}</span>
              <span class="text-[10px] text-gray-400">{{ pl.tracks_count || 0 }}곡</span>
            </button>
            <button @click.stop="deletePlaylist(pl.id)" class="text-red-400 text-xs px-2 opacity-0 group-hover:opacity-100 hover:text-red-600">✕</button>
          </div>
        </div>

        <!-- 검색 -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-3">
          <form @submit.prevent="searchTracks" class="flex gap-1">
            <input v-model="searchQ" type="text" placeholder="제목/아티스트 검색" class="flex-1 border rounded-lg px-2 py-1.5 text-xs focus:ring-2 focus:ring-amber-400 outline-none" />
            <button type="submit" class="bg-amber-400 text-amber-900 font-bold px-2 py-1.5 rounded-lg text-xs">검색</button>
          </form>
          <div v-if="searchResults.length" class="mt-2 space-y-1">
            <div v-for="t in searchResults" :key="t.id" class="flex items-center justify-between py-1 text-xs">
              <div class="truncate flex-1"><span class="font-semibold text-gray-700">{{ t.title }}</span> <span class="text-gray-400">{{ t.artist }}</span></div>
              <button @click="playTrack(t)" class="text-amber-600 ml-1">▶</button>
            </div>
          </div>
        </div>
      </div>

      <!-- 트랙 목록 -->
      <div class="col-span-12 lg:col-span-5">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
          <div class="px-4 py-3 border-b font-bold text-sm text-amber-900 flex items-center justify-between">
            <span>🎶 {{ showFavorites ? '❤️ 즐겨찾기' : (activePL ? activePL.name : (activeCat?.name || '트랙')) }}</span>
            <div class="flex items-center gap-2">
              <span class="text-xs text-gray-400">{{ displayTracks.length }}곡</span>
              <button v-if="displayTracks.length" @click="playAll" class="text-[10px] bg-amber-100 text-amber-700 px-2 py-1 rounded-full font-bold hover:bg-amber-200">▶ 전체</button>
              <button v-if="displayTracks.length" @click="shufflePlay" class="text-[10px] bg-blue-100 text-blue-700 px-2 py-1 rounded-full font-bold hover:bg-blue-200">🔀 랜덤</button>
            </div>
          </div>
          <div v-if="!displayTracks.length" class="py-8 text-center text-sm text-gray-400">{{ showFavorites ? '즐겨찾기한 곡이 없습니다' : (activePL ? '플레이리스트가 비어있습니다' : '카테고리를 선택해주세요') }}</div>
          <div v-for="(track, i) in displayTracks" :key="track.id"
            class="flex items-center gap-3 px-4 py-2.5 border-b last:border-0 hover:bg-amber-50/50 transition cursor-pointer group"
            :class="playing?.id === track.id ? 'bg-amber-50' : ''">
            <span class="text-xs text-gray-400 w-5 text-center">{{ i + 1 + (trackPage - 1) * 20 }}</span>
            <div class="flex-1 min-w-0" @click="playTrack(track)">
              <div class="text-sm font-medium text-gray-800 truncate">{{ playing?.id === track.id ? '🔊 ' : '' }}{{ track.title }}</div>
              <div class="text-[10px] text-gray-400">{{ track.artist }} {{ track.duration ? '· ' + formatDuration(track.duration) : '' }}</div>
            </div>
            <!-- 즐겨찾기 버튼 -->
            <button v-if="auth.isLoggedIn" @click.stop="toggleFav(track)" class="text-sm transition" :class="isFav(track.id) ? 'text-red-500' : 'text-gray-300 hover:text-red-400'">{{ isFav(track.id) ? '❤️' : '🤍' }}</button>
            <!-- 플레이리스트 추가 -->
            <button v-if="auth.isLoggedIn && !activePL && playlists.length" @click.stop="showAddToPL(track)" class="text-sm transition text-gray-300 hover:text-amber-500" title="플레이리스트에 추가">⭐</button>
            <!-- 플레이리스트에서 제거 -->
            <button v-if="auth.isLoggedIn && activePL" @click.stop="removeFromPL(track)" class="text-red-400 text-xs hover:text-red-600 opacity-0 group-hover:opacity-100">✕</button>
          </div>
        </div>

        <!-- 페이지네이션 -->
        <div v-if="trackLastPage > 1 && activeCat && !showFavorites && !activePL" class="flex justify-center gap-1.5 mt-3">
          <button v-for="pg in Math.min(trackLastPage, 10)" :key="pg" @click="loadCategoryPage(pg)"
            class="px-3 py-1 rounded text-sm" :class="pg===trackPage?'bg-amber-400 text-amber-900 font-bold':'bg-white text-gray-600 border hover:bg-amber-50'">{{ pg }}</button>
        </div>
      </div>

      <!-- 플레이어 -->
      <div class="col-span-12 lg:col-span-4">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden sticky top-20">
          <div class="px-4 py-3 border-b font-bold text-sm text-amber-900">🎧 Now Playing</div>
          <div v-if="playing" class="p-3">
            <div class="aspect-video bg-gray-900 rounded-lg overflow-hidden mb-3">
              <iframe :src="`https://www.youtube.com/embed/${playing.youtube_id}?autoplay=1`"
                class="w-full h-full" frameborder="0" allow="autoplay; encrypted-media" allowfullscreen></iframe>
            </div>
            <div class="text-sm font-bold text-gray-800 truncate">{{ playing.title }}</div>
            <div class="text-xs text-gray-400 mt-0.5">{{ playing.artist }}</div>
            <div class="flex gap-2 mt-3">
              <button @click="prevTrack" class="bg-gray-100 px-3 py-1 rounded text-xs hover:bg-gray-200">⏮</button>
              <button @click="nextTrack" class="bg-gray-100 px-3 py-1 rounded text-xs hover:bg-gray-200 flex-1">다음 ⏭</button>
              <button @click="shufflePlay" class="bg-blue-100 px-2 py-1 rounded text-xs hover:bg-blue-200" :class="isShuffled?'text-blue-700 font-bold':'text-blue-400'">🔀</button>
              <button @click="toggleRepeat" class="bg-gray-100 px-2 py-1 rounded text-xs hover:bg-gray-200" :class="repeatMode?'text-amber-700 font-bold':'text-gray-400'">{{ repeatMode === 'one' ? '🔂' : '🔁' }}</button>
              <button v-if="auth.isLoggedIn" @click="toggleFav(playing)" class="text-sm" :class="isFav(playing.id)?'text-red-500':'text-gray-300'">{{ isFav(playing.id)?'❤️':'🤍' }}</button>
            </div>
          </div>
          <div v-else class="p-4 text-center text-sm text-gray-400">트랙을 선택해주세요 🎵</div>

          <!-- 즐겨찾기 큐 -->
          <div v-if="favoriteTracks.length" class="border-t">
            <div class="px-4 py-2 font-bold text-xs text-red-700 flex items-center justify-between">
              <span>❤️ 즐겨찾기 큐 ({{ favoriteTracks.length }}곡)</span>
              <button @click="playAllFavorites" class="text-amber-600 hover:text-amber-800">▶ 전체재생</button>
            </div>
            <div class="max-h-40 overflow-y-auto">
              <div v-for="ft in favoriteTracks.slice(0,10)" :key="ft.id" @click="playTrack(ft)"
                class="px-4 py-1.5 text-xs text-gray-600 hover:bg-amber-50 cursor-pointer truncate"
                :class="playing?.id===ft.id ? 'bg-amber-50 font-bold' : ''">
                {{ ft.title }}
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- 플레이리스트 생성 모달 -->
    <div v-if="showCreatePL" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center" @click.self="showCreatePL=false">
      <div class="bg-white rounded-xl p-5 w-full max-w-sm shadow-xl">
        <h3 class="font-bold text-gray-800 mb-3">새 플레이리스트</h3>
        <input v-model="newPLName" type="text" placeholder="플레이리스트 이름" class="w-full border rounded-lg px-3 py-2 text-sm mb-3 focus:ring-2 focus:ring-amber-400 outline-none" />
        <div class="flex gap-2">
          <button @click="createPlaylist" class="bg-amber-400 text-amber-900 font-bold px-4 py-2 rounded-lg text-sm flex-1 hover:bg-amber-500">만들기</button>
          <button @click="showCreatePL=false" class="text-gray-500 px-4 py-2">취소</button>
        </div>
      </div>
    </div>

    <!-- 플레이리스트에 추가 모달 -->
    <div v-if="addTrackTarget" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center" @click.self="addTrackTarget=null">
      <div class="bg-white rounded-xl p-5 w-full max-w-sm shadow-xl">
        <h3 class="font-bold text-gray-800 mb-3">플레이리스트에 추가</h3>
        <div class="text-sm text-gray-600 mb-3">{{ addTrackTarget.title }}</div>
        <div class="space-y-1">
          <button v-for="pl in playlists" :key="pl.id" @click="addToPL(pl.id)"
            class="w-full text-left px-3 py-2 rounded-lg text-sm hover:bg-amber-50 transition">📋 {{ pl.name }}</button>
        </div>
        <button @click="addTrackTarget=null" class="mt-3 text-gray-500 text-sm">취소</button>
      </div>
    </div>
  </div>
</div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useAuthStore } from '../../stores/auth'
import axios from 'axios'

const auth = useAuthStore()
const categories = ref([])
const tracks = ref([])
const playlists = ref([])
const plTracks = ref([])
const favoriteTracks = ref([])
const favIds = ref(new Set())
const activeCat = ref(null)
const activePL = ref(null)
const showFavorites = ref(false)
const playing = ref(null)
const loading = ref(true)
const searchQ = ref('')
const searchResults = ref([])
const showCreatePL = ref(false)
const newPLName = ref('')
const addTrackTarget = ref(null)
const trackPage = ref(1)
const trackLastPage = ref(1)
const playQueue = ref([])
const isShuffled = ref(false)
const repeatMode = ref(null) // null, 'all', 'one'

const displayTracks = computed(() => {
  if (showFavorites.value) return favoriteTracks.value
  if (activePL.value) return plTracks.value
  return tracks.value
})

function formatDuration(sec) {
  if (!sec) return ''
  return `${Math.floor(sec / 60)}:${String(sec % 60).padStart(2, '0')}`
}

function isFav(id) { return favIds.value.has(id) }

function playTrack(track) {
  playing.value = track
  // 현재 표시 목록을 재생 큐로 설정
  playQueue.value = [...displayTracks.value]
}

function nextTrack() {
  if (repeatMode.value === 'one') { playing.value = { ...playing.value }; return } // 같은 곡 재시작
  const list = playQueue.value.length ? playQueue.value : displayTracks.value
  const idx = list.findIndex(t => t.id === playing.value?.id)
  if (idx >= 0 && idx < list.length - 1) playing.value = list[idx + 1]
  else if (repeatMode.value === 'all' && list.length) playing.value = list[0] // 전체 반복
  else if (list.length) playing.value = list[0]
}

function prevTrack() {
  const list = playQueue.value.length ? playQueue.value : displayTracks.value
  const idx = list.findIndex(t => t.id === playing.value?.id)
  if (idx > 0) playing.value = list[idx - 1]
}

function playAll() {
  const list = [...displayTracks.value]
  if (!list.length) return
  playQueue.value = list
  isShuffled.value = false
  playing.value = list[0]
}

function shufflePlay() {
  const list = [...displayTracks.value]
  if (!list.length) return
  // Fisher-Yates shuffle
  for (let i = list.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    [list[i], list[j]] = [list[j], list[i]]
  }
  playQueue.value = list
  isShuffled.value = true
  playing.value = list[0]
}

function toggleRepeat() {
  if (!repeatMode.value) repeatMode.value = 'all'
  else if (repeatMode.value === 'all') repeatMode.value = 'one'
  else repeatMode.value = null
}

function playAllFavorites() {
  if (!favoriteTracks.value.length) return
  playQueue.value = [...favoriteTracks.value]
  isShuffled.value = false
  playing.value = favoriteTracks.value[0]
}

async function selectCategory(cat) {
  activeCat.value = cat; activePL.value = null; showFavorites.value = false
  trackPage.value = 1
  await loadCategoryPage(1)
}

async function loadCategoryPage(p = 1) {
  trackPage.value = p
  try {
    const { data } = await axios.get(`/api/music/tracks/${activeCat.value.id}`, { params: { page: p, per_page: 20 } })
    tracks.value = data.data?.data || data.data || []
    trackLastPage.value = data.data?.last_page || 1
  } catch {}
}

async function selectPlaylist(pl) {
  activePL.value = pl; activeCat.value = null; showFavorites.value = false
  try {
    const { data } = await axios.get(`/api/music/playlists/${pl.id}`)
    plTracks.value = (data.data?.tracks || []).map(pt => pt.track).filter(Boolean)
  } catch {}
}

async function loadFavorites() {
  showFavorites.value = true; activeCat.value = null; activePL.value = null
  if (!auth.isLoggedIn) return
  try { const { data } = await axios.get('/api/music/favorites'); favoriteTracks.value = data.data || [] } catch {}
}

async function toggleFav(track) {
  if (!auth.isLoggedIn) return
  try {
    const { data } = await axios.post('/api/music/favorites', { track_id: track.id })
    if (data.favorited) {
      favIds.value.add(track.id)
      favoriteTracks.value.push(track)
    } else {
      favIds.value.delete(track.id)
      favoriteTracks.value = favoriteTracks.value.filter(t => t.id !== track.id)
    }
  } catch {}
}

async function loadPlaylists() {
  if (!auth.isLoggedIn) return
  try { const { data } = await axios.get('/api/music/playlists'); playlists.value = data.data || [] } catch {}
}

async function createPlaylist() {
  if (!newPLName.value.trim()) return
  try {
    await axios.post('/api/music/playlists', { name: newPLName.value })
    newPLName.value = ''; showCreatePL.value = false
    await loadPlaylists()
  } catch {}
}

async function deletePlaylist(plId) {
  if (!confirm('삭제하시겠습니까?')) return
  try {
    await axios.delete(`/api/music/playlists/${plId}`)
    if (activePL.value?.id === plId) { activePL.value = null; plTracks.value = [] }
    await loadPlaylists()
  } catch {}
}

function showAddToPL(track) { addTrackTarget.value = track }

async function addToPL(plId) {
  try {
    await axios.post(`/api/music/playlists/${plId}/tracks`, { track_id: addTrackTarget.value.id })
    await loadPlaylists()
  } catch {}
  addTrackTarget.value = null
}

async function removeFromPL(track) {
  if (!activePL.value) return
  try {
    await axios.delete(`/api/music/playlists/${activePL.value.id}/tracks/${track.id}`)
    plTracks.value = plTracks.value.filter(t => t.id !== track.id)
    await loadPlaylists()
  } catch {}
}

async function searchTracks() {
  if (!searchQ.value.trim()) { searchResults.value = []; return }
  try { const { data } = await axios.get('/api/music/search', { params: { q: searchQ.value } }); searchResults.value = data.data || [] } catch {}
}

onMounted(async () => {
  try {
    const { data } = await axios.get('/api/music/categories')
    categories.value = data.data || []
    if (categories.value.length) selectCategory(categories.value[0])
  } catch {}
  await loadPlaylists()
  // 즐겨찾기 ID 로드
  if (auth.isLoggedIn) {
    try {
      const { data } = await axios.get('/api/music/favorites')
      favoriteTracks.value = data.data || []
      favoriteTracks.value.forEach(t => favIds.value.add(t.id))
    } catch {}
  }
  loading.value = false
})
</script>
