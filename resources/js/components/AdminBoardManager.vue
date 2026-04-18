<template>
<div>
  <!-- 헤더 -->
  <div class="mb-4 flex items-start justify-between">
    <div>
      <div class="text-xs text-gray-500">관리자 › 게시판 관리 › {{ label }}</div>
      <h1 class="text-2xl font-black text-gray-800 mt-1">{{ icon }} {{ label }} 관리</h1>
      <p class="text-xs text-gray-400 mt-0.5">게시글·카테고리·설정·포인트·배너·신고를 한 페이지에서 관리합니다</p>
    </div>
  </div>

  <!-- 통계 카드 -->
  <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-4">
    <div class="bg-white rounded-lg border p-3">
      <div class="text-[10px] text-gray-500">전체 게시글</div>
      <div class="text-xl font-bold">{{ overview.total || 0 }}</div>
    </div>
    <div class="bg-white rounded-lg border p-3">
      <div class="text-[10px] text-gray-500">오늘 등록</div>
      <div class="text-xl font-bold text-blue-600">{{ overview.today || 0 }}</div>
    </div>
    <div class="bg-white rounded-lg border p-3">
      <div class="text-[10px] text-gray-500">주간 등록</div>
      <div class="text-xl font-bold text-green-600">{{ overview.week || 0 }}</div>
    </div>
    <div class="bg-white rounded-lg border p-3">
      <div class="text-[10px] text-gray-500">신고 대기</div>
      <div class="text-xl font-bold text-red-600">{{ overview.pending_reports || 0 }}</div>
    </div>
    <div class="bg-white rounded-lg border p-3">
      <div class="text-[10px] text-gray-500">광고 활성</div>
      <div class="text-xl font-bold text-purple-600">{{ overview.active_banners || 0 }}</div>
    </div>
  </div>

  <!-- 탭 네비 -->
  <div class="bg-white rounded-t-lg border border-b-0">
    <div class="flex overflow-x-auto">
      <button v-for="t in tabs" :key="t.key" @click="activeTab=t.key"
        class="px-4 py-3 text-sm whitespace-nowrap border-b-2 transition"
        :class="activeTab===t.key ? 'border-amber-500 text-amber-700 font-bold bg-amber-50' : 'border-transparent text-gray-500 hover:text-gray-800'">
        {{ t.label }}
        <span v-if="t.badge" class="ml-1 bg-red-500 text-white text-[10px] px-1.5 rounded-full">{{ t.badge }}</span>
      </button>
    </div>
  </div>

  <!-- 탭 컨텐츠 -->
  <div class="bg-white rounded-b-lg border border-t-0 p-4 min-h-[400px]">
    <!-- 📝 게시글 -->
    <div v-if="activeTab==='posts'">
      <AdminListView :icon="icon" :title="''" :api-url="apiUrl" :delete-url="deleteUrl || apiUrl"
        :extra-cols="extraCols" @open-user="u => $emit('openUser', u)" />
    </div>

    <!-- 📂 카테고리 -->
    <div v-else-if="activeTab==='cat'">
      <div class="flex justify-between items-center mb-3">
        <div class="text-sm text-gray-600">
          {{ label }} 전용 카테고리
          <span v-if="usesTable" class="text-[10px] bg-green-100 text-green-700 px-1.5 py-0.5 rounded ml-1">DB 테이블</span>
          <span v-else class="text-[10px] bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded ml-1">설정 저장</span>
        </div>
        <div class="flex gap-2">
          <button @click="addCategory" class="bg-blue-600 text-white rounded px-3 py-1.5 text-sm">+ 추가</button>
          <button @click="saveCategories" class="bg-amber-400 text-amber-900 font-bold rounded px-3 py-1.5 text-sm">저장</button>
        </div>
      </div>
      <div v-if="categories.length === 0" class="text-center text-gray-400 py-8 text-sm">카테고리가 없습니다. "+ 추가" 버튼을 눌러주세요.</div>
      <div v-else class="space-y-2">
        <div v-for="(c, i) in categories" :key="i" class="flex items-center gap-2 border rounded p-2 bg-gray-50">
          <span class="text-gray-400 cursor-move">☰</span>
          <input v-model="c.icon" placeholder="🏷" class="border rounded px-2 py-1 text-sm w-14 text-center bg-white" />
          <input v-model="c.name" placeholder="이름" class="border rounded px-2 py-1 text-sm flex-1 bg-white" />
          <input v-model="c.slug" placeholder="slug" class="border rounded px-2 py-1 text-sm w-28 bg-white" />
          <label class="text-xs flex items-center gap-1"><input type="checkbox" v-model="c.is_active"> 활성</label>
          <button @click="removeCategory(i)" class="text-red-500 text-xs px-2">삭제</button>
        </div>
      </div>
    </div>

    <!-- ⚙️ 설정 -->
    <div v-else-if="activeTab==='set'">
      <div class="text-sm text-gray-600 mb-3">이 게시판만의 기본 설정입니다. 키 형식: <code class="bg-gray-100 px-1 rounded">board.{{ slug }}.*</code></div>
      <div class="space-y-3">
        <div v-for="(def, key) in settingSchema" :key="key" class="flex items-center gap-3 border-b pb-2">
          <label class="text-sm flex-1">
            <div class="font-medium text-gray-800">{{ def.label }}</div>
            <div class="text-[10px] text-gray-400">{{ key }}</div>
          </label>
          <template v-if="def.type==='bool'">
            <input type="checkbox" v-model="settingValues[key]" class="w-4 h-4" />
          </template>
          <template v-else-if="def.type==='select'">
            <select v-model="settingValues[key]" class="border rounded px-2 py-1 text-sm">
              <option v-for="o in def.options" :key="o.value" :value="o.value">{{ o.label }}</option>
            </select>
          </template>
          <template v-else-if="def.type==='number'">
            <input type="number" v-model.number="settingValues[key]" class="border rounded px-2 py-1 text-sm w-28" />
          </template>
          <template v-else>
            <input type="text" v-model="settingValues[key]" class="border rounded px-2 py-1 text-sm w-56" />
          </template>
        </div>
      </div>
      <div class="mt-4 text-right">
        <button @click="saveSettings" class="bg-amber-400 text-amber-900 font-bold rounded px-4 py-2 text-sm">설정 저장</button>
      </div>
    </div>

    <!-- 💰 포인트 -->
    <div v-else-if="activeTab==='point'">
      <div class="text-sm text-gray-600 mb-3">💡 <strong>{{ label }} 전용</strong> 포인트 규칙. 전역 포인트는 [시스템 › 포인트 설정]에서 관리합니다.</div>
      <table class="w-full text-sm border">
        <thead class="bg-gray-50">
          <tr>
            <th class="p-2 text-left">항목</th>
            <th class="p-2 w-32">포인트 (P)</th>
            <th class="p-2 w-32">일일 한도</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(def, key) in pointSchema" :key="key" class="border-t" :class="def.is_deduction ? 'bg-red-50' : ''">
            <td class="p-2">
              <div class="font-medium">{{ def.label }}</div>
              <div class="text-[10px] text-gray-400">{{ key }}</div>
            </td>
            <td class="p-2"><input type="number" v-model.number="pointValues[key]" class="border rounded px-2 py-1 text-sm w-full" /></td>
            <td class="p-2"><input type="number" v-model.number="pointValues[key + '_daily_max']" class="border rounded px-2 py-1 text-sm w-full" placeholder="-" /></td>
          </tr>
        </tbody>
      </table>
      <div class="mt-4 text-right">
        <button @click="savePoints" class="bg-amber-400 text-amber-900 font-bold rounded px-4 py-2 text-sm">포인트 규칙 저장</button>
      </div>
    </div>

    <!-- 📢 배너/광고 -->
    <div v-else-if="activeTab==='ban'">
      <div class="flex justify-between items-center mb-3">
        <div class="text-sm text-gray-600">
          이 게시판 페이지({{ slug }})에 노출되는 광고 — 실제 <code class="bg-gray-100 px-1 rounded">banner_ads</code> 연동
        </div>
        <router-link to="/admin/banners" class="text-xs text-blue-600 hover:underline">전체 광고 관리 →</router-link>
      </div>
      <div v-if="bannerStats" class="grid grid-cols-5 gap-2 mb-3">
        <div class="bg-gray-50 rounded p-2 text-center"><div class="text-[10px] text-gray-500">전체</div><div class="font-bold">{{ bannerStats.total }}</div></div>
        <div class="bg-green-50 rounded p-2 text-center"><div class="text-[10px] text-gray-500">활성</div><div class="font-bold text-green-700">{{ bannerStats.active }}</div></div>
        <div class="bg-yellow-50 rounded p-2 text-center"><div class="text-[10px] text-gray-500">대기</div><div class="font-bold text-yellow-700">{{ bannerStats.pending }}</div></div>
        <div class="bg-blue-50 rounded p-2 text-center"><div class="text-[10px] text-gray-500">노출</div><div class="font-bold text-blue-700">{{ bannerStats.total_impressions }}</div></div>
        <div class="bg-purple-50 rounded p-2 text-center"><div class="text-[10px] text-gray-500">매출(P)</div><div class="font-bold text-purple-700">{{ bannerStats.total_revenue }}</div></div>
      </div>
      <div v-if="banners.length === 0" class="text-center text-gray-400 py-8 text-sm">이 게시판에 등록된 광고가 없습니다.</div>
      <table v-else class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-600">
          <tr>
            <th class="p-2 text-left">제목</th>
            <th class="p-2 text-left">광고주</th>
            <th class="p-2">위치</th>
            <th class="p-2">슬롯</th>
            <th class="p-2">기간</th>
            <th class="p-2">노출/클릭</th>
            <th class="p-2">비용(P)</th>
            <th class="p-2">상태</th>
            <th class="p-2">관리</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="b in banners" :key="b.id" class="border-t hover:bg-amber-50/30">
            <td class="p-2 font-medium">{{ b.title }}</td>
            <td class="p-2"><button @click="$emit('openUser', b.user)" class="text-blue-600 hover:underline text-xs">{{ b.user?.name }}</button></td>
            <td class="p-2 text-center text-xs">{{ b.position }}</td>
            <td class="p-2 text-center text-xs">{{ b.slot_number ? `S${b.slot_number}` : '-' }}</td>
            <td class="p-2 text-[10px] text-gray-500">{{ b.start_date }} ~ {{ b.end_date }}</td>
            <td class="p-2 text-center text-xs">{{ b.impressions }}/{{ b.clicks }}</td>
            <td class="p-2 text-center">{{ b.total_cost }}</td>
            <td class="p-2 text-center">
              <span class="text-[10px] px-2 py-0.5 rounded" :class="statusClass(b.status)">{{ b.status }}</span>
            </td>
            <td class="p-2 text-center">
              <button v-if="b.status==='pending'" @click="approveBanner(b)" class="text-xs text-green-600 mr-1">승인</button>
              <button v-if="b.status==='pending'" @click="rejectBanner(b)" class="text-xs text-red-600 mr-1">거절</button>
              <button v-if="b.status==='active'" @click="pauseBanner(b)" class="text-xs text-orange-600 mr-1">일시정지</button>
              <button @click="deleteBanner(b)" class="text-xs text-gray-500">삭제</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- 🚨 신고 -->
    <div v-else-if="activeTab==='rep'">
      <div class="text-sm text-gray-600 mb-3">이 게시판에 대한 신고 대기 목록</div>
      <div v-if="reports.length === 0" class="text-center text-gray-400 py-8 text-sm">신고가 없습니다.</div>
      <table v-else class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-600">
          <tr>
            <th class="p-2 text-left">대상</th>
            <th class="p-2 text-left">신고자</th>
            <th class="p-2 text-left">사유</th>
            <th class="p-2">상태</th>
            <th class="p-2">시간</th>
            <th class="p-2">처리</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="r in reports" :key="r.id" class="border-t">
            <td class="p-2 text-xs">#{{ r.reportable_id }}</td>
            <td class="p-2"><button @click="$emit('openUser', r.reporter)" class="text-blue-600 hover:underline text-xs">{{ r.reporter?.name }}</button></td>
            <td class="p-2 text-red-600 text-xs">{{ r.reason }}</td>
            <td class="p-2 text-center"><span class="text-[10px] px-2 py-0.5 rounded bg-gray-100">{{ r.status }}</span></td>
            <td class="p-2 text-[10px] text-gray-400">{{ (r.created_at||'').slice(0,10) }}</td>
            <td class="p-2 text-center">
              <button @click="updateReport(r, 'resolved')" class="text-xs text-green-600 mr-1">해결</button>
              <button @click="updateReport(r, 'rejected')" class="text-xs text-gray-500">기각</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</div>
</template>

<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import axios from 'axios'
import AdminListView from './AdminListView.vue'

const props = defineProps({
  slug:      { type: String, required: true },
  label:     { type: String, required: true },
  icon:      { type: String, default: '📋' },
  apiUrl:    { type: String, required: true },
  deleteUrl: { type: String, default: null },
  extraCols: { type: Array, default: () => [] },
  // 게시판 고유 설정 스키마 (key → { label, type, default, options })
  settingSchema: { type: Object, default: () => ({}) },
  pointSchema:   { type: Object, default: () => ({}) },
})
defineEmits(['openUser'])

const activeTab = ref('posts')
const tabs = computed(() => [
  { key: 'posts', label: '📝 게시글' },
  { key: 'cat',   label: '📂 카테고리' },
  { key: 'set',   label: '⚙️ 설정' },
  { key: 'point', label: '💰 포인트' },
  { key: 'ban',   label: '📢 배너/광고', badge: bannerStats.value?.pending || 0 },
  { key: 'rep',   label: '🚨 신고', badge: overview.value?.pending_reports || 0 },
])

const overview = ref({})
const categories = ref([])
const usesTable = ref(false)
const settingValues = ref({})
const pointValues = ref({})
const banners = ref([])
const bannerStats = ref(null)
const reports = ref([])

async function loadOverview() {
  try {
    const { data } = await axios.get(`/api/admin/board-manager/${props.slug}/overview`)
    overview.value = data.data
  } catch (e) { console.warn('overview load failed', e) }
}

async function loadCategories() {
  try {
    const { data } = await axios.get(`/api/admin/board-manager/${props.slug}/categories`)
    categories.value = (data.data || []).map(c => ({
      id: c.id,
      name: c.name || '',
      slug: c.slug || '',
      icon: c.icon || '',
      is_active: c.is_active !== false,
    }))
    usesTable.value = !!data.uses_table
  } catch (e) { console.warn('categories load failed', e) }
}
function addCategory() { categories.value.push({ name: '', slug: '', icon: '🏷', is_active: true }) }
function removeCategory(i) { categories.value.splice(i, 1) }
async function saveCategories() {
  try {
    await axios.post(`/api/admin/board-manager/${props.slug}/categories`, { categories: categories.value })
    alert('카테고리가 저장되었습니다')
    loadCategories()
  } catch (e) { alert(e.response?.data?.message || '저장 실패') }
}

async function loadSettings() {
  try {
    const { data } = await axios.get(`/api/admin/board-manager/${props.slug}/settings`)
    const raw = data.data || {}
    const vals = {}
    Object.keys(props.settingSchema).forEach(key => {
      const fullKey = `board.${props.slug}.${key}`
      const def = props.settingSchema[key]
      const item = raw[fullKey]
      let val = item?.value
      if (val === undefined || val === null) val = def.default
      if (def.type === 'bool') val = val === true || val === 'true' || val === '1' || val === 1
      if (def.type === 'number') val = Number(val || 0)
      vals[key] = val
    })
    settingValues.value = vals
  } catch (e) { console.warn('settings load failed', e) }
}
async function saveSettings() {
  const payload = {}
  Object.keys(settingValues.value).forEach(key => {
    payload[`board.${props.slug}.${key}`] = settingValues.value[key]
  })
  try {
    await axios.post(`/api/admin/board-manager/${props.slug}/settings`, { settings: payload })
    alert('설정이 저장되었습니다')
  } catch (e) { alert(e.response?.data?.message || '저장 실패') }
}

async function loadPoints() {
  try {
    const { data } = await axios.get(`/api/admin/board-manager/${props.slug}/points`)
    const raw = (data.data || []).reduce((acc, r) => { acc[r.key] = r.value; return acc }, {})
    const vals = {}
    Object.keys(props.pointSchema).forEach(key => {
      const fullKey = `board.${props.slug}.point_${key}`
      vals[key] = Number(raw[fullKey] ?? props.pointSchema[key].default ?? 0)
      vals[key + '_daily_max'] = Number(raw[fullKey + '_daily_max'] ?? props.pointSchema[key].daily_max ?? 0)
    })
    pointValues.value = vals
  } catch (e) { console.warn('points load failed', e) }
}
async function savePoints() {
  const payload = {}
  Object.keys(pointValues.value).forEach(key => {
    const cleanKey = key.replace(/_daily_max$/, '')
    const suffix = key.endsWith('_daily_max') ? '_daily_max' : ''
    payload[`board.${props.slug}.point_${cleanKey}${suffix}`] = pointValues.value[key]
  })
  try {
    await axios.post(`/api/admin/board-manager/${props.slug}/points`, { points: payload })
    alert('포인트 규칙이 저장되었습니다')
  } catch (e) { alert(e.response?.data?.message || '저장 실패') }
}

async function loadBanners() {
  try {
    const { data } = await axios.get(`/api/admin/board-manager/${props.slug}/banners`)
    banners.value = data.data || []
    bannerStats.value = data.stats
  } catch (e) { console.warn('banners load failed', e) }
}
async function approveBanner(b) { await axios.post(`/api/admin/banners/${b.id}/approve`); loadBanners() }
async function rejectBanner(b) {
  const reason = prompt('거절 사유')
  if (!reason) return
  await axios.post(`/api/admin/banners/${b.id}/reject`, { reason })
  loadBanners()
}
async function pauseBanner(b) { await axios.post(`/api/admin/banners/${b.id}/pause`); loadBanners() }
async function deleteBanner(b) {
  if (!confirm('삭제? (활성 광고는 포인트 환불됨)')) return
  await axios.delete(`/api/admin/banners/${b.id}`)
  loadBanners()
}

async function loadReports() {
  try {
    const { data } = await axios.get(`/api/admin/board-manager/${props.slug}/reports`)
    reports.value = data.data?.data || data.data || []
  } catch (e) { console.warn('reports load failed', e) }
}
async function updateReport(r, status) {
  await axios.put(`/api/admin/reports/${r.id}`, { status })
  loadReports()
  loadOverview()
}

function statusClass(s) {
  return {
    active:   'bg-green-100 text-green-700',
    pending:  'bg-yellow-100 text-yellow-700',
    paused:   'bg-orange-100 text-orange-700',
    rejected: 'bg-red-100 text-red-700',
    expired:  'bg-gray-100 text-gray-500',
  }[s] || 'bg-gray-100 text-gray-700'
}

watch(activeTab, (tab) => {
  if (tab === 'cat')   loadCategories()
  if (tab === 'set')   loadSettings()
  if (tab === 'point') loadPoints()
  if (tab === 'ban')   loadBanners()
  if (tab === 'rep')   loadReports()
})

onMounted(() => {
  loadOverview()
  loadBanners() // for badge count
})
</script>
