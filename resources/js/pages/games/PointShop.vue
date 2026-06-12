<template>
  <div class="min-h-screen pb-20">
    <div class="bg-gradient-to-r from-[#FF8A53] to-[#F2570F] text-white py-8 px-4 text-center">
      <div class="icon-chip w-12 h-12 bg-white/20 text-white mx-auto mb-2"><AppIcon name="shopping-bag" :size="26" /></div>
      <h1 class="text-xl font-bold">포인트샵</h1>
      <div class="mt-2 bg-white/20 inline-block rounded-full px-4 py-1">
        <span class="text-sm font-medium">보유: <span class="font-black">{{ myPoints.toLocaleString() }}P</span></span>
      </div>
    </div>

    <!-- 카테고리 탭 -->
    <div class="flex overflow-x-auto bg-white border-b border-gray-100 gap-0 px-2 pt-1">
      <button v-for="cat in categories" :key="cat.value"
        @click="activeCategory = cat.value"
        class="flex-shrink-0 px-4 py-2 text-sm whitespace-nowrap transition-colors border-b-2"
        :class="activeCategory === cat.value ? 'border-amber-500 text-amber-600 font-semibold' : 'border-transparent text-ink-muted'">
        {{ cat.label }}
      </button>
    </div>

    <div class="max-w-[1200px] mx-auto px-4 py-4 space-y-3">
      <div v-if="loading" class="text-center py-10 text-ink-muted">불러오는 중...</div>

      <div v-else>
        <div class="grid grid-cols-1 gap-3">
          <div v-for="item in filteredItems" :key="item.id"
            class="card p-4 flex items-center gap-4">
            <div class="text-4xl w-12 text-center flex-shrink-0">{{ item.icon }}</div>
            <div class="flex-1 min-w-0">
              <div class="font-bold text-ink">{{ item.name }}</div>
              <div class="text-xs text-ink-muted mt-0.5">{{ item.description }}</div>
              <div class="mt-2 flex items-center gap-2">
                <span class="text-amber-600 font-black">{{ item.cost.toLocaleString() }}P</span>
                <span v-if="myPoints < item.cost" class="text-xs text-red-400">포인트 부족</span>
              </div>
            </div>
            <button @click="openConfirm(item)"
              :disabled="myPoints < item.cost"
              class="flex-shrink-0 px-4 py-2 rounded-xl text-sm font-bold transition-colors"
              :class="myPoints >= item.cost
                ? 'bg-amber-400 hover:bg-amber-500 text-white shadow-btn'
                : 'bg-gray-100 text-ink-faint cursor-not-allowed'">
              구매
            </button>
          </div>
        </div>

        <div v-if="!filteredItems.length" class="py-16 text-center">
          <div class="icon-chip w-14 h-14 bg-gray-100 text-gray-300 mx-auto mb-3"><AppIcon name="shopping-bag" :size="28" :stroke-width="1.5" /></div>
          <p class="text-sm text-ink-muted">해당 카테고리에 상품이 없습니다.</p>
        </div>
      </div>
    </div>

    <!-- 구매 확인 모달 -->
    <div v-if="confirmItem" class="fixed inset-0 bg-black/60 flex items-end justify-center z-50 px-4 pb-6">
      <div class="bg-white rounded-2xl p-6 w-full max-w-sm">
        <div class="text-center mb-4">
          <div class="text-5xl mb-2">{{ confirmItem.icon }}</div>
          <h3 class="font-bold text-ink text-lg">{{ confirmItem.name }}</h3>
          <p class="text-ink-muted text-sm mt-1">{{ confirmItem.description }}</p>
        </div>
        <div class="bg-amber-50 rounded-xl p-3 text-center mb-4">
          <div class="text-amber-600 font-black text-lg">{{ confirmItem.cost.toLocaleString() }}P 차감</div>
          <div class="text-ink-muted text-xs mt-0.5">
            잔여: {{ (myPoints - confirmItem.cost).toLocaleString() }}P
          </div>
        </div>
        <div class="flex gap-2">
          <button @click="confirmItem = null" class="flex-1 bg-gray-100 hover:bg-gray-200 rounded-xl py-3 font-semibold text-ink-light transition-colors">취소</button>
          <button @click="doPurchase" :disabled="purchasing"
            class="flex-1 bg-amber-400 hover:bg-amber-500 disabled:opacity-50 text-white rounded-xl py-3 font-bold transition-colors shadow-btn">
            {{ purchasing ? '구매 중...' : '구매하기' }}
          </button>
        </div>
      </div>
    </div>

    <!-- 성공 토스트 -->
    <div v-if="successMsg"
      class="fixed top-4 left-4 right-4 bg-green-600 text-white rounded-xl px-4 py-3 text-center font-medium z-50 shadow-lg flex items-center justify-center gap-1.5">
      <AppIcon name="check" :size="16" /> {{ successMsg }}
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
import AppIcon from '../../components/AppIcon.vue'
import axios from 'axios'

const auth = useAuthStore()
const myPoints   = ref(auth.user?.points ?? 0)
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
    myPoints.value = data.points
    auth.user.points = data.points
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
