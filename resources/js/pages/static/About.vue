<template>
<div class="min-h-screen">
  <!-- 히어로 -->
  <section class="relative overflow-hidden" style="background-image:linear-gradient(135deg,#FFF4EA 0%,#FFE9E0 55%,#FFF8F0 100%)">
    <div class="absolute -right-24 -top-24 w-80 h-80 rounded-full opacity-40" style="background:radial-gradient(circle,#FFB27A 0%,transparent 70%)"></div>
    <div class="absolute -left-16 bottom-0 w-64 h-64 rounded-full opacity-30" style="background:radial-gradient(circle,#FF8FA3 0%,transparent 70%)"></div>
    <div class="relative max-w-4xl mx-auto px-4 py-14 md:py-20 text-center">
      <div class="inline-block text-[11px] font-black tracking-widest text-orange-600 bg-white/70 border border-orange-100 rounded-full px-3 py-1 mb-4">ABOUT AWESOMEKOREAN</div>
      <h1 class="text-3xl md:text-5xl font-black text-ink leading-tight mb-4">
        미국에서 사는 한인의 하루를,<br class="hidden md:block" /> 한곳에서 더 쉽게.
      </h1>
      <p class="text-sm md:text-base text-ink-light leading-relaxed max-w-2xl mx-auto mb-7">
        AwesomeKorean은 미국 한인이 생활하며 필요한 정보, 이웃, 거래, 소식을 한국어로 모아 놓은 커뮤니티 플랫폼입니다.
        낯선 제도와 서류 앞에서 막막했던 경험이 우리 서비스의 출발점입니다.
      </p>
      <div class="flex flex-wrap items-center justify-center gap-2.5">
        <RouterLink to="/community" class="btn-primary">커뮤니티 둘러보기</RouterLink>
        <a href="/info" class="btn-secondary">생활 정보 읽기</a>
      </div>
    </div>
  </section>

  <div class="max-w-4xl mx-auto px-4 py-10 md:py-14 space-y-12 md:space-y-16">

    <!-- 우리가 하는 일 -->
    <section>
      <div class="text-center mb-6">
        <div class="text-xs font-black text-orange-500 mb-1">WHAT WE DO</div>
        <h2 class="text-xl md:text-2xl font-black text-ink">우리가 하는 일</h2>
      </div>
      <div class="card p-6 md:p-8 text-sm md:text-[15px] text-ink-light leading-loose space-y-3">
        <p>미국에서 살다 보면 영주권, 세금 보고, 운전면허, 신용점수, 의료보험처럼 한국과 전혀 다른 제도를 하루아침에 이해해야 할 때가 많습니다. 영어로 된 공식 안내는 어렵고, 믿을 만한 한국어 정보는 흩어져 있습니다.</p>
        <p>AwesomeKorean은 그 간극을 줄이기 위해 만들어졌습니다. 생활에 꼭 필요한 정보를 한국어로 쉽게 풀어 쓰고, 같은 지역에 사는 한인끼리 묻고 답하고 거래하고 일자리를 나눌 수 있는 공간을 함께 제공합니다.</p>
      </div>
    </section>

    <!-- 서비스 -->
    <section>
      <div class="text-center mb-6">
        <div class="text-xs font-black text-orange-500 mb-1">SERVICES</div>
        <h2 class="text-xl md:text-2xl font-black text-ink">이런 서비스를 제공합니다</h2>
      </div>
      <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
        <RouterLink v-for="s in services" :key="s.title" :to="s.to" class="card card-hover p-5 block">
          <div class="w-11 h-11 rounded-xl grid place-items-center text-2xl mb-3" :style="{ background: s.bg }">{{ s.icon }}</div>
          <div class="font-bold text-ink mb-1">{{ s.title }}</div>
          <div class="text-[13px] text-ink-muted leading-relaxed">{{ s.desc }}</div>
        </RouterLink>
      </div>
    </section>

    <!-- 원칙 -->
    <section>
      <div class="text-center mb-6">
        <div class="text-xs font-black text-orange-500 mb-1">OUR PRINCIPLES</div>
        <h2 class="text-xl md:text-2xl font-black text-ink">우리가 지키는 세 가지 원칙</h2>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div v-for="(p, i) in principles" :key="p.title" class="card p-6">
          <div class="text-3xl font-black text-orange-200 mb-2">0{{ i + 1 }}</div>
          <div class="font-bold text-ink mb-2">{{ p.title }}</div>
          <div class="text-[13px] text-ink-light leading-relaxed">{{ p.desc }}</div>
        </div>
      </div>
    </section>

    <!-- 정보 글 제작 과정 -->
    <section>
      <div class="text-center mb-6">
        <div class="text-xs font-black text-orange-500 mb-1">HOW WE PUBLISH</div>
        <h2 class="text-xl md:text-2xl font-black text-ink">정보 글은 이렇게 만들어집니다</h2>
      </div>
      <div class="card p-6 md:p-8">
        <ol class="space-y-5">
          <li v-for="(st, i) in steps" :key="st.title" class="flex gap-4">
            <div class="flex-shrink-0 w-9 h-9 rounded-full text-white font-black text-sm grid place-items-center" style="background-image:linear-gradient(135deg,#FF7A30,#FF4D12)">{{ i + 1 }}</div>
            <div>
              <div class="font-bold text-ink mb-0.5">{{ st.title }}</div>
              <div class="text-[13px] text-ink-light leading-relaxed">{{ st.desc }}</div>
            </div>
          </li>
        </ol>
        <div class="mt-6 rounded-xl bg-amber-50/70 border border-amber-100 px-4 py-3 text-[12.5px] text-ink-light leading-relaxed">
          <b class="text-ink">알아두세요.</b> 정보 글은 AI 도구의 도움을 받아 초안을 만들고 있습니다. 법률·세금·이민·금융·의료 내용은 일반적인 참고 자료이며 전문가 상담이나 공식 기관의 안내를 대체하지 않습니다. 제도와 금액은 수시로 바뀔 수 있으니 중요한 결정 전에는 해당 기관의 최신 공지를 꼭 확인해 주세요.
        </div>
      </div>
    </section>

    <!-- 광고와 수익 -->
    <section>
      <div class="text-center mb-6">
        <div class="text-xs font-black text-orange-500 mb-1">TRANSPARENCY</div>
        <h2 class="text-xl md:text-2xl font-black text-ink">광고와 운영 방식</h2>
      </div>
      <div class="card p-6 md:p-8 text-sm md:text-[15px] text-ink-light leading-loose space-y-3">
        <p>AwesomeKorean은 이용자에게 무료로 열려 있으며, 서비스를 계속 운영하기 위해 NEW 전단 광고, 제휴 링크, Google AdSense 같은 광고를 게재할 수 있습니다. 광고는 광고임을 알아볼 수 있게 표시하고, 광고 때문에 글의 내용을 바꾸지 않습니다.</p>
        <p>이용자의 개인정보는 필요한 범위에서만 사용하며, 자세한 내용은 <RouterLink to="/privacy" class="text-blue-600 underline">개인정보처리방침</RouterLink>과 <RouterLink to="/terms" class="text-blue-600 underline">이용약관</RouterLink>에서 확인하실 수 있습니다.</p>
      </div>
    </section>

    <!-- 문의 -->
    <section class="rounded-3xl text-center px-6 py-10 md:py-12" style="background-image:linear-gradient(135deg,#FF7A30,#FF4D12)">
      <h2 class="text-xl md:text-2xl font-black text-white mb-2">의견을 들려주세요</h2>
      <p class="text-sm text-white/90 leading-relaxed max-w-xl mx-auto mb-6">
        잘못된 정보, 고쳤으면 하는 점, 함께하고 싶은 제안 모두 환영합니다. 보내 주신 의견은 서비스를 더 믿을 수 있게 만드는 데 쓰입니다.
      </p>
      <RouterLink to="/contact" class="inline-flex items-center justify-center bg-white text-orange-600 font-bold rounded-full px-6 py-2.5 text-sm shadow-btn hover:bg-orange-50 transition-colors">문의하기</RouterLink>
    </section>

    <!-- 운영 정보 -->
    <section v-if="company || address" class="text-center text-xs text-ink-muted leading-relaxed">
      <div v-if="company" class="font-semibold text-ink-light">{{ company }}</div>
      <div v-if="address">{{ address }}</div>
      <div>문의: <a :href="`mailto:${email}`" class="underline">{{ email }}</a></div>
    </section>
  </div>
</div>
</template>
<script setup>
import { computed, onMounted } from 'vue'
import { useSiteStore } from '../../stores/site'

const site = useSiteStore()
onMounted(() => site.load())
const email = computed(() => site.getSetting('contact_email', '') || 'admin@awesomekorean.com')
const company = computed(() => site.getSetting('company_name', ''))
const address = computed(() => site.getSetting('company_address', ''))

const services = [
  { icon: '💬', title: '커뮤니티 · Q&A', desc: '생활 속 궁금증을 묻고, 겪어 본 사람의 경험을 나누는 게시판', to: '/community', bg: '#FFF1E6' },
  { icon: '📚', title: '생활 정보', desc: '영주권, 세금, 면허, 신용점수 등 미국 생활 정보를 한국어로 정리한 글', to: '/info', bg: '#EAF1FF' },
  { icon: '💼', title: '구인구직', desc: '지역 한인 업체의 채용 소식과 일자리를 찾는 분들을 연결', to: '/jobs', bg: '#E9F8F1' },
  { icon: '🛒', title: '중고장터 · 공동구매', desc: '가까운 이웃과 안전하게 사고팔고, 함께 사서 아끼는 거래', to: '/market', bg: '#FFF6D9' },
  { icon: '🏠', title: '부동산', desc: '한인이 많이 찾는 지역의 렌트와 매물 정보', to: '/realestate', bg: '#F1ECFF' },
  { icon: '🏪', title: '업소록 · NEW 소식', desc: '우리 동네 한인 업소와 신장개업·폐업정리 소식', to: '/directory', bg: '#FFEEF2' },
]

const principles = [
  { title: '정확함이 먼저입니다', desc: '공식 기관의 안내를 기준으로 삼고 가능한 한 출처를 함께 적습니다. 확실하지 않은 내용은 단정하지 않고, 바뀔 수 있는 내용은 확인 방법을 알려 드립니다.' },
  { title: '숨기지 않습니다', desc: '글 작성에 AI 도구를 쓰고 있다는 점과 광고·제휴 링크가 있다는 점을 밝힙니다. 이용자가 스스로 판단할 수 있도록 필요한 정보는 투명하게 공개합니다.' },
  { title: '이용자의 제보를 반영합니다', desc: '틀린 내용을 알려 주시면 확인 후 고치거나 내립니다. 신고된 게시물과 개인정보 요청은 정해진 절차에 따라 처리합니다.' },
]

const steps = [
  { title: '궁금증에서 주제를 고릅니다', desc: '한인 이용자가 실제로 자주 묻는 질문과 검색되는 주제를 기준으로 다룰 내용을 정합니다.' },
  { title: '공식 자료를 확인합니다', desc: '연방·주 정부와 공공기관의 최신 안내를 바탕으로 핵심 내용, 금액, 기한을 정리합니다.' },
  { title: '쉬운 한국어로 풀어 씁니다', desc: '용어를 한국어로 풀어 설명하고, 순서와 준비물이 한눈에 보이도록 구성합니다. AI 도구가 초안을 돕습니다.' },
  { title: '게시 뒤에도 고칩니다', desc: '독자 제보와 제도 변경에 맞춰 내용을 수정하고, 더 이상 맞지 않는 글은 내립니다.' },
]
</script>
