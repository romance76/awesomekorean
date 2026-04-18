<template>
  <!-- /admin/v2/site/pages (Phase 2-C Post: About/Terms/Privacy 편집) -->
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <h2 class="text-xl font-bold">📄 정적 페이지</h2>
    </div>

    <!-- 탭 -->
    <div class="flex gap-1 border-b">
      <button v-for="p in pages" :key="p.id" @click="selectPage(p)"
        :class="['px-4 py-2 text-sm font-medium border-b-2', selected?.id === p.id ? 'border-amber-400 text-amber-600' : 'border-transparent text-gray-500']">
        {{ p.title }} <span class="text-xs text-gray-400">v{{ p.version }}</span>
      </button>
    </div>

    <div v-if="loading" class="text-sm text-gray-400 p-6 text-center">로딩 중...</div>

    <div v-else-if="selected" class="bg-white rounded-xl shadow-sm p-5 space-y-3">
      <div class="grid grid-cols-2 gap-3">
        <label class="block">
          <span class="text-xs text-gray-500">Slug (URL)</span>
          <input :value="selected.slug" disabled class="w-full border rounded px-3 py-2 mt-1 text-sm bg-gray-50 font-mono" />
        </label>
        <label class="block">
          <span class="text-xs text-gray-500">현재 버전</span>
          <input :value="'v' + selected.version" disabled class="w-full border rounded px-3 py-2 mt-1 text-sm bg-gray-50 font-mono" />
        </label>
      </div>

      <label class="block">
        <span class="text-xs text-gray-500">제목</span>
        <input v-model="form.title" class="w-full border rounded px-3 py-2 mt-1 text-sm" />
      </label>

      <label class="block">
        <span class="text-xs text-gray-500">본문 (WYSIWYG)</span>
        <RichEditor v-model="form.content" :height="400" />
      </label>

      <div class="grid grid-cols-2 gap-3">
        <label class="block">
          <span class="text-xs text-gray-500">Meta Description (SEO)</span>
          <input v-model="form.meta_description" class="w-full border rounded px-3 py-2 mt-1 text-sm" />
        </label>
        <label class="block">
          <span class="text-xs text-gray-500">Meta Keywords</span>
          <input v-model="form.meta_keywords" class="w-full border rounded px-3 py-2 mt-1 text-sm" />
        </label>
      </div>

      <label class="block">
        <span class="text-xs text-gray-500">변경 메모 (이력에 저장)</span>
        <input v-model="form.change_note" placeholder="예: 개인정보 수집항목 추가" class="w-full border rounded px-3 py-2 mt-1 text-sm" />
      </label>

      <div class="flex items-center justify-between pt-3 border-t">
        <label class="flex items-center gap-2 text-sm">
          <input type="checkbox" v-model="form.published" />
          공개
        </label>
        <div class="flex gap-2">
          <button @click="loadVersions" class="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 rounded text-sm">📜 버전 이력</button>
          <button @click="save" :disabled="saving" class="px-4 py-2 bg-amber-400 hover:bg-amber-500 text-white rounded text-sm font-semibold disabled:opacity-50">
            {{ saving ? '저장 중...' : '💾 저장 (새 버전 생성)' }}
          </button>
        </div>
      </div>
    </div>

    <!-- 버전 이력 모달 -->
    <div v-if="showVersions" @click.self="showVersions = false" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl max-w-3xl w-full p-5 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-4">
          <h3 class="font-bold">📜 {{ selected?.slug }} 버전 이력</h3>
          <button @click="showVersions = false" class="text-xl text-gray-400 hover:text-gray-600">×</button>
        </div>
        <div v-if="!versions.length" class="text-sm text-gray-400 text-center p-6">이력 없음</div>
        <div v-else class="space-y-3">
          <details v-for="v in versions" :key="v.id" class="border rounded-lg">
            <summary class="p-3 cursor-pointer hover:bg-gray-50 flex items-center justify-between">
              <span class="font-semibold">v{{ v.version }}</span>
              <span class="text-xs text-gray-500">{{ fmtDate(v.created_at) }}</span>
            </summary>
            <div class="p-3 border-t">
              <p v-if="v.change_note" class="text-xs text-gray-500 mb-2">📝 {{ v.change_note }}</p>
              <div class="prose prose-sm max-w-none" v-html="v.content"></div>
            </div>
          </details>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import axios from 'axios'
import RichEditor from '../../../components/admin/RichEditor.vue'
import { useSiteStore } from '../../../stores/site'

const site = useSiteStore()
const pages = ref([])
const selected = ref(null)
const loading = ref(true)
const saving = ref(false)
const versions = ref([])
const showVersions = ref(false)

const form = reactive({
  title: '', content: '', meta_description: '', meta_keywords: '',
  published: true, change_note: '',
})

const fmtDate = (s) => s ? new Date(s).toLocaleString('ko-KR') : ''

async function load() {
  loading.value = true
  try {
    const { data } = await axios.get('/api/admin/site-content/static-pages')
    pages.value = data.data || []
    if (pages.value.length && !selected.value) {
      selectPage(pages.value[0])
    }
  } finally { loading.value = false }
}

function selectPage(p) {
  selected.value = p
  Object.assign(form, {
    title: p.title,
    content: p.content,
    meta_description: p.meta_description || '',
    meta_keywords: p.meta_keywords || '',
    published: !!p.published,
    change_note: '',
  })
}

async function save() {
  if (!selected.value) return
  saving.value = true
  try {
    await axios.put(`/api/admin/site-content/static-pages/${selected.value.slug}`, form)
    await load()
    site.toast('저장되었습니다 (새 버전 생성)', 'success')
  } catch (e) {
    site.toast(e.response?.data?.message || '저장 실패', 'error')
  } finally { saving.value = false }
}

async function loadVersions() {
  if (!selected.value) return
  try {
    const { data } = await axios.get(`/api/admin/site-content/static-pages/${selected.value.slug}/versions`)
    versions.value = data.data || []
    showVersions.value = true
  } catch { site.toast('이력 로드 실패', 'error') }
}

onMounted(load)
</script>
