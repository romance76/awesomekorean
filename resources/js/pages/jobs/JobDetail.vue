<template>
<div class="min-h-screen bg-gray-50">
  <div class="max-w-7xl mx-auto px-4 py-5">
    <button @click="router.push('/jobs')" class="text-sm text-gray-500 hover:text-amber-600 mb-3 inline-flex items-center gap-1">
      <span>&larr;</span> 구인구직 목록
    </button>

    <div v-if="loading" class="text-center py-20 text-gray-400">로딩중...</div>

    <div v-else-if="job" class="grid grid-cols-12 gap-4">

      <!-- ══════════ LEFT: 같은 카테고리 목록 (lg only) ══════════ -->
      <aside class="col-span-2 hidden lg:block">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden sticky top-20">
          <div class="px-3 py-2.5 border-b font-bold text-xs text-amber-900 flex items-center gap-1">
            <span>{{ categoryLabel(job.category) }}</span>
            <span class="text-gray-400 font-normal ml-auto">{{ sameCategoryJobs.length }}건</span>
          </div>
          <div class="max-h-[70vh] overflow-y-auto divide-y divide-gray-50">
            <router-link v-for="j in sameCategoryJobs" :key="j.id" :to="`/jobs/${j.id}`"
              class="block px-3 py-2.5 text-xs transition hover:bg-amber-50/60"
              :class="j.id === job.id ? 'border-l-3 border-l-amber-400 bg-amber-50' : ''">
              <div class="font-medium text-gray-800 truncate leading-snug">{{ j.title }}</div>
              <div class="text-gray-400 mt-0.5 truncate">
                <span v-if="j.company">{{ j.company }}</span>
                <span v-if="j.city" class="ml-1">{{ j.city }}</span>
              </div>
            </router-link>
            <div v-if="!sameCategoryJobs.length" class="px-3 py-4 text-xs text-gray-400 text-center">
              같은 카테고리 공고가 없습니다
            </div>
          </div>
        </div>
        <AdSlot page="jobs" position="left" :maxSlots="2" class="mt-4" />
      </aside>

      <!-- ══════════ CENTER: Job Detail Card ══════════ -->
      <main class="col-span-12 lg:col-span-7 md:col-span-8">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">

          <!-- Header: badges + title + company + location -->
          <div class="px-5 py-4 border-b border-gray-100">
            <div class="flex items-center gap-2 flex-wrap mb-2">
              <span class="text-xs px-2 py-0.5 rounded-full font-bold"
                :class="typeClass(job.type)">
                {{ typeLabel(job.type) }}
              </span>
              <span class="text-xs px-2 py-0.5 rounded-full font-bold"
                :class="job.post_type === 'hiring' ? 'bg-indigo-100 text-indigo-700' : 'bg-pink-100 text-pink-700'">
                {{ job.post_type === 'hiring' ? '구인' : '구직' }}
              </span>
            </div>
            <h1 class="text-xl font-bold text-gray-900 leading-snug">{{ job.title }}</h1>
            <div v-if="job.company" class="text-sm text-amber-700 font-semibold mt-1">{{ job.company }}</div>
            <div class="text-sm text-gray-500 mt-1 flex items-center gap-1">
              <span v-if="job.city || job.state || job.zipcode">
                {{ [job.city, job.state, job.zipcode].filter(Boolean).join(', ') }}
              </span>
            </div>
          </div>

          <!-- Salary box -->
          <div v-if="job.salary_min || job.salary_max" class="mx-5 my-3 bg-green-50 border border-green-200 rounded-lg px-4 py-3">
            <div class="text-green-800 font-bold text-base flex items-center gap-2">
              <span>{{ formatSalary(job) }}</span>
            </div>
          </div>

          <!-- Tags: category + type -->
          <div class="px-5 py-2 flex items-center gap-2 flex-wrap">
            <span class="text-xs px-2.5 py-1 rounded-full bg-gray-100 text-gray-600 font-medium">
              {{ categoryLabel(job.category) }}
            </span>
            <span class="text-xs px-2.5 py-1 rounded-full bg-gray-100 text-gray-600 font-medium">
              {{ typeLabel(job.type) }}
            </span>
            <span v-if="job.salary_type" class="text-xs px-2.5 py-1 rounded-full bg-gray-100 text-gray-600 font-medium">
              {{ salaryTypeLabel(job.salary_type) }}
            </span>
            <span class="text-xs text-gray-400 ml-auto">{{ job.view_count }}회 조회</span>
          </div>

          <!-- Apply / Contact bar -->
          <div class="px-5 py-3 border-t border-b border-gray-100 bg-gray-50/50">
            <div class="flex items-center gap-3 flex-wrap">
              <a v-if="job.contact_phone" :href="'tel:' + job.contact_phone"
                class="inline-flex items-center gap-1.5 bg-amber-500 hover:bg-amber-600 text-white font-bold text-sm px-5 py-2 rounded-lg transition">
                {{ job.post_type === 'hiring' ? '지원하기' : '연락하기' }}
              </a>
              <a v-else-if="job.contact_email" :href="'mailto:' + job.contact_email"
                class="inline-flex items-center gap-1.5 bg-amber-500 hover:bg-amber-600 text-white font-bold text-sm px-5 py-2 rounded-lg transition">
                {{ job.post_type === 'hiring' ? '지원하기' : '연락하기' }}
              </a>
            </div>
            <div class="flex items-center gap-4 mt-2 text-sm text-gray-600 flex-wrap">
              <a v-if="job.contact_phone" :href="'tel:' + job.contact_phone" class="hover:text-amber-600 transition">
                {{ job.contact_phone }}
              </a>
              <a v-if="job.contact_email" :href="'mailto:' + job.contact_email" class="hover:text-amber-600 transition">
                {{ job.contact_email }}
              </a>
            </div>
          </div>

          <!-- Content body -->
          <div class="px-5 py-5 text-sm text-gray-700 leading-relaxed whitespace-pre-wrap">{{ job.content }}</div>

          <!-- Footer: author + date + actions -->
          <div class="px-5 py-3 border-t border-gray-100 bg-gray-50/30 flex items-center justify-between flex-wrap gap-2">
            <div class="text-xs text-gray-500 flex items-center gap-2">
              <span>작성자:</span>
              <UserName v-if="job.user?.id" :userId="job.user.id" :name="job.user.nickname || job.user.name" className="text-gray-700 font-semibold" />
              <span v-else class="text-gray-700 font-semibold">{{ job.company || '익명' }}</span>
              <span class="text-gray-300">|</span>
              <span>{{ formatDate(job.created_at) }}</span>
            </div>
            <div v-if="auth.user?.id === job.user_id" class="flex items-center gap-3">
              <router-link :to="`/jobs/write?edit=${job.id}`" class="text-xs text-amber-600 hover:text-amber-800 font-medium">
                수정
              </router-link>
              <button @click="deleteJob" class="text-xs text-red-400 hover:text-red-600 font-medium">
                삭제
              </button>
            </div>
          </div>
        </div>

        <!-- Comments -->
        <CommentSection v-if="job.id" type="job" :typeId="job.id" class="mt-4" />
      </main>

      <!-- ══════════ RIGHT: Related Jobs (md+) ══════════ -->
      <aside class="col-span-4 md:col-span-4 lg:col-span-3 hidden md:block">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden sticky top-20">
          <div class="px-3 py-2.5 border-b font-bold text-xs text-amber-900">
            관련 채용공고
          </div>
          <div class="divide-y divide-gray-50">
            <router-link v-for="j in relatedJobs" :key="j.id" :to="`/jobs/${j.id}`"
              class="block px-3 py-3 hover:bg-amber-50/60 transition">
              <div class="text-xs font-medium text-gray-800 truncate leading-snug">{{ j.title }}</div>
              <div v-if="j.company" class="text-xs text-amber-700 mt-0.5 truncate">{{ j.company }}</div>
              <div class="text-xs text-gray-400 mt-0.5 flex items-center gap-1.5">
                <span v-if="j.city">{{ j.city }}, {{ j.state }}</span>
                <span v-if="j.salary_min" class="text-green-600 font-medium">
                  ${{ Number(j.salary_min).toLocaleString() }}~
                </span>
              </div>
            </router-link>
            <div v-if="!relatedJobs.length" class="px-3 py-4 text-xs text-gray-400 text-center">
              관련 공고가 없습니다
            </div>
          </div>
        </div>
        <AdSlot page="jobs" position="right" :maxSlots="2" class="mt-4" />
      </aside>

    </div>

    <!-- Not found -->
    <div v-else class="text-center py-20">
      <div class="text-4xl mb-3">&#128188;</div>
      <div class="text-gray-500 font-semibold">공고를 찾을 수 없습니다</div>
    </div>
  </div>
</div>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import CommentSection from '../../components/CommentSection.vue'
import AdSlot from '../../components/AdSlot.vue'
import axios from 'axios'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const job = ref(null)
const loading = ref(true)
const sameCategoryJobs = ref([])
const relatedJobs = ref([])

// ── Category helpers ──
const jobCategories = {
  restaurant: '🍳 요식업',
  it: '💻 IT',
  beauty: '💅 미용',
  driving: '🚗 운전',
  retail: '🛒 판매',
  office: '🏢 사무직',
  construction: '🔨 건설',
  medical: '🏥 의료',
  education: '📚 교육',
  etc: '📋 기타',
}

function categoryLabel(cat) {
  return jobCategories[cat] || cat || '기타'
}

// ── Type helpers ──
function typeLabel(t) {
  return { full: '풀타임', part: '파트타임', contract: '계약직' }[t] || t
}

function typeClass(t) {
  return {
    full: 'bg-blue-100 text-blue-700',
    part: 'bg-green-100 text-green-700',
    contract: 'bg-orange-100 text-orange-700',
  }[t] || 'bg-gray-100 text-gray-700'
}

// ── Salary helpers ──
function salaryTypeLabel(st) {
  return { hourly: '시급', monthly: '월급', yearly: '연봉' }[st] || st
}

function formatSalary(j) {
  const min = j.salary_min ? '$' + Number(j.salary_min).toLocaleString() : ''
  const max = j.salary_max ? '$' + Number(j.salary_max).toLocaleString() : ''
  const label = salaryTypeLabel(j.salary_type)
  if (min && max) return `${min} ~ ${max} / ${label}`
  if (min) return `${min} / ${label}`
  if (max) return `${max} / ${label}`
  return ''
}

// ── Date helper ──
function formatDate(dt) {
  if (!dt) return ''
  const d = new Date(dt)
  const y = d.getFullYear()
  const m = d.getMonth() + 1
  const day = d.getDate()
  return `${y}년 ${m}월 ${day}일`
}

// ── Load data ──
async function loadJob(id) {
  loading.value = true
  job.value = null
  sameCategoryJobs.value = []
  relatedJobs.value = []

  try {
    const { data } = await axios.get(`/api/jobs/${id}`)
    job.value = data.data

    // Load same-category and related jobs in parallel
    const cat = job.value.category
    const postType = job.value.post_type
    const [sameRes, relRes] = await Promise.all([
      axios.get('/api/jobs', { params: { category: cat, post_type: postType, per_page: 20 } }),
      axios.get('/api/jobs', { params: { category: cat, per_page: 10, page: 2 } }),
    ])

    const sameRaw = sameRes.data?.data?.data || []
    sameCategoryJobs.value = sameRaw

    // Related: exclude current job, take from both requests to fill
    const relRaw = relRes.data?.data?.data || []
    const combined = [...relRaw, ...sameRaw]
    const seen = new Set()
    relatedJobs.value = combined.filter(j => {
      if (j.id === job.value.id || seen.has(j.id)) return false
      seen.add(j.id)
      return true
    }).slice(0, 8)
  } catch (e) {
    job.value = null
  }
  loading.value = false
}

// ── Delete ──
async function deleteJob() {
  if (!confirm('정말 삭제하시겠습니까?')) return
  try {
    await axios.delete(`/api/jobs/${job.value.id}`)
    router.push('/jobs')
  } catch {}
}

// ── Watch route changes ──
watch(() => route.params.id, (newId) => {
  if (newId) {
    loadJob(newId)
    window.scrollTo({ top: 0, behavior: 'smooth' })
  }
})

onMounted(() => {
  if (route.params.id) loadJob(route.params.id)
})
</script>
