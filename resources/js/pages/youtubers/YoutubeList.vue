<template>
<div class="min-h-screen">
  <div class="max-w-7xl mx-auto px-4 py-5">
    <div class="flex items-center justify-between gap-3 mb-4">
      <GroupTitle menu-key="youtubers">
        <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink">
          <span class="icon-chip w-9 h-9 bg-red-50 text-red-600"><AppIcon name="video" :size="20" /></span>
          한인 유튜버
        </h1>
      </GroupTitle>
      <RouterLink to="/youtubers/apply" class="btn-primary shrink-0 inline-flex items-center gap-1.5 px-4 py-2 text-sm"><AppIcon name="plus" :size="14" />내 채널 등록</RouterLink>
    </div>

    <!-- 분야 -->
    <div class="flex gap-2 overflow-x-auto pb-1 mb-3" style="scrollbar-width:none">
      <button @click="setCategory('')" class="shrink-0 px-3.5 py-1.5 rounded-full text-[13px] border transition-colors"
        :class="!category ? 'bg-amber-500 text-white border-amber-500 font-bold' : 'bg-white text-ink-light border-gray-200'">전체</button>
      <button v-for="c in categories" :key="c.key" @click="setCategory(c.key)" class="shrink-0 px-3.5 py-1.5 rounded-full text-[13px] border transition-colors"
        :class="category === c.key ? 'bg-amber-500 text-white border-amber-500 font-bold' : 'bg-white text-ink-light border-gray-200'">{{ c.label }}</button>
    </div>

    <!-- 검색·정렬 -->
    <div class="flex items-center gap-2 mb-5">
      <div class="relative flex-1 max-w-sm">
        <input v-model="search" @keyup.enter="reload" type="text" placeholder="채널 이름 검색" class="input-soft w-full !pl-9" />
        <AppIcon name="search" :size="15" class="absolute left-3 top-1/2 -translate-y-1/2 text-ink-faint" />
      </div>
      <select v-model="sort" @change="reload" class="input-soft !w-auto">
        <option value="latest">최근 업로드순</option>
        <option value="subs">구독자순</option>
        <option value="new">새로 등록순</option>
      </select>
    </div>

    <!-- 추천 유튜버 -->
    <section v-if="featured.length" class="mb-6">
      <h2 class="text-sm font-extrabold text-amber-700 mb-2.5 flex items-center gap-1.5"><AppIcon name="star" :size="14" />추천 유튜버</h2>
      <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
        <RouterLink v-for="c in featured" :key="'f' + c.id" :to="`/youtubers/${c.id}`" class="card card-hover p-3 flex flex-col items-center text-center ring-1 ring-amber-200">
          <img v-if="c.thumbnail_url" :src="c.thumbnail_url" alt="" loading="lazy" class="w-16 h-16 rounded-full object-cover bg-surface" @error="e => e.target.style.display = 'none'" />
          <div class="mt-2 text-[13.5px] font-bold text-ink line-clamp-1 w-full">{{ c.title }}</div>
          <div class="text-[11.5px] text-ink-muted">{{ c.category_label }}</div>
        </RouterLink>
      </div>
    </section>

    <div v-if="loading && !items.length" class="py-16 text-center text-ink-muted text-sm">불러오는 중...</div>
    <div v-else-if="!items.length" class="py-16 text-center text-ink-muted text-sm">
      아직 등록된 채널이 없어요.<br />
      <RouterLink to="/youtubers/apply" class="inline-block mt-3 text-amber-700 font-bold hover:underline">첫 번째로 내 채널을 등록해 보세요 →</RouterLink>
    </div>

    <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
      <RouterLink v-for="c in items" :key="c.id" :to="`/youtubers/${c.id}`" class="card card-hover overflow-hidden flex flex-col">
        <div class="aspect-video bg-surface relative">
          <img v-if="c.latest_thumb" :src="c.latest_thumb" alt="" loading="lazy" class="w-full h-full object-cover" @error="e => e.target.style.display = 'none'" />
          <span class="absolute left-2 top-2 text-[11px] font-bold bg-black/60 text-white px-2 py-0.5 rounded-full">{{ c.category_label }}</span>
        </div>
        <div class="p-3.5 flex items-center gap-3">
          <img v-if="c.thumbnail_url" :src="c.thumbnail_url" alt="" loading="lazy" class="w-11 h-11 rounded-full object-cover bg-surface shrink-0" @error="e => e.target.style.display = 'none'" />
          <div class="min-w-0">
            <div class="text-[14.5px] font-bold text-ink truncate">{{ c.title }}<span v-if="c.verified" class="ml-1 inline-block align-middle text-[10.5px] font-bold text-green-700 bg-green-50 px-1.5 py-0.5 rounded-full" title="채널 주인이 직접 인증했어요">✔ 인증</span></div>
            <div class="text-[12px] text-ink-muted">구독자 {{ fmtCount(c.subscriber_count) }}</div>
          </div>
        </div>
        <p v-if="c.intro" class="px-3.5 pb-3.5 -mt-1 text-[12.5px] text-ink-light line-clamp-2">{{ c.intro }}</p>
      </RouterLink>
    </div>

    <div v-if="page < lastPage" class="text-center mt-6">
      <button @click="loadMore" :disabled="loading" class="btn-secondary px-6 py-2 text-sm">{{ loading ? '불러오는 중...' : '더 보기' }}</button>
    </div>
  </div>
</div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import GroupTitle from '../../components/GroupTitle.vue'
import AppIcon from '../../components/AppIcon.vue'

const items = ref([])
const featured = ref([])
const categories = ref([])
const category = ref('')
const search = ref('')
const sort = ref('latest')
const page = ref(1)
const lastPage = ref(1)
const loading = ref(false)

function fmtCount(n) {
  n = Number(n || 0)
  if (n >= 10000) return (n / 10000).toFixed(n >= 100000 ? 0 : 1).replace(/\.0$/, '') + '만'
  if (n >= 1000) return (n / 1000).toFixed(1).replace(/\.0$/, '') + '천'
  return String(n)
}

async function load(p) {
  loading.value = true
  try {
    const { data } = await axios.get('/api/youtubers', { params: { page: p, category: category.value || undefined, q: search.value || undefined, sort: sort.value } })
    const d = data.data || {}
    items.value = p === 1 ? (d.items || []) : items.value.concat(d.items || [])
    if (p === 1) featured.value = d.featured || []
    if (d.categories?.length) categories.value = d.categories
    page.value = d.meta?.current_page || p
    lastPage.value = d.meta?.last_page || 1
  } catch { /* 목록이 안 떠도 화면은 유지 */ } finally { loading.value = false }
}
function reload() { load(1) }
function loadMore() { load(page.value + 1) }
function setCategory(k) { category.value = k; reload() }

onMounted(reload)
</script>
