<template>
<div class="min-h-screen bg-gray-50">
  <div class="max-w-7xl mx-auto px-4 py-5">
    <router-link to="/events" class="text-sm text-gray-500 hover:text-amber-600 mb-3 inline-block">← 이벤트 목록</router-link>
    <div v-if="loading" class="text-center py-12 text-gray-400">로딩중...</div>
    <div v-else-if="event" class="grid grid-cols-12 gap-4">
      <div class="col-span-12 lg:col-span-9">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
          <!-- 이미지 또는 배너 색상 -->
          <div v-if="event.image_url" class="h-48 lg:h-64 bg-gray-200 overflow-hidden">
            <img :src="event.image_url" class="w-full h-full object-cover" @error="e=>e.target.style.display='none'" />
          </div>
          <div v-else-if="event.event_type === 'somekorean'" class="relative flex items-center justify-between px-6 overflow-hidden"
            :style="{ background: 'linear-gradient(135deg, ' + (event.banner_color || '#F5A623') + ', ' + (event.banner_color || '#F5A623') + '99)', height: '280px' }">
            <div class="z-10 max-w-[65%]">
              <div class="flex items-center gap-2 mb-3">
                <span class="text-xs bg-white/30 text-white font-bold px-2.5 py-1 rounded-full">⭐ 썸코리안 공식</span>
              </div>
              <h2 class="text-2xl lg:text-3xl font-black text-white leading-tight">{{ event.title }}</h2>
              <div v-if="event.banner_subtitle" class="text-base text-white/80 mt-2">{{ event.banner_subtitle }}</div>
            </div>
            <div class="text-9xl opacity-15 flex-shrink-0">{{ event.title.match(/[\u{1F300}-\u{1F9FF}]/u)?.[0] || '⭐' }}</div>
          </div>
          <div v-else class="h-32 bg-gradient-to-r from-amber-100 to-orange-100 flex items-center justify-center text-4xl">🎉</div>

          <!-- 헤더 -->
          <div class="px-4 lg:px-5 py-4">
            <div class="flex items-center gap-2 flex-wrap mb-2">
              <span v-if="event.event_type === 'somekorean'" class="text-xs bg-amber-400 text-amber-900 px-2 py-0.5 rounded-full font-bold">⭐ 썸코리안 공식</span>
              <span v-if="event.reward_points" class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full font-bold">🎁 최대 {{ event.reward_points }}P</span>
              <span class="text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full font-semibold">{{ event.category || '이벤트' }}</span>
              <span v-if="event.is_free || !event.price" class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full font-bold">무료</span>
              <span v-else class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full font-bold">${{ Number(event.price).toLocaleString() }}</span>
              <span v-if="isPast" class="text-xs bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full">종료됨</span>
              <span v-else class="text-xs bg-green-50 text-green-600 px-2 py-0.5 rounded-full">진행 예정</span>
            </div>
            <h1 class="text-xl lg:text-2xl font-bold text-gray-900">{{ event.title }}</h1>
            <div v-if="event.organizer" class="text-sm text-amber-700 font-semibold mt-1">{{ event.organizer }}</div>
          </div>

          <!-- 정보 그리드 -->
          <div class="px-4 lg:px-5 py-3 border-t border-b border-gray-100 bg-gray-50/50">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
              <div class="flex items-center gap-2 text-gray-700">
                <span class="text-base">📅</span>
                <div>
                  <div class="font-medium">{{ formatDate(event.start_date) }}</div>
                  <div v-if="event.end_date" class="text-xs text-gray-400">~ {{ formatDate(event.end_date) }}</div>
                </div>
              </div>
              <div v-if="event.venue || event.city" class="flex items-center gap-2 text-gray-700">
                <span class="text-base">📍</span>
                <div>
                  <div class="font-medium">{{ event.venue }}</div>
                  <div v-if="event.address || event.city" class="text-xs text-gray-400">{{ [event.address, event.city, event.state].filter(Boolean).join(', ') }}</div>
                </div>
              </div>
              <div class="flex items-center gap-2 text-gray-700">
                <span class="text-base">👥</span>
                <span class="font-medium">{{ event.attendee_count }}명 참가</span>
                <span v-if="event.max_attendees" class="text-xs text-gray-400">/ {{ event.max_attendees }}명</span>
              </div>
              <div class="flex items-center gap-2 text-gray-700">
                <span class="text-base">👁</span>
                <span>{{ event.view_count }}회 조회</span>
              </div>
            </div>
          </div>

          <!-- 참가 버튼 -->
          <div class="px-4 lg:px-5 py-3 border-b border-gray-100 flex items-center gap-3 flex-wrap">
            <template v-if="auth.isLoggedIn && !isPast">
              <button @click="toggleAttend('going')"
                class="px-5 py-2 rounded-lg font-bold text-sm transition"
                :class="myStatus === 'going' ? 'bg-amber-400 text-amber-900' : 'bg-gray-100 text-gray-600 hover:bg-amber-100'">
                {{ myStatus === 'going' ? '✅ 참가 중' : '🙋 참가하기' }}
              </button>
              <button @click="toggleAttend('interested')"
                class="px-5 py-2 rounded-lg font-bold text-sm transition"
                :class="myStatus === 'interested' ? 'bg-blue-400 text-white' : 'bg-gray-100 text-gray-600 hover:bg-blue-100'">
                {{ myStatus === 'interested' ? '⭐ 관심 등록됨' : '⭐ 관심있음' }}
              </button>
            </template>
            <span v-if="!auth.isLoggedIn" class="text-xs text-gray-400">참가하려면 로그인하세요</span>
            <div v-if="event.url" class="ml-auto">
              <a :href="event.url" target="_blank" class="text-xs text-blue-600 hover:underline">🔗 관련 링크</a>
            </div>
          </div>

          <!-- 본문 -->
          <div class="px-4 lg:px-5 py-4 text-sm text-gray-700 leading-relaxed whitespace-pre-wrap">{{ event.content || event.description }}</div>

          <!-- 공식 이벤트 참여하기 버튼 -->
          <div v-if="event.event_url || event.event_type === 'somekorean'" class="px-4 lg:px-5 py-3 border-t">
            <button v-if="event.event_url" @click="$router.push(event.event_url)"
              class="w-full py-3 rounded-xl font-bold text-sm text-white transition hover:opacity-90"
              :style="{ backgroundColor: event.banner_color || '#F59E0B' }">
              {{ eventActionLabel }}
            </button>
            <button v-else @click="scrollToComments"
              class="w-full py-3 rounded-xl bg-amber-400 text-amber-900 font-bold text-sm hover:bg-amber-500 transition">
              📸 댓글로 참여하기
            </button>
          </div>

          <!-- 하단: 작성자 + 수정/삭제 -->
          <div class="px-4 lg:px-5 py-3 border-t border-gray-100 bg-gray-50/30 flex items-center justify-between flex-wrap gap-2">
            <div class="text-xs text-gray-500">
              <UserName v-if="event.user?.id" :userId="event.user.id" :name="event.user.nickname || event.user.name" className="text-gray-700 font-semibold" />
              <span v-if="event.created_at" class="ml-2 text-gray-300">|</span>
              <span v-if="event.created_at" class="ml-2">{{ formatDate(event.created_at) }}</span>
            </div>
            <div v-if="canEdit" class="flex items-center gap-3">
              <router-link :to="`/events/${event.id}/edit`" class="text-xs text-amber-600 hover:text-amber-800 font-medium">수정</router-link>
              <button @click="deleteEvent" class="text-xs text-red-400 hover:text-red-600 font-medium">삭제</button>
            </div>
          </div>
        </div>

        <!-- 댓글 -->
        <CommentSection v-if="event.id" type="event" :typeId="event.id" class="mt-4" />
      </div>

      <!-- 사이드바 -->
      <div class="col-span-12 lg:col-span-3 hidden lg:block">
        <SidebarWidgets mode="detail" :currentCategory="event?.category || ''" api-url="/api/events" detail-path="/events/" :current-id="event.id"
          label="이벤트" recommend-label="추천 이벤트" quick-label="다가오는 이벤트"
          :filter-params="event.lat && event.lng ? { lat: event.lat, lng: event.lng, radius: 50 } : {}"
          :links="[{to:'/events',icon:'📋',label:'전체 이벤트'},{to:'/events/create',icon:'✏️',label:'이벤트 등록'}]" />
      </div>
    </div>

    <!-- Not found -->
    <div v-else class="text-center py-20">
      <div class="text-4xl mb-3">🎉</div>
      <div class="text-gray-500 font-semibold">이벤트를 찾을 수 없습니다</div>
    </div>
  </div>
</div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import SidebarWidgets from '../../components/SidebarWidgets.vue'
import CommentSection from '../../components/CommentSection.vue'
import axios from 'axios'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const event = ref(null)
const loading = ref(true)
const myStatus = ref(null)

const isPast = computed(() => event.value?.start_date && new Date(event.value.start_date) < new Date())

const eventActionLabel = computed(() => {
  const url = event.value?.event_url || ''
  if (url.includes('realestate')) return '🏠 리스팅 등록하러 가기'
  if (url.includes('music')) return '🎵 음악듣기 바로가기'
  if (url.includes('chat')) return '💬 채팅방 입장하기'
  return '🎯 참여하기'
})

function scrollToComments() {
  document.querySelector('.comment-section, [class*=CommentSection]')?.scrollIntoView({ behavior: 'smooth' })
}
const canEdit = computed(() => {
  if (!event.value || !auth.user) return false
  return event.value.user_id === auth.user.id || ['admin','super_admin'].includes(auth.user.role)
})

function formatDate(dt) {
  if (!dt) return ''
  return new Date(dt).toLocaleDateString('ko-KR', { year:'numeric', month:'long', day:'numeric', hour:'2-digit', minute:'2-digit' })
}

async function toggleAttend(status) {
  try {
    const { data } = await axios.post(`/api/events/${event.value.id}/attend`, { status })
    if (data.data?.status) {
      myStatus.value = data.data.status
    } else {
      myStatus.value = null
    }
    // reload to get updated count
    const { data: fresh } = await axios.get(`/api/events/${event.value.id}`)
    event.value = fresh.data
    myStatus.value = fresh.data.my_status || null
  } catch {}
}

async function deleteEvent() {
  if (!confirm('정말 삭제하시겠습니까?')) return
  try {
    await axios.delete(`/api/events/${event.value.id}`)
    router.push('/events')
  } catch {}
}

onMounted(async () => {
  try {
    const { data } = await axios.get(`/api/events/${route.params.id}`)
    event.value = data.data
    myStatus.value = data.data.my_status || null
  } catch (err) {
    if (err.response?.status === 404) router.replace('/404')
  }
  loading.value = false
})
</script>
