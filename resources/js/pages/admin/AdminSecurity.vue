<template>
<!-- ───────── 휴대폰 화면 ───────── -->
<div v-if="isMobile" class="alv-m space-y-3 pb-4">
  <!-- 구역 -->
  <div class="flex gap-2 overflow-x-auto scrollbar-hide" role="tablist" aria-label="보안 메뉴">
    <button v-for="t in mTabs" :key="t.key" @click="mTab = t.key" role="tab" :aria-selected="mTab === t.key"
      class="shrink-0 min-h-[44px] px-4 rounded-full border text-[15px] flex items-center gap-1.5"
      :class="mTab === t.key ? 'bg-amber-500 text-white border-amber-500 font-bold' : 'bg-white text-ink border-gray-200 font-medium'">
      {{ t.label }}
      <span v-if="t.badge" class="min-w-[20px] h-5 px-1.5 rounded-full bg-red-500 text-white text-[12px] font-bold grid place-items-center">{{ t.badge }}</span>
    </button>
  </div>

  <!-- 신고 -->
  <div v-if="mTab === 'reports'" class="space-y-2.5">
    <div class="grid grid-cols-4 gap-1 bg-gray-200/70 rounded-2xl p-1" role="group" aria-label="신고 상태">
      <button v-for="f in reportStatusChips" :key="f.v" @click="setReportStatus(f.v)" :aria-pressed="reportFilter.status === f.v"
        class="min-h-[44px] rounded-xl text-[14px] font-bold" :class="reportFilter.status === f.v ? 'bg-white text-ink shadow-sm' : 'text-ink-muted'">{{ f.l }}</button>
    </div>
    <select v-model="reportFilter.type" @change="setReportType" aria-label="신고 유형" class="w-full min-h-[48px] bg-white border border-gray-200 rounded-xl px-3 text-ink">
      <option value="">전체 유형</option>
      <option v-for="o in reportTypes" :key="o.v" :value="o.v">{{ o.l }}</option>
    </select>
    <div v-if="!reports.length" class="text-center text-ink-muted py-12 text-[15px]">{{ reportsLoading ? '불러오는 중...' : '신고가 없어요 👍' }}</div>
    <div v-for="r in reports" :key="r.id" class="bg-white border border-gray-100 rounded-2xl p-3.5">
      <div class="flex items-center gap-2 flex-wrap">
        <span class="text-[12px] px-2 py-1 rounded-full font-bold"
          :class="{'bg-yellow-100 text-yellow-700': r.status==='pending', 'bg-green-100 text-green-700': r.status==='resolved', 'bg-gray-100 text-ink-light': r.status==='dismissed'}">{{ reportStatusLabel(r.status) }}</span>
        <span class="text-[12px] px-2 py-1 rounded-full font-bold bg-blue-50 text-blue-700">{{ formatType(r.reportable_type) }}</span>
        <span class="text-[13px] text-ink-faint">#{{ r.reportable_id }}</span>
      </div>
      <div class="text-[16px] font-bold text-ink mt-1.5 break-words">{{ r.reason }}</div>
      <div v-if="r.content" class="text-[14px] text-ink-muted mt-0.5 break-words line-clamp-3">{{ r.content }}</div>
      <div class="text-[13px] text-ink-faint mt-1.5 space-y-0.5">
        <div><span v-if="r.reporter">신고자 <b class="text-ink-light">{{ r.reporter.nickname || r.reporter.name }}</b> · </span>신고 {{ fullTime(r.created_at) }}</div>
        <div v-if="r.status !== 'pending'">{{ reportStatusLabel(r.status) }} {{ fullTime(r.handled_at) }}<span v-if="handledEstimated(r)" class="text-amber-600"> (추정)</span> · {{ handlerName(r) }}</div>
      </div>
      <div v-if="r.admin_note" class="mt-2 text-[14px] text-amber-800 bg-amber-50 rounded-xl px-3 py-2 break-words">📝 {{ r.admin_note }}</div>
      <button @click="openLogId = openLogId === r.id ? null : r.id" class="mt-2 min-h-[40px] text-[14px] font-bold text-ink-light underline">{{ openLogId === r.id ? '이력 접기' : '처리 이력 보기' }}</button>
      <ol v-if="openLogId === r.id" class="mt-1 space-y-2 border-l-2 border-gray-100 pl-3">
        <li v-for="l in (r.logs || [])" :key="l.id" class="text-[13px]">
          <div class="text-ink-faint tabular-nums">{{ fullTime(l.created_at) }}<span v-if="l.estimated" class="text-amber-600"> (추정)</span></div>
          <div><span class="text-[12px] px-2 py-0.5 rounded-full font-bold" :class="logClass(l.action)">{{ logLabel(l.action) }}</span> <span class="text-ink-light">{{ l.actor_name || (l.action === 'created' ? '' : '기록 없음') }}</span></div>
          <div v-if="l.note" class="text-ink-muted break-words">{{ l.note }}</div>
        </li>
      </ol>
      <div v-if="r.status === 'pending'" class="grid grid-cols-2 gap-2 mt-3">
        <button @click="resolveReport(r, 'dismissed')" :disabled="busyId === r.id" class="min-h-[48px] rounded-xl bg-gray-100 text-ink text-[15px] font-bold disabled:opacity-50">문제 없음</button>
        <button @click="resolveReport(r, 'resolved')" :disabled="busyId === r.id" class="min-h-[48px] rounded-xl bg-emerald-500 text-white text-[15px] font-bold disabled:opacity-50">처리 완료</button>
        <button @click="openSuspend(r)" :disabled="busyId === r.id" class="col-span-2 min-h-[48px] rounded-xl bg-red-500 text-white text-[15px] font-bold disabled:opacity-50">게시판 정지</button>
      </div>
      <button v-if="r.status !== 'pending'" @click="resolveReport(r, 'pending')" :disabled="busyId === r.id" class="mt-3 min-h-[48px] w-full rounded-xl border border-gray-200 text-[15px] font-bold text-ink-light disabled:opacity-50">처리 취소 (확인 대기로 되돌리기)</button>
      <button @click="openNote(r)" class="mt-2 min-h-[44px] w-full rounded-xl border border-gray-200 text-[14px] font-bold text-ink-light">{{ r.admin_note ? '메모 고치기' : '메모 추가' }}</button>
      <button v-if="isSuperAdmin" @click="deleteReport(r)" :disabled="busyId === r.id" class="mt-2 min-h-[44px] w-full rounded-xl border border-red-200 text-[14px] font-bold text-red-600 disabled:opacity-50">신고 삭제</button>
    </div>
    <div v-if="reportPagination.lastPage > 1" class="flex items-center justify-between gap-2 pt-1">
      <button @click="goReportPage(reportFilter.page - 1)" :disabled="reportFilter.page <= 1" class="min-h-[48px] px-5 rounded-xl bg-white border border-gray-200 text-[15px] font-bold disabled:opacity-40">이전</button>
      <span class="text-[14px] text-ink-muted tabular-nums">{{ reportPagination.currentPage }} / {{ reportPagination.lastPage }}</span>
      <button @click="goReportPage(reportFilter.page + 1)" :disabled="reportFilter.page >= reportPagination.lastPage" class="min-h-[48px] px-5 rounded-xl bg-white border border-gray-200 text-[15px] font-bold disabled:opacity-40">다음</button>
    </div>
  </div>

  <!-- 로그인 잠금 -->
  <div v-else-if="mTab === 'locks'" class="space-y-2.5">
    <p class="text-[14px] text-ink-muted">비밀번호를 계속 틀려서 잠긴 계정이에요 (최근 24시간).</p>
    <div v-if="!loginLocks.length" class="text-center text-ink-muted py-12 text-[15px]">최근 로그인 실패가 없어요 👍</div>
    <div v-for="l in loginLocks" :key="l.email + l.ip" class="bg-white border border-gray-100 rounded-2xl p-3.5">
      <div class="text-[16px] font-bold text-ink break-all">{{ l.email }}</div>
      <div class="text-[13px] text-ink-muted mt-0.5"><span class="font-mono">{{ l.ip }}</span> · 실패 {{ l.fails }}회 · {{ formatDate(l.last_at) }}</div>
      <template v-if="l.locked">
        <div class="text-[13px] font-bold text-red-600 bg-red-50 rounded-lg px-2.5 py-1.5 mt-2">잠김 · {{ l.retry_min }}분 뒤 자동으로 풀려요</div>
        <button @click="unlockLogin(l)" class="mt-2 w-full min-h-[48px] rounded-xl bg-blue-50 text-blue-600 text-[15px] font-bold">지금 풀기</button>
      </template>
    </div>
  </div>

  <!-- IP 차단 -->
  <div v-else-if="mTab === 'ips'" class="space-y-2.5">
    <form @submit.prevent="addBan" class="bg-white border border-gray-100 rounded-2xl p-3.5 space-y-2">
      <div class="text-[15px] font-bold text-ink">IP 주소 차단하기</div>
      <input v-model="newIp" inputmode="decimal" autocomplete="off" placeholder="예: 203.0.113.5 또는 203.0.113.0/24" aria-label="IP 주소 또는 대역" class="w-full min-h-[48px] rounded-xl border border-gray-200 px-3 font-mono" />
      <select v-model="banDays" aria-label="차단 기간" class="w-full min-h-[48px] rounded-xl border border-gray-200 px-3 bg-white">
        <option v-for="o in banDayOptions" :key="o.v" :value="o.v">{{ o.l }}</option>
      </select>
      <p class="text-[12px] text-ink-faint leading-relaxed">/24 같은 대역을 쓰면 그 범위 전체가 차단돼요. 기간이 지나면 자동으로 풀려요. 지금 접속 중인 내 IP 가 들어 있으면 막혀요.</p>
      <button type="submit" :disabled="!newIp.trim()" class="w-full min-h-[50px] rounded-xl bg-red-500 text-white text-[16px] font-bold disabled:opacity-40">차단</button>
    </form>
    <div v-if="!ipBans.length" class="text-center text-ink-muted py-8 text-[15px]">차단된 IP가 없어요</div>
    <div v-for="ban in ipBans" :key="ban.id" class="bg-white border border-gray-100 rounded-2xl p-3.5 flex items-center gap-3">
      <div class="min-w-0 flex-1"><div class="font-mono text-[16px] font-bold text-ink break-all">{{ ban.ip_address }}</div><div class="text-[12px] text-ink-faint">{{ banUntil(ban) }}</div><div v-if="ban.reason" class="text-[13px] text-ink-muted break-words">{{ ban.reason }}</div></div>
      <button @click="removeBanMobile(ban)" class="shrink-0 min-h-[44px] px-4 rounded-xl bg-gray-100 text-red-600 text-[14px] font-bold">차단 풀기</button>
    </div>
  </div>

  <!-- 차단된 사용자 -->
  <div v-else-if="mTab === 'users'" class="space-y-2.5">
    <div v-if="!bannedUsers.length" class="text-center text-ink-muted py-12 text-[15px]">차단된 사용자가 없어요</div>
    <div v-for="u in bannedUsers" :key="u.id" class="bg-white border border-gray-100 rounded-2xl p-3.5 flex items-center gap-3">
      <div class="min-w-0 flex-1"><div class="text-[16px] font-bold text-ink truncate">{{ u.nickname || u.name }}</div><div class="text-[13px] text-ink-muted break-words">{{ u.ban_reason || '사유 없음' }}</div></div>
      <button @click="unbanUserMobile(u)" class="shrink-0 min-h-[44px] px-4 rounded-xl bg-blue-50 text-blue-600 text-[14px] font-bold">해제</button>
    </div>
    <p class="text-[13px] text-ink-faint px-0.5">회원을 새로 정지하려면 회원관리에서 해당 회원을 열어 주세요.</p>
  </div>

  <!-- 서버 자동 차단 -->
  <div v-else-if="mTab === 'server'" class="space-y-2.5">
    <p class="text-[14px] text-ink-muted leading-relaxed">서버(SSH)에 비밀번호를 계속 시도한 IP를 서버가 1일 동안 자동으로 막아 둔 목록이에요. 웹사이트 로그인과는 별개예요.</p>
    <div v-if="serverBansMsg" class="text-center text-ink-muted py-8 text-[15px]">{{ serverBansMsg }}</div>
    <div v-else-if="!serverBans.length" class="text-center text-ink-muted py-8 text-[15px]">현재 차단된 IP가 없어요</div>
    <div v-if="!serverBansMsg && serverBans.length" class="flex items-center gap-2">
      <span class="shrink-0 text-[14px] font-bold text-red-600 bg-red-50 px-3 py-2 rounded-xl">차단 중 {{ serverBans.length }}개</span>
      <input v-model="banSearch" inputmode="decimal" autocomplete="off" placeholder="IP 검색" aria-label="IP 검색" class="flex-1 min-w-0 min-h-[44px] rounded-xl border border-gray-200 px-3 font-mono" />
    </div>
    <div v-for="ip in pagedBans" :key="ip" class="bg-white border border-gray-100 rounded-2xl px-3.5 py-2.5 flex items-center gap-3">
      <span class="min-w-0 flex-1 font-mono text-[15px] font-bold text-ink break-all">{{ ip }}</span>
      <button @click="serverUnban(ip)" class="shrink-0 min-h-[40px] px-4 rounded-xl bg-blue-50 text-blue-600 text-[14px] font-bold">풀기</button>
    </div>
    <div v-if="serverBans.length && !pagedBans.length" class="text-center text-ink-muted py-6 text-[15px]">검색 결과가 없어요</div>
    <div v-if="banLastPage > 1" class="flex items-center justify-between gap-2 pt-1">
      <button @click="banPage = Math.max(1, banPage - 1)" :disabled="banPage <= 1" class="min-h-[46px] px-5 rounded-xl bg-white border border-gray-200 text-[15px] font-bold disabled:opacity-40">이전</button>
      <span class="text-[14px] text-ink-muted tabular-nums">{{ banPage }} / {{ banLastPage }}</span>
      <button @click="banPage = Math.min(banLastPage, banPage + 1)" :disabled="banPage >= banLastPage" class="min-h-[46px] px-5 rounded-xl bg-white border border-gray-200 text-[15px] font-bold disabled:opacity-40">다음</button>
    </div>
  </div>

  <Teleport to="body">
    <!-- 메모 시트 -->
    <div v-if="noteReport" class="alv-m fixed inset-0 z-[70] bg-black/45 flex items-end" @click.self="noteReport = null">
      <div class="w-full bg-white rounded-t-3xl px-4 pt-2 max-h-[90vh] overflow-y-auto" role="dialog" aria-modal="true"
        :style="{ paddingBottom: 'calc(16px + env(safe-area-inset-bottom, 0px))' }">
        <div class="w-10 h-1 rounded bg-gray-200 mx-auto mb-3"></div>
        <div class="text-[17px] font-bold text-ink mb-1">관리자 메모</div>
        <div class="text-[14px] text-ink-muted mb-3 break-words">{{ noteReport.reason }}</div>
        <textarea v-model="noteText" rows="4" maxlength="500" placeholder="처리 내용을 적어 두세요" aria-label="관리자 메모" class="w-full rounded-xl border border-gray-200 px-3 py-3"></textarea>
        <button @click="saveNoteMobile" class="mt-3 w-full min-h-[52px] rounded-xl bg-amber-500 text-white text-[16px] font-bold">저장</button>
        <button @click="noteReport = null" class="mt-2 w-full min-h-[50px] rounded-xl bg-gray-100 text-ink text-[16px] font-bold">취소</button>
      </div>
    </div>
    <div v-if="toast" class="alv-m fixed left-1/2 -translate-x-1/2 z-[80] max-w-[90vw] px-4 py-3 rounded-xl text-[15px] font-bold text-white shadow-lg"
      :class="toast.error ? 'bg-red-600' : 'bg-ink'" :style="{ top: 'calc(70px + env(safe-area-inset-top, 0px))' }" role="status">{{ toast.text }}</div>
  </Teleport>
</div>

<!-- ───────── PC 화면 ───────── -->
<div v-else>
  <div v-if="toast" class="fixed top-5 right-5 z-[95] max-w-[90vw] px-5 py-3 rounded-xl text-sm font-medium text-white shadow-lg" :class="toast.error ? 'bg-red-500' : 'bg-green-500'" role="status">{{ toast.text }}</div>
  <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink mb-4">
    <span class="icon-chip w-9 h-9 bg-amber-50 text-amber-600"><AppIcon name="shield" :size="20" /></span>
    보안
  </h1>
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
    <!-- IP 차단 -->
    <div class="card overflow-hidden">
      <div class="px-4 py-3 border-b border-gray-50 flex items-center gap-2 font-bold text-sm text-ink">
        <span class="icon-chip w-7 h-7 bg-red-50 text-red-500"><AppIcon name="shield" :size="14" /></span>IP 차단 목록
      </div>
      <div v-for="ban in ipBans" :key="ban.id" class="px-4 py-2.5 border-b border-gray-50 last:border-0 flex justify-between text-sm">
        <div><span class="font-mono text-ink">{{ ban.ip_address }}</span> <span class="text-xs text-ink-muted ml-2">{{ ban.reason }}</span> <span class="text-[11px] text-ink-faint ml-2">{{ banUntil(ban) }}</span></div>
        <button @click="removeBan(ban)" class="text-red-400 hover:text-red-600 text-xs transition-colors">삭제</button>
      </div>
      <div v-if="!ipBans.length" class="px-4 py-4 text-sm text-ink-muted text-center">차단된 IP 없음</div>
      <div class="px-4 py-3 border-t border-gray-50 flex gap-2">
        <input v-model="newIp" placeholder="IP 또는 대역 (1.2.3.0/24)" class="input-soft flex-1 !w-auto !px-2 !py-1 !text-sm" />
        <select v-model="banDays" aria-label="차단 기간" class="input-soft !w-auto !px-2 !py-1 !text-xs"><option v-for="o in banDayOptions" :key="o.v" :value="o.v">{{ o.l }}</option></select>
        <button @click="addBan" class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded-lg text-xs font-bold transition-colors">차단</button>
      </div>
    </div>

    <!-- 차단 사용자 -->
    <div class="card overflow-hidden">
      <div class="px-4 py-3 border-b border-gray-50 flex items-center gap-2 font-bold text-sm text-ink">
        <span class="icon-chip w-7 h-7 bg-red-50 text-red-500"><AppIcon name="user" :size="14" /></span>차단된 사용자
      </div>
      <div v-for="u in bannedUsers" :key="u.id" class="px-4 py-2.5 border-b border-gray-50 last:border-0 flex justify-between items-center text-sm">
        <div>
          <span class="font-bold text-ink">{{ u.nickname || u.name }}</span>
          <span class="text-xs text-ink-muted ml-2">{{ u.ban_reason || '사유 없음' }}</span>
        </div>
        <button @click="unbanUser(u)" class="text-blue-500 hover:text-blue-700 text-xs font-bold transition-colors">해제</button>
      </div>
      <div v-if="!bannedUsers.length" class="px-4 py-4 text-sm text-ink-muted text-center">차단된 사용자 없음</div>
    </div>

    <!-- 로그인 잠금 (비밀번호를 계속 틀려서 잠긴 계정) -->
    <div class="card overflow-hidden">
      <div class="px-4 py-3 border-b border-gray-50 flex items-center gap-2 font-bold text-sm text-ink">
        <span class="icon-chip w-7 h-7 bg-amber-50 text-amber-600"><AppIcon name="lock" :size="14" /></span>로그인 실패 · 잠금 (최근 24시간)
      </div>
      <div v-for="l in loginLocks" :key="l.email + l.ip" class="px-4 py-2.5 border-b border-gray-50 last:border-0 flex justify-between items-center gap-2 text-sm">
        <div class="min-w-0">
          <div class="truncate text-ink font-semibold">{{ l.email }}</div>
          <div class="text-xs text-ink-muted"><span class="font-mono">{{ l.ip }}</span> · 실패 {{ l.fails }}회 · {{ formatDate(l.last_at) }}</div>
        </div>
        <div class="flex items-center gap-2 shrink-0">
          <span v-if="l.locked" class="text-[11px] font-bold text-red-600 bg-red-50 px-1.5 py-0.5 rounded-md">잠김 · {{ l.retry_min }}분 후 자동 해제</span>
          <button v-if="l.locked" @click="unlockLogin(l)" class="text-blue-500 hover:text-blue-700 text-xs font-bold transition-colors">지금 풀기</button>
        </div>
      </div>
      <div v-if="!loginLocks.length" class="px-4 py-4 text-sm text-ink-muted text-center">최근 로그인 실패 없음</div>
    </div>

    <!-- 서버(SSH) 자동 차단 IP -->
    <div class="card overflow-hidden">
      <div class="px-4 py-3 border-b border-gray-50 flex items-center gap-2 font-bold text-sm text-ink">
        <span class="icon-chip w-7 h-7 bg-red-50 text-red-500"><AppIcon name="shield" :size="14" /></span>서버 접속 자동 차단 IP (fail2ban)
      </div>
      <div class="px-4 pt-2 text-[11px] text-ink-muted">서버(SSH)에 비밀번호를 계속 시도한 IP를 서버가 1일 동안 자동으로 막아 둔 목록이에요. 웹사이트 로그인과는 별개예요.</div>
      <div v-if="!serverBansMsg && serverBans.length" class="px-4 pt-2 pb-2 flex items-center gap-2 border-b border-gray-50">
        <span class="text-xs font-bold text-red-600 bg-red-50 px-2 py-0.5 rounded-full whitespace-nowrap">차단 중 {{ serverBans.length }}개</span>
        <input v-model="banSearch" placeholder="IP 검색 (예: 195.211)" aria-label="IP 검색" class="input-soft flex-1 !w-auto !px-2 !py-1 !text-xs font-mono" />
      </div>
      <div v-if="pagedBans.length" class="grid grid-cols-1 sm:grid-cols-2">
        <div v-for="ip in pagedBans" :key="ip" class="px-4 py-1.5 border-b border-gray-50 flex justify-between items-center text-sm">
          <span class="font-mono text-ink text-[13px]">{{ ip }}</span>
          <button @click="serverUnban(ip)" class="text-blue-500 hover:text-blue-700 text-xs font-bold transition-colors">풀기</button>
        </div>
      </div>
      <div v-if="serverBans.length && !pagedBans.length" class="px-4 py-4 text-sm text-ink-muted text-center">검색 결과가 없어요</div>
      <div v-if="banLastPage > 1" class="px-4 py-2 flex items-center justify-center gap-3 text-xs">
        <button @click="banPage = Math.max(1, banPage - 1)" :disabled="banPage <= 1" class="px-2 py-1 rounded-lg font-bold text-ink-muted hover:bg-gray-100 disabled:opacity-40">이전</button>
        <span class="tabular-nums text-ink-muted">{{ banPage }} / {{ banLastPage }}</span>
        <button @click="banPage = Math.min(banLastPage, banPage + 1)" :disabled="banPage >= banLastPage" class="px-2 py-1 rounded-lg font-bold text-ink-muted hover:bg-gray-100 disabled:opacity-40">다음</button>
      </div>
      <div v-if="serverBansMsg" class="px-4 py-4 text-sm text-ink-muted text-center">{{ serverBansMsg }}</div>
      <div v-else-if="!serverBans.length" class="px-4 py-4 text-sm text-ink-muted text-center">현재 차단된 IP 없음</div>
    </div>
  </div>

  <!-- 신고 관리 — 한 줄에 한 건(표 목록). 줄을 누르면 그 신고의 처리 이력(누가·언제)이 펼쳐진다 -->
  <div class="mt-6 card overflow-hidden">
    <div class="px-4 py-3 border-b border-gray-50 flex items-center justify-between flex-wrap gap-2">
      <div class="flex items-center gap-2 font-bold text-sm text-ink">
        <span class="icon-chip w-7 h-7 bg-amber-50 text-amber-600"><AppIcon name="alert-circle" :size="14" /></span>신고 관리
        <span class="text-xs font-semibold text-ink-faint">총 {{ reportPagination.total }}건 · 대기 {{ pendingCount }}건</span>
      </div>
      <div class="flex items-center gap-2">
        <span class="text-[11px] text-ink-faint hidden xl:inline">시각은 애틀랜타(ET) 기준 · 마우스를 올리면 UTC</span>
        <select v-model="reportFilter.type" @change="reportFilter.page=1; loadReports()" class="input-soft !w-auto !px-2 !py-1 !text-xs">
          <option value="">전체 유형</option>
          <option v-for="o in reportTypes" :key="o.v" :value="o.v">{{ o.l }}</option>
        </select>
        <select v-model="reportFilter.status" @change="reportFilter.page=1; loadReports()" class="input-soft !w-auto !px-2 !py-1 !text-xs">
          <option value="">전체 상태</option>
          <option value="pending">확인 대기</option>
          <option value="resolved">처리 완료</option>
          <option value="dismissed">문제 없음(기각)</option>
        </select>
      </div>
    </div>

    <details class="px-4 py-2.5 border-b border-gray-50 bg-amber-50/40 text-[12px] text-ink-light">
      <summary class="cursor-pointer font-bold text-ink">신고를 어떻게 처리하나요? (눌러서 보기)</summary>
      <ul class="mt-2 space-y-1 leading-relaxed list-disc pl-4">
        <li><b>문제 없음</b> — 내용을 확인해 보니 문제가 없어서 아무 조치 없이 닫아요. 신고를 무시하는 게 아니라 "확인했고 이상 없음"으로 기록돼요.</li>
        <li><b>처리 완료</b> — 확인하고 조치(글 숨김·경고 등)를 끝냈을 때 닫아요.</li>
        <li><b>게시판 정지</b> — 글쓴이가 그 게시판을 며칠(또는 영구) 못 쓰게 막아요. 정지된 회원이 들어가면 "신고로 인하여 정지되어 사용할 수 없습니다"가 나와요. <b>정지가 3번 쌓이면 계정 이용이 정지</b>되고, 로그인할 때 "관리자에게 문의해 주세요"가 나와요.</li>
        <li><b>처리 취소</b> — 잘못 닫았을 때 다시 "확인 대기"로 되돌려요. 이미 건 게시판 정지는 아래 <b>정지 현황</b>에서 따로 풀어요.</li>
      </ul>
    </details>

    <div class="overflow-x-auto">
      <table class="w-full text-xs min-w-[900px]">
        <thead class="bg-gray-50 text-ink-muted">
          <tr class="text-left">
            <th class="px-3 py-2 font-semibold w-[68px]">상태</th>
            <th class="px-3 py-2 font-semibold">유형 · 대상</th>
            <th class="px-3 py-2 font-semibold">사유 / 내용</th>
            <th class="px-3 py-2 font-semibold">신고자</th>
            <th class="px-3 py-2 font-semibold">신고 시각</th>
            <th class="px-3 py-2 font-semibold">처리 시각</th>
            <th class="px-3 py-2 font-semibold">처리자</th>
            <th class="px-3 py-2 font-semibold text-right">처리</th>
          </tr>
        </thead>
        <tbody>
          <template v-for="r in reports" :key="r.id">
            <tr class="border-t border-gray-50 hover:bg-amber-50/30 align-top cursor-pointer" @click="toggleLog(r)">
              <td class="px-3 py-2.5">
                <span class="text-[11px] px-2 py-0.5 rounded-full font-bold whitespace-nowrap"
                  :class="{'bg-yellow-100 text-yellow-700': r.status==='pending', 'bg-green-100 text-green-700': r.status==='resolved', 'bg-gray-100 text-ink-light': r.status==='dismissed'}">{{ reportStatusLabel(r.status) }}</span>
              </td>
              <td class="px-3 py-2.5 whitespace-nowrap"><span class="badge-blue">{{ formatType(r.reportable_type) }}</span> <span class="text-ink-faint">#{{ r.reportable_id }}</span></td>
              <td class="px-3 py-2.5 max-w-[280px]">
                <div class="font-semibold text-ink truncate" :title="r.reason">{{ r.reason }}</div>
                <div v-if="r.content" class="text-ink-muted truncate" :title="r.content">{{ r.content }}</div>
                <div v-if="r.admin_note" class="mt-0.5 text-amber-700 truncate" :title="r.admin_note">📝 {{ r.admin_note }}</div>
              </td>
              <td class="px-3 py-2.5 whitespace-nowrap text-ink-light">{{ r.reporter ? (r.reporter.nickname || r.reporter.name) : '—' }}</td>
              <td class="px-3 py-2.5 whitespace-nowrap tabular-nums text-ink-light" :title="utcTime(r.created_at)">{{ fullTime(r.created_at) }}</td>
              <td class="px-3 py-2.5 whitespace-nowrap tabular-nums" :class="r.status==='pending' ? 'text-ink-faint' : 'text-ink-light'" :title="r.handled_at ? utcTime(r.handled_at) : ''">
                {{ r.status === 'pending' ? '—' : fullTime(r.handled_at) }}<span v-if="r.status !== 'pending' && handledEstimated(r)" class="ml-1 text-[10px] text-amber-600" title="옛 기록이라 정확한 처리 시각이 남아 있지 않아 마지막 수정 시각으로 표시한 값이에요">(추정)</span>
              </td>
              <td class="px-3 py-2.5 whitespace-nowrap text-ink-light">{{ r.status === 'pending' ? '—' : handlerName(r) }}</td>
              <td class="px-3 py-2.5 text-right whitespace-nowrap" @click.stop>
                <template v-if="r.status === 'pending'">
                  <button @click="resolveReport(r, 'dismissed')" :disabled="busyId === r.id" title="확인해 보니 문제가 없어서 아무 조치 없이 닫아요 (신고를 무시하는 게 아니라 '이상 없음'으로 확인한 거예요)" class="bg-gray-300 text-ink-light text-[11px] font-bold px-2 py-1 rounded-lg hover:bg-gray-400 disabled:opacity-50 transition-colors">문제 없음</button>
                  <button @click="resolveReport(r, 'resolved')" :disabled="busyId === r.id" title="확인하고 조치(글 숨김·경고 등)를 끝냈을 때 닫아요" class="ml-1 bg-green-500 text-white text-[11px] font-bold px-2 py-1 rounded-lg hover:bg-green-600 disabled:opacity-50 transition-colors">처리 완료</button>
                  <button @click="openSuspend(r)" :disabled="busyId === r.id" title="글쓴이가 그 게시판을 일정 기간(또는 영구) 못 쓰게 막아요. 3번 쌓이면 계정 이용이 정지돼요" class="ml-1 bg-red-500 text-white text-[11px] font-bold px-2 py-1 rounded-lg hover:bg-red-600 disabled:opacity-50 transition-colors">게시판 정지</button>
                </template>
                <button v-else @click="resolveReport(r, 'pending')" :disabled="busyId === r.id" title="잘못 닫았을 때 다시 '확인 대기'로 되돌려요 (이미 건 게시판 정지는 아래 '정지 현황'에서 풀어요)" class="text-[11px] font-bold px-2 py-1 rounded-lg border border-gray-200 text-ink-light hover:bg-gray-50 disabled:opacity-50 transition-colors">처리 취소</button>
                <button @click="toggleLog(r)" class="ml-1 text-[11px] font-bold px-2 py-1 rounded-lg border border-gray-200 text-ink-light hover:bg-gray-50 transition-colors">{{ openLogId === r.id ? '접기' : '이력' }}</button>
                <button v-if="isSuperAdmin" @click="deleteReport(r)" :disabled="busyId === r.id" class="ml-1 text-[11px] font-bold px-2 py-1 rounded-lg border border-red-200 text-red-600 hover:bg-red-50 disabled:opacity-50 transition-colors">삭제</button>
              </td>
            </tr>
            <!-- 처리 이력(타임라인) + 메모 -->
            <tr v-if="openLogId === r.id" class="bg-gray-50/70">
              <td colspan="8" class="px-4 py-3">
                <div class="text-[11px] font-bold text-ink-muted mb-2">처리 이력 (신고 #{{ r.id }})</div>
                <ol class="space-y-1.5">
                  <li v-for="l in (r.logs || [])" :key="l.id" class="flex items-start gap-3">
                    <span class="w-[150px] shrink-0 tabular-nums text-ink-light" :title="utcTime(l.created_at)">{{ fullTime(l.created_at) }}</span>
                    <span class="shrink-0 text-[11px] px-2 py-0.5 rounded-full font-bold" :class="logClass(l.action)">{{ logLabel(l.action) }}</span>
                    <span class="shrink-0 w-[90px] truncate text-ink-light">{{ l.actor_name || (l.action === 'created' ? '—' : '기록 없음') }}</span>
                    <span class="min-w-0 flex-1 text-ink-muted break-words">{{ l.note || '' }}<span v-if="l.estimated" class="ml-1 text-[10px] text-amber-600">(시각 추정)</span></span>
                  </li>
                  <li v-if="!(r.logs || []).length" class="text-ink-faint">이력이 없어요</li>
                </ol>
                <div class="mt-3 flex gap-2">
                  <input v-model="noteDraft" placeholder="관리자 메모 (저장하면 이력에 남아요)" maxlength="500" class="input-soft flex-1 !w-auto !px-2 !py-1 !text-xs" @keyup.enter="saveNoteLog(r)" />
                  <button @click="saveNoteLog(r)" :disabled="busyId === r.id || noteDraft === (r.admin_note || '')" class="btn-primary !px-3 !py-1 !text-xs disabled:opacity-50">메모 저장</button>
                </div>
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>

    <div v-if="!reports.length" class="px-4 py-8 text-sm text-ink-muted text-center">{{ reportsLoading ? '불러오는 중...' : '신고 없음' }}</div>

    <!-- 페이지 나눔 -->
    <div v-if="reportPagination.lastPage > 1" class="px-4 py-3 border-t border-gray-50 flex items-center justify-center gap-2">
      <button @click="goReportPage(reportFilter.page - 1)" :disabled="reportFilter.page <= 1" class="px-3 h-8 rounded-lg text-xs font-bold text-ink-muted hover:bg-gray-100 disabled:opacity-40">이전</button>
      <button v-for="p in reportPageNumbers" :key="p" @click="goReportPage(p)"
        class="w-8 h-8 rounded-lg text-xs font-bold transition-colors"
        :class="p === reportPagination.currentPage ? 'bg-amber-400 text-white' : 'text-ink-muted hover:bg-gray-100'">{{ p }}</button>
      <button @click="goReportPage(reportFilter.page + 1)" :disabled="reportFilter.page >= reportPagination.lastPage" class="px-3 h-8 rounded-lg text-xs font-bold text-ink-muted hover:bg-gray-100 disabled:opacity-40">다음</button>
    </div>
  </div>

  <!-- 게시판 정지 현황 -->
  <div class="mt-6 card overflow-hidden">
    <div class="px-4 py-3 border-b border-gray-50 flex items-center gap-2 font-bold text-sm text-ink">
      <span class="icon-chip w-7 h-7 bg-red-50 text-red-600"><AppIcon name="alert-circle" :size="14" /></span>게시판 정지 현황
      <span class="text-xs font-semibold text-ink-faint">지금 정지 중 {{ suspensions.length }}건 · 정지 3번이면 계정 이용 정지</span>
    </div>
    <div v-if="!suspensions.length" class="px-4 py-6 text-sm text-ink-muted text-center">정지 중인 회원이 없어요.</div>
    <table v-else class="w-full text-xs">
      <thead class="bg-gray-50 text-ink-muted"><tr class="text-left">
        <th class="px-3 py-2 font-semibold">회원</th><th class="px-3 py-2 font-semibold">게시판</th><th class="px-3 py-2 font-semibold">정지 기간</th><th class="px-3 py-2 font-semibold">누적</th><th class="px-3 py-2 font-semibold">사유</th><th class="px-3 py-2 font-semibold text-right">처리</th>
      </tr></thead>
      <tbody>
        <tr v-for="s in suspensions" :key="s.id" class="border-t border-gray-50 align-top">
          <td class="px-3 py-2.5"><div class="font-semibold text-ink">{{ s.user?.name || '—' }}</div><div class="text-ink-faint">{{ s.user?.email }}</div></td>
          <td class="px-3 py-2.5 whitespace-nowrap">{{ s.board }}</td>
          <td class="px-3 py-2.5 whitespace-nowrap">{{ s.permanent ? '영구' : fullTime(s.ends_at) + '까지' }}</td>
          <td class="px-3 py-2.5 whitespace-nowrap"><b :class="s.strikes >= 3 ? 'text-red-600' : 'text-ink'">{{ s.strikes }}/3회</b><span v-if="s.user?.is_banned" class="ml-1 text-[10px] bg-red-100 text-red-700 px-1.5 py-0.5 rounded-full font-bold">계정 정지</span></td>
          <td class="px-3 py-2.5 text-ink-muted max-w-[240px] truncate" :title="s.reason">{{ s.reason || '—' }}</td>
          <td class="px-3 py-2.5 text-right"><button @click="liftSuspension(s)" class="text-[11px] font-bold px-2 py-1 rounded-lg border border-gray-200 text-ink-light hover:bg-gray-50">정지 풀기</button></td>
        </tr>
      </tbody>
    </table>
  </div>
</div>

<!-- 게시판 정지 창 (PC·휴대폰 공용) -->
<div v-if="susp.open" class="fixed inset-0 z-[80] bg-black/40 flex items-end md:items-center justify-center p-3" @click.self="susp.open = false">
  <div class="bg-white rounded-2xl w-full max-w-md max-h-[90vh] overflow-y-auto p-4 shadow-xl">
    <div class="flex items-center mb-2"><div class="text-[16px] font-extrabold text-ink">게시판 정지</div><button class="ml-auto text-ink-muted text-xl leading-none px-1" @click="susp.open = false" aria-label="닫기">×</button></div>
    <div v-if="susp.loading" class="py-8 text-center text-sm text-ink-muted">불러오는 중…</div>
    <div v-else-if="susp.error && !susp.info" class="text-sm text-red-600 py-4">{{ susp.error }}</div>
    <template v-else-if="susp.info">
      <div v-if="!susp.info.owner" class="rounded-xl bg-yellow-50 text-yellow-800 text-[13px] p-3 mb-3">글쓴이를 찾을 수 없어요. 글이 이미 지워졌을 수 있어요.</div>
      <div v-else-if="susp.info.is_staff_target" class="rounded-xl bg-yellow-50 text-yellow-800 text-[13px] p-3 mb-3">운영진 계정은 신고로 정지할 수 없어요.</div>
      <template v-else>
        <div class="rounded-xl bg-gray-50 p-3 text-[13px] mb-3">
          <div>정지 대상: <b class="text-ink">{{ susp.info.owner.name }}</b> <span class="text-ink-faint">({{ susp.info.owner.email }})</span></div>
          <div class="mt-0.5">지금까지 정지 <b :class="susp.info.owner.strikes >= susp.info.max_strikes - 1 ? 'text-red-600' : 'text-ink'">{{ susp.info.owner.strikes }}회</b> · 이번에 정지하면 <b>{{ susp.info.owner.strikes + 1 }}회</b><span v-if="susp.info.owner.strikes + 1 >= susp.info.max_strikes" class="text-red-600 font-bold"> → 계정 이용이 정지돼요</span></div>
        </div>
        <label class="block text-[12px] font-bold text-ink-light mb-1">어느 게시판을 막을까요?</label>
        <select v-model="susp.board" class="w-full input-soft !py-2 !px-3 text-sm mb-3">
          <option v-for="b in susp.info.boards" :key="b.key" :value="b.key">{{ b.label }}{{ b.key === susp.info.suggested_board ? ' (신고된 글이 있는 게시판)' : '' }}</option>
        </select>
        <label class="block text-[12px] font-bold text-ink-light mb-1">얼마나 막을까요?</label>
        <div class="flex flex-wrap gap-1.5 mb-3">
          <button v-for="d in suspDurations" :key="d.v" type="button" @click="susp.days = d.v"
            class="px-3 py-1.5 rounded-full border text-[12px] font-bold" :class="susp.days === d.v ? 'bg-red-500 text-white border-red-500' : 'bg-white text-ink-light border-gray-200'">{{ d.l }}</button>
        </div>
        <label class="block text-[12px] font-bold text-ink-light mb-1">메모 (선택 · 처리 이력에 남아요)</label>
        <textarea v-model="susp.note" rows="2" maxlength="500" class="w-full input-soft !py-2 !px-3 text-sm mb-3" placeholder="예: 광고성 글 반복"></textarea>
        <p class="text-[11px] text-ink-faint leading-relaxed mb-3">정지되면 이 회원은 선택한 게시판에 들어갈 수 없고, "이 게시판은 신고로 인하여 정지되어 사용할 수 없습니다" 안내가 나와요. 이 신고는 "처리 완료"로 닫혀요.</p>
        <div v-if="susp.error" class="text-[12px] text-red-600 mb-2">{{ susp.error }}</div>
        <div class="flex gap-2">
          <button type="button" class="flex-1 py-2.5 rounded-xl border border-gray-200 text-sm font-bold text-ink-light" @click="susp.open = false">취소</button>
          <button type="button" :disabled="susp.busy || !susp.board" class="flex-1 py-2.5 rounded-xl bg-red-500 text-white text-sm font-bold disabled:opacity-40" @click="submitSuspend">{{ susp.busy ? '처리 중…' : '정지하기' }}</button>
        </div>
      </template>
    </template>
  </div>
</div>
</template>
<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch, inject } from 'vue'
import { useAuthStore } from '../../stores/auth'
import { useRoute } from 'vue-router'
import axios from 'axios'
import AppIcon from '../../components/AppIcon.vue'
import { useAdminConfirm } from '../../composables/useAdminConfirm'

// 알림·"오늘 할 일" 카드의 바로가기 주소(?tab=reports&status=pending)를 반영하기 위해 사용
const route = useRoute()
// 관리자 공용 확인 시트(할 일 #83 첫 적용 화면) — 차단 해제 같은 되돌리기 어려운 동작 전 확인
const { confirmAction } = useAdminConfirm()

// 관리자 휴대폰 화면이면 구역 칩 + 카드로 보여 줌 (AdminLayout 이 알려 줌)
const adminIsMobile = inject('adminIsMobile', ref(false))
const isMobile = computed(() => !!adminIsMobile.value)

const ipBans = ref([])
const reports = ref([])
const bannedUsers = ref([])
const newIp = ref('')
const banDays = ref('')
const banDayOptions = [{ v: '', l: '영구 차단' }, { v: 1, l: '1일' }, { v: 7, l: '7일' }, { v: 30, l: '30일' }, { v: 90, l: '90일' }]
// 만료 시각은 UTC 로 저장돼 있어 애틀랜타 날짜로 보여 준다
function banUntil(b) {
  if (!b.expires_at) return '영구 차단'
  const d = new Date(b.expires_at); if (isNaN(d)) return ''
  return d.toLocaleDateString('ko-KR', { timeZone: 'America/New_York', month: 'numeric', day: 'numeric' }) + ' 까지 (애틀랜타)'
}
const loginLocks = ref([])
const serverBans = ref([])
const serverBansMsg = ref('')
const reportFilter = ref({ type: '', status: '', page: 1 })
const reportPagination = ref({ currentPage: 1, lastPage: 1, total: 0 })
const editNoteId = ref(null)
const editNote = ref('')

// ── 신고 목록(표)·처리 이력 ──
const openLogId = ref(null)     // 이력을 펼친 신고 id
const noteDraft = ref('')       // 이력 칸의 메모 입력값
function toggleLog(r) {
  if (openLogId.value === r.id) { openLogId.value = null; return }
  openLogId.value = r.id
  noteDraft.value = r.admin_note || ''
}
// 시각은 애틀랜타(ET) 기준 초 단위로 정확히 — 마우스를 올리면 UTC 가 보인다 (DB 는 UTC 저장)
const ET_ZONE = 'America/New_York'
function fullTime(dt) {
  if (!dt) return '—'
  const d = new Date(dt)
  if (isNaN(d)) return '—'
  const p = new Intl.DateTimeFormat('en-CA', { timeZone: ET_ZONE, year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false })
    .formatToParts(d).reduce((a, x) => { a[x.type] = x.value; return a }, {})
  return `${p.year}-${p.month}-${p.day} ${p.hour === '24' ? '00' : p.hour}:${p.minute}:${p.second}`
}
function utcTime(dt) {
  const d = new Date(dt)
  return isNaN(d) ? '' : d.toISOString().replace('T', ' ').slice(0, 19) + ' UTC'
}
function lastHandledLog(r) {
  const hs = (r.logs || []).filter(l => ['resolved', 'dismissed'].includes(l.action))
  return hs.length ? hs[hs.length - 1] : null
}
// 옛 기록은 정확한 처리 시각이 저장된 적이 없어 마지막 수정 시각으로 채웠다 → (추정) 표시
function handledEstimated(r) { const l = lastHandledLog(r); return !!(l && l.estimated) }
function handlerName(r) {
  if (r.handler) return r.handler.nickname || r.handler.name
  return lastHandledLog(r)?.actor_name || '기록 없음'
}
const logLabel = (a) => ({ created: '신고 접수', resolved: '처리 완료', dismissed: '문제 없음', reopened: '처리 취소', suspend: '게시판 정지', note: '메모', hide_content: '콘텐츠 숨김', restore_content: '콘텐츠 복구', status: '상태 변경' }[a] || a)
const logClass = (a) => ({
  created: 'bg-blue-50 text-blue-700', resolved: 'bg-green-100 text-green-700', dismissed: 'bg-gray-200 text-ink-light',
  reopened: 'bg-yellow-100 text-yellow-700', note: 'bg-amber-50 text-amber-700', hide_content: 'bg-red-50 text-red-600', restore_content: 'bg-green-50 text-green-700', suspend: 'bg-red-100 text-red-700',
}[a] || 'bg-gray-100 text-ink-light')
const reportPageNumbers = computed(() => {
  const last = reportPagination.value.lastPage, cur = reportPagination.value.currentPage
  const from = Math.max(1, cur - 2), to = Math.min(last, cur + 2)
  return Array.from({ length: to - from + 1 }, (_, i) => from + i)
})
async function saveNoteLog(r) {
  try {
    busyId.value = r.id
    const { data } = await axios.put('/api/admin/reports/' + r.id, { admin_note: noteDraft.value })
    if (data?.data) Object.assign(r, data.data); else r.admin_note = noteDraft.value
    noteDraft.value = r.admin_note || ''
  } catch {}
  finally { busyId.value = null }
}

// ── 서버(fail2ban) 자동 차단 IP — 개수·검색·페이지 ──
const banSearch = ref('')
const banPage = ref(1)
const filteredBans = computed(() => {
  const q = banSearch.value.trim()
  return q ? serverBans.value.filter(ip => ip.includes(q)) : serverBans.value
})
const banPerPage = computed(() => (isMobile.value ? 10 : 20))
const banLastPage = computed(() => Math.max(1, Math.ceil(filteredBans.value.length / banPerPage.value)))
const pagedBans = computed(() => filteredBans.value.slice((banPage.value - 1) * banPerPage.value, banPage.value * banPerPage.value))
watch(banSearch, () => { banPage.value = 1 })
watch(banLastPage, (n) => { if (banPage.value > n) banPage.value = n })

// ── 휴대폰 화면 전용 상태 ──
const mTab = ref('reports')
const reportsLoading = ref(false)
const busyId = ref(null)
const noteReport = ref(null)
const noteText = ref('')
const toast = ref(null)
let toastTimer = null
function say(text, error = false) {
  toast.value = { text, error }
  clearTimeout(toastTimer)
  toastTimer = setTimeout(() => { toast.value = null }, 3000)
}
const reportStatusChips = [{ v: 'pending', l: '확인 대기' }, { v: 'resolved', l: '처리 완료' }, { v: 'dismissed', l: '문제 없음' }, { v: '', l: '전체' }]
const reportTypes = [{ v: 'User', l: '사용자' }, { v: 'Post', l: '게시글' }, { v: 'MarketItem', l: '중고장터' }, { v: 'RealEstateListing', l: '부동산' }, { v: 'Comment', l: '댓글' }, { v: 'ChatMessage', l: '채팅' }, { v: 'GroupBuy', l: '공동구매' }]
const reportStatusLabel = (st) => ({ pending: '확인 대기', resolved: '처리 완료', dismissed: '문제 없음' }[st] || st)
const pendingCount = ref(0)
const mTabs = computed(() => [
  { key: 'reports', label: '신고', badge: pendingCount.value },
  { key: 'locks', label: '로그인 잠금', badge: loginLocks.value.filter(l => l.locked).length },
  { key: 'ips', label: 'IP 차단' },
  { key: 'users', label: '차단 사용자' },
  { key: 'server', label: '서버 차단' },
])
function setReportStatus(v) { reportFilter.value.status = v; reportFilter.value.page = 1; loadReports() }
function setReportType() { reportFilter.value.page = 1; loadReports() }
function goReportPage(n) { if (n >= 1 && n <= reportPagination.value.lastPage) { reportFilter.value.page = n; loadReports() } }
function openNote(r) { noteReport.value = r; noteText.value = r.admin_note || '' }
async function saveNoteMobile() {
  const r = noteReport.value
  if (!r) return
  try {
    await axios.put('/api/admin/reports/' + r.id, { admin_note: noteText.value })
    r.admin_note = noteText.value
    noteReport.value = null
    say('메모를 저장했어요')
  } catch { say('메모를 저장하지 못했어요', true) }
}
async function removeBanMobile(b) {
  if (!await confirmAction({ title: 'IP 차단 풀기', message: `${b.ip_address} 차단을 풀까요?`, kind: 'danger', confirmLabel: '풀기' })) return
  try { await axios.delete('/api/admin/ip-bans/' + b.id); ipBans.value = ipBans.value.filter(x => x.id !== b.id); say('차단을 풀었어요') }
  catch { say('풀지 못했어요', true) }
}
async function unbanUserMobile(u) {
  if (!await confirmAction({ title: '회원 차단 풀기', message: `${u.nickname || u.name} 님의 차단을 풀까요?`, kind: 'danger', confirmLabel: '풀기' })) return
  try { await axios.post(`/api/admin/chat/users/${u.id}/permaban`, { unban: true }); bannedUsers.value = bannedUsers.value.filter(x => x.id !== u.id); say('차단을 풀었어요') }
  catch { say('풀지 못했어요', true) }
}
async function loadPendingCount() {
  try {
    const { data } = await axios.get('/api/admin/reports', { params: { status: 'pending', page: 1 } })
    pendingCount.value = data.data?.total ?? (data.data?.data || data.data || []).length
  } catch {}
}
// 시트가 열려 있는 동안 뒤쪽 화면이 같이 스크롤되지 않게
watch(() => isMobile.value && !!noteReport.value, locked => { document.body.style.overflow = locked ? 'hidden' : '' })
onBeforeUnmount(() => { document.body.style.overflow = ''; clearTimeout(toastTimer) })

function formatType(t) {
  if (!t) return '?'
  const name = t.replace(/^App\\Models\\/, '')
  const map = { User: '사용자', Post: '게시글', MarketItem: '중고장터', RealEstateListing: '부동산', Comment: '댓글', ChatMessage: '채팅', GroupBuy: '공동구매', AmazonProduct: '내돈내산', user: '사용자', post: '게시글', comment: '댓글', chat_message: '채팅' }
  return map[name] || name
}

function formatDate(dt) {
  if (!dt) return ''
  const d = new Date(dt)
  return `${d.getFullYear()}.${d.getMonth()+1}.${d.getDate()} ${String(d.getHours()).padStart(2,'0')}:${String(d.getMinutes()).padStart(2,'0')}`
}

// 바로가기 주소 반영: ?tab=reports|locks|ips|users|server (휴대폰 구역), ?status=pending|resolved|dismissed|all (신고 상태)
function applyDeepLink() {
  const t = String(route.query.tab || '')
  const s = route.query.status
  if (['reports', 'locks', 'ips', 'users', 'server'].includes(t)) mTab.value = t
  if (typeof s === 'string' && ['pending', 'resolved', 'dismissed', 'all'].includes(s)) reportFilter.value.status = s === 'all' ? '' : s
}
watch(() => route.fullPath, () => {
  if (route.path !== '/admin/security' || (!route.query.tab && !route.query.status)) return
  applyDeepLink(); reportFilter.value.page = 1; loadReports()
})

onMounted(async () => {
  if (isMobile.value) reportFilter.value.status = 'pending'
  applyDeepLink()
  loadPendingCount()
  loadIpBans()
  loadReports()
  loadSuspensions()
  loadBannedUsers()
  loadLoginLocks()
  loadServerBans()
})

async function loadIpBans() {
  try { const { data } = await axios.get('/api/admin/ip-bans'); ipBans.value = data.data || [] } catch {}
}

async function loadReports() {
  reportsLoading.value = true
  try {
    const { data } = await axios.get('/api/admin/reports', {
      params: { type: reportFilter.value.type, status: reportFilter.value.status, page: reportFilter.value.page }
    })
    const d = data.data
    reports.value = d?.data || d || []
    reportPagination.value = { currentPage: d?.current_page || 1, lastPage: d?.last_page || 1, total: d?.total ?? reports.value.length }
  } catch {}
  reportsLoading.value = false
}

async function loadBannedUsers() {
  try {
    const { data } = await axios.get('/api/admin/users', { params: { banned: 1, per_page: 50 } })
    bannedUsers.value = (data.data?.data || data.data || []).filter(u => u.is_banned)
  } catch {}
}

async function loadLoginLocks() {
  try { const { data } = await axios.get('/api/admin/security/login-locks'); loginLocks.value = data.data || [] } catch {}
}

async function unlockLogin(l) {
  try { await axios.post('/api/admin/security/login-unlock', { email: l.email, ip: l.ip }); loadLoginLocks(); if (isMobile.value) say('잠금을 풀었어요') } catch { if (isMobile.value) say('풀지 못했어요', true) }
}

async function loadServerBans() {
  try {
    const { data } = await axios.get('/api/admin/security/server-bans')
    serverBans.value = data.data || []
    serverBansMsg.value = data.available === false ? (data.message || '조회할 수 없어요') : ''
  } catch { serverBansMsg.value = '조회할 수 없어요 (최고 관리자만 볼 수 있어요)' }
}

async function serverUnban(ip) {
  if (!await confirmAction({ title: '서버 차단 풀기', message: `${ip} 차단을 풀까요?`, kind: 'danger', confirmLabel: '풀기' })) return
  try { await axios.post('/api/admin/security/server-unban', { ip }); loadServerBans(); if (isMobile.value) say('차단을 풀었어요') } catch { if (isMobile.value) say('풀지 못했어요', true) }
}

async function addBan() {
  if (!newIp.value) return
  try {
    await axios.post('/api/admin/ip-bans', { ip_address: newIp.value.trim(), reason: '관리자 차단', days: banDays.value || undefined })
    newIp.value = ''; loadIpBans()
    say('차단했어요')
  } catch (e) {
    say(e.response?.data?.errors?.ip_address?.[0] || e.response?.data?.message || '차단하지 못했어요', true)
  }
}

async function removeBan(b) {
  // PC 표에는 원래 확인창이 없어 실수로 누르면 바로 풀렸음 — 공용 확인 시트로 통일
  if (!await confirmAction({ title: 'IP 차단 풀기', message: `${b.ip_address} 차단을 풀까요?`, kind: 'danger', confirmLabel: '풀기' })) return
  try { await axios.delete('/api/admin/ip-bans/' + b.id); ipBans.value = ipBans.value.filter(x => x.id !== b.id) } catch {}
}

async function resolveReport(r, status) {
  try {
    busyId.value = r.id
    // 서버가 처리 이력(누가·언제)까지 기록해 돌려준다 — 그 값으로 이 줄을 갱신
    const { data } = await axios.put('/api/admin/reports/' + r.id, { status, admin_note: r.admin_note || '' })
    if (data?.data) Object.assign(r, data.data); else r.status = status
    if (isMobile.value) say(status === 'resolved' ? '해결 처리했어요' : (status === 'dismissed' ? '기각했어요' : '다시 대기로 돌렸어요'))
    // 상태 필터가 걸린 목록에서는 상태가 바뀐 신고를 목록에서 바로 뺌
    if (reportFilter.value.status && reportFilter.value.status !== status) reports.value = reports.value.filter(x => x.id !== r.id)
    loadPendingCount()
  } catch { if (isMobile.value) say('처리하지 못했어요', true) }
  finally { busyId.value = null }
}

// 시험 신고 삭제 — 최고관리자만 (처리 이력도 같이 지워지고, 신고로 숨긴 글이 있으면 다시 보이게 돼요)
const isSuperAdmin = computed(() => useAuthStore().user?.role === 'super_admin')
async function deleteReport(r) {
  if (!await confirmAction({ title: '신고 삭제', message: `신고 #${r.id} (${r.reason || '사유 없음'})와 처리 이력을 지울까요? 되돌릴 수 없어요.`, kind: 'danger', confirmLabel: '삭제' })) return
  try {
    busyId.value = r.id
    await axios.delete('/api/admin/reports/' + r.id)
    reports.value = reports.value.filter(x => x.id !== r.id)
    if (reportPagination.value.total > 0) reportPagination.value.total--
    if (openLogId.value === r.id) openLogId.value = null
    loadPendingCount()
    say('신고를 삭제했어요')
  } catch (e) { say(e.response?.data?.message || '삭제하지 못했어요', true) }
  finally { busyId.value = null }
}

// 게시판 정지 — 신고를 확인한 뒤 글쓴이를 그 게시판에서 일정 기간(또는 영구) 막는다. 3번 쌓이면 계정 이용 정지(서버가 처리)
const suspDurations = [{ v: 7, l: '7일' }, { v: 30, l: '30일' }, { v: 90, l: '3개월' }, { v: 180, l: '6개월' }, { v: 365, l: '1년' }, { v: 0, l: '영구' }]
const susp = ref({ open: false, loading: false, busy: false, report: null, info: null, board: '', days: 30, note: '', error: '' })
const suspensions = ref([])
async function loadSuspensions() {
  try { suspensions.value = (await axios.get('/api/admin/board-suspensions')).data.data || [] } catch { /* 목록만 못 불러온 것 */ }
}
async function openSuspend(r) {
  susp.value = { open: true, loading: true, busy: false, report: r, info: null, board: '', days: 30, note: '', error: '' }
  try {
    const info = (await axios.get('/api/admin/reports/' + r.id + '/suspend-info')).data.data
    susp.value.info = info
    susp.value.board = info.suggested_board || info.boards?.[0]?.key || ''
  } catch (e) { susp.value.error = e.response?.data?.message || '정보를 불러오지 못했어요.' }
  susp.value.loading = false
}
async function submitSuspend() {
  const st = susp.value
  if (st.busy || !st.board) return
  st.busy = true; st.error = ''
  try {
    const { data } = await axios.post('/api/admin/reports/' + st.report.id + '/suspend', { board_key: st.board, days: st.days || null, note: st.note || null })
    if (data?.data) Object.assign(st.report, data.data)
    // 상태 필터가 걸린 목록에서는 처리된 신고를 목록에서 바로 뺌
    if (reportFilter.value.status && reportFilter.value.status !== 'resolved') reports.value = reports.value.filter(x => x.id !== st.report.id)
    st.open = false
    loadPendingCount(); loadSuspensions()
    say(data.message || '정지했어요')
  } catch (e) { st.error = e.response?.data?.message || '정지하지 못했어요.' }
  st.busy = false
}
async function liftSuspension(s) {
  if (!await confirmAction({ title: '게시판 정지 풀기', message: `${s.user?.name || '이 회원'} 님의 "${s.board}" 정지를 풀까요?`, kind: 'danger', confirmLabel: '풀기' })) return
  try {
    await axios.post('/api/admin/board-suspensions/' + s.id + '/lift')
    suspensions.value = suspensions.value.filter(x => x.id !== s.id)
    say('정지를 풀었어요')
  } catch (e) { say(e.response?.data?.message || '풀지 못했어요', true) }
}

async function saveNote(r) {
  try {
    await axios.put('/api/admin/reports/' + r.id, { admin_note: editNote.value })
    r.admin_note = editNote.value
    editNoteId.value = null
  } catch {}
}

async function unbanUser(u) {
  try {
    await axios.post(`/api/admin/chat/users/${u.id}/permaban`, { unban: true })
    bannedUsers.value = bannedUsers.value.filter(x => x.id !== u.id)
  } catch {}
}
</script>
<style>
/* 휴대폰 관리자: 입력창 글자가 16px 보다 작으면 iOS 가 화면을 확대해 버림 */
.alv-m input, .alv-m textarea, .alv-m select { font-size: 16px; }
</style>
