<template>
<div class="min-h-screen bg-gray-50">
  <div class="max-w-4xl mx-auto px-4 py-5">
    <button @click="$router.back()" class="text-sm text-gray-500 hover:text-amber-600 mb-3">← 레시피 목록</button>

    <div v-if="loading" class="text-center py-16 text-gray-400">로딩중...</div>

    <div v-else-if="recipe" class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
      <!-- 히어로 이미지 -->
      <div class="relative h-56 sm:h-72 bg-gradient-to-br from-amber-100 to-orange-100 overflow-hidden">
        <img v-if="recipe.thumbnail" :src="recipe.thumbnail" :alt="recipe.title"
          class="w-full h-full object-cover"
          @error="$event.target.style.display='none'" />
        <div v-else class="w-full h-full flex items-center justify-center text-8xl">🍲</div>
        <div class="absolute inset-0 bg-gradient-to-t from-black/50 to-transparent"></div>
        <div class="absolute bottom-0 left-0 right-0 p-5 text-white">
          <div class="flex items-center gap-2 mb-2">
            <span v-if="recipe.category" class="bg-amber-400 text-amber-900 text-[11px] font-bold px-2 py-0.5 rounded-full">
              {{ recipe.category }}
            </span>
            <span v-if="recipe.cook_method" class="bg-white/20 backdrop-blur text-white text-[11px] font-bold px-2 py-0.5 rounded-full">
              {{ recipe.cook_method }}
            </span>
          </div>
          <h1 class="text-xl sm:text-2xl font-black leading-tight">{{ recipe.title }}</h1>
          <div class="text-[11px] text-white/80 mt-1">👁 {{ recipe.view_count || 0 }}</div>
        </div>
      </div>

      <!-- 영양 정보 -->
      <div class="grid grid-cols-5 border-b">
        <div v-for="item in nutritionItems" :key="item.key"
          class="p-3 text-center border-r last:border-r-0 border-gray-100">
          <div class="text-xs text-gray-400">{{ item.label }}</div>
          <div class="text-sm font-bold text-gray-800 mt-0.5">{{ recipe[item.key] || '-' }}</div>
        </div>
      </div>

      <!-- 재료 -->
      <div v-if="recipe.ingredients" class="px-5 py-4 border-b">
        <h2 class="text-base font-black text-gray-800 mb-2">📝 재료</h2>
        <div class="text-sm text-gray-700 whitespace-pre-wrap leading-relaxed bg-amber-50/50 rounded-lg p-3">{{ recipe.ingredients }}</div>
      </div>

      <!-- 조리 순서 -->
      <div v-if="recipe.steps && recipe.steps.length" class="px-5 py-4 border-b">
        <h2 class="text-base font-black text-gray-800 mb-3">👨‍🍳 조리 순서</h2>
        <div class="space-y-4">
          <div v-for="step in recipe.steps" :key="step.order" class="flex gap-3">
            <div class="flex-shrink-0 w-7 h-7 rounded-full bg-amber-400 text-amber-900 font-black text-xs flex items-center justify-center">
              {{ step.order }}
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-sm text-gray-700 leading-relaxed whitespace-pre-wrap">{{ step.text }}</p>
              <img v-if="step.image_url" :src="step.image_url"
                :alt="'step ' + step.order"
                class="mt-2 rounded-lg max-w-full sm:max-w-sm border"
                @error="$event.target.style.display='none'" />
            </div>
          </div>
        </div>
      </div>

      <!-- 해시태그 -->
      <div v-if="recipe.hash_tags" class="px-5 py-4 border-b">
        <div class="flex flex-wrap gap-1.5">
          <span v-for="tag in parseTags(recipe.hash_tags)" :key="tag"
            class="text-[11px] bg-amber-50 text-amber-700 px-2 py-1 rounded-full">
            #{{ tag }}
          </span>
        </div>
      </div>

      <!-- 출처 -->
      <div class="px-5 py-3 bg-gray-50 text-[11px] text-gray-400 text-center">
        출처: 식품안전나라 조리식품 레시피 DB
      </div>
    </div>

    <div v-else class="text-center py-16 text-gray-400">레시피를 찾을 수 없습니다</div>
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

const nutritionItems = [
  { key: 'calories', label: '칼로리(kcal)' },
  { key: 'carbs', label: '탄수화물(g)' },
  { key: 'protein', label: '단백질(g)' },
  { key: 'fat', label: '지방(g)' },
  { key: 'sodium', label: '나트륨(mg)' },
]

function parseTags(tagStr) {
  if (!tagStr) return []
  return tagStr.split(/[#,\s]+/).map(t => t.trim()).filter(Boolean)
}

onMounted(async () => {
  try {
    const { data } = await axios.get(`/api/recipes/${route.params.id}`)
    recipe.value = data.data || data
  } catch {}
  loading.value = false
})
</script>
