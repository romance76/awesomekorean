<template>
<!--
  모든 게임의 공통 레이아웃 쉘.
  - 왼쪽 상단 뒤로가기 (통일)
  - 가운데 타이틀
  - 오른쪽 포인트/레벨/점수 배지
  - 아래 기본 슬롯 (게임 콘텐츠)
  - 전체창(fullscreen)/카드형(card) 테마 선택 가능
  props:
    title, icon, level, score, bg(그라디언트), theme('light'|'dark'), back(경로), fullscreen(bool)
-->
<div :class="['game-shell', `theme-${theme}`, fullscreen ? 'is-fullscreen' : 'is-card']" :style="fullscreen && bg ? { background: bg } : {}">
  <div v-if="!fullscreen" class="shell-blobs" aria-hidden="true"><span class="b1"></span><span class="b2"></span><span class="b3"></span></div>
  <div class="shell-inner">
    <div class="shell-header">
      <button class="shell-back" @click="onBack" aria-label="뒤로">
        <span class="arrow">←</span>
        <span class="label">뒤로</span>
      </button>
      <div class="shell-title">
        <span v-if="icon" class="shell-icon">{{ icon }}</span>
        <span>{{ title }}</span>
      </div>
      <div class="shell-meta">
        <slot name="meta">
          <span v-if="level !== null && level !== undefined" class="shell-badge badge-lv">Lv.{{ level }}</span>
          <span v-if="score !== null && score !== undefined" class="shell-badge badge-score">⭐ {{ score }}</span>
          <span v-if="points !== null && points !== undefined" class="shell-badge badge-pts">🪙 {{ points }}</span>
        </slot>
      </div>
    </div>

    <div class="shell-body" :style="!fullscreen && bg ? { background: bg } : {}">
      <slot />
    </div>
  </div>
</div>
</template>

<script setup>
import { useRouter } from 'vue-router'
const props = defineProps({
  title: { type: String, default: '게임' },
  icon: { type: String, default: '' },
  level: { default: null },
  score: { default: null },
  points: { default: null },
  bg: { type: String, default: '' },
  theme: { type: String, default: 'light' }, // 'light' | 'dark'
  back: { type: String, default: '/games' },
  fullscreen: { type: Boolean, default: false },
})
const emit = defineEmits(['back'])
const router = useRouter()
function onBack() {
  if (emit) emit('back')
  router.push(props.back)
}
</script>

<style scoped>
.game-shell { min-height: 100vh; display: flex; flex-direction: column; position: relative; }
/* 카드형(is-card)은 사이트 표준 배경 위에 게임 고유 색을 담은 카드가 놓이는
   구조 — 게임 배경이 브라우저 전체 폭을 그대로 차지해 "완전히 다른 페이지로
   전환된 것처럼" 보이던 문제 수정. 전체화면(is-fullscreen) 모드는 원래
   의도대로 그대로 엣지투엣지 유지. */
/* 글자색은 카드형/전체화면 여부와 무관하게 항상 게임의 테마(theme)를 따름 —
   이전엔 카드형(is-card)일 때 테마와 무관하게 어두운 잉크색으로 고정돼,
   다크테마 게임의 어두운 헤더 배경과 겹쳐 글자가 안 보이던 문제 수정. */
.game-shell.theme-light { color: #191F28; }
.game-shell.theme-dark { color: #f3f4f6; }
/* 은은한 메시 그라디언트 배경 — 밋밋한 단색 대신 앱스토어 인기게임 느낌의
   블러 블롭 무드를 깐다. is-fullscreen은 게임 고유 bg가 이미 전체를 채우므로
   그대로 둠. */
.game-shell.is-card.theme-light { background: linear-gradient(160deg,#f1eefc 0%,#fdf1f7 45%,#eef6fb 100%); }
.game-shell.is-card.theme-dark { background: linear-gradient(160deg,#0d0b1e 0%,#150f2e 50%,#0a1224 100%); }
.game-shell.is-fullscreen.theme-light { background: #F7F8FA; }
.game-shell.is-fullscreen.theme-dark { background: #0b1020; }

/* 카드 뒤 은은한 블러 블롭 — 클릭 통과, fixed 고정 */
.shell-blobs { position: fixed; inset: 0; z-index: 0; overflow: hidden; pointer-events: none; }
.shell-blobs span { position: absolute; border-radius: 50%; filter: blur(70px); opacity: .4; }
.shell-blobs .b1 { width: 320px; height: 320px; top: -80px; left: -60px; background: #8b5cf6; }
.shell-blobs .b2 { width: 280px; height: 280px; bottom: -60px; right: -40px; background: #ec4899; }
.shell-blobs .b3 { width: 240px; height: 240px; top: 40%; right: 10%; background: #38bdf8; opacity: .25; }
.theme-dark .shell-blobs span { opacity: .28; }

.shell-header {
  position: relative; z-index: 20;
  display: flex; align-items: center; justify-content: space-between;
  padding: 12px 16px;
  backdrop-filter: blur(20px) saturate(160%);
  -webkit-backdrop-filter: blur(20px) saturate(160%);
  background: rgba(255, 255, 255, 0.6);
  border-bottom: 1px solid rgba(255,255,255,0.5);
}
.theme-dark .shell-header { background: rgba(13, 12, 28, 0.62); border-color: rgba(255,255,255,0.1); }
/* 전체창(fullscreen) 모드에서만 상단 고정 내비바로 동작 — 카드형은
   shell-inner 카드 안에 얹혀 있으므로 sticky를 주지 않음 */
.is-fullscreen .shell-header { position: sticky; top: 0; }

.shell-back {
  display: inline-flex; align-items: center; gap: 4px;
  background: rgba(255,255,255,0.5); border: 1px solid rgba(255,255,255,0.6); color: inherit;
  padding: 7px 14px; border-radius: 999px; cursor: pointer;
  font-size: 13px; font-weight: 700;
  box-shadow: 0 2px 8px rgba(31,38,80,0.08);
  transition: transform .15s ease, background 0.15s ease, box-shadow .15s ease;
}
.shell-back:hover { background: rgba(255,255,255,0.8); transform: translateY(-1px); box-shadow: 0 4px 12px rgba(31,38,80,0.12); }
.theme-dark .shell-back { background: rgba(255,255,255,0.08); border-color: rgba(255,255,255,0.14); }
.theme-dark .shell-back:hover { background: rgba(255,255,255,0.16); }
.shell-back .arrow { font-size: 16px; }

.shell-title {
  font-size: 15px; font-weight: 800;
  display: flex; align-items: center; gap: 6px;
  position: absolute; left: 50%; transform: translateX(-50%);
}
.shell-icon {
  font-size: 19px; display: inline-flex; align-items: center; justify-content: center;
  width: 30px; height: 30px; border-radius: 10px;
  background: linear-gradient(135deg, rgba(139,92,246,0.18), rgba(236,72,153,0.18));
}

.shell-meta { display: flex; align-items: center; gap: 6px; }
.shell-badge {
  font-size: 11.5px; font-weight: 800; padding: 5px 12px; color: #fff;
  border-radius: 999px; white-space: nowrap;
  box-shadow: 0 3px 10px -2px rgba(0,0,0,0.18), inset 0 1px 0 rgba(255,255,255,0.3);
}
.badge-lv { background-image: linear-gradient(135deg,#8b5cf6,#6d28d9); }
.badge-score { background-image: linear-gradient(135deg,#fbbf24,#f59e0b); }
.badge-pts { background-image: linear-gradient(135deg,#fbbf24,#f59e0b); }

.shell-inner { flex: 1; display: flex; flex-direction: column; position: relative; z-index: 1; }
.shell-body { flex: 1; display: flex; flex-direction: column; }

/* 카드형: 사이트 배경 위에 게임 고유 색(bg prop)을 담은 둥근 유리질감 카드로
   표시 — 헤더와 바디를 shell-inner 하나로 묶어 같은 폭/모서리/그림자를
   공유하게 해서 "두 개의 서로 다른 폭의 띠"로 보이던 문제 수정. 카드 밖으로는
   블러 블롭이 있는 은은한 메시 그라디언트 배경이 계속 보임. */
.is-card .shell-inner {
  max-width: 900px; margin: 20px auto; width: calc(100% - 32px);
  border-radius: 26px; overflow: hidden;
  border: 1px solid rgba(255,255,255,0.6);
  box-shadow: 0 20px 50px -12px rgba(31,38,80,0.22), 0 2px 8px rgba(31,38,80,0.08), inset 0 1px 0 rgba(255,255,255,0.5);
}
.theme-dark .is-card .shell-inner {
  border-color: rgba(255,255,255,0.1);
  box-shadow: 0 20px 50px -12px rgba(0,0,0,0.5), inset 0 1px 0 rgba(255,255,255,0.06);
}
.is-card .shell-body { padding: 18px; }
/* 전체창: 여백 없음, 엣지투엣지 그대로 유지 */
.is-fullscreen .shell-body { padding: 0; }

@media (max-width: 640px) {
  .shell-title { font-size: 13px; }
  .shell-title .shell-icon { font-size: 16px; width: 26px; height: 26px; }
  .shell-back .label { display: none; }
  .shell-back { padding: 7px 11px; }
  .shell-badge { font-size: 11px; padding: 4px 9px; }
  .is-card .shell-inner { margin: 12px auto; border-radius: 20px; }
}
</style>
