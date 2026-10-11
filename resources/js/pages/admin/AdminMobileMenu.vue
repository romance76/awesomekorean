<template>
<div class="space-y-3">
  <!-- '더보기'(길목 타일 3개)는 검색이 필요 없을 만큼 적어서 검색칸은 실제 메뉴 그룹에서만 보여 줌 -->
  <label v-if="group !== 'more'" class="flex items-center gap-2 bg-white border border-gray-200 rounded-xl px-3 min-h-[46px] text-ink-muted">
    <AppIcon name="search" :size="18" />
    <input v-model="q" type="search" placeholder="메뉴 찾기 (예: 부동산)" autocomplete="off"
      class="w-full bg-transparent outline-none text-[15px] text-ink" />
  </label>

  <div class="grid grid-cols-2 gap-2.5">
    <RouterLink v-for="tab in shown" :key="tab.to" :to="tab.to"
      class="relative flex flex-col justify-between gap-2.5 min-h-[84px] p-3 rounded-2xl bg-white border border-gray-100 active:bg-amber-50 transition-colors">
      <span class="w-9 h-9 rounded-xl grid place-items-center bg-amber-50 text-amber-600"><AppIcon :name="tab.icon" :size="20" /></span>
      <span class="text-[15px] font-bold text-ink leading-tight">{{ tab.label }}</span>
      <span v-if="tab.isNew" class="absolute top-2.5 right-2.5 text-[11px] font-bold text-amber-700 bg-amber-100 px-2 py-0.5 rounded-full">NEW</span>
      <!-- 즐겨찾기 별 — 누르면 홈 "즐겨찾기"에 모인다(서버에 저장돼 PC 와 휴대폰이 같은 구성) -->
      <button v-if="group !== 'more'" type="button" @click.prevent.stop="toggle(tab.to)" :aria-pressed="isFav(tab.to)"
        :aria-label="(isFav(tab.to) ? '즐겨찾기 해제 ' : '즐겨찾기 추가 ') + tab.label"
        class="absolute bottom-1 right-1 w-11 h-11 grid place-items-center rounded-full text-[22px] leading-none"
        :class="isFav(tab.to) ? 'text-amber-500' : 'text-gray-300'">{{ isFav(tab.to) ? '★' : '☆' }}</button>
    </RouterLink>
  </div>
  <p v-if="!shown.length" class="text-center text-sm text-ink-muted py-6">"{{ q }}" 에 맞는 메뉴가 없어요.</p>
</div>
</template>

<script setup>
import { ref, computed, inject, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppIcon from '../../components/AppIcon.vue'
import { useAdminFavorites } from '../../composables/useAdminFavorites'

const route = useRoute()
const router = useRouter()
const tabsFor = inject('adminTabsFor', () => [])
const q = ref('')
const { isFav, toggle, load: loadFavs } = useAdminFavorites()
loadFavs()

const group = computed(() => String(route.params.group || ''))
// 지원하는 그룹이 아니면 홈으로 (7그룹 + '더보기' 길목, 할 일 #84)
watch(group, g => { if (!['member', 'content', 'money', 'promo', 'support', 'system', 'more'].includes(g)) router.replace('/admin') }, { immediate: true })

const shown = computed(() => {
  const k = q.value.trim().toLowerCase()
  return tabsFor(group.value).filter(t => !k || t.label.toLowerCase().includes(k))
})
</script>
