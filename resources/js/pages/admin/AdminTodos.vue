<template>
<!-- ───────── 휴대폰 화면 ───────── -->
<div v-if="isMobile" class="alv-m space-y-3 pb-20">
  <!-- 보기 (해야 할 것 / 완료 / 전체) -->
  <div class="grid grid-cols-3 gap-1 bg-gray-200/70 rounded-2xl p-1" role="group" aria-label="보기">
    <button v-for="f in statusFilters" :key="f.v" @click="statusFilter = f.v" :aria-pressed="statusFilter === f.v"
      class="min-h-[46px] rounded-xl text-[15px] font-bold flex items-center justify-center gap-1.5"
      :class="statusFilter === f.v ? 'bg-white text-ink shadow-sm' : 'text-ink-muted'">
      {{ f.l }}<span class="text-[13px] tabular-nums" :class="statusFilter === f.v ? 'text-amber-600' : ''">{{ f.v === 'open' ? count('todo') + count('doing') : (f.v === 'done' ? count('done') : todos.length) }}</span>
    </button>
  </div>

  <!-- 분류 -->
  <div class="flex gap-2 overflow-x-auto scrollbar-hide" role="group" aria-label="분류">
    <button @click="catFilter = ''" :aria-pressed="!catFilter"
      class="shrink-0 min-h-[44px] px-4 rounded-full border text-[15px]"
      :class="!catFilter ? 'bg-ink text-white border-ink font-bold' : 'bg-white text-ink border-gray-200 font-medium'">전체</button>
    <button v-for="c in categories" :key="c" @click="catFilter = c" :aria-pressed="catFilter === c"
      class="shrink-0 min-h-[44px] px-4 rounded-full border text-[15px]"
      :class="catFilter === c ? 'bg-ink text-white border-ink font-bold' : 'bg-white text-ink border-gray-200 font-medium'">{{ c }}</button>
  </div>

  <p v-if="error" class="bg-red-50 text-red-600 text-[14px] rounded-xl p-3">{{ error }}</p>

  <!-- 목록 -->
  <div v-for="t in visible" :key="t.id" class="bg-white border border-gray-100 rounded-2xl flex items-start" :class="t.status === 'done' ? 'opacity-70' : ''">
    <button @click="setStatus(t, t.status === 'done' ? 'todo' : 'done')" :aria-label="t.status === 'done' ? '다시 할 일로' : '완료 처리'"
      class="shrink-0 w-14 min-h-[72px] flex justify-center pt-[15px] self-stretch rounded-l-2xl active:bg-amber-50">
      <span class="w-7 h-7 rounded-lg border-2 grid place-items-center text-white text-[16px] font-bold"
        :class="t.status === 'done' ? 'bg-emerald-500 border-emerald-500' : 'border-gray-300 bg-white'">{{ t.status === 'done' ? '✓' : '' }}</span>
    </button>
    <button @click="openEdit(t)" class="min-w-0 flex-1 text-left py-3 pr-3 min-h-[72px] active:bg-amber-50 rounded-r-2xl">
      <span class="block text-[16px] font-bold text-ink leading-snug break-words" :class="t.status === 'done' ? 'line-through' : ''">{{ t.title }}</span>
      <span class="flex flex-wrap items-center gap-1.5 mt-1.5">
        <span class="text-[12px] font-bold px-2 py-0.5 rounded-md" :class="priClass(t.priority)">{{ priLabel(t.priority) }}</span>
        <span class="text-[12px] font-bold px-2 py-0.5 rounded-md bg-gray-100 text-ink-muted">{{ t.category }}</span>
        <span v-if="t.status === 'doing'" class="text-[12px] font-bold px-2 py-0.5 rounded-md bg-blue-50 text-blue-600">진행 중</span>
      </span>
      <span v-if="t.detail" class="text-[14px] text-ink-light mt-1.5 leading-relaxed whitespace-pre-wrap line-clamp-3">{{ t.detail }}</span>
    </button>
    <button v-if="t.status === 'todo'" @click="setStatus(t, 'doing')" class="shrink-0 self-center mr-2 min-h-[44px] px-3 rounded-xl bg-blue-50 text-blue-600 text-[14px] font-bold">시작</button>
  </div>
  <div v-if="!visible.length" class="text-center text-ink-muted py-12 text-[15px]">{{ loading ? '불러오는 중...' : '해당하는 항목이 없어요' }}</div>

  <!-- 추가 버튼 -->
  <button @click="openNew" class="fixed right-4 z-30 min-h-[52px] px-5 rounded-full bg-amber-500 text-white text-[16px] font-bold shadow-lg flex items-center gap-1.5"
    :style="{ bottom: 'calc(78px + env(safe-area-inset-bottom, 0px))' }"><AppIcon name="plus" :size="18" />항목 추가</button>

  <!-- 추가/수정 시트 -->
  <Teleport to="body">
    <div v-if="form" class="alv-m fixed inset-0 z-[70] bg-black/45 flex items-end" @click.self="form = null">
      <div class="w-full bg-white rounded-t-3xl px-4 pt-2 max-h-[90vh] overflow-y-auto" role="dialog" aria-modal="true"
        :style="{ paddingBottom: 'calc(16px + env(safe-area-inset-bottom, 0px))' }">
        <div class="w-10 h-1 rounded bg-gray-200 mx-auto mb-3"></div>
        <div class="text-[17px] font-bold text-ink mb-3">{{ form.id ? '항목 수정' : '새 항목' }}</div>
        <div class="space-y-3">
          <input v-model="form.title" maxlength="200" placeholder="제목" aria-label="제목" class="w-full min-h-[50px] rounded-xl border border-gray-200 px-3" />
          <textarea v-model="form.detail" rows="5" maxlength="5000" placeholder="설명 / 메모 (선택)" aria-label="설명" class="w-full rounded-xl border border-gray-200 px-3 py-3"></textarea>
          <div>
            <div class="text-[13px] font-bold text-ink-muted mb-1.5">분류</div>
            <div class="flex gap-2 overflow-x-auto scrollbar-hide mb-2">
              <button v-for="c in categories" :key="c" type="button" @click="form.category = c"
                class="shrink-0 min-h-[44px] px-4 rounded-full border text-[15px]"
                :class="form.category === c ? 'bg-ink text-white border-ink font-bold' : 'bg-white text-ink border-gray-200'">{{ c }}</button>
            </div>
            <input v-model="form.category" maxlength="30" placeholder="직접 입력" aria-label="분류" class="w-full min-h-[48px] rounded-xl border border-gray-200 px-3" />
          </div>
          <div>
            <div class="text-[13px] font-bold text-ink-muted mb-1.5">우선순위</div>
            <div class="grid grid-cols-3 gap-1 bg-gray-100 rounded-xl p-1">
              <button v-for="o in priOptions" :key="o.v" type="button" @click="form.priority = o.v"
                class="min-h-[44px] rounded-lg text-[15px] font-bold" :class="form.priority === o.v ? 'bg-white text-ink shadow-sm' : 'text-ink-muted'">{{ o.l }}</button>
            </div>
          </div>
          <div>
            <div class="text-[13px] font-bold text-ink-muted mb-1.5">상태</div>
            <div class="grid grid-cols-3 gap-1 bg-gray-100 rounded-xl p-1">
              <button v-for="o in statusOptions" :key="o.v" type="button" @click="form.status = o.v"
                class="min-h-[44px] rounded-lg text-[15px] font-bold" :class="form.status === o.v ? 'bg-white text-ink shadow-sm' : 'text-ink-muted'">{{ o.l }}</button>
            </div>
          </div>
          <button @click="save" :disabled="!form.title.trim() || saving" class="w-full min-h-[52px] rounded-xl bg-amber-500 text-white text-[16px] font-bold disabled:opacity-40">{{ saving ? '저장 중...' : '저장' }}</button>
          <button v-if="form.id" @click="removeFromSheet" class="w-full min-h-[50px] rounded-xl bg-red-50 text-red-600 text-[16px] font-bold">삭제</button>
          <button @click="form = null" class="w-full min-h-[50px] rounded-xl bg-gray-100 text-ink text-[16px] font-bold">취소</button>
        </div>
      </div>
    </div>
  </Teleport>
</div>

<!-- ───────── PC 화면 ───────── -->
<div v-else>
  <div class="mb-4 flex items-start justify-between flex-wrap gap-2">
    <div>
      <div class="text-xs text-ink-muted">관리자 › 시스템 › 할 일 목록</div>
      <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink mt-1">
        <span class="icon-chip w-9 h-9 bg-amber-50 text-amber-600"><AppIcon name="list" :size="20" /></span>
        할 일 목록
      </h1>
      <p class="text-xs text-ink-faint mt-0.5">앞으로 만들거나 보강할 것들을 적어 두고, 상태와 우선순위를 직접 고칠 수 있어요</p>
    </div>
    <button @click="openNew" class="btn-primary px-4 py-2 text-sm"><AppIcon name="plus" :size="14" />항목 추가</button>
  </div>

  <div class="grid grid-cols-3 gap-3 mb-4">
    <div class="card p-3"><div class="text-xs text-ink-muted">할 일</div><div class="text-xl font-bold text-ink">{{ count('todo') }}</div></div>
    <div class="card p-3"><div class="text-xs text-ink-muted">진행 중</div><div class="text-xl font-bold text-blue-600">{{ count('doing') }}</div></div>
    <div class="card p-3"><div class="text-xs text-ink-muted">완료</div><div class="text-xl font-bold text-emerald-600">{{ count('done') }}</div></div>
  </div>

  <div class="flex flex-wrap items-center gap-2 mb-3 text-xs">
    <button v-for="f in statusFilters" :key="f.v" @click="statusFilter = f.v"
      class="px-3 py-1.5 rounded-full font-bold transition-colors"
      :class="statusFilter === f.v ? 'bg-amber-400 text-white' : 'bg-gray-100 text-ink-muted hover:bg-gray-200'">{{ f.l }}</button>
    <select v-model="catFilter" class="input-soft !w-auto !px-2 !py-1 !text-xs ml-auto">
      <option value="">전체 분류</option>
      <option v-for="c in categories" :key="c" :value="c">{{ c }}</option>
    </select>
  </div>

  <div v-if="error" class="mb-3 bg-red-50 border border-red-200 text-red-600 text-xs rounded-xl p-3">{{ error }}</div>

  <div class="space-y-2">
    <div v-for="t in visible" :key="t.id" class="card p-3" :class="t.status === 'done' ? 'opacity-70' : ''">
      <div class="flex items-start gap-3">
        <input type="checkbox" :checked="t.status === 'done'" @change="setStatus(t, t.status === 'done' ? 'todo' : 'done')" class="mt-1 w-4 h-4 accent-amber-500 cursor-pointer" />
        <div class="min-w-0 flex-1">
          <div class="flex flex-wrap items-center gap-1.5">
            <span class="font-bold text-sm text-ink" :class="t.status === 'done' ? 'line-through' : ''">{{ t.title }}</span>
            <span class="text-[10px] font-bold px-1.5 py-0.5 rounded-md" :class="priClass(t.priority)">{{ priLabel(t.priority) }}</span>
            <span class="text-[10px] font-bold px-1.5 py-0.5 rounded-md bg-gray-100 text-ink-muted">{{ t.category }}</span>
            <span v-if="t.status === 'doing'" class="text-[10px] font-bold px-1.5 py-0.5 rounded-md bg-blue-50 text-blue-600">진행 중</span>
          </div>
          <p v-if="t.detail" class="text-xs text-ink-light mt-1 whitespace-pre-wrap leading-relaxed">{{ t.detail }}</p>
        </div>
        <div class="flex items-center gap-1 shrink-0">
          <button v-if="t.status === 'todo'" @click="setStatus(t, 'doing')" class="text-[11px] text-blue-500 hover:text-blue-700 font-bold px-1.5">시작</button>
          <button @click="openEdit(t)" class="text-ink-muted hover:text-ink p-1" title="수정"><AppIcon name="edit" :size="14" /></button>
          <button @click="remove(t)" class="text-red-400 hover:text-red-600 p-1" title="삭제"><AppIcon name="trash" :size="14" /></button>
        </div>
      </div>
    </div>
    <div v-if="!visible.length" class="card px-4 py-8 text-sm text-ink-muted text-center">{{ loading ? '불러오는 중...' : '해당하는 항목이 없어요' }}</div>
  </div>

  <!-- 추가/수정 -->
  <div v-if="form && !isMobile" class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4" @click.self="form = null">
    <div class="card w-full max-w-lg p-4 space-y-3">
      <div class="font-bold text-ink">{{ form.id ? '항목 수정' : '새 항목' }}</div>
      <input v-model="form.title" maxlength="200" placeholder="제목" class="input-soft w-full" />
      <textarea v-model="form.detail" rows="5" maxlength="5000" placeholder="설명 / 메모 (선택)" class="input-soft w-full"></textarea>
      <div class="grid grid-cols-3 gap-2">
        <input v-model="form.category" list="todo-cats" maxlength="30" placeholder="분류" class="input-soft" />
        <datalist id="todo-cats"><option v-for="c in categories" :key="c" :value="c" /></datalist>
        <select v-model="form.priority" class="input-soft"><option value="high">높음</option><option value="medium">보통</option><option value="low">낮음</option></select>
        <select v-model="form.status" class="input-soft"><option value="todo">할 일</option><option value="doing">진행 중</option><option value="done">완료</option></select>
      </div>
      <div class="flex justify-end gap-2">
        <button @click="form = null" class="px-4 py-2 text-sm rounded-xl bg-gray-100 text-ink-muted font-bold hover:bg-gray-200">취소</button>
        <button @click="save" :disabled="!form.title.trim() || saving" class="btn-primary px-4 py-2 text-sm disabled:opacity-50">{{ saving ? '저장 중...' : '저장' }}</button>
      </div>
    </div>
  </div>
</div>
</template>
<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch, inject } from 'vue'
import axios from 'axios'
import AppIcon from '../../components/AppIcon.vue'

const todos = ref([])
const loading = ref(true)
const error = ref('')
const statusFilter = ref('open')
const catFilter = ref('')
const form = ref(null)

// 관리자 휴대폰 화면이면 카드 + 아래에서 올라오는 시트로 보여 줌 (AdminLayout 이 알려 줌)
const adminIsMobile = inject('adminIsMobile', ref(false))
const isMobile = computed(() => !!adminIsMobile.value)
// 시트가 열려 있는 동안 뒤쪽 화면이 같이 스크롤되지 않게
watch(() => isMobile.value && !!form.value, locked => { document.body.style.overflow = locked ? 'hidden' : '' })
onBeforeUnmount(() => { document.body.style.overflow = '' })
const saving = ref(false)

const priOptions = [{ v: 'high', l: '높음' }, { v: 'medium', l: '보통' }, { v: 'low', l: '낮음' }]
const statusOptions = [{ v: 'todo', l: '할 일' }, { v: 'doing', l: '진행 중' }, { v: 'done', l: '완료' }]
const statusFilters = [{ v: 'open', l: '해야 할 것' }, { v: 'done', l: '완료' }, { v: 'all', l: '전체' }]
const categories = computed(() => [...new Set(['보안', '서버', '기능', '데이터', '콘텐츠', ...todos.value.map(t => t.category)])])
const count = (s) => todos.value.filter(t => t.status === s).length
const visible = computed(() => todos.value.filter(t =>
  (statusFilter.value === 'all' || (statusFilter.value === 'done' ? t.status === 'done' : t.status !== 'done')) &&
  (!catFilter.value || t.category === catFilter.value)))

const priLabel = (p) => ({ high: '높음', medium: '보통', low: '낮음' }[p] || p)
const priClass = (p) => ({ high: 'bg-red-50 text-red-600', medium: 'bg-amber-50 text-amber-700', low: 'bg-gray-100 text-ink-muted' }[p] || '')

async function load() {
  loading.value = true
  try {
    const { data } = await axios.get('/api/admin/todos')
    todos.value = data.data || []
    error.value = ''
  } catch (e) {
    error.value = e.response?.status === 403 ? '최고 관리자만 볼 수 있는 화면이에요.' : '불러오지 못했어요.'
  } finally { loading.value = false }
}

function openNew() { form.value = { title: '', detail: '', category: '기능', priority: 'medium', status: 'todo' } }
function openEdit(t) { form.value = { ...t, detail: t.detail || '' } }

async function save() {
  if (!form.value.title.trim()) return
  saving.value = true
  try {
    const body = { title: form.value.title.trim(), detail: form.value.detail || null, category: form.value.category || '기능', priority: form.value.priority, status: form.value.status }
    if (form.value.id) await axios.put('/api/admin/todos/' + form.value.id, body)
    else await axios.post('/api/admin/todos', body)
    form.value = null
    await load()
  } catch { error.value = '저장하지 못했어요.' } finally { saving.value = false }
}

async function setStatus(t, status) {
  try { await axios.put('/api/admin/todos/' + t.id, { status }); t.status = status; await load() } catch { error.value = '바꾸지 못했어요.' }
}

async function remove(t) {
  if (!confirm(`"${t.title}" 항목을 삭제할까요?`)) return
  try { await axios.delete('/api/admin/todos/' + t.id); todos.value = todos.value.filter(x => x.id !== t.id) } catch { error.value = '삭제하지 못했어요.' }
}

async function removeFromSheet() {
  const t = form.value
  if (!t?.id) return
  await remove(t)
  if (!todos.value.some(x => x.id === t.id)) form.value = null
}

onMounted(load)
</script>
<style>
/* 휴대폰 관리자: 입력창 글자가 16px 보다 작으면 iOS 가 화면을 확대해 버림 */
.alv-m input, .alv-m textarea, .alv-m select { font-size: 16px; }
</style>
