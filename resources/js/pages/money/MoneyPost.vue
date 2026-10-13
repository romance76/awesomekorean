<template>
<MoneyFrame :crumbs="post ? [{ label: post.cafe?.name, to: `/money/cafe/${post.cafe?.slug}` }] : []" :cafe-slug="post?.cafe?.slug || ''">
    <!-- 폭: 바깥은 다른 MONEY 화면과 같은 큰 폭(7xl) — MONEY 제목 줄 위치가 화면마다 흔들리지 않게. 글은 읽기 좋은 폭으로 가운데 -->
    <div class="max-w-4xl">

    <p v-if="notFound" class="card p-8 text-center text-ink-muted">글을 찾을 수 없어요. 내려갔거나 주소가 바뀌었을 수 있어요.</p>
    <article v-else-if="post" class="card p-5 md:p-7">
      <div class="flex items-center gap-2 mb-2">
        <span class="text-[11px] font-bold rounded-full px-2 py-0.5" :class="KIND_CLASS[post.kind]">{{ KIND_LABEL[post.kind] }}</span>
        <span class="text-xs text-ink-muted">{{ post.cafe?.name }}</span>
        <RouterLink v-if="post.category" :to="`/money/cafe/${post.cafe?.slug}?topic=${post.category.id}`" class="text-[11px] font-semibold rounded px-1.5 py-0.5 bg-gray-100 text-ink-light hover:bg-gray-200">{{ post.category.name }}</RouterLink>
      </div>
      <h2 class="text-[22px] font-bold text-ink leading-snug">{{ post.title }}</h2>
      <div class="text-sm text-ink-muted mt-1.5 flex flex-wrap gap-x-3">
        <span>{{ post.user?.display_name || post.user?.name || '회원' }}</span><span>{{ fmt(post.created_at) }}</span><span>조회 {{ post.view_count }}</span>
      </div>
      <div class="money-intro mt-5 text-[16px] text-ink leading-relaxed break-words" v-html="post.content"></div>
      <GuideBlocks v-if="post.blocks?.length" :blocks="post.blocks" :theme="post.cafe?.theme || 'emerald'" class="mt-5" />
      <div v-if="canEdit" class="flex gap-2 justify-end mt-6">
        <RouterLink :to="`/money/write?id=${post.id}`" class="btn-secondary px-4 py-2 text-sm">수정</RouterLink>
        <button @click="remove" class="btn-secondary px-4 py-2 text-sm text-red-600">내리기</button>
      </div>
      <p class="mt-6 pt-4 border-t border-line text-[12.5px] text-ink-muted">{{ DISCLAIMER }}</p>
    </article>

    <nav v-if="post" class="card mt-4 divide-y divide-gray-50 overflow-hidden">
      <RouterLink v-if="post.next_post" :to="`/money/post/${post.next_post.id}`" class="flex items-center gap-3 px-4 sm:px-5 py-3 hover:bg-gray-50">
        <span class="text-[11px] font-bold text-ink-muted shrink-0 w-14">다음 글</span>
        <span class="truncate text-[14px] text-ink">{{ post.next_post.title }}</span>
      </RouterLink>
      <RouterLink v-if="post.prev_post" :to="`/money/post/${post.prev_post.id}`" class="flex items-center gap-3 px-4 sm:px-5 py-3 hover:bg-gray-50">
        <span class="text-[11px] font-bold text-ink-muted shrink-0 w-14">이전 글</span>
        <span class="truncate text-[14px] text-ink">{{ post.prev_post.title }}</span>
      </RouterLink>
      <RouterLink :to="listLink" class="flex items-center justify-center gap-1.5 px-4 sm:px-5 py-3 font-bold text-[14px] text-ink hover:bg-gray-50">
        <AppIcon name="list" :size="16" />게시판 목록으로
      </RouterLink>
    </nav>

    <div v-if="post" class="card p-5 mt-4">
      <CommentSection type="money_post" :typeId="post.id" />
    </div>
    </div>
</MoneyFrame>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import axios from 'axios'
import { useAuthStore } from '../../stores/auth'
import { useSiteStore } from '../../stores/site'
import GuideBlocks from '../../components/money/GuideBlocks.vue'
import MoneyFrame from '../../components/money/MoneyFrame.vue'
import AppIcon from '../../components/AppIcon.vue'
import CommentSection from '../../components/CommentSection.vue'
import { KIND_LABEL, KIND_CLASS, DISCLAIMER } from '../../components/money/moneyText'

const route = useRoute(); const router = useRouter()
const auth = useAuthStore(); const site = useSiteStore()
const post = ref(null); const notFound = ref(false)
const fmt = (s) => (s ? String(s).slice(0, 16).replace('T', ' ') : '')
// 운영팀이 올린 주제 이미지만 허용(정해진 폴더의 svg)
const topicImage = computed(() => (/^\/images\/money\/topics\/[\w-]+\.svg$/.test(post.value?.image || '') ? post.value.image : ''))
const canEdit = computed(() => post.value && auth.user && (auth.user.id === post.value.user_id || auth.isAdmin))
const listLink = computed(() => `/money/cafe/${post.value?.cafe?.slug || ''}${post.value?.category ? `?topic=${post.value.category.id}` : ''}`)

async function load() {
  notFound.value = false; post.value = null
  try { const { data } = await axios.get(`/api/money/posts/${route.params.id}`); post.value = data.data } catch { notFound.value = true }
}
async function remove() {
  if (!confirm('이 글을 내릴까요?')) return
  try {
    await axios.delete(`/api/money/posts/${post.value.id}`)
    site.toast?.('글을 내렸어요', 'success')
    router.push(`/money/cafe/${post.value.cafe?.slug}`)
  } catch (e) { site.toast?.(e.response?.data?.message || '내리지 못했어요', 'error') }
}
watch(() => route.params.id, (v) => { if (v) load() })
onMounted(load)
</script>
