<template>
  <div class="rm space-y-3">
    <!-- 목록 -->
    <template v-if="mode === 'list'">
      <div v-if="loading" class="text-center py-8 text-sm text-ink-muted">이력서 불러오는 중…</div>
      <template v-else>
        <p v-if="selectable && list.length" class="text-xs text-ink-muted">지원에 쓸 이력서를 골라 주세요. 미리보기로 내용을 확인할 수 있어요.</p>

        <div v-if="!list.length" class="rounded-2xl border border-dashed border-gray-200 px-4 py-6 text-center">
          <div class="icon-chip w-12 h-12 bg-amber-50 text-amber-500 mx-auto mb-2"><AppIcon name="file-text" :size="24" /></div>
          <p class="text-sm font-semibold text-ink">등록된 이력서가 없어요</p>
          <p class="text-xs text-ink-muted mt-1 leading-relaxed">가지고 있는 이력서 파일을 올리거나, 여기서 직접 써서 만들 수 있어요.</p>
        </div>

        <div v-else class="space-y-2">
          <div v-for="r in list" :key="r.id"
            class="rounded-xl border p-3 transition-colors"
            :class="selectable && modelValue === r.id ? 'border-amber-400 bg-amber-50/60 ring-1 ring-amber-300' : 'border-gray-200 bg-white'"
            :role="selectable ? 'radio' : undefined" :aria-checked="selectable ? String(modelValue === r.id) : undefined"
            :tabindex="selectable ? 0 : undefined"
            @click="selectable && $emit('update:modelValue', r.id)" @keydown.enter.prevent="selectable && $emit('update:modelValue', r.id)" @keydown.space.prevent="selectable && $emit('update:modelValue', r.id)"
            :style="selectable ? 'cursor:pointer' : ''">
            <div class="flex items-start gap-2.5">
              <span v-if="selectable" class="mt-0.5 w-4 h-4 rounded-full border-2 shrink-0 flex items-center justify-center" :class="modelValue === r.id ? 'border-amber-500' : 'border-gray-300'">
                <span v-if="modelValue === r.id" class="w-2 h-2 rounded-full bg-amber-500"></span>
              </span>
              <span class="icon-chip w-9 h-9 shrink-0" :class="r.kind === 'uploaded' ? 'bg-sky-50 text-sky-600' : 'bg-amber-50 text-amber-600'">
                <AppIcon :name="r.kind === 'uploaded' ? 'paperclip' : 'edit'" :size="17" />
              </span>
              <div class="min-w-0 flex-1">
                <div class="text-sm font-bold text-ink truncate">{{ r.title }}</div>
                <div class="text-[11px] text-ink-muted mt-0.5 truncate">
                  <template v-if="r.kind === 'uploaded'">올린 파일 · {{ r.file_name }} · {{ fmtBytes(r.file_size) }}</template>
                  <template v-else>직접 쓴 이력서 · {{ r.name }}</template>
                </div>
                <div class="text-[10.5px] text-ink-faint mt-0.5">{{ fmtDay(r.updated_at) }} 수정</div>
              </div>
            </div>
            <div class="flex items-center gap-1.5 mt-2.5 flex-wrap">
              <button type="button" class="btn-soft !px-2.5 !py-1 !text-[11px]" @click.stop="openPreview(r)"><AppIcon name="eye" :size="12" />미리보기</button>
              <button type="button" class="btn-soft !px-2.5 !py-1 !text-[11px]" @click.stop="startEdit(r)"><AppIcon name="edit" :size="12" />{{ r.kind === 'uploaded' ? '이름 바꾸기' : '수정' }}</button>
              <button type="button" class="text-[11px] text-red-400 hover:text-red-600 px-2 py-1 ml-auto" @click.stop="removeResume(r)"><AppIcon name="trash" :size="12" /> 삭제</button>
            </div>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-2 pt-1">
          <button type="button" class="btn-primary !py-2.5 text-sm justify-center" :disabled="full" @click="startWrite"><AppIcon name="edit" :size="15" />직접 쓰기</button>
          <button type="button" class="btn-secondary !py-2.5 text-sm justify-center" :disabled="full" @click="startUpload"><AppIcon name="upload" :size="15" />파일 올리기</button>
        </div>
        <p v-if="full" class="text-[11px] text-amber-700">이력서는 최대 {{ max }}개까지 둘 수 있어요. 안 쓰는 이력서를 지운 뒤 추가해 주세요.</p>
        <p v-else class="text-[11px] text-ink-faint">파일은 PDF · JPG · PNG, 10MB 이하. Word 파일은 PDF 로 저장해서 올려 주세요.</p>
      </template>
    </template>

    <!-- 파일 올리기 -->
    <form v-else-if="mode === 'upload'" class="space-y-3" @submit.prevent="submitUpload">
      <div class="flex items-center gap-2"><button type="button" class="text-xs text-ink-muted hover:text-ink" @click="backToList">&larr; 목록</button><span class="text-sm font-bold text-ink">이력서 파일 올리기</span></div>
      <div>
        <label class="input-label">파일 (PDF · JPG · PNG, 10MB 이하)</label>
        <label class="flex items-center gap-2 rounded-xl bg-[#F4F6F8] text-ink-muted px-4 py-3 cursor-pointer hover:bg-amber-50 transition-colors">
          <AppIcon name="upload" :size="16" />
          <span class="text-sm truncate">{{ upFile ? upFile.name : '파일을 선택하세요' }}</span>
          <input type="file" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" class="hidden" @change="onPickFile" />
        </label>
        <p class="text-[11px] text-ink-faint mt-1">Word(.doc, .docx) 파일은 'PDF 로 저장'한 뒤 올려 주세요.</p>
      </div>
      <div>
        <label class="input-label">이력서 이름 <span class="text-red-400">*</span></label>
        <input v-model="upTitle" maxlength="100" placeholder="예: 영문 이력서, 요식업 경력 이력서" class="input-soft" />
      </div>
      <p v-if="err" class="text-sm text-red-500">{{ err }}</p>
      <button type="submit" class="btn-primary w-full !py-2.5 text-sm" :disabled="saving || !upFile || !upTitle.trim()">{{ saving ? '올리는 중…' : '올리기' }}</button>
    </form>

    <!-- 이름만 바꾸기 (올린 파일) -->
    <form v-else-if="mode === 'rename'" class="space-y-3" @submit.prevent="submitRename">
      <div class="flex items-center gap-2"><button type="button" class="text-xs text-ink-muted hover:text-ink" @click="backToList">&larr; 목록</button><span class="text-sm font-bold text-ink">이력서 이름 바꾸기</span></div>
      <input v-model="upTitle" maxlength="100" class="input-soft" />
      <p v-if="err" class="text-sm text-red-500">{{ err }}</p>
      <button type="submit" class="btn-primary w-full !py-2.5 text-sm" :disabled="saving || !upTitle.trim()">{{ saving ? '저장 중…' : '저장' }}</button>
    </form>

    <!-- 직접 쓰기 / 고치기 -->
    <form v-else class="space-y-3" @submit.prevent="submitWrite">
      <div class="flex items-center gap-2"><button type="button" class="text-xs text-ink-muted hover:text-ink" @click="backToList">&larr; 목록</button><span class="text-sm font-bold text-ink">{{ editing ? '이력서 수정' : '이력서 직접 쓰기' }}</span></div>
      <div>
        <label class="input-label">이력서 이름 <span class="text-red-400">*</span></label>
        <input v-model="f.title" maxlength="100" placeholder="예: 요식업 경력 3년 구직자" class="input-soft" />
      </div>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div><label class="input-label">이름 <span class="text-red-400">*</span></label><input v-model="f.name" maxlength="50" class="input-soft" /></div>
        <div><label class="input-label">연락처</label><input v-model="f.phone" maxlength="30" placeholder="예: 404-555-1234" class="input-soft" /></div>
      </div>
      <div><label class="input-label">이메일</label><input v-model="f.email" type="email" maxlength="120" class="input-soft" /></div>
      <div class="grid grid-cols-2 gap-3">
        <div><label class="input-label">도시</label><input v-model="f.city" maxlength="100" placeholder="Duluth" class="input-soft" /></div>
        <div><label class="input-label">주</label><input v-model="f.state" maxlength="10" placeholder="GA" class="input-soft" /></div>
      </div>
      <div><label class="input-label">자기소개</label><textarea v-model="f.summary" rows="4" maxlength="3000" placeholder="간단한 자기소개를 적어 주세요" class="input-soft"></textarea></div>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div>
          <label class="input-label">희망 직종</label>
          <select v-model="f.category" class="input-soft"><option value="">선택 안 함</option><option v-for="c in RESUME_CATEGORIES" :key="c.value" :value="c.value">{{ c.label }}</option></select>
        </div>
        <div>
          <label class="input-label">희망 근무형태</label>
          <select v-model="f.desired_type" class="input-soft"><option value="">선택 안 함</option><option v-for="c in RESUME_TYPES" :key="c.value" :value="c.value">{{ c.label }}</option></select>
        </div>
      </div>
      <div><label class="input-label">희망 급여</label><input v-model="f.desired_pay" maxlength="100" placeholder="예: 시급 $18 이상, 월급 $3,000 이상" class="input-soft" /></div>
      <div><label class="input-label">경력 사항</label><textarea v-model="f.experience" rows="6" maxlength="6000" placeholder="예)&#10;2021.03 ~ 현재  OO식당 주방 보조 (Duluth, GA)&#10;- 점심·저녁 준비, 재료 관리" class="input-soft"></textarea></div>
      <div><label class="input-label">학력</label><textarea v-model="f.education" rows="3" maxlength="4000" class="input-soft"></textarea></div>
      <div><label class="input-label">보유 기술</label><textarea v-model="f.skills" rows="3" maxlength="3000" class="input-soft"></textarea></div>
      <div><label class="input-label">자격증</label><textarea v-model="f.certifications" rows="3" maxlength="3000" class="input-soft"></textarea></div>
      <p v-if="err" class="text-sm text-red-500">{{ err }}</p>
      <div class="flex gap-2">
        <button type="button" class="btn-secondary !py-2.5 text-sm flex-1 justify-center" :disabled="!f.name" @click="previewDraft"><AppIcon name="eye" :size="14" />미리보기</button>
        <button type="submit" class="btn-primary !py-2.5 text-sm flex-1 justify-center" :disabled="saving">{{ saving ? '저장 중…' : '저장' }}</button>
      </div>
    </form>

    <ResumePreviewModal v-model="showPreview" :resume="previewResume" :file-url="previewFileUrl" />
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import axios from 'axios'
import AppIcon from '../AppIcon.vue'
import ResumePreviewModal from './ResumePreviewModal.vue'
import { RESUME_CATEGORIES, RESUME_TYPES, fmtBytes } from './resumeOptions'
import { useAuthStore } from '../../stores/auth'
import { useModal } from '../../composables/useModal'

const props = defineProps({
  selectable: { type: Boolean, default: false },   // true: 지원할 때처럼 하나를 고르는 화면
  modelValue: { type: Number, default: null },     // 고른 이력서 id
})
const emit = defineEmits(['update:modelValue', 'loaded'])

const auth = useAuthStore()
const { showConfirm } = useModal()
const list = ref([])
const max = ref(5)
const loading = ref(true)
const mode = ref('list')   // list | write | upload | rename
const editing = ref(null)
const saving = ref(false)
const err = ref('')
const full = computed(() => list.value.length >= max.value)

const blank = () => ({ title: '', name: auth.user?.name || '', phone: '', email: auth.user?.email || '', summary: '', category: '', desired_type: '', desired_pay: '', city: '', state: '', experience: '', education: '', skills: '', certifications: '' })
const f = reactive(blank())
const upFile = ref(null)
const upTitle = ref('')

const showPreview = ref(false)
const previewResume = ref(null)
const previewFileUrl = ref('')

function fmtDay(d) { return d ? new Date(d).toLocaleDateString('ko-KR', { year: 'numeric', month: 'short', day: 'numeric' }) : '' }

async function load(selectId) {
  loading.value = true
  try {
    const { data } = await axios.get('/api/my-resumes')
    list.value = data.data || []
    max.value = data.max || 5
  } catch { list.value = [] }
  loading.value = false
  if (props.selectable) {
    const ids = list.value.map(r => r.id)
    if (selectId && ids.includes(selectId)) emit('update:modelValue', selectId)
    else if (!ids.includes(props.modelValue)) emit('update:modelValue', ids[0] || null)
  }
  emit('loaded', list.value)
}
onMounted(() => load())

function backToList() { mode.value = 'list'; editing.value = null; err.value = '' }
function startWrite() { Object.assign(f, blank()); editing.value = null; err.value = ''; mode.value = 'write' }
function startUpload() { upFile.value = null; upTitle.value = ''; err.value = ''; mode.value = 'upload' }
function startEdit(r) {
  err.value = ''; editing.value = r
  if (r.kind === 'uploaded') { upTitle.value = r.title; mode.value = 'rename'; return }
  Object.assign(f, blank(), Object.fromEntries(Object.keys(blank()).map(k => [k, r[k] ?? ''])))
  mode.value = 'write'
}

function openPreview(r) {
  previewResume.value = r
  previewFileUrl.value = r.kind === 'uploaded' ? `/api/resumes/${r.id}/file` : ''
  showPreview.value = true
}
function previewDraft() { previewResume.value = { ...f, kind: 'written' }; previewFileUrl.value = ''; showPreview.value = true }

function onPickFile(e) {
  const file = e.target.files?.[0]
  err.value = ''
  if (!file) return
  if (!/\.(pdf|jpe?g|png)$/i.test(file.name)) { err.value = 'PDF, JPG, PNG 파일만 올릴 수 있어요. Word 파일은 PDF 로 저장해서 올려 주세요.'; e.target.value = ''; return }
  if (file.size > 10 * 1024 * 1024) { err.value = '파일은 10MB 이하여야 해요.'; e.target.value = ''; return }
  upFile.value = file
  if (!upTitle.value.trim()) upTitle.value = file.name.replace(/\.[^.]+$/, '').slice(0, 100)
}

function apiError(e, fallback) {
  const errs = e.response?.data?.errors
  if (errs) return Object.values(errs)[0]?.[0] || fallback
  return e.response?.data?.message || fallback
}

async function submitWrite() {
  if (!f.title.trim() || !f.name.trim()) { err.value = '이력서 이름과 이름은 꼭 적어 주세요.'; return }
  saving.value = true; err.value = ''
  try {
    const body = { ...f }
    const { data } = editing.value ? await axios.put(`/api/resumes/${editing.value.id}`, body) : await axios.post('/api/resumes', body)
    mode.value = 'list'; editing.value = null
    await load(data.data?.id)
  } catch (e) { err.value = apiError(e, '저장하지 못했어요. 잠시 후 다시 시도해 주세요.') }
  saving.value = false
}
async function submitUpload() {
  if (!upFile.value || !upTitle.value.trim()) return
  saving.value = true; err.value = ''
  try {
    const fd = new FormData(); fd.append('file', upFile.value); fd.append('title', upTitle.value.trim())
    const { data } = await axios.post('/api/resumes/upload', fd)
    mode.value = 'list'
    await load(data.data?.id)
  } catch (e) { err.value = apiError(e, '올리지 못했어요. 잠시 후 다시 시도해 주세요.') }
  saving.value = false
}
async function submitRename() {
  saving.value = true; err.value = ''
  try { await axios.put(`/api/resumes/${editing.value.id}`, { title: upTitle.value.trim() }); mode.value = 'list'; editing.value = null; await load() }
  catch (e) { err.value = apiError(e, '저장하지 못했어요.') }
  saving.value = false
}
async function removeResume(r) {
  if (!await showConfirm(`'${r.title}' 이력서를 지울까요?\n이미 낸 지원서에 붙은 이력서는 그대로 보여요.`)) return
  try { await axios.delete(`/api/resumes/${r.id}`); await load() } catch {}
}

defineExpose({ reload: load })
</script>
