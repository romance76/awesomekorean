<template>
<div class="min-h-screen bg-gray-50">
  <div class="max-w-7xl mx-auto px-4 py-5">
    <router-link to="/jobs" class="text-xl font-black text-gray-800 mb-3 inline-block hover:text-amber-600 transition">
      💼 구인구직
    </router-link>

    <div v-if="loading" class="text-center py-20 text-gray-400">로딩중...</div>

    <div v-else-if="job" class="grid grid-cols-12 gap-4">

      <!-- ══════════ LEFT: 카테고리 사이드바 (JobList와 동일) ══════════ -->
      <aside class="col-span-2 hidden lg:block">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden sticky top-20">
          <div class="px-3 py-2.5 border-b font-bold text-xs text-gray-800">📋 카테고리</div>
          <div class="flex border-b">
            <router-link to="/jobs?type=hiring"
              class="flex-1 py-1.5 text-[10px] font-bold text-center transition"
              :class="job.post_type !== 'seeking' ? 'bg-amber-400 text-amber-900' : 'text-gray-400 hover:bg-gray-50'">
              💼 구인
            </router-link>
            <router-link to="/jobs?type=seeking"
              class="flex-1 py-1.5 text-[10px] font-bold text-center transition"
              :class="job.post_type === 'seeking' ? 'bg-blue-500 text-white' : 'text-gray-400 hover:bg-gray-50'">
              🙋 구직
            </router-link>
          </div>
          <router-link v-for="c in jobCategories" :key="c.value" :to="`/jobs${c.value ? '?category=' + c.value : ''}`"
            class="block w-full text-left px-3 py-2 text-xs transition"
            :class="c.value === job.category ? 'bg-amber-50 text-amber-700 font-bold' : 'text-gray-600 hover:bg-gray-50'">
            {{ c.label }}
          </router-link>
          <AdSlot page="jobs" position="left" :maxSlots="2" />
        </div>
      </aside>

      <!-- ══════════ CENTER: Job Detail Card ══════════ -->
      <main class="col-span-12 lg:col-span-7 md:col-span-8">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">

          <!-- Header: badges + title + company + location -->
          <div class="px-3 lg:px-5 py-3 lg:py-4 border-b border-gray-100">
            <div class="flex items-center gap-2 flex-wrap mb-2">
              <!-- 프로모션 뱃지 -->
              <span v-if="job.promotion_tier === 'national'" class="text-xs bg-red-500 text-white px-2 py-0.5 rounded-full font-bold">🌐 전국구</span>
              <span v-else-if="job.promotion_tier === 'state_plus'" class="text-xs bg-blue-500 text-white px-2 py-0.5 rounded-full font-bold">⭐ 주+인접 상위노출</span>
              <span v-else-if="job.promotion_tier === 'sponsored'" class="text-xs bg-amber-500 text-white px-2 py-0.5 rounded-full font-bold">📢 스폰서</span>
              <span class="text-xs px-2 py-0.5 rounded-full font-bold" :class="typeClass(job.type)">{{ typeLabel(job.type) }}</span>
              <span class="text-xs px-2 py-0.5 rounded-full font-bold" :class="job.post_type === 'hiring' ? 'bg-indigo-100 text-indigo-700' : 'bg-pink-100 text-pink-700'">
                {{ job.post_type === 'hiring' ? '구인' : '구직' }}
              </span>
            </div>
            <div class="flex items-start gap-3">
              <!-- 로고 -->
              <img v-if="job.logo" :src="job.logo" class="w-16 h-16 rounded-lg object-cover border flex-shrink-0" @error="$event.target.style.display='none'" />
              <div class="flex-1 min-w-0">
                <h1 class="text-xl lg:text-2xl font-bold text-gray-900 leading-snug">{{ job.title }}</h1>
                <div v-if="job.company" class="text-sm lg:text-base text-amber-700 font-semibold mt-1">{{ job.company }}</div>
                <div class="text-xs lg:text-sm text-gray-500 mt-1">
                  <span v-if="job.city || job.state || job.zipcode">
                    {{ [job.city, job.state, job.zipcode].filter(Boolean).join(', ') }}
                  </span>
                </div>
                <!-- 직종 태그 -->
                <div v-if="job.job_tags && job.job_tags.length" class="flex items-center gap-1 mt-2 flex-wrap">
                  <span v-for="tag in job.job_tags" :key="tag" class="text-[10px] bg-amber-100 text-amber-700 px-2 py-0.5 rounded font-semibold">{{ jobTagLabel(tag) }}</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Salary box -->
          <div v-if="job.salary_min || job.salary_max" class="mx-3 lg:mx-5 my-3 bg-green-50 border border-green-200 rounded-lg px-3 lg:px-4 py-3">
            <div class="text-green-800 font-bold text-base flex items-center gap-2">
              <span>{{ formatSalary(job) }}</span>
            </div>
          </div>

          <!-- Tags: category + type -->
          <div class="px-3 lg:px-5 py-2 flex items-center gap-2 flex-wrap">
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
            <BookmarkToggle v-if="auth.isLoggedIn" :active="jobFavorited" @toggle="toggleJobFav" size="md" class="ml-2" />
          </div>

          <!-- Apply / Contact bar -->
          <div class="px-3 lg:px-5 py-3 border-t border-b border-gray-100 bg-gray-50/50">
            <div class="flex items-center gap-3 flex-wrap">
              <!-- 구인 글 → 지원하기 (이력서 모달) -->
              <button v-if="job.post_type === 'hiring'"
                @click="openApplyModal"
                class="inline-flex items-center gap-1.5 bg-amber-500 hover:bg-amber-600 text-white font-bold text-sm px-5 py-2 rounded-lg transition">
                지원하기
              </button>
              <!-- 구직 글 → 연락하기 (전화/이메일) -->
              <a v-else-if="job.contact_phone" :href="'tel:' + job.contact_phone"
                class="inline-flex items-center gap-1.5 bg-blue-500 hover:bg-blue-600 text-white font-bold text-sm px-5 py-2 rounded-lg transition">
                연락하기
              </a>
              <a v-else-if="job.contact_email" :href="'mailto:' + job.contact_email"
                class="inline-flex items-center gap-1.5 bg-blue-500 hover:bg-blue-600 text-white font-bold text-sm px-5 py-2 rounded-lg transition">
                연락하기
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

          <!-- ══════════ 지원 모달 ══════════ -->
          <Teleport to="body">
            <div v-if="showApplyModal" class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/40" @click.self="showApplyModal = false">
              <div class="bg-white rounded-2xl shadow-2xl w-[90vw] max-w-md mx-4 overflow-hidden">
                <!-- 모달 헤더 -->
                <div class="px-5 py-4 border-b bg-amber-50">
                  <div class="flex items-center justify-between">
                    <h3 class="text-base font-bold text-gray-900">지원하기</h3>
                    <button @click="showApplyModal = false" class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
                  </div>
                  <p class="text-xs text-gray-500 mt-1">{{ job.company }} — {{ job.title }}</p>
                </div>

                <!-- 로그인 안됨 -->
                <div v-if="!auth.isLoggedIn" class="p-5 text-center">
                  <div class="text-3xl mb-3">🔒</div>
                  <p class="text-sm text-gray-600 mb-4">지원하려면 로그인이 필요합니다</p>
                  <router-link to="/login" @click="showApplyModal = false"
                    class="inline-block bg-amber-500 text-white font-bold text-sm px-6 py-2.5 rounded-lg hover:bg-amber-600 transition">
                    로그인하기
                  </router-link>
                </div>

                <!-- 로그인 됨 -->
                <div v-else class="p-5">
                  <!-- 이력서 로딩 중 -->
                  <div v-if="resumeLoading" class="text-center py-6 text-gray-400 text-sm">이력서 확인 중...</div>

                  <!-- 이력서 있음 → 가져와서 지원 -->
                  <div v-else-if="myResume">
                    <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-4">
                      <div class="flex items-center gap-2 mb-2">
                        <span class="text-green-600 text-lg">✅</span>
                        <span class="text-sm font-bold text-green-800">내 이력서</span>
                      </div>
                      <div class="text-sm text-gray-700 font-semibold">{{ myResume.title }}</div>
                      <div class="text-xs text-gray-500 mt-1">
                        {{ myResume.name }} · {{ myResume.category ? categoryLabel(myResume.category) : '' }}
                        <span v-if="myResume.city"> · {{ myResume.city }}</span>
                      </div>
                    </div>

                    <button @click="submitApplication"
                      :disabled="applySubmitting"
                      class="w-full bg-amber-500 hover:bg-amber-600 text-white font-bold text-sm py-3 rounded-lg transition disabled:opacity-50">
                      {{ applySubmitting ? '지원 중...' : '이 이력서로 지원하기' }}
                    </button>

                    <div class="flex items-center gap-2 mt-3">
                      <router-link to="/dashboard?tab=resume" @click="showApplyModal = false"
                        class="flex-1 text-center text-xs text-amber-600 hover:text-amber-800 py-2 border border-amber-200 rounded-lg transition">
                        이력서 수정하기
                      </router-link>
                    </div>
                  </div>

                  <!-- 이력서 없음 → 작성 유도 -->
                  <div v-else class="text-center">
                    <div class="text-4xl mb-3">📝</div>
                    <p class="text-sm text-gray-600 mb-1">등록된 이력서가 없습니다</p>
                    <p class="text-xs text-gray-400 mb-5">이력서를 먼저 작성하면 간편하게 지원할 수 있어요</p>

                    <div class="flex flex-col gap-2">
                      <router-link to="/dashboard?tab=resume" @click="showApplyModal = false"
                        class="w-full bg-amber-500 hover:bg-amber-600 text-white font-bold text-sm py-3 rounded-lg text-center transition">
                        이력서 작성하기
                      </router-link>
                      <button @click="applyDirect"
                        class="w-full text-xs text-gray-500 hover:text-gray-700 py-2 border border-gray-200 rounded-lg transition">
                        이력서 없이 바로 연락하기
                      </button>
                    </div>
                  </div>

                  <!-- 지원 완료 메시지 -->
                  <div v-if="applyDone" class="mt-4 bg-blue-50 border border-blue-200 rounded-lg p-3 text-center">
                    <span class="text-blue-700 text-sm font-medium">✅ 지원이 완료되었습니다!</span>
                  </div>
                </div>
              </div>
            </div>
          </Teleport>

          <!-- Content body (HTML 또는 plain) -->
          <div class="px-3 lg:px-5 py-3 lg:py-5 text-xs lg:text-sm text-gray-700 leading-relaxed job-content"
            v-html="safeContent"></div>

          <!-- 복리후생 -->
          <div v-if="job.benefits && job.benefits.length" class="px-3 lg:px-5 py-3 border-t border-gray-100">
            <div class="text-xs font-bold text-gray-600 mb-2">🎁 복리후생</div>
            <div class="flex items-center gap-1.5 flex-wrap">
              <span v-for="b in job.benefits" :key="b" class="text-xs bg-green-50 text-green-700 px-2 py-1 rounded font-medium">{{ benefitLabel(b) }}</span>
            </div>
          </div>

          <!-- 회사 소개 PDF -->
          <div v-if="job.company_pdf" class="px-3 lg:px-5 py-3 border-t border-gray-100">
            <a :href="job.company_pdf" target="_blank"
              class="inline-flex items-center gap-2 bg-red-50 hover:bg-red-100 text-red-700 font-bold text-xs px-3 py-2 rounded-lg transition">
              📄 회사 소개서 (PDF) 다운로드
            </a>
          </div>

          <!-- Footer: author + date + actions -->
          <div class="px-3 lg:px-5 py-3 border-t border-gray-100 bg-gray-50/30 flex items-center justify-between flex-wrap gap-2">
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

        <!-- Prev / List / Next -->
        <div class="flex justify-between items-center mt-4 bg-white rounded-xl p-3 border">
          <button v-if="prevJob" @click="$router.push('/jobs/'+prevJob.id)" class="text-xs text-gray-600 hover:text-amber-600">&larr; 이전글</button>
          <span v-else></span>
          <router-link to="/jobs" class="text-xs font-bold text-amber-700 hover:text-amber-500">📋 목록</router-link>
          <button v-if="nextJob" @click="$router.push('/jobs/'+nextJob.id)" class="text-xs text-gray-600 hover:text-amber-600">다음글 &rarr;</button>
          <span v-else></span>
        </div>
      </main>

      <!-- ══════════ RIGHT: 내 위치 기반 관련 목록 ══════════ -->
      <aside class="col-span-4 md:col-span-4 lg:col-span-3 hidden md:block">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden sticky top-20">
          <div class="px-3 py-2.5 border-b font-bold text-xs text-amber-900 flex items-center gap-1">
            <span>{{ categoryLabel(job.category) }}</span>
            <span v-if="nearbyLabel" class="text-[10px] text-gray-400 font-normal">· {{ nearbyLabel }}</span>
            <span class="text-gray-400 font-normal ml-auto">{{ sameCategoryJobs.length }}건</span>
          </div>
          <div class="max-h-[70vh] overflow-y-auto divide-y divide-gray-50">
            <router-link v-for="j in sameCategoryJobs" :key="j.id" :to="`/jobs/${j.id}`"
              class="block px-3 py-2.5 text-xs transition hover:bg-amber-50/60"
              :class="j.id === job.id ? 'border-l-3 border-l-amber-400 bg-amber-50 font-bold' : ''">
              <div class="text-gray-800 truncate leading-snug">{{ j.title }}</div>
              <div class="text-gray-400 mt-0.5 truncate">
                <span v-if="j.company">{{ j.company }}</span>
                <span v-if="j.city" class="ml-1">· {{ j.city }}</span>
              </div>
            </router-link>
            <div v-if="!sameCategoryJobs.length" class="px-3 py-4 text-xs text-gray-400 text-center">
              내 지역 근처 공고가 없습니다
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
import { ref, computed, watch, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import { useLocation } from '../../composables/useLocation'
import CommentSection from '../../components/CommentSection.vue'
import AdSlot from '../../components/AdSlot.vue'
import BookmarkToggle from '../../components/BookmarkToggle.vue'
import axios from 'axios'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const job = ref(null)
const loading = ref(true)
const jobFavorited = ref(false)
const sameCategoryJobs = ref([])
const relatedJobs = ref([])
const nearbyLabel = ref('')

// ── Apply modal state ──
const showApplyModal = ref(false)
const myResume = ref(null)
const resumeLoading = ref(false)
const applySubmitting = ref(false)
const applyDone = ref(false)

// ── Prev / Next navigation ──
const currentJobIdx = computed(() => {
  if (!job.value) return -1
  return sameCategoryJobs.value.findIndex(j => j.id === job.value.id)
})
const prevJob = computed(() => {
  const idx = currentJobIdx.value
  return idx > 0 ? sameCategoryJobs.value[idx - 1] : null
})
const nextJob = computed(() => {
  const idx = currentJobIdx.value
  return idx >= 0 && idx < sameCategoryJobs.value.length - 1 ? sameCategoryJobs.value[idx + 1] : null
})

// ── Category helpers ──
const categoryMap = {
  restaurant: '🍳 요식업', it: '💻 IT', beauty: '💅 미용', driving: '🚗 운전',
  retail: '🛒 판매', office: '🏢 사무직', construction: '🔨 건설',
  medical: '🏥 의료', education: '📚 교육', etc: '📋 기타',
}

const jobCategories = [
  { value: '', label: '전체' },
  ...Object.entries(categoryMap).map(([value, label]) => ({ value, label }))
]

function categoryLabel(cat) {
  return categoryMap[cat] || cat || '기타'
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
// 직종 태그/복리후생 레이블
const jobTagLabels = { cook:'요리사', server:'서빙', cashier:'캐셔', manager:'매니저', barista:'바리스타', delivery:'배달', driver:'운전', mechanic:'정비', accountant:'회계', receptionist:'접수', cleaner:'청소', nail:'네일', hair:'미용사', esthetician:'피부관리', teacher:'강사', tutor:'과외', developer:'개발자', designer:'디자이너', sales:'영업', nurse:'간호사', security:'보안', warehouse:'창고' }
const benefitLabels = { health:'건강보험', dental:'치과보험', vision:'비전보험', '401k':'401K', paid_vacation:'유급휴가', sick_leave:'병가', meal:'식사제공', tips:'팁', bonus:'보너스', sponsor:'비자 스폰서', training:'교육지원', parking:'주차지원' }
function jobTagLabel(tag) { return jobTagLabels[tag] || tag }
function benefitLabel(b) { return benefitLabels[b] || b }

// HTML인지 plain text인지 감지해서 safe하게 렌더
const safeContent = computed(() => {
  if (!job.value?.content) return ''
  const c = job.value.content
  // HTML 태그 있으면 그대로, 없으면 <br> 변환
  if (/<[a-z][\s\S]*>/i.test(c)) return c
  return c.replace(/\n/g, '<br>')
})

function formatDate(dt) {
  if (!dt) return ''
  const d = new Date(dt)
  const y = d.getFullYear()
  const m = d.getMonth() + 1
  const day = d.getDate()
  return `${y}년 ${m}월 ${day}일`
}

// ── Apply modal functions ──
async function openApplyModal() {
  showApplyModal.value = true
  applyDone.value = false
  if (!auth.isLoggedIn) return

  // 내 이력서 불러오기
  resumeLoading.value = true
  myResume.value = null
  try {
    const { data } = await axios.get('/api/my-resume')
    myResume.value = data.data
  } catch {}
  resumeLoading.value = false
}

async function submitApplication() {
  if (!job.value || !myResume.value) return
  applySubmitting.value = true
  try {
    await axios.post(`/api/jobs/${job.value.id}/apply`, { resume_id: myResume.value.id })
    applyDone.value = true
  } catch (e) {
    // 이미 지원했거나 에러
    const msg = e.response?.data?.message || '지원 중 오류가 발생했습니다'
    alert(msg)
  }
  applySubmitting.value = false
}

function applyDirect() {
  showApplyModal.value = false
  // 전화 또는 이메일 직접 연결
  if (job.value.contact_phone) {
    window.location.href = 'tel:' + job.value.contact_phone
  } else if (job.value.contact_email) {
    window.location.href = 'mailto:' + job.value.contact_email
  }
}

// ── Location (useLocation composable — JobList와 동일) ──
const { city: locCity, init: initLocation } = useLocation()

// ── Load data ──
async function loadJob(id) {
  loading.value = true
  job.value = null
  sameCategoryJobs.value = []
  relatedJobs.value = []
  nearbyLabel.value = ''

  try {
    const { data } = await axios.get(`/api/jobs/${id}`)
    job.value = data.data

    const cat = job.value.category
    const postType = job.value.post_type

    // 1순위: 유저 프로필 위치, 2순위: 현재 공고 위치
    await initLocation()
    const userLoc = locCity.value
    let lat, lng, label
    if (userLoc?.lat && userLoc?.lng) {
      lat = userLoc.lat
      lng = userLoc.lng
      label = userLoc.label || userLoc.name || '내 주변'
    } else if (job.value.lat && job.value.lng) {
      lat = parseFloat(job.value.lat)
      lng = parseFloat(job.value.lng)
      label = job.value.city || job.value.state || '이 공고 주변'
    }

    // 위치 기반 같은 카테고리 목록
    const sameParams = { category: cat, post_type: postType, per_page: 30 }
    if (lat && lng) {
      sameParams.lat = lat
      sameParams.lng = lng
      sameParams.radius = 50
      nearbyLabel.value = label
    }

    const { data: sameData } = await axios.get('/api/jobs', { params: sameParams })
    const sameRaw = sameData?.data?.data || []
    sameCategoryJobs.value = sameRaw
  } catch (e) {
    job.value = null
    if (e.response?.status === 404) router.replace('/404')
  }
  loading.value = false
  // 즐겨찾기 체크
  if (auth.isLoggedIn && job.value) {
    try {
      const { data: bData } = await axios.get('/api/bookmarks/check', { params: { type: 'App\\Models\\JobPost', ids: job.value.id } })
      jobFavorited.value = (bData.data || []).includes(job.value.id)
    } catch {}
  }
}

async function toggleJobFav() {
  if (!auth.isLoggedIn || !job.value) return
  const { useBookmarkStore } = await import('../../stores/bookmarks')
  const bStore = useBookmarkStore()
  const result = await bStore.toggle('App\\Models\\JobPost', job.value.id)
  if (result !== null) jobFavorited.value = result
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

<style scoped>
.job-content :deep(h2) { font-size: 1.1rem; font-weight: bold; margin: 12px 0 6px; }
.job-content :deep(h3) { font-size: 1rem; font-weight: bold; margin: 10px 0 5px; }
.job-content :deep(img) { max-width: 100%; height: auto; border-radius: 6px; margin: 8px 0; }
.job-content :deep(ul), .job-content :deep(ol) { padding-left: 20px; margin: 6px 0; }
.job-content :deep(ul) { list-style: disc; }
.job-content :deep(ol) { list-style: decimal; }
.job-content :deep(p) { margin: 6px 0; }
</style>
