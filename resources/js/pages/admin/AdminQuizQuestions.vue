<template>
<div>
  <div class="flex items-center gap-3 mb-3">
    <RouterLink :to="`/admin/games/settings/${slug}`" class="text-xs text-gray-500 hover:text-amber-600">← {{ slug }} 설정</RouterLink>
  </div>

  <div class="flex items-center justify-between mb-3 gap-2 flex-wrap">
    <div>
      <h1 class="text-xl font-black text-gray-800">❓ {{ slug }} 퀴즈 문제</h1>
      <p class="text-xs text-gray-500">이미지-선택지 퀴즈 문제를 추가/수정/삭제합니다. 선택지는 같은 레벨의 다른 정답에서 자동으로 섞입니다 (직접 지정 가능).</p>
    </div>
    <button @click="openNew" class="bg-amber-400 text-amber-900 font-bold px-3 py-1.5 rounded-lg text-sm">+ 새 문제</button>
  </div>

  <!-- 필터 -->
  <div class="flex items-center gap-2 mb-3 flex-wrap">
    <select v-model="filterLevel" @change="load" class="border rounded px-2 py-1 text-xs">
      <option value="">레벨 전체</option>
      <option v-for="l in [1,2,3,4,5]" :key="l" :value="l">Lv.{{ l }}</option>
    </select>
    <input v-model="filterSearch" @keyup.enter="load" placeholder="정답으로 검색" class="border rounded px-2 py-1 text-xs flex-1 min-w-[200px] max-w-[300px]" />
    <button @click="load" class="text-xs bg-gray-100 px-3 py-1 rounded">검색</button>
    <div class="ml-auto text-xs text-gray-500">총 <strong>{{ meta.total || 0 }}</strong>건</div>
  </div>

  <!-- 목록 -->
  <div v-if="loading" class="text-center py-10 text-gray-400">로딩중...</div>
  <div v-else-if="!items.length" class="text-center py-12 bg-gray-50 rounded text-sm text-gray-400">문제가 없습니다</div>
  <div v-else class="bg-white border rounded-xl overflow-hidden">
    <table class="w-full text-xs">
      <thead class="bg-gray-50 text-gray-500 font-bold">
        <tr>
          <th class="px-2 py-2 w-12 text-left">Lv</th>
          <th class="px-2 py-2 w-14">이미지</th>
          <th class="px-2 py-2 text-left">정답</th>
          <th class="px-2 py-2 text-left hidden md:table-cell">오답(선택)</th>
          <th class="px-2 py-2 text-left hidden sm:table-cell">힌트/음성</th>
          <th class="px-2 py-2 w-20">활성</th>
          <th class="px-2 py-2 w-24"></th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="q in items" :key="q.id" class="border-t hover:bg-amber-50/30">
          <td class="px-2 py-2 text-center font-bold text-purple-700">Lv.{{ q.level }}</td>
          <td class="px-2 py-2 text-center">
            <img v-if="imageUrl(q)" :src="imageUrl(q)" class="w-10 h-10 object-contain mx-auto"
              @error="(e) => e.target.style.display='none'" />
            <span v-else class="text-gray-300">-</span>
          </td>
          <td class="px-2 py-2 font-bold text-gray-800">{{ q.answer }}</td>
          <td class="px-2 py-2 text-gray-500 hidden md:table-cell truncate max-w-[200px]">{{ q.wrong_answers || '(자동)' }}</td>
          <td class="px-2 py-2 text-gray-500 hidden sm:table-cell">
            <div v-if="q.hint" class="text-[11px]">💡 {{ q.hint }}</div>
            <div v-if="q.sound" class="text-[11px]">🔊 {{ q.sound }}</div>
          </td>
          <td class="px-2 py-2 text-center">
            <label class="cursor-pointer inline-flex items-center">
              <input type="checkbox" :checked="q.is_active" @change="toggle(q)" class="sr-only peer" />
              <div class="w-8 h-4 bg-gray-300 peer-checked:bg-green-500 rounded-full relative transition-colors">
                <div class="absolute left-0.5 top-0.5 w-3 h-3 bg-white rounded-full transition-transform peer-checked:translate-x-4"></div>
              </div>
            </label>
          </td>
          <td class="px-2 py-2 text-right space-x-2">
            <button @click="edit(q)" class="text-amber-600 hover:underline">수정</button>
            <button @click="remove(q)" class="text-red-500 hover:underline">삭제</button>
          </td>
        </tr>
      </tbody>
    </table>
  </div>

  <!-- 페이지네이션 -->
  <div v-if="meta.last_page > 1" class="flex justify-center gap-1 mt-4">
    <button v-for="p in meta.last_page" :key="p" @click="page = p; load()"
      :class="['text-xs px-3 py-1 rounded', page === p ? 'bg-amber-400 text-amber-900 font-bold' : 'bg-white border']">
      {{ p }}
    </button>
  </div>

  <!-- 모달 -->
  <div v-if="modalOpen" class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4" @click.self="modalOpen = false">
    <div class="bg-white rounded-xl w-full max-w-lg p-5 shadow-2xl">
      <h3 class="font-black text-base mb-3">{{ form.id ? '문제 수정' : '새 문제' }}</h3>
      <div class="space-y-3">
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="text-[11px] font-bold text-gray-600 block mb-1">레벨 *</label>
            <select v-model.number="form.level" class="w-full border rounded px-2 py-1.5 text-sm">
              <option v-for="l in [1,2,3,4,5]" :key="l" :value="l">Lv.{{ l }}</option>
            </select>
          </div>
          <div>
            <label class="text-[11px] font-bold text-gray-600 block mb-1">활성</label>
            <label class="flex items-center gap-2 pt-1.5">
              <input v-model="form.is_active" type="checkbox" class="accent-amber-500 w-4 h-4" />
              <span class="text-sm">활성</span>
            </label>
          </div>
        </div>
        <div>
          <label class="text-[11px] font-bold text-gray-600 block mb-1">정답 *</label>
          <input v-model="form.answer" class="w-full border rounded px-2 py-1.5 text-sm" placeholder="예: 강아지" />
        </div>
        <div>
          <label class="text-[11px] font-bold text-gray-600 block mb-1">오답 (선택) - <code>|||</code> 로 구분</label>
          <input v-model="form.wrong_answers" class="w-full border rounded px-2 py-1.5 text-sm" placeholder="예: 고양이|||토끼|||여우 (비우면 같은 레벨 다른 정답에서 자동)" />
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="text-[11px] font-bold text-gray-600 block mb-1">Noto 이모지 hex</label>
            <input v-model="form.emoji_hex" class="w-full border rounded px-2 py-1.5 text-sm font-mono" placeholder="1f436" />
          </div>
          <div>
            <label class="text-[11px] font-bold text-gray-600 block mb-1">또는 이미지 URL</label>
            <input v-model="form.image_url" class="w-full border rounded px-2 py-1.5 text-sm" placeholder="https://..." />
          </div>
        </div>
        <div v-if="imageUrl(form)" class="text-center">
          <img :src="imageUrl(form)" class="w-24 h-24 object-contain inline-block border rounded bg-gray-50" />
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="text-[11px] font-bold text-gray-600 block mb-1">힌트</label>
            <input v-model="form.hint" class="w-full border rounded px-2 py-1.5 text-sm" />
          </div>
          <div>
            <label class="text-[11px] font-bold text-gray-600 block mb-1">음성 표현</label>
            <input v-model="form.sound" class="w-full border rounded px-2 py-1.5 text-sm" placeholder="예: 멍멍!" />
          </div>
        </div>
      </div>
      <div class="flex justify-end gap-2 mt-5">
        <button @click="modalOpen = false" class="px-4 py-1.5 text-sm text-gray-500 hover:bg-gray-100 rounded">취소</button>
        <button @click="save" :disabled="saving" class="bg-amber-400 text-amber-900 font-bold px-4 py-1.5 rounded text-sm disabled:opacity-50">
          {{ saving ? '저장중...' : '저장' }}
        </button>
      </div>
    </div>
  </div>
</div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useRoute, RouterLink } from 'vue-router'
import axios from 'axios'

const route = useRoute()
const slug = route.params.slug

const items = ref([])
const meta = ref({ total: 0, last_page: 1 })
const page = ref(1)
const loading = ref(false)
const filterLevel = ref('')
const filterSearch = ref('')

const modalOpen = ref(false)
const saving = ref(false)
const form = reactive({ id: null, level: 1, answer: '', wrong_answers: '', emoji_hex: '', image_url: '', hint: '', sound: '', is_active: true })

function imageUrl(q) {
  if (q.image_url) return q.image_url
  if (q.emoji_hex) return `https://fonts.gstatic.com/s/e/notoemoji/latest/${q.emoji_hex}/512.png`
  return ''
}

async function load() {
  loading.value = true
  try {
    const params = { page: page.value }
    if (filterLevel.value) params.level = filterLevel.value
    if (filterSearch.value.trim()) params.search = filterSearch.value.trim()
    const { data } = await axios.get(`/api/admin/games/${slug}/questions`, { params })
    items.value = data.data?.data || []
    meta.value = { total: data.data?.total || 0, last_page: data.data?.last_page || 1 }
  } catch { items.value = [] }
  loading.value = false
}

function resetForm() {
  Object.assign(form, { id: null, level: 1, answer: '', wrong_answers: '', emoji_hex: '', image_url: '', hint: '', sound: '', is_active: true })
}

function openNew() { resetForm(); modalOpen.value = true }
function edit(q) { Object.assign(form, { ...q, wrong_answers: q.wrong_answers || '' }); modalOpen.value = true }

async function save() {
  if (!form.answer.trim()) return alert('정답을 입력하세요')
  saving.value = true
  try {
    if (form.id) {
      await axios.put(`/api/admin/games/${slug}/questions/${form.id}`, form)
    } else {
      await axios.post(`/api/admin/games/${slug}/questions`, form)
    }
    modalOpen.value = false
    await load()
  } catch (e) {
    alert('저장 실패: ' + (e.response?.data?.message || e.message))
  }
  saving.value = false
}

async function toggle(q) {
  const prev = q.is_active
  q.is_active = !q.is_active
  try { await axios.put(`/api/admin/games/${slug}/questions/${q.id}`, { is_active: q.is_active }) }
  catch { q.is_active = prev }
}

async function remove(q) {
  if (!confirm(`'${q.answer}' 문제를 삭제할까요?`)) return
  try {
    await axios.delete(`/api/admin/games/${slug}/questions/${q.id}`)
    await load()
  } catch { alert('삭제 실패') }
}

onMounted(load)
</script>
