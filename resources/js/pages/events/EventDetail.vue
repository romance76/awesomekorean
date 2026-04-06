<template>
<div class="min-h-screen bg-gray-50">
  <div class="max-w-7xl mx-auto px-4 py-5">
    <button @click="$router.back()" class="text-sm text-gray-500 hover:text-amber-600 mb-3">← 이벤트 목록</button>
    <div v-if="loading" class="text-center py-12 text-gray-400">로딩중...</div>
    <div v-else-if="event" class="grid grid-cols-12 gap-4">
      <div class="col-span-12 lg:col-span-9 bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
      <div v-if="event.image_url" class="h-48 bg-gray-200 overflow-hidden">
        <img :src="event.image_url" class="w-full h-full object-cover" @error="e=>e.target.style.display='none'" />
      </div>
      <div class="px-5 py-4">
        <div class="flex items-center gap-2 mb-2">
          <span class="text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full font-semibold">{{ event.category }}</span>
          <span v-if="event.price == 0 || !event.price" class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full font-bold">무료</span>
          <span v-else class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full">${{ event.price }}</span>
        </div>
        <h1 class="text-lg font-bold text-gray-900">🎉 {{ event.title }}</h1>
        <div class="grid grid-cols-2 gap-3 mt-3 text-sm">
          <div class="flex items-center gap-2 text-gray-600"><span>📅</span>{{ formatDate(event.start_date) }}</div>
          <div class="flex items-center gap-2 text-gray-600"><span>📍</span>{{ event.venue || event.city }}</div>
          <div class="flex items-center gap-2 text-gray-600"><span>🏢</span>{{ event.organizer }}</div>
          <div class="flex items-center gap-2 text-gray-600"><span>👥</span>{{ event.attendee_count }}명 참가</div>
        </div>
      </div>
      <div class="px-5 py-4 border-t text-sm text-gray-700 whitespace-pre-wrap">{{ event.content || event.description }}</div>
      <div v-if="auth.isLoggedIn" class="px-5 py-3 border-t">
        <button @click="toggleAttend" class="bg-amber-400 text-amber-900 font-bold px-6 py-2 rounded-lg text-sm hover:bg-amber-500">
          {{ attending ? '✅ 참가 취소' : '🙋 참가하기' }}
        </button>
      </div>
      <div class="px-5 py-2 border-t text-xs text-gray-400">👁 {{ event.view_count }}조회</div>
    </div>
    <div class="col-span-12 lg:col-span-3 hidden lg:block">
      <SidebarWidgets api-url="/api/events" detail-path="/events/" :current-id="event.id"
        label="이벤트" recommend-label="추천 이벤트" quick-label="다가오는 이벤트"
        :links="[{to:'/events',icon:'📋',label:'전체 이벤트'},{to:'/events/create',icon:'✏️',label:'이벤트 등록'}]" />
    </div>
    </div>
  </div>
</div>
</template>
<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import SidebarWidgets from '../../components/SidebarWidgets.vue'
import axios from 'axios'
const route = useRoute()
const auth = useAuthStore()
const event = ref(null)
const loading = ref(true)
const attending = ref(false)
function formatDate(dt) { return dt ? new Date(dt).toLocaleDateString('ko-KR', { year:'numeric',month:'long',day:'numeric',hour:'2-digit',minute:'2-digit' }) : '' }
async function toggleAttend() {
  try {
    const { data } = await axios.post(`/api/events/${event.value.id}/attend`)
    attending.value = data.attending
    event.value.attendee_count += data.attending ? 1 : -1
  } catch {}
}
onMounted(async () => {
  try { const { data } = await axios.get(`/api/events/${route.params.id}`); event.value = data.data } catch {}
  loading.value = false
})
</script>
