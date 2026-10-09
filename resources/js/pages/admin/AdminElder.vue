<template>
<!-- ───────── 휴대폰 화면 ───────── -->
<div v-if="isMobile" class="alv-m space-y-3 pb-4">
  <button v-if="(overview.sos_unresolved || 0) > 0 && tab !== 'sos'" @click="switchTab('sos')" class="w-full min-h-[56px] rounded-2xl bg-red-600 text-white px-4 flex items-center justify-between text-[16px] font-bold">
    <span>🚨 미해결 SOS {{ overview.sos_unresolved }}건</span><span class="text-[14px] font-medium">보러가기 ›</span>
  </button>
  <div class="flex gap-2 overflow-x-auto scrollbar-hide" role="group" aria-label="안심서비스 메뉴">
    <button v-for="t in tabs" :key="t.key" @click="switchTab(t.key)" :aria-pressed="tab === t.key"
      class="shrink-0 min-h-[44px] px-4 rounded-full border text-[15px]" :class="tab === t.key ? 'bg-ink text-white border-ink font-bold' : 'bg-white text-ink border-gray-200 font-medium'">{{ t.label }}</button>
  </div>

  <!-- 통계 -->
  <div v-if="tab === 'overview'">
    <div v-if="overviewLoading" class="text-center py-10 text-ink-muted text-[15px]">불러오는 중...</div>
    <div v-else class="grid grid-cols-2 gap-2">
      <div v-for="o in mOverview" :key="o.label" class="bg-white border border-gray-100 rounded-2xl p-3.5">
        <div class="text-[13px] text-ink-muted">{{ o.label }}</div>
        <div class="text-[26px] font-black tabular-nums" :class="o.color">{{ o.value }}</div>
      </div>
    </div>
  </div>

  <!-- 매칭 -->
  <div v-else-if="tab === 'guardians'" class="space-y-2.5">
    <form @submit.prevent="loadGuardians(1)" class="flex gap-2">
      <label class="flex-1 min-w-0 flex items-center gap-2 bg-white border border-gray-200 rounded-xl px-3 min-h-[48px] text-ink-muted">
        <AppIcon name="search" :size="18" />
        <input v-model="search" type="search" placeholder="이름·이메일 검색" aria-label="이름 또는 이메일 검색" autocomplete="off" class="w-full min-w-0 bg-transparent outline-none text-ink" />
      </label>
      <button type="submit" class="shrink-0 min-h-[48px] px-4 rounded-xl bg-amber-500 text-white text-[15px] font-bold">검색</button>
    </form>
    <div class="flex gap-2 overflow-x-auto scrollbar-hide" role="group" aria-label="매칭 상태">
      <button v-for="f in [['','전체'],['active','활성'],['pending','대기'],['rejected','거절']]" :key="f[0]" @click="statusFilter = f[0]; loadGuardians(1)" :aria-pressed="statusFilter === f[0]"
        class="shrink-0 min-h-[44px] px-4 rounded-full border text-[15px]" :class="statusFilter === f[0] ? 'bg-ink text-white border-ink font-bold' : 'bg-white text-ink border-gray-200'">{{ f[1] }}</button>
    </div>
    <div v-if="loading" class="text-center py-10 text-ink-muted text-[15px]">불러오는 중...</div>
    <div v-else-if="!guardians.length" class="text-center py-12 text-ink-muted text-[15px]">데이터가 없어요.</div>
    <div v-for="g in guardians" v-else :key="g.id" class="bg-white border border-gray-100 rounded-2xl p-3.5">
      <button @click="openDetail(g)" class="w-full text-left">
        <div class="flex items-center gap-2 flex-wrap">
          <span class="text-[12px] px-2.5 py-1 rounded-full font-bold" :class="g.status === 'active' ? 'bg-green-100 text-green-700' : g.status === 'pending' ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700'">{{ {active:'활성',pending:'대기',rejected:'거절'}[g.status] || g.status }}</span>
          <span v-if="g.schedule_type" class="text-[12px] px-2 py-0.5 rounded-full font-bold" :class="g.schedule_type === 'scheduled' ? 'bg-purple-100 text-purple-700' : 'bg-gray-100 text-ink-light'">{{ g.schedule_type === 'scheduled' ? '예약·유료' : '랜덤·무료' }}</span>
          <span class="text-[12px] text-ink-faint ml-auto">{{ fmt(g.created_at) }}</span>
        </div>
        <div class="mt-1.5 text-[15px] text-ink break-words"><span class="text-ink-muted text-[13px]">보호자</span> <b>{{ g.guardian_name || '-' }}</b></div>
        <div class="text-[15px] text-ink break-words"><span class="text-ink-muted text-[13px]">보호대상</span> <b>{{ g.ward_name || '-' }}</b></div>
        <div v-if="g.schedule_type" class="text-[13px] text-ink-muted mt-0.5">{{ g.time_start }} ~ {{ g.time_end }} · 하루 {{ g.calls_per_day }}회</div>
      </button>
      <div class="grid gap-2 mt-2.5" :class="g.ward_phone ? 'grid-cols-2' : 'grid-cols-1'">
        <a v-if="g.ward_phone" :href="`tel:${g.ward_phone}`" class="min-h-[46px] rounded-xl bg-green-50 text-green-700 text-[15px] font-bold grid place-items-center">📞 보호대상 전화</a>
        <button @click="mDel = g" class="min-h-[46px] rounded-xl bg-red-50 text-red-600 text-[15px] font-bold">매칭 해제</button>
      </div>
    </div>
  </div>

  <!-- 통화 -->
  <div v-else-if="tab === 'calls'" class="space-y-2">
    <div v-if="loading" class="text-center py-10 text-ink-muted text-[15px]">불러오는 중...</div>
    <div v-else-if="!calls.length" class="text-center py-12 text-ink-muted text-[15px]">통화 기록이 없어요.</div>
    <div v-for="c in calls" v-else :key="c.id" class="bg-white border border-gray-100 rounded-2xl p-3.5">
      <div class="flex items-center gap-2 flex-wrap">
        <span class="text-[12px] px-2.5 py-1 rounded-full font-bold" :class="c.answered ? 'bg-green-100 text-green-700' : c.status==='ringing' ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700'">{{ c.answered ? '응답' : c.status==='ringing' ? '대기' : '미응답' }}</span>
        <span v-if="c.guardian_notified" class="text-[12px] px-2 py-0.5 rounded-full font-bold bg-amber-100 text-amber-700">보호자 알림</span>
        <span class="text-[12px] text-ink-faint ml-auto">{{ fmt(c.called_at) }}</span>
      </div>
      <div class="text-[15px] text-ink mt-1 break-words">{{ c.guardian_name || '-' }} → <b>{{ c.ward_name || '-' }}</b></div>
      <div class="text-[13px] mt-0.5" :class="c.duration > 0 ? 'text-green-700 font-bold' : 'text-ink-faint'">통화 {{ c.duration > 0 ? Math.floor(c.duration/60) + '분 ' + (c.duration%60) + '초' : '없음' }}</div>
    </div>
  </div>

  <!-- 체크인 -->
  <div v-else-if="tab === 'checkins'" class="space-y-2">
    <div v-if="loading" class="text-center py-10 text-ink-muted text-[15px]">불러오는 중...</div>
    <div v-else-if="!checkins.length" class="text-center py-12 text-ink-muted text-[15px]">체크인이 없어요.</div>
    <div v-for="c in checkins" v-else :key="c.id" class="bg-white border border-gray-100 rounded-2xl p-3.5">
      <div class="flex items-center gap-2 flex-wrap">
        <span class="text-[12px] px-2.5 py-1 rounded-full font-bold" :class="c.status === 'ok' ? 'bg-green-100 text-green-700' : c.status === 'sos' ? 'bg-red-100 text-red-700' : 'bg-yellow-100 text-yellow-700'">{{ {ok:'정상',sos:'SOS',missed:'누락'}[c.status] || c.status }}</span>
        <span class="text-[12px] text-ink-faint ml-auto">{{ fmt(c.checked_in_at) }}</span>
      </div>
      <div class="text-[15px] font-bold text-ink mt-1">{{ c.user?.name || '-' }}</div>
      <div class="text-[13px] text-ink-muted break-all">{{ c.user?.email }}</div>
      <div v-if="c.lat && c.lng" class="text-[12px] text-ink-faint mt-0.5">📍 {{ Number(c.lat).toFixed(4) }}, {{ Number(c.lng).toFixed(4) }}</div>
    </div>
  </div>

  <!-- SOS -->
  <div v-else-if="tab === 'sos'" class="space-y-2">
    <div v-if="loading" class="text-center py-10 text-ink-muted text-[15px]">불러오는 중...</div>
    <div v-else-if="!sosLogs.length" class="text-center py-12 text-ink-muted text-[15px]">SOS가 없어요 👍</div>
    <div v-for="s in sosLogs" v-else :key="s.id" class="bg-white border rounded-2xl p-3.5" :class="s.resolved_at ? 'border-gray-100' : 'border-red-300'">
      <div class="flex items-center gap-2 flex-wrap">
        <span class="text-[12px] px-2.5 py-1 rounded-full font-bold" :class="s.resolved_at ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'">{{ s.resolved_at ? '해결' : '미해결' }}</span>
        <span class="text-[12px] text-ink-faint ml-auto">{{ fmt(s.created_at) }}</span>
      </div>
      <div class="text-[16px] font-bold text-ink mt-1">{{ s.user?.name || '-' }}</div>
      <div class="text-[13px] text-ink-muted break-all">{{ s.user?.email }}</div>
      <div class="text-[14px] text-ink-light mt-1 bg-gray-50 rounded-xl px-3 py-2 break-words">{{ s.message || '(메시지 없음)' }}</div>
      <div v-if="s.lat && s.lng" class="text-[12px] text-ink-faint mt-1">📍 {{ Number(s.lat).toFixed(4) }}, {{ Number(s.lng).toFixed(4) }}</div>
      <a v-if="s.user?.phone" :href="`tel:${s.user.phone}`" class="mt-2 min-h-[48px] rounded-xl bg-red-50 text-red-700 text-[15px] font-bold grid place-items-center">📞 {{ s.user.phone }} 전화하기</a>
    </div>
  </div>

  <div v-if="tab !== 'overview' && lastPage > 1" class="flex items-center justify-between gap-2 pt-1">
    <button @click="mPage(page - 1)" :disabled="page <= 1" class="min-h-[48px] px-5 rounded-xl bg-white border border-gray-200 text-[15px] font-bold disabled:opacity-40">이전</button>
    <span class="text-[14px] text-ink-muted tabular-nums">{{ page }} / {{ lastPage }}</span>
    <button @click="mPage(page + 1)" :disabled="page >= lastPage" class="min-h-[48px] px-5 rounded-xl bg-white border border-gray-200 text-[15px] font-bold disabled:opacity-40">다음</button>
  </div>

  <Teleport to="body">
    <!-- 매칭 상세 (전체 화면) -->
    <div v-if="detailModal" class="alv-m fixed inset-0 z-[60] bg-white flex flex-col" role="dialog" aria-modal="true" aria-label="안심서비스 상세">
      <div class="shrink-0 flex items-center gap-2 px-2 border-b border-gray-100" :style="{ paddingTop: 'env(safe-area-inset-top, 0px)' }">
        <button @click="closeDetail" class="min-h-[52px] min-w-[52px] grid place-items-center text-[22px]" aria-label="목록으로">←</button>
        <div class="flex-1 min-w-0"><div class="text-[16px] font-bold text-ink truncate">안심서비스 상세</div><div class="text-[12px] text-ink-muted">매칭 #{{ detailModal.id }} · {{ fmt(detailModal.created_at) }}</div></div>
      </div>
      <div class="flex-1 overflow-y-auto px-4 py-4 space-y-3">
        <div v-if="detailLoading" class="text-center py-10 text-ink-muted text-[15px]">불러오는 중...</div>
        <template v-else-if="detail">
          <div class="flex flex-wrap items-center gap-1.5">
            <span class="text-[12px] px-3 py-1 rounded-full font-bold" :class="detail.status === 'active' ? 'bg-green-100 text-green-700' : detail.status === 'pending' ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700'">{{ {active:'활성',pending:'대기',rejected:'거절'}[detail.status] || detail.status }}</span>
            <span class="text-[12px] px-3 py-1 rounded-full font-bold" :class="detail.service_type === 'paid' ? 'bg-purple-100 text-purple-700' : detail.service_type === 'free' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-ink-light'">{{ detail.service_type === 'paid' ? '유료 (예약형, 50P/콜)' : detail.service_type === 'free' ? '무료 (랜덤형)' : '스케줄 미설정' }}</span>
            <span v-if="detail.schedule?.is_active" class="text-[12px] px-3 py-1 rounded-full font-bold bg-green-100 text-green-700">스케줄 활성</span>
          </div>
          <div class="grid grid-cols-1 gap-2">
            <div v-for="who in [['보호자', detail.guardian, 'bg-blue-50'], ['보호대상', detail.ward, 'bg-amber-50']]" :key="who[0]" class="rounded-2xl p-3.5" :class="who[2]">
              <div class="text-[13px] font-bold text-ink-light">{{ who[0] }}</div>
              <div class="text-[16px] font-bold text-ink break-words">{{ who[1]?.name || who[1]?.nickname || '-' }}</div>
              <div class="text-[13px] text-ink-light break-all">{{ who[1]?.email }}</div>
              <div v-if="who[1]?.city" class="text-[13px] text-ink-muted">📍 {{ who[1].city }}, {{ who[1].state }}</div>
              <div v-if="who[1]?.address" class="text-[13px] text-ink-muted break-words">{{ who[1].address }}</div>
              <a v-if="who[1]?.phone" :href="`tel:${who[1].phone}`" class="mt-2 min-h-[46px] rounded-xl bg-white text-green-700 text-[15px] font-bold grid place-items-center">📞 {{ who[1].phone }}</a>
            </div>
          </div>
          <div class="rounded-2xl border border-gray-100 p-3.5">
            <div class="text-[15px] font-bold text-ink mb-2">스케줄</div>
            <div v-if="!detail.schedule" class="text-[14px] text-ink-faint">스케줄이 설정되지 않았어요.</div>
            <div v-else class="grid grid-cols-2 gap-2 text-[14px]">
              <div><div class="text-[12px] text-ink-muted">타입</div><b>{{ detail.schedule.type === 'random' ? '랜덤 (무료)' : '예약 (유료)' }}</b></div>
              <div><div class="text-[12px] text-ink-muted">통화 시간대</div><b>{{ detail.schedule.time_start }} ~ {{ detail.schedule.time_end }}</b></div>
              <div><div class="text-[12px] text-ink-muted">하루 통화수</div><b>{{ detail.schedule.calls_per_day }}회</b></div>
              <div><div class="text-[12px] text-ink-muted">활성화</div><b>{{ detail.schedule.is_active ? 'ON' : 'OFF' }}</b></div>
              <div class="col-span-2"><div class="text-[12px] text-ink-muted">요일</div><b class="break-words">{{ (detail.schedule.days || []).join(', ') || '미설정' }}</b></div>
              <div v-if="detail.schedule.type === 'scheduled'" class="col-span-2"><div class="text-[12px] text-ink-muted">예약 시각</div><b class="break-words">{{ (detail.schedule.scheduled_times || []).join(', ') || '미설정' }}</b></div>
            </div>
          </div>
          <div class="rounded-2xl bg-gray-50 p-3.5">
            <div class="text-[15px] font-bold text-ink mb-2">통화 통계</div>
            <div class="grid grid-cols-3 gap-2">
              <div v-for="st in [['총 통화', detail.call_stats?.total, 'text-ink'], ['응답', detail.call_stats?.answered, 'text-green-600'], ['미응답', detail.call_stats?.unanswered, 'text-red-500'], ['총 시도', detail.call_stats?.total_attempts, 'text-ink-light'], ['평균 시도', detail.call_stats?.avg_attempts_to_answer, 'text-blue-600'], ['보호자 알림', detail.call_stats?.guardian_notified, 'text-amber-600']]" :key="st[0]" class="bg-white rounded-xl p-2 text-center">
                <div class="text-[12px] text-ink-muted">{{ st[0] }}</div><div class="text-[20px] font-black tabular-nums" :class="st[2]">{{ st[1] || 0 }}</div>
              </div>
            </div>
            <div class="flex flex-wrap gap-x-4 gap-y-1 mt-2 text-[13px] text-ink-muted"><span>오늘 <b class="text-ink">{{ detail.call_stats?.today_calls || 0 }}회</b></span><span>이번주 <b class="text-ink">{{ detail.call_stats?.week_calls || 0 }}회</b></span><span>마지막 <b class="text-ink">{{ fmt(detail.call_stats?.last_call) }}</b></span></div>
          </div>
          <div class="rounded-2xl border border-gray-100 p-3.5">
            <div class="text-[15px] font-bold text-ink mb-2">통화 로그 (최근 100건)</div>
            <div v-if="!detail.call_logs?.length" class="text-[14px] text-ink-faint text-center py-3">통화 기록 없음</div>
            <div v-else class="max-h-72 overflow-y-auto divide-y divide-gray-50">
              <div v-for="log in detail.call_logs" :key="log.id" class="py-2 text-[13px]">
                <div class="flex items-center gap-2 flex-wrap"><span class="text-ink-muted">{{ fmt(log.called_at) }}</span>
                  <span class="text-[12px] px-2 py-0.5 rounded-full font-bold" :class="log.answered ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'">{{ log.answered ? '응답' : '미응답' }}</span>
                  <span class="font-bold text-ink">{{ log.attempts }}회 시도</span>
                  <span v-if="log.guardian_notified" class="text-[12px] px-2 py-0.5 rounded-full font-bold bg-amber-100 text-amber-700">알림</span></div>
                <div v-if="log.notes" class="text-ink-muted break-words">{{ log.notes }}</div>
              </div>
            </div>
          </div>
          <div class="grid grid-cols-1 gap-2">
            <div class="rounded-2xl p-3.5 bg-emerald-50">
              <div class="text-[15px] font-bold text-emerald-700 mb-2">체크인 통계</div>
              <div class="grid grid-cols-4 gap-1 text-center">
                <div><div class="text-[12px] text-ink-muted">전체</div><div class="font-black text-[18px]">{{ detail.checkin_stats?.total || 0 }}</div></div>
                <div><div class="text-[12px] text-ink-muted">정상</div><div class="font-black text-[18px] text-green-600">{{ detail.checkin_stats?.ok || 0 }}</div></div>
                <div><div class="text-[12px] text-ink-muted">누락</div><div class="font-black text-[18px] text-yellow-600">{{ detail.checkin_stats?.missed || 0 }}</div></div>
                <div><div class="text-[12px] text-ink-muted">SOS</div><div class="font-black text-[18px] text-red-600">{{ detail.checkin_stats?.sos || 0 }}</div></div>
              </div>
            </div>
            <div class="rounded-2xl p-3.5 bg-red-50">
              <div class="text-[15px] font-bold text-red-700 mb-1">SOS 기록</div>
              <div v-if="!detail.recent_sos?.length" class="text-[14px] text-ink-faint text-center py-2">SOS 없음</div>
              <div v-for="sx in detail.recent_sos" :key="sx.id" class="text-[13px] text-ink-light py-1"><b>{{ fmt(sx.created_at) }}</b><span v-if="sx.resolved_at" class="text-green-600 ml-1">해결</span><div v-if="sx.message" class="text-ink-muted break-words">{{ sx.message }}</div></div>
            </div>
          </div>
          <div v-if="detail.recent_checkins?.length" class="rounded-2xl border border-gray-100 p-3.5">
            <div class="text-[15px] font-bold text-ink mb-1">최근 체크인 (20건)</div>
            <div class="max-h-56 overflow-y-auto divide-y divide-gray-50">
              <div v-for="c in detail.recent_checkins" :key="c.id" class="py-2 flex items-center gap-2 flex-wrap text-[13px] text-ink-light">
                <span>{{ fmt(c.checked_in_at) }}</span>
                <span class="text-[12px] px-2 py-0.5 rounded-full font-bold" :class="c.status === 'ok' ? 'bg-green-100 text-green-700' : c.status === 'sos' ? 'bg-red-100 text-red-700' : 'bg-yellow-100 text-yellow-700'">{{ {ok:'정상',sos:'SOS',missed:'누락'}[c.status] || c.status }}</span>
                <span v-if="c.lat && c.lng" class="text-[12px] text-ink-faint">{{ Number(c.lat).toFixed(4) }}, {{ Number(c.lng).toFixed(4) }}</span>
              </div>
            </div>
          </div>
        </template>
      </div>
    </div>
    <!-- 매칭 해제 확인 -->
    <div v-if="mDel" class="alv-m fixed inset-0 z-[70] bg-black/45 flex items-end" @click.self="mDel = null">
      <div class="w-full bg-white rounded-t-3xl px-4 pt-2" role="alertdialog" aria-modal="true" :style="{ paddingBottom: 'calc(16px + env(safe-area-inset-bottom, 0px))' }">
        <div class="w-10 h-1 rounded bg-gray-200 mx-auto mb-3"></div>
        <div class="text-[17px] font-bold text-ink mb-1">매칭을 해제할까요?</div>
        <p class="text-[15px] text-ink-light mb-1 break-words">{{ mDel.guardian_name }} ↔ {{ mDel.ward_name }}</p>
        <p class="text-[14px] text-red-600 mb-3">해제하면 안심 전화·알림이 멈추고 되돌릴 수 없어요.</p>
        <button @click="mRemove" :disabled="mBusy" class="w-full min-h-[52px] rounded-xl bg-red-500 text-white text-[16px] font-bold disabled:opacity-40">{{ mBusy ? '해제 중...' : '매칭 해제하기' }}</button>
        <button @click="mDel = null" :disabled="mBusy" class="mt-2 w-full min-h-[50px] rounded-xl bg-gray-100 text-ink text-[16px] font-bold">취소</button>
      </div>
    </div>
    <div v-if="toast" class="alv-m fixed left-1/2 -translate-x-1/2 z-[80] max-w-[90vw] px-4 py-3 rounded-xl text-[15px] font-bold text-white shadow-lg" :class="toast.error ? 'bg-red-600' : 'bg-ink'" :style="{ top: 'calc(70px + env(safe-area-inset-top, 0px))' }" role="status">{{ toast.text }}</div>
  </Teleport>
</div>

<!-- ───────── PC 화면 ───────── -->
<div v-else>
  <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink mb-4">
    <span class="icon-chip w-9 h-9 bg-amber-50 text-amber-600"><AppIcon name="heart-handshake" :size="20" /></span>
    안심서비스 관리
  </h1>

  <!-- 탭 -->
  <div class="flex gap-0 border-b border-gray-100 mb-4 overflow-x-auto">
    <button v-for="t in tabs" :key="t.key" @click="switchTab(t.key)"
      class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-bold border-b-2 transition-colors whitespace-nowrap"
      :class="tab === t.key ? 'border-amber-500 text-amber-700' : 'border-transparent text-ink-muted hover:text-ink-light'">
      <AppIcon :name="t.icon" :size="14" /> {{ t.label }}
    </button>
  </div>

  <!-- 📊 통계 -->
  <div v-if="tab === 'overview'">
    <div v-if="overviewLoading" class="text-center py-8 text-ink-muted">로딩중...</div>
    <div v-else class="grid grid-cols-2 lg:grid-cols-3 gap-3">
      <div class="card p-4">
        <div class="text-xs text-ink-muted">활성 매칭</div>
        <div class="text-2xl font-black text-amber-600 mt-1">{{ overview.active_guardians || 0 }}</div>
      </div>
      <div class="card p-4">
        <div class="text-xs text-ink-muted">대기 중 매칭</div>
        <div class="text-2xl font-black text-ink-light mt-1">{{ overview.pending_guardians || 0 }}</div>
      </div>
      <div class="card p-4">
        <div class="text-xs text-ink-muted">활성 스케줄</div>
        <div class="text-2xl font-black text-blue-600 mt-1">{{ overview.total_schedules || 0 }}</div>
      </div>
      <div class="card p-4">
        <div class="text-xs text-ink-muted">총 안심통화</div>
        <div class="text-2xl font-black text-blue-600 mt-1">{{ overview.total_elder_calls || 0 }}</div>
      </div>
      <div class="card p-4">
        <div class="text-xs text-ink-muted">오늘 통화</div>
        <div class="text-2xl font-black text-green-600 mt-1">{{ overview.calls_today || 0 }}</div>
      </div>
      <div class="card p-4">
        <div class="text-xs text-ink-muted">오늘 체크인</div>
        <div class="text-2xl font-black text-emerald-600 mt-1">{{ overview.checkins_today || 0 }}</div>
      </div>
      <div class="card p-4">
        <div class="text-xs text-ink-muted">미해결 SOS</div>
        <div class="text-2xl font-black text-red-600 mt-1">{{ overview.sos_unresolved || 0 }}</div>
      </div>
    </div>
  </div>

  <!-- 👫 매칭 -->
  <div v-else-if="tab === 'guardians'">
    <div class="card p-3 mb-3 flex gap-2">
      <input v-model="search" @keyup.enter="loadGuardians(1)" placeholder="보호자/보호대상 이름·이메일 검색..."
        class="input-soft flex-1 !w-auto !px-3 !py-1.5 !text-sm" />
      <select v-model="statusFilter" @change="loadGuardians(1)" class="input-soft !w-auto !px-3 !py-1.5 !text-sm">
        <option value="">전체</option>
        <option value="active">활성</option>
        <option value="pending">대기</option>
        <option value="rejected">거절</option>
      </select>
      <button @click="loadGuardians(1)" class="btn-primary !px-4 !py-1.5 !text-sm flex-shrink-0">검색</button>
    </div>

    <div v-if="loading" class="text-center py-8 text-ink-muted">로딩중...</div>
    <div v-else-if="!guardians.length" class="text-center py-8 text-ink-muted">데이터 없음</div>
    <div v-else class="card overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b border-gray-50">
          <tr>
            <th class="px-3 py-2 text-left text-xs text-ink-muted">보호자</th>
            <th class="px-3 py-2 text-left text-xs text-ink-muted">보호대상</th>
            <th class="px-3 py-2 text-left text-xs text-ink-muted">상태</th>
            <th class="px-3 py-2 text-left text-xs text-ink-muted">스케줄</th>
            <th class="px-3 py-2 text-left text-xs text-ink-muted">등록일</th>
            <th class="px-3 py-2 text-xs text-ink-muted">관리</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="g in guardians" :key="g.id" @click="openDetail(g)"
            class="border-b border-gray-50 last:border-0 hover:bg-amber-50/40 cursor-pointer transition-colors">
            <td class="px-3 py-2.5">
              <div class="font-semibold text-ink text-xs">{{ g.guardian_name || '-' }}</div>
              <div class="text-[11px] text-ink-faint">{{ g.guardian_email }}</div>
            </td>
            <td class="px-3 py-2.5">
              <div class="font-semibold text-ink text-xs">{{ g.ward_name || '-' }}</div>
              <div class="text-[11px] text-ink-faint">{{ g.ward_email }}</div>
              <div v-if="g.ward_phone" class="flex items-center gap-0.5 text-[11px] text-ink-faint"><AppIcon name="phone" :size="10" /> {{ g.ward_phone }}</div>
            </td>
            <td class="px-3 py-2.5">
              <span class="text-xs px-2 py-0.5 rounded-full font-bold"
                :class="g.status === 'active' ? 'bg-green-100 text-green-700' : g.status === 'pending' ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700'">
                {{ g.status }}
              </span>
            </td>
            <td class="px-3 py-2.5 text-xs text-ink-light">
              <template v-if="g.schedule_type">
                <div class="font-semibold flex items-center gap-1">
                  {{ g.schedule_type === 'random' ? '랜덤' : '예약' }}
                  <span class="text-[11px] px-1.5 py-0.5 rounded font-bold"
                    :class="g.schedule_type === 'scheduled' ? 'bg-purple-100 text-purple-700' : 'bg-gray-100 text-ink-light'">
                    {{ g.schedule_type === 'scheduled' ? '유료' : '무료' }}
                  </span>
                </div>
                <div class="text-[11px] text-ink-faint">{{ g.time_start }} ~ {{ g.time_end }} · {{ g.calls_per_day }}회/일</div>
              </template>
              <span v-else class="text-ink-faint text-[11px]">미설정</span>
            </td>
            <td class="px-3 py-2.5 text-[11px] text-ink-muted">{{ fmt(g.created_at) }}</td>
            <td class="px-3 py-2.5 text-center">
              <button @click.stop="deleteGuardian(g)" class="text-xs text-red-400 hover:text-red-600 transition-colors">해제</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <div v-if="lastPage > 1" class="flex justify-center gap-1.5 mt-4">
      <button v-for="pg in Math.min(lastPage, 10)" :key="pg" @click="loadGuardians(pg)"
        class="w-8 h-8 rounded-lg text-xs font-bold transition-colors"
        :class="pg === page ? 'bg-amber-400 text-white' : 'text-ink-muted hover:bg-gray-100'">{{ pg }}</button>
    </div>
  </div>

  <!-- 📞 통화 -->
  <div v-else-if="tab === 'calls'">
    <div v-if="loading" class="text-center py-8 text-ink-muted">로딩중...</div>
    <div v-else-if="!calls.length" class="text-center py-8 text-ink-muted">통화 기록 없음</div>
    <div v-else class="card overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b border-gray-50">
          <tr>
            <th class="px-3 py-2 text-left text-xs text-ink-muted">시각</th>
            <th class="px-3 py-2 text-left text-xs text-ink-muted">보호자</th>
            <th class="px-3 py-2 text-left text-xs text-ink-muted">보호대상</th>
            <th class="px-3 py-2 text-center text-xs text-ink-muted">상태</th>
            <th class="px-3 py-2 text-center text-xs text-ink-muted">통화시간</th>
            <th class="px-3 py-2 text-center text-xs text-ink-muted">알림</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="c in calls" :key="c.id" class="border-b border-gray-50 last:border-0 hover:bg-amber-50/40 transition-colors">
            <td class="px-3 py-2.5 text-[11px] text-ink-muted">{{ fmt(c.called_at) }}</td>
            <td class="px-3 py-2.5 text-xs text-ink">{{ c.guardian_name || '-' }}</td>
            <td class="px-3 py-2.5 text-xs text-ink">{{ c.ward_name || '-' }}</td>
            <td class="px-3 py-2.5 text-center">
              <span class="text-xs px-2 py-0.5 rounded-full font-bold"
                :class="c.answered ? 'bg-green-100 text-green-700' : c.status==='ringing' ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700'">
                {{ c.answered ? '응답' : c.status==='ringing' ? '대기' : '미응답' }}
              </span>
            </td>
            <td class="px-3 py-2.5 text-center text-xs font-bold" :class="c.duration > 0 ? 'text-green-700' : 'text-ink-faint'">
              {{ c.duration > 0 ? Math.floor(c.duration/60) + '분 ' + (c.duration%60) + '초' : '-' }}
            </td>
            <td class="px-3 py-2.5 text-center">
              <span v-if="c.guardian_notified" class="inline-flex items-center text-[11px] px-1.5 py-0.5 rounded-full font-bold bg-amber-100 text-amber-700"><AppIcon name="megaphone" :size="10" /></span>
              <span v-else class="text-[11px] text-ink-faint">-</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <div v-if="lastPage > 1" class="flex justify-center gap-1.5 mt-4">
      <button v-for="pg in Math.min(lastPage, 10)" :key="pg" @click="loadCalls(pg)"
        class="w-8 h-8 rounded-lg text-xs font-bold transition-colors"
        :class="pg === page ? 'bg-amber-400 text-white' : 'text-ink-muted hover:bg-gray-100'">{{ pg }}</button>
    </div>
  </div>

  <!-- ✅ 체크인 -->
  <div v-else-if="tab === 'checkins'">
    <div v-if="loading" class="text-center py-8 text-ink-muted">로딩중...</div>
    <div v-else-if="!checkins.length" class="text-center py-8 text-ink-muted">체크인 없음</div>
    <div v-else class="card overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b border-gray-50">
          <tr>
            <th class="px-3 py-2 text-left text-xs text-ink-muted">유저</th>
            <th class="px-3 py-2 text-left text-xs text-ink-muted">시각</th>
            <th class="px-3 py-2 text-center text-xs text-ink-muted">상태</th>
            <th class="px-3 py-2 text-left text-xs text-ink-muted">위치</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="c in checkins" :key="c.id" class="border-b border-gray-50 last:border-0 hover:bg-amber-50/40 transition-colors">
            <td class="px-3 py-2.5 text-xs">
              <div class="font-semibold text-ink">{{ c.user?.name || '-' }}</div>
              <div class="text-[11px] text-ink-faint">{{ c.user?.email }}</div>
            </td>
            <td class="px-3 py-2.5 text-[11px] text-ink-muted">{{ fmt(c.checked_in_at) }}</td>
            <td class="px-3 py-2.5 text-center">
              <span class="text-xs px-2 py-0.5 rounded-full font-bold"
                :class="c.status === 'ok' ? 'bg-green-100 text-green-700' : c.status === 'sos' ? 'bg-red-100 text-red-700' : 'bg-yellow-100 text-yellow-700'">
                {{ c.status }}
              </span>
            </td>
            <td class="px-3 py-2.5 text-[11px] text-ink-muted">
              <template v-if="c.lat && c.lng">{{ Number(c.lat).toFixed(4) }}, {{ Number(c.lng).toFixed(4) }}</template>
              <span v-else>-</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <div v-if="lastPage > 1" class="flex justify-center gap-1.5 mt-4">
      <button v-for="pg in Math.min(lastPage, 10)" :key="pg" @click="loadCheckins(pg)"
        class="w-8 h-8 rounded-lg text-xs font-bold transition-colors"
        :class="pg === page ? 'bg-amber-400 text-white' : 'text-ink-muted hover:bg-gray-100'">{{ pg }}</button>
    </div>
  </div>

  <!-- 🚨 SOS -->
  <div v-else-if="tab === 'sos'">
    <div v-if="loading" class="text-center py-8 text-ink-muted">로딩중...</div>
    <div v-else-if="!sosLogs.length" class="text-center py-8 text-ink-muted">SOS 없음</div>
    <div v-else class="space-y-2">
      <div v-for="s in sosLogs" :key="s.id" class="card p-4">
        <div class="flex items-start justify-between gap-3">
          <div class="flex-1">
            <div class="flex items-center gap-2 mb-1">
              <span class="icon-chip w-7 h-7 bg-red-50 text-red-500"><AppIcon name="alert-circle" :size="15" /></span>
              <span class="font-bold text-ink">{{ s.user?.name || '-' }}</span>
              <span class="text-[11px] text-ink-faint">{{ s.user?.email }}</span>
              <span class="text-xs px-2 py-0.5 rounded-full font-bold ml-auto"
                :class="s.resolved_at ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'">
                {{ s.resolved_at ? '해결' : '미해결' }}
              </span>
            </div>
            <div class="text-xs text-ink-light mb-1">{{ s.message || '(메시지 없음)' }}</div>
            <div class="text-[11px] text-ink-faint">
              {{ fmt(s.created_at) }}
              <template v-if="s.lat && s.lng"> · {{ Number(s.lat).toFixed(4) }}, {{ Number(s.lng).toFixed(4) }}</template>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div v-if="lastPage > 1" class="flex justify-center gap-1.5 mt-4">
      <button v-for="pg in Math.min(lastPage, 10)" :key="pg" @click="loadSos(pg)"
        class="w-8 h-8 rounded-lg text-xs font-bold transition-colors"
        :class="pg === page ? 'bg-amber-400 text-white' : 'text-ink-muted hover:bg-gray-100'">{{ pg }}</button>
    </div>
  </div>

  <!-- ─── 매칭 상세 모달 ─── -->
  <div v-if="detailModal && !isMobile" class="fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4" @click.self="closeDetail">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-y-auto">
      <!-- 헤더 -->
      <div class="sticky top-0 bg-amber-50 border-b border-gray-50 px-5 py-3 flex items-center justify-between z-10">
        <div class="flex items-center gap-3">
          <span class="icon-chip w-9 h-9 bg-white text-amber-600"><AppIcon name="heart-handshake" :size="20" /></span>
          <div>
            <div class="font-bold text-amber-800">안심서비스 상세</div>
            <div class="text-[11px] text-amber-700">매칭 #{{ detailModal.id }} · {{ fmt(detailModal.created_at) }}</div>
          </div>
        </div>
        <button @click="closeDetail" class="text-amber-700 hover:text-amber-900 transition-colors"><AppIcon name="x" :size="20" /></button>
      </div>

      <div v-if="detailLoading" class="p-10 text-center text-ink-muted">로딩중...</div>
      <div v-else-if="detail" class="p-5 space-y-5">
        <!-- 서비스 상태 배지 -->
        <div class="flex flex-wrap items-center gap-2">
          <span class="text-[11px] px-3 py-1 rounded-full font-bold"
            :class="detail.status === 'active' ? 'bg-green-100 text-green-700' : detail.status === 'pending' ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700'">
            상태: {{ detail.status }}
          </span>
          <span class="text-[11px] px-3 py-1 rounded-full font-bold"
            :class="detail.service_type === 'paid' ? 'bg-purple-100 text-purple-700' : detail.service_type === 'free' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-ink-light'">
            요금: {{ detail.service_type === 'paid' ? '유료 (예약형, 50P/콜)' : detail.service_type === 'free' ? '무료 (랜덤형)' : '스케줄 미설정' }}
          </span>
          <span v-if="detail.schedule?.is_active" class="text-[11px] px-3 py-1 rounded-full font-bold bg-green-100 text-green-700">
            스케줄 활성
          </span>
        </div>

        <!-- 보호자 & 보호대상 -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <div class="rounded-xl p-3 bg-blue-50">
            <div class="flex items-center gap-1 text-xs font-bold text-blue-700 mb-1"><AppIcon name="users" :size="12" /> 보호자</div>
            <div class="font-bold text-ink">{{ detail.guardian?.name || detail.guardian?.nickname || '-' }}</div>
            <div class="text-xs text-ink-light">{{ detail.guardian?.email }}</div>
            <div v-if="detail.guardian?.phone" class="flex items-center gap-1 text-xs text-ink-light"><AppIcon name="phone" :size="11" /> {{ detail.guardian.phone }}</div>
            <div v-if="detail.guardian?.city" class="flex items-center gap-1 text-xs text-ink-muted"><AppIcon name="map-pin" :size="11" /> {{ detail.guardian.city }}, {{ detail.guardian.state }}</div>
          </div>
          <div class="rounded-xl p-3 bg-amber-50">
            <div class="flex items-center gap-1 text-xs font-bold text-amber-700 mb-1"><AppIcon name="user" :size="12" /> 보호대상</div>
            <div class="font-bold text-ink">{{ detail.ward?.name || detail.ward?.nickname || '-' }}</div>
            <div class="text-xs text-ink-light">{{ detail.ward?.email }}</div>
            <div v-if="detail.ward?.phone" class="flex items-center gap-1 text-xs text-ink-light"><AppIcon name="phone" :size="11" /> {{ detail.ward.phone }}</div>
            <div v-if="detail.ward?.city" class="flex items-center gap-1 text-xs text-ink-muted"><AppIcon name="map-pin" :size="11" /> {{ detail.ward.city }}, {{ detail.ward.state }}</div>
            <div v-if="detail.ward?.address" class="text-[11px] text-ink-muted mt-1">{{ detail.ward.address }}</div>
          </div>
        </div>

        <!-- 스케줄 -->
        <div class="rounded-xl border border-gray-100 p-4">
          <div class="flex items-center gap-1 text-xs font-bold text-ink-light mb-2"><AppIcon name="calendar" :size="13" /> 스케줄 설정</div>
          <div v-if="!detail.schedule" class="text-sm text-ink-faint">스케줄이 설정되지 않았습니다.</div>
          <div v-else class="grid grid-cols-2 md:grid-cols-4 gap-3 text-xs">
            <div>
              <div class="text-[11px] text-ink-muted">타입</div>
              <div class="font-bold text-ink">{{ detail.schedule.type === 'random' ? '랜덤 (무료)' : '예약 (유료)' }}</div>
            </div>
            <div>
              <div class="text-[11px] text-ink-muted">통화 시간대</div>
              <div class="font-bold text-ink">{{ detail.schedule.time_start }} ~ {{ detail.schedule.time_end }}</div>
            </div>
            <div>
              <div class="text-[11px] text-ink-muted">하루 통화수</div>
              <div class="font-bold text-ink">{{ detail.schedule.calls_per_day }}회</div>
            </div>
            <div>
              <div class="text-[11px] text-ink-muted">활성화</div>
              <div class="font-bold text-ink">{{ detail.schedule.is_active ? 'ON' : 'OFF' }}</div>
            </div>
            <div class="col-span-2 md:col-span-4">
              <div class="text-[11px] text-ink-muted">요일</div>
              <div class="font-bold text-ink">{{ (detail.schedule.days || []).join(', ') || '미설정' }}</div>
            </div>
            <div v-if="detail.schedule.type === 'scheduled'" class="col-span-2 md:col-span-4">
              <div class="text-[11px] text-ink-muted">예약 시각</div>
              <div class="font-bold text-ink">{{ (detail.schedule.scheduled_times || []).join(', ') || '미설정' }}</div>
            </div>
          </div>
        </div>

        <!-- 통화 통계 -->
        <div class="rounded-xl p-4 bg-gray-50">
          <div class="flex items-center gap-1 text-xs font-bold text-ink-light mb-3"><AppIcon name="phone" :size="13" /> 통화 통계</div>
          <div class="grid grid-cols-3 md:grid-cols-6 gap-2">
            <div class="bg-white rounded-lg p-2 text-center">
              <div class="text-[11px] text-ink-muted">총 통화</div>
              <div class="text-lg font-black text-ink">{{ detail.call_stats?.total || 0 }}</div>
            </div>
            <div class="bg-white rounded-lg p-2 text-center">
              <div class="text-[11px] text-ink-muted">응답</div>
              <div class="text-lg font-black text-green-600">{{ detail.call_stats?.answered || 0 }}</div>
            </div>
            <div class="bg-white rounded-lg p-2 text-center">
              <div class="text-[11px] text-ink-muted">미응답</div>
              <div class="text-lg font-black text-red-500">{{ detail.call_stats?.unanswered || 0 }}</div>
            </div>
            <div class="bg-white rounded-lg p-2 text-center">
              <div class="text-[11px] text-ink-muted">총 시도수</div>
              <div class="text-lg font-black text-ink-light">{{ detail.call_stats?.total_attempts || 0 }}</div>
            </div>
            <div class="bg-white rounded-lg p-2 text-center">
              <div class="text-[11px] text-ink-muted">평균 시도수</div>
              <div class="text-lg font-black text-blue-600">{{ detail.call_stats?.avg_attempts_to_answer || 0 }}</div>
            </div>
            <div class="bg-white rounded-lg p-2 text-center">
              <div class="text-[11px] text-ink-muted">보호자 알림</div>
              <div class="text-lg font-black text-amber-600">{{ detail.call_stats?.guardian_notified || 0 }}</div>
            </div>
          </div>
          <div class="flex gap-4 mt-3 text-[11px] text-ink-muted">
            <div>오늘: <span class="font-bold text-ink-light">{{ detail.call_stats?.today_calls || 0 }}회</span></div>
            <div>이번주: <span class="font-bold text-ink-light">{{ detail.call_stats?.week_calls || 0 }}회</span></div>
            <div>마지막: <span class="font-bold text-ink-light">{{ fmt(detail.call_stats?.last_call) }}</span></div>
          </div>
        </div>

        <!-- 통화 로그 -->
        <div class="rounded-xl border border-gray-100 p-4">
          <div class="flex items-center gap-1 text-xs font-bold text-ink-light mb-2"><AppIcon name="list" :size="13" /> 통화 로그 (최근 100건)</div>
          <div v-if="!detail.call_logs?.length" class="text-sm text-ink-faint text-center py-4">통화 기록 없음</div>
          <div v-else class="max-h-64 overflow-y-auto">
            <table class="w-full text-xs">
              <thead class="sticky top-0 bg-white border-b border-gray-100">
                <tr>
                  <th class="px-2 py-1.5 text-left text-[11px] text-ink-muted">시각</th>
                  <th class="px-2 py-1.5 text-center text-[11px] text-ink-muted">응답</th>
                  <th class="px-2 py-1.5 text-center text-[11px] text-ink-muted">시도</th>
                  <th class="px-2 py-1.5 text-center text-[11px] text-ink-muted">보호자알림</th>
                  <th class="px-2 py-1.5 text-left text-[11px] text-ink-muted">노트</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="log in detail.call_logs" :key="log.id" class="border-b border-gray-50 last:border-0 hover:bg-amber-50/40 transition-colors">
                  <td class="px-2 py-1.5 text-[11px] text-ink-light">{{ fmt(log.called_at) }}</td>
                  <td class="px-2 py-1.5 text-center">
                    <span class="text-[11px] px-1.5 py-0.5 rounded-full font-bold"
                      :class="log.answered ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'">
                      {{ log.answered ? '응답' : '미응답' }}
                    </span>
                  </td>
                  <td class="px-2 py-1.5 text-center font-bold text-ink">{{ log.attempts }}회</td>
                  <td class="px-2 py-1.5 text-center">
                    <span v-if="log.guardian_notified" class="text-[11px] px-1.5 py-0.5 rounded-full font-bold bg-amber-100 text-amber-700">알림</span>
                    <span v-else class="text-[11px] text-ink-faint">-</span>
                  </td>
                  <td class="px-2 py-1.5 text-[11px] text-ink-muted">{{ log.notes || '-' }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- 체크인 통계 -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <div class="rounded-xl p-4 bg-emerald-50">
            <div class="flex items-center gap-1 text-xs font-bold text-emerald-700 mb-2"><AppIcon name="check" :size="13" /> 체크인 통계</div>
            <div class="grid grid-cols-4 gap-2 text-center">
              <div><div class="text-[11px] text-ink-muted">전체</div><div class="font-black text-ink">{{ detail.checkin_stats?.total || 0 }}</div></div>
              <div><div class="text-[11px] text-ink-muted">정상</div><div class="font-black text-green-600">{{ detail.checkin_stats?.ok || 0 }}</div></div>
              <div><div class="text-[11px] text-ink-muted">누락</div><div class="font-black text-yellow-600">{{ detail.checkin_stats?.missed || 0 }}</div></div>
              <div><div class="text-[11px] text-ink-muted">SOS</div><div class="font-black text-red-600">{{ detail.checkin_stats?.sos || 0 }}</div></div>
            </div>
          </div>
          <div class="rounded-xl p-4 bg-red-50">
            <div class="flex items-center gap-1 text-xs font-bold text-red-700 mb-2"><AppIcon name="alert-circle" :size="13" /> SOS 기록</div>
            <div v-if="!detail.recent_sos?.length" class="text-sm text-ink-faint text-center py-2">SOS 없음</div>
            <div v-else class="space-y-1 max-h-24 overflow-y-auto">
              <div v-for="s in detail.recent_sos" :key="s.id" class="text-[11px] text-ink-light">
                <span class="font-bold">{{ fmt(s.created_at) }}</span>
                <span v-if="s.resolved_at" class="text-[11px] text-green-600 ml-1">해결</span>
                <div v-if="s.message" class="text-[11px] text-ink-muted">{{ s.message }}</div>
              </div>
            </div>
          </div>
        </div>

        <!-- 최근 체크인 -->
        <div v-if="detail.recent_checkins?.length" class="rounded-xl border border-gray-100 p-4">
          <div class="flex items-center gap-1 text-xs font-bold text-ink-light mb-2"><AppIcon name="list" :size="13" /> 최근 체크인 (20건)</div>
          <div class="max-h-40 overflow-y-auto space-y-1">
            <div v-for="c in detail.recent_checkins" :key="c.id" class="flex items-center gap-2 text-[11px] text-ink-light">
              <span class="font-mono">{{ fmt(c.checked_in_at) }}</span>
              <span class="text-[11px] px-1.5 py-0.5 rounded-full font-bold"
                :class="c.status === 'ok' ? 'bg-green-100 text-green-700' : c.status === 'sos' ? 'bg-red-100 text-red-700' : 'bg-yellow-100 text-yellow-700'">
                {{ c.status }}
              </span>
              <span v-if="c.lat && c.lng" class="text-[11px] text-ink-faint">{{ Number(c.lat).toFixed(4) }}, {{ Number(c.lng).toFixed(4) }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
</template>

<script setup>
import { ref, computed, watch, inject, onMounted, onBeforeUnmount } from 'vue'
import axios from 'axios'
import AppIcon from '../../components/AppIcon.vue'

// 관리자 휴대폰 화면이면 카드 + 전체 화면 상세 + 확인 시트 (AdminLayout 이 알려 줌)
const adminIsMobile = inject('adminIsMobile', ref(false))
const isMobile = computed(() => !!adminIsMobile.value)
const toast = ref(null); let toastTimer = null
function say(text, error = false) { toast.value = { text, error }; clearTimeout(toastTimer); toastTimer = setTimeout(() => { toast.value = null }, 3000) }
const mDel = ref(null); const mBusy = ref(false)

const tabs = [
  { key: 'overview', icon: 'chart-bar', label: '통계' },
  { key: 'guardians', icon: 'users', label: '매칭' },
  { key: 'calls', icon: 'phone', label: '통화' },
  { key: 'checkins', icon: 'check', label: '체크인' },
  { key: 'sos', icon: 'alert-circle', label: 'SOS' },
]
const tab = ref('overview')
const loading = ref(false)
const overviewLoading = ref(true)
const overview = ref({})
const guardians = ref([])
const calls = ref([])
const checkins = ref([])
const sosLogs = ref([])
const search = ref('')
const statusFilter = ref('')
const page = ref(1)
const lastPage = ref(1)

// 매칭 상세 모달
const detailModal = ref(null)
const detail = ref(null)
const detailLoading = ref(false)

// 상세 화면: 뒤로가기 버튼으로 목록에 돌아오도록 history 한 칸 사용
let pushedDetail = false
function onPopState() { if (pushedDetail && !history.state?.elderDetail) { pushedDetail = false; detailModal.value = null; detail.value = null } }
window.addEventListener('popstate', onPopState)
watch(() => isMobile.value && (!!detailModal.value || !!mDel.value), locked => { document.body.style.overflow = locked ? 'hidden' : '' })
onBeforeUnmount(() => { document.body.style.overflow = ''; clearTimeout(toastTimer); window.removeEventListener('popstate', onPopState) })
const mOverview = computed(() => [
  { label: '활성 매칭', value: overview.value.active_guardians || 0, color: 'text-amber-600' },
  { label: '대기 중 매칭', value: overview.value.pending_guardians || 0, color: 'text-ink-light' },
  { label: '활성 스케줄', value: overview.value.total_schedules || 0, color: 'text-blue-600' },
  { label: '총 안심통화', value: overview.value.total_elder_calls || 0, color: 'text-blue-600' },
  { label: '오늘 통화', value: overview.value.calls_today || 0, color: 'text-green-600' },
  { label: '오늘 체크인', value: overview.value.checkins_today || 0, color: 'text-emerald-600' },
  { label: '미해결 SOS', value: overview.value.sos_unresolved || 0, color: 'text-red-600' },
])
function mPage(p) {
  if (tab.value === 'guardians') loadGuardians(p)
  else if (tab.value === 'calls') loadCalls(p)
  else if (tab.value === 'checkins') loadCheckins(p)
  else if (tab.value === 'sos') loadSos(p)
}
async function mRemove() {
  if (mBusy.value || !mDel.value) return
  mBusy.value = true
  const g = mDel.value
  try { await axios.delete(`/api/admin/elder/guardians/${g.id}`); guardians.value = guardians.value.filter(x => x.id !== g.id); mDel.value = null; say('매칭을 해제했어요') }
  catch (e) { say(e.response?.data?.message || '해제하지 못했어요', true) }
  finally { mBusy.value = false }
}

async function openDetail(g) {
  if (isMobile.value && !pushedDetail) { history.pushState({ ...(history.state || {}), elderDetail: true }, ''); pushedDetail = true }
  detailModal.value = g
  detail.value = null
  detailLoading.value = true
  try {
    const { data } = await axios.get(`/api/admin/elder/guardians/${g.id}/detail`)
    detail.value = data.data || null
  } catch (e) {
    if (isMobile.value) say(e.response?.data?.message || '상세 정보를 불러올 수 없어요', true)
    else alert(e.response?.data?.message || '상세 정보를 불러올 수 없습니다')
  }
  detailLoading.value = false
}

function closeDetail() {
  if (pushedDetail) { history.back(); return }
  detailModal.value = null
  detail.value = null
}

function fmt(v) {
  if (!v) return '-'
  try { return new Date(v).toLocaleString('ko-KR', { month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' }) }
  catch { return v }
}

async function loadOverview() {
  overviewLoading.value = true
  try {
    const { data } = await axios.get('/api/admin/elder/overview')
    overview.value = data.data || {}
  } catch (e) {}
  overviewLoading.value = false
}

async function loadGuardians(p = 1) {
  loading.value = true
  page.value = p
  try {
    const { data } = await axios.get('/api/admin/elder/guardians', {
      params: { page: p, search: search.value, status: statusFilter.value }
    })
    guardians.value = data.data?.data || []
    lastPage.value = data.data?.last_page || 1
  } catch (e) {}
  loading.value = false
}

async function deleteGuardian(g) {
  if (!confirm(`${g.guardian_name} ↔ ${g.ward_name} 매칭을 해제할까요?`)) return
  try {
    await axios.delete(`/api/admin/elder/guardians/${g.id}`)
    guardians.value = guardians.value.filter(x => x.id !== g.id)
  } catch (e) { alert(e.response?.data?.message || '실패') }
}

async function loadCalls(p = 1) {
  loading.value = true
  page.value = p
  try {
    const { data } = await axios.get('/api/admin/elder/calls', { params: { page: p } })
    calls.value = data.data?.data || []
    lastPage.value = data.data?.last_page || 1
  } catch (e) {}
  loading.value = false
}

async function loadCheckins(p = 1) {
  loading.value = true
  page.value = p
  try {
    const { data } = await axios.get('/api/admin/elder/checkins', { params: { page: p } })
    checkins.value = data.data?.data || []
    lastPage.value = data.data?.last_page || 1
  } catch (e) {}
  loading.value = false
}

async function loadSos(p = 1) {
  loading.value = true
  page.value = p
  try {
    const { data } = await axios.get('/api/admin/elder/sos', { params: { page: p } })
    sosLogs.value = data.data?.data || []
    lastPage.value = data.data?.last_page || 1
  } catch (e) {}
  loading.value = false
}

function switchTab(k) {
  tab.value = k
  page.value = 1
  lastPage.value = 1
  if (k === 'overview') loadOverview()
  else if (k === 'guardians') loadGuardians(1)
  else if (k === 'calls') loadCalls(1)
  else if (k === 'checkins') loadCheckins(1)
  else if (k === 'sos') loadSos(1)
}

onMounted(() => loadOverview())
</script>

<style>
/* 휴대폰 관리자: 입력창 글자가 16px 보다 작으면 iOS 가 화면을 확대해 버림 */
.alv-m input, .alv-m textarea, .alv-m select { font-size: 16px; }
</style>
