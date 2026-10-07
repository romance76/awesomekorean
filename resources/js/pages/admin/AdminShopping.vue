<template>
<div>
  <!-- 헤더 — 다른 게시판 관리 화면(AdminInfo 등)과 동일한 형식 -->
  <div class="mb-4 flex items-start justify-between flex-wrap gap-2">
    <div>
      <div class="text-xs text-ink-muted">관리자 › 게시판 관리 › 쇼핑</div>
      <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink mt-1">
        <span class="icon-chip w-9 h-9 bg-lime-50 text-lime-600 text-lg">🛍️</span>
        쇼핑 관리 (Amazon 제휴)
      </h1>
      <p class="text-xs text-ink-faint mt-0.5">Amazon Associates 제휴 상품을 등록/관리합니다 · 태그: {{ associateTag || 'awesomekorean-20' }}</p>
    </div>
    <button @click="openCreate" class="inline-flex items-center gap-1.5 bg-orange-500 text-white font-semibold px-4 py-2 rounded-xl text-sm hover:bg-orange-600 transition-colors">
      ➕ 상품 등록
    </button>
  </div>

  <!-- 통계 카드 -->
  <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-4">
    <div class="card p-3">
      <div class="text-xs text-ink-muted">전체 상품</div>
      <div class="text-xl font-bold text-ink">{{ stats.total ?? 0 }}</div>
    </div>
    <div class="card p-3">
      <div class="text-xs text-ink-muted">활성 상품</div>
      <div class="text-xl font-bold text-green-600">{{ stats.active ?? 0 }}</div>
    </div>
    <div class="card p-3">
      <div class="text-xs text-ink-muted">추천 상품</div>
      <div class="text-xl font-bold text-blue-600">{{ stats.featured ?? 0 }}</div>
    </div>
    <div class="card p-3">
      <div class="text-xs text-ink-muted">총 클릭</div>
      <div class="text-xl font-bold text-purple-600">{{ stats.clicks_total ?? 0 }}</div>
    </div>
    <div class="card p-3">
      <div class="text-xs text-ink-muted">오늘 클릭</div>
      <div class="text-xl font-bold text-red-600">{{ stats.clicks_today ?? 0 }}</div>
    </div>
  </div>
  <p class="text-[11px] text-ink-faint mb-4">※ 클릭 수는 자체 집계용 참고 데이터이며, 실제 구매/커미션은 Amazon Associates Central에서 확인해야 합니다.</p>

  <!-- 탭 네비 -->
  <div class="bg-white rounded-t-2xl border border-b-0 border-gray-100">
    <div class="flex overflow-x-auto">
      <button v-for="t in tabs" :key="t.key" @click="activeTab = t.key"
        class="flex items-center gap-1.5 px-4 py-3 text-sm whitespace-nowrap border-b-2 transition-colors"
        :class="activeTab === t.key ? 'border-amber-500 text-amber-700 font-bold bg-amber-50' : 'border-transparent text-ink-muted hover:text-ink'">
        <AppIcon :name="t.icon" :size="14" />{{ t.label }}
      </button>
    </div>
  </div>

  <div class="bg-white rounded-b-2xl border border-t-0 border-gray-100 p-4 min-h-[400px]">
    <!-- 🛍 상품 -->
    <div v-if="activeTab === 'products'">
      <div class="flex flex-wrap gap-2 mb-4">
        <select v-model="category" @change="load(1)" class="border border-line rounded-lg px-3 py-1.5 text-sm">
          <option value="">전체 카테고리</option>
          <option v-for="c in categories" :key="c" :value="c">{{ c }}</option>
        </select>
        <input v-model="search" @keyup.enter="load(1)" placeholder="상품명 검색" class="border border-line rounded-lg px-3 py-1.5 text-sm flex-1 min-w-[160px]" />
      </div>

      <div class="overflow-x-auto border border-line rounded-xl">
        <table class="w-full text-sm">
          <thead class="bg-surface text-ink-light text-xs">
            <tr>
              <th class="text-left px-3 py-2">상품</th>
              <th class="text-left px-3 py-2">ASIN</th>
              <th class="text-left px-3 py-2">카테고리</th>
              <th class="text-left px-3 py-2">가격</th>
              <th class="text-left px-3 py-2">순서</th>
              <th class="text-left px-3 py-2">클릭</th>
              <th class="text-left px-3 py-2">상태</th>
              <th class="text-right px-3 py-2">관리</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="product in products" :key="product.id" class="border-t border-line">
              <td class="px-3 py-2 max-w-xs">
                <div class="flex items-center gap-2">
                  <img v-if="product.image_url" :src="product.image_url" class="w-8 h-8 object-cover rounded-lg border border-line" @error="e=>e.target.style.display='none'" />
                  <span class="truncate">{{ product.title }}</span>
                  <span v-if="product.is_featured" class="badge-red !text-[10px]">추천</span>
                </div>
              </td>
              <td class="px-3 py-2 text-xs text-ink-light">{{ product.asin }}</td>
              <td class="px-3 py-2">{{ product.category || '-' }}</td>
              <td class="px-3 py-2">{{ product.price ? '$' + Number(product.price).toLocaleString() : '-' }}</td>
              <td class="px-3 py-2">{{ product.display_order }}</td>
              <td class="px-3 py-2">{{ product.clicks }}</td>
              <td class="px-3 py-2">
                <span :class="product.is_active ? 'text-emerald-600' : 'text-ink-faint'">{{ product.is_active ? '활성' : '비활성' }}</span>
              </td>
              <td class="px-3 py-2 text-right whitespace-nowrap">
                <button @click="openEdit(product)" class="text-blue-600 hover:underline mr-2">수정</button>
                <button @click="toggle(product)" class="text-amber-600 hover:underline mr-2">{{ product.is_active ? '비활성' : '활성' }}</button>
                <button @click="remove(product)" class="text-red-600 hover:underline">삭제</button>
              </td>
            </tr>
            <tr v-if="!products.length">
              <td colspan="8" class="px-3 py-8 text-center text-ink-faint text-sm">등록된 상품이 없습니다</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="flex justify-center gap-2 mt-4" v-if="lastPage > 1">
        <button v-for="p in lastPage" :key="p" @click="load(p)"
          class="w-8 h-8 rounded-lg text-sm" :class="p === page ? 'bg-orange-500 text-white' : 'bg-surface text-ink-light'">{{ p }}</button>
      </div>
    </div>

    <!-- 📂 카테고리 — 고정 분류, 실제 등록 수만 참고용으로 표시 -->
    <div v-else-if="activeTab === 'guide'">
      <AdminAssociatesGuide />
    </div>

    <div v-else-if="activeTab === 'member'">
      <AdminShoppingReviews />
    </div>

    <div v-else-if="activeTab === 'cat'">
      <div class="text-sm text-ink-light mb-3">쇼핑 카테고리는 {{ categories.length }}개로 고정돼 있습니다 (상품 등록 시 하나를 배정). 실제 상품 수 기준 집계입니다.</div>
      <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-2">
        <div v-for="c in categoryStats" :key="c.name" class="flex items-center justify-between border border-line rounded-xl px-3 py-2.5">
          <span class="flex items-center gap-1.5 text-sm text-ink"><span>🏷</span>{{ c.name }}</span>
          <span class="badge-gray !text-xs">{{ c.count }}개</span>
        </div>
      </div>
    </div>
  </div>

  <!-- 등록/수정 모달 -->
  <div v-if="editing" class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4" @click.self="editing=null">
    <div class="bg-white rounded-2xl p-5 w-full max-w-2xl max-h-[90vh] overflow-y-auto">
      <h2 class="font-bold mb-3">{{ isNew ? '상품 등록' : '상품 수정' }}</h2>
      <div class="space-y-3 text-sm">
        <div v-if="isNew">
          <label class="block text-xs text-ink-light mb-1">ASIN 또는 Amazon 상품 URL</label>
          <input v-model="editing.input" placeholder="예: B0ABCDEFGH 또는 https://www.amazon.com/dp/B0ABCDEFGH" class="w-full border border-line rounded-lg px-3 py-2" />
          <p class="text-[11px] text-ink-faint mt-1">상품명/이미지/가격은 Amazon에서 자동으로 가져오지 않습니다(제휴 정책상 scraping 금지) — 아래에 직접 입력해주세요.</p>
        </div>
        <div>
          <label class="block text-xs text-ink-light mb-1">상품명</label>
          <input v-model="editing.title" class="w-full border border-line rounded-lg px-3 py-2" />
        </div>
        <div class="flex gap-3">
          <div class="flex-1">
            <label class="block text-xs text-ink-light mb-1">카테고리</label>
            <select v-model="editing.category" class="w-full border border-line rounded-lg px-3 py-2">
              <option value="">선택 안함</option>
              <option v-for="c in categories" :key="c" :value="c">{{ c }}</option>
            </select>
          </div>
          <div class="flex-1">
            <label class="block text-xs text-ink-light mb-1">가격 ($, 참고용 — 실시간 Amazon 가격 아님)</label>
            <input v-model.number="editing.price" type="number" step="0.01" min="0" placeholder="예: 15.99" class="w-full border border-line rounded-lg px-3 py-2" />
          </div>
        </div>

        <!-- Amazon 이미지 URL (여러 장) -->
        <div>
          <label class="block text-xs text-ink-light mb-1">Amazon 상품 이미지 URL (우클릭 → "이미지 주소 복사" 후 붙여넣기 — 서버에 저장하지 않음, 여러 장 가능)</label>
          <div v-for="(url, i) in editing.amazon_image_urls" :key="i" class="flex items-center gap-2 mb-1.5">
            <div class="w-10 h-10 flex-shrink-0 border border-line rounded-lg overflow-hidden bg-gray-50">
              <img v-if="url" :src="url" class="w-full h-full object-cover" @error="e=>e.target.style.opacity=0.2" @load="e=>e.target.style.opacity=1" />
            </div>
            <input v-model="editing.amazon_image_urls[i]" class="flex-1 border border-line rounded-lg px-3 py-2" />
            <button type="button" @click="editing.amazon_image_urls.splice(i,1)" class="text-red-500 text-xs px-2">삭제</button>
          </div>
          <button type="button" @click="editing.amazon_image_urls.push('')" class="text-xs text-amber-600 hover:underline">+ Amazon 이미지 추가</button>
        </div>

        <!-- 직접 업로드 이미지 (여러 장) -->
        <div>
          <label class="block text-xs text-ink-light mb-1">직접 촬영/제작한 이미지 (서버에 저장됨 — Amazon 이미지가 아닌 것만, 여러 장 가능)</label>
          <div class="flex flex-wrap gap-2">
            <div v-for="(url, i) in editing.keep_own_image_urls" :key="'k'+i" class="relative w-16 h-16 rounded-lg overflow-hidden border border-line group">
              <img :src="url" class="w-full h-full object-cover" />
              <button type="button" @click="editing.keep_own_image_urls.splice(i,1)"
                class="absolute inset-0 bg-black/40 text-white text-xs opacity-0 group-hover:opacity-100 flex items-center justify-center transition">삭제</button>
            </div>
            <div v-for="(f, i) in newOwnImages" :key="'n'+i" class="relative w-16 h-16 rounded-lg overflow-hidden border border-line group">
              <img :src="f.preview" class="w-full h-full object-cover" />
              <button type="button" @click="newOwnImages.splice(i,1)"
                class="absolute inset-0 bg-black/40 text-white text-xs opacity-0 group-hover:opacity-100 flex items-center justify-center transition">삭제</button>
            </div>
            <label v-if="(editing.keep_own_image_urls.length + newOwnImages.length) < 10"
              class="w-16 h-16 rounded-lg bg-surface text-ink-muted flex flex-col items-center justify-center cursor-pointer hover:bg-amber-50 transition-colors text-xs">
              <AppIcon name="camera" :size="18" :stroke-width="1.5" />
              <input type="file" multiple accept="image/*" @change="onSelectOwnImages" class="hidden" />
            </label>
          </div>
        </div>

        <!-- 추천/설명 문구 — 서식 편집 에디터 -->
        <div>
          <label class="block text-xs text-ink-light mb-1">Awesome Korean 추천/설명 문구</label>
          <div class="flex flex-wrap items-center gap-1 mb-1.5 p-1.5 bg-surface rounded-lg">
            <button type="button" @click="execCmd('bold')" title="굵게" class="w-7 h-7 rounded-md hover:bg-gray-200 font-bold text-ink-light text-xs">B</button>
            <button type="button" @click="execCmd('italic')" title="기울임" class="w-7 h-7 rounded-md hover:bg-gray-200 italic text-ink-light text-xs">I</button>
            <button type="button" @click="execCmd('underline')" title="밑줄" class="w-7 h-7 rounded-md hover:bg-gray-200 underline text-ink-light text-xs">U</button>
            <span class="w-px h-4 bg-gray-300 mx-1"></span>
            <button type="button" @click="execCmd('formatBlock', 'H2')" title="제목 H2" class="px-2 h-7 rounded-md hover:bg-gray-200 text-[11px] font-bold text-ink-light">H2</button>
            <button type="button" @click="execCmd('formatBlock', 'H3')" title="제목 H3" class="px-2 h-7 rounded-md hover:bg-gray-200 text-[11px] font-bold text-ink-light">H3</button>
            <button type="button" @click="execCmd('formatBlock', 'P')" title="단락" class="px-2 h-7 rounded-md hover:bg-gray-200 text-[11px] text-ink-light">P</button>
            <span class="w-px h-4 bg-gray-300 mx-1"></span>
            <button type="button" @click="execCmd('insertUnorderedList')" title="글머리 기호" class="w-7 h-7 rounded-md hover:bg-gray-200 text-ink-light text-xs">•</button>
            <button type="button" @click="execCmd('insertOrderedList')" title="번호 목록" class="w-7 h-7 rounded-md hover:bg-gray-200 text-[11px] text-ink-light">1.</button>
            <span class="w-px h-4 bg-gray-300 mx-1"></span>
            <button type="button" @click="descImageInputRef?.click()" title="이미지 삽입" class="w-7 h-7 rounded-md hover:bg-gray-200 text-ink-light inline-flex items-center justify-center"><AppIcon name="image" :size="14" /></button>
            <input ref="descImageInputRef" type="file" accept="image/*" @change="onInsertDescImage" class="hidden" />
            <button type="button" @click="execCmd('removeFormat')" title="서식 지우기" class="ml-auto px-2 h-7 rounded-md hover:bg-gray-200 text-[11px] text-ink-muted">지우기</button>
          </div>
          <div ref="editorRef" contenteditable="true" @input="syncDescription"
            class="input-soft min-h-[140px] py-2.5 leading-relaxed prose prose-sm max-w-none border border-line rounded-lg px-3"
            data-placeholder="이 상품을 추천하는 이유, 사용 후기 등을 자유롭게 작성해주세요"></div>
        </div>

        <div class="flex gap-3">
          <div class="flex-1">
            <label class="block text-xs text-ink-light mb-1">표시 순서</label>
            <input v-model.number="editing.display_order" type="number" class="w-full border border-line rounded-lg px-3 py-2" />
          </div>
          <label class="flex items-center gap-1.5 text-xs mt-6"><input type="checkbox" v-model="editing.is_featured" /> 추천 상품(Featured)</label>
          <label class="flex items-center gap-1.5 text-xs mt-6"><input type="checkbox" v-model="editing.is_active" /> 활성</label>
        </div>
      </div>
      <div class="flex justify-end gap-2 mt-4">
        <button @click="editing=null" class="px-4 py-2 rounded-lg bg-surface text-sm">취소</button>
        <button @click="save" :disabled="saving" class="px-4 py-2 rounded-lg bg-orange-500 text-white text-sm font-semibold disabled:opacity-50">{{ saving ? '저장 중...' : '저장' }}</button>
      </div>
    </div>
  </div>
</div>
</template>

<script setup>
import { ref, nextTick, onMounted } from 'vue'
import axios from 'axios'
import AppIcon from '../../components/AppIcon.vue'
import AdminShoppingReviews from './AdminShoppingReviews.vue'
import AdminAssociatesGuide from './AdminAssociatesGuide.vue'

const activeTab = ref('products')
const tabs = [
  { key: 'products', icon: 'shopping-bag', label: '상품' },
  { key: 'cat',       icon: 'tag',         label: '카테고리' },
  { key: 'member',    icon: 'users',       label: '회원 리뷰' },
  { key: 'guide',     icon: 'book-open',   label: '가입 안내' },
]

const categories = [
  '인기상품', '오늘의 딜', '주방용품', '한국요리 관련 제품', '식품', '생활용품',
  '가전/전자제품', '육아/교육', 'Beauty/K-Beauty', 'Home', '계절상품', '선물', 'Awesome Korean 추천',
]

const stats = ref({})
const categoryStats = ref([])
const associateTag = ref('')

const products = ref([])
const page = ref(1)
const lastPage = ref(1)
const category = ref('')
const search = ref('')
const editing = ref(null)
const isNew = ref(false)
const saving = ref(false)
const newOwnImages = ref([]) // [{file, preview}]
const editorRef = ref(null)
const descImageInputRef = ref(null)

async function loadStats() {
  try {
    const { data } = await axios.get('/api/admin/amazon-products/stats')
    stats.value = data.data
  } catch (e) { console.warn('stats load failed', e) }
}

function buildCategoryStats() {
  const counts = {}
  categories.forEach(c => counts[c] = 0)
  products.value.forEach(p => { if (p.category && counts[p.category] !== undefined) counts[p.category]++ })
  categoryStats.value = categories.map(c => ({ name: c, count: counts[c] }))
}

async function load(p = 1) {
  page.value = p
  const { data } = await axios.get('/api/admin/amazon-products', { params: { page: p, category: category.value || undefined, search: search.value || undefined } })
  products.value = data.data.data
  lastPage.value = data.data.last_page
  buildCategoryStats()
}

function openCreate() {
  isNew.value = true
  newOwnImages.value = []
  editing.value = {
    input: '', title: '', category: '', price: null,
    amazon_image_urls: [''], keep_own_image_urls: [],
    our_description: '', display_order: 0, is_featured: false, is_active: true,
  }
  nextTick(() => { if (editorRef.value) editorRef.value.innerHTML = '' })
}

function openEdit(product) {
  isNew.value = false
  newOwnImages.value = []
  editing.value = {
    ...product,
    price: product.price ?? null,
    amazon_image_urls: product.amazon_image_urls?.length ? [...product.amazon_image_urls] : [''],
    keep_own_image_urls: product.own_image_urls ? [...product.own_image_urls] : [],
  }
  nextTick(() => { if (editorRef.value) editorRef.value.innerHTML = product.our_description || '' })
}

function execCmd(cmd, value = null) {
  editorRef.value?.focus()
  document.execCommand(cmd, false, value)
  syncDescription()
}
function syncDescription() {
  if (editorRef.value) editing.value.our_description = editorRef.value.innerHTML
}
function onInsertDescImage(e) {
  const file = e.target.files?.[0]
  if (!file) return
  if (file.size > 5 * 1024 * 1024) { alert('이미지는 5MB 이하여야 합니다.'); e.target.value = ''; return }
  const reader = new FileReader()
  reader.onload = () => { execCmd('insertImage', reader.result); e.target.value = '' }
  reader.readAsDataURL(file)
}

function onSelectOwnImages(e) {
  const files = Array.from(e.target.files || [])
  for (const file of files) {
    if ((editing.value.keep_own_image_urls.length + newOwnImages.value.length) >= 10) break
    newOwnImages.value.push({ file, preview: URL.createObjectURL(file) })
  }
  e.target.value = ''
}

async function save() {
  saving.value = true
  try {
    const fd = new FormData()
    fd.append('title', editing.value.title)
    fd.append('category', editing.value.category || '')
    if (editing.value.price !== null && editing.value.price !== '') fd.append('price', editing.value.price)
    fd.append('our_description', editing.value.our_description || '')
    fd.append('display_order', editing.value.display_order || 0)
    fd.append('is_featured', editing.value.is_featured ? 'true' : 'false')
    fd.append('is_active', editing.value.is_active ? 'true' : 'false')
    editing.value.amazon_image_urls.filter(u => u).forEach(u => fd.append('amazon_image_urls[]', u))
    newOwnImages.value.forEach(f => fd.append('own_images[]', f.file))

    if (isNew.value) {
      fd.append('input', editing.value.input)
      await axios.post('/api/admin/amazon-products', fd, { headers: { 'Content-Type': 'multipart/form-data' } })
    } else {
      fd.append('_method', 'PUT')
      editing.value.keep_own_image_urls.forEach(u => fd.append('keep_own_image_urls[]', u))
      await axios.post(`/api/admin/amazon-products/${editing.value.id}`, fd, { headers: { 'Content-Type': 'multipart/form-data' } })
    }
    editing.value = null
    load(page.value)
    loadStats()
  } catch (e) { alert(e.response?.data?.message || '저장 실패') }
  saving.value = false
}

async function toggle(product) {
  await axios.put(`/api/admin/amazon-products/${product.id}`, { is_active: !product.is_active })
  load(page.value)
  loadStats()
}

async function remove(product) {
  if (!confirm(`"${product.title}" 상품을 삭제할까요?`)) return
  await axios.delete(`/api/admin/amazon-products/${product.id}`)
  load(page.value)
  loadStats()
}

onMounted(() => {
  load(1)
  loadStats()
})
</script>

<style scoped>
[contenteditable="true"]:empty::before {
  content: attr(data-placeholder);
  color: #9ca3af;
  pointer-events: none;
}
[contenteditable="true"] img {
  max-width: 100%;
  height: auto;
  border-radius: 6px;
  margin: 8px 0;
}
[contenteditable="true"] h2 { font-size: 1.15rem; font-weight: 700; margin: 0.5rem 0; }
[contenteditable="true"] h3 { font-size: 1.05rem; font-weight: 600; margin: 0.5rem 0; }
[contenteditable="true"] ul { list-style: disc; padding-left: 1.5rem; }
[contenteditable="true"] ol { list-style: decimal; padding-left: 1.5rem; }
</style>
