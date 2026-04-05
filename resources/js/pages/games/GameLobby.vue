<template>
  <div class="min-h-screen bg-gray-50 pb-20">
    <div class="max-w-[1200px] mx-auto px-4 pt-4">

      <!-- 헤더 -->
      <div class="bg-gradient-to-r from-indigo-600 to-purple-600 rounded-2xl px-6 py-5 mb-5 shadow-lg text-white">
        <div class="flex items-center justify-between">
          <div>
            <h1 class="text-xl font-black">🎮 게임 센터</h1>
            <p class="text-sm opacity-80 mt-0.5">연령별 맞춤 게임 · 두뇌트레이닝 · 커뮤니티 게임</p>
          </div>
          <div v-if="auth.isLoggedIn" class="text-right">
            <div class="text-xs text-indigo-200 mb-1">내 지갑</div>
            <div class="flex items-center gap-2">
              <span class="bg-white/20 px-2 py-1 rounded-lg text-sm font-bold">🪙 {{ wallet.coin.toLocaleString() }}</span>
              <span v-if="wallet.gem > 0" class="bg-white/20 px-2 py-1 rounded-lg text-sm font-bold">💎 {{ wallet.gem.toLocaleString() }}</span>
              <span v-if="wallet.star > 0" class="bg-white/20 px-2 py-1 rounded-lg text-sm font-bold">⭐ {{ wallet.star.toLocaleString() }}</span>
            </div>
          </div>
        </div>
        <!-- 일일 보너스 버튼 -->
        <div v-if="auth.isLoggedIn" class="flex items-center gap-3">
          <button @click="claimDaily" :disabled="dailyClaimed"
            class="flex items-center gap-2 bg-yellow-400 hover:bg-yellow-300 disabled:opacity-60 text-gray-900 px-4 py-2 rounded-xl text-sm font-bold transition">
            🎁 {{ dailyClaimed ? '출석 완료 ✓' : '오늘의 출석 +50 COIN' }}
          </button>
          <button @click="showLeaderboard = !showLeaderboard" class="flex items-center gap-2 bg-white/20 hover:bg-white/30 px-4 py-2 rounded-xl text-sm font-semibold transition">
            🏆 리더보드
          </button>
        </div>
      </div>


      <!-- 연령별 탭 -->
      <div class="bg-white rounded-2xl shadow-sm mb-5 overflow-hidden">
        <div class="flex overflow-x-auto" style="scrollbar-width:none">
          <button v-for="tab in ageTabs" :key="tab.key"
            @click="activeAge = tab.key"
            class="flex-shrink-0 flex items-center gap-2 px-5 py-4 text-sm font-semibold transition border-b-2"
            :class="activeAge === tab.key
              ? 'border-indigo-500 text-indigo-600 bg-indigo-50'
              : 'border-transparent text-gray-500 hover:text-gray-700 hover:bg-gray-50'">
            <span class="text-xl">{{ tab.icon }}</span>
            <span class="whitespace-nowrap">{{ tab.label }}</span>
            <span class="text-[11px] text-gray-400">({{ tab.ages }})</span>
          </button>
        </div>
      </div>

      <!-- 리더보드 (토글) -->
      <div v-if="showLeaderboard" class="bg-white rounded-2xl shadow-sm p-5 mb-5">
        <h3 class="font-bold text-gray-700 mb-4">🏆 리더보드</h3>
        <div class="space-y-2">
          <div v-for="(u,i) in leaders" :key="u.id" class="flex items-center gap-3 py-2 border-b border-gray-50 last:border-0">
            <span class="w-6 text-center font-bold text-sm">
              <template v-if="i===0">🥇</template>
              <template v-else-if="i===1">🥈</template>
              <template v-else-if="i===2">🥉</template>
              <template v-else>{{ i+1 }}</template>
            </span>
            <span class="flex-1 text-sm font-medium text-gray-700">{{ u.nickname || u.username }}</span>
            <span class="text-xs text-gray-400">{{ u.play_count }}게임</span>
            <span class="text-sm font-bold text-indigo-600">{{ Number(u.total_reward).toLocaleString() }} COIN</span>
          </div>
        </div>
      </div>

      <!-- 게임 그리드 -->
      <div v-if="loading" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
        <div v-for="i in 8" :key="i" class="bg-white rounded-2xl p-4 animate-pulse h-36"></div>
      </div>

      <div v-else>
        <!-- 현재 연령 그룹 소개 -->
        <div v-if="activeTabInfo" class="mb-4 p-4 rounded-xl" :style="{background: activeTabInfo.bg}">
          <p class="text-sm font-semibold" :style="{color: activeTabInfo.color}">
            {{ activeTabInfo.icon }} {{ activeTabInfo.desc }}
          </p>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3">
          <div v-for="game in currentGames" :key="game.id"
            @click="playGame(game)"
            class="relative bg-white rounded-2xl shadow-sm p-4 cursor-pointer hover:shadow-md transition hover:scale-[1.02] group">
            <!-- 게임 아이콘/썸네일 -->
            <div class="text-3xl mb-2 text-center group-hover:scale-110 transition-transform">{{ getGameEmoji(game) }}</div>
            <!-- 이름 -->
            <h3 class="text-sm font-bold text-gray-800 text-center mb-1 line-clamp-1">{{ game.name }}</h3>
            <!-- 설명 -->
            <p class="text-[11px] text-gray-400 text-center line-clamp-2 mb-2">{{ game.description }}</p>
            <!-- 보상 + 타입 -->
            <div class="flex items-center justify-between">
              <span class="text-[10px] px-1.5 py-0.5 rounded-full font-medium"
                :class="typeClass(game.type)">
                {{ typeLabel(game.type) }}
              </span>
              <span v-if="game.reward_base > 0" class="text-[11px] font-bold text-yellow-600">
                +{{ game.reward_base }} {{ currencyIcon(game.currency) }}
              </span>
              <span v-if="game.min_bet > 0" class="text-[11px] font-bold text-orange-500">
                베팅
              </span>
            </div>
            <!-- NEW 배지 -->
            <div v-if="game.is_new" class="absolute top-2 right-2 bg-red-500 text-white text-[9px] px-1.5 py-0.5 rounded-full font-bold">NEW</div>
            <!-- 준비중 오버레이 -->
            <div v-if="!game.route_name" class="absolute inset-0 bg-gray-900/40 rounded-2xl flex items-center justify-center">
              <span class="bg-gray-700 text-white text-[11px] px-2.5 py-1 rounded-full font-bold">준비중</span>
            </div>
          </div>
        </div>

        <div v-if="currentGames.length === 0" class="text-center py-16 text-gray-400">
          <div class="text-5xl mb-3">🎮</div>
          <p class="text-sm">준비 중인 게임이 있습니다. 곧 오픈 예정!</p>
        </div>
      </div>

      <!-- 통화 설명 -->
      <div class="mt-8 bg-white rounded-2xl shadow-sm p-5">
        <h3 class="font-bold text-gray-700 mb-4">💰 게임 화폐 안내</h3>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
          <div class="bg-pink-50 rounded-xl p-3 text-center">
            <div class="text-2xl mb-1">⭐</div>
            <div class="font-bold text-pink-600 text-sm">STAR</div>
            <div class="text-xs text-pink-400">유아/어린이 전용</div>
          </div>
          <div class="bg-blue-50 rounded-xl p-3 text-center">
            <div class="text-2xl mb-1">💎</div>
            <div class="font-bold text-blue-600 text-sm">GEM</div>
            <div class="text-xs text-blue-400">어린이/청소년 전용</div>
          </div>
          <div class="bg-yellow-50 rounded-xl p-3 text-center">
            <div class="text-2xl mb-1">🪙</div>
            <div class="font-bold text-yellow-600 text-sm">COIN</div>
            <div class="text-xs text-yellow-500">성인 게임 기본단위</div>
          </div>
          <div class="bg-purple-50 rounded-xl p-3 text-center">
            <div class="text-2xl mb-1">🎰</div>
            <div class="font-bold text-purple-600 text-sm">CHIP</div>
            <div class="text-xs text-purple-400">고액 게임 전용</div>
          </div>
        </div>
      </div>

    </div>
  </div>

  <!-- 준비중 토스트 -->
  <div v-if="showComingSoon" class="fixed bottom-4 left-1/2 -translate-x-1/2 bg-gray-800 text-white px-6 py-3 rounded-full text-sm z-50">
    🚧 이 게임은 준비 중입니다
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import axios from 'axios'

const router = useRouter()

const auth = useAuthStore()
const loading = ref(false)
const categories = ref([])
const leaders = ref([])
const showLeaderboard = ref(false)
const showComingSoon = ref(false)
const dailyClaimed = ref(false)
const wallet = ref({ star: 0, gem: 0, coin: 1000, chip: 0 })
const activeAge = ref('arcade')

const ageTabs = [
  { key: 'arcade', icon: '🕹️', label: '아케이드', ages: '전체', bg: '#F5F3FF', color: '#7C3AED', desc: '클래식 아케이드 게임! 스네이크, 팟맨, 플래피버드 등 🕹️ 지금 바로 플레이' },
  { key: 'baby',   icon: '👶', label: '유아',   ages: '3-6세',   bg: '#FFF0F6', color: '#DB2777', desc: '한글/영어/숫자를 재미있게 배워요! 정답 시 ⭐ STAR 획득' },
  { key: 'kids',   icon: '🧒', label: '어린이', ages: '7-12세',  bg: '#F0FDF4', color: '#15803D', desc: '재미있는 게임으로 학습! 게임 클리어 시 💎 GEM 획득' },
  { key: 'teen',   icon: '🧑', label: '청소년', ages: '13-18세', bg: '#EFF6FF', color: '#1D4ED8', desc: '전략과 학습의 조화! 퀴즈와 챌린지로 💎 GEM 적립' },
  { key: 'adult',  icon: '👨', label: '성인',   ages: '19-59세', bg: '#FFFBEB', color: '#B45309', desc: '전통 카드게임부터 전략게임까지! 🪙 COIN으로 즐기세요' },
  { key: 'senior', icon: '👴', label: '시니어', ages: '60세+',   bg: '#F5F3FF', color: '#6D28D9', desc: '두뇌 건강을 지키는 인지훈련 게임! 매일 참여하면 🪙 COIN 획득' },
]

const activeTabInfo = computed(() => ageTabs.find(t => t.key === activeAge.value))

const currentGames = computed(() => {
  const cat = categories.value.find(c => c.age_group === activeAge.value)
  return cat ? cat.games : []
})

const gameEmojis = {
  '한글 자음모음 퍼즐': '🔤', '영어 알파벳 퍼즐': '🔡', '숫자 세기 게임': '🔢', '색깔 맞추기': '🎨',
  '동물 이름 퀴즈': '🐾', '모양 맞추기': '🔷', '낱말 카드 게임': '📝', '덧셈 뺄셈': '➕',
  '자동차 점프': '🚗', '끝말잇기': '💬', '타자 연습': '⌨️', '구구단 마스터': '✖️',
  '한국어 단어 맞추기': '🇰🇷', '미로 탈출': '🌀', '그림 조각 퍼즐': '🧩', '영어 단어 카드': '📚',
  '주식 시뮬레이션': '📈', 'SAT 단어 퀴즈': '📖', '수학 챌린지': '🧮', '세계 지리 퀴즈': '🌍',
  '한국어 맞춤법 퀴즈': '✏️', '스피드 계산': '⚡', '타워 디펜스': '🏰', '코딩 퀴즈': '💻',
  '고스톱': '🎴', '텍사스 홀덤': '♠️', '블랙잭': '🃏', '포커': '🎰', '오목': '⚫',
  '2048': '🔢', '빙고': '🎯', '기억력 카드': '🃏', '일일 퀴즈': '❓',
  '한국어 워들': '🔡', '미국 생활 상식 퀴즈': '🇺🇸', '슬롯머신': '🎰', '맞고 (화투)': '🎴',
  '숫자 기억하기': '🔢', '그림 기억 게임': '🖼️', '속담 완성하기': '📜', '색깔 단어 스트룹': '🎨',
  '계산 훈련': '🧮', '단어 찾기': '🔍', '고향 지리 퀴즈': '🗺️', '명상 호흡 게임': '🧘',
}

function getGameEmoji(game) {
  return gameEmojis[game.name] || '🎮'
}

function typeLabel(t) {
  return { single: '1인게임', multi: '멀티', betting: '베팅', educational: '학습', arcade: '아케이드' }[t] || t
}

function typeClass(t) {
  return {
    single: 'bg-blue-100 text-blue-600',
    multi: 'bg-green-100 text-green-600',
    betting: 'bg-orange-100 text-orange-600',
    educational: 'bg-purple-100 text-purple-600',
    arcade: 'bg-pink-100 text-pink-600',
  }[t] || 'bg-gray-100 text-gray-600'
}

function currencyIcon(c) {
  return { star: '⭐', gem: '💎', coin: '🪙', chip: '🎰' }[c] || '🪙'
}

async function loadWallet() {
  if (!auth.isLoggedIn) return
  try {
    const { data } = await axios.get('/api/wallet/balance')
    wallet.value = data
  } catch {}
}

async function claimDaily() {
  try {
    const { data } = await axios.post('/api/wallet/daily-bonus')
    wallet.value.coin = data.coin
    dailyClaimed.value = true
    alert(data.message)
  } catch (e) {
    if (e.response?.data?.error) {
      dailyClaimed.value = true
    }
  }
}

async function loadLeaderboard() {
  try {
    const { data } = await axios.get('/api/game-leaderboard')
    leaders.value = data.slice ? data.slice(0,10) : (data.data || []).slice(0,10)
  } catch {}
}

function playGame(game) {
  if (game.route_name) {
    router.push({ name: game.route_name })
  } else {
    showComingSoon.value = true
    setTimeout(() => showComingSoon.value = false, 2000)
  }
}

onMounted(async () => {
  loading.value = true
  try {
    const { data } = await axios.get('/api/game-categories')
    categories.value = data
  } catch {}
  loading.value = false
  await Promise.all([loadWallet(), loadLeaderboard()])
})
</script>

<style scoped>
.line-clamp-1 { display:-webkit-box; -webkit-line-clamp:1; -webkit-box-orient:vertical; overflow:hidden; }
.line-clamp-2 { display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }


</style>