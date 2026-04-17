<template>
<div class="min-h-screen bg-gray-50">
  <div class="max-w-7xl mx-auto px-4 py-5">
    <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
      <h1 class="text-xl font-black text-gray-800">🍳 레시피</h1>
      <button @click="$router.push('/recipes')" class="text-sm text-gray-500 hover:text-amber-600">← 레시피 목록</button>
    </div>

    <div class="grid grid-cols-12 gap-4">
      <!-- 왼쪽: 카테고리 -->
      <div class="col-span-12 lg:col-span-2 hidden lg:block">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden sticky top-20">
          <div class="px-3 py-2.5 border-b font-bold text-xs text-amber-900">📋 분류</div>
          <RouterLink to="/recipes"
            class="block w-full text-left px-3 py-2 text-xs text-gray-600 hover:bg-amber-50/50 transition">
            전체
          </RouterLink>
          <RouterLink v-for="c in categories" :key="c.category" :to="`/recipes?category=${encodeURIComponent(c.category)}`"
            class="block w-full text-left px-3 py-2 text-xs transition"
            :class="recipe?.category === c.category ? 'bg-amber-50 text-amber-700 font-bold' : 'text-gray-600 hover:bg-amber-50/50'">
            {{ c.category }} <span class="text-[9px] text-gray-400">({{ c.count }})</span>
          </RouterLink>
          <RouterLink v-if="auth.isLoggedIn" to="/recipes?favorites=1"
            class="block w-full text-left px-3 py-2 text-xs text-gray-600 hover:bg-red-50/50 transition border-t">
            🔖 내 북마크
          </RouterLink>
        </div>
      </div>

      <!-- 중앙: 상세 -->
      <div class="col-span-12 lg:col-span-7">
        <div v-if="loading" class="text-center py-16 text-gray-400">로딩중...</div>

        <div v-else-if="recipe" class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
          <!-- 히어로 이미지 -->
          <div class="relative h-56 sm:h-72 bg-gradient-to-br from-amber-100 to-orange-100 overflow-hidden">
            <img v-if="recipe.thumbnail" :src="recipe.thumbnail" :alt="recipe.title"
              class="w-full h-full object-cover"
              @error="$event.target.style.display='none'" />
            <div v-else class="w-full h-full flex items-center justify-center text-8xl">🍲</div>
            <div class="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent"></div>
            <div class="absolute bottom-0 left-0 right-0 p-5 text-white">
              <div class="flex items-center gap-2 mb-2">
                <span v-if="recipe.category" class="bg-amber-400 text-amber-900 text-[11px] font-bold px-2 py-0.5 rounded-full">
                  {{ recipe.category }}
                </span>
                <span v-if="recipe.cook_method" class="bg-white/20 backdrop-blur text-white text-[11px] font-bold px-2 py-0.5 rounded-full">
                  {{ recipe.cook_method }}
                </span>
              </div>
              <!-- 별점 (타이틀 위) -->
              <div class="flex items-center gap-2 mb-1">
                <span class="text-amber-300 text-sm">{{ '★'.repeat(Math.round(Number(recipe.rating_avg) || 0)) }}{{ '☆'.repeat(5 - Math.round(Number(recipe.rating_avg) || 0)) }}</span>
                <span class="text-xs font-bold">{{ Number(recipe.rating_avg || 0).toFixed(1) }}</span>
                <span class="text-[10px] text-white/70">({{ recipe.rating_count || 0 }}명 평가)</span>
              </div>
              <h1 class="text-xl sm:text-2xl font-black leading-tight">{{ recipe.title }}</h1>
              <div v-if="recipe.title_en" class="text-sm text-white/90 mt-0.5">{{ recipe.title_en }}</div>
              <div class="text-[11px] text-white/80 mt-1 flex items-center gap-3">
                <span>👁 {{ recipe.view_count || 0 }}</span>
                <span>💖 {{ recipe.favorite_count || 0 }}</span>
                <span v-if="recipe.user" class="text-amber-200">by {{ recipe.user.nickname || recipe.user.name }}</span>
              </div>
            </div>
          </div>

          <!-- 액션 버튼 -->
          <div class="px-5 py-3 border-b flex items-center justify-between flex-wrap gap-2">
            <!-- 북마크/공유 -->
            <div class="flex gap-2 items-center">
              <BookmarkToggle v-if="auth.isLoggedIn" :active="recipe.is_favorited" @toggle="toggleFavorite" size="lg" />
              <span v-else class="text-[10px] text-gray-400">로그인 필요</span>
            </div>
            <!-- 소유자 수정/삭제 -->
            <div v-if="isOwner" class="flex gap-2">
              <button @click="$router.push(`/recipes/${recipe.id}/edit`)" class="text-xs bg-blue-500 text-white px-3 py-1.5 rounded-full font-bold">✏️ 수정</button>
              <button @click="deleteRecipe" class="text-xs bg-red-500 text-white px-3 py-1.5 rounded-full font-bold">🗑 삭제</button>
            </div>
          </div>

          <!-- 영양 정보 -->
          <div class="grid grid-cols-5 border-b">
            <div v-for="item in nutritionItems" :key="item.key"
              class="p-3 text-center border-r last:border-r-0 border-gray-100">
              <div class="text-[10px] text-gray-400">{{ item.label }}</div>
              <div class="text-sm font-bold text-gray-800 mt-0.5">{{ recipe[item.key] || '-' }}</div>
            </div>
          </div>

          <!-- 재료 — 구조화 테이블 (있으면) -->
          <div v-if="recipe.ingredients_structured && recipe.ingredients_structured.length" class="px-5 py-4 border-b">
            <h2 class="text-base font-black text-gray-800 mb-3 border-b-2 border-amber-400 pb-1 inline-block">
              재료 <span v-if="recipe.servings" class="text-xs text-gray-500 font-normal">({{ recipe.servings }})</span>
            </h2>
            <div class="divide-y divide-gray-100">
              <div v-for="(ing, idx) in recipe.ingredients_structured" :key="idx"
                class="flex items-center justify-between py-2 text-sm">
                <div class="flex items-baseline gap-2 flex-1 min-w-0">
                  <span class="font-bold text-gray-800">{{ ing.name_ko || ing.name }}</span>
                  <span v-if="ing.name_en" class="text-amber-600 text-xs italic">{{ ing.name_en }}</span>
                </div>
                <span class="text-gray-500 text-sm ml-3 flex-shrink-0">{{ ing.amount || '' }}</span>
              </div>
            </div>
          </div>
          <!-- 재료 — 일반 텍스트 (fallback) -->
          <div v-else-if="recipe.ingredients || recipe.ingredients_en" class="px-5 py-4 border-b">
            <h2 class="text-base font-black text-gray-800 mb-2 border-b-2 border-amber-400 pb-1 inline-block">
              재료 <span v-if="recipe.servings" class="text-xs text-gray-500 font-normal">({{ recipe.servings }})</span>
            </h2>
            <div class="bg-amber-50/50 rounded-lg p-3">
              <div v-if="recipe.ingredients" class="text-sm text-gray-700 whitespace-pre-wrap leading-relaxed">{{ recipe.ingredients }}</div>
              <div v-if="recipe.ingredients_en" class="text-xs text-gray-500 whitespace-pre-wrap leading-relaxed mt-2 pt-2 border-t border-amber-200/50 italic">{{ recipe.ingredients_en }}</div>
            </div>
          </div>

          <!-- 조리 순서 (한영) -->
          <div v-if="recipe.steps && recipe.steps.length" class="px-5 py-4 border-b">
            <h2 class="text-base font-black text-gray-800 mb-3">👨‍🍳 조리 순서</h2>
            <div class="space-y-4">
              <div v-for="step in recipe.steps" :key="step.order" class="flex gap-3">
                <div class="flex-shrink-0 w-7 h-7 rounded-full bg-amber-400 text-amber-900 font-black text-xs flex items-center justify-center">
                  {{ step.order }}
                </div>
                <div class="flex-1 min-w-0">
                  <p class="text-sm text-gray-700 leading-relaxed whitespace-pre-wrap">{{ step.text }}</p>
                  <p v-if="step.text_en" class="text-xs text-gray-500 italic leading-relaxed whitespace-pre-wrap mt-1">{{ step.text_en }}</p>
                  <img v-if="step.image_url" :src="step.image_url"
                    :alt="'step ' + step.order"
                    class="mt-2 rounded-lg max-w-full sm:max-w-sm border"
                    @error="$event.target.style.display='none'" />
                </div>
              </div>
            </div>
          </div>

          <!-- 별점 + 리뷰 -->
          <div class="px-5 py-4 border-b bg-amber-50/30">
            <h2 class="text-base font-black text-gray-800 mb-3">⭐ 별점 & 리뷰 ({{ comments.length }})</h2>

            <!-- 작성 폼 (로그인 시) -->
            <div v-if="!auth.isLoggedIn" class="text-sm text-gray-500 mb-3">로그인 후 별점과 리뷰를 남길 수 있습니다</div>
            <div v-else class="bg-white rounded-lg border border-amber-200 p-3 mb-4">
              <div class="flex items-center gap-2 mb-2">
                <span class="text-xs text-gray-500">내 별점:</span>
                <div class="flex">
                  <button v-for="s in 5" :key="s" @click="setRating(s)"
                    class="text-2xl transition hover:scale-110"
                    :class="s <= (hoverRating || myRating || 0) ? 'text-amber-400' : 'text-gray-300'"
                    @mouseenter="hoverRating = s" @mouseleave="hoverRating = 0">
                    ★
                  </button>
                </div>
                <span v-if="myRating" class="text-xs text-amber-600 font-bold">{{ myRating }}점</span>
              </div>
              <textarea v-model="myComment" rows="2" placeholder="이 레시피에 대한 리뷰를 남겨주세요 (선택)"
                class="w-full border rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-amber-400 resize-none"></textarea>
              <div class="flex justify-end mt-2 gap-2">
                <span v-if="ratingMsg" class="text-xs text-green-600 font-bold self-center">{{ ratingMsg }}</span>
                <button @click="submitRating" :disabled="!myRating || submittingRating"
                  class="bg-amber-400 text-amber-900 font-bold px-4 py-1.5 rounded-lg text-xs disabled:opacity-50">
                  {{ submittingRating ? '저장중...' : '등록' }}
                </button>
              </div>
            </div>

            <!-- 댓글 리스트 -->
            <div v-if="loadingComments" class="text-center py-4 text-xs text-gray-400">로딩중...</div>
            <div v-else-if="!comments.length" class="text-center py-4 text-xs text-gray-400">첫 번째 리뷰를 남겨보세요</div>
            <div v-else class="space-y-3">
              <div v-for="c in comments" :key="c.id" class="bg-white rounded-lg border p-3">
                <div class="flex items-start justify-between gap-2">
                  <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-full bg-amber-200 flex items-center justify-center text-sm font-bold text-amber-800 flex-shrink-0">
                      {{ (c.user?.nickname || c.user?.name || '?')[0] }}
                    </div>
                    <div>
                      <div class="text-xs font-bold text-gray-800">{{ c.user?.nickname || c.user?.name || '익명' }}</div>
                      <div class="flex items-center gap-1">
                        <span class="text-amber-400 text-[11px]">{{ '★'.repeat(c.rating) }}{{ '☆'.repeat(5 - c.rating) }}</span>
                        <span class="text-[10px] text-gray-400">{{ fmtDate(c.created_at) }}</span>
                      </div>
                    </div>
                  </div>
                  <button v-if="auth.user && c.user_id === auth.user.id" @click="deleteComment(c.id)"
                    class="text-[10px] text-red-400 hover:text-red-600">삭제</button>
                </div>
                <div v-if="c.comment" class="text-sm text-gray-700 mt-2 whitespace-pre-wrap leading-relaxed">{{ c.comment }}</div>
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
            {{ recipe.source === 'user' ? '작성: ' + (recipe.user?.nickname || recipe.user?.name || '회원') : '출처: 식품안전나라 조리식품 레시피 DB' }}
          </div>
        </div>

        <div v-else class="text-center py-16 text-gray-400">레시피를 찾을 수 없습니다</div>
      </div>

      <!-- 오른쪽: 사이드바 위젯 -->
      <div class="col-span-12 lg:col-span-3 hidden lg:block">
        <SidebarWidgets mode="detail" :currentCategory="recipe?.category || ''" api-url="/api/recipes" detail-path="/recipes/" :current-id="recipe?.id || 0"
          label="레시피"
          :second-tab="{ label: '⭐ 별점 순', sort: 'rating' }" />
      </div>
    </div>
  </div>
</div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import SidebarWidgets from '../../components/SidebarWidgets.vue'
import axios from 'axios'
import BookmarkToggle from '../../components/BookmarkToggle.vue'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()
const recipe = ref(null)
const loading = ref(true)
const categories = ref([])
const hoverRating = ref(0)
const ratingMsg = ref('')
const myRating = ref(0)
const myComment = ref('')
const submittingRating = ref(false)
const comments = ref([])
const loadingComments = ref(false)

const nutritionItems = [
  { key: 'calories', label: '칼로리(kcal)' },
  { key: 'carbs', label: '탄수화물(g)' },
  { key: 'protein', label: '단백질(g)' },
  { key: 'fat', label: '지방(g)' },
  { key: 'sodium', label: '나트륨(mg)' },
]

const isOwner = computed(() => {
  return auth.user && recipe.value?.user_id === auth.user.id
})

function parseTags(tagStr) {
  if (!tagStr) return []
  return tagStr.split(/[#,\s]+/).map(t => t.trim()).filter(Boolean)
}

async function loadRecipe(id) {
  loading.value = true
  recipe.value = null
  comments.value = []
  try {
    const { data } = await axios.get(`/api/recipes/${id}`)
    recipe.value = data.data || data
    myRating.value = recipe.value?.my_rating || 0
    await loadComments()
  } catch (err) {
    if (err.response?.status === 404) router.replace('/404')
  }
  loading.value = false
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

async function loadCategories() {
  try {
    const { data } = await axios.get('/api/recipes/categories')
    categories.value = data.data || []
  } catch {}
}

async function toggleFavorite() {
  if (!auth.isLoggedIn || !recipe.value) return
  try {
    const { data } = await axios.post(`/api/recipes/${recipe.value.id}/favorite`)
    recipe.value.is_favorited = data.is_favorited
    recipe.value.favorite_count = data.favorite_count
  } catch (e) {
    alert(e.response?.data?.message || '실패')
  }
}

function setRating(stars) {
  myRating.value = stars
}

async function submitRating() {
  if (!auth.isLoggedIn || !recipe.value || !myRating.value) return
  submittingRating.value = true
  try {
    const { data } = await axios.post(`/api/recipes/${recipe.value.id}/rate`, {
      rating: myRating.value,
      comment: myComment.value,
    })
    recipe.value.my_rating = data.my_rating
    recipe.value.rating_avg = data.rating_avg
    recipe.value.rating_count = data.rating_count
    ratingMsg.value = '등록 완료!'
    myComment.value = ''
    setTimeout(() => { ratingMsg.value = '' }, 2000)
    await loadComments()
  } catch (e) {
    alert(e.response?.data?.message || '실패')
  }
  submittingRating.value = false
}

async function loadComments() {
  if (!recipe.value) return
  loadingComments.value = true
  try {
    const { data } = await axios.get(`/api/recipes/${recipe.value.id}/comments`)
    comments.value = data.data?.data || []
  } catch {}
  loadingComments.value = false
}

async function deleteComment(commentId) {
  if (!confirm('이 리뷰를 삭제할까요?')) return
  try {
    await axios.delete(`/api/recipes/${recipe.value.id}/comments/${commentId}`)
    await loadComments()
    // 평점도 갱신
    const { data } = await axios.get(`/api/recipes/${recipe.value.id}`)
    recipe.value.rating_avg = data.data?.rating_avg
    recipe.value.rating_count = data.data?.rating_count
    recipe.value.my_rating = data.data?.my_rating
    if (!data.data?.my_rating) myRating.value = 0
  } catch (e) { alert(e.response?.data?.message || '실패') }
}

function fmtDate(dt) {
  if (!dt) return ''
  const d = new Date(dt)
  const diff = Date.now() - d.getTime()
  if (diff < 60000) return '방금'
  if (diff < 3600000) return Math.floor(diff / 60000) + '분 전'
  if (diff < 86400000) return Math.floor(diff / 3600000) + '시간 전'
  return d.toLocaleDateString('ko-KR')
}

async function deleteRecipe() {
  if (!confirm('이 레시피를 삭제하시겠습니까?')) return
  try {
    await axios.delete(`/api/recipes/${recipe.value.id}`)
    router.push('/recipes')
  } catch (e) {
    alert(e.response?.data?.message || '실패')
  }
}

watch(() => route.params.id, (newId) => {
  if (newId) loadRecipe(newId)
})

onMounted(() => {
  loadCategories()
  loadRecipe(route.params.id)
})
</script>
