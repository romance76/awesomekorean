<template>
  <!-- 목록 페이지 제목 자리 — 상단 메뉴 묶음이 켜져 있고 이 메뉴가 2개 이상짜리 묶음에 들어 있으면 [묶음 이름][하위 탭], 아니면 원래 제목(slot) -->
  <div v-if="grp" class="flex min-w-0" :class="stack ? 'flex-col items-start gap-2' : 'items-center gap-3'">
    <RouterLink :to="grp.items[0].path" class="flex items-center gap-2.5 text-xl font-bold text-ink hover:text-amber-600 transition-colors whitespace-nowrap">
      <span class="icon-chip w-9 h-9 bg-amber-50 text-[18px] leading-none">{{ grp.icon || '📁' }}</span>{{ grp.label }}
    </RouterLink>
    <div class="flex gap-1.5 overflow-x-auto scrollbar-hide max-w-full" role="tablist" :aria-label="grp.label + ' 메뉴'">
      <component v-for="m in grp.items" :key="m.key" :is="m.external ? 'a' : 'RouterLink'" v-bind="m.external ? { href: m.path } : { to: m.path }"
        role="tab" :aria-selected="m.key === menuKey"
        class="shrink-0 rounded-full border px-3.5 py-1.5 text-sm font-bold whitespace-nowrap transition-colors"
        :class="m.key === menuKey ? 'bg-[#fff1f5] text-[#FC226B] border-[#ffc2d6]' : 'bg-surface text-ink-light border-line hover:border-gray-300'">{{ m.label }}</component>
    </div>
  </div>
  <slot v-else />
</template>

<script setup>
import { computed } from 'vue'
import { useSiteStore } from '../stores/site'
import { useAuthStore } from '../stores/auth'
import { useLangStore } from '../stores/lang'

const props = defineProps({
  menuKey: { type: String, required: true },   // 이 페이지의 메뉴 키 (menu_config 의 key: community, qa, market …)
  stack: { type: Boolean, default: false },    // 휴대폰: 제목 아래 줄에 탭
})
const site = useSiteStore()
const auth = useAuthStore()
const lang = useLangStore()

const grp = computed(() => {
  const groups = site.menuGroups
  if (!groups) return null
  const g = groups.find(x => !x.hidden && x.type === 'group' && (x.items || []).includes(props.menuKey))
  if (!g) return null
  const ko = lang.locale === 'ko'
  const cfg = Object.fromEntries((site.menuConfig || []).map(m => [m.key, m]))
  const items = g.items.map(k => cfg[k] ? { ...cfg[k], key: k } : null).filter(m => m
    && m.enabled !== false && (!m.admin_only || auth.isAdmin) && (!m.login_required || auth.isLoggedIn) && m.path)
    .map(m => ({ key: m.key, path: m.path, external: m.path.startsWith('/info'), label: ko ? (g.item_labels?.[m.key] || m.label) : (m.label_en || m.label) }))
  if (items.length < 2 || !items.some(m => m.key === props.menuKey)) return null
  return { label: ko ? g.label : (g.label_en || g.label), icon: g.icon, items }
})
</script>
