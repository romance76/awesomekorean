<template>
<div class="min-h-screen bg-gray-50">
  <div class="max-w-7xl mx-auto px-4 py-5">
    <router-link to="/groupbuy" class="text-xl font-black text-gray-800 mb-3 inline-block hover:text-amber-600 transition">
      🤝 공동구매
    </router-link>

    <div v-if="loading" class="text-center py-20 text-gray-400">로딩중...</div>

    <div v-else-if="gb" class="grid grid-cols-12 gap-4">

      <!-- 왼쪽: 상태 필터 -->
      <div class="col-span-12 lg:col-span-2 hidden lg:block">
        <div class="sticky top-20 max-h-[calc(100vh-6rem)] overflow-y-auto space-y-3 pr-0.5">
          <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-3 py-2.5 border-b font-bold text-xs text-amber-900">📋 상태</div>
            <RouterLink to="/groupbuy" class="block w-full text-left px-3 py-2 text-xs transition text-gray-600 hover:bg-amber-50/50">전체</RouterLink>
            <RouterLink to="/groupbuy?status=recruiting" class="block w-full text-left px-3 py-2 text-xs transition"
              :class="gb.status==='recruiting' ? 'bg-amber-50 text-amber-700 font-bold' : 'text-gray-600 hover:bg-amber-50/50'">🟢 모집중</RouterLink>
            <RouterLink to="/groupbuy?status=confirmed" class="block w-full text-left px-3 py-2 text-xs transition"
              :class="gb.status==='confirmed' ? 'bg-amber-50 text-amber-700 font-bold' : 'text-gray-600 hover:bg-amber-50/50'">🔵 확정</RouterLink>
            <RouterLink to="/groupbuy?status=completed" class="block w-full text-left px-3 py-2 text-xs transition"
              :class="gb.status==='completed' ? 'bg-amber-50 text-amber-700 font-bold' : 'text-gray-600 hover:bg-amber-50/50'">✅ 완료</RouterLink>
            <button v-if="auth.isLoggedIn" @click="$router.push('/groupbuy?fav=1')"
              class="w-full text-left px-3 py-2 text-xs transition border-t text-gray-600 hover:bg-red-50/50">
              🔖 내 북마크<span v-if="gbFavCount > 0" class="ml-0.5">({{ gbFavCount }})</span>
            </button>
          </div>
          <AdSlot page="groupbuy" position="left" :maxSlots="2" />
        </div>
      </div>

      <!-- ══════════ CENTER: Detail ══════════ -->
      <main class="col-span-12 lg:col-span-7 md:col-span-7" :class="gb.status === 'completed' || gb.status === 'cancelled' ? 'opacity-60' : ''">
        <div class="bg-white rounded-xl shadow-sm border overflow-hidden" :class="gb.status === 'completed' || gb.status === 'cancelled' ? 'border-gray-300' : 'border-gray-100'">

          <!-- Header: status + title + organizer -->
          <div class="px-3 lg:px-5 py-3 lg:py-4 border-b border-gray-100">
            <div class="flex items-center gap-2 flex-wrap mb-2">
              <span class="text-xs px-2.5 py-0.5 rounded-full font-bold" :class="statusClass(gb.status)">
                {{ statusLabel(gb.status) }}
              </span>
              <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-600 font-medium">
                {{ categoryLabel(gb.category) }}
              </span>
              <span v-if="myParticipation" class="text-xs px-2.5 py-0.5 rounded-full font-bold bg-amber-400 text-amber-900">🙋 참여중</span>
            </div>
            <div class="flex items-center gap-2">
              <h1 class="text-xl lg:text-2xl font-bold text-gray-900 leading-snug flex-1">{{ gb.title }}</h1>
              <BookmarkToggle v-if="auth.isLoggedIn" :active="gbFavorited" @toggle="toggleGbFav" size="lg" />
            </div>
            <div class="text-xs lg:text-sm text-gray-500 mt-1.5 flex items-center gap-2 flex-wrap">
              <span class="flex items-center gap-1">
                주최:
                <UserName v-if="gb.user?.id" :userId="gb.user.id" :name="gb.user.nickname || gb.user.name" className="text-amber-700 font-semibold" />
                <span v-else class="text-amber-700 font-semibold">{{ gb.user?.name || '알 수 없음' }}</span>
              </span>
              <span v-if="gb.city" class="text-gray-400">📍 {{ gb.city }}<span v-if="gb.state">, {{ gb.state }}</span></span>
              <span class="text-gray-300">|</span>
              <span>{{ formatDate(gb.created_at) }}</span>
              <span class="text-gray-400 ml-auto text-xs">{{ gb.view_count || 0 }}회 조회</span>
            </div>
          </div>

          <!-- Image gallery -->
          <div v-if="gb.images?.length" class="bg-gray-50 p-3">
            <div class="rounded-lg overflow-hidden bg-white flex items-center justify-center cursor-pointer mb-2" style="height: 360px;" @click="lightboxImg = mainImage">
              <img v-if="mainImage" :src="mainImage" class="max-w-full max-h-full object-contain" />
            </div>
            <div v-if="gb.images.length > 1" class="flex gap-2 overflow-x-auto">
              <div v-for="(img, i) in gb.images" :key="i" @click="selectedImgIdx = i"
                class="w-16 h-16 rounded-lg overflow-hidden border-2 cursor-pointer flex-shrink-0"
                :class="selectedImgIdx === i ? 'border-amber-400' : 'border-gray-200'">
                <img :src="getImageUrl(img)" class="w-full h-full object-cover" />
              </div>
            </div>
          </div>

          <!-- Price info box -->
          <div class="mx-3 lg:mx-5 my-3 bg-amber-50 border border-amber-200 rounded-lg px-4 py-3">
            <div class="flex items-center gap-3 flex-wrap">
              <div v-if="gb.original_price" class="text-gray-400 line-through text-sm">${{ Number(gb.original_price).toLocaleString() }}</div>
              <div class="text-2xl font-black text-amber-600">${{ Number(currentPrice).toLocaleString() }}</div>
              <div v-if="currentDiscount > 0" class="bg-red-500 text-white text-xs font-bold px-2 py-0.5 rounded-full">-{{ currentDiscount }}%</div>
            </div>
            <div v-if="currentTier" class="text-xs text-amber-700 mt-1.5 font-semibold">
              현재 {{ gb.participant_count || 0 }}명 참여 → {{ currentTier.discount_pct }}% 할인
            </div>

            <!-- Tier progress visualization -->
            <div v-if="sortedTiers.length" class="mt-3">
              <div class="relative h-2.5 bg-gray-200 rounded-full overflow-hidden">
                <div class="absolute inset-y-0 left-0 bg-gradient-to-r from-amber-400 to-amber-500 rounded-full transition-all duration-500"
                  :style="{ width: tierProgressPct + '%' }"></div>
              </div>
              <div class="flex justify-between mt-1">
                <div v-for="(tier, idx) in sortedTiers" :key="idx"
                  class="text-[10px] font-semibold"
                  :class="(gb.participant_count || 0) >= tier.min_people ? 'text-amber-600' : 'text-gray-400'">
                  {{ tier.min_people }}명 ({{ tier.discount_pct }}%)
                </div>
              </div>
            </div>
          </div>

          <!-- Progress section -->
          <div class="mx-3 lg:mx-5 my-3 bg-blue-50 border border-blue-200 rounded-lg px-4 py-3">
            <div class="flex items-center justify-between mb-2">
              <span class="text-sm font-bold text-blue-800">참여 현황</span>
              <span class="text-sm font-bold text-blue-600">
                {{ gb.participant_count || 0 }}명 / {{ gb.max_participants || gb.min_participants }}명
                <span class="text-xs text-blue-400 font-normal">({{ progressPct }}% 달성)</span>
              </span>
            </div>
            <div class="relative h-4 bg-blue-100 rounded-full overflow-hidden">
              <div class="absolute inset-y-0 left-0 bg-gradient-to-r from-blue-400 to-blue-500 rounded-full transition-all duration-500"
                :style="{ width: Math.min(progressPct, 100) + '%' }"></div>
            </div>
            <div v-if="timeRemaining" class="text-xs text-blue-600 mt-2 font-semibold">
              ⏰ {{ timeRemaining }}
            </div>
            <div class="text-[10px] text-gray-400 mt-1">
              최소 {{ gb.min_participants }}명
              <span v-if="gb.max_participants"> · 최대 {{ gb.max_participants }}명</span>
            </div>
          </div>

          <!-- Discount tiers table -->
          <div v-if="sortedTiers.length" class="mx-3 lg:mx-5 my-3">
            <h3 class="text-sm font-bold text-gray-800 mb-2">할인 티어</h3>
            <div class="border border-gray-200 rounded-lg overflow-hidden">
              <table class="w-full text-sm">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="text-left px-3 py-2 text-xs font-semibold text-gray-600">최소 인원</th>
                    <th class="text-left px-3 py-2 text-xs font-semibold text-gray-600">할인율</th>
                    <th class="text-right px-3 py-2 text-xs font-semibold text-gray-600">예상 가격</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                  <tr v-for="(tier, idx) in sortedTiers" :key="idx"
                    :class="currentTier && currentTier.min_people === tier.min_people ? 'bg-amber-50 font-semibold' : ''">
                    <td class="px-3 py-2 text-xs">
                      {{ tier.min_people }}명 이상
                      <span v-if="currentTier && currentTier.min_people === tier.min_people"
                        class="ml-1 text-[10px] bg-amber-400 text-amber-900 px-1.5 py-0.5 rounded-full">현재</span>
                    </td>
                    <td class="px-3 py-2 text-xs text-red-500 font-bold">{{ tier.discount_pct }}%</td>
                    <td class="px-3 py-2 text-xs text-right text-amber-600 font-bold">
                      ${{ tierPrice(tier).toLocaleString() }}
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Content -->
          <div class="px-3 lg:px-5 py-3 lg:py-5 border-t border-gray-100">
            <h3 class="text-sm font-bold text-gray-800 mb-2">상세 설명</h3>
            <div class="text-xs lg:text-sm text-gray-700 leading-relaxed whitespace-pre-wrap">{{ gb.content }}</div>
            <a v-if="gb.product_url" :href="gb.product_url" target="_blank" rel="noopener"
              class="inline-flex items-center gap-1 mt-3 text-xs text-blue-600 hover:text-blue-800 font-semibold">
              🔗 상품 링크 보기
            </a>
          </div>

          <!-- Action buttons -->
          <div class="px-3 lg:px-5 py-3 border-t border-gray-100 bg-gray-50/50">
            <div class="flex items-center gap-3 flex-wrap">
              <!-- 참여하기 -->
              <button v-if="canJoin"
                @click="showJoinModal = true"
                class="inline-flex items-center gap-1.5 bg-amber-500 hover:bg-amber-600 text-white font-bold text-sm px-6 py-2.5 rounded-lg transition">
                🤝 참여하기
              </button>
              <!-- 참여 취소 -->
              <button v-if="myParticipation"
                @click="cancelParticipation"
                class="inline-flex items-center gap-1.5 bg-red-100 hover:bg-red-200 text-red-700 font-bold text-sm px-5 py-2.5 rounded-lg transition">
                참여 취소
              </button>
              <!-- 이미 참여 표시 (버튼 모양으로) -->
              <span v-if="myParticipation" class="inline-flex items-center gap-1.5 bg-green-500 text-white font-bold text-sm px-6 py-2.5 rounded-lg">
                ✅ 참여중
              </span>
              <!-- 승인 대기 -->
              <span v-if="gb.status === 'pending'" class="text-xs text-gray-500 font-medium">
                관리자 승인 대기중입니다
              </span>
            </div>

            <!-- Owner actions -->
            <div v-if="isOwner" class="flex items-center gap-3 mt-2 pt-2 border-t border-gray-200">
              <button @click="deleteGb" class="text-xs text-red-400 hover:text-red-600 font-medium">삭제</button>
            </div>
          </div>

          <!-- Participants -->
          <div v-if="participants.length" class="px-3 lg:px-5 py-3 border-t border-gray-100">
            <h3 class="text-sm font-bold text-gray-800 mb-3">참여자 ({{ participants.length }}명)</h3>
            <div class="flex flex-wrap gap-3">
              <div v-for="p in participants" :key="p.id" class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-full bg-amber-100 flex items-center justify-center text-xs font-bold text-amber-700 overflow-hidden flex-shrink-0">
                  <img v-if="p.user?.avatar" :src="'/storage/' + p.user.avatar" class="w-full h-full object-cover" @error="e => e.target.style.display='none'" />
                  <span v-else>{{ (p.user?.nickname || p.user?.name || '?')[0] }}</span>
                </div>
                <span class="text-xs text-gray-700 font-medium">{{ p.user?.nickname || p.user?.name }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- 주최자 + 공동구매 정보 (본문 안에 2열) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-4">
          <!-- 주최자 정보 -->
          <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
            <div class="text-xs font-bold text-gray-500 mb-3">주최자 정보</div>
            <div class="flex items-center gap-3">
              <div class="w-12 h-12 rounded-full bg-amber-100 flex items-center justify-center text-lg font-bold text-amber-700 overflow-hidden flex-shrink-0">
                <img v-if="gb.user?.avatar" :src="'/storage/' + gb.user.avatar" class="w-full h-full object-cover" @error="e => e.target.style.display='none'" />
                <span v-else>{{ (gb.user?.name || '?')[0] }}</span>
              </div>
              <div class="flex-1">
                <div class="font-bold text-gray-800 text-sm">{{ gb.user?.nickname || gb.user?.name }}</div>
                <div class="text-[10px] text-gray-400 mt-0.5">가입: {{ formatDate(gb.user?.created_at) }}</div>
              </div>
            </div>
            <div v-if="auth.isLoggedIn && !isOwner" class="flex gap-2 mt-3">
              <button @click="doAddFriend" :disabled="friendLoading" class="flex-1 bg-blue-50 text-blue-700 border border-blue-200 font-bold py-2 rounded-lg text-xs hover:bg-blue-100 disabled:opacity-50">👋 친구추가</button>
              <button @click="msgModal=true" class="flex-1 bg-amber-50 text-amber-700 border border-amber-200 font-bold py-2 rounded-lg text-xs hover:bg-amber-100">✉️ 쪽지</button>
              <button @click="reportShow=true" class="px-3 py-2 rounded-lg border border-gray-200 text-gray-400 text-xs hover:text-red-500 hover:border-red-200">🚨</button>
            </div>
          </div>

          <!-- 공동구매 정보 -->
          <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
            <div class="text-xs font-bold text-gray-500 mb-3">공동구매 정보</div>
            <div class="space-y-2 text-xs">
              <div class="flex justify-between">
                <span class="text-gray-500">종료 방식</span>
                <span class="text-gray-800 font-semibold">{{ endTypeLabel(gb.end_type) }}</span>
              </div>
              <div v-if="gb.deadline" class="flex justify-between">
                <span class="text-gray-500">마감일</span>
                <span class="text-gray-800 font-semibold">{{ formatDate(gb.deadline) }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-500">최소 인원</span>
                <span class="text-gray-800 font-semibold">{{ gb.min_participants }}명</span>
              </div>
              <div v-if="gb.max_participants" class="flex justify-between">
                <span class="text-gray-500">최대 인원</span>
                <span class="text-gray-800 font-semibold">{{ gb.max_participants }}명</span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-500">현재 참여</span>
                <span class="text-amber-600 font-bold">{{ gb.participant_count || 0 }}명</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Comments -->
        <CommentSection v-if="gb.id" type="groupbuy" :typeId="gb.id" class="mt-4" />

        <!-- Back to list -->
        <div class="flex justify-center mt-4">
          <router-link to="/groupbuy" class="text-xs font-bold text-amber-700 hover:text-amber-500 bg-white rounded-xl px-6 py-3 border shadow-sm">
            📋 목록으로
          </router-link>
        </div>
      </main>

      <!-- ══════════ RIGHT: Sidebar ══════════ -->
      <aside class="col-span-4 md:col-span-4 lg:col-span-3 hidden md:block">
        <div class="space-y-3 sticky top-20">
          <SidebarWidgets mode="detail" :currentCategory="gb?.category || ''" api-url="/api/groupbuys" detail-path="/groupbuy/" :current-id="gb.id" label="공동구매"
            :filter-params="gb.lat && gb.lng ? { lat: gb.lat, lng: gb.lng, radius: 50 } : {}" />
          <AdSlot page="groupbuy" position="right" :maxSlots="2" />
        </div>
      </aside>
    </div>

    <!-- Not found -->
    <div v-else class="text-center py-20">
      <div class="text-4xl mb-3">🤝</div>
      <div class="text-gray-500 font-semibold">공동구매를 찾을 수 없습니다</div>
    </div>
  </div>

  <!-- Lightbox -->
  <div v-if="lightboxImg" class="fixed inset-0 bg-black/90 z-50 flex items-center justify-center p-4" @click="lightboxImg = null">
    <button @click="lightboxImg = null" class="absolute top-4 right-4 text-white text-3xl">✕</button>
    <img :src="lightboxImg" class="max-w-full max-h-[90vh] rounded-lg" @click.stop />
  </div>

  <!-- Join/Payment modal -->
  <Teleport to="body">
    <div v-if="showJoinModal" class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/40" @click.self="showJoinModal = false">
      <div class="bg-white rounded-2xl shadow-2xl w-[90vw] max-w-md mx-4 overflow-hidden">
        <!-- Modal header -->
        <div class="px-5 py-4 border-b bg-amber-50">
          <div class="flex items-center justify-between">
            <h3 class="text-base font-bold text-gray-900">🤝 참여하기</h3>
            <button @click="showJoinModal = false" class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
          </div>
          <p class="text-xs text-gray-500 mt-1">{{ gb.title }}</p>
        </div>

        <!-- Not logged in -->
        <div v-if="!auth.isLoggedIn" class="p-5 text-center">
          <div class="text-3xl mb-3">🔒</div>
          <p class="text-sm text-gray-600 mb-4">참여하려면 로그인이 필요합니다</p>
          <router-link to="/login" @click="showJoinModal = false"
            class="inline-block bg-amber-500 text-white font-bold text-sm px-6 py-2.5 rounded-lg hover:bg-amber-600 transition">
            로그인하기
          </router-link>
        </div>

        <!-- Logged in -->
        <div v-else class="p-5 space-y-4">
          <!-- Price summary -->
          <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 text-center">
            <div class="text-sm text-gray-600">참여 가격</div>
            <div class="text-2xl font-black text-amber-600">${{ Number(currentPrice).toLocaleString() }}</div>
            <div v-if="currentDiscount > 0" class="text-xs text-red-500 font-semibold">{{ currentDiscount }}% 할인 적용</div>
          </div>

          <!-- Quantity -->
          <div>
            <label class="text-sm font-semibold text-gray-700 mb-1 block">수량</label>
            <input v-model.number="joinQuantity" type="number" min="1" max="10"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-400 outline-none" />
          </div>

          <!-- Payment method -->
          <div v-if="gb.payment_method !== 'none'">
            <label class="text-sm font-semibold text-gray-700 mb-2 block">결제 방법</label>

            <!-- point only -->
            <div v-if="gb.payment_method === 'point'" class="space-y-2">
              <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
                <div class="flex items-center justify-between">
                  <span class="text-sm text-gray-700">내 포인트</span>
                  <span class="text-sm font-bold text-blue-600">{{ auth.user?.points?.toLocaleString() || 0 }}P</span>
                </div>
                <div class="text-xs text-gray-400 mt-1">
                  차감 예정: {{ (currentPrice * 100 * joinQuantity).toLocaleString() }}P
                </div>
              </div>
            </div>

            <!-- stripe only -->
            <div v-else-if="gb.payment_method === 'stripe'" class="space-y-2">
              <div class="bg-gray-50 border border-gray-200 rounded-lg p-3 text-center text-sm text-gray-500">
                💳 카드 결제 (Stripe)
                <div class="text-[10px] text-gray-400 mt-1">참여 확정 후 결제가 진행됩니다</div>
              </div>
            </div>

            <!-- both -->
            <div v-else-if="gb.payment_method === 'both'" class="space-y-2">
              <label class="flex items-center gap-2 p-3 rounded-lg border cursor-pointer transition"
                :class="joinPaymentType === 'point' ? 'border-amber-400 bg-amber-50' : 'border-gray-200 hover:bg-gray-50'">
                <input v-model="joinPaymentType" type="radio" value="point" class="accent-amber-500" />
                <div>
                  <div class="text-sm font-semibold text-gray-800">포인트 결제</div>
                  <div class="text-xs text-gray-400">보유: {{ auth.user?.points?.toLocaleString() || 0 }}P</div>
                </div>
              </label>
              <label class="flex items-center gap-2 p-3 rounded-lg border cursor-pointer transition"
                :class="joinPaymentType === 'stripe' ? 'border-amber-400 bg-amber-50' : 'border-gray-200 hover:bg-gray-50'">
                <input v-model="joinPaymentType" type="radio" value="stripe" class="accent-amber-500" />
                <div>
                  <div class="text-sm font-semibold text-gray-800">💳 카드 결제</div>
                  <div class="text-xs text-gray-400">Stripe 결제</div>
                </div>
              </label>
            </div>
          </div>

          <!-- No payment needed -->
          <div v-if="!gb.payment_method || gb.payment_method === 'none'" class="bg-green-50 border border-green-200 rounded-lg p-3 text-center">
            <div class="text-sm text-green-700 font-semibold">결제 없이 참여 가능합니다</div>
          </div>

          <!-- Submit join -->
          <button @click="submitJoin" :disabled="joining"
            class="w-full bg-amber-500 hover:bg-amber-600 text-white font-bold text-sm py-3 rounded-lg transition disabled:opacity-50">
            {{ joining ? '참여 처리 중...' : '참여 확정' }}
          </button>

          <!-- Join done -->
          <div v-if="joinDone" class="bg-green-50 border border-green-200 rounded-lg p-3 text-center">
            <span class="text-green-700 text-sm font-medium">✅ 참여가 완료되었습니다!</span>
          </div>
        </div>
      </div>
    </div>
  </Teleport>

  <!-- 쪽지 모달 -->
  <MessageModal :show="msgModal" :userId="gb?.user_id" :userName="gb?.user?.nickname || gb?.user?.name || ''"
    @close="msgModal=false" @sent="msgModal=false" />

  <!-- 신고 모달 -->
  <ReportModal :show="reportShow" reportableType="App\Models\GroupBuy" :reportableId="gb?.id"
    contentType="trade" @close="reportShow=false" @reported="reportShow=false" />
</div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import { useBookmarkStore } from '../../stores/bookmarks'
import CommentSection from '../../components/CommentSection.vue'
import SidebarWidgets from '../../components/SidebarWidgets.vue'
import AdSlot from '../../components/AdSlot.vue'
import ReportModal from '../../components/ReportModal.vue'
import MessageModal from '../../components/MessageModal.vue'
import { useFriendAction } from '../../composables/useSocialActions'
import BookmarkToggle from '../../components/BookmarkToggle.vue'
import axios from 'axios'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const gbStore = useBookmarkStore()
const gb = ref(null)
const loading = ref(true)
const gbFavorited = ref(false)
const gbFavCount = computed(() => gbStore.getBookmarkedIds('App\\Models\\GroupBuy').length)
const participants = ref([])
const selectedImgIdx = ref(0)
const lightboxImg = ref(null)

// Join modal state
const showJoinModal = ref(false)
const joinQuantity = ref(1)
const joinPaymentType = ref('point')
const joining = ref(false)
const joinDone = ref(false)

// Countdown timer
const now = ref(Date.now())
let countdownTimer = null

// ── Computed helpers ──

const isOwner = computed(() => gb.value && String(gb.value.user_id) === String(auth.user?.id))

const myParticipation = computed(() => {
  if (!auth.user || !gb.value) return null
  return gb.value.my_participation || null
})

const canJoin = computed(() => {
  if (!gb.value) return false
  return gb.value.status === 'recruiting' && gb.value.is_approved && !myParticipation.value && !isOwner.value
})

const progressPct = computed(() => {
  if (!gb.value) return 0
  const target = gb.value.max_participants || gb.value.min_participants
  if (!target) return 0
  return Math.round(((gb.value.participant_count || 0) / target) * 100)
})

const sortedTiers = computed(() => {
  if (!gb.value?.discount_tiers?.length) return []
  return [...gb.value.discount_tiers].sort((a, b) => a.min_people - b.min_people)
})

const currentTier = computed(() => {
  if (!sortedTiers.value.length || !gb.value) return null
  const count = gb.value.participant_count || 0
  let active = null
  for (const tier of sortedTiers.value) {
    if (count >= tier.min_people) active = tier
  }
  return active
})

const currentDiscount = computed(() => {
  if (gb.value?.current_discount) return gb.value.current_discount
  return currentTier.value?.discount_pct || 0
})

const currentPrice = computed(() => {
  if (gb.value?.current_price) return gb.value.current_price
  const base = gb.value?.group_price || gb.value?.original_price || 0
  if (currentDiscount.value > 0) {
    return Math.round(base * (1 - currentDiscount.value / 100))
  }
  return base
})

const tierProgressPct = computed(() => {
  if (!sortedTiers.value.length || !gb.value) return 0
  const maxTier = sortedTiers.value[sortedTiers.value.length - 1]
  const count = gb.value.participant_count || 0
  return Math.min(100, Math.round((count / maxTier.min_people) * 100))
})

const timeRemaining = computed(() => {
  if (!gb.value?.deadline) return ''
  const deadline = new Date(gb.value.deadline).getTime()
  const diff = deadline - now.value
  if (diff <= 0) return '마감됨'
  const days = Math.floor(diff / (1000 * 60 * 60 * 24))
  const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60))
  const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60))
  if (days > 0) return `${days}일 ${hours}시간 남음`
  if (hours > 0) return `${hours}시간 ${minutes}분 남음`
  return `${minutes}분 남음`
})

const mainImage = computed(() => {
  if (!gb.value?.images?.length) return null
  return getImageUrl(gb.value.images[selectedImgIdx.value] || gb.value.images[0])
})

// ── Category / Status helpers ──

const categoryMap = {
  food: '🍱 식품', beauty: '💄 뷰티', electronics: '📱 전자제품', living: '🏠 생활용품',
  fashion: '👗 패션', health: '💊 건강', education: '📚 교육', etc: '📋 기타',
}

function categoryLabel(cat) {
  return categoryMap[cat] || cat || '기타'
}

function statusLabel(s) {
  return { recruiting: '모집중', confirmed: '확정', completed: '완료', cancelled: '취소', pending: '승인대기' }[s] || s
}

function statusClass(s) {
  return {
    recruiting: 'bg-green-100 text-green-700',
    confirmed: 'bg-blue-100 text-blue-700',
    completed: 'bg-gray-200 text-gray-600',
    cancelled: 'bg-red-100 text-red-600',
    pending: 'bg-yellow-100 text-yellow-700',
  }[s] || 'bg-gray-100 text-gray-700'
}

function endTypeLabel(t) {
  return {
    target_met: '목표 인원 달성 시',
    time_limit: '기한 종료 시 진행',
    flexible: '최소 미달 시 취소',
  }[t] || t || '-'
}

// ── Utility helpers ──

function getImageUrl(img) {
  if (!img) return ''
  return img.startsWith('http') || img.startsWith('/') ? img : '/storage/' + img
}

function formatDate(dt) {
  if (!dt) return ''
  const d = new Date(dt)
  return `${d.getFullYear()}년 ${d.getMonth() + 1}월 ${d.getDate()}일`
}

function tierPrice(tier) {
  const base = gb.value?.group_price || gb.value?.original_price || 0
  return Math.round(base * (1 - tier.discount_pct / 100))
}

// ── API calls ──

async function loadDetail(id) {
  loading.value = true
  gb.value = null
  participants.value = []

  try {
    const { data } = await axios.get(`/api/groupbuys/${id}`)
    gb.value = data.data

    // Load participants
    try {
      const { data: pData } = await axios.get(`/api/groupbuys/${id}/participants`)
      participants.value = pData.data || []
    } catch {}
  } catch (err) {
    gb.value = null
    if (err.response?.status === 404) router.replace('/404')
  }

  loading.value = false
  // 하트 체크
  if (auth.isLoggedIn && gb.value) {
    try {
      const { data: bData } = await axios.get('/api/bookmarks/check', { params: { type: 'App\\Models\\GroupBuy', ids: gb.value.id } })
      gbFavorited.value = (bData.data || []).includes(gb.value.id)
    } catch {}
  }
}

async function toggleGbFav() {
  if (!auth.isLoggedIn || !gb.value) return
  const bStore = useBookmarkStore()
  const result = await bStore.toggle('App\\Models\\GroupBuy', gb.value.id)
  if (result !== null) gbFavorited.value = result
}

async function submitJoin() {
  if (!gb.value || joining.value) return
  joining.value = true
  joinDone.value = false

  try {
    const payload = { quantity: joinQuantity.value }
    if (gb.value.payment_method && gb.value.payment_method !== 'none') {
      payload.payment_type = gb.value.payment_method === 'both' ? joinPaymentType.value : gb.value.payment_method
    }

    await axios.post(`/api/groupbuys/${gb.value.id}/join`, payload)
    joinDone.value = true

    // Reload data
    await loadDetail(gb.value.id)
  } catch (e) {
    const msg = e.response?.data?.message || '참여 중 오류가 발생했습니다'
    alert(msg)
  }

  joining.value = false
}

async function cancelParticipation() {
  if (!gb.value) return
  if (!confirm('참여를 취소하시겠습니까?')) return

  try {
    await axios.post(`/api/groupbuys/${gb.value.id}/cancel`)
    await loadDetail(gb.value.id)
  } catch (e) {
    const msg = e.response?.data?.message || '취소 중 오류가 발생했습니다'
    alert(msg)
  }
}

async function deleteGb() {
  if (!confirm('정말 삭제하시겠습니까?')) return
  try {
    await axios.delete(`/api/groupbuys/${gb.value.id}`)
    router.push('/groupbuy')
  } catch {}
}

const { sendRequest: doSendFriendReq, loading: friendLoading } = useFriendAction()
const msgModal = ref(false)
const reportShow = ref(false)

async function doAddFriend() { await doSendFriendReq(gb.value.user_id) }

// ── Watch route changes ──
watch(() => route.params.id, (newId) => {
  if (newId) {
    loadDetail(newId)
    window.scrollTo({ top: 0, behavior: 'smooth' })
  }
})

onMounted(() => {
  gbStore.loadAll()
  if (route.params.id) loadDetail(route.params.id)
  countdownTimer = setInterval(() => { now.value = Date.now() }, 60000)
})

onUnmounted(() => {
  if (countdownTimer) clearInterval(countdownTimer)
})
</script>
