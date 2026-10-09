<template>
  <section class="ls-root" :style="rootVars">
    <div class="ls-frame">
      <!-- 상단 바: 로고 / 타이틀 / 상태 -->
      <!-- 히어로: 3D 무대 + 유리 패널 오버레이 -->
      <div class="ls-hero">
        <div class="ls-stage">
          <Stage
            ref="stageRef"
            :theme="sweepstakes.theme || {}"
            :prize-name="sweepstakes.prize_name || ''"
            :prize-image="sweepstakes.prize_image || ''"
            :ticket-count="totalTickets"
            :winning-ticket="winningTicket"
            :winner-name="winnerName"
            hide-chrome
            @phase="onPhase"
            @finished="onFinished"
          />
          <div class="ls-logo" v-if="showLogo && !logoFailed">
            <span class="ls-logo-pill" :class="{ plain: logoIsDark }">
              <img :src="logoSrc" alt="AwesomeKorean" @error="logoFailed = true" />
            </span>
          </div>
          <div v-else-if="showLogo" class="ls-logo"><span class="ls-logo-text">AWESOME KOREAN</span></div>
          <div class="ls-live" :class="{ on: playing }">
            <i></i>{{ playing ? 'LIVE 재생 중' : drawn ? '추첨 완료' : 'STANDBY' }}
          </div>
          <div class="ls-status">{{ statusText }}</div>
        </div>

        <!-- 좌측: 오늘의 경품 -->
        <aside class="ls-panel ls-prize">
          <div class="ls-panel-title"><span class="ls-dot"></span>오늘의 경품</div>
          <div class="ls-prize-img">
            <img v-if="prizeImg && !prizeImgFailed" :src="prizeImg" alt="" @error="prizeImgFailed = true" />
            <span v-else>🎁</span>
          </div>
          <div class="ls-prize-name">{{ sweepstakes.prize_name || '경품' }}</div>
          <div v-if="prizeValueText" class="ls-prize-price">{{ prizeValueText }}</div>
          <dl class="ls-rows">
            <div class="ls-row"><dt>이벤트 마감</dt><dd>{{ endText }}</dd></div>
            <div class="ls-row"><dt>참가 회원</dt><dd>{{ participantsText }}</dd></div>
            <div class="ls-row"><dt>총 엔트리 수</dt><dd>{{ totalTickets.toLocaleString() }}장</dd></div>
            <div class="ls-row"><dt>당첨 인원</dt><dd>1명</dd></div>
          </dl>
        </aside>

        <!-- 우측: 당첨자 / 대기 -->
        <aside class="ls-panel ls-winner" :class="{ pending: !drawn }">
          <template v-if="drawn">
            <div class="ls-panel-title center"><span class="ls-dot"></span>오늘의 당첨자</div>
            <transition name="ls-pop" mode="out-in">
              <div v-if="showWinner" key="w" class="ls-winner-body">
                <div class="ls-congrats">🎉 축하합니다!</div>
                <div class="ls-avatar-wrap">
                  <svg class="laurel l" viewBox="0 0 52 104" overflow="visible" aria-hidden="true"><g v-for="n in LEAVES" :key="n" :transform="leafTf(n)"><ellipse cx="0" cy="0" rx="5" ry="11" fill="url(#lsgold)" /></g></svg>
                  <div class="ls-avatar">{{ initial }}</div>
                  <svg class="laurel r" viewBox="0 0 52 104" overflow="visible" aria-hidden="true"><g v-for="n in LEAVES" :key="n" :transform="leafTf(n)"><ellipse cx="0" cy="0" rx="5" ry="11" fill="url(#lsgold)" /></g></svg>
                </div>
                <div class="ls-winner-name">{{ winnerName || '비공개' }}<small>님</small></div>
                <div class="ls-ticket-label">당첨 엔트리 번호</div>
                <div class="ls-ticket">{{ ticketText }}</div>
                <div class="ls-winner-prize">
                  <div class="ls-mini-img">
                    <img v-if="prizeImg && !prizeImgFailed" :src="prizeImg" alt="" />
                    <span v-else>🎁</span>
                  </div>
                  <div class="ls-mini-name">{{ sweepstakes.prize_name }}</div>
                </div>
              </div>
              <div v-else key="p" class="ls-winner-body ls-drawing">
                <div class="ls-avatar ghost">?</div>
                <div class="ls-ticket ghost">???</div>
                <div class="ls-drawing-text">당첨자를 추첨하고 있어요…</div>
              </div>
            </transition>
          </template>
          <template v-else>
            <div class="ls-panel-title center"><span class="ls-dot"></span>추첨 대기 중</div>
            <div class="ls-winner-body">
              <div class="ls-wait-icon">⏳</div>
              <div class="ls-countdown">{{ countdownText }}</div>
              <div class="ls-ticket-label">{{ countdownLabel }}</div>
              <div class="ls-wait-note">마감 후 추첨이 진행되면 이곳에 당첨자가 공개됩니다.</div>
            </div>
          </template>
        </aside>
      </div>

      <!-- 진행 단계 -->
      <ol class="ls-steps">
        <li v-for="(s, i) in STEPS" :key="i" :class="{ active: activeStep === i, done: doneStep > i }">
          <span class="ls-step-art" aria-hidden="true">
            <svg viewBox="0 0 48 36" width="48" height="36">
              <template v-if="i === 0"><circle cx="14" cy="22" r="7" fill="#f1ba4b"/><circle cx="26" cy="14" r="7" fill="#ff7a90"/><circle cx="34" cy="25" r="7" fill="#6fb7ff"/><path d="M6 8c8-6 26-6 36 0" stroke="#fff" stroke-opacity=".6" fill="none" stroke-width="2" stroke-linecap="round"/></template>
              <template v-else-if="i === 1"><circle cx="24" cy="22" r="9" fill="#f1ba4b"/><path d="M24 2v8M16 4l6 8M32 4l-6 8" stroke="#fff" stroke-opacity=".75" stroke-width="2.4" stroke-linecap="round" fill="none"/></template>
              <template v-else-if="i === 2"><rect x="6" y="3" width="36" height="10" rx="5" fill="#fff" fill-opacity=".18"/><circle cx="24" cy="24" r="8" fill="#f1ba4b"/><path d="M24 12v4" stroke="#fff" stroke-width="2.4" stroke-linecap="round"/></template>
              <template v-else><circle cx="24" cy="18" r="13" fill="#f1ba4b"/><text x="24" y="23" text-anchor="middle" font-size="13" font-weight="900" fill="#3a2606">{{ ticketText }}</text><path d="M5 6l3 3M43 6l-3 3M24 1v4" stroke="#ffe9a8" stroke-width="2" stroke-linecap="round"/></template>
            </svg>
            <b class="ls-step-no">{{ doneStep > i && activeStep !== i ? '✓' : i + 1 }}</b>
          </span>
          <span class="ls-step-text"><b>{{ s.t }}</b><em>{{ s.d }}</em></span>
        </li>
      </ol>

      <!-- 버튼 -->
      <div class="ls-actions">
        <button v-if="drawn" type="button" class="ls-btn primary" :disabled="playing" @click="replay">
          ▶ {{ playing ? '재생 중…' : '전체 추첨 다시 보기' }}
        </button>
        <router-link to="/events" class="ls-btn ghost">☰ 이벤트 목록 보기</router-link>
      </div>

      <!-- 최근 당첨 기록 -->
      <div v-if="drawn && recentList.length" class="ls-recent">
        <div class="ls-recent-head">
          <span class="ls-panel-title"><span class="ls-dot"></span>최근 당첨 기록</span>
          <span class="ls-arrows">
            <button type="button" aria-label="이전" @click="scrollRecent(-1)">‹</button>
            <button type="button" aria-label="다음" @click="scrollRecent(1)">›</button>
          </span>
        </div>
        <div ref="recentEl" class="ls-recent-list">
          <div v-for="(r, i) in recentList" :key="r.sweepstakes_id || i" class="ls-recent-item">
            <div class="ls-recent-date">{{ fmtDate(r.drawn_at) }}</div>
            <div class="ls-ball">{{ pad(r.winning_ticket) }}</div>
            <div class="ls-recent-name">{{ r.winner_display_name || '비공개' }}<small>님</small></div>
            <div class="ls-recent-no">#{{ pad(r.winning_ticket) }}</div>
          </div>
        </div>
      </div>
    </div>

    <!-- 금색 그라디언트 정의(월계수용) -->
    <svg width="0" height="0" style="position:absolute" aria-hidden="true">
      <defs>
        <linearGradient id="lsgold" x1="0" y1="0" x2="1" y2="1">
          <stop offset="0" stop-color="#ffe9a8" /><stop offset="1" stop-color="#d9992a" />
        </linearGradient>
      </defs>
    </svg>
  </section>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, defineAsyncComponent } from 'vue'
import { useSiteStore } from '../stores/site'

const Stage = defineAsyncComponent(() => import('./LotteryStage3D.vue'))

const props = defineProps({
  sweepstakes: { type: Object, required: true },
  recent: { type: Array, default: () => [] },
})

const STEPS = [
  { t: '공 섞는 중', d: '무작위로 섞이는 중' },
  { t: '공 선택 중', d: '당첨 공을 고르는 중' },
  { t: '당첨 공 이동', d: '배출구로 이동 중' },
  { t: '당첨 번호 공개', d: '당첨 번호 발표' },
]
const LEAVES = [0, 1, 2, 3, 4, 5, 6, 7, 8]

const pad = (n) => String(n ?? '').padStart(3, '0')
const HEX = /^#[0-9a-fA-F]{6}$/
const hex = (v, d) => (typeof v === 'string' && HEX.test(v) ? v : d)

// ── 데이터 파생 ──
const drawn = computed(() => props.sweepstakes.status === 'winner_selected' && props.sweepstakes.draw?.winning_ticket != null)
const winningTicket = computed(() => props.sweepstakes.draw?.winning_ticket ?? null)
const totalTickets = computed(() => Number(props.sweepstakes.draw?.total_tickets || props.sweepstakes.total_entries || 0))
const winnerName = computed(() => props.sweepstakes.draw?.winner_display_name || props.sweepstakes.winner_display_name || '')
const ticketText = computed(() => pad(winningTicket.value))
const initial = computed(() => (winnerName.value || '?').trim().charAt(0) || '?')
const theme = computed(() => props.sweepstakes.theme || {})
const showLogo = computed(() => theme.value.show_logo !== false)
const siteStore = useSiteStore()
// 로고: 테마 지정 로고 > 사이트 다크용 로고 > 사이트 로고(어두운 글자이므로 반투명 흰 알약 위에 표시)
const logoIsDark = computed(() => !theme.value.logo_url && !!siteStore.logoDarkUrl)
const logoSrc = computed(() => theme.value.logo_url || siteStore.logoDarkUrl || siteStore.logoUrl)
const logoFailed = ref(false)
const prizeImg = computed(() => theme.value.prize_image_url || props.sweepstakes.prize_image || '')
const prizeImgFailed = ref(false)
const prizeValueText = computed(() => {
  const v = Number(props.sweepstakes.prize_value)
  return v > 0 ? `$${v.toLocaleString()}` : ''
})
const participantsText = computed(() => {
  const n = props.sweepstakes.draw?.participants_count ?? props.sweepstakes.participants_count
  return n != null ? `${Number(n).toLocaleString()}명` : '-'
})
const endDate = computed(() => {
  const d = props.sweepstakes.end_at ? new Date(props.sweepstakes.end_at) : null
  return d && !isNaN(d) ? d : null
})
const endText = computed(() => {
  const d = endDate.value
  if (!d) return '-'
  return `${d.getFullYear()}.${String(d.getMonth() + 1).padStart(2, '0')}.${String(d.getDate()).padStart(2, '0')}`
})
const recentList = computed(() => (Array.isArray(props.recent) ? props.recent : []).slice(0, 12))
const rootVars = computed(() => ({
  '--ls-top': hex(theme.value.bg_top, '#13244d'),
  '--ls-bot': hex(theme.value.bg_bottom, '#070d1f'),
}))

function fmtDate(iso) {
  const d = iso ? new Date(iso) : null
  if (!d || isNaN(d)) return '-'
  return `${d.getFullYear()}.${String(d.getMonth() + 1).padStart(2, '0')}.${String(d.getDate()).padStart(2, '0')}`
}
function leafTf(n) {
  // 아바타(원 중심 x=74,y=52, 반지름 50)를 감싸는 호 위에 잎사귀 배치
  const th = (118 + (n / (LEAVES.length - 1)) * 124) * Math.PI / 180
  const x = 74 + 50 * Math.cos(th)
  const y = 52 - 50 * Math.sin(th)
  return `translate(${x.toFixed(1)} ${y.toFixed(1)}) rotate(${(th * 57.3 - 180).toFixed(0)})`
}

// ── 카운트다운 (추첨 대기) ──
const now = ref(Date.now())
let clock = null
const countdownLabel = computed(() => (remainMs.value > 0 ? '마감까지 남은 시간' : '응모 마감 · 추첨 준비 중'))
const remainMs = computed(() => (endDate.value ? endDate.value.getTime() - now.value : 0))
const countdownText = computed(() => {
  if (!endDate.value) return '--:--:--'
  let s = Math.max(0, Math.floor(remainMs.value / 1000))
  const d = Math.floor(s / 86400); s -= d * 86400
  const h = Math.floor(s / 3600); s -= h * 3600
  const m = Math.floor(s / 60); s -= m * 60
  const p = (x) => String(x).padStart(2, '0')
  return `${d > 0 ? d + '일 ' : ''}${p(h)}:${p(m)}:${p(s)}`
})

// ── 재생 + 단계 하이라이트 (무대의 phase 이벤트가 없어 자체 타이밍으로 추정) ──
const stageRef = ref(null)
const playing = ref(false)
const showWinner = ref(true)
const stepIdx = ref(-1) // -1: 재생 전
let timers = []
// 단계별 최대 길이(초). 공 선택은 최대 15초. 워치독은 이 합 + 여유(>=45초)
const PHASE_MAX = { mix: 8, select: 15, eject: 8, reveal: 6 }
const WATCHDOG = Math.max(45, Object.values(PHASE_MAX).reduce((a, b) => a + b, 0) + 10)
const STATUS = ['행운의 공을 섞고 있습니다…', '당첨 공을 고르고 있습니다…', '당첨 공이 배출구로 이동합니다…', '당첨 번호를 공개합니다!']
const statusText = computed(() => {
  if (!drawn.value) return '추첨 대기 중입니다'
  if (playing.value) return STATUS[Math.max(0, stepIdx.value)] || STATUS[0]
  return '추첨이 완료되었습니다'
})

const activeStep = computed(() => {
  if (!drawn.value) return -1
  if (stepIdx.value === -1) return 3 // 결과 확정 상태
  return stepIdx.value
})
const doneStep = computed(() => (activeStep.value < 0 ? 0 : activeStep.value))

function clearTimers() { timers.forEach(clearTimeout); timers = [] }
function after(sec, fn) { timers.push(setTimeout(fn, sec * 1000)) }

function replay() {
  if (playing.value || !stageRef.value?.play) return
  stageRef.value.play()
  playing.value = true
  showWinner.value = false
  stepIdx.value = 0
  clearTimers()
  // 단계 표시는 무대의 phase 이벤트를 따른다. 무대가 아직 로딩 중이라 재생되지 않은 경우를 대비한 안전장치만 둔다
  after(WATCHDOG, onFinished)
}
// 무대가 알려주는 진행 단계 → 아래 1~4 단계 카드 하이라이트
function onPhase(p) {
  if (!playing.value) return
  const map = { mix: 0, select: 1, eject: 2, reveal: 3 }
  if (p in map) stepIdx.value = map[p]
}
function onFinished() {
  clearTimers()
  playing.value = false
  stepIdx.value = 3
  showWinner.value = true
}

// ── 최근 기록 스트립 ──
const recentEl = ref(null)
function scrollRecent(dir) {
  const el = recentEl.value
  if (el) el.scrollBy({ left: dir * Math.max(160, el.clientWidth * 0.7), behavior: 'smooth' })
}

onMounted(() => { clock = setInterval(() => { now.value = Date.now() }, 1000) })
onBeforeUnmount(() => { clearTimers(); if (clock) clearInterval(clock) })
</script>

<style scoped>
.ls-root { container-type: inline-size; --gold: #f1ba4b; --gold-soft: rgba(241, 186, 75, .35); color: #eaf0ff; }
.ls-frame {
  position: relative; border-radius: 20px; padding: 14px;
  background: radial-gradient(120% 90% at 50% 0%, var(--ls-top), var(--ls-bot) 70%);
  border: 1px solid var(--gold-soft);
  box-shadow: 0 0 0 1px rgba(255, 255, 255, .04) inset, 0 12px 40px rgba(3, 8, 24, .45), 0 0 36px rgba(241, 186, 75, .08);
}
.ls-frame::before { content: ''; position: absolute; inset: 5px; border-radius: 16px; border: 1px solid rgba(241, 186, 75, .14); pointer-events: none; }

/* 오버레이 요소(로고/LIVE/상태) */
.ls-logo { position: absolute; top: 12px; left: 12px; z-index: 3; pointer-events: none; }
.ls-logo-pill { display: inline-flex; align-items: center; padding: 5px 12px; border-radius: 999px; background: rgba(255, 255, 255, .92); box-shadow: 0 4px 14px rgba(0, 0, 0, .3); }
.ls-logo-pill.plain { background: transparent; box-shadow: none; padding: 0; }
.ls-logo-pill img { height: 24px; width: auto; max-width: 150px; object-fit: contain; display: block; }
.ls-logo-text { font-weight: 900; letter-spacing: .12em; font-size: 12px; color: var(--gold); }
.ls-live { position: absolute; top: 12px; right: 12px; z-index: 3; display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 999px; font-size: 11px; font-weight: 800; letter-spacing: .06em; background: rgba(8, 12, 28, .6); border: 1px solid rgba(255, 255, 255, .18); color: #c9d6f5; }
.ls-live i { width: 7px; height: 7px; border-radius: 50%; background: #7d8bb0; }
.ls-live.on { color: #fff; border-color: #ff5d7a; background: rgba(255, 93, 122, .25); }
.ls-live.on i { background: #ff5d7a; box-shadow: 0 0 8px #ff5d7a; animation: ls-blink 1s infinite; }
.ls-status { position: absolute; left: 50%; bottom: 14px; transform: translateX(-50%); z-index: 3; max-width: calc(100% - 24px); padding: 7px 18px; border-radius: 999px; font-size: 13px; font-weight: 800; color: #fff3cf; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; background: rgba(8, 12, 28, .62); border: 1px solid var(--gold-soft); -webkit-backdrop-filter: blur(8px); backdrop-filter: blur(8px); pointer-events: none; }

/* 히어로 (좁은 컨테이너: 무대 → 패널 2열/1열 스택) */
.ls-hero { display: grid; grid-template-columns: 1fr; gap: 12px; }
.ls-stage { position: relative; order: 1; min-width: 0; border-radius: 16px; overflow: hidden; border: 1px solid rgba(255, 255, 255, .1); box-shadow: 0 0 30px rgba(0, 0, 0, .35); }
.ls-stage :deep(.lottery-stage) { border: 0; border-radius: 0; box-shadow: none; }
.ls-winner { order: 2; }
.ls-prize { order: 3; }

/* 유리 패널 */
.ls-panel {
  position: relative; padding: 14px; border-radius: 16px;
  background: linear-gradient(160deg, rgba(255, 255, 255, .1), rgba(255, 255, 255, .03));
  -webkit-backdrop-filter: blur(14px); backdrop-filter: blur(14px);
  border: 1px solid rgba(241, 186, 75, .28);
  box-shadow: 0 8px 24px rgba(2, 6, 20, .4), 0 1px 0 rgba(255, 255, 255, .12) inset;
}
.ls-panel-title { display: inline-flex; align-items: center; gap: 7px; font-weight: 800; font-size: 13px; color: #ffe7a6; letter-spacing: .03em; }
.ls-panel-title.center { display: flex; justify-content: center; }
.ls-dot { width: 7px; height: 7px; background: var(--gold); transform: rotate(45deg); box-shadow: 0 0 8px var(--gold); }

.ls-prize-img { margin: 12px auto 10px; width: 112px; height: 112px; border-radius: 16px; display: flex; align-items: center; justify-content: center; overflow: hidden; background: rgba(255, 255, 255, .06); border: 1px solid var(--gold-soft); box-shadow: 0 0 22px rgba(241, 186, 75, .18); font-size: 52px; }
.ls-prize-img img, .ls-mini-img img { width: 100%; height: 100%; object-fit: cover; }
.ls-prize-name { text-align: center; font-weight: 800; font-size: 15px; line-height: 1.3; color: #fff; word-break: keep-all; }
.ls-prize-price { text-align: center; margin: 4px 0 10px; font-size: 34px; font-weight: 900; line-height: 1.1; background: linear-gradient(180deg, #fff1bd, #f1ba4b 70%, #c8861c); -webkit-background-clip: text; background-clip: text; color: transparent; filter: drop-shadow(0 2px 8px rgba(241, 186, 75, .35)); }
.ls-rows { margin: 8px 0 0; border-top: 1px solid rgba(255, 255, 255, .12); }
.ls-row { display: flex; justify-content: space-between; gap: 8px; padding: 8px 2px; font-size: 13px; border-bottom: 1px solid rgba(255, 255, 255, .07); }
.ls-row:last-child { border-bottom: 0; }
.ls-row dt { color: #aebbdc; }
.ls-row dd { margin: 0; font-weight: 800; color: #fff; text-align: right; }

/* 당첨자 */
.ls-winner-body { text-align: center; padding-top: 6px; }
.ls-congrats { font-weight: 900; font-size: 18px; margin: 6px 0 2px; color: #fff3cf; text-shadow: 0 0 14px rgba(241, 186, 75, .5); }
.ls-avatar-wrap { position: relative; display: flex; align-items: center; justify-content: center; height: 108px; margin: 4px 0; }
.ls-avatar { width: 76px; height: 76px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 32px; font-weight: 900; color: #3a2606; background: linear-gradient(145deg, #ffe9a8, #e0a030); border: 3px solid #fff3cf; box-shadow: 0 0 0 2px var(--gold), 0 0 26px rgba(241, 186, 75, .5); z-index: 1; }
.ls-avatar.ghost { margin: 12px auto 8px; background: rgba(255, 255, 255, .08); color: #8a97bb; border: 2px dashed rgba(255, 255, 255, .25); box-shadow: none; }
.laurel { position: absolute; height: 104px; width: 52px; top: 2px; filter: drop-shadow(0 0 6px rgba(241, 186, 75, .45)); }
.laurel.l { left: calc(50% - 74px); }
.laurel.r { left: calc(50% + 22px); transform: scaleX(-1); }
.ls-winner-name { font-size: 24px; font-weight: 900; color: #fff; word-break: keep-all; }
.ls-winner-name small, .ls-recent-name small { font-size: .62em; font-weight: 700; color: #cdd7f2; margin-left: 2px; }
.ls-ticket-label { margin-top: 10px; font-size: 12px; color: #aebbdc; letter-spacing: .06em; }
.ls-ticket { display: inline-block; margin-top: 4px; padding: 2px 22px; border-radius: 14px; font-size: 44px; line-height: 1.2; font-weight: 900; letter-spacing: .06em; color: #3a2606; background: linear-gradient(180deg, #fff1bd, #f1ba4b 60%, #d9992a); box-shadow: 0 0 24px rgba(241, 186, 75, .45), 0 2px 0 rgba(255, 255, 255, .6) inset; }
.ls-ticket.ghost { background: rgba(255, 255, 255, .08); color: #8a97bb; box-shadow: none; }
.ls-winner-prize { display: flex; align-items: center; gap: 10px; margin-top: 14px; padding: 8px 10px; border-radius: 12px; background: rgba(255, 255, 255, .06); border: 1px solid rgba(255, 255, 255, .1); text-align: left; }
.ls-mini-img { flex: none; width: 44px; height: 44px; border-radius: 10px; overflow: hidden; display: flex; align-items: center; justify-content: center; font-size: 24px; background: rgba(255, 255, 255, .08); }
.ls-mini-name { font-size: 13px; font-weight: 700; color: #e6edff; line-height: 1.3; }
.ls-drawing-text, .ls-wait-note { margin-top: 12px; font-size: 12.5px; color: #aebbdc; line-height: 1.5; }
.ls-wait-icon { font-size: 40px; margin-top: 8px; }
.ls-countdown { margin-top: 8px; font-size: 34px; font-weight: 900; font-variant-numeric: tabular-nums; color: #fff3cf; text-shadow: 0 0 18px rgba(241, 186, 75, .45); }
.ls-pop-enter-active { transition: all .6s cubic-bezier(.2, 1.2, .4, 1); }
.ls-pop-leave-active { transition: all .15s ease; }
.ls-pop-enter-from { opacity: 0; transform: scale(.85) translateY(14px); }
.ls-pop-leave-to { opacity: 0; }

/* 단계 */
.ls-steps { list-style: none; margin: 14px 0 0; padding: 0; display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
.ls-steps li { display: flex; align-items: center; gap: 9px; padding: 9px 10px; border-radius: 12px; background: rgba(255, 255, 255, .05); border: 1px solid rgba(255, 255, 255, .1); color: #8f9dc4; transition: all .3s; min-width: 0; }
.ls-step-art { position: relative; flex: none; width: 48px; height: 36px; border-radius: 8px; background: rgba(8, 12, 28, .45); display: block; opacity: .75; transition: opacity .3s; }
.ls-step-art svg { display: block; }
.ls-step-no { position: absolute; left: -6px; top: -6px; width: 18px; height: 18px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 10px; font-weight: 900; background: #2a3763; color: #cdd7f2; border: 1px solid rgba(255, 255, 255, .2); }
.ls-step-text { display: flex; flex-direction: column; min-width: 0; line-height: 1.25; }
.ls-step-text b { font-size: 13px; font-weight: 800; }
.ls-step-text em { font-style: normal; font-size: 11px; opacity: .8; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ls-steps li.done { color: #d7e1fb; }
.ls-steps li.done .ls-step-no { background: rgba(241, 186, 75, .35); color: var(--gold); }
.ls-steps li.active { color: #fff; border-color: var(--gold); background: linear-gradient(160deg, rgba(241, 186, 75, .22), rgba(241, 186, 75, .06)); box-shadow: 0 0 20px rgba(241, 186, 75, .28); }
.ls-steps li.active .ls-step-art, .ls-steps li.done .ls-step-art { opacity: 1; }
.ls-steps li.active .ls-step-no { background: linear-gradient(145deg, #ffe9a8, #e0a030); color: #3a2606; }

/* 버튼 */
.ls-actions { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 14px; }
.ls-btn { flex: 1 1 180px; display: inline-flex; align-items: center; justify-content: center; gap: 6px; padding: 12px 18px; border-radius: 14px; font-size: 14px; font-weight: 800; cursor: pointer; text-decoration: none; transition: transform .15s, box-shadow .15s, opacity .15s; }
.ls-btn.primary { color: #3a2606; border: 0; background: linear-gradient(180deg, #ffe9a8, #f1ba4b 60%, #d9992a); box-shadow: 0 6px 18px rgba(241, 186, 75, .3); }
.ls-btn.primary:hover:not(:disabled) { transform: translateY(-1px); box-shadow: 0 8px 22px rgba(241, 186, 75, .45); }
.ls-btn.primary:disabled { opacity: .55; cursor: not-allowed; }
.ls-btn.ghost { color: #fff3cf; background: rgba(255, 255, 255, .06); border: 1px solid var(--gold-soft); }
.ls-btn.ghost:hover { background: rgba(255, 255, 255, .12); }

/* 최근 당첨 */
.ls-recent { margin-top: 14px; padding: 12px; border-radius: 16px; background: rgba(255, 255, 255, .04); border: 1px solid rgba(255, 255, 255, .1); }
.ls-recent-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; }
.ls-arrows { display: inline-flex; gap: 6px; }
.ls-arrows button { width: 28px; height: 28px; border-radius: 50%; border: 1px solid var(--gold-soft); background: rgba(255, 255, 255, .06); color: var(--gold); font-size: 18px; line-height: 1; cursor: pointer; }
.ls-arrows button:hover { background: rgba(241, 186, 75, .2); }
.ls-recent-list { display: flex; gap: 10px; overflow-x: auto; scrollbar-width: none; scroll-snap-type: x proximity; }
.ls-recent-list::-webkit-scrollbar { display: none; }
.ls-recent-item { flex: none; width: 118px; scroll-snap-align: start; text-align: center; padding: 10px 8px; border-radius: 12px; background: rgba(255, 255, 255, .05); border: 1px solid rgba(255, 255, 255, .08); }
.ls-recent-date { font-size: 11px; color: #9fb0d8; }
.ls-ball { margin: 8px auto; width: 44px; height: 44px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 900; font-size: 14px; color: #3a2606; background: radial-gradient(circle at 32% 28%, #fff7d6, #f1ba4b 60%, #b97a14); box-shadow: 0 3px 10px rgba(0, 0, 0, .35); }
.ls-recent-name { font-size: 13px; font-weight: 800; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ls-recent-no { font-size: 11px; color: var(--gold); margin-top: 2px; }

/* 중간 컨테이너: 무대 아래 패널 2열 */
@container (min-width: 560px) {
  .ls-steps { grid-template-columns: repeat(4, 1fr); }
  .ls-hero { grid-template-columns: 1fr 1fr; }
  .ls-stage { grid-column: 1 / -1; }
  .ls-prize { order: 2; }
  .ls-winner { order: 3; }
}
/* 넓은 컨테이너: 큰 무대 위에 유리 패널을 겹쳐 표시 */
@container (min-width: 900px) {
  .ls-frame { padding: 20px 22px; }
  .ls-hero { display: block; position: relative; }
  .ls-stage { height: 580px; }
  .ls-stage :deep(.lottery-stage), .ls-stage :deep(.stage) { height: 100% !important; }
  .ls-logo { top: 16px; left: 18px; }
  .ls-logo-pill img { height: 30px; }
  .ls-live { right: 18px; top: 16px; }
  .ls-panel { position: absolute; z-index: 4; top: 64px; width: 240px; background: linear-gradient(160deg, rgba(255, 255, 255, .13), rgba(255, 255, 255, .04)), rgba(8, 14, 34, .35); }
  .ls-prize { left: 16px; }
  .ls-winner { right: 16px; width: 262px; }
  .ls-status { bottom: 18px; font-size: 14px; }
  .ls-actions { justify-content: center; }
  .ls-btn { flex: 0 1 260px; }
  .ls-recent-item { width: 132px; }
}
@keyframes ls-blink { 50% { opacity: .3; } }
@media (prefers-reduced-motion: reduce) { .ls-live.on i { animation: none; } .ls-pop-enter-active { transition: none; } }
</style>
