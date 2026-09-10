<template>
<div class="min-h-screen">
  <div class="max-w-7xl mx-auto px-4 py-5">
    <DetailHeader :title="item?.title || '중고장터'" fallback="/market" />
    <div class="hidden lg:flex items-center justify-between mb-4">
      <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink">
        <span class="icon-chip w-9 h-9 bg-emerald-50 text-emerald-600"><AppIcon name="shopping-cart" :size="20" /></span>
        중고장터
      </h1>
    </div>

    <div v-if="loading" class="text-center py-12 text-ink-muted">로딩중...</div>
    <div v-else-if="item" class="grid grid-cols-12 gap-4">

      <!-- 왼쪽: 카테고리 -->
      <div class="col-span-12 lg:col-span-2 hidden lg:block">
        <div class="sticky top-20 max-h-[calc(100vh-6rem)] overflow-y-auto space-y-3 pr-0.5">
          <div class="card overflow-hidden">
            <div class="px-3 py-2.5 border-b border-gray-50 font-bold text-xs text-ink flex items-center gap-1.5"><AppIcon name="list" :size="13" class="text-emerald-600" />카테고리</div>
            <RouterLink v-for="c in categories" :key="c.value" :to="c.value ? `/market?category=${c.value}` : '/market'"
              class="block w-full text-left px-3 py-2 text-xs transition-colors"
              :class="item.category === c.value ? 'bg-amber-50 text-amber-700 font-bold' : 'text-ink-light hover:bg-amber-50/50'">
              {{ c.label }}
            </RouterLink>
            <button v-if="auth.isLoggedIn" @click="$router.push('/market?fav=1')"
              class="w-full text-left px-3 py-2 text-xs transition-colors border-t border-gray-50 text-ink-light hover:bg-red-50/50 flex items-center gap-1">
              <AppIcon name="bookmark" :size="12" />내 북마크<span v-if="favCount > 0" class="ml-0.5">({{ favCount }})</span>
            </button>
          </div>
          <AdSlot page="market" position="left" :maxSlots="2" />
        </div>
      </div>

      <!-- 가운데: 상세 -->
      <div class="col-span-12 lg:col-span-7 space-y-4">
        <!-- 사진 갤러리 (전체 폭) -->
        <div v-if="item.images?.length" class="card overflow-hidden">
          <div class="relative cursor-pointer" @click="lightboxImg = mainImage">
            <img :src="mainImage" style="width:100%; height:380px; object-fit:contain; background:#f9fafb;" />
          </div>
          <div v-if="item.images.length > 1" class="flex gap-1 p-2 overflow-x-auto bg-gray-50">
            <div v-for="(img, i) in item.images" :key="i" @click="selectedImgIdx = i"
              class="flex-shrink-0 rounded cursor-pointer border-2 transition overflow-hidden"
              :class="i === selectedImgIdx ? 'border-amber-400' : 'border-transparent hover:border-gray-300'"
              style="width:60px; height:45px;">
              <img :src="getImageUrl(img)" style="width:100%;height:100%;object-fit:cover;" />
            </div>
          </div>
        </div>
        <div v-else class="bg-gray-100 rounded-2xl flex items-center justify-center text-gray-300" style="height:200px;"><AppIcon name="image" :size="40" :stroke-width="1.5" /></div>

        <!-- 가격/정보 + 판매자 (나란히) — 부동산 스타일 -->
        <div class="flex gap-3">
          <!-- 왼쪽: 상품 정보 -->
          <div class="flex-1 min-w-0 card p-4"
            :style="item.promotion_tier && item.promotion_tier !== 'none' ? promoBorderStyle : 'border: 1px solid #e5e7eb; border-radius: 12px;'">
            <!-- 1행: 뱃지 + ❤️🚨 -->
            <div class="flex items-center justify-between mb-2">
              <div class="flex items-center gap-2 flex-wrap">
                <span
                  :class="{'badge-green':item.status==='active','badge-primary':item.status==='reserved','badge-gray':item.status==='sold'}">
                  {{ {active:'판매중',reserved:'예약중',sold:'판매완료'}[item.status] }}
                </span>
                <span class="badge-gray !text-[11px]">{{ conditionLabel }}</span>
                <span v-if="item.is_negotiable" class="text-[11px] text-amber-600 font-semibold">가격협의</span>
                <span v-if="item.hold_enabled" class="badge-blue !text-[11px]"><AppIcon name="lock" :size="11" />홀드가능</span>
              </div>
              <div class="flex items-center gap-3">
                <BookmarkToggle :active="liked" @toggle="toggleLike" size="lg" />
                <ShareButton :title="item.title" :text="'중고장터: ' + item.title" label="" />
                <button @click="showReport = true" class="text-ink-faint hover:text-red-500 hover:scale-110 transition"><AppIcon name="alert-circle" :size="19" /></button>
              </div>
            </div>
            <!-- 가격 -->
            <div class="text-2xl font-black text-amber-600">${{ Number(item.price).toLocaleString() }}</div>
            <h1 class="text-base font-bold text-ink mt-1">{{ item.title }}</h1>
            <div class="text-xs text-ink-muted mt-1 flex items-center gap-0.5"><AppIcon name="map-pin" :size="12" />{{ item.city }}, {{ item.state }}</div>
            <!-- 스펙 + 등록일 -->
            <div class="flex items-center gap-4 mt-3 pt-3 border-t border-gray-100">
              <div class="text-center"><div class="text-lg font-black text-ink">{{ item.view_count || 0 }}</div><div class="text-xs text-ink-muted">조회</div></div>
              <div class="ml-auto text-xs text-ink-light text-right font-semibold">
                등록일: {{ formatFullDate(item.created_at) }}
              </div>
            </div>
          </div>

          <!-- 오른쪽: 판매자 정보 (부동산 스타일) -->
          <div class="hidden lg:block flex-shrink-0" style="width:200px;">
            <div class="card overflow-hidden h-full">
              <div class="px-3 py-2 border-b border-gray-50 font-bold text-xs text-ink flex items-center gap-1.5"><AppIcon name="user" :size="13" class="text-amber-600" />판매자 정보</div>
              <div class="p-3 space-y-2">
                <div v-if="item.user" class="flex items-center gap-2">
                  <img v-if="item.user.avatar" :src="'/storage/' + item.user.avatar" class="w-10 h-10 rounded-full object-cover border-2 border-amber-200" @error="e => e.target.style.display='none'" />
                  <div v-else class="w-10 h-10 rounded-full bg-amber-100 flex items-center justify-center text-sm font-bold text-amber-700">
                    {{ (item.user.nickname || item.user.name || '?')[0] }}
                  </div>
                  <div class="min-w-0">
                    <div class="text-xs font-bold text-ink truncate">{{ item.user?.nickname || item.user?.name }}</div>
                    <div class="text-[11px] text-ink-muted">가입: {{ formatFullDate(item.user?.created_at) }}</div>
                    <div class="text-[11px] text-amber-600 font-semibold">거래 {{ sellerTradeCount }}회</div>
                  </div>
                </div>
                <!-- 평점 -->
                <div v-if="item.seller_rating?.count" class="flex items-center gap-1 text-[11px] text-amber-600 font-semibold">
                  <AppIcon name="star" :size="12" />{{ item.seller_rating.average }} ({{ item.seller_rating.count }}건)
                </div>
                <!-- 친구/쪽지/채팅 -->
                <div v-if="auth.isLoggedIn && !isOwner" class="flex gap-1.5 pt-2 border-t border-gray-50">
                  <button @click="addFriend" class="flex-1 inline-flex items-center justify-center gap-1 text-[11px] bg-green-50 text-green-700 font-bold py-1.5 rounded-lg hover:bg-green-100 transition-colors"><AppIcon name="user-plus" :size="12" />친구</button>
                  <button @click="sendMessage" class="flex-1 inline-flex items-center justify-center gap-1 text-[11px] bg-blue-50 text-blue-700 font-bold py-1.5 rounded-lg hover:bg-blue-100 transition-colors"><AppIcon name="mail" :size="12" />쪽지</button>
                </div>
                <button v-if="auth.isLoggedIn && !isOwner" @click="startChat" :disabled="chatStarting"
                  class="w-full inline-flex items-center justify-center gap-1 text-[11px] bg-emerald-50 text-emerald-700 font-bold py-1.5 rounded-lg hover:bg-emerald-100 transition-colors disabled:opacity-50">
                  <AppIcon name="message-circle" :size="12" />실시간 채팅으로 문의
                </button>
                <!-- 전화 (있으면) -->
                <a v-if="item.user?.phone" :href="'tel:'+item.user.phone"
                  class="btn-primary w-full py-1.5 text-[11px]">
                  <AppIcon name="phone" :size="12" />{{ item.user.phone }}
                </a>
                <!-- 홀드 -->
                <button v-if="item.hold_enabled && item.status==='active' && auth.isLoggedIn && !isOwner && !item.active_hold"
                  @click="showHoldModal = true" class="w-full inline-flex items-center justify-center gap-1 bg-blue-500 text-white font-bold py-1.5 rounded-lg text-[11px] hover:bg-blue-600 transition-colors">
                  <AppIcon name="lock" :size="12" />홀드 ({{ item.hold_price_per_6h }}P/6h)
                </button>
                <!-- 부스트 (공용) -->
                <BoostButton v-if="isOwner && item.status === 'active'"
                  resource="market" :item="item" size="sm" @updated="loadItem" />
              </div>
            </div>
          </div>
        </div>

        <!-- 거래 진행 상황: 홀드 중일 때 약속 잡기 + (판매자) 거래완료 -->
        <div v-if="item.active_hold && (isOwner || isActiveBuyer)" class="card p-4 space-y-3">
          <h2 class="font-bold text-sm text-ink flex items-center gap-1.5"><AppIcon name="lock" :size="14" class="text-blue-500" />거래 진행 중</h2>
          <div class="text-xs text-ink-muted">
            {{ item.active_hold.buyer?.nickname || item.active_hold.buyer?.name }}님과 홀드 중 · 만료: {{ formatDateTime(item.active_hold.hold_until) }}
          </div>
          <div v-if="item.active_hold.meetup_at || item.active_hold.meetup_place" class="bg-blue-50 border border-blue-100 rounded-lg px-3 py-2 text-xs text-ink">
            <div class="font-bold text-blue-700 flex items-center gap-1"><AppIcon name="calendar" :size="12" />거래 약속</div>
            <div v-if="item.active_hold.meetup_at">{{ formatDateTime(item.active_hold.meetup_at) }}</div>
            <div v-if="item.active_hold.meetup_place">{{ item.active_hold.meetup_place }}</div>
          </div>
          <button v-if="!showMeetupForm" @click="openMeetupForm" class="text-xs text-blue-600 font-semibold hover:underline">
            {{ item.active_hold.meetup_at || item.active_hold.meetup_place ? '약속 수정' : '거래 약속 잡기' }}
          </button>
          <div v-if="showMeetupForm" class="space-y-2 bg-gray-50 rounded-lg p-3">
            <input type="datetime-local" v-model="meetupForm.at" class="input-soft text-xs" />
            <input type="text" v-model="meetupForm.place" placeholder="만날 장소 (예: OO마트 주차장)" maxlength="200" class="input-soft text-xs" />
            <div class="flex gap-2">
              <button @click="showMeetupForm=false" class="btn-secondary flex-1 py-1.5 text-xs">취소</button>
              <button @click="submitMeetup" :disabled="meetupSaving" class="flex-1 py-1.5 bg-blue-500 text-white rounded-lg text-xs font-bold disabled:opacity-50">저장</button>
            </div>
          </div>
          <button v-if="isOwner" @click="submitCompleteHold" :disabled="completingHold"
            class="w-full inline-flex items-center justify-center gap-1 py-2 bg-emerald-500 text-white rounded-lg text-xs font-bold hover:bg-emerald-600 disabled:opacity-50">
            <AppIcon name="check" :size="12" />거래완료 처리
          </button>
        </div>

        <!-- 홀드 상태 + 수정/삭제 (모바일에서도 보이게) -->
        <div v-if="item.active_hold || isOwner || (item.hold_enabled && item.status==='active' && auth.isLoggedIn && !isOwner && !item.active_hold)" class="card p-4 space-y-2 lg:hidden">
          <div v-if="item.active_hold" class="bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
            <span class="text-amber-600 font-bold text-sm inline-flex items-center gap-1"><AppIcon name="lock" :size="14" />홀드 중</span>
            <div class="text-xs text-ink-muted">{{ item.active_hold.buyer?.nickname || item.active_hold.buyer?.name }}님 · 만료: {{ formatDateTime(item.active_hold.hold_until) }}</div>
          </div>
          <button v-if="item.hold_enabled && item.status==='active' && auth.isLoggedIn && !isOwner && !item.active_hold"
            @click="showHoldModal = true" class="w-full inline-flex items-center justify-center gap-1.5 bg-blue-500 text-white font-bold py-2.5 rounded-xl text-sm hover:bg-blue-600 transition-colors">
            <AppIcon name="lock" :size="14" />홀드하기 ({{ item.hold_price_per_6h }}P/6h)
          </button>
          <BoostButton v-if="isOwner && item.status === 'active'"
            resource="market" :item="item" size="md" @updated="loadItem" />
          <div v-if="isOwner" class="flex gap-2 pt-1 border-t border-gray-50 mt-2">
            <RouterLink :to="`/market/write?edit=${item.id}`" class="btn-secondary flex-1 py-2 text-xs"><AppIcon name="edit" :size="12" />수정</RouterLink>
            <button @click="deleteItem" class="flex-1 inline-flex items-center justify-center gap-1 bg-red-50 text-red-600 font-semibold py-2 rounded-lg text-xs hover:bg-red-100 transition-colors"><AppIcon name="trash" :size="12" />삭제</button>
          </div>
        </div>

        <!-- 상세 설명 -->
        <div class="card p-4">
          <h2 class="font-bold text-sm text-ink mb-2 flex items-center gap-1.5"><AppIcon name="list" :size="14" class="text-emerald-600" />상세 설명</h2>
          <div class="text-sm text-ink-light whitespace-pre-wrap leading-relaxed">{{ item.content }}</div>
        </div>

        <!-- 주의사항 -->
        <div class="bg-gray-50 border border-gray-100 rounded-xl p-3">
          <div class="flex items-start gap-2">
            <span class="text-amber-500 flex-shrink-0"><AppIcon name="alert-circle" :size="16" /></span>
            <div class="text-xs text-ink-muted leading-relaxed">
              <b class="text-ink-light">거래 전 주의!</b> 해당 게시글은 회원이 등록한 것으로 AwesomeKorean은 등록된 내용에 대하여 일체의 책임을 지지 않습니다.
              직거래 시 안전한 장소에서 만나시고, 선입금 요구에 주의하세요.
            </div>
          </div>
        </div>

        <!-- 수정/삭제 -->
        <div class="flex items-center gap-3 justify-end">
          <button @click="$router.back()" class="btn-ghost text-sm"><AppIcon name="arrow-left" :size="14" />목록</button>
        </div>

        <!-- 거래 후기 -->
        <div v-if="item.reviews?.length || item.can_review" class="card p-4 space-y-3">
          <div class="flex items-center justify-between">
            <h2 class="font-bold text-sm text-ink flex items-center gap-1.5"><AppIcon name="star" :size="14" class="text-amber-500" />거래 후기</h2>
            <button v-if="item.can_review" @click="showReviewModal = true" class="text-xs text-blue-600 font-semibold hover:underline">후기 남기기</button>
          </div>
          <div v-if="!item.reviews?.length" class="text-xs text-ink-faint">아직 등록된 후기가 없습니다.</div>
          <div v-for="r in item.reviews" :key="r.id" class="border-t border-gray-50 pt-2 first:border-0 first:pt-0">
            <div class="flex items-center gap-1.5 text-xs font-bold text-ink">
              {{ r.reviewer?.nickname || r.reviewer?.name }}
              <span class="text-amber-500 inline-flex items-center gap-0.5"><AppIcon name="star" :size="11" />{{ r.rating }}</span>
            </div>
            <div v-if="r.comment" class="text-xs text-ink-muted mt-0.5">{{ r.comment }}</div>
          </div>
        </div>

        <CommentSection v-if="item.id" :type="'market'" :typeId="item.id" />
        <!-- 📱 모바일 상세 배너: 댓글 ↔ 페이지네이션 사이 (4광고 가중 랜덤) -->
        <MobileBanner page="market" class="lg:hidden" />
        <PostNavigator :prev-id="prev?.id" :prev-title="prev?.title"
          :next-id="next?.id" :next-title="next?.title"
          list-path="/market" detail-base="/market/" />
      </div>

      <!-- 오른쪽: 위젯 -->
      <div class="col-span-12 lg:col-span-3 hidden lg:block space-y-3">
        <SidebarWidgets mode="detail" :currentCategory="item?.category || ''" :categoryLabel="categoryLabel"
          api-url="/api/market" detail-path="/market/" :current-id="item.id" label="물품"
          :filter-params="item.lat && item.lng ? { lat: item.lat, lng: item.lng, radius: 50 } : {}" />
        <AdSlot page="market" position="right" :maxSlots="2" />
      </div>
    </div>
  </div>

  <!-- 라이트박스 -->
  <div v-if="lightboxImg" class="fixed inset-0 bg-black/95 z-50 flex flex-col items-center justify-center" @click.self="lightboxImg=null">
    <button class="absolute top-4 right-4 text-white hover:text-gray-300 z-10 transition-colors" @click="lightboxImg=null"><AppIcon name="x" :size="28" /></button>
    <img :src="lightboxImg" style="max-width:90vw;max-height:85vh;object-fit:contain;border-radius:8px;" />
  </div>

  <!-- 홀드 모달 -->
  <div v-if="showHoldModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" @click.self="showHoldModal=false">
    <div class="bg-white rounded-2xl p-5 w-full max-w-sm">
      <h3 class="font-bold text-lg text-ink mb-3 flex items-center gap-1.5"><AppIcon name="lock" :size="18" class="text-blue-500" />홀드 신청</h3>
      <div class="text-xs text-ink-muted mb-3">6시간당 <b class="text-amber-600">{{ item.hold_price_per_6h }}P</b> · 최대 {{ item.hold_max_hours }}시간</div>
      <div class="grid grid-cols-3 gap-2 mb-3">
        <button v-for="h in holdOptions" :key="h" @click="holdHours = h"
          :class="holdHours === h ? 'bg-blue-500 text-white' : 'bg-gray-100 text-ink-light'" class="py-2 rounded-lg text-sm font-bold transition-colors">
          {{ h >= 24 ? (h/24) + '일' : h + '시간' }}
        </button>
      </div>
      <div class="bg-blue-50 rounded-lg p-3 mb-4 text-center">
        <div class="text-2xl font-black text-blue-600">{{ holdCost }}P</div>
        <div class="text-xs text-ink-muted">판매자 {{ Math.floor(holdCost * 0.9) }}P · 수수료 {{ Math.ceil(holdCost * 0.1) }}P</div>
      </div>
      <div class="flex gap-2">
        <button @click="showHoldModal=false" class="btn-secondary flex-1 py-2 text-sm">취소</button>
        <button @click="submitHold" :disabled="holdingInProgress" class="flex-1 py-2 bg-blue-500 text-white rounded-lg text-sm font-bold disabled:opacity-50 hover:bg-blue-600 transition-colors">홀드 신청</button>
      </div>
    </div>
  </div>


  <!-- 거래 후기 모달 -->
  <div v-if="showReviewModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" @click.self="showReviewModal=false">
    <div class="bg-white rounded-2xl p-5 w-full max-w-sm">
      <h3 class="font-bold text-lg text-ink mb-3 flex items-center gap-1.5"><AppIcon name="star" :size="18" class="text-amber-500" />거래 후기 남기기</h3>
      <div class="flex gap-1 mb-3 justify-center">
        <button v-for="s in 5" :key="s" @click="reviewForm.rating = s" type="button" class="text-2xl leading-none" :class="s <= reviewForm.rating ? 'text-amber-400' : 'text-gray-200'">★</button>
      </div>
      <textarea v-model="reviewForm.comment" rows="3" maxlength="1000" placeholder="거래 경험을 남겨주세요 (선택)" class="input-soft text-sm mb-3"></textarea>
      <div class="flex gap-2">
        <button @click="showReviewModal=false" class="btn-secondary flex-1 py-2 text-sm">취소</button>
        <button @click="submitReview" :disabled="reviewSaving" class="flex-1 py-2 bg-amber-500 text-white rounded-lg text-sm font-bold disabled:opacity-50">등록</button>
      </div>
    </div>
  </div>

  <!-- 신고 모달 -->
  <ReportModal :show="showReport" reportableType="App\Models\MarketItem" :reportableId="item?.id"
    contentType="trade" @close="showReport=false" @reported="showReport=false" />

  <!-- 쪽지 모달 -->
  <MessageModal :show="msgModal" :userId="item?.user_id" :userName="item?.user?.nickname || item?.user?.name || ''"
    @close="msgModal=false" @sent="msgModal=false" />
</div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import { useSiteStore } from '../../stores/site'
import SidebarWidgets from '../../components/SidebarWidgets.vue'
import CommentSection from '../../components/CommentSection.vue'
import ReportModal from '../../components/ReportModal.vue'
import MessageModal from '../../components/MessageModal.vue'
import AdSlot from '../../components/AdSlot.vue'
import BookmarkToggle from '../../components/BookmarkToggle.vue'
import ShareButton from '../../components/ShareButton.vue'
import BoostButton from '../../components/BoostButton.vue'
import DetailHeader from '../../components/DetailHeader.vue'
import PostNavigator from '../../components/PostNavigator.vue'
import MobileBanner from '../../components/MobileBanner.vue'
import AppIcon from '../../components/AppIcon.vue'
import { useFriendAction, useBookmarkLike } from '../../composables/useSocialActions'
import { useBookmarkStore } from '../../stores/bookmarks'
import axios from 'axios'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const siteStore = useSiteStore()
const bStore = useBookmarkStore()
const BM_TYPE = 'App\\Models\\MarketItem'
const favCount = computed(() => bStore.getBookmarkedIds(BM_TYPE).length)
const item = ref(null)
const loading = ref(true)
const selectedImgIdx = ref(0)
const lightboxImg = ref(null)
const sellerTradeCount = ref(0)

const categories = [
  { value: '', label: '전체' },
  { value: 'electronics', label: '📱 전자기기' }, { value: 'furniture', label: '🪑 가구' },
  { value: 'clothing', label: '👕 의류' }, { value: 'auto', label: '🚗 자동차' },
  { value: 'baby', label: '👶 유아' }, { value: 'sports', label: '⚽ 스포츠' },
  { value: 'books', label: '📚 도서' }, { value: 'etc', label: '📋 기타' },
]

const isOwner = computed(() => item.value && String(item.value.user_id) === String(auth.user?.id))
const isActiveBuyer = computed(() => item.value?.active_hold && String(item.value.active_hold.buyer_id) === String(auth.user?.id))
const conditionLabel = computed(() => ({new:'새상품',like_new:'거의 새것',good:'양호',fair:'보통'})[item.value?.condition] || '')
const categoryLabel = computed(() => categories.find(c => c.value === item.value?.category)?.label || '전체')
const mainImage = computed(() => {
  if (!item.value?.images?.length) return null
  return getImageUrl(item.value.images[selectedImgIdx.value] || item.value.images[0])
})
const promoBorderStyle = computed(() => {
  const t = item.value?.promotion_tier
  if (t === 'national') return 'border: 2px solid #fca5a5; border-radius: 12px;'
  if (t === 'state_plus') return 'border: 2px solid #93c5fd; border-radius: 12px;'
  if (t === 'sponsored') return 'border: 2px solid #fde68a; border-radius: 12px;'
  if (isBoosted.value) return 'border: 2px solid #c084fc; border-radius: 12px; box-shadow: 0 0 0 3px rgba(192,132,252,0.15);'
  return 'border: 1px solid #e5e7eb; border-radius: 12px;'
})

// Boost (legacy boosted_until) 활성 여부 + 남은 시간
const isBoosted = computed(() => {
  const u = item.value?.boosted_until
  return u && new Date(u) > new Date()
})
const boostRemaining = computed(() => {
  if (!isBoosted.value) return ''
  const diff = new Date(item.value.boosted_until) - new Date()
  const h = Math.floor(diff / 3_600_000)
  const d = Math.floor(h / 24)
  const remH = h - d * 24
  if (d >= 1) return `${d}일 ${remH}시간`
  const m = Math.floor(diff / 60_000) - h * 60
  return `${h}시간 ${m}분`
})

function getImageUrl(img) {
  if (!img) return ''
  return img.startsWith('http') || img.startsWith('/') ? img : '/storage/' + img
}

// 홀드
const showHoldModal = ref(false)
const holdHours = ref(6)
const holdingInProgress = ref(false)
const holdOptions = computed(() => [6, 12, 24, 48, 72, 168].filter(h => h <= (item.value?.hold_max_hours || 24)))
const holdCost = computed(() => Math.ceil(holdHours.value / 6) * (item.value?.hold_price_per_6h || 0))

async function submitHold() {
  if (!confirm(`${holdHours.value}시간 홀드에 ${holdCost.value}P 차감됩니다.`)) return
  holdingInProgress.value = true
  try {
    const { data } = await axios.post(`/api/market/${item.value.id}/hold`, { hours: holdHours.value })
    siteStore.toast(data.message, 'success'); showHoldModal.value = false; loadItem()
  } catch (e) { siteStore.toast(e.response?.data?.message || '홀드 실패', 'error') }
  holdingInProgress.value = false
}


// 실시간 채팅 (판매자와 DM 채팅방 생성/재사용 후 이동)
const chatStarting = ref(false)
async function startChat() {
  if (!item.value?.user_id) return
  chatStarting.value = true
  try {
    const { data } = await axios.post('/api/chat/rooms', { type: 'dm', user_id: item.value.user_id })
    router.push(`/chat/${data.data.id}`)
  } catch (e) { siteStore.toast(e.response?.data?.message || '채팅방을 열 수 없습니다', 'error') }
  chatStarting.value = false
}

// 거래 약속 시간/장소
const showMeetupForm = ref(false)
const meetupForm = ref({ at: '', place: '' })
const meetupSaving = ref(false)
function openMeetupForm() {
  const h = item.value?.active_hold
  meetupForm.value = {
    at: h?.meetup_at ? new Date(h.meetup_at).toISOString().slice(0, 16) : '',
    place: h?.meetup_place || '',
  }
  showMeetupForm.value = true
}
async function submitMeetup() {
  meetupSaving.value = true
  try {
    const { data } = await axios.post(`/api/market/${item.value.id}/hold/meetup`, {
      meetup_at: meetupForm.value.at || null,
      meetup_place: meetupForm.value.place || null,
    })
    siteStore.toast(data.message, 'success'); showMeetupForm.value = false; loadItem()
  } catch (e) { siteStore.toast(e.response?.data?.message || '저장 실패', 'error') }
  meetupSaving.value = false
}

// 거래완료 (판매자)
const completingHold = ref(false)
async function submitCompleteHold() {
  if (!confirm('거래를 완료 처리하시겠습니까? 완료 후에는 물품이 판매완료로 전환되고 서로 후기를 남길 수 있습니다.')) return
  completingHold.value = true
  try {
    const { data } = await axios.post(`/api/market/${item.value.id}/hold/complete`)
    siteStore.toast(data.message, 'success'); loadItem()
  } catch (e) { siteStore.toast(e.response?.data?.message || '처리 실패', 'error') }
  completingHold.value = false
}

// 거래 후기
const showReviewModal = ref(false)
const reviewForm = ref({ rating: 5, comment: '' })
const reviewSaving = ref(false)
async function submitReview() {
  reviewSaving.value = true
  try {
    const { data } = await axios.post(`/api/market/${item.value.id}/review`, reviewForm.value)
    siteStore.toast(data.message, 'success'); showReviewModal.value = false
    reviewForm.value = { rating: 5, comment: '' }
    loadItem()
  } catch (e) { siteStore.toast(e.response?.data?.message || '등록 실패', 'error') }
  reviewSaving.value = false
}

// 좋아요 (Bookmark API — bStore 동기화)
const { liked, check: checkLike, toggle: doToggleLike } = useBookmarkLike(BM_TYPE)
async function toggleLike() {
  const result = await bStore.toggle(BM_TYPE, item.value.id)
  if (result !== null) liked.value = result
}

// 친구 요청
const { sendRequest: doSendFriend } = useFriendAction()
async function addFriend() { await doSendFriend(item.value.user_id) }

// 쪽지
const msgModal = ref(false)
function sendMessage() { msgModal.value = true }

// 신고
const showReport = ref(false)
async function deleteItem() {
  if (!confirm('정말 삭제하시겠습니까?')) return
  try { await axios.delete(`/api/market/${item.value.id}`); router.push('/market') } catch {}
}

function formatDateTime(dt) { if (!dt) return ''; const d = new Date(dt); return d.toLocaleDateString('ko-KR') + ' ' + d.toLocaleTimeString('ko-KR', {hour:'2-digit',minute:'2-digit'}) }
function formatFullDate(dt) {
  if (!dt) return ''
  const d = new Date(dt)
  return `${d.getFullYear()}.${d.getMonth()+1}.${d.getDate()}`
}
function timeAgo(dt) {
  if (!dt) return ''; const s = (Date.now() - new Date(dt)) / 1000
  if (s < 3600) return Math.floor(s/60) + '분 전'; if (s < 86400) return Math.floor(s/3600) + '시간 전'
  if (s < 604800) return Math.floor(s/86400) + '일 전'; return new Date(dt).toLocaleDateString('ko-KR')
}

const prev = ref(null)
const next = ref(null)

async function loadItem() {
  try {
    const { data } = await axios.get(`/api/market/${route.params.id}`)
    item.value = data.data
    prev.value = data.prev
    next.value = data.next
    try {
      const { data: trades } = await axios.get(`/api/market?user_id=${item.value.user_id}&per_page=1`)
      sellerTradeCount.value = trades.data?.total || 0
    } catch {}
  } catch (err) {
    // Issue #17: 존재하지 않는 id → /404
    if (err.response?.status === 404) {
      router.replace('/404')
    } else {
      siteStore.toast?.('페이지를 불러올 수 없습니다', 'error')
    }
  }
}

onMounted(async () => {
  bStore.loadAll()
  await loadItem()
  loading.value = false
  if (item.value?.id) checkLike(item.value.id)
})
</script>
