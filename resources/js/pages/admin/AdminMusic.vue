<template>
<div>
  <div class="flex justify-end gap-2 mb-3">
    <button @click="fetchMusic" :disabled="fetching"
      class="inline-flex items-center gap-1.5 bg-blue-500 text-white font-semibold px-3 py-2 rounded-xl text-sm hover:bg-blue-600 transition-colors disabled:opacity-50">
      <AppIcon name="refresh" :size="14" />{{ fetching ? '수집중...' : 'YouTube 자동수집 (100곡)' }}
    </button>
    <button @click="showImport = true" class="btn-primary px-3 py-2 text-sm">
      <AppIcon name="download" :size="14" />플레이리스트/채널 가져오기
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
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-xl overflow-hidden">
      <div class="px-5 py-4 border-b border-gray-100 flex justify-between items-center">
        <div class="flex items-center gap-2 font-bold text-ink"><span class="icon-chip w-7 h-7 bg-amber-50 text-amber-600"><AppIcon name="download" :size="15" /></span>YouTube에서 가져오기</div>
        <button @click="closeImport" class="text-ink-muted hover:text-ink transition-colors"><AppIcon name="x" :size="20" /></button>
      </div>
      <div class="p-5">
        <div class="mb-3">
          <label class="input-label !text-xs">카테고리</label>
          <select v-model="importCatId" class="input-soft px-3 py-2 mt-1">
            <option :value="null">카테고리 선택</option>
            <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>
        </div>
        <div class="flex gap-1 mb-4 bg-gray-100 rounded-xl p-1">
          <button @click="importMode='playlist'" class="flex-1 inline-flex items-center justify-center gap-1 px-3 py-1.5 rounded-lg text-xs transition-colors"
            :class="importMode==='playlist' ? 'bg-white text-ink font-semibold shadow-sm' : 'text-ink-muted'"><AppIcon name="list" :size="13" />플레이리스트</button>
          <button @click="importMode='channel'" class="flex-1 inline-flex items-center justify-center gap-1 px-3 py-1.5 rounded-lg text-xs transition-colors"
            :class="importMode==='channel' ? 'bg-white text-ink font-semibold shadow-sm' : 'text-ink-muted'"><AppIcon name="monitor" :size="13" />채널</button>
          <button @click="importMode='urls'" class="flex-1 inline-flex items-center justify-center gap-1 px-3 py-1.5 rounded-lg text-xs transition-colors"
            :class="importMode==='urls' ? 'bg-white text-ink font-semibold shadow-sm' : 'text-ink-muted'"><AppIcon name="external-link" :size="13" />URL</button>
        </div>

        <div v-if="importMode==='playlist'">
          <input v-model="importUrl" placeholder="https://www.youtube.com/playlist?list=PLxxxxxx"
            class="input-soft px-3 py-2" />
          <div class="text-[11px] text-ink-faint mt-1">최대 200개, 5분 이하만</div>
        </div>
        <div v-else-if="importMode==='channel'">
          <input v-model="importUrl" placeholder="https://www.youtube.com/@channelname"
            class="input-soft px-3 py-2" />
          <div class="text-[11px] text-ink-faint mt-1">채널 최근 업로드 최대 200개</div>
        </div>
        <div v-else>
          <textarea v-model="importUrl" rows="6" placeholder="https://youtu.be/xxx&#10;https://youtu.be/yyy"
            class="input-soft px-3 py-2 font-mono"></textarea>
        </div>

        <div class="mt-3 bg-blue-50 border border-blue-200 rounded-lg p-2 text-xs text-blue-800 flex items-center gap-1.5">
          <AppIcon name="info" :size="13" />자동 필터: 5분 이하 · 비한국어 제외 · 라이브/믹스 제외 · 중복 스킵
        </div>

        <div v-if="importResult" class="mt-3 p-2 rounded-lg text-xs"
          :class="importResult.success ? 'bg-green-50 text-green-800' : 'bg-red-50 text-red-800'">
          <div class="flex items-center gap-1 font-bold"><AppIcon :name="importResult.success ? 'check' : 'alert-circle'" :size="13" />{{ importResult.success ? '완료' : '실패' }}</div>
          <div>{{ importResult.message }}</div>
        </div>
      </div>
      <div class="bg-gray-50 px-5 py-3 border-t border-gray-100 flex justify-end gap-2">
        <button @click="closeImport" class="btn-ghost px-4 py-2 text-sm">닫기</button>
        <button @click="runImport" :disabled="!importUrl.trim() || !importCatId || importing"
          class="btn-primary px-5 py-2 text-sm">
          <AppIcon name="download" :size="14" />{{ importing ? '가져오는 중...' : '시작' }}
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
import AppIcon from '../../components/AppIcon.vue'

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
