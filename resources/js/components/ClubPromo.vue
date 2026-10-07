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

  <!-- 동호회를 만들면 이래서 좋아요 (카톡 단톡방/오픈채팅과 비교) -->
  <div class="px-5 py-6 border-t border-line">
    <div class="text-base font-black text-ink">동호회를 만들면 이래서 좋아요</div>
    <p class="text-xs text-ink-muted mt-1 mb-4">단톡방·오픈채팅만으로는 아쉬웠던 점을, 웹사이트 동호회가 이렇게 풀어줘요.</p>
    <div class="space-y-3">
      <div v-for="b in benefits" :key="b.title" class="rounded-2xl border border-line bg-white p-4">
        <div class="flex items-start gap-3">
          <span class="icon-chip w-10 h-10 flex-shrink-0" :class="b.chip"><AppIcon :name="b.icon" :size="20" /></span>
          <div class="min-w-0">
            <div class="text-sm font-black text-ink">{{ b.title }}</div>
            <p class="text-xs text-ink-light mt-1 leading-relaxed">{{ b.desc }}</p>
            <div class="mt-2.5 grid grid-cols-1 sm:grid-cols-2 gap-1.5 text-[11px] leading-snug">
              <div class="rounded-lg bg-gray-50 px-2.5 py-1.5 text-ink-muted"><b class="text-ink-light">단톡방</b> · {{ b.before }}</div>
              <div class="rounded-lg bg-emerald-50 px-2.5 py-1.5 text-emerald-800"><b>동호회</b> · {{ b.after }}</div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="mt-5 text-center">
      <RouterLink :to="createLink" class="btn-primary"><AppIcon name="plus" :size="15" />지금 동호회 만들기</RouterLink>
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

const benefits = [
  {
    icon: 'bookmark', chip: 'bg-blue-50 text-blue-600',
    title: '정보가 사라지지 않고 차곡차곡 쌓여요',
    desc: '게시판에 올린 글과 사진은 위로 밀려 사라지지 않아요. 트레이딩 차트 분석, 집수리(DIY) 노하우, 설정 가이드, 세금 팁 같은 알짜 정보를 주제별 게시판에 정리해 두고 언제든 다시 찾아볼 수 있어요.',
    before: '대화가 위로 밀리면 끝, 예전 정보를 찾기 어려워요', after: '글이 자산처럼 남고, 신입 회원도 지난 정보를 볼 수 있어요',
  },
  {
    icon: 'users', chip: 'bg-emerald-50 text-emerald-600',
    title: '회원 모집이 저절로 돼요',
    desc: '사이트를 찾는 미주 한인들에게 분류·지역별 동호회 목록으로 꾸준히 보여요. 링크를 들고 이곳저곳 홍보하지 않아도, 관심사가 맞는 이웃이 먼저 찾아와요.',
    before: '외부 커뮤니티에 링크를 계속 올려야 사람이 와요', after: '목록에 노출되고, 필요하면 상위 노출로 더 알릴 수 있어요',
  },
  {
    icon: 'list', chip: 'bg-violet-50 text-violet-600',
    title: '진짜 모임을 꾸리기 좋은 구조예요',
    desc: '공지, 모임 후기, 사진 갤러리처럼 게시판을 나눠 운영하고, 사진은 원본에 가깝게 올릴 수 있어요. 가벼운 수다는 단체 채팅방에서, 정리할 내용은 게시판에서 나눠 쓰면 모임의 질이 달라져요.',
    before: '공지·후기·사진·잡담이 한 방에 뒤섞여요', after: '게시판과 채팅방을 목적별로 나눠 쓸 수 있어요',
  },
  {
    icon: 'shield', chip: 'bg-rose-50 text-rose-600',
    title: '분위기를 지키는 회원 관리',
    desc: '가입 신청을 받아 승인한 사람만 모임 글을 읽고 쓰게 할 수 있어요. 모임 규칙을 걸어 두고, 믿을 수 있는 회원을 운영진으로 세우고, 분란을 만드는 회원은 내보낼 수 있어요. 광고 테러 걱정이 훨씬 줄어요.',
    before: '아무나 들어와 광고·분란이 생기기 쉬워요', after: '승인제 + 운영진 + 규칙으로 결속력 있는 모임을 만들어요',
  },
  {
    icon: 'heart-handshake', chip: 'bg-amber-50 text-amber-600',
    title: '제휴·후원 얘기가 쉬워져요',
    desc: '동호회가 커지면 지역 업체와 단체 할인, 장비 공동구매, 후원 같은 제휴를 이야기할 일이 생겨요. 게시판과 규칙을 갖춘 정식 동호회 페이지는 단순 채팅방보다 훨씬 믿음직하게 보여요.',
    before: '메신저 그룹이라 단체로서 신뢰를 보여주기 어려워요', after: '공개 소개 페이지와 활동 기록이 단체의 얼굴이 돼요',
  },
]
</script>
