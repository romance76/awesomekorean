<template>
<div class="min-h-screen">
  <div class="page-main px-4 py-5">
    <LegalNav />
    <div class="card px-6 py-10 text-center">
      <h1 class="inline-block text-2xl font-black text-ink pb-2 border-b-4 border-pink-600">이메일 무단수집거부</h1>
      <p class="mt-8 text-sm text-ink-light leading-loose">
        본 사이트에 게시된 이메일 주소가 전자우편 수집 프로그램이나 그 밖의 기술적 장치를 이용하여<br class="hidden md:inline" />
        무단으로 수집되는 것을 거부합니다.
      </p>
      <p class="mt-4 text-sm text-ink-light leading-loose">
        이를 위반할 경우 한국의 「정보통신망 이용촉진 및 정보보호 등에 관한 법률」,<br class="hidden md:inline" />
        미국의 CAN-SPAM Act(Controlling the Assault of Non-Solicited Pornography And Marketing Act) 등<br class="hidden md:inline" />
        관련 법령에 의해 처벌받을 수 있음을 알려 드립니다.
      </p>
      <div class="my-8 text-8xl font-black text-ink-light leading-none select-none" aria-hidden="true">@</div>

      <div class="text-left max-w-2xl mx-auto border-t border-gray-100 pt-6 space-y-3 text-sm text-ink-light leading-relaxed">
        <h2 class="font-bold text-base text-ink">어썸코리안이 보내는 메일</h2>
        <ul class="list-disc pl-5 space-y-1">
          <li>어썸코리안이 보내는 광고·소식 메일에는 <b>수신 거부 버튼</b>이 들어 있으며, 누르시면 즉시 반영되어 같은 주소로 다시 보내지 않습니다.</li>
          <li>메일 맨 아래에는 발송자 정보와 우편 주소를 표시합니다.</li>
          <li>회원가입·본인 확인·비밀번호 재설정처럼 서비스 이용에 꼭 필요한 안내 메일은 수신 거부와 관계없이 발송될 수 있습니다.</li>
        </ul>
        <h2 class="font-bold text-base text-ink pt-3">무단 수집이나 원치 않는 메일을 발견하셨나요?</h2>
        <p>어썸코리안을 사칭하거나 이 사이트에서 수집한 주소로 보낸 것으로 보이는 메일을 받으셨다면 <a :href="`mailto:${email}`" class="text-blue-600 underline break-all">{{ email }}</a> 로 알려 주세요. 확인 후 필요한 조치를 하겠습니다.</p>
        <p class="text-ink-muted pt-3">최종 수정일: 2026-10-29</p>
      </div>
    </div>
  </div>
</div>
</template>

<script setup>
import { computed } from 'vue'
import { useSiteStore } from '../../stores/site'
import LegalNav from '../../components/static/LegalNav.vue'

const site = useSiteStore()
site.load()
const email = computed(() => site.getSetting('contact_email', '') || 'admin@awesomekorean.com')
</script>
