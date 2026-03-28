<template>
  <div class="min-h-screen bg-gray-50 pb-16">
    <div v-if="loading" class="text-center py-20 text-gray-400">불러오는 중...</div>
    <template v-else-if="listing">
      <div class="max-w-[1200px] mx-auto px-4 pt-4">

        <!-- Photo Gallery -->
        <div class="bg-white rounded-2xl shadow-sm overflow-hidden mb-4">
          <div class="relative">
            <div class="aspect-[16/9] bg-gray-100 flex items-center justify-center">
              <img v-if="mainPhoto" :src="mainPhoto" class="w-full h-full object-cover" />
              <span v-else class="text-7xl text-gray-300">&#x1F3E0;</span>
            </div>
            <!-- Type badge -->
            <span class="absolute top-4 left-4 text-sm font-bold px-3 py-1.5 rounded-full shadow-lg"
              :class="typeBadgeClass">
              {{ listing.type }}
            </span>
          </div>
          <!-- Thumbnails -->
          <div v-if="listing.photos?.length > 1" class="p-3 flex gap-2 overflow-x-auto">
            <button v-for="(photo, idx) in listing.photos" :key="idx"
              @click="selectedPhotoIdx = idx"
              class="w-20 h-20 flex-shrink-0 rounded-lg overflow-hidden border-2 transition"
              :class="selectedPhotoIdx === idx ? 'border-blue-500' : 'border-transparent'">
              <img :src="photo" class="w-full h-full object-cover" />
            </button>
          </div>
        </div>

        <!-- Price + Title -->
        <div class="bg-white rounded-2xl shadow-sm p-5 mb-4">
          <div class="flex items-start justify-between mb-3">
            <div class="flex-1 min-w-0">
              <h1 class="text-xl font-black text-gray-900 mb-2">{{ listing.title }}</h1>
              <p class="text-3xl font-black text-blue-600">
                {{ listing.price ? '$' + Number(listing.price).toLocaleString() : '가격문의' }}
                <span v-if="(listing.type === '렌트' || listing.type === '룸메이트') && listing.price" class="text-base font-medium text-gray-400">/월</span>
              </p>
            </div>
            <!-- Actions -->
            <div class="flex gap-2 flex-shrink-0">
              <button @click="toggleBookmark" class="w-10 h-10 rounded-full flex items-center justify-center transition"
                :class="bookmarked ? 'bg-yellow-100 text-yellow-500' : 'bg-gray-100 text-gray-400 hover:bg-gray-200'">
                {{ bookmarked ? '&#x2B50;' : '&#x2606;' }}
              </button>
              <button @click="shareListing" class="w-10 h-10 rounded-full bg-gray-100 text-gray-400 hover:bg-gray-200 flex items-center justify-center">
                &#x1F517;
              </button>
            </div>
          </div>

          <p class="text-sm text-gray-500 mb-4">&#x1F4CD; {{ listing.address }}</p>

          <!-- Property specs -->
          <div class="grid grid-cols-4 gap-3 bg-gray-50 rounded-xl p-4 mb-4">
            <div class="text-center" v-if="listing.bedrooms !== null && listing.bedrooms !== undefined">
              <p class="text-lg font-black text-gray-800">{{ listing.bedrooms }}</p>
              <p class="text-xs text-gray-500">방</p>
            </div>
            <div class="text-center" v-if="listing.bathrooms !== null && listing.bathrooms !== undefined">
              <p class="text-lg font-black text-gray-800">{{ listing.bathrooms }}</p>
              <p class="text-xs text-gray-500">욕실</p>
            </div>
            <div class="text-center" v-if="listing.sqft">
              <p class="text-lg font-black text-gray-800">{{ Number(listing.sqft).toLocaleString() }}</p>
              <p class="text-xs text-gray-500">sqft</p>
            </div>
            <div class="text-center">
              <p class="text-lg font-black text-gray-800">{{ listing.type }}</p>
              <p class="text-xs text-gray-500">유형</p>
            </div>
          </div>

          <!-- Additional info -->
          <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 mb-4">
            <div v-if="listing.deposit" class="bg-blue-50 rounded-xl p-3">
              <p class="text-xs text-gray-500">디파짓</p>
              <p class="font-bold text-gray-800">${{ Number(listing.deposit).toLocaleString() }}</p>
            </div>
            <div v-if="listing.move_in_date" class="bg-green-50 rounded-xl p-3">
              <p class="text-xs text-gray-500">입주 가능일</p>
              <p class="font-bold text-gray-800">{{ listing.move_in_date }}</p>
            </div>
            <div v-if="listing.pet_policy" class="bg-purple-50 rounded-xl p-3">
              <p class="text-xs text-gray-500">반려동물</p>
              <p class="font-bold text-gray-800">{{ listing.pet_policy }}</p>
            </div>
          </div>

          <!-- Description -->
          <div class="border-t border-gray-100 pt-4">
            <h2 class="font-bold text-gray-800 text-sm mb-2">상세 설명</h2>
            <p class="text-gray-700 text-sm leading-relaxed whitespace-pre-wrap">{{ listing.description }}</p>
          </div>
        </div>

        <!-- Google Maps -->
        <div class="bg-white rounded-2xl shadow-sm overflow-hidden mb-4">
          <div class="p-4 border-b border-gray-100 flex items-center justify-between">
            <h2 class="font-bold text-gray-800 text-sm">&#x1F4CD; 위치</h2>
            <a :href="`https://www.google.com/maps/dir/?api=1&destination=${encodeURIComponent(listing.address)}`"
              target="_blank" rel="noopener"
              class="text-blue-600 text-xs font-bold hover:underline flex items-center gap-1">
              &#x1F697; 길찾기
            </a>
          </div>
          <div class="aspect-[16/7]">
            <iframe
              :src="`https://www.google.com/maps?q=${encodeURIComponent(listing.address)}&output=embed`"
              class="w-full h-full border-0"
              allowfullscreen loading="lazy"
              referrerpolicy="no-referrer-when-downgrade" />
          </div>
        </div>

        <!-- Contact -->
        <div class="bg-white rounded-2xl shadow-sm p-5 mb-4">
          <h2 class="font-bold text-gray-800 text-sm mb-3">&#x1F4DE; 연락처</h2>
          <div class="flex flex-col sm:flex-row gap-3">
            <a v-if="listing.phone" :href="`tel:${listing.phone}`"
              class="flex-1 bg-green-500 text-white text-center py-3 rounded-xl font-bold text-sm hover:bg-green-600 transition">
              &#x1F4DE; {{ listing.phone }}
            </a>
            <a v-if="listing.email" :href="`mailto:${listing.email}`"
              class="flex-1 bg-blue-500 text-white text-center py-3 rounded-xl font-bold text-sm hover:bg-blue-600 transition">
              &#x2709;&#xFE0F; 이메일 보내기
            </a>
            <button v-if="authStore.isLoggedIn && !canEdit" @click="sendMessage"
              class="flex-1 bg-indigo-500 text-white text-center py-3 rounded-xl font-bold text-sm hover:bg-indigo-600 transition">
              &#x1F4AC; 쪽지 보내기
            </button>
          </div>
        </div>

        <!-- Seller info -->
        <div class="bg-white rounded-2xl shadow-sm p-5 mb-4">
          <div class="flex items-center gap-3">
            <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center text-blue-600 text-lg font-bold">
              {{ listing.user?.name?.[0] || '?' }}
            </div>
            <div>
              <p class="font-bold text-gray-800">{{ listing.user?.name || '익명' }}</p>
              <p class="text-xs text-gray-400">{{ formatDate(listing.created_at) }} 등록</p>
            </div>
            <div v-if="canEdit" class="ml-auto flex gap-2">
              <button @click="deleteListing" class="text-xs text-red-500 bg-red-50 px-3 py-1.5 rounded-lg hover:bg-red-100">삭제</button>
            </div>
          </div>
        </div>

        <!-- Comments -->
        <div class="bg-white rounded-2xl shadow-sm p-5 mb-4">
          <h2 class="font-bold text-gray-800 text-sm mb-3">&#x1F4AC; 문의/댓글 ({{ comments.length }})</h2>

          <!-- Write comment -->
          <div v-if="authStore.isLoggedIn" class="flex gap-2 mb-4">
            <input v-model="newComment" @keyup.enter="postComment" type="text" placeholder="문의 사항을 입력하세요..."
              class="flex-1 border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-blue-400" />
            <button @click="postComment" :disabled="!newComment.trim()"
              class="bg-blue-600 text-white px-4 py-2.5 rounded-xl text-sm font-bold hover:bg-blue-700 disabled:opacity-50 flex-shrink-0">
              등록
            </button>
          </div>

          <!-- Comment list -->
          <div v-if="comments.length === 0" class="text-center py-6 text-gray-400 text-sm">아직 문의가 없습니다</div>
          <div v-else class="space-y-3">
            <div v-for="c in comments" :key="c.id" class="flex gap-3 p-3 bg-gray-50 rounded-xl">
              <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center text-blue-600 text-xs font-bold flex-shrink-0">
                {{ (c.user?.name || c.user_name || '익명')[0] }}
              </div>
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 mb-0.5">
                  <span class="font-semibold text-gray-800 text-sm">{{ c.user?.name || c.user_name || '익명' }}</span>
                  <span class="text-xs text-gray-400">{{ formatDate(c.created_at) }}</span>
                </div>
                <p class="text-sm text-gray-700">{{ c.content }}</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Back link -->
        <router-link to="/realestate" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-blue-600 mb-8">
          &#x2190; 목록으로
        </router-link>
      </div>
    </template>
    <div v-else class="text-center py-20 text-gray-400">매물을 찾을 수 없습니다.</div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import axios from 'axios'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()

const listing = ref(null)
const loading = ref(true)
const selectedPhotoIdx = ref(0)
const bookmarked = ref(false)
const newComment = ref('')
const comments = ref([])

const mainPhoto = computed(() => {
  if (!listing.value?.photos?.length) return null
  return listing.value.photos[selectedPhotoIdx.value] || listing.value.photos[0]
})

const canEdit = computed(() =>
  authStore.user && (authStore.user.id === listing.value?.user_id || authStore.user.is_admin)
)

const typeBadgeClass = computed(() => {
  const t = listing.value?.type
  if (t === '매매') return 'bg-red-500 text-white'
  if (t === '렌트') return 'bg-blue-500 text-white'
  if (t === '룸메이트') return 'bg-green-500 text-white'
  return 'bg-orange-500 text-white'
})

// Mock data for demo
const mockListings = {
  1: { id: 1, title: '에이범동 2BR 아파트 렌트', type: '렌트', price: 2200, address: '3456 Wilshire Blvd, Los Angeles, CA 90010', bedrooms: 2, bathrooms: 1, sqft: 950, region: 'Los Angeles', photos: [], created_at: '2026-03-20', deposit: 2200, pet_policy: '협의', move_in_date: '2026-04-01', phone: '213-555-1234', email: 'rent@example.com', description: '에이버 지역 깨끗한 아파트입니다.\n- 주차 1대 포함\n- 세탁기/건조기 인유닛\n- 냉난방 중앙 시스템\n- 한인타운 5분 거리\n- 1년 리스 기본', user: { id: 2, name: '김부동산' } },
  2: { id: 2, title: '풀러턴 타운하우스 매매', type: '매매', price: 685000, address: '125 Peachtree St NE, Atlanta, GA 30303', bedrooms: 3, bathrooms: 2, sqft: 1800, region: 'Atlanta', photos: [], created_at: '2026-03-18', deposit: 0, pet_policy: '가능', phone: '770-555-5678', email: 'sale@example.com', description: '풀러턴 지역 타운하우스입니다.\n- 복층 구조 3베드 2배스\n- 2차 주차 가라지\n- 넓은 백야드\n- HOA $250/월\n- 좋은 학군', user: { id: 3, name: '이매매' } },
}

function toggleBookmark() {
  bookmarked.value = !bookmarked.value
}

function shareListing() {
  if (navigator.share) {
    navigator.share({ title: listing.value.title, url: window.location.href })
  } else {
    navigator.clipboard.writeText(window.location.href)
    alert('링크가 복사되었습니다!')
  }
}

function sendMessage() {
  router.push('/messages')
}

async function deleteListing() {
  if (!confirm('이 매물을 삭제하시겠습니까?')) return
  try {
    await axios.delete(`/api/realestate/${listing.value.id}`)
    router.push('/realestate')
  } catch (e) {
    alert('삭제 실패')
  }
}

async function postComment() {
  if (!newComment.value.trim()) return
  try {
    const { data } = await axios.post(`/api/realestate/${listing.value.id}/comments`, {
      content: newComment.value
    })
    comments.value.push(data.comment || {
      id: Date.now(),
      content: newComment.value,
      user: { name: authStore.user?.name },
      created_at: new Date().toISOString()
    })
    newComment.value = ''
  } catch (e) {
    alert('댓글 등록 실패')
  }
}

function formatDate(d) {
  if (!d) return ''
  return new Date(d).toLocaleDateString('ko-KR', { year: 'numeric', month: 'short', day: 'numeric' })
}

onMounted(async () => {
  const id = route.params.id
  try {
    const { data } = await axios.get(`/api/realestate/${id}`)
    listing.value = data
  } catch {
    // Use mock data as fallback
    listing.value = mockListings[id] || null
  }

  // Load comments
  try {
    const { data } = await axios.get(`/api/realestate/${id}/comments`)
    comments.value = data.data || data || []
  } catch {
    comments.value = []
  }

  loading.value = false
})
</script>
