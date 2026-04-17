<template>
<div class="min-h-screen bg-gray-50">
  <div class="max-w-7xl mx-auto px-4 py-5">
    <!-- 헤더: 모바일 -->
    <div class="lg:hidden mb-3">
      <div class="flex items-center justify-between mb-2">
        <h1 class="text-lg font-black text-gray-800">🎉 이벤트</h1>
        <div class="flex items-center gap-2">
          <button @click="showFilter = true" class="bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold px-3 py-2 rounded-lg">🔍 필터</button>
          <RouterLink v-if="auth.isLoggedIn" to="/events/create" class="bg-amber-400 text-amber-900 text-xs font-bold px-3 py-2 rounded-lg">✏️ 등록</RouterLink>
        </div>
      </div>
      <div class="flex items-center gap-1.5 overflow-x-auto">
        <span class="text-[10px] bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full font-semibold whitespace-nowrap">
          📍{{ selectedCityIdx == -1 ? '전국' : (koreanCities[selectedCityIdx]?.label || '내 위치') }}
        </span>
        <span v-if="search" class="text-[10px] bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full font-semibold whitespace-nowrap">
          "{{ search }}"
        </span>
      </div>
    </div>

    <!-- 모바일 필터 바텀시트 -->
    <MobileFilter v-model="showFilter" @apply="loadPage()" @reset="activeCat = ''; search = ''; selectedCityIdx = '-1'; onCityChange()">
      <div class="mb-4">
        <label class="text-xs font-bold text-gray-600 mb-2 block">지역</label>
        <select v-model="selectedCityIdx" @change="onCityChange"
          class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm bg-white outline-none focus:ring-2 focus:ring-amber-400">
          <option value="-2" v-if="myCity">📌 내 위치 ({{ myCity.label || myCity.name }})</option>
          <option value="-1">🇺🇸 전국</option>
          <optgroup label="한인 밀집 도시">
            <option v-for="(c, i) in koreanCities" :key="i" :value="i">{{ c.label }}</option>
          </optgroup>
        </select>
      </div>
      <div class="mb-4">
        <label class="text-xs font-bold text-gray-600 mb-2 block">검색어</label>
        <input v-model="search" type="text" placeholder="검색어 입력..."
          class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-amber-400" />
      </div>
      <div>
        <label class="text-xs font-bold text-gray-600 mb-2 block">카테고리</label>
        <div class="grid grid-cols-3 gap-1.5">
          <button v-for="c in eventCategories" :key="c.value" @click="activeCat = c.value"
            class="text-xs py-2 rounded-lg font-semibold border transition"
            :class="activeCat === c.value ? 'bg-amber-50 text-amber-700 border-amber-300' : 'border-gray-200 text-gray-600 hover:bg-gray-50'">
            {{ c.label }}
          </button>
        </div>
      </div>
    </MobileFilter>

    <!-- 헤더: 데스크탑 -->
    <div class="hidden lg:flex items-center justify-between mb-4 flex-wrap gap-2">
      <h1 class="text-xl font-black text-gray-800">🎉 이벤트</h1>
      <div class="flex items-center gap-2 flex-wrap">
        <span class="text-amber-600 text-sm">📍</span>
        <select v-model="selectedCityIdx" @change="onCityChange" class="border border-gray-200 rounded-lg px-2 py-1.5 text-xs font-semibold text-gray-700 outline-none focus:ring-2 focus:ring-amber-400 bg-amber-50">
          <option value="-2" v-if="myCity">📌 내 위치 ({{ myCity.label || myCity.name }})</option>
          <option value="-1">🇺🇸 전국</option>
          <optgroup label="한인 밀집 도시">
            <option v-for="(c, i) in koreanCities" :key="i" :value="i">{{ c.label }}</option>
          </optgroup>
        </select>
        <select v-if="selectedCityIdx !== '-1' && selectedCityIdx !== -1" v-model="radius" @change="loadPage()" class="border border-gray-200 rounded-lg px-2 py-1.5 text-xs text-gray-600 outline-none">
          <option value="10">10mi</option><option value="30">30mi</option><option value="50">50mi</option><option value="100">100mi</option>
        </select>
        <form @submit.prevent="loadPage()" class="flex gap-1">
          <input v-model="search" type="text" placeholder="검색..." class="border rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-amber-400 outline-none w-40" />
          <button type="submit" class="bg-amber-400 text-amber-900 font-bold px-3 py-1.5 rounded-lg text-xs hover:bg-amber-500">검색</button>
        </form>
        <RouterLink v-if="auth.isLoggedIn" to="/events/create" class="bg-amber-400 text-amber-900 font-bold px-3 py-1.5 rounded-lg text-xs hover:bg-amber-500">✏️ 등록</RouterLink>
      </div>
    </div>

    <div class="grid grid-cols-12 gap-4">
    <!-- 왼쪽: 카테고리 -->
    <div class="col-span-12 lg:col-span-2 hidden lg:block">
      <div class="sticky top-20 max-h-[calc(100vh-6rem)] overflow-y-auto space-y-3 pr-0.5">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
          <div class="px-3 py-2.5 border-b font-bold text-xs text-amber-900">📋 카테고리</div>
          <button v-for="c in eventCategories" :key="c.value" @click="showFavorites=false; activeCat=c.value; activeItem=null; loadPage()"
            class="w-full text-left px-3 py-2 text-xs transition"
            :class="!showFavorites && activeCat===c.value ? 'bg-amber-50 text-amber-700 font-bold' : 'text-gray-600 hover:bg-amber-50/50'">{{ c.label }}</button>
          <button v-if="auth.isLoggedIn" @click="showFavorites=true; activeItem=null; loadFavoritesPage()"
            class="w-full text-left px-3 py-2 text-xs transition border-t"
            :class="showFavorites ? 'bg-red-50 text-red-600 font-bold' : 'text-gray-600 hover:bg-red-50/50'">
            🔖 내 북마크<span v-if="favCount > 0" class="ml-0.5">({{ favCount }})</span>
          </button>
        </div>
        <AdSlot page="events" position="left" :maxSlots="2" />
      </div>
    </div>
    <div class="col-span-12 lg:col-span-7">

    <div class="mb-2">
      <span v-if="showFavorites" class="font-bold text-red-600 text-sm">🔖 내 북마크</span>
      <template v-else>
        <span class="font-bold text-amber-700 text-sm">{{ activeCat ? (eventCategories.find(c => c.value === activeCat)?.label || activeCat) : '전체' }}</span>
        <span v-if="!activeCat" class="text-xs text-gray-400 ml-2">모든 이벤트를 볼 수 있습니다</span>
      </template>
    </div>

    <!-- 상세 모드 -->
    <div v-if="activeItem">
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <!-- 공식 이벤트: 배너 이미지 또는 색상 헤더 -->
        <div v-if="activeItem.event_type === 'somekorean'" class="relative overflow-hidden"
          :style="{ backgroundColor: activeItem.banner_color || '#F5A623', height: '180px' }">
          <!-- 이미지가 있으면 이미지만 (높이 맞춤, 비율 유지) -->
          <img v-if="activeItem.banner_image || activeItem.image_url" :src="activeItem.banner_image || activeItem.image_url"
            class="absolute inset-0 m-auto h-full object-contain" />
          <!-- 이미지 없으면 텍스트 -->
          <div v-else class="absolute inset-0 flex items-center justify-between px-6">
            <div class="z-10 max-w-[60%]">
              <div class="flex items-center gap-2 mb-2">
                <span class="text-xs bg-white/30 text-white font-bold px-2.5 py-1 rounded-full">⭐ 썸코리안 공식</span>
                <span class="text-xs bg-white/20 text-white font-bold px-2 py-0.5 rounded-full">{{ eventStatusLabel(activeItem) }}</span>
              </div>
              <h2 class="text-xl font-black text-white leading-tight">{{ activeItem.title }}</h2>
              <div v-if="activeItem.banner_subtitle" class="text-sm text-white/80 mt-2">{{ activeItem.banner_subtitle }}</div>
            </div>
            <div class="text-8xl opacity-20 flex-shrink-0">{{ activeItem.title.match(/[\u{1F300}-\u{1F9FF}]/u)?.[0] || '⭐' }}</div>
          </div>
        </div>
        <!-- 일반 이벤트: 기존 헤더 -->
        <div v-else class="px-5 py-4">
          <div class="flex items-center gap-2 mb-2">
            <span class="text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full font-semibold">{{ activeItem.category }}</span>
            <span v-if="!activeItem.price || activeItem.price == 0" class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full font-bold">무료</span>
            <span v-else class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full">${{ activeItem.price }}</span>
          </div>
          <div class="flex items-center gap-2">
            <h2 class="text-lg font-bold text-gray-900 flex-1">{{ activeItem.title }}</h2>
            <BookmarkToggle v-if="auth.isLoggedIn" :active="favorited.has(activeItem.id)" @toggle="toggleFav(activeItem)" size="lg" class="flex-shrink-0" />
          </div>
        </div>
        <!-- 공통 정보 -->
        <div class="px-5 py-3 bg-gray-50/50 border-t border-b">
          <div class="grid grid-cols-2 gap-2 text-sm text-gray-600">
            <div>📅 {{ formatDate(activeItem.start_date) }}{{ activeItem.end_date ? ' ~ ' + formatDate(activeItem.end_date) : '' }}</div>
            <div>📍 {{ activeItem.venue || activeItem.city }}</div>
            <div>🏢 {{ activeItem.organizer }}</div>
            <div>👥 {{ activeItem.attendee_count }}명 참가 · 👁 {{ activeItem.view_count }}회</div>
          </div>
        </div>
        <!-- 참여 버튼 (공식 이벤트) -->
        <div v-if="activeItem.event_url" class="px-5 py-3 border-b">
          <button @click="$router.push(activeItem.event_url)"
            class="w-full py-2.5 rounded-xl font-bold text-sm text-white transition hover:opacity-90"
            :style="{ backgroundColor: activeItem.banner_color || '#F59E0B' }">
            {{ activeItem.event_url.includes('realestate') ? '🏠 리스팅 등록하러 가기' : activeItem.event_url.includes('music') ? '🎵 음악듣기 바로가기' : activeItem.event_url.includes('chat') ? '💬 채팅방 입장하기' : '🎯 참여하기' }}
          </button>
        </div>
        <!-- 본문 -->
        <div class="px-5 py-4 text-sm text-gray-700 whitespace-pre-wrap leading-relaxed">{{ activeItem.content || activeItem.description }}</div>
      </div>
      <div v-if="auth.user?.id === activeItem.user_id" class="flex gap-2 mt-3 justify-end">
        <button @click="deleteActiveItem('events')" class="text-xs text-red-400 hover:text-red-600">🗑️ 삭제</button>
      </div>
      <CommentSection v-if="activeItem.id" type="event" :typeId="activeItem.id" class="mt-3" />
      <div class="flex justify-between mt-2">
        <button @click="navItem(-1)" :disabled="currentIdx<=0" class="text-xs text-gray-500 hover:text-amber-700 disabled:opacity-30">← 이전글</button>
        <button @click="activeItem=null" class="text-xs text-gray-400">목록</button>
        <button @click="navItem(1)" :disabled="currentIdx>=items.length-1" class="text-xs text-gray-500 hover:text-amber-700 disabled:opacity-30">다음글 →</button>
      </div>
    </div>

    <!-- 목록 -->
    <div v-else-if="loading" class="text-center py-12 text-gray-400">로딩중...</div>
    <div v-else-if="!items.length" class="text-center py-12 text-gray-400">검색 결과 없음</div>
    <!-- 카드형 -->
    <div v-else-if="viewMode==='card'" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
      <template v-for="(item, i) in items" :key="item.id">
      <div @click="openItem(item)"
        class="rounded-xl shadow-sm border overflow-hidden hover:shadow-md hover:-translate-y-0.5 transition-all cursor-pointer"
        :class="item.event_type === 'somekorean' ? 'border-amber-200' : 'bg-white border-gray-100'">
        <!-- 공식 이벤트: 배너 색상 상단 영역 -->
        <div v-if="item.event_type === 'somekorean'" class="relative flex items-center justify-between px-5 py-4"
          :style="{ background: 'linear-gradient(135deg, ' + (item.banner_color || '#F5A623') + ', ' + (item.banner_color || '#F5A623') + 'cc)' }">
          <div class="z-10">
            <div class="flex items-center gap-2 mb-1">
              <span class="text-[10px] bg-white/30 text-white font-bold px-2 py-0.5 rounded-full">⭐ 썸코리안 공식</span>
              <span class="text-[10px] bg-white/20 text-white font-bold px-2 py-0.5 rounded-full">
                {{ eventStatusLabel(item) }}
              </span>
            </div>
            <div class="text-white font-black text-base leading-tight">{{ item.title }}</div>
            <div v-if="item.banner_subtitle" class="text-white/80 text-xs mt-1">{{ item.banner_subtitle }}</div>
          </div>
          <div class="text-5xl opacity-30 flex-shrink-0 ml-3">{{ item.title.match(/[\u{1F300}-\u{1F9FF}]/u)?.[0] || '⭐' }}</div>
        </div>

        <!-- 일반 이벤트: 기존 카드 헤더 -->
        <div v-else class="p-4">
          <div class="flex items-center gap-3 mb-3">
            <div class="w-12 h-12 bg-amber-100 rounded-xl flex items-center justify-center text-2xl">🎉</div>
            <div class="flex-1 min-w-0">
              <div class="text-sm font-bold text-gray-800 truncate">{{ item.title }}</div>
              <div class="text-[10px] text-gray-400"><template v-if="!activeCat">{{ item.category || '기타' }} · </template><template v-if="item.organizer && !item.user">{{ item.organizer }}</template><UserName v-else :userId="item.user?.id" :name="item.organizer || item.user?.name" /></div>
            </div>
            <div v-if="item.price" class="text-amber-600 font-black text-sm">${{ Number(item.price).toLocaleString() }}</div>
            <div v-else class="text-green-600 text-xs font-bold">무료</div>
          </div>
        </div>

        <!-- 공통: 정보 영역 -->
        <div class="px-4 pb-3" :class="item.event_type === 'somekorean' ? 'bg-amber-50/50 pt-3' : ''">
          <div v-if="item.event_type === 'somekorean'" class="flex items-center gap-2 mb-2 flex-wrap">
            <span v-if="item.reward_points" class="text-[10px] bg-green-100 text-green-700 font-bold px-2 py-0.5 rounded-full">🎁 최대 {{ item.reward_points }}P</span>
            <span class="text-[10px] text-gray-400">📅 {{ formatDate(item.start_date) }}{{ item.end_date ? ' ~ ' + formatDate(item.end_date) : '' }}</span>
          </div>
          <div class="text-xs text-gray-500 line-clamp-2 mb-2">{{ (item.content || '').slice(0, 120) }}</div>
          <div class="flex items-center justify-between text-[10px] text-gray-400">
            <span v-if="item.city">📍 {{ item.city }}, {{ item.state }}</span>
            <div class="flex items-center gap-2">
              <span>👁 {{ item.view_count || 0 }}</span>
              <span>👥 {{ item.attendee_count || 0 }}</span>
              <BookmarkToggle v-if="auth.isLoggedIn" :active="favorited.has(item.id)" @toggle="toggleFav(item)" size="sm" />
            </div>
          </div>
        </div>
      </div>
      <MobileAdInline v-if="i === 4" page="events" />
      </template>
    </div>
    <!-- 리스트형 -->
    <div v-else class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
      <template v-for="(item, i) in items" :key="item.id">
      <div @click="openItem(item)"
        class="px-4 py-3 border-b border-gray-50 hover:bg-amber-50/50 hover:border-l-2 hover:border-l-amber-400 transition cursor-pointer">
        <div class="flex items-center justify-between">
          <div class="flex-1 min-w-0">
            <div class="text-sm font-medium text-gray-800 truncate">{{ item.title || item.name }}</div>
            <div class="text-xs text-gray-400 mt-0.5 flex items-center gap-1.5 flex-wrap">
              <span v-if="item.user?.name"><UserName :userId="item.user?.id" :name="item.user?.name" /></span>
              <span v-else-if="item.company || item.organizer">{{ item.company || item.organizer }}</span>
              <span v-if="item.city" class="flex items-center gap-0.5">📍{{ item.city }}, {{ item.state }}</span>
              <span v-if="item.distance !== undefined && item.distance !== null" class="text-amber-600 font-semibold">{{ Number(item.distance).toFixed(1) }}mi</span>
              <span v-if="item.created_at">🕐 {{ fmtDate(item.created_at) }}</span>
              <span v-if="item.view_count">👁{{ item.view_count }}</span>
            </div>
          </div>
          <div class="ml-3 flex-shrink-0 text-right">
            <div v-if="item.price !== undefined && item.price !== null" class="text-amber-600 font-bold text-sm">${{ Number(item.price).toLocaleString() }}</div>
            <div v-if="item.salary_min" class="text-amber-600 font-bold text-xs">${{ item.salary_min }}~${{ item.salary_max }}/{{ item.salary_type }}</div>
            <div v-if="item.rating" class="text-amber-400 text-xs">{{'★'.repeat(Math.round(item.rating))}} {{ item.rating }}</div>
          </div>
        </div>
      </div>
      <MobileAdInline v-if="i === 4" page="events" />
      </template>
    </div>

    <!-- 페이지네이션 -->
    <Pagination :page="page" :lastPage="lastPage" @page="loadPage" />
    </div>
    <div class="col-span-12 lg:col-span-3 hidden lg:block">
      <SidebarWidgets :currentCategory="activeItem ? (activeItem.category || '') : activeCat" :inline="true" @select="openItem" api-url="/api/events" detail-path="/events/" :current-id="activeItem?.id || 0"
        :mode="activeItem ? 'detail' : 'list'" :categoryLabel="eventCategories.find(c => c.value === (activeItem?.category || activeCat))?.label || activeItem?.category || ''" label="이벤트" :filter-params="locationParams" />
        <AdSlot page="events" position="right" :maxSlots="2" />
    </div>
    </div>
  </div>
</div>
</template>
<script setup>
import { useRoute } from 'vue-router'
import { ref, computed, watch, onMounted } from 'vue'
import { useLocation } from '../../composables/useLocation'
import { useAuthStore } from '../../stores/auth'
import { useBookmarkStore } from '../../stores/bookmarks'
import SidebarWidgets from '../../components/SidebarWidgets.vue'
import CommentSection from '../../components/CommentSection.vue'
import { useMenuConfig } from '../../composables/useMenuConfig'
import axios from 'axios'
import AdSlot from '../../components/AdSlot.vue'
import BookmarkToggle from '../../components/BookmarkToggle.vue'

const auth = useAuthStore()
const route = useRoute()
const { city, radius: locRadius, locationQuery, koreanCities, init: initLocation, selectKoreanCity, setRadius } = useLocation()

function fmtDate(dt) {
  if (!dt) return ''
  const d = new Date(dt)
  const diff = (Date.now() - d.getTime()) / 1000
  if (diff < 60) return '방금'
  if (diff < 3600) return Math.floor(diff/60) + '분 전'
  if (diff < 86400) return Math.floor(diff/3600) + '시간 전'
  if (diff < 604800) return Math.floor(diff/86400) + '일 전'
  return d.toLocaleDateString('ko-KR', { month: 'short', day: 'numeric' })
}

const bStore = useBookmarkStore()
const BM_TYPE = 'App\\Models\\Event'
const showFilter = ref(false)
const showFavorites = ref(false)
const favorited = ref(new Set())
const favCount = computed(() => bStore.getBookmarkedIds(BM_TYPE).length)
const activeCat = ref('')
const { loadConfig, getDefaultView } = useMenuConfig()
const viewMode = ref('list')
const activeItem = ref(null)
const currentIdx = ref(-1)

async function openItem(item) {
  currentIdx.value = items.value.findIndex(i => i.id === item.id)
  try { const { data } = await axios.get(`/api/events/${item.id}`); activeItem.value = data.data }
  catch { activeItem.value = item }
  if (activeItem.value?.category) activeCat.value = activeItem.value.category
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

function navItem(dir) {
  const newIdx = currentIdx.value + dir
  if (newIdx >= 0 && newIdx < items.value.length) openItem(items.value[newIdx])
}

async function deleteActiveItem(type) {
  if (!confirm('정말 삭제하시겠습니까?')) return
  try { await axios.delete(`/api/${type}/${activeItem.value.id}`); activeItem.value = null; loadPage() } catch {}
}

function formatDate(dt) { return dt ? new Date(dt).toLocaleDateString('ko-KR', { year:'numeric',month:'long',day:'numeric' }) : '' }
function eventStatusLabel(item) {
  const now = new Date()
  const start = item.start_date ? new Date(item.start_date) : null
  const end = item.end_date ? new Date(item.end_date) : null
  if (end && end < now) return '종료'
  if (start && start > now) return '진행예정'
  return '진행중'
}
const eventCategories = [
  { value: '', label: '전체' },{ value: 'somekorean', label: '⭐ 썸코리안', isType: true },{ value: 'culture', label: '🎭 문화' },{ value: 'networking', label: '🤝 네트워킹' },
  { value: 'education', label: '📚 교육' },{ value: 'community', label: '👥 커뮤니티' },
  { value: 'sports', label: '⚽ 스포츠' },{ value: 'food', label: '🍽️ 음식' },
]
const items = ref([])
const loading = ref(true)
const page = ref(1)
const lastPage = ref(1)
const search = ref('')
const radius = ref(String(auth.user?.default_radius || 30))
const selectedCityIdx = ref('-2') // -2=내위치, -1=전국, 0~=도시
const myCity = ref(null)

const locationParams = computed(() => {
  const idx = parseInt(selectedCityIdx.value)
  if (idx === -1) return {}
  let lat, lng
  if (idx >= 0) { lat = koreanCities[idx]?.lat; lng = koreanCities[idx]?.lng }
  else if (myCity.value?.lat) { lat = myCity.value.lat; lng = myCity.value.lng }
  return lat && lng ? { lat, lng, radius: parseInt(radius.value) } : {}
})

const locationInfo = computed(() => {
  if (selectedCityIdx.value === -1 || selectedCityIdx.value === '-1') return '전국 검색 중'
  const c = selectedCityIdx.value === '-2' || selectedCityIdx.value === -2
    ? myCity.value
    : koreanCities[selectedCityIdx.value]
  if (!c) return '위치를 선택해주세요'
  return c.label || c.name + ', ' + c.state + ' 기준 ' + radius.value + 'mi 반경'
})

function onCityChange() {
  const idx = parseInt(selectedCityIdx.value)
  if (idx === -1) {
    // 전국
    radius.value = '0'
  } else if (idx === -2) {
    // 내 위치 복원
    if (myCity.value) {
      selectKoreanCity(-1) // reset first
      city.value = myCity.value
      radius.value = '30'
    }
  } else {
    selectKoreanCity(idx)
    radius.value = '30'
  }
  loadPage()
}

async function loadPage(p = 1) {
  loading.value = true
  page.value = p

  const params = { page: p, per_page: 20 }
  if (search.value) params.search = search.value
  // 썸코리안 이벤트는 event_type으로, 나머지는 category로
  const catInfo = eventCategories.find(c => c.value === activeCat.value)
  if (activeCat.value && catInfo?.isType) params.event_type = activeCat.value
  else if (activeCat.value) params.category = activeCat.value

  if (radius.value !== '0') {
    let lat, lng
    const idx = parseInt(selectedCityIdx.value)
    if (idx >= 0) {
      const kc = koreanCities[idx]
      lat = kc.lat; lng = kc.lng
    } else if (idx === -2 && myCity.value?.lat) {
      lat = myCity.value.lat; lng = myCity.value.lng
    } else {
      const loc = locationQuery.value
      lat = loc.lat; lng = loc.lng
    }

    if (lat && lng) {
      params.lat = lat
      params.lng = lng
      params.radius = parseInt(radius.value)
    }
  }

  try {
    const { data } = await axios.get('/api/events', { params })
    items.value = data.data?.data || []
    lastPage.value = data.data?.last_page || 1
  } catch {}
  loading.value = false
  loadFavorited()
}

// 좋아요 (Bookmark)
async function loadFavorited() {
  if (!auth.isLoggedIn || !items.value.length) return
  try {
    const ids = items.value.map(i => i.id).join(',')
    const { data } = await axios.get('/api/bookmarks/check', { params: { type: 'App\\Models\\Event', ids } })
    favorited.value = new Set(data.data || [])
  } catch {}
}
async function toggleFav(item) {
  if (!auth.isLoggedIn) return
  const result = await bStore.toggle(BM_TYPE, item.id)
  if (result !== null) {
    if (result) favorited.value.add(item.id)
    else favorited.value.delete(item.id)
    favorited.value = new Set(favorited.value)
  }
}
async function loadFavoritesPage() {
  loading.value = true
  try {
    const { data } = await axios.get('/api/bookmarks', { params: { type: 'App\\Models\\Event', per_page: 50 } })
    const bms = data.data?.data || []
    items.value = bms.map(b => b.bookmarkable).filter(Boolean)
    lastPage.value = 1
    loadFavorited()
  } catch {}
  loading.value = false
}

onMounted(async () => {
  bStore.loadAll()
  await loadConfig(); viewMode.value = getDefaultView('events')
  await initLocation()
  if (city.value) {
    myCity.value = { ...city.value }
    selectedCityIdx.value = '-2'
  } else {
    selectedCityIdx.value = '-1'
    radius.value = '0'
  }
  await loadPage()

  // 메인 배너에서 ?open={id} 로 왔을 때 해당 이벤트 자동 열기
  if (route.query.open) {
    try {
      const { data } = await axios.get('/api/events/' + route.query.open)
      if (data.data) {
        activeItem.value = data.data
        if (data.data.category) activeCat.value = data.data.event_type === 'somekorean' ? 'somekorean' : data.data.category
      }
    } catch {}
  }
})

watch(() => route.params.id, (newId, oldId) => {
  if (oldId && !newId) {
    loadPage()
    activeItem.value = null
  }
})
</script>