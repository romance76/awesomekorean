<template>
<div class="space-y-4">
  <div v-if="loading" class="text-center py-10 text-ink-muted">로딩중...</div>
  <template v-else>
    <!-- 내 Amazon 태그 -->
    <div class="card p-5">
      <div class="flex items-center justify-between gap-2 flex-wrap">
        <h2 class="flex items-center gap-2 font-bold text-ink"><span class="icon-chip w-7 h-7 bg-lime-50 text-lime-600"><AppIcon name="shopping-bag" :size="15" /></span>내돈내산 리뷰</h2>
        <RouterLink to="/shopping/write" class="btn-primary px-4 py-1.5 text-xs font-bold">✍️ 리뷰 쓰기</RouterLink>
      </div>
      <div class="mt-3 text-sm text-ink-light">
        내 Amazon Associates 태그:
        <b v-if="d.amazon_tag" class="text-amber-700">{{ d.amazon_tag }}</b>
        <span v-else class="text-red-500">아직 없어요 — 태그가 있어야 리뷰를 쓸 수 있어요</span>
      </div>
      <div class="flex gap-2 mt-2">
        <input v-model="tagInput" class="input-soft flex-1" placeholder="예) myshop-20" maxlength="40" />
        <button @click="saveTag" :disabled="tagBusy || !tagInput.trim()" class="btn-primary px-4 text-sm disabled:opacity-50">{{ d.amazon_tag ? '변경' : '등록' }}</button>
      </div>
      <p v-if="tagMsg" class="text-xs mt-1" :class="tagOk ? 'text-green-600' : 'text-red-500'">{{ tagMsg }}</p>
      <RouterLink to="/shopping/guide" class="inline-block mt-2 text-xs font-bold text-amber-700 underline">📘 Amazon Associates 가입 방법 보기</RouterLink>
      <p class="text-[11px] text-ink-faint mt-2 leading-relaxed">리뷰 속 "Amazon에서 보기" 링크에는 이 태그가 붙고, 수익은 내 Associates 계정으로 들어가요. 태그를 바꾸면 내 리뷰 링크도 모두 새 태그로 바뀌고, 태그를 지우면 내 리뷰는 숨겨져요.</p>
    </div>

    <!-- 합계 -->
    <div class="grid grid-cols-2 sm:grid-cols-5 gap-2">
      <div v-for="s in summary" :key="s.label" class="card p-3 text-center">
        <div class="text-lg font-black text-ink">{{ s.value.toLocaleString() }}</div>
        <div class="text-[11px] text-ink-muted">{{ s.label }}</div>
      </div>
    </div>

    <!-- 내 리뷰 목록 -->
    <div v-if="!d.items.length" class="card p-8 text-center text-sm text-ink-faint">아직 쓴 리뷰가 없어요. 첫 리뷰를 써보세요!</div>
    <div v-for="r in d.items" :key="r.id" class="card p-3 flex gap-3">
      <RouterLink :to="`/shopping/${r.id}`" class="w-20 h-20 rounded-lg overflow-hidden bg-gray-50 flex-shrink-0 flex items-center justify-center">
        <img v-if="r.image_url" :src="r.image_url" class="w-full h-full object-contain" />
      </RouterLink>
      <div class="flex-1 min-w-0 text-sm">
        <div class="flex items-center gap-1.5 flex-wrap">
          <span class="text-[11px] font-bold px-1.5 py-0.5 rounded border" :class="ST[r.status]?.cls">{{ ST[r.status]?.text || r.status }}</span>
          <span v-if="r.is_hot" class="bg-amber-500 text-white rounded-full px-2 py-0.5 text-[10px] font-black">🔥 이번주 HOT</span>
        </div>
        <RouterLink :to="`/shopping/${r.id}`" class="block font-semibold text-ink truncate mt-1">{{ r.title }}</RouterLink>
        <div class="text-[11px] text-ink-muted mt-1 flex flex-wrap gap-x-3 gap-y-0.5">
          <span>👁 전체 {{ r.view_count }} <span class="text-ink-faint">(이번주 {{ r.week_views }})</span></span>
          <span>🛒 클릭 {{ r.clicks }} <span class="text-ink-faint">(이번주 {{ r.week_clicks }})</span></span>
          <span>💬 댓글 {{ r.comments }}</span>
          <span v-if="r.reports" class="text-red-500 font-bold">🚩 신고 {{ r.reports }}명</span>
        </div>
        <p v-if="r.admin_note && r.status !== 'published'" class="text-[11px] text-red-500 mt-1">사유: {{ r.admin_note }}</p>
        <div class="flex gap-3 mt-1.5 text-xs">
          <RouterLink :to="{ path: '/shopping/write', query: { edit: r.id } }" class="text-blue-500 font-bold">수정</RouterLink>
          <button @click="remove(r)" class="text-red-500 font-bold">삭제</button>
        </div>
      </div>
    </div>
    <p class="text-[11px] text-ink-faint">※ 조회수는 같은 사람이 30분 안에 다시 봐도 한 번만 세고, 내가 본 건 빼요. 실제 구매·수익은 Amazon Associates Central에서 확인하세요.</p>
  </template>
</div>
</template>

<script setup>
import { useModal } from '../../composables/useModal'
import { ref, computed, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import axios from 'axios'
import AppIcon from '../../components/AppIcon.vue'
const { showConfirm } = useModal()

const loading = ref(true)
const d = ref({ amazon_tag: null, items: [], totals: {} })
const tagInput = ref('')
const tagBusy = ref(false)
const tagMsg = ref('')
const tagOk = ref(true)
const ST = {
  published: { text: '공개 중', cls: 'bg-green-50 text-green-700 border-green-200' },
  pending: { text: '승인 대기', cls: 'bg-amber-50 text-amber-700 border-amber-200' },
  hidden: { text: '내려감', cls: 'bg-red-50 text-red-600 border-red-200' },
  rejected: { text: '반려됨', cls: 'bg-red-50 text-red-600 border-red-200' },
}
const summary = computed(() => {
  const t = d.value.totals || {}
  return [
    { label: '내 리뷰', value: t.reviews || 0 }, { label: '전체 조회', value: t.views || 0 },
    { label: '전체 클릭', value: t.clicks || 0 }, { label: '이번주 조회', value: t.week_views || 0 },
    { label: '댓글', value: t.comments || 0 },
  ]
})

async function load() {
  try {
    const { data } = await axios.get('/api/shopping/my')
    d.value = data.data
    tagInput.value = data.data.amazon_tag || ''
  } catch {}
  loading.value = false
}
async function saveTag() {
  tagBusy.value = true; tagMsg.value = ''
  try {
    const { data } = await axios.put('/api/shopping/my-tag', { amazon_tag: tagInput.value })
    tagMsg.value = data.message; tagOk.value = true
    await load()
  } catch (e) { tagMsg.value = e.response?.data?.message || '저장하지 못했어요'; tagOk.value = false }
  tagBusy.value = false
}
async function remove(r) {
  if (!await showConfirm(`'${r.title}' 리뷰를 삭제할까요?`)) return
  try { await axios.delete(`/api/shopping/reviews/${r.id}`); await load() } catch {}
}
onMounted(load)
</script>
