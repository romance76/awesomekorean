<template>
<div class="min-h-screen bg-gray-50">
  <div class="max-w-7xl mx-auto px-4 py-5">
    <!-- 헤더: 데스크탑 -->
    <div class="hidden lg:flex items-center justify-between mb-4 flex-wrap gap-2">
      <h1 class="text-xl font-black text-gray-800">💼 구인구직</h1>
      <div class="flex items-center gap-2 flex-wrap">
        <select v-model="selectedCityIdx" @change="onCityChange"
          class="border border-gray-200 rounded-lg px-2 py-1.5 text-xs font-semibold text-gray-700 outline-none focus:ring-2 focus:ring-amber-400 bg-amber-50">
          <option value="-2" v-if="myCity">📌 내 위치 ({{ myCity.label || myCity.name }})</option>
          <option value="-1">🇺🇸 전국</option>
          <optgroup label="한인 밀집 도시">
            <option v-for="(c, i) in koreanCities" :key="i" :value="i">{{ c.label }}</option>
          </optgroup>
        </select>
        <select v-if="selectedCityIdx !== '-1' && selectedCityIdx !== -1" v-model="radius" @change="loadPage()"
          class="border border-gray-200 rounded-lg px-2 py-1.5 text-xs text-gray-600 outline-none">
          <option value="10">10mi</option><option value="30">30mi</option><option value="50">50mi</option><option value="100">100mi</option>
        </select>
        <form @submit.prevent="loadPage()" class="flex gap-1">
          <input v-model="search" type="text" placeholder="검색..." class="border rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-amber-400 outline-none w-40" />
          <button type="submit" class="bg-amber-400 text-amber-900 font-bold px-3 py-1.5 rounded-lg text-xs hover:bg-amber-500">검색</button>
        </form>
        <RouterLink v-if="auth.isLoggedIn" to="/jobs/write" class="bg-amber-400 text-amber-900 font-bold px-3 py-1.5 rounded-lg text-xs hover:bg-amber-500">✏️ 등록</RouterLink>
      </div>
    </div>

    <!-- 헤더: 모바일 (필터 바텀시트) -->
    <div class="lg:hidden mb-3">
      <div class="flex items-center justify-between mb-2">
        <h1 class="text-lg font-black text-gray-800">💼 구인구직</h1>
        <div class="flex items-center gap-2">
          <button @click="showFilter = true" class="bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold px-3 py-2 rounded-lg flex items-center gap-1">
            🔍 필터
          </button>
          <RouterLink v-if="auth.isLoggedIn" to="/jobs/write" class="bg-amber-400 text-amber-900 text-xs font-bold px-3 py-2 rounded-lg">✏️ 등록</RouterLink>
        </div>
      </div>
      <!-- 선택된 필터 태그 -->
      <div class="flex items-center gap-1.5 overflow-x-auto">
        <span class="text-[10px] px-2 py-0.5 rounded-full font-semibold whitespace-nowrap"
          :class="postType === 'hiring' ? 'bg-amber-100 text-amber-800' : 'bg-blue-100 text-blue-800'">
          {{ postType === 'hiring' ? '💼 구인' : '🙋 구직' }}
        </span>
        <span class="text-[10px] bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full font-semibold whitespace-nowrap">
          📍{{ selectedCityIdx == -1 ? '전국' : (koreanCities[selectedCityIdx]?.label || '내 위치') }}
        </span>
        <span v-if="search" class="text-[10px] bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full font-semibold whitespace-nowrap">
          "{{ search }}"
        </span>
      </div>
    </div>

    <!-- 모바일 필터 (위→아래) -->
    <MobileFilter v-model="showFilter" @apply="loadPage()" @reset="postType = 'hiring'; activeCat = ''; search = ''; selectedCityIdx = -1; onCityChange()">
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
        <input v-model="search" type="text" placeholder="직종, 회사명, 키워드..."
          class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-amber-400" />
      </div>
      <div class="mb-4">
        <label class="text-xs font-bold text-gray-600 mb-2 block">공고 유형</label>
        <div class="flex gap-2">
          <button @click="postType = 'hiring'" class="flex-1 py-2.5 rounded-lg font-bold text-sm border-2 transition"
            :class="postType === 'hiring' ? 'bg-amber-400 text-amber-900 border-amber-400' : 'border-gray-200 text-gray-500'">💼 구인</button>
          <button @click="postType = 'seeking'" class="flex-1 py-2.5 rounded-lg font-bold text-sm border-2 transition"
            :class="postType === 'seeking' ? 'bg-blue-500 text-white border-blue-500' : 'border-gray-200 text-gray-500'">🙋 구직</button>
        </div>
      </div>
      <div>
        <label class="text-xs font-bold text-gray-600 mb-2 block">카테고리</label>
        <div class="grid grid-cols-3 gap-1.5">
          <button v-for="c in jobCategories" :key="c.value" @click="activeCat = c.value"
            class="text-xs py-2 rounded-lg font-semibold border transition"
            :class="activeCat === c.value ? 'bg-amber-50 text-amber-700 border-amber-300' : 'border-gray-200 text-gray-600 hover:bg-gray-50'">
            {{ c.label }}
          </button>
        </div>
      </div>
    </MobileFilter>

    <div class="grid grid-cols-12 gap-4">
    <!-- 왼쪽: 카테고리 사이드바 + 구인/구직 토글 (lg 이상) -->
    <div class="col-span-12 lg:col-span-2 hidden lg:block">
      <div class="sticky top-20 max-h-[calc(100vh-6rem)] overflow-y-auto space-y-3 pr-0.5">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
          <div class="px-3 py-2.5 border-b font-bold text-xs text-gray-800 flex items-center justify-between">
            <span>📋 카테고리</span>
          </div>
          <!-- 구인/구직 작은 토글 -->
          <div class="flex border-b">
            <button @click="postType = 'hiring'; loadPage()"
              class="flex-1 py-1.5 text-[10px] font-bold transition"
              :class="postType === 'hiring' ? 'bg-amber-400 text-amber-900' : 'text-gray-400 hover:bg-gray-50'">
              💼 구인
            </button>
            <button @click="postType = 'seeking'; loadPage()"
              class="flex-1 py-1.5 text-[10px] font-bold transition"
              :class="postType === 'seeking' ? 'bg-blue-500 text-white' : 'text-gray-400 hover:bg-gray-50'">
              🙋 구직
            </button>
          </div>
          <button v-for="c in jobCategories" :key="c.value" @click="showFavorites=false; activeCat = c.value; loadPage()"
            class="w-full text-left px-3 py-2 text-xs transition"
            :class="!showFavorites && activeCat === c.value
              ? (postType === 'hiring' ? 'bg-amber-50 text-amber-700 font-bold' : 'bg-blue-50 text-blue-700 font-bold')
              : 'text-gray-600 hover:bg-gray-50'">{{ c.label }}</button>
          <button v-if="auth.isLoggedIn" @click="loadFavoritesPage()"
            class="w-full text-left px-3 py-2 text-xs transition border-t"
            :class="showFavorites ? 'bg-red-50 text-red-600 font-bold' : 'text-gray-600 hover:bg-red-50/50'">
            🔖 내 북마크<span v-if="favCount > 0" class="ml-0.5">({{ favCount }})</span>
          </button>
        </div>
        <AdSlot page="jobs" position="left" :maxSlots="2" />
      </div>
    </div>
    <div class="col-span-12 lg:col-span-7">

    <div class="mb-2">
      <span v-if="showFavorites" class="font-bold text-red-600 text-sm">🔖 내 북마크</span>
      <template v-else>
        <span class="text-sm font-bold" :class="postType === 'hiring' ? 'text-amber-700' : 'text-blue-700'">
          {{ activeCat ? (jobCategories.find(c => c.value === activeCat)?.label || activeCat) : '전체' }}
        </span>
        <span v-if="!activeCat" class="text-xs text-gray-400 ml-2">
          {{ postType === 'hiring' ? '모든 채용 공고를 볼 수 있습니다' : '모든 구직 인재를 볼 수 있습니다' }}
        </span>
      </template>
    </div>


    <!-- 목록 -->
    <div v-if="loading" class="text-center py-12 text-gray-400">로딩중...</div>
    <div v-else-if="!items.length" class="text-center py-12">
      <div class="text-4xl mb-3">{{ postType === 'hiring' ? '💼' : '🙋' }}</div>
      <div class="text-gray-500 font-semibold">검색 결과가 없습니다</div>
    </div>
    <div v-else class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
      <template v-for="(item, i) in items" :key="item.id">
      <div @click="goDetail(item)"
        class="px-4 py-3 border-b border-gray-50 transition cursor-pointer"
        :class="jobBorderClass(item)" :style="jobBorderStyle(item)">
        <div class="flex items-center gap-3">
          <!-- 로고 -->
          <img v-if="item.logo" :src="item.logo" class="w-12 h-12 rounded object-cover flex-shrink-0 border" @error="$event.target.style.display='none'" />
          <div v-else class="w-12 h-12 rounded bg-amber-50 flex items-center justify-center text-xl flex-shrink-0">{{ categoryEmoji(item.category) }}</div>

          <div class="flex-1 min-w-0">
            <!-- 1행: 프로모션 뱃지 + 직종 태그 (제목 위로) -->
            <div class="flex items-center gap-1 mb-0.5 flex-wrap">
              <span v-if="item.promotion_tier==='national'" class="text-[10px] bg-red-500 text-white font-bold px-1 py-px rounded">🌐 전국구</span>
              <span v-else-if="item.promotion_tier==='state_plus'" class="text-[10px] bg-blue-500 text-white font-bold px-1 py-px rounded">⭐ 주+</span>
              <span v-else-if="item.promotion_tier==='sponsored'" class="text-[10px] bg-amber-500 text-white font-bold px-1 py-px rounded">📢 스폰서</span>
              <span v-for="tag in (item.job_tags || []).slice(0,4)" :key="tag" class="text-[10px] bg-gray-100 text-gray-600 px-1 py-px rounded">{{ jobTagLabel(tag) }}</span>
            </div>
            <!-- 2행: 제목 -->
            <div class="text-sm font-medium text-gray-800 truncate">{{ item.title || item.name }}</div>
            <!-- 3행: 메타 -->
            <div class="text-xs text-gray-400 mt-0.5 flex items-center gap-1.5 flex-wrap">
              <template v-if="postType === 'seeking'">
                <UserName v-if="item.user?.id" :userId="item.user?.id" :name="item.user?.name" className="text-blue-600 font-semibold" />
                <span v-else class="text-blue-600 font-semibold">{{ item.user?.name || '익명' }}</span>
              </template>
              <template v-else>
                <UserName v-if="item.user?.id" :userId="item.user?.id" :name="item.user?.name || item.company || item.organizer" className="text-gray-600" />
                <span v-else-if="item.company || item.organizer">{{ item.company || item.organizer }}</span>
              </template>
              <span v-if="item.city" class="flex items-center gap-0.5">📍{{ item.city }}, {{ item.state }}</span>
              <span v-if="item.distance !== undefined && item.distance !== null"
                class="font-semibold" :class="postType === 'hiring' ? 'text-amber-600' : 'text-blue-600'">
                {{ Number(item.distance).toFixed(1) }}mi
              </span>
              <span v-if="item.created_at" class="text-gray-400">🕐 {{ fmtDate(item.created_at) }}</span>
              <span v-if="item.view_count">👁{{ item.view_count }}</span>
            </div>
          </div>
          <!-- 급여 + 하트 -->
          <div class="ml-3 flex-shrink-0 text-right flex flex-col items-end gap-1">
            <div v-if="item.salary_min"
              class="font-black text-base whitespace-nowrap" :class="postType === 'hiring' ? 'text-amber-600' : 'text-blue-600'">
              ${{ item.salary_min }}~${{ item.salary_max }} <span class="text-xs font-bold text-gray-500">/ {{ {hourly:'hr',monthly:'mo',yearly:'yr'}[item.salary_type] || item.salary_type }}</span>
            </div>
            <div v-else-if="item.price !== undefined && item.price !== null"
              class="font-black text-base" :class="postType === 'hiring' ? 'text-amber-600' : 'text-blue-600'">
              ${{ Number(item.price).toLocaleString() }}
            </div>
            <BookmarkToggle v-if="auth.isLoggedIn" :active="favorited.has(item.id)" @toggle="toggleFav(item)" size="sm" class="mt-1" />
          </div>
        </div>
      </div>
      <MobileAdInline v-if="i === 4" page="jobs" />
      </template>
    </div>

    <Pagination :page="page" :lastPage="lastPage" @page="loadPage" />
    </div>

    <!-- 오른쪽: 위젯 -->
    <div class="col-span-12 lg:col-span-3 hidden lg:block">
      <SidebarWidgets :currentCategory="activeCat" api-url="/api/jobs" detail-path="/jobs/" :current-id="0"
        :label="postType === 'hiring' ? '채용' : '구직'" :filter-params="locationParams" />
        <AdSlot page="jobs" position="right" :maxSlots="2" />
    </div>
    </div>
  </div>
</div>
</template>
<script setup>
import { useRoute, useRouter } from 'vue-router'
import { ref, computed, watch, onMounted } from 'vue'
import { useLocation } from '../../composables/useLocation'
import { useAuthStore } from '../../stores/auth'
import { useBookmarkStore } from '../../stores/bookmarks'
import { useLocationFilterStore } from '../../stores/locationFilter'
import SidebarWidgets from '../../components/SidebarWidgets.vue'
import axios from 'axios'
import AdSlot from '../../components/AdSlot.vue'
import BookmarkToggle from '../../components/BookmarkToggle.vue'

const auth = useAuthStore()
const bStore = useBookmarkStore()
const BM_TYPE = 'App\\Models\\JobPost'
const route = useRoute()
const router = useRouter()
const locFilter = useLocationFilterStore()

const postType = ref('hiring')
const activeCat = ref('')
const showFilter = ref(false)
const jobCategories = [
  { value: '', label: '전체' },
  { value: 'restaurant', label: '🍳 요식업' },
  { value: 'it', label: '💻 IT' },
  { value: 'beauty', label: '💅 미용' },
  { value: 'driving', label: '🚗 운전' },
  { value: 'retail', label: '🛒 판매' },
  { value: 'office', label: '🏢 사무직' },
  { value: 'construction', label: '🔨 건설' },
  { value: 'medical', label: '🏥 의료' },
  { value: 'education', label: '📚 교육' },
  { value: 'etc', label: '📋 기타' },
]
const { city, radius: locRadius, locationQuery, koreanCities, init: initLocation, selectKoreanCity, setRadius } = useLocation()

const items = ref([])
const loading = ref(true)

// URL 쿼리 변경 시 반영 (상세→목록 이동, 카테고리 클릭 등)
watch(() => route.query, (q) => {
  if (route.path !== '/jobs') return
  if (q.category !== undefined) activeCat.value = q.category || ''
  if (q.type === 'seeking') postType.value = 'seeking'
  else if (q.type === 'hiring') postType.value = 'hiring'
  if (q.search !== undefined) search.value = q.search || ''
  loadPage()
})

// 프로모션 헬퍼
function isPromoted(item) {
  return item.promotion_tier && item.promotion_tier !== 'none'
}
function promotionClass(item) {
  if (item.promotion_tier === 'national') return 'bg-red-50 border-l-4 border-l-red-500 hover:bg-red-100/60'
  if (item.promotion_tier === 'state_plus') return 'bg-blue-50 border-l-4 border-l-blue-500 hover:bg-blue-100/60'
  if (item.promotion_tier === 'sponsored') return 'bg-amber-50 border-l-4 border-l-amber-500 hover:bg-amber-100/60'
  return ''
}

function jobBorderClass(item) {
  if (['national','state_plus','sponsored'].includes(item.promotion_tier)) return 'rounded-lg my-1 hover:bg-gray-50'
  return 'hover:bg-gray-50'
}
function jobBorderStyle(item) {
  if (item.promotion_tier === 'national') return 'border: 2px solid #fca5a5; border-radius: 8px; background: #fef2f2;'
  if (item.promotion_tier === 'state_plus') return 'border: 2px solid #bfdbfe; border-radius: 8px; background: #f0f7ff;'
  if (item.promotion_tier === 'sponsored') return 'border: 2px solid #fde68a; border-radius: 8px; background: #fffef5;'
  return ''
}

// 좋아요 (Bookmark)
const favorited = ref(new Set())
async function loadFavorited() {
  if (!auth.isLoggedIn || !items.value.length) return
  try {
    const ids = items.value.map(i => i.id).join(',')
    const { data } = await axios.get('/api/bookmarks/check', {
      params: { type: 'App\\Models\\JobPost', ids },
    })
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

// 즐겨찾기 모드
const showFavorites = ref(false)
const favCount = computed(() => bStore.getBookmarkedIds(BM_TYPE).length)
async function loadFavoritesPage() {
  loading.value = true
  showFavorites.value = true
  try {
    const { data } = await axios.get('/api/bookmarks', {
      params: { type: 'App\\Models\\JobPost', per_page: 50 },
    })
    const bms = data.data?.data || []
    items.value = bms.map(b => b.bookmarkable).filter(Boolean)
    lastPage.value = 1
    loadFavorited()
  } catch {}
  loading.value = false
}

// 카테고리 이모지
const categoryEmojis = { restaurant:'🍳', it:'💻', beauty:'💅', driving:'🚗', retail:'🛒', office:'🏢', construction:'🔨', medical:'🏥', education:'📚', etc:'📋' }
function categoryEmoji(cat) { return categoryEmojis[cat] || '📋' }

// 직종 태그 레이블
const jobTagLabels = { cook:'요리', server:'서빙', cashier:'캐셔', manager:'매니저', barista:'바리스타', delivery:'배달', driver:'운전', mechanic:'정비', accountant:'회계', receptionist:'접수', cleaner:'청소', nail:'네일', hair:'미용사', esthetician:'피부관리', teacher:'강사', tutor:'과외', developer:'개발자', designer:'디자이너', sales:'영업', nurse:'간호사', security:'보안', warehouse:'창고' }
function jobTagLabel(tag) { return jobTagLabels[tag] || tag }

// 날짜 포맷 (상대시간)
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

function goDetail(item) {
  router.push('/jobs/' + item.id)
}

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
  // 페이지 내 이동에서도 유지되도록 섹션 스토어에 저장
  locFilter.set('jobs', { cityIdx: selectedCityIdx.value, radius: radius.value })
  loadPage()
}

// 현재 위치 파라미터 계산
function buildLocationParams() {
  const p = {}
  const idx = parseInt(selectedCityIdx.value)
  // user_state 는 프로모션 주 인접 매칭용: 내 위치/한인도시일 때 전달
  if (radius.value !== '0') {
    let lat, lng, state
    if (idx >= 0) {
      const kc = koreanCities[idx]
      lat = kc.lat; lng = kc.lng; state = kc.state
    } else if (idx === -2 && myCity.value?.lat) {
      lat = myCity.value.lat; lng = myCity.value.lng; state = myCity.value.state
    } else {
      const loc = locationQuery.value
      lat = loc.lat; lng = loc.lng
      state = myCity.value?.state
    }
    if (lat && lng) {
      p.lat = lat
      p.lng = lng
      p.radius = parseInt(radius.value)
      if (state) p.user_state = state
    }
  } else {
    // 전국 모드라도 user_state 는 프로모션 가중치에 사용 (있으면 전달)
    if (myCity.value?.state) p.user_state = myCity.value.state
  }
  return p
}

async function loadPage(p = 1) {
  loading.value = true
  page.value = p

  const params = { page: p, per_page: 20, ...buildLocationParams() }
  if (search.value) params.search = search.value
  if (activeCat.value) params.category = activeCat.value
  params.post_type = postType.value

  showFavorites.value = false
  try {
    const { data } = await axios.get('/api/jobs', { params })
    items.value = data.data?.data || []
    lastPage.value = data.data?.last_page || 1
    loadFavorited()
  } catch {}
  loading.value = false
}


onMounted(async () => {
  bStore.loadAll()
  // URL 쿼리 파라미터 반영
  if (route.query.category) activeCat.value = route.query.category
  if (route.query.type === 'seeking') postType.value = 'seeking'
  if (route.query.type === 'hiring') postType.value = 'hiring'
  if (route.query.search) search.value = route.query.search

  await initLocation()
  // myCity 는 항상 사용자 프로필 기준으로 세팅 (기본 위치)
  if (city.value) {
    myCity.value = { ...city.value }
  }

  // 이전에 이 섹션에서 선택한 필터 복원 (/jobs → /jobs/123 → /jobs 로 돌아왔을 때)
  const saved = locFilter.get('jobs')
  if (saved) {
    selectedCityIdx.value = saved.cityIdx
    radius.value = saved.radius
  } else if (myCity.value) {
    selectedCityIdx.value = '-2'
  } else {
    selectedCityIdx.value = '-1'
    radius.value = '0'
  }
  loadPage()
})
</script>
<style scoped>
.scrollbar-hide {
  -ms-overflow-style: none;
  scrollbar-width: none;
}
.scrollbar-hide::-webkit-scrollbar {
  display: none;
}
</style>
