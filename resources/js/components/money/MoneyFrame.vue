<template>
<div class="min-h-screen">
  <!-- 폭은 사이트의 다른 화면과 같은 7xl — 메뉴를 옮겨 다녀도 창 크기가 달라지지 않게 -->
  <div class="max-w-7xl mx-auto px-4 py-5">
    <MoneyHead :crumbs="crumbs" />

    <!-- 휴대폰: 분야 고르기 (누르면 펼침) -->
    <div class="lg:hidden mb-3">
      <button type="button" @click="open = !open" :aria-expanded="open"
        class="w-full flex items-center justify-between gap-2 rounded-xl border border-line bg-white px-3.5 py-2.5 text-[14.5px] font-semibold text-ink">
        <span class="flex items-center gap-2 min-w-0">
          <AppIcon :name="current ? cafeIcon(current.slug) : 'coins'" :size="18" class="text-emerald-700" />
          <span class="truncate">{{ current ? current.name : '분야 선택' }}</span>
        </span>
        <AppIcon :name="open ? 'chevron-up' : 'chevron-down'" :size="18" class="text-ink-faint shrink-0" />
      </button>
      <div v-if="open" class="mt-1.5 rounded-xl border border-line bg-white py-1 shadow-card">
        <RouterLink v-for="c in cafes" :key="c.slug" :to="`/money/cafe/${c.slug}`" @click="open = false"
          class="flex items-center gap-2.5 px-3.5 py-2 text-[14px] hover:bg-gray-50" :class="c.slug === cafeSlug ? 'text-emerald-700 font-bold bg-emerald-50/60' : 'text-ink-light'">
          <AppIcon :name="cafeIcon(c.slug)" :size="17" />
          <span class="flex-1">{{ c.name }}</span>
          <span v-if="c.new_count > 0" class="badge-green !text-[10px] !px-1.5 !py-0 shrink-0">NEW</span>
          <span class="text-[12px] text-ink-faint">{{ c.post_count }}</span>
        </RouterLink>
      </div>
    </div>

    <div class="lg:flex lg:gap-6 lg:items-start">
      <!-- PC: 왼쪽 분야 메뉴 -->
      <aside class="hidden lg:block w-[228px] shrink-0 lg:sticky lg:top-24 max-h-[calc(100vh-7rem)] overflow-y-auto pr-1" aria-label="MONEY 분야">
        <template v-for="c in cafes" :key="c.slug">
          <RouterLink :to="`/money/cafe/${c.slug}`" class="flex items-center gap-2.5 px-2.5 py-2 rounded-lg text-[14px] hover:bg-gray-50"
            :class="c.slug === cafeSlug ? 'bg-emerald-50 text-emerald-700 font-bold' : 'text-ink-light'">
            <AppIcon :name="cafeIcon(c.slug)" :size="17" />
            <span class="flex-1 min-w-0 truncate">{{ c.name }}</span>
            <span v-if="c.new_count > 0" class="badge-green !text-[10px] !px-1.5 !py-0 shrink-0 font-bold">NEW</span>
            <span class="text-[11.5px] font-normal text-ink-faint">{{ c.post_count }}</span>
          </RouterLink>
        </template>
        <div class="border-t border-line mt-2 pt-2">
          <RouterLink to="/money#money-guides" class="flex items-center gap-2.5 px-2.5 py-2 rounded-lg text-[14px] text-ink-light hover:bg-gray-50">
            <AppIcon name="book-open" :size="17" />처음 읽는 가이드
          </RouterLink>
        </div>
        <!-- 메뉴 아래 빈자리: 읽는 방법 안내 + 글쓰기 -->
        <div class="mt-4 rounded-xl border border-emerald-100 bg-emerald-50/50 px-3.5 py-3.5">
          <div class="flex items-center gap-1.5 text-[13.5px] font-bold text-emerald-800"><AppIcon name="sparkles" :size="15" />이렇게 읽어 보세요</div>
          <ul class="mt-2 space-y-1.5 text-[12.5px] leading-snug text-ink-light">
            <li>① 왼쪽에서 궁금한 분야를 고르세요</li>
            <li>② 글 안의 사진과 번호를 따라 하시면 돼요</li>
            <li>③ 금액·날짜는 공식 사이트에서 한 번 더 확인하세요</li>
          </ul>
          <RouterLink to="/money/write" class="mt-3 flex items-center justify-center gap-1 rounded-lg bg-white border border-emerald-200 py-2 text-[12.5px] font-bold text-emerald-700 hover:bg-emerald-50">
            <AppIcon name="edit" :size="13" />내 경험 올리기
          </RouterLink>
        </div>
      </aside>

      <div class="min-w-0 flex-1"><slot /></div>
    </div>
  </div>
</div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import MoneyHead from './MoneyHead.vue'
import AppIcon from '../AppIcon.vue'
import { cafeIcon } from './moneyThemes'
import { moneyNav, loadMoneyNav } from './moneyNav'

const props = defineProps({
  crumbs: { type: Array, default: () => [] },
  cafeSlug: { type: String, default: '' },   // 지금 보는 분야
})
const open = ref(false)
const cafes = computed(() => moneyNav.cafes)
const current = computed(() => cafes.value.find(c => c.slug === props.cafeSlug) || null)

onMounted(loadMoneyNav)
</script>
