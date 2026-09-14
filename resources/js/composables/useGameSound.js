/**
 * useGameSound — 미니게임 공용 효과음
 * Web Audio API로 간단한 효과음 생성 (파일 불필요) — usePokerSound와 동일한 패턴,
 * 퀴즈/캐주얼 게임 전반에서 쓰는 "정답/오답/레벨업/클릭" 톤을 하나로 통일.
 */

let audioCtx = null

function getCtx() {
  if (!audioCtx) {
    audioCtx = new (window.AudioContext || window.webkitAudioContext)()
  }
  return audioCtx
}

function playTone(freq, duration = 0.1, type = 'sine', volume = 0.15, delay = 0) {
  try {
    const ctx = getCtx()
    const osc = ctx.createOscillator()
    const gain = ctx.createGain()
    osc.type = type
    osc.frequency.setValueAtTime(freq, ctx.currentTime + delay)
    gain.gain.setValueAtTime(volume, ctx.currentTime + delay)
    gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + delay + duration)
    osc.connect(gain)
    gain.connect(ctx.destination)
    osc.start(ctx.currentTime + delay)
    osc.stop(ctx.currentTime + delay + duration)
  } catch {}
}

export function useGameSound() {
  // 정답 (경쾌한 2음)
  function correct() {
    playTone(660, 0.12, 'triangle', 0.18)
    playTone(880, 0.14, 'triangle', 0.18, 0.08)
  }

  // 오답 (짧은 하강음)
  function wrong() {
    playTone(220, 0.22, 'sawtooth', 0.14)
  }

  // 레벨업 / 승리 (팡파레)
  function levelUp() {
    const notes = [523, 659, 784, 1047, 1319]
    notes.forEach((f, i) => playTone(f, 0.3, 'triangle', 0.2, i * 0.11))
  }

  // 게임오버 / 패배 (하강)
  function gameOver() {
    playTone(400, 0.2, 'sine', 0.1)
    playTone(300, 0.2, 'sine', 0.08, 0.15)
    playTone(200, 0.3, 'sine', 0.06, 0.3)
  }

  // 버튼 클릭 / 타일 뒤집기 (짧은 틱)
  function click() {
    playTone(700, 0.05, 'sine', 0.08)
  }

  // AudioContext resume (모바일 필수 — 첫 터치 시 호출)
  function resumeAudio() {
    try { getCtx().resume() } catch {}
  }

  return { correct, wrong, levelUp, gameOver, click, resumeAudio }
}
