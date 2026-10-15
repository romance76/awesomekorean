// 3D 추첨기 효과음 — 소리 파일 없이 WebAudio 로 직접 합성 (추가 용량 0)
// - 브라우저 자동재생 정책: 사용자가 버튼을 누른 뒤(resume) 에만 소리가 납니다. 소리를 낼 수 없는 상태면 조용히 건너뜁니다.
// - 모든 소리는 연출 전용입니다. 당첨 결과와는 무관합니다.

const MUTE_KEY = 'lottery_sound_muted'

export function readMuted() {
  try { return localStorage.getItem(MUTE_KEY) === '1' } catch (e) { return false }
}
function saveMuted(v) {
  try { localStorage.setItem(MUTE_KEY, v ? '1' : '0') } catch (e) { /* 저장 불가여도 동작 */ }
}

export function createLotterySound() {
  let ctx = null, master = null, noiseBuf = null
  let rumble = null          // { src, bp, gain }
  let allowed = true         // 관리자 설정(theme.sound_on)
  let muted = readMuted()    // 보는 사람이 끈 상태
  let clackAcc = 0

  function ensure() {
    if (ctx) return ctx
    const AC = typeof window !== 'undefined' ? (window.AudioContext || window.webkitAudioContext) : null
    if (!AC) return null
    try {
      ctx = new AC()
      master = ctx.createGain()
      master.gain.value = 0.55
      // 갑자기 큰 소리가 나지 않도록 가볍게 눌러 줌
      const comp = ctx.createDynamicsCompressor()
      comp.threshold.value = -18; comp.ratio.value = 4
      master.connect(comp); comp.connect(ctx.destination)
      const len = ctx.sampleRate * 2
      noiseBuf = ctx.createBuffer(1, len, ctx.sampleRate)
      const d = noiseBuf.getChannelData(0)
      for (let i = 0; i < len; i++) d[i] = Math.random() * 2 - 1
    } catch (e) { ctx = null }
    return ctx
  }

  // 버튼 클릭 같은 사용자 동작 안에서 호출 → 이후 소리 가능
  function unlock() {
    if (!allowed || muted) return
    const c = ensure()
    if (c && c.state === 'suspended') c.resume().catch(() => {})
  }
  const on = () => allowed && !muted && !!ctx && ctx.state === 'running'

  function env(g, t0, peak, attack, decay) {
    g.gain.cancelScheduledValues(t0)
    g.gain.setValueAtTime(0.0001, t0)
    g.gain.exponentialRampToValueAtTime(Math.max(0.0002, peak), t0 + attack)
    g.gain.exponentialRampToValueAtTime(0.0001, t0 + attack + decay)
  }
  function tone(freq, t0, dur, { type = 'sine', gain = 0.1, attack = 0.005, toFreq = null, dest = null } = {}) {
    const o = ctx.createOscillator(), g = ctx.createGain()
    o.type = type
    o.frequency.setValueAtTime(freq, t0)
    if (toFreq) o.frequency.exponentialRampToValueAtTime(toFreq, t0 + dur)
    env(g, t0, gain, attack, dur)
    o.connect(g); g.connect(dest || master)
    o.start(t0); o.stop(t0 + attack + dur + 0.05)
  }
  function noiseBurst(t0, dur, { gain = 0.1, f0 = 1000, f1 = null, q = 1, type = 'bandpass' } = {}) {
    const s = ctx.createBufferSource(), f = ctx.createBiquadFilter(), g = ctx.createGain()
    s.buffer = noiseBuf
    f.type = type; f.Q.value = q
    f.frequency.setValueAtTime(f0, t0)
    if (f1) f.frequency.exponentialRampToValueAtTime(f1, t0 + dur)
    env(g, t0, gain, Math.min(0.03, dur * 0.3), dur)
    s.connect(f); f.connect(g); g.connect(master)
    s.start(t0, Math.random()); s.stop(t0 + dur + 0.1)
  }

  // ── 공이 통 안에서 섞이는 소리: 낮은 웅웅거림(계속) + 공끼리 부딪히는 딱딱 소리(불규칙) ──
  function startRumble() {
    if (!on() || rumble) return
    const src = ctx.createBufferSource(), bp = ctx.createBiquadFilter(), gain = ctx.createGain()
    src.buffer = noiseBuf; src.loop = true
    bp.type = 'bandpass'; bp.frequency.value = 240; bp.Q.value = 0.8
    gain.gain.value = 0.0001
    src.connect(bp); bp.connect(gain); gain.connect(master)
    src.start()
    rumble = { src, bp, gain }
  }
  function stopRumble() {
    if (!rumble) return
    const r = rumble; rumble = null
    try {
      const t0 = ctx.currentTime
      r.gain.gain.cancelScheduledValues(t0)
      r.gain.gain.setTargetAtTime(0.0001, t0, 0.15)
      r.src.stop(t0 + 0.8)
    } catch (e) { /* 이미 멈춤 */ }
  }
  function clack(strength = 1) {
    const t0 = ctx.currentTime
    const base = 520 + Math.random() * 1500
    tone(base, t0, 0.05, { type: 'triangle', gain: 0.05 * strength, attack: 0.002, toFreq: base * 0.7 })
    noiseBurst(t0, 0.035, { gain: 0.035 * strength, f0: 2400 + Math.random() * 2200, q: 1.4 })
  }

  /** 매 프레임 호출: ph 가 mix/select 일 때만 소리 냄. tension 0~1 */
  function tick(dt, ph, tension) {
    if (!on()) return
    if (ph !== 'mix' && ph !== 'select') { if (rumble) stopRumble(); return }
    if (!rumble) startRumble()
    if (rumble) {
      const t0 = ctx.currentTime
      const lvl = ph === 'mix' ? 0.075 : 0.05 + tension * 0.07
      rumble.gain.gain.setTargetAtTime(lvl, t0, 0.2)
      rumble.bp.frequency.setTargetAtTime(220 + tension * 330 + (ph === 'mix' ? 60 : 0), t0, 0.3)
    }
    const rate = ph === 'mix' ? 9 : 4 + tension * 11   // 초당 딱딱 횟수 (긴장될수록 빨라짐)
    clackAcc += dt * rate * (0.6 + Math.random() * 0.8)
    let n = 0
    while (clackAcc >= 1 && n < 3) { clackAcc -= 1; n++; clack(0.7 + Math.random() * 0.6) }
    if (clackAcc > 3) clackAcc = 0
  }

  /** 등수 시작: 짧은 '쉬익' + 시작음 */
  function start() {
    if (!on()) return
    const t0 = ctx.currentTime
    noiseBurst(t0, 0.7, { gain: 0.1, f0: 400, f1: 3200, q: 0.9 })
    tone(330, t0 + 0.05, 0.25, { type: 'triangle', gain: 0.06, toFreq: 495 })
  }
  /** 아쉬운 공이 올라왔다가 내려갈 때: 올라가다 힘 빠지는 소리 */
  function near() {
    if (!on()) return
    const t0 = ctx.currentTime
    tone(300, t0, 0.6, { type: 'sawtooth', gain: 0.025, toFreq: 880, attack: 0.15 })
    tone(900, t0 + 0.62, 0.35, { type: 'sine', gain: 0.04, toFreq: 380 })
  }
  /** 당첨 공이 올라오기 시작: 상승음 + 반짝 */
  function lift() {
    if (!on()) return
    const t0 = ctx.currentTime
    tone(380, t0, 1.1, { type: 'sine', gain: 0.07, toFreq: 1500, attack: 0.25 })
    tone(760, t0, 1.1, { type: 'triangle', gain: 0.03, toFreq: 3000, attack: 0.3 })
    for (let i = 0; i < 5; i++) tone(1800 + Math.random() * 1800, t0 + 0.4 + i * 0.13, 0.12, { gain: 0.02 })
  }
  /** 공이 파이프를 타고 내려가는 소리 */
  function eject() {
    if (!on()) return
    stopRumble()
    const t0 = ctx.currentTime
    noiseBurst(t0, 1.5, { gain: 0.12, f0: 2200, f1: 260, q: 1.1 })
    tone(900, t0, 1.4, { type: 'sine', gain: 0.03, toFreq: 220, attack: 0.05 })
  }
  /** 바닥에 떨어져 튕길 때 (strength 0~1) */
  function thump(strength = 1) {
    if (!on()) return
    const s = Math.max(0.15, Math.min(1, strength))
    const t0 = ctx.currentTime
    tone(150, t0, 0.2, { type: 'sine', gain: 0.2 * s, toFreq: 55, attack: 0.003 })
    noiseBurst(t0, 0.08, { gain: 0.06 * s, f0: 900, q: 0.8 })
  }
  /** 당첨 발표 팡파르. big=마지막(1등) 발표면 더 크게 */
  function reveal(big = true) {
    if (!on()) return
    const t0 = ctx.currentTime
    const notes = big ? [523.25, 659.25, 783.99, 1046.5, 1318.5] : [523.25, 659.25, 783.99, 1046.5]
    notes.forEach((f, i) => {
      const s = t0 + i * 0.09
      tone(f, s, 0.5, { type: 'triangle', gain: 0.11, attack: 0.01 })
      tone(f * 2, s, 0.3, { type: 'sine', gain: 0.03, attack: 0.01 })
    })
    // 마지막 화음
    const c0 = t0 + notes.length * 0.09
    ;(big ? [523.25, 659.25, 783.99, 1046.5] : [523.25, 783.99]).forEach((f) => tone(f, c0, big ? 1.6 : 0.9, { type: 'triangle', gain: big ? 0.07 : 0.05, attack: 0.02 }))
    noiseBurst(c0, 0.5, { gain: 0.05, f0: 3000, f1: 8000, q: 0.7, type: 'highpass' })
    if (big) {
      for (let i = 0; i < 14; i++) tone(2200 + Math.random() * 2600, c0 + 0.1 + i * 0.1 + Math.random() * 0.05, 0.14, { gain: 0.025 })
    }
  }

  function setAllowed(v) {
    allowed = v !== false
    if (!allowed) stopRumble()
  }
  /** 보는 사람이 스피커 버튼으로 끄고/켬. 켤 때는 사용자 동작이므로 바로 unlock */
  function setMuted(v) {
    muted = !!v
    saveMuted(muted)
    if (muted) stopRumble()
    else unlock()
  }
  const isMuted = () => muted
  function dispose() {
    stopRumble()
    try { ctx?.close?.() } catch (e) { /* ignore */ }
    ctx = null; master = null; noiseBuf = null
  }

  return { unlock, tick, start, near, lift, eject, thump, reveal, setAllowed, setMuted, isMuted, dispose }
}
