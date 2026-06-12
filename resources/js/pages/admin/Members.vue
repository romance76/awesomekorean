<template>
<div>
  <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink mb-4">
    <span class="icon-chip w-9 h-9 bg-blue-50 text-blue-600"><AppIcon name="users" :size="20" /></span>
    회원관리
  </h1>

  <!-- 검색 + 필터 -->
  <div class="card p-3 mb-4">
    <div class="flex flex-wrap gap-2">
      <select v-model="roleFilter" @change="loadUsers()" class="input-soft w-auto px-3 py-1.5 text-xs">
        <option value="">전체 회원</option><option value="super_admin">슈퍼관리자</option><option value="admin">관리자</option><option value="moderator">운영자</option><option value="business">기업회원</option><option value="user">일반</option>
      </select>
      <select v-model="statusFilter" @change="loadUsers()" class="input-soft w-auto px-3 py-1.5 text-xs">
        <option value="">전체 상태</option><option value="active">활동</option><option value="banned">정지</option>
      </select>
      <form @submit.prevent="loadUsers()" class="flex-1 flex gap-1.5 min-w-[150px]">
        <input v-model="search" type="text" placeholder="이름/이메일/닉네임 검색..." class="flex-1 input-soft px-3 py-1.5" />
        <button type="submit" class="btn-primary px-3 py-1.5 text-xs">검색</button>
      </form>
    </div>
    <div class="text-[11px] text-ink-faint mt-1">전체 {{ totalUsers }}명</div>
  </div>

  <div v-if="loading" class="text-center py-8 text-ink-muted">로딩중...</div>
  <div v-else class="flex gap-4">
    <!-- 왼쪽: 회원 목록 -->
    <div :class="activeUser ? 'w-2/5' : 'w-full'">
      <div class="card overflow-hidden">
        <table class="w-full text-sm">
          <thead class="bg-gray-50 border-b border-gray-100"><tr>
            <th class="px-3 py-2 text-left text-xs text-ink-muted w-8">#</th>
            <th class="px-3 py-2 text-left text-xs text-ink-muted">이름</th>
            <th v-if="!activeUser" class="px-3 py-2 text-left text-xs text-ink-muted">이메일</th>
            <th class="px-3 py-2 text-xs text-ink-muted">역할</th>
            <th class="px-3 py-2 text-xs text-ink-muted">포인트</th>
            <th class="px-3 py-2 text-xs text-ink-muted">상태</th>
            <th class="px-3 py-2 text-xs text-ink-muted">가입</th>
          </tr></thead>
          <tbody>
            <tr v-for="u in users" :key="u.id"
              class="border-b border-gray-50 last:border-0 hover:bg-amber-50/40 cursor-pointer transition-colors"
              :class="activeUser?.id===u.id ? 'bg-amber-50 border-l-2 border-l-amber-500' : ''"
              @click="openUser(u)">
              <td class="px-3 py-2 text-xs text-ink-faint">{{ u.id }}</td>
              <td class="px-3 py-2.5">
                <div class="flex items-center gap-2">
                  <div class="w-7 h-7 rounded-full bg-amber-100 flex items-center justify-center text-xs font-bold text-amber-700">{{ (u.name||'?')[0] }}</div>
                  <div>
                    <div class="text-sm font-medium text-ink">{{ u.name }}</div>
                    <div v-if="activeUser" class="text-[11px] text-ink-faint">{{ u.email }}</div>
                  </div>
                </div>
              </td>
              <td v-if="!activeUser" class="px-3 py-2.5 text-xs text-ink-muted">{{ u.email }}</td>
              <td class="px-3 py-2.5 text-center"><span class="text-[11px] px-2 py-0.5 rounded-full font-bold" :class="roleClass(u.role)">{{ roleLabel(u.role) }}</span></td>
              <td class="px-3 py-2.5 text-center text-xs text-amber-600 font-semibold">{{ u.points||0 }}</td>
              <td class="px-3 py-2.5 text-center"><span :class="u.is_banned?'text-red-500':'text-green-500'" class="text-[11px] font-bold">{{ u.is_banned ? '정지' : '활동' }}</span></td>
              <td class="px-3 py-2.5 text-[11px] text-ink-faint">{{ u.created_at?.slice(2,10) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-if="lastPage > 1" class="flex justify-center gap-1.5 mt-4">
        <button v-for="pg in Math.min(lastPage, 10)" :key="pg" @click="loadUsers(pg)"
          class="w-8 h-8 rounded-lg text-sm transition-colors" :class="pg===page?'bg-amber-400 text-white font-bold':'text-ink-muted hover:bg-gray-100'">{{ pg }}</button>
      </div>
    </div>

    <!-- 오른쪽: 회원 상세 인라인 뷰 -->
    <div v-if="activeUser" class="w-3/5">
      <div class="card overflow-hidden sticky top-4">
        <!-- 헤더 -->
        <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between bg-amber-50">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-amber-400 text-white flex items-center justify-center text-lg font-bold">{{ (userData?.user?.name||'?')[0] }}</div>
            <div>
              <div class="font-bold text-ink">{{ userData?.user?.name }}</div>
              <div class="text-[11px] text-ink-muted">{{ userData?.user?.email }} · ID: {{ userData?.user?.id }}</div>
            </div>
          </div>
          <button @click="activeUser=null" class="text-ink-muted hover:text-ink transition-colors"><AppIcon name="x" :size="18" /></button>
        </div>

        <!-- 서브메뉴 탭 -->
        <div class="flex border-b border-gray-100 overflow-x-auto">
          <button v-for="t in userTabs" :key="t.key" @click="userTab=t.key"
            class="flex items-center gap-1 px-3.5 py-2.5 text-xs font-semibold border-b-2 -mb-px transition-colors whitespace-nowrap"
            :class="userTab===t.key?'border-amber-500 text-amber-700':'border-transparent text-ink-muted hover:text-ink-light'">
            <AppIcon :name="t.icon" :size="13" />{{ t.label }}</button>
        </div>

        <div v-if="userLoading" class="py-8 text-center text-ink-muted">로딩중...</div>
        <div v-else-if="userData" class="max-h-[70vh] overflow-y-auto">

          <!-- 📊 요약 (대시보드 스타일) -->
          <div v-show="userTab==='summary'" class="p-4 space-y-4">
            <!-- 핵심 통계 4개 -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
              <div class="bg-amber-50 rounded-xl p-3">
                <div class="text-xs text-amber-700 font-bold">보유 포인트</div>
                <div class="text-2xl font-black text-amber-600 mt-1">{{ userData.user.points || 0 }}<span class="text-xs">P</span></div>
                <div class="text-[11px] text-ink-muted mt-0.5">게임 {{ userData.user.game_points||0 }}P</div>
              </div>
              <div class="bg-green-50 rounded-xl p-3">
                <div class="text-xs text-green-700 font-bold">결제 누적</div>
                <div class="text-2xl font-black text-green-600 mt-1">${{ userData.summary?.total_spent_usd || 0 }}</div>
                <div class="text-[11px] text-ink-muted mt-0.5">{{ userData.payments?.length || 0 }}건</div>
              </div>
              <div class="bg-blue-50 rounded-xl p-3">
                <div class="text-xs text-blue-700 font-bold">게시물/활동</div>
                <div class="text-2xl font-black text-blue-600 mt-1">{{ userData.summary?.posts_total || 0 }}</div>
                <div class="text-[11px] text-ink-muted mt-0.5">전체 콘텐츠</div>
              </div>
              <div class="bg-purple-50 rounded-xl p-3">
                <div class="text-xs text-purple-700 font-bold">활성 광고</div>
                <div class="text-2xl font-black text-purple-600 mt-1">{{ userData.summary?.ads_active || 0 }}</div>
                <div class="text-[11px] text-ink-muted mt-0.5">총 {{ userData.banners?.length || 0 }}건</div>
              </div>
            </div>

            <!-- 포인트 earn/spend -->
            <div class="grid grid-cols-2 gap-3">
              <div class="border border-gray-100 rounded-xl p-3">
                <div class="text-xs text-ink-muted font-bold">누적 획득 포인트</div>
                <div class="text-lg font-black text-emerald-600 mt-1">+{{ (userData.summary?.total_points_earned || 0).toLocaleString() }}P</div>
              </div>
              <div class="border border-gray-100 rounded-xl p-3">
                <div class="text-xs text-ink-muted font-bold">누적 사용 포인트</div>
                <div class="text-lg font-black text-rose-600 mt-1">-{{ (userData.summary?.total_points_spent || 0).toLocaleString() }}P</div>
              </div>
            </div>

            <!-- 로그인/활동 정보 -->
            <div class="border border-gray-100 rounded-xl p-3">
              <div class="flex items-center gap-1.5 text-xs font-bold text-ink-light mb-2"><AppIcon name="clock" :size="13" />활동 내역</div>
              <div class="grid grid-cols-3 gap-2 text-xs">
                <div><span class="text-ink-faint">가입일:</span> <span class="font-semibold text-ink-light">{{ userData.user.created_at?.slice(0,10) || '-' }}</span></div>
                <div><span class="text-ink-faint">최근 로그인:</span> <span class="font-semibold text-ink-light">{{ userData.user.last_login_at?.slice(0,10) || '없음' }}</span></div>
                <div><span class="text-ink-faint">로그인 횟수:</span> <span class="font-semibold text-ink-light">{{ userData.user.login_count || 0 }}회</span></div>
              </div>
            </div>

            <!-- 콘텐츠 유형별 카운트 -->
            <div class="border border-gray-100 rounded-xl p-3">
              <div class="flex items-center gap-1.5 text-xs font-bold text-ink-light mb-2"><AppIcon name="list" :size="13" />콘텐츠 분포</div>
              <div class="grid grid-cols-2 lg:grid-cols-4 gap-2 text-xs">
                <div class="flex justify-between border-b border-gray-50 py-1"><span class="flex items-center gap-1 text-ink-light"><AppIcon name="list" :size="12" />게시글</span><span class="font-bold text-amber-600">{{ userData.posts?.length || 0 }}</span></div>
                <div class="flex justify-between border-b border-gray-50 py-1"><span class="flex items-center gap-1 text-ink-light"><AppIcon name="briefcase" :size="12" />구인</span><span class="font-bold text-amber-600">{{ userData.jobs?.length || 0 }}</span></div>
                <div class="flex justify-between border-b border-gray-50 py-1"><span class="flex items-center gap-1 text-ink-light"><AppIcon name="shopping-cart" :size="12" />장터</span><span class="font-bold text-amber-600">{{ userData.market?.length || 0 }}</span></div>
                <div class="flex justify-between border-b border-gray-50 py-1"><span class="flex items-center gap-1 text-ink-light"><AppIcon name="building" :size="12" />부동산</span><span class="font-bold text-amber-600">{{ userData.realestate?.length || 0 }}</span></div>
                <div class="flex justify-between border-b border-gray-50 py-1"><span class="flex items-center gap-1 text-ink-light"><AppIcon name="users" :size="12" />동호회</span><span class="font-bold text-amber-600">{{ userData.clubs?.length || 0 }}</span></div>
                <div class="flex justify-between border-b border-gray-50 py-1"><span class="flex items-center gap-1 text-ink-light"><AppIcon name="calendar" :size="12" />이벤트</span><span class="font-bold text-amber-600">{{ userData.events?.length || 0 }}</span></div>
                <div class="flex justify-between border-b border-gray-50 py-1"><span class="flex items-center gap-1 text-ink-light"><AppIcon name="help-circle" :size="12" />Q&A</span><span class="font-bold text-amber-600">{{ userData.qa?.length || 0 }}</span></div>
                <div class="flex justify-between border-b border-gray-50 py-1"><span class="flex items-center gap-1 text-ink-light"><AppIcon name="message-circle" :size="12" />댓글</span><span class="font-bold text-amber-600">{{ userData.comments?.length || 0 }}</span></div>
              </div>
            </div>

            <!-- 빠른 액션 -->
            <div class="flex gap-2 flex-wrap">
              <button @click="userTab='manage'" class="btn-primary px-3 py-1.5 text-xs"><AppIcon name="settings" :size="13" />관리 →</button>
              <button @click="impersonateUser" v-if="auth.user?.role === 'super_admin' && auth.user?.id !== userData.user.id"
                class="inline-flex items-center gap-1.5 bg-purple-500 text-white font-bold px-3 py-1.5 rounded-xl text-xs hover:bg-purple-600 transition-colors">
                <AppIcon name="log-in" :size="13" />이 회원으로 로그인
              </button>
            </div>
          </div>

          <!-- 기본 정보 -->
          <div v-show="userTab==='info'" class="p-4">
            <div class="grid grid-cols-2 gap-3">
              <div><label class="input-label !text-xs !mb-0.5">이름</label><input v-model="userData.user.name" class="input-soft px-3 py-1.5" /></div>
              <div><label class="input-label !text-xs !mb-0.5">닉네임</label><input v-model="userData.user.nickname" class="input-soft px-3 py-1.5" /></div>
              <div><label class="input-label !text-xs !mb-0.5">이메일</label><input v-model="userData.user.email" class="input-soft px-3 py-1.5" /></div>
              <div><label class="input-label !text-xs !mb-0.5">전화</label><input v-model="userData.user.phone" class="input-soft px-3 py-1.5" /></div>
              <div><label class="input-label !text-xs !mb-0.5">도시</label><input v-model="userData.user.city" class="input-soft px-3 py-1.5" /></div>
              <div><label class="input-label !text-xs !mb-0.5">주</label><input v-model="userData.user.state" class="input-soft px-3 py-1.5" /></div>
              <div><label class="input-label !text-xs !mb-0.5">역할</label>
                <select v-model="userData.user.role" class="input-soft px-3 py-1.5">
                  <option value="user">일반회원</option>
                  <option value="business">기업회원</option>
                  <option value="moderator">운영자</option>
                  <option value="admin">관리자</option>
                  <option value="super_admin">슈퍼관리자</option>
                </select></div>
              <div><label class="input-label !text-xs !mb-0.5">상태</label>
                <select v-model="userData.user.is_banned" class="input-soft px-3 py-1.5"><option :value="false">활동</option><option :value="true">정지</option></select></div>
              <div><label class="input-label !text-xs !mb-0.5">친구요청</label>
                <select v-model="friendRequestVal" @change="userData.user.allow_friend_request = friendRequestVal === 'true'" class="input-soft px-3 py-1.5"><option value="true">수락</option><option value="false">거절</option></select></div>
            </div>
            <div class="mt-2"><label class="input-label !text-xs !mb-0.5">소개</label><textarea v-model="userData.user.bio" rows="2" class="input-soft px-3 py-1.5"></textarea></div>
            <div class="mt-2 flex items-center gap-4 text-[11px] text-ink-faint">
              <span>가입: {{ userData.user.created_at?.slice(0,10) }}</span>
              <span>최근: {{ userData.user.last_login_at?.slice(0,10) || '없음' }}</span>
              <span>로그인 {{ userData.user.login_count || 0 }}회</span>
            </div>
            <button @click="saveUser" class="btn-primary mt-3 px-5 py-2">저장</button>
          </div>

          <!-- 포인트 -->
          <div v-show="userTab==='points'" class="p-4">
            <div class="flex items-center gap-3 mb-3">
              <div class="bg-amber-50 rounded-xl px-4 py-2">
                <div class="text-xl font-black text-amber-600">{{ userData.user.points||0 }}P</div>
                <div class="text-[11px] text-ink-muted">보유 포인트</div>
              </div>
              <div class="bg-blue-50 rounded-xl px-4 py-2">
                <div class="text-xl font-black text-blue-600">{{ userData.user.game_points||0 }}P</div>
                <div class="text-[11px] text-ink-muted">게임 포인트</div>
              </div>
              <div class="flex gap-2 ml-auto">
                <input v-model.number="addPoints" type="number" placeholder="포인트" class="input-soft w-20 px-2 py-1.5" />
                <button @click="givePoints" class="bg-green-500 text-white px-3 py-1.5 rounded-lg text-xs font-bold hover:bg-green-600 transition-colors">지급</button>
              </div>
            </div>
            <div v-if="!userData.points?.length" class="py-4 text-center text-ink-muted text-sm">포인트 내역 없음</div>
            <div v-for="pt in userData.points" :key="pt.id" class="py-2 border-b border-gray-50 flex justify-between text-xs">
              <span class="text-ink-muted">{{ pt.created_at?.slice(0,10) }}</span>
              <span class="text-ink-light">{{ pt.reason }}</span>
              <span :class="pt.amount>0?'text-green-600':'text-red-600'" class="font-bold">{{ pt.amount>0?'+':'' }}{{ pt.amount }}P</span>
            </div>
          </div>

          <!-- 결제 -->
          <div v-show="userTab==='payments'" class="p-4">
            <div v-if="!userData.payments?.length" class="py-4 text-center text-ink-muted text-sm">결제 내역 없음</div>
            <div v-for="p in userData.payments" :key="p.id" class="py-2 border-b border-gray-50 flex justify-between text-xs text-ink-light">
              <span>{{ p.created_at?.slice(0,10) }}</span><span>${{ p.amount }}</span><span class="text-amber-600 font-bold">+{{ p.points }}P</span><span>{{ p.status }}</span>
            </div>
          </div>

          <!-- 📝 콘텐츠 (전체 유형) -->
          <div v-show="userTab==='content'" class="p-4 space-y-4">
            <!-- 게시글 -->
            <div v-if="userData.posts?.length">
              <div class="flex items-center gap-1.5 text-xs font-bold text-amber-700 mb-2"><AppIcon name="list" :size="13" />커뮤니티 게시글 ({{ userData.posts.length }})</div>
              <div v-for="post in userData.posts" :key="post.id"
                class="py-2 border-b border-gray-50 flex items-center gap-2 hover:bg-amber-50/40 transition-colors">
                <div class="flex-1 min-w-0">
                  <div class="text-sm font-medium text-ink truncate">{{ post.title }}</div>
                  <div class="text-[11px] text-ink-faint">{{ post.created_at?.slice(0,10) }} · {{ post.view_count||0 }}회 · ❤️ {{ post.like_count||0 }}</div>
                </div>
                <a :href="`/board/${post.board_id}/${post.id}`" target="_blank" class="text-[11px] bg-gray-100 hover:bg-gray-200 text-ink-light px-2 py-1 rounded-lg transition-colors">보기</a>
              </div>
            </div>
            <!-- 구인 -->
            <div v-if="userData.jobs?.length">
              <div class="flex items-center gap-1.5 text-xs font-bold text-indigo-700 mb-2"><AppIcon name="briefcase" :size="13" />구인/구직 ({{ userData.jobs.length }})</div>
              <div v-for="j in userData.jobs" :key="j.id" class="py-2 border-b border-gray-50 flex items-center gap-2 hover:bg-indigo-50/30 transition-colors">
                <div class="flex-1 min-w-0">
                  <div class="text-sm font-medium text-ink truncate">{{ j.title }}</div>
                  <div class="text-[11px] text-ink-faint">{{ j.company }} · {{ j.city }}, {{ j.state }} · {{ j.created_at?.slice(0,10) }}</div>
                </div>
                <a :href="`/jobs/${j.id}`" target="_blank" class="text-[11px] bg-gray-100 hover:bg-gray-200 text-ink-light px-2 py-1 rounded-lg transition-colors">보기</a>
              </div>
            </div>
            <!-- 장터 -->
            <div v-if="userData.market?.length">
              <div class="flex items-center gap-1.5 text-xs font-bold text-orange-700 mb-2"><AppIcon name="shopping-cart" :size="13" />중고장터 ({{ userData.market.length }})</div>
              <div v-for="m in userData.market" :key="m.id" class="py-2 border-b border-gray-50 flex items-center gap-2 hover:bg-orange-50/30 transition-colors">
                <div class="flex-1 min-w-0">
                  <div class="text-sm font-medium text-ink truncate">{{ m.title }}</div>
                  <div class="text-[11px] text-ink-faint">${{ Number(m.price||0).toLocaleString() }} · {{ m.status }} · {{ m.created_at?.slice(0,10) }}</div>
                </div>
                <a :href="`/market/${m.id}`" target="_blank" class="text-[11px] bg-gray-100 hover:bg-gray-200 text-ink-light px-2 py-1 rounded-lg transition-colors">보기</a>
              </div>
            </div>
            <!-- 부동산 -->
            <div v-if="userData.realestate?.length">
              <div class="flex items-center gap-1.5 text-xs font-bold text-blue-700 mb-2"><AppIcon name="building" :size="13" />부동산 ({{ userData.realestate.length }})</div>
              <div v-for="r in userData.realestate" :key="r.id" class="py-2 border-b border-gray-50 flex items-center gap-2 hover:bg-blue-50/30 transition-colors">
                <div class="flex-1 min-w-0">
                  <div class="text-sm font-medium text-ink truncate">{{ r.title }}</div>
                  <div class="text-[11px] text-ink-faint">${{ Number(r.price||0).toLocaleString() }} · {{ {rent:'렌트',sale:'매매',roommate:'룸메'}[r.type] }} · {{ r.city }}, {{ r.state }}</div>
                </div>
                <a :href="`/realestate/${r.id}`" target="_blank" class="text-[11px] bg-gray-100 hover:bg-gray-200 text-ink-light px-2 py-1 rounded-lg transition-colors">보기</a>
              </div>
            </div>
            <!-- 동호회 -->
            <div v-if="userData.clubs?.length">
              <div class="flex items-center gap-1.5 text-xs font-bold text-green-700 mb-2"><AppIcon name="users" :size="13" />동호회 ({{ userData.clubs.length }})</div>
              <div v-for="cl in userData.clubs" :key="cl.id" class="py-2 border-b border-gray-50 flex items-center gap-2 hover:bg-green-50/30 transition-colors">
                <div class="flex-1 min-w-0">
                  <div class="text-sm font-medium text-ink truncate">{{ cl.name }}</div>
                  <div class="text-[11px] text-ink-faint">{{ cl.category }} · {{ cl.member_count||0 }}명 · {{ cl.created_at?.slice(0,10) }}</div>
                </div>
                <a :href="`/clubs/${cl.id}`" target="_blank" class="text-[11px] bg-gray-100 hover:bg-gray-200 text-ink-light px-2 py-1 rounded-lg transition-colors">보기</a>
              </div>
            </div>
            <!-- 이벤트 -->
            <div v-if="userData.events?.length">
              <div class="flex items-center gap-1.5 text-xs font-bold text-pink-700 mb-2"><AppIcon name="calendar" :size="13" />이벤트 ({{ userData.events.length }})</div>
              <div v-for="ev in userData.events" :key="ev.id" class="py-2 border-b border-gray-50 flex items-center gap-2 hover:bg-pink-50/30 transition-colors">
                <div class="flex-1 min-w-0">
                  <div class="text-sm font-medium text-ink truncate">{{ ev.title }}</div>
                  <div class="text-[11px] text-ink-faint">{{ ev.event_date?.slice(0,10) }} · {{ ev.city }}, {{ ev.state }}</div>
                </div>
                <a :href="`/events/${ev.id}`" target="_blank" class="text-[11px] bg-gray-100 hover:bg-gray-200 text-ink-light px-2 py-1 rounded-lg transition-colors">보기</a>
              </div>
            </div>
            <!-- Q&A -->
            <div v-if="userData.qa?.length">
              <div class="flex items-center gap-1.5 text-xs font-bold text-violet-700 mb-2"><AppIcon name="help-circle" :size="13" />Q&A ({{ userData.qa.length }})</div>
              <div v-for="q in userData.qa" :key="q.id" class="py-2 border-b border-gray-50 flex items-center gap-2 hover:bg-violet-50/30 transition-colors">
                <div class="flex-1 min-w-0">
                  <div class="text-sm font-medium text-ink truncate">{{ q.title }}</div>
                  <div class="text-[11px] text-ink-faint">{{ q.category }} · ❤️ {{ q.like_count||0 }} · {{ q.created_at?.slice(0,10) }}</div>
                </div>
                <a :href="`/qa/${q.id}`" target="_blank" class="text-[11px] bg-gray-100 hover:bg-gray-200 text-ink-light px-2 py-1 rounded-lg transition-colors">보기</a>
              </div>
            </div>

            <div v-if="!hasAnyContent" class="py-8 text-center text-ink-muted text-sm">작성한 콘텐츠가 없습니다</div>
          </div>

          <!-- 📢 광고 (배너) -->
          <div v-show="userTab==='ads'" class="p-4">
            <div v-if="!userData.banners?.length" class="py-4 text-center text-ink-muted text-sm">광고 내역 없음</div>
            <div v-for="b in userData.banners" :key="b.id" class="py-2 border-b border-gray-50">
              <div class="flex items-center gap-2">
                <img v-if="b.image_url" :src="b.image_url" class="w-16 h-12 rounded-lg object-cover border border-gray-100" @error="$event.target.style.display='none'" />
                <div class="flex-1 min-w-0">
                  <div class="text-sm font-medium text-ink truncate">{{ b.title }}</div>
                  <div class="text-[11px] text-ink-faint flex items-center gap-2 flex-wrap">
                    <span :class="{ active:'badge-green', pending:'badge-primary', rejected:'badge-red', paused:'badge-gray', expired:'badge-gray' }[b.status] || 'badge-gray'">{{ b.status }}</span>
                    <span>👁 {{ b.impressions||0 }}</span>
                    <span>🖱 {{ b.clicks||0 }}</span>
                    <span>{{ b.created_at?.slice(0,10) }}</span>
                    <span v-if="b.expires_at">~{{ b.expires_at?.slice(0,10) }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- 🚨 신고 기록 -->
          <div v-show="userTab==='reports'" class="p-4">
            <div class="text-xs text-ink-muted mb-2">이 회원이 제출한 신고 {{ userData.reports_filed?.length || 0 }}건</div>
            <div v-if="!userData.reports_filed?.length" class="py-4 text-center text-ink-muted text-sm">신고 내역 없음</div>
            <div v-for="r in userData.reports_filed" :key="r.id" class="py-2 border-b border-gray-50">
              <div class="flex items-center justify-between gap-2">
                <div class="flex-1 min-w-0">
                  <div class="text-sm font-medium text-ink">
                    <span class="mr-1"
                      :class="{ pending:'badge-primary', resolved:'badge-green', dismissed:'badge-gray' }[r.status] || 'badge-gray'">
                      {{ r.status }}
                    </span>
                    {{ r.reason }}
                  </div>
                  <div class="text-xs text-ink-muted truncate">{{ r.content }}</div>
                  <div class="text-[11px] text-ink-faint">{{ shortType(r.reportable_type) }} #{{ r.reportable_id }} · {{ r.created_at?.slice(0,10) }}</div>
                </div>
              </div>
            </div>
          </div>

          <!-- 댓글 -->
          <div v-show="userTab==='comments'" class="p-4">
            <div v-if="!userData.comments?.length" class="py-4 text-center text-ink-muted text-sm">댓글 없음</div>
            <div v-for="c in userData.comments" :key="c.id" class="py-2 border-b border-gray-50">
              <div class="text-sm text-ink-light">{{ c.content }}</div>
              <div class="text-[11px] text-ink-faint">{{ c.created_at?.slice(0,10) }}</div>
            </div>
          </div>

          <!-- 관리 -->
          <div v-show="userTab==='manage'" class="p-4 space-y-4">
            <!-- 권한 설정 -->
            <div>
              <h3 class="flex items-center gap-1.5 text-sm font-bold text-ink mb-2"><AppIcon name="key" :size="14" />권한 설정</h3>
              <div class="grid grid-cols-1 gap-2">
                <label v-for="r in roleOptions" :key="r.value"
                  class="flex items-center gap-3 p-3 rounded-xl border cursor-pointer transition-colors"
                  :class="userData.user.role===r.value ? 'border-amber-400 bg-amber-50' : 'border-gray-200 hover:bg-gray-50'"
                  @click="userData.user.role=r.value">
                  <div class="icon-chip w-8 h-8" :class="r.bgClass"><AppIcon :name="r.icon" :size="16" /></div>
                  <div class="flex-1">
                    <div class="text-sm font-semibold text-ink">{{ r.label }}</div>
                    <div class="text-[11px] text-ink-faint">{{ r.desc }}</div>
                  </div>
                  <div class="w-5 h-5 rounded-full border-2 flex items-center justify-center" :class="userData.user.role===r.value ? 'border-amber-500 bg-amber-500' : 'border-gray-300'">
                    <div v-if="userData.user.role===r.value" class="w-2 h-2 rounded-full bg-white"></div>
                  </div>
                </label>
              </div>
              <button @click="saveUser" class="btn-primary mt-3 px-5 py-2">권한 저장</button>
            </div>

            <!-- 계정 정지 -->
            <div class="border-t border-gray-100 pt-4">
              <h3 class="flex items-center gap-1.5 text-sm font-bold text-ink mb-2"><AppIcon name="alert-circle" :size="14" />계정 정지</h3>
              <div class="flex gap-2 items-end">
                <div class="flex-1">
                  <label class="input-label !text-xs !mb-0.5">정지 사유</label>
                  <input v-model="banReason" type="text" placeholder="정지 사유 입력" class="input-soft px-3 py-1.5" />
                </div>
                <button v-if="!userData.user.is_banned" @click="banCurrentUser" class="inline-flex items-center gap-1 bg-red-500 text-white px-4 py-2 rounded-xl text-sm font-bold hover:bg-red-600 transition-colors flex-shrink-0"><AppIcon name="x" :size="14" />정지</button>
                <button v-else @click="unbanCurrentUser" class="inline-flex items-center gap-1 bg-green-500 text-white px-4 py-2 rounded-xl text-sm font-bold hover:bg-green-600 transition-colors flex-shrink-0"><AppIcon name="check" :size="14" />해제</button>
              </div>
              <div v-if="userData.user.is_banned" class="mt-2 text-sm text-red-500 bg-red-50 rounded-xl px-3 py-2">현재 정지됨 — 사유: {{ userData.user.ban_reason || '없음' }}</div>
            </div>

            <!-- 비밀번호 초기화 -->
            <div class="border-t border-gray-100 pt-4">
              <h3 class="flex items-center gap-1.5 text-sm font-bold text-ink mb-2"><AppIcon name="lock" :size="14" />비밀번호 초기화</h3>
              <div class="flex gap-2 items-end">
                <div class="flex-1">
                  <label class="input-label !text-xs !mb-0.5">새 비밀번호 (비우면 자동 생성)</label>
                  <input v-model="newPassword" type="text" placeholder="랜덤 12자 자동 생성" class="input-soft px-3 py-1.5 font-mono" />
                </div>
                <button @click="resetPassword" class="bg-blue-500 text-white px-4 py-2 rounded-xl text-sm font-bold hover:bg-blue-600 transition-colors flex-shrink-0">초기화</button>
              </div>
              <div v-if="tempPassword" class="mt-2 text-sm bg-blue-50 text-blue-700 rounded-xl px-3 py-2 font-mono">
                새 비밀번호: <strong>{{ tempPassword }}</strong> <button @click="copyPassword" class="ml-2 text-[11px] bg-white px-2 py-0.5 rounded-lg border border-blue-200 hover:bg-blue-100 transition-colors">복사</button>
              </div>
            </div>

            <!-- Super Admin 전용: Impersonate -->
            <div v-if="auth.user?.role === 'super_admin' && auth.user?.id !== userData.user.id" class="border-t border-gray-100 pt-4">
              <h3 class="flex items-center gap-1.5 text-sm font-bold text-ink mb-2"><AppIcon name="log-in" :size="14" />이 회원으로 로그인 (Impersonate)</h3>
              <p class="text-[11px] text-ink-muted mb-2">이 회원의 계정으로 로그인하여 그대로 사이트를 볼 수 있습니다. 활동이 로그에 기록됩니다.</p>
              <button @click="impersonateUser" class="inline-flex items-center gap-1.5 bg-purple-500 text-white px-4 py-2 rounded-xl text-sm font-bold hover:bg-purple-600 transition-colors"><AppIcon name="log-in" :size="14" />이 회원으로 로그인</button>
            </div>

            <!-- Super Admin 전용: 계정 삭제 -->
            <div v-if="auth.user?.role === 'super_admin' && auth.user?.id !== userData.user.id && userData.user.role !== 'super_admin'" class="border-t border-gray-100 pt-4 pb-2">
              <h3 class="flex items-center gap-1.5 text-sm font-bold text-red-700 mb-2"><AppIcon name="alert-circle" :size="14" />계정 삭제</h3>
              <p class="text-[11px] text-ink-muted mb-2">계정이 정지되고 이메일이 비활성화 처리됩니다. 슈퍼관리자가 직접 DB에서 복구 가능합니다.</p>
              <button @click="deleteAccount" class="inline-flex items-center gap-1.5 bg-red-600 text-white px-4 py-2 rounded-xl text-sm font-bold hover:bg-red-700 transition-colors"><AppIcon name="trash" :size="14" />계정 삭제</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
</template>
<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import { useAuthStore } from '../../stores/auth'
import { useRouter } from 'vue-router'
import AppIcon from '../../components/AppIcon.vue'

const auth = useAuthStore()
const router = useRouter()

const users = ref([]); const loading = ref(true)
const search = ref(''); const roleFilter = ref(''); const statusFilter = ref('')
const page = ref(1); const lastPage = ref(1); const totalUsers = ref(0)

const activeUser = ref(null); const userData = ref(null); const userLoading = ref(false)
const userTab = ref('summary'); const banReason = ref(''); const addPoints = ref(0)
const friendRequestVal = ref('true')
const newPassword = ref(''); const tempPassword = ref('')

const hasAnyContent = computed(() => {
  const d = userData.value
  if (!d) return false
  return (d.posts?.length || d.jobs?.length || d.market?.length || d.realestate?.length
    || d.clubs?.length || d.events?.length || d.qa?.length) > 0
})

function shortType(t) {
  if (!t) return ''
  return String(t).split(/[\\\\/]/).pop() || t
}

const roleOptions = [
  { value: 'super_admin', label: '슈퍼관리자', icon: 'star', bgClass: 'bg-red-50 text-red-600', desc: '모든 권한 (사이트 설정, 회원 관리, 콘텐츠 삭제)' },
  { value: 'admin', label: '관리자', icon: 'settings', bgClass: 'bg-violet-50 text-violet-600', desc: '콘텐츠 관리, 회원 정지, 신고 처리' },
  { value: 'moderator', label: '운영자', icon: 'shield', bgClass: 'bg-blue-50 text-blue-600', desc: '게시글/댓글 관리, 신고 처리' },
  { value: 'business', label: '기업회원', icon: 'building', bgClass: 'bg-emerald-50 text-emerald-600', desc: '업소록 등록, 구인 등록, 프로모션 가능' },
  { value: 'user', label: '일반회원', icon: 'user', bgClass: 'bg-gray-100 text-ink-light', desc: '기본 회원 (글쓰기, 댓글, 게임 참여)' },
]

function roleLabel(role) {
  return { super_admin:'슈퍼관리자', admin:'관리자', moderator:'운영자', business:'기업회원', user:'일반' }[role] || role
}
function roleClass(role) {
  return { super_admin:'bg-red-100 text-red-700', admin:'bg-purple-100 text-purple-700', moderator:'bg-blue-100 text-blue-700', business:'bg-green-100 text-green-700', user:'bg-gray-100 text-gray-600' }[role] || 'bg-gray-100 text-gray-600'
}

const userTabs = [
  { key: 'summary', icon: 'chart-bar', label: '요약' },
  { key: 'info', icon: 'user', label: '정보' },
  { key: 'points', icon: 'coins', label: '포인트' },
  { key: 'payments', icon: 'wallet', label: '결제' },
  { key: 'content', icon: 'edit', label: '콘텐츠' },
  { key: 'ads', icon: 'megaphone', label: '광고' },
  { key: 'comments', icon: 'message-circle', label: '댓글' },
  { key: 'reports', icon: 'alert-circle', label: '신고' },
  { key: 'manage', icon: 'settings', label: '관리' },
]

async function loadUsers(p = 1) {
  loading.value = true; page.value = p
  const params = { page: p }
  if (search.value) params.search = search.value
  if (roleFilter.value) params.role = roleFilter.value
  try {
    const { data } = await axios.get('/api/admin/users', { params })
    users.value = data.data?.data || []
    lastPage.value = data.data?.last_page || 1
    totalUsers.value = data.data?.total || 0
  } catch {}
  loading.value = false
}

async function openUser(u) {
  activeUser.value = u; userLoading.value = true; userData.value = null
  userTab.value = 'summary'; tempPassword.value = ''; newPassword.value = ''; banReason.value = ''
  try { const { data } = await axios.get(`/api/admin/users/${u.id}/detail`); userData.value = data.data; friendRequestVal.value = userData.value?.user?.allow_friend_request ? 'true' : 'false' } catch {}
  userLoading.value = false
}

async function impersonateUser() {
  if (!userData.value?.user) return
  if (!confirm(`'${userData.value.user.name}' 로 로그인합니다. 본인 관리자 세션은 종료됩니다. 계속?`)) return
  try {
    const { data } = await axios.post(`/api/admin/users/${userData.value.user.id}/impersonate`)
    if (data.success) {
      localStorage.setItem('token', data.data.token)
      axios.defaults.headers.common['Authorization'] = 'Bearer ' + data.data.token
      auth.token = data.data.token
      auth.user = data.data.user
      alert(data.message)
      router.push('/')
      setTimeout(() => window.location.reload(), 100)
    }
  } catch (e) { alert(e.response?.data?.message || 'Impersonate 실패') }
}

async function resetPassword() {
  if (!userData.value?.user) return
  if (!confirm('이 회원의 비밀번호를 변경합니다. 계속?')) return
  try {
    const { data } = await axios.post(`/api/admin/users/${userData.value.user.id}/reset-password`,
      newPassword.value ? { password: newPassword.value } : {})
    if (data.success) {
      tempPassword.value = data.data.temporary_password
      newPassword.value = ''
    }
  } catch (e) { alert(e.response?.data?.message || '비밀번호 초기화 실패') }
}

function copyPassword() {
  navigator.clipboard?.writeText(tempPassword.value)
  alert('복사됨')
}

async function deleteAccount() {
  if (!userData.value?.user) return
  const name = userData.value.user.name
  if (!confirm(`⚠️ '${name}' 계정을 삭제합니다. 정지 + 이메일 비활성화됩니다. 계속?`)) return
  if (!confirm(`정말 삭제합니까? (한 번 더 확인)`)) return
  try {
    const { data } = await axios.delete(`/api/admin/users/${userData.value.user.id}`)
    if (data.success) {
      alert(data.message)
      activeUser.value = null; userData.value = null
      loadUsers(page.value)
    }
  } catch (e) { alert(e.response?.data?.message || '삭제 실패') }
}

async function saveUser() {
  if (!userData.value?.user) return
  try {
    await axios.put(`/api/admin/users/${userData.value.user.id}`, userData.value.user)
    // 목록도 업데이트
    const u = users.value.find(x => x.id === userData.value.user.id)
    if (u) Object.assign(u, userData.value.user)
    alert('저장되었습니다!')
  } catch (e) { alert(e.response?.data?.message || '저장 실패') }
}

async function givePoints() {
  if (!addPoints.value || !userData.value?.user) return
  try {
    // 포인트 직접 수정
    userData.value.user.points = (userData.value.user.points || 0) + addPoints.value
    await axios.put(`/api/admin/users/${userData.value.user.id}`, { points: userData.value.user.points })
    addPoints.value = 0
    alert('포인트 지급!')
  } catch {}
}

async function banCurrentUser() {
  const reason = banReason.value || '관리자 정지'
  try {
    await axios.post(`/api/admin/users/${userData.value.user.id}/ban`, { reason })
    userData.value.user.is_banned = true; userData.value.user.ban_reason = reason
    const u = users.value.find(x => x.id === userData.value.user.id)
    if (u) u.is_banned = true
  } catch {}
}

async function unbanCurrentUser() {
  try {
    await axios.post(`/api/admin/users/${userData.value.user.id}/unban`)
    userData.value.user.is_banned = false; userData.value.user.ban_reason = null
    const u = users.value.find(x => x.id === userData.value.user.id)
    if (u) u.is_banned = false
  } catch {}
}

onMounted(() => loadUsers())
</script>
