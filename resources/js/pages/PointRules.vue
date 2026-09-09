<template>
<div class="min-h-screen">
  <div class="max-w-3xl mx-auto px-4 py-5">
    <button @click="$router.back()" class="btn-ghost text-sm -ml-2 mb-3"><AppIcon name="arrow-left" :size="15" /> 돌아가기</button>
    <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink mb-1">
      <span class="icon-chip w-9 h-9 bg-amber-50 text-amber-600"><AppIcon name="coins" :size="20" /></span>
      포인트 적립·사용 규칙
    </h1>
    <p class="text-xs text-ink-muted mb-4">관리자 설정과 실시간 동기화되는 공식 규칙입니다.</p>

    <div v-if="loading" class="text-center text-ink-faint py-12">로딩 중...</div>
    <div v-else class="space-y-4">
      <!-- 일일 룰렛 / 글·댓글 적립 규칙: 표 형태로 표현하기 어려운 항목은 안내 문구로 별도 설명 -->
      <section class="card overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-50 font-bold text-sm flex items-center gap-2 bg-amber-50 text-amber-800">
          <AppIcon name="sparkles" :size="15" /> 특별 규칙 안내
        </div>
        <div class="divide-y divide-gray-50 text-sm">
          <div class="px-4 py-3">
            <div class="text-ink font-semibold mb-1">🎰 일일 룰렛 (출석 체크)</div>
            <div class="text-ink-muted text-xs leading-relaxed">
              꽝 60% · 1P 20% · 2P 10% · 5P 6% · 10P 3% · 30P(잭팟) 1% — 하루 1회
            </div>
          </div>
          <div class="px-4 py-3">
            <div class="text-ink font-semibold mb-1">✍️ 게시글 · 댓글 작성</div>
            <div class="text-ink-muted text-xs leading-relaxed">
              게시글/댓글 작성 횟수 제한은 없습니다. 단, 하루 3회(게시글+댓글 합산)까지만 포인트가 지급됩니다.
              게시글 3P · 댓글 3P
            </div>
          </div>
        </div>
      </section>

      <section v-for="section in visibleSections" :key="section.key"
        class="card overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-50 font-bold text-sm flex items-center gap-2" :class="section.headerClass">
          <AppIcon :name="section.icon" :size="15" /> {{ section.title }}
        </div>
        <div class="divide-y divide-gray-50">
          <div v-for="rule in section.rules" :key="rule.key"
            class="px-4 py-2.5 flex items-start justify-between gap-3 text-sm">
            <div class="flex-1">
              <div class="text-ink">{{ rule.label || rule.key }}</div>
              <div v-if="rule.description" class="text-xs text-ink-faint">{{ rule.description }}</div>
            </div>
            <span class="font-bold whitespace-nowrap" :class="section.valueClass">
              {{ formatValue(rule, section.key) }}
            </span>
          </div>
        </div>
      </section>

      <div v-if="!visibleSections.length" class="py-16 text-center">
        <div class="icon-chip w-14 h-14 bg-gray-100 text-gray-300 mx-auto mb-3"><AppIcon name="coins" :size="28" :stroke-width="1.5" /></div>
        <p class="text-sm text-ink-muted">아직 등록된 규칙이 없습니다</p>
      </div>

      <p class="text-xs text-ink-faint mt-2 text-center">
        📌 관리자 설정이 변경되면 이 페이지도 즉시 반영됩니다.
      </p>
    </div>
  </div>
</div>
</template>
<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import AppIcon from '../components/AppIcon.vue'

const loading = ref(true)
const grouped = ref({})

// 카테고리별 렌더링 메타
const SECTION_META = {
  earn:     { title: '포인트 적립',     icon: 'coins',         headerClass: 'bg-green-50 text-green-800',   valueClass: 'text-green-600',  prefix: '+' },
  spend:    { title: '포인트 사용',     icon: 'shopping-cart', headerClass: 'bg-red-50 text-red-800',       valueClass: 'text-red-500',    prefix: '-' },
  game:     { title: '게임 포인트',     icon: 'gamepad',       headerClass: 'bg-amber-50 text-amber-800',   valueClass: 'text-amber-600',  prefix: ''  },
  market:   { title: '중고장터 규칙',   icon: 'shopping-bag',  headerClass: 'bg-indigo-50 text-indigo-800', valueClass: 'text-indigo-600', prefix: ''  },
  image:    { title: '이미지 업로드',   icon: 'image',         headerClass: 'bg-sky-50 text-sky-800',       valueClass: 'text-sky-600',    prefix: ''  },
  auction:  { title: '옥션',            icon: 'tag',           headerClass: 'bg-rose-50 text-rose-800',     valueClass: 'text-rose-600',   prefix: ''  },
  promotion:{ title: '상위노출 · 광고 (P/일)', icon: 'megaphone', headerClass: 'bg-violet-50 text-violet-800', valueClass: 'text-violet-600', prefix: '-' },
}

const visibleSections = computed(() => {
  const order = ['earn', 'spend', 'game', 'market', 'image', 'auction', 'promotion']
  return order
    .filter(k => (grouped.value[k] || []).length)
    .map(k => ({
      key: k,
      ...SECTION_META[k],
      rules: grouped.value[k] || [],
    }))
})

function formatValue(rule, sectionKey) {
  const v = rule.value
  if (v == null || v === '') return '-'
  // 숫자면 포인트 표기, 아니면 그대로
  const meta = SECTION_META[sectionKey]
  const n = Number(v)
  if (!isNaN(n)) {
    // 가격(spend) 는 - 프리픽스, earn 은 +
    const prefix = meta?.prefix ?? ''
    return `${prefix}${n.toLocaleString()}P`
  }
  return v
}

onMounted(async () => {
  try {
    const { data } = await axios.get('/api/point-rules')
    grouped.value = data.data || {}
  } catch {
    grouped.value = {}
  }
  loading.value = false
})
</script>
