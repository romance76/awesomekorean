<template>
<div class="min-h-screen">
  <div class="max-w-7xl mx-auto px-4 py-5">
    <MoneyHead :crumbs="[{ label: '분야별 카페', to: '/money/cafes', mobileHide: true }, { label: cafe ? cafe.name : '카페' }]" />

    <p v-if="notFound" class="card p-8 text-center text-ink-muted">카페를 찾을 수 없어요. <RouterLink to="/money/cafes" class="text-emerald-700 font-bold underline">카페 안내로</RouterLink></p>

    <template v-else-if="cafe">
      <!-- ① 대문 그림: 큰 문구 + 그림 (시안 awesomekorean_12_money_pages) -->
      <section class="mc-hero" :style="{ background: `linear-gradient(125deg, ${t.from}, ${t.to} 62%)`, borderColor: t.line }">
        <div class="min-w-0">
          <div class="text-[12px] font-black tracking-[2px]" :style="{ color: t.accent }">MONEY CAFE · 분야별 카페</div>
          <h1 class="mc-h1">{{ cafe.name }}</h1>
          <h2 class="mc-h2">{{ cafe.headline || cafe.summary }}</h2>
          <p v-if="cafe.headline && cafe.summary" class="text-[15px] md:text-[16px] text-ink-light max-w-xl">{{ cafe.summary }}</p>
          <div class="flex flex-wrap gap-2.5 mt-6">
            <a href="#topics" @click.prevent="scrollTo('topics')" class="mc-btn" :style="{ background: t.accent }">주제 살펴보기 ↓</a>
            <a href="#board" @click.prevent="scrollTo('board')" class="mc-btn mc-btn-line" :style="{ color: t.ink, borderColor: t.line }">글 보러 가기 →</a>
          </div>
          <div class="flex flex-wrap gap-x-4 gap-y-1 mt-5 text-[13px] font-semibold" :style="{ color: t.ink }">
            <span>✓ 가입 없이 누구나 보기·답글</span><span>✓ 글 {{ totalPosts }}개</span><span>✓ 주제 {{ categories.length }}가지</span>
          </div>
        </div>
        <div class="mc-art" :style="{ background: `radial-gradient(circle at 30% 30%, #fff 0%, ${t.art} 70%)` }" role="img" :aria-label="cafe.name + ' 그림'">
          <span class="mc-coin mc-coin-a">{{ art.a }}</span>
          <span class="mc-emoji">{{ art.main }}</span>
          <span class="mc-coin mc-coin-b">{{ art.b }}</span>
        </div>
      </section>

      <!-- ② 주제 카드: 누르면 아래 게시판이 그 주제 글만 -->
      <section id="topics" class="mt-10 scroll-mt-40" v-if="categories.length">
        <h3 class="mc-sec">이곳에서는 무엇을 알아볼 수 있나요?</h3>
        <p class="text-ink-muted mb-5">관심 있는 주제를 누르면 그 주제의 글만 모아 볼 수 있어요.</p>
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3">
          <button v-for="c in categories" :key="c.id" type="button" @click="pickTopic(c.id)"
            class="mc-topic" :class="topic === c.id ? 'mc-topic-on' : ''" :style="topic === c.id ? { borderColor: t.accent, background: t.soft } : {}">
            <span class="text-[26px] leading-none">{{ c.icon || '•' }}</span>
            <span class="mt-3 font-bold text-ink text-[15px] leading-snug">{{ c.name }}</span>
            <span v-if="c.hint" class="mt-1 text-[12.5px] text-ink-muted leading-snug">{{ c.hint }}</span>
            <span class="mt-auto pt-2 text-[12px] font-bold" :style="{ color: t.accent }">{{ c.post_count ? `글 ${c.post_count}개 보기 →` : '첫 글을 기다려요 →' }}</span>
          </button>
        </div>
      </section>

      <!-- ③ 처음 방문했다면 / 함께 나누는 방법 -->
      <section class="grid md:grid-cols-2 gap-4 mt-10">
        <div class="mc-panel">
          <h3 class="text-[20px] font-bold text-ink mb-3">👋 처음 방문했다면</h3>
          <ol class="space-y-3">
            <li v-for="(s, i) in steps" :key="i" class="flex gap-3 items-start">
              <span class="mc-num" :style="{ background: t.soft, color: t.accent }">{{ i + 1 }}</span><span class="text-[15px] text-ink-light pt-0.5">{{ s }}</span>
            </li>
          </ol>
        </div>
        <div class="mc-panel">
          <h3 class="text-[20px] font-bold text-ink mb-3">🤝 함께 나누는 방법</h3>
          <ol class="space-y-3">
            <li class="flex gap-3 items-start"><span class="mc-num" :style="{ background: t.soft, color: t.accent }">1</span><span class="text-[15px] text-ink-light pt-0.5">관심 있는 주제의 정보를 읽어 보세요.</span></li>
            <li class="flex gap-3 items-start"><span class="mc-num" :style="{ background: t.soft, color: t.accent }">2</span><span class="text-[15px] text-ink-light pt-0.5">궁금한 점은 <b class="text-ink">질문 글</b>로 남겨 보세요. 답글은 로그인 없이도 볼 수 있어요.</span></li>
            <li class="flex gap-3 items-start"><span class="mc-num" :style="{ background: t.soft, color: t.accent }">3</span><span class="text-[15px] text-ink-light pt-0.5">내 경험과 팁을 나누면 다른 분께 큰 도움이 돼요.</span></li>
          </ol>
        </div>
      </section>

      <!-- ④ 먼저 읽어보면 좋은 정보: 이 카페 가이드 + 인기 글 -->
      <section v-if="guides.length || popular.length" class="mt-10">
        <h3 class="mc-sec">먼저 읽어보면 좋은 정보</h3>
        <p class="text-ink-muted mb-5">운영자가 정리한 가이드와 회원들이 많이 본 글이에요.</p>
        <div v-if="guides.length" class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
          <component v-for="(g, i) in guides" :key="i" :is="g.ready ? 'RouterLink' : 'div'" :to="g.ready ? `/money/guide/${g.slug}` : undefined"
            class="mc-guide" :class="g.ready ? 'card-hover cursor-pointer' : 'opacity-80'">
            <div class="text-[12px] font-black tracking-wide" :style="{ color: t.accent }">GUIDE {{ String(i + 1).padStart(2, '0') }}</div>
            <h4 class="text-[17px] font-bold text-ink leading-snug mt-2">{{ g.title }}</h4>
            <p v-if="g.summary" class="text-[13px] text-ink-muted mt-1 line-clamp-2">{{ g.summary }}</p>
            <div class="mt-auto pt-3 text-[12px] font-bold" :class="g.ready ? '' : 'text-ink-faint'" :style="g.ready ? { color: t.accent } : {}">{{ g.ready ? '읽어 보기 →' : '운영자 가이드 · 준비 중' }}</div>
          </component>
        </div>
        <div v-if="popular.length" class="card mt-4 p-4">
          <div class="text-sm font-bold text-ink mb-1">🔥 많이 본 글</div>
          <RouterLink v-for="p in popular" :key="p.id" :to="`/money/post/${p.id}`" class="flex items-center gap-2 py-2 text-[14px] text-ink-light hover:text-ink border-b border-gray-50 last:border-0">
            <span class="text-[11px] font-bold rounded-full px-2 py-0.5 shrink-0" :class="KIND_CLASS[p.kind]">{{ KIND_LABEL[p.kind] }}</span>
            <span class="truncate">{{ p.title }}</span><span class="ml-auto text-xs text-ink-faint shrink-0">조회 {{ p.view_count }}</span>
          </RouterLink>
        </div>
      </section>

      <!-- ⑤ 게시판 -->
      <section id="board" class="mt-10 scroll-mt-40">
        <div class="flex items-end justify-between gap-3 flex-wrap mb-3">
          <div>
            <h3 class="mc-sec">{{ cafe.name }} 게시판</h3>
            <p class="text-ink-muted">정보·질문·소통 글을 한곳에서 봐요.</p>
          </div>
          <RouterLink v-if="auth.isLoggedIn" :to="writeLink" class="mc-btn" :style="{ background: t.accent }">✏️ 글쓰기</RouterLink>
          <RouterLink v-else to="/login" class="mc-btn mc-btn-line" :style="{ color: t.ink, borderColor: t.line }">로그인하고 글쓰기</RouterLink>
        </div>

        <!-- 주제 고르기 (가로로 밀기) -->
        <div v-if="categories.length" class="flex gap-1.5 overflow-x-auto scrollbar-hide pb-1 mb-2">
          <button type="button" @click="pickTopic(0, false)" class="mc-chip" :style="!topic ? chipOn : {}">전체</button>
          <button v-for="c in categories" :key="c.id" type="button" @click="pickTopic(c.id, false)" class="mc-chip" :style="topic === c.id ? chipOn : {}">{{ c.icon }} {{ c.name }}</button>
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
            <div class="text-4xl mb-2">{{ art.main }}</div>
            <p class="text-ink-muted">{{ topic ? '이 주제에는 아직 글이 없어요.' : '아직 글이 없어요.' }} 첫 정보를 올려 주세요!</p>
            <RouterLink v-if="auth.isLoggedIn" :to="writeLink" class="mc-btn mt-4" :style="{ background: t.accent }">첫 글 쓰기</RouterLink>
          </div>
          <RouterLink v-for="p in posts" :key="p.id" :to="`/money/post/${p.id}`" class="list-row !px-4 sm:!px-5 !py-3.5 flex items-center gap-3 border-b border-gray-50 last:border-0"
            :class="p.is_pinned ? 'bg-amber-50/40' : ''">
            <div class="min-w-0 flex-1">
              <div class="flex items-center gap-1.5 flex-wrap">
                <span v-if="p.is_pinned" class="text-[11px] font-bold rounded px-1.5 py-0.5 bg-red-50 text-red-600">📌 공지</span>
                <span class="text-[11px] font-bold rounded-full px-2 py-0.5" :class="KIND_CLASS[p.kind]">{{ KIND_LABEL[p.kind] }}</span>
                <span v-if="p.category" class="text-[11px] font-semibold rounded px-1.5 py-0.5 bg-gray-100 text-ink-light">{{ p.category.icon }} {{ p.category.name }}</span>
              </div>
              <div class="text-[15px] font-bold text-ink leading-snug mt-1 truncate">{{ p.title }}</div>
              <div class="text-xs text-ink-muted mt-1">{{ p.user?.display_name || p.user?.name || '회원' }} · {{ fmt(p.created_at) }} · 조회 {{ p.view_count }}</div>
            </div>
            <span class="shrink-0 text-xs font-semibold" :class="p.comment_count ? 'text-amber-700' : 'text-ink-faint'">💬 {{ p.comment_count }}</span>
          </RouterLink>
        </div>
        <div v-if="lastPage > 1" class="flex justify-center gap-1.5 mt-4">
          <button v-for="n in Math.min(lastPage, 10)" :key="n" @click="load(n)" class="w-9 h-9 rounded-lg text-sm" :class="n === page ? 'text-white font-bold' : 'text-ink-muted hover:bg-gray-100'" :style="n === page ? { background: t.accent } : {}">{{ n }}</button>
        </div>
      </section>

      <!-- ⑥ 함께 시작해요 + 이용 규칙(접어 둠) + 다른 카페 -->
      <section class="mt-10 rounded-[22px] p-6 md:p-8 flex flex-col md:flex-row md:items-center justify-between gap-4" :style="{ background: t.soft }">
        <div>
          <h3 class="text-[20px] font-bold text-ink">{{ cafe.name }}, 함께 시작해 볼까요?</h3>
          <p class="text-ink-light mt-1">실생활에 도움이 되는 정보를 배우고, 질문하고, 나눠 보세요.</p>
        </div>
        <RouterLink :to="auth.isLoggedIn ? writeLink : '/login'" class="mc-btn shrink-0 self-start md:self-auto" :style="{ background: t.accent }">{{ auth.isLoggedIn ? '내 경험 나누기 →' : '로그인하고 참여하기 →' }}</RouterLink>
      </section>

      <details v-if="cafe.intro" class="card mt-4 p-4 md:p-5">
        <summary class="cursor-pointer font-bold text-ink">📌 카페 소개·이용 규칙</summary>
        <div class="money-intro mt-3 text-[15px] text-ink-light leading-relaxed" v-html="cafe.intro"></div>
      </details>

      <div v-if="others.length" class="mt-6">
        <div class="text-sm font-bold text-ink mb-2">다른 카페 둘러보기</div>
        <div class="flex flex-wrap gap-2">
          <RouterLink v-for="c in others" :key="c.slug" :to="`/money/cafe/${c.slug}`" class="mc-chip" :style="c.slug === cafe.slug ? chipOn : {}">{{ c.icon }} {{ c.name }}</RouterLink>
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
import { KIND_LABEL, KIND_CLASS, DISCLAIMER } from '../../components/money/moneyText'
import { themeOf, artOf } from '../../components/money/moneyThemes'

const route = useRoute()
const auth = useAuthStore()
const cafe = ref(null); const categories = ref([]); const popular = ref([]); const guides = ref([]); const notFound = ref(false)
const others = ref([])
const posts = ref([]); const page = ref(1); const lastPage = ref(1); const loadingPosts = ref(false)
const kind = ref(''); const search = ref(''); const sort = ref(''); const topic = ref(0)
const kinds = [{ v: '', l: '전체' }, { v: 'info', l: '정보' }, { v: 'question', l: '질문' }, { v: 'talk', l: '소통' }]
const fmt = (s) => (s ? String(s).slice(0, 10) : '')

const t = computed(() => themeOf(cafe.value?.theme))
const art = computed(() => artOf(cafe.value))
const chipOn = computed(() => ({ background: t.value.soft, color: t.value.ink, borderColor: t.value.accent }))
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
    popular.value = data.data.popular || []; guides.value = data.data.guides || []
    load(1)
    if (topic.value) nextTick(() => scrollTo('board'))
  } catch { notFound.value = true }
  if (!others.value.length) {
    try { const { data } = await axios.get('/api/money/cafes'); others.value = data.data || []
      const me = others.value.find(c => c.slug === route.params.slug); if (me && cafe.value) cafe.value.post_count = me.post_count } catch {}
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
// 주제 고르기: 대문 카드에서 누르면 게시판으로 내려간다.
// 주소(?topic=)는 바꾸지 않는다 — App.vue 가 주소 전체를 key 로 써서, 주소가 바뀌면 화면 전체가 새로 그려져 깜빡이고 맨 위로 튐.
// (다른 화면에서 ?topic= 으로 들어오는 것은 그대로 됨)
function pickTopic(id, scroll = true) {
  topic.value = topic.value === id && id ? 0 : id
  load(1)
  if (scroll) nextTick(() => scrollTo('board'))
}
watch(() => route.params.slug, (s, old) => { if (s && s !== old) { kind.value = ''; search.value = ''; sort.value = ''; loadCafe(); window.scrollTo(0, 0) } })
onMounted(loadCafe)
</script>

<style scoped>
.mc-hero { margin-top: 4px; border: 1px solid; border-radius: 26px; padding: clamp(22px, 4.5vw, 56px); display: grid; grid-template-columns: 1.35fr 1fr; gap: 30px; align-items: center; min-height: 340px; }
.mc-h1 { font-size: clamp(34px, 5vw, 60px); font-weight: 900; letter-spacing: -1.5px; line-height: 1.1; margin: 10px 0 10px; color: #182b28; }
.mc-h2 { font-size: clamp(18px, 2vw, 25px); font-weight: 800; line-height: 1.4; margin-bottom: 12px; color: #1f2d2a; }
.mc-btn { display: inline-flex; align-items: center; gap: 6px; color: #fff; padding: 11px 19px; border-radius: 12px; font-weight: 700; font-size: 15px; transition: transform .15s, box-shadow .15s; }
.mc-btn:hover { transform: translateY(-1px); box-shadow: 0 6px 16px rgba(0,0,0,.12); }
.mc-btn-line { background: #fff; border: 1px solid; }
.mc-art { min-height: 250px; border-radius: 26px; position: relative; display: grid; place-items: center; overflow: hidden; }
.mc-art::before, .mc-art::after { content: ''; position: absolute; width: 180px; height: 180px; border: 2px solid rgba(255,255,255,.7); border-radius: 50%; }
.mc-art::before { top: -80px; right: -40px; }
.mc-art::after { bottom: -95px; left: -35px; }
.mc-emoji { font-size: clamp(90px, 12vw, 150px); line-height: 1; filter: drop-shadow(0 14px 14px rgba(40,88,71,.18)); z-index: 1; animation: mc-bob 4s ease-in-out infinite; }
.mc-coin { position: absolute; font-size: 32px; z-index: 2; animation: mc-bob 3.2s ease-in-out infinite; }
.mc-coin-a { left: 15%; top: 14%; }
.mc-coin-b { right: 14%; bottom: 14%; animation-delay: -1.6s; }
@keyframes mc-bob { 0%, 100% { transform: translateY(0) } 50% { transform: translateY(-8px) } }
@media (prefers-reduced-motion: reduce) { .mc-emoji, .mc-coin { animation: none; } }
.mc-sec { font-size: 24px; font-weight: 800; color: #182b28; margin-bottom: 4px; }
.mc-topic { display: flex; flex-direction: column; align-items: flex-start; text-align: left; border: 1px solid #e3ebe6; border-radius: 16px; background: #fff; padding: 16px; min-height: 150px; transition: transform .15s, box-shadow .15s, border-color .15s; }
.mc-topic:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,0,0,.07); }
.mc-panel { background: #fff; border: 1px solid #e3ebe6; border-radius: 20px; padding: 24px; }
.mc-num { width: 26px; height: 26px; border-radius: 9999px; display: inline-grid; place-items: center; font-weight: 800; font-size: 13px; flex-shrink: 0; }
.mc-guide { display: flex; flex-direction: column; background: #fff; border: 1px solid #e3ebe6; border-radius: 18px; padding: 20px; min-height: 140px; }
.mc-chip { flex-shrink: 0; white-space: nowrap; border: 1px solid #e3e6e8; background: #fff; border-radius: 9999px; padding: 5px 12px; font-size: 13px; font-weight: 600; color: #4a5753; }
@media (max-width: 767px) {
  .mc-hero { grid-template-columns: 1fr; gap: 20px; min-height: 0; }
  .mc-art { min-height: 190px; order: -1; }
  .mc-topic { min-height: 132px; padding: 14px; }
  .mc-sec { font-size: 21px; }
}
</style>
