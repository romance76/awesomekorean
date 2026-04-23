<template>
<div class="min-h-screen bg-gray-50">
  <div class="max-w-7xl mx-auto px-4 py-5">
    <!-- 헤더 -->
    <div class="flex items-center justify-between mb-4">
      <h1 class="text-xl font-black text-gray-800">🎮 게임</h1>
      <div v-if="auth.isLoggedIn" class="flex items-center gap-2 text-xs">
        <span class="bg-amber-100 text-amber-700 px-3 py-1.5 rounded-full font-bold">🪙 {{ (auth.user?.points || 0).toLocaleString() }}P</span>
        <RouterLink to="/points" class="bg-amber-400 text-amber-900 font-bold px-3 py-1.5 rounded-full hover:bg-amber-500">🎰 일일 룰렛</RouterLink>
      </div>
    </div>

    <div class="grid grid-cols-12 gap-4">
      <!-- 왼쪽: 카테고리 -->
      <div class="col-span-12 lg:col-span-2 hidden lg:block">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden sticky top-20">
          <div class="px-3 py-2.5 border-b font-bold text-xs text-amber-900">📋 카테고리</div>
          <button v-for="cat in categories" :key="cat.key" @click="activeCat=cat.key"
            class="w-full text-left px-3 py-2 text-xs transition"
            :class="activeCat===cat.key ? 'bg-amber-50 text-amber-700 font-bold' : 'text-gray-600 hover:bg-amber-50/50'">
            {{ cat.icon }} {{ cat.label }}
          </button>
        </div>
      </div>

      <!-- 메인: 게임 카드 -->
      <div class="col-span-12 lg:col-span-7">
        <!-- 모바일 카테고리 -->
        <div class="lg:hidden flex gap-1.5 mb-3 overflow-x-auto pb-1 scrollbar-hide">
          <button v-for="cat in categories" :key="cat.key" @click="activeCat=cat.key"
            class="px-3 py-1.5 rounded-full text-xs font-bold whitespace-nowrap transition flex-shrink-0"
            :class="activeCat===cat.key ? 'bg-amber-400 text-amber-900' : 'bg-white border text-gray-500'">
            {{ cat.icon }} {{ cat.label }}
          </button>
        </div>

        <div v-if="loading" class="text-center py-16 text-sm text-gray-400">로딩중...</div>
        <div v-else-if="!filteredGames.length" class="text-center py-16 text-sm text-gray-400">게임이 없습니다</div>
        <div v-else class="grid grid-cols-2 sm:grid-cols-3 gap-3">
          <RouterLink v-for="game in filteredGames" :key="game.path" :to="game.path"
            class="rounded-xl shadow-sm border p-4 hover:shadow-md hover:-translate-y-0.5 transition-all text-center group"
            :class="game.slug === 'casino'
              ? 'bg-gradient-to-br from-amber-50 to-orange-100 border-amber-300 col-span-2 sm:col-span-3 flex items-center gap-4 text-left p-5'
              : 'bg-white border-gray-100'">
            <div :class="game.slug === 'casino' ? 'text-5xl flex-shrink-0' : 'text-3xl mb-2'">{{ game.icon }}</div>
            <div class="flex-1 min-w-0">
              <div :class="game.slug === 'casino'
                ? 'text-lg font-black text-amber-900'
                : 'text-sm font-bold text-gray-800 group-hover:text-amber-700'">
                {{ game.name }}
              </div>
              <div :class="game.slug === 'casino' ? 'text-xs text-amber-700 mt-0.5' : 'text-[10px] text-gray-400 mt-0.5'">
                {{ game.description }}
              </div>
              <div v-if="game.slug === 'casino'" class="flex gap-1 mt-2 text-lg">
                <span>♠️</span><span>♦️</span><span>🎴</span><span>🂡</span>
              </div>
            </div>
            <div v-if="game.slug === 'casino'" class="flex-shrink-0 bg-amber-500 text-white font-bold px-4 py-2 rounded-full text-sm group-hover:bg-amber-600">
              입장 →
            </div>
          </RouterLink>
        </div>
      </div>

      <!-- 오른쪽: 위젯 -->
      <div class="col-span-12 lg:col-span-3 hidden lg:block space-y-3">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
          <div class="px-3 py-2.5 border-b font-bold text-xs text-amber-900">🔥 인기 게임</div>
          <RouterLink v-for="g in popularGames" :key="g.path" :to="g.path"
            class="block px-3 py-2 hover:bg-amber-50/50 transition text-xs text-gray-600 hover:text-amber-700">
            {{ g.icon }} {{ g.name }}
          </RouterLink>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-3">
          <div class="font-bold text-xs text-gray-800 mb-2">📢 게임 안내</div>
          <div class="text-[10px] text-gray-500 space-y-1">
            <div>• 게임 플레이 시 포인트 획득</div>
            <div>• 일일 룰렛으로 무료 포인트</div>
            <div>• 리더보드에 도전하세요</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useAuthStore } from '../../stores/auth'
import axios from 'axios'

const auth = useAuthStore()
const activeCat = ref('all')
const allGames = ref([])
const loading = ref(true)

const categories = [
  { key: 'all', icon: '🎮', label: '전체' },
  { key: 'card', icon: '🃏', label: '카드' },
  { key: 'brain', icon: '🧠', label: '두뇌' },
  { key: 'arcade', icon: '👾', label: '아케이드' },
  { key: 'word', icon: '📝', label: '단어/퀴즈' },
  { key: 'education', icon: '📚', label: '교육' },
]

const filteredGames = computed(() => {
  if (activeCat.value === 'all') return allGames.value
  return allGames.value.filter(g => g.category === activeCat.value)
})

const popularGames = computed(() => allGames.value.slice(0, 8))

onMounted(async () => {
  try {
    const { data } = await axios.get('/api/games')
    allGames.value = (data.data || []).map(g => ({
      slug: g.slug,
      path: g.path,
      icon: g.icon,
      name: g.name,
      description: g.description,
      category: g.category,
    }))
  } catch {}
  loading.value = false
})
</script>
