<template>
  <div class="space-y-6">
  <div class="card p-6">
    <div class="flex items-center justify-between mb-4">
      <h3 class="flex items-center gap-2 text-lg font-bold text-ink"><span class="icon-chip w-8 h-8 bg-amber-50 text-amber-600"><AppIcon name="key" :size="16" /></span>API 키 관리</h3>
      <div class="flex gap-2">
        <button @click="runMailTestSend" class="btn-secondary px-4 py-2 text-sm">테스트 메일 보내기</button>
        <button @click="openAddAnalytics" class="btn-secondary px-4 py-2 text-sm">+ 구글 Analytics</button>
        <button @click="showAddApiKey = true" class="btn-primary px-4 py-2">+ 새 API 키</button>
      </div>
    </div>

    <div class="space-y-3">
      <div v-for="key in apiKeys" :key="key.id" class="border border-gray-100 rounded-xl p-4 flex items-center justify-between">
        <div class="flex-1">
          <div class="flex items-center gap-2">
            <span class="text-sm font-bold text-ink">{{ key.name }}</span>
            <span class="badge-blue !text-[11px]">{{ key.service }}</span>
            <span :class="key.is_active ? 'badge-green' : 'badge-red'" class="!text-[11px]">{{ key.is_active ? '활성' : '비활성' }}</span>
          </div>
          <p v-if="key.description" class="text-xs text-ink-muted mt-1">{{ key.description }}</p>
          <div class="flex items-center gap-2 mt-2">
            <code class="text-xs bg-gray-100 px-2 py-1 rounded-lg font-mono text-ink-light">{{ key.showFull ? key.fullKey : key.masked_key }}</code>
            <button @click="toggleReveal(key)" class="text-xs text-amber-600 hover:underline transition-colors">{{ key.showFull ? '숨기기' : '보기' }}</button>
            <button @click="copyKey(key)" class="text-xs text-ink-muted hover:underline transition-colors">복사</button>
          </div>
        </div>
        <div class="flex gap-2">
          <button @click="openEditApiKey(key)" class="text-xs text-blue-600 hover:underline">수정</button>
          <button @click="toggleApiKeyActive(key)" :class="key.is_active ? 'text-amber-600' : 'text-green-600'" class="text-xs hover:underline">{{ key.is_active ? '비활성화' : '활성화' }}</button>
          <button @click="deleteApiKey(key.id)" class="text-xs text-red-600 hover:underline">삭제</button>
        </div>
      </div>
      <div v-if="!apiKeys.length" class="text-center py-8 text-ink-muted text-sm">등록된 API 키가 없습니다</div>
    </div>
  </div>

  <!-- 새 API 키 추가 모달 -->
  <div v-if="showAddApiKey" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center" @click.self="showAddApiKey = false">
    <div class="bg-white rounded-2xl p-6 w-full max-w-md">
      <h3 class="flex items-center gap-2 font-bold text-lg text-ink mb-4"><span class="icon-chip w-8 h-8 bg-amber-50 text-amber-600"><AppIcon name="key" :size="16" /></span>새 API 키 등록</h3>
      <div class="space-y-3">
        <div>
          <label class="input-label !text-xs">서비스 이름</label>
          <input v-model="newApiKey.name" placeholder="예: YouTube Data API v3" class="input-field" />
        </div>
        <div>
          <label class="input-label !text-xs">서비스 코드</label>
          <input v-model="newApiKey.service" placeholder="예: youtube, stripe, openai" class="input-field" />
        </div>
        <div>
          <label class="input-label !text-xs">API 키</label>
          <input v-model="newApiKey.api_key" placeholder="키 입력..." class="input-field font-mono" />
        </div>
        <div>
          <label class="input-label !text-xs">설명 (선택)</label>
          <input v-model="newApiKey.description" placeholder="용도 설명" class="input-field" />
        </div>
        <div class="flex gap-2 pt-2">
          <button @click="showAddApiKey = false" class="btn-secondary flex-1 py-2">취소</button>
          <button @click="saveApiKey" class="btn-primary flex-1 py-2">등록</button>
        </div>
      </div>
    </div>
  </div>

  <!-- API 키 수정 모달 -->
  <div v-if="showEditApiKey" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center" @click.self="showEditApiKey = false">
    <div class="bg-white rounded-2xl p-6 w-full max-w-md">
      <h3 class="flex items-center gap-2 font-bold text-lg text-ink mb-4"><span class="icon-chip w-8 h-8 bg-amber-50 text-amber-600"><AppIcon name="key" :size="16" /></span>API 키 수정</h3>
      <div class="space-y-3">
        <div>
          <label class="input-label !text-xs">서비스 이름</label>
          <input v-model="editApiKey.name" class="input-field" />
        </div>
        <div>
          <label class="input-label !text-xs">서비스 코드</label>
          <input v-model="editApiKey.service" class="input-field" />
        </div>
        <div>
          <label class="input-label !text-xs">API 키</label>
          <input v-model="editApiKey.api_key" placeholder="변경하려면 입력 (비워두면 기존 값 유지)" class="input-field font-mono" />
        </div>
        <div>
          <label class="input-label !text-xs">설명 (선택)</label>
          <input v-model="editApiKey.description" class="input-field" />
        </div>
        <div class="flex gap-2 pt-2">
          <button @click="showEditApiKey = false" class="btn-secondary flex-1 py-2">취소</button>
          <button @click="saveEditApiKey" class="btn-primary flex-1 py-2">저장</button>
        </div>
      </div>
    </div>
  </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import AppIcon from '../../AppIcon.vue'

// API 키 목록·등록·수정·열람 — 다른 탭과 값을 나누지 않아서 불러오기·저장 전부 여기서 한다. (탭을 열 때마다 목록을 새로 불러온다)
const apiKeys = ref([])
const showAddApiKey = ref(false)
const newApiKey = ref({ name: '', service: '', api_key: '', description: '' })
const showEditApiKey = ref(false)
const editApiKey = ref({ id: null, name: '', service: '', api_key: '', description: '' })

async function loadApiKeys() {
  try {
    const { data } = await axios.get('/api/admin/api-keys')
    const list = data.data || data || []
    apiKeys.value = list.map(k => ({ ...k, showFull: false, fullKey: '' }))
  } catch (e) { console.error('loadApiKeys error:', e) }
}

// 구글 Analytics(GA4) 측정 ID — service 코드가 google_analytics 인 키가 있으면(활성일 때)
// 모든 공개 페이지에 방문 통계 추적 코드가 자동으로 들어간다. 비활성화/삭제하면 빠짐.
function openAddAnalytics() {
  newApiKey.value = { name: '구글 Analytics 측정 ID', service: 'google_analytics', api_key: '', description: '방문 통계(GA4). 측정 ID는 G- 로 시작합니다 (예: G-ABC123DEF4)' }
  showAddApiKey.value = true
}

async function saveApiKey() {
  try {
    await axios.post('/api/admin/api-keys', newApiKey.value)
    showAddApiKey.value = false
    newApiKey.value = { name: '', service: '', api_key: '', description: '' }
    loadApiKeys()
  } catch (e) { alert(e.response?.data?.message || '등록 실패') }
}

// api_key는 목록에 마스킹된 값만 내려오므로 빈 칸으로 시작 — 비워두고 저장하면
// 백엔드가 그 필드를 건드리지 않고 기존 값을 그대로 유지함
function openEditApiKey(key) {
  editApiKey.value = { id: key.id, name: key.name, service: key.service, api_key: '', description: key.description || '' }
  showEditApiKey.value = true
}

async function saveEditApiKey() {
  try {
    const payload = { name: editApiKey.value.name, service: editApiKey.value.service, description: editApiKey.value.description }
    if (editApiKey.value.api_key) payload.api_key = editApiKey.value.api_key
    await axios.put('/api/admin/api-keys/' + editApiKey.value.id, payload)
    showEditApiKey.value = false
    loadApiKeys()
  } catch (e) { alert(e.response?.data?.message || '수정 실패') }
}

async function deleteApiKey(id) {
  if (!confirm('정말 삭제하시겠습니까?')) return
  await axios.delete('/api/admin/api-keys/' + id)
  loadApiKeys()
}

async function toggleApiKeyActive(key) {
  await axios.put('/api/admin/api-keys/' + key.id, { is_active: !key.is_active })
  loadApiKeys()
}

async function runMailTestSend() {
  try {
    const { data } = await axios.post('/api/admin/system/mail-test-send')
    alert(data.message)
  } catch (e) { alert('발송 실패 — 실제 에러: ' + (e.response?.data?.message || e.message)) }
}

async function toggleReveal(key) {
  if (!key.showFull) {
    const { data } = await axios.get('/api/admin/api-keys/' + key.id + '/reveal')
    key.fullKey = data.data?.key || data.key || key.api_key || ''
  }
  key.showFull = !key.showFull
}

function copyKey(key) {
  const text = key.showFull ? key.fullKey : key.masked_key
  navigator.clipboard.writeText(key.fullKey || text)
  alert('복사되었습니다!')
}

onMounted(loadApiKeys)
</script>

<style scoped>
/* 사이트 설정 화면과 동일한 소프트 입력 스타일 (SiteSettings.vue 와 같은 값) */
.input-field {
  @apply w-full bg-[#F4F6F8] border-[1.5px] border-transparent rounded-xl px-3 py-2 text-sm text-ink placeholder:text-ink-faint outline-none transition-all duration-150 focus:bg-white focus:border-amber-400 focus:ring-4 focus:ring-amber-400/10;
}
</style>
