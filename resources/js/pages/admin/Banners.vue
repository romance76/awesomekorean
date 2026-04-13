<template>
<div>
  <h1 class="text-xl font-black text-gray-800 mb-4">📢 광고 관리</h1>

  <div class="grid grid-cols-4 gap-3 mb-4">
    <div class="bg-white rounded-xl border p-3 text-center">
      <div class="text-xs text-gray-500">대기</div>
      <div class="text-lg font-black text-amber-600">{{ items.filter(i=>i.status==='pending').length }}</div>
    </div>
    <div class="bg-white rounded-xl border p-3 text-center">
      <div class="text-xs text-gray-500">게시 중</div>
      <div class="text-lg font-black text-green-600">{{ items.filter(i=>i.status==='active').length }}</div>
    </div>
    <div class="bg-white rounded-xl border p-3 text-center">
      <div class="text-xs text-gray-500">총 수입</div>
      <div class="text-lg font-black text-blue-600">{{ items.reduce((s,i)=>s+i.total_cost,0).toLocaleString() }}P</div>
    </div>
    <div class="bg-white rounded-xl border p-3 text-center">
      <div class="text-xs text-gray-500">총 클릭</div>
      <div class="text-lg font-black text-purple-600">{{ items.reduce((s,i)=>s+(i.clicks||0),0).toLocaleString() }}</div>
    </div>
  </div>

  <div v-if="loading" class="text-center py-8 text-gray-400">로딩중...</div>
  <div v-else-if="!items.length" class="text-center py-8 text-gray-400">배너 신청 없음</div>
  <div v-else class="space-y-3">
    <div v-for="item in items" :key="item.id" class="bg-white rounded-xl border p-4">
      <div class="flex gap-4">
        <div class="w-48 h-24 rounded-lg overflow-hidden bg-gray-100 flex-shrink-0">
          <img :src="item.image_url" class="w-full h-full object-cover" />
        </div>
        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-2 mb-1 flex-wrap">
            <span class="text-xs px-2 py-0.5 rounded-full font-bold" :class="{'bg-amber-100 text-amber-700':item.status==='pending','bg-green-100 text-green-700':item.status==='active','bg-red-100 text-red-700':item.status==='rejected','bg-gray-200 text-gray-500':item.status==='expired'||item.status==='paused'}">{{ {pending:'대기',active:'게시중',rejected:'거절',expired:'만료',paused:'중지'}[item.status] }}</span>
            <span class="text-[10px] bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded-full">{{ {home:'홈',market:'장터',jobs:'구인',directory:'업소록',news:'뉴스',qa:'Q&A',recipes:'레시피',community:'커뮤니티',all:'전체'}[item.page] }}</span>
            <span class="text-[10px] bg-purple-100 text-purple-700 px-1.5 py-0.5 rounded-full">{{ {top:'상단',left:'좌측',center:'중앙',right:'우측'}[item.position] }}</span>
          </div>
          <div class="font-bold text-sm text-gray-800 truncate">{{ item.title }}</div>
          <div class="text-xs text-gray-400 mt-1">{{ item.user?.name }} · {{ item.start_date?.slice(0,10) }} ~ {{ item.end_date?.slice(0,10) }} · {{ item.total_cost }}P · 노출{{ item.impressions }} · 클릭{{ item.clicks }}</div>
        </div>
        <div class="flex flex-col gap-1 flex-shrink-0">
          <button v-if="item.status==='pending'" @click="approve(item)" class="text-xs bg-green-500 text-white px-3 py-1.5 rounded font-bold">승인</button>
          <button v-if="item.status==='pending'" @click="reject(item)" class="text-xs bg-red-500 text-white px-3 py-1.5 rounded font-bold">거절</button>
          <button v-if="item.status==='active'" @click="pause(item)" class="text-xs bg-gray-200 text-gray-700 px-3 py-1.5 rounded font-bold">중지</button>
          <button @click="remove(item)" class="text-xs text-red-400 hover:text-red-600">삭제</button>
        </div>
      </div>
    </div>
  </div>
</div>
</template>
<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
const items = ref([]); const loading = ref(true)
async function load() { try { const{data}=await axios.get('/api/admin/banners'); items.value=data.data||[] }catch{}; loading.value=false }
async function approve(item) { try { await axios.post(`/api/admin/banners/${item.id}/approve`); item.status='active' }catch(e){ alert(e.response?.data?.message||'실패') } }
async function reject(item) { const r=prompt('거절 사유:'); if(!r)return; try{await axios.post(`/api/admin/banners/${item.id}/reject`,{reason:r}); item.status='rejected'}catch{} }
async function pause(item) { try{await axios.post(`/api/admin/banners/${item.id}/pause`); item.status='paused'}catch{} }
async function remove(item) { if(!confirm('삭제?'))return; try{await axios.delete(`/api/admin/banners/${item.id}`); items.value=items.value.filter(i=>i.id!==item.id)}catch{} }
onMounted(load)
</script>
