<template>
<div class="min-h-screen bg-gray-50">
  <div class="max-w-4xl mx-auto px-4 py-5">
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
          <div class="w-16 h-16 rounded-full bg-amber-400 text-white flex items-center justify-center text-2xl font-bold">{{ (auth.user?.name||'?')[0] }}</div>
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
          <input v-model="pf.phone" placeholder="전화번호를 입력하세요" class="w-full border rounded-lg px-3 py-2 text-sm" />
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
          <button @click="dailySpin" :disabled="spun" class="bg-amber-400 text-amber-900 font-bold px-4 py-2 rounded-lg text-sm hover:bg-amber-500 disabled:opacity-50 disabled:cursor-not-allowed">
            {{ spun ? '✅ 오늘 완료' : '🎰 출석 체크' }}
          </button>
        </div>
        <div v-if="spinResult" class="text-center text-sm font-bold text-green-600 mb-4">🎉 {{ spinResult }}P 적립!</div>
        <div class="text-3xl font-black text-amber-600 mb-4">{{ (auth.user?.points || ptBalance).toLocaleString() }}P</div>
        <!-- 포인트 구매 -->
        <div class="border-t pt-4 mt-4 mb-4">
          <h3 class="font-bold text-gray-700 text-sm mb-3">🛒 포인트 구매</h3>
          <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
            <button v-for="pkg in packages" :key="pkg.key" @click="buyPackage(pkg)"
              class="border-2 rounded-xl p-3 text-center hover:border-amber-400 hover:bg-amber-50 transition"
              :class="selectedPkg===pkg.key ? 'border-amber-400 bg-amber-50' : 'border-gray-200'">
              <div class="text-lg font-black text-amber-600">{{ (pkg.points + pkg.bonus).toLocaleString() }}P</div>
              <div v-if="pkg.bonus" class="text-[10px] text-green-600 font-bold">+{{ pkg.bonus.toLocaleString() }}P 보너스</div>
              <div class="text-sm font-bold text-gray-800 mt-1">${{ pkg.price }}</div>
              <div class="text-[9px] text-gray-400">{{ pkg.label }}</div>
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
          <RouterLink v-for="p in myPosts" :key="p.id" :to="'/community/post/'+p.id" class="block border rounded-lg p-3 hover:bg-amber-50/50 transition">
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

    <!-- ═══ 북마크 탭 ═══ -->
    <div v-else-if="tab==='bookmarks'" class="space-y-4">
      <div class="bg-white rounded-xl shadow-sm border p-5">
        <h2 class="font-bold text-gray-800 mb-4">🔖 북마크</h2>
        <div v-if="!bookmarks.length" class="text-sm text-gray-400 py-6 text-center">저장한 북마크가 없습니다</div>
        <div v-else class="space-y-2">
          <div v-for="b in bookmarks" :key="b.id" class="border rounded-lg p-3">
            <div class="text-sm font-bold text-gray-800">{{ b.bookmarkable?.title || b.bookmarkable_type + ' #' + b.bookmarkable_id }}</div>
            <div class="text-[10px] text-gray-400 mt-1">{{ fmtDate(b.created_at) }}</div>
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
import { ref, reactive, onMounted, onUnmounted, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import { useModal } from '../../composables/useModal'
import axios from 'axios'

const { showAlert, showConfirm, showPrompt } = useModal()

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()
const tab = ref(route.query.tab || 'profile')

const tabs = [
  { key: 'profile', icon: '📝', label: '프로필' },
  { key: 'points', icon: '💰', label: '포인트' },
  { key: 'messages', icon: '✉️', label: '쪽지' },
  { key: 'posts', icon: '📄', label: '내 글' },
  { key: 'bookmarks', icon: '🔖', label: '북마크' },
  { key: 'elder', icon: '🛡️', label: '안심' },
  { key: 'payments', icon: '💳', label: '결제' },
]

const loaded = reactive({})

function switchTab(key) {
  tab.value = key
  if (!loaded[key]) { loadTab(key); loaded[key] = true }
  else if (key === 'messages') loadMessages() // 쪽지 탭은 매번 새로고침
}

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

function printInvoice(p) {
  const w = window.open('', '_blank', 'width=600,height=700')
  w.document.write(`<!DOCTYPE html><html><head><title>Invoice #${p.id}</title>
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
<div class="header"><div class="logo">SomeKorean</div><h1>결제 인보이스</h1></div>
<table>
<tr><td>인보이스 번호</td><td>#${p.id}</td></tr>
<tr><td>결제일</td><td>${fmtDateFull(p.created_at)}</td></tr>
<tr><td>상태</td><td>${p.status==='completed'?'✅ 완료':'⏳ 대기'}</td></tr>
<tr><td>구매자</td><td>${auth.user?.name} (${auth.user?.email})</td></tr>
<tr><td>상품</td><td>포인트 ${p.points_purchased?.toLocaleString()}P</td></tr>
<tr class="total"><td>결제 금액</td><td>$${p.amount} USD</td></tr>
</table>
<div class="footer">SomeKorean — 미국 한인 커뮤니티<br>이 인보이스는 자동 생성되었습니다.</div>
<script>setTimeout(()=>window.print(),300)<\/script></body></html>`)
  w.document.close()
}

// ─── 프로필 ───
const pf = reactive({ name: '', nickname: '', bio: '', phone: '', address1: '', address2: '', city: '', state: '', zipcode: '', language: 'ko', allow_friend_request: true, allow_messages: true, allow_elder_service: false })
const pfMsg = ref(''); const pfMsgType = ref(''); const pfSaving = ref(false); const avatarMsg = ref('')
const pw = reactive({ current_password: '', password: '', password_confirmation: '' })
const pwMsg = ref(''); const pwMsgType = ref(''); const pwSaving = ref(false)

function loadProfile() {
  const u = auth.user
  if (u) Object.assign(pf, { name: u.name, nickname: u.nickname, bio: u.bio, phone: u.phone, address1: u.address1, address2: u.address2, city: u.city, state: u.state, zipcode: u.zipcode, language: u.language || 'ko', allow_friend_request: u.allow_friend_request !== false, allow_messages: u.allow_messages !== false, allow_elder_service: !!u.allow_elder_service })
}
// 친구요청 거절 → 쪽지도 자동 차단
watch(() => pf.allow_friend_request, (v) => { if (!v) pf.allow_messages = false })

async function saveProfile() {
  pfSaving.value = true; pfMsg.value = ''
  try { await axios.put('/api/user/profile', pf); await auth.fetchUser(); pfMsg.value = '저장되었습니다!'; pfMsgType.value = 'success' }
  catch (e) { pfMsg.value = e.response?.data?.message || '저장 실패'; pfMsgType.value = 'error' }
  pfSaving.value = false
}
async function uploadAvatar(e) {
  const file = e.target.files[0]; if (!file) return
  const fd = new FormData(); fd.append('avatar', file)
  try { await axios.post('/api/user/avatar', fd); await auth.fetchUser(); avatarMsg.value = '변경됨!' } catch { avatarMsg.value = '실패' }
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
const packages = ref([]); const selectedPkg = ref('')
const payModal = ref(false); const payPkg = ref(null); const payError = ref(''); const paying = ref(false)
let stripe = null; let cardElement = null; let clientSecret = null
async function loadPoints() {
  try { const { data } = await axios.get('/api/points/balance'); ptBalance.value = data.data?.points || data.points || auth.user?.points || 0; spun.value = data.daily_spin_done || false } catch { ptBalance.value = auth.user?.points || 0 }
  try { const { data } = await axios.get('/api/points/history'); ptHistory.value = data.data?.data || data.data || [] } catch {}
  // 패키지 로드
  try {
    const { data } = await axios.get('/api/settings/points')
    const ps = data.data || {}
    packages.value = Object.entries(ps).filter(([k]) => k.startsWith('pkg_')).map(([k, v]) => {
      const [price, points, bonus] = v.split('|').map(Number)
      return { key: k, label: { pkg_starter:'스타터', pkg_basic:'베이직', pkg_standard:'스탠다드', pkg_pro:'프로', pkg_business:'비즈니스' }[k] || k, price, points, bonus }
    })
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
      stripe = window.Stripe(document.querySelector('meta[name="stripe-key"]')?.content || 'pk_test_51THnmRPg1ubIggWTCCl54BiED9Q9ZE0ZvrUTtK5ddJBU3EcLCfZUKYWvPIM2acKqbDo1S76auDkfD0RlEeuIdFty00helczpYq')
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
async function dailySpin() {
  try { const { data } = await axios.post('/api/points/daily-spin'); spinResult.value = data.points || data.amount; spun.value = true; ptBalance.value += (data.points || data.amount || 0); await auth.fetchUser() }
  catch (e) { showAlert(e.response?.data?.message || '실패', '오류') }
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

// ─── 내 글 ───
const myPosts = ref([])
async function loadPosts() { try { const { data } = await axios.get(`/api/users/${auth.user?.id}/posts`); myPosts.value = data.data?.data || data.data || [] } catch {} }

// ─── 북마크 ───
const bookmarks = ref([])
async function loadBookmarks() { try { const { data } = await axios.get('/api/bookmarks'); bookmarks.value = data.data?.data || data.data || [] } catch {} }

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

// ─── 탭 로딩 ───
function loadTab(key) {
  const loaders = { profile: loadProfile, points: loadPoints, messages: loadMessages, posts: loadPosts, bookmarks: loadBookmarks, elder: loadElder, payments: loadPayments }
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
  loadProfile(); loaded.profile = true
  if (tab.value !== 'profile') { loadTab(tab.value); loaded[tab.value] = true }
  // 쪽지 탭 열려있으면 15초마다 자동 갱신
  msgPoll = setInterval(() => { if (tab.value === 'messages') loadMessages() }, 60000)
})
onUnmounted(() => { if (msgPoll) clearInterval(msgPoll) })
</script>
