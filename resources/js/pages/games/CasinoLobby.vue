<template>
<div class="casino-lobby">
  <!-- 헤더 -->
  <header class="cl-header">
    <button class="cl-back" @click="$router.push('/games')">← 게임 홈</button>
    <h1 class="cl-title">🎰 AwesomeKorean 카지노</h1>
    <div class="cl-wallet" v-if="auth.isLoggedIn">
      <span class="wallet-item" title="포인트">🪙 {{ pointsFmt }}</span>
      <span class="wallet-item wallet-chip" title="게임머니">💰 {{ chipsFmt }}</span>
    </div>
    <RouterLink v-else to="/login" class="cl-login">로그인</RouterLink>
  </header>

  <!-- 안내 배너 -->
  <section class="cl-intro">
    <div class="intro-left">
      <div class="intro-title">실전 머니 게임 허브</div>
      <div class="intro-sub">포커 · 홀덤 · 고스톱 · 블랙잭 — 원하는 베팅 금액을 선택하고 입장하세요</div>
    </div>
    <div class="intro-right">
      <button class="exchange-btn" @click="showExchange = true">🪙 → 💰 포인트 환전</button>
    </div>
  </section>

  <!-- 게임 카드 그리드 -->
  <section class="cl-games">
    <div v-for="g in games" :key="g.key" class="game-card" :class="`card-${g.key}`">
      <div class="card-top">
        <div class="card-icon">{{ g.icon }}</div>
        <div class="card-info">
          <div class="card-title">{{ g.name }}</div>
          <div class="card-desc">{{ g.description }}</div>
        </div>
        <span class="card-type-badge">{{ g.typeLabel }}</span>
      </div>

      <!-- 베팅 금액 선택 -->
      <div class="bet-row">
        <div class="bet-label">베팅 금액</div>
        <div class="bet-chips">
          <button v-for="b in g.bets" :key="b"
            class="bet-chip" :class="{ active: selectedBet[g.key] === b }"
            @click="selectedBet[g.key] = b">
            {{ fmt(b) }}
          </button>
        </div>
      </div>

      <!-- 요구 칩 + 입장 -->
      <div class="enter-row">
        <div class="require-info">
          <span class="req-label">필요</span>
          <span class="req-value" :class="{ insufficient: selectedBet[g.key] > chips }">💰 {{ fmt(selectedBet[g.key]) }}</span>
        </div>
        <button class="enter-btn"
          :disabled="!auth.isLoggedIn || selectedBet[g.key] > chips"
          @click="enter(g)">
          {{ !auth.isLoggedIn ? '로그인 필요' : selectedBet[g.key] > chips ? '칩 부족' : '입장하기 →' }}
        </button>
      </div>
    </div>
  </section>

  <!-- 통계/리더보드 -->
  <section class="cl-stats">
    <RouterLink to="/games/leaderboard" class="stats-card">
      <div class="stats-icon">🏆</div>
      <div>
        <div class="stats-title">리더보드</div>
        <div class="stats-sub">최고의 플레이어</div>
      </div>
    </RouterLink>
    <RouterLink to="/games/shop" class="stats-card">
      <div class="stats-icon">🛒</div>
      <div>
        <div class="stats-title">포인트 샵</div>
        <div class="stats-sub">포인트로 칩 구매</div>
      </div>
    </RouterLink>
    <RouterLink to="/games/poker/tutorial" class="stats-card">
      <div class="stats-icon">📖</div>
      <div>
        <div class="stats-title">포커 튜토리얼</div>
        <div class="stats-sub">룰 배우기</div>
      </div>
    </RouterLink>
  </section>

  <!-- 환전 모달 -->
  <GameMoneyExchange v-if="showExchange" @close="onExchangeClose" />
</div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRouter, RouterLink } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import axios from 'axios'
import GameMoneyExchange from '../../components/GameMoneyExchange.vue'

const router = useRouter()
const auth = useAuthStore()
const chips = ref(0)
const showExchange = ref(false)

const games = [
  {
    key: 'poker',
    name: '토너먼트 포커',
    icon: '♠️',
    description: 'AI·플레이어 대전 · 토너먼트',
    typeLabel: 'PvP',
    bets: [100, 500, 1000, 5000],
    path: '/games/poker',
  },
  {
    key: 'holdem',
    name: '텍사스 홀덤',
    icon: '♦️',
    description: '1인 AI 홀덤',
    typeLabel: 'Solo',
    bets: [100, 500, 1000, 5000],
    path: '/games/holdem',
  },
  {
    key: 'gostop',
    name: '고스톱',
    icon: '🎴',
    description: '한국 전통 카드 · AI 대전',
    typeLabel: 'Solo',
    bets: [50, 100, 500, 1000],
    path: '/games/gostop',
  },
  {
    key: 'blackjack',
    name: '블랙잭',
    icon: '🂡',
    description: '21 가까이 · 딜러 대전',
    typeLabel: 'Solo',
    bets: [50, 100, 500, 1000],
    path: '/games/blackjack',
  },
]

const selectedBet = reactive(Object.fromEntries(games.map(g => [g.key, g.bets[0]])))
const fmt = (n) => Number(n || 0).toLocaleString()
const pointsFmt = computed(() => fmt(auth.user?.points || 0))
const chipsFmt = computed(() => fmt(chips.value))

async function loadWallet() {
  if (!auth.isLoggedIn) return
  try {
    const { data } = await axios.get('/api/poker/wallet')
    chips.value = data.data?.chips ?? data.chips ?? 0
  } catch {}
}

function enter(g) {
  // 베팅 선택을 쿼리로 전달 (각 게임이 읽어서 기본값으로 사용)
  router.push({ path: g.path, query: { bet: selectedBet[g.key] } })
}

function onExchangeClose() {
  showExchange.value = false
  loadWallet()
}

onMounted(loadWallet)
</script>

<style scoped>
.casino-lobby { min-height: 100vh; background: linear-gradient(160deg, #0b1020 0%, #1a1030 50%, #2a1040 100%); color: #f3f4f6; font-family: 'Noto Sans KR', sans-serif; padding-bottom: 40px; }

.cl-header {
  display: flex; align-items: center; justify-content: space-between;
  padding: 14px 20px; background: rgba(0, 0, 0, 0.35);
  border-bottom: 1px solid rgba(251, 191, 36, 0.2);
  backdrop-filter: blur(10px);
  position: sticky; top: 0; z-index: 10;
}
.cl-back { background: rgba(255,255,255,0.08); border: none; color: #fbbf24; padding: 6px 12px; border-radius: 18px; cursor: pointer; font-size: 13px; font-weight: 700; }
.cl-back:hover { background: rgba(255,255,255,0.16); }
.cl-title { font-size: 20px; font-weight: 900; color: #fbbf24; letter-spacing: 0.5px; margin: 0; }
.cl-wallet { display: flex; gap: 8px; }
.wallet-item { background: rgba(251, 191, 36, 0.15); color: #fcd34d; padding: 6px 12px; border-radius: 14px; font-size: 12px; font-weight: 800; border: 1px solid rgba(251, 191, 36, 0.3); }
.wallet-chip { background: rgba(52, 211, 153, 0.15); color: #6ee7b7; border-color: rgba(52, 211, 153, 0.3); }
.cl-login { color: #fbbf24; font-size: 13px; font-weight: 700; text-decoration: none; padding: 6px 14px; border-radius: 14px; border: 1px solid rgba(251,191,36,0.4); }

.cl-intro {
  max-width: 1100px; margin: 24px auto; padding: 0 20px;
  display: flex; align-items: center; justify-content: space-between; gap: 20px;
  flex-wrap: wrap;
}
.intro-title { font-size: 24px; font-weight: 900; color: #fbbf24; margin-bottom: 4px; }
.intro-sub { font-size: 13px; color: rgba(255,255,255,0.7); }
.exchange-btn { background: linear-gradient(135deg, #f59e0b, #ea580c); color: #fff; border: none; padding: 10px 18px; border-radius: 22px; font-size: 13px; font-weight: 800; cursor: pointer; box-shadow: 0 4px 20px rgba(245, 158, 11, 0.4); }
.exchange-btn:hover { transform: translateY(-1px); }

.cl-games {
  max-width: 1100px; margin: 0 auto; padding: 0 20px;
  display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 16px;
}
.game-card {
  background: linear-gradient(160deg, rgba(24, 24, 39, 0.9), rgba(31, 24, 59, 0.9));
  border: 1px solid rgba(251, 191, 36, 0.15); border-radius: 16px; padding: 16px;
  display: flex; flex-direction: column; gap: 12px;
  transition: all 0.2s;
}
.game-card:hover { transform: translateY(-3px); border-color: rgba(251, 191, 36, 0.4); box-shadow: 0 12px 40px rgba(0,0,0,0.4); }
.card-top { display: flex; align-items: center; gap: 10px; }
.card-icon { font-size: 36px; }
.card-info { flex: 1; min-width: 0; }
.card-title { font-size: 17px; font-weight: 900; color: #fff; }
.card-desc { font-size: 11px; color: rgba(255,255,255,0.55); margin-top: 2px; }
.card-type-badge { background: rgba(251,191,36,0.15); color: #fcd34d; font-size: 10px; font-weight: 800; padding: 3px 8px; border-radius: 10px; border: 1px solid rgba(251,191,36,0.3); flex-shrink: 0; }

.bet-row { border-top: 1px solid rgba(255,255,255,0.06); padding-top: 12px; }
.bet-label { font-size: 11px; color: rgba(255,255,255,0.5); font-weight: 700; margin-bottom: 6px; }
.bet-chips { display: grid; grid-template-columns: repeat(4, 1fr); gap: 5px; }
.bet-chip { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: rgba(255,255,255,0.7); padding: 6px 4px; border-radius: 10px; font-size: 11px; font-weight: 800; cursor: pointer; transition: all 0.15s; }
.bet-chip:hover { background: rgba(251,191,36,0.1); color: #fcd34d; }
.bet-chip.active { background: rgba(251,191,36,0.2); border-color: rgba(251,191,36,0.5); color: #fcd34d; }

.enter-row { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding-top: 4px; }
.require-info { display: flex; flex-direction: column; gap: 1px; }
.req-label { font-size: 9px; color: rgba(255,255,255,0.4); font-weight: 700; }
.req-value { font-size: 13px; font-weight: 800; color: #6ee7b7; }
.req-value.insufficient { color: #fca5a5; }
.enter-btn { background: linear-gradient(135deg, #f59e0b, #dc2626); color: #fff; border: none; padding: 9px 16px; border-radius: 18px; font-size: 12px; font-weight: 800; cursor: pointer; }
.enter-btn:disabled { background: #374151; color: rgba(255,255,255,0.4); cursor: not-allowed; }
.enter-btn:not(:disabled):hover { transform: translateX(2px); }

.cl-stats {
  max-width: 1100px; margin: 32px auto 0; padding: 0 20px;
  display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px;
}
.stats-card {
  display: flex; align-items: center; gap: 12px;
  background: rgba(255, 255, 255, 0.04); border: 1px solid rgba(255, 255, 255, 0.06);
  border-radius: 14px; padding: 14px 16px; text-decoration: none; color: inherit;
  transition: all 0.15s;
}
.stats-card:hover { background: rgba(251, 191, 36, 0.06); border-color: rgba(251, 191, 36, 0.25); }
.stats-icon { font-size: 28px; }
.stats-title { font-size: 14px; font-weight: 800; color: #fff; }
.stats-sub { font-size: 11px; color: rgba(255, 255, 255, 0.5); }

@media (max-width: 640px) {
  .cl-title { font-size: 16px; }
  .cl-header { padding: 10px 12px; }
  .cl-intro { margin: 18px auto; }
  .intro-title { font-size: 18px; }
}
</style>
