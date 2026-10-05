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
              <td colspan="7" class="px-3 py-8 text-center text-ink-faint text-sm">등록된 상품이 없습니다</td>
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
    <div class="bg-white rounded-2xl p-5 w-full max-w-xl max-h-[85vh] overflow-y-auto">
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
        <div>
          <label class="block text-xs text-ink-light mb-1">카테고리</label>
          <select v-model="editing.category" class="w-full border border-line rounded-lg px-3 py-2">
            <option value="">선택 안함</option>
            <option v-for="c in categories" :key="c" :value="c">{{ c }}</option>
          </select>
        </div>
        <div>
          <label class="block text-xs text-ink-light mb-1">상품 이미지 URL (Amazon 이미지 URL을 직접 입력 — 서버에 저장하지 않음)</label>
          <input v-model="editing.image_url" class="w-full border border-line rounded-lg px-3 py-2" />
        </div>
        <div>
          <label class="block text-xs text-ink-light mb-1">Awesome Korean 추천/설명 문구</label>
          <textarea v-model="editing.our_description" rows="2" class="w-full border border-line rounded-lg px-3 py-2"></textarea>
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
        <button @click="save" class="px-4 py-2 rounded-lg bg-orange-500 text-white text-sm font-semibold">저장</button>
      </div>
    </div>
  </div>
</div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import AppIcon from '../../components/AppIcon.vue'

const activeTab = ref('products')
const tabs = [
  { key: 'products', icon: 'shopping-bag', label: '상품' },
  { key: 'cat',       icon: 'tag',         label: '카테고리' },
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
  editing.value = { input: '', title: '', category: '', image_url: '', our_description: '', display_order: 0, is_featured: false, is_active: true }
}

function openEdit(product) {
  isNew.value = false
  editing.value = { ...product }
}

async function save() {
  try {
    if (isNew.value) {
      await axios.post('/api/admin/amazon-products', {
        input: editing.value.input,
        title: editing.value.title,
        category: editing.value.category || null,
        image_url: editing.value.image_url || null,
        our_description: editing.value.our_description || null,
        display_order: editing.value.display_order || 0,
        is_featured: editing.value.is_featured,
        is_active: editing.value.is_active,
      })
    } else {
      await axios.put(`/api/admin/amazon-products/${editing.value.id}`, {
        title: editing.value.title,
        category: editing.value.category || null,
        image_url: editing.value.image_url || null,
        our_description: editing.value.our_description || null,
        display_order: editing.value.display_order || 0,
        is_featured: editing.value.is_featured,
        is_active: editing.value.is_active,
      })
    }
    editing.value = null
    load(page.value)
    loadStats()
  } catch (e) { alert(e.response?.data?.message || '저장 실패') }
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
