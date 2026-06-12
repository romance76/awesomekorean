<template>
<div class="min-h-screen">
  <div class="max-w-3xl mx-auto px-4 py-5">
    <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink mb-4">
      <span class="icon-chip w-9 h-9 bg-blue-50 text-blue-600"><AppIcon name="edit" :size="20" /></span>
      {{ isEdit ? '글 수정' : '글쓰기' }}
    </h1>

    <div class="card p-5 space-y-4">
      <div>
        <label class="input-label">게시판</label>
        <select v-model="form.board_id" class="input-soft px-3">
          <option value="">게시판을 선택하세요</option>
          <option v-for="b in boards" :key="b.id" :value="b.id">{{ b.name }}</option>
        </select>
      </div>
      <div>
        <label class="input-label">제목</label>
        <input v-model="form.title" type="text" placeholder="제목을 입력하세요" maxlength="120"
          class="input-soft px-3" />
      </div>

      <!-- 탭: 작성/미리보기 (Issue #24) -->
      <div>
        <div class="flex items-center justify-between mb-2">
          <label class="input-label mb-0">내용</label>
          <div class="flex gap-1 text-xs bg-gray-100 rounded-xl p-1">
            <button type="button" @click="tab='write'"
              :class="['px-3 py-1 rounded-lg transition flex items-center gap-1', tab==='write' ? 'bg-white shadow-sm font-semibold text-ink' : 'text-ink-muted']"><AppIcon name="edit" :size="12" /> 작성</button>
            <button type="button" @click="tab='preview'"
              :class="['px-3 py-1 rounded-lg transition flex items-center gap-1', tab==='preview' ? 'bg-white shadow-sm font-semibold text-ink' : 'text-ink-muted']"><AppIcon name="eye" :size="12" /> 미리보기</button>
          </div>
        </div>

        <!-- 툴바 -->
        <div v-show="tab==='write'" class="flex flex-wrap gap-1 rounded-t-xl bg-[#F4F6F8] px-2 py-1.5 text-xs">
          <button type="button" @click="wrapSelection('**','**')" class="px-2 py-1 rounded-lg hover:bg-white font-bold text-ink-light transition-colors" title="굵게 (Ctrl+B)">B</button>
          <button type="button" @click="wrapSelection('*','*')" class="px-2 py-1 rounded-lg hover:bg-white italic text-ink-light transition-colors" title="기울임 (Ctrl+I)">I</button>
          <button type="button" @click="wrapSelection('~~','~~')" class="px-2 py-1 rounded-lg hover:bg-white line-through text-ink-light transition-colors" title="취소선">S</button>
          <span class="w-px bg-gray-300 mx-1"></span>
          <button type="button" @click="prefixLine('# ')" class="px-2 py-1 rounded-lg hover:bg-white font-bold text-ink-light transition-colors" title="제목">H1</button>
          <button type="button" @click="prefixLine('## ')" class="px-2 py-1 rounded-lg hover:bg-white font-bold text-ink-light transition-colors" title="소제목">H2</button>
          <button type="button" @click="prefixLine('- ')" class="px-2 py-1 rounded-lg hover:bg-white text-ink-light transition-colors" title="목록">• 목록</button>
          <button type="button" @click="prefixLine('> ')" class="px-2 py-1 rounded-lg hover:bg-white text-ink-light transition-colors" title="인용">❝ 인용</button>
          <span class="w-px bg-gray-300 mx-1"></span>
          <button type="button" @click="insertLink" class="px-2 py-1 rounded-lg hover:bg-white text-ink-light transition-colors inline-flex items-center gap-1" title="링크"><AppIcon name="external-link" :size="12" /> 링크</button>
          <button type="button" @click="$refs.imgInput.click()" class="px-2 py-1 rounded-lg hover:bg-white text-ink-light transition-colors inline-flex items-center gap-1" title="이미지 삽입"><AppIcon name="image" :size="12" /> 이미지</button>
          <span class="w-px bg-gray-300 mx-1"></span>
          <button type="button" @click="clearDraft" class="px-2 py-1 rounded-lg hover:bg-white text-ink-muted ml-auto transition-colors inline-flex items-center gap-1" title="임시저장 지우기"><AppIcon name="trash" :size="12" /> 초기화</button>
        </div>

        <!-- 에디터 (드래그앤드롭 지원) -->
        <textarea v-show="tab==='write'" ref="editorRef" v-model="form.content" rows="14"
          @keydown="onEditorKey" @drop.prevent="onDrop" @dragover.prevent
          placeholder="내용을 입력하세요. 이미지는 툴바·파일 선택·드래그앤드롭 모두 가능합니다."
          class="input-soft rounded-t-none px-3 resize-y font-mono" style="min-height:340px"></textarea>

        <!-- 미리보기 -->
        <div v-show="tab==='preview'" class="bg-[#F4F6F8] rounded-xl px-4 py-3 text-sm text-ink min-h-[340px] whitespace-pre-wrap leading-relaxed">
          <div v-if="!form.content.trim()" class="text-ink-muted text-center py-10">미리볼 내용이 없습니다.</div>
          <div v-else v-html="previewHtml"></div>
        </div>

        <!-- 상태 바 -->
        <div class="flex items-center justify-between mt-1.5 text-[11px] text-ink-muted">
          <span class="flex items-center gap-1"><AppIcon name="edit" :size="12" /> {{ charCount }}자 · {{ lineCount }}줄</span>
          <span v-if="draftSavedAt" class="text-green-600 flex items-center gap-1"><AppIcon name="check" :size="12" /> 임시저장됨 ({{ draftSavedAt }})</span>
        </div>
      </div>

      <div>
        <label class="input-label">이미지 파일 (선택)</label>
        <input type="file" multiple accept="image/*" @change="onFiles" class="input-soft px-3 py-2" />
        <input ref="imgInput" type="file" multiple accept="image/*" class="hidden" @change="onFiles" />
        <div v-if="previews.length" class="flex flex-wrap gap-2 mt-2">
          <div v-for="(p, i) in previews" :key="i" class="relative">
            <img :src="p" class="w-20 h-20 object-cover rounded-xl border border-gray-100" />
            <button @click="removeFile(i)" class="absolute -top-1 -right-1 bg-red-500 text-white w-4 h-4 rounded-full flex items-center justify-center"><AppIcon name="x" :size="10" /></button>
          </div>
        </div>
        <div class="text-xs text-ink-muted mt-1 flex items-center gap-1"><AppIcon name="info" :size="12" /> 본문에 드래그해서 놓으면 자동으로 첨부됩니다.</div>
      </div>

      <div v-if="error" class="text-red-500 text-sm">{{ error }}</div>
      <div class="flex gap-3 pt-2">
        <button @click="submit" :disabled="submitting" class="btn-primary px-6">
          {{ submitting ? '저장 중...' : (isEdit ? '수정하기' : '등록하기 (+5P)') }}
        </button>
        <button @click="$router.back()" class="btn-secondary px-6">취소</button>
      </div>
    </div>
  </div>
</div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import axios from 'axios'
import AppIcon from '../../components/AppIcon.vue'

const router = useRouter()
const route = useRoute()
const boards = ref([])
const form = ref({ board_id: '', title: '', content: '' })
const files = ref([])
const error = ref('')
const submitting = ref(false)
const isEdit = ref(false)
const editId = ref(null)

const previews = ref([])
const tab = ref('write')
const editorRef = ref(null)
const draftSavedAt = ref('')
const DRAFT_KEY = 'postWrite.draft.v1'

const charCount = computed(() => form.value.content.length)
const lineCount = computed(() => form.value.content.split('\n').length)

// ─── 매우 단순한 마크다운 렌더러 (보안상 HTML 이스케이프 후 변환) ───
function escapeHtml(s) {
  return s.replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]))
}
const previewHtml = computed(() => {
  let t = escapeHtml(form.value.content)
  // 제목
  t = t.replace(/^##\s+(.+)$/gm, '<h3 class="text-base font-bold mt-3 mb-1">$1</h3>')
  t = t.replace(/^#\s+(.+)$/gm, '<h2 class="text-lg font-bold mt-4 mb-2">$1</h2>')
  // 인용
  t = t.replace(/^&gt;\s+(.+)$/gm, '<blockquote class="border-l-4 border-amber-300 pl-3 text-gray-600 my-1">$1</blockquote>')
  // 목록
  t = t.replace(/^- (.+)$/gm, '<li class="ml-4 list-disc">$1</li>')
  // 강조
  t = t.replace(/\*\*([^*]+)\*\*/g, '<b>$1</b>')
  t = t.replace(/\*([^*]+)\*/g, '<i>$1</i>')
  t = t.replace(/~~([^~]+)~~/g, '<s>$1</s>')
  // 링크
  t = t.replace(/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/g, '<a href="$2" target="_blank" rel="nofollow noopener" class="text-amber-600 underline">$1</a>')
  // 이미지 ![alt](url)
  t = t.replace(/!\[([^\]]*)\]\((https?:\/\/[^\s)]+|\/storage\/[^\s)]+)\)/g,
    '<img src="$2" alt="$1" class="my-2 rounded-lg max-w-full" />')
  // 줄바꿈
  return t.replace(/\n/g, '<br>')
})

// ─── 에디터 삽입 유틸 ───
function wrapSelection(before, after) {
  const el = editorRef.value
  if (!el) return
  const s = el.selectionStart, e = el.selectionEnd
  const v = form.value.content
  const selected = v.slice(s, e) || '텍스트'
  form.value.content = v.slice(0, s) + before + selected + after + v.slice(e)
  setTimeout(() => { el.focus(); el.selectionStart = s + before.length; el.selectionEnd = s + before.length + selected.length }, 0)
}
function prefixLine(prefix) {
  const el = editorRef.value
  if (!el) return
  const s = el.selectionStart
  const v = form.value.content
  // 현재 줄 시작 찾기
  const lineStart = v.lastIndexOf('\n', s - 1) + 1
  form.value.content = v.slice(0, lineStart) + prefix + v.slice(lineStart)
  setTimeout(() => { el.focus(); el.selectionStart = el.selectionEnd = s + prefix.length }, 0)
}
function insertLink() {
  const url = prompt('링크 URL:', 'https://')
  if (!url) return
  const text = prompt('표시할 텍스트:', '링크') || url
  wrapSelection('', '')
  const el = editorRef.value
  const s = el.selectionStart
  const v = form.value.content
  form.value.content = v.slice(0, s) + `[${text}](${url})` + v.slice(s)
}

function onEditorKey(e) {
  // Ctrl/Cmd + B / I
  if ((e.ctrlKey || e.metaKey) && !e.altKey) {
    if (e.key === 'b' || e.key === 'B') { e.preventDefault(); wrapSelection('**','**') }
    else if (e.key === 'i' || e.key === 'I') { e.preventDefault(); wrapSelection('*','*') }
  }
}

// ─── 드래그앤드롭 이미지 ───
function onDrop(e) {
  const dropped = Array.from(e.dataTransfer?.files || []).filter(f => f.type.startsWith('image/'))
  if (!dropped.length) return
  addFiles(dropped)
}

function addFiles(list) {
  list.forEach(f => {
    files.value.push(f)
    previews.value.push(URL.createObjectURL(f))
  })
}
function onFiles(e) {
  const newFiles = Array.from(e.target.files || [])
  if (newFiles.length) addFiles(newFiles)
  e.target.value = ''
}
function removeFile(i) {
  URL.revokeObjectURL(previews.value[i])
  files.value.splice(i, 1); previews.value.splice(i, 1)
}

// ─── 임시 저장 (localStorage) ───
let saveTimer = null
watch(() => [form.value.title, form.value.content, form.value.board_id], () => {
  clearTimeout(saveTimer)
  saveTimer = setTimeout(() => {
    if (isEdit.value) return
    const hasContent = form.value.title.trim() || form.value.content.trim()
    if (!hasContent) return
    const payload = { ...form.value, savedAt: Date.now() }
    try { localStorage.setItem(DRAFT_KEY, JSON.stringify(payload)) } catch {}
    const d = new Date()
    draftSavedAt.value = d.toLocaleTimeString('ko-KR', { hour: '2-digit', minute: '2-digit' })
  }, 800)
})

function clearDraft() {
  if (!confirm('임시저장과 본문을 모두 초기화할까요?')) return
  form.value.content = ''
  form.value.title = ''
  draftSavedAt.value = ''
  try { localStorage.removeItem(DRAFT_KEY) } catch {}
}

async function submit() {
  if (!form.value.board_id || !form.value.title || !form.value.content) {
    error.value = '게시판, 제목, 내용을 모두 입력해주세요'; return
  }
  submitting.value = true; error.value = ''
  try {
    if (isEdit.value) {
      await axios.put(`/api/posts/${editId.value}`, form.value)
      router.push(`/community/free/${editId.value}`)
    } else {
      const fd = new FormData()
      fd.append('board_id', form.value.board_id)
      fd.append('title', form.value.title)
      fd.append('content', form.value.content)
      files.value.forEach(f => fd.append('images[]', f))
      const { data } = await axios.post('/api/posts', fd, { headers: { 'Content-Type': 'multipart/form-data' } })
      try { localStorage.removeItem(DRAFT_KEY) } catch {}
      router.push(`/community/free/${data.data.id}`)
    }
  } catch (e) { error.value = e.response?.data?.message || '등록 실패' }
  submitting.value = false
}

onMounted(async () => {
  try {
    const { data } = await axios.get('/api/boards')
    boards.value = data.data || []
    if (route.params.board) {
      const b = boards.value.find(b => b.slug === route.params.board)
      if (b) form.value.board_id = b.id
    }
  } catch {}
  // 수정 모드
  if (route.query.edit) {
    editId.value = route.query.edit
    isEdit.value = true
    try {
      const { data } = await axios.get(`/api/posts/${editId.value}`)
      const p = data.data
      form.value = { board_id: p.board_id, title: p.title, content: p.content }
    } catch {}
    return
  }
  // 신규 작성 시 임시저장 복원
  try {
    const raw = localStorage.getItem(DRAFT_KEY)
    if (raw) {
      const d = JSON.parse(raw)
      if (d.title || d.content) {
        if (confirm('임시저장된 글이 있습니다. 복원할까요?')) {
          form.value.title = d.title || form.value.title
          form.value.content = d.content || ''
          if (d.board_id) form.value.board_id = d.board_id
        } else {
          localStorage.removeItem(DRAFT_KEY)
        }
      }
    }
  } catch {}
})
</script>
