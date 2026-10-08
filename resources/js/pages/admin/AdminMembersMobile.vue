<template>
<div>
  <!-- ───────── 회원 목록 ───────── -->
  <div v-if="!detailId" class="space-y-3">
    <label class="flex items-center gap-2 bg-white border border-gray-200 rounded-xl px-3 min-h-[46px] text-ink-muted">
      <AppIcon name="search" :size="18" />
      <input v-model="search" type="search" placeholder="이름·이메일·닉네임 검색" autocomplete="off"
        class="w-full bg-transparent outline-none text-[15px] text-ink" />
    </label>

    <div class="flex gap-2 overflow-x-auto scrollbar-hide" role="group" aria-label="회원 보기">
      <button v-for="f in filters" :key="f.key" @click="filter = f.key" :aria-pressed="filter === f.key"
        class="shrink-0 min-h-[44px] px-4 rounded-full border text-[15px] flex items-center gap-1.5"
        :class="filter === f.key ? 'bg-ink text-white border-ink font-bold' : 'bg-white text-ink border-gray-200 font-medium'">
        {{ f.label }}
        <span v-if="f.key === 'banned' && bannedCount > 0" class="min-w-[20px] h-5 px-1.5 rounded-full bg-red-500 text-white text-[12px] font-bold grid place-items-center">{{ bannedCount }}</span>
      </button>
    </div>

    <div class="text-[13px] text-ink-muted px-0.5">{{ loadingList ? '불러오는 중...' : `${total.toLocaleString()}명` }}</div>

    <p v-if="listError" class="bg-red-50 text-red-600 text-sm rounded-xl p-3">
      불러오지 못했어요. <button class="font-bold underline" @click="loadUsers(true)">다시 시도</button>
    </p>

    <button v-for="u in users" :key="u.id" @click="openUser(u)"
      class="w-full text-left bg-white border border-gray-100 rounded-2xl p-3 min-h-[72px] grid grid-cols-[44px_1fr_auto] gap-3 items-center active:bg-amber-50">
      <span class="w-11 h-11 rounded-full bg-amber-100 text-amber-700 grid place-items-center font-bold text-[17px]">{{ initial(u) }}</span>
      <span class="min-w-0">
        <span class="flex items-center gap-2 flex-wrap">
          <span class="text-[16px] font-bold text-ink">{{ u.name || '(이름 없음)' }}</span>
          <span class="text-[12px] font-bold px-2 py-0.5 rounded-full" :class="roleClass(u.role)">{{ roleLabel(u.role) }}</span>
        </span>
        <span class="block text-[13px] text-ink-muted break-all">{{ u.email }}</span>
        <span class="block text-[13px] text-ink-muted"><span class="font-bold text-amber-600 tabular-nums">{{ Number(u.points || 0).toLocaleString() }}P</span> · 가입 {{ fmtDate(u.created_at) }}</span>
      </span>
      <span class="text-[13px] font-bold" :class="u.is_banned ? 'text-red-600' : 'text-emerald-600'">{{ u.is_banned ? '정지' : '활동' }}</span>
    </button>

    <p v-if="!loadingList && !users.length && !listError" class="text-center text-sm text-ink-muted py-8">조건에 맞는 회원이 없어요.</p>

    <button v-if="page < lastPage" @click="loadUsers(false)" :disabled="loadingList"
      class="w-full min-h-[50px] rounded-xl border border-gray-200 bg-white text-[15px] font-bold text-ink disabled:opacity-50">
      {{ loadingList ? '불러오는 중...' : `더 보기 (${(total - users.length).toLocaleString()}명 남음)` }}
    </button>
  </div>

  <!-- ───────── 회원 상세 ───────── -->
  <div v-else class="space-y-3">
    <p v-if="detailLoading" class="text-center text-sm text-ink-muted py-10">불러오는 중...</p>
    <p v-else-if="detailError" class="bg-red-50 text-red-600 text-sm rounded-xl p-3">
      회원 정보를 불러오지 못했어요. <button class="font-bold underline" @click="loadDetail()">다시 시도</button>
    </p>

    <template v-else-if="d">
      <!-- 요약 -->
      <div class="bg-white border border-gray-100 rounded-2xl p-4 space-y-3">
        <div class="flex items-center gap-3">
          <span class="w-[52px] h-[52px] rounded-full bg-amber-100 text-amber-700 grid place-items-center font-bold text-xl shrink-0">{{ initial(d.user) }}</span>
          <div class="min-w-0">
            <div class="text-[18px] font-bold text-ink break-words">{{ d.user.name || '(이름 없음)' }}<span v-if="d.user.nickname" class="text-[14px] font-medium text-ink-muted"> · {{ d.user.nickname }}</span></div>
            <div class="text-[13px] text-ink-muted break-all">{{ d.user.email }} · ID {{ d.user.id }}</div>
          </div>
        </div>
        <div class="flex flex-wrap gap-1.5">
          <span class="text-[12px] font-bold px-2.5 py-1 rounded-full" :class="roleClass(d.user.role)">{{ roleLabel(d.user.role) }}</span>
          <span class="text-[12px] font-bold px-2.5 py-1 rounded-full" :class="d.user.is_banned ? 'bg-red-100 text-red-700' : 'bg-emerald-100 text-emerald-700'">{{ d.user.is_banned ? '정지 중' : '활동 중' }}</span>
          <span v-if="!d.user.email_verified_at" class="text-[12px] font-bold px-2.5 py-1 rounded-full bg-amber-100 text-amber-700">이메일 미인증</span>
          <span v-if="d.user.city || d.user.state" class="text-[12px] font-bold px-2.5 py-1 rounded-full bg-gray-100 text-ink-muted">{{ [d.user.city, d.user.state].filter(Boolean).join(', ') }}</span>
        </div>
        <div v-if="d.user.is_banned" class="text-[14px] text-red-600 bg-red-50 rounded-xl px-3 py-2">정지 사유: {{ d.user.ban_reason || '없음' }}</div>
        <div class="grid grid-cols-3 gap-2">
          <div class="bg-gray-50 rounded-xl py-2 px-1 text-center"><div class="text-[17px] font-bold tabular-nums text-amber-600">{{ Number(d.user.points || 0).toLocaleString() }}</div><div class="text-[12px] text-ink-muted">포인트</div></div>
          <div class="bg-gray-50 rounded-xl py-2 px-1 text-center"><div class="text-[17px] font-bold tabular-nums">${{ Number(d.summary?.total_spent_usd || 0).toLocaleString() }}</div><div class="text-[12px] text-ink-muted">결제 누적</div></div>
          <div class="bg-gray-50 rounded-xl py-2 px-1 text-center"><div class="text-[17px] font-bold tabular-nums">{{ d.summary?.posts_total || 0 }}</div><div class="text-[12px] text-ink-muted">콘텐츠</div></div>
        </div>
        <div class="text-[13px] text-ink-muted leading-relaxed">
          가입 {{ fmtDate(d.user.created_at) }} · 최근 로그인 {{ fmtDate(d.user.last_login_at) || '없음' }} · 로그인 {{ d.user.login_count || 0 }}회<br>
          누적 획득 <span class="text-emerald-600 font-bold">+{{ Number(d.summary?.total_points_earned || 0).toLocaleString() }}P</span> · 누적 사용 <span class="text-rose-600 font-bold">-{{ Number(d.summary?.total_points_spent || 0).toLocaleString() }}P</span> · 활성 광고 {{ d.summary?.ads_active || 0 }}건
        </div>
      </div>

      <!-- 빠른 작업 -->
      <div class="text-[13px] font-bold text-ink-muted px-0.5 pt-1">빠른 작업</div>
      <div class="grid grid-cols-2 gap-2.5">
        <button @click="openSheet('ban')" class="min-h-[56px] rounded-xl border px-3 py-2 text-left text-[15px] font-bold flex items-center gap-2"
          :class="d.user.is_banned ? 'border-gray-200 bg-white text-ink' : 'border-red-300 bg-white text-red-600'">
          <AppIcon name="alert-circle" :size="20" /><span>{{ d.user.is_banned ? '정지 해제' : '정지하기' }}</span>
        </button>
        <button v-if="isAdminUp" @click="openSheet('pw')" class="min-h-[56px] rounded-xl border border-gray-200 bg-white px-3 py-2 text-left text-[15px] font-bold text-ink flex items-center gap-2">
          <AppIcon name="key" :size="20" /><span>비밀번호 재설정</span>
        </button>
        <button v-if="isAdminUp && !d.user.email_verified_at" @click="openSheet('verify')" class="min-h-[56px] rounded-xl border border-gray-200 bg-white px-3 py-2 text-left text-[15px] font-bold text-ink flex items-center gap-2">
          <AppIcon name="check" :size="20" /><span>이메일 인증 처리</span>
        </button>
        <button v-if="isAdminUp" @click="openSheet('points')" class="min-h-[56px] rounded-xl border border-gray-200 bg-white px-3 py-2 text-left text-[15px] font-bold text-ink flex items-center gap-2">
          <AppIcon name="coins" :size="20" /><span>포인트 지급·차감</span>
        </button>
        <button v-if="isSuper" @click="openSheet('role')" class="min-h-[56px] rounded-xl border border-gray-200 bg-white px-3 py-2 text-left text-[15px] font-bold text-ink flex items-center gap-2">
          <AppIcon name="shield" :size="20" /><span>권한 변경</span>
        </button>
      </div>

      <!-- 자세히 보기 -->
      <div class="text-[13px] font-bold text-ink-muted px-0.5 pt-1">자세히 보기</div>
      <div class="bg-white border border-gray-100 rounded-2xl overflow-hidden">
        <div v-for="(sec, i) in sections" :key="sec.key" :class="i ? 'border-t border-gray-100' : ''">
          <button @click="toggle(sec.key)" :aria-expanded="open.has(sec.key)"
            class="w-full min-h-[54px] px-4 flex items-center justify-between gap-2 text-left">
            <span class="text-[16px] text-ink">{{ sec.label }}<span v-if="sec.count !== null" class="ml-1.5 text-[13px] text-ink-muted">{{ sec.count }}</span></span>
            <AppIcon :name="open.has(sec.key) ? 'chevron-up' : 'chevron-down'" :size="18" />
          </button>

          <div v-if="open.has(sec.key)" class="px-4 pb-4">
            <!-- 정보 수정 -->
            <form v-if="sec.key === 'info'" @submit.prevent="saveInfo" class="space-y-3">
              <label v-for="f in infoFields" :key="f.k" class="block">
                <span class="block text-[13px] font-bold text-ink-muted mb-1">{{ f.l }}</span>
                <input v-model="form[f.k]" :type="f.t || 'text'" :inputmode="f.m" autocomplete="off" class="w-full min-h-[48px] rounded-xl border border-gray-200 px-3 text-[16px] bg-white" />
              </label>
              <label class="block">
                <span class="block text-[13px] font-bold text-ink-muted mb-1">소개</span>
                <textarea v-model="form.bio" rows="3" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-[16px] bg-white"></textarea>
              </label>
              <label class="block">
                <span class="block text-[13px] font-bold text-ink-muted mb-1">친구 요청</span>
                <select v-model="form.allow_friend_request" class="w-full min-h-[48px] rounded-xl border border-gray-200 px-3 text-[16px] bg-white">
                  <option :value="true">수락</option><option :value="false">거절</option>
                </select>
              </label>
              <button type="submit" :disabled="busy" class="w-full min-h-[50px] rounded-xl bg-amber-500 text-white text-[16px] font-bold disabled:opacity-50">{{ busy ? '저장 중...' : '저장' }}</button>
            </form>

            <!-- 포인트 내역 -->
            <div v-else-if="sec.key === 'points'">
              <p v-if="!d.points?.length" class="text-sm text-ink-muted py-3">포인트 내역이 없어요.</p>
              <div v-for="pt in d.points" :key="pt.id" class="flex items-start justify-between gap-3 py-2.5 border-t first:border-t-0 border-gray-50">
                <div class="min-w-0"><div class="text-[14px] text-ink break-words">{{ pt.reason }}</div><div class="text-[12px] text-ink-muted">{{ fmtDate(pt.created_at) }}</div></div>
                <div class="text-[15px] font-bold tabular-nums shrink-0" :class="pt.amount > 0 ? 'text-emerald-600' : 'text-red-600'">{{ pt.amount > 0 ? '+' : '' }}{{ pt.amount }}P</div>
              </div>
            </div>

            <!-- Entry -->
            <div v-else-if="sec.key === 'entries'">
              <div class="flex items-baseline justify-between mb-3"><span class="text-[13px] text-ink-muted">보유 Entry</span><span class="text-[20px] font-bold text-amber-600 tabular-nums">{{ Number(d.user.entries || 0).toLocaleString() }}</span></div>
              <form @submit.prevent="giveEntries" class="space-y-2 mb-3">
                <div class="grid grid-cols-[110px_1fr] gap-2">
                  <input v-model.number="entryAmount" type="number" inputmode="numeric" placeholder="수량(±)" class="min-h-[48px] rounded-xl border border-gray-200 px-3 text-[16px]" />
                  <input v-model="entryDesc" type="text" placeholder="사유 (선택)" class="min-h-[48px] rounded-xl border border-gray-200 px-3 text-[16px]" />
                </div>
                <button type="submit" :disabled="busy || !entryAmount" class="w-full min-h-[48px] rounded-xl bg-emerald-500 text-white text-[15px] font-bold disabled:opacity-40">지급 / 차감</button>
              </form>
              <p v-if="!d.entries?.length" class="text-sm text-ink-muted py-2">Entry 내역이 없어요.</p>
              <div v-for="et in d.entries" :key="et.id" class="flex items-start justify-between gap-3 py-2.5 border-t border-gray-50">
                <div class="min-w-0"><div class="text-[14px] text-ink break-words">{{ et.description }}</div><div class="text-[12px] text-ink-muted">{{ fmtDate(et.created_at) }} · 잔액 {{ et.balance_after }}</div></div>
                <div class="text-[15px] font-bold tabular-nums shrink-0" :class="et.amount > 0 ? 'text-emerald-600' : 'text-red-600'">{{ et.amount > 0 ? '+' : '' }}{{ et.amount }}</div>
              </div>
            </div>

            <!-- 결제 -->
            <div v-else-if="sec.key === 'payments'">
              <p v-if="!d.payments?.length" class="text-sm text-ink-muted py-3">결제 내역이 없어요.</p>
              <div v-for="p in d.payments" :key="p.id" class="flex items-center justify-between gap-3 py-2.5 border-t first:border-t-0 border-gray-50">
                <div><div class="text-[15px] font-bold tabular-nums">${{ p.amount }}</div><div class="text-[12px] text-ink-muted">{{ fmtDate(p.created_at) }} · {{ p.status }}</div></div>
                <div class="text-[15px] font-bold text-amber-600 tabular-nums">+{{ p.points }}P</div>
              </div>
            </div>

            <!-- 작성한 콘텐츠 -->
            <div v-else-if="sec.key === 'content'" class="space-y-4">
              <p v-if="!contentGroups.length" class="text-sm text-ink-muted py-3">작성한 콘텐츠가 없어요.</p>
              <div v-for="g in contentGroups" :key="g.label">
                <div class="text-[13px] font-bold text-ink-muted mb-1">{{ g.label }} ({{ g.items.length }})</div>
                <div v-for="it in g.items" :key="it.id" class="flex items-center gap-2 py-2.5 border-t border-gray-50">
                  <div class="min-w-0 flex-1"><div class="text-[15px] text-ink break-words">{{ it.title }}</div><div class="text-[12px] text-ink-muted">{{ it.meta }}</div></div>
                  <a :href="it.href" target="_blank" rel="noopener" class="shrink-0 min-h-[40px] px-3 rounded-lg bg-gray-100 text-[13px] font-bold text-ink-light grid place-items-center">보기</a>
                </div>
              </div>
            </div>

            <!-- 광고 -->
            <div v-else-if="sec.key === 'ads'">
              <p v-if="!d.banners?.length" class="text-sm text-ink-muted py-3">광고 내역이 없어요.</p>
              <div v-for="b in d.banners" :key="b.id" class="flex items-center gap-3 py-2.5 border-t first:border-t-0 border-gray-50">
                <img v-if="b.image_url" :src="b.image_url" class="w-16 h-12 rounded-lg object-cover border border-gray-100 shrink-0" @error="$event.target.style.display='none'" />
                <div class="min-w-0">
                  <div class="text-[15px] text-ink break-words">{{ b.title }}</div>
                  <div class="text-[12px] text-ink-muted">{{ b.status }} · 노출 {{ b.impressions || 0 }} · 클릭 {{ b.clicks || 0 }} · {{ fmtDate(b.created_at) }}<span v-if="b.expires_at"> ~ {{ fmtDate(b.expires_at) }}</span></div>
                </div>
              </div>
            </div>

            <!-- 댓글 -->
            <div v-else-if="sec.key === 'comments'">
              <p v-if="!d.comments?.length" class="text-sm text-ink-muted py-3">댓글이 없어요.</p>
              <div v-for="c in d.comments" :key="c.id" class="py-2.5 border-t first:border-t-0 border-gray-50">
                <div class="text-[14px] text-ink break-words">{{ c.content }}</div><div class="text-[12px] text-ink-muted">{{ fmtDate(c.created_at) }}</div>
              </div>
            </div>

            <!-- 신고 기록 -->
            <div v-else-if="sec.key === 'reports'">
              <p v-if="!d.reports_filed?.length" class="text-sm text-ink-muted py-3">이 회원이 낸 신고가 없어요.</p>
              <div v-for="r in d.reports_filed" :key="r.id" class="py-2.5 border-t first:border-t-0 border-gray-50">
                <div class="text-[15px] text-ink"><span class="text-[12px] font-bold px-2 py-0.5 rounded-full mr-1.5" :class="{ pending: 'bg-amber-100 text-amber-700', resolved: 'bg-emerald-100 text-emerald-700' }[r.status] || 'bg-gray-100 text-ink-muted'">{{ r.status }}</span>{{ r.reason }}</div>
                <div class="text-[13px] text-ink-muted break-words">{{ r.content }}</div>
                <div class="text-[12px] text-ink-muted">{{ shortType(r.reportable_type) }} #{{ r.reportable_id }} · {{ fmtDate(r.created_at) }}</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- 위험 구역 (슈퍼관리자 전용) -->
      <template v-if="isSuper && auth.user?.id !== d.user.id">
        <div class="text-[13px] font-bold text-ink-muted px-0.5 pt-1">슈퍼관리자 전용</div>
        <div class="grid grid-cols-2 gap-2.5">
          <button @click="openSheet('login')" class="min-h-[56px] rounded-xl border border-gray-200 bg-white px-3 py-2 text-left text-[15px] font-bold text-ink flex items-center gap-2"><AppIcon name="log-in" :size="20" /><span>이 회원으로 로그인</span></button>
          <button v-if="d.user.role !== 'super_admin'" @click="openSheet('delete')" class="min-h-[56px] rounded-xl border border-red-300 bg-white px-3 py-2 text-left text-[15px] font-bold text-red-600 flex items-center gap-2"><AppIcon name="trash" :size="20" /><span>계정 삭제</span></button>
        </div>
      </template>
    </template>
  </div>

  <!-- ───────── 아래에서 올라오는 확인 시트 ───────── -->
  <Teleport to="body">
    <div v-if="sheet" class="fixed inset-0 z-[70] bg-black/45 flex items-end" @click.self="closeSheet">
      <div class="w-full bg-white rounded-t-3xl px-4 pt-2 max-h-[85vh] overflow-y-auto" role="dialog" aria-modal="true"
        :style="{ paddingBottom: 'calc(16px + env(safe-area-inset-bottom, 0px))' }">
        <div class="w-10 h-1 rounded bg-gray-200 mx-auto mb-3"></div>
        <template v-if="d">
          <!-- 정지 / 해제 -->
          <div v-if="sheet === 'ban'" class="space-y-3">
            <div class="text-[17px] font-bold text-ink">{{ d.user.name }}님을 {{ d.user.is_banned ? '정지 해제할까요?' : '정지할까요?' }}</div>
            <input v-if="!d.user.is_banned" v-model="banReason" type="text" maxlength="200" placeholder="정지 사유 (예: 스팸 글 반복)" class="w-full min-h-[48px] rounded-xl border border-gray-200 px-3 text-[16px]" />
            <button @click="doBan" :disabled="busy" class="w-full min-h-[52px] rounded-xl text-white text-[16px] font-bold disabled:opacity-50" :class="d.user.is_banned ? 'bg-emerald-500' : 'bg-red-500'">{{ d.user.is_banned ? '정지 해제하기' : '정지하기' }}</button>
          </div>

          <!-- 비밀번호 재설정 -->
          <div v-else-if="sheet === 'pw'" class="space-y-3">
            <template v-if="!tempPassword">
              <div class="text-[17px] font-bold text-ink">{{ d.user.name }}님의 비밀번호를 재설정할까요?</div>
              <p class="text-[13px] text-ink-muted">비워 두면 12자 임시 비밀번호가 자동으로 만들어져요. 재설정하면 이 회원의 기존 로그인이 풀립니다.</p>
              <input v-model="newPassword" type="text" autocomplete="off" placeholder="새 비밀번호 (선택, 8자 이상)" class="w-full min-h-[48px] rounded-xl border border-gray-200 px-3 text-[16px] font-mono" />
              <button @click="doReset" :disabled="busy" class="w-full min-h-[52px] rounded-xl bg-blue-500 text-white text-[16px] font-bold disabled:opacity-50">재설정하기</button>
            </template>
            <template v-else>
              <div class="text-[17px] font-bold text-ink">새 비밀번호가 만들어졌어요</div>
              <div class="bg-blue-50 text-blue-700 rounded-xl px-4 py-3 text-[20px] font-mono font-bold break-all select-all">{{ tempPassword }}</div>
              <button @click="copyText(tempPassword)" class="w-full min-h-[50px] rounded-xl border border-blue-200 text-blue-700 text-[16px] font-bold">복사</button>
              <p class="text-[12px] text-ink-muted">이 창을 닫으면 다시 볼 수 없어요. 회원에게 안전한 방법으로 전달하세요.</p>
            </template>
          </div>

          <!-- 이메일 인증 -->
          <div v-else-if="sheet === 'verify'" class="space-y-3">
            <div class="text-[17px] font-bold text-ink">{{ d.user.name }}님의 이메일 인증을 대신 처리할까요?</div>
            <p class="text-[13px] text-ink-muted">인증 메일이 스팸함에 들어갔거나 발송에 실패했을 때 쓰세요.</p>
            <button @click="doVerify" :disabled="busy" class="w-full min-h-[52px] rounded-xl bg-amber-500 text-white text-[16px] font-bold disabled:opacity-50">인증 처리하기</button>
          </div>

          <!-- 포인트 -->
          <div v-else-if="sheet === 'points'" class="space-y-3">
            <div class="text-[17px] font-bold text-ink">포인트 지급·차감</div>
            <div class="grid grid-cols-2 gap-1 bg-gray-100 rounded-xl p-1">
              <button @click="ptSign = 1" class="min-h-[44px] rounded-lg text-[15px] font-bold" :class="ptSign === 1 ? 'bg-white text-emerald-600 shadow-sm' : 'text-ink-muted'">지급 +</button>
              <button @click="ptSign = -1" class="min-h-[44px] rounded-lg text-[15px] font-bold" :class="ptSign === -1 ? 'bg-white text-red-600 shadow-sm' : 'text-ink-muted'">차감 −</button>
            </div>
            <input v-model.number="ptAmount" type="number" inputmode="numeric" min="1" placeholder="포인트" class="w-full min-h-[48px] rounded-xl border border-gray-200 px-3 text-[18px] font-bold tabular-nums" />
            <input v-model="ptReason" type="text" maxlength="100" placeholder="사유 (기록에 남아요)" class="w-full min-h-[48px] rounded-xl border border-gray-200 px-3 text-[16px]" />
            <div class="text-[14px] text-ink-muted">현재 <b class="text-ink tabular-nums">{{ Number(d.user.points || 0).toLocaleString() }}P</b> → 변경 후 <b class="tabular-nums" :class="ptAfter < 0 ? 'text-red-600' : 'text-ink'">{{ ptAfter.toLocaleString() }}P</b></div>
            <button @click="doPoints" :disabled="busy || !ptAmount || ptAmount < 1 || ptAfter < 0" class="w-full min-h-[52px] rounded-xl text-white text-[16px] font-bold disabled:opacity-40" :class="ptSign === 1 ? 'bg-emerald-500' : 'bg-red-500'">{{ ptSign === 1 ? '지급하기' : '차감하기' }}</button>
          </div>

          <!-- 권한 -->
          <div v-else-if="sheet === 'role'" class="space-y-2">
            <div class="text-[17px] font-bold text-ink mb-1">권한 변경</div>
            <button v-for="r in roleOptions" :key="r.value" @click="roleChoice = r.value"
              class="w-full text-left min-h-[60px] rounded-xl border px-3 py-2 flex items-center gap-3" :class="roleChoice === r.value ? 'border-amber-400 bg-amber-50' : 'border-gray-200'">
              <span class="w-5 h-5 rounded-full border-2 grid place-items-center shrink-0" :class="roleChoice === r.value ? 'border-amber-500 bg-amber-500' : 'border-gray-300'"><span v-if="roleChoice === r.value" class="w-2 h-2 rounded-full bg-white"></span></span>
              <span><span class="block text-[15px] font-bold text-ink">{{ r.label }}</span><span class="block text-[12px] text-ink-muted">{{ r.desc }}</span></span>
            </button>
            <button @click="doRole" :disabled="busy || roleChoice === d.user.role" class="w-full min-h-[52px] rounded-xl bg-amber-500 text-white text-[16px] font-bold disabled:opacity-40 mt-1">권한 저장</button>
          </div>

          <!-- 이 회원으로 로그인 -->
          <div v-else-if="sheet === 'login'" class="space-y-3">
            <div class="text-[17px] font-bold text-ink">{{ d.user.name }}님으로 로그인할까요?</div>
            <p class="text-[13px] text-ink-muted">그 회원의 화면을 그대로 볼 수 있고 활동이 기록에 남아요. <b>지금 관리자 로그인은 끝나서</b> 다시 관리자로 들어오려면 로그인을 새로 해야 해요.</p>
            <button @click="doLogin" :disabled="busy" class="w-full min-h-[52px] rounded-xl bg-purple-500 text-white text-[16px] font-bold disabled:opacity-50">이 회원으로 로그인</button>
          </div>

          <!-- 계정 삭제 -->
          <div v-else-if="sheet === 'delete'" class="space-y-3">
            <div class="text-[17px] font-bold text-red-600">{{ d.user.name }}님의 계정을 삭제할까요?</div>
            <p class="text-[13px] text-ink-muted">계정이 정지되고 이메일이 비활성화돼요. 되돌리려면 슈퍼관리자가 데이터베이스에서 직접 복구해야 해요.</p>
            <button v-if="!deleteArmed" @click="deleteArmed = true" class="w-full min-h-[52px] rounded-xl border border-red-300 text-red-600 text-[16px] font-bold">삭제하기</button>
            <button v-else @click="doDelete" :disabled="busy" class="w-full min-h-[52px] rounded-xl bg-red-600 text-white text-[16px] font-bold disabled:opacity-50">정말 삭제합니다 (한 번 더 눌러요)</button>
          </div>
        </template>
        <button @click="closeSheet" class="w-full min-h-[50px] rounded-xl bg-gray-100 text-[16px] font-bold text-ink mt-3">{{ tempPassword && sheet === 'pw' ? '닫기' : '취소' }}</button>
      </div>
    </div>
  </Teleport>
</div>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted, onBeforeUnmount, nextTick } from 'vue'
import axios from 'axios'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import { useSiteStore } from '../../stores/site'
import AppIcon from '../../components/AppIcon.vue'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const site = useSiteStore()
const say = (m, type = 'success') => site.toast(m, type)

const isSuper = computed(() => auth.user?.role === 'super_admin')
const isAdminUp = computed(() => ['admin', 'super_admin'].includes(auth.user?.role))

// ───────── 목록 ─────────
const filters = [{ key: 'all', label: '전체' }, { key: 'banned', label: '정지' }, { key: 'staff', label: '운영진' }]
const users = ref([]); const total = ref(0); const page = ref(1); const lastPage = ref(1)
const loadingList = ref(false); const listError = ref(false)
const search = ref(''); const filter = ref('all'); const bannedCount = ref(0)
let listSeq = 0

async function loadUsers(reset = true) {
  const seq = ++listSeq
  loadingList.value = true; listError.value = false
  const nextPage = reset ? 1 : page.value + 1
  const params = { page: nextPage, per_page: 20 }
  if (search.value.trim()) params.search = search.value.trim()
  if (filter.value === 'banned') params.banned = 1
  if (filter.value === 'staff') params.role = 'staff'
  try {
    const { data } = await axios.get('/api/admin/users', { params })
    if (seq !== listSeq) return   // 더 최근에 보낸 요청이 있으면 이 응답은 버림
    const rows = data.data?.data || []
    users.value = reset ? rows : [...users.value, ...rows]
    page.value = data.data?.current_page || nextPage
    lastPage.value = data.data?.last_page || 1
    total.value = data.data?.total || 0
  } catch {
    if (seq === listSeq) listError.value = true
  } finally {
    if (seq === listSeq) loadingList.value = false
  }
}
async function loadBannedCount() {
  try { const { data } = await axios.get('/api/admin/users', { params: { banned: 1, per_page: 1 } }); bannedCount.value = data.data?.total || 0 } catch {}
}
let searchTimer = null
watch(search, () => { clearTimeout(searchTimer); searchTimer = setTimeout(() => loadUsers(true), 300) })
watch(filter, () => loadUsers(true))

// ───────── 상세 (주소의 ?user=번호 로 열림 → 뒤로가기가 자연스럽게 목록으로 돌아감) ─────────
const detailId = computed(() => { const n = Number(route.query.user); return Number.isInteger(n) && n > 0 ? n : null })
const d = ref(null); const detailLoading = ref(false); const detailError = ref(false)
let listScroll = 0

function openUser(u) {
  listScroll = window.scrollY
  router.push({ path: '/admin/members', query: { user: u.id } })
}
async function loadDetail(quiet = false) {
  const id = detailId.value; if (!id) return
  if (!quiet) { detailLoading.value = true; d.value = null }
  detailError.value = false
  try {
    const { data } = await axios.get(`/api/admin/users/${id}/detail`)
    if (detailId.value !== id) return
    d.value = data.data
    syncForm()
  } catch { if (!quiet) detailError.value = true }
  finally { detailLoading.value = false }
}
watch(detailId, async (id, prev) => {
  closeSheet(); open.clear()
  if (id) { window.scrollTo(0, 0); loadDetail() }
  else { d.value = null; await nextTick(); window.scrollTo(0, listScroll); loadUsers(true); loadBannedCount() }
}, { immediate: false })

// 화면 오른쪽 위 영역(제목)은 AdminLayout 이 맡고, 여기서는 목록/상세 전환만 처리
onMounted(() => { loadUsers(true); loadBannedCount(); if (detailId.value) loadDetail() })
onBeforeUnmount(() => clearTimeout(searchTimer))

// ───────── 상세: 펼침 항목 ─────────
const open = reactive(new Set())
function toggle(k) { open.has(k) ? open.delete(k) : open.add(k) }

const contentGroups = computed(() => {
  if (!d.value) return []
  const x = d.value; const g = []
  const add = (label, arr, map) => { if (arr?.length) g.push({ label, items: arr.map(map) }) }
  add('커뮤니티 게시글', x.posts, p => ({ id: p.id, title: p.title, meta: `${fmtDate(p.created_at)} · 조회 ${p.view_count || 0} · 좋아요 ${p.like_count || 0}`, href: `/board/${p.board_id}/${p.id}` }))
  add('구인/구직', x.jobs, j => ({ id: j.id, title: j.title, meta: `${j.company || ''} · ${j.city || ''}, ${j.state || ''} · ${fmtDate(j.created_at)}`, href: `/jobs/${j.id}` }))
  add('중고장터', x.market, m => ({ id: m.id, title: m.title, meta: `$${Number(m.price || 0).toLocaleString()} · ${m.status} · ${fmtDate(m.created_at)}`, href: `/market/${m.id}` }))
  add('부동산', x.realestate, r => ({ id: r.id, title: r.title, meta: `$${Number(r.price || 0).toLocaleString()} · ${{ rent: '렌트', sale: '매매', roommate: '룸메' }[r.type] || r.type} · ${r.city || ''}, ${r.state || ''}`, href: `/realestate/${r.id}` }))
  add('동호회', x.clubs, c => ({ id: c.id, title: c.name, meta: `${c.category || ''} · ${c.member_count || 0}명 · ${fmtDate(c.created_at)}`, href: `/clubs/${c.id}` }))
  add('이벤트', x.events, e => ({ id: e.id, title: e.title, meta: `${fmtDate(e.event_date)} · ${e.city || ''}, ${e.state || ''}`, href: `/events/${e.id}` }))
  add('Q&A', x.qa, q => ({ id: q.id, title: q.title, meta: `${q.category || ''} · 좋아요 ${q.like_count || 0} · ${fmtDate(q.created_at)}`, href: `/qa/${q.id}` }))
  return g
})
const sections = computed(() => {
  const x = d.value || {}
  const contentCount = contentGroups.value.reduce((s, g) => s + g.items.length, 0)
  return [
    { key: 'info', label: '정보 수정', count: null },
    { key: 'points', label: '포인트 내역', count: x.points?.length ?? 0 },
    { key: 'entries', label: 'Entry', count: x.entries?.length ?? 0 },
    { key: 'payments', label: '결제 내역', count: x.payments?.length ?? 0 },
    { key: 'content', label: '작성한 콘텐츠', count: contentCount },
    { key: 'ads', label: '광고', count: x.banners?.length ?? 0 },
    { key: 'comments', label: '댓글', count: x.comments?.length ?? 0 },
    { key: 'reports', label: '이 회원이 낸 신고', count: x.reports_filed?.length ?? 0 },
  ]
})

// ───────── 정보 수정 ─────────
const infoFields = [
  { k: 'name', l: '이름' }, { k: 'nickname', l: '닉네임' }, { k: 'email', l: '이메일', t: 'email', m: 'email' },
  { k: 'phone', l: '전화', t: 'tel', m: 'tel' }, { k: 'city', l: '도시' }, { k: 'state', l: '주' },
]
const form = reactive({ name: '', nickname: '', email: '', phone: '', city: '', state: '', bio: '', allow_friend_request: true })
function syncForm() {
  const u = d.value?.user || {}
  Object.assign(form, { name: u.name || '', nickname: u.nickname || '', email: u.email || '', phone: u.phone || '', city: u.city || '', state: u.state || '', bio: u.bio || '', allow_friend_request: u.allow_friend_request !== false && u.allow_friend_request !== 0 })
}
const busy = ref(false)
function apiMsg(e, fallback) {
  const errs = e.response?.data?.errors
  return (errs ? Object.values(errs).flat().join(' ') : e.response?.data?.message) || fallback
}
async function saveInfo() {
  if (!d.value) return
  busy.value = true
  try {
    // 포인트·정지 상태는 보내지 않는다(다른 곳에서 바뀐 값을 오래된 값으로 덮어쓰는 것 방지)
    await axios.put(`/api/admin/users/${d.value.user.id}`, { ...form, allow_friend_request: !!form.allow_friend_request })
    Object.assign(d.value.user, form)
    const row = users.value.find(x => x.id === d.value.user.id); if (row) Object.assign(row, { name: form.name, email: form.email })
    say('저장했어요')
  } catch (e) { say(apiMsg(e, '저장하지 못했어요'), 'error') }
  busy.value = false
}

// ───────── Entry ─────────
const entryAmount = ref(0); const entryDesc = ref('')
async function giveEntries() {
  if (!entryAmount.value || !d.value) return
  busy.value = true
  try {
    await axios.post('/api/admin/entries/adjust', { user_id: d.value.user.id, amount: entryAmount.value, description: entryDesc.value || '관리자 조정' })
    entryAmount.value = 0; entryDesc.value = ''
    say('Entry를 반영했어요'); await loadDetail(true)
  } catch (e) { say(apiMsg(e, '처리하지 못했어요'), 'error') }
  busy.value = false
}

// ───────── 시트 ─────────
const sheet = ref(null)
const banReason = ref(''); const newPassword = ref(''); const tempPassword = ref('')
const ptSign = ref(1); const ptAmount = ref(null); const ptReason = ref('')
const roleChoice = ref(''); const deleteArmed = ref(false)
const ptAfter = computed(() => Number(d.value?.user?.points || 0) + ptSign.value * (Number(ptAmount.value) || 0))

const roleOptions = [
  { value: 'super_admin', label: '슈퍼관리자', desc: '모든 권한 (사이트 설정, 회원 관리, 삭제)' },
  { value: 'admin', label: '관리자', desc: '콘텐츠 관리, 회원 정지, 신고 처리' },
  { value: 'moderator', label: '운영자', desc: '게시글·댓글 관리, 신고 처리' },
  { value: 'business', label: '기업회원', desc: '업소록·구인 등록, 프로모션 가능' },
  { value: 'user', label: '일반회원', desc: '기본 회원 (글쓰기, 댓글, 게임 참여)' },
]
function openSheet(k) {
  banReason.value = ''; newPassword.value = ''; tempPassword.value = ''
  ptSign.value = 1; ptAmount.value = null; ptReason.value = ''; deleteArmed.value = false
  roleChoice.value = d.value?.user?.role || ''
  sheet.value = k
}
function closeSheet() { sheet.value = null; tempPassword.value = '' }

async function run(fn, errMsg) {
  busy.value = true
  try { await fn() } catch (e) { say(apiMsg(e, errMsg), 'error') }
  busy.value = false
}
const syncRow = (patch) => { const row = users.value.find(x => x.id === d.value?.user?.id); if (row) Object.assign(row, patch) }

const doBan = () => run(async () => {
  const u = d.value.user
  if (u.is_banned) {
    await axios.post(`/api/admin/users/${u.id}/unban`)
    u.is_banned = false; u.ban_reason = null; syncRow({ is_banned: false }); say('정지를 풀었어요')
  } else {
    const reason = banReason.value.trim() || '관리자 정지'
    await axios.post(`/api/admin/users/${u.id}/ban`, { reason })
    u.is_banned = true; u.ban_reason = reason; syncRow({ is_banned: true }); say('정지했어요')
  }
  closeSheet(); loadBannedCount()
}, '처리하지 못했어요')

const doReset = () => run(async () => {
  const body = newPassword.value.trim() ? { password: newPassword.value.trim() } : {}
  const { data } = await axios.post(`/api/admin/users/${d.value.user.id}/reset-password`, body)
  if (data.success) { tempPassword.value = data.data.temporary_password; newPassword.value = '' }
}, '비밀번호를 재설정하지 못했어요')

const doVerify = () => run(async () => {
  const { data } = await axios.post(`/api/admin/users/${d.value.user.id}/verify-email`)
  if (data.success) { d.value.user.email_verified_at = data.data.email_verified_at; say(data.message || '인증 처리했어요'); closeSheet() }
}, '인증 처리하지 못했어요')

const doPoints = () => run(async () => {
  const amount = ptSign.value * Number(ptAmount.value)
  const { data } = await axios.post(`/api/admin/users/${d.value.user.id}/points`, { amount, reason: ptReason.value.trim() || undefined })
  d.value.user.points = data.data.points; syncRow({ points: data.data.points })
  say(data.message || '반영했어요'); closeSheet(); loadDetail(true)
}, '포인트를 반영하지 못했어요')

const doRole = () => run(async () => {
  await axios.put(`/api/admin/users/${d.value.user.id}`, { role: roleChoice.value })
  d.value.user.role = roleChoice.value; syncRow({ role: roleChoice.value }); say('권한을 바꿨어요'); closeSheet()
}, '권한을 바꾸지 못했어요')

const doLogin = () => run(async () => {
  const { data } = await axios.post(`/api/admin/users/${d.value.user.id}/impersonate`)
  if (data.success) { await auth.loginWithToken(data.data.token); window.location.assign('/') }
}, '로그인하지 못했어요')

const doDelete = () => run(async () => {
  const { data } = await axios.delete(`/api/admin/users/${d.value.user.id}`)
  if (data.success) { say(data.message || '삭제했어요'); closeSheet(); router.replace('/admin/members') }
}, '삭제하지 못했어요')

async function copyText(t) {
  try { await navigator.clipboard.writeText(t); say('복사했어요') } catch { say('길게 눌러서 직접 복사해 주세요', 'info') }
}

// ───────── 표시 도우미 ─────────
const fmtDate = (s) => (s ? String(s).slice(0, 10) : '')
const initial = (u) => ((u?.name || u?.email || '?').trim()[0] || '?').toUpperCase()
const shortType = (t) => (t ? String(t).split(/[\\/]/).pop() : '')
const roleLabel = (r) => ({ super_admin: '슈퍼관리자', admin: '관리자', moderator: '운영자', business: '기업회원', user: '일반' }[r] || r)
const roleClass = (r) => ({ super_admin: 'bg-red-100 text-red-700', admin: 'bg-purple-100 text-purple-700', moderator: 'bg-blue-100 text-blue-700', business: 'bg-green-100 text-green-700', user: 'bg-gray-100 text-gray-600' }[r] || 'bg-gray-100 text-gray-600')
</script>
