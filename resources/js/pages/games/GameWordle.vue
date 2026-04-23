<template>
<GameShell title="한국어 워들" icon="🔡" :points="coinStr">
  <template #meta>
    <button @click="newGame" class="new-btn">새 게임</button>
  </template>

  <div class="wordle-box">
    <div class="wordle-card">
      <!-- 게임 그리드 (4글자 × 6회) -->
      <div class="grid-rows">
        <div v-for="(row, ri) in board" :key="ri" class="grid-row">
          <div v-for="(cell, ci) in row" :key="ci"
            class="grid-cell"
            :class="getCellClass(ri, ci)">
            {{ cell }}
          </div>
        </div>
      </div>

      <!-- 결과 메시지 -->
      <div v-if="gameState !== 'playing'" class="result-box"
        :class="gameState === 'won' ? 'won' : 'lost'">
        <div class="result-title">
          {{ gameState === 'won' ? '🎉 정답!' : '😢 아쉬워요' }}
        </div>
        <div class="result-answer">정답: <strong>{{ answer }}</strong></div>
        <div v-if="gameState === 'won'" class="result-reward">+30 🪙 COIN 획득!</div>
        <button @click="newGame" class="result-btn">새 게임</button>
      </div>

      <!-- 현재 입력 표시 -->
      <div v-if="gameState === 'playing'" class="current-row">
        <div v-for="i in WORD_LEN" :key="i"
          class="grid-cell current"
          :class="currentInput[i-1] ? 'filled' : 'empty'">
          {{ currentInput[i-1] || '' }}
        </div>
      </div>

      <!-- 범례 -->
      <div class="legend">
        <span><span class="dot correct"></span>정확한 위치</span>
        <span><span class="dot present"></span>다른 위치</span>
        <span><span class="dot absent"></span>없음</span>
      </div>
    </div>

    <!-- 키보드 -->
    <div class="keyboard-card">
      <div v-for="(row, ri) in keyboard" :key="ri" class="keyboard-row">
        <button v-for="key in row" :key="key"
          @click="typeKey(key)"
          class="key"
          :class="getKeyClass(key)">
          {{ key }}
        </button>
      </div>
      <div class="action-row">
        <button @click="deleteLast" class="btn-delete">⌫ 지우기</button>
        <button @click="submitGuess" :disabled="currentInput.length !== WORD_LEN" class="btn-submit">입력 ↵</button>
      </div>
    </div>
  </div>
</GameShell>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import GameShell from '../../components/GameShell.vue'

const WORD_LEN = 4
const MAX_ROWS = 6

// 4글자 한국어 단어 80+ (음식, 일상어, 관용표현, 명사구)
const WORDS = [
  // 한국 음식 (30)
  '김치찌개','된장찌개','순두부찌','부대찌개','미역국밥',
  '불고기밥','삼겹살집','비빔냉면','떡볶이집','잡채볶음',
  '제육볶음','오징어볶','닭갈비볶','감자탕집','해물파전',
  '빈대떡집','순대국밥','설렁탕집','곰탕국밥','삼계탕집',
  '돼지국밥','족발보쌈','냉면가게','막국수집','칼국수집',
  '잔치국수','콩나물국','갈비탕집','매운탕집','갈비찜집',
  // 동물 (10)
  '강아지들','고양이들','호랑이들','코끼리들','기린친구',
  '돼지가족','토끼친구','원숭이들','펭귄친구','여우친구',
  // 일상어/명사구 (20)
  '아침식사','점심시간','저녁노을','밤하늘에','새벽공기',
  '봄바람이','여름휴가','가을단풍','겨울눈길','봄여름가',
  '학교친구','가족사랑','친구사이','선생님들','동네식당',
  '우리이웃','우리엄마','우리아빠','할머니댁','할아버지',
  // 장소/자연 (15)
  '한강공원','남산타워','명동거리','강남역앞','종로거리',
  '인사동길','동대문앞','청계천길','홍대입구','이태원로',
  '바닷가에','산꼭대기','강변공원','시골마을','도시야경',
  // 감정/행동 (10)
  '사랑하는','행복하게','즐거운일','신나게놀','반가워요',
  '고마워요','미안하다','응원합니','기쁜마음','따뜻한말',
]

const keyboard = [
  ['ㅂ','ㅈ','ㄷ','ㄱ','ㅅ','ㅛ','ㅕ','ㅑ','ㅐ','ㅔ'],
  ['ㅁ','ㄴ','ㅇ','ㄹ','ㅎ','ㅗ','ㅓ','ㅏ','ㅣ'],
  ['ㅋ','ㅌ','ㅊ','ㅍ','ㅠ','ㅜ','ㅡ'],
]

const answer = ref(WORDS[Math.floor(Math.random() * WORDS.length)])
const board = ref(Array(MAX_ROWS).fill(null).map(() => Array(WORD_LEN).fill('')))
const results = ref(Array(MAX_ROWS).fill(null).map(() => Array(WORD_LEN).fill('')))
const currentRow = ref(0)
const currentInput = ref('')
const gameState = ref('playing')
const usedKeys = reactive({})
const coin = ref(parseInt(localStorage.getItem('wordle_coin') || '0'))
const coinStr = computed(() => coin.value.toLocaleString())

function submitGuess() {
  const g = currentInput.value
  if (g.length !== WORD_LEN || currentRow.value >= MAX_ROWS || gameState.value !== 'playing') return
  const row = currentRow.value
  const ans = answer.value
  const res = Array(WORD_LEN).fill('absent')
  const ansArr = ans.split('')
  const gArr = g.split('')
  gArr.forEach((ch, i) => { if (ch === ansArr[i]) { res[i] = 'correct'; ansArr[i] = null } })
  gArr.forEach((ch, i) => {
    if (res[i] === 'correct') return
    const idx = ansArr.indexOf(ch)
    if (idx !== -1) { res[i] = 'present'; ansArr[idx] = null }
  })
  board.value[row] = gArr
  results.value[row] = res
  // 키보드 색상: 자모로 분해해서 색칠 (한글 음절 → 자모 매핑은 복잡하니, 음절 자체로 매핑)
  gArr.forEach((ch, i) => {
    const cur = usedKeys[ch]
    if (res[i] === 'correct') usedKeys[ch] = 'correct'
    else if (res[i] === 'present' && cur !== 'correct') usedKeys[ch] = 'present'
    else if (!cur) usedKeys[ch] = 'absent'
  })
  if (g === ans) {
    gameState.value = 'won'
    coin.value += 30
    localStorage.setItem('wordle_coin', coin.value)
  }
  else if (row === MAX_ROWS - 1) { gameState.value = 'lost' }
  currentRow.value++
  currentInput.value = ''
}

// 자모 입력 → 한글 음절 조합 (간단 버전: 한 글자씩 직접 입력 아닌 자모 조합 필요)
// 현 구현: 자모 키를 눌렀을 때 그냥 자모 char 를 추가. 사용자가 한글 음절 입력해야 함.
// 대안: 전체 한글 자판 (완성형) 키보드로 전환 필요하나 복잡. 현재는 키보드 선택 자유.
function typeKey(k) {
  // 4 cells 니까 자모 4개가 아닌 음절 4개. 자모 키 UI 는 참고용.
  // 유저는 실제로는 한글 IME 로 입력하는 게 정상 - 이 키보드는 자주 사용된 자모 색상 확인용.
}

function deleteLast() { currentInput.value = currentInput.value.slice(0, -1) }

// 외부에서 키 입력 받기 (IME)
function handleKeyPress(e) {
  if (gameState.value !== 'playing') return
  const ch = e.key
  if (e.key === 'Backspace' || e.key === 'Delete') { deleteLast(); return }
  if (e.key === 'Enter') { submitGuess(); return }
  // 한글 음절(AC00-D7A3)만 받음
  if (ch && ch.length === 1 && ch.charCodeAt(0) >= 0xAC00 && ch.charCodeAt(0) <= 0xD7A3) {
    if (currentInput.value.length < WORD_LEN) currentInput.value += ch
  }
}
window.addEventListener('keydown', handleKeyPress)

function getCellClass(ri, ci) {
  const r = results.value[ri][ci]
  if (!board.value[ri][ci] && ri === currentRow.value) return 'cell-empty'
  if (!r && board.value[ri][ci]) return 'cell-pending'
  if (r === 'correct') return 'cell-correct'
  if (r === 'present') return 'cell-present'
  if (r === 'absent') return 'cell-absent'
  return 'cell-empty'
}

function getKeyClass(k) {
  const s = usedKeys[k]
  if (s === 'correct') return 'key-correct'
  if (s === 'present') return 'key-present'
  if (s === 'absent') return 'key-absent'
  return ''
}

function newGame() {
  answer.value = WORDS[Math.floor(Math.random() * WORDS.length)]
  board.value = Array(MAX_ROWS).fill(null).map(() => Array(WORD_LEN).fill(''))
  results.value = Array(MAX_ROWS).fill(null).map(() => Array(WORD_LEN).fill(''))
  currentRow.value = 0; currentInput.value = ''; gameState.value = 'playing'
  Object.keys(usedKeys).forEach(k => delete usedKeys[k])
}

import { onUnmounted } from 'vue'
onUnmounted(() => { window.removeEventListener('keydown', handleKeyPress) })
</script>

<style scoped>
.new-btn { background: rgba(99, 102, 241, 0.1); color: #4338ca; border: 1px solid rgba(99,102,241,0.3); padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 700; cursor: pointer; }
.new-btn:hover { background: rgba(99,102,241,0.2); }

.wordle-box { max-width: 480px; margin: 0 auto; padding: 16px; display: flex; flex-direction: column; gap: 12px; width: 100%; }
.wordle-card { background: #fff; border-radius: 18px; box-shadow: 0 4px 16px rgba(0,0,0,0.06); padding: 20px; }

.grid-rows { display: flex; flex-direction: column; gap: 6px; margin-bottom: 16px; }
.grid-row { display: flex; gap: 6px; justify-content: center; }
.grid-cell { width: 54px; height: 54px; display: flex; align-items: center; justify-content: center; font-size: 22px; font-weight: 900; border: 2px solid #e5e7eb; border-radius: 12px; transition: all 0.3s; background: #fff; color: #1f2937; }
.cell-empty { background: #fff; border-color: #e5e7eb; }
.cell-pending { background: #f9fafb; border-color: #d1d5db; color: #6b7280; }
.cell-correct { background: #10b981; border-color: #10b981; color: #fff; box-shadow: 0 2px 8px rgba(16,185,129,0.3); }
.cell-present { background: #f59e0b; border-color: #f59e0b; color: #fff; box-shadow: 0 2px 8px rgba(245,158,11,0.3); }
.cell-absent { background: #e5e7eb; border-color: #e5e7eb; color: #9ca3af; }

.current-row { display: flex; gap: 6px; justify-content: center; margin-bottom: 12px; }
.current-row .grid-cell { border-color: #c7d2fe; }
.current-row .grid-cell.filled { background: #eef2ff; color: #4338ca; border-color: #6366f1; }

.result-box { text-align: center; padding: 20px 16px; border-radius: 16px; margin-bottom: 14px; }
.result-box.won { background: #ecfdf5; border: 1px solid #a7f3d0; }
.result-box.lost { background: #fef2f2; border: 1px solid #fecaca; }
.result-title { font-size: 22px; font-weight: 900; margin-bottom: 6px; }
.result-box.won .result-title { color: #047857; }
.result-box.lost .result-title { color: #b91c1c; }
.result-answer { font-size: 14px; color: #6b7280; margin-bottom: 6px; }
.result-answer strong { font-size: 18px; color: #1f2937; }
.result-reward { font-size: 13px; color: #059669; margin-bottom: 12px; font-weight: 700; }
.result-btn { background: #6366f1; color: #fff; padding: 10px 28px; border-radius: 14px; font-size: 14px; font-weight: 800; border: none; cursor: pointer; }

.legend { display: flex; justify-content: center; gap: 16px; font-size: 11px; color: #9ca3af; }
.dot { display: inline-block; width: 10px; height: 10px; border-radius: 3px; margin-right: 4px; vertical-align: middle; }
.dot.correct { background: #10b981; }
.dot.present { background: #f59e0b; }
.dot.absent { background: #d1d5db; }

.keyboard-card { background: #fff; border-radius: 16px; box-shadow: 0 4px 16px rgba(0,0,0,0.06); padding: 10px; }
.keyboard-row { display: flex; gap: 4px; justify-content: center; margin-bottom: 6px; }
.key { min-width: 30px; padding: 10px 6px; border-radius: 8px; font-size: 13px; font-weight: 700; border: none; cursor: default; background: #f3f4f6; color: #374151; }
.key-correct { background: #10b981; color: #fff; }
.key-present { background: #f59e0b; color: #fff; }
.key-absent { background: #e5e7eb; color: #9ca3af; }

.action-row { display: flex; gap: 8px; justify-content: center; margin-top: 4px; }
.btn-delete { background: #e5e7eb; color: #374151; padding: 10px 20px; border-radius: 10px; font-size: 13px; font-weight: 700; border: none; cursor: pointer; }
.btn-submit { background: #6366f1; color: #fff; padding: 10px 24px; border-radius: 10px; font-size: 13px; font-weight: 700; border: none; cursor: pointer; }
.btn-submit:disabled { opacity: 0.4; cursor: default; }

.hint-note { font-size: 11px; color: #9ca3af; text-align: center; margin-top: 6px; font-style: italic; }
</style>
