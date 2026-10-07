<template>
<div class="card overflow-hidden">
  <!-- 상단 안내 -->
  <div class="px-5 py-8 text-center bg-gradient-to-b from-teal-50/70 to-white">
    <div class="icon-chip w-16 h-16 bg-teal-100 text-teal-600 mx-auto mb-4"><AppIcon name="users" :size="32" /></div>
    <h2 class="text-lg md:text-xl font-black text-ink leading-snug">
      <template v-if="category">{{ place ? place + ' ' : '' }}{{ categoryText }} 동호회,<br>첫 번째 모임장이 되어 보세요!</template>
      <template v-else>{{ place ? place + '의' : '우리' }} 첫 동호회를<br>지금 만들어 보세요!</template>
    </h2>
    <p class="text-sm text-ink-muted mt-3 leading-relaxed">
      같은 취미를 가진 이웃이 모이는 공간이에요.<br class="hidden sm:block">
      동호회를 만들면 게시판, 단체 채팅, 가입 관리를 바로 쓸 수 있어요.
    </p>
    <div class="flex items-center justify-center gap-2 mt-5 flex-wrap">
      <RouterLink :to="createLink" class="btn-primary"><AppIcon name="plus" :size="15" />동호회 만들기</RouterLink>
      <RouterLink to="/clubs/sample" class="btn-secondary"><AppIcon name="eye" :size="15" />샘플 동호회 둘러보기</RouterLink>
    </div>
    <p class="text-[11px] text-ink-faint mt-3">이 분류의 첫 동호회가 되면 목록 맨 위에 보여요. 다른 동호회가 생기면 이 안내는 사라지고 목록으로 바뀝니다.</p>
  </div>

  <!-- 동호회에서 할 수 있는 일 -->
  <div class="px-5 py-5 border-t border-line">
    <div class="text-xs font-bold text-ink mb-3">동호회에서는 이런 걸 할 수 있어요</div>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
      <div v-for="f in features" :key="f.title" class="flex items-start gap-3 rounded-xl bg-gray-50/70 p-3">
        <span class="icon-chip w-9 h-9 flex-shrink-0" :class="f.chip"><AppIcon :name="f.icon" :size="18" /></span>
        <div class="min-w-0">
          <div class="text-sm font-bold text-ink">{{ f.title }}</div>
          <div class="text-xs text-ink-muted mt-0.5 leading-relaxed">{{ f.desc }}</div>
        </div>
      </div>
    </div>
  </div>

  <!-- 분류를 정하지 않은 경우: 어떤 동호회를 만들지 고르기 -->
  <div v-if="!category" class="px-5 py-5 border-t border-line">
    <div class="text-xs font-bold text-ink mb-2">어떤 동호회를 만들까요?</div>
    <div class="flex flex-wrap gap-1.5">
      <RouterLink v-for="c in categories" :key="c.value" :to="{ path: '/clubs/create', query: { category: c.value, ...(onlineOnly ? { type: 'online' } : {}) } }"
        class="text-xs px-3 py-1.5 rounded-full border border-gray-200 text-ink-light hover:bg-amber-50 hover:border-amber-300 hover:text-amber-700 transition-colors">{{ c.label }}</RouterLink>
    </div>
  </div>
</div>
</template>

<script setup>
import { computed } from 'vue'
import AppIcon from './AppIcon.vue'

const props = defineProps({
  category: { type: String, default: '' },
  categoryLabel: { type: String, default: '' },
  place: { type: String, default: '' },
  onlineOnly: { type: Boolean, default: false },
  categories: { type: Array, default: () => [] },
})

// "🥾 등산" 에서 이모지를 빼고 글자만
const categoryText = computed(() => (props.categoryLabel || props.category).replace(/^[^\p{L}\p{N}]+/u, '').trim() || props.category)

const createLink = computed(() => ({
  path: '/clubs/create',
  query: { ...(props.category ? { category: props.category } : {}), ...(props.onlineOnly ? { type: 'online' } : {}) },
}))

const features = [
  { icon: 'list', title: '게시판', desc: '공지, 모임 후기, 사진을 올리고 댓글로 이야기해요.', chip: 'bg-blue-50 text-blue-600' },
  { icon: 'message-circle', title: '단체 채팅방', desc: '회원들과 실시간으로 대화하며 모임을 정해요.', chip: 'bg-violet-50 text-violet-600' },
  { icon: 'user-plus', title: '가입 승인', desc: '신청을 받아 승인·거절하고, 운영진을 정할 수 있어요.', chip: 'bg-emerald-50 text-emerald-600' },
  { icon: 'megaphone', title: '홍보', desc: '상위 노출로 더 많은 이웃에게 동호회를 알려요.', chip: 'bg-amber-50 text-amber-600' },
]
</script>
