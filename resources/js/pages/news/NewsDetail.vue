<template>
  <div class="min-h-screen bg-gray-50 pb-20">
    <div class="max-w-[1200px] mx-auto px-4 pt-4">
      <!-- Loading -->
      <div v-if="loading" class="text-center py-20 text-gray-400">불러오는 중...</div>

      <template v-else-if="news">
        <!-- Header -->
        <div class="bg-gradient-to-r from-blue-600 to-blue-500 text-white px-6 py-5 rounded-2xl mb-6">
          <button @click="router.push('/news')" class="text-blue-200 text-sm mb-2 hover:text-white transition">&larr; 뉴스 목록</button>
          <span class="bg-white/20 text-xs px-3 py-1 rounded-full ml-2">{{ news.category || '뉴스' }}</span>
          <h1 class="text-2xl font-black mt-3 leading-tight">{{ news.title }}</h1>
          <div class="flex items-center gap-2 mt-3 text-sm text-blue-100">
            <span>{{ news.source }}</span>
            <span v-if="news.source && displayDate">·</span>
            <span>{{ displayDate }}</span>
          </div>
        </div>

        <!-- Article Content -->
        <div class="bg-white rounded-2xl shadow-sm p-6 mb-4">
          <div class="prose prose-sm max-w-none text-gray-700 leading-relaxed whitespace-pre-line">
            {{ news.content || news.summary || '내용을 불러올 수 없습니다.' }}
          </div>

          <!-- Original Source Link -->
          <div v-if="news.url" class="mt-6 pt-4 border-t border-gray-100">
            <a :href="news.url" target="_blank" rel="noopener noreferrer"
              class="inline-flex items-center gap-2 bg-blue-50 text-blue-600 font-medium px-5 py-2.5 rounded-xl hover:bg-blue-100 transition text-sm">
              원본 기사 보기 &rarr;
            </a>
          </div>
        </div>

        <!-- Actions -->
        <div class="bg-white rounded-2xl shadow-sm p-4 mb-4 flex items-center justify-between">
          <div class="flex items-center gap-4">
            <!-- Like -->
            <button @click="toggleLike"
              class="flex items-center gap-1.5 px-4 py-2 rounded-xl text-sm font-medium transition"
              :class="liked ? 'bg-red-50 text-red-500' : 'bg-gray-50 text-gray-500 hover:bg-gray-100'">
              <span>{{ liked ? '&#10084;&#65039;' : '&#9825;' }}</span>
              <span>관심</span>
              <span v-if="likeCount" class="text-xs ml-0.5">({{ likeCount }})</span>
            </button>

            <!-- Bookmark -->
            <button @click="bookmarked = !bookmarked"
              class="flex items-center gap-1.5 px-4 py-2 rounded-xl text-sm font-medium transition"
              :class="bookmarked ? 'bg-yellow-50 text-yellow-600' : 'bg-gray-50 text-gray-500 hover:bg-gray-100'">
              <span>{{ bookmarked ? '&#9733;' : '&#9734;' }}</span>
              <span>저장</span>
            </button>
          </div>

          <!-- Share -->
          <button @click="shareNews"
            class="flex items-center gap-1.5 px-4 py-2 rounded-xl text-sm font-medium bg-gray-50 text-gray-500 hover:bg-gray-100 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" />
            </svg>
            <span>공유</span>
          </button>
        </div>

        <!-- Comments Section -->
        <div class="bg-white rounded-2xl shadow-sm p-6 mb-4">
          <h3 class="font-bold text-gray-800 mb-4">댓글 ({{ comments.length }})</h3>

          <!-- Comment Write Form -->
          <div v-if="auth.isLoggedIn" class="flex gap-2 mb-5">
            <div class="w-8 h-8 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center text-xs font-bold flex-shrink-0">
              {{ auth.user?.name?.charAt(0) || 'U' }}
            </div>
            <div class="flex-1">
              <textarea v-model="newComment" rows="2" placeholder="댓글을 작성하세요..."
                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-blue-400 resize-none"></textarea>
              <div class="flex justify-end mt-1">
                <button @click="submitComment" :disabled="!newComment.trim()"
                  class="px-4 py-1.5 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 transition disabled:opacity-40">
                  등록
                </button>
              </div>
            </div>
          </div>
          <div v-else class="text-center py-3 mb-4 bg-gray-50 rounded-lg">
            <span class="text-sm text-gray-500">댓글을 작성하려면 </span>
            <router-link to="/auth/login" class="text-sm text-blue-600 font-medium">로그인</router-link>
            <span class="text-sm text-gray-500">하세요.</span>
          </div>

          <!-- Comment List -->
          <div v-if="comments.length" class="space-y-4">
            <div v-for="c in comments" :key="c.id" class="flex gap-2">
              <div class="w-8 h-8 rounded-full bg-gray-100 text-gray-500 flex items-center justify-center text-xs font-bold flex-shrink-0">
                {{ c.user?.name?.charAt(0) || 'U' }}
              </div>
              <div class="flex-1">
                <div class="flex items-center gap-2">
                  <span class="text-sm font-medium text-gray-800">{{ c.user?.name || '익명' }}</span>
                  <span class="text-xs text-gray-400">{{ c.created_at_human || '' }}</span>
                </div>
                <p class="text-sm text-gray-600 mt-0.5">{{ c.content }}</p>
              </div>
            </div>
          </div>
          <div v-else class="text-center py-6 text-gray-400 text-sm">첫 번째 댓글을 작성해보세요!</div>
        </div>

        <!-- Related News -->
        <div v-if="relatedNews.length" class="bg-white rounded-2xl shadow-sm p-6 mb-4">
          <h3 class="font-bold text-gray-800 mb-4">관련 뉴스</h3>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div v-for="r in relatedNews" :key="r.id"
              @click="router.push('/news/' + r.id)"
              class="flex gap-3 p-3 rounded-xl hover:bg-gray-50 cursor-pointer transition">
              <div class="w-10 h-10 rounded-lg flex items-center justify-center text-lg flex-shrink-0"
                :style="{ backgroundColor: r.iconBg || '#f3f4f6' }">
                {{ r.icon || '📰' }}
              </div>
              <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-gray-800 line-clamp-2">{{ r.title }}</p>
                <span class="text-xs text-gray-400">{{ r.source }}</span>
              </div>
            </div>
          </div>
        </div>
      </template>

      <!-- Not Found -->
      <div v-else class="text-center py-20">
        <p class="text-gray-400 text-lg mb-4">뉴스를 찾을 수 없습니다.</p>
        <button @click="router.push('/news')" class="text-blue-600 text-sm font-medium">뉴스 목록으로 돌아가기</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import axios from 'axios'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const news = ref(null)
const loading = ref(true)
const comments = ref([])
const newComment = ref('')
const liked = ref(false)
const likeCount = ref(0)
const bookmarked = ref(false)
const relatedNews = ref([])

const categoryIcons = { '정치/사회':'🏛️', '경제':'💰', '생활':'🏥', '문화':'🎭', '스포츠':'⚽', '기타':'📰', '이민/비자':'📋', '경제/취업':'💰', '교육':'📚', '생활정보':'🏥', '한국소식':'🛂', '미국정치':'🏛️' }
const categoryBgs   = { '정치/사회':'#e0e7ff', '경제':'#dcfce7', '생활':'#fef9c3', '문화':'#fce7f3', '스포츠':'#dbeafe', '기타':'#f3f4f6', '이민/비자':'#dbeafe', '경제/취업':'#dcfce7', '교육':'#fef9c3', '생활정보':'#fee2e2', '한국소식':'#e0e7ff', '미국정치':'#f3e8ff' }

const displayDate = computed(() => {
  if (!news.value) return ''
  if (news.value.date) return news.value.date
  const dt = news.value.published_at || news.value.created_at
  if (!dt) return ''
  return new Date(dt).toLocaleDateString('ko-KR', { year: 'numeric', month: 'long', day: 'numeric' })
})

onMounted(async () => {
  try {
    const { data } = await axios.get(`/api/news/${route.params.id}`)
    news.value = data
    comments.value = data.comments || []
    likeCount.value = data.like_count || 0
    liked.value = data.user_liked || false
    bookmarked.value = data.user_bookmarked || false
    loadRelated(data.category)
  } catch {
    // Fallback - show basic info from legacy data
    const legacyItem = _legacy.find(n => n.id == route.params.id)
    if (legacyItem) {
      news.value = legacyItem
    } else {
      news.value = { title: '뉴스 기사', summary: '내용을 불러올 수 없습니다.' }
    }
  } finally {
    loading.value = false
  }
})

const _legacy = [
  { id:1,  category:'이민/비자',  title:'2026년 H-1B 비자 추첨 결과 발표 — 한인 지원자 역대 최다', summary:'USCIS가 올해 H-1B 전산 추첨 결과를 발표했다. 약 78만 건의 등록 중 85,000명이 선정됐으며, 한인 지원자 수는 역대 최다를 기록했다. 전문가들은 AI 산업의 성장으로 기술직 비자 수요가 급증했다고 분석했다.', source:'미주한국일보', date:'2시간 전', icon:'📋', iconBg:'#dbeafe' },
  { id:2,  category:'경제/취업',  title:'LA 한인타운 임금 인상 — 캘리포니아 최저임금 $17로 상향', summary:'캘리포니아 주 최저임금이 올해부터 $17로 올랐다. 한인 식당 및 소규모 업체들의 인건비 부담이 커지는 가운데, 한인타운 상인회는 대응 방안을 논의하고 있다.', source:'코리아타운데일리', date:'4시간 전', icon:'💰', iconBg:'#dcfce7' },
  { id:3,  category:'교육', title:'조지아주 한국어 AP 과목 도입 — 한인 학부모 환영', summary:'조지아 교육부가 한국어를 AP 과목으로 추가했다. 애틀랜타 한인 밀집 지역 학교들을 중심으로 시범 운영이 시작되며, 한인 학부모들은 크게 환영하고 있다.', source:'애틀랜타중앙일보', date:'6시간 전', icon:'📚', iconBg:'#fef9c3' },
  { id:4,  category:'생활정보', title:'미국 의료보험 오픈 엔롤먼트 — 2027년 플랜 비교하기', summary:'11월부터 시작되는 의료보험 오픈 엔롤먼트를 앞두고 한인 보험 전문가들이 주의사항을 안내했다. 올해부터 변경되는 보조금 기준도 함께 확인하자.', source:'미주중앙일보', date:'8시간 전', icon:'🏥', iconBg:'#fee2e2' },
  { id:5,  category:'한국소식', title:'한국 여권 파워 세계 3위 — 미국 비자 면제 지속', summary:'2026년 헨리 여권 지수에서 한국 여권이 세계 3위를 기록했다. 190개국 비자 없이 입국 가능하며, 미국과의 비자 면제 협정도 계속 유지된다.', source:'연합뉴스', date:'1일 전', icon:'🛂', iconBg:'#e0e7ff' },
  { id:6,  category:'미국정치', title:'트럼프 행정부 이민 정책 변화 — 한인 커뮤니티 영향은?', summary:'새 행정부의 이민 정책 변화로 한인 커뮤니티에 미칠 영향을 전문가들이 분석했다. 특히 불법체류자 단속 강화와 합법 이민 경로 변화에 주목해야 한다.', source:'VOA 한국어', date:'1일 전', icon:'🏛️', iconBg:'#f3e8ff' },
  { id:7,  category:'경제/취업', title:'실리콘밸리 한인 스타트업 열풍 — AI 분야 창업 급증', summary:'미국 내 한인 AI 스타트업이 급격히 늘고 있다. 2025년 한 해 동안 한인이 설립한 AI 기업에 약 12억 달러의 투자금이 유입됐다.', source:'한인경제신문', date:'2일 전', icon:'🚀', iconBg:'#dcfce7' },
  { id:8,  category:'생활정보', title:'아마존·코스트코 한국 식품 온라인 구매 가이드', summary:'미국 어디서든 한국 식재료를 구할 수 있는 온라인 쇼핑 방법을 정리했다. 아마존, 코스트코, H마트 온라인 등 다양한 채널을 비교했다.', source:'SomeKorean', date:'2일 전', icon:'🛒', iconBg:'#fef3c7' },
  { id:9,  category:'이민/비자', title:'영주권 처리 기간 단축 — USCIS 인력 확충 계획 발표', summary:'USCIS가 영주권 신청 처리 기간 단축을 위한 인력 확충 계획을 발표했다. 현재 평균 18개월에서 12개월로 단축하는 것이 목표다.', source:'이민법률뉴스', date:'3일 전', icon:'🗂️', iconBg:'#dbeafe' },
  { id:10, category:'교육', title:'SAT 2026 개편 — 디지털 시험 전환 후 첫 전국 결과 분석', summary:'디지털 SAT 전환 이후 첫 전국 결과가 공개됐다. 한인 학생들의 평균 점수 변화와 대비 전략을 분석한다.', source:'교육뉴스코리아', date:'3일 전', icon:'📝', iconBg:'#fef9c3' },
]

async function loadRelated(category) {
  try {
    const { data } = await axios.get('/api/news', { params: { category, limit: 5 } })
    const items = (data.data || data).filter(n => n.id != route.params.id).slice(0, 4)
    relatedNews.value = items.map(n => ({
      ...n,
      icon: categoryIcons[n.category] || '📰',
      iconBg: categoryBgs[n.category] || '#f3f4f6',
    }))
  } catch {
    // Fallback from legacy
    relatedNews.value = _legacy
      .filter(n => n.category === category && n.id != route.params.id)
      .slice(0, 4)
  }
}

async function toggleLike() {
  if (!auth.isLoggedIn) {
    router.push('/auth/login')
    return
  }
  liked.value = !liked.value
  likeCount.value += liked.value ? 1 : -1
  try {
    await axios.post(`/api/news/${route.params.id}/like`)
  } catch { /* silent */ }
}

async function submitComment() {
  if (!newComment.value.trim()) return
  try {
    const { data } = await axios.post(`/api/news/${route.params.id}/comments`, {
      content: newComment.value.trim()
    })
    comments.value.unshift(data.comment || {
      id: Date.now(),
      content: newComment.value.trim(),
      user: auth.user,
      created_at_human: '방금 전'
    })
    newComment.value = ''
  } catch {
    // Optimistic add
    comments.value.unshift({
      id: Date.now(),
      content: newComment.value.trim(),
      user: auth.user,
      created_at_human: '방금 전'
    })
    newComment.value = ''
  }
}

function shareNews() {
  const url = window.location.href
  if (navigator.share) {
    navigator.share({ title: news.value?.title, url })
  } else if (navigator.clipboard) {
    navigator.clipboard.writeText(url)
    alert('링크가 복사되었습니다!')
  }
}
</script>
