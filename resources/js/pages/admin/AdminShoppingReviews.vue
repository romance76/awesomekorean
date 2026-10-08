<template>
<!-- ───────── 휴대폰 화면 ───────── -->
<div v-if="isMobile" class="alv-m space-y-3">
  <p class="text-[13px] text-ink-muted px-0.5">회원이 자기 Amazon 제휴 태그로 쓴 리뷰예요. 처음 쓴 리뷰는 승인해야 공개되고, 신고가 3명 쌓이면 자동으로 내려가요.</p>
  <div class="flex gap-2 overflow-x-auto scrollbar-hide" role="group" aria-label="리뷰 상태">
    <button v-for="t in tabs" :key="t.key" @click="status = t.key; page = 1; load()" :aria-pressed="status === t.key"
      class="shrink-0 min-h-[44px] px-4 rounded-full border text-[15px]" :class="status === t.key ? 'bg-ink text-white border-ink font-bold' : 'bg-white text-ink border-gray-200 font-medium'">
      {{ t.label }}<span v-if="counts[t.key]" class="ml-1.5 bg-rose-500 text-white rounded-full px-1.5 text-[12px]">{{ counts[t.key] }}</span></button>
  </div>
  <div v-if="loading" class="text-center py-10 text-ink-muted text-[15px]">불러오는 중...</div>
  <div v-else-if="!items.length" class="text-center py-12 text-ink-muted text-[15px]">해당하는 리뷰가 없어요.</div>
  <div v-else class="space-y-2.5">
    <div v-for="r in items" :key="r.id" class="bg-white border border-gray-100 rounded-2xl p-3.5">
      <div class="flex gap-3">
        <a :href="`/shopping/${r.id}`" target="_blank" rel="noopener" class="w-20 h-20 rounded-xl overflow-hidden bg-gray-50 shrink-0 flex items-center justify-center" aria-label="상세 보기">
          <img v-if="r.image_url" :src="r.image_url" alt="" class="w-full h-full object-contain" />
        </a>
        <div class="min-w-0 flex-1">
          <div class="flex items-center gap-1.5 flex-wrap">
            <span class="text-[12px] font-bold px-2 py-0.5 rounded-full border" :class="ST[r.status]">{{ LABEL[r.status] || r.status }}</span>
            <span v-if="r.reports" class="text-[12px] font-bold text-red-500">🚩 신고 {{ r.reports }}명</span>
          </div>
          <div class="text-[16px] font-bold text-ink leading-snug break-words line-clamp-2 mt-0.5">{{ r.title }}</div>
          <div class="text-[13px] text-ink-muted break-words">{{ r.user?.nickname || r.user?.name }}</div>
        </div>
      </div>
      <div class="text-[12px] text-ink-faint mt-2 break-all">{{ r.user?.email }} · 태그 <b>{{ r.affiliate_tag }}</b> · ASIN {{ r.asin }}</div>
      <div class="text-[13px] text-ink-muted mt-0.5">👁 {{ r.view_count }} · 🛒 {{ r.clicks }} · 💬 {{ r.comment_count }}</div>
      <p class="text-[14px] text-ink-light mt-2 line-clamp-4 whitespace-pre-line break-words bg-gray-50 rounded-xl px-3 py-2">{{ r.our_description }}</p>
      <div v-if="r.own_image_urls?.length" class="flex gap-1.5 mt-2 overflow-x-auto">
        <a v-for="u in r.own_image_urls" :key="u" :href="u" target="_blank" rel="noopener" class="shrink-0"><img :src="u" alt="" class="w-16 h-16 object-cover rounded-lg border border-gray-100" /></a>
      </div>
      <a :href="r.affiliate_url" target="_blank" rel="noopener noreferrer nofollow" class="mt-2 flex items-center min-h-[44px] text-[13px] text-blue-600 underline break-all">{{ r.affiliate_url }}</a>
      <p v-if="r.admin_note" class="text-[13px] text-red-500">메모: {{ r.admin_note }}</p>
      <div class="grid grid-cols-2 gap-2 mt-2">
        <button v-if="r.status === 'pending' || r.status === 'rejected'" @click="mAct(r, 'approve')" class="min-h-[50px] rounded-xl bg-emerald-500 text-white text-[16px] font-bold">승인</button>
        <button v-if="r.status === 'pending'" @click="mAct(r, 'reject', true)" class="min-h-[50px] rounded-xl bg-red-50 text-red-600 text-[16px] font-bold">반려</button>
        <button v-if="r.status === 'published'" @click="mAct(r, 'hide', true)" class="min-h-[50px] rounded-xl bg-red-50 text-red-600 text-[16px] font-bold col-span-2">내리기</button>
        <button v-if="r.status === 'hidden'" @click="mAct(r, 'restore')" class="min-h-[50px] rounded-xl bg-emerald-500 text-white text-[16px] font-bold col-span-2">복구</button>
      </div>
    </div>
  </div>
  <div v-if="lastPage > 1" class="flex items-center justify-between gap-2 pt-1">
    <button @click="page--; load()" :disabled="page <= 1" class="min-h-[48px] px-5 rounded-xl bg-white border border-gray-200 text-[15px] font-bold disabled:opacity-40">이전</button>
    <span class="text-[14px] text-ink-muted tabular-nums">{{ page }} / {{ lastPage }}</span>
    <button @click="page++; load()" :disabled="page >= lastPage" class="min-h-[48px] px-5 rounded-xl bg-white border border-gray-200 text-[15px] font-bold disabled:opacity-40">다음</button>
  </div>
  <Teleport to="body">
    <div v-if="sheet" class="alv-m fixed inset-0 z-[70] bg-black/45 flex items-end" @click.self="closeSheet">
      <div class="w-full bg-white rounded-t-3xl px-4 pt-2 max-h-[90vh] overflow-y-auto" role="dialog" aria-modal="true" :style="{ paddingBottom: 'calc(16px + env(safe-area-inset-bottom, 0px))' }">
        <div class="w-10 h-1 rounded bg-gray-200 mx-auto mb-3"></div>
        <div class="text-[17px] font-bold text-ink mb-1">{{ sheet.path === 'approve' ? '리뷰를 승인할까요?' : sheet.path === 'restore' ? '리뷰를 다시 공개할까요?' : sheet.path === 'hide' ? '리뷰를 내릴까요?' : '리뷰를 반려할까요?' }}</div>
        <p class="text-[15px] text-ink-light mb-3 break-words">{{ sheet.r.title }}</p>
        <template v-if="sheet.ask">
          <label class="block text-[14px] font-bold text-ink mb-1" for="sr-reason">사유 (작성자에게 전달돼요)</label>
          <textarea id="sr-reason" v-model="reason" rows="3" maxlength="300" class="w-full rounded-xl border border-gray-200 px-3 py-3 mb-3"></textarea>
        </template>
        <button @click="doSheet" :disabled="busy || (sheet.ask && !reason.trim())" class="w-full min-h-[52px] rounded-xl text-white text-[16px] font-bold disabled:opacity-40" :class="sheet.ask ? 'bg-red-500' : 'bg-emerald-500'">{{ busy ? '처리 중...' : (sheet.path === 'approve' ? '승인하기' : sheet.path === 'restore' ? '다시 공개' : sheet.path === 'hide' ? '내리기' : '반려하기') }}</button>
        <button @click="closeSheet" :disabled="busy" class="mt-2 w-full min-h-[50px] rounded-xl bg-gray-100 text-ink text-[16px] font-bold">취소</button>
      </div>
    </div>
    <div v-if="toast" class="alv-m fixed left-1/2 -translate-x-1/2 z-[80] max-w-[90vw] px-4 py-3 rounded-xl text-[15px] font-bold text-white shadow-lg" :class="toast.error ? 'bg-red-600' : 'bg-ink'" :style="{ top: 'calc(70px + env(safe-area-inset-top, 0px))' }" role="status">{{ toast.text }}</div>
  </Teleport>
</div>

<!-- ───────── PC 화면 ───────── -->
<div v-else>
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
        <div v-if="r.own_image_urls?.length" class="flex gap-1 mt-1">
          <a v-for="u in r.own_image_urls" :key="u" :href="u" target="_blank"><img :src="u" class="w-12 h-12 object-cover rounded border border-gray-100" /></a>
        </div>
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
import { ref, computed, watch, inject, onMounted, onBeforeUnmount } from 'vue'
import axios from 'axios'

// 관리자 휴대폰 화면이면 카드 + 아래에서 올라오는 시트 (AdminLayout 이 알려 줌)
const adminIsMobile = inject('adminIsMobile', ref(false))
const isMobile = computed(() => !!adminIsMobile.value)
const toast = ref(null); let toastTimer = null
function say(text, error = false) { toast.value = { text, error }; clearTimeout(toastTimer); toastTimer = setTimeout(() => { toast.value = null }, 3000) }
const busy = ref(false)
const sheet = ref(null)   // { r, path, ask }
const reason = ref('')
function mAct(r, path, ask = false) { reason.value = ask ? '리뷰 작성 규칙(홍보·광고 금지 등)에 맞지 않아요.' : ''; sheet.value = { r, path, ask } }
function closeSheet() { if (!busy.value) sheet.value = null }
async function doSheet() {
  if (busy.value || !sheet.value) return
  busy.value = true
  const { r, path, ask } = sheet.value
  try {
    const { data } = await axios.post(`/api/admin/shopping-reviews/${r.id}/${path}`, ask ? { reason: reason.value.trim() } : {})
    sheet.value = null; say(data.message || '처리했어요'); await load()
  } catch (e) { say(e.response?.data?.message || '처리하지 못했어요', true) }
  finally { busy.value = false }
}
watch(() => isMobile.value && !!sheet.value, locked => { document.body.style.overflow = locked ? 'hidden' : '' })
onBeforeUnmount(() => { document.body.style.overflow = ''; clearTimeout(toastTimer) })

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

<style>
/* 휴대폰 관리자: 입력창 글자가 16px 보다 작으면 iOS 가 화면을 확대해 버림 */
.alv-m input, .alv-m textarea, .alv-m select { font-size: 16px; }
</style>
