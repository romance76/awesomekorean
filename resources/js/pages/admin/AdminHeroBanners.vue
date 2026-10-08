<template>
<!-- ───────── 휴대폰 화면 ───────── -->
<div v-if="isMobile" class="alv-m space-y-3 pb-4">
  <button @click="openForm()" class="w-full min-h-[52px] rounded-2xl bg-amber-500 text-white text-[16px] font-bold">+ 새 배너</button>
  <div class="space-y-2">
    <div v-for="b in banners" :key="b.id" class="bg-white border border-gray-100 rounded-2xl p-3 flex items-center gap-3">
      <button @click="editBanner(b)" class="flex items-center gap-3 min-w-0 flex-1 text-left min-h-[64px]">
        <span class="shrink-0 w-14 h-14 rounded-xl overflow-hidden flex items-center justify-center" :style="{ backgroundColor: b.bg_color || '#F5A623' }"><img v-if="b.image_url" :src="b.image_url" alt="" loading="lazy" class="w-full h-full object-cover" /><span v-else class="text-white text-[13px] font-bold">{{ b.sort_order }}</span></span>
        <span class="min-w-0"><span class="block text-[15px] font-bold text-ink truncate">{{ b.title }}</span><span class="block text-[12px] text-ink-muted truncate">{{ b.image_url ? '이미지' : '텍스트' }}<template v-if="b.image_url_en"> · EN 이미지</template><template v-if="b.image_only"> · 이미지 전용</template><template v-if="b.link_type && b.link_type !== 'none'"> · {{ b.link_type }}</template></span></span>
      </button>
      <button type="button" role="switch" :aria-checked="!!b.is_active" :aria-label="b.title + ' 활성'" @click="toggleActive(b)" class="relative shrink-0 w-[54px] h-[32px] rounded-full transition-colors" :class="b.is_active ? 'bg-emerald-500' : 'bg-gray-300'"><span class="absolute top-[4px] w-6 h-6 bg-white rounded-full shadow transition-all" :class="b.is_active ? 'left-[26px]' : 'left-[4px]'"></span></button>
    </div>
    <div v-if="!banners.length" class="text-center text-ink-muted py-12 text-[15px]">배너가 없어요</div>
  </div>

  <Teleport to="body">
    <div v-if="showForm" class="alv-m fixed inset-0 z-[70] bg-black/45 flex items-end" @click.self="resetForm">
      <div class="w-full bg-white rounded-t-3xl px-4 pt-2 max-h-[92vh] overflow-y-auto" role="dialog" aria-modal="true" :style="{ paddingBottom: 'calc(16px + env(safe-area-inset-bottom, 0px))' }">
        <div class="w-10 h-1 rounded bg-gray-200 mx-auto mb-3"></div>
        <div class="text-[17px] font-bold text-ink mb-3">{{ editId ? '히어로 배너 수정' : '새 히어로 배너' }}</div>
        <div class="space-y-3">
        <input v-model="form.title" placeholder="제목 (예: 🐛 버그를 잡아라!)" aria-label="제목" class="w-full min-h-[50px] rounded-xl border border-gray-200 px-3" />
        <input v-model="form.subtitle" placeholder="소제목 (선택)" aria-label="소제목" class="w-full min-h-[48px] rounded-xl border border-gray-200 px-3" />
        <div class="space-y-1"><div class="text-[13px] font-bold text-ink-muted">배경 이미지 (한글 / 기본)</div>
          <input type="file" accept="image/*" @change="onFile" class="block w-full text-[14px]" />
          <div v-if="pickedFile" class="text-[12px] text-green-600">선택됨: {{ pickedFile.name }}</div>
          <div v-else-if="form.image_url" class="flex items-center gap-3"><img :src="form.image_url" alt="" class="max-h-20 rounded-lg border border-gray-200" /><button type="button" @click="clearImage" class="min-h-[44px] px-3 text-[13px] font-bold text-red-500">이미지 제거</button></div></div>
        <div class="space-y-1"><div class="text-[13px] font-bold text-ink-muted">배경 이미지 (영어, 선택)</div>
          <input type="file" accept="image/*" @change="onFileEn" class="block w-full text-[14px]" />
          <div v-if="pickedFileEn" class="text-[12px] text-green-600">선택됨: {{ pickedFileEn.name }}</div>
          <div v-else-if="form.image_url_en" class="flex items-center gap-3"><img :src="form.image_url_en" alt="" class="max-h-20 rounded-lg border border-gray-200" /><button type="button" @click="clearImageEn" class="min-h-[44px] px-3 text-[13px] font-bold text-red-500">이미지 제거</button></div></div>
        <label class="flex items-center gap-3 min-h-[48px] text-[15px] text-ink"><input v-model="form.image_only" type="checkbox" class="w-6 h-6 accent-amber-500" /><span>이미지 전용 <span class="text-[12px] text-ink-muted">(글자·버튼 끄기)</span></span></label>
        <div class="grid grid-cols-2 gap-2">
          <label class="block"><span class="block text-[12px] text-ink-muted mb-1">배경색 (이미지 없을 때)</span><input v-model="form.bg_color" placeholder="#F5A623" class="w-full min-h-[48px] rounded-xl border border-gray-200 px-3" /></label>
          <label class="block"><span class="block text-[12px] text-ink-muted mb-1">글자색</span><input v-model="form.text_color" placeholder="#FFFFFF" class="w-full min-h-[48px] rounded-xl border border-gray-200 px-3" /></label>
        </div>
        <label class="block"><span class="block text-[12px] text-ink-muted mb-1">클릭 링크 타입</span>
          <select v-model="form.link_type" class="w-full min-h-[48px] rounded-xl border border-gray-200 bg-white px-3"><option value="none">클릭 없음</option><option value="event">이벤트 연결</option><option value="page">페이지 이동</option><option value="url">외부 URL</option></select></label>
        <input v-if="form.link_type === 'event'" v-model.number="form.event_id" type="number" inputmode="numeric" placeholder="이벤트 ID" aria-label="이벤트 ID" class="w-full min-h-[48px] rounded-xl border border-gray-200 px-3" />
        <input v-if="form.link_type === 'page'" v-model="form.link_page" placeholder="페이지 경로 (/music, /chat 등)" aria-label="페이지 경로" class="w-full min-h-[48px] rounded-xl border border-gray-200 px-3" />
        <input v-if="form.link_type === 'url'" v-model="form.link_url" placeholder="https://..." aria-label="외부 URL" class="w-full min-h-[48px] rounded-xl border border-gray-200 px-3" />
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
    <span class="icon-chip w-9 h-9 bg-amber-50 text-amber-600"><AppIcon name="image" :size="20" /></span>
    히어로 배너 관리
  </h1>
  <div class="card overflow-hidden mb-4">
    <div class="px-4 py-3 border-b border-gray-50 flex items-center justify-between">
      <span class="font-bold text-sm text-ink">메인 홈 상단 슬라이드 배너</span>
      <button @click="openForm()" class="btn-primary !px-3 !py-1 text-xs"><AppIcon name="plus" :size="13" /> 추가</button>
    </div>

    <!-- 추가/수정 폼 -->
    <div v-if="showForm" class="px-4 py-3 border-b border-gray-50 bg-amber-50 space-y-3">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
        <div>
          <label class="input-label">제목</label>
          <input v-model="form.title" placeholder="예: 🐛 버그를 잡아라!" class="input-soft !px-3 !py-1.5 text-sm" />
        </div>
        <div>
          <label class="input-label">소제목</label>
          <input v-model="form.subtitle" placeholder="부제목 (선택)" class="input-soft !px-3 !py-1.5 text-sm" />
        </div>
      </div>

      <!-- 이미지 업로드 -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
        <div>
          <label class="input-label">배경 이미지 (한글 / 기본)</label>
          <input type="file" accept="image/*" @change="onFile" class="text-xs" />
          <div v-if="pickedFile" class="text-[11px] text-green-600 mt-1">선택됨: {{ pickedFile.name }}</div>
          <div v-else-if="form.image_url" class="mt-1">
            <img :src="form.image_url" class="max-h-20 rounded-lg border border-gray-200" />
            <button type="button" @click="clearImage" class="ml-2 text-[11px] text-red-500 hover:text-red-600 transition-colors">이미지 제거</button>
          </div>
        </div>
        <div>
          <label class="input-label">배경 이미지 (영어, 선택 — 사이트 언어가 영어일 때 대신 표시)</label>
          <input type="file" accept="image/*" @change="onFileEn" class="text-xs" />
          <div v-if="pickedFileEn" class="text-[11px] text-green-600 mt-1">선택됨: {{ pickedFileEn.name }}</div>
          <div v-else-if="form.image_url_en" class="mt-1">
            <img :src="form.image_url_en" class="max-h-20 rounded-lg border border-gray-200" />
            <button type="button" @click="clearImageEn" class="ml-2 text-[11px] text-red-500 hover:text-red-600 transition-colors">이미지 제거</button>
          </div>
        </div>
        <div class="flex items-end">
          <label class="flex items-center gap-2 text-sm text-ink-light">
            <input v-model="form.image_only" type="checkbox" class="accent-amber-500 w-4 h-4" />
            이미지 전용 (텍스트/CTA 오버레이 끄기 — 이미지 자체에 문구가 이미 있을 때)
          </label>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
        <div>
          <label class="input-label">배경색 (이미지 없을 때)</label>
          <input v-model="form.bg_color" placeholder="#F5A623" class="input-soft !px-3 !py-1.5 text-sm" />
        </div>
        <div>
          <label class="input-label">글자색</label>
          <input v-model="form.text_color" placeholder="#FFFFFF" class="input-soft !px-3 !py-1.5 text-sm" />
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
        <div>
          <label class="input-label">클릭 링크 타입</label>
          <select v-model="form.link_type" class="input-soft !px-3 !py-1.5 text-sm">
            <option value="none">클릭 없음</option>
            <option value="event">이벤트 연결</option>
            <option value="page">페이지 이동</option>
            <option value="url">외부 URL</option>
          </select>
        </div>
        <div v-if="form.link_type === 'event'">
          <label class="input-label">이벤트 ID</label>
          <input v-model.number="form.event_id" type="number" class="input-soft !px-3 !py-1.5 text-sm" />
        </div>
        <div v-if="form.link_type === 'page'">
          <label class="input-label">페이지 경로</label>
          <input v-model="form.link_page" placeholder="/music, /chat 등" class="input-soft !px-3 !py-1.5 text-sm" />
        </div>
        <div v-if="form.link_type === 'url'">
          <label class="input-label">외부 URL</label>
          <input v-model="form.link_url" placeholder="https://..." class="input-soft !px-3 !py-1.5 text-sm" />
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

    <!-- 배너 목록 -->
    <div v-for="b in banners" :key="b.id" class="px-4 py-3 border-b border-gray-50 flex items-center gap-3 hover:bg-amber-50/40 transition-colors">
      <div class="w-12 h-12 rounded-lg flex items-center justify-center flex-shrink-0 overflow-hidden"
        :style="{ backgroundColor: b.bg_color || '#F5A623' }">
        <img v-if="b.image_url" :src="b.image_url" class="w-full h-full object-cover" />
        <span v-else class="text-white text-xs font-bold">{{ b.sort_order }}</span>
      </div>
      <div class="flex-1 min-w-0">
        <div class="text-sm font-bold text-ink truncate">{{ b.title }}</div>
        <div class="text-[11px] text-ink-muted truncate">
          <span class="inline-flex items-center gap-0.5"><AppIcon :name="b.image_url ? 'image' : 'edit'" :size="10" />{{ b.image_url ? '이미지' : '텍스트' }}</span>
          <span v-if="b.image_url_en"> · EN 이미지 있음</span>
          <span v-if="b.image_only"> · 이미지 전용</span>
          <span v-if="b.subtitle"> · {{ b.subtitle }}</span>
          <span v-if="b.link_type && b.link_type !== 'none'"> · {{ b.link_type }}{{ b.event_id ? ' #' + b.event_id : '' }}{{ b.link_page || '' }}</span>
        </div>
      </div>
      <label class="flex items-center gap-1 text-xs text-ink-light">
        <input type="checkbox" :checked="b.is_active" @change="toggleActive(b)" class="accent-amber-500" /> 활성
      </label>
      <button @click="editBanner(b)" class="text-xs text-amber-600 hover:text-amber-700 transition-colors">수정</button>
      <button @click="deleteBanner(b)" class="text-xs text-red-400 hover:text-red-600 transition-colors">삭제</button>
    </div>
    <div v-if="!banners.length" class="py-16 text-center">
      <div class="icon-chip w-14 h-14 bg-gray-100 text-gray-300 mx-auto mb-3"><AppIcon name="image" :size="28" :stroke-width="1.5" /></div>
      <p class="text-sm text-ink-muted">배너 없음</p>
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
const pickedFileEn = ref(null)

const DEFAULT_FORM = {
  title: '', subtitle: '',
  image_url: '', image_url_en: '', image_only: false,
  bg_color: '#F5A623', text_color: '#FFFFFF',
  link_type: 'none', event_id: null, link_page: '', link_url: '',
  sort_order: 0, is_active: true,
}

const form = ref({ ...DEFAULT_FORM })

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
  try { const { data } = await axios.get('/api/admin/hero-banners'); banners.value = data.data || [] } catch {}
}

function openForm() {
  resetForm()
  showForm.value = true
}

function onFile(e) {
  pickedFile.value = e.target.files?.[0] || null
}

function clearImage() {
  form.value.image_url = ''
  pickedFile.value = null
}

function onFileEn(e) {
  pickedFileEn.value = e.target.files?.[0] || null
}

function clearImageEn() {
  form.value.image_url_en = ''
  pickedFileEn.value = null
}

async function saveForm() {
  if (!form.value.title) { alert('제목을 입력하세요'); return }
  saving.value = true
  try {
    const fd = new FormData()
    Object.entries(form.value).forEach(([k, v]) => {
      if (v === null || v === undefined) return
      if (typeof v === 'boolean') fd.append(k, v ? '1' : '0')
      else fd.append(k, v)
    })
    if (pickedFile.value) fd.append('image', pickedFile.value)
    if (pickedFileEn.value) fd.append('image_en', pickedFileEn.value)
    const url = editId.value ? `/api/admin/hero-banners/${editId.value}` : '/api/admin/hero-banners'
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
  form.value = { ...DEFAULT_FORM, ...b }
  pickedFile.value = null
  pickedFileEn.value = null
  showForm.value = true
}

function resetForm() {
  editId.value = null
  form.value = { ...DEFAULT_FORM }
  pickedFile.value = null
  pickedFileEn.value = null
  showForm.value = false
}

async function toggleActive(b) {
  try {
    const fd = new FormData()
    fd.append('title', b.title)
    fd.append('is_active', b.is_active ? '0' : '1')
    await axios.post(`/api/admin/hero-banners/${b.id}`, fd)
    b.is_active = !b.is_active
  } catch {}
}

async function deleteBanner(b) {
  if (!confirm(`'${b.title}' 삭제?`)) return
  try {
    await axios.delete(`/api/admin/hero-banners/${b.id}`)
    banners.value = banners.value.filter(x => x.id !== b.id)
  } catch {}
}

onMounted(load)
</script>
<style>
/* 휴대폰 관리자: 입력창 글자가 16px 보다 작으면 iOS 가 화면을 확대해 버림 */
.alv-m input, .alv-m textarea, .alv-m select { font-size: 16px; }
</style>
