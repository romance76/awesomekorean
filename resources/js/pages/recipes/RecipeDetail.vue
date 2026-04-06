<template>
<div class="min-h-screen bg-gray-50">
  <div class="max-w-4xl mx-auto px-4 py-5">
    <button @click="$router.back()" class="text-sm text-gray-500 hover:text-amber-600 mb-3">← 레시피 목록</button>
    <div v-if="loading" class="text-center py-12 text-gray-400">로딩중...</div>
    <div v-else-if="recipe" class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
      <div class="px-5 py-4 border-b">
        <div class="flex items-center gap-2 mb-2">
          <span class="text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full font-semibold">{{ recipe.category?.name }}</span>
          <span class="text-xs px-2 py-0.5 rounded-full" :class="recipe.difficulty==='easy'?'bg-green-100 text-green-700':recipe.difficulty==='hard'?'bg-red-100 text-red-700':'bg-amber-100 text-amber-700'">
            {{ {easy:'쉬움',medium:'보통',hard:'어려움'}[recipe.difficulty] }}
          </span>
        </div>
        <h1 class="text-lg font-bold text-gray-900">🍳 {{ recipe.title }}</h1>
        <div class="flex gap-4 mt-2 text-xs text-gray-500">
          <span>🍽 {{ recipe.servings }}인분</span>
          <span>⏱ 준비 {{ recipe.prep_time }}분</span>
          <span>🔥 조리 {{ recipe.cook_time }}분</span>
          <span>❤️ {{ recipe.like_count }}</span>
          <span>👁 {{ recipe.view_count }}</span>
        </div>
      </div>
      <div class="px-5 py-4 border-b">
        <h3 class="font-bold text-sm text-gray-800 mb-2">📝 재료</h3>
        <ul v-if="recipe.ingredients" class="list-disc pl-5 text-sm text-gray-700 space-y-1">
          <li v-for="(item, i) in recipe.ingredients" :key="i">{{ item }}</li>
        </ul>
      </div>
      <div class="px-5 py-4 border-b">
        <h3 class="font-bold text-sm text-gray-800 mb-2">👨‍🍳 조리 순서</h3>
        <ol v-if="recipe.steps" class="list-decimal pl-5 text-sm text-gray-700 space-y-2">
          <li v-for="(step, i) in recipe.steps" :key="i">{{ step }}</li>
        </ol>
      </div>
      <div class="px-5 py-4 text-sm text-gray-700 whitespace-pre-wrap">{{ recipe.content }}</div>
      <div class="px-5 py-3 border-t text-xs text-gray-400">{{ recipe.user?.name }} · {{ formatDate(recipe.created_at) }}</div>
    </div>
  </div>
</div>
</template>
<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import axios from 'axios'
const route = useRoute()
const recipe = ref(null)
const loading = ref(true)
function formatDate(dt) { return dt ? new Date(dt).toLocaleDateString('ko-KR') : '' }
onMounted(async () => {
  try { const { data } = await axios.get(`/api/recipes/${route.params.id}`); recipe.value = data.data } catch {}
  loading.value = false
})
</script>
