<template>
  <div class="min-h-screen bg-gray-50 pb-16">
    <div class="max-w-[1200px] mx-auto px-4 pt-4">

    <!-- 헤더 -->
    <div class="bg-gradient-to-r from-orange-500 to-amber-500 text-white rounded-2xl px-6 py-5 mb-6 flex items-center justify-between">
      <div>
        <h1 class="text-xl font-black mb-1">🛒 공동구매</h1>
        <p class="text-white/75 text-sm opacity-80 mt-0.5">함께 사면 더 저렴하게! 한인 그룹 바이</p>
      </div>
      <button v-if="auth.isLoggedIn" @click="showWrite = true"
        class="bg-white text-orange-600 font-black px-4 py-2 rounded-xl text-sm hover:bg-orange-50 transition">
        + 공동구매 등록
      </button>
    </div>

    <!-- 카테고리 필터 -->
    <div class="flex gap-2 overflow-x-auto pb-2 mb-4 scrollbar-hide">
      <button v-for="cat in categories" :key="cat.key" @click="activeCat = cat.key; fetchItems()"
        class="flex-shrink-0 px-4 py-2 rounded-full text-sm font-semibold transition"
        :class="activeCat === cat.key ? 'bg-orange-500 text-white' : 'bg-white text-gray-600 border border-gray-200'">
        {{ cat.icon }} {{ cat.label }}
      </button>
    </div>

    <!-- Location Bar -->
    <div class="max-w-[1200px] mx-auto px-4 mt-2">
      <LocationBar placeholder="공동구매 검색..." default-radius="전국" @search="onLocationSearch" @location-change="onLocationChange" />
    </div>

    <!-- 목록 -->
    <div v-if="loading" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <div v-for="i in 6" :key="i" class="bg-white rounded-2xl h-48 animate-pulse"></div>
    </div>
    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <div v-for="item in items" :key="item.id" @click="openDetail(item)"
        class="bg-white rounded-2xl shadow-sm hover:shadow-md transition cursor-pointer overflow-hidden">
        <div v-if="item.images?.[0]" class="h-40 bg-gray-100 overflow-hidden">
          <img :src="item.images[0]" class="w-full h-full object-cover" />
        </div>
        <div v-else class="h-32 bg-gradient-to-br from-orange-400 to-amber-400 flex items-center justify-center">
          <span class="text-4xl">🛒</span>
        </div>
        <div class="p-4">
          <!-- 상태 배지 -->
          <div class="flex items-center gap-2 mb-2">
            <span class="text-xs font-bold px-2 py-0.5 rounded-full"
              :class="item.status==='open' ? 'bg-green-100 text-green-700' : item.status==='completed' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-500'">
              {{ item.status==='open' ? '모집중' : item.status==='completed' ? '달성완료' : '취소' }}
            </span>
            <span class="text-xs text-gray-400">{{ item.category }}</span>
          </div>
          <h3 class="font-bold text-gray-800 text-sm mb-2 line-clamp-2">{{ item.title }}</h3>

          <!-- 진행률 바 -->
          <div class="mb-2">
            <div class="flex justify-between text-xs text-gray-500 mb-1">
              <span>{{ item.participants_count }}명 참여</span>
              <span>최소 {{ item.min_participants }}명</span>
            </div>
            <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
              <div class="h-full bg-orange-400 rounded-full transition-all"
                :style="{width: Math.min(100, (item.participants_count / item.min_participants) * 100) + '%'}"></div>
            </div>
          </div>

          <div class="flex items-center justify-between">
            <span class="font-black text-orange-600 text-sm">${{ item.target_price }}</span>
            <span class="text-xs text-gray-400">{{ deadlineText(item.deadline) }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- 빈 상태 -->
    <div v-if="!loading && !items.length" class="text-center py-16 text-gray-400">
      <p class="text-5xl mb-3">🛒</p>
      <p class="font-semibold">등록된 공동구매가 없습니다</p>
      <button v-if="auth.isLoggedIn" @click="showWrite=true" class="mt-4 bg-orange-500 text-white px-6 py-2.5 rounded-xl font-bold text-sm">첫 공동구매 등록</button>
    </div>

    <!-- 상세 모달 -->
    <Transition name="fade">
      <div v-if="selected" class="fixed inset-0 bg-black/60 flex items-center justify-center z-50 px-4" @click.self="selected=null">
        <div class="bg-white rounded-2xl max-w-lg w-full max-h-[85vh] overflow-y-auto shadow-2xl">
          <div v-if="selected.images?.[0]" class="h-52 overflow-hidden rounded-t-2xl">
            <img :src="selected.images[0]" class="w-full h-full object-cover" />
          </div>
          <div class="p-6">
            <div class="flex items-start justify-between mb-3">
              <h2 class="font-black text-gray-800 text-lg flex-1">{{ selected.title }}</h2>
              <button @click="selected=null" class="text-gray-400 ml-2">✕</button>
            </div>
            <p class="text-sm text-gray-600 leading-relaxed mb-4 whitespace-pre-wrap">{{ selected.description }}</p>

            <!-- 참여 현황 -->
            <div class="bg-orange-50 rounded-xl p-4 mb-4">
              <div class="flex justify-between items-center mb-2">
                <span class="text-sm font-bold text-orange-700">참여 현황</span>
                <span class="text-sm font-black text-orange-600">{{ selected.participants_count }} / {{ selected.min_participants }}명</span>
              </div>
              <div class="h-3 bg-orange-100 rounded-full overflow-hidden">
                <div class="h-full bg-orange-500 rounded-full"
                  :style="{width: Math.min(100, (selected.participants_count / selected.min_participants) * 100) + '%'}"></div>
              </div>
              <p class="text-xs text-orange-600 mt-1">최소 {{ selected.min_participants }}명 달성 시 구매 진행</p>
            </div>

            <div class="flex items-center gap-2 mb-4">
              <span class="text-2xl font-black text-orange-600">${{ selected.target_price }}</span>
              <span class="text-sm text-gray-400">목표가</span>
            </div>

            <a v-if="selected.product_url" :href="selected.product_url" target="_blank"
              class="flex items-center gap-1 text-blue-600 text-sm hover:underline mb-4">
              🔗 상품 링크
            </a>

            <div class="flex gap-3">
              <button v-if="selected.status === 'open' && auth.isLoggedIn && !selected.is_joined"
                @click="join(selected.id)"
                :disabled="joining"
                class="flex-1 bg-orange-500 text-white py-3 rounded-xl font-black text-sm disabled:opacity-50">
                {{ joining ? '처리중...' : '🙋 참여하기' }}
              </button>
              <button v-else-if="selected.is_joined"
                @click="leave(selected.id)"
                class="flex-1 border border-gray-200 text-gray-600 py-3 rounded-xl font-semibold text-sm">
                참여 취소
              </button>
              <button v-else-if="!auth.isLoggedIn" @click="$router.push('/auth/login')"
                class="flex-1 bg-orange-500 text-white py-3 rounded-xl font-black text-sm">
                로그인 후 참여
              </button>
              <span v-else class="flex-1 text-center text-sm text-gray-400 py-3">
                {{ selected.status === 'completed' ? '✅ 달성 완료' : '❌ 취소됨' }}
              </span>
            </div>
          </div>
        </div>
      </div>
    </Transition>

    <!-- 등록 모달 -->
    <Transition name="fade">
      <div v-if="showWrite" class="fixed inset-0 bg-black/60 flex items-center justify-center z-50 px-4" @click.self="showWrite=false">
        <div class="bg-white rounded-2xl max-w-md w-full max-h-[90vh] overflow-y-auto shadow-2xl">
          <div class="bg-gradient-to-r from-orange-500 to-amber-500 text-white px-6 py-4 rounded-t-2xl">
            <h2 class="font-black text-lg">🛒 공동구매 등록</h2>
          </div>
          <div class="p-5 space-y-4">
            <div>
              <label class="block text-xs font-semibold text-gray-500 mb-1">제목 *</label>
              <input v-model="form.title" type="text" placeholder="예: 삼양 불닭볶음면 24개입 공동구매"
                class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-orange-400" />
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-500 mb-1">설명 *</label>
              <textarea v-model="form.description" rows="3" placeholder="상품 설명, 배송 방법 등..."
                class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm resize-none focus:outline-none focus:border-orange-400"></textarea>
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-xs font-semibold text-gray-500 mb-1">목표가 ($) *</label>
                <input v-model="form.target_price" type="number" placeholder="0.00"
                  class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-orange-400" />
              </div>
              <div>
                <label class="block text-xs font-semibold text-gray-500 mb-1">최소 인원 *</label>
                <input v-model="form.min_participants" type="number" min="2" placeholder="2"
                  class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-orange-400" />
              </div>
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-500 mb-1">카테고리</label>
              <select v-model="form.category" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-orange-400">
                <option v-for="c in categories.filter(c=>c.key!=='all')" :key="c.key" :value="c.key">{{ c.icon }} {{ c.label }}</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-500 mb-1">마감일 *</label>
              <input v-model="form.deadline" type="datetime-local"
                class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-orange-400" />
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-500 mb-1">상품 링크 (선택)</label>
              <input v-model="form.product_url" type="url" placeholder="https://..."
                class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-orange-400" />
            </div>
            <div class="flex gap-3 pt-2">
              <button @click="showWrite=false" class="flex-1 border border-gray-200 text-gray-600 py-2.5 rounded-xl font-semibold text-sm">취소</button>
              <button @click="submit" :disabled="submitting || !form.title || !form.target_price"
                class="flex-1 bg-orange-500 text-white py-2.5 rounded-xl font-black text-sm disabled:opacity-50">
                {{ submitting ? '등록 중...' : '등록하기' }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import { useAuthStore } from '../../stores/auth'
import LocationBar from '../../components/location/LocationBar.vue'

const auth = useAuthStore()

const categories = [
  { key: 'all',      icon: '🌐', label: '전체' },
  { key: 'food',     icon: '🍜', label: '식품' },
  { key: 'beauty',   icon: '💄', label: '뷰티' },
  { key: 'health',   icon: '💊', label: '건강' },
  { key: 'baby',     icon: '👶', label: '육아' },
  { key: 'fashion',  icon: '👗', label: '패션' },
  { key: 'elec',     icon: '📱', label: '전자제품' },
  { key: 'general',  icon: '📦', label: '기타' },
]

const activeCat = ref('all')
const items     = ref([])
const loading   = ref(true)
const selected  = ref(null)
const showWrite = ref(false)
const joining   = ref(false)
const submitting= ref(false)

const form = ref({
  title: '', description: '', target_price: '', min_participants: 2,
  category: 'food', deadline: '', product_url: '',
})

async function fetchItems() {
  loading.value = true
  try {
    const { data } = await axios.get('/api/groupbuy', {
      params: activeCat.value !== 'all' ? { category: activeCat.value } : {}
    })
    items.value = data.data ?? data
  } catch {} finally { loading.value = false }
}

onMounted(fetchItems)

function openDetail(item) {
  selected.value = item
}

function deadlineText(dt) {
  if (!dt) return ''
  const diff = (new Date(dt) - Date.now()) / 1000
  if (diff < 0) return '마감'
  if (diff < 3600) return `${Math.floor(diff / 60)}분 남음`
  if (diff < 86400) return `${Math.floor(diff / 3600)}시간 남음`
  return `${Math.floor(diff / 86400)}일 남음`
}

async function join(id) {
  joining.value = true
  try {
    const { data } = await axios.post(`/api/groupbuy/${id}/join`)
    if (selected.value) {
      selected.value.participants_count = data.participants_count
      selected.value.is_joined = true
    }
    const idx = items.value.findIndex(i => i.id === id)
    if (idx >= 0) items.value[idx].participants_count = data.participants_count
  } catch (e) { alert(e?.response?.data?.message || '참여 실패') }
  finally { joining.value = false }
}

async function leave(id) {
  try {
    await axios.post(`/api/groupbuy/${id}/leave`)
    if (selected.value) { selected.value.is_joined = false; selected.value.participants_count-- }
  } catch {}
}

async function submit() {
  if (!form.value.title || !form.value.target_price || !form.value.deadline) return
  submitting.value = true
  try {
    const { data } = await axios.post('/api/groupbuy', form.value)
    items.value.unshift(data)
    showWrite.value = false
    form.value = { title: '', description: '', target_price: '', min_participants: 2, category: 'food', deadline: '', product_url: '' }
  } catch (e) { alert(e?.response?.data?.message || '등록 실패') }
  finally { submitting.value = false }
}

// LocationBar handlers
function onLocationSearch(keyword) {
  searchKeyword.value = keyword
  fetchItems()
}

function onLocationChange(location) {
  console.log('Location changed:', location)
  fetchItems()
}

</script>

<style scoped>
.scrollbar-hide::-webkit-scrollbar { display: none; }
.scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
.fade-enter-active, .fade-leave-active { transition: opacity 0.2s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
.line-clamp-2 { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
</style>
