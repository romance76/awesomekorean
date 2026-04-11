<template>
<div>
  <div class="flex items-center justify-between mb-4">
    <h1 class="text-xl font-black text-gray-800">🍳 레시피 관리</h1>
    <div class="flex gap-2">
      <span class="text-xs text-gray-500 self-center">전체 {{ stats.total || 0 }}개 · 활성 {{ stats.active || 0 }}</span>
    </div>
  </div>

  <!-- 탭 -->
  <div class="flex gap-0 border-b mb-4">
    <button @click="tab='list'"
      :class="tab==='list' ? 'border-amber-500 text-amber-700' : 'border-transparent text-gray-400 hover:text-gray-600'"
      class="px-4 py-2 text-sm font-bold border-b-2 transition">📋 레시피 목록</button>
    <button @click="tab='api'"
      :class="tab==='api' ? 'border-amber-500 text-amber-700' : 'border-transparent text-gray-400 hover:text-gray-600'"
      class="px-4 py-2 text-sm font-bold border-b-2 transition">🔄 API 관리</button>
  </div>

  <!-- ─── 📋 레시피 목록 탭 ─── -->
  <div v-if="tab === 'list'">
    <!-- 검색/필터 -->
    <div class="bg-white rounded-xl shadow-sm border p-3 mb-4 flex flex-wrap gap-2">
      <input v-model="search" @keyup.enter="loadList(1)" placeholder="레시피명 검색..."
        class="flex-1 min-w-[200px] border rounded px-3 py-1.5 text-sm" />
      <select v-model="filterCat" @change="loadList(1)" class="border rounded px-3 py-1.5 text-sm">
        <option value="">전체 카테고리</option>
        <option v-for="c in categories" :key="c.category" :value="c.category">{{ c.category }} ({{ c.count }})</option>
      </select>
      <select v-model="filterStatus" @change="loadList(1)" class="border rounded px-3 py-1.5 text-sm">
        <option value="">전체 상태</option>
        <option value="1">활성</option>
        <option value="0">비활성</option>
      </select>
      <button @click="loadList(1)" class="bg-amber-400 text-amber-900 font-bold px-3 py-1.5 rounded text-xs">검색</button>
      <button v-if="selected.length" @click="bulkDelete"
        class="bg-red-500 text-white font-bold px-3 py-1.5 rounded text-xs">선택 삭제 ({{ selected.length }})</button>
    </div>

    <!-- 테이블 -->
    <div v-if="listLoading" class="text-center py-8 text-gray-400">로딩중...</div>
    <div v-else-if="!list.length" class="text-center py-12 text-gray-400">레시피가 없습니다. "API 관리" 탭에서 동기화하세요.</div>
    <div v-else class="bg-white rounded-xl shadow-sm border overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b">
          <tr>
            <th class="px-3 py-2 w-8"><input type="checkbox" @change="toggleAll($event)" /></th>
            <th class="px-3 py-2 text-left text-xs text-gray-500">ID</th>
            <th class="px-3 py-2 text-left text-xs text-gray-500">레시피명</th>
            <th class="px-3 py-2 text-left text-xs text-gray-500">카테고리</th>
            <th class="px-3 py-2 text-left text-xs text-gray-500">조리법</th>
            <th class="px-3 py-2 text-center text-xs text-gray-500">칼로리</th>
            <th class="px-3 py-2 text-center text-xs text-gray-500">조회</th>
            <th class="px-3 py-2 text-center text-xs text-gray-500">상태</th>
            <th class="px-3 py-2 text-center text-xs text-gray-500">관리</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="r in list" :key="r.id" class="border-b last:border-0 hover:bg-amber-50/30">
            <td class="px-3 py-2"><input type="checkbox" :value="r.id" v-model="selected" /></td>
            <td class="px-3 py-2 text-[10px] text-gray-400">{{ r.id }}</td>
            <td class="px-3 py-2">
              <div class="flex items-center gap-2">
                <img v-if="r.thumbnail" :src="r.thumbnail" class="w-10 h-10 object-cover rounded flex-shrink-0" @error="$event.target.style.display='none'" />
                <div class="text-sm font-medium text-gray-800 truncate max-w-xs">{{ r.title }}</div>
              </div>
            </td>
            <td class="px-3 py-2">
              <span v-if="r.category" class="text-[10px] bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full font-bold">{{ r.category }}</span>
            </td>
            <td class="px-3 py-2 text-xs text-gray-500">{{ r.cook_method || '-' }}</td>
            <td class="px-3 py-2 text-xs text-gray-500 text-center">{{ r.calories || '-' }}</td>
            <td class="px-3 py-2 text-xs text-gray-400 text-center">{{ r.view_count || 0 }}</td>
            <td class="px-3 py-2 text-center">
              <button @click="toggleStatus(r)"
                :class="r.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                class="text-[10px] px-2 py-1 rounded-full font-bold">
                {{ r.is_active ? '활성' : '비활성' }}
              </button>
            </td>
            <td class="px-3 py-2 text-center">
              <button @click="openEdit(r)" class="text-blue-500 hover:text-blue-700 text-xs mr-2">수정</button>
              <button @click="deleteOne(r.id)" class="text-red-500 hover:text-red-700 text-xs">삭제</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="lastPage > 1" class="flex justify-center gap-1.5 mt-4">
      <button v-for="pg in Math.min(lastPage, 10)" :key="pg" @click="loadList(pg)"
        class="px-3 py-1 rounded text-sm" :class="pg===page?'bg-amber-400 text-amber-900 font-bold':'bg-white border text-gray-600'">{{ pg }}</button>
    </div>
  </div>

  <!-- ─── 🔄 API 관리 탭 ─── -->
  <div v-if="tab === 'api'" class="space-y-4">
    <!-- 상태 카드 -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
      <div class="bg-white rounded-xl shadow-sm border p-3">
        <div class="text-[10px] text-gray-500">API 상태</div>
        <div class="text-lg font-black mt-1" :class="apiStatus.success ? 'text-green-600' : 'text-red-500'">
          {{ testing ? '확인중...' : (apiStatus.success ? '✅ 정상' : '❌ 오류') }}
        </div>
      </div>
      <div class="bg-white rounded-xl shadow-sm border p-3">
        <div class="text-[10px] text-gray-500">API 총 레시피</div>
        <div class="text-lg font-black text-gray-800 mt-1">{{ apiStatus.total?.toLocaleString() || '-' }}</div>
      </div>
      <div class="bg-white rounded-xl shadow-sm border p-3">
        <div class="text-[10px] text-gray-500">DB 저장됨</div>
        <div class="text-lg font-black text-amber-600 mt-1">{{ stats.total?.toLocaleString() || 0 }}</div>
      </div>
      <div class="bg-white rounded-xl shadow-sm border p-3">
        <div class="text-[10px] text-gray-500">활성 레시피</div>
        <div class="text-lg font-black text-green-600 mt-1">{{ stats.active?.toLocaleString() || 0 }}</div>
      </div>
    </div>

    <!-- API 정보 + 연결 테스트 -->
    <div class="bg-white rounded-xl shadow-sm border p-4">
      <h3 class="text-sm font-black text-gray-800 mb-3">📡 식품안전나라 API</h3>
      <div class="text-xs text-gray-600 space-y-1 mb-3">
        <div><strong>인증키:</strong> <code class="bg-gray-100 px-1 rounded">e3ffc744a3fb41299c10</code></div>
        <div><strong>서비스:</strong> <code class="bg-gray-100 px-1 rounded">COOKRCP01</code></div>
        <div><strong>URL:</strong> <code class="bg-gray-100 px-1 rounded">openapi.foodsafetykorea.go.kr</code></div>
      </div>
      <button @click="testConnection" :disabled="testing"
        class="bg-blue-500 text-white font-bold px-4 py-2 rounded-lg text-xs hover:bg-blue-600 disabled:opacity-50">
        {{ testing ? '테스트 중...' : '🔌 API 연결 테스트' }}
      </button>
    </div>

    <!-- 동기화 컨트롤 -->
    <div class="bg-white rounded-xl shadow-sm border p-4">
      <h3 class="text-sm font-black text-gray-800 mb-3">🔄 레시피 동기화</h3>
      <div class="flex flex-wrap items-center gap-2 mb-3">
        <label class="text-xs text-gray-600">시작</label>
        <input v-model.number="syncStart" type="number" min="1" class="border rounded px-2 py-1 text-xs w-20" />
        <label class="text-xs text-gray-600">끝</label>
        <input v-model.number="syncEnd" type="number" min="1" class="border rounded px-2 py-1 text-xs w-20" />
        <button @click="syncRange" :disabled="syncing"
          class="bg-amber-400 text-amber-900 font-bold px-3 py-1.5 rounded text-xs disabled:opacity-50">
          {{ syncing ? '동기화 중...' : '범위 동기화' }}
        </button>
        <button @click="syncAll" :disabled="syncing"
          class="bg-green-500 text-white font-bold px-3 py-1.5 rounded text-xs disabled:opacity-50">
          ⚡ 전체 동기화 (1~1000)
        </button>
        <button @click="clearAll" :disabled="syncing"
          class="bg-red-500 text-white font-bold px-3 py-1.5 rounded text-xs disabled:opacity-50 ml-auto">
          🗑 전체 삭제
        </button>
      </div>
      <div v-if="syncResult" class="rounded-lg p-3 text-xs"
        :class="syncResult.success ? 'bg-green-50 border border-green-200 text-green-800' : 'bg-red-50 border border-red-200 text-red-800'">
        <div class="font-bold">{{ syncResult.success ? '✅ 완료' : '❌ 오류' }}</div>
        <div class="mt-1" v-if="syncResult.success">
          저장: <strong>{{ syncResult.saved || syncResult.total_saved || 0 }}</strong>개
          <span v-if="syncResult.skipped !== undefined">· 중복 스킵: {{ syncResult.skipped }}개</span>
        </div>
        <div v-else class="mt-1">{{ syncResult.error || '알 수 없는 오류' }}</div>
      </div>
    </div>

    <!-- 카테고리별 통계 -->
    <div v-if="stats.categories && Object.keys(stats.categories).length" class="bg-white rounded-xl shadow-sm border p-4">
      <h3 class="text-sm font-black text-gray-800 mb-3">📊 카테고리별 현황</h3>
      <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2">
        <div v-for="(count, cat) in stats.categories" :key="cat"
          class="flex items-center justify-between px-3 py-2 bg-amber-50 rounded">
          <span class="text-xs font-semibold text-gray-700">{{ cat }}</span>
          <span class="text-[10px] bg-amber-200 text-amber-800 px-2 py-0.5 rounded-full font-bold">{{ count }}</span>
        </div>
      </div>
    </div>
  </div>

  <!-- 수정 모달 -->
  <div v-if="editTarget" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" @click.self="editTarget=null">
    <div class="bg-white rounded-xl p-5 w-full max-w-md space-y-3">
      <h3 class="font-black text-gray-800">레시피 수정</h3>
      <div>
        <label class="text-xs text-gray-500">레시피명</label>
        <input v-model="editTarget.title" class="w-full border rounded px-3 py-2 text-sm mt-1" />
      </div>
      <div>
        <label class="text-xs text-gray-500">카테고리</label>
        <input v-model="editTarget.category" class="w-full border rounded px-3 py-2 text-sm mt-1" />
      </div>
      <div>
        <label class="text-xs text-gray-500">조리법</label>
        <input v-model="editTarget.cook_method" class="w-full border rounded px-3 py-2 text-sm mt-1" />
      </div>
      <div>
        <label class="text-xs text-gray-500">칼로리</label>
        <input v-model="editTarget.calories" class="w-full border rounded px-3 py-2 text-sm mt-1" />
      </div>
      <div class="flex justify-end gap-2 pt-2">
        <button @click="editTarget=null" class="text-gray-500 px-4 py-2 text-sm">취소</button>
        <button @click="saveEdit" class="bg-amber-400 text-amber-900 font-bold px-4 py-2 rounded text-sm">저장</button>
      </div>
    </div>
  </div>
</div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'

const tab = ref('list')
const stats = ref({})
const apiStatus = ref({})
const testing = ref(false)
const syncing = ref(false)
const syncStart = ref(1)
const syncEnd = ref(100)
const syncResult = ref(null)

// 리스트
const list = ref([])
const listLoading = ref(false)
const page = ref(1)
const lastPage = ref(1)
const search = ref('')
const filterCat = ref('')
const filterStatus = ref('')
const categories = ref([])
const selected = ref([])
const editTarget = ref(null)

async function loadStats() {
  try { const { data } = await axios.get('/api/admin/recipes/stats'); stats.value = data.data || data } catch {}
}

async function loadCategories() {
  try { const { data } = await axios.get('/api/recipes/categories'); categories.value = data.data || [] } catch {}
}

async function loadList(p = 1) {
  listLoading.value = true
  page.value = p
  const params = { page: p }
  if (search.value) params.search = search.value
  if (filterCat.value) params.category = filterCat.value
  if (filterStatus.value !== '') params.status = filterStatus.value
  try {
    const { data } = await axios.get('/api/admin/recipes', { params })
    list.value = data.data?.data || []
    lastPage.value = data.data?.last_page || 1
  } catch {}
  listLoading.value = false
}

function toggleAll(e) {
  selected.value = e.target.checked ? list.value.map(r => r.id) : []
}

async function toggleStatus(r) {
  try {
    await axios.patch(`/api/admin/recipes/${r.id}/toggle`)
    r.is_active = !r.is_active
    loadStats()
  } catch (e) { alert(e.response?.data?.message || '실패') }
}

function openEdit(r) { editTarget.value = { ...r } }

async function saveEdit() {
  try {
    await axios.put(`/api/admin/recipes/${editTarget.value.id}`, editTarget.value)
    const idx = list.value.findIndex(r => r.id === editTarget.value.id)
    if (idx >= 0) list.value[idx] = { ...list.value[idx], ...editTarget.value }
    editTarget.value = null
  } catch (e) { alert(e.response?.data?.message || '실패') }
}

async function deleteOne(id) {
  if (!confirm('이 레시피를 삭제할까요?')) return
  try {
    await axios.delete(`/api/admin/recipes/${id}`)
    list.value = list.value.filter(r => r.id !== id)
    loadStats()
  } catch (e) { alert(e.response?.data?.message || '실패') }
}

async function bulkDelete() {
  if (!confirm(`${selected.value.length}개 레시피를 삭제할까요?`)) return
  try {
    await axios.delete('/api/admin/recipes/bulk-delete', { data: { ids: selected.value } })
    list.value = list.value.filter(r => !selected.value.includes(r.id))
    selected.value = []
    loadStats()
  } catch (e) { alert(e.response?.data?.message || '실패') }
}

async function testConnection() {
  testing.value = true
  try {
    const { data } = await axios.get('/api/admin/recipes/test-connection')
    apiStatus.value = data
  } catch (e) { apiStatus.value = { success: false, error: e.message } }
  testing.value = false
}

async function syncRange() {
  syncing.value = true
  syncResult.value = null
  try {
    const { data } = await axios.post('/api/admin/recipes/sync', { start: syncStart.value, end: syncEnd.value })
    syncResult.value = data
  } catch (e) { syncResult.value = { success: false, error: e.response?.data?.message || e.message } }
  syncing.value = false
  loadStats()
  if (tab.value === 'list') loadList(1)
}

async function syncAll() {
  if (!confirm('전체 동기화(1~1000)는 시간이 걸립니다. 진행할까요?')) return
  syncing.value = true
  syncResult.value = null
  try {
    const { data } = await axios.post('/api/admin/recipes/sync-all')
    syncResult.value = data
  } catch (e) { syncResult.value = { success: false, error: e.response?.data?.message || e.message } }
  syncing.value = false
  loadStats()
  if (tab.value === 'list') loadList(1)
}

async function clearAll() {
  if (!confirm('DB의 모든 레시피를 삭제합니다. 계속할까요?')) return
  if (!confirm('정말로 모든 레시피를 삭제하시겠습니까? 되돌릴 수 없습니다.')) return
  try {
    const { data } = await axios.post('/api/admin/recipes/clear-all')
    alert(`${data.deleted}개 삭제됨`)
    loadStats()
    loadList(1)
  } catch (e) { alert(e.response?.data?.message || '실패') }
}

onMounted(() => {
  loadStats()
  loadCategories()
  loadList()
  testConnection()
})
</script>
