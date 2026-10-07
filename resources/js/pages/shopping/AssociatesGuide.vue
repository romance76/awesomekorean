<template>
<div class="min-h-screen">
  <div class="page-main px-4 py-5">
    <PageHeader title="Amazon Associates 가입 방법" icon="shopping-bag" chip="bg-lime-50 text-lime-600" fallback="/shopping" />

    <div class="card p-4 mb-4 text-sm text-ink-light leading-relaxed">
      <p><b>Amazon Associates</b>는 Amazon의 무료 제휴 프로그램이에요. 내가 쓴 리뷰의 링크로 누군가 Amazon에서 물건을 사면 Amazon이 <b>내게 수수료</b>를 줍니다.
      Awesome Korean에서 내돈내산 리뷰를 쓰려면 이 가입이 먼저 필요하고, 가입하면 받는 <b>Store ID(태그)</b>를 사이트에 등록하면 돼요.</p>
      <p class="mt-2 text-xs text-ink-muted">가입은 무료이고, 아래 순서를 천천히 따라 하면 20~30분이면 끝나요. 화면 모양과 문구는 Amazon이 바꿀 수 있어요.</p>
      <a href="https://affiliate-program.amazon.com" target="_blank" rel="noopener noreferrer" class="btn-primary inline-flex items-center gap-1.5 px-4 py-2 text-sm font-bold mt-3">
        Amazon Associates 가입하러 가기 <AppIcon name="external-link" :size="14" />
      </a>
    </div>

    <div v-if="loading" class="text-center py-10 text-ink-muted">로딩중...</div>
    <ol v-else class="space-y-3">
      <li v-for="(s, i) in steps" :key="i" class="card p-4">
        <h2 class="font-bold text-ink text-base">{{ s.title }}</h2>
        <p v-if="s.body" class="text-sm text-ink-light leading-relaxed whitespace-pre-line mt-2">{{ s.body }}</p>
        <img v-if="s.image" :src="s.image" :alt="s.title" loading="lazy" class="mt-3 w-full rounded-lg border border-gray-100" />
      </li>
    </ol>

    <div class="card p-4 mt-4 flex flex-col sm:flex-row gap-2 items-center justify-between">
      <span class="text-sm text-ink-light">가입이 끝났다면 Store ID를 등록하고 리뷰를 써보세요.</span>
      <RouterLink to="/shopping/write" class="btn-primary px-4 py-2 text-sm font-bold">✍️ 태그 등록하고 리뷰 쓰기</RouterLink>
    </div>

    <p class="text-[11px] text-ink-faint mt-3 leading-relaxed">
      ※ Amazon Associates 가입·승인·수수료 지급은 Amazon이 직접 운영하며 Awesome Korean은 관여하지 않아요.
      가입 후 180일 안에 내 링크로 3건 이상의 구매가 나와야 계정이 유지되고, 링크를 올리는 곳(웹사이트/앱)은 Associates 계정에 등록해야 해요. 자세한 규정은 Amazon의 Operating Agreement를 확인하세요.
    </p>
  </div>
</div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import axios from 'axios'
import AppIcon from '../../components/AppIcon.vue'
import PageHeader from '../../components/PageHeader.vue'

const steps = ref([])
const loading = ref(true)
onMounted(async () => {
  try { steps.value = (await axios.get('/api/shopping/associates-guide')).data.data.steps } catch {}
  loading.value = false
})
</script>
