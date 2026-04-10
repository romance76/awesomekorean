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
          <button @click="openAddCat" class="text-amber-600 text-xs font-bold hover:text-amber-800">+ 추가</button>
        </div>
        <div v-for="cat in categories" :key="cat.id" class="border-b last:border-0"
          :class="activeCat?.id===cat.id ? 'bg-amber-50' : ''">
          <div class="group flex items-center justify-between px-4 py-3 hover:bg-gray-50 transition">
            <button @click="selectCategory(cat)" class="flex-1 text-left">
              <div class="text-sm font-semibold" :class="activeCat?.id===cat.id ? 'text-amber-700' : 'text-gray-800'">{{ cat.name }}</div>
              <div class="text-[10px] text-gray-400">{{ trackCounts[cat.id] || 0 }}곡</div>
            </button>
            <div class="flex gap-1 opacity-0 group-hover:opacity-100 transition">
              <button @click.stop="openRenameCat(cat)" title="이름 변경"
                class="text-blue-500 hover:text-blue-700 text-xs px-1.5 py-0.5 rounded hover:bg-blue-50">✏️</button>
              <button @click.stop="deleteCategory(cat)" title="삭제"
                class="text-red-500 hover:text-red-700 text-xs px-1.5 py-0.5 rounded hover:bg-red-50">🗑</button>
            </div>
          </div>
        </div>
      </div>

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
          <button @click="showImport = true" class="bg-amber-400 text-amber-900 font-bold px-3 py-1.5 rounded-lg text-xs hover:bg-amber-500">
            📥 YouTube 가져오기
          </button>
        </div>
        <table class="w-full text-sm">
          <thead class="bg-gray-50 border-b"><tr>
            <th class="px-3 py-2 text-left text-xs text-gray-500 w-10">#</th>
            <th class="px-3 py-2 text-left text-xs text-gray-500">제목</th>
            <th class="px-3 py-2 text-left text-xs text-gray-500 w-40">아티스트</th>
            <th class="px-3 py-2 text-xs text-gray-500 w-16">길이</th>
            <th class="px-3 py-2 text-xs text-gray-500 w-12">YouTube</th>
            <th class="px-3 py-2 text-xs text-gray-500 w-12">관리</th>
          </tr></thead>
          <tbody>
            <tr v-for="(t, i) in tracks" :key="t.id" class="border-b last:border-0 hover:bg-amber-50/30">
              <td class="px-3 py-2 text-xs text-gray-400">{{ i+1 }}</td>
              <td class="px-3 py-2 text-sm text-gray-800 max-w-md">
                <div class="truncate">{{ t.title }}</div>
              </td>
              <td class="px-3 py-2 text-xs text-gray-500 truncate max-w-[150px]">{{ t.artist }}</td>
              <td class="px-3 py-2 text-xs text-gray-600 text-center">{{ formatDuration(t.duration) }}</td>
              <td class="px-3 py-2 text-center">
                <a v-if="t.youtube_id" :href="`https://youtube.com/watch?v=${t.youtube_id}`" target="_blank" class="text-red-500 text-xs">▶</a>
              </td>
              <td class="px-3 py-2 text-center">
                <button @click="deleteTrack(t)" class="text-xs text-red-400 hover:text-red-600">🗑</button>
              </td>
            </tr>
          </tbody>
        </table>
        <div v-if="!tracks.length" class="py-8 text-center text-sm text-gray-400">트랙이 없습니다. "📥 YouTube 가져오기"로 추가하세요.</div>
      </div>
    </div>
  </div>

  <!-- ─── 카테고리 추가/수정 모달 ─── -->
  <div v-if="showCatModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" @click.self="showCatModal=false">
    <div class="bg-white rounded-xl p-5 w-full max-w-md shadow-xl">
      <h3 class="font-black text-gray-800 mb-4">{{ catModalMode === 'edit' ? '카테고리 이름 변경' : '새 카테고리' }}</h3>
      <div class="space-y-3">
        <div>
          <label class="text-xs font-bold text-gray-600">카테고리 이름</label>
          <input v-model="catForm.name" placeholder="예: 인디" class="w-full border rounded-lg px-3 py-2 text-sm mt-1 focus:ring-2 focus:ring-amber-400 outline-none" />
        </div>
        <div v-if="catModalMode === 'add'">
          <label class="text-xs font-bold text-gray-600">Slug (영문)</label>
          <input v-model="catForm.slug" placeholder="자동 생성됨" class="w-full border rounded-lg px-3 py-2 text-sm mt-1 focus:ring-2 focus:ring-amber-400 outline-none" />
        </div>
        <div class="text-[10px] text-gray-400">
          카테고리 생성 후 "📥 YouTube 가져오기"로 플레이리스트/채널/링크를 한 번에 불러올 수 있습니다.
        </div>
      </div>
      <div class="flex gap-2 justify-end mt-4">
        <button @click="showCatModal=false" class="text-gray-500 px-4 py-2 text-sm">취소</button>
        <button @click="saveCategory" :disabled="!catForm.name.trim()" class="bg-amber-400 text-amber-900 font-bold px-5 py-2 rounded-lg text-sm disabled:opacity-50">
          {{ catModalMode === 'edit' ? '저장' : '추가' }}
        </button>
      </div>
    </div>
  </div>

  <!-- ─── YouTube 가져오기 모달 (3 모드) ─── -->
  <div v-if="showImport" class="fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4" @click.self="closeImport">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-xl overflow-hidden">
      <div class="bg-gradient-to-r from-amber-50 to-orange-50 px-5 py-4 border-b flex items-center justify-between">
        <div>
          <div class="font-black text-gray-800">📥 YouTube에서 가져오기</div>
          <div class="text-[11px] text-gray-500">카테고리: <strong class="text-amber-700">{{ activeCat?.name }}</strong></div>
        </div>
        <button @click="closeImport" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">✕</button>
      </div>

      <div class="p-5">
        <!-- 모드 선택 -->
        <div class="flex gap-2 mb-4">
          <button @click="importMode='playlist'" class="flex-1 px-3 py-2 rounded-lg text-xs font-bold transition"
            :class="importMode==='playlist' ? 'bg-amber-400 text-amber-900' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'">
            📋 플레이리스트
          </button>
          <button @click="importMode='channel'" class="flex-1 px-3 py-2 rounded-lg text-xs font-bold transition"
            :class="importMode==='channel' ? 'bg-amber-400 text-amber-900' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'">
            📺 채널 전체
          </button>
          <button @click="importMode='urls'" class="flex-1 px-3 py-2 rounded-lg text-xs font-bold transition"
            :class="importMode==='urls' ? 'bg-amber-400 text-amber-900' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'">
            🔗 개별 링크
          </button>
        </div>

        <!-- 입력 필드 -->
        <div v-if="importMode==='playlist'">
          <label class="text-xs font-bold text-gray-700">YouTube 플레이리스트 URL</label>
          <input v-model="importUrl" placeholder="https://www.youtube.com/playlist?list=PLxxxxxx"
            class="w-full border rounded-lg px-3 py-2 text-sm mt-1 focus:ring-2 focus:ring-amber-400 outline-none" />
          <div class="text-[10px] text-gray-400 mt-1">플레이리스트의 모든 영상(최대 200개)을 자동으로 가져옵니다</div>
        </div>

        <div v-else-if="importMode==='channel'">
          <label class="text-xs font-bold text-gray-700">YouTube 채널 URL</label>
          <input v-model="importUrl" placeholder="https://www.youtube.com/@channelname 또는 /channel/UCxxxxx"
            class="w-full border rounded-lg px-3 py-2 text-sm mt-1 focus:ring-2 focus:ring-amber-400 outline-none" />
          <div class="text-[10px] text-gray-400 mt-1">채널의 최근 업로드(최대 200개)를 자동으로 가져옵니다</div>
        </div>

        <div v-else>
          <label class="text-xs font-bold text-gray-700">YouTube 영상 링크 (여러 개 입력 가능)</label>
          <textarea v-model="importUrl" rows="6" placeholder="https://www.youtube.com/watch?v=xxxxx&#10;https://youtu.be/yyyyy&#10;...여러 줄 입력 가능"
            class="w-full border rounded-lg px-3 py-2 text-sm mt-1 focus:ring-2 focus:ring-amber-400 outline-none resize-none font-mono"></textarea>
          <div class="text-[10px] text-gray-400 mt-1">각 줄에 한 개의 링크, 또는 쉼표/공백으로 구분</div>
        </div>

        <!-- 필터 안내 -->
        <div class="mt-4 bg-blue-50 border border-blue-200 rounded-lg p-3 text-[11px] text-blue-800">
          <div class="font-bold mb-1">📌 자동 필터</div>
          <ul class="space-y-0.5 text-blue-700">
            <li>✅ 5분 이하만 저장</li>
            <li>✅ 중국어/일본어/베트남어/인도어/스페인어 자동 제외</li>
            <li>✅ 라이브/믹스(길이 불명) 제외</li>
            <li>✅ 중복 자동 건너뛰기</li>
          </ul>
        </div>

        <!-- 결과 -->
        <div v-if="importResult" class="mt-4 p-3 rounded-lg text-xs"
          :class="importResult.success ? 'bg-green-50 border border-green-200 text-green-800' : 'bg-red-50 border border-red-200 text-red-800'">
          <div class="font-bold mb-1">{{ importResult.success ? '✅ 완료' : '❌ 실패' }}</div>
          <div>{{ importResult.message }}</div>
          <div v-if="importResult.skip_reasons && Object.keys(importResult.skip_reasons).length" class="mt-1">
            제외 사유:
            <span v-for="(cnt, reason) in importResult.skip_reasons" :key="reason" class="ml-1">{{ reason }}({{ cnt }})</span>
          </div>
        </div>
      </div>

      <div class="bg-gray-50 px-5 py-3 border-t flex justify-end gap-2">
        <button @click="closeImport" class="text-gray-500 px-4 py-2 text-sm">닫기</button>
        <button @click="runImport" :disabled="!importUrl.trim() || importing"
          class="bg-amber-400 text-amber-900 font-bold px-5 py-2 rounded-lg text-sm disabled:opacity-50">
          {{ importing ? '가져오는 중...' : '📥 가져오기 시작' }}
        </button>
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
const trackCounts = ref({})

// 카테고리 모달
const showCatModal = ref(false)
const catModalMode = ref('add')  // 'add' | 'edit'
const catForm = reactive({ id: null, name: '', slug: '' })

// YouTube 가져오기 모달
const showImport = ref(false)
const importMode = ref('playlist')  // 'playlist' | 'channel' | 'urls'
const importUrl = ref('')
const importing = ref(false)
const importResult = ref(null)

const totalTracks = computed(() => Object.values(trackCounts.value).reduce((s, n) => s + n, 0))

function formatDuration(sec) {
  if (!sec || sec <= 0) return '-'
  const m = Math.floor(sec / 60)
  const s = sec % 60
  return `${m}:${String(s).padStart(2, '0')}`
}

async function loadCategories() {
  try {
    const { data } = await axios.get('/api/music/categories')
    categories.value = data.data || []
    for (const cat of categories.value) {
      try {
        const { data: tData } = await axios.get(`/api/music/tracks/${cat.id}`, { params: { per_page: 1 } })
        trackCounts.value[cat.id] = tData.data?.total || 0
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

async function fetchMusic() {
  fetching.value = true
  try {
    const { data } = await axios.post('/api/admin/fetch-music')
    alert(data.message || '음악 수집 완료')
    await loadCategories()
    if (activeCat.value) await selectCategory(activeCat.value)
  } catch(e) { alert(e.response?.data?.message || '수집 실패') }
  fetching.value = false
}

// ─── 카테고리 CRUD ───
function openAddCat() {
  catModalMode.value = 'add'
  catForm.id = null
  catForm.name = ''
  catForm.slug = ''
  showCatModal.value = true
}

function openRenameCat(cat) {
  catModalMode.value = 'edit'
  catForm.id = cat.id
  catForm.name = cat.name
  catForm.slug = cat.slug
  showCatModal.value = true
}

async function saveCategory() {
  if (!catForm.name.trim()) return
  try {
    if (catModalMode.value === 'edit') {
      await axios.put(`/api/admin/music/categories/${catForm.id}`, { name: catForm.name, slug: catForm.slug })
    } else {
      await axios.post('/api/admin/music/categories', { name: catForm.name, slug: catForm.slug })
    }
    showCatModal.value = false
    await loadCategories()
  } catch (e) { alert(e.response?.data?.message || '실패') }
}

async function deleteCategory(cat) {
  const count = trackCounts.value[cat.id] || 0
  if (count > 0) {
    if (!confirm(`"${cat.name}"에 ${count}개 트랙이 있습니다. 카테고리와 함께 트랙도 모두 삭제하시겠습니까?`)) return
  } else {
    if (!confirm(`"${cat.name}" 카테고리를 삭제하시겠습니까?`)) return
  }
  try {
    await axios.delete(`/api/admin/music/categories/${cat.id}`, { params: { delete_tracks: true } })
    if (activeCat.value?.id === cat.id) { activeCat.value = null; tracks.value = [] }
    await loadCategories()
  } catch (e) { alert(e.response?.data?.message || '실패') }
}

async function deleteTrack(t) {
  if (!confirm('이 트랙을 삭제하시겠습니까?')) return
  try {
    await axios.delete(`/api/admin/music/tracks/${t.id}`)
    tracks.value = tracks.value.filter(x => x.id !== t.id)
    if (activeCat.value) trackCounts.value[activeCat.value.id] = (trackCounts.value[activeCat.value.id] || 1) - 1
  } catch {}
}

// ─── YouTube 가져오기 ───
function closeImport() {
  showImport.value = false
  importUrl.value = ''
  importResult.value = null
}

async function runImport() {
  if (!importUrl.value.trim() || !activeCat.value) return
  importing.value = true
  importResult.value = null
  try {
    const { data } = await axios.post('/api/admin/music/bulk-import', {
      category_id: activeCat.value.id,
      mode: importMode.value,
      url: importUrl.value,
    })
    importResult.value = data
    if (data.success) {
      await selectCategory(activeCat.value)
      await loadCategories()
    }
  } catch (e) {
    importResult.value = { success: false, message: e.response?.data?.message || '가져오기 실패' }
  }
  importing.value = false
}

onMounted(loadCategories)
</script>
