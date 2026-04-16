<template>
<div class="min-h-screen bg-gray-50">
  <div class="max-w-7xl mx-auto px-4 py-5">
    <button @click="$router.back()" class="text-sm text-gray-500 hover:text-amber-600 mb-3">← 뉴스 목록</button>
    <div v-if="loading" class="text-center py-12 text-gray-400">로딩중...</div>
    <div v-else-if="news" class="grid grid-cols-12 gap-4">

      <!-- 왼쪽: 카테고리 -->
      <div class="col-span-12 lg:col-span-2 hidden lg:block">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden sticky top-20">
          <div class="px-3 py-2.5 border-b font-bold text-xs text-amber-900">📋 카테고리</div>
          <RouterLink to="/news" class="block px-3 py-2 text-xs text-gray-600 hover:bg-amber-50/50 transition">전체</RouterLink>
          <RouterLink v-for="cat in categories" :key="cat.id" :to="`/news?category=${cat.id}`"
            class="block px-3 py-2 text-xs transition"
            :class="news.category_id === cat.id ? 'bg-amber-50 text-amber-700 font-bold' : 'text-gray-600 hover:bg-amber-50/50'">
            {{ cat.name }}
          </RouterLink>
        </div>
      </div>

      <!-- 메인 콘텐츠 -->
      <div class="col-span-12 lg:col-span-7">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
          <div class="px-5 py-4">
            <div class="flex items-center gap-2 mb-2">
              <span class="text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full font-semibold">{{ news.category?.name || '뉴스' }}</span>
              <span class="text-xs text-gray-400">{{ news.source }}</span>
            </div>
            <h1 class="text-lg font-bold text-gray-900 leading-snug">{{ news.title }}</h1>
            <div class="flex items-center gap-3 mt-2 text-xs text-gray-400">
              <span>{{ formatDate(news.published_at) }}</span>
              <span>👁 {{ news.view_count }}회</span>
            </div>
          </div>
          <div v-if="news.image_url" class="px-5 pb-3">
            <img :src="news.image_url" class="w-full max-h-96 object-cover rounded-lg" @error="e=>e.target.style.display='none'" />
          </div>
          <div class="px-5 py-5 border-t text-sm text-gray-700 leading-relaxed whitespace-pre-wrap">{{ news.content }}</div>
          <div v-if="news.source_url" class="px-5 py-3 border-t">
            <a :href="news.source_url" target="_blank" class="text-amber-600 text-sm hover:underline">📎 원문 보기 →</a>
          </div>
        </div>

        <CommentSection v-if="news.id" :type="'news'" :typeId="news.id" class="mt-4" />
      </div>

      <!-- 오른쪽: 위젯 -->
      <div class="col-span-12 lg:col-span-3 hidden lg:block">
        <SidebarWidgets mode="detail" :currentCategory="news?.source || ''" api-url="/api/news" detail-path="/news/" :current-id="news.id"
          label="뉴스" recommend-label="좋아할 기사" quick-label="실시간 뉴스"
          :links="[{to:'/news',icon:'📰',label:'전체 뉴스'},{to:'/community',icon:'💬',label:'커뮤니티'}]" />
      </div>
    </div>
  </div>
</div>
</template>
<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import SidebarWidgets from '../../components/SidebarWidgets.vue'
import CommentSection from '../../components/CommentSection.vue'
import axios from 'axios'

const route = useRoute()
const news = ref(null)
const categories = ref([])
const loading = ref(true)

function formatDate(dt) { return dt ? new Date(dt).toLocaleDateString('ko-KR') : '' }

onMounted(async () => {
  try {
    const [nRes, cRes] = await Promise.allSettled([
      axios.get(`/api/news/${route.params.id}`),
      axios.get('/api/news/categories'),
    ])
    if (nRes.status === 'fulfilled') news.value = nRes.value.data?.data
    if (cRes.status === 'fulfilled') categories.value = cRes.value.data?.data || []
  } catch {}
  loading.value = false
})
</script>
