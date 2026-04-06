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

        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
          <RouterLink v-for="game in filteredGames" :key="game.path" :to="game.path"
            class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 hover:shadow-md hover:-translate-y-0.5 transition-all text-center group">
            <div class="text-3xl mb-2">{{ game.icon }}</div>
            <div class="text-sm font-bold text-gray-800 group-hover:text-amber-700">{{ game.name }}</div>
            <div class="text-[10px] text-gray-400 mt-0.5">{{ game.desc }}</div>
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
import { ref, computed } from 'vue'
import { useAuthStore } from '../../stores/auth'

const auth = useAuthStore()
const activeCat = ref('all')

const categories = [
  { key: 'all', icon: '🎮', label: '전체' },
  { key: 'card', icon: '🃏', label: '카드' },
  { key: 'brain', icon: '🧠', label: '두뇌' },
  { key: 'arcade', icon: '👾', label: '아케이드' },
  { key: 'word', icon: '📝', label: '단어/퀴즈' },
  { key: 'education', icon: '📚', label: '교육' },
]

const allGames = [
  { path: '/games/gostop', icon: '🎴', name: '고스톱', desc: '한국 전통 카드', cat: 'card' },
  { path: '/games/poker', icon: '♠️', name: '포커', desc: '텍사스 홀덤', cat: 'card' },
  { path: '/games/holdem', icon: '♦️', name: '홀덤', desc: '1인 홀덤', cat: 'card' },
  { path: '/games/blackjack', icon: '🂡', name: '블랙잭', desc: '21 카드', cat: 'card' },
  { path: '/games/memory', icon: '🧩', name: '메모리', desc: '카드 짝맞추기', cat: 'brain' },
  { path: '/games/2048', icon: '🔢', name: '2048', desc: '숫자 퍼즐', cat: 'brain' },
  { path: '/games/omok', icon: '⚫', name: '오목', desc: '5목', cat: 'brain' },
  { path: '/games/puzzle', icon: '🧩', name: '퍼즐', desc: '조각 맞추기', cat: 'brain' },
  { path: '/games/bingo', icon: '📋', name: '빙고', desc: '빙고 게임', cat: 'brain' },
  { path: '/games/speedcalc', icon: '➕', name: '빠른계산', desc: '암산 훈련', cat: 'brain' },
  { path: '/games/seniormemory', icon: '🧠', name: '시니어 기억력', desc: '기억력 훈련', cat: 'brain' },
  { path: '/games/snake', icon: '🐍', name: '스네이크', desc: '뱀 키우기', cat: 'arcade' },
  { path: '/games/towerdefense', icon: '🏰', name: '타워 디펜스', desc: '타워 방어', cat: 'arcade' },
  { path: '/games/slots', icon: '🎰', name: '슬롯머신', desc: '행운의 슬롯', cat: 'arcade' },
  { path: '/games/stocksim', icon: '📈', name: '주식 시뮬', desc: '모의 투자', cat: 'arcade' },
  { path: '/games/wordle', icon: '🔤', name: '워들', desc: '5글자 단어', cat: 'word' },
  { path: '/games/quiz', icon: '❓', name: '퀴즈', desc: '일반 상식', cat: 'word' },
  { path: '/games/wordchain', icon: '🔗', name: '끝말잇기', desc: '단어 이어가기', cat: 'word' },
  { path: '/games/wordblank', icon: '📝', name: '빈칸채우기', desc: '단어 완성', cat: 'word' },
  { path: '/games/spelling', icon: '📖', name: '스펠링', desc: '영단어', cat: 'word' },
  { path: '/games/typing', icon: '⌨️', name: '타이핑', desc: '타자 연습', cat: 'word' },
  { path: '/games/hangul', icon: '🇰🇷', name: '한글', desc: '한글 학습', cat: 'education' },
  { path: '/games/counting', icon: '🔢', name: '수세기', desc: '유아 수학', cat: 'education' },
  { path: '/games/colors', icon: '🎨', name: '색깔', desc: '색상 학습', cat: 'education' },
  { path: '/games/shapes', icon: '🔷', name: '도형', desc: '도형 학습', cat: 'education' },
  { path: '/games/satwords', icon: '📚', name: 'SAT 단어', desc: 'SAT 준비', cat: 'education' },
  { path: '/games/proverb', icon: '📜', name: '속담 퀴즈', desc: '한국 속담', cat: 'education' },
  { path: '/games/flag', icon: '🏳️', name: '국기 퀴즈', desc: '세계 국기', cat: 'education' },
  { path: '/games/uslife', icon: '🇺🇸', name: '미국 생활', desc: '시민권 퀴즈', cat: 'education' },
]

const filteredGames = computed(() => {
  if (activeCat.value === 'all') return allGames
  return allGames.filter(g => g.cat === activeCat.value)
})

const popularGames = allGames.slice(0, 8)
</script>
