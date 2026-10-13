<template>
<div class="min-h-screen">
  <div class="max-w-7xl mx-auto px-4 py-5">
    <MoneyHead :crumbs="[{ label: cafe ? cafe.name : '카페' }]" />

    <p v-if="notFound" class="card p-8 text-center text-ink-muted">카페를 찾을 수 없어요. <RouterLink to="/money" class="text-emerald-700 font-bold underline">MONEY 처음으로</RouterLink></p>

    <template v-else-if="cafe">
      <!-- ① 소개: 작게 (큰 그림·이모지 없이) -->
      <section class="rounded-2xl border px-4 py-4 md:px-6 md:py-5" :style="{ borderColor: t.line, background: t.soft }">
        <div class="flex items-start gap-3">
          <span class="icon-chip w-11 h-11 shrink-0" :style="{ background: '#fff', color: t.accent }"><AppIcon :name="cafeIcon(cafe.slug)" :size="22" /></span>
          <div class="min-w-0 flex-1">
            <h2 class="text-[22px] md:text-[26px] font-black text-ink leading-tight">{{ cafe.name }}</h2>
            <p class="text-[15px] text-ink-light mt-1 leading-snug">{{ cafe.headline || cafe.summary }}</p>
            <p class="text-[12.5px] text-ink-muted mt-2">글 {{ totalPosts }}개 · 주제 {{ categories.length }}가지 · 공식 자료 기반 · 가입 없이 읽기</p>
          </div>
        </div>
      </section>

      <!-- ② 처음이라면 먼저 (있을 때만, 작게) -->
      <section v-if="readyGuides.length" class="card mt-3 p-3.5 md:p-4">
        <div class="text-[13px] font-bold text-ink mb-1">처음이라면 먼저 읽어 보세요</div>
        <RouterLink v-for="g in readyGuides.slice(0, 3)" :key="g.slug" :to="`/money/guide/${g.slug}`"
          class="flex items-center gap-2 py-2 text-[14.5px] text-ink hover:text-emerald-700 border-b border-gray-50 last:border-0">
          <span class="truncate flex-1">{{ g.title }}</span>
          <AppIcon name="chevron-right" :size="15" class="text-ink-faint shrink-0" />
        </RouterLink>
      </section>

      <!-- ③ 게시판 — 바로 보이게 -->
      <section id="board" class="mt-4 scroll-mt-40">
        <div class="flex items-center justify-between gap-3 mb-2.5">
          <h3 class="text-[18px] font-bold text-ink">글 목록</h3>
          <RouterLink v-if="auth.isLoggedIn" :to="writeLink" class="mc-btn" :style="{ background: t.accent }"><AppIcon name="edit" :size="15" />글쓰기</RouterLink>
          <RouterLink v-else to="/login" class="mc-btn mc-btn-line" :style="{ color: t.ink, borderColor: t.line }">로그인하고 글쓰기</RouterLink>
        </div>

        <!-- 주제 고르기 (한 줄, 가로로 밀기) -->
        <div v-if="categories.length" class="flex gap-1.5 overflow-x-auto scrollbar-hide pb-1 mb-2">
          <button type="button" @click="pickTopic(0)" class="mc-chip" :style="!topic ? chipOn : {}">전체</button>
          <button v-for="c in categories" :key="c.id" type="button" @click="pickTopic(c.id)" class="mc-chip" :style="topic === c.id ? chipOn : {}">{{ c.name }}<span class="ml-1 text-[11px] opacity-60">{{ c.post_count || 0 }}</span></button>
        </div>
        <div class="flex items-center gap-2 flex-wrap mb-3">
          <div class="flex gap-1 bg-gray-100 rounded-xl p-1">
            <button v-for="k in kinds" :key="k.v" type="button" @click="setKind(k.v)" class="px-3 py-1 rounded-lg text-[13px] font-semibold"
              :class="kind === k.v ? 'bg-white text-ink shadow-sm' : 'text-ink-muted'">{{ k.l }}</button>
          </div>
          <select v-model="sort" @change="load(1)" class="input-soft w-auto text-xs py-1.5">
            <option value="">최신순</option><option value="views">조회순</option><option value="comments">답글순</option>
          </select>
          <form @submit.prevent="load(1)" class="ml-auto flex gap-1.5 min-w-0">
            <input v-model="search" type="search" placeholder="제목 검색" class="input-soft w-36 sm:w-48 text-xs py-1.5" />
            <button type="submit" class="btn-secondary px-3 py-1.5 text-xs">검색</button>
          </form>
        </div>

        <div class="card overflow-hidden">
          <div v-if="loadingPosts" class="py-10 text-center text-ink-muted">불러오는 중...</div>
          <div v-else-if="!posts.length" class="py-12 text-center">
            <p class="text-ink-muted">{{ topic ? '이 주제에는 아직 글이 없어요.' : '아직 글이 없어요.' }} 첫 정보를 올려 주세요!</p>
            <RouterLink v-if="auth.isLoggedIn" :to="writeLink" class="mc-btn mt-4" :style="{ background: t.accent }">첫 글 쓰기</RouterLink>
          </div>
          <RouterLink v-for="p in posts" :key="p.id" :to="`/money/post/${p.id}`" class="list-row !px-4 sm:!px-5 !py-3.5 flex items-center gap-3 border-b border-gray-50 last:border-0"
            :class="p.is_pinned ? 'bg-amber-50/40' : ''">
            <div class="min-w-0 flex-1">
              <div class="flex items-center gap-1.5 flex-wrap">
                <span v-if="p.is_pinned" class="text-[11px] font-bold rounded px-1.5 py-0.5 bg-red-50 text-red-600">공지</span>
                <span class="text-[11px] font-bold rounded-full px-2 py-0.5" :class="KIND_CLASS[p.kind]">{{ KIND_LABEL[p.kind] }}</span>
                <span v-if="p.category" class="text-[11px] font-semibold rounded px-1.5 py-0.5 bg-gray-100 text-ink-light">{{ p.category.name }}</span>
              </div>
              <div class="text-[15px] font-bold text-ink leading-snug mt-1 truncate">{{ p.title }}</div>
              <div class="text-xs text-ink-muted mt-1">{{ p.user?.display_name || p.user?.name || '회원' }} · {{ fmt(p.created_at) }} · 조회 {{ p.view_count }}</div>
            </div>
            <span class="shrink-0 text-xs font-semibold inline-flex items-center gap-1" :class="p.comment_count ? 'text-amber-700' : 'text-ink-faint'"><AppIcon name="message-circle" :size="14" />{{ p.comment_count }}</span>
          </RouterLink>
        </div>
        <div v-if="lastPage > 1" class="flex justify-center gap-1.5 mt-4">
          <button v-for="n in Math.min(lastPage, 10)" :key="n" @click="load(n)" class="w-9 h-9 rounded-lg text-sm" :class="n === page ? 'text-white font-bold' : 'text-ink-muted hover:bg-gray-100'" :style="n === page ? { background: t.accent } : {}">{{ n }}</button>
        </div>
      </section>

      <!-- ④ 이용 안내 (접어 둠) -->
      <details class="card mt-5 p-4 md:p-5">
        <summary class="cursor-pointer font-bold text-ink">이 카페 이용 안내</summary>
        <div class="grid md:grid-cols-2 gap-5 mt-3">
          <div>
            <div class="text-sm font-bold text-ink mb-2">처음 방문했다면</div>
            <ol class="space-y-2">
              <li v-for="(s, i) in steps" :key="i" class="flex gap-2.5 items-start text-[14.5px] text-ink-light">
                <span class="mc-num" :style="{ background: t.soft, color: t.accent }">{{ i + 1 }}</span><span class="pt-0.5">{{ s }}</span>
              </li>
            </ol>
          </div>
          <div>
            <div class="text-sm font-bold text-ink mb-2">함께 나누는 방법</div>
            <ol class="space-y-2 text-[14.5px] text-ink-light">
              <li class="flex gap-2.5 items-start"><span class="mc-num" :style="{ background: t.soft, color: t.accent }">1</span><span class="pt-0.5">관심 있는 주제의 정보를 읽어 보세요.</span></li>
              <li class="flex gap-2.5 items-start"><span class="mc-num" :style="{ background: t.soft, color: t.accent }">2</span><span class="pt-0.5">궁금한 점은 질문 글이나 답글로 물어보세요.</span></li>
              <li class="flex gap-2.5 items-start"><span class="mc-num" :style="{ background: t.soft, color: t.accent }">3</span><span class="pt-0.5">내 경험과 팁을 나눠 주세요(개인정보·계좌정보는 올리지 마세요).</span></li>
            </ol>
          </div>
        </div>
        <div v-if="cafe.intro" class="money-intro mt-4 pt-4 border-t border-line text-[15px] text-ink-light leading-relaxed" v-html="cafe.intro"></div>
      </details>

      <div v-if="others.length" class="mt-5">
        <div class="text-sm font-bold text-ink mb-2">다른 분야</div>
        <div class="flex flex-wrap gap-2">
          <RouterLink v-for="c in others" :key="c.slug" :to="`/money/cafe/${c.slug}`" class="mc-chip" :style="c.slug === cafe.slug ? chipOn : {}">{{ c.name }}</RouterLink>
        </div>
      </div>

      <p class="mt-6 text-[12.5px] text-ink-muted">{{ DISCLAIMER }}</p>
    </template>
  </div>
</div>
</template>

<script setup>
import { ref, computed, watch, onMounted, nextTick } from 'vue'
import { useRoute } from 'vue-router'
import axios from 'axios'
import { useAuthStore } from '../../stores/auth'
import MoneyHead from '../../components/money/MoneyHead.vue'
import AppIcon from '../../components/AppIcon.vue'
import { KIND_LABEL, KIND_CLASS, DISCLAIMER } from '../../components/money/moneyText'
import { themeOf, cafeIcon } from '../../components/money/moneyThemes'

const route = useRoute()
const auth = useAuthStore()
const cafe = ref(null); const categories = ref([]); const guides = ref([]); const notFound = ref(false)
const others = ref([])
const posts = ref([]); const page = ref(1); const lastPage = ref(1); const loadingPosts = ref(false)
const kind = ref(''); const search = ref(''); const sort = ref(''); const topic = ref(0)
const kinds = [{ v: '', l: '전체' }, { v: 'info', l: '정보' }, { v: 'question', l: '질문' }, { v: 'talk', l: '소통' }]
const fmt = (s) => (s ? String(s).slice(0, 10) : '')

const t = computed(() => themeOf(cafe.value?.theme))
const chipOn = computed(() => ({ background: t.value.soft, color: t.value.ink, borderColor: t.value.accent }))
const readyGuides = computed(() => guides.value.filter(g => g.ready && g.slug))
const steps = computed(() => (cafe.value?.first_steps?.length ? cafe.value.first_steps
  : ['관심 있는 주제부터 가볍게 읽어 보기', '궁금한 건 질문 글로 물어보기', '내 경험을 짧게라도 나눠 보기']))
const totalPosts = computed(() => cafe.value?.post_count ?? categories.value.reduce((n, c) => n + (c.post_count || 0), 0))
const writeLink = computed(() => `/money/write?cafe=${cafe.value?.slug || ''}${topic.value ? `&topic=${topic.value}` : ''}`)

function scrollTo(id) { document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'start' }) }

async function loadCafe() {
  notFound.value = false; cafe.value = null
  topic.value = Number(route.query.topic) || 0
  try {
    const { data } = await axios.get(`/api/money/cafes/${route.params.slug}`)
    cafe.value = data.data.cafe; categories.value = data.data.categories || []
    guides.value = data.data.guides || []
    load(1)
    if (topic.value) nextTick(() => scrollTo('board'))
  } catch { notFound.value = true }
  if (!others.value.length) {
    try { const { data } = await axios.get('/api/money/cafes'); others.value = (data.data || []).filter(c => (c.post_count || 0) > 0 || c.slug === route.params.slug)
      const me = (data.data || []).find(c => c.slug === route.params.slug); if (me && cafe.value) cafe.value.post_count = me.post_count } catch {}
  }
}
async function load(n) {
  loadingPosts.value = true
  try {
    const { data } = await axios.get(`/api/money/cafes/${route.params.slug}/posts`, { params: {
      page: n, kind: kind.value || undefined, search: search.value || undefined, sort: sort.value || undefined, category: topic.value || undefined } })
    posts.value = data.data.data || []; page.value = data.data.current_page || n; lastPage.value = data.data.last_page || 1
  } catch { posts.value = [] }
  loadingPosts.value = false
}
function setKind(v) { kind.value = v; load(1) }
// 주소(?topic=)는 바꾸지 않는다 — App.vue 가 주소 전체를 key 로 써서, 주소가 바뀌면 화면 전체가 새로 그려져 깜빡이고 맨 위로 튐.
function pickTopic(id) {
  topic.value = topic.value === id && id ? 0 : id
  load(1)
}
watch(() => route.params.slug, (s, old) => { if (s && s !== old) { kind.value = ''; search.value = ''; sort.value = ''; loadCafe(); window.scrollTo(0, 0) } })
onMounted(loadCafe)
</script>

<style scoped>
.mc-btn { display: inline-flex; align-items: center; gap: 6px; color: #fff; padding: 9px 16px; border-radius: 12px; font-weight: 700; font-size: 14px; transition: transform .15s, box-shadow .15s; }
.mc-btn:hover { transform: translateY(-1px); box-shadow: 0 6px 16px rgba(0,0,0,.12); }
.mc-btn-line { background: #fff; border: 1px solid; }
.mc-num { width: 24px; height: 24px; border-radius: 9999px; display: inline-grid; place-items: center; font-weight: 800; font-size: 12px; flex-shrink: 0; }
.mc-chip { flex-shrink: 0; white-space: nowrap; border: 1px solid #e3e6e8; background: #fff; border-radius: 9999px; padding: 6px 13px; font-size: 13.5px; font-weight: 600; color: #4a5753; }
</style>
