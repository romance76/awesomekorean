<template>
<div class="space-y-4">
  <!-- 장부: 상품에 쓴 돈 -->
  <div class="grid grid-cols-2 lg:grid-cols-4 gap-2.5">
    <div class="rounded-2xl bg-white border border-gray-100 p-3.5 md:p-3">
      <div class="text-[13px] md:text-xs text-ink-muted">이번 달 쓴 금액</div>
      <div class="text-[22px] md:text-[18px] font-extrabold text-ink mt-0.5">{{ usd(sum.month_usd) }}</div>
    </div>
    <div class="rounded-2xl bg-white border border-gray-100 p-3.5 md:p-3">
      <div class="text-[13px] md:text-xs text-ink-muted">지금까지 쓴 금액</div>
      <div class="text-[22px] md:text-[18px] font-extrabold text-ink mt-0.5">{{ usd(sum.total_usd) }}</div>
    </div>
    <div class="rounded-2xl bg-white border border-gray-100 p-3.5 md:p-3">
      <div class="text-[13px] md:text-xs text-ink-muted">보낸 상품</div>
      <div class="text-[22px] md:text-[18px] font-extrabold text-ink mt-0.5">{{ sum.sent_count || 0 }}건</div>
    </div>
    <div class="rounded-2xl p-3.5 border" :class="sum.pending_count ? 'bg-amber-50 border-amber-200' : 'bg-white border-gray-100'">
      <div class="text-[13px]" :class="sum.pending_count ? 'text-amber-800' : 'text-ink-muted'">아직 안 보낸 상품</div>
      <div class="text-[22px] md:text-[18px] font-extrabold mt-0.5" :class="sum.pending_count ? 'text-amber-700' : 'text-ink'">{{ sum.pending_count || 0 }}건 <span class="text-[13px] font-bold">({{ usd(sum.pending_usd) }})</span></div>
    </div>
  </div>
  <div class="flex items-center justify-between gap-2 flex-wrap -mt-1">
    <p class="text-[12px] text-ink-faint leading-relaxed">금액을 직접 입력하지 않은 {{ sum.estimated_count || 0 }}건은 상품 가치(이벤트에 적은 금액)로 계산해요. 당첨자별 화면에서 실제로 쓴 금액을 입력하면 정확해져요.</p>
    <button @click="showMonths = !showMonths" class="text-[13px] font-bold text-ink-light underline min-h-[36px]">{{ showMonths ? '월별 접기' : '월별 보기' }}</button>
  </div>
  <div v-if="showMonths" class="rounded-2xl bg-white border border-gray-100 overflow-hidden">
    <div v-for="m in (sum.by_month || [])" :key="m.month" class="flex items-center justify-between px-4 py-2.5 border-b border-gray-50 last:border-0 text-[15px]">
      <span class="font-bold text-ink">{{ m.month }}</span><span class="text-ink-muted">{{ m.count }}건</span><span class="font-extrabold text-ink">{{ usd(m.usd) }}</span>
    </div>
    <div v-if="!(sum.by_month || []).length" class="px-4 py-5 text-center text-[14px] text-ink-muted">아직 보낸 상품이 없어요</div>
  </div>

  <!-- 탭 -->
  <div class="flex gap-2 overflow-x-auto scrollbar-hide" role="tablist">
    <button v-for="t in tabs" :key="t.k" @click="setTab(t.k)" role="tab" :aria-selected="tab === t.k"
      class="shrink-0 min-h-[44px] px-4 rounded-full border text-[15px] flex items-center gap-1.5"
      :class="tab === t.k ? 'bg-amber-500 text-white border-amber-500 font-bold' : 'bg-white text-ink border-gray-200 font-medium'">
      {{ t.l }}<span v-if="t.n != null" class="text-[12px] opacity-80">{{ t.n }}</span>
    </button>
  </div>

  <!-- 추첨 목록 (한 줄씩 쌓임) -->
  <div v-if="tab !== 'log' && tab !== 'auto'">
    <div v-if="loading" class="text-center py-10 text-ink-muted text-[15px]">불러오는 중...</div>
    <div v-else-if="!isMobile" class="card overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-xs min-w-[860px]">
          <thead class="bg-gray-50 text-ink-muted">
            <tr class="text-left">
              <th class="px-3 py-2 font-semibold w-[84px]">상태</th>
              <th class="px-3 py-2 font-semibold">이벤트</th>
              <th class="px-3 py-2 font-semibold">상품</th>
              <th class="px-3 py-2 font-semibold">기간 (애틀랜타)</th>
              <th class="px-3 py-2 font-semibold">참가</th>
              <th class="px-3 py-2 font-semibold">상품 지급</th>
              <th class="px-3 py-2 font-semibold text-right">처리</th>
            </tr>
          </thead>
          <tbody>
            <template v-for="item in visible" :key="item.id">
              <tr class="border-t border-gray-50 hover:bg-amber-50/30" :class="isDone(item) ? 'cursor-pointer' : ''" @click="isDone(item) && toggle(item)">
                <td class="px-3 py-2"><span class="text-[11px] px-2 py-0.5 rounded-full font-bold whitespace-nowrap" :class="badge(item.status)">{{ label(item.status) }}</span></td>
                <td class="px-3 py-2 max-w-[260px]"><div class="font-semibold text-ink truncate" :title="item.title">{{ item.title }}</div></td>
                <td class="px-3 py-2 max-w-[220px]"><div class="text-ink-light truncate" :title="item.prize_name">🎁 {{ item.prize_name }}<span v-if="item.prize_value"> (${{ item.prize_value }})</span><span v-if="(item.winner_count || 1) > 1"> · {{ item.winner_count }}명</span></div></td>
                <td class="px-3 py-2 whitespace-nowrap tabular-nums text-ink-light">{{ short(item.start_at) }} ~ {{ short(item.end_at) }}</td>
                <td class="px-3 py-2 whitespace-nowrap text-ink-light">{{ item.unique_participants ?? 0 }}명 · {{ item.total_entries }}</td>
                <td class="px-3 py-2 whitespace-nowrap">
                  <template v-if="isDone(item)">
                    <span v-if="progress[item.id]" class="text-[11px] px-2 py-0.5 rounded-full font-bold" :class="progress[item.id].pending ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-700'">{{ progress[item.id].sent }}/{{ progress[item.id].total }}<span v-if="progress[item.id].pending"> · 안 보냄 {{ progress[item.id].pending }}</span></span>
                    <span v-else class="text-ink-faint">—</span>
                  </template>
                  <span v-else class="text-ink-faint">—</span>
                </td>
                <td class="px-3 py-2 text-right whitespace-nowrap" @click.stop>
                  <template v-if="!isDone(item)">
                    <button @click="$emit('participants', item)" class="bg-gray-100 text-ink text-[11px] font-bold px-2 py-1 rounded-lg hover:bg-gray-200">참가현황</button>
                    <RouterLink v-if="item.event_id" :to="`/events/${item.event_id}`" class="ml-1 bg-gray-100 text-ink text-[11px] font-bold px-2 py-1 rounded-lg hover:bg-gray-200 inline-block">보기</RouterLink>
                    <RouterLink v-if="item.event_id" :to="`/events/${item.event_id}/edit`" class="ml-1 bg-gray-100 text-ink text-[11px] font-bold px-2 py-1 rounded-lg hover:bg-gray-200 inline-block">수정</RouterLink>
                    <button @click="$emit('design', item)" class="ml-1 bg-amber-50 text-amber-700 text-[11px] font-bold px-2 py-1 rounded-lg hover:bg-amber-100">화면 꾸미기</button>
                    <button @click="$emit('winner', item)" class="ml-1 bg-violet-600 text-white text-[11px] font-bold px-2 py-1 rounded-lg hover:bg-violet-700">당첨자 선정</button>
                    <button @click="$emit('delete', item)" :disabled="item.total_entries > 0" class="ml-1 text-red-500 text-[11px] font-bold px-1.5 py-1 disabled:opacity-30">삭제</button>
                  </template>
                  <template v-else>
                    <button @click="toggle(item)" class="bg-white border border-gray-200 text-ink text-[11px] font-bold px-2 py-1 rounded-lg hover:bg-gray-50">{{ openId === item.id ? '당첨자 접기' : '당첨자·지급' }}</button>
                  </template>
                </td>
              </tr>
              <tr v-if="isDone(item) && openId === item.id" class="border-t border-gray-50">
                <td colspan="7" class="bg-gray-50/70 px-4 py-3">
                  <div v-if="claimsLoading" class="text-xs text-ink-muted">당첨자 불러오는 중...</div>
                  <template v-else>
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-2">
                      <button v-for="c in (claims[item.id] || [])" :key="c.id" @click="openPanel(c, item)" class="text-left rounded-lg bg-white border border-gray-200 px-3 py-2 flex items-center gap-3 hover:bg-amber-50">
                        <b class="shrink-0 w-8 text-amber-700 text-xs">{{ c.rank }}등</b>
                        <span class="min-w-0 flex-1">
                          <span class="block text-[13px] font-bold text-ink truncate">{{ c.nickname || c.name || ('회원 #' + c.user_id) }}</span>
                          <span class="block text-[11px] text-ink-muted truncate">{{ c.prize_label || item.prize_name }} · {{ c.prize_type === 'physical' ? '실물' : '디지털' }}<span v-if="costOf(c, item) != null"> · {{ usd(costOf(c, item)) }}</span></span>
                        </span>
                        <span class="shrink-0 text-[11px] font-bold px-2 py-0.5 rounded-full" :class="stChip(c.delivery_status)">{{ stLabel(c.delivery_status) }}</span>
                      </button>
                    </div>
                    <div v-if="!(claims[item.id] || []).length" class="text-xs text-ink-muted">당첨자가 없어요</div>
                    <div class="flex flex-wrap gap-1.5 mt-2">
                      <button @click="$emit('participants', item)" class="bg-white border border-gray-200 text-ink text-[11px] font-bold px-2 py-1 rounded-lg hover:bg-gray-50">참가현황</button>
                      <RouterLink v-if="item.event_id" :to="`/events/${item.event_id}`" class="bg-white border border-gray-200 text-ink text-[11px] font-bold px-2 py-1 rounded-lg hover:bg-gray-50 inline-block">이벤트 보기</RouterLink>
                      <button @click="loadLogsFor(item)" class="bg-white border border-gray-200 text-ink text-[11px] font-bold px-2 py-1 rounded-lg hover:bg-gray-50">이 추첨 이력</button>
                    </div>
                    <div v-if="itemLogs && itemLogs.id === item.id" class="mt-2 rounded-lg bg-white border border-gray-200 p-2.5 space-y-1">
                      <div v-for="l in itemLogs.rows" :key="l.id" class="text-[11px]"><span class="text-ink-faint tabular-nums">{{ tAtl(l.created_at) }}</span> · <b>{{ actionLabel(l.action) }}</b> · {{ l.winner_name || '' }}<span v-if="l.actor_name" class="text-ink-muted"> (처리 {{ l.actor_name }})</span></div>
                      <div v-if="!itemLogs.rows.length" class="text-[11px] text-ink-muted">아직 이력이 없어요</div>
                    </div>
                  </template>
                </td>
              </tr>
            </template>
            <tr v-if="!shown.length"><td colspan="7" class="py-12 text-center text-ink-muted text-sm">{{ tab === 'active' ? '진행 중인 추첨이 없어요' : '표시할 추첨이 없어요' }}</td></tr>
          </tbody>
        </table>
      </div>
      <div v-if="shown.length > visible.length" class="border-t border-gray-50 px-4 py-2.5 text-center"><button @click="limit += 30" class="text-xs font-bold text-ink-light underline">더 보기 ({{ shown.length - visible.length }}개 남음)</button></div>
    </div>
    <div v-else class="rounded-2xl bg-white border border-gray-100 overflow-hidden divide-y divide-gray-100">
      <div v-for="item in visible" :key="item.id">
        <div class="px-3.5 py-3 flex items-start gap-3" :class="isDone(item) ? 'cursor-pointer active:bg-gray-50' : ''" @click="isDone(item) && toggle(item)">
          <span class="mt-0.5 shrink-0 text-[12px] font-bold px-2.5 py-1 rounded-full" :class="badge(item.status)">{{ label(item.status) }}</span>
          <div class="min-w-0 flex-1">
            <div class="text-[16px] font-bold text-ink break-words leading-snug">{{ item.title }}</div>
            <div class="text-[14px] text-ink-muted mt-0.5 break-words">🎁 {{ item.prize_name }}<span v-if="item.prize_value"> (${{ item.prize_value }})</span><span v-if="(item.winner_count || 1) > 1"> · {{ item.winner_count }}명</span></div>
            <div class="text-[12.5px] text-ink-faint mt-0.5">{{ short(item.start_at) }} ~ {{ short(item.end_at) }} · 참가 {{ item.unique_participants ?? 0 }}명 · Entry {{ item.total_entries }}</div>
            <div v-if="isDone(item) && progress[item.id]" class="mt-1 text-[13px] font-bold" :class="progress[item.id].pending ? 'text-amber-700' : 'text-emerald-700'">
              지급 {{ progress[item.id].sent }}/{{ progress[item.id].total }}<span v-if="progress[item.id].pending"> · 안 보냄 {{ progress[item.id].pending }}</span>
            </div>
          </div>
          <span v-if="isDone(item)" class="shrink-0 text-ink-faint text-[18px] mt-1">{{ openId === item.id ? '▲' : '▼' }}</span>
        </div>

        <!-- 진행중·준비중: 관리 버튼 -->
        <div v-if="!isDone(item)" class="px-3.5 pb-3 flex flex-wrap gap-2">
          <button @click="$emit('participants', item)" class="min-h-[44px] px-4 rounded-xl bg-gray-100 text-ink text-[14px] font-bold">참가 현황</button>
          <RouterLink v-if="item.event_id" :to="`/events/${item.event_id}`" class="min-h-[44px] px-4 rounded-xl bg-gray-100 text-ink text-[14px] font-bold inline-flex items-center">이벤트 보기</RouterLink>
          <RouterLink v-if="item.event_id" :to="`/events/${item.event_id}/edit`" class="min-h-[44px] px-4 rounded-xl bg-gray-100 text-ink text-[14px] font-bold inline-flex items-center">수정</RouterLink>
          <button @click="$emit('design', item)" class="min-h-[44px] px-4 rounded-xl bg-amber-50 text-amber-700 text-[14px] font-bold">추첨 화면 꾸미기</button>
          <button @click="$emit('winner', item)" class="min-h-[44px] px-4 rounded-xl bg-violet-600 text-white text-[14px] font-bold">당첨자 선정</button>
          <button @click="$emit('delete', item)" :disabled="item.total_entries > 0" class="min-h-[44px] px-3 rounded-xl text-red-500 text-[14px] font-bold disabled:opacity-30">삭제</button>
        </div>

        <!-- 종료: 당첨자 펼침 -->
        <div v-if="isDone(item) && openId === item.id" class="bg-gray-50/70 px-3.5 py-3 space-y-2">
          <div v-if="claimsLoading" class="text-[14px] text-ink-muted py-2">당첨자 불러오는 중...</div>
          <template v-else>
            <button v-for="c in (claims[item.id] || [])" :key="c.id" @click="openPanel(c, item)"
              class="w-full text-left rounded-xl bg-white border border-gray-200 px-3.5 py-3 flex items-center gap-3 active:bg-amber-50" style="min-height:56px">
              <b class="shrink-0 w-10 text-amber-700 text-[15px]">{{ c.rank }}등</b>
              <span class="min-w-0 flex-1">
                <span class="block text-[16px] font-bold text-ink truncate">{{ c.nickname || c.name || ('회원 #' + c.user_id) }}</span>
                <span class="block text-[13px] text-ink-muted truncate">{{ c.prize_label || item.prize_name }} · {{ c.prize_type === 'physical' ? '실물' : '디지털' }}<span v-if="costOf(c, item) != null"> · {{ usd(costOf(c, item)) }}</span></span>
              </span>
              <span class="shrink-0 text-[12px] font-bold px-2.5 py-1 rounded-full" :class="stChip(c.delivery_status)">{{ stLabel(c.delivery_status) }}</span>
            </button>
            <div v-if="!(claims[item.id] || []).length" class="text-[14px] text-ink-muted py-2">당첨자가 없어요</div>
            <div class="flex flex-wrap gap-2 pt-1">
              <button @click="$emit('participants', item)" class="min-h-[40px] px-3.5 rounded-xl bg-white border border-gray-200 text-ink text-[13px] font-bold">참가 현황</button>
              <RouterLink v-if="item.event_id" :to="`/events/${item.event_id}`" class="min-h-[40px] px-3.5 rounded-xl bg-white border border-gray-200 text-ink text-[13px] font-bold inline-flex items-center">이벤트 보기</RouterLink>
              <button @click="loadLogsFor(item)" class="min-h-[40px] px-3.5 rounded-xl bg-white border border-gray-200 text-ink text-[13px] font-bold">이 추첨 이력</button>
            </div>
            <div v-if="itemLogs && itemLogs.id === item.id" class="rounded-xl bg-white border border-gray-200 p-3 space-y-2">
              <div v-for="l in itemLogs.rows" :key="l.id" class="text-[13px]"><span class="text-ink-faint tabular-nums">{{ tAtl(l.created_at) }}</span> · <b>{{ actionLabel(l.action) }}</b> · {{ l.winner_name || '' }} <span v-if="l.actor_name" class="text-ink-muted">(처리 {{ l.actor_name }})</span></div>
              <div v-if="!itemLogs.rows.length" class="text-[13px] text-ink-muted">아직 이력이 없어요</div>
            </div>
          </template>
        </div>
      </div>
      <div v-if="!shown.length" class="py-12 text-center text-ink-muted text-[15px]">{{ tab === 'active' ? '진행 중인 추첨이 없어요' : '표시할 추첨이 없어요' }}</div>
      <button v-if="shown.length > visible.length" @click="limit += 30" class="w-full min-h-[48px] text-[14px] font-bold text-ink-light underline">더 보기 ({{ shown.length - visible.length }}개 남음)</button>
    </div>
  </div>

  <!-- 전체 지급 이력 -->
  <div v-else-if="tab === 'log'" class="rounded-2xl bg-white border border-gray-100 overflow-hidden">
    <div v-if="logsLoading" class="py-10 text-center text-ink-muted text-[15px]">불러오는 중...</div>
    <div v-for="l in logs" :key="l.id" class="px-3.5 py-3 border-b border-gray-50 last:border-0">
      <div class="flex items-center gap-2 flex-wrap">
        <span class="text-[12px] font-bold px-2 py-0.5 rounded-full" :class="actionChip(l.action)">{{ actionLabel(l.action) }}</span>
        <span class="text-[15px] font-bold text-ink">{{ l.winner_name || '-' }}</span>
        <span class="text-[13px] text-ink-muted truncate">{{ l.sweepstakes_title }}</span>
      </div>
      <div class="text-[12.5px] text-ink-faint mt-0.5 tabular-nums" :title="tUtc(l.created_at)">{{ tAtl(l.created_at) }} (애틀랜타) · {{ tUtc(l.created_at) }} (UTC)<span v-if="l.actor_name"> · 처리 {{ l.actor_name }}</span></div>
      <div v-if="l.note" class="text-[13px] text-ink-light mt-1 break-words">📝 {{ l.note }}</div>
    </div>
    <div v-if="!logsLoading && !logs.length" class="py-12 text-center text-ink-muted text-[15px]">아직 지급 이력이 없어요</div>
  </div>

  <SweepstakesAutomation v-if="tab === 'auto'" @changed="$emit('reload')" />

  <!-- 당첨자 지급 패널 -->
  <Teleport to="body">
    <div v-if="panel" class="fixed inset-0 z-[72] bg-black/45 flex items-end sm:items-center justify-center" @click.self="closePanel">
      <div class="w-full sm:max-w-lg bg-white rounded-t-3xl sm:rounded-2xl max-h-[92vh] overflow-y-auto px-4 pt-3 pb-5" role="dialog" aria-modal="true" :style="{ paddingBottom: 'calc(20px + env(safe-area-inset-bottom, 0px))' }">
        <div class="w-10 h-1 rounded bg-gray-200 mx-auto mb-3 sm:hidden"></div>
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0">
            <div class="text-[18px] font-extrabold text-ink break-words">{{ panel.c.rank }}등 · {{ panel.c.nickname || panel.c.name }}</div>
            <div class="text-[13px] text-ink-muted break-words">{{ panel.item.title }} · {{ panel.c.prize_label || panel.item.prize_name }}</div>
          </div>
          <button @click="closePanel" class="w-11 h-11 -mt-1 -mr-2 rounded-full text-ink-light text-[22px] shrink-0" aria-label="닫기">✕</button>
        </div>
        <span class="inline-block mt-2 text-[12px] font-bold px-2.5 py-1 rounded-full" :class="stChip(panel.c.delivery_status)">{{ stLabel(panel.c.delivery_status) }}<span v-if="panel.c.sent_at"> · {{ tAtl(panel.c.sent_at) }}</span></span>

        <!-- 당첨자 연락처 -->
        <div class="mt-3 rounded-xl bg-gray-50 p-3 text-[14px] space-y-0.5">
          <div class="font-bold text-ink">당첨자 정보 <span class="text-[12px] font-normal" :class="panel.c.contact_confirmed_at ? 'text-emerald-700' : 'text-amber-700'">{{ panel.c.contact_confirmed_at ? '(본인이 확인함)' : '(본인 확인 전 — 프로필 기준)' }}</span></div>
          <div class="text-ink-light break-all">이름 {{ panel.c.name || '-' }}</div>
          <div class="text-ink-light break-all">이메일 {{ panel.c.email || '-' }}</div>
          <div class="text-ink-light">전화 {{ panel.c.phone || '-' }}</div>
          <div class="text-ink-light break-words">주소 {{ panel.c.address || '-' }}</div>
        </div>

        <!-- 상품 종류 -->
        <div class="mt-4 text-[14px] font-bold text-ink mb-1.5">상품 종류</div>
        <div class="grid grid-cols-2 gap-2">
          <button v-for="o in [{v:'digital',l:'디지털 (링크·코드)'},{v:'physical',l:'실물 (배송)'}]" :key="o.v" @click="form.prize_type = o.v"
            class="min-h-[48px] rounded-xl border-2 text-[15px] font-bold" :class="form.prize_type === o.v ? 'border-amber-500 bg-amber-50 text-amber-800' : 'border-gray-200 text-ink'">{{ o.l }}</button>
        </div>

        <template v-if="form.prize_type === 'digital'">
          <label class="block mt-3 text-[14px] font-bold text-ink">상품 링크 또는 코드</label>
          <textarea v-model="form.link" rows="2" maxlength="2000" placeholder="예: https://... 또는 기프트카드 코드" class="mt-1 w-full rounded-xl border border-gray-200 px-3 py-2.5 text-[16px]"></textarea>
          <p class="text-[12px] text-ink-faint mt-1">보내면 당첨자 쪽지함으로 전달되고 알림이 가요. 링크·코드는 이력에는 남기지 않아요.</p>
        </template>
        <template v-else>
          <label class="block mt-3 text-[14px] font-bold text-ink">안내 메시지 (비워 두면 기본 안내가 가요)</label>
          <textarea v-model="form.message" rows="3" maxlength="1500" placeholder="기본: 받으실 주소와 연락처를 이 쪽지로 답장해 주세요." class="mt-1 w-full rounded-xl border border-gray-200 px-3 py-2.5 text-[16px]"></textarea>
        </template>
        <label v-if="form.prize_type === 'digital'" class="block mt-3 text-[14px] font-bold text-ink">추가 안내 (선택)</label>
        <textarea v-if="form.prize_type === 'digital'" v-model="form.message" rows="2" maxlength="1500" placeholder="예: 사용 기한, 사용 방법" class="mt-1 w-full rounded-xl border border-gray-200 px-3 py-2.5 text-[16px]"></textarea>

        <label class="block mt-3 text-[14px] font-bold text-ink">실제로 쓴 금액 (USD)</label>
        <input v-model="form.cost" type="number" inputmode="decimal" min="0" step="0.01" :placeholder="panel.c.default_cost != null ? ('비워 두면 ' + usd(panel.c.default_cost) + ' 로 계산') : '예: 10.00'" class="mt-1 w-full rounded-xl border border-gray-200 px-3 py-2.5 text-[16px]" />

        <p v-if="err" class="mt-2 text-[14px] text-red-600">{{ err }}</p>
        <button v-if="!confirmSend" @click="askSend" :disabled="busy" class="mt-4 w-full min-h-[52px] rounded-xl bg-amber-500 text-white text-[16px] font-extrabold disabled:opacity-50">{{ panel.c.delivery_status === 'pending' ? '쪽지로 보내기' : '다시 보내기' }}</button>
        <div v-else class="mt-4 rounded-xl border-2 border-amber-300 bg-amber-50 p-3">
          <div class="text-[15px] font-bold text-amber-900">{{ panel.c.nickname || panel.c.name }} 님에게 지금 보낼까요?</div>
          <div class="grid grid-cols-2 gap-2 mt-2">
            <button @click="confirmSend = false" :disabled="busy" class="min-h-[48px] rounded-xl bg-white border border-gray-200 text-ink text-[15px] font-bold">아니요</button>
            <button @click="doSend" :disabled="busy" class="min-h-[48px] rounded-xl bg-amber-500 text-white text-[15px] font-extrabold disabled:opacity-50">{{ busy ? '보내는 중...' : '보내기' }}</button>
          </div>
        </div>

        <div class="mt-3 text-[13px] font-bold text-ink-light">직접 처리했을 때</div>
        <div class="grid grid-cols-3 gap-2 mt-1.5">
          <button @click="setStatus('sent')" :disabled="busy" class="min-h-[44px] rounded-xl bg-gray-100 text-ink text-[13px] font-bold">직접 보냄</button>
          <button @click="setStatus('confirmed')" :disabled="busy" class="min-h-[44px] rounded-xl bg-emerald-50 text-emerald-700 text-[13px] font-bold">수령 확인</button>
          <button @click="setStatus('pending')" :disabled="busy" class="min-h-[44px] rounded-xl bg-gray-100 text-ink-light text-[13px] font-bold">대기로</button>
        </div>
        <button @click="saveCostOnly" :disabled="busy" class="mt-2 w-full min-h-[44px] rounded-xl border border-gray-200 text-ink-light text-[13px] font-bold">쓴 금액만 저장</button>

        <div class="mt-4 text-[14px] font-bold text-ink">처리 이력</div>
        <div class="mt-1.5 space-y-2">
          <div v-for="l in panelLogs" :key="l.id" class="text-[13px] rounded-lg bg-gray-50 px-3 py-2">
            <div><b>{{ actionLabel(l.action) }}</b><span v-if="l.actor_name" class="text-ink-muted"> · 처리 {{ l.actor_name }}</span></div>
            <div class="text-ink-faint tabular-nums">{{ tAtl(l.created_at) }} (애틀랜타) · {{ tUtc(l.created_at) }} (UTC)</div>
            <div v-if="l.note" class="text-ink-light break-words">📝 {{ l.note }}</div>
          </div>
          <div v-if="!panelLogs.length" class="text-[13px] text-ink-muted">아직 이력이 없어요</div>
        </div>
      </div>
    </div>
    <div v-if="msg" class="fixed left-1/2 -translate-x-1/2 z-[90] max-w-[90vw] px-4 py-3 rounded-xl text-[15px] font-bold text-white shadow-lg" :class="msg.error ? 'bg-red-600' : 'bg-ink'" style="bottom: calc(24px + env(safe-area-inset-bottom, 0px))" role="status">{{ msg.text }}</div>
  </Teleport>
</div>
</template>

<script setup>
import { ref, computed, watch, onMounted, inject } from 'vue'
import axios from 'axios'
import SweepstakesAutomation from './SweepstakesAutomation.vue'

const props = defineProps({ items: { type: Array, default: () => [] }, loading: Boolean })
defineEmits(['participants', 'design', 'winner', 'delete', 'reload'])

const adminIsMobile = inject('adminIsMobile', ref(false))
const isMobile = computed(() => !!adminIsMobile.value)
const limit = ref(30)
const tab = ref('active')
const openId = ref(null)
const claims = ref({})          // { sweepstakesId: [claim...] }
const progress = ref({})        // { sweepstakesId: { total, sent, pending } }
const claimsLoading = ref(false)
const sum = ref({})
const showMonths = ref(false)
const logs = ref([])
const logsLoading = ref(false)
const itemLogs = ref(null)
const panel = ref(null)         // { c, item }
const panelLogs = ref([])
const form = ref({ prize_type: 'digital', link: '', message: '', cost: '' })
const busy = ref(false)
const confirmSend = ref(false)
const err = ref('')
const msg = ref(null)
let msgTimer = null
function say(text, error = false) { msg.value = { text, error }; clearTimeout(msgTimer); msgTimer = setTimeout(() => { msg.value = null }, 3500) }

const isDone = (it) => it.status === 'winner_selected'
const isOpen = (it) => it.status === 'active' || it.status === 'draft' || it.status === 'ended'
const tabs = computed(() => [
  { k: 'active', l: '진행·예정', n: props.items.filter(isOpen).length },
  { k: 'done', l: '종료·당첨', n: props.items.filter(isDone).length },
  { k: 'all', l: '전체', n: props.items.length },
  { k: 'log', l: '지급 이력', n: null },
  { k: 'auto', l: '반복·자동', n: null },
])
const shown = computed(() => {
  const list = [...props.items]
  if (tab.value === 'active') return list.filter(isOpen).sort((a, b) => new Date(a.end_at) - new Date(b.end_at))
  if (tab.value === 'done') return list.filter(isDone).sort((a, b) => new Date(b.end_at) - new Date(a.end_at))
  return list.sort((a, b) => new Date(b.start_at) - new Date(a.start_at))
})

const visible = computed(() => shown.value.slice(0, limit.value))
const usd = (n) => '$' + (Number(n) || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
const costOf = (c, item) => c.cost_usd != null ? c.cost_usd : (c.default_cost != null ? c.default_cost : (item.prize_value != null ? Number(item.prize_value) : null))
function label(s) { return { draft: '준비중', active: '진행중', ended: '마감', winner_selected: '종료·당첨', cancelled: '취소됨' }[s] || s }
function badge(s) { return { draft: 'bg-gray-100 text-gray-600', active: 'bg-green-100 text-green-700', ended: 'bg-amber-100 text-amber-700', winner_selected: 'bg-violet-100 text-violet-700', cancelled: 'bg-red-100 text-red-700' }[s] || 'bg-gray-100 text-gray-600' }
const stLabel = (s) => ({ pending: '안 보냄', sent: '보냄', confirmed: '수령 확인' }[s] || '안 보냄')
const stChip = (s) => ({ sent: 'bg-blue-100 text-blue-700', confirmed: 'bg-emerald-100 text-emerald-700' }[s] || 'bg-amber-100 text-amber-800')
const ACTIONS = { digital_sent: '디지털 상품 보냄', physical_notice_sent: '실물 안내 보냄', status_changed: '상태 변경', cost_set: '금액 입력', note: '메모' }
const actionLabel = (a) => ACTIONS[a] || a
const actionChip = (a) => ({ digital_sent: 'bg-blue-100 text-blue-700', physical_notice_sent: 'bg-violet-100 text-violet-700', cost_set: 'bg-gray-100 text-ink-light' }[a] || 'bg-amber-100 text-amber-800')
const short = (dt) => dt ? new Date(dt).toLocaleString('ko-KR', { timeZone: 'America/New_York', month: 'numeric', day: 'numeric', hour: '2-digit', minute: '2-digit', hour12: false }) : ''
const tAtl = (iso) => iso ? new Date(iso).toLocaleString('ko-KR', { timeZone: 'America/New_York', month: 'numeric', day: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false }) : ''
const tUtc = (iso) => iso ? new Date(iso).toLocaleString('ko-KR', { timeZone: 'UTC', month: 'numeric', day: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false }) : ''

async function loadSummary() { try { const { data } = await axios.get('/api/admin/sweepstakes-delivery/summary'); sum.value = data.data || {} } catch {} }
async function loadLogs() {
  logsLoading.value = true
  try { const { data } = await axios.get('/api/admin/sweepstakes-delivery/logs?limit=150'); logs.value = data.data || [] } catch { logs.value = [] }
  logsLoading.value = false
}
function setTab(k) { tab.value = k; limit.value = 30; openId.value = null; if (k === 'log') loadLogs() }

async function fetchClaims(item) {
  try {
    const { data } = await axios.get(`/api/admin/sweepstakes/${item.id}/claims`)
    const rows = data.data || []
    claims.value = { ...claims.value, [item.id]: rows }
    progress.value = { ...progress.value, [item.id]: { total: rows.length, sent: rows.filter(r => r.delivery_status !== 'pending').length, pending: rows.filter(r => r.delivery_status === 'pending').length } }
  } catch { claims.value = { ...claims.value, [item.id]: [] } }
}
async function toggle(item) {
  if (openId.value === item.id) { openId.value = null; return }
  openId.value = item.id; itemLogs.value = null
  claimsLoading.value = true
  await fetchClaims(item)
  claimsLoading.value = false
}
async function loadLogsFor(item) {
  try { const { data } = await axios.get(`/api/admin/sweepstakes-delivery/logs?sweepstakes_id=${item.id}&limit=100`); itemLogs.value = { id: item.id, rows: data.data || [] } } catch { itemLogs.value = { id: item.id, rows: [] } }
}

async function loadPanelLogs() {
  if (!panel.value) return
  try { const { data } = await axios.get(`/api/admin/sweepstakes-delivery/logs?claim_id=${panel.value.c.id}&limit=50`); panelLogs.value = data.data || [] } catch { panelLogs.value = [] }
}
function openPanel(c, item) {
  panel.value = { c: { ...c }, item }
  form.value = { prize_type: c.prize_type || 'digital', link: '', message: '', cost: c.cost_usd != null ? String(c.cost_usd) : '' }
  confirmSend.value = false; err.value = ''; panelLogs.value = []
  document.body.style.overflow = 'hidden'
  loadPanelLogs()
}
function closePanel() { if (busy.value) return; panel.value = null; document.body.style.overflow = '' }
function applyClaim(row) {
  if (!row) return
  panel.value.c = { ...panel.value.c, ...row }
  const id = panel.value.item.id
  claims.value = { ...claims.value, [id]: (claims.value[id] || []).map(c => c.id === row.id ? { ...c, ...row } : c) }
  const rows = claims.value[id] || []
  progress.value = { ...progress.value, [id]: { total: rows.length, sent: rows.filter(r => r.delivery_status !== 'pending').length, pending: rows.filter(r => r.delivery_status === 'pending').length } }
}
function askSend() {
  err.value = ''
  if (form.value.prize_type === 'digital' && !form.value.link.trim()) { err.value = '상품 링크나 코드를 입력해 주세요'; return }
  confirmSend.value = true
}
async function doSend() {
  if (busy.value) return
  busy.value = true; err.value = ''
  try {
    const body = { prize_type: form.value.prize_type, delivery_link: form.value.link.trim() || null, message: form.value.message.trim() || null }
    if (form.value.cost !== '') body.cost_usd = Number(form.value.cost)
    const { data } = await axios.post(`/api/admin/prize-claims/${panel.value.c.id}/deliver`, body)
    applyClaim(data.data); confirmSend.value = false; form.value.link = ''
    say('쪽지로 보냈어요'); loadSummary(); loadPanelLogs()
  } catch (e) { err.value = e.response?.data?.message || '보내지 못했어요. 다시 시도해 주세요.' }
  busy.value = false
}
async function setStatus(st) {
  if (busy.value) return
  busy.value = true; err.value = ''
  try {
    const body = { delivery_status: st, prize_type: form.value.prize_type }
    if (form.value.cost !== '') body.cost_usd = Number(form.value.cost)
    const { data } = await axios.put(`/api/admin/prize-claims/${panel.value.c.id}`, body)
    applyClaim(data.data); say('상태를 바꿨어요'); loadSummary(); loadPanelLogs()
  } catch (e) { err.value = e.response?.data?.message || '저장하지 못했어요' }
  busy.value = false
}
async function saveCostOnly() {
  if (busy.value) return
  if (form.value.cost === '') { err.value = '금액을 입력해 주세요'; return }
  busy.value = true; err.value = ''
  try {
    const { data } = await axios.put(`/api/admin/prize-claims/${panel.value.c.id}`, { cost_usd: Number(form.value.cost) })
    applyClaim(data.data); say('금액을 저장했어요'); loadSummary(); loadPanelLogs()
  } catch (e) { err.value = e.response?.data?.message || '저장하지 못했어요' }
  busy.value = false
}

watch(() => props.items, () => { loadSummary() })
// 종료된 추첨은 목록에서 바로 "지급 n/m" 이 보이도록, 화면에 보이는 것들의 지급 현황을 미리 불러온다
watch(visible, async (list) => {
  const todo = list.filter(it => isDone(it) && !progress.value[it.id]).slice(0, 30)
  for (let i = 0; i < todo.length; i += 6) await Promise.all(todo.slice(i, i + 6).map(fetchClaims))
}, { immediate: true })
onMounted(loadSummary)
</script>
