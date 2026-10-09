<template>
<div class="space-y-5">
  <Transition name="toast">
    <div v-if="toast.show" role="status" :class="['fixed top-5 right-5 z-[100] text-white text-sm font-semibold px-4 py-3 rounded-xl shadow-lg flex items-center gap-2', toast.type === 'success' ? 'bg-green-500' : 'bg-red-500']">
      <span>{{ toast.type === 'success' ? '✓' : '✕' }}</span>{{ toast.message }}
    </div>
  </Transition>

  <div>
    <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink mb-2">
      <span class="icon-chip w-9 h-9 bg-violet-50 text-violet-600"><AppIcon name="book-open" :size="20" /></span>
      경품 추첨 공식 규정
    </h1>
    <p class="text-sm text-ink-muted">경품 추첨(Sweepstakes) 이벤트의 공식 규정을 작성합니다. 저장하면 공개 페이지에 바로 반영돼요.</p>
  </div>

  <div v-if="loading" class="card p-10 text-center text-ink-muted text-sm">불러오는 중...</div>

  <template v-else>
    <!-- 작성 가이드 -->
    <div class="card overflow-hidden">
      <button type="button" class="w-full flex items-center justify-between px-5 py-3 text-sm font-bold text-ink bg-violet-50" :aria-expanded="guideOpen" @click="guideOpen = !guideOpen">
        <span>작성 가이드 (체크리스트)</span>
        <span aria-hidden="true">{{ guideOpen ? '▲' : '▼' }}</span>
      </button>
      <div v-if="guideOpen" class="px-5 py-4 text-sm text-ink-light space-y-3">
        <ul class="grid sm:grid-cols-2 gap-x-6 gap-y-1.5 list-disc pl-5">
          <li><b>주최자</b> — 운영 주체와 연락처</li>
          <li><b>참가 자격</b> — 연령, 거주 지역</li>
          <li><b>No Purchase Necessary</b> — Entry는 구매할 수 없음</li>
          <li><b>응모 방법·기간</b></li>
          <li><b>상품·가치</b> — 상품 내용과 금액</li>
          <li><b>당첨 확률</b></li>
          <li><b>추첨 방식</b> — 서버 난수 사용, 결과 기록 보관</li>
          <li><b>당첨자 통보와 확인 기한</b></li>
          <li><b>상품 발송</b> — 디지털 상품권</li>
          <li><b>세금</b></li>
          <li><b>개인정보</b> 처리</li>
          <li><b>면책·무효 조항</b></li>
        </ul>
        <p class="font-bold text-red-600">※ 미국 주별 경품 규정이 달라 공개 전 법률 검토를 권장합니다.</p>
      </div>
    </div>

    <div class="card p-5 space-y-4">
      <div>
        <label for="sr-title" class="text-xs text-ink-muted block mb-1">제목</label>
        <input id="sr-title" v-model="form.title" type="text" maxlength="200" class="input-soft w-full" placeholder="경품 추첨 공식 규정" />
      </div>

      <div>
        <label for="sr-content" class="text-xs text-ink-muted block mb-1">내용 (HTML 태그 또는 일반 글, 간단 표기: ## 제목 / - 목록)</label>
        <div class="flex items-center gap-1 mb-2 p-1 bg-gray-50 border border-gray-100 rounded-xl">
          <button type="button" @click="fmt('bold')" class="tb-btn font-bold" aria-label="굵게">B</button>
          <button type="button" @click="fmt('italic')" class="tb-btn italic" aria-label="기울임">I</button>
          <div class="w-px h-5 bg-gray-300 mx-1"></div>
          <button type="button" @click="fmt('h2')" class="tb-btn">H2</button>
          <button type="button" @click="fmt('h3')" class="tb-btn">H3</button>
          <div class="w-px h-5 bg-gray-300 mx-1"></div>
          <button type="button" @click="fmt('list')" class="tb-btn" aria-label="목록">&#8801;</button>
          <button type="button" @click="fmt('link')" class="tb-btn" aria-label="링크">&#128279;</button>
          <div class="ml-auto text-xs text-ink-faint pr-2">{{ form.content.length }}자</div>
        </div>
        <textarea id="sr-content" ref="ta" v-model="form.content" rows="22" class="input-soft w-full resize-y font-mono text-sm" placeholder="공식 규정 내용을 입력하세요..."></textarea>
      </div>

      <div class="flex flex-wrap items-center gap-3">
        <button type="button" @click="save" :disabled="saving" class="btn-primary !px-6 !py-2.5">{{ saving ? '저장중...' : '저장하기' }}</button>
        <a href="/sweepstakes/rules" target="_blank" rel="noopener" class="text-sm text-violet-600 hover:underline">공개 페이지 미리보기/열기 ↗</a>
        <span class="ml-auto text-xs text-ink-faint">
          마지막 수정: {{ updatedLabel || '미정' }}<template v-if="version"> · v{{ version }}</template>
        </span>
      </div>
    </div>
  </template>
</div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onBeforeUnmount } from 'vue'
import axios from 'axios'
import AppIcon from '../../components/AppIcon.vue'

const loading = ref(true)
const saving = ref(false)
const guideOpen = ref(false)
const form = reactive({ title: '', content: '' })
const updatedAt = ref('')
const version = ref('')
const ta = ref(null)
const toast = reactive({ show: false, message: '', type: 'success' })
let toastTimer = null

function showToast(message, type = 'success') {
  clearTimeout(toastTimer)
  toast.message = message; toast.type = type; toast.show = true
  toastTimer = setTimeout(() => { toast.show = false }, 3000)
}
onBeforeUnmount(() => clearTimeout(toastTimer))

const updatedLabel = computed(() => {
  if (!updatedAt.value) return ''
  const d = new Date(updatedAt.value)
  if (isNaN(d.getTime())) return ''
  const p = n => String(n).padStart(2, '0')
  return `${d.getFullYear()}.${p(d.getMonth() + 1)}.${p(d.getDate())} ${p(d.getHours())}:${p(d.getMinutes())}`
})

function apply(d) {
  form.title = d?.title || ''
  form.content = d?.content || ''
  updatedAt.value = d?.updated_at || ''
  version.value = d?.version ?? ''
}

async function load() {
  loading.value = true
  try {
    const { data } = await axios.get('/api/admin/sweepstakes-rules')
    apply(data?.data)
  } catch (e) {
    showToast(e.response?.data?.message || '불러오기에 실패했습니다.', 'error')
  } finally {
    loading.value = false
  }
}

async function save() {
  saving.value = true
  try {
    const { data } = await axios.put('/api/admin/sweepstakes-rules', { title: form.title, content: form.content })
    if (data?.data) apply(data.data)
    showToast('공식 규정이 저장되었습니다.')
  } catch (e) {
    showToast(e.response?.data?.message || '저장에 실패했습니다.', 'error')
  } finally {
    saving.value = false
  }
}

// 선택 영역을 HTML 태그로 감싸기 (공개 페이지는 HTML을 sanitize 해서 표시)
function fmt(kind) {
  const el = ta.value
  if (!el) return
  const s = el.selectionStart, e = el.selectionEnd
  const sel = form.content.substring(s, e)
  let rep = sel
  switch (kind) {
    case 'bold': rep = `<strong>${sel || '굵은 글씨'}</strong>`; break
    case 'italic': rep = `<em>${sel || '기울임'}</em>`; break
    case 'h2': rep = `\n<h2>${sel || '제목'}</h2>\n`; break
    case 'h3': rep = `\n<h3>${sel || '소제목'}</h3>\n`; break
    case 'list': rep = `\n<ul>\n<li>${sel || '항목'}</li>\n</ul>\n`; break
    case 'link': {
      const url = window.prompt('URL을 입력하세요:', 'https://')
      if (url) rep = `<a href="${url}">${sel || '링크 텍스트'}</a>`
      break
    }
  }
  form.content = form.content.substring(0, s) + rep + form.content.substring(e)
}

onMounted(load)
</script>

<style scoped>
.tb-btn { min-width: 32px; height: 32px; padding: 0 8px; border-radius: 8px; font-size: 13px; color: #374151; }
.tb-btn:hover { background: #fff; box-shadow: 0 0 0 1px #e5e7eb; }
.toast-enter-active, .toast-leave-active { transition: all .2s; }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translateY(-8px); }
</style>
