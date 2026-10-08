<template>
<!-- ───────── 휴대폰 화면 ───────── -->
<div v-if="isMobile" class="alv-m space-y-3 pb-4">
  <!-- 자동 생성 -->
  <div class="bg-white border border-gray-100 rounded-2xl p-3.5 space-y-2">
    <div class="flex items-center gap-3">
      <div class="min-w-0 flex-1"><div class="text-[16px] font-bold text-ink">정보 글 자동 생성</div><div class="text-[13px] text-ink-muted">글 10개를 자동으로 만들어요 (수 분~1시간)</div></div>
    </div>
    <div v-if="genStatus && genStatus.status === 'running'" class="bg-orange-50 border border-orange-200 rounded-xl p-3">
      <div class="flex justify-between text-[13px] font-bold text-orange-700 mb-1.5"><span>진행 중...</span><span class="tabular-nums">{{ genStatus.completed || 0 }} / {{ genStatus.target || 10 }}</span></div>
      <div class="h-2.5 bg-orange-100 rounded-full overflow-hidden"><div class="h-full bg-orange-500 transition-all" :style="{ width: progressPct + '%' }"></div></div>
    </div>
    <div v-else-if="genStatus && genStatus.status === 'done' && genStatus.message" class="bg-emerald-50 border border-emerald-200 rounded-xl p-3 text-[13px] text-emerald-700 leading-relaxed break-words">✅ {{ genStatus.message }}</div>
    <button @click="mSheet = { mode: 'gen' }" :disabled="generating" class="w-full min-h-[50px] rounded-xl bg-orange-500 text-white text-[16px] font-bold disabled:opacity-50">🚀 {{ generating ? '생성 중...' : '지금 자동 생성' }}</button>
  </div>

  <div class="flex gap-2 overflow-x-auto scrollbar-hide">
    <div class="shrink-0 min-w-[92px] bg-white border border-gray-100 rounded-2xl px-3 py-2.5"><div class="text-[12px] text-ink-muted">전체 글</div><div class="text-[20px] font-black tabular-nums text-ink">{{ overview.total ?? 0 }}</div></div>
    <div class="shrink-0 min-w-[92px] bg-white border border-gray-100 rounded-2xl px-3 py-2.5"><div class="text-[12px] text-ink-muted">오늘</div><div class="text-[20px] font-black tabular-nums text-blue-600">{{ overview.today ?? 0 }}</div></div>
    <div class="shrink-0 min-w-[92px] bg-white border border-gray-100 rounded-2xl px-3 py-2.5"><div class="text-[12px] text-ink-muted">이번 주</div><div class="text-[20px] font-black tabular-nums text-green-600">{{ overview.week ?? 0 }}</div></div>
  </div>

  <div class="grid grid-cols-2 gap-1 bg-gray-200/70 rounded-2xl p-1" role="tablist" aria-label="정보 관리 메뉴">
    <button v-for="t in tabs" :key="t.key" @click="activeTab = t.key" role="tab" :aria-selected="activeTab === t.key" class="min-h-[46px] rounded-xl text-[15px] font-bold" :class="activeTab === t.key ? 'bg-white text-ink shadow-sm' : 'text-ink-muted'">{{ t.label }}</button>
  </div>

  <template v-if="activeTab === 'posts'">
    <form @submit.prevent="load(1)" class="flex gap-2">
      <label class="flex-1 min-w-0 flex items-center gap-2 bg-white border border-gray-200 rounded-xl px-3 min-h-[48px] text-ink-muted"><AppIcon name="search" :size="18" /><input v-model="search" type="search" placeholder="제목 검색" autocomplete="off" class="w-full min-w-0 bg-transparent outline-none text-ink" /></label>
      <button type="submit" class="shrink-0 min-h-[48px] px-4 rounded-xl bg-amber-500 text-white text-[15px] font-bold">검색</button>
    </form>
    <select v-model="category" @change="load(1)" aria-label="카테고리" class="w-full min-h-[48px] bg-white border border-gray-200 rounded-xl px-3 text-ink"><option value="">전체 카테고리</option><option v-for="c in categories" :key="c" :value="c">{{ c }}</option></select>

    <div v-for="post in posts" :key="post.id" class="bg-white border border-gray-100 rounded-2xl p-3.5">
      <div class="flex items-center gap-2 flex-wrap"><span class="text-[12px] font-bold px-2.5 py-1 rounded-full" :class="post.is_published ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-ink-muted'">{{ post.is_published ? '발행됨' : '비공개' }}</span><span class="text-[12px] text-ink-muted">{{ post.category }}</span><span class="text-[12px] text-ink-faint">{{ post.published_at ? post.published_at.slice(0, 10) : '-' }} · 조회 {{ post.view_count }}</span></div>
      <div class="text-[16px] font-bold text-ink leading-snug mt-1 break-words">{{ post.title }}</div>
      <div class="grid grid-cols-3 gap-2 mt-3">
        <button @click="openEdit(post)" class="min-h-[48px] rounded-xl bg-blue-50 text-blue-700 text-[15px] font-bold">수정</button>
        <button @click="mToggle(post)" class="min-h-[48px] rounded-xl bg-amber-50 text-amber-700 text-[15px] font-bold">{{ post.is_published ? '비공개' : '발행' }}</button>
        <button @click="mSheet = { mode: 'delete', post }" class="min-h-[48px] rounded-xl bg-red-50 text-red-600 text-[15px] font-bold">삭제</button>
      </div>
    </div>
    <div v-if="!posts.length" class="text-center py-12 text-ink-muted text-[15px]">글이 없어요</div>
    <div v-if="lastPage > 1" class="flex items-center justify-between gap-2 pt-1">
      <button @click="load(page - 1)" :disabled="page <= 1" class="min-h-[48px] px-5 rounded-xl bg-white border border-gray-200 text-[15px] font-bold disabled:opacity-40">이전</button>
      <span class="text-[14px] text-ink-muted tabular-nums">{{ page }} / {{ lastPage }}</span>
      <button @click="load(page + 1)" :disabled="page >= lastPage" class="min-h-[48px] px-5 rounded-xl bg-white border border-gray-200 text-[15px] font-bold disabled:opacity-40">다음</button>
    </div>
  </template>

  <template v-else>
    <p class="text-[13px] text-ink-muted leading-relaxed px-0.5">정보 글 카테고리는 11개로 고정돼 있어요 (자동 생성 시 하나를 배정). 실제 글 수 기준 집계예요.</p>
    <div v-for="c in categoryStats" :key="c.name" class="bg-white border border-gray-100 rounded-2xl px-4 min-h-[52px] flex items-center justify-between"><span class="text-[15px] text-ink">🏷 {{ c.name }}</span><b class="text-[14px] text-ink-light tabular-nums">{{ c.post_count }}개</b></div>
    <div v-if="!categoryStats.length" class="text-center py-10 text-ink-muted text-[15px]">아직 발행된 글이 없어요</div>
  </template>

  <Teleport to="body">
    <!-- 글 수정 -->
    <div v-if="editing" class="alv-m fixed inset-0 z-[70] bg-black/45 flex items-end" @click.self="editing = null">
      <div class="w-full bg-white rounded-t-3xl px-4 pt-2 max-h-[94vh] overflow-y-auto" role="dialog" aria-modal="true" :style="{ paddingBottom: 'calc(16px + env(safe-area-inset-bottom, 0px))' }">
        <div class="w-10 h-1 rounded bg-gray-200 mx-auto mb-3"></div>
        <div class="text-[17px] font-bold text-ink mb-3">글 수정</div>
        <div class="space-y-3">
          <label class="block"><span class="block text-[13px] font-bold text-ink-muted mb-1">제목</span><input v-model="editing.title" class="w-full min-h-[50px] rounded-xl border border-gray-200 px-3" /></label>
          <label class="block"><span class="block text-[13px] font-bold text-ink-muted mb-1">카테고리</span><select v-model="editing.category" class="w-full min-h-[48px] rounded-xl border border-gray-200 bg-white px-3"><option v-for="c in categories" :key="c" :value="c">{{ c }}</option></select></label>
          <label class="block"><span class="block text-[13px] font-bold text-ink-muted mb-1">요약</span><textarea v-model="editing.excerpt" rows="3" class="w-full rounded-xl border border-gray-200 px-3 py-3"></textarea></label>
          <label class="block"><span class="block text-[13px] font-bold text-ink-muted mb-1">커버 이미지 주소</span><input v-model="editing.cover_image_url" class="w-full min-h-[48px] rounded-xl border border-gray-200 px-3" /></label>
          <label class="block"><span class="block text-[13px] font-bold text-ink-muted mb-1">본문 (HTML)</span><textarea v-model="editing.body" rows="10" class="w-full rounded-xl border border-gray-200 px-3 py-3 font-mono"></textarea></label>
          <label class="block"><span class="block text-[13px] font-bold text-ink-muted mb-1">meta_title</span><input v-model="editing.meta_title" class="w-full min-h-[48px] rounded-xl border border-gray-200 px-3" /></label>
          <label class="block"><span class="block text-[13px] font-bold text-ink-muted mb-1">meta_description</span><input v-model="editing.meta_description" class="w-full min-h-[48px] rounded-xl border border-gray-200 px-3" /></label>
          <button @click="mSave" :disabled="busy" class="w-full min-h-[52px] rounded-xl bg-amber-500 text-white text-[16px] font-bold disabled:opacity-50">{{ busy ? '저장 중...' : '저장' }}</button>
          <button @click="editing = null" class="w-full min-h-[50px] rounded-xl bg-gray-100 text-ink text-[16px] font-bold">취소</button>
        </div>
      </div>
    </div>
    <!-- 삭제 / 자동 생성 확인 -->
    <div v-if="mSheet" class="alv-m fixed inset-0 z-[70] bg-black/45 flex items-end" @click.self="closeSheet">
      <div class="w-full bg-white rounded-t-3xl px-4 pt-2" role="dialog" aria-modal="true" :style="{ paddingBottom: 'calc(16px + env(safe-area-inset-bottom, 0px))' }">
        <div class="w-10 h-1 rounded bg-gray-200 mx-auto mb-3"></div>
        <template v-if="mSheet.mode === 'delete'">
          <div class="text-[17px] font-bold text-ink mb-1">글을 삭제할까요?</div>
          <p class="text-[15px] text-ink-light mb-3 break-words">“{{ mSheet.post.title }}”</p>
          <button @click="mRemove" :disabled="busy" class="w-full min-h-[52px] rounded-xl bg-red-500 text-white text-[16px] font-bold disabled:opacity-50">{{ busy ? '처리 중...' : '삭제하기' }}</button>
        </template>
        <template v-else>
          <div class="text-[17px] font-bold text-ink mb-1">글 10개를 자동 생성할까요?</div>
          <p class="text-[15px] text-ink-light mb-3">보통 수 분~1시간 안에 처리돼요. 진행 상황은 이 화면에서 볼 수 있어요.</p>
          <button @click="mGenerate" :disabled="busy" class="w-full min-h-[52px] rounded-xl bg-orange-500 text-white text-[16px] font-bold disabled:opacity-50">{{ busy ? '시작 중...' : '시작하기' }}</button>
        </template>
        <button @click="closeSheet" :disabled="busy" class="mt-2 w-full min-h-[50px] rounded-xl bg-gray-100 text-ink text-[16px] font-bold">취소</button>
      </div>
    </div>
    <div v-if="toast" class="alv-m fixed left-1/2 -translate-x-1/2 z-[80] max-w-[90vw] px-4 py-3 rounded-xl text-[15px] font-bold text-white shadow-lg" :class="toast.error ? 'bg-red-600' : 'bg-ink'" :style="{ top: 'calc(70px + env(safe-area-inset-top, 0px))' }" role="status">{{ toast.text }}</div>
  </Teleport>
</div>

<!-- ───────── PC 화면 ───────── -->
<div v-else>
  <!-- 헤더 — 다른 게시판 관리 화면(AdminBoardManager)과 동일한 형식 -->
  <div class="mb-4 flex items-start justify-between flex-wrap gap-2">
    <div>
      <div class="text-xs text-ink-muted">관리자 › 게시판 관리 › 정보</div>
      <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink mt-1">
        <span class="icon-chip w-9 h-9 bg-amber-50 text-amber-600 text-lg">📘</span>
        정보 관리
      </h1>
      <p class="text-xs text-ink-faint mt-0.5">자동 생성된 생활정보 글을 관리합니다</p>
    </div>
    <button @click="triggerGeneration" :disabled="generating"
      class="inline-flex items-center gap-1.5 bg-orange-500 text-white font-semibold px-4 py-2 rounded-xl text-sm hover:bg-orange-600 transition-colors disabled:opacity-50">
      🚀 {{ generating ? '생성 중...' : '지금 자동 생성' }}
    </button>
  </div>

  <div v-if="genStatus && genStatus.status === 'running'" class="mb-4 bg-orange-50 border border-orange-200 rounded-xl p-3">
    <div class="flex justify-between text-xs font-semibold text-orange-700 mb-1.5">
      <span>자동 생성 진행 중...</span>
      <span>{{ genStatus.completed || 0 }} / {{ genStatus.target || 10 }}</span>
    </div>
    <div class="h-2 bg-orange-100 rounded-full overflow-hidden">
      <div class="h-full bg-orange-500 transition-all" :style="{ width: progressPct + '%' }"></div>
    </div>
  </div>
  <div v-else-if="genStatus && genStatus.status === 'done' && genStatus.message" class="mb-4 bg-emerald-50 border border-emerald-200 rounded-xl p-3 text-xs text-emerald-700">
    ✅ {{ genStatus.message }}
  </div>

  <!-- 통계 카드 — 다른 게시판과 동일한 5칸 구성(신고/광고는 정보 게시판엔 해당 없어 항상 0) -->
  <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-4">
    <div class="card p-3">
      <div class="text-xs text-ink-muted">전체 게시글</div>
      <div class="text-xl font-bold text-ink">{{ overview.total ?? 0 }}</div>
    </div>
    <div class="card p-3">
      <div class="text-xs text-ink-muted">오늘 등록</div>
      <div class="text-xl font-bold text-blue-600">{{ overview.today ?? 0 }}</div>
    </div>
    <div class="card p-3">
      <div class="text-xs text-ink-muted">주간 등록</div>
      <div class="text-xl font-bold text-green-600">{{ overview.week ?? 0 }}</div>
    </div>
    <div class="card p-3">
      <div class="text-xs text-ink-muted">신고 대기</div>
      <div class="text-xl font-bold text-red-600">{{ overview.pending_reports ?? 0 }}</div>
    </div>
    <div class="card p-3">
      <div class="text-xs text-ink-muted">광고 활성</div>
      <div class="text-xl font-bold text-purple-600">{{ overview.active_banners ?? 0 }}</div>
    </div>
  </div>

  <!-- 탭 네비 — 정보 게시판은 포인트/배너/신고 개념이 없어 게시글/카테고리 2탭만 -->
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
    <!-- 📝 게시글 -->
    <div v-if="activeTab === 'posts'">
      <div class="flex flex-wrap gap-2 mb-4">
        <select v-model="category" @change="load(1)" class="border border-line rounded-lg px-3 py-1.5 text-sm">
          <option value="">전체 카테고리</option>
          <option v-for="c in categories" :key="c" :value="c">{{ c }}</option>
        </select>
        <input v-model="search" @keyup.enter="load(1)" placeholder="제목 검색" class="border border-line rounded-lg px-3 py-1.5 text-sm flex-1 min-w-[160px]" />
      </div>

      <div class="overflow-x-auto border border-line rounded-xl">
        <table class="w-full text-sm">
          <thead class="bg-surface text-ink-light text-xs">
            <tr>
              <th class="text-left px-3 py-2">제목</th>
              <th class="text-left px-3 py-2">카테고리</th>
              <th class="text-left px-3 py-2">발행일</th>
              <th class="text-left px-3 py-2">조회</th>
              <th class="text-left px-3 py-2">상태</th>
              <th class="text-right px-3 py-2">관리</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="post in posts" :key="post.id" class="border-t border-line">
              <td class="px-3 py-2 max-w-xs truncate">{{ post.title }}</td>
              <td class="px-3 py-2">{{ post.category }}</td>
              <td class="px-3 py-2 text-xs text-ink-light">{{ post.published_at ? post.published_at.slice(0,10) : '-' }}</td>
              <td class="px-3 py-2">{{ post.view_count }}</td>
              <td class="px-3 py-2">
                <span :class="post.is_published ? 'text-emerald-600' : 'text-ink-faint'">{{ post.is_published ? '발행됨' : '비공개' }}</span>
              </td>
              <td class="px-3 py-2 text-right whitespace-nowrap">
                <button @click="openEdit(post)" class="text-blue-600 hover:underline mr-2">수정</button>
                <button @click="toggle(post)" class="text-amber-600 hover:underline mr-2">{{ post.is_published ? '비공개' : '발행' }}</button>
                <button @click="remove(post)" class="text-red-600 hover:underline">삭제</button>
              </td>
            </tr>
            <tr v-if="!posts.length">
              <td colspan="6" class="px-3 py-8 text-center text-ink-faint text-sm">글이 없습니다</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="flex justify-center gap-2 mt-4" v-if="lastPage > 1">
        <button v-for="p in lastPage" :key="p" @click="load(p)"
          class="w-8 h-8 rounded-lg text-sm" :class="p === page ? 'bg-orange-500 text-white' : 'bg-surface text-ink-light'">{{ p }}</button>
      </div>
    </div>

    <!-- 📂 카테고리 — 고정된 11개 분류라 추가/삭제는 없고, 실제 집계만 참고용으로 보여줌 -->
    <div v-else-if="activeTab === 'cat'">
      <div class="text-sm text-ink-light mb-3">정보 글 카테고리는 11개로 고정돼 있습니다 (자동 생성 시 하나를 배정). 실제 글 수 기준 집계입니다.</div>
      <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-2">
        <div v-for="c in categoryStats" :key="c.name" class="flex items-center justify-between border border-line rounded-xl px-3 py-2.5">
          <span class="flex items-center gap-1.5 text-sm text-ink"><span>🏷</span>{{ c.name }}</span>
          <span class="badge-gray !text-xs">{{ c.post_count }}개</span>
        </div>
        <div v-if="!categoryStats.length" class="col-span-full py-8 text-center text-ink-faint text-sm">아직 발행된 글이 없습니다</div>
      </div>
    </div>
  </div>

  <!-- 수정 모달 -->
  <div v-if="editing" class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4" @click.self="editing=null">
    <div class="bg-white rounded-2xl p-5 w-full max-w-xl max-h-[85vh] overflow-y-auto">
      <h2 class="font-bold mb-3">글 수정</h2>
      <div class="space-y-3 text-sm">
        <div>
          <label class="block text-xs text-ink-light mb-1">제목</label>
          <input v-model="editing.title" class="w-full border border-line rounded-lg px-3 py-2" />
        </div>
        <div>
          <label class="block text-xs text-ink-light mb-1">카테고리</label>
          <select v-model="editing.category" class="w-full border border-line rounded-lg px-3 py-2">
            <option v-for="c in categories" :key="c" :value="c">{{ c }}</option>
          </select>
        </div>
        <div>
          <label class="block text-xs text-ink-light mb-1">요약(excerpt)</label>
          <textarea v-model="editing.excerpt" rows="2" class="w-full border border-line rounded-lg px-3 py-2"></textarea>
        </div>
        <div>
          <label class="block text-xs text-ink-light mb-1">커버 이미지 URL</label>
          <input v-model="editing.cover_image_url" class="w-full border border-line rounded-lg px-3 py-2" />
        </div>
        <div>
          <label class="block text-xs text-ink-light mb-1">본문 HTML</label>
          <textarea v-model="editing.body" rows="10" class="w-full border border-line rounded-lg px-3 py-2 font-mono text-xs"></textarea>
        </div>
        <div>
          <label class="block text-xs text-ink-light mb-1">meta_title</label>
          <input v-model="editing.meta_title" class="w-full border border-line rounded-lg px-3 py-2" />
        </div>
        <div>
          <label class="block text-xs text-ink-light mb-1">meta_description</label>
          <input v-model="editing.meta_description" class="w-full border border-line rounded-lg px-3 py-2" />
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
import { ref, computed, onMounted, onUnmounted, watch, inject } from 'vue'
import axios from 'axios'
import AppIcon from '../../components/AppIcon.vue'

// 관리자 휴대폰 화면이면 카드 + 아래에서 올라오는 시트로 보여 줌 (AdminLayout 이 알려 줌)
const adminIsMobile = inject('adminIsMobile', ref(false))
const isMobile = computed(() => !!adminIsMobile.value)

const activeTab = ref('posts')
const tabs = [
  { key: 'posts', icon: 'edit', label: '게시글' },
  { key: 'cat',   icon: 'tag',  label: '카테고리' },
]

const overview = ref({})
const categoryStats = ref([])

const posts = ref([])
const page = ref(1)
const lastPage = ref(1)
const category = ref('')
const search = ref('')
const editing = ref(null)
const generating = ref(false)
const genStatus = ref(null)
let pollTimer = null

const categories = ['생활정보', '이민·비자', '세금', '금융', '보험', '부동산', '교통', '교육', '날씨·안전', '통신', '창업·비즈니스']

const progressPct = computed(() => {
  if (!genStatus.value?.target) return 0
  return Math.min(100, Math.round((genStatus.value.completed / genStatus.value.target) * 100))
})

async function loadOverview() {
  try {
    const { data } = await axios.get('/api/admin/board-manager/info/overview')
    overview.value = data.data
  } catch (e) { console.warn('overview load failed', e) }
}

async function loadCategoryStats() {
  try {
    const { data } = await axios.get('/api/admin/board-manager/info/categories')
    categoryStats.value = data.data || []
  } catch (e) { console.warn('categories load failed', e) }
}

async function load(p = 1) {
  page.value = p
  const { data } = await axios.get('/api/admin/info-posts', { params: { page: p, category: category.value || undefined, search: search.value || undefined } })
  posts.value = data.data.data
  lastPage.value = data.data.last_page
}

function openEdit(post) {
  editing.value = { ...post }
}

async function save() {
  try {
    await axios.put(`/api/admin/info-posts/${editing.value.id}`, {
      title: editing.value.title,
      category: editing.value.category,
      excerpt: editing.value.excerpt,
      cover_image_url: editing.value.cover_image_url,
      body: editing.value.body,
      meta_title: editing.value.meta_title,
      meta_description: editing.value.meta_description,
    })
    editing.value = null
    load(page.value)
  } catch (e) { alert(e.response?.data?.message || '저장 실패') }
}

async function toggle(post) {
  await axios.patch(`/api/admin/info-posts/${post.id}/toggle`)
  load(page.value)
  loadOverview()
}

async function remove(post) {
  if (!confirm(`"${post.title}" 글을 삭제할까요?`)) return
  await axios.delete(`/api/admin/info-posts/${post.id}`)
  load(page.value)
  loadOverview()
  loadCategoryStats()
}

async function fetchGenStatus() {
  const { data } = await axios.get('/api/admin/info-generation/status')
  genStatus.value = data
  if (data.status === 'running') {
    generating.value = true
  } else {
    generating.value = false
    if (pollTimer) { clearInterval(pollTimer); pollTimer = null }
    loadOverview()
    loadCategoryStats()
  }
}

async function triggerGeneration() {
  if (!confirm('글 10개를 자동 생성합니다. 보통 수 분~1시간 내 처리됩니다. 시작할까요?')) return
  generating.value = true
  await axios.post('/api/admin/info-generation/trigger')
  startPolling()
}

function startPolling() {
  if (pollTimer) return
  pollTimer = setInterval(fetchGenStatus, 10000)
  fetchGenStatus()
}

// ── 휴대폰 화면 전용: 브라우저 확인창 대신 시트 + 처리 결과 안내 ──
const mSheet = ref(null)   // { mode: 'delete', post } | { mode: 'gen' }
const busy = ref(false)
const toast = ref(null)
let toastTimer = null
function say(text, error = false) { toast.value = { text, error }; clearTimeout(toastTimer); toastTimer = setTimeout(() => { toast.value = null }, 3000) }
function closeSheet() { if (!busy.value) mSheet.value = null }
async function mSave() {
  if (busy.value) return
  busy.value = true
  try {
    await axios.put(`/api/admin/info-posts/${editing.value.id}`, {
      title: editing.value.title, category: editing.value.category, excerpt: editing.value.excerpt,
      cover_image_url: editing.value.cover_image_url, body: editing.value.body,
      meta_title: editing.value.meta_title, meta_description: editing.value.meta_description,
    })
    editing.value = null; say('저장했어요'); load(page.value)
  } catch (e) { say(e.response?.data?.message || '저장하지 못했어요', true) }
  finally { busy.value = false }
}
async function mToggle(post) {
  try { await axios.patch(`/api/admin/info-posts/${post.id}/toggle`); say(post.is_published ? '비공개로 바꿨어요' : '발행했어요'); load(page.value); loadOverview() }
  catch (e) { say(e.response?.data?.message || '바꾸지 못했어요', true) }
}
async function mRemove() {
  if (busy.value) return
  busy.value = true
  const post = mSheet.value.post
  try { await axios.delete(`/api/admin/info-posts/${post.id}`); mSheet.value = null; say('삭제했어요'); load(page.value); loadOverview(); loadCategoryStats() }
  catch (e) { say(e.response?.data?.message || '삭제하지 못했어요', true) }
  finally { busy.value = false }
}
async function mGenerate() {
  if (busy.value) return
  busy.value = true
  try { generating.value = true; await axios.post('/api/admin/info-generation/trigger'); mSheet.value = null; say('자동 생성을 시작했어요'); startPolling() }
  catch (e) { generating.value = false; say(e.response?.data?.message || '시작하지 못했어요', true) }
  finally { busy.value = false }
}
watch(() => isMobile.value && (!!editing.value || !!mSheet.value), locked => { document.body.style.overflow = locked ? 'hidden' : '' })

onMounted(() => {
  load(1)
  loadOverview()
  loadCategoryStats()
  fetchGenStatus().then(() => { if (genStatus.value?.status === 'running') startPolling() })
})
onUnmounted(() => { if (pollTimer) clearInterval(pollTimer); document.body.style.overflow = ''; clearTimeout(toastTimer) })
</script>
<style>
/* 휴대폰 관리자: 입력창 글자가 16px 보다 작으면 iOS 가 화면을 확대해 버림 */
.alv-m input, .alv-m textarea, .alv-m select { font-size: 16px; }
</style>
