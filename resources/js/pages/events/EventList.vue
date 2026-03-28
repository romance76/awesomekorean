<template>
  <div class="min-h-screen bg-gray-50 pb-20">
    <div class="max-w-[1200px] mx-auto px-4 pt-4">
      <div class="bg-gradient-to-r from-blue-600 to-blue-500 text-white px-6 py-6 rounded-2xl">
        <div class="flex items-center justify-between">
          <div>
            <h1 class="text-xl font-black">🎉 이벤트 & 모임</h1>
            <p class="text-blue-100 text-sm mt-0.5">한인 커뮤니티 행사와 모임</p>
          </div>
          <button @click="$router.push('/events/create')" v-if="auth.isLoggedIn"
            class="bg-white text-blue-600 text-sm px-4 py-2 rounded-lg font-bold hover:bg-blue-50">+ 등록</button>
        </div>
      </div>
    </div>

    <!-- Category tabs -->
    <div class="max-w-[1200px] mx-auto px-4 mt-3">
      <div class="flex gap-2 overflow-x-auto pb-1" style="scrollbar-width:none">
        <button v-for="cat in categories" :key="cat.value"
          @click="selectedCat = cat.value; load()"
          class="flex-shrink-0 px-4 py-2 rounded-full text-sm font-semibold transition"
          :class="selectedCat === cat.value ? 'bg-blue-600 text-white' : 'bg-white text-gray-600 border border-gray-200 hover:border-blue-300'">
          {{ cat.label }}
        </button>
      </div>
    </div>
    <!-- Search bar -->
    <div class="max-w-[1200px] mx-auto px-4 mt-2">
      <div class="bg-white rounded-2xl shadow-sm p-3">
        <div class="flex items-center gap-2">
          <select v-model="radius" class="border border-gray-200 rounded-lg px-2 py-2 text-sm bg-white flex-shrink-0">
            <option :value="5">📍 5mi</option>
            <option :value="10">📍 10mi</option>
            <option :value="20">📍 20mi</option>
            <option :value="30">📍 30mi</option>
            <option :value="50">📍 50mi</option>
            <option :value="100">📍 100mi</option>
            <option :value="0">📍 전체</option>
          </select>
          <input v-model="search" @keyup.enter="load()" type="text" placeholder="이벤트 검색..."
            class="flex-1 min-w-[100px] border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-blue-400" />
          <button @click="load()" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-bold hover:bg-blue-700 flex-shrink-0">검색</button>
        </div>
      </div>
    </div>
    <!-- Content area -->
    <div class="max-w-[1200px] mx-auto px-4 py-4 space-y-3">
      <div v-if="loading" class="text-center py-8 text-gray-400">불러오는 중...</div>
      <div
        v-for="event in events"
        :key="event.id"
        class="bg-white rounded-xl shadow p-4 cursor-pointer hover:shadow-md transition"
        @click="$router.push(`/events/${event.id}`)"
      >
        <div class="flex items-start gap-3">
          <div class="bg-blue-100 rounded-xl p-3 text-center min-w-12">
            <p class="text-xs text-blue-600 font-bold">{{ formatMonth(event.event_date) }}</p>
            <p class="text-xl font-black text-blue-700">{{ formatDay(event.event_date) }}</p>
          </div>
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 mb-1">
              <span class="text-xs bg-purple-100 text-purple-600 px-2 py-0.5 rounded-full">{{ categoryLabel(event.category) }}</span>
              <span v-if="event.is_online" class="text-xs bg-green-100 text-green-600 px-2 py-0.5 rounded-full">온라인</span>
              <span v-if="event.price == 0" class="text-xs bg-yellow-100 text-yellow-600 px-2 py-0.5 rounded-full">무료</span>
            </div>
            <h3 class="font-bold text-gray-800 truncate">{{ event.title }}</h3>
            <p class="text-gray-500 text-sm truncate mt-0.5">📍 {{ event.location || event.region }}</p>
            <p class="text-gray-400 text-xs mt-1">주최: {{ event.organizer?.nickname || event.organizer?.name || event.user?.nickname || event.user?.name }}</p>
          </div>
          <div class="text-right">
            <p v-if="event.price > 0" class="font-bold text-blue-600 text-sm">${{ event.price }}</p>
            <p v-else class="font-bold text-green-500 text-sm">무료</p>
            <p class="text-xs text-gray-400 mt-1">{{ event.attendee_count }}명 참가</p>
          </div>
        </div>
      </div>
      <div v-if="!loading && events.length === 0" class="text-center py-12">
        <p class="text-4xl mb-3">📅</p>
        <p class="text-gray-400">이벤트가 없습니다</p>
      </div>
    </div><!-- /max-w-[1200px] -->
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useAuthStore } from '../../stores/auth'
import axios from 'axios'

const auth = useAuthStore()
const events      = ref([])
const loading     = ref(true)
const selectedCat = ref('')
const radius      = ref(30)
const search      = ref('')

const categories = [
  { value:'', label:'전체' },
  { value:'meetup', label:'모임' },
  { value:'food', label:'음식' },
  { value:'culture', label:'문화' },
  { value:'sports', label:'스포츠' },
  { value:'education', label:'교육' },
  { value:'business', label:'비즈니스' },
]

const catLabels = { general:'일반', meetup:'모임', food:'음식', culture:'문화', sports:'스포츠', education:'교육', business:'비즈니스' }
const categoryLabel = (c) => catLabels[c] || c
const formatMonth   = (d) => d ? new Date(d).toLocaleDateString('en-US', { month:'short' }) : ''
const formatDay     = (d) => d ? new Date(d).getDate() : ''

async function load() {
  loading.value = true
  try {
    const params = {}
    if (selectedCat.value) params.category = selectedCat.value
    if (search.value) params.search = search.value
    const { data } = await axios.get('/api/events', { params })
    events.value = data.data || data
  } catch (e) {}
  finally { loading.value = false }
}

onMounted(load)
</script>
