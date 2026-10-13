import { ref, computed } from 'vue'

// MONEY 글 글씨 크기(0 보통 · 1 크게 · 2 아주 크게) — 기기에 기억. 저장소를 못 쓰는 환경이어도 화면은 그대로 동작
const KEY = 'sk_money_font'
const SCALES = [1, 1.18, 1.38]
let start = 0
try { const v = Number(localStorage.getItem(KEY)); if (v >= 0 && v <= 2) start = v } catch {}
const level = ref(start)

export const moneyFont = {
  level,
  scale: computed(() => SCALES[level.value] || 1),
  set(v) { level.value = v; try { localStorage.setItem(KEY, String(v)) } catch {} },
}
