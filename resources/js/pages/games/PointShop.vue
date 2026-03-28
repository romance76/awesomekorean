<template>
  <div class="min-h-screen bg-gray-50 pb-20">
    <div class="bg-gradient-to-r from-purple-600 to-pink-500 text-white py-8 px-4 text-center">
      <div class="text-4xl mb-1">🛍️</div>
      <h1 class="text-xl font-bold">포인트샵</h1>
      <div class="mt-2 bg-white/20 inline-block rounded-full px-4 py-1">
        <span class="text-sm font-medium">보유: <span class="font-bold">{{ myPoints.toLocaleString() }}P</span></span>
      </div>
    </div>

    <!-- 카테고리 탭 -->
    <div class="flex overflow-x-auto bg-white border-b gap-0 px-2 pt-1">
      <button v-for="cat in categories" :key="cat.value"
        @click="activeCategory = cat.value"
        class="flex-shrink-0 px-4 py-2 text-sm whitespace-nowrap transition border-b-2"
        :class="activeCategory === cat.value ? 'border-purple-600 text-purple-600 font-medium' : 'border-transparent text-gray-500'">
        {{ cat.label }}
      </button>
    </div>

    <div class="max-w-[1200px] mx-auto px-4 py-4 space-y-3">
      <div v-if="loading" class="text-center py-10 text-gray-400">불러오는 중...</div>

      <div v-else>
        <div class="grid grid-cols-1 gap-3">
          <div v-for="item in filteredItems" :key="item.id"
            class="bg-white rounded-2xl shadow-sm p-4 flex items-center gap-4">
            <div class="text-4xl w-12 text-center flex-shrink-0">{{ item.icon }}</div>
            <div class="flex-1 min-w-0">
              <div class="font-bold text-gray-800">{{ item.name }}</div>
              <div class="text-xs text-gray-500 mt-0.5">{{ item.description }}</div>
              <div class="mt-2 flex items-center gap-2">
                <span class="text-purple-600 font-bold">{{ item.cost.toLocaleString() }}P</span>
                <span v-if="myPoints < item.cost" class="text-xs text-red-400">포인트 부족</span>
              </div>
            </div>
            <button @click="openConfirm(item)"
              :disabled="myPoints < item.cost"
              class="flex-shrink-0 px-4 py-2 rounded-xl text-sm font-bold transition"
              :class="myPoints >= item.cost
                ? 'bg-purple-600 hover:bg-purple-700 text-white'
                : 'bg-gray-200 text-gray-400 cursor-not-allowed'">
              구매
            </button>
          </div>
        </div>

        <div v-if="!filteredItems.length" class="text-center py-10 text-gray-400">
          해당 카테고리에 상품이 없습니다.
        </div>
      </div>
    </div>

    <!-- 구매 확인 모달 -->
    <div v-if="confirmItem" class="fixed inset-0 bg-black/60 flex items-end justify-center z-50 px-4 pb-6">
      <div class="bg-white rounded-2xl p-6 w-full max-w-sm">
        <div class="text-center mb-4">
          <div class="text-5xl mb-2">{{ confirmItem.icon }}</div>
          <h3 class="font-bold text-gray-800 text-lg">{{ confirmItem.name }}</h3>
          <p class="text-gray-500 text-sm mt-1">{{ confirmItem.description }}</p>
        </div>
        <div class="bg-purple-50 rounded-xl p-3 text-center mb-4">
          <div class="text-purple-600 font-bold text-lg">{{ confirmItem.cost.toLocaleString() }}P 차감</div>
          <div class="text-gray-500 text-xs mt-0.5">
            잔여: {{ (myPoints - confirmItem.cost).toLocaleString() }}P
          </div>
        </div>
        <div class="flex gap-2">
          <button @click="confirmItem = null" class="flex-1 bg-gray-100 rounded-xl py-3 font-medium text-gray-700">취소</button>
          <button @click="doPurchase" :disabled="purchasing"
            class="flex-1 bg-purple-600 hover:bg-purple-700 disabled:opacity-50 text-white rounded-xl py-3 font-bold">
            {{ purchasing ? '구매 중...' : '구매하기' }}
          </button>
        </div>
      </div>
    </div>

    <!-- 성공 토스트 -->
    <div v-if="successMsg"
      class="fixed top-4 left-4 right-4 bg-green-600 text-white rounded-xl px-4 py-3 text-center font-medium z-50 shadow-lg">
      ✅ {{ successMsg }}
    </div>

    <!-- 에러 토스트 -->
    <div v-if="errorMsg"
      class="fixed bottom-24 left-4 right-4 bg-red-600 text-white rounded-xl px-4 py-3 text-center font-medium z-50">
      {{ errorMsg }}
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useAuthStore } from '../../stores/auth'
import axios from 'axios'

const auth = useAuthStore()
const myPoints   = ref(auth.user?.points_total ?? 0)
const items      = ref([])
const loading    = ref(false)
const confirmItem = ref(null)
const purchasing  = ref(false)
const successMsg  = ref('')
const errorMsg    = ref('')
const activeCategory = ref('all')

const categories = [
  { value: 'all',      label: '🛍️ 전체' },
  { value: 'title',    label: '👑 칭호' },
  { value: 'style',    label: '✨ 스타일' },
  { value: 'feature',  label: '📌 기능' },
  { value: 'match',    label: '💘 매칭' },
  { value: 'business', label: '🏆 업소' },
  { value: 'giftcard', label: '🎁 기프트카드' },
]

const filteredItems = computed(() => {
  if (activeCategory.value === 'all') return items.value
  return items.value.filter(i => i.type === activeCategory.value)
})

function openConfirm(item) {
  confirmItem.value = item
}

async function doPurchase() {
  if (!confirmItem.value) return
  purchasing.value = true
  try {
    const { data } = await axios.post('/api/games/shop/redeem', { item_id: confirmItem.value.id })
    myPoints.value = data.points_total
    auth.user.points_total = data.points_total
    successMsg.value = data.message
    confirmItem.value = null
    setTimeout(() => successMsg.value = '', 3000)
  } catch (e) {
    errorMsg.value = e.response?.data?.message || '구매에 실패했습니다.'
    setTimeout(() => errorMsg.value = '', 3000)
    confirmItem.value = null
  }
  purchasing.value = false
}

onMounted(async () => {
  loading.value = true
  try {
    const { data } = await axios.get('/api/games/shop')
    items.value = data
  } catch { }
  loading.value = false
})
</script>
