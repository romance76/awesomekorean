/**
 * RingtoneService — Web Audio API ringtone generator
 * Produces a phone-like ring pattern using oscillators.
 * No external audio files needed.
 */

let audioCtx = null
let ringtoneNodes = []
let isPlaying = false

const RING_PATTERN = [
  { freq: 440, duration: 0.4 },
  { freq: 480, duration: 0.4 },
]
const RING_GAP    = 1.0
const RING_REPEAT = 10

function getAudioContext() {
  if (!audioCtx || audioCtx.state === 'closed') {
    audioCtx = new (window.AudioContext || window.webkitAudioContext)()
  }
  if (audioCtx.state === 'suspended') audioCtx.resume()
  return audioCtx
}

export async function startRingtone() {
  if (isPlaying) return
  isPlaying = true
  try {
    const ctx = getAudioContext()
    let startTime = ctx.currentTime

    for (let ring = 0; ring < RING_REPEAT && isPlaying; ring++) {
      for (const tone of RING_PATTERN) {
        if (!isPlaying) break
        const osc  = ctx.createOscillator()
        const gain = ctx.createGain()
        osc.connect(gain)
        gain.connect(ctx.destination)
        osc.type = 'sine'
        osc.frequency.setValueAtTime(tone.freq, startTime)
        gain.gain.setValueAtTime(0, startTime)
        gain.gain.linearRampToValueAtTime(0.4, startTime + 0.02)
        gain.gain.linearRampToValueAtTime(0.4, startTime + tone.duration - 0.02)
        gain.gain.linearRampToValueAtTime(0, startTime + tone.duration)
        osc.start(startTime)
        osc.stop(startTime + tone.duration)
        ringtoneNodes.push(osc, gain)
        startTime += tone.duration
      }
      startTime += RING_GAP
    }

    // Vibrate pattern for mobile devices
    navigator.vibrate?.([700, 300, 700, 300, 700, 1000, 700, 300, 700])
  } catch (err) {
    console.warn('[RingtoneService] playback failed:', err)
  }
}

export function stopRingtone() {
  isPlaying = false
  navigator.vibrate?.(0)
  ringtoneNodes.forEach(n => {
    try { n.disconnect() } catch {}
    try { n.stop?.() } catch {}
  })
  ringtoneNodes = []
}
