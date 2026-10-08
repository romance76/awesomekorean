<template>
<!-- ───────── 휴대폰 화면 ───────── -->
<div v-if="isMobile" class="alv-m space-y-3 pb-4">
  <button @click="openForm()" class="w-full min-h-[52px] rounded-2xl bg-amber-500 text-white text-[16px] font-bold">+ 새 팝업</button>
  <div class="space-y-2">
    <div v-for="b in banners" :key="b.id" class="bg-white border border-gray-100 rounded-2xl p-3 flex items-center gap-3">
      <button @click="editBanner(b)" class="flex items-center gap-3 min-w-0 flex-1 text-left min-h-[64px]">
        <span class="shrink-0 w-12 h-12 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center overflow-hidden"><img v-if="b.type === 'image' && b.image_url" :src="b.image_url" alt="" loading="lazy" class="w-full h-full object-cover" /><AppIcon v-else :name="b.type === 'image' ? 'image' : 'edit'" :size="20" /></span>
        <span class="min-w-0"><span class="block text-[15px] font-bold text-ink truncate">{{ b.title }}</span><span class="block text-[12px] text-ink-muted truncate">{{ b.type === 'image' ? '이미지' : `텍스트 ${b.width}×${b.height}` }} · {{ b.display_mode === 'once_per_day' ? '하루 1회' : '매번' }}<template v-if="b.start_at || b.end_at"> · {{ fmt(b.start_at) }}~{{ fmt(b.end_at) }}</template></span></span>
      </button>
      <button type="button" role="switch" :aria-checked="!!b.is_active" :aria-label="b.title + ' 활성'" @click="toggleActive(b)" class="relative shrink-0 w-[54px] h-[32px] rounded-full transition-colors" :class="b.is_active ? 'bg-emerald-500' : 'bg-gray-300'"><span class="absolute top-[4px] w-6 h-6 bg-white rounded-full shadow transition-all" :class="b.is_active ? 'left-[26px]' : 'left-[4px]'"></span></button>
    </div>
    <div v-if="!banners.length" class="text-center text-ink-muted py-12 text-[15px]">등록된 팝업이 없어요</div>
  </div>

  <Teleport to="body">
    <div v-if="showForm" class="alv-m fixed inset-0 z-[70] bg-black/45 flex items-end" @click.self="resetForm">
      <div class="w-full bg-white rounded-t-3xl px-4 pt-2 max-h-[92vh] overflow-y-auto" role="dialog" aria-modal="true" :style="{ paddingBottom: 'calc(16px + env(safe-area-inset-bottom, 0px))' }">
        <div class="w-10 h-1 rounded bg-gray-200 mx-auto mb-3"></div>
        <div class="text-[17px] font-bold text-ink mb-3">{{ editId ? '팝업 배너 수정' : '새 팝업 배너' }}</div>
        <div class="space-y-3">
        <input v-model="form.title" placeholder="관리용 제목 (예: 신년 이벤트 공지)" aria-label="관리용 제목" class="w-full min-h-[50px] rounded-xl border border-gray-200 px-3" />
        <div class="grid grid-cols-2 gap-1 bg-gray-100 rounded-xl p-1"><button type="button" @click="form.type = 'text'" class="min-h-[44px] rounded-lg text-[15px] font-bold" :class="form.type === 'text' ? 'bg-white text-ink shadow-sm' : 'text-ink-muted'">📝 텍스트</button><button type="button" @click="form.type = 'image'" class="min-h-[44px] rounded-lg text-[15px] font-bold" :class="form.type === 'image' ? 'bg-white text-ink shadow-sm' : 'text-ink-muted'">🖼️ 이미지</button></div>
        <div v-if="form.type === 'image'" class="space-y-1"><div class="text-[13px] font-bold text-ink-muted">이미지 업로드</div><input type="file" accept="image/*" @change="onFile" class="block w-full text-[14px]" /><img v-if="form.image_url && !pickedFile" :src="form.image_url" alt="" class="max-h-32 rounded-lg border border-gray-200" /><div v-if="pickedFile" class="text-[12px] text-green-600">선택됨: {{ pickedFile.name }}</div></div>
        <template v-else>
          <textarea v-model="form.content" rows="5" placeholder="본문 (HTML 허용)" aria-label="본문" class="w-full rounded-xl border border-gray-200 px-3 py-3 font-mono"></textarea>
          <div class="grid grid-cols-2 gap-2"><label class="block"><span class="block text-[12px] text-ink-muted mb-1">가로 (px)</span><input v-model.number="form.width" type="number" inputmode="numeric" min="200" max="1200" class="w-full min-h-[48px] rounded-xl border border-gray-200 px-3" /></label><label class="block"><span class="block text-[12px] text-ink-muted mb-1">세로 (px)</span><input v-model.number="form.height" type="number" inputmode="numeric" min="150" max="900" class="w-full min-h-[48px] rounded-xl border border-gray-200 px-3" /></label></div>
        </template>
        <input v-model="form.link_url" placeholder="클릭 시 이동 URL (선택)" aria-label="이동 URL" class="w-full min-h-[48px] rounded-xl border border-gray-200 px-3" />
        <label class="block"><span class="block text-[12px] text-ink-muted mb-1">표시 빈도</span><select v-model="form.display_mode" class="w-full min-h-[48px] rounded-xl border border-gray-200 bg-white px-3"><option value="once_per_day">하루에 한 번</option><option value="every_visit">매번 방문 시</option></select></label>
        <label class="block"><span class="block text-[12px] text-ink-muted mb-1">시작 일시 (선택)</span><input v-model="form.start_at" type="datetime-local" class="w-full min-h-[48px] rounded-xl border border-gray-200 px-3" /></label>
        <label class="block"><span class="block text-[12px] text-ink-muted mb-1">종료 일시 (선택)</span><input v-model="form.end_at" type="datetime-local" class="w-full min-h-[48px] rounded-xl border border-gray-200 px-3" /></label>
        <label class="block"><span class="block text-[12px] text-ink-muted mb-1">순서</span><input v-model.number="form.sort_order" type="number" inputmode="numeric" class="w-full min-h-[48px] rounded-xl border border-gray-200 px-3" /></label>
        <label class="flex items-center gap-3 min-h-[48px] text-[16px] text-ink"><input v-model="form.is_active" type="checkbox" class="w-6 h-6 accent-amber-500" /> 활성화</label>
          <button @click="saveForm" :disabled="saving" class="w-full min-h-[52px] rounded-xl bg-amber-500 text-white text-[16px] font-bold disabled:opacity-50">{{ saving ? '저장 중...' : (editId ? '수정' : '등록') }}</button>
          <button v-if="editId" @click="mDelete" :disabled="saving" class="w-full min-h-[50px] rounded-xl bg-red-50 text-red-600 text-[16px] font-bold">삭제</button>
          <button @click="resetForm" class="w-full min-h-[50px] rounded-xl bg-gray-100 text-ink text-[16px] font-bold">취소</button>
        </div>
      </div>
    </div>
  </Teleport>
</div>

<!-- ───────── PC 화면 ───────── -->
<div v-else>
  <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink mb-4">
    <span class="icon-chip w-9 h-9 bg-amber-50 text-amber-600"><AppIcon name="message-square" :size="20" /></span>
    팝업 배너 관리
  </h1>
  <div class="card overflow-hidden mb-4">
    <div class="px-4 py-3 border-b border-gray-50 flex items-center justify-between">
      <div>
        <div class="font-bold text-sm text-ink">메인 팝업 배너</div>
        <div class="text-[11px] text-ink-muted mt-0.5">사이트 접속 시 띄우는 팝업 (이미지 또는 텍스트)</div>
      </div>
      <button @click="openForm()" class="btn-primary !px-3 !py-1 text-xs"><AppIcon name="plus" :size="13" /> 새 팝업</button>
    </div>

    <!-- 추가/수정 폼 -->
    <div v-if="showForm" class="px-4 py-4 border-b border-gray-50 bg-amber-50 space-y-3">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
        <div>
          <label class="input-label">관리용 제목 <span class="text-red-500">*</span></label>
          <input v-model="form.title" placeholder="예: 신년 이벤트 공지" class="input-soft !px-3 !py-1.5 text-sm" />
        </div>
        <div>
          <label class="input-label">타입</label>
          <select v-model="form.type" class="input-soft !px-3 !py-1.5 text-sm">
            <option value="text">📝 텍스트</option>
            <option value="image">🖼️ 이미지</option>
          </select>
        </div>
      </div>

      <!-- 이미지 타입 -->
      <div v-if="form.type === 'image'" class="space-y-2">
        <label class="input-label">이미지 업로드</label>
        <input type="file" accept="image/*" @change="onFile" class="text-xs" />
        <div v-if="form.image_url && !pickedFile" class="mt-1">
          <img :src="form.image_url" class="max-h-32 rounded-lg border border-gray-200" />
        </div>
        <div v-if="pickedFile" class="text-[11px] text-green-600">선택됨: {{ pickedFile.name }}</div>
      </div>

      <!-- 텍스트 타입 -->
      <div v-else class="space-y-2">
        <div>
          <label class="input-label">본문 (HTML 허용)</label>
          <textarea v-model="form.content" rows="5" placeholder="예: &lt;h2&gt;공지사항&lt;/h2&gt;&lt;p&gt;내용...&lt;/p&gt;" class="input-soft !px-3 !py-1.5 text-sm font-mono"></textarea>
        </div>
        <div class="grid grid-cols-2 gap-2">
          <div>
            <label class="input-label">가로 (px)</label>
            <input v-model.number="form.width" type="number" min="200" max="1200" class="input-soft !px-3 !py-1.5 text-sm" />
          </div>
          <div>
            <label class="input-label">세로 (px)</label>
            <input v-model.number="form.height" type="number" min="150" max="900" class="input-soft !px-3 !py-1.5 text-sm" />
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
        <div>
          <label class="input-label">클릭 시 이동 URL (선택)</label>
          <input v-model="form.link_url" placeholder="https://... 또는 /events" class="input-soft !px-3 !py-1.5 text-sm" />
        </div>
        <div>
          <label class="input-label">표시 빈도</label>
          <select v-model="form.display_mode" class="input-soft !px-3 !py-1.5 text-sm">
            <option value="once_per_day">하루에 한 번</option>
            <option value="every_visit">매번 방문 시</option>
          </select>
        </div>
        <div>
          <label class="input-label">시작 일시 (선택)</label>
          <input v-model="form.start_at" type="datetime-local" class="input-soft !px-3 !py-1.5 text-sm" />
        </div>
        <div>
          <label class="input-label">종료 일시 (선택)</label>
          <input v-model="form.end_at" type="datetime-local" class="input-soft !px-3 !py-1.5 text-sm" />
        </div>
        <div>
          <label class="input-label">순서</label>
          <input v-model.number="form.sort_order" type="number" class="input-soft !px-3 !py-1.5 text-sm" />
        </div>
        <div class="flex items-end">
          <label class="flex items-center gap-2 text-sm text-ink-light">
            <input v-model="form.is_active" type="checkbox" class="accent-amber-500 w-4 h-4" />
            활성화
          </label>
        </div>
      </div>

      <div class="flex gap-2 pt-1">
        <button @click="saveForm" :disabled="saving" class="btn-primary !px-4 !py-1.5 text-xs">
          {{ saving ? '저장 중...' : (editId ? '수정' : '등록') }}
        </button>
        <button @click="resetForm" class="btn-ghost !px-3 !py-1.5 text-xs">취소</button>
      </div>
    </div>

    <!-- 목록 -->
    <div v-for="b in banners" :key="b.id" class="px-4 py-3 border-b border-gray-50 flex items-center gap-3 hover:bg-amber-50/40 transition-colors">
      <div class="w-10 h-10 rounded-lg flex items-center justify-center bg-amber-50 text-amber-600 flex-shrink-0 overflow-hidden">
        <img v-if="b.type === 'image' && b.image_url" :src="b.image_url" class="w-full h-full object-cover" />
        <AppIcon v-else :name="b.type === 'image' ? 'image' : 'edit'" :size="18" />
      </div>
      <div class="flex-1 min-w-0">
        <div class="text-sm font-bold text-ink truncate">{{ b.title }}</div>
        <div class="text-[11px] text-ink-muted">
          {{ b.type === 'image' ? '이미지' : `텍스트 ${b.width}×${b.height}` }}
          · {{ b.display_mode === 'once_per_day' ? '하루 1회' : '매번' }}
          <span v-if="b.start_at || b.end_at"> · 기간 {{ fmt(b.start_at) }}~{{ fmt(b.end_at) }}</span>
        </div>
      </div>
      <label class="flex items-center gap-1 text-xs text-ink-light">
        <input type="checkbox" :checked="b.is_active" @change="toggleActive(b)" class="accent-amber-500" /> 활성
      </label>
      <button @click="editBanner(b)" class="text-xs text-amber-600 hover:text-amber-700 transition-colors">수정</button>
      <button @click="deleteBanner(b)" class="text-xs text-red-400 hover:text-red-600 transition-colors">삭제</button>
    </div>
    <div v-if="!banners.length" class="py-16 text-center">
      <div class="icon-chip w-14 h-14 bg-gray-100 text-gray-300 mx-auto mb-3"><AppIcon name="message-square" :size="28" :stroke-width="1.5" /></div>
      <p class="text-sm text-ink-muted">등록된 팝업이 없습니다</p>
    </div>
  </div>
</div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch, inject } from 'vue'
import axios from 'axios'
import AppIcon from '../../components/AppIcon.vue'

// 관리자 휴대폰 화면이면 카드 + 아래에서 올라오는 시트로 보여 줌 (AdminLayout 이 알려 줌)
const adminIsMobile = inject('adminIsMobile', ref(false))
const isMobile = computed(() => !!adminIsMobile.value)

const banners = ref([])
const showForm = ref(false)
const editId = ref(null)
const saving = ref(false)
const pickedFile = ref(null)

const DEFAULT_FORM = {
  title: '', type: 'text',
  image_url: '', content: '',
  width: 500, height: 300,
  link_url: '', display_mode: 'once_per_day',
  is_active: true, start_at: '', end_at: '', sort_order: 0,
}

const form = ref({ ...DEFAULT_FORM })

function fmt(dt) {
  if (!dt) return ''
  const d = new Date(dt)
  return `${d.getFullYear()}.${d.getMonth()+1}.${d.getDate()}`
}

// 시트가 열려 있는 동안 뒤쪽 화면이 같이 스크롤되지 않게 + 시트 안 삭제
async function mDelete() {
  const b = banners.value.find(x => x.id === editId.value)
  if (!b) return
  await deleteBanner(b)
  if (!banners.value.some(x => x.id === b.id)) resetForm()
}
watch(() => isMobile.value && showForm.value, locked => { document.body.style.overflow = locked ? 'hidden' : '' })
onBeforeUnmount(() => { document.body.style.overflow = '' })

async function load() {
  try {
    const { data } = await axios.get('/api/admin/popup-banners')
    banners.value = data.data || []
  } catch {}
}

function openForm() {
  resetForm()
  showForm.value = true
}

function onFile(e) {
  pickedFile.value = e.target.files?.[0] || null
}

async function saveForm() {
  if (!form.value.title) { alert('제목을 입력하세요'); return }
  if (form.value.type === 'text' && !form.value.content) { alert('본문을 입력하세요'); return }
  if (form.value.type === 'image' && !pickedFile.value && !form.value.image_url) { alert('이미지를 선택하세요'); return }
  saving.value = true
  try {
    const fd = new FormData()
    Object.entries(form.value).forEach(([k, v]) => {
      if (v === null || v === undefined) return
      if (typeof v === 'boolean') fd.append(k, v ? '1' : '0')
      else fd.append(k, v)
    })
    if (pickedFile.value) fd.append('image', pickedFile.value)
    const url = editId.value ? `/api/admin/popup-banners/${editId.value}` : '/api/admin/popup-banners'
    await axios.post(url, fd, { headers: { 'Content-Type': 'multipart/form-data' } })
    resetForm()
    await load()
  } catch (err) {
    alert('저장 실패: ' + (err.response?.data?.message || err.message))
  } finally {
    saving.value = false
  }
}

function editBanner(b) {
  editId.value = b.id
  form.value = {
    ...DEFAULT_FORM,
    ...b,
    start_at: b.start_at ? b.start_at.substring(0, 16) : '',
    end_at: b.end_at ? b.end_at.substring(0, 16) : '',
  }
  pickedFile.value = null
  showForm.value = true
}

function resetForm() {
  editId.value = null
  form.value = { ...DEFAULT_FORM }
  pickedFile.value = null
  showForm.value = false
}

async function toggleActive(b) {
  try {
    const fd = new FormData()
    fd.append('title', b.title)
    fd.append('type', b.type)
    fd.append('display_mode', b.display_mode)
    fd.append('is_active', b.is_active ? '0' : '1')
    await axios.post(`/api/admin/popup-banners/${b.id}`, fd)
    b.is_active = !b.is_active
  } catch {}
}

async function deleteBanner(b) {
  if (!confirm(`'${b.title}' 삭제하시겠습니까?`)) return
  try {
    await axios.delete(`/api/admin/popup-banners/${b.id}`)
    banners.value = banners.value.filter(x => x.id !== b.id)
  } catch {}
}

onMounted(load)
</script>
<style>
/* 휴대폰 관리자: 입력창 글자가 16px 보다 작으면 iOS 가 화면을 확대해 버림 */
.alv-m input, .alv-m textarea, .alv-m select { font-size: 16px; }
</style>
