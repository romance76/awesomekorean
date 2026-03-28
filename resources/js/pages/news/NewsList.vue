<template>
  <div class="min-h-screen bg-gray-50 pb-20">
    <div class="max-w-[1200px] mx-auto px-4 pt-4">
      <div class="bg-gradient-to-r from-blue-600 to-blue-500 text-white px-6 py-6 rounded-2xl">
        <h1 class="text-xl font-black">📰 한인 뉴스</h1>
        <p class="text-blue-100 text-sm mt-0.5">미국 한인 커뮤니티 소식</p>
      </div>
    </div>

    <!-- 카테고리 -->
    <div class="max-w-[1200px] mx-auto flex overflow-x-auto bg-white border-b px-4 gap-2 py-2">
      <button v-for="cat in categories" :key="cat"
        @click="activeCategory = cat; loadNews()"
        class="flex-shrink-0 px-3 py-1 rounded-full text-sm transition"
        :class="activeCategory === cat ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600'">
        {{ cat }}
      </button>
    </div>

    <div class="max-w-[1200px] mx-auto px-4 py-4 space-y-3">
      <div v-if="loading" class="text-center py-10 text-gray-400">불러오는 중...</div>
      <div v-else>
        <!-- 헤드라인 (첫 번째 뉴스) -->
        <div v-if="news.length" @click="openNews(news[0])" class="bg-white rounded-2xl shadow-sm overflow-hidden mb-4 cursor-pointer">
          <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-4 py-6 text-white">
            <span class="bg-white/20 text-xs px-2 py-0.5 rounded-full">{{ news[0].category }}</span>
            <h2 class="mt-2 font-bold text-lg leading-snug">{{ news[0].title }}</h2>
            <p class="text-blue-100 text-sm mt-1 line-clamp-2">{{ news[0].summary }}</p>
            <div class="flex items-center justify-between mt-3">
              <span class="text-xs text-blue-200">{{ news[0].source }} · {{ news[0].date }}</span>
            </div>
          </div>
        </div>

        <!-- 뉴스 리스트 -->
        <div v-for="item in news.slice(1)" :key="item.id"
          @click="openNews(item)"
          class="bg-white rounded-xl shadow-sm p-4 flex gap-3 cursor-pointer">
          <div class="w-16 h-16 rounded-xl flex items-center justify-center flex-shrink-0 text-2xl"
            :style="{ backgroundColor: item.iconBg }">{{ item.icon }}</div>
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-1 mb-1">
              <span class="text-xs text-blue-600 font-medium">{{ item.category }}</span>
              <span class="text-gray-300 text-xs">·</span>
              <span class="text-xs text-gray-400">{{ item.source }}</span>
            </div>
            <h3 class="font-medium text-gray-800 text-sm leading-snug line-clamp-2">{{ item.title }}</h3>
            <p class="text-gray-500 text-xs mt-0.5 line-clamp-1">{{ item.summary }}</p>
            <div class="text-gray-400 text-xs mt-1">{{ item.date }}</div>
          </div>
        </div>

        <div v-if="!news.length" class="text-center py-10 text-gray-400">뉴스가 없습니다.</div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'

const router = useRouter()

const categories   = ['전체', '정치/사회', '경제', '생활', '문화', '스포츠', '기타']
const activeCategory = ref('전체')
const loading = ref(false)
const news    = ref([])

const categoryIcons = { '정치/사회':'🏛️', '경제':'💰', '생활':'🏥', '문화':'🎭', '스포츠':'⚽', '기타':'📰' }
const categoryBgs   = { '정치/사회':'#e0e7ff', '경제':'#dcfce7', '생활':'#fef9c3', '문화':'#fce7f3', '스포츠':'#dbeafe', '기타':'#f3f4f6' }

const _legacy = [
  { id:1,  category:'이민/비자',  title:'2026년 H-1B 비자 추첨 결과 발표 — 한인 지원자 역대 최다',         summary:'USCIS가 올해 H-1B 전산 추첨 결과를 발표했다. 약 78만 건의 등록 중 85,000명이 선정됐으며...',     source:'미주한국일보', date:'2시간 전', icon:'📋', iconBg:'#dbeafe' },
  { id:2,  category:'경제/취업',  title:'LA 한인타운 임금 인상 — 캘리포니아 최저임금 $17로 상향',          summary:'캘리포니아 주 최저임금이 올해부터 $17로 올랐다. 한인 식당 및 소규모 업체들의 인건비 부담이 커지는 가운데...', source:'코리아타운데일리', date:'4시간 전', icon:'💰', iconBg:'#dcfce7' },
  { id:3,  category:'교육',       title:'조지아주 한국어 AP 과목 도입 — 한인 학부모 환영',                  summary:'조지아 교육부가 한국어를 AP 과목으로 추가했다. 애틀랜타 한인 밀집 지역 학교들을 중심으로...', source:'애틀랜타중앙일보', date:'6시간 전', icon:'📚', iconBg:'#fef9c3' },
  { id:4,  category:'생활정보',   title:'미국 의료보험 오픈 엔롤먼트 — 2027년 플랜 비교하기',             summary:'11월부터 시작되는 의료보험 오픈 엔롤먼트를 앞두고 한인 보험 전문가들이 주의사항을 안내했다...', source:'미주중앙일보', date:'8시간 전', icon:'🏥', iconBg:'#fee2e2' },
  { id:5,  category:'한국소식',   title:'한국 여권 파워 세계 3위 — 미국 비자 면제 지속',                  summary:'2026년 헨리 여권 지수에서 한국 여권이 세계 3위를 기록했다. 190개국 비자 없이 입국 가능...', source:'연합뉴스', date:'1일 전', icon:'🛂', iconBg:'#e0e7ff' },
  { id:6,  category:'미국정치',   title:'트럼프 행정부 이민 정책 변화 — 한인 커뮤니티 영향은?',            summary:'새 행정부의 이민 정책 변화로 한인 커뮤니티에 미칠 영향을 전문가들이 분석했다...', source:'VOA 한국어', date:'1일 전', icon:'🏛️', iconBg:'#f3e8ff' },
  { id:7,  category:'경제/취업',  title:'실리콘밸리 한인 스타트업 열풍 — AI 분야 창업 급증',              summary:'미국 내 한인 AI 스타트업이 급격히 늘고 있다. 2025년 한 해 동안 한인이 설립한 AI 기업에...',  source:'한인경제신문', date:'2일 전', icon:'🚀', iconBg:'#dcfce7' },
  { id:8,  category:'생활정보',   title:'아마존·코스트코 한국 식품 온라인 구매 가이드',                   summary:'미국 어디서든 한국 식재료를 구할 수 있는 온라인 쇼핑 방법을 정리했다...',             source:'SomeKorean', date:'2일 전', icon:'🛒', iconBg:'#fef3c7' },
  { id:9,  category:'이민/비자',  title:'영주권 처리 기간 단축 — USCIS 인력 확충 계획 발표',             summary:'USCIS가 영주권 신청 처리 기간 단축을 위한 인력 확충 계획을 발표했다. 현재 평균 18개월에서...', source:'이민법률뉴스', date:'3일 전', icon:'🗂️', iconBg:'#dbeafe' },
  { id:10, category:'교육',       title:'SAT 2026 개편 — 디지털 시험 전환 후 첫 전국 결과 분석',         summary:'디지털 SAT 전환 이후 첫 전국 결과가 공개됐다. 한인 학생들의 평균 점수 변화와 대비 전략...',  source:'교육뉴스코리아', date:'3일 전', icon:'📝', iconBg:'#fef9c3' },
]

async function loadNews() {
  loading.value = true
  try {
    const params = {}
    if (activeCategory.value !== '전체') params.category = activeCategory.value
    const { data } = await axios.get('/api/news', { params })
    news.value = (data.data || data).map(n => ({
      ...n,
      icon: categoryIcons[n.category] || '📰',
      iconBg: categoryBgs[n.category] || '#f3f4f6',
      date: timeAgo(n.published_at || n.created_at),
    }))
  } catch {
    // Fallback to legacy data
    news.value = _legacy
  } finally {
    loading.value = false
  }
}

function timeAgo(dt) {
  if (!dt) return ''
  const diff = Date.now() - new Date(dt).getTime()
  const hrs = Math.floor(diff / 3600000)
  if (hrs < 1) return '방금 전'
  if (hrs < 24) return `${hrs}시간 전`
  const days = Math.floor(hrs / 24)
  if (days < 30) return `${days}일 전`
  return `${Math.floor(days/30)}개월 전`
}

function openNews(item) {
  router.push('/news/' + item.id)
}

onMounted(loadNews)
</script>
