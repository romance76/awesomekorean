<template>
  <div class="min-h-screen bg-gray-50 pb-16">
  <div class="max-w-[1200px] mx-auto px-4 pt-4">
    <!-- 헤더 -->
    <div class="bg-gradient-to-r from-teal-600 to-emerald-500 text-white rounded-2xl px-6 py-5 mb-6">
      <div class="flex items-center justify-between">
      <div>
        <h1 class="text-xl font-black">🤝 멘토링 매칭</h1>
        <p class="text-sm opacity-80 mt-0.5">한인 선배에게 배우고 · 후배에게 경험을 나눠요</p>
      </div>
      <button v-if="auth.isLoggedIn" @click="showRegister = true"
        class="bg-white text-teal-700 font-black px-4 py-2 rounded-xl text-sm hover:bg-teal-50 transition">
        🙋 멘토 등록
      </button>
      </div>
    </div>

    <!-- 분야 필터 -->
    <div class="flex gap-2 overflow-x-auto pb-2 mb-4 scrollbar-hide">
      <button v-for="f in fields" :key="f.key" @click="activeField = f.key; fetchMentors()"
        class="flex-shrink-0 px-4 py-2 rounded-full text-sm font-semibold transition"
        :class="activeField === f.key ? 'bg-teal-600 text-white' : 'bg-white text-gray-600 border border-gray-200'">
        {{ f.icon }} {{ f.label }}
      </button>
    </div>

    <!-- 멘토 목록 -->
    <div v-if="loading" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <div v-for="i in 6" :key="i" class="bg-white rounded-2xl h-48 animate-pulse"></div>
    </div>
    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <div v-for="m in mentors" :key="m.id" @click="selected = m"
        class="bg-white rounded-2xl shadow-sm hover:shadow-md transition cursor-pointer p-5">
        <!-- 상단: 아바타 + 이름 -->
        <div class="flex items-start gap-3 mb-3">
          <div class="w-12 h-12 rounded-full bg-teal-100 flex items-center justify-center font-black text-teal-700 overflow-hidden flex-shrink-0">
            <img v-if="m.user?.avatar" :src="m.user.avatar" class="w-full h-full object-cover" />
            <span v-else>{{ (m.user?.name ?? '?')[0] }}</span>
          </div>
          <div class="flex-1 min-w-0">
            <p class="font-black text-gray-800 truncate">{{ m.user?.name }}</p>
            <p class="text-xs text-gray-400 truncate">{{ m.position }} · {{ m.company }}</p>
          </div>
          <span class="text-xs px-2 py-0.5 rounded-full font-semibold"
            :class="m.is_available ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'">
            {{ m.is_available ? '활성' : '비활성' }}
          </span>
        </div>

        <!-- 분야 배지 -->
        <div class="flex flex-wrap gap-1 mb-3">
          <span class="bg-teal-50 text-teal-700 text-xs px-2 py-0.5 rounded-full font-semibold">{{ m.field }}</span>
          <span class="bg-gray-50 text-gray-500 text-xs px-2 py-0.5 rounded-full">경력 {{ m.years_experience }}년</span>
        </div>

        <p class="text-xs text-gray-500 line-clamp-2 mb-3">{{ m.bio }}</p>

        <!-- 스킬 -->
        <div v-if="m.skills?.length" class="flex flex-wrap gap-1">
          <span v-for="s in m.skills.slice(0, 4)" :key="s"
            class="bg-gray-100 text-gray-500 text-xs px-2 py-0.5 rounded-md">
            {{ s }}
          </span>
          <span v-if="m.skills.length > 4" class="text-xs text-gray-400">+{{ m.skills.length - 4 }}</span>
        </div>
      </div>
    </div>

    <div v-if="!loading && !mentors.length" class="text-center py-16 text-gray-400">
      <p class="text-5xl mb-3">🤝</p>
      <p class="font-semibold">등록된 멘토가 없습니다</p>
    </div>

    <!-- 멘토 상세 모달 -->
    <Transition name="fade">
      <div v-if="selected" class="fixed inset-0 bg-black/60 flex items-center justify-center z-50 px-4" @click.self="selected=null">
        <div class="bg-white rounded-2xl max-w-md w-full shadow-2xl overflow-hidden">
          <div class="bg-gradient-to-r from-teal-600 to-emerald-500 px-6 py-5 text-white flex items-center gap-4">
            <div class="w-14 h-14 rounded-full bg-white/20 flex items-center justify-center font-black text-2xl overflow-hidden">
              <img v-if="selected.user?.avatar" :src="selected.user.avatar" class="w-full h-full object-cover" />
              <span v-else>{{ (selected.user?.name ?? '?')[0] }}</span>
            </div>
            <div>
              <p class="font-black text-lg">{{ selected.user?.name }}</p>
              <p class="text-white/80 text-sm">{{ selected.position }} · {{ selected.company }}</p>
            </div>
            <button @click="selected=null" class="ml-auto text-white/70">✕</button>
          </div>
          <div class="p-5">
            <div class="flex gap-2 mb-4 flex-wrap">
              <span class="bg-teal-50 text-teal-700 text-sm px-3 py-1 rounded-full font-bold">{{ selected.field }}</span>
              <span class="bg-gray-100 text-gray-500 text-sm px-3 py-1 rounded-full">경력 {{ selected.years_experience }}년</span>
            </div>
            <p class="text-sm text-gray-600 leading-relaxed mb-4 whitespace-pre-wrap">{{ selected.bio }}</p>
            <div v-if="selected.skills?.length" class="flex flex-wrap gap-1.5 mb-5">
              <span v-for="s in selected.skills" :key="s" class="bg-gray-100 text-gray-600 text-xs px-2.5 py-1 rounded-lg">{{ s }}</span>
            </div>

            <!-- 신청 -->
            <template v-if="auth.isLoggedIn">
              <textarea v-model="requestMsg" rows="3" placeholder="멘토링 신청 메시지를 입력하세요 (어떤 분야의 도움이 필요한지)"
                class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm resize-none focus:outline-none focus:border-teal-400 mb-3"></textarea>
              <button @click="sendRequest" :disabled="!requestMsg.trim() || requesting"
                class="w-full bg-teal-600 text-white py-3 rounded-xl font-black text-sm disabled:opacity-50">
                {{ requesting ? '신청 중...' : '🙋 멘토링 신청' }}
              </button>
            </template>
            <button v-else @click="$router.push('/auth/login')"
              class="w-full bg-teal-600 text-white py-3 rounded-xl font-black text-sm">
              로그인 후 신청
            </button>
          </div>
        </div>
      </div>
    </Transition>

    <!-- 멘토 등록 모달 -->
    <Transition name="fade">
      <div v-if="showRegister" class="fixed inset-0 bg-black/60 flex items-center justify-center z-50 px-4" @click.self="showRegister=false">
        <div class="bg-white rounded-2xl max-w-md w-full max-h-[90vh] overflow-y-auto shadow-2xl">
          <div class="bg-gradient-to-r from-teal-600 to-emerald-500 px-6 py-5 text-white rounded-t-2xl">
            <h2 class="font-black text-lg">🙋 멘토 등록</h2>
          </div>
          <div class="p-5 space-y-4">
            <div>
              <label class="block text-xs font-semibold text-gray-500 mb-1">분야 *</label>
              <select v-model="regForm.field" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-teal-400">
                <option v-for="f in fields.filter(f=>f.key!=='all')" :key="f.key" :value="f.label">{{ f.icon }} {{ f.label }}</option>
              </select>
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-xs font-semibold text-gray-500 mb-1">회사</label>
                <input v-model="regForm.company" placeholder="회사명"
                  class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-teal-400" />
              </div>
              <div>
                <label class="block text-xs font-semibold text-gray-500 mb-1">직책</label>
                <input v-model="regForm.position" placeholder="직책"
                  class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-teal-400" />
              </div>
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-500 mb-1">경력 (년) *</label>
              <input v-model="regForm.years_experience" type="number" min="0"
                class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-teal-400" />
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-500 mb-1">자기소개 *</label>
              <textarea v-model="regForm.bio" rows="4" placeholder="어떤 경험을 가지고 있는지, 어떤 도움을 줄 수 있는지 소개해주세요"
                class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm resize-none focus:outline-none focus:border-teal-400"></textarea>
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-500 mb-1">스킬 (쉼표로 구분)</label>
              <input v-model="skillsInput" placeholder="React, Python, 마케팅, 세무..."
                class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-teal-400" />
            </div>
            <div class="flex gap-3 pt-2">
              <button @click="showRegister=false" class="flex-1 border border-gray-200 text-gray-600 py-2.5 rounded-xl font-semibold text-sm">취소</button>
              <button @click="registerMentor" :disabled="registering || !regForm.field || !regForm.bio"
                class="flex-1 bg-teal-600 text-white py-2.5 rounded-xl font-black text-sm disabled:opacity-50">
                {{ registering ? '등록 중...' : '등록하기' }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import { useAuthStore } from '../../stores/auth'

const auth = useAuthStore()

const fields = [
  { key: 'all',     icon: '🌐', label: '전체' },
  { key: 'biz',     icon: '💼', label: '비즈니스' },
  { key: 'it',      icon: '💻', label: 'IT/개발' },
  { key: 'medical', icon: '🏥', label: '의료/헬스' },
  { key: 'law',     icon: '⚖️', label: '법률/세무' },
  { key: 'edu',     icon: '📚', label: '교육' },
  { key: 'real',    icon: '🏠', label: '부동산' },
  { key: 'marketing',icon:'📢', label: '마케팅' },
]

const activeField   = ref('all')
const mentors       = ref([])
const loading       = ref(true)
const selected      = ref(null)
const requestMsg    = ref('')
const requesting    = ref(false)
const showRegister  = ref(false)
const registering   = ref(false)
const skillsInput   = ref('')

const regForm = ref({ field: '비즈니스', bio: '', years_experience: 1, company: '', position: '' })

async function fetchMentors() {
  loading.value = true
  try {
    const { data } = await axios.get('/api/mentors', {
      params: activeField.value !== 'all' ? { field: fields.find(f=>f.key===activeField.value)?.label } : {}
    })
    mentors.value = data.data ?? data
  } catch {} finally { loading.value = false }
}

onMounted(fetchMentors)

async function sendRequest() {
  if (!requestMsg.value.trim()) return
  requesting.value = true
  try {
    await axios.post(`/api/mentors/${selected.value.id}/request`, { message: requestMsg.value })
    alert('멘토링 신청이 완료됐습니다! 멘토의 수락을 기다려주세요.')
    selected.value = null; requestMsg.value = ''
  } catch (e) { alert(e?.response?.data?.message || '신청 실패') }
  finally { requesting.value = false }
}

async function registerMentor() {
  if (!regForm.value.field || !regForm.value.bio) return
  registering.value = true
  try {
    const payload = {
      ...regForm.value,
      skills: skillsInput.value ? skillsInput.value.split(',').map(s => s.trim()).filter(Boolean) : [],
    }
    const { data } = await axios.post('/api/mentors/profile', payload)
    mentors.value.unshift(data)
    showRegister.value = false
    alert('멘토로 등록되었습니다!')
  } catch (e) { alert(e?.response?.data?.message || '등록 실패') }
  finally { registering.value = false }
}
</script>

<style scoped>
.scrollbar-hide::-webkit-scrollbar { display: none; }
.scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
.fade-enter-active, .fade-leave-active { transition: opacity 0.2s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
.line-clamp-2 { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
</style>
