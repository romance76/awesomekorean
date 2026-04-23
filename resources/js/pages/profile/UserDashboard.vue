<template>
<div class="min-h-screen bg-gray-50">
  <div class="max-w-6xl mx-auto px-4 py-5">
    <h1 class="text-xl font-black text-gray-800 mb-4">📋 내 대시보드</h1>

    <!-- 포인트 카드 -->
    <div class="bg-gradient-to-r from-amber-400 via-orange-400 to-amber-500 rounded-2xl p-5 mb-5 text-amber-900">
      <div class="text-sm opacity-80">내 포인트</div>
      <div class="text-3xl font-black">{{ (auth.user?.points || 0).toLocaleString() }}P</div>
    </div>

    <!-- 탭 -->
    <div class="flex gap-1 mb-4 bg-white rounded-xl p-1 shadow-sm border overflow-x-auto scrollbar-hide">
      <button v-for="t in tabs" :key="t.key" @click="switchTab(t.key)"
        class="flex-shrink-0 text-xs font-bold py-2 px-3 rounded-lg transition whitespace-nowrap"
        :class="tab===t.key ? 'bg-amber-400 text-amber-900' : 'text-gray-500 hover:bg-gray-50'">{{ t.icon }} {{ t.label }}</button>
    </div>

    <!-- ═══ 프로필 탭 ═══ -->
    <div v-if="tab==='profile'" class="space-y-4">
      <div class="bg-white rounded-xl shadow-sm border p-5">
        <h2 class="font-bold text-gray-800 mb-4">📝 프로필 수정</h2>
        <!-- 아바타 -->
        <div class="flex items-center gap-4 mb-5">
          <div class="w-16 h-16 rounded-full bg-amber-400 text-white flex items-center justify-center text-2xl font-bold overflow-hidden relative">
            <img v-if="auth.user?.avatar" :src="auth.user.avatar" alt="avatar" class="absolute inset-0 w-full h-full object-cover" @error="(e)=>{ e.target.style.display='none' }" />
            <span v-else>{{ (auth.user?.name||'?')[0] }}</span>
          </div>
          <label class="cursor-pointer text-sm text-amber-600 font-bold hover:text-amber-800">
            📷 사진 변경<input type="file" accept="image/*" @change="uploadAvatar" class="hidden" />
          </label>
          <span v-if="avatarMsg" class="text-xs text-green-600">{{ avatarMsg }}</span>
        </div>
        <!-- 이메일 (읽기 전용) -->
        <div class="mb-3">
          <label class="text-xs font-bold text-gray-600 mb-1 block">이메일</label>
          <input :value="auth.user?.email" disabled class="w-full border rounded-lg px-3 py-2 text-sm bg-gray-50 text-gray-500 cursor-not-allowed" />
        </div>
        <!-- 이름/닉네임 -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-3">
          <div><label class="text-xs font-bold text-gray-600 mb-1 block">이름</label><input v-model="pf.name" class="w-full border rounded-lg px-3 py-2 text-sm" /></div>
          <div><label class="text-xs font-bold text-gray-600 mb-1 block">닉네임</label><input v-model="pf.nickname" class="w-full border rounded-lg px-3 py-2 text-sm" /></div>
        </div>
        <div class="mb-3"><label class="text-xs font-bold text-gray-600 mb-1 block">소개</label><textarea v-model="pf.bio" rows="3" placeholder="자기소개를 적어주세요" class="w-full border rounded-lg px-3 py-2 text-sm resize-none"></textarea></div>
        <!-- 전화번호 (필수) -->
        <div class="mb-3">
          <label class="text-xs font-bold text-gray-600 mb-1 block">전화번호 <span class="text-red-500">*필수</span></label>
          <input :value="pf.phone" @input="onPhoneInput" placeholder="000-000-0000" maxlength="12" class="w-full border rounded-lg px-3 py-2 text-sm" />
        </div>
        <!-- 주소 -->
        <div class="mb-3">
          <label class="text-xs font-bold text-gray-600 mb-1 block">주소 1</label>
          <input v-model="pf.address1" placeholder="주소 1 (예: 123 Main St)" class="w-full border rounded-lg px-3 py-2 text-sm" />
        </div>
        <div class="mb-3">
          <label class="text-xs font-bold text-gray-600 mb-1 block">주소 2</label>
          <input v-model="pf.address2" placeholder="주소 2 (예: Apt 4B)" class="w-full border rounded-lg px-3 py-2 text-sm" />
        </div>
        <div class="grid grid-cols-3 gap-3 mb-3">
          <div><label class="text-xs font-bold text-gray-600 mb-1 block">도시</label><input v-model="pf.city" class="w-full border rounded-lg px-3 py-2 text-sm" /></div>
          <div><label class="text-xs font-bold text-gray-600 mb-1 block">주</label><input v-model="pf.state" class="w-full border rounded-lg px-3 py-2 text-sm" /></div>
          <div><label class="text-xs font-bold text-gray-600 mb-1 block">우편번호</label><input v-model="pf.zipcode" class="w-full border rounded-lg px-3 py-2 text-sm" /></div>
        </div>

        <!-- 기본 검색 반경 -->
        <div class="mb-3">
          <label class="text-xs font-bold text-gray-600 mb-1 block">📍 기본 검색 반경</label>
          <select v-model="pf.default_radius" class="w-full border rounded-lg px-3 py-2 text-sm">
            <option :value="10">10마일 이내</option>
            <option :value="30">30마일 이내</option>
            <option :value="50">50마일 이내</option>
            <option :value="100">100마일 이내</option>
          </select>
          <p class="text-[10px] text-gray-400 mt-1">구인구직, 중고장터, 부동산 등 위치 기반 게시판의 기본 검색 범위</p>
        </div>

        <!-- 프라이버시 설정 -->
        <div class="border-t pt-4 mt-4">
          <h3 class="font-bold text-gray-700 text-sm mb-3">🔐 프라이버시 설정</h3>
          <div class="space-y-3">
            <div>
              <label class="text-xs font-bold text-gray-600 mb-1 block">친구 요청</label>
              <div class="flex gap-4">
                <label class="text-sm"><input type="radio" v-model="pf.allow_friend_request" :value="true" /> 허용</label>
                <label class="text-sm"><input type="radio" v-model="pf.allow_friend_request" :value="false" /> 거절</label>
              </div>
            </div>
            <div>
              <label class="text-xs font-bold text-gray-600 mb-1 block">쪽지 수신</label>
              <div class="flex gap-4">
                <label class="text-sm"><input type="radio" v-model="pf.allow_messages" :value="true" :disabled="!pf.allow_friend_request" /> 허용</label>
                <label class="text-sm"><input type="radio" v-model="pf.allow_messages" :value="false" /> 거절</label>
              </div>
              <p v-if="!pf.allow_friend_request" class="text-[10px] text-red-400 mt-1">친구 요청을 거절하면 쪽지도 자동으로 차단됩니다.</p>
            </div>
            <div>
              <label class="text-xs font-bold text-gray-600 mb-1 block">안심서비스 이용</label>
              <div class="flex gap-4">
                <label class="text-sm"><input type="radio" v-model="pf.allow_elder_service" :value="true" /> 허용</label>
                <label class="text-sm"><input type="radio" v-model="pf.allow_elder_service" :value="false" /> 거절</label>
              </div>
              <p class="text-[10px] text-gray-400 mt-1">허용 시 보호자가 안심 서비스를 등록할 수 있습니다.</p>
            </div>
          </div>
        </div>

        <div class="mb-4 mt-4">
          <label class="text-xs font-bold text-gray-600 mb-1 block">언어</label>
          <select v-model="pf.language" class="border rounded-lg px-3 py-2 text-sm"><option value="ko">한국어</option><option value="en">English</option></select>
        </div>
        <div v-if="pfMsg" class="text-sm mb-2" :class="pfMsgType==='success'?'text-green-600':'text-red-500'">{{ pfMsg }}</div>
        <button @click="saveProfile" :disabled="pfSaving" class="bg-amber-400 text-amber-900 font-bold px-6 py-2 rounded-lg hover:bg-amber-500 disabled:opacity-50">{{ pfSaving ? '저장중...' : '저장하기' }}</button>
      </div>
      <div class="bg-white rounded-xl shadow-sm border p-5">
        <h2 class="font-bold text-gray-800 mb-4">🔒 비밀번호 변경</h2>
        <div class="space-y-3 max-w-sm">
          <input v-model="pw.current_password" type="password" placeholder="현재 비밀번호" class="w-full border rounded-lg px-3 py-2 text-sm" />
          <input v-model="pw.password" type="password" placeholder="새 비밀번호" class="w-full border rounded-lg px-3 py-2 text-sm" />
          <input v-model="pw.password_confirmation" type="password" placeholder="새 비밀번호 확인" class="w-full border rounded-lg px-3 py-2 text-sm" />
        </div>
        <div v-if="pwMsg" class="text-sm mt-2" :class="pwMsgType==='success'?'text-green-600':'text-red-500'">{{ pwMsg }}</div>
        <button @click="changePw" :disabled="pwSaving" class="mt-3 bg-gray-700 text-white font-bold px-6 py-2 rounded-lg hover:bg-gray-800 disabled:opacity-50">{{ pwSaving ? '변경중...' : '변경하기' }}</button>
      </div>
    </div>

    <!-- ═══ 포인트 탭 ═══ -->
    <div v-else-if="tab==='points'" class="space-y-4">
      <div class="bg-white rounded-xl shadow-sm border p-5">
        <div class="flex items-center justify-between mb-4">
          <h2 class="font-bold text-gray-800">💰 포인트</h2>
          <button @click="startRoulette" :disabled="spun || spinning" class="font-bold px-4 py-2 rounded-lg text-sm transition"
            :class="spun ? 'bg-gray-300 text-gray-500 cursor-not-allowed' : 'bg-amber-400 text-amber-900 hover:bg-amber-500'">
            {{ spun ? '✅ 오늘 완료' : spinning ? '🎰 돌리는 중...' : '🎰 출석 체크' }}
          </button>
        </div>

        <!-- 룰렛 -->
        <div v-if="showRoulette" class="flex flex-col items-center mb-4">
          <div class="relative w-48 h-48 mb-3">
            <!-- 화살표 -->
            <div class="absolute top-0 left-1/2 -translate-x-1/2 -mt-1 z-10 text-red-500 text-2xl">▼</div>
            <!-- 회전 원판 -->
            <div class="w-48 h-48 rounded-full border-4 border-amber-400 overflow-hidden relative transition-transform"
              :style="{ transform: `rotate(${rouletteAngle}deg)`, transitionDuration: spinning ? '4s' : '0s', transitionTimingFunction: 'cubic-bezier(0.17,0.67,0.12,0.99)' }">
              <div v-for="(seg, i) in rouletteSegments" :key="i"
                class="absolute w-full h-full flex items-start justify-center"
                :style="{ transform: `rotate(${i * (360/rouletteSegments.length) + (180/rouletteSegments.length)}deg)` }">
                <span class="text-[10px] font-black mt-2" :class="seg.color">{{ seg.points }}P</span>
              </div>
              <!-- 중심 원 -->
              <div class="absolute inset-0 flex items-center justify-center">
                <div class="w-14 h-14 bg-white rounded-full border-2 border-amber-300 flex items-center justify-center text-lg">🎰</div>
              </div>
              <!-- 색상 구획 배경 -->
              <svg class="absolute inset-0 w-full h-full -z-10" viewBox="0 0 100 100">
                <circle v-for="(seg, i) in rouletteSegments" :key="'bg'+i" cx="50" cy="50" r="48"
                  fill="none" :stroke="seg.bg" stroke-width="48"
                  :stroke-dasharray="`${(100*Math.PI/rouletteSegments.length)} ${100*Math.PI}`"
                  :stroke-dashoffset="`${-(100*Math.PI/rouletteSegments.length)*i}`" />
              </svg>
            </div>
          </div>
          <div v-if="spinResult" class="text-center animate-bounce">
            <span class="text-xl font-black text-amber-600">🎉 {{ spinResult }}P 당첨!</span>
          </div>
        </div>
        <div class="text-3xl font-black text-amber-600 mb-4">{{ (auth.user?.points || ptBalance).toLocaleString() }}P</div>
        <!-- 포인트 구매 -->
        <div class="border-t pt-4 mt-4 mb-4">
          <div class="flex items-center justify-between mb-3">
            <h3 class="font-bold text-gray-700 text-sm">🛒 포인트 구매</h3>
            <span v-if="packagePromotion" class="text-[10px] bg-red-100 text-red-600 font-bold px-2 py-0.5 rounded-full">
              🎉 {{ packagePromotion.title }} -{{ packagePromotion.discount_pct }}%
            </span>
          </div>
          <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
            <button v-for="pkg in packages" :key="pkg.key" @click="buyPackage(pkg)"
              class="border-2 rounded-xl p-3 text-center hover:border-amber-400 hover:bg-amber-50 transition relative"
              :class="selectedPkg===pkg.key ? 'border-amber-400 bg-amber-50' : 'border-gray-200'">
              <span v-if="pkg.discount_pct > 0" class="absolute -top-1.5 -right-1.5 bg-red-500 text-white text-[9px] font-bold px-1.5 py-0.5 rounded-full shadow">-{{ pkg.discount_pct }}%</span>
              <div class="text-lg font-black text-amber-600">{{ (pkg.points + pkg.bonus).toLocaleString() }}P</div>
              <div v-if="pkg.bonus" class="text-[10px] text-green-600 font-bold">+{{ pkg.bonus.toLocaleString() }}P 보너스</div>
              <div class="mt-1">
                <span v-if="pkg.discount_pct > 0" class="text-[10px] text-gray-400 line-through block leading-none">${{ pkg.original_price }}</span>
                <span class="text-sm font-bold text-gray-800">${{ pkg.price }}</span>
              </div>
              <div class="text-[9px] text-gray-400 mt-0.5">{{ pkg.name }}</div>
            </button>
          </div>
        </div>
        <h3 class="font-bold text-gray-700 text-sm mb-2">📋 적립/사용 내역</h3>
        <div v-if="!ptHistory.length" class="text-sm text-gray-400 py-4 text-center">내역이 없습니다</div>
        <div v-else class="max-h-80 overflow-y-auto pr-2">
          <div v-for="h in ptHistory" :key="h.id" class="flex items-center justify-between py-1.5 border-b last:border-0">
            <!-- 데스크톱: 한줄 / 모바일: 두줄 -->
            <div class="flex items-center gap-2 min-w-0 flex-1 sm:flex-row flex-col sm:items-center items-start">
              <span class="text-xs text-gray-800 truncate">{{ h.reason }}</span>
              <span class="text-[10px] text-gray-400 flex-shrink-0">{{ fmtDate(h.created_at) }}</span>
            </div>
            <span :class="h.amount>0?'text-green-600':'text-red-500'" class="text-xs font-bold flex-shrink-0 ml-3">{{ h.amount>0?'+':'' }}{{ h.amount }}P</span>
          </div>
        </div>
      </div>
    </div>

    <!-- ═══ 쪽지 탭 ═══ -->
    <div v-else-if="tab==='messages'" class="space-y-4">
      <div class="bg-white rounded-xl shadow-sm border p-5">
        <div class="flex items-center justify-between mb-4">
          <h2 class="font-bold text-gray-800">✉️ 쪽지</h2>
          <span v-if="msgUnread" class="bg-red-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full">{{ msgUnread }}</span>
        </div>
        <div class="flex gap-1 mb-3 bg-gray-50 rounded-lg p-1">
          <button @click="msgTab='received'; loadMessages()" class="flex-1 text-xs font-bold py-1.5 rounded-lg" :class="msgTab==='received'?'bg-amber-400 text-amber-900':'text-gray-500'">📥 받은 쪽지</button>
          <button @click="msgTab='sent'; loadMessages()" class="flex-1 text-xs font-bold py-1.5 rounded-lg" :class="msgTab==='sent'?'bg-blue-500 text-white':'text-gray-500'">📤 보낸 쪽지</button>
        </div>
        <div v-if="!msgList.length" class="text-sm text-gray-400 py-6 text-center">{{ msgTab==='received'?'받은 쪽지가 없습니다':'보낸 쪽지가 없습니다' }}</div>
        <div v-else class="space-y-0 max-h-96 overflow-y-auto">
          <div v-for="m in msgList" :key="m.id" @click="openMsg(m)" class="flex items-center gap-2 px-3 py-2.5 border-b last:border-0 cursor-pointer hover:bg-amber-50/50" :class="msgTab==='received'&&!m.is_read?'bg-amber-50':''">
            <span v-if="msgTab==='received'&&!m.is_read" class="w-2 h-2 bg-amber-500 rounded-full flex-shrink-0"></span>
            <div class="w-7 h-7 bg-amber-100 rounded-full flex items-center justify-center text-[11px] font-bold text-amber-700 flex-shrink-0">{{ ((msgTab==='received'?m.sender?.name:m.receiver?.name)||'?')[0] }}</div>
            <div class="min-w-0 flex-1">
              <div class="text-xs font-bold text-gray-800 truncate">{{ msgTab==='received'?m.sender?.name:m.receiver?.name }}</div>
              <div class="text-[11px] text-gray-500 truncate">{{ m.content }}</div>
            </div>
            <span class="text-[10px] text-gray-400 flex-shrink-0">{{ fmtDate(m.created_at) }}</span>
            <button @click.stop="deleteMsg(m)" class="text-[10px] text-red-400 hover:text-red-600 flex-shrink-0 ml-1">✕</button>
          </div>
        </div>
      </div>
      <div v-if="activeMsg" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center" @click.self="activeMsg=null">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-md overflow-hidden">
          <div class="bg-gradient-to-r from-amber-400 to-orange-400 px-5 py-3 flex items-center justify-between">
            <span class="text-sm font-bold text-amber-900">{{ msgTab==='received'?activeMsg.sender?.name:activeMsg.receiver?.name }}</span>
            <button @click="activeMsg=null" class="text-amber-900/50 hover:text-amber-900">✕</button>
          </div>
          <div class="p-5">
            <div class="text-sm text-gray-700 whitespace-pre-wrap leading-relaxed mb-4">{{ activeMsg.content }}</div>
            <div class="text-[10px] text-gray-400 mb-3">{{ fmtDate(activeMsg.created_at) }}</div>
            <div v-if="msgTab==='received'" class="flex gap-2">
              <button @click="replyMsg(activeMsg)" class="bg-amber-400 text-amber-900 font-bold px-4 py-2 rounded-lg text-sm flex-1 hover:bg-amber-500">↩️ 답장</button>
              <button @click="activeMsg=null" class="text-gray-500 px-4 py-2 text-sm">닫기</button>
            </div>
          </div>
        </div>
      </div>
      <div v-if="replyTarget" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center" @click.self="replyTarget=null">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-md overflow-hidden">
          <div class="bg-gradient-to-r from-blue-500 to-blue-600 px-5 py-3 flex items-center justify-between">
            <span class="text-sm font-bold text-white">{{ replyTarget.name }}님에게 답장</span>
            <button @click="replyTarget=null" class="text-white/50 hover:text-white">✕</button>
          </div>
          <div class="p-5">
            <textarea v-model="replyText" rows="5" maxlength="500" placeholder="내용을 입력하세요..." class="w-full border rounded-lg p-3 text-sm resize-none"></textarea>
            <div class="flex justify-between items-center mt-3">
              <span class="text-[10px] text-gray-400">{{ replyText.length }}/500</span>
              <button @click="sendReply" :disabled="replySending||!replyText.trim()" class="bg-blue-500 text-white font-bold px-4 py-2 rounded-lg text-sm hover:bg-blue-600 disabled:opacity-50">{{ replySending?'전송중...':'보내기' }}</button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ═══ 내 글 탭 ═══ -->
    <div v-else-if="tab==='posts'" class="space-y-4">
      <div class="bg-white rounded-xl shadow-sm border p-5">
        <h2 class="font-bold text-gray-800 mb-4">📄 내가 쓴 글</h2>
        <div v-if="!myPosts.length" class="text-sm text-gray-400 py-6 text-center">작성한 글이 없습니다</div>
        <div v-else class="space-y-2">
          <RouterLink v-for="p in myPosts" :key="p.id" :to="'/community/'+(p.board?.slug||'free')+'/'+p.id" class="block border rounded-lg p-3 hover:bg-amber-50/50 transition">
            <div class="text-sm font-bold text-gray-800 truncate">{{ p.title }}</div>
            <div class="flex items-center gap-2 mt-1">
              <span class="text-[10px] text-gray-400">{{ fmtDate(p.created_at) }}</span>
              <span class="text-[10px] text-amber-600">💬 {{ p.comments_count||0 }}</span>
              <span class="text-[10px] text-red-400">❤️ {{ p.likes_count||0 }}</span>
              <span class="text-[10px] text-gray-400">👁 {{ p.views||0 }}</span>
            </div>
          </RouterLink>
        </div>
      </div>
    </div>

    <!-- ═══ 내 장터 탭 ═══ -->
    <div v-else-if="tab==='market'" class="space-y-4">
      <div class="bg-white rounded-xl shadow-sm border p-5">
        <div class="flex items-center justify-between mb-4">
          <h2 class="font-bold text-gray-800">🛒 내 판매 물품</h2>
          <RouterLink to="/market/write" class="bg-amber-400 text-amber-900 font-bold px-4 py-1.5 rounded-lg text-xs hover:bg-amber-500">+ 등록</RouterLink>
        </div>
        <div v-if="!myMarketItems.length" class="text-sm text-gray-400 py-6 text-center">등록한 물품이 없습니다</div>
        <div v-else class="space-y-2">
          <div v-for="m in myMarketItems" :key="m.id" class="border rounded-lg p-3 hover:bg-amber-50/30 transition">
            <div class="flex items-start gap-3">
              <div v-if="m.images?.length" class="w-14 h-14 rounded-lg overflow-hidden bg-gray-100 flex-shrink-0">
                <img :src="m.images[(m.thumbnail_index || 0)]?.startsWith?.('http') ? m.images[m.thumbnail_index || 0] : '/storage/'+m.images[m.thumbnail_index || 0]" class="w-full h-full object-cover" @error="e=>e.target.style.display='none'" />
              </div>
              <div v-else class="w-14 h-14 rounded-lg bg-amber-50 flex items-center justify-center text-2xl flex-shrink-0">🛒</div>
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-1.5 flex-wrap">
                  <span class="text-[10px] px-1.5 py-0.5 rounded font-bold"
                    :class="{'bg-green-100 text-green-700':m.status==='active','bg-amber-100 text-amber-700':m.status==='reserved','bg-gray-200 text-gray-500':m.status==='sold'}">
                    {{ {active:'판매중',reserved:'예약중',sold:'판매완료'}[m.status] }}
                  </span>
                  <span v-if="m.promotion_tier === 'national' && m.promotion_expires_at && new Date(m.promotion_expires_at) > new Date()" class="text-[10px] bg-red-500 text-white px-1.5 py-0.5 rounded font-bold">🌐 전국구</span>
                  <span v-else-if="m.promotion_tier === 'state_plus' && m.promotion_expires_at && new Date(m.promotion_expires_at) > new Date()" class="text-[10px] bg-blue-500 text-white px-1.5 py-0.5 rounded font-bold">⭐ 주+</span>
                  <span v-else-if="m.promotion_tier === 'sponsored' && m.promotion_expires_at && new Date(m.promotion_expires_at) > new Date()" class="text-[10px] bg-amber-500 text-white px-1.5 py-0.5 rounded font-bold">📢 스폰서</span>
                  <span v-if="m.boosted_until && new Date(m.boosted_until) > new Date()" class="text-[9px] bg-purple-100 text-purple-700 px-1 py-0.5 rounded font-bold">🚀</span>
                </div>
                <RouterLink :to="'/market/'+m.id" class="block mt-1">
                  <div class="text-sm font-bold text-gray-800 truncate hover:text-amber-600">{{ m.title }}</div>
                </RouterLink>
                <div class="flex items-center gap-2 mt-0.5 text-[10px] text-gray-400">
                  <span class="font-bold text-amber-600">${{ Number(m.price).toLocaleString() }}</span>
                  <span>👁 {{ m.view_count }}</span>
                  <span>{{ m.city }}{{ m.state ? ', '+m.state : '' }}</span>
                </div>
              </div>
            </div>
            <div class="flex items-center gap-1.5 mt-2 pt-2 border-t border-gray-100">
              <RouterLink :to="'/market/write?edit='+m.id" class="flex-1 text-center text-xs font-bold py-1.5 rounded bg-amber-50 text-amber-700 hover:bg-amber-100">✏️ 수정</RouterLink>
              <button @click="openMarketPromote(m)" class="flex-1 text-xs font-bold py-1.5 rounded bg-purple-50 text-purple-700 hover:bg-purple-100">🚀 상위노출</button>
              <RouterLink :to="'/market/'+m.id" class="flex-1 text-center text-xs font-bold py-1.5 rounded bg-blue-50 text-blue-700 hover:bg-blue-100">👁 미리보기</RouterLink>
              <button @click="deleteMarketItem(m)" class="text-xs font-bold py-1.5 px-3 rounded bg-red-50 text-red-600 hover:bg-red-100">🗑</button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ═══ 내 구인구직 탭 ═══ -->
    <div v-else-if="tab==='jobs'" class="space-y-4">
      <div class="bg-white rounded-xl shadow-sm border p-5">
        <div class="flex items-center justify-between mb-4">
          <h2 class="font-bold text-gray-800">💼 내 구인/구직 공고</h2>
          <RouterLink to="/jobs/write" class="bg-amber-400 text-amber-900 font-bold px-4 py-1.5 rounded-lg text-xs hover:bg-amber-500">+ 등록</RouterLink>
        </div>
        <div v-if="!myJobs.length" class="text-sm text-gray-400 py-6 text-center">등록한 공고가 없습니다</div>
        <div v-else class="space-y-2">
          <div v-for="j in myJobs" :key="j.id" class="border rounded-lg p-3 hover:bg-amber-50/30 transition">
            <div class="flex items-start gap-3">
              <div v-if="j.logo" class="w-14 h-14 rounded-lg overflow-hidden bg-gray-100 flex-shrink-0">
                <img :src="j.logo" class="w-full h-full object-cover" @error="e=>e.target.style.display='none'" />
              </div>
              <div v-else class="w-14 h-14 rounded-lg bg-gradient-to-br from-amber-100 to-amber-200 flex items-center justify-center text-2xl flex-shrink-0">
                {{ j.post_type === 'hiring' ? '💼' : '🔍' }}
              </div>
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-1.5 flex-wrap">
                  <span class="text-[10px] px-1.5 py-0.5 rounded font-bold"
                    :class="j.post_type === 'hiring' ? 'bg-amber-100 text-amber-700' : 'bg-blue-100 text-blue-700'">
                    {{ j.post_type === 'hiring' ? '구인' : '구직' }}
                  </span>
                  <span class="text-[10px] px-1.5 py-0.5 rounded font-bold"
                    :class="j.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-200 text-gray-500'">
                    {{ j.is_active ? '게시중' : '종료' }}
                  </span>
                  <span v-if="j.promotion_tier && j.promotion_tier !== 'none' && j.promotion_expires_at && new Date(j.promotion_expires_at) > new Date()"
                    class="text-[10px] px-1.5 py-0.5 rounded font-bold bg-purple-100 text-purple-700">
                    ⭐ {{ {national:'전국',state_plus:'주',sponsored:'스폰'}[j.promotion_tier] }}
                  </span>
                </div>
                <RouterLink :to="'/jobs/'+j.id" class="block">
                  <div class="text-sm font-bold text-gray-800 truncate mt-1 hover:text-amber-600">{{ j.title }}</div>
                </RouterLink>
                <div class="flex items-center gap-2 mt-0.5 text-[10px] text-gray-400">
                  <span v-if="j.company">{{ j.company }}</span>
                  <span v-if="j.city">· {{ j.city }}{{ j.state ? ', '+j.state : '' }}</span>
                  <span>· 👁 {{ j.view_count || 0 }}</span>
                  <span>· {{ fmtDate(j.created_at) }}</span>
                </div>
              </div>
            </div>
            <div class="flex items-center gap-1.5 mt-2 pt-2 border-t border-gray-100">
              <RouterLink :to="'/jobs/write?edit='+j.id"
                class="flex-1 text-center text-xs font-bold py-1.5 rounded bg-amber-50 text-amber-700 hover:bg-amber-100 transition">
                ✏️ 수정
              </RouterLink>
              <button @click="toggleJobActive(j)"
                class="flex-1 text-xs font-bold py-1.5 rounded transition"
                :class="j.is_active ? 'bg-gray-100 text-gray-600 hover:bg-gray-200' : 'bg-green-50 text-green-700 hover:bg-green-100'">
                {{ j.is_active ? '⏸ 비활성화' : '▶ 재게시' }}
              </button>
              <RouterLink :to="'/jobs/'+j.id"
                class="flex-1 text-center text-xs font-bold py-1.5 rounded bg-blue-50 text-blue-700 hover:bg-blue-100 transition">
                👁 미리보기
              </RouterLink>
              <button @click="deleteJob(j)"
                class="text-xs font-bold py-1.5 px-3 rounded bg-red-50 text-red-600 hover:bg-red-100 transition">
                🗑
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ═══ 내 부동산 탭 ═══ -->
    <div v-else-if="tab==='realestate'" class="space-y-4">
      <div class="bg-white rounded-xl shadow-sm border p-5">
        <div class="flex items-center justify-between mb-4">
          <h2 class="font-bold text-gray-800">🏠 내 부동산 매물</h2>
          <RouterLink to="/realestate/write" class="bg-amber-400 text-amber-900 font-bold px-4 py-1.5 rounded-lg text-xs hover:bg-amber-500">+ 등록</RouterLink>
        </div>
        <div v-if="!myRealEstate.length" class="text-sm text-gray-400 py-6 text-center">등록한 매물이 없습니다</div>
        <div v-else class="space-y-2">
          <div v-for="r in myRealEstate" :key="r.id" class="border rounded-lg p-3 hover:bg-amber-50/30 transition">
            <div class="flex items-start gap-3">
              <div v-if="r.images?.length" class="w-14 h-14 rounded-lg overflow-hidden bg-gray-100 flex-shrink-0">
                <img :src="r.images[0]?.startsWith?.('http') ? r.images[0] : '/storage/'+r.images[0]" class="w-full h-full object-cover" @error="e=>e.target.style.display='none'" />
              </div>
              <div v-else class="w-14 h-14 rounded-lg bg-amber-50 flex items-center justify-center text-2xl flex-shrink-0">
                {{ r.type==='sale'?'🏠':r.type==='rent'?'🔑':'👥' }}
              </div>
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-1.5 flex-wrap">
                  <span class="text-[10px] px-1.5 py-0.5 rounded font-bold" :class="r.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-200 text-gray-500'">{{ r.is_active ? '게시중' : '종료' }}</span>
                  <span class="text-[10px] px-1.5 py-0.5 rounded font-bold bg-amber-100 text-amber-700">{{ {rent:'렌트',sale:'매매',roommate:'룸메이트'}[r.type] }}</span>
                  <span v-if="r.promotion_tier === 'national' && r.promotion_expires_at && new Date(r.promotion_expires_at) > new Date()" class="text-[10px] bg-red-500 text-white px-1.5 py-0.5 rounded font-bold">🌐 전국구</span>
                  <span v-else-if="r.promotion_tier === 'state_plus' && r.promotion_expires_at && new Date(r.promotion_expires_at) > new Date()" class="text-[10px] bg-blue-500 text-white px-1.5 py-0.5 rounded font-bold">⭐ 주+</span>
                </div>
                <RouterLink :to="'/realestate/'+r.id" class="block mt-1">
                  <div class="text-sm font-bold text-gray-800 truncate hover:text-amber-600">{{ r.title }}</div>
                </RouterLink>
                <div class="flex items-center gap-2 mt-0.5 text-[10px] text-gray-400">
                  <span class="font-bold text-amber-600">${{ Number(r.price).toLocaleString() }}{{ r.type==='rent'?'/월':'' }}</span>
                  <span>{{ r.city }}{{ r.state ? ', '+r.state : '' }}</span>
                  <span v-if="r.bedrooms">🛏 {{ r.bedrooms }}</span>
                </div>
              </div>
            </div>
            <div class="flex items-center gap-1.5 mt-2 pt-2 border-t border-gray-100">
              <RouterLink :to="'/realestate/write?edit='+r.id" class="flex-1 text-center text-xs font-bold py-1.5 rounded bg-amber-50 text-amber-700 hover:bg-amber-100">✏️ 수정</RouterLink>
              <button @click="openRePromote(r)" class="flex-1 text-xs font-bold py-1.5 rounded bg-purple-50 text-purple-700 hover:bg-purple-100">🚀 상위노출</button>
              <RouterLink :to="'/realestate/'+r.id" class="flex-1 text-center text-xs font-bold py-1.5 rounded bg-blue-50 text-blue-700 hover:bg-blue-100">👁 미리보기</RouterLink>
              <button @click="deleteRealEstateItem(r)" class="text-xs font-bold py-1.5 px-3 rounded bg-red-50 text-red-600 hover:bg-red-100">🗑</button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- 공통 상위노출 모달 (중고장터/부동산) -->
    <div v-if="resourcePromoTarget" class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4" @click.self="resourcePromoTarget=null">
      <div class="bg-white rounded-xl max-w-lg w-full max-h-[90vh] overflow-y-auto p-4 space-y-3">
        <div class="flex items-center justify-between">
          <h3 class="font-bold text-gray-800">🚀 {{ resourcePromoTarget.title }} - 상위노출</h3>
          <button @click="resourcePromoTarget=null" class="text-gray-400 text-xl">&times;</button>
        </div>
        <PromotionSection :resource="resourcePromoType"
          :category="resourcePromoTarget.category || resourcePromoTarget.type"
          :state="resourcePromoTarget.state"
          v-model="resourcePromotion" ref="resourcePromoRef"
          :category-label="resourcePromoType === 'realestate' ? '유형' : '카테고리'" />
        <div v-if="resourcePromoError" class="bg-red-50 border border-red-200 text-red-600 rounded-lg px-3 py-2 text-xs">{{ resourcePromoError }}</div>
        <div class="flex gap-2 justify-end">
          <button @click="resourcePromoTarget=null" class="text-gray-500 text-xs px-4 py-2">취소</button>
          <button @click="submitResourcePromote" :disabled="resourcePromoSubmitting || resourcePromotion.tier === 'none'"
            class="bg-purple-500 text-white font-bold px-4 py-2 rounded-lg text-xs disabled:opacity-50">
            {{ resourcePromoSubmitting ? '처리중...' : '상위노출 적용' }}
          </button>
        </div>
      </div>
    </div>

    <!-- ═══ 광고 신청 탭 ═══ -->
    <div v-else-if="tab==='ads'" class="space-y-4">
      <!-- 내 광고 목록 -->
      <div class="bg-white rounded-xl shadow-sm border p-5">
        <div class="flex items-center justify-between mb-4">
          <h2 class="font-bold text-gray-800">📢 내 광고</h2>
        </div>
        <div v-if="!myAds.length" class="text-sm text-gray-400 py-6 text-center">신청한 광고가 없습니다</div>
        <div v-for="ad in myAds" :key="ad.id" class="border rounded-lg p-3 flex gap-3 mb-2">
          <div class="w-24 h-14 rounded overflow-hidden bg-gray-100 flex-shrink-0">
            <img :src="ad.image_url" class="w-full h-full object-cover" />
          </div>
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2">
              <span class="text-xs px-2 py-0.5 rounded-full font-bold" :class="{'bg-amber-100 text-amber-700':ad.status==='pending','bg-green-100 text-green-700':ad.status==='active','bg-red-100 text-red-700':ad.status==='rejected'}">
                {{ {pending:'승인대기',active:'게시중',rejected:'거절',expired:'만료',paused:'중지'}[ad.status] }}
              </span>
              <span class="text-xs text-gray-400">{{ ad.bid_amount || ad.total_cost }}P</span>
            </div>
            <div class="text-sm font-bold text-gray-800 truncate mt-0.5">{{ ad.title }}</div>
            <div class="text-[10px] text-gray-400">{{ ad.start_date?.slice(0,10) }} ~ {{ ad.end_date?.slice(0,10) }} · 노출{{ ad.impressions }} · 클릭{{ ad.clicks }}</div>
            <div v-if="ad.reject_reason" class="text-[10px] text-red-500 mt-0.5">거절사유: {{ ad.reject_reason }}</div>
          </div>
        </div>
      </div>
      <!-- 광고 신청 폼 (AdApply 임베드) -->
      <AdApplyEmbed :embedded="true" />
    </div>

    <!-- ═══ 통화 내역 탭 ═══ -->
    <div v-else-if="tab==='calls'" class="space-y-4">
      <div class="bg-white rounded-xl shadow-sm border p-5">
        <h2 class="font-bold text-gray-800 mb-4">📞 통화 내역</h2>
        <div v-if="!callHistory.length" class="text-sm text-gray-400 py-6 text-center">통화 기록이 없습니다</div>
        <div v-else class="space-y-2">
          <div v-for="c in callHistory" :key="c.id" class="border rounded-lg p-3 flex items-center gap-3">
            <div class="w-10 h-10 rounded-full flex items-center justify-center text-white font-bold text-sm"
              :class="c.direction==='outgoing' ? 'bg-blue-500' : 'bg-green-500'">
              {{ c.direction==='outgoing' ? '📤' : '📥' }}
            </div>
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2">
                <span class="text-sm font-bold text-gray-800">{{ c.partner_name }}</span>
                <span class="text-[10px] px-1.5 py-0.5 rounded-full font-bold"
                  :class="(c.call_type||'friend')==='elder' ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700'">
                  {{ (c.call_type||'friend')==='elder' ? '🛡️안심' : '👫친구' }}
                </span>
                <span class="text-[10px] px-2 py-0.5 rounded-full font-bold"
                  :class="c.status==='ended'||c.status==='answered' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'">
                  {{ c.direction==='outgoing' ? '발신' : '수신' }} · {{ {ended:'완료',answered:'응답',initiated:'부재중'}[c.status]||c.status }}
                </span>
              </div>
              <div class="text-[10px] text-gray-400 mt-0.5">{{ fmtDateFull(c.created_at) }}</div>
            </div>
            <div class="text-right flex-shrink-0">
              <div class="text-sm font-bold" :class="c.duration && c.duration!=='0초' ? 'text-green-700' : 'text-gray-400'">{{ c.duration || '-' }}</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ═══ 북마크 탭 ═══ -->
    <div v-else-if="tab==='bookmarks'" class="space-y-4">
      <div class="bg-white rounded-xl shadow-sm border p-5">
        <h2 class="font-bold text-gray-800 mb-4">🔖 북마크</h2>
        <div v-if="!bookmarks.length" class="text-sm text-gray-400 py-6 text-center">저장한 북마크가 없습니다</div>
        <div v-else class="space-y-2">
          <div v-for="b in bookmarks" :key="b.id" class="border rounded-lg p-3 flex items-center gap-3">
            <div class="flex-1 min-w-0 cursor-pointer hover:text-amber-700" @click="goBookmark(b)">
              <div class="text-sm font-bold text-gray-800 truncate">{{ b.bookmarkable?.title || b.bookmarkable_type + ' #' + b.bookmarkable_id }}</div>
              <div class="text-[10px] text-gray-400 mt-0.5">{{ fmtDate(b.created_at) }}</div>
            </div>
            <button @click="deleteBookmark(b)" class="text-xs text-red-400 hover:text-red-600 flex-shrink-0">삭제</button>
          </div>
        </div>
      </div>
    </div>

    <!-- ═══ 안심서비스 탭 ═══ -->
    <div v-else-if="tab==='elder'" class="space-y-4">
      <!-- 보호대상 등록 -->
      <div class="bg-white rounded-xl shadow-sm border p-5">
        <h2 class="font-bold text-gray-800 mb-4">🛡️ 보호대상 등록</h2>
        <p class="text-xs text-gray-500 mb-3">보호하고 싶은 분의 이메일을 검색하여 안심서비스를 등록하세요.</p>
        <div class="flex gap-2 mb-3">
          <input v-model="wardSearch" type="email" placeholder="보호대상 이메일 입력" class="flex-1 border rounded-lg px-3 py-2 text-sm" />
          <button @click="searchWard" :disabled="wardSearching" class="bg-green-600 text-white font-bold px-4 py-2 rounded-lg text-sm hover:bg-green-700 disabled:opacity-50">{{ wardSearching?'검색중...':'검색' }}</button>
        </div>
        <div v-if="wardResult" class="rounded-lg p-3 text-sm" :class="wardResult.ok ? 'bg-green-50 border border-green-200 text-green-800' : 'bg-red-50 border border-red-200 text-red-700'">
          {{ wardResult.message }}
          <div v-if="wardResult.ok && wardResult.user" class="mt-2 flex items-center justify-between">
            <span class="font-bold">{{ wardResult.user.name }} ({{ wardResult.user.city }}, {{ wardResult.user.state }})</span>
            <button @click="registerWard(wardResult.user.id)" class="bg-green-600 text-white font-bold px-3 py-1 rounded-lg text-xs hover:bg-green-700">등록하기</button>
          </div>
        </div>
      </div>

      <!-- 내 보호대상 목록 -->
      <div class="bg-white rounded-xl shadow-sm border p-5">
        <h2 class="font-bold text-gray-800 mb-4">👥 내 보호대상</h2>
        <div v-if="!elderWards.length" class="text-sm text-gray-400 py-4 text-center">등록된 보호대상이 없습니다</div>
        <div v-for="w in elderWards" :key="w.id" class="border rounded-lg p-4 mb-3">
          <div class="flex items-center justify-between mb-3">
            <div class="flex items-center gap-2">
              <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center text-lg font-bold text-green-700">{{ (w.ward?.name||'?')[0] }}</div>
              <div>
                <div class="text-sm font-bold text-gray-800">{{ w.ward?.name }}</div>
                <div class="text-[10px] text-gray-400">{{ w.ward?.email }}</div>
              </div>
            </div>
            <span class="text-[10px] px-2 py-0.5 rounded-full font-bold" :class="w.status==='active'?'bg-green-100 text-green-700':w.status==='pending'?'bg-yellow-100 text-yellow-700':'bg-red-100 text-red-700'">{{ {active:'활성',pending:'대기',rejected:'거절'}[w.status] }}</span>
          </div>

          <!-- 스케줄 설정 (활성 상태만) -->
          <div v-if="w.status==='active'" class="border-t pt-3">
            <h4 class="text-xs font-bold text-gray-600 mb-2">📞 전화 스케줄</h4>
            <div class="space-y-2">
              <div>
                <label class="text-[10px] font-bold text-gray-500">서비스 유형</label>
                <div class="flex gap-3 mt-1">
                  <label class="text-xs"><input type="radio" v-model="w._type" value="random" /> 랜덤 시간</label>
                  <label class="text-xs"><input type="radio" v-model="w._type" value="scheduled" /> 스케줄 <span class="text-amber-600">(유료)</span></label>
                </div>
              </div>
              <template v-if="w._type==='random'">
                <div class="grid grid-cols-2 gap-2">
                  <div><label class="text-[10px] font-bold text-gray-500">시작</label><input type="time" v-model="w._time_start" class="w-full border rounded px-2 py-1 text-xs" /></div>
                  <div><label class="text-[10px] font-bold text-gray-500">종료</label><input type="time" v-model="w._time_end" class="w-full border rounded px-2 py-1 text-xs" /></div>
                </div>
              </template>
              <template v-if="w._type==='scheduled'">
                <div><label class="text-[10px] font-bold text-gray-500">하루 몇 번</label>
                  <select v-model="w._calls_per_day" @change="w._times = w._times.slice(0, w._calls_per_day)" class="border rounded px-2 py-1 text-xs ml-1">
                    <option :value="1">1회</option><option :value="2">2회</option><option :value="3">3회</option>
                  </select>
                </div>
                <div><label class="text-[10px] font-bold text-gray-500">요일 선택</label>
                  <div class="flex gap-1 mt-1">
                    <label v-for="d in dayOptions" :key="d.key" class="text-[10px]">
                      <input type="checkbox" :value="d.key" v-model="w._days" class="mr-0.5" />{{ d.label }}
                    </label>
                  </div>
                </div>
                <div>
                  <label class="text-[10px] font-bold text-gray-500">전화 시간</label>
                  <div class="flex flex-wrap gap-1.5 mt-1">
                    <div v-for="(t, ti) in w._times" :key="ti" class="flex items-center gap-0.5 bg-green-50 border border-green-200 rounded-lg px-1">
                      <input type="time" v-model="w._times[ti]" class="border-0 bg-transparent text-xs py-1 px-1 outline-none w-20" />
                      <button @click="w._times.splice(ti,1)" class="text-red-400 hover:text-red-600 text-xs px-1">✕</button>
                    </div>
                    <button v-if="w._times.length < w._calls_per_day" @click="w._times.push('12:00')" class="bg-green-100 text-green-700 text-[10px] font-bold px-2 py-1 rounded-lg hover:bg-green-200">+ 시간 추가</button>
                  </div>
                </div>
                <p class="text-[10px] text-amber-600">* 스케줄 전화는 1회당 50P가 차감됩니다.</p>
              </template>
              <button @click="saveSchedule(w)" class="bg-green-600 text-white font-bold px-4 py-1.5 rounded-lg text-xs hover:bg-green-700 mt-1">스케줄 저장</button>
              <span v-if="w._schedMsg" class="text-xs ml-2" :class="w._schedOk?'text-green-600':'text-red-500'">{{ w._schedMsg }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ═══ 결제 탭 ═══ -->
    <div v-else-if="tab==='payments'" class="space-y-4">
      <div class="bg-white rounded-xl shadow-sm border p-5">
        <h2 class="font-bold text-gray-800 mb-4">💳 결제 내역</h2>
        <div v-if="!payments.length" class="text-sm text-gray-400 py-6 text-center">결제 내역이 없습니다</div>
        <div v-else class="space-y-2">
          <div v-for="p in payments" :key="p.id" class="border rounded-lg p-4 hover:bg-amber-50/30 transition">
            <div class="flex items-center justify-between mb-2">
              <div class="text-sm font-bold text-gray-800">포인트 구매 — {{ p.points_purchased?.toLocaleString() }}P</div>
              <span class="text-[10px] px-2 py-0.5 rounded-full font-bold" :class="p.status==='completed'?'bg-green-100 text-green-700':'bg-gray-100 text-gray-500'">{{ p.status==='completed'?'완료':'대기' }}</span>
            </div>
            <div class="flex items-center justify-between text-xs text-gray-500">
              <div>
                <span class="font-semibold">${{ p.amount }}</span>
                <span class="mx-1">|</span>
                <span>{{ fmtDateFull(p.created_at) }}</span>
              </div>
              <button @click="printInvoice(p)" class="text-amber-600 hover:text-amber-800 font-bold flex items-center gap-1">
                🧾 인보이스
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ═══ 내 업소 탭 ═══ -->
    <div v-else-if="tab==='mybiz'" class="space-y-4">
      <div class="bg-white rounded-xl shadow-sm border p-5">
        <h2 class="font-bold text-gray-800 mb-4">🏪 내 업소 관리</h2>
        <div v-if="!myBizList.length" class="text-center py-8 text-gray-400">
          <div class="text-3xl mb-2">🏪</div>
          <div class="text-sm">등록된 업소가 없습니다</div>
          <RouterLink to="/directory" class="text-xs text-amber-600 mt-2 inline-block">업소록에서 소유권 신청하기 →</RouterLink>
        </div>

        <!-- 업소 편집 모드 -->
        <div v-else-if="editBiz" class="space-y-3">
          <button @click="editBiz=null" class="text-xs text-gray-500 hover:text-gray-700">← 목록으로</button>
          <h3 class="font-bold text-amber-700">{{ editBiz.name }}</h3>

          <!-- 기본 정보 -->
          <div class="grid grid-cols-2 gap-3">
            <div><label class="text-xs font-bold text-gray-600 block mb-1">가게 이름</label><input v-model="editBiz.name" class="w-full border rounded-lg px-3 py-2 text-sm" /></div>
            <div><label class="text-xs font-bold text-gray-600 block mb-1">전화번호</label><input v-model="editBiz.phone" class="w-full border rounded-lg px-3 py-2 text-sm" /></div>
          </div>
          <div><label class="text-xs font-bold text-gray-600 block mb-1">주소</label><input v-model="editBiz.address" class="w-full border rounded-lg px-3 py-2 text-sm" /></div>
          <div class="grid grid-cols-3 gap-3">
            <div><label class="text-xs font-bold text-gray-600 block mb-1">도시</label><input v-model="editBiz.city" class="w-full border rounded-lg px-3 py-2 text-sm" /></div>
            <div><label class="text-xs font-bold text-gray-600 block mb-1">주</label><input v-model="editBiz.state" maxlength="2" class="w-full border rounded-lg px-3 py-2 text-sm uppercase" /></div>
            <div><label class="text-xs font-bold text-gray-600 block mb-1">우편번호</label>
              <input v-model="editBiz.zipcode" maxlength="5" @input="onBizZipChange" class="w-full border rounded-lg px-3 py-2 text-sm font-mono" />
            </div>
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="text-xs font-bold text-gray-600 block mb-1">카테고리 <span class="text-red-400">*</span></label>
              <select v-model="editBiz.category" class="w-full border rounded-lg px-3 py-2 text-sm">
                <option value="">선택</option>
                <option value="restaurant">🍽️ 음식점</option>
                <option value="beauty">💅 미용</option>
                <option value="medical">🏥 의료</option>
                <option value="retail">🛒 판매/리테일</option>
                <option value="service">🔧 서비스</option>
                <option value="education">📚 교육</option>
                <option value="auto">🚗 자동차</option>
                <option value="real_estate">🏠 부동산</option>
                <option value="professional">💼 전문직</option>
                <option value="etc">📋 기타</option>
              </select>
            </div>
            <div><label class="text-xs font-bold text-gray-600 block mb-1">웹사이트</label><input v-model="editBiz.website" class="w-full border rounded-lg px-3 py-2 text-sm" /></div>
          </div>

          <!-- 주소 기반 인라인 상위노출 -->
          <PromotionSection resource="business" :is-edit="false"
            :category="editBiz.category" :state="editBiz.state"
            v-model="editBizPromotion" ref="editBizPromoRef"
            category-label="카테고리" />
          <div class="flex items-center justify-end gap-2">
            <button @click="submitEditBizPromote" :disabled="editBizPromotion.tier === 'none' || editBizPromoSubmitting"
              class="bg-purple-500 text-white font-bold px-4 py-1.5 rounded-lg text-xs disabled:opacity-50">
              {{ editBizPromoSubmitting ? '처리중...' : '🚀 상위노출 적용' }}
            </button>
          </div>
          <div v-if="editBizPromoMsg" class="text-xs" :class="editBizPromoMsg.ok ? 'text-green-600' : 'text-red-500'">{{ editBizPromoMsg.text }}</div>

          <div><label class="text-xs font-bold text-gray-600 block mb-1">업소 소개</label><textarea v-model="editBiz.description" rows="4" class="w-full border rounded-lg px-3 py-2 text-sm resize-none"></textarea></div>

          <!-- 사진 관리 -->
          <div>
            <label class="text-xs font-bold text-gray-600 block mb-1">사진</label>
            <div class="flex gap-2 flex-wrap mb-2">
              <div v-for="(img, i) in editBiz.images" :key="i" class="relative">
                <img :src="img" class="w-20 h-20 rounded-lg object-cover" />
                <button @click="editBiz.images.splice(i,1)" class="absolute -top-1 -right-1 bg-red-500 text-white rounded-full w-5 h-5 text-xs">✕</button>
              </div>
            </div>
            <label class="text-xs bg-amber-100 text-amber-700 px-3 py-1.5 rounded-lg cursor-pointer hover:bg-amber-200 font-semibold">
              📷 사진 추가
              <input type="file" accept="image/*" multiple @change="uploadBizPhotos" class="hidden" />
            </label>
          </div>

          <button @click="saveMyBiz" class="bg-amber-400 text-amber-900 font-bold px-6 py-2 rounded-lg hover:bg-amber-500">저장하기</button>

          <!-- 메뉴 관리 -->
          <div class="border-t pt-4 mt-4">
            <div class="flex items-center justify-between mb-3">
              <h3 class="font-bold text-gray-700 text-sm">📋 메뉴 관리</h3>
              <button @click="showMenuForm=true" class="text-xs bg-amber-400 text-amber-900 font-bold px-3 py-1.5 rounded-lg">+ 메뉴 추가</button>
            </div>

            <!-- 메뉴 추가/수정 폼 -->
            <div v-if="showMenuForm" class="bg-amber-50 rounded-lg p-3 mb-3 space-y-2">
              <div class="grid grid-cols-3 gap-2">
                <div class="col-span-2"><input v-model="menuForm.name" placeholder="메뉴 이름" class="w-full border rounded-lg px-3 py-2 text-sm" /></div>
                <div><input v-model.number="menuForm.price" type="number" step="0.01" placeholder="가격" class="w-full border rounded-lg px-3 py-2 text-sm" /></div>
              </div>
              <input v-model="menuForm.description" placeholder="설명 (선택)" class="w-full border rounded-lg px-3 py-2 text-sm" />
              <select v-model="menuForm.category" class="border rounded-lg px-3 py-2 text-sm">
                <option value="main">메인</option><option value="side">사이드</option><option value="drink">음료</option><option value="dessert">디저트</option><option value="etc">기타</option>
              </select>

              <!-- 옵션 (최대 5개) -->
              <div class="text-xs font-bold text-gray-600">옵션 (최대 5개)</div>
              <div v-for="(opt, i) in menuForm.options" :key="i" class="flex gap-2">
                <input v-model="opt.name" placeholder="옵션명" class="flex-1 border rounded-lg px-2 py-1 text-xs" />
                <input v-model.number="opt.price_add" type="number" step="0.01" placeholder="+$" class="w-20 border rounded-lg px-2 py-1 text-xs" />
                <button @click="menuForm.options.splice(i,1)" class="text-red-400 text-xs">✕</button>
              </div>
              <button v-if="menuForm.options.length < 5" @click="menuForm.options.push({name:'',price_add:0})" class="text-xs text-amber-600">+ 옵션 추가</button>

              <div class="flex gap-2">
                <button @click="saveMenu" class="bg-amber-400 text-amber-900 font-bold px-4 py-1.5 rounded-lg text-xs">저장</button>
                <button @click="showMenuForm=false; menuForm={name:'',description:'',price:0,category:'main',options:[]}" class="text-xs text-gray-500">취소</button>
              </div>
            </div>

            <!-- 기존 메뉴 목록 -->
            <div v-for="menu in editBiz.menus" :key="menu.id" class="border rounded-lg p-3 mb-2">
              <div class="flex items-center justify-between">
                <div>
                  <span class="text-sm font-bold text-gray-800">{{ menu.name }}</span>
                  <span class="text-xs text-gray-400 ml-2">{{ menu.category }}</span>
                </div>
                <div class="flex items-center gap-2">
                  <span class="text-sm font-bold text-amber-600">${{ Number(menu.price).toFixed(2) }}</span>
                  <button @click="editMenuItem(menu)" class="text-xs text-amber-600">수정</button>
                  <button @click="deleteMenu(menu)" class="text-xs text-red-400">삭제</button>
                </div>
              </div>
              <div v-if="menu.options?.length" class="mt-1 flex gap-2 flex-wrap">
                <span v-for="opt in menu.options" :key="opt.id" class="text-[10px] bg-gray-100 px-2 py-0.5 rounded-full">{{ opt.name }} +${{ opt.price_add }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- 업소 목록 -->
        <div v-else class="space-y-2">
          <div v-for="biz in myBizList" :key="biz.id" class="border rounded-lg p-3 hover:border-amber-400 transition">
            <div class="flex items-center gap-3" @click="editBiz=biz" style="cursor:pointer">
              <img v-if="biz.images?.length" :src="biz.images[0]" class="w-12 h-12 rounded-lg object-cover" />
              <div class="w-12 h-12 bg-amber-100 rounded-lg flex items-center justify-center text-xl" v-else>🏪</div>
              <div class="flex-1">
                <div class="flex items-center gap-1.5">
                  <div class="text-sm font-bold text-gray-800">{{ biz.name }}</div>
                  <span v-if="biz.promotion_tier === 'national' && biz.promotion_expires_at && new Date(biz.promotion_expires_at) > new Date()" class="text-[9px] bg-red-500 text-white font-bold px-1.5 py-0.5 rounded">🌐 전국구</span>
                  <span v-else-if="biz.promotion_tier === 'state_plus' && biz.promotion_expires_at && new Date(biz.promotion_expires_at) > new Date()" class="text-[9px] bg-blue-500 text-white font-bold px-1.5 py-0.5 rounded">⭐ 주+</span>
                  <span v-else-if="biz.promotion_tier === 'sponsored' && biz.promotion_expires_at && new Date(biz.promotion_expires_at) > new Date()" class="text-[9px] bg-amber-500 text-white font-bold px-1.5 py-0.5 rounded">📢 스폰서</span>
                </div>
                <div class="text-xs text-gray-400">{{ biz.category }} · {{ biz.city }}, {{ biz.state }}</div>
              </div>
            </div>
            <div class="mt-2 pt-2 border-t border-gray-100 flex items-center justify-between gap-2">
              <div v-if="biz.promotion_tier && biz.promotion_tier !== 'none' && biz.promotion_expires_at && new Date(biz.promotion_expires_at) > new Date()"
                class="text-[10px] text-gray-500">
                상위노출 만료: {{ fmtDateFull(biz.promotion_expires_at) }}
              </div>
              <div v-else class="text-[10px] text-gray-400">상위노출 미적용</div>
              <button @click="openBizPromote(biz)"
                class="text-[10px] font-bold px-3 py-1 rounded bg-purple-100 text-purple-700 hover:bg-purple-200">
                🚀 상위노출 {{ biz.promotion_tier === 'none' || !biz.promotion_expires_at || new Date(biz.promotion_expires_at) <= new Date() ? '신청' : '연장/변경' }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- 업소 상위노출 신청 모달 -->
    <div v-if="bizPromoTarget" class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4" @click.self="bizPromoTarget=null">
      <div class="bg-white rounded-xl max-w-lg w-full max-h-[90vh] overflow-y-auto p-4 space-y-3">
        <div class="flex items-center justify-between">
          <h3 class="font-bold text-gray-800">🚀 {{ bizPromoTarget.name }} 상위노출</h3>
          <button @click="bizPromoTarget=null" class="text-gray-400 text-xl">&times;</button>
        </div>
        <PromotionSection resource="business" :is-edit="false"
          :category="bizPromoTarget.category" :state="bizPromoTarget.state"
          v-model="bizPromotion" ref="bizPromoRef"
          category-label="업소 카테고리" />
        <div v-if="bizPromoError" class="bg-red-50 border border-red-200 text-red-600 rounded-lg px-3 py-2 text-xs">{{ bizPromoError }}</div>
        <div class="flex gap-2 justify-end">
          <button @click="bizPromoTarget=null" class="text-gray-500 text-xs px-4 py-2">취소</button>
          <button @click="submitBizPromote" :disabled="bizPromoSubmitting || bizPromotion.tier === 'none'"
            class="bg-purple-500 text-white font-bold px-4 py-2 rounded-lg text-xs disabled:opacity-50">
            {{ bizPromoSubmitting ? '처리중...' : '상위노출 적용' }}
          </button>
        </div>
      </div>
    </div>

    <!-- ═══ 이력서 탭 ═══ -->
    <div v-else-if="tab==='resume'" class="space-y-4">
      <div class="bg-white rounded-xl shadow-sm border p-5">
        <h2 class="font-bold text-gray-800 mb-4">📄 이력서 관리</h2>
        <div class="space-y-3">
          <div>
            <label class="text-xs font-bold text-gray-600 mb-1 block">이력서 제목</label>
            <input v-model="resume.title" placeholder="예: 요식업 경력 3년 구직자" class="w-full border rounded-lg px-3 py-2 text-sm" />
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div><label class="text-xs font-bold text-gray-600 mb-1 block">이름</label><input v-model="resume.name" class="w-full border rounded-lg px-3 py-2 text-sm" /></div>
            <div><label class="text-xs font-bold text-gray-600 mb-1 block">연락처</label><input v-model="resume.phone" class="w-full border rounded-lg px-3 py-2 text-sm" /></div>
          </div>
          <div>
            <label class="text-xs font-bold text-gray-600 mb-1 block">이메일</label>
            <input v-model="resume.email" type="email" class="w-full border rounded-lg px-3 py-2 text-sm" />
          </div>
          <div>
            <label class="text-xs font-bold text-gray-600 mb-1 block">자기소개</label>
            <textarea v-model="resume.introduction" rows="4" placeholder="간단한 자기소개를 작성해주세요" class="w-full border rounded-lg px-3 py-2 text-sm resize-none"></textarea>
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label class="text-xs font-bold text-gray-600 mb-1 block">희망 직종</label>
              <select v-model="resume.desired_category" class="w-full border rounded-lg px-3 py-2 text-sm">
                <option value="">선택해주세요</option>
                <option value="restaurant">요식업</option><option value="it">IT</option><option value="beauty">미용</option>
                <option value="driving">운전</option><option value="retail">판매</option><option value="office">사무직</option>
                <option value="construction">건설</option><option value="medical">의료</option><option value="education">교육</option><option value="etc">기타</option>
              </select>
            </div>
            <div>
              <label class="text-xs font-bold text-gray-600 mb-1 block">희망 근무형태</label>
              <select v-model="resume.desired_type" class="w-full border rounded-lg px-3 py-2 text-sm">
                <option value="full">풀타임</option><option value="part">파트타임</option><option value="contract">계약직</option>
              </select>
            </div>
          </div>
          <div>
            <label class="text-xs font-bold text-gray-600 mb-1 block">희망 급여</label>
            <input v-model="resume.desired_salary" placeholder="예: 시급 $18 이상, 월급 $3000 이상" class="w-full border rounded-lg px-3 py-2 text-sm" />
          </div>
          <div>
            <label class="text-xs font-bold text-gray-600 mb-1 block">경력사항</label>
            <textarea v-model="resume.experience" rows="4" placeholder="경력사항을 작성해주세요" class="w-full border rounded-lg px-3 py-2 text-sm resize-none"></textarea>
          </div>
          <div>
            <label class="text-xs font-bold text-gray-600 mb-1 block">학력</label>
            <textarea v-model="resume.education" rows="3" placeholder="학력사항을 작성해주세요" class="w-full border rounded-lg px-3 py-2 text-sm resize-none"></textarea>
          </div>
          <div>
            <label class="text-xs font-bold text-gray-600 mb-1 block">보유기술</label>
            <textarea v-model="resume.skills" rows="3" placeholder="보유 기술을 작성해주세요" class="w-full border rounded-lg px-3 py-2 text-sm resize-none"></textarea>
          </div>
          <div>
            <label class="text-xs font-bold text-gray-600 mb-1 block">자격증</label>
            <textarea v-model="resume.certifications" rows="3" placeholder="자격증을 작성해주세요" class="w-full border rounded-lg px-3 py-2 text-sm resize-none"></textarea>
          </div>
          <div class="flex items-center gap-3 pt-2 border-t">
            <label class="text-xs font-bold text-gray-600">공개 여부</label>
            <button @click="resume.is_public=!resume.is_public" class="relative w-10 h-5 rounded-full transition"
              :class="resume.is_public ? 'bg-amber-400' : 'bg-gray-300'">
              <span class="absolute top-0.5 w-4 h-4 rounded-full bg-white shadow transition-all"
                :class="resume.is_public ? 'left-5' : 'left-0.5'"></span>
            </button>
            <span class="text-xs text-gray-500">{{ resume.is_public ? '공개 (구인 업체가 볼 수 있음)' : '비공개' }}</span>
          </div>
          <div v-if="resumeMsg" class="text-sm" :class="resumeMsgType==='success' ? 'text-green-600' : 'text-red-500'">{{ resumeMsg }}</div>
          <button @click="saveResume" :disabled="resumeSaving" class="bg-amber-400 text-amber-900 font-bold px-6 py-2.5 rounded-lg hover:bg-amber-500 disabled:opacity-50">{{ resumeSaving ? '저장 중...' : '저장하기' }}</button>
        </div>
      </div>
    </div>

    <!-- 결제 모달 -->
    <div v-if="payModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center" @click.self="payModal=false">
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm mx-4 overflow-hidden">
        <div class="bg-gradient-to-r from-amber-400 to-orange-400 px-5 py-3">
          <div class="text-sm font-black text-amber-900">💳 포인트 구매</div>
        </div>
        <div class="p-5">
          <div class="text-center mb-4">
            <div class="text-2xl font-black text-amber-600">{{ payPkg?.label }}</div>
            <div class="text-lg font-bold text-gray-800">{{ ((payPkg?.points||0)+(payPkg?.bonus||0)).toLocaleString() }}P — ${{ payPkg?.price }}</div>
          </div>
          <div id="card-element" class="border-2 border-gray-200 rounded-lg p-3 mb-3 min-h-[40px]"></div>
          <div v-if="payError" class="text-sm text-red-500 mb-2">{{ payError }}</div>
          <div class="flex gap-2">
            <button @click="payModal=false" class="flex-1 bg-gray-100 text-gray-600 font-bold py-2.5 rounded-lg text-sm hover:bg-gray-200">취소</button>
            <button @click="confirmPay" :disabled="paying" class="flex-1 bg-amber-500 text-white font-bold py-2.5 rounded-lg text-sm hover:bg-amber-600 disabled:opacity-50">{{ paying ? '결제중...' : '결제하기' }}</button>
          </div>
        </div>
      </div>
    </div>

    <!-- 계정 관리 -->
    <div class="bg-white rounded-xl shadow-sm border p-5 mt-5">
      <h2 class="font-bold text-gray-700 mb-3">⚙️ 계정 관리</h2>
      <div class="flex gap-3">
        <button @click="handleLogout" class="bg-gray-100 text-gray-700 font-bold px-4 py-2 rounded-lg text-sm hover:bg-gray-200">🚪 로그아웃</button>
        <button @click="deleteAccount" class="text-red-400 text-sm hover:text-red-600">⚠️ 회원 탈퇴</button>
      </div>
    </div>
  </div>
</div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onUnmounted, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import { useSiteStore } from '../../stores/site'
import { useModal } from '../../composables/useModal'
import axios from 'axios'
import AdApplyEmbed from '../ads/AdApply.vue'
import PromotionSection from '../../components/PromotionSection.vue'

const { showAlert, showConfirm, showPrompt } = useModal()

const auth = useAuthStore()
const siteStore = useSiteStore()
const router = useRouter()
const route = useRoute()
const tab = ref(route.query.tab || 'profile')

// menuKey 가 지정된 탭은 관리자 페이지에서 해당 메뉴가 숨김/관리자전용 처리되면 함께 숨김 처리됨
const allTabs = [
  { key: 'profile',    icon: '📝', label: '프로필' },
  { key: 'points',     icon: '💰', label: '포인트' },
  { key: 'messages',   icon: '✉️', label: '쪽지' },
  { key: 'posts',      icon: '📄', label: '내 글' },
  { key: 'market',     icon: '🛒', label: '내 장터',     menuKey: 'market' },
  { key: 'jobs',       icon: '💼', label: '내 구인',     menuKey: 'jobs' },
  { key: 'realestate', icon: '🏠', label: '내 부동산',   menuKey: 'realestate' },
  { key: 'ads',        icon: '📢', label: '광고 신청' },
  { key: 'calls',      icon: '📞', label: '통화내역',    menuKey: 'comms' },
  { key: 'bookmarks',  icon: '🔖', label: '북마크' },
  { key: 'elder',      icon: '🛡️', label: '안심',        menuKey: 'elder' },
  { key: 'payments',   icon: '💳', label: '결제' },
  { key: 'mybiz',      icon: '🏪', label: '내 업소',     menuKey: 'directory' },
  { key: 'resume',     icon: '📄', label: '이력서',      menuKey: 'jobs' },
]

const tabs = computed(() => {
  const mc = siteStore.menuConfig
  if (!mc || !Array.isArray(mc)) return allTabs
  const map = Object.fromEntries(mc.map(m => [m.key, m]))
  return allTabs.filter(t => {
    if (!t.menuKey) return true
    const menu = map[t.menuKey]
    if (!menu) return true
    if (menu.enabled === false) return false
    if (menu.admin_only && !auth.isAdmin) return false
    return true
  })
})

const loaded = reactive({})

function switchTab(key) {
  // 광고 신청은 독립 페이지로 이동
  const tabObj = tabs.value.find(t => t.key === key)
  if (tabObj?.link) { router.push(tabObj.link); return }
  tab.value = key
  if (!loaded[key]) { loadTab(key); loaded[key] = true }
  else if (key === 'messages') loadMessages() // 쪽지 탭은 매번 새로고침
}

// URL ?tab= 이 바뀌면 탭 자동 전환 (NavBar 벨 알림 → /dashboard?tab=messages 대응)
watch(() => route.query.tab, (newTab) => {
  if (newTab && newTab !== tab.value && tabs.value.find(t => t.key === newTab)) {
    switchTab(newTab)
  }
})

// 관리자에서 메뉴를 숨기면 (enabled=false / admin_only) 해당 탭이 사라지므로,
// 현재 선택 탭이 더 이상 노출되지 않으면 프로필로 자동 이동
watch(tabs, (newTabs) => {
  if (!newTabs.find(t => t.key === tab.value)) {
    tab.value = 'profile'
  }
})

function fmtDate(dt) {
  if (!dt) return ''
  const d = new Date(dt)
  const now = new Date()
  const y = d.getFullYear(), m = d.getMonth() + 1, day = d.getDate()
  const hh = String(d.getHours()).padStart(2, '0'), mm = String(d.getMinutes()).padStart(2, '0')
  if (y === now.getFullYear() && m === now.getMonth() + 1 && day === now.getDate()) return `오늘 ${hh}:${mm}`
  if (y === now.getFullYear()) return `${m}/${day} ${hh}:${mm}`
  return `${y}.${m}.${day} ${hh}:${mm}`
}

function fmtDateFull(dt) {
  if (!dt) return ''
  const d = new Date(dt)
  return `${d.getFullYear()}.${d.getMonth()+1}.${d.getDate()} ${String(d.getHours()).padStart(2,'0')}:${String(d.getMinutes()).padStart(2,'0')}`
}

function esc(str) { const d = document.createElement('div'); d.textContent = str; return d.innerHTML }

function printInvoice(p) {
  const w = window.open('', '_blank', 'width=600,height=700')
  if (!w) { showAlert('팝업이 차단되었습니다. 팝업 허용 후 다시 시도해주세요.', '오류'); return }
  w.document.write(`<!DOCTYPE html><html><head><title>Invoice #${esc(String(p.id))}</title>
<style>body{font-family:'Noto Sans KR',sans-serif;padding:40px;color:#1f2937}
.header{text-align:center;border-bottom:3px solid #f59e0b;padding-bottom:20px;margin-bottom:30px}
.logo{font-size:24px;font-weight:900;color:#92400e}
h1{font-size:18px;color:#374151;margin:10px 0 0}
table{width:100%;border-collapse:collapse;margin:20px 0}
td{padding:8px 0;border-bottom:1px solid #e5e7eb;font-size:14px}
td:last-child{text-align:right;font-weight:600}
.total{font-size:18px;font-weight:900;color:#d97706;border-top:2px solid #f59e0b}
.footer{text-align:center;margin-top:40px;font-size:11px;color:#9ca3af}
@media print{body{padding:20px}}</style></head><body>
<div class="header"><div class="logo">AwesomeKorean</div><h1>결제 인보이스</h1></div>
<table>
<tr><td>인보이스 번호</td><td>#${esc(String(p.id))}</td></tr>
<tr><td>결제일</td><td>${esc(fmtDateFull(p.created_at))}</td></tr>
<tr><td>상태</td><td>${p.status==='completed'?'완료':'대기'}</td></tr>
<tr><td>구매자</td><td>${esc(auth.user?.name||'')} (${esc(auth.user?.email||'')})</td></tr>
<tr><td>상품</td><td>포인트 ${esc(String(p.points_purchased?.toLocaleString()||0))}P</td></tr>
<tr class="total"><td>결제 금액</td><td>$${esc(String(p.amount))} USD</td></tr>
</table>
<div class="footer">AwesomeKorean — 미국 한인 커뮤니티<br>이 인보이스는 자동 생성되었습니다.</div>
<script>setTimeout(()=>window.print(),300)<\/script></body></html>`)
  w.document.close()
}

// ─── 프로필 ───
const pf = reactive({ name: '', nickname: '', bio: '', phone: '', address1: '', address2: '', city: '', state: '', zipcode: '', default_radius: 30, language: 'ko', allow_friend_request: true, allow_messages: true, allow_elder_service: false })
const pfMsg = ref(''); const pfMsgType = ref(''); const pfSaving = ref(false); const avatarMsg = ref('')
const pw = reactive({ current_password: '', password: '', password_confirmation: '' })
const pwMsg = ref(''); const pwMsgType = ref(''); const pwSaving = ref(false)

function loadProfile() {
  const u = auth.user
  if (u) {
    Object.assign(pf, { name: u.name, nickname: u.nickname, bio: u.bio, phone: u.phone ? formatPhone(u.phone) : '', address1: u.address1, address2: u.address2, city: u.city, state: u.state, zipcode: u.zipcode, default_radius: u.default_radius || 30, language: u.language || 'ko', allow_friend_request: u.allow_friend_request !== false, allow_messages: u.allow_messages !== false, allow_elder_service: !!u.allow_elder_service })
  }
}
// 친구요청 거절 → 쪽지도 자동 차단
watch(() => pf.allow_friend_request, (v) => { if (!v) pf.allow_messages = false })

function formatPhone(val) {
  const digits = val.replace(/\D/g, '').slice(0, 10)
  if (digits.length <= 3) return digits
  if (digits.length <= 6) return digits.slice(0, 3) + '-' + digits.slice(3)
  return digits.slice(0, 3) + '-' + digits.slice(3, 6) + '-' + digits.slice(6)
}
function onPhoneInput(e) {
  pf.phone = formatPhone(e.target.value)
}
async function saveProfile() {
  pfSaving.value = true; pfMsg.value = ''
  const phoneDigits = (pf.phone || '').replace(/\D/g, '')
  if (!phoneDigits || phoneDigits.length < 10) { pfMsg.value = '전화번호를 정확히 입력하세요 (10자리)'; pfMsgType.value = 'error'; pfSaving.value = false; return }
  try { await axios.put('/api/user/profile', pf); await auth.fetchUser(); localStorage.removeItem('sk_location'); pfMsg.value = '저장되었습니다!'; pfMsgType.value = 'success' }
  catch (e) { pfMsg.value = e.response?.data?.message || '저장 실패'; pfMsgType.value = 'error' }
  pfSaving.value = false
}
async function uploadAvatar(e) {
  const file = e.target.files[0]
  if (!file) return
  // 10MB 초과 즉시 차단
  if (file.size > 10 * 1024 * 1024) {
    avatarMsg.value = `파일이 너무 큽니다 (${(file.size/1024/1024).toFixed(1)}MB). 최대 10MB.`
    e.target.value = ''
    return
  }
  const fd = new FormData()
  fd.append('avatar', file)
  avatarMsg.value = '업로드 중...'
  try {
    const { data } = await axios.post('/api/user/avatar', fd, { headers: { 'Content-Type': 'multipart/form-data' } })
    await auth.fetchUser()
    avatarMsg.value = '변경됨! ✓'
    // 캐시 버스팅: 유저 아바타 URL에 타임스탬프 쿼리 추가
    if (auth.user && auth.user.avatar) {
      auth.user.avatar = auth.user.avatar.split('?')[0] + '?t=' + Date.now()
    }
  } catch (err) {
    const msg = err.response?.data?.errors?.avatar?.[0]
      || err.response?.data?.message
      || err.message
      || '업로드 실패'
    avatarMsg.value = '실패: ' + msg
  }
  // 같은 파일 재선택 가능하도록 리셋
  e.target.value = ''
}
async function changePw() {
  if (pw.password !== pw.password_confirmation) { pwMsg.value = '비밀번호가 일치하지 않습니다'; pwMsgType.value = 'error'; return }
  pwSaving.value = true; pwMsg.value = ''
  try { await axios.post('/api/change-password', pw); pwMsg.value = '변경되었습니다!'; pwMsgType.value = 'success'; pw.current_password = ''; pw.password = ''; pw.password_confirmation = '' }
  catch (e) { pwMsg.value = e.response?.data?.message || '변경 실패'; pwMsgType.value = 'error' }
  pwSaving.value = false
}

// ─── 포인트 ───
const ptBalance = ref(0); const ptHistory = ref([]); const spun = ref(false); const spinResult = ref(null)
const spinning = ref(false); const showRoulette = ref(false); const rouletteAngle = ref(0)
const rouletteSegments = [
  { points: 1, color: 'text-gray-600', bg: '#fef3c7' },
  { points: 5, color: 'text-amber-700', bg: '#fde68a' },
  { points: 3, color: 'text-gray-600', bg: '#fef9c3' },
  { points: 10, color: 'text-red-600', bg: '#fed7aa' },
  { points: 2, color: 'text-gray-600', bg: '#fef3c7' },
  { points: 7, color: 'text-amber-700', bg: '#fde68a' },
  { points: 1, color: 'text-gray-600', bg: '#fef9c3' },
  { points: 50, color: 'text-red-600', bg: '#fca5a5' },
]
const packages = ref([]); const selectedPkg = ref('')
const packagePromotion = ref(null)
const payModal = ref(false); const payPkg = ref(null); const payError = ref(''); const paying = ref(false)
let stripe = null; let cardElement = null; let clientSecret = null
async function loadPoints() {
  try { const { data } = await axios.get('/api/points/balance'); ptBalance.value = data.data?.points || data.points || auth.user?.points || 0; spun.value = data.daily_spin_done || false } catch { ptBalance.value = auth.user?.points || 0 }
  try { const { data } = await axios.get('/api/points/history'); ptHistory.value = data.data?.data || data.data || [] } catch {}
  // 패키지 로드 (할인 이벤트 반영된 가격)
  try {
    const { data } = await axios.get('/api/payments/packages')
    packages.value = (data.data || []).map(pkg => ({
      ...pkg,
      label: { pkg_starter:'스타터', pkg_basic:'베이직', pkg_standard:'스탠다드', pkg_pro:'프로', pkg_business:'비즈니스' }[pkg.key] || pkg.name,
    }))
  } catch {}
  // 현재 활성 할인 이벤트 (표시용)
  try {
    const { data } = await axios.get('/api/pricing-promotions/active')
    packagePromotion.value = data.data?.package || null
  } catch {}
}
async function buyPackage(pkg) {
  const ok = await showConfirm(`${pkg.label} (${(pkg.points+pkg.bonus).toLocaleString()}P) — $${pkg.price}\n구매하시겠습니까?`, '포인트 구매')
  if (!ok) return
  payPkg.value = pkg; payError.value = ''
  try {
    const { data } = await axios.post('/api/payments/create-intent', { package_key: pkg.key })
    clientSecret = data.data?.client_secret
    if (!clientSecret) { await showAlert('결제 생성 실패', '오류'); return }
    // Stripe.js 로드
    if (!stripe) {
      if (!window.Stripe) {
        const s = document.createElement('script'); s.src = 'https://js.stripe.com/v3/'; document.head.appendChild(s)
        await new Promise(r => s.onload = r)
      }
      const { data: sd } = await axios.get('/api/settings/points')
      const stripeKey = document.querySelector('meta[name="stripe-key"]')?.content
      if (!stripeKey) { await showAlert('Stripe 설정이 없습니다. 관리자에게 문의하세요.', '오류'); return }
      stripe = window.Stripe(stripeKey)
    }
    payModal.value = true
    // Stripe Elements 마운트
    setTimeout(() => {
      const elements = stripe.elements()
      cardElement = elements.create('card', { style: { base: { fontSize: '14px', color: '#1f2937' } } })
      cardElement.mount('#card-element')
    }, 100)
  } catch (e) {
    const msg = e.response?.data?.message || '결제 실패'
    await showAlert(msg, '오류')
  }
}

async function confirmPay() {
  if (!stripe || !cardElement || !clientSecret) return
  paying.value = true; payError.value = ''
  const { error, paymentIntent } = await stripe.confirmCardPayment(clientSecret, { payment_method: { card: cardElement } })
  if (error) { payError.value = error.message; paying.value = false; return }
  if (paymentIntent.status === 'succeeded') {
    try {
      const { data } = await axios.post('/api/payments/confirm', { payment_intent_id: paymentIntent.id })
      payModal.value = false; paying.value = false
      await showAlert(`${data.data?.points_added?.toLocaleString()}P가 지급되었습니다!`, '구매 완료')
      ptBalance.value = data.data?.total_points || ptBalance.value
      await auth.fetchUser()
      await loadPoints()
    } catch (e) { payError.value = e.response?.data?.message || '포인트 지급 실패' }
  }
  paying.value = false
}
async function startRoulette() {
  if (spinning.value || spun.value) return

  // 먼저 API 호출 (룰렛 보여주기 전에 성공 여부 확인)
  let won = 0
  try {
    const { data } = await axios.post('/api/points/daily-spin')
    won = data.data?.points_won || data.points || data.amount || 1
  } catch (e) {
    spun.value = true
    showAlert(e.response?.data?.message || '이미 출석 룰렛을 돌렸습니다', '출석체크')
    return
  }

  // API 성공 → 룰렛 애니메이션 시작
  spinning.value = true
  showRoulette.value = true
  spinResult.value = null
  rouletteAngle.value = 0

  await new Promise(r => setTimeout(r, 50)) // DOM 업데이트 대기

  const segIdx = rouletteSegments.findIndex(s => s.points === won) ?? 0
  const segAngle = 360 / rouletteSegments.length
  const targetAngle = 1800 + (360 - segIdx * segAngle - segAngle / 2)
  rouletteAngle.value = targetAngle

  // 4초 후 결과 표시
  setTimeout(() => {
    spinResult.value = won
    spun.value = true
    spinning.value = false
    ptBalance.value += won
    auth.fetchUser()
  }, 4200)
}

// ─── 쪽지 ───
const msgTab = ref('received'); const msgList = ref([]); const msgUnread = ref(0); const activeMsg = ref(null)
const replyTarget = ref(null); const replyText = ref(''); const replySending = ref(false)
async function loadMessages() {
  try { const { data } = await axios.get('/api/messages', { params: { tab: msgTab.value } }); msgList.value = data.data?.data || data.data || []; msgUnread.value = data.unread_count || 0 } catch {}
}
async function openMsg(m) {
  activeMsg.value = m
  if (msgTab.value === 'received' && !m.is_read) { m.is_read = true; msgUnread.value = Math.max(0, msgUnread.value - 1); axios.post(`/api/messages/${m.id}/read`).catch(() => {}) }
}
function replyMsg(m) { activeMsg.value = null; replyTarget.value = { id: m.sender_id || m.sender?.id, name: m.sender?.name }; replyText.value = '' }
async function sendReply() {
  if (!replyText.value.trim()) return; replySending.value = true
  try { await axios.post('/api/messages', { receiver_id: replyTarget.value.id, content: replyText.value.trim() }); replyTarget.value = null; replyText.value = '' }
  catch (e) { showAlert(e.response?.data?.message || '전송 실패', '오류') }
  replySending.value = false
}

async function deleteMsg(m) {
  try { await axios.delete(`/api/messages/${m.id}`); msgList.value = msgList.value.filter(x => x.id !== m.id) } catch {}
}

// ─── 통화 내역 ───
const callHistory = ref([])
async function loadCallHistory() {
  try { const { data } = await axios.get('/api/comms/calls/history'); callHistory.value = data || [] } catch {}
}

// ─── 내 글 ───
const myPosts = ref([])
async function loadPosts() { try { const { data } = await axios.get(`/api/users/${auth.user?.id}/posts`); myPosts.value = data.data?.data || data.data || [] } catch {} }

// ─── 내 광고 ───
const myAds = ref([])
const showAdForm = ref(false)
const adForm = reactive({ title:'', link_url:'', page:'home', position:'top', geo_scope:'all', geo_value:'', start_date:'', end_date:'' })
const adImage = ref(null)
async function loadMyAds() { try { const{data}=await axios.get('/api/banners/my'); myAds.value=data.data||[] }catch{} }
async function submitAd() {
  const fd = new FormData()
  Object.keys(adForm).forEach(k => fd.append(k, adForm[k]))
  if (adImage.value) fd.append('image', adImage.value)
  try {
    const{data}=await axios.post('/api/banners/apply', fd)
    showAlert(data.message, '광고 신청')
    showAdForm.value = false
    loadMyAds()
  } catch(e) { showAlert(e.response?.data?.message || '신청 실패', '오류') }
}

// ─── 내 장터 ───
const myMarketItems = ref([])
async function loadMyMarket() {
  try {
    const { data } = await axios.get('/api/market', { params: { user_id: auth.user?.id, per_page: 50 } })
    myMarketItems.value = data.data?.data || []
  } catch {}
}

// ─── 공통 상위노출 모달 (중고장터/부동산) ───
const resourcePromoTarget = ref(null)
const resourcePromoType = ref('market')
const resourcePromotion = reactive({ tier: 'none', days: 7 })
const resourcePromoRef = ref(null)
const resourcePromoSubmitting = ref(false)
const resourcePromoError = ref('')

function openMarketPromote(m) {
  resourcePromoTarget.value = m
  resourcePromoType.value = 'market'
  resourcePromotion.tier = 'none'
  resourcePromotion.days = 7
  resourcePromoError.value = ''
}
function openRePromote(r) {
  resourcePromoTarget.value = r
  resourcePromoType.value = 'realestate'
  resourcePromotion.tier = 'none'
  resourcePromotion.days = 7
  resourcePromoError.value = ''
}
async function submitResourcePromote() {
  if (!resourcePromoTarget.value || resourcePromotion.tier === 'none') return
  if (['state_plus','national'].includes(resourcePromotion.tier) && resourcePromoRef.value?.isSlotFull) {
    const t = resourcePromoRef.value?.nextSlotTimeFmt
    resourcePromoError.value = t ? `슬롯 만석. ${t} 이후 가능합니다.` : '슬롯 만석입니다.'
    return
  }
  resourcePromoSubmitting.value = true
  resourcePromoError.value = ''
  try {
    const url = resourcePromoType.value === 'realestate'
      ? `/api/realestate/${resourcePromoTarget.value.id}/promote`
      : `/api/market/${resourcePromoTarget.value.id}/promote`
    await axios.post(url, { tier: resourcePromotion.tier, days: resourcePromotion.days })
    if (resourcePromoType.value === 'market') await loadMyMarket()
    else await loadMyRealEstate()
    resourcePromoTarget.value = null
    showAlert('상위노출이 적용되었습니다', '완료')
  } catch (e) {
    resourcePromoError.value = e.response?.data?.message || '상위노출 적용 실패'
  }
  resourcePromoSubmitting.value = false
}

async function deleteMarketItem(m) {
  const ok = await showConfirm(`"${m.title}" 삭제하시겠습니까?`, '삭제')
  if (!ok) return
  try { await axios.delete(`/api/market/${m.id}`); myMarketItems.value = myMarketItems.value.filter(x => x.id !== m.id) }
  catch (e) { showAlert(e.response?.data?.message || '삭제 실패', '오류') }
}

// ─── 내 부동산 ───
const myRealEstate = ref([])
async function loadMyRealEstate() {
  try {
    const { data } = await axios.get('/api/realestate', { params: { user_id: auth.user?.id, per_page: 50 } })
    myRealEstate.value = data.data?.data || data.data || []
  } catch {}
}
async function deleteRealEstateItem(r) {
  const ok = await showConfirm(`"${r.title}" 삭제하시겠습니까?`, '삭제')
  if (!ok) return
  try { await axios.delete(`/api/realestate/${r.id}`); myRealEstate.value = myRealEstate.value.filter(x => x.id !== r.id) }
  catch (e) { showAlert(e.response?.data?.message || '삭제 실패', '오류') }
}

// ─── 내 구인구직 ───
const myJobs = ref([])
async function loadMyJobs() {
  try {
    const { data } = await axios.get('/api/my-jobs')
    myJobs.value = data.data || []
  } catch {}
}
async function toggleJobActive(j) {
  try {
    const fd = new FormData()
    fd.append('_method', 'PUT')
    fd.append('is_active', j.is_active ? '0' : '1')
    await axios.post(`/api/jobs/${j.id}`, fd)
    j.is_active = !j.is_active
  } catch (e) {
    showAlert(e.response?.data?.message || '상태 변경 실패', '오류')
  }
}
async function deleteJob(j) {
  const ok = await showConfirm(`"${j.title}" 공고를 삭제하시겠습니까?`, '삭제')
  if (!ok) return
  try {
    await axios.delete(`/api/jobs/${j.id}`)
    myJobs.value = myJobs.value.filter(x => x.id !== j.id)
  } catch (e) {
    showAlert(e.response?.data?.message || '삭제 실패', '오류')
  }
}

// ─── 북마크 ───
const bookmarks = ref([])
async function loadBookmarks() { try { const { data } = await axios.get('/api/bookmarks'); bookmarks.value = data.data?.data || data.data || [] } catch {} }
async function deleteBookmark(b) {
  const ok = await showConfirm('이 북마크를 삭제하시겠습니까?', '삭제')
  if (!ok) return
  try { await axios.post('/api/bookmarks', { bookmarkable_type: b.bookmarkable_type, bookmarkable_id: b.bookmarkable_id }); bookmarks.value = bookmarks.value.filter(x => x.id !== b.id) } catch {}
}
function goBookmark(b) {
  const typeMap = { 'App\\Models\\Post': '/community/free/', 'App\\Models\\MarketItem': '/market/', 'App\\Models\\JobPost': '/jobs/', 'App\\Models\\Business': '/directory/', 'App\\Models\\RecipePost': '/recipes/', 'App\\Models\\QaPost': '/qa/', 'App\\Models\\News': '/news/', 'App\\Models\\Event': '/events/' }
  const base = typeMap[b.bookmarkable_type] || '/community/free/'
  router.push(base + b.bookmarkable_id)
}

// ─── 안심서비스 ───
const wardSearch = ref(''); const wardSearching = ref(false); const wardResult = ref(null)
const elderWards = ref([])
const dayOptions = [{ key: 'mon', label: '월' },{ key: 'tue', label: '화' },{ key: 'wed', label: '수' },{ key: 'thu', label: '목' },{ key: 'fri', label: '금' },{ key: 'sat', label: '토' },{ key: 'sun', label: '일' }]

async function searchWard() {
  if (!wardSearch.value.trim()) return
  wardSearching.value = true; wardResult.value = null
  try {
    const { data } = await axios.post('/api/elder/search-ward', { email: wardSearch.value.trim() })
    wardResult.value = data
  } catch (e) {
    wardResult.value = { ok: false, message: e.response?.data?.message || '검색 실패' }
  }
  wardSearching.value = false
}
async function registerWard(wardId) {
  try {
    await axios.post('/api/elder/register-ward', { ward_user_id: wardId })
    wardResult.value = null; wardSearch.value = ''
    await loadElder()
  } catch (e) { showAlert(e.response?.data?.message || '등록 실패', '오류') }
}
async function loadElder() {
  try {
    const { data } = await axios.get('/api/elder/my-wards')
    elderWards.value = (data.data || []).map(w => ({
      ...w, _type: w.schedule?.type || 'random', _time_start: w.schedule?.time_start || '09:00', _time_end: w.schedule?.time_end || '18:00',
      _calls_per_day: w.schedule?.calls_per_day || 1, _days: w.schedule?.days || ['mon','tue','wed','thu','fri'],
      _times: w.schedule?.scheduled_times || ['09:00'], _schedMsg: '', _schedOk: false,
    }))
  } catch {}
}
async function saveSchedule(w) {
  try {
    await axios.post('/api/elder/save-schedule', {
      elder_guardian_id: w.id, type: w._type, time_start: w._time_start, time_end: w._time_end,
      calls_per_day: w._calls_per_day, days: w._days,
      scheduled_times: w._type === 'scheduled' ? w._times.filter(Boolean) : [],
    })
    w._schedMsg = '저장됨!'; w._schedOk = true
  } catch (e) { w._schedMsg = e.response?.data?.message || '실패'; w._schedOk = false }
}

// ─── 결제 ───
const payments = ref([])
async function loadPayments() { try { const { data } = await axios.get('/api/payments/history'); payments.value = data.data?.data || data.data || [] } catch {} }

// ─── 내 업소 ───
const myBizList = ref([])
const editBiz = ref(null)
const showMenuForm = ref(false)
const menuForm = ref({ name: '', description: '', price: 0, category: 'main', options: [] })

// 편집 모드 인라인 상위노출
const editBizPromotion = reactive({ tier: 'none', days: 7 })
const editBizPromoRef = ref(null)
const editBizPromoSubmitting = ref(false)
const editBizPromoMsg = ref(null)

// 편집 중 zipcode 변경시 city/state 자동
let editBizZipTimer = null
function onBizZipChange() {
  clearTimeout(editBizZipTimer)
  editBizZipTimer = setTimeout(async () => {
    const z = (editBiz.value?.zipcode || '').trim()
    if (!/^\d{5}$/.test(z)) return
    try {
      const r = await fetch(`https://api.zippopotam.us/us/${z}`)
      if (!r.ok) return
      const d = await r.json()
      const p = d.places?.[0]
      if (p && editBiz.value) {
        if (!editBiz.value.city) editBiz.value.city = p['place name'] || ''
        if (!editBiz.value.state) editBiz.value.state = p['state abbreviation'] || ''
      }
    } catch {}
  }, 400)
}

async function submitEditBizPromote() {
  if (!editBiz.value || editBizPromotion.tier === 'none') return
  if (['state_plus','national'].includes(editBizPromotion.tier) && editBizPromoRef.value?.isSlotFull) {
    editBizPromoMsg.value = { ok: false, text: '슬롯 만석 — 다른 티어를 선택하세요' }
    return
  }
  editBizPromoSubmitting.value = true
  editBizPromoMsg.value = null
  try {
    await axios.post(`/api/businesses/${editBiz.value.id}/promote`, {
      tier: editBizPromotion.tier, days: editBizPromotion.days,
    })
    editBizPromoMsg.value = { ok: true, text: '✅ 상위노출이 적용되었습니다' }
    await loadMyBiz()
    // 갱신된 editBiz 다시 로드
    const fresh = myBizList.value.find(b => b.id === editBiz.value.id)
    if (fresh) editBiz.value = { ...fresh }
    editBizPromotion.tier = 'none'
  } catch (e) {
    editBizPromoMsg.value = { ok: false, text: e.response?.data?.message || '상위노출 적용 실패' }
  }
  editBizPromoSubmitting.value = false
}

// 업소 상위노출 모달
const bizPromoTarget = ref(null)
const bizPromotion = reactive({ tier: 'none', days: 7 })
const bizPromoRef = ref(null)
const bizPromoSubmitting = ref(false)
const bizPromoError = ref('')
function openBizPromote(biz) {
  bizPromoTarget.value = biz
  bizPromotion.tier = 'none'
  bizPromotion.days = 7
  bizPromoError.value = ''
}
async function submitBizPromote() {
  if (!bizPromoTarget.value || bizPromotion.tier === 'none') return
  if (['state_plus','national'].includes(bizPromotion.tier) && bizPromoRef.value?.isSlotFull) {
    const t = bizPromoRef.value?.nextSlotTimeFmt
    bizPromoError.value = t ? `슬롯 만석. ${t} 이후 가능합니다.` : '슬롯 만석입니다.'
    return
  }
  bizPromoSubmitting.value = true
  bizPromoError.value = ''
  try {
    await axios.post(`/api/businesses/${bizPromoTarget.value.id}/promote`, {
      tier: bizPromotion.tier, days: bizPromotion.days,
    })
    await loadMyBiz()
    bizPromoTarget.value = null
    showAlert('상위노출이 적용되었습니다', '완료')
  } catch (e) {
    bizPromoError.value = e.response?.data?.message || '상위노출 적용 실패'
  }
  bizPromoSubmitting.value = false
}

async function loadMyBiz() {
  try { const { data } = await axios.get('/api/my-businesses'); myBizList.value = data.data || [] } catch {}
}
async function saveMyBiz() {
  try {
    await axios.put(`/api/my-businesses/${editBiz.value.id}`, editBiz.value)
    await loadMyBiz()
    editBiz.value = myBizList.value.find(b => b.id === editBiz.value.id) || null
    showAlert('저장되었습니다!', '업소 수정')
  } catch(e) { showAlert(e.response?.data?.message || '저장 실패', '오류') }
}
async function uploadBizPhotos(e) {
  const files = e.target.files
  if (!files.length) return
  const fd = new FormData()
  for (const f of files) fd.append('photos[]', f)
  try {
    const { data } = await axios.post(`/api/my-businesses/${editBiz.value.id}/photos`, fd)
    editBiz.value.images = data.data?.images || editBiz.value.images
    await loadMyBiz()
  } catch {}
}
async function saveMenu() {
  try {
    if (menuForm.value.id) {
      await axios.put(`/api/my-businesses/${editBiz.value.id}/menus/${menuForm.value.id}`, menuForm.value)
    } else {
      await axios.post(`/api/my-businesses/${editBiz.value.id}/menus`, menuForm.value)
    }
    showMenuForm.value = false
    menuForm.value = { name: '', description: '', price: 0, category: 'main', options: [] }
    await loadMyBiz()
    editBiz.value = myBizList.value.find(b => b.id === editBiz.value.id) || null
  } catch(e) { showAlert(e.response?.data?.message || '저장 실패', '오류') }
}
function editMenuItem(menu) {
  menuForm.value = { ...menu, options: (menu.options || []).map(o => ({...o})) }
  showMenuForm.value = true
}
async function deleteMenu(menu) {
  const ok = await showConfirm('메뉴를 삭제하시겠습니까?', '메뉴 삭제')
  if (!ok) return
  try {
    await axios.delete(`/api/my-businesses/${editBiz.value.id}/menus/${menu.id}`)
    await loadMyBiz()
    editBiz.value = myBizList.value.find(b => b.id === editBiz.value.id) || null
  } catch {}
}

// ─── 이력서 ───
const resume = reactive({ title:'', name:'', phone:'', email:'', introduction:'', desired_category:'', desired_type:'full', desired_salary:'', experience:'', education:'', skills:'', certifications:'', is_public:false })
const resumeMsg = ref(''); const resumeMsgType = ref(''); const resumeSaving = ref(false)

async function loadResume() {
  try {
    const { data } = await axios.get('/api/my-resume')
    if (data.data) Object.keys(resume).forEach(k => { if (data.data[k] !== undefined) resume[k] = data.data[k] })
  } catch {}
}

async function saveResume() {
  resumeSaving.value = true; resumeMsg.value = ''
  try {
    await axios.post('/api/resumes', { ...resume })
    resumeMsg.value = '이력서가 저장되었습니다.'; resumeMsgType.value = 'success'
  } catch (e) {
    resumeMsg.value = e.response?.data?.message || '저장 실패'; resumeMsgType.value = 'error'
  }
  resumeSaving.value = false
}

// ─── 탭 로딩 ───
function loadTab(key) {
  const loaders = { profile: loadProfile, points: loadPoints, messages: loadMessages, posts: loadPosts, market: loadMyMarket, jobs: loadMyJobs, realestate: loadMyRealEstate, ads: loadMyAds, calls: loadCallHistory, bookmarks: loadBookmarks, elder: loadElder, payments: loadPayments, mybiz: loadMyBiz, resume: loadResume }
  if (loaders[key]) loaders[key]()
}

// ─── 계정 ───
async function handleLogout() { await auth.logout(); router.push('/login') }
async function deleteAccount() {
  const c = await showPrompt('정말 탈퇴하시겠습니까?\n"탈퇴합니다"를 입력하세요.', '회원 탈퇴', '탈퇴합니다')
  if (c !== '탈퇴합니다') return
  try { await axios.delete('/api/user/delete'); await auth.logout(); router.push('/') } catch (e) { showAlert(e.response?.data?.message || '실패', '오류') }
}

let msgPoll = null
onMounted(() => {
  siteStore.load() // 탭 필터링에 메뉴 설정 필요 (NavBar 보다 먼저 마운트될 수 있어 명시)
  loadProfile(); loaded.profile = true
  if (tab.value !== 'profile') { loadTab(tab.value); loaded[tab.value] = true }
  // 쪽지 탭 열려있으면 15초마다 자동 갱신
  msgPoll = setInterval(() => { if (tab.value === 'messages') loadMessages() }, 60000)
})
onUnmounted(() => { if (msgPoll) clearInterval(msgPoll) })
</script>
