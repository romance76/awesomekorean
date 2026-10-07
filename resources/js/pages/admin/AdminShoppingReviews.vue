<template>
<div>
  <p class="text-xs text-ink-muted mb-3">회원이 자기 Amazon Associates 태그로 쓴 내돈내산 리뷰예요. 처음 쓰는 리뷰는 여기서 승인해야 공개되고, 신고가 3명 이상 쌓이면 자동으로 내려가 확인을 기다려요.</p>
  <div class="flex gap-1.5 mb-3 flex-wrap">
    <button v-for="t in tabs" :key="t.key" @click="status = t.key; page = 1; load()"
      class="px-3 py-1.5 rounded-full text-xs font-bold border transition-colors"
      :class="status === t.key ? 'bg-ink text-white border-ink' : 'bg-white text-ink-light border-gray-200'">
      {{ t.label }}<span v-if="counts[t.key]" class="ml-1 bg-rose-500 text-white rounded-full px-1.5">{{ counts[t.key] }}</span>
    </button>
  </div>

  <div v-if="loading" class="text-center py-10 text-ink-muted">로딩중...</div>
  <div v-else-if="!items.length" class="text-center py-10 text-sm text-ink-faint">해당하는 리뷰가 없습니다</div>
  <div v-else class="space-y-3">
    <div v-for="r in items" :key="r.id" class="border border-gray-100 rounded-xl p-3 flex gap-3">
      <a :href="`/shopping/${r.id}`" target="_blank" class="w-24 h-24 rounded-lg overflow-hidden bg-gray-50 flex-shrink-0 flex items-center justify-center">
        <img v-if="r.image_url" :src="r.image_url" class="w-full h-full object-contain" />
      </a>
      <div class="flex-1 min-w-0 text-sm">
        <div class="flex items-center gap-2 flex-wrap">
          <span class="text-[11px] font-bold px-1.5 py-0.5 rounded border" :class="ST[r.status]">{{ LABEL[r.status] || r.status }}</span>
          <span v-if="r.reports" class="text-[11px] font-bold text-red-500">🚩 신고 {{ r.reports }}명</span>
          <span class="font-bold text-ink truncate">{{ r.title }}</span>
        </div>
        <div class="text-xs text-ink-muted mt-1">{{ r.user?.nickname || r.user?.name }} ({{ r.user?.email }}) · 태그 <b>{{ r.affiliate_tag }}</b> · ASIN {{ r.asin }} · 👁 {{ r.view_count }} · 🛒 {{ r.clicks }} · 💬 {{ r.comment_count }}</div>
        <p class="text-xs text-ink-light mt-1 line-clamp-3 whitespace-pre-line">{{ r.our_description }}</p>
        <a :href="r.affiliate_url" target="_blank" rel="noopener noreferrer nofollow" class="text-[11px] text-blue-500 underline break-all">{{ r.affiliate_url }}</a>
        <p v-if="r.admin_note" class="text-[11px] text-red-500 mt-1">메모: {{ r.admin_note }}</p>
        <div class="flex gap-2 mt-2 flex-wrap">
          <button v-if="r.status === 'pending' || r.status === 'rejected'" @click="act(r, 'approve')" class="btn-primary px-3 py-1 rounded-lg text-xs">승인</button>
          <button v-if="r.status === 'pending'" @click="act(r, 'reject', true)" class="px-3 py-1 rounded-lg text-xs font-bold border border-red-200 text-red-500 hover:bg-red-50">반려</button>
          <button v-if="r.status === 'published'" @click="act(r, 'hide', true)" class="px-3 py-1 rounded-lg text-xs font-bold border border-red-200 text-red-500 hover:bg-red-50">내리기</button>
          <button v-if="r.status === 'hidden'" @click="act(r, 'restore')" class="btn-primary px-3 py-1 rounded-lg text-xs">복구</button>
          <a :href="`/shopping/${r.id}`" target="_blank" class="px-3 py-1 text-xs text-ink-muted hover:text-amber-600">상세 보기</a>
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
import axios from 'axios'

const tabs = [
  { key: 'pending', label: '승인 대기' }, { key: 'hidden', label: '내려감(신고)' },
  { key: 'published', label: '공개 중' }, { key: 'rejected', label: '반려' }, { key: 'all', label: '전체' },
]
const LABEL = { pending: '승인 대기', published: '공개 중', hidden: '내려감', rejected: '반려됨' }
const ST = {
  pending: 'bg-amber-50 text-amber-700 border-amber-200', published: 'bg-green-50 text-green-700 border-green-200',
  hidden: 'bg-red-50 text-red-600 border-red-200', rejected: 'bg-gray-100 text-gray-500 border-gray-200',
}
const status = ref('pending')
const items = ref([])
const counts = ref({})
const page = ref(1)
const lastPage = ref(1)
const loading = ref(true)
const msg = ref('')
const msgOk = ref(true)

async function load() {
  loading.value = true
  try {
    const { data } = await axios.get('/api/admin/shopping-reviews', { params: { status: status.value, page: page.value } })
    items.value = data.data.data
    lastPage.value = data.data.last_page
    counts.value = data.counts || {}
  } catch {}
  loading.value = false
}
async function act(r, path, ask = false) {
  let body = {}
  if (ask) {
    const reason = window.prompt(`'${r.title}' 사유를 입력하세요 (작성자에게 전달돼요)`, '리뷰 작성 규칙(홍보·광고 금지 등)에 맞지 않아요.')
    if (reason === null) return
    body = { reason }
  }
  msg.value = ''
  try {
    const { data } = await axios.post(`/api/admin/shopping-reviews/${r.id}/${path}`, body)
    msg.value = data.message; msgOk.value = true
  } catch (e) { msg.value = e.response?.data?.message || '처리 실패'; msgOk.value = false }
  await load()
}
onMounted(load)
</script>
