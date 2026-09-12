<template>
<div class="min-h-screen">
  <div class="max-w-3xl mx-auto px-4 py-5">
    <div class="flex items-center justify-between mb-4">
      <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink">
        <span class="icon-chip w-9 h-9 bg-amber-50 text-amber-600"><AppIcon name="bell" :size="20" /></span>
        알림
      </h1>
      <button @click="markAllRead" class="btn-ghost text-xs">전체 읽음</button>
    </div>

    <!-- 통합검색 -->
    <div class="card p-3 mb-4 space-y-2">
      <div class="relative">
        <AppIcon name="search" :size="14" class="absolute left-3 top-1/2 -translate-y-1/2 text-ink-faint" />
        <input v-model="q" @input="onSearchInput" type="text" placeholder="알림 제목·내용 검색"
          class="input-soft w-full !pl-9 text-sm" />
      </div>
      <div class="flex items-center gap-1.5 flex-wrap">
        <button @click="typeFilter=''; reload()" class="text-xs font-semibold px-2.5 py-1 rounded-full transition-colors"
          :class="!typeFilter ? 'bg-amber-400 text-white' : 'bg-gray-100 text-ink-light hover:bg-gray-200'">전체</button>
        <button v-for="t in availableTypes" :key="t" @click="typeFilter=t; reload()" class="text-xs font-semibold px-2.5 py-1 rounded-full transition-colors"
          :class="typeFilter===t ? 'bg-amber-400 text-white' : 'bg-gray-100 text-ink-light hover:bg-gray-200'">{{ typeLabel(t) }}</button>
        <label class="text-xs text-ink-muted flex items-center gap-1 ml-auto cursor-pointer">
          <input type="checkbox" v-model="unreadOnly" @change="reload" class="rounded accent-amber-500" /> 안읽음만
        </label>
      </div>
    </div>

    <div v-if="loading" class="text-center py-12 text-ink-faint">로딩중...</div>
    <div v-else-if="!notifs.length" class="py-16 text-center">
      <div class="icon-chip w-14 h-14 bg-gray-100 text-gray-300 mx-auto mb-3"><AppIcon name="bell" :size="28" :stroke-width="1.5" /></div>
      <p class="text-sm text-ink-muted">{{ q || typeFilter || unreadOnly ? '조건에 맞는 알림이 없습니다' : '알림이 없습니다' }}</p>
    </div>
    <template v-else>
      <div class="card overflow-hidden divide-y divide-gray-50">
        <div v-for="n in notifs" :key="n.id"
          class="px-4 py-3 transition-colors"
          :class="n.read_at ? 'bg-white' : 'bg-amber-50'">
          <div class="flex items-start gap-3">
            <span class="icon-chip w-8 h-8 bg-amber-50 text-amber-600 flex-shrink-0"><AppIcon :name="typeIcons[n.type] || 'megaphone'" :size="15" /></span>
            <div class="flex-1 min-w-0">
              <div class="text-sm font-medium text-ink">{{ n.title }}</div>
              <div v-if="n.content" class="text-xs text-ink-muted mt-0.5">{{ n.content }}</div>
              <div class="text-xs text-ink-faint mt-1">{{ formatDate(n.created_at) }}</div>
            </div>
          </div>
        </div>
      </div>
      <div v-if="hasMore" class="text-center mt-3">
        <button @click="loadMore" :disabled="loadingMore" class="btn-secondary text-xs px-5 py-2 disabled:opacity-50">
          {{ loadingMore ? '불러오는 중...' : '더 보기' }}
        </button>
      </div>
    </template>
  </div>
</div>
</template>
<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import AppIcon from '../components/AppIcon.vue'

const notifs = ref([])
const loading = ref(true)
const loadingMore = ref(false)
const page = ref(1)
const hasMore = ref(false)
const q = ref('')
const typeFilter = ref('')
const unreadOnly = ref(false)
let searchTimer = null

const typeIcons = { like:'heart', comment:'message-circle', friend:'user-plus', answer:'help-circle', reservation:'shopping-cart', checkin:'check', sos:'alert-circle' }
const typeLabels = {
  new_message: '쪽지', message: '쪽지', friend_request: '친구요청', friend_accepted: '친구수락',
  qa_answer_accepted: 'Q&A 채택', badge_earned: '뱃지', claim_approved: '업소인증', claim_rejected: '업소인증',
  job_hire_accepted: '채용확정', job_hire_rejected: '지원결과', elder_checkin_missed: '안심체크인',
  call_missed: '부재중전화', report_resolved: '신고처리', banner_approved: '광고', banner_rejected: '광고',
  password_reset_by_admin: '계정', payment_refunded: '환불', groupbuy_approved: '공동구매',
  groupbuy_rejected: '공동구매', groupbuy_completed: '공동구매', new_notification: '알림',
}
function typeLabel(t) { return typeLabels[t] || t }

// 화면에 실제로 뜬 알림들에서 종류를 뽑아 필터 칩을 동적으로 구성 —
// 새 알림 종류가 추가돼도 별도 유지보수 없이 자동 반영됨.
const availableTypes = computed(() => {
  const seen = new Set()
  notifs.value.forEach(n => n.type && seen.add(n.type))
  return [...seen].sort()
})

function formatDate(dt) {
  if (!dt) return ''
  const h = Math.floor((Date.now() - new Date(dt).getTime()) / 3600000)
  if (h < 1) return '방금'
  if (h < 24) return h + '시간 전'
  return Math.floor(h/24) + '일 전'
}

async function fetchPage(p) {
  const { data } = await axios.get('/api/notifications', {
    params: { page: p, q: q.value || undefined, type: typeFilter.value || undefined, unread_only: unreadOnly.value || undefined },
  })
  const payload = data.data
  const items = payload?.data || payload || []
  hasMore.value = payload?.current_page && payload?.last_page ? payload.current_page < payload.last_page : false
  return items
}

async function reload() {
  loading.value = true
  page.value = 1
  try { notifs.value = await fetchPage(1) } catch { notifs.value = [] }
  loading.value = false
}

async function loadMore() {
  loadingMore.value = true
  try {
    const items = await fetchPage(page.value + 1)
    notifs.value = [...notifs.value, ...items]
    page.value += 1
  } catch {}
  loadingMore.value = false
}

function onSearchInput() {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(reload, 350)
}

async function markAllRead() {
  try { await axios.post('/api/notifications/read'); notifs.value.forEach(n => n.read_at = new Date()) } catch {}
}

onMounted(reload)
</script>
