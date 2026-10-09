<template>
<div class="min-h-screen">
  <div class="page-main px-4 py-5 rules-page">
    <router-link to="/events" class="no-print inline-flex items-center gap-1 text-sm text-ink-muted hover:text-ink mb-4">
      <span aria-hidden="true">&larr;</span> 경품 추첨 이벤트로 돌아가기
    </router-link>

    <div class="flex flex-wrap items-start gap-3 mb-5">
      <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink flex-1 min-w-0">
        <span class="icon-chip w-9 h-9 bg-violet-50 text-violet-600 shrink-0"><AppIcon name="gift" :size="20" /></span>
        <span class="break-words">{{ title || '경품 추첨 공식 규정' }}</span>
      </h1>
      <button v-if="!loading && !error" type="button" class="no-print btn-secondary text-sm !px-3 !py-1.5" @click="printPage">인쇄</button>
    </div>

    <div v-if="loading" class="card p-10 text-center text-ink-muted text-sm">불러오는 중...</div>

    <div v-else-if="error" class="card p-8 text-center">
      <p class="text-sm text-red-500 mb-3">{{ error }}</p>
      <button type="button" class="no-print btn-primary !px-5 !py-2 text-sm" @click="load">다시 시도</button>
    </div>

    <template v-else>
      <div v-if="!content" class="rounded-xl bg-amber-50 border border-amber-100 text-amber-800 text-sm p-4 mb-4">
        공식 규정이 아직 등록되지 않았어요. 곧 안내해 드릴게요.
      </div>
      <div v-else class="card p-5 sm:p-7">
        <nav v-if="toc.length > 2" class="no-print mb-6 rounded-xl bg-gray-50 border border-gray-100 p-4 text-sm">
          <div class="font-bold text-ink mb-2">목차</div>
          <ol class="space-y-1">
            <li v-for="h in toc" :key="h.id"><a :href="'#' + h.id" class="text-ink-light hover:text-ink" @click.prevent="goTo(h.id)">{{ h.text }}</a></li>
          </ol>
        </nav>
        <div ref="bodyEl" class="rules-body text-sm text-ink-light leading-relaxed" v-html="safeContent"></div>
      </div>
      <p v-if="updatedLabel" class="text-xs text-ink-faint mt-4">최종 수정: {{ updatedLabel }}</p>
    </template>
  </div>
</div>
</template>

<script setup>
import { ref, computed, onMounted, nextTick } from 'vue'
import axios from 'axios'
import { sanitizeHtml } from '../../utils/sanitizeHtml'
import AppIcon from '../../components/AppIcon.vue'

const loading = ref(true)
const error = ref('')
const title = ref('')
const content = ref('')
const updatedAt = ref('')
const bodyEl = ref(null)
const toc = ref([])

function esc(s) {
  return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
}

// HTML 태그가 있으면 sanitize 후 표시, 없으면 줄글/간단 표기(## 제목, - 목록)를 안전하게 변환
function plainToHtml(text) {
  const out = []
  let inList = false
  for (const raw of text.split('\n')) {
    const line = esc(raw.trimEnd())
    const li = line.match(/^\s*[-*]\s+(.*)$/)
    if (li) {
      if (!inList) { out.push('<ul>'); inList = true }
      out.push('<li>' + li[1] + '</li>')
      continue
    }
    if (inList) { out.push('</ul>'); inList = false }
    const h = line.match(/^(#{2,3})\s+(.*)$/)
    if (h) out.push(`<h${h[1].length}>${h[2]}</h${h[1].length}>`)
    else if (line.trim() === '') out.push('<br>')
    else out.push('<p>' + line + '</p>')
  }
  if (inList) out.push('</ul>')
  return out.join('')
}

const safeContent = computed(() => {
  if (!content.value) return ''
  if (/<[a-z][\s\S]*>/i.test(content.value)) return sanitizeHtml(content.value)
  return plainToHtml(content.value)
})

const updatedLabel = computed(() => {
  if (!updatedAt.value) return ''
  const d = new Date(updatedAt.value)
  if (isNaN(d.getTime())) return ''
  const p = n => String(n).padStart(2, '0')
  return `${d.getFullYear()}.${p(d.getMonth() + 1)}.${p(d.getDate())}`
})

function buildToc() {
  toc.value = []
  const el = bodyEl.value
  if (!el) return
  const list = []
  el.querySelectorAll('h2, h3, h4').forEach((h, i) => {
    h.id = 'rule-sec-' + i
    list.push({ id: h.id, text: h.textContent.trim() })
  })
  toc.value = list
}

function goTo(id) {
  const el = document.getElementById(id)
  if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

function printPage() { window.print() }

async function load() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await axios.get('/api/sweepstakes-rules')
    const d = data?.data || {}
    title.value = d.title || ''
    content.value = d.content || ''
    updatedAt.value = d.updated_at || ''
  } catch {
    error.value = '공식 규정을 불러오지 못했어요. 잠시 후 다시 시도해 주세요.'
  } finally {
    loading.value = false
    await nextTick()
    buildToc()
  }
}

onMounted(load)
</script>

<style scoped>
.rules-body :deep(h2) { font-size: 1.15rem; font-weight: bold; color: #1f2937; margin: 24px 0 8px; scroll-margin-top: 80px; }
.rules-body :deep(h2:first-child) { margin-top: 0; }
.rules-body :deep(h3) { font-size: 1rem; font-weight: bold; color: #1f2937; margin: 18px 0 6px; scroll-margin-top: 80px; }
.rules-body :deep(h4) { font-size: 0.95rem; font-weight: bold; margin: 16px 0 4px; scroll-margin-top: 80px; }
.rules-body :deep(p) { margin: 6px 0; }
.rules-body :deep(ul), .rules-body :deep(ol) { padding-left: 20px; margin: 6px 0; }
.rules-body :deep(ul) { list-style: disc; }
.rules-body :deep(ol) { list-style: decimal; }
.rules-body :deep(li) { margin: 3px 0; }
.rules-body :deep(a) { color: #2563eb; text-decoration: underline; }
.rules-body :deep(strong) { color: #111827; }
.rules-body :deep(table) { width: 100%; border-collapse: collapse; margin: 10px 0; display: block; overflow-x: auto; }
.rules-body :deep(th), .rules-body :deep(td) { border: 1px solid #e5e7eb; padding: 6px 10px; text-align: left; }
.rules-body { overflow-wrap: anywhere; }

@media print {
  .no-print { display: none !important; }
  .rules-page { padding: 0 !important; }
  .rules-page :deep(.card) { box-shadow: none !important; border: none !important; padding: 0 !important; }
}
</style>
