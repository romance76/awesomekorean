<template>
  <!-- MONEY 화면 공통 제목 줄: 💰 MONEY › (카페 이름) › … — MONEY 를 누르면 허브로 -->
  <div class="flex items-center gap-3 flex-wrap mb-4">
    <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink min-w-0">
      <span class="icon-chip w-9 h-9 bg-emerald-50 text-emerald-700"><AppIcon name="coins" :size="19" /></span>
      <RouterLink v-if="crumbs.length" to="/money" class="text-emerald-700 hover:underline whitespace-nowrap">{{ label }}</RouterLink>
      <span v-else class="whitespace-nowrap">{{ label }}</span>
      <!-- 휴대폰: 중간 단계(mobileHide)는 숨겨 제목이 잘리지 않게 (MONEY › 돈 벌기) -->
      <template v-for="(c, i) in crumbs" :key="i">
        <span class="text-gray-300 font-normal" :class="c.mobileHide ? 'hidden sm:inline' : ''">›</span>
        <RouterLink v-if="c.to" :to="c.to" class="text-emerald-700 hover:underline truncate" :class="c.mobileHide ? 'hidden sm:inline' : ''">{{ c.label }}</RouterLink>
        <span v-else class="truncate" :class="c.mobileHide ? 'hidden sm:inline' : ''">{{ c.label }}</span>
      </template>
    </h1>
    <div v-if="$slots.default" class="ml-auto flex items-center gap-2"><slot /></div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import AppIcon from '../AppIcon.vue'
import { useSiteStore } from '../../stores/site'

defineProps({ crumbs: { type: Array, default: () => [] } })   // [{ label, to? }]
const site = useSiteStore()
// 이름·아이콘은 관리자 › 메뉴 묶음의 MONEY 묶음을 따른다 (정식 이름이 정해지면 거기서 바꿈)
const grp = computed(() => {
  try {
    const raw = site.settings?.menu_groups
    const groups = typeof raw === 'string' ? JSON.parse(raw) : raw
    return (groups || []).find(g => g.type === 'hub' && String(g.path || '').startsWith('/money')) || null
  } catch { return null }
})
const label = computed(() => grp.value?.label || 'MONEY')
const icon = computed(() => grp.value?.icon || '💰')
</script>
