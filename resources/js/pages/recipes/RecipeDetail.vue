<template>
<div class="min-h-screen">
  <div class="max-w-7xl mx-auto px-4 py-5">
    <DetailHeader :title="recipe?.title || '레시피'" fallback="/recipes" />
    <div class="hidden lg:flex items-center justify-between mb-4 flex-wrap gap-2">
      <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink">
        <span class="icon-chip w-9 h-9 bg-orange-50 text-orange-600"><AppIcon name="utensils" :size="20" /></span>
        레시피
      </h1>
      <button @click="$router.push('/recipes')" class="text-sm text-ink-muted hover:text-amber-600 transition-colors inline-flex items-center gap-1"><AppIcon name="arrow-left" :size="14" />레시피 목록</button>
    </div>

    <div class="grid grid-cols-12 gap-4">
      <!-- 왼쪽: 카테고리 -->
      <div class="col-span-12 lg:col-span-2 hidden lg:block">
        <div class="card overflow-hidden sticky top-20">
          <div class="px-3 py-2.5 border-b border-gray-50 font-bold text-xs text-ink flex items-center gap-1.5"><AppIcon name="list" :size="13" class="text-orange-600" />분류</div>
          <RouterLink to="/recipes"
            class="block w-full text-left px-3 py-2 text-xs text-ink-light hover:bg-amber-50/50 transition-colors">
            전체
          </RouterLink>
          <RouterLink v-for="c in categories" :key="c.category" :to="`/recipes?category=${encodeURIComponent(c.category)}`"
            class="block w-full text-left px-3 py-2 text-xs transition-colors"
            :class="recipe?.category === c.category ? 'bg-amber-50 text-amber-700 font-bold' : 'text-ink-light hover:bg-amber-50/50'">
            {{ c.category }} <span class="text-[11px] text-ink-faint">({{ c.count }})</span>
          </RouterLink>
          <RouterLink v-if="auth.isLoggedIn" to="/recipes?favorites=1"
            class="w-full text-left px-3 py-2 text-xs text-ink-light hover:bg-red-50/50 transition-colors border-t border-gray-50 flex items-center gap-1">
            <AppIcon name="bookmark" :size="12" />내 북마크
          </RouterLink>
        </div>
      </div>

      <!-- 중앙: 상세 -->
      <div class="col-span-12 lg:col-span-7">
        <div v-if="loading" class="text-center py-16 text-ink-muted">로딩중...</div>

        <div v-else-if="recipe" class="card overflow-hidden">
          <!-- 히어로 이미지 -->
          <div class="relative h-56 sm:h-72 bg-gradient-to-br from-amber-100 to-orange-100 overflow-hidden">
            <img v-if="recipe.thumbnail" :src="recipe.thumbnail" :alt="recipe.title"
              class="w-full h-full object-cover"
              @error="$event.target.style.display='none'" />
            <div v-else class="w-full h-full flex items-center justify-center text-amber-300"><AppIcon name="utensils" :size="64" :stroke-width="1.5" /></div>
            <div class="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent"></div>
            <div class="absolute bottom-0 left-0 right-0 p-5 text-white">
              <div class="flex items-center gap-2 mb-2">
                <span v-if="recipe.category" class="bg-amber-400 text-white text-[11px] font-bold px-2 py-0.5 rounded-full">
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
                <span class="text-xs text-white/70">({{ recipe.rating_count || 0 }}명 평가)</span>
              </div>
              <h1 class="text-xl sm:text-2xl font-bold leading-tight">{{ recipe.title }}</h1>
              <div v-if="recipe.title_en" class="text-sm text-white/90 mt-0.5">{{ recipe.title_en }}</div>
              <div class="text-[11px] text-white/80 mt-1 flex items-center gap-3">
                <span class="inline-flex items-center gap-1"><AppIcon name="eye" :size="12" />{{ recipe.view_count || 0 }}</span>
                <span class="inline-flex items-center gap-1"><AppIcon name="heart" :size="12" />{{ recipe.favorite_count || 0 }}</span>
                <span v-if="recipe.user" class="text-amber-200">by {{ recipe.user.nickname || recipe.user.name }}</span>
              </div>
            </div>
          </div>

          <!-- 액션 버튼 -->
          <div class="px-5 py-3 border-b border-gray-50 flex items-center justify-between flex-wrap gap-2">
            <!-- 북마크/공유 -->
            <div class="flex gap-2 items-center">
              <BookmarkToggle v-if="auth.isLoggedIn" :active="recipe.is_favorited" @toggle="toggleFavorite" size="lg" />
              <span v-else class="text-xs text-ink-faint">로그인 필요</span>
            </div>
            <!-- 소유자 수정/삭제 -->
            <div v-if="isOwner" class="flex gap-2">
              <button @click="$router.push(`/recipes/${recipe.id}/edit`)" class="btn-soft px-3 py-1.5 text-xs rounded-full"><AppIcon name="edit" :size="12" />수정</button>
              <button @click="deleteRecipe" class="text-xs bg-red-50 text-red-600 hover:bg-red-100 font-semibold px-3 py-1.5 rounded-full inline-flex items-center gap-1 transition-colors"><AppIcon name="trash" :size="12" />삭제</button>
            </div>
          </div>

          <!-- 영양 정보 -->
          <div class="grid grid-cols-5 border-b border-gray-50">
            <div v-for="item in nutritionItems" :key="item.key"
              class="p-3 text-center border-r last:border-r-0 border-gray-50">
              <div class="text-xs text-ink-faint">{{ item.label }}</div>
              <div class="text-sm font-bold text-ink mt-0.5">{{ recipe[item.key] || '-' }}</div>
            </div>
          </div>

          <!-- 재료 — 구조화 테이블 (있으면) -->
          <div v-if="recipe.ingredients_structured && recipe.ingredients_structured.length" class="px-5 py-4 border-b border-gray-50">
            <h2 class="text-base font-bold text-ink mb-3 border-b-2 border-amber-400 pb-1 inline-block">
              재료 <span v-if="recipe.servings" class="text-xs text-ink-muted font-normal">({{ recipe.servings }})</span>
            </h2>
            <div class="divide-y divide-gray-50">
              <div v-for="(ing, idx) in recipe.ingredients_structured" :key="idx"
                class="flex items-center justify-between py-2 text-sm">
                <div class="flex items-baseline gap-2 flex-1 min-w-0">
                  <span class="font-semibold text-ink">{{ ing.name_ko || ing.name }}</span>
                  <span v-if="ing.name_en" class="text-amber-600 text-xs italic">{{ ing.name_en }}</span>
                </div>
                <span class="text-ink-muted text-sm ml-3 flex-shrink-0">{{ ing.amount || '' }}</span>
              </div>
            </div>
          </div>
          <!-- 재료 — 일반 텍스트 (fallback) -->
          <div v-else-if="recipe.ingredients || recipe.ingredients_en" class="px-5 py-4 border-b border-gray-50">
            <h2 class="text-base font-bold text-ink mb-2 border-b-2 border-amber-400 pb-1 inline-block">
              재료 <span v-if="recipe.servings" class="text-xs text-ink-muted font-normal">({{ recipe.servings }})</span>
            </h2>
            <div class="bg-amber-50/50 rounded-xl p-3">
              <div v-if="recipe.ingredients" class="text-sm text-ink-light whitespace-pre-wrap leading-relaxed">{{ recipe.ingredients }}</div>
              <div v-if="recipe.ingredients_en" class="text-xs text-ink-muted whitespace-pre-wrap leading-relaxed mt-2 pt-2 border-t border-amber-200/50 italic">{{ recipe.ingredients_en }}</div>
            </div>
          </div>

          <!-- 조리 순서 (한영) -->
          <div v-if="recipe.steps && recipe.steps.length" class="px-5 py-4 border-b border-gray-50">
            <h2 class="text-base font-bold text-ink mb-3 flex items-center gap-1.5"><AppIcon name="utensils" :size="16" class="text-orange-500" />조리 순서</h2>
            <div class="space-y-4">
              <div v-for="step in recipe.steps" :key="step.order" class="flex gap-3">
                <div class="flex-shrink-0 w-7 h-7 rounded-full bg-amber-400 text-white font-bold text-xs flex items-center justify-center">
                  {{ step.order }}
                </div>
                <div class="flex-1 min-w-0">
                  <p class="text-sm text-ink-light leading-relaxed whitespace-pre-wrap">{{ step.text }}</p>
                  <p v-if="step.text_en" class="text-xs text-ink-muted italic leading-relaxed whitespace-pre-wrap mt-1">{{ step.text_en }}</p>
                  <img v-if="step.image_url" :src="step.image_url"
                    :alt="'step ' + step.order"
                    class="mt-2 rounded-xl max-w-full sm:max-w-sm border border-gray-100"
                    @error="$event.target.style.display='none'" />
                </div>
              </div>
            </div>
          </div>

          <!-- 별점 + 리뷰 -->
          <div class="px-5 py-4 border-b border-gray-50 bg-amber-50/30">
            <h2 class="text-base font-bold text-ink mb-3 flex items-center gap-1.5"><AppIcon name="star" :size="16" class="text-amber-500" />별점 & 리뷰 ({{ comments.length }})</h2>

            <!-- 작성 폼 (로그인 시) -->
            <div v-if="!auth.isLoggedIn" class="text-sm text-ink-muted mb-3">로그인 후 별점과 리뷰를 남길 수 있습니다</div>
            <div v-else class="card p-3 mb-4">
              <div class="flex items-center gap-2 mb-2">
                <span class="text-xs text-ink-muted">내 별점:</span>
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
                class="input-soft"></textarea>
              <div class="flex justify-end mt-2 gap-2">
                <span v-if="ratingMsg" class="text-xs text-green-600 font-bold self-center">{{ ratingMsg }}</span>
                <button @click="submitRating" :disabled="!myRating || submittingRating"
                  class="btn-primary px-4 py-1.5 text-xs">
                  {{ submittingRating ? '저장중...' : '등록' }}
                </button>
              </div>
            </div>

            <!-- 댓글 리스트 -->
            <div v-if="loadingComments" class="text-center py-4 text-xs text-ink-muted">로딩중...</div>
            <div v-else-if="!comments.length" class="text-center py-4 text-xs text-ink-muted">첫 번째 리뷰를 남겨보세요</div>
            <div v-else class="space-y-3">
              <div v-for="c in comments" :key="c.id" class="card p-3">
                <div class="flex items-start justify-between gap-2">
                  <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-full bg-amber-200 flex items-center justify-center text-sm font-bold text-amber-800 flex-shrink-0">
                      {{ (c.user?.nickname || c.user?.name || '?')[0] }}
                    </div>
                    <div>
                      <div class="text-xs font-bold text-ink">{{ c.user?.nickname || c.user?.name || '익명' }}</div>
                      <div class="flex items-center gap-1">
                        <span class="text-amber-400 text-[11px]">{{ '★'.repeat(c.rating) }}{{ '☆'.repeat(5 - c.rating) }}</span>
                        <span class="text-[11px] text-ink-faint">{{ fmtDate(c.created_at) }}</span>
                      </div>
                    </div>
                  </div>
                  <button v-if="auth.user && c.user_id === auth.user.id" @click="deleteComment(c.id)"
                    class="text-[11px] text-red-400 hover:text-red-600 transition-colors">삭제</button>
                </div>
                <div v-if="c.comment" class="text-sm text-ink-light mt-2 whitespace-pre-wrap leading-relaxed">{{ c.comment }}</div>
              </div>
            </div>
          </div>

          <!-- 해시태그 -->
          <div v-if="recipe.hash_tags" class="px-5 py-4 border-b border-gray-50">
            <div class="flex flex-wrap gap-1.5">
              <span v-for="tag in parseTags(recipe.hash_tags)" :key="tag"
                class="badge-primary">
                #{{ tag }}
              </span>
            </div>
          </div>

          <!-- 출처 -->
          <div class="px-5 py-3 bg-gray-50 text-[11px] text-ink-faint text-center">
            {{ recipe.source === 'user' ? '작성: ' + (recipe.user?.nickname || recipe.user?.name || '회원') : '출처: 식품안전나라 조리식품 레시피 DB' }}
          </div>
        </div>

        <PostNavigator v-if="recipe" :prev-id="prev?.id" :prev-title="prev?.title"
          :next-id="next?.id" :next-title="next?.title"
          list-path="/recipes" detail-base="/recipes/" />

        <div v-else class="py-16 text-center">
          <div class="icon-chip w-14 h-14 bg-gray-100 text-gray-300 mx-auto mb-3"><AppIcon name="utensils" :size="28" :stroke-width="1.5" /></div>
          <p class="text-sm text-ink-muted">레시피를 찾을 수 없습니다</p>
        </div>
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
import DetailHeader from '../../components/DetailHeader.vue'
import PostNavigator from '../../components/PostNavigator.vue'
import AppIcon from '../../components/AppIcon.vue'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()
const recipe = ref(null)
const prev = ref(null)
const next = ref(null)
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
    prev.value = data.prev
    next.value = data.next
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
