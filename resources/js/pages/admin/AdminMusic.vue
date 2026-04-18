<template>
<div>
  <div class="flex justify-end gap-2 mb-3">
    <button @click="fetchMusic" :disabled="fetching"
      class="bg-blue-500 text-white font-bold px-3 py-2 rounded-lg text-sm hover:bg-blue-600 disabled:opacity-50">
      {{ fetching ? '수집중...' : '🔄 YouTube 자동수집 (100곡)' }}
    </button>
    <button @click="showImport = true" class="bg-amber-400 text-amber-900 font-bold px-3 py-2 rounded-lg text-sm hover:bg-amber-500">
      📥 플레이리스트/채널 가져오기
    </button>
  </div>

  <AdminBoardManager
    slug="music"
    label="음악"
    icon="🎵"
    api-url="/api/admin/music/tracks"
    delete-url="/api/admin/music/tracks"
    :extra-cols='[{"key":"artist","label":"아티스트"},{"key":"category_id","label":"카테고리"},{"key":"duration","label":"초"},{"key":"youtube_id","label":"YouTube"}]'
    :setting-schema="settingSchema"
    :point-schema="pointSchema"
    @open-user="u => { selectedUserId = u?.id; showUser = true }"
  />
  <AdminUserModal :show="showUser" :user-id="selectedUserId" @close="showUser=false" />

  <!-- YouTube 가져오기 모달 (플레이리스트/채널/개별 URL) -->
  <div v-if="showImport" class="fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4" @click.self="closeImport">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-xl overflow-hidden">
      <div class="bg-gradient-to-r from-amber-50 to-orange-50 px-5 py-4 border-b flex justify-between items-center">
        <div class="font-black text-gray-800">📥 YouTube에서 가져오기</div>
        <button @click="closeImport" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">✕</button>
      </div>
      <div class="p-5">
        <div class="mb-3">
          <label class="text-xs font-bold text-gray-700">카테고리</label>
          <select v-model="importCatId" class="w-full border rounded-lg px-3 py-2 text-sm mt-1">
            <option :value="null">카테고리 선택</option>
            <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>
        </div>
        <div class="flex gap-2 mb-4">
          <button @click="importMode='playlist'" class="flex-1 px-3 py-2 rounded-lg text-xs font-bold"
            :class="importMode==='playlist' ? 'bg-amber-400 text-amber-900' : 'bg-gray-100 text-gray-600'">📋 플레이리스트</button>
          <button @click="importMode='channel'" class="flex-1 px-3 py-2 rounded-lg text-xs font-bold"
            :class="importMode==='channel' ? 'bg-amber-400 text-amber-900' : 'bg-gray-100 text-gray-600'">📺 채널</button>
          <button @click="importMode='urls'" class="flex-1 px-3 py-2 rounded-lg text-xs font-bold"
            :class="importMode==='urls' ? 'bg-amber-400 text-amber-900' : 'bg-gray-100 text-gray-600'">🔗 URL</button>
        </div>

        <div v-if="importMode==='playlist'">
          <input v-model="importUrl" placeholder="https://www.youtube.com/playlist?list=PLxxxxxx"
            class="w-full border rounded-lg px-3 py-2 text-sm" />
          <div class="text-[10px] text-gray-400 mt-1">최대 200개, 5분 이하만</div>
        </div>
        <div v-else-if="importMode==='channel'">
          <input v-model="importUrl" placeholder="https://www.youtube.com/@channelname"
            class="w-full border rounded-lg px-3 py-2 text-sm" />
          <div class="text-[10px] text-gray-400 mt-1">채널 최근 업로드 최대 200개</div>
        </div>
        <div v-else>
          <textarea v-model="importUrl" rows="6" placeholder="https://youtu.be/xxx&#10;https://youtu.be/yyy"
            class="w-full border rounded-lg px-3 py-2 text-sm font-mono resize-none"></textarea>
        </div>

        <div class="mt-3 bg-blue-50 border border-blue-200 rounded p-2 text-[10px] text-blue-800">
          📌 자동 필터: 5분 이하 · 비한국어 제외 · 라이브/믹스 제외 · 중복 스킵
        </div>

        <div v-if="importResult" class="mt-3 p-2 rounded text-xs"
          :class="importResult.success ? 'bg-green-50 text-green-800' : 'bg-red-50 text-red-800'">
          <div class="font-bold">{{ importResult.success ? '✅ 완료' : '❌ 실패' }}</div>
          <div>{{ importResult.message }}</div>
        </div>
      </div>
      <div class="bg-gray-50 px-5 py-3 border-t flex justify-end gap-2">
        <button @click="closeImport" class="text-gray-500 px-4 py-2 text-sm">닫기</button>
        <button @click="runImport" :disabled="!importUrl.trim() || !importCatId || importing"
          class="bg-amber-400 text-amber-900 font-bold px-5 py-2 rounded text-sm disabled:opacity-50">
          {{ importing ? '가져오는 중...' : '📥 시작' }}
        </button>
      </div>
    </div>
  </div>
</div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import AdminBoardManager from '../../components/AdminBoardManager.vue'
import AdminUserModal from '../../components/AdminUserModal.vue'

const showUser = ref(false)
const selectedUserId = ref(null)
const fetching = ref(false)
const categories = ref([])

const showImport = ref(false)
const importCatId = ref(null)
const importMode = ref('playlist')
const importUrl = ref('')
const importing = ref(false)
const importResult = ref(null)

async function loadCategoriesForImport() {
  try { const { data } = await axios.get('/api/music/categories'); categories.value = data.data || [] }
  catch {}
}

async function fetchMusic() {
  fetching.value = true
  try {
    const { data } = await axios.post('/api/admin/fetch-music')
    alert(data.message || '음악 수집 완료')
  } catch (e) { alert(e.response?.data?.message || '수집 실패') }
  fetching.value = false
}

function closeImport() {
  showImport.value = false
  importUrl.value = ''
  importResult.value = null
}

async function runImport() {
  if (!importUrl.value.trim() || !importCatId.value) return
  importing.value = true; importResult.value = null
  try {
    const { data } = await axios.post('/api/admin/music/bulk-import', {
      category_id: importCatId.value,
      mode: importMode.value,
      url: importUrl.value,
    })
    importResult.value = data
  } catch (e) { importResult.value = { success: false, message: e.response?.data?.message || '실패' } }
  importing.value = false
}

const settingSchema = {
  enabled:           { label: '음악 기능 활성화',           type: 'bool',   default: true },
  allow_user_submit: { label: '일반 회원 트랙 제출 허용',    type: 'bool',   default: true },
  auto_fetch_daily:  { label: '매일 YouTube 자동 수집',      type: 'bool',   default: true },
  max_duration:      { label: '최대 길이 (초)',              type: 'number', default: 300 },
  filter_languages:  { label: '비한국어 자동 제외',          type: 'bool',   default: true },
  allow_explicit:    { label: '성인 콘텐츠 허용',            type: 'bool',   default: false },
}

const pointSchema = {
  track_submit:  { label: '트랙 제출',              default: 5,  daily_max: 3 },
  track_play:    { label: '재생 보상 (청자)',        default: 1,  daily_max: 30 },
  track_liked:   { label: '트랙 좋아요 받기 (제출자)', default: 1, daily_max: 50 },
  reported:      { label: '신고 당함 (-차감)',       is_deduction: true, default: -5, daily_max: 0 },
}

onMounted(() => loadCategoriesForImport())
</script>
