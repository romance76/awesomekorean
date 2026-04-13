<template>
<div>
  <h1 class="text-xl font-black text-gray-800 mb-4">📞 통화 내역 관리</h1>

  <!-- 통계 -->
  <div class="grid grid-cols-5 gap-2 mb-4">
    <div class="bg-white rounded-xl border p-3 text-center">
      <div class="text-[10px] text-gray-500">전체</div>
      <div class="text-lg font-black text-gray-800">{{ stats.total || 0 }}</div>
    </div>
    <div class="bg-white rounded-xl border p-3 text-center">
      <div class="text-[10px] text-gray-500">통화 성공</div>
      <div class="text-lg font-black text-green-600">{{ stats.answered || 0 }}</div>
    </div>
    <div class="bg-white rounded-xl border p-3 text-center">
      <div class="text-[10px] text-gray-500">부재중</div>
      <div class="text-lg font-black text-red-600">{{ stats.missed || 0 }}</div>
    </div>
    <div class="bg-white rounded-xl border p-3 text-center">
      <div class="text-[10px] text-gray-500">오늘</div>
      <div class="text-lg font-black text-blue-600">{{ stats.today || 0 }}</div>
    </div>
    <div class="bg-white rounded-xl border p-3 text-center">
      <div class="text-[10px] text-gray-500">평균 통화시간</div>
      <div class="text-lg font-black text-purple-600">{{ formatSec(stats.avg_duration || 0) }}</div>
    </div>
  </div>

  <!-- 필터 -->
  <div class="flex gap-2 mb-4">
    <select v-model="filter" @change="load" class="border rounded-lg px-3 py-1.5 text-xs">
      <option value="">전체 상태</option>
      <option value="ended">통화 완료</option>
      <option value="answered">응답</option>
      <option value="initiated">부재중</option>
    </select>
    <input v-model="search" @keyup.enter="load" placeholder="이름 검색" class="border rounded-lg px-3 py-1.5 text-xs flex-1" />
    <button @click="load" class="bg-amber-400 text-amber-900 font-bold px-4 py-1.5 rounded-lg text-xs">검색</button>
  </div>

  <!-- 목록 -->
  <div v-if="loading" class="text-center py-8 text-gray-400">로딩중...</div>
  <div v-else-if="!calls.length" class="text-center py-8 text-gray-400">통화 기록이 없습니다</div>
  <div v-else class="bg-white rounded-xl border overflow-hidden">
    <table class="w-full text-xs">
      <thead class="bg-gray-50 border-b">
        <tr>
          <th class="px-3 py-2 text-left text-gray-500">ID</th>
          <th class="px-3 py-2 text-center text-gray-500">유형</th>
          <th class="px-3 py-2 text-left text-gray-500">발신자</th>
          <th class="px-3 py-2 text-center text-gray-500">→</th>
          <th class="px-3 py-2 text-left text-gray-500">수신자</th>
          <th class="px-3 py-2 text-center text-gray-500">상태</th>
          <th class="px-3 py-2 text-center text-gray-500">통화시간</th>
          <th class="px-3 py-2 text-right text-gray-500">일시</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="c in calls" :key="c.id" class="border-b last:border-0 hover:bg-amber-50/30">
          <td class="px-3 py-2 text-gray-400">#{{ c.id }}</td>
          <td class="px-3 py-2 text-center">
            <span class="text-[10px] px-2 py-0.5 rounded-full font-bold"
              :class="(c.call_type||'friend')==='elder' ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700'">
              {{ (c.call_type||'friend')==='elder' ? '🛡️안심' : '👫친구' }}
            </span>
          </td>
          <td class="px-3 py-2">
            <div class="flex items-center gap-2">
              <div class="w-6 h-6 rounded-full bg-amber-400 text-white flex items-center justify-center text-[9px] font-bold">{{ (c.caller?.name||'?')[0] }}</div>
              <div>
                <div class="font-bold text-gray-800">{{ c.caller?.nickname || c.caller?.name }}</div>
                <div class="text-[10px] text-gray-400">ID:{{ c.caller_id }}</div>
              </div>
            </div>
          </td>
          <td class="px-3 py-2 text-center text-gray-300">📞→</td>
          <td class="px-3 py-2">
            <div class="flex items-center gap-2">
              <div class="w-6 h-6 rounded-full bg-blue-400 text-white flex items-center justify-center text-[9px] font-bold">{{ (c.callee?.name||'?')[0] }}</div>
              <div>
                <div class="font-bold text-gray-800">{{ c.callee?.nickname || c.callee?.name }}</div>
                <div class="text-[10px] text-gray-400">ID:{{ c.callee_id }}</div>
              </div>
            </div>
          </td>
          <td class="px-3 py-2 text-center">
            <span class="text-[10px] px-2 py-0.5 rounded-full font-bold"
              :class="{'bg-green-100 text-green-700': c.status==='ended'||c.status==='answered', 'bg-red-100 text-red-700': c.status==='initiated', 'bg-gray-100 text-gray-500': c.status==='missed'}">
              {{ {ended:'완료',answered:'응답',initiated:'부재중',missed:'부재중'}[c.status] || c.status }}
            </span>
          </td>
          <td class="px-3 py-2 text-center font-bold" :class="c.duration > 0 ? 'text-green-700' : 'text-gray-400'">
            {{ c.duration > 0 ? formatSec(c.duration) : '-' }}
          </td>
          <td class="px-3 py-2 text-right text-gray-500">{{ fmtDate(c.created_at) }}</td>
        </tr>
      </tbody>
    </table>

    <!-- 페이지네이션 -->
    <div v-if="lastPage > 1" class="flex justify-center gap-1 py-3 border-t">
      <button v-for="p in lastPage" :key="p" @click="page=p; load()" class="w-7 h-7 rounded text-[10px] font-bold"
        :class="p===page?'bg-amber-400 text-amber-900':'text-gray-400 hover:bg-gray-100'">{{ p }}</button>
    </div>
  </div>
</div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'

const calls = ref([])
const stats = ref({})
const loading = ref(true)
const filter = ref('')
const search = ref('')
const page = ref(1)
const lastPage = ref(1)

function formatSec(s) {
  if (!s) return '0초'
  const m = Math.floor(s / 60)
  const sec = s % 60
  return m > 0 ? `${m}분 ${sec}초` : `${sec}초`
}

function fmtDate(dt) {
  if (!dt) return ''
  const d = new Date(dt)
  return `${d.getMonth()+1}/${d.getDate()} ${String(d.getHours()).padStart(2,'0')}:${String(d.getMinutes()).padStart(2,'0')}`
}

async function load() {
  loading.value = true
  try {
    const params = { page: page.value }
    if (filter.value) params.status = filter.value
    if (search.value) params.search = search.value
    const { data } = await axios.get('/api/admin/calls', { params })
    calls.value = data.data?.data || []
    lastPage.value = data.data?.last_page || 1
  } catch {}
  loading.value = false
}

async function loadStats() {
  try { const { data } = await axios.get('/api/admin/calls/stats'); stats.value = data.data || {} } catch {}
}

onMounted(() => { load(); loadStats() })
</script>
