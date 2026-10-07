<template>
<div>
  <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink mb-2">
    <span class="icon-chip w-9 h-9 bg-rose-50 text-rose-600"><AppIcon name="megaphone" :size="20" /></span>
    NEW 전단 광고 관리
  </h1>
  <p class="text-sm text-ink-muted mb-4">광고주가 시간대를 골라 신청한 전단입니다. 승인하면 예약한 시간에 NEW 게시판 상단에 방송되고, 반려하면 포인트가 환불되며 시간 슬롯이 다시 열립니다. (가격·피크/심야 배율은 가격/할인 센터의 flyer_* 항목)</p>

  <div class="flex gap-1.5 mb-4 flex-wrap">
    <button v-for="t in tabs" :key="t.key" @click="status = t.key; page = 1; load()"
      class="px-3 py-1.5 rounded-full text-xs font-bold border transition-colors"
      :class="status === t.key ? 'bg-ink text-white border-ink' : 'bg-white text-ink-light border-gray-200'">
      {{ t.label }}<span v-if="t.key === 'pending' && pendingCount" class="ml-1 bg-rose-500 text-white rounded-full px-1.5">{{ pendingCount }}</span>
    </button>
  </div>

  <div v-if="loading" class="text-center py-12 text-ink-muted">로딩중...</div>
  <div v-else-if="!items.length" class="text-center py-12 text-sm text-ink-faint card">해당 상태의 신청이 없습니다</div>
  <div v-else class="space-y-3">
    <div v-for="f in items" :key="f.id" class="card p-4 flex gap-4">
      <a :href="f.image_url" target="_blank" class="flex-shrink-0"><img :src="f.image_url" alt="" class="w-24 h-32 object-cover rounded-lg border border-gray-100" /></a>
      <div class="flex-1 min-w-0 text-sm">
        <div class="flex items-center gap-2 flex-wrap">
          <span class="text-[11px] font-bold px-1.5 py-0.5 rounded border" :class="STATUS_LABEL[f.status]?.cls">{{ STATUS_LABEL[f.status]?.text }}</span>
          <span class="text-xs font-bold text-rose-600">{{ kindLabel(f.kind) }}</span>
          <span class="font-bold text-ink">{{ f.title }}</span>
        </div>
        <div class="text-xs text-ink-muted mt-1">
          {{ f.user?.nickname || f.user?.name }} ({{ f.user?.email }}) · {{ f.scope === 'national' ? '🇺🇸 전국' : '📍 ' + stateName(f.region_key) }} ({{ tzLabel(f.tz) }}) · {{ f.hours_count }}시간 · <b>{{ Number(f.total_price).toLocaleString() }}P</b>
        </div>
        <p v-if="f.description" class="text-xs text-ink-light mt-1 line-clamp-2">{{ f.description }}</p>
        <div class="text-xs text-ink-muted mt-1">
          <span v-if="f.phone">📞 {{ f.phone }} </span>
          <a v-if="f.link_url" :href="f.link_url" target="_blank" rel="noopener noreferrer nofollow" class="text-blue-500 underline break-all">{{ f.link_url }}</a>
        </div>
        <div class="mt-2 text-[11px] text-ink-muted space-y-0.5 max-h-24 overflow-y-auto">
          <div v-for="s in f.schedule" :key="s.date"><span class="inline-block w-24">{{ fmtDay(s.date) }}</span>{{ hourRanges(s.hours).join(', ') }}</div>
        </div>
        <div v-if="f.status === 'approved'" class="text-[11px] text-ink-faint mt-1">노출 {{ f.view_count }} · 클릭 {{ f.click_count }}</div>
        <div v-if="f.reject_reason" class="text-[11px] text-red-500 mt-1">사유: {{ f.reject_reason }}</div>

        <div class="flex gap-2 mt-3">
          <button v-if="f.status === 'pending'" @click="approve(f)" class="btn-primary px-4 py-1.5 rounded-lg text-xs">승인</button>
          <button v-if="f.status === 'pending' || f.status === 'approved'" @click="reject(f)" class="px-4 py-1.5 rounded-lg text-xs font-bold border border-red-200 text-red-500 hover:bg-red-50">{{ f.status === 'pending' ? '반려(전액 환불)' : '게시 중단(남은 시간 환불)' }}</button>
          <RouterLink v-if="f.status === 'approved'" :to="`/new/${f.id}`" class="px-3 py-1.5 text-xs text-ink-muted hover:text-rose-600">전단 보기</RouterLink>
        </div>
      </div>
    </div>
  </div>
  <Pagination :page="page" :lastPage="lastPage" @page="p => { page = p; load() }" />
  <p v-if="msg" class="text-sm mt-3" :class="msgOk ? 'text-green-600' : 'text-red-500'">{{ msg }}</p>
</div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import axios from 'axios'
import AppIcon from '../../components/AppIcon.vue'
import { kindLabel, fmtDay, hourRanges, tzLabel, stateName, STATUS_LABEL } from '../../utils/flyer'

const tabs = [
  { key: 'pending', label: '승인 대기' }, { key: 'approved', label: '게시 중' },
  { key: 'rejected', label: '반려' }, { key: 'cancelled', label: '취소' }, { key: 'all', label: '전체' },
]
const status = ref('pending')
const items = ref([])
const page = ref(1)
const lastPage = ref(1)
const pendingCount = ref(0)
const loading = ref(true)
const msg = ref('')
const msgOk = ref(true)

async function load() {
  loading.value = true
  try {
    const { data } = await axios.get('/api/admin/flyers', { params: { status: status.value, page: page.value } })
    items.value = data.data.data
    lastPage.value = data.data.last_page
    pendingCount.value = data.pending_count
  } catch {}
  loading.value = false
}

async function act(path, f, body = {}) {
  msg.value = ''
  try {
    const { data } = await axios.post(`/api/admin/flyers/${f.id}/${path}`, body)
    msg.value = data.message; msgOk.value = true
  } catch (e) {
    msg.value = e.response?.data?.message || '처리 실패'; msgOk.value = false
  }
  await load()
}

const approve = (f) => act('approve', f)
function reject(f) {
  const reason = window.prompt(`'${f.title}' 사유를 입력하세요 (광고주에게 전달됩니다)`, '운영 정책에 맞지 않는 내용입니다.')
  if (reason === null) return
  act('reject', f, { reason })
}

onMounted(load)
</script>
