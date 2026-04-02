<template>
  <div class="min-h-screen bg-gray-50 pb-16">
    <div class="max-w-[1200px] mx-auto px-4 pt-4">
      <div class="bg-gradient-to-r from-blue-600 to-blue-500 text-white px-6 py-5 rounded-2xl">
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
        <!-- 헤드라인 (첫 번째 뉴스) - 이미지 왼쪽 / 텍스트 오른쪽 -->
        <div v-if="news.length" @click="openNews(news[0])"
          class="bg-white rounded-2xl shadow-sm overflow-hidden mb-4 cursor-pointer hover:shadow-md transition flex">
          <!-- 이미지: 왼쪽 고정 크기 -->
          <div class="flex-shrink-0 overflow-hidden bg-gray-100" style="width:200px; min-height:150px">
            <img v-if="news[0].image_url" :src="news[0].image_url"
              class="w-full h-full object-cover object-center" style="min-height:150px"
              @error="news[0].image_url = null" />
            <div v-else class="w-full h-full flex items-center justify-center text-5xl"
              style="min-height:150px"
              :style="{ background: 'linear-gradient(135deg, ' + catBg(news[0].category) + ' 0%, #dbeafe 100%)' }">
              {{ news[0].icon }}
            </div>
          </div>
          <!-- 텍스트: 오른쪽 -->
          <div class="flex-1 min-w-0 px-4 py-3 flex flex-col justify-between">
            <div>
              <div class="flex items-center gap-2 mb-2">
                <span class="text-[11px] font-bold px-2 py-0.5 rounded-full"
                  :style="{ backgroundColor: catBg(news[0].category), color: catFg(news[0].category) }">
                  {{ news[0].category }}
                </span>
                <span class="text-xs text-gray-400">{{ news[0].source }}</span>
              </div>
              <h2 class="font-bold text-[15px] text-gray-900 leading-snug line-clamp-3 mb-2">{{ news[0].title }}</h2>
              <p v-if="cleanSummary(news[0].summary)" class="text-gray-500 text-sm line-clamp-3">
                {{ cleanSummary(news[0].summary) }}
              </p>
            </div>
            <span class="text-xs text-gray-400 mt-2">{{ news[0].date }}</span>
          </div>
        </div>

        <!-- 뉴스 리스트 -->
        <div v-for="item in news.slice(1)" :key="item.id"
          @click="openNews(item)"
          class="bg-white rounded-xl shadow-sm p-3 flex gap-3 cursor-pointer hover:bg-blue-50/30 transition">
          <template v-if="item.image_url">
            <img :src="item.image_url" class="w-20 h-16 rounded-xl object-cover flex-shrink-0" @error="item.image_url = null">
          </template>
          <div v-else class="w-20 h-16 rounded-xl flex items-center justify-center flex-shrink-0 text-2xl"
            :style="{ backgroundColor: item.iconBg }">{{ item.icon }}</div>
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-1 mb-1">
              <span class="text-[11px] font-semibold px-1.5 py-0.5 rounded-full"
                :style="{ backgroundColor: catBg(item.category), color: catFg(item.category) }">
                {{ item.category }}
              </span>
              <span class="text-gray-300 text-xs">·</span>
              <span class="text-xs text-gray-400">{{ item.source }}</span>
            </div>
            <h3 class="font-semibold text-gray-800 text-sm leading-snug line-clamp-2">{{ item.title }}</h3>
            <p class="text-gray-500 text-xs mt-0.5 line-clamp-1">{{ cleanSummary(item.summary) }}</p>
            <div class="text-gray-400 text-[11px] mt-1">{{ item.date }}</div>
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

// 이민/비자, 미국생활을 앞에 배치 — 한인들에게 가장 중요한 카테고리
const categories = ['전체', '이민/비자', '미국생활', '정치/사회', '경제', '생활', '문화', '스포츠', '기타']
const activeCategory = ref('전체')
const loading = ref(false)
const news    = ref([])

const categoryIcons = {
  '이민/비자':  '🛂',
  '미국생활':   '🏠',
  '정치/사회':  '🏛️',
  '경제':       '💰',
  '생활':       '🏥',
  '문화':       '🎭',
  '스포츠':     '⚽',
  '기타':       '📰',
}
const categoryBgs = {
  '이민/비자':  '#ede9fe',
  '미국생활':   '#dbeafe',
  '정치/사회':  '#e0e7ff',
  '경제':       '#dcfce7',
  '생활':       '#fef9c3',
  '문화':       '#fce7f3',
  '스포츠':     '#dbeafe',
  '기타':       '#f3f4f6',
}
const categoryFgColors = {
  '이민/비자':  '#6d28d9',
  '미국생활':   '#1d4ed8',
  '정치/사회':  '#4338ca',
  '경제':       '#15803d',
  '생활':       '#a16207',
  '문화':       '#be185d',
  '스포츠':     '#1d4ed8',
  '기타':       '#374151',
}

function catBg(cat)    { return categoryBgs[cat]      || '#f3f4f6' }
function catFg(cat)    { return categoryFgColors[cat] || '#374151' }
function catColor(cat) { return categoryBgs[cat]      || 'rgba(255,255,255,0.2)' }

/**
 * 한겨레 등 한국 뉴스의 summary 필드에 포함된 쓰레기 텍스트 제거
 * - "본문[섹션명]..." 접두사
 * - "기자수정 YYYY-MM-DD HH:MM등록..." 메타데이터
 * - "기사를 읽어드립니다...0:00" TTS 텍스트
 */
function cleanSummary(text) {
  if (!text) return ''
  let t = text

  // 오디오 TTS 텍스트 이전 모든 내용 제거 (가장 확실한 경계)
  t = t.replace(/^[\s\S]*?기사를\s*읽어드립니다[\s\S]*?\d+:\d+\s*/m, '')

  // 남아있는 오디오 텍스트 잔재
  t = t.replace(/기사를\s*읽어드립니다/g, '')
  t = t.replace(/Your browser does not support\s*the?\s*audio element\.?/gi, '')

  // "기자수정 YYYY-MM-DD HH:MM등록 YYYY-MM-DD HH:MM" 메타데이터
  t = t.replace(/[가-힣]{1,10}기자\s*수정\s*\d{4}[-./]\d{2}[-./]\d{2}[\s\S]*?등록\s*\d{4}[-./]\d{2}[-./]\d{2}\s*\d{2}:\d{2}\s*/g, '')
  t = t.replace(/수정\s*\d{4}[-./]\d{2}[-./]\d{2}[\s\S]*?등록\s*\d{4}[-./]\d{2}[-./]\d{2}\s*\d{2}:\d{2}\s*/g, '')

  // "본문[섹션][섹션]..." 접두사
  t = t.replace(/^본문[가-힣\s]{2,40}/g, '')

  // 단독 "광고" 텍스트
  t = t.replace(/(?<![가-힣])광고(?![가-힣])/g, '')

  // 타임스탬프 잔재 (0:00 형태)
  t = t.replace(/^\s*\d+:\d{2}\s*/g, '')

  t = t.trim()

  // 120자로 자르기
  if (t.length > 120) t = t.slice(0, 120) + '...'
  return t
}

async function loadNews() {
  loading.value = true
  try {
    const params = {}
    if (activeCategory.value !== '전체') params.category = activeCategory.value
    const { data } = await axios.get('/api/news', { params })
    news.value = (data.data || data).map(n => ({
      ...n,
      icon:   categoryIcons[n.category] || '📰',
      iconBg: categoryBgs[n.category]   || '#f3f4f6',
      date:   timeAgo(n.published_at || n.created_at),
    }))
  } catch {
    news.value = []
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
