<template>
<GameShell title="한국어 워들" icon="🔡" :points="coinStr">
  <template #meta>
    <button @click="showHelp = true" class="new-btn" style="background:#3b82f6;">❓ 도움말</button>
    <button @click="newGame" class="new-btn">새 게임</button>
  </template>

  <!-- 도움말 모달 -->
  <div v-if="showHelp" class="help-overlay" @click.self="showHelp = false">
    <div class="help-modal">
      <h2 class="help-title">🔡 한국어 워들 규칙</h2>
      <div class="help-section">
        <h3>🎯 목표</h3>
        <p>4글자 한국어 단어를 6번 안에 맞히세요!</p>
      </div>
      <div class="help-section">
        <h3>⌨️ 입력 방법</h3>
        <ol>
          <li>아래 키보드에서 자음/모음 버튼을 눌러 4글자를 만듭니다</li>
          <li>"입력 ↵" 버튼으로 추측을 제출합니다</li>
          <li>잘못 입력했으면 "⌫ 지우기"로 마지막 글자 삭제</li>
        </ol>
      </div>
      <div class="help-section">
        <h3>🟩 색상 의미</h3>
        <ul class="help-colors">
          <li><span class="color-box correct"></span> <strong>초록</strong> — 정확한 글자가 정확한 위치</li>
          <li><span class="color-box present"></span> <strong>노랑</strong> — 글자는 맞지만 위치가 다름</li>
          <li><span class="color-box absent"></span> <strong>회색</strong> — 정답에 없는 글자</li>
        </ul>
      </div>
      <div class="help-section">
        <h3>💡 예시</h3>
        <p>정답이 <strong>"김치찌개"</strong>일 때 <strong>"김밥볶음"</strong>을 입력하면<br>
          김(🟩) 밥(⬜) 볶(⬜) 음(⬜) — "김"만 정답!</p>
      </div>
      <div class="help-section">
        <h3>🎁 보상</h3>
        <p>정답을 맞히면 <strong>+30 코인</strong> 획득!</p>
      </div>
      <button @click="showHelp = false" class="help-close-btn">시작하기</button>
    </div>
  </div>

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

      <!-- 실제 입력창 — 탭하면 휴대폰 한글 키보드가 뜸. 아래 화면 키보드는 보조 입력 수단 -->
      <input v-if="gameState === 'playing'"
        ref="nativeInputEl" v-model="nativeText" @input="onNativeInput" @keyup.enter="submitGuess"
        type="text" inputmode="text" autocomplete="off" autocapitalize="off" spellcheck="false"
        maxlength="4" class="native-input" placeholder="탭해서 한글로 입력하세요" />

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
  <ConfettiBurst ref="confettiRef" />
</GameShell>
</template>

<script setup>
import { ref, reactive, computed, onMounted, nextTick } from 'vue'
import GameShell from '../../components/GameShell.vue'
import ConfettiBurst from '../../components/ConfettiBurst.vue'
import { useGameSound } from '../../composables/useGameSound'

const sound = useGameSound()
const confettiRef = ref(null)
const showHelp = ref(false)

onMounted(() => {
  // 첫 방문 시 자동으로 도움말 표시
  if (!localStorage.getItem('wordle_help_seen')) {
    showHelp.value = true
    localStorage.setItem('wordle_help_seen', '1')
  }
})

const WORD_LEN = 4
const MAX_ROWS = 6

// 4글자 한국어 단어 80+ (음식, 일상어, 관용표현, 명사구)
// 예전엔 5글자 단어를 4칸에 맞춰 그냥 잘라 넣은 항목이 여럿 있어(순두부찌개→순두부찌 등)
// 실제로 존재하지 않는 글자 조합이 정답으로 나오는 문제가 있었음 — 전부 완전한 4글자 단어/구로 교체.
const WORDS = [
  // 한국 음식 (30)
  '김치찌개','된장찌개','두부김치','부대찌개','미역국밥',
  '불고기밥','삼겹살집','비빔냉면','떡볶이집','잡채볶음',
  '제육볶음','오징어탕','닭갈비집','감자탕집','해물파전',
  '빈대떡집','순대국밥','설렁탕집','곰탕국밥','삼계탕집',
  '돼지국밥','족발보쌈','냉면가게','막국수집','칼국수집',
  '잔치국수','콩나물국','갈비탕집','매운탕집','갈비찜집',
  // 동물 (10)
  '강아지들','고양이들','호랑이들','코끼리들','기린친구',
  '돼지가족','토끼친구','원숭이들','펭귄친구','여우친구',
  // 일상어/명사구 (20)
  '아침식사','점심시간','저녁노을','밤하늘에','새벽공기',
  '봄바람이','여름휴가','가을단풍','겨울눈길','고향생각',
  '학교친구','가족사랑','친구사이','선생님들','동네식당',
  '우리이웃','우리엄마','우리아빠','할머니댁','할아버지',
  // 장소/자연 (15)
  '한강공원','남산타워','명동거리','강남역앞','종로거리',
  '인사동길','동대문앞','청계천길','홍대입구','이태원로',
  '바닷가에','산꼭대기','강변공원','시골마을','도시야경',
  // 감정/행동 (10)
  '사랑하는','행복하게','즐거운일','기쁜하루','반가워요',
  '고마워요','미안하다','응원해요','기쁜마음','따뜻한말',
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
const gameState = ref('playing')
const usedKeys = reactive({})
const coin = ref(parseInt(localStorage.getItem('wordle_coin') || '0'))
const coinStr = computed(() => coin.value.toLocaleString())

// ── 실제 입력창(모바일 OS 키보드/IME용) ──────────────────────────────────────
// 예전엔 window 전체에 keydown 리스너만 걸어놔서, 포커스를 받을 수 있는 <input>이
// 전혀 없어 모바일에서는 한글 키보드 자체가 뜨지 않아 플레이가 불가능했음.
const nativeText = ref('')
const nativeInputEl = ref(null)

function sanitizeNative(v) {
  return [...v].filter(ch => {
    const c = ch.charCodeAt(0)
    return c >= 0xAC00 && c <= 0xD7A3
  }).slice(0, WORD_LEN).join('')
}
function onNativeInput() { nativeText.value = sanitizeNative(nativeText.value) }
function focusNativeInput() { nextTick(() => nativeInputEl.value?.focus()) }

// ── 화면 속 자모 키보드 조합(2벌식) ───────────────────────────────────────────
// 예전엔 typeKey()가 빈 함수라 화면 속 키보드를 눌러도 아무 반응이 없었음.
// 자음/모음 개별 키 입력을 실제 한글 음절로 조합해서 nativeText 에 이어붙임.
const CHO = ['ㄱ','ㄲ','ㄴ','ㄷ','ㄸ','ㄹ','ㅁ','ㅂ','ㅃ','ㅅ','ㅆ','ㅇ','ㅈ','ㅉ','ㅊ','ㅋ','ㅌ','ㅍ','ㅎ']
const JUNG = ['ㅏ','ㅐ','ㅑ','ㅒ','ㅓ','ㅔ','ㅕ','ㅖ','ㅗ','ㅘ','ㅙ','ㅚ','ㅛ','ㅜ','ㅝ','ㅞ','ㅟ','ㅠ','ㅡ','ㅢ','ㅣ']
const JONG = ['','ㄱ','ㄲ','ㄳ','ㄴ','ㄵ','ㄶ','ㄷ','ㄹ','ㄺ','ㄻ','ㄼ','ㄽ','ㄾ','ㄿ','ㅀ','ㅁ','ㅂ','ㅄ','ㅅ','ㅆ','ㅇ','ㅈ','ㅊ','ㅋ','ㅌ','ㅍ','ㅎ']
// 받침 뒤에 모음이 오면(재음절화) 받침이 다음 글자 초성으로 넘어가는데, 겹받침은 뒷부분만 넘어감
const JONG_COMBO = {'ㄱㅅ':'ㄳ','ㄴㅈ':'ㄵ','ㄴㅎ':'ㄶ','ㄹㄱ':'ㄺ','ㄹㅁ':'ㄻ','ㄹㅂ':'ㄼ','ㄹㅅ':'ㄽ','ㄹㅌ':'ㄾ','ㄹㅍ':'ㄿ','ㄹㅎ':'ㅀ','ㅂㅅ':'ㅄ'}
const JONG_SPLIT = {'ㄳ':['ㄱ','ㅅ'],'ㄵ':['ㄴ','ㅈ'],'ㄶ':['ㄴ','ㅎ'],'ㄺ':['ㄹ','ㄱ'],'ㄻ':['ㄹ','ㅁ'],'ㄼ':['ㄹ','ㅂ'],'ㄽ':['ㄹ','ㅅ'],'ㄾ':['ㄹ','ㅌ'],'ㄿ':['ㄹ','ㅍ'],'ㅀ':['ㄹ','ㅎ'],'ㅄ':['ㅂ','ㅅ']}

const composing = reactive({ cho: null, jung: null, jong: null })

function composeSyllable(cho, jung, jong) {
  if (cho == null || jung == null) return ''
  const ci = CHO.indexOf(cho), vi = JUNG.indexOf(jung), ji = jong ? JONG.indexOf(jong) : 0
  if (ci < 0 || vi < 0 || ji < 0) return ''
  return String.fromCharCode(0xAC00 + (ci * 21 + vi) * 28 + ji)
}
function decompose(ch) {
  const code = ch.charCodeAt(0) - 0xAC00
  if (code < 0 || code > 11171) return null
  return { cho: CHO[Math.floor(code / 588)], jung: JUNG[Math.floor((code % 588) / 28)], jong: JONG[code % 28] || null }
}
function clearComposing() { composing.cho = null; composing.jung = null; composing.jong = null }
function canStartNewSyllable() { return nativeText.value.length < WORD_LEN }
// 지금 조합 중이던 글자를 확정 짓고 그 다음 글자를 새로 시작해도 되는지 — 확정 후 자리가
// 하나도 안 남으면(이번이 마지막 칸) 다음 글자는 시작하면 안 됨
function canCommitAndStartNew() { return nativeText.value.length + 1 < WORD_LEN }
function commitComposing() {
  const ch = composeSyllable(composing.cho, composing.jung, composing.jong)
  if (ch) nativeText.value += ch
  clearComposing()
}

function typeKey(k) {
  if (gameState.value !== 'playing') return
  const isVowel = JUNG.includes(k)
  const isCons = CHO.includes(k)
  if (!isVowel && !isCons) return

  if (isCons) {
    if (composing.cho === null) {
      if (!canStartNewSyllable()) return
      composing.cho = k
    } else if (composing.jung === null) {
      if (!canStartNewSyllable()) return
      commitComposing(); composing.cho = k // 모음 없이 자음만 있던 상태(commitComposing은 실제로 아무 글자도 안 만듦) — 그냥 버리고 새로 시작
    } else if (composing.jong === null) {
      composing.jong = k
    } else {
      const combo = JONG_COMBO[composing.jong + k]
      if (combo) { composing.jong = combo }
      else { if (!canCommitAndStartNew()) return; commitComposing(); composing.cho = k }
    }
  } else {
    if (composing.cho === null) {
      if (!canStartNewSyllable()) return
      composing.cho = 'ㅇ'; composing.jung = k
    } else if (composing.jung === null) {
      composing.jung = k
    } else if (composing.jong === null) {
      if (!canCommitAndStartNew()) return
      commitComposing(); composing.cho = 'ㅇ'; composing.jung = k
    } else {
      const split = JONG_SPLIT[composing.jong] || [null, composing.jong]
      if (!canCommitAndStartNew()) return
      composing.jong = split[0]
      commitComposing()
      composing.cho = split[1]; composing.jung = k
    }
  }
}

// 미리보기(입력창 + 화면 키보드로 조합 중인 글자)를 합쳐서 그리드에 실시간으로 보여줌
const currentInput = computed(() => nativeText.value + composeSyllable(composing.cho, composing.jung, composing.jong))

function deleteLast() {
  if (composing.jong !== null) { composing.jong = null; return }
  if (composing.jung !== null) { composing.jung = null; return }
  if (composing.cho !== null) { composing.cho = null; return }
  nativeText.value = nativeText.value.slice(0, -1)
}

function submitGuess() {
  if (composing.cho !== null && composing.jung !== null) commitComposing()
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
  // 화면 키보드는 음절이 아니라 자모 단위라, 맞힌 글자를 자모로 분해해서 그 자모 키들을 색칠
  gArr.forEach((ch, i) => {
    const d = decompose(ch)
    if (!d) return
    ;[d.cho, d.jung, d.jong].filter(Boolean).forEach(jamo => {
      const cur = usedKeys[jamo]
      if (res[i] === 'correct') usedKeys[jamo] = 'correct'
      else if (res[i] === 'present' && cur !== 'correct') usedKeys[jamo] = 'present'
      else if (!cur) usedKeys[jamo] = 'absent'
    })
  })
  if (g === ans) {
    gameState.value = 'won'
    coin.value += 30
    localStorage.setItem('wordle_coin', coin.value)
    sound.levelUp(); confettiRef.value?.burst()
  }
  else if (row === MAX_ROWS - 1) { gameState.value = 'lost'; sound.gameOver() }
  else { sound.wrong() }
  currentRow.value++
  nativeText.value = ''
  focusNativeInput()
}

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
  currentRow.value = 0; nativeText.value = ''; gameState.value = 'playing'
  clearComposing()
  Object.keys(usedKeys).forEach(k => delete usedKeys[k])
  focusNativeInput()
}

onMounted(focusNativeInput)
</script>

<style scoped>
.new-btn { background-image: linear-gradient(135deg,#818cf8,#6366f1); color: #fff; border: none; padding: 6px 14px; border-radius: 999px; font-size: 11px; font-weight: 800; cursor: pointer; margin-left: 4px; box-shadow: 0 4px 12px -3px rgba(99,102,241,0.5); transition: transform .15s ease; }
.new-btn:hover { transform: translateY(-1px); }

.help-overlay { position: fixed; inset: 0; background: rgba(20,20,35,0.45); backdrop-filter: blur(4px); z-index: 9999; display: flex; align-items: center; justify-content: center; padding: 20px; }
.help-modal { background: rgba(255,255,255,0.85); backdrop-filter: blur(20px) saturate(160%); border: 1px solid rgba(255,255,255,0.6); border-radius: 22px; padding: 24px; max-width: 480px; width: 100%; max-height: 90vh; overflow-y: auto; box-shadow: 0 24px 60px rgba(31,38,80,0.3); }
.help-title { font-size: 22px; font-weight: 900; color: #1f2937; margin: 0 0 16px; text-align: center; }
.help-section { margin-bottom: 16px; }
.help-section h3 { font-size: 14px; font-weight: 800; color: #4338ca; margin: 0 0 6px; }
.help-section p, .help-section ol, .help-section ul { font-size: 13px; color: #374151; line-height: 1.6; margin: 0; padding-left: 20px; }
.help-section ol li, .help-section ul li { margin-bottom: 4px; }
.help-colors { list-style: none !important; padding-left: 0 !important; }
.help-colors li { display: flex; align-items: center; gap: 8px; }
.color-box { width: 18px; height: 18px; border-radius: 6px; display: inline-block; }
.color-box.correct { background-image: linear-gradient(135deg,#34d399,#10b981); }
.color-box.present { background-image: linear-gradient(135deg,#fbbf24,#f59e0b); }
.color-box.absent { background: #9ca3af; }
.help-close-btn { position: relative; overflow: hidden; display: block; width: 100%; background-image: linear-gradient(135deg,#8b5cf6,#ec4899); color: #fff; font-weight: 800; padding: 13px; border: none; border-radius: 999px; font-size: 15px; cursor: pointer; margin-top: 8px; box-shadow: 0 10px 24px -8px rgba(139,92,246,0.55); transition: transform .15s ease; }
.help-close-btn:hover { transform: translateY(-2px); }

.wordle-box { max-width: 480px; margin: 0 auto; padding: 16px; display: flex; flex-direction: column; gap: 14px; width: 100%; }
.wordle-card {
  background: rgba(255,255,255,0.65); backdrop-filter: blur(18px) saturate(160%);
  border: 1px solid rgba(255,255,255,0.6); border-radius: 24px;
  box-shadow: 0 16px 40px rgba(31,38,80,0.14), inset 0 1px 0 rgba(255,255,255,0.6);
  padding: 22px;
}

.grid-rows { display: flex; flex-direction: column; gap: 7px; margin-bottom: 16px; }
.grid-row { display: flex; gap: 7px; justify-content: center; }
.grid-cell { width: 54px; height: 54px; display: flex; align-items: center; justify-content: center; font-size: 22px; font-weight: 900; border: 2px solid rgba(0,0,0,0.08); border-radius: 14px; transition: all 0.3s; background: rgba(255,255,255,0.7); color: #1f2937; }
.cell-empty { background: rgba(255,255,255,0.5); border-color: rgba(0,0,0,0.08); }
.cell-pending { background: rgba(0,0,0,0.03); border-color: rgba(0,0,0,0.12); color: #6b7280; }
.cell-correct { background-image: linear-gradient(135deg,#34d399,#059669); border-color: transparent; color: #fff; box-shadow: 0 6px 16px -4px rgba(16,185,129,0.5); }
.cell-present { background-image: linear-gradient(135deg,#fbbf24,#d97706); border-color: transparent; color: #fff; box-shadow: 0 6px 16px -4px rgba(217,119,6,0.5); }
.cell-absent { background: rgba(0,0,0,0.08); border-color: transparent; color: #9ca3af; }

.current-row { display: flex; gap: 7px; justify-content: center; margin-bottom: 14px; }
.current-row .grid-cell { border-color: #c7d2fe; }
.current-row .grid-cell.filled { background-image: linear-gradient(135deg,#eef2ff,#e0e7ff); color: #4338ca; border-color: #818cf8; }

.native-input { display: block; width: 100%; max-width: 260px; margin: 0 auto 14px; padding: 11px 14px; font-size: 16px; text-align: center; border: 2px solid rgba(99,102,241,0.3); border-radius: 999px; color: #1f2937; background: rgba(255,255,255,0.7); backdrop-filter: blur(8px); box-shadow: 0 4px 12px rgba(0,0,0,0.06); }
.native-input:focus { outline: none; border-color: #6366f1; box-shadow: 0 0 0 4px rgba(99,102,241,0.15); }

.result-box { text-align: center; padding: 22px 16px; border-radius: 20px; margin-bottom: 14px; backdrop-filter: blur(12px); }
.result-box.won { background: rgba(16,185,129,0.12); border: 1px solid rgba(16,185,129,0.3); box-shadow: 0 10px 28px -8px rgba(16,185,129,0.25); }
.result-box.lost { background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.28); }
.result-title { font-size: 22px; font-weight: 900; margin-bottom: 6px; }
.result-box.won .result-title { color: #047857; }
.result-box.lost .result-title { color: #b91c1c; }
.result-answer { font-size: 14px; color: #6b7280; margin-bottom: 6px; }
.result-answer strong { font-size: 18px; color: #1f2937; }
.result-reward { font-size: 13px; color: #059669; margin-bottom: 12px; font-weight: 700; }
.result-btn { position: relative; overflow: hidden; background-image: linear-gradient(135deg,#818cf8,#6366f1); color: #fff; padding: 11px 30px; border-radius: 999px; font-size: 14px; font-weight: 800; border: none; cursor: pointer; box-shadow: 0 10px 24px -8px rgba(99,102,241,0.55); transition: transform .15s ease; }
.result-btn:hover { transform: translateY(-2px); }

.legend { display: flex; justify-content: center; gap: 16px; font-size: 11px; color: #9ca3af; font-weight: 600; }
.dot { display: inline-block; width: 10px; height: 10px; border-radius: 4px; margin-right: 4px; vertical-align: middle; }
.dot.correct { background-image: linear-gradient(135deg,#34d399,#10b981); }
.dot.present { background-image: linear-gradient(135deg,#fbbf24,#f59e0b); }
.dot.absent { background: #d1d5db; }

.keyboard-card {
  background: rgba(255,255,255,0.65); backdrop-filter: blur(18px) saturate(160%);
  border: 1px solid rgba(255,255,255,0.6); border-radius: 22px;
  box-shadow: 0 16px 40px rgba(31,38,80,0.14), inset 0 1px 0 rgba(255,255,255,0.6);
  padding: 12px;
}
.keyboard-row { display: flex; gap: 5px; justify-content: center; margin-bottom: 7px; }
.key { min-width: 30px; padding: 11px 6px; border-radius: 10px; font-size: 13px; font-weight: 700; border: none; cursor: pointer; background: rgba(0,0,0,0.05); color: #374151; transition: transform .1s ease, background .15s ease; }
.key:hover { background: rgba(0,0,0,0.1); transform: translateY(-1px); }
.key-correct { background-image: linear-gradient(135deg,#34d399,#059669); color: #fff; }
.key-present { background-image: linear-gradient(135deg,#fbbf24,#d97706); color: #fff; }
.key-absent { background: rgba(0,0,0,0.12); color: #9ca3af; }

.action-row { display: flex; gap: 10px; justify-content: center; margin-top: 6px; }
.btn-delete { background: rgba(0,0,0,0.07); color: #374151; padding: 11px 22px; border-radius: 999px; font-size: 13px; font-weight: 700; border: none; cursor: pointer; transition: background .15s ease; }
.btn-delete:hover { background: rgba(0,0,0,0.12); }
.btn-submit { position: relative; overflow: hidden; background-image: linear-gradient(135deg,#818cf8,#6366f1); color: #fff; padding: 11px 26px; border-radius: 999px; font-size: 13px; font-weight: 800; border: none; cursor: pointer; box-shadow: 0 8px 20px -6px rgba(99,102,241,0.5); transition: transform .15s ease; }
.btn-submit:hover:not(:disabled) { transform: translateY(-2px); }
.btn-submit:disabled { opacity: 0.4; cursor: default; box-shadow: none; }

.hint-note { font-size: 11px; color: #9ca3af; text-align: center; margin-top: 6px; font-style: italic; }
</style>
