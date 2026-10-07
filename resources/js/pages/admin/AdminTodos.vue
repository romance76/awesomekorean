<template>
<div>
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
  <div v-if="form" class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4" @click.self="form = null">
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
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import AppIcon from '../../components/AppIcon.vue'

const todos = ref([])
const loading = ref(true)
const error = ref('')
const statusFilter = ref('open')
const catFilter = ref('')
const form = ref(null)
const saving = ref(false)

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

onMounted(load)
</script>
