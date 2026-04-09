<template>
<div class="min-h-screen bg-gray-50">
  <div class="max-w-7xl mx-auto px-4 py-5">
    <div class="flex items-center justify-between mb-4">
      <h1 class="text-xl font-black text-gray-800">❓ Q&A</h1>
      <RouterLink v-if="auth.isLoggedIn" to="/qa/write" class="bg-amber-400 text-amber-900 font-bold px-4 py-2 rounded-lg text-sm hover:bg-amber-500">✏️ 질문하기</RouterLink>
    </div>

    <div class="grid grid-cols-12 gap-4">
      <!-- 왼쪽: 카테고리 + 상태 -->
      <div class="col-span-12 lg:col-span-2 hidden lg:block">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden sticky top-20">
          <div class="px-3 py-2.5 border-b font-bold text-xs text-amber-900">📋 카테고리</div>
          <button @click="activeCat=null; activeItem=null; loadQa()" class="w-full text-left px-3 py-2 text-xs transition"
            :class="!activeCat ? 'bg-amber-50 text-amber-700 font-bold' : 'text-gray-600 hover:bg-amber-50/50'">전체</button>
          <button v-for="cat in categories" :key="cat.id" @click="activeCat=cat; activeItem=null; loadQa()"
            class="w-full text-left px-3 py-2 text-xs transition"
            :class="activeCat?.id===cat.id ? 'bg-amber-50 text-amber-700 font-bold' : 'text-gray-600 hover:bg-amber-50/50'">{{ cat.name }}</button>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mt-3">
          <div class="px-3 py-2.5 border-b font-bold text-xs text-amber-900">🔍 상태</div>
          <button v-for="s in statusFilters" :key="s.value" @click="statusFilter=s.value; activeItem=null; loadQa()"
            class="w-full text-left px-3 py-2 text-xs transition"
            :class="statusFilter===s.value ? 'bg-amber-50 text-amber-700 font-bold' : 'text-gray-600 hover:bg-amber-50/50'">{{ s.label }}</button>
        </div>
      </div>

      <!-- 메인: 목록 또는 상세 (인라인 전환) -->
      <div class="col-span-12 lg:col-span-7">

        <div class="mb-2">
          <span class="font-bold text-amber-700 text-sm">{{ activeCat ? activeCat.name : '전체' }}</span>
          <span v-if="!activeCat" class="text-xs text-gray-400 ml-2">모든 질문을 볼 수 있습니다</span>
        </div>

        <!-- ═══ 상세 모드 ═══ -->
        <div v-if="activeItem">

          <!-- 질문 카드 -->
          <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mb-3">
            <div class="px-5 py-4">
              <div class="flex items-center gap-2 mb-2">
                <span class="text-[10px] bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded-full font-semibold">{{ activeItem.category?.name }}</span>
                <span v-if="activeItem.bounty_points > 0" class="text-[10px] bg-yellow-400 text-yellow-900 px-1.5 py-0.5 rounded-full font-bold">🏆 {{ activeItem.bounty_points }}P</span>
                <span v-if="activeItem.is_resolved" class="text-[10px] bg-green-100 text-green-700 px-1.5 py-0.5 rounded-full font-bold">✅ 해결</span>
                <span v-else class="text-[10px] bg-red-100 text-red-600 px-1.5 py-0.5 rounded-full">미해결</span>
              </div>
              <h2 class="text-lg font-bold text-gray-900">{{ activeItem.title }}</h2>
              <div class="text-xs text-gray-400 mt-1"><UserName :userId="activeItem.user?.id" :name="activeItem.user?.name" className="text-xs text-gray-400 inline" /> · {{ activeItem.view_count }}조회 · 답변 {{ activeItem.answer_count }}개</div>
            </div>
            <div class="px-5 py-4 border-t text-sm text-gray-700 whitespace-pre-wrap">{{ activeItem.content }}</div>
          </div>

          <!-- 답변 목록 -->
          <div class="space-y-2 mb-3">
            <h3 class="font-bold text-sm text-gray-800">💡 답변 {{ answers.length }}개</h3>
            <div v-for="ans in answers" :key="ans.id"
              class="bg-white rounded-xl shadow-sm border overflow-hidden"
              :class="ans.is_best ? 'border-amber-400' : 'border-gray-100'">
              <div v-if="ans.is_best" class="bg-amber-50 px-4 py-1.5 text-xs font-bold text-amber-700">👑 채택된 답변</div>
              <div class="px-5 py-4">
                <div class="text-sm text-gray-700 whitespace-pre-wrap">{{ ans.content }}</div>
                <div class="flex items-center gap-3 mt-3 text-xs text-gray-400">
                  <UserName :userId="ans.user?.id" :name="ans.user?.name" className="font-semibold text-gray-600" />
                  <span>❤️ {{ ans.like_count }}</span>
                  <span>{{ formatDate(ans.created_at) }}</span>
                </div>
              </div>
            </div>
          </div>

          <!-- 답변 작성 -->
          <div v-if="auth.isLoggedIn && !activeItem.is_resolved" class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
            <h3 class="font-bold text-sm text-gray-800 mb-2">✍️ 답변 작성</h3>
            <textarea v-model="newAnswer" rows="4" placeholder="답변을 입력하세요..." class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-400 outline-none resize-none"></textarea>
            <button @click="submitAnswer" :disabled="!newAnswer.trim()" class="mt-2 bg-amber-400 text-amber-900 font-bold px-5 py-2 rounded-lg text-sm hover:bg-amber-500 disabled:opacity-50">답변 등록</button>
          </div>

          <!-- 이전/다음글 -->
          <div class="flex justify-between mt-3">
            <button @click="navItem(-1)" :disabled="currentIdx <= 0" class="text-xs text-gray-500 hover:text-amber-700 disabled:opacity-30">← 이전글</button>
            <button @click="activeItem=null; answers=[]" class="text-xs text-gray-400 hover:text-gray-600">목록</button>
            <button @click="navItem(1)" :disabled="currentIdx >= items.length-1" class="text-xs text-gray-500 hover:text-amber-700 disabled:opacity-30">다음글 →</button>
          </div>
        </div>

        <!-- ═══ 목록 모드 ═══ -->
        <div v-else>
          <div class="lg:hidden flex gap-2 mb-3">
            <select @change="e => { activeCat = categories.find(c=>c.id==e.target.value)||null; loadQa() }" class="flex-1 border rounded-lg px-2 py-2 text-sm">
              <option :value="null">전체 카테고리</option>
              <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
            </select>
          </div>

          <div v-if="loading" class="text-center py-12 text-gray-400">로딩중...</div>
          <div v-else-if="!items.length" class="text-center py-12">
            <div class="text-4xl mb-3">❓</div>
            <div class="text-gray-500 font-semibold">질문이 없습니다</div>
          </div>
          <div v-else class="space-y-2">
            <div v-for="item in items" :key="item.id" @click="openItem(item)"
              class="bg-white rounded-xl shadow-sm border border-gray-100 px-4 py-3 hover:shadow-md hover:border-amber-300 transition cursor-pointer">
              <div class="flex items-center gap-2 mb-1">
                <span class="text-[10px] bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded-full font-semibold">{{ item.category?.name || 'Q&A' }}</span>
                <span v-if="item.bounty_points > 0" class="text-[10px] bg-yellow-400 text-yellow-900 px-1.5 py-0.5 rounded-full font-bold">🏆 {{ item.bounty_points }}P</span>
                <span v-if="item.is_resolved" class="text-[10px] bg-green-100 text-green-700 px-1.5 py-0.5 rounded-full font-bold">✅ 해결</span>
                <span v-else class="text-[10px] bg-red-100 text-red-600 px-1.5 py-0.5 rounded-full">미해결</span>
              </div>
              <div class="text-sm font-medium text-gray-800">{{ item.title }}</div>
              <div class="flex items-center gap-3 mt-1.5 text-xs text-gray-400">
                <UserName :userId="item.user?.id" :name="item.user?.name" className="text-xs text-gray-400" />
                <span>💬 답변 {{ item.answer_count }}개</span>
                <span>👁 {{ item.view_count }}</span>
                <span>{{ formatDate(item.created_at) }}</span>
              </div>
            </div>
          </div>

          <div v-if="lastPage > 1" class="flex justify-center gap-1.5 mt-4">
            <button v-for="pg in Math.min(lastPage, 10)" :key="pg" @click="loadQa(pg)"
              class="px-3 py-1 rounded text-sm" :class="pg===page?'bg-amber-400 text-amber-900 font-bold':'bg-white border text-gray-600'">{{ pg }}</button>
          </div>
        </div>
      </div>

      <!-- 오른쪽: 위젯 -->
      <div class="col-span-12 lg:col-span-3 hidden lg:block">
        <SidebarWidgets :inline="true" @select="openItem" api-url="/api/qa" detail-path="/qa/" :current-id="activeItem?.id || 0"
          label="질문" />
      </div>
    </div>
  </div>
</div>
</template>
<script setup>
import { useRoute } from 'vue-router'
import { ref, onMounted } from 'vue'
import { useAuthStore } from '../../stores/auth'
import SidebarWidgets from '../../components/SidebarWidgets.vue'
import axios from 'axios'

const auth = useAuthStore()
const route = useRoute()
const items = ref([])
const categories = ref([])
const activeCat = ref(null)
const statusFilter = ref('')
const activeItem = ref(null)
const answers = ref([])
const newAnswer = ref('')
const currentIdx = ref(-1)

function navItem(dir) {
  const newIdx = currentIdx.value + dir
  if (newIdx >= 0 && newIdx < items.value.length) openItem(items.value[newIdx])
}
const loading = ref(true)
const page = ref(1)
const lastPage = ref(1)

const statusFilters = [
  { value: '', label: '전체' },
  { value: 'false', label: '❌ 미해결' },
  { value: 'true', label: '✅ 해결됨' },
]

function formatDate(dt) {
  if (!dt) return ''
  const h = Math.floor((Date.now() - new Date(dt).getTime()) / 3600000)
  if (h < 1) return '방금'
  if (h < 24) return h + '시간 전'
  return Math.floor(h / 24) + '일 전'
}

async function openItem(item) {
  currentIdx.value = items.value.findIndex(i => i.id === item.id)
  try {
    const { data } = await axios.get(`/api/qa/${item.id}`)
    activeItem.value = data.data
    answers.value = data.data?.answers || []
  } catch {
    activeItem.value = item
    answers.value = []
  }
  if (activeItem.value?.category_id) activeCat.value = categories.value.find(c => c.id === activeItem.value.category_id) || null
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

async function submitAnswer() {
  if (!newAnswer.value.trim() || !activeItem.value) return
  try {
    const { data } = await axios.post(`/api/qa/${activeItem.value.id}/answer`, { content: newAnswer.value })
    answers.value.push(data.data)
    newAnswer.value = ''
    activeItem.value.answer_count++
  } catch {}
}

async function loadQa(p = 1) {
  loading.value = true; page.value = p
  const params = { page: p, per_page: 20 }
  if (activeCat.value) params.category_id = activeCat.value.id
  if (statusFilter.value) params.resolved = statusFilter.value
  try {
    const { data } = await axios.get('/api/qa', { params })
    items.value = data.data?.data || []
    lastPage.value = data.data?.last_page || 1
  } catch {}
  loading.value = false
}

onMounted(async () => {
  const [qRes, cRes] = await Promise.allSettled([
    axios.get('/api/qa?per_page=20'),
    axios.get('/api/qa/categories'),
  ])
  if (qRes.status === 'fulfilled') { items.value = qRes.value.data?.data?.data || []; lastPage.value = qRes.value.data?.data?.last_page || 1 }
  if (cRes.status === 'fulfilled') categories.value = cRes.value.data?.data || []

  // URL에 id가 있으면 해당 항목 인라인 열기
  const itemId = route.params.id
  if (itemId) {
    try { const { data } = await axios.get('/api/qa/' + itemId); activeItem.value = data.data } catch {}
  }
  loading.value = false
})
</script>
