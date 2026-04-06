<template>
<div class="min-h-screen bg-gray-50">
  <div class="max-w-4xl mx-auto px-4 py-5">
    <button @click="$router.back()" class="text-sm text-gray-500 hover:text-amber-600 mb-3">← 뉴스 목록</button>
    <div v-if="loading" class="text-center py-12 text-gray-400">로딩중...</div>
    <div v-else-if="news" class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
      <div v-if="news.image_url" class="h-56 bg-gray-200 overflow-hidden">
        <img :src="news.image_url" class="w-full h-full object-cover" @error="e=>e.target.style.display='none'" />
      </div>
      <div class="px-5 py-4">
        <div class="flex items-center gap-2 mb-2">
          <span class="text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full font-semibold">{{ news.category?.name }}</span>
          <span class="text-xs text-gray-400">{{ news.source }}</span>
          <span class="text-xs text-gray-400">{{ formatDate(news.published_at) }}</span>
        </div>
        <h1 class="text-lg font-bold text-gray-900 leading-snug">{{ news.title }}</h1>
        <div class="text-xs text-gray-400 mt-2">👁 {{ news.view_count }}조회</div>
      </div>
      <div class="px-5 py-5 border-t text-sm text-gray-700 leading-relaxed whitespace-pre-wrap">{{ news.content }}</div>
      <div v-if="news.source_url" class="px-5 py-3 border-t">
        <a :href="news.source_url" target="_blank" class="text-amber-600 text-sm hover:underline">📎 원문 보기 →</a>
      </div>
    </div>
  </div>
</div>
</template>
<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import axios from 'axios'
const route = useRoute()
const news = ref(null)
const loading = ref(true)
function formatDate(dt) { return dt ? new Date(dt).toLocaleDateString('ko-KR') : '' }
onMounted(async () => {
  try { const { data } = await axios.get(`/api/news/${route.params.id}`); news.value = data.data } catch {}
  loading.value = false
})
</script>
