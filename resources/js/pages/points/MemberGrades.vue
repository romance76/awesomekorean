<template>
<div class="min-h-screen">
  <div class="page-main px-4 py-5">
    <PageHeader title="회원 등급 안내" icon="trophy" fallback="/points" />
    <p class="text-xs text-ink-muted mb-4">활동으로 모은 포인트가 쌓일수록 프로필 사진 테두리가 화려해져요. 총 15단계예요.</p>

    <!-- 내 등급 -->
    <section v-if="auth.isLoggedIn && auth.user" class="card p-4 mb-4 overflow-hidden">
      <div class="flex items-center gap-2">
        <UserAvatar :user="auth.user" :level="myLevel" :size="140" class="-ml-3 -my-3" />
        <div class="min-w-0 flex-1">
          <div class="text-xs text-ink-muted">내 등급</div>
          <div class="text-lg font-extrabold text-ink leading-tight">Lv.{{ myLevel }} {{ myTier?.label }}</div>
          <div class="text-xs text-ink-light mt-1">지금까지 모은 포인트 <b class="text-ink">{{ lifetime.toLocaleString() }}P</b></div>
          <template v-if="nextTier">
            <div class="h-2 bg-gray-100 rounded-full overflow-hidden mt-2">
              <div class="h-full bg-gradient-to-r from-[#FF8A4D] to-[#FC226B] rounded-full transition-all" :style="{ width: progress + '%' }"></div>
            </div>
            <div class="text-[11px] text-ink-muted mt-1">다음 <b class="text-ink-light">{{ nextTier.label }}</b>까지 <b class="text-amber-600">{{ (nextTier.min - lifetime).toLocaleString() }}P</b> 남았어요</div>
          </template>
          <div v-else class="text-[11px] text-amber-600 font-bold mt-2">최고 등급이에요!</div>
        </div>
      </div>
    </section>
    <section v-else class="card p-4 mb-4 text-sm text-ink-light flex items-center justify-between gap-3">
      <span>로그인하면 내 등급과 다음 단계까지 남은 포인트를 볼 수 있어요.</span>
      <RouterLink to="/login" class="btn-primary px-4 py-2 text-xs whitespace-nowrap">로그인</RouterLink>
    </section>

    <!-- 15단계 -->
    <section class="mb-5">
      <h2 class="text-base font-bold text-ink mb-2">등급 한눈에 보기</h2>
      <div v-if="!tiers.length" class="text-center text-ink-faint py-8 text-sm">불러오는 중...</div>
      <div v-else class="grid grid-cols-2 sm:grid-cols-3 gap-3">
        <div v-for="t in tiers" :key="t.level" class="card p-3 text-center relative"
          :class="t.level === myLevel ? 'ring-2 ring-[#FC226B]' : ''">
          <span v-if="t.level === myLevel" class="absolute top-2 left-2 text-[10px] font-bold bg-[#FC226B] text-white rounded-full px-2 py-0.5">내 등급</span>
          <div class="flex justify-center -my-1">
            <UserAvatar :user="{ name: t.label }" :level="t.level" :size="120" />
          </div>
          <div class="text-[11px] text-ink-faint font-semibold mt-1">Lv.{{ t.level }}</div>
          <div class="text-sm font-extrabold text-ink">{{ t.label }}</div>
          <div class="text-[12px] text-amber-600 font-bold mt-0.5">{{ t.min === 0 ? '가입하면 바로' : t.min.toLocaleString() + 'P 이상' }}</div>
          <p class="text-[11px] text-ink-light leading-snug mt-1">{{ t.desc }}</p>
        </div>
      </div>
    </section>

    <!-- 올리는 방법 -->
    <section class="card p-4 mb-4">
      <h2 class="text-base font-bold text-ink mb-1">어떻게 하면 올라가나요?</h2>
      <p class="text-xs text-ink-light leading-relaxed mb-3">등급은 <b>지금까지 모은 포인트 총량</b>으로 정해져요. 포인트를 사용해도 등급은 내려가지 않아요.</p>
      <ul class="text-sm text-ink-light space-y-2">
        <li class="flex gap-2"><AppIcon name="check" :size="15" class="text-amber-500 mt-0.5 flex-shrink-0" /><span><b class="text-ink">가입하고 프로필을 채우세요.</b> 처음 한 번 받는 포인트가 있어서 첫날 바로 다음 단계로 올라갈 수 있어요.</span></li>
        <li class="flex gap-2"><AppIcon name="check" :size="15" class="text-amber-500 mt-0.5 flex-shrink-0" /><span><b class="text-ink">매일 들러서 로그인하고 룰렛을 돌리세요.</b> 하루 한 번씩 꾸준히 쌓여요.</span></li>
        <li class="flex gap-2"><AppIcon name="check" :size="15" class="text-amber-500 mt-0.5 flex-shrink-0" /><span><b class="text-ink">글과 댓글을 남기세요.</b> 커뮤니티·Q&amp;A·중고장터·구인구직·레시피 등에 쓰면 적립돼요. (하루 적립 한도가 있어요)</span></li>
        <li class="flex gap-2"><AppIcon name="check" :size="15" class="text-amber-500 mt-0.5 flex-shrink-0" /><span><b class="text-ink">도움이 되는 글을 쓰세요.</b> 내 글에 좋아요를 받거나 Q&amp;A 답변이 채택되면 포인트가 따로 들어와요.</span></li>
        <li class="flex gap-2"><AppIcon name="check" :size="15" class="text-amber-500 mt-0.5 flex-shrink-0" /><span><b class="text-ink">게임을 즐기세요.</b> 레벨업이나 신기록을 세우면 포인트를 받아요.</span></li>
        <li class="flex gap-2"><AppIcon name="check" :size="15" class="text-amber-500 mt-0.5 flex-shrink-0" /><span><b class="text-ink">거래와 이웃 활동에 참여하세요.</b> 장터 판매 완료, 공동구매, 채용 확정, 부동산 거래 완료 등에도 포인트가 있어요.</span></li>
      </ul>
      <RouterLink to="/points/rules" class="inline-flex items-center gap-1 mt-3 text-xs font-bold text-amber-600 hover:text-amber-700">포인트 적립·사용 규칙에서 정확한 적립량 보기 <AppIcon name="arrow-right" :size="13" /></RouterLink>
    </section>

    <!-- 속도 -->
    <section class="card p-4 mb-4">
      <h2 class="text-base font-bold text-ink mb-2">올라가는 속도는 이 정도예요</h2>
      <div class="text-xs text-ink-light space-y-1.5">
        <div class="flex justify-between gap-3"><span>브론즈 ~ 골드 (Lv.2~4)</span><b class="text-ink">며칠 ~ 몇 주</b></div>
        <div class="flex justify-between gap-3"><span>로즈 ~ 사파이어 (Lv.5~7)</span><b class="text-ink">한 달 ~ 몇 달</b></div>
        <div class="flex justify-between gap-3"><span>아쿠아 ~ 스타 사파이어 (Lv.8~10)</span><b class="text-ink">몇 달 ~ 1년 안팎</b></div>
        <div class="flex justify-between gap-3"><span>바이올렛 ~ 레전드 (Lv.11~15)</span><b class="text-ink">1년 이상</b></div>
      </div>
      <p class="text-[11px] text-ink-faint mt-2">매일 꾸준히 활동하는 경우의 대략적인 예시예요. 실제 속도는 활동량에 따라 달라요.</p>
    </section>

    <!-- 예전 등급 -->
    <section class="card p-4 mb-4">
      <h2 class="text-base font-bold text-ink mb-1">등급이 15단계로 바뀌었어요</h2>
      <p class="text-xs text-ink-light leading-relaxed mb-2">예전 10단계에서 15단계로 새로 나눴어요. 기준이 낮아졌기 때문에 기존 회원의 등급이 내려가는 일은 없어요.</p>
      <div class="text-[11px] text-ink-light grid grid-cols-2 gap-x-4 gap-y-1">
        <span>새싹 → Lv.1~2</span><span>초보 → Lv.3</span>
        <span>일반회원 → Lv.4</span><span>활동회원 → Lv.5</span>
        <span>우수회원 → Lv.7</span><span>인기회원 → Lv.9</span>
        <span>베테랑 → Lv.11</span><span>마스터 → Lv.14</span>
        <span class="col-span-2">레전드·명예의 전당 → Lv.15</span>
      </div>
    </section>

    <!-- 자주 묻는 질문 -->
    <section class="card p-4">
      <h2 class="text-base font-bold text-ink mb-2">자주 묻는 질문</h2>
      <div class="space-y-3 text-sm">
        <div><div class="font-bold text-ink">포인트를 쓰면 등급이 내려가나요?</div><p class="text-xs text-ink-light mt-0.5">아니요. 등급은 모은 포인트의 총량 기준이라 포인트를 써도 내려가지 않아요.</p></div>
        <div><div class="font-bold text-ink">내 등급은 어디에 보이나요?</div><p class="text-xs text-ink-light mt-0.5">프로필 사진 테두리로 표시돼요. 마이페이지, 프로필, 상단 내 사진에서 볼 수 있어요.</p></div>
        <div><div class="font-bold text-ink">다른 사람 등급도 볼 수 있나요?</div><p class="text-xs text-ink-light mt-0.5">네, 이름을 눌러 열리는 프로필과 팝업에서 볼 수 있어요.</p></div>
        <div><div class="font-bold text-ink">부정한 방법으로 포인트를 모으면요?</div><p class="text-xs text-ink-light mt-0.5">확인되면 포인트와 등급이 조정되고 이용이 제한될 수 있어요.</p></div>
      </div>
    </section>
  </div>
</div>
</template>

<script setup>
import { computed } from 'vue'
import { useAuthStore } from '../../stores/auth'
import { useMemberGrades } from '../../composables/useMemberGrades'
import UserAvatar from '../../components/UserAvatar.vue'
import AppIcon from '../../components/AppIcon.vue'
import PageHeader from '../../components/PageHeader.vue'

const auth = useAuthStore()
const { tiers, tierOf } = useMemberGrades()

const lifetime = computed(() => Number(auth.user?.lifetime_points || 0))
const myLevel = computed(() => Number(auth.user?.grade_level) || 1)
const myTier = computed(() => tierOf(myLevel.value))
const nextTier = computed(() => tierOf(myLevel.value + 1))
const progress = computed(() => {
  if (!myTier.value || !nextTier.value) return 100
  const span = Math.max(1, nextTier.value.min - myTier.value.min)
  return Math.max(0, Math.min(100, Math.round(((lifetime.value - myTier.value.min) / span) * 100)))
})
</script>
