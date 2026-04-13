/**
 * usePokerSound — 포커 게임 효과음
 * Web Audio API로 간단한 효과음 생성 (파일 불필요)
 */

let audioCtx = null

function getCtx() {
  if (!audioCtx) {
    audioCtx = new (window.AudioContext || window.webkitAudioContext)()
  }
  return audioCtx
}

function playTone(freq, duration = 0.1, type = 'sine', volume = 0.15) {
  try {
    const ctx = getCtx()
    const osc = ctx.createOscillator()
    const gain = ctx.createGain()
    osc.type = type
    osc.frequency.value = freq
    gain.gain.value = volume
    gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + duration)
    osc.connect(gain)
    gain.connect(ctx.destination)
    osc.start(ctx.currentTime)
    osc.stop(ctx.currentTime + duration)
  } catch {}
}

export function usePokerSound() {
  // 카드 딜링 (짧은 틱 소리)
  function soundDeal() {
    playTone(800, 0.03, 'square', 0.08)
    setTimeout(() => playTone(600, 0.03, 'square', 0.06), 50)
  }

  // 칩 베팅 (동전 떨어지는 소리)
  function soundBet() {
    playTone(1200, 0.05, 'sine', 0.1)
    setTimeout(() => playTone(1800, 0.04, 'sine', 0.08), 40)
    setTimeout(() => playTone(2200, 0.03, 'sine', 0.06), 80)
  }

  // 폴드 (낮은 톤)
  function soundFold() {
    playTone(300, 0.15, 'sine', 0.06)
  }

  // 체크 (톡톡)
  function soundCheck() {
    playTone(500, 0.05, 'square', 0.05)
    setTimeout(() => playTone(500, 0.05, 'square', 0.05), 100)
  }

  // 올인 (드라마틱)
  function soundAllIn() {
    playTone(400, 0.1, 'sawtooth', 0.1)
    setTimeout(() => playTone(600, 0.1, 'sawtooth', 0.12), 100)
    setTimeout(() => playTone(800, 0.15, 'sawtooth', 0.15), 200)
  }

  // 승리 (팡파레)
  function soundWin() {
    const notes = [523, 659, 784, 1047]
    notes.forEach((f, i) => {
      setTimeout(() => playTone(f, 0.2, 'sine', 0.12), i * 120)
    })
  }

  // 패배 (하강)
  function soundLose() {
    playTone(400, 0.2, 'sine', 0.08)
    setTimeout(() => playTone(300, 0.2, 'sine', 0.06), 150)
    setTimeout(() => playTone(200, 0.3, 'sine', 0.04), 300)
  }

  // 내 턴 알림
  function soundMyTurn() {
    playTone(880, 0.08, 'sine', 0.1)
    setTimeout(() => playTone(1100, 0.1, 'sine', 0.12), 100)
  }

  // 커뮤니티 카드 오픈 (플롭/턴/리버)
  function soundFlop() {
    for (let i = 0; i < 3; i++) {
      setTimeout(() => playTone(700 + i * 100, 0.04, 'square', 0.07), i * 80)
    }
  }

  // AudioContext resume (모바일 필수 — 첫 터치 시 호출)
  function resumeAudio() {
    try { getCtx().resume() } catch {}
  }

  return {
    soundDeal, soundBet, soundFold, soundCheck, soundAllIn,
    soundWin, soundLose, soundMyTurn, soundFlop, resumeAudio
  }
}
