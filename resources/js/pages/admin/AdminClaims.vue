<template>
<div>
  <h2 class="text-lg font-bold text-gray-800 mb-4">📋 업소 클레임 관리</h2>

  <div v-if="loading" class="text-center py-12 text-gray-400">로딩중...</div>
  <div v-else-if="!claims.length" class="text-center py-12 text-gray-400">대기 중인 클레임이 없습니다</div>
  <div v-else class="space-y-3">
    <div v-for="c in claims" :key="c.id" class="bg-white rounded-xl shadow-sm border p-4">
      <div class="flex items-start justify-between gap-3">
        <div>
          <div class="flex items-center gap-2 mb-1">
            <span class="text-xs px-2 py-0.5 rounded-full font-bold"
              :class="c.status==='pending'?'bg-amber-100 text-amber-700':c.status==='approved'?'bg-green-100 text-green-700':'bg-red-100 text-red-700'">
              {{ {pending:'⏳ 대기',approved:'✅ 승인',rejected:'❌ 거절'}[c.status] }}
            </span>
            <span class="text-xs text-gray-400">{{ formatDate(c.created_at) }}</span>
          </div>
          <div class="text-sm font-bold text-gray-800">🏪 {{ c.business?.name }}</div>
          <div class="text-xs text-gray-500">{{ c.business?.category }} · {{ c.business?.city }}</div>
          <div class="text-xs text-gray-600 mt-1">👤 {{ c.user?.name }} ({{ c.user?.email }})</div>
          <div class="text-xs text-gray-600">📱 {{ c.notes }}</div>
          <div v-if="c.document_url" class="mt-2">
            <a :href="c.document_url" target="_blank" class="text-xs text-amber-600 hover:underline">📎 증빙서류 보기</a>
          </div>
        </div>
        <div v-if="c.status==='pending'" class="flex gap-2 flex-shrink-0">
          <button @click="approve(c)" class="bg-green-500 text-white font-bold px-3 py-1.5 rounded-lg text-xs hover:bg-green-600">승인</button>
          <button @click="reject(c)" class="bg-red-400 text-white font-bold px-3 py-1.5 rounded-lg text-xs hover:bg-red-500">거절</button>
        </div>
      </div>
    </div>
  </div>
</div>
</template>
<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'

const claims = ref([])
const loading = ref(true)

function formatDate(dt) { return dt ? new Date(dt).toLocaleDateString('ko-KR') : '' }

async function loadClaims() {
  try {
    const { data } = await axios.get('/api/admin/claims')
    claims.value = data.data?.data || data.data || []
  } catch {}
  loading.value = false
}

async function approve(c) {
  if (!confirm(`${c.business?.name} 클레임을 승인하시겠습니까?`)) return
  try {
    await axios.post(`/api/admin/claims/${c.id}/approve`)
    c.status = 'approved'
  } catch (e) { alert(e.response?.data?.message || '실패') }
}

async function reject(c) {
  const reason = prompt('거절 사유를 입력하세요:')
  if (reason === null) return
  try {
    await axios.post(`/api/admin/claims/${c.id}/reject`, { notes: reason })
    c.status = 'rejected'
  } catch (e) { alert(e.response?.data?.message || '실패') }
}

onMounted(loadClaims)
</script>
