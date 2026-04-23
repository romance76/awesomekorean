<template>
<div>
  <div class="flex items-center gap-3 mb-4">
    <RouterLink to="/admin/games" class="text-xs text-gray-500 hover:text-amber-600">← 게임 관리</RouterLink>
  </div>

  <div v-if="loading" class="text-center py-12 text-gray-400">로딩중...</div>
  <div v-else-if="!game" class="text-center py-12 text-red-400">게임을 찾을 수 없습니다</div>
  <div v-else>
    <!-- 게임 헤더 -->
    <div class="bg-white rounded-xl border shadow-sm p-5 mb-4 flex items-center gap-4">
      <div class="w-16 h-16 bg-gray-50 rounded-xl flex items-center justify-center text-4xl flex-shrink-0">{{ game.icon }}</div>
      <div class="flex-1 min-w-0">
        <div class="flex items-center gap-2">
          <h1 class="text-xl font-black text-gray-800">{{ game.name }}</h1>
          <span class="text-[10px] font-bold px-2 py-0.5 rounded" :class="catBadge(game.category)">{{ catLabel(game.category) }}</span>
          <span v-if="game.is_active" class="text-[10px] font-bold px-2 py-0.5 rounded bg-green-100 text-green-700">활성</span>
          <span v-else class="text-[10px] font-bold px-2 py-0.5 rounded bg-gray-100 text-gray-500">비활성</span>
        </div>
        <div class="text-xs text-gray-500 mt-1">{{ game.description || game.slug }}</div>
        <div class="text-[11px] text-gray-400 mt-0.5">slug: <code class="bg-gray-100 px-1">{{ game.slug }}</code> · 경로: <code class="bg-gray-100 px-1">{{ game.path }}</code></div>
      </div>
      <div class="flex flex-col gap-1 flex-shrink-0">
        <a :href="game.path" target="_blank" class="text-xs bg-amber-400 text-amber-900 font-bold px-3 py-1.5 rounded-lg hover:bg-amber-500 text-center">
          🔗 게임 열기
        </a>
        <RouterLink v-if="isQuizGame" :to="`/admin/games/questions/${game.slug}`" class="text-xs bg-purple-100 text-purple-700 font-bold px-3 py-1.5 rounded-lg hover:bg-purple-200 text-center">
          ❓ 퀴즈 문제
        </RouterLink>
      </div>
    </div>

    <!-- 기본 정보 수정 -->
    <div class="bg-white rounded-xl border shadow-sm p-5 mb-4">
      <h2 class="font-black text-sm text-gray-800 mb-3">📝 기본 정보</h2>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div>
          <label class="text-[11px] font-bold text-gray-600 block mb-0.5">게임 이름</label>
          <input v-model="form.name" class="w-full border rounded px-2 py-1.5 text-sm" />
        </div>
        <div>
          <label class="text-[11px] font-bold text-gray-600 block mb-0.5">아이콘 (이모지)</label>
          <input v-model="form.icon" maxlength="8" class="w-full border rounded px-2 py-1.5 text-sm" />
        </div>
        <div class="md:col-span-2">
          <label class="text-[11px] font-bold text-gray-600 block mb-0.5">설명 (한 줄)</label>
          <input v-model="form.description" class="w-full border rounded px-2 py-1.5 text-sm" />
        </div>
        <div>
          <label class="text-[11px] font-bold text-gray-600 block mb-0.5">카테고리</label>
          <select v-model="form.category" class="w-full border rounded px-2 py-1.5 text-sm">
            <option value="card">🃏 카드</option>
            <option value="brain">🧠 두뇌</option>
            <option value="arcade">👾 아케이드</option>
            <option value="word">📝 단어/퀴즈</option>
            <option value="education">📚 교육</option>
          </select>
        </div>
        <div class="flex items-end">
          <label class="flex items-center gap-2 text-sm">
            <input v-model="form.is_active" type="checkbox" class="accent-amber-500 w-4 h-4" />
            활성화 (유저 게임 목록에 노출)
          </label>
        </div>
      </div>
      <div class="flex items-center gap-3 mt-4">
        <button @click="saveGame" :disabled="savingGame" class="bg-amber-400 text-amber-900 font-bold px-4 py-1.5 rounded text-xs hover:bg-amber-500 disabled:opacity-50">
          {{ savingGame ? '저장중...' : '💾 기본 정보 저장' }}
        </button>
        <span v-if="gameMsg" class="text-xs text-green-600">{{ gameMsg }}</span>
      </div>
    </div>

    <!-- 커스텀 설정 (key/value) -->
    <div class="bg-white rounded-xl border shadow-sm p-5">
      <div class="flex items-center justify-between mb-3">
        <h2 class="font-black text-sm text-gray-800">⚙️ 커스텀 설정 <span class="font-normal text-xs text-gray-400">(게임별 파라미터)</span></h2>
        <button @click="addRow" class="bg-amber-400 text-amber-900 font-bold px-3 py-1 rounded text-xs">+ 항목 추가</button>
      </div>
      <p class="text-[11px] text-gray-500 mb-3">
        이 게임에서만 사용되는 설정을 key/value 로 저장합니다.
        예시: <code class="bg-gray-100 px-1">max_players=4</code>,
        <code class="bg-gray-100 px-1">time_limit=60</code>,
        <code class="bg-gray-100 px-1">difficulty=hard</code> 등.
        게임 코드에서 <code class="bg-gray-100 px-1">/api/game-settings/{slug}</code> 로 읽습니다.
      </p>

      <div v-if="!settings.length && !newRows.length" class="text-center py-8 text-sm text-gray-400 bg-gray-50 rounded-lg">
        등록된 설정이 없습니다
      </div>

      <div v-else class="space-y-2">
        <!-- 기존 설정 -->
        <div v-for="s in settings" :key="s.id || s.key" class="flex items-center gap-2">
          <input :value="s.key" disabled class="w-40 border rounded px-2 py-1.5 text-xs font-mono bg-gray-50" />
          <input v-model="s.value" class="flex-1 border rounded px-2 py-1.5 text-xs font-mono" />
          <button @click="deleteSetting(s)" class="text-red-400 hover:text-red-600 text-xs flex-shrink-0">✕</button>
        </div>
        <!-- 신규 행 -->
        <div v-for="(r, i) in newRows" :key="'new' + i" class="flex items-center gap-2">
          <input v-model="r.key" placeholder="key (예: max_players)" class="w-40 border rounded px-2 py-1.5 text-xs font-mono" />
          <input v-model="r.value" placeholder="value" class="flex-1 border rounded px-2 py-1.5 text-xs font-mono" />
          <button @click="newRows.splice(i, 1)" class="text-gray-400 hover:text-red-500 text-xs flex-shrink-0">✕</button>
        </div>
      </div>

      <div class="flex items-center gap-3 mt-4">
        <button @click="saveSettings" :disabled="savingSettings" class="bg-amber-500 text-white font-bold px-4 py-1.5 rounded text-xs hover:bg-amber-600 disabled:opacity-50">
          {{ savingSettings ? '저장중...' : '💾 설정 저장' }}
        </button>
        <span v-if="settingMsg" class="text-xs text-green-600">{{ settingMsg }}</span>
      </div>
    </div>
  </div>
</div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute, RouterLink } from 'vue-router'
import axios from 'axios'

const route = useRoute()
const slug = route.params.slug

const loading = ref(true)
const game = ref(null)
const settings = ref([])
const newRows = ref([])
const QUIZ_GAMES = ['animals','flag','idiom','proverb','satwords','uslife','shapes','colors','wordcard']
const isQuizGame = computed(() => game.value && QUIZ_GAMES.includes(game.value.slug))
const form = reactive({ name: '', icon: '', description: '', category: 'brain', is_active: true })
const savingGame = ref(false)
const savingSettings = ref(false)
const gameMsg = ref('')
const settingMsg = ref('')

function catLabel(c) {
  return { card: '카드', brain: '두뇌', arcade: '아케이드', word: '단어', education: '교육' }[c] || c
}
function catBadge(c) {
  return {
    card: 'bg-purple-100 text-purple-700', brain: 'bg-blue-100 text-blue-700',
    arcade: 'bg-red-100 text-red-700', word: 'bg-green-100 text-green-700',
    education: 'bg-amber-100 text-amber-700',
  }[c] || 'bg-gray-100 text-gray-600'
}

async function load() {
  loading.value = true
  try {
    const { data } = await axios.get(`/api/admin/games/${slug}/settings`)
    game.value = data.data?.game || null
    settings.value = data.data?.settings || []
    if (game.value) {
      form.name = game.value.name
      form.icon = game.value.icon
      form.description = game.value.description || ''
      form.category = game.value.category
      form.is_active = !!game.value.is_active
    }
  } catch {}
  loading.value = false
}

async function saveGame() {
  if (!game.value) return
  savingGame.value = true; gameMsg.value = ''
  try {
    await axios.put(`/api/admin/games/${game.value.id}`, form)
    gameMsg.value = '저장됨!'
    game.value = { ...game.value, ...form }
  } catch { gameMsg.value = '저장 실패' }
  savingGame.value = false
  setTimeout(() => gameMsg.value = '', 2500)
}

function addRow() { newRows.value.push({ key: '', value: '' }) }

async function saveSettings() {
  savingSettings.value = true; settingMsg.value = ''
  const payload = [
    ...settings.value.map(s => ({ key: s.key, value: s.value })),
    ...newRows.value.filter(r => r.key.trim()),
  ]
  try {
    await axios.post(`/api/admin/games/${slug}/settings`, { settings: payload })
    settingMsg.value = '저장됨!'
    newRows.value = []
    await load()
  } catch { settingMsg.value = '저장 실패' }
  savingSettings.value = false
  setTimeout(() => settingMsg.value = '', 2500)
}

async function deleteSetting(s) {
  if (!confirm(`'${s.key}' 삭제?`)) return
  try {
    await axios.delete(`/api/admin/games/${slug}/settings/${encodeURIComponent(s.key)}`)
    settings.value = settings.value.filter(x => x.key !== s.key)
  } catch { alert('삭제 실패') }
}

onMounted(load)
</script>
