<template>
<div>
  <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink mb-1">
    <span class="icon-chip w-9 h-9 bg-emerald-50 text-[18px]">💰</span> MONEY 관리
  </h1>
  <p class="text-sm text-ink-muted mb-4">정보 공유 카페와 머니 가이드를 관리해요. 지우는 대신 카페는 "끄기", 가이드는 "비공개", 글은 "숨김"으로. <a href="/money" target="_blank" class="text-emerald-700 font-bold underline">MONEY 화면 보기 ↗</a></p>

  <div class="flex gap-2 mb-4" role="tablist">
    <button v-for="t in tabs" :key="t.k" @click="tab = t.k" class="rounded-full border px-4 py-2 text-sm font-bold"
      :class="tab === t.k ? 'bg-ink text-white border-ink' : 'bg-white text-ink border-gray-200'">{{ t.l }}</button>
  </div>

  <!-- 카페 -->
  <div v-if="tab === 'cafes'" class="grid gap-4" :class="edit ? 'lg:grid-cols-[1fr_1.2fr]' : ''">
    <div class="card overflow-hidden">
      <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100">
        <b class="text-sm text-ink">카페 {{ cafes.length }}개</b>
        <button @click="openCafe(null)" class="btn-primary px-3 py-1.5 text-sm">+ 카페 추가</button>
      </div>
      <button v-for="c in cafes" :key="c.id" @click="openCafe(c)" class="w-full text-left flex items-center gap-3 px-4 py-3 border-b border-gray-50 last:border-0 hover:bg-gray-50" :class="edit?.id === c.id ? 'bg-emerald-50/60' : ''">
        <span class="text-xl">{{ c.icon }}</span>
        <span class="min-w-0 flex-1"><b class="block text-sm text-ink">{{ c.name }}</b><span class="block text-xs text-ink-muted truncate">{{ c.summary }}</span></span>
        <span class="text-xs text-ink-muted whitespace-nowrap">글 {{ c.post_count }}<template v-if="c.hidden_count"> · 숨김 {{ c.hidden_count }}</template></span>
        <span class="text-[11px] font-bold rounded-full px-2 py-0.5" :class="c.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-ink-muted'">{{ c.is_active ? '켜짐' : '꺼짐' }}</span>
      </button>
    </div>
    <form v-if="edit" @submit.prevent="saveCafe" class="card p-4 space-y-3">
      <div class="flex items-center justify-between"><b class="text-ink">{{ edit.id ? '카페 고치기' : '새 카페' }}</b><button type="button" @click="edit = null" class="text-sm text-ink-muted">닫기</button></div>
      <div class="grid grid-cols-[70px_1fr] gap-2">
        <label><span class="input-label">아이콘</span><input v-model="edit.icon" maxlength="8" class="input-soft text-center" /></label>
        <label><span class="input-label">카페 이름</span><input v-model="edit.name" maxlength="60" class="input-soft" /></label>
      </div>
      <label v-if="!edit.id"><span class="input-label">주소 (영문 소문자·숫자·-, 예: side-jobs)</span><input v-model="edit.slug" maxlength="60" class="input-soft font-mono" /></label>
      <label class="block"><span class="input-label">한 줄 소개</span><input v-model="edit.summary" maxlength="200" class="input-soft" /></label>
      <label class="block">
        <span class="input-label">대문 (카페 맨 위에 보이는 설명)</span>
        <textarea v-model="edit.introText" rows="12" class="input-soft font-mono text-sm"></textarea>
        <span class="block text-xs text-ink-muted mt-1">쓰는 법: <code>## 제목</code> · <code>- 목록</code> · 빈 줄 = 새 문단</span>
      </label>
      <div class="grid grid-cols-2 gap-2">
        <label><span class="input-label">순서</span><input v-model.number="edit.sort_order" type="number" min="0" class="input-soft" /></label>
        <label><span class="input-label">카페 운영자 회원 번호 (선택)</span><input v-model.number="edit.manager_user_id" type="number" min="1" class="input-soft" placeholder="비우면 모집 중" /></label>
      </div>
      <label class="flex items-center gap-2 text-sm font-bold text-ink"><input type="checkbox" v-model="edit.is_active" /> 카페 켜기 (끄면 사이트에서 안 보여요, 글은 그대로)</label>
      <div v-if="edit.introText" class="rounded-xl border border-gray-100 bg-gray-50 p-3">
        <div class="text-xs font-bold text-ink-muted mb-1">대문 미리보기</div>
        <div class="money-intro text-sm text-ink-light" v-html="simpleToHtml(edit.introText)"></div>
      </div>
      <button type="submit" :disabled="busy" class="btn-primary w-full py-2.5 disabled:opacity-50">{{ busy ? '저장 중...' : '저장' }}</button>
    </form>
  </div>

  <!-- 가이드 -->
  <div v-else-if="tab === 'guides'" class="grid gap-4" :class="gEdit ? 'lg:grid-cols-[1fr_1.2fr]' : ''">
    <div class="card overflow-hidden">
      <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100">
        <b class="text-sm text-ink">가이드 {{ guides.length }}개</b>
        <button @click="openGuide(null)" class="btn-primary px-3 py-1.5 text-sm">+ 가이드 추가</button>
      </div>
      <button v-for="g in guides" :key="g.id" @click="openGuide(g)" class="w-full text-left flex items-center gap-3 px-4 py-3 border-b border-gray-50 last:border-0 hover:bg-gray-50" :class="gEdit?.id === g.id ? 'bg-emerald-50/60' : ''">
        <span class="text-xl">{{ g.icon }}</span>
        <span class="min-w-0 flex-1"><b class="block text-sm text-ink">{{ g.title }}</b><span class="block text-xs text-ink-muted truncate">{{ g.summary }}</span></span>
        <span class="text-[11px] font-bold rounded-full px-2 py-0.5" :class="g.is_published ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-ink-muted'">{{ g.is_published ? '공개' : '비공개' }}</span>
      </button>
    </div>
    <form v-if="gEdit" @submit.prevent="saveGuide" class="card p-4 space-y-3">
      <div class="flex items-center justify-between"><b class="text-ink">{{ gEdit.id ? '가이드 고치기' : '새 가이드' }}</b><button type="button" @click="gEdit = null" class="text-sm text-ink-muted">닫기</button></div>
      <div class="grid grid-cols-[70px_1fr] gap-2">
        <label><span class="input-label">아이콘</span><input v-model="gEdit.icon" maxlength="8" class="input-soft text-center" /></label>
        <label><span class="input-label">제목</span><input v-model="gEdit.title" maxlength="200" class="input-soft" /></label>
      </div>
      <label v-if="!gEdit.id"><span class="input-label">주소 (영문 소문자·숫자·-)</span><input v-model="gEdit.slug" maxlength="80" class="input-soft font-mono" /></label>
      <label class="block"><span class="input-label">한 줄 소개</span><input v-model="gEdit.summary" maxlength="300" class="input-soft" /></label>
      <label class="block">
        <span class="input-label">본문</span>
        <textarea v-model="gEdit.contentText" rows="16" class="input-soft font-mono text-sm"></textarea>
        <span class="block text-xs text-ink-muted mt-1">쓰는 법: <code>## 제목</code> · <code>- 목록</code> · 빈 줄 = 새 문단. 금융·세무·보험 내용은 "전문가 상담" 안내가 화면에 자동으로 붙어요.</span>
      </label>
      <div class="grid grid-cols-2 gap-2">
        <label><span class="input-label">순서</span><input v-model.number="gEdit.sort_order" type="number" min="0" class="input-soft" /></label>
        <label class="flex items-end gap-2 text-sm font-bold text-ink pb-2"><input type="checkbox" v-model="gEdit.is_published" /> 공개 (MONEY 첫 화면에 보여요)</label>
      </div>
      <div v-if="gEdit.contentText" class="rounded-xl border border-gray-100 bg-gray-50 p-3 max-h-80 overflow-y-auto">
        <div class="text-xs font-bold text-ink-muted mb-1">미리보기</div>
        <div class="money-intro text-sm text-ink-light" v-html="simpleToHtml(gEdit.contentText)"></div>
      </div>
      <button type="submit" :disabled="busy" class="btn-primary w-full py-2.5 disabled:opacity-50">{{ busy ? '저장 중...' : '저장' }}</button>
    </form>
  </div>

  <!-- 카페 글 -->
  <div v-else class="card overflow-hidden">
    <div class="flex flex-wrap items-center gap-2 px-4 py-3 border-b border-gray-100">
      <select v-model="pFilter.cafe_id" @change="loadPosts(1)" class="input-soft w-auto py-1.5 text-sm">
        <option value="">모든 카페</option><option v-for="c in cafes" :key="c.id" :value="c.id">{{ c.icon }} {{ c.name }}</option>
      </select>
      <select v-model="pFilter.status" @change="loadPosts(1)" class="input-soft w-auto py-1.5 text-sm">
        <option value="">전체</option><option value="visible">보이는 글</option><option value="hidden">숨긴 글</option>
      </select>
      <form @submit.prevent="loadPosts(1)" class="flex gap-1.5"><input v-model="pFilter.search" placeholder="제목 검색" class="input-soft w-44 py-1.5 text-sm" /><button class="btn-secondary px-3 py-1.5 text-sm">검색</button></form>
      <span class="ml-auto text-xs text-ink-muted">{{ pTotal }}개</span>
    </div>
    <div v-if="!posts.length" class="py-10 text-center text-sm text-ink-muted">글이 없어요.</div>
    <div v-for="p in posts" :key="p.id" class="flex items-center gap-3 px-4 py-2.5 border-b border-gray-50 last:border-0 text-sm" :class="p.is_hidden ? 'opacity-60' : ''">
      <span class="w-6 text-center">{{ p.cafe?.icon }}</span>
      <a :href="`/money/post/${p.id}`" target="_blank" class="min-w-0 flex-1 truncate text-ink hover:underline">{{ p.title }}</a>
      <span class="text-xs text-ink-muted whitespace-nowrap">{{ p.user?.nickname || p.user?.name }} · {{ String(p.created_at).slice(0, 10) }} · 조회 {{ p.view_count }}</span>
      <button @click="togglePost(p)" class="text-xs font-bold rounded-lg border px-2.5 py-1" :class="p.is_hidden ? 'border-emerald-300 text-emerald-700' : 'border-red-200 text-red-600'">{{ p.is_hidden ? '복구' : '숨김' }}</button>
    </div>
    <div v-if="pLast > 1" class="flex justify-center gap-1.5 py-3">
      <button v-for="n in Math.min(pLast, 10)" :key="n" @click="loadPosts(n)" class="w-8 h-8 rounded-lg text-sm" :class="n === pPage ? 'bg-ink text-white font-bold' : 'text-ink-muted hover:bg-gray-100'">{{ n }}</button>
    </div>
  </div>
</div>
</template>

<script setup>
import { ref, reactive, watch, onMounted } from 'vue'
import axios from 'axios'
import { useSiteStore } from '../../stores/site'

const site = useSiteStore()
const say = (m, t = 'success') => site.toast?.(m, t)
const apiMsg = (e, f) => { const errs = e.response?.data?.errors; return (errs ? Object.values(errs).flat().join(' ') : e.response?.data?.message) || f }
const tabs = [{ k: 'cafes', l: '카페' }, { k: 'guides', l: '머니 가이드' }, { k: 'posts', l: '카페 글' }]
const tab = ref('cafes')
const busy = ref(false)

// ── 쉬운 글쓰기 형식 ↔ HTML ──
const esc = (s) => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
function simpleToHtml(text) {
  const out = []; let para = []; let list = []
  const flushP = () => { if (para.length) { out.push('<p>' + para.map(esc).join('<br>') + '</p>'); para = [] } }
  const flushL = () => { if (list.length) { out.push('<ul>' + list.map(i => '<li>' + esc(i) + '</li>').join('') + '</ul>'); list = [] } }
  for (const raw of String(text || '').split('\n')) {
    const line = raw.trimEnd()
    if (/^##\s+/.test(line)) { flushP(); flushL(); out.push('<h3>' + esc(line.replace(/^##\s+/, '')) + '</h3>') }
    else if (/^\s*-\s+/.test(line)) { flushP(); list.push(line.replace(/^\s*-\s+/, '')) }
    else if (!line.trim()) { flushP(); flushL() }
    else { flushL(); para.push(line) }
  }
  flushP(); flushL()
  return out.join('')
}
function htmlToSimple(html) {
  const box = document.createElement('div'); box.innerHTML = String(html || '')
  const parts = []
  for (const el of box.children) {
    const tag = el.tagName.toLowerCase()
    if (tag === 'h2' || tag === 'h3') parts.push('## ' + el.textContent.trim())
    else if (tag === 'ul' || tag === 'ol') parts.push([...el.querySelectorAll('li')].map(li => '- ' + li.textContent.trim()).join('\n'))
    else { const t = el.innerHTML.replace(/<br\s*\/?>/gi, '\n'); const d = document.createElement('div'); d.innerHTML = t; parts.push(d.textContent.trim()) }
  }
  if (!box.children.length && box.textContent.trim()) parts.push(box.textContent.trim())
  return parts.filter(Boolean).join('\n\n')
}

// ── 카페 ──
const cafes = ref([]); const edit = ref(null)
async function loadCafes() { try { const { data } = await axios.get('/api/admin/money/cafes'); cafes.value = data.data || [] } catch (e) { say(apiMsg(e, '카페를 불러오지 못했어요'), 'error') } }
function openCafe(c) {
  edit.value = c ? { ...c, introText: htmlToSimple(c.intro) } : { id: null, slug: '', name: '', icon: '☕', summary: '', introText: '## 이 카페는요\n\n## 이용 규칙\n- 가입 없이 누구나 보고 답글을 달 수 있어요.\n- 광고·홍보 글은 지워져요.', sort_order: null, is_active: true, manager_user_id: null }
}
async function saveCafe() {
  const e = edit.value
  busy.value = true
  try {
    const payload = { name: e.name, icon: e.icon, summary: e.summary, intro: simpleToHtml(e.introText), sort_order: e.sort_order ?? undefined, is_active: !!e.is_active, manager_user_id: e.manager_user_id || null }
    if (!e.id) payload.slug = e.slug
    const { data } = e.id ? await axios.put(`/api/admin/money/cafes/${e.id}`, payload) : await axios.post('/api/admin/money/cafes', payload)
    say(data.message || '저장했어요'); await loadCafes()
    const fresh = cafes.value.find(c => c.id === data.data.id); if (fresh) openCafe(fresh)
  } catch (err) { say(apiMsg(err, '저장하지 못했어요'), 'error') }
  busy.value = false
}

// ── 가이드 ──
const guides = ref([]); const gEdit = ref(null)
async function loadGuides() { try { const { data } = await axios.get('/api/admin/money/guides'); guides.value = data.data || [] } catch (e) { say(apiMsg(e, '가이드를 불러오지 못했어요'), 'error') } }
function openGuide(g) {
  gEdit.value = g ? { ...g, contentText: htmlToSimple(g.content) } : { id: null, slug: '', title: '', icon: '📘', summary: '', contentText: '## 1. 이게 뭔가요?\n\n## 2. 시작하는 순서\n- \n\n## 3. 자주 묻는 질문\n', is_published: false, sort_order: null }
}
async function saveGuide() {
  const g = gEdit.value
  busy.value = true
  try {
    const payload = { title: g.title, icon: g.icon, summary: g.summary, content: simpleToHtml(g.contentText), is_published: !!g.is_published, sort_order: g.sort_order ?? undefined }
    if (!g.id) payload.slug = g.slug
    const { data } = g.id ? await axios.put(`/api/admin/money/guides/${g.id}`, payload) : await axios.post('/api/admin/money/guides', payload)
    say(data.message || '저장했어요'); await loadGuides()
    const fresh = guides.value.find(x => x.id === data.data.id); if (fresh) openGuide(fresh)
  } catch (err) { say(apiMsg(err, '저장하지 못했어요'), 'error') }
  busy.value = false
}

// ── 카페 글 ──
const posts = ref([]); const pPage = ref(1); const pLast = ref(1); const pTotal = ref(0)
const pFilter = reactive({ cafe_id: '', status: '', search: '' })
async function loadPosts(n = 1) {
  try {
    const { data } = await axios.get('/api/admin/money/posts', { params: { page: n, cafe_id: pFilter.cafe_id || undefined, status: pFilter.status || undefined, search: pFilter.search || undefined } })
    posts.value = data.data.data || []; pPage.value = data.data.current_page; pLast.value = data.data.last_page; pTotal.value = data.data.total
  } catch (e) { say(apiMsg(e, '글을 불러오지 못했어요'), 'error') }
}
async function togglePost(p) {
  try { const { data } = await axios.post(`/api/admin/money/posts/${p.id}/toggle`); p.is_hidden = data.data.is_hidden; say(data.message) } catch (e) { say(apiMsg(e, '처리하지 못했어요'), 'error') }
}

watch(tab, (t) => { if (t === 'guides' && !guides.value.length) loadGuides(); if (t === 'posts') loadPosts(1) })
onMounted(loadCafes)
</script>
