<template>
<div class="min-h-screen bg-gray-50">
  <div class="max-w-7xl mx-auto px-4 py-5">
    <div class="flex items-center justify-between mb-4">
      <h1 class="text-xl font-black text-gray-800">🏠 부동산</h1>
      <RouterLink v-if="auth.isLoggedIn" to="/realestate/write" class="bg-amber-400 text-amber-900 font-bold px-3 py-1.5 rounded-lg text-xs">✏️ 등록</RouterLink>
    </div>

    <div v-if="loading" class="text-center py-12 text-gray-400">로딩중...</div>
    <div v-else-if="listing" class="grid grid-cols-12 gap-4">

      <!-- 왼쪽: 카테고리 (리스트와 동일) -->
      <div class="col-span-12 lg:col-span-2 hidden lg:block">
        <div class="sticky top-20 max-h-[calc(100vh-6rem)] overflow-y-auto space-y-3 pr-0.5">
          <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-3 py-2.5 border-b font-bold text-xs text-amber-900">📋 부동산</div>
            <div class="flex border-b">
              <RouterLink to="/realestate?type=rent" class="flex-1 py-1.5 text-[10px] font-bold text-center transition"
                :class="listing.type==='rent' ? 'bg-blue-500 text-white' : 'text-gray-400 hover:bg-gray-50'">🔑 렌트</RouterLink>
              <RouterLink to="/realestate?type=sale" class="flex-1 py-1.5 text-[10px] font-bold text-center transition"
                :class="listing.type==='sale' ? 'bg-red-500 text-white' : 'text-gray-400 hover:bg-gray-50'">🏠 매매</RouterLink>
            </div>
            <RouterLink to="/realestate" class="block w-full text-left px-3 py-2 text-xs text-gray-600 hover:bg-amber-50/50 border-b">← 전체 목록</RouterLink>
            <div class="px-3 py-1 bg-gray-50 text-[9px] text-gray-500 font-bold">현재 매물</div>
            <div class="px-3 py-1.5 text-[11px] text-amber-700 font-bold truncate bg-amber-50">{{ listing.title }}</div>
          </div>
          <AdSlot page="realestate" position="left" :maxSlots="2" />
        </div>
      </div>

      <!-- 중앙: 상세 -->
      <div class="col-span-12 lg:col-span-7 space-y-4">
        <!-- 사진 갤러리 (메인 + 썸네일) -->
        <div v-if="allPhotos.length" class="bg-white rounded-xl shadow-sm border overflow-hidden">
          <!-- 메인 사진 -->
          <div class="relative cursor-pointer" @click="openLightbox(activePhotoIdx)">
            <img :src="photoUrl(allPhotos[activePhotoIdx])" style="width:100%; height:400px; object-fit:cover;" />
            <div class="absolute bottom-3 right-3 bg-black/60 text-white text-xs px-2 py-1 rounded">
              {{ activePhotoIdx + 1 }} / {{ allPhotos.length }}
            </div>
          </div>
          <!-- 썸네일 (아래) -->
          <div v-if="allPhotos.length > 1" class="flex gap-1 p-2 overflow-x-auto bg-gray-50">
            <div v-for="(p, idx) in allPhotos" :key="idx"
              @click="activePhotoIdx = idx"
              class="flex-shrink-0 rounded cursor-pointer border-2 transition overflow-hidden"
              :class="idx === activePhotoIdx ? 'border-amber-400' : 'border-transparent hover:border-gray-300'"
              style="width:60px; height:45px;">
              <img :src="photoUrl(p)" style="width:100%; height:100%; object-fit:cover;" />
            </div>
          </div>
        </div>
        <div v-else class="bg-gray-100 rounded-xl flex items-center justify-center text-4xl" style="height:200px;">🏠</div>

        <!-- 가격 + 정보 -->
        <div class="bg-white rounded-xl shadow-sm border p-4">
          <div class="flex items-center gap-2 mb-1">
            <span class="text-[10px] px-1.5 py-0.5 rounded-full font-bold" :class="listing.type==='sale'?'bg-red-100 text-red-700':'bg-blue-100 text-blue-700'">
              {{ listing.type==='sale' ? '매매' : '렌트' }}
            </span>
            <span class="text-[10px] bg-gray-100 text-gray-600 px-1.5 py-0.5 rounded-full font-bold">{{ listing.property_type }}</span>
          </div>
          <div class="text-2xl font-black text-gray-900">${{ Number(listing.price).toLocaleString() }}<span v-if="listing.type==='rent'" class="text-sm font-bold text-gray-500">/월</span></div>
          <h1 class="text-base font-bold text-gray-800 mt-1">{{ listing.title }}</h1>
          <div class="text-xs text-gray-500 mt-1">📍 {{ listing.address ? listing.address + ', ' : '' }}{{ listing.city }}, {{ listing.state }} {{ listing.zipcode }}</div>
          <div class="flex items-center gap-4 mt-3 pt-3 border-t text-center">
            <div v-if="listing.bedrooms"><div class="text-lg font-black text-gray-800">{{ listing.bedrooms }}</div><div class="text-[10px] text-gray-500">beds</div></div>
            <div v-if="listing.bathrooms"><div class="text-lg font-black text-gray-800">{{ listing.bathrooms }}</div><div class="text-[10px] text-gray-500">baths</div></div>
            <div v-if="listing.sqft"><div class="text-lg font-black text-gray-800">{{ Number(listing.sqft).toLocaleString() }}</div><div class="text-[10px] text-gray-500">sqft</div></div>
            <div><div class="text-lg font-black text-gray-800">{{ listing.view_count || 0 }}</div><div class="text-[10px] text-gray-500">조회</div></div>
          </div>
        </div>

        <!-- 설명 -->
        <div class="bg-white rounded-xl shadow-sm border p-4">
          <h2 class="font-bold text-sm text-gray-800 mb-2">📝 상세 설명</h2>
          <div class="text-sm text-gray-700 whitespace-pre-wrap leading-relaxed">{{ listing.content }}</div>
        </div>

        <!-- 지도 -->
        <div v-if="listing.lat && listing.lng" class="bg-white rounded-xl shadow-sm border overflow-hidden">
          <h2 class="font-bold text-sm text-gray-800 px-4 pt-3 mb-1">📍 위치</h2>
          <iframe :src="`https://www.google.com/maps?q=${listing.lat},${listing.lng}&z=15&output=embed`"
            class="w-full border-0" style="height:250px;" loading="lazy"></iframe>
        </div>

        <!-- 학교 -->
        <div v-if="listing.zipcode" class="bg-white rounded-xl shadow-sm border p-4">
          <h2 class="font-bold text-sm text-gray-800 mb-2">🏫 주변 학교</h2>
          <a :href="`https://www.greatschools.org/search/search.page?q=${listing.zipcode}&distance=5`"
            target="_blank" rel="noopener"
            class="inline-flex items-center gap-1 bg-green-500 text-white font-bold px-3 py-1.5 rounded-lg hover:bg-green-600 text-xs">
            🏫 주변 학교 보기 ({{ listing.zipcode }})
          </a>
        </div>

        <!-- 수정/삭제 -->
        <div v-if="isOwner" class="flex items-center gap-3 justify-end">
          <RouterLink :to="`/realestate/write?edit=${listing.id}`" class="text-sm text-amber-600 hover:text-amber-800">✏️ 수정</RouterLink>
          <button @click="deleteListing" class="text-sm text-red-400 hover:text-red-600">🗑 삭제</button>
        </div>

        <CommentSection v-if="listing.id" type="realestate" :typeId="listing.id" />
      </div>

      <!-- 오른쪽: 판매자 정보 + 위젯 -->
      <div class="col-span-12 lg:col-span-3 hidden lg:block space-y-3">
        <!-- 판매자/연락처 카드 -->
        <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
          <div class="px-4 py-3 border-b bg-amber-50 font-bold text-xs text-amber-900">📋 판매자 정보</div>
          <div class="p-4 space-y-3">
            <div v-if="listing.user" class="flex items-center gap-3">
              <img v-if="listing.user.avatar" :src="listing.user.avatar" class="w-12 h-12 rounded-full object-cover border-2 border-amber-200" />
              <div v-else class="w-12 h-12 rounded-full bg-amber-100 flex items-center justify-center text-lg font-bold text-amber-700">
                {{ (listing.user.nickname || listing.user.name || '?')[0] }}
              </div>
              <div>
                <UserName :userId="listing.user.id" :name="listing.user.nickname || listing.user.name" className="text-sm font-bold text-gray-800" />
                <div class="text-[10px] text-gray-400">가입: {{ fmtDate(listing.user.created_at) }}</div>
              </div>
            </div>

            <!-- 연락처 -->
            <div class="space-y-2 pt-2 border-t">
              <a v-if="listing.contact_phone" :href="'tel:'+listing.contact_phone"
                class="flex items-center gap-2 w-full bg-blue-500 text-white font-bold px-3 py-2 rounded-lg hover:bg-blue-600 text-sm justify-center">
                📱 {{ listing.contact_phone }}
              </a>
              <a v-if="listing.contact_email" :href="'mailto:'+listing.contact_email"
                class="flex items-center gap-2 w-full bg-gray-100 text-gray-700 font-bold px-3 py-2 rounded-lg hover:bg-gray-200 text-sm justify-center">
                📧 이메일 보내기
              </a>
            </div>

            <!-- 액션 -->
            <div v-if="auth.isLoggedIn && listing.user_id !== auth.user?.id" class="flex gap-2 pt-2 border-t">
              <button @click="sendFriendRequest" class="flex-1 text-xs bg-green-50 text-green-700 font-bold py-2 rounded-lg hover:bg-green-100">👫 친구추가</button>
              <button @click="sendMessage" class="flex-1 text-xs bg-blue-50 text-blue-700 font-bold py-2 rounded-lg hover:bg-blue-100">✉️ 쪽지</button>
              <button @click="reportUser" class="text-xs bg-red-50 text-red-600 font-bold py-2 px-3 rounded-lg hover:bg-red-100">🚨</button>
            </div>
          </div>
        </div>

        <!-- 등록일/조회 -->
        <div class="bg-white rounded-xl shadow-sm border p-4 text-xs text-gray-500 space-y-1">
          <div>📅 등록일: {{ fmtDate(listing.created_at) }}</div>
          <div>👁 조회: {{ listing.view_count }}회</div>
          <div v-if="listing.promotion_tier && listing.promotion_tier !== 'none'">
            🚀 상위노출: {{ {national:'전국구',state_plus:'주+',sponsored:'스폰서'}[listing.promotion_tier] }}
          </div>
        </div>

        <SidebarWidgets api-url="/api/realestate" detail-path="/realestate/" :current-id="listing.id"
          label="매물"
          :filter-params="listing.lat && listing.lng ? { lat: listing.lat, lng: listing.lng, radius: 50 } : {}" />
        <AdSlot page="realestate" position="right" :maxSlots="2" />
      </div>
    </div>
  </div>

  <!-- 라이트박스 -->
  <div v-if="lightboxIdx !== null" class="fixed inset-0 bg-black/95 z-50 flex flex-col items-center justify-center" @click.self="lightboxIdx=null">
    <button class="absolute top-4 right-4 text-white text-3xl hover:text-gray-300 z-10" @click="lightboxIdx=null">✕</button>
    <img v-if="allPhotos[lightboxIdx]" :src="photoUrl(allPhotos[lightboxIdx])" style="max-width:90vw;max-height:75vh;object-fit:contain;border-radius:8px;" />
    <div class="flex gap-2 mt-4 overflow-x-auto px-4">
      <div v-for="(p, idx) in allPhotos" :key="idx" @click="lightboxIdx=idx"
        class="flex-shrink-0 rounded cursor-pointer border-2 overflow-hidden"
        :class="idx === lightboxIdx ? 'border-amber-400' : 'border-transparent'"
        style="width:50px; height:38px;">
        <img :src="photoUrl(p)" style="width:100%;height:100%;object-fit:cover;" />
      </div>
    </div>
    <div class="text-white text-sm mt-2">{{ lightboxIdx + 1 }} / {{ allPhotos.length }}</div>
  </div>

  <!-- 쪽지 모달 -->
  <div v-if="msgModal" class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4" @click.self="msgModal=false">
    <div class="bg-white rounded-xl max-w-sm w-full p-4 space-y-3">
      <h3 class="font-bold text-sm text-gray-800">✉️ 쪽지 보내기</h3>
      <textarea v-model="msgText" rows="4" maxlength="500" placeholder="내용..." class="w-full border rounded-lg px-3 py-2 text-sm outline-none resize-none"></textarea>
      <div class="flex gap-2 justify-end">
        <button @click="msgModal=false" class="text-gray-500 text-xs px-3 py-1.5">취소</button>
        <button @click="doSendMessage" class="bg-blue-500 text-white font-bold px-3 py-1.5 rounded-lg text-xs">전송</button>
      </div>
    </div>
  </div>
</div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import SidebarWidgets from '../../components/SidebarWidgets.vue'
import CommentSection from '../../components/CommentSection.vue'
import AdSlot from '../../components/AdSlot.vue'
import axios from 'axios'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const listing = ref(null)
const loading = ref(true)
const lightboxIdx = ref(null)
const activePhotoIdx = ref(0)
const msgModal = ref(false)
const msgText = ref('')

const allPhotos = computed(() => {
  const imgs = listing.value?.images
  return Array.isArray(imgs) ? imgs : []
})

const isOwner = computed(() => auth.user?.id === listing.value?.user_id || auth.user?.is_admin)

function photoUrl(path) {
  if (!path) return ''
  return String(path).startsWith('http') || String(path).startsWith('/storage/') ? path : '/storage/' + path
}
function openLightbox(idx) { lightboxIdx.value = idx }
function fmtDate(dt) {
  if (!dt) return ''
  const d = new Date(dt)
  return `${d.getFullYear()}.${d.getMonth()+1}.${d.getDate()}`
}
async function deleteListing() {
  if (!confirm('정말 삭제하시겠습니까?')) return
  try { await axios.delete(`/api/realestate/${listing.value.id}`); router.push('/realestate') } catch {}
}

// 판매자 액션
async function sendFriendRequest() {
  try { await axios.post(`/api/friends/request/${listing.value.user_id}`); alert('친구 요청을 보냈습니다') } catch (e) { alert(e.response?.data?.message || '실패') }
}
function sendMessage() { msgModal.value = true; msgText.value = '' }
async function doSendMessage() {
  if (!msgText.value.trim()) return
  try {
    await axios.post('/api/messages', { receiver_id: listing.value.user_id, content: msgText.value })
    alert('쪽지를 보냈습니다'); msgModal.value = false
  } catch (e) { alert(e.response?.data?.message || '실패') }
}
async function reportUser() {
  const reason = prompt('신고 사유를 입력해주세요:')
  if (!reason) return
  try {
    await axios.post('/api/reports', { reportable_type: 'App\\Models\\User', reportable_id: listing.value.user_id, reason })
    alert('신고가 접수되었습니다')
  } catch (e) { alert(e.response?.data?.message || '실패') }
}

onMounted(async () => {
  try { const { data } = await axios.get(`/api/realestate/${route.params.id}`); listing.value = data.data } catch {}
  loading.value = false
})
</script>
