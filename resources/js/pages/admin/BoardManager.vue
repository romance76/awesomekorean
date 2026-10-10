<template>
<!-- ───────── 휴대폰 화면 ───────── -->
<div v-if="isMobile" class="alv-m space-y-3 pb-4">
  <div class="flex items-center gap-2">
    <p class="flex-1 min-w-0 text-[14px] text-ink-muted">게시판 {{ boards.length }}개</p>
    <button @click="showCreate = true" class="shrink-0 min-h-[48px] px-5 rounded-xl bg-amber-500 text-white text-[15px] font-bold">+ 게시판 추가</button>
  </div>
  <div v-if="!boards.length" class="text-center py-12 text-ink-muted text-[15px]">게시판이 없어요.</div>
  <div v-for="b in boards" :key="b.id" class="bg-white border border-gray-100 rounded-2xl p-3.5 flex items-center gap-3">
    <div class="min-w-0 flex-1">
      <div class="text-[16px] font-bold text-ink break-words">{{ b.name }}</div>
      <div class="text-[13px] text-ink-muted break-all">/{{ b.slug }}</div>
    </div>
    <button @click="toggleActive(b)" :disabled="busy" class="shrink-0 min-h-[44px] px-3 rounded-xl text-[14px] font-bold disabled:opacity-40" :class="b.is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'" :aria-label="b.is_active ? '켜짐 — 눌러서 끄기' : '꺼짐 — 눌러서 켜기'">{{ b.is_active ? '켜짐' : '꺼짐' }}</button>
    <button @click="del = b" class="shrink-0 min-h-[44px] px-3 rounded-xl bg-red-50 text-red-600 text-[14px] font-bold">삭제</button>
  </div>

  <Teleport to="body">
    <div v-if="showCreate" class="alv-m fixed inset-0 z-[70] bg-black/45 flex items-end" @click.self="showCreate = false">
      <div class="w-full bg-white rounded-t-3xl px-4 pt-2 max-h-[90vh] overflow-y-auto" role="dialog" aria-modal="true" :style="{ paddingBottom: 'calc(16px + env(safe-area-inset-bottom, 0px))' }">
        <div class="w-10 h-1 rounded bg-gray-200 mx-auto mb-3"></div>
        <div class="text-[17px] font-bold text-ink mb-3">게시판 추가</div>
        <label class="block text-[14px] font-bold text-ink mb-1" for="bm-name">이름</label>
        <input id="bm-name" v-model="newBoard.name" placeholder="예: 자유게시판" class="w-full min-h-[48px] rounded-xl border border-gray-200 px-3 mb-3" />
        <label class="block text-[14px] font-bold text-ink mb-1" for="bm-slug">주소 (영문)</label>
        <input id="bm-slug" v-model="newBoard.slug" placeholder="예: free" autocapitalize="none" class="w-full min-h-[48px] rounded-xl border border-gray-200 px-3 mb-4" />
        <button @click="createBoard" :disabled="busy || !newBoard.name.trim() || !newBoard.slug.trim()" class="w-full min-h-[52px] rounded-xl bg-amber-500 text-white text-[16px] font-bold disabled:opacity-40">{{ busy ? '추가 중...' : '추가하기' }}</button>
        <button @click="showCreate = false" class="mt-2 w-full min-h-[50px] rounded-xl bg-gray-100 text-ink text-[16px] font-bold">취소</button>
      </div>
    </div>
    <div v-if="del" class="alv-m fixed inset-0 z-[70] bg-black/45 flex items-end" @click.self="del = null">
      <div class="w-full bg-white rounded-t-3xl px-4 pt-2" role="dialog" aria-modal="true" :style="{ paddingBottom: 'calc(16px + env(safe-area-inset-bottom, 0px))' }">
        <div class="w-10 h-1 rounded bg-gray-200 mx-auto mb-3"></div>
        <div class="text-[17px] font-bold text-ink mb-1">게시판을 삭제할까요?</div>
        <p class="text-[15px] text-ink-light mb-3 break-words">{{ del.name }} (/{{ del.slug }})</p>
        <button @click="doDelete" :disabled="busy" class="w-full min-h-[52px] rounded-xl bg-red-500 text-white text-[16px] font-bold disabled:opacity-40">{{ busy ? '삭제 중...' : '삭제하기' }}</button>
        <button @click="del = null" :disabled="busy" class="mt-2 w-full min-h-[50px] rounded-xl bg-gray-100 text-ink text-[16px] font-bold">취소</button>
      </div>
    </div>
    <div v-if="toast" class="alv-m fixed left-1/2 -translate-x-1/2 z-[80] max-w-[90vw] px-4 py-3 rounded-xl text-[15px] font-bold text-white shadow-lg" :class="toast.error ? 'bg-red-600' : 'bg-ink'" :style="{ top: 'calc(70px + env(safe-area-inset-top, 0px))' }" role="status">{{ toast.text }}</div>
  </Teleport>
</div>

<!-- ───────── PC 화면 ───────── -->
<div v-else>
  <div class="flex items-center justify-between mb-4">
    <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink">
      <span class="icon-chip w-9 h-9 bg-amber-50 text-amber-600"><AppIcon name="list" :size="20" /></span>
      게시판 관리
    </h1>
    <button @click="showCreate=true" class="btn-primary px-4 py-2 text-sm"><AppIcon name="plus" :size="14" />추가</button>
  </div>
  <div class="card overflow-hidden divide-y divide-gray-50">
    <div v-for="b in boards" :key="b.id" class="list-row flex items-center justify-between">
      <div><span class="font-semibold text-ink">{{ b.name }}</span> <span class="text-xs text-ink-faint ml-2">/{{ b.slug }}</span></div>
      <div class="flex gap-2">
        <button @click="toggleActive(b)" :disabled="busy" class="text-xs font-bold px-2 py-0.5 rounded-lg disabled:opacity-40" :class="b.is_active?'bg-green-50 text-green-600':'bg-red-50 text-red-600'" :title="b.is_active ? '눌러서 끄기 (회원 화면에서 숨김)' : '눌러서 켜기'">{{ b.is_active ? '켜짐' : '꺼짐' }}</button>
        <button @click="deleteBoard(b)" class="text-xs text-red-400 hover:text-red-600 transition-colors">삭제</button>
      </div>
    </div>
  </div>
  <div v-if="showCreate" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center" @click.self="showCreate=false">
    <div class="bg-white rounded-2xl p-5 w-full max-w-sm shadow-xl space-y-3">
      <h3 class="flex items-center gap-2 font-bold text-ink"><span class="icon-chip w-7 h-7 bg-amber-50 text-amber-600"><AppIcon name="plus" :size="15" /></span>게시판 추가</h3>
      <input v-model="newBoard.name" placeholder="이름" class="input-soft px-3 py-2" />
      <input v-model="newBoard.slug" placeholder="slug (영문)" class="input-soft px-3 py-2" />
      <div class="flex gap-2"><button @click="createBoard" class="btn-primary px-4 py-2 text-sm flex-1">추가</button><button @click="showCreate=false" class="btn-ghost px-4">취소</button></div>
    </div>
  </div>
</div>
</template>
<script setup>
import { ref, reactive, computed, watch, onMounted, onBeforeUnmount, inject } from 'vue'
import axios from 'axios'
import AppIcon from '../../components/AppIcon.vue'
const boards = ref([]); const showCreate = ref(false); const newBoard = reactive({name:'',slug:''})

// 관리자 휴대폰 화면이면 카드 + 아래에서 올라오는 시트로 보여 줌 (AdminLayout 이 알려 줌)
const adminIsMobile = inject('adminIsMobile', ref(false))
const isMobile = computed(() => !!adminIsMobile.value)
const toast = ref(null); let toastTimer = null
function say(text, error = false) { toast.value = { text, error }; clearTimeout(toastTimer); toastTimer = setTimeout(() => { toast.value = null }, 3000) }
const busy = ref(false)
const del = ref(null)
async function doDelete() {
  if (busy.value || !del.value) return
  busy.value = true
  try { await axios.delete('/api/admin/boards/' + del.value.id); del.value = null; say('삭제했어요'); load() }
  catch (e) { say(e.response?.data?.message || '삭제하지 못했어요', true) }
  finally { busy.value = false }
}
watch(() => isMobile.value && (showCreate.value || !!del.value), locked => { document.body.style.overflow = locked ? 'hidden' : '' })
onBeforeUnmount(() => { document.body.style.overflow = ''; clearTimeout(toastTimer) })
async function load() { try { const{data}=await axios.get('/api/admin/boards'); boards.value=data.data||[] }catch{} }
async function createBoard() {
  if (busy.value) return
  busy.value = true
  try { await axios.post('/api/admin/boards',newBoard); showCreate.value=false; newBoard.name=''; newBoard.slug=''; if (isMobile.value) say('추가했어요'); load() }
  catch (e) { if (isMobile.value) say(e.response?.data?.message || '추가하지 못했어요', true) }
  finally { busy.value = false }
}
// 게시판 켜기/끄기 — 끄면 회원 화면의 게시판 탭·전체 목록·글쓰기에서 빠진다(글은 지우지 않음)
async function toggleActive(b) {
  if (busy.value) return
  if (b.is_active && !confirm(`'${b.name}' 게시판을 끌까요? 회원 화면에서 이 게시판과 글이 보이지 않게 돼요.`)) return
  busy.value = true
  try {
    await axios.put('/api/admin/boards/' + b.id, { is_active: !b.is_active })
    b.is_active = !b.is_active
    say(b.is_active ? '게시판을 켰어요' : '게시판을 껐어요')
  } catch (e) { const m = e.response?.data?.message || '바꾸지 못했어요'; isMobile.value ? say(m, true) : alert(m) }
  finally { busy.value = false }
}
async function deleteBoard(b) { if(!confirm('삭제?'))return; try { await axios.delete('/api/admin/boards/'+b.id); load() }catch{} }
onMounted(load)
</script>
<style>
/* 휴대폰 관리자: 입력창 글자가 16px 보다 작으면 iOS 가 화면을 확대해 버림 */
.alv-m input, .alv-m textarea, .alv-m select { font-size: 16px; }
</style>
