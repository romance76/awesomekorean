<template>
<div>
  <div class="flex items-center justify-between mb-4">
    <h1 class="text-xl font-black text-gray-800">🎵 음악 관리</h1>
    <div class="flex gap-2 items-center">
      <span class="text-xs text-gray-400">총 {{ totalTracks }}곡</span>
      <button @click="fetchMusic" :disabled="fetching" class="bg-blue-500 text-white font-bold px-4 py-2 rounded-lg text-sm hover:bg-blue-600 disabled:opacity-50">
        {{ fetching ? '수집중...' : '🔄 음악 수집 (100곡)' }}
      </button>
    </div>
  </div>

  <div class="grid grid-cols-12 gap-4">
    <!-- 카테고리 목록 -->
    <div class="col-span-12 lg:col-span-4">
      <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
        <div class="px-4 py-3 border-b flex items-center justify-between">
          <span class="font-bold text-sm text-gray-800">🎵 카테고리 ({{ categories.length }}개)</span>
          <button @click="showAddCat=true" class="text-amber-600 text-xs font-bold hover:text-amber-800">+ 추가</button>
        </div>
        <button v-for="cat in categories" :key="cat.id" @click="selectCategory(cat)"
          class="w-full text-left px-4 py-3 border-b last:border-0 transition flex items-center justify-between"
          :class="activeCat?.id===cat.id ? 'bg-amber-50 text-amber-700' : 'hover:bg-gray-50'">
          <div>
            <div class="text-sm font-semibold">{{ cat.name }}</div>
            <div class="text-[10px] text-gray-400">{{ trackCounts[cat.id] || 0 }}곡</div>
          </div>
          <span class="text-xs text-gray-300">→</span>
        </button>
      </div>

      <!-- 통계 -->
      <div class="bg-white rounded-xl shadow-sm border p-4 mt-3">
        <div class="text-xs text-gray-500 space-y-1">
          <div>전체 카테고리: <strong>{{ categories.length }}개</strong></div>
          <div>전체 트랙: <strong>{{ totalTracks }}곡</strong></div>
          <div class="text-amber-600">* 트랙은 YouTube API로 매일 자동 추가됩니다</div>
        </div>
      </div>
    </div>

    <!-- 트랙 목록 -->
    <div class="col-span-12 lg:col-span-8">
      <div v-if="!activeCat" class="bg-white rounded-xl shadow-sm border p-12 text-center text-gray-400">
        카테고리를 선택하세요
      </div>
      <div v-else class="bg-white rounded-xl shadow-sm border overflow-hidden">
        <div class="px-4 py-3 border-b flex items-center justify-between">
          <span class="font-bold text-sm text-gray-800">🎶 {{ activeCat.name }} ({{ tracks.length }}곡)</span>
          <button @click="showAddTrack=true" class="bg-amber-400 text-amber-900 font-bold px-3 py-1 rounded-lg text-xs hover:bg-amber-500">+ 트랙 추가</button>
        </div>
        <table class="w-full text-sm">
          <thead class="bg-gray-50 border-b"><tr>
            <th class="px-3 py-2 text-left text-xs text-gray-500">#</th>
            <th class="px-3 py-2 text-left text-xs text-gray-500">제목</th>
            <th class="px-3 py-2 text-left text-xs text-gray-500">아티스트</th>
            <th class="px-3 py-2 text-xs text-gray-500">YouTube</th>
            <th class="px-3 py-2 text-xs text-gray-500">관리</th>
          </tr></thead>
          <tbody>
            <tr v-for="(t, i) in tracks" :key="t.id" class="border-b last:border-0 hover:bg-amber-50/30">
              <td class="px-3 py-2 text-xs text-gray-400">{{ i+1 }}</td>
              <td class="px-3 py-2 font-medium text-gray-800">{{ t.title }}</td>
              <td class="px-3 py-2 text-xs text-gray-500">{{ t.artist }}</td>
              <td class="px-3 py-2 text-center">
                <a v-if="t.youtube_id" :href="`https://youtube.com/watch?v=${t.youtube_id}`" target="_blank" class="text-red-500 text-xs">▶</a>
              </td>
              <td class="px-3 py-2 text-center">
                <button @click="deleteTrack(t)" class="text-xs text-red-400 hover:text-red-600">삭제</button>
              </td>
            </tr>
          </tbody>
        </table>
        <div v-if="!tracks.length" class="py-6 text-center text-sm text-gray-400">트랙이 없습니다</div>
      </div>
    </div>
  </div>

  <!-- 카테고리 추가 모달 -->
  <div v-if="showAddCat" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center" @click.self="showAddCat=false">
    <div class="bg-white rounded-xl p-5 w-full max-w-md shadow-xl space-y-3">
      <h3 class="font-bold">카테고리 추가</h3>
      <input v-model="newCat.name" placeholder="카테고리 이름 (예: 인디)" class="w-full border rounded-lg px-3 py-2 text-sm" />
      <input v-model="newCat.slug" placeholder="slug (영문, 예: indie)" class="w-full border rounded-lg px-3 py-2 text-sm" />
      <div>
        <label class="text-xs text-gray-500">한국 검색어 (쉼표 구분)</label>
        <input v-model="newCat.korean_queries" placeholder="한국 인디 음악,인디 밴드,인디 인기곡" class="w-full border rounded-lg px-3 py-2 text-sm mt-1" />
      </div>
      <div>
        <label class="text-xs text-gray-500">팝 검색어 (쉼표 구분)</label>
        <input v-model="newCat.pop_queries" placeholder="indie music,indie rock,indie pop hits" class="w-full border rounded-lg px-3 py-2 text-sm mt-1" />
      </div>
      <p class="text-[10px] text-gray-400">* 매일 자동 수집 시 이 검색어로 YouTube에서 음악을 찾습니다</p>
      <div class="flex gap-2">
        <button @click="addCategory" class="bg-amber-400 text-amber-900 font-bold px-4 py-2 rounded-lg text-sm flex-1">추가</button>
        <button @click="showAddCat=false" class="text-gray-500 px-4">취소</button>
      </div>
    </div>
  </div>

  <!-- 트랙 추가 모달 -->
  <div v-if="showAddTrack" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center" @click.self="showAddTrack=false">
    <div class="bg-white rounded-xl p-5 w-full max-w-sm shadow-xl space-y-3">
      <h3 class="font-bold">트랙 추가 ({{ activeCat?.name }})</h3>
      <input v-model="newTrack.title" placeholder="곡 제목" class="w-full border rounded-lg px-3 py-2 text-sm" />
      <input v-model="newTrack.artist" placeholder="아티스트" class="w-full border rounded-lg px-3 py-2 text-sm" />
      <input v-model="newTrack.youtube_url" placeholder="YouTube URL" class="w-full border rounded-lg px-3 py-2 text-sm" />
      <div class="flex gap-2">
        <button @click="addTrack" class="bg-amber-400 text-amber-900 font-bold px-4 py-2 rounded-lg text-sm flex-1">추가</button>
        <button @click="showAddTrack=false" class="text-gray-500 px-4">취소</button>
      </div>
    </div>
  </div>
</div>
</template>
<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import axios from 'axios'

const categories = ref([])
const tracks = ref([])
const activeCat = ref(null)
const fetching = ref(false)

async function fetchMusic() {
  fetching.value = true
  try { await axios.post('/api/admin/fetch-music'); alert('음악 100곡 수집 완료! 새로고침하세요.') } catch(e) { alert(e.response?.data?.message || '수집 실패') }
  fetching.value = false
}
const trackCounts = ref({})
const showAddCat = ref(false)
const showAddTrack = ref(false)
const newCat = reactive({ name: '', slug: '', korean_queries: '', pop_queries: '' })
const newTrack = reactive({ title: '', artist: '', youtube_url: '' })

const totalTracks = computed(() => Object.values(trackCounts.value).reduce((s, n) => s + n, 0))

async function loadCategories() {
  try {
    const { data } = await axios.get('/api/music/categories')
    categories.value = data.data || []
    // 각 카테고리별 트랙 수 로딩
    for (const cat of categories.value) {
      try {
        const { data: tData } = await axios.get(`/api/music/tracks/${cat.id}`, { params: { per_page: 1 } })
        trackCounts.value[cat.id] = tData.data?.total || (tData.data?.data || tData.data || []).length
      } catch { trackCounts.value[cat.id] = 0 }
    }
  } catch {}
}

async function selectCategory(cat) {
  activeCat.value = cat
  try {
    const { data } = await axios.get(`/api/music/tracks/${cat.id}`, { params: { per_page: 100 } })
    tracks.value = data.data?.data || data.data || []
  } catch {}
}

async function addCategory() {
  if (!newCat.name) return
  try {
    await axios.post('/api/admin/music/categories', newCat)
    showAddCat.value = false; newCat.name = ''; newCat.slug = ''
    loadCategories()
  } catch {}
}

async function addTrack() {
  if (!newTrack.title || !activeCat.value) return
  try {
    await axios.post('/api/admin/music/tracks', { ...newTrack, category_id: activeCat.value.id })
    showAddTrack.value = false; newTrack.title = ''; newTrack.artist = ''; newTrack.youtube_url = ''
    selectCategory(activeCat.value)
  } catch {}
}

async function deleteTrack(t) {
  if (!confirm('삭제?')) return
  try { await axios.delete(`/api/admin/music/tracks/${t.id}`); tracks.value = tracks.value.filter(x => x.id !== t.id) } catch {}
}

onMounted(loadCategories)
</script>
