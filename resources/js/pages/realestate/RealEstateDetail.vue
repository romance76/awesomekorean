<template>
<div class="min-h-screen bg-gray-50">
  <div class="max-w-7xl mx-auto px-4 py-5">
    <button @click="$router.back()" class="text-sm text-gray-500 hover:text-amber-600 mb-3">← 부동산 목록</button>
    <div v-if="loading" class="text-center py-12 text-gray-400">로딩중...</div>
    <div v-else-if="listing">

      <!-- ═══ 사진 갤러리 (Zillow 스타일) ═══ -->
      <div v-if="photos.length" class="rounded-xl overflow-hidden mb-4">
        <div class="grid gap-1" :class="photos.length >= 5 ? 'grid-cols-4 grid-rows-2' : photos.length >= 3 ? 'grid-cols-3' : photos.length === 2 ? 'grid-cols-2' : 'grid-cols-1'">
          <!-- 메인 사진 (왼쪽 큰거) -->
          <div :class="photos.length >= 5 ? 'col-span-2 row-span-2' : ''" class="relative cursor-pointer" @click="openLightbox(0)">
            <img :src="photoUrl(photos[0])" class="w-full h-full object-cover" :class="photos.length >= 5 ? 'h-[400px]' : 'h-[300px]'" />
            <div v-if="listing.promotion_tier && listing.promotion_tier !== 'none'" class="absolute top-3 left-3">
              <span v-if="listing.promotion_tier==='national'" class="text-xs bg-red-500 text-white font-bold px-2 py-1 rounded shadow">🌐 전국구</span>
              <span v-else-if="listing.promotion_tier==='state_plus'" class="text-xs bg-blue-500 text-white font-bold px-2 py-1 rounded shadow">⭐ 주+</span>
              <span v-else-if="listing.promotion_tier==='sponsored'" class="text-xs bg-amber-500 text-white font-bold px-2 py-1 rounded shadow">📢 스폰서</span>
            </div>
          </div>
          <!-- 서브 사진들 -->
          <div v-for="(p, idx) in photos.slice(1, photos.length >= 5 ? 5 : 3)" :key="idx+1"
            class="relative cursor-pointer" @click="openLightbox(idx+1)">
            <img :src="photoUrl(p)" class="w-full h-full object-cover h-[198px]" />
            <!-- 마지막 서브: +N 오버레이 -->
            <div v-if="idx === (photos.length >= 5 ? 3 : 1) && photos.length > (photos.length >= 5 ? 5 : 3)"
              class="absolute inset-0 bg-black/50 flex items-center justify-center">
              <span class="text-white font-bold text-lg">📷 +{{ photos.length - (photos.length >= 5 ? 5 : 3) }}</span>
            </div>
          </div>
        </div>
      </div>
      <!-- 사진 없음 -->
      <div v-else class="bg-gray-100 rounded-xl h-[200px] flex items-center justify-center text-4xl mb-4">🏠</div>

      <div class="grid grid-cols-12 gap-4">
        <!-- 메인 컨텐츠 -->
        <div class="col-span-12 lg:col-span-8 space-y-4">
          <!-- 가격 + 기본정보 -->
          <div class="bg-white rounded-xl shadow-sm border p-5">
            <div class="flex items-center gap-2 mb-2 flex-wrap">
              <span class="text-xs px-2 py-0.5 rounded-full font-bold" :class="listing.type==='sale'?'bg-red-100 text-red-700':'bg-blue-100 text-blue-700'">
                {{ listing.type==='sale' ? '매매' : '렌트' }}
              </span>
              <span class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full font-bold">{{ listing.property_type }}</span>
            </div>
            <div class="text-3xl font-black text-gray-900">${{ Number(listing.price).toLocaleString() }}<span v-if="listing.type==='rent'" class="text-lg font-bold text-gray-500">/월</span></div>
            <div class="text-sm text-gray-600 mt-1">📍 {{ listing.address ? listing.address + ', ' : '' }}{{ listing.city }}, {{ listing.state }} {{ listing.zipcode }}</div>

            <!-- 방/화장실/sqft (크게) -->
            <div class="flex items-center gap-6 mt-4 pt-4 border-t">
              <div v-if="listing.bedrooms" class="text-center">
                <div class="text-2xl font-black text-gray-800">{{ listing.bedrooms }}</div>
                <div class="text-xs text-gray-500">beds</div>
              </div>
              <div v-if="listing.bathrooms" class="text-center">
                <div class="text-2xl font-black text-gray-800">{{ listing.bathrooms }}</div>
                <div class="text-xs text-gray-500">baths</div>
              </div>
              <div v-if="listing.sqft" class="text-center">
                <div class="text-2xl font-black text-gray-800">{{ Number(listing.sqft).toLocaleString() }}</div>
                <div class="text-xs text-gray-500">sqft</div>
              </div>
              <div class="text-center">
                <div class="text-2xl font-black text-gray-800">{{ listing.view_count || 0 }}</div>
                <div class="text-xs text-gray-500">조회</div>
              </div>
            </div>

            <div v-if="listing.deposit" class="mt-3 text-sm text-gray-600">💰 보증금: <b>${{ Number(listing.deposit).toLocaleString() }}</b></div>
          </div>

          <!-- 설명 -->
          <div class="bg-white rounded-xl shadow-sm border p-5">
            <h2 class="font-bold text-gray-800 mb-3">📝 상세 설명</h2>
            <div class="text-sm text-gray-700 whitespace-pre-wrap leading-relaxed">{{ listing.content }}</div>
          </div>

          <!-- 지도 (Google Maps embed) -->
          <div v-if="listing.lat && listing.lng" class="bg-white rounded-xl shadow-sm border overflow-hidden">
            <h2 class="font-bold text-gray-800 px-5 pt-4 mb-2">📍 위치</h2>
            <iframe
              :src="`https://www.google.com/maps?q=${listing.lat},${listing.lng}&z=15&output=embed`"
              class="w-full h-[300px] border-0" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
          </div>

          <!-- 주변 학교 (GreatSchools 링크) -->
          <div v-if="listing.zipcode" class="bg-white rounded-xl shadow-sm border p-5">
            <h2 class="font-bold text-gray-800 mb-3">🏫 주변 학교</h2>
            <p class="text-sm text-gray-500 mb-3">GreatSchools.org 에서 이 지역의 학교 정보와 평점을 확인하세요.</p>
            <a :href="`https://www.greatschools.org/search/search.page?q=${listing.zipcode}&distance=5`"
              target="_blank" rel="noopener"
              class="inline-flex items-center gap-2 bg-green-500 text-white font-bold px-4 py-2 rounded-lg hover:bg-green-600 text-sm">
              🏫 주변 학교 보기 ({{ listing.zipcode }})
            </a>
            <div class="mt-3 text-[10px] text-gray-400">* GreatSchools.org 에서 학교 등급, 리뷰, 거리 정보 제공</div>
          </div>

          <!-- 수정/삭제 -->
          <div v-if="isOwner" class="flex items-center gap-3 justify-end">
            <RouterLink :to="`/realestate/write?edit=${listing.id}`" class="text-sm text-amber-600 hover:text-amber-800">✏️ 수정</RouterLink>
            <button @click="deleteListing" class="text-sm text-red-400 hover:text-red-600">🗑 삭제</button>
          </div>

          <!-- 댓글 -->
          <CommentSection v-if="listing.id" type="realestate" :typeId="listing.id" />
        </div>

        <!-- 오른쪽 사이드바 -->
        <div class="col-span-12 lg:col-span-4 hidden lg:block space-y-4">
          <!-- 연락처 카드 -->
          <div class="bg-white rounded-xl shadow-sm border p-5">
            <h3 class="font-bold text-gray-800 mb-3">📞 연락처</h3>
            <div v-if="listing.contact_phone" class="flex items-center gap-2 mb-2">
              <span class="text-xl">📱</span>
              <a :href="'tel:'+listing.contact_phone" class="text-sm font-bold text-blue-600 hover:underline">{{ listing.contact_phone }}</a>
            </div>
            <div v-if="listing.contact_email" class="flex items-center gap-2">
              <span class="text-xl">📧</span>
              <a :href="'mailto:'+listing.contact_email" class="text-sm text-blue-600 hover:underline break-all">{{ listing.contact_email }}</a>
            </div>
            <div v-if="listing.user" class="mt-3 pt-3 border-t flex items-center gap-2">
              <img v-if="listing.user.avatar" :src="listing.user.avatar" class="w-8 h-8 rounded-full object-cover" />
              <div>
                <UserName :userId="listing.user.id" :name="listing.user.name || listing.user.nickname" className="text-sm font-bold text-gray-800" />
                <div class="text-[10px] text-gray-400">등록일: {{ fmtDate(listing.created_at) }}</div>
              </div>
            </div>
          </div>

          <SidebarWidgets api-url="/api/realestate" detail-path="/realestate/" :current-id="listing.id"
            label="매물"
            :filter-params="listing.lat && listing.lng ? { lat: listing.lat, lng: listing.lng, radius: 50 } : {}" />
        </div>
      </div>
    </div>
  </div>

  <!-- 라이트박스 -->
  <div v-if="lightboxIdx !== null" class="fixed inset-0 bg-black/90 z-50 flex items-center justify-center" @click.self="lightboxIdx=null">
    <button class="absolute top-4 right-4 text-white text-3xl hover:text-gray-300" @click="lightboxIdx=null">✕</button>
    <button class="absolute left-4 top-1/2 -translate-y-1/2 text-white text-3xl hover:text-gray-300" @click="prevPhoto">‹</button>
    <button class="absolute right-4 top-1/2 -translate-y-1/2 text-white text-3xl hover:text-gray-300" @click="nextPhoto">›</button>
    <img :src="photoUrl(photos[lightboxIdx])" class="max-w-[90vw] max-h-[85vh] object-contain rounded-lg" />
    <div class="absolute bottom-4 text-white text-sm">{{ lightboxIdx + 1 }} / {{ photos.length }}</div>
  </div>
</div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import SidebarWidgets from '../../components/SidebarWidgets.vue'
import CommentSection from '../../components/CommentSection.vue'
import axios from 'axios'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const listing = ref(null)
const loading = ref(true)
const lightboxIdx = ref(null)

const photos = computed(() => {
  if (!listing.value?.images) return []
  return Array.isArray(listing.value.images) ? listing.value.images : []
})

const isOwner = computed(() => auth.user?.id === listing.value?.user_id || auth.user?.is_admin)

function photoUrl(path) {
  if (!path) return ''
  return String(path).startsWith('http') || String(path).startsWith('/storage/') ? path : '/storage/' + path
}

function openLightbox(idx) { lightboxIdx.value = idx }
function prevPhoto() { if (lightboxIdx.value > 0) lightboxIdx.value-- }
function nextPhoto() { if (lightboxIdx.value < photos.value.length - 1) lightboxIdx.value++ }

function fmtDate(dt) {
  if (!dt) return ''
  const d = new Date(dt)
  return `${d.getFullYear()}.${d.getMonth()+1}.${d.getDate()}`
}

async function deleteListing() {
  if (!confirm('정말 삭제하시겠습니까?')) return
  try { await axios.delete(`/api/realestate/${listing.value.id}`); router.push('/realestate') } catch {}
}

onMounted(async () => {
  try { const { data } = await axios.get(`/api/realestate/${route.params.id}`); listing.value = data.data } catch {}
  loading.value = false
})
</script>
