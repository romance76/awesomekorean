<template>
<div class="min-h-screen bg-gray-50">
  <div class="max-w-7xl mx-auto px-4 py-5">
    <button @click="$router.back()" class="text-sm text-gray-500 hover:text-amber-600 mb-3">← 부동산 목록</button>
    <div v-if="loading" class="text-center py-12 text-gray-400">로딩중...</div>
    <div v-else-if="listing" class="grid grid-cols-12 gap-4">
      <div class="col-span-12 lg:col-span-9 bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
      <div class="px-5 py-4">
        <div class="flex items-center gap-2 mb-2">
          <span class="text-xs px-2 py-0.5 rounded-full font-bold" :class="listing.type==='sale'?'bg-red-100 text-red-700':listing.type==='rent'?'bg-blue-100 text-blue-700':'bg-green-100 text-green-700'">
            {{ {rent:'렌트',sale:'매매',roommate:'룸메이트'}[listing.type] }}
          </span>
          <span class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full">{{ listing.property_type }}</span>
        </div>
        <h1 class="text-lg font-bold text-gray-900">🏠 {{ listing.title }}</h1>
        <div class="text-2xl font-black text-amber-600 mt-2">${{ Number(listing.price).toLocaleString() }}{{ listing.type==='rent'?'/월':'' }}</div>
        <div class="grid grid-cols-4 gap-3 mt-3 text-center">
          <div class="bg-gray-50 rounded-lg py-2"><div class="text-lg font-bold text-gray-800">{{ listing.bedrooms || '-' }}</div><div class="text-[10px] text-gray-500">방</div></div>
          <div class="bg-gray-50 rounded-lg py-2"><div class="text-lg font-bold text-gray-800">{{ listing.bathrooms || '-' }}</div><div class="text-[10px] text-gray-500">화장실</div></div>
          <div class="bg-gray-50 rounded-lg py-2"><div class="text-lg font-bold text-gray-800">{{ listing.sqft || '-' }}</div><div class="text-[10px] text-gray-500">sqft</div></div>
          <div class="bg-gray-50 rounded-lg py-2"><div class="text-lg font-bold text-gray-800">{{ listing.view_count }}</div><div class="text-[10px] text-gray-500">조회</div></div>
        </div>
      </div>
      <div class="px-5 py-3 border-t text-sm text-gray-600">
        <div>📍 {{ listing.address }}, {{ listing.city }}, {{ listing.state }} {{ listing.zipcode }}</div>
        <div v-if="listing.deposit" class="mt-1">💰 보증금: ${{ Number(listing.deposit).toLocaleString() }}</div>
      </div>
      <div class="px-5 py-4 border-t text-sm text-gray-700 whitespace-pre-wrap">{{ listing.content }}</div>
      <div class="px-5 py-3 border-t bg-amber-50">
        <div class="text-sm font-semibold text-amber-900 mb-1">📞 연락처</div>
        <div v-if="listing.contact_phone" class="text-sm text-gray-700">📱 {{ listing.contact_phone }}</div>
        <div v-if="listing.contact_email" class="text-sm text-gray-700">📧 {{ listing.contact_email }}</div>
      </div>

      <!-- 댓글 -->
      <CommentSection v-if="listing.id" :type="'realestate'" :typeId="listing.id" class="mt-4" />
    </div>

    <div class="col-span-12 lg:col-span-3 hidden lg:block">
      <SidebarWidgets api-url="/api/realestate" detail-path="/realestate/" :current-id="listing.id"
        label="매물" recommend-label="추천 매물" quick-label="최신 매물"
        :links="[{to:'/realestate',icon:'📋',label:'전체 부동산'},{to:'/realestate/write',icon:'✏️',label:'매물 등록'}]" />
    </div>
    </div>
  </div>
</div>
</template>
<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import SidebarWidgets from '../../components/SidebarWidgets.vue'
import CommentSection from '../../components/CommentSection.vue'
import axios from 'axios'
const route = useRoute()
const listing = ref(null)
const loading = ref(true)
onMounted(async () => {
  try { const { data } = await axios.get(`/api/realestate/${route.params.id}`); listing.value = data.data } catch {}
  loading.value = false
})
</script>
