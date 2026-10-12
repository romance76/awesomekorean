<template>
<div class="min-h-screen">
  <div class="max-w-7xl mx-auto px-4 py-5">
    <MoneyHead :crumbs="[{ label: cafe ? cafe.name + ' 카페' : '카페' }]" />

    <p v-if="notFound" class="card p-8 text-center text-ink-muted">카페를 찾을 수 없어요. <RouterLink to="/money/cafes" class="text-emerald-700 font-bold underline">카페 안내로</RouterLink></p>

    <template v-else-if="cafe">
      <!-- 카페 머리 -->
      <div class="rounded-2xl text-white px-5 py-5 flex items-center gap-4 flex-wrap" style="background:linear-gradient(135deg,#0b3d2e,#0f6b4d)">
        <div class="text-4xl leading-none">{{ cafe.icon }}</div>
        <div class="min-w-0 flex-1">
          <h2 class="text-[22px] font-black">{{ cafe.name }} 카페</h2>
          <div class="text-sm text-[#bdeed6]">{{ cafe.summary }} · 가입 없이 누구나 보기·답글</div>
        </div>
        <span class="text-xs font-bold rounded-xl border border-white/35 bg-white/10 px-3 py-2">카페 운영자: {{ cafe.manager_user_id ? '있음' : '모집 중' }}</span>
      </div>

      <!-- 대문 -->
      <section class="card p-5 mt-4">
        <div class="flex items-center justify-between gap-2">
          <h3 class="font-bold text-ink">📌 카페 대문</h3>
          <button @click="introOpen = !introOpen" class="text-sm font-bold text-emerald-700">{{ introOpen ? '접기' : '펼치기' }}</button>
        </div>
        <div v-show="introOpen" class="money-intro mt-3 text-[15px] text-ink-light leading-relaxed" v-html="cafe.intro"></div>
        <div v-if="popular.length" class="mt-4 pt-4 border-t border-line">
          <div class="text-sm font-bold text-ink mb-2">🔥 인기 정보</div>
          <RouterLink v-for="p in popular" :key="p.id" :to="`/money/post/${p.id}`" class="flex items-center gap-2 py-1.5 text-sm text-ink-light hover:text-emerald-700">
            <span class="text-[11px] font-bold rounded-full px-2 py-0.5" :class="KIND_CLASS[p.kind]">{{ KIND_LABEL[p.kind] }}</span>
            <span class="truncate">{{ p.title }}</span><span class="ml-auto text-xs text-ink-faint shrink-0">조회 {{ p.view_count }}</span>
          </RouterLink>
        </div>
      </section>

      <!-- 게시판 -->
      <div class="flex items-center gap-2 flex-wrap mt-5 mb-3">
        <button v-for="t in tabs" :key="t.v" @click="setKind(t.v)" class="rounded-full border px-3.5 py-1.5 text-sm font-bold"
          :class="kind === t.v ? 'bg-[#fff1f5] text-[#FC226B] border-[#ffc2d6]' : 'bg-surface text-ink-light border-line'">{{ t.l }}</button>
        <form @submit.prevent="load(1)" class="ml-auto flex gap-1.5">
          <input v-model="search" type="search" placeholder="제목 검색" class="input-soft w-40 py-1.5" />
          <button type="submit" class="btn-secondary px-3 py-1.5 text-sm">검색</button>
        </form>
        <RouterLink v-if="auth.isLoggedIn" :to="`/money/write?cafe=${cafe.slug}`" class="btn-primary px-4 py-2 text-sm">새 정보 올리기</RouterLink>
        <RouterLink v-else to="/login" class="btn-secondary px-4 py-2 text-sm">로그인하고 글쓰기</RouterLink>
      </div>

      <div class="card overflow-hidden">
        <div v-if="loadingPosts" class="py-10 text-center text-ink-muted">불러오는 중...</div>
        <div v-else-if="!posts.length" class="py-12 text-center text-ink-muted">아직 글이 없어요. 첫 정보를 올려 주세요!</div>
        <RouterLink v-for="p in posts" :key="p.id" :to="`/money/post/${p.id}`" class="flex items-center gap-3 px-4 py-3.5 border-b border-gray-50 last:border-0 hover:bg-gray-50">
          <span class="shrink-0 text-[11px] font-bold rounded-full px-2 py-0.5" :class="KIND_CLASS[p.kind]">{{ KIND_LABEL[p.kind] }}</span>
          <div class="min-w-0 flex-1">
            <div class="font-semibold text-ink truncate">{{ p.title }}</div>
            <div class="text-xs text-ink-muted mt-0.5">{{ p.user?.display_name || p.user?.name || '회원' }} · {{ fmt(p.created_at) }} · 조회 {{ p.view_count }}</div>
          </div>
          <span class="shrink-0 text-xs text-ink-muted">답글 {{ p.comment_count }}</span>
        </RouterLink>
      </div>
      <div v-if="lastPage > 1" class="flex justify-center gap-1.5 mt-4">
        <button v-for="n in Math.min(lastPage, 10)" :key="n" @click="load(n)" class="w-9 h-9 rounded-lg text-sm" :class="n === page ? 'bg-emerald-600 text-white font-bold' : 'text-ink-muted hover:bg-gray-100'">{{ n }}</button>
      </div>

      <p class="mt-6 text-[12.5px] text-ink-muted">{{ DISCLAIMER }}</p>
    </template>
  </div>
</div>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import axios from 'axios'
import { useAuthStore } from '../../stores/auth'
import MoneyHead from '../../components/money/MoneyHead.vue'
import { KIND_LABEL, KIND_CLASS, DISCLAIMER } from '../../components/money/moneyText'

const route = useRoute()
const auth = useAuthStore()
const cafe = ref(null); const popular = ref([]); const notFound = ref(false)
const introOpen = ref(true)
const posts = ref([]); const page = ref(1); const lastPage = ref(1); const loadingPosts = ref(false)
const kind = ref(''); const search = ref('')
const tabs = [{ v: '', l: '전체' }, { v: 'info', l: '정보' }, { v: 'question', l: '질문' }, { v: 'talk', l: '소통' }]
const fmt = (s) => (s ? String(s).slice(0, 10) : '')

async function loadCafe() {
  notFound.value = false; cafe.value = null
  try {
    const { data } = await axios.get(`/api/money/cafes/${route.params.slug}`)
    cafe.value = data.data.cafe; popular.value = data.data.popular || []
    // 글이 쌓이면 대문은 접어 두고 게시판이 먼저 보이게
    introOpen.value = popular.value.length < 3
    load(1)
  } catch { notFound.value = true }
}
async function load(n) {
  loadingPosts.value = true
  try {
    const { data } = await axios.get(`/api/money/cafes/${route.params.slug}/posts`, { params: { page: n, kind: kind.value || undefined, search: search.value || undefined } })
    posts.value = data.data.data || []; page.value = data.data.current_page || n; lastPage.value = data.data.last_page || 1
  } catch { posts.value = [] }
  loadingPosts.value = false
}
function setKind(v) { kind.value = v; load(1) }
watch(() => route.params.slug, (s) => { if (s) loadCafe() })
onMounted(loadCafe)
</script>
