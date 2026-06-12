<template>
<GameShell title="리더보드" icon="🏆"
  bg="#F7F8FA">

  <div class="lb-body">
    <!-- 헤더 배너 -->
    <div class="hero-banner">
      <div class="hero-emoji"><AppIcon name="trophy" :size="30" /></div>
      <h2 class="hero-title">한인 커뮤니티 TOP 20</h2>
      <p class="hero-sub">매일 갱신되는 실시간 랭킹</p>
    </div>

    <!-- 탭 -->
    <div class="tabs">
      <button v-for="tab in tabs" :key="tab.type"
        @click="activeTab = tab.type; loadData()"
        class="tab-btn"
        :class="activeTab === tab.type ? 'active' : ''">
        {{ tab.label }}
      </button>
    </div>

    <!-- 로딩 -->
    <div v-if="loading" class="loading">불러오는 중...</div>
    <div v-else-if="!data.length" class="empty">아직 랭킹 데이터가 없습니다</div>

    <template v-else>
      <!-- TOP 3 포디움 -->
      <div v-if="data.length >= 3" class="podium">
        <!-- 2등 -->
        <div class="p-col p2">
          <img :src="getAvatar(data[1])" class="p-avatar" @error="onAvatarErr" />
          <div class="p-box box2">
            <div class="p-name">{{ data[1].username }}</div>
            <div class="p-val">{{ formatVal(data[1].value) }}</div>
          </div>
          <div class="p-rank rank2">🥈 2위</div>
        </div>
        <!-- 1등 -->
        <div class="p-col p1">
          <div class="p-crown">👑</div>
          <img :src="getAvatar(data[0])" class="p-avatar big" @error="onAvatarErr" />
          <div class="p-box box1">
            <div class="p-name">{{ data[0].username }}</div>
            <div class="p-val">{{ formatVal(data[0].value) }}</div>
          </div>
          <div class="p-rank rank1">🥇 1위</div>
        </div>
        <!-- 3등 -->
        <div class="p-col p3">
          <img :src="getAvatar(data[2])" class="p-avatar" @error="onAvatarErr" />
          <div class="p-box box3">
            <div class="p-name">{{ data[2].username }}</div>
            <div class="p-val">{{ formatVal(data[2].value) }}</div>
          </div>
          <div class="p-rank rank3">🥉 3위</div>
        </div>
      </div>

      <!-- 리스트 (4위~) -->
      <div class="list">
        <div v-for="(user, idx) in restList" :key="user.id"
          class="rank-row"
          :class="user.id == myId ? 'me' : ''">
          <div class="list-rank">{{ idx + startRank }}</div>
          <img :src="getAvatar(user)" class="list-avatar" @error="onAvatarErr" />
          <div class="list-info">
            <div class="list-name">
              <span>{{ user.name || user.username }}</span>
              <span class="list-level" :class="levelColor(user.level)">{{ user.level }}</span>
            </div>
            <div class="list-sub">@{{ user.username }}</div>
          </div>
          <div class="list-value">
            <span class="value-main">{{ formatVal(user.value) }}</span>
            <span class="value-unit">{{ unitLabel }}</span>
          </div>
        </div>
      </div>

      <!-- 내 순위 -->
      <div v-if="myRank" class="my-rank">
        <div class="my-badge">{{ myRank }}위</div>
        <div>
          <div class="my-title">나의 순위</div>
          <div class="my-sub">{{ unitLabel }}: {{ formatVal(myValue) }}</div>
        </div>
      </div>
    </template>
  </div>
</GameShell>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useAuthStore } from '../../stores/auth'
import axios from 'axios'
import GameShell from '../../components/GameShell.vue'
import AppIcon from '../../components/AppIcon.vue'

const auth = useAuthStore()
const myId = auth.user?.id
const data = ref([])
const loading = ref(false)
const activeTab = ref('points')

const tabs = [
  { type: 'points', label: '💎 포인트' },
  { type: 'posts',  label: '✏️ 게시글' },
  { type: 'quiz',   label: '🧠 퀴즈' },
]

const unitLabel = computed(() => ({
  points: 'P', posts: '개', quiz: 'P'
}[activeTab.value]))

const restList = computed(() => data.value.length >= 3 ? data.value.slice(3) : data.value)
const startRank = computed(() => data.value.length >= 3 ? 4 : 1)

const myRank = computed(() => {
  if (!myId) return null
  const i = data.value.findIndex(u => u.id == myId)
  return i >= 0 ? i + 1 : null
})
const myValue = computed(() => {
  const m = data.value.find(u => u.id == myId)
  return m?.value ?? 0
})

function formatVal(v) {
  const n = parseInt(v)
  return isNaN(n) ? '0' : n.toLocaleString()
}

function getAvatar(user) {
  if (!user) return fallbackAvatar('?')
  return user.avatar || fallbackAvatar(user.name || user.username || '?')
}
function fallbackAvatar(name) {
  return `https://ui-avatars.com/api/?name=${encodeURIComponent(name)}&background=random&color=fff`
}
function onAvatarErr(e) {
  e.target.src = fallbackAvatar('?')
}

function levelColor(level) {
  return {
    '씨앗': 'lv-seed',
    '새싹': 'lv-sprout',
    '나무': 'lv-tree',
    '숲':   'lv-forest',
    '참나무':'lv-oak',
  }[level] ?? 'lv-seed'
}

async function loadData() {
  loading.value = true
  try {
    const { data: res } = await axios.get(`/api/games/leaderboard`, { params: { type: activeTab.value } })
    const rows = res?.data ?? []
    data.value = Array.isArray(rows) ? rows : []
  } catch {
    data.value = []
  }
  loading.value = false
}

onMounted(loadData)
</script>

<style scoped>
.lb-body { padding: 14px; max-width: 900px; margin: 0 auto; width: 100%; }

.hero-banner { text-align: center; padding: 18px 16px; background: linear-gradient(135deg,#FF8A53,#F2570F); color: #fff; border-radius: 16px; margin-bottom: 14px; box-shadow: 0 6px 24px rgba(242,87,15,0.25); }
.hero-emoji { display: flex; justify-content: center; margin-bottom: 6px; }
.hero-title { font-size: 18px; font-weight: 900; margin: 0; }
.hero-sub { font-size: 12px; color: rgba(255,255,255,0.85); margin-top: 4px; }

.tabs { display: flex; background: #fff; border-radius: 12px; padding: 4px; gap: 4px; margin-bottom: 14px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
.tab-btn { flex: 1; padding: 10px; font-size: 13px; font-weight: 700; border: none; background: transparent; color: #8B95A1; cursor: pointer; border-radius: 8px; transition: all 0.15s; }
.tab-btn:hover { background: rgba(255,107,44,0.08); }
.tab-btn.active { background: #FF6B2C; color: #fff; box-shadow: 0 2px 8px rgba(255,107,44,0.3); }

.loading, .empty { text-align: center; padding: 40px; color: #8B95A1; font-size: 14px; }

.podium { display: flex; align-items: flex-end; justify-content: center; gap: 10px; margin: 10px 0 20px; }
.p-col { display: flex; flex-direction: column; align-items: center; }
.p-col.p1 { margin-bottom: -8px; }
.p-crown { font-size: 22px; margin-bottom: 2px; }
.p-avatar { width: 52px; height: 52px; border-radius: 50%; border: 2px solid #d1d5db; object-fit: cover; background: #fff; }
.p-avatar.big { width: 64px; height: 64px; border: 3px solid #fbbf24; box-shadow: 0 4px 14px rgba(251,191,36,0.5); }
.p-col.p2 .p-avatar { border-color: #9ca3af; }
.p-col.p3 .p-avatar { border-color: #fb923c; }
.p-box { border-radius: 12px 12px 0 0; padding: 18px 10px 8px; margin-top: 4px; text-align: center; width: 88px; }
.p-col.p1 .p-box { width: 96px; padding-top: 24px; }
.box1 { background: #fbbf24; color: #78350f; }
.box2 { background: #d1d5db; color: #374151; }
.box3 { background: #fed7aa; color: #9a3412; }
.p-name { font-size: 12px; font-weight: 800; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; padding: 0 4px; }
.p-val { font-size: 11px; margin-top: 2px; }
.p-rank { font-size: 11px; font-weight: 800; color: #fff; padding: 4px; text-align: center; width: 88px; border-radius: 0 0 12px 12px; }
.p-col.p1 .p-rank { width: 96px; }
.rank1 { background: #f59e0b; }
.rank2 { background: #6b7280; }
.rank3 { background: #f97316; }

.list { display: flex; flex-direction: column; gap: 6px; }
.rank-row { display: flex; align-items: center; gap: 10px; background: #fff; border-radius: 12px; padding: 10px 14px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); transition: transform 0.15s, box-shadow 0.15s; }
.rank-row:hover { transform: translateX(2px); box-shadow: 0 4px 12px rgba(0,0,0,0.06); }
.rank-row.me { background: #FFF4EC; border: 2px solid #FF6B2C; }
.list-rank { width: 28px; text-align: center; font-weight: 800; color: #8B95A1; font-size: 14px; }
.list-avatar { width: 36px; height: 36px; border-radius: 50%; object-fit: cover; background: #f3f4f6; flex-shrink: 0; }
.list-info { flex: 1; min-width: 0; }
.list-name { display: flex; align-items: center; gap: 6px; color: #191F28; font-weight: 700; font-size: 14px; }
.list-name > span:first-child { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.list-level { flex-shrink: 0; font-size: 11px; padding: 1px 6px; border-radius: 8px; font-weight: 700; }
.lv-seed { background: #f3f4f6; color: #6b7280; }
.lv-sprout { background: #d1fae5; color: #065f46; }
.lv-tree { background: #dbeafe; color: #1e40af; }
.lv-forest { background: #d1fae5; color: #047857; }
.lv-oak { background: #fef3c7; color: #92400e; }
.list-sub { font-size: 11px; color: #8B95A1; }
.list-value { text-align: right; flex-shrink: 0; white-space: nowrap; display: inline-flex; align-items: baseline; gap: 3px; }
.value-main { font-weight: 800; color: #F2570F; font-size: 15px; }
.value-unit { font-size: 11px; color: #8B95A1; font-weight: 600; }

.my-rank { display: flex; align-items: center; gap: 14px; margin-top: 18px; padding: 14px 18px; background: #FFF4EC; border: 1px solid #FFC7A6; border-radius: 14px; }
.my-badge { font-size: 20px; font-weight: 900; color: #F2570F; }
.my-title { font-weight: 700; color: #191F28; font-size: 14px; }
.my-sub { font-size: 12px; color: #4E5968; }
</style>
