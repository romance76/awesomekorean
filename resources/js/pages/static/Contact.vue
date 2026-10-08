<template>
<div class="min-h-screen">
  <div class="max-w-3xl mx-auto px-4 py-8">
    <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink mb-6">
      <span class="icon-chip w-9 h-9 bg-amber-50 text-amber-600"><AppIcon name="mail" :size="20" /></span>
      문의하기
    </h1>
    <div class="card p-6 text-sm text-ink-light leading-relaxed space-y-5">
      <p>AwesomeKorean 이용 중 궁금한 점, 불편한 점, 정보 글의 오류나 제안이 있으시면 아래 이메일로 보내 주세요. 가능한 한 빨리 확인하고 답변드리겠습니다.</p>

      <div class="rounded-xl bg-amber-50/60 border border-amber-100 px-4 py-3">
        <div class="text-xs text-ink-muted mb-0.5">대표 문의 이메일</div>
        <a :href="`mailto:${email}`" class="text-base font-bold text-amber-700 hover:underline break-all">{{ email }}</a>
      </div>

      <div>
        <h2 class="font-bold text-base text-ink mb-2">이런 내용은 이렇게 보내 주세요</h2>
        <ul class="list-disc pl-5 space-y-1.5">
          <li><b>정보 글의 오류·정정 요청</b> — 제목에 [정정] 과 글 제목을 적고, 틀린 부분과 근거(공식 기관 링크 등)를 알려 주세요.</li>
          <li><b>광고·제휴 문의</b> — 제목에 [광고] 를 적어 주세요. NEW 전단 광고는 로그인 후 마이페이지에서 직접 신청할 수 있습니다.</li>
          <li><b>게시물 신고·저작권 침해 신고</b> — 해당 글 주소와 신고 사유를 적어 주세요.</li>
          <li><b>개인정보 열람·삭제 요청</b> — 가입한 이메일 주소로 보내 주세요. 본인 확인 후 처리합니다.</li>
          <li><b>그 밖의 문의·건의</b> — 자유롭게 보내 주세요.</li>
        </ul>
      </div>

      <div v-if="company || address" class="text-xs text-ink-muted border-t border-gray-100 pt-4">
        <div v-if="company">운영: {{ company }}</div>
        <div v-if="address">{{ address }}</div>
        <div v-if="phone">전화: {{ phone }}</div>
      </div>

      <p class="text-xs text-ink-muted">
        이용약관은 <RouterLink to="/terms" class="text-blue-600 underline">여기</RouterLink>,
        개인정보 처리 방침은 <RouterLink to="/privacy" class="text-blue-600 underline">여기</RouterLink>에서 볼 수 있습니다.
      </p>
    </div>
  </div>
</div>
</template>
<script setup>
import { computed, onMounted } from 'vue'
import { useSiteStore } from '../../stores/site'
import AppIcon from '../../components/AppIcon.vue'
const site = useSiteStore()
onMounted(() => site.load())
const email = computed(() => site.getSetting('contact_email', '') || 'admin@awesomekorean.com')
const company = computed(() => site.getSetting('company_name', ''))
const address = computed(() => site.getSetting('company_address', ''))
const phone = computed(() => site.getSetting('contact_phone', ''))
</script>
