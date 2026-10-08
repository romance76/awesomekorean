<template>
<!-- ───────── 휴대폰 화면 ───────── -->
<div v-if="isMobile" class="alv-m space-y-3 pb-4">
  <div class="grid grid-cols-3 gap-2">
    <div v-for="o in overviewCards" :key="o.label" class="bg-white border border-gray-100 rounded-2xl p-3">
      <div class="text-[12px] text-ink-muted">{{ o.label }}</div>
      <div class="text-[18px] font-bold tabular-nums break-all" :class="o.color">{{ o.value }}</div>
    </div>
  </div>
  <div class="flex gap-2 overflow-x-auto scrollbar-hide" role="group" aria-label="포커 메뉴">
    <button v-for="t in [['tour','토너먼트'],['wallet','지갑'],['set','설정']]" :key="t[0]" @click="mTab = t[0]" :aria-pressed="mTab === t[0]"
      class="shrink-0 min-h-[44px] px-5 rounded-full border text-[15px]" :class="mTab === t[0] ? 'bg-ink text-white border-ink font-bold' : 'bg-white text-ink border-gray-200 font-medium'">{{ t[1] }}</button>
  </div>

  <!-- 토너먼트 -->
  <template v-if="mTab === 'tour'">
    <button @click="showCreateTournament = true" class="w-full min-h-[52px] rounded-xl bg-amber-500 text-white text-[16px] font-bold">+ 새 토너먼트</button>
    <div v-if="!tournaments.length" class="text-center py-12 text-ink-muted text-[15px]">토너먼트가 없어요.</div>
    <div v-for="t in tournaments" :key="t.id" class="bg-white border border-gray-100 rounded-2xl p-3.5">
      <div class="flex items-center gap-1.5 flex-wrap">
        <span class="text-[12px] px-2.5 py-1 rounded-full font-bold" :class="{'bg-blue-50 text-blue-700': t.status==='scheduled', 'bg-emerald-50 text-emerald-700': t.status==='registering', 'bg-amber-50 text-amber-700': t.status==='running', 'bg-gray-100 text-ink-light': t.status==='finished' || t.status==='cancelled'}">{{ statusKo[t.status] || t.status }}</span>
        <span v-if="t.is_template" class="text-[12px] font-bold text-blue-600 bg-blue-50 px-2 py-0.5 rounded-full">반복</span>
      </div>
      <div class="text-[16px] font-bold text-ink mt-1 break-words">{{ t.title }}</div>
      <div class="text-[13px] text-ink-muted mt-0.5">{{ formatNY(t.scheduled_at) }} (뉴욕 시간)</div>
      <div class="grid grid-cols-2 gap-2 mt-2 text-[14px]">
        <div class="bg-gray-50 rounded-xl px-3 py-2"><div class="text-[12px] text-ink-muted">바이인</div><div class="font-bold tabular-nums">{{ (t.buy_in || 0).toLocaleString() }}</div></div>
        <div class="bg-gray-50 rounded-xl px-3 py-2"><div class="text-[12px] text-ink-muted">참가자</div><div class="font-bold tabular-nums">{{ t.entries_count || 0 }} / {{ t.max_players }}</div></div>
      </div>
      <button v-if="t.status !== 'running' && t.status !== 'finished' && t.status !== 'cancelled'" @click="mCancel = t" class="mt-3 w-full min-h-[48px] rounded-xl bg-red-50 text-red-600 text-[15px] font-bold">토너먼트 취소</button>
    </div>
  </template>

  <!-- 지갑 -->
  <template v-else-if="mTab === 'wallet'">
    <div class="text-[14px] text-ink-muted px-0.5">총 {{ wallets.length }}건 (칩 잔고 많은 순)</div>
    <div v-if="!wallets.length" class="text-center py-12 text-ink-muted text-[15px]">지갑 데이터가 없어요.</div>
    <div v-for="w in wallets" :key="w.id" class="bg-white border border-gray-100 rounded-2xl p-3.5">
      <div class="flex items-center gap-2">
        <div class="min-w-0 flex-1"><div class="text-[16px] font-bold text-ink truncate">{{ w.user?.name || w.user?.nickname || '?' }}</div><div class="text-[12px] text-ink-muted truncate">{{ w.user?.email || '-' }}</div></div>
        <div class="text-[18px] font-bold text-amber-600 tabular-nums">{{ (w.chips_balance || 0).toLocaleString() }}</div>
      </div>
      <div class="flex items-center gap-3 text-[13px] mt-1.5"><span class="text-emerald-600">입금 {{ (w.total_deposited || 0).toLocaleString() }}</span><span class="text-red-500">출금 {{ (w.total_withdrawn || 0).toLocaleString() }}</span></div>
      <button @click="openAdjust(w)" class="mt-2.5 w-full min-h-[46px] rounded-xl bg-blue-50 text-blue-700 text-[15px] font-bold">칩 잔고 조정</button>
    </div>
  </template>

  <!-- 설정 -->
  <template v-else>
    <div class="bg-white border border-gray-100 rounded-2xl p-3.5 space-y-3">
      <div v-for="f in settingsFields" :key="f.key">
        <template v-if="f.type === 'number'">
          <label class="block text-[14px] font-bold text-ink mb-1" :for="'ps-' + f.key">{{ f.label }}</label>
          <input :id="'ps-' + f.key" v-model.number="settings[f.key]" type="number" inputmode="decimal" class="w-full min-h-[48px] rounded-xl border border-gray-200 px-3 text-right tabular-nums" />
        </template>
        <button v-else type="button" @click="settings[f.key] = !settings[f.key]" role="switch" :aria-checked="!!settings[f.key]" class="w-full min-h-[52px] rounded-xl border px-4 flex items-center justify-between text-[15px]" :class="settings[f.key] ? 'bg-green-50 border-green-200 text-green-700 font-bold' : 'bg-white border-gray-200 text-ink'"><span>{{ f.label }}</span><span>{{ settings[f.key] ? '켜짐' : '꺼짐' }}</span></button>
      </div>
      <button @click="askSave" :disabled="saving || !settingChanges.length" class="w-full min-h-[52px] rounded-xl bg-amber-500 text-white text-[16px] font-bold disabled:opacity-40">{{ settingChanges.length ? `설정 저장 (${settingChanges.length}개 변경)` : '바뀐 설정이 없어요' }}</button>
    </div>
  </template>

  <Teleport to="body">
    <!-- 새 토너먼트 -->
    <div v-if="showCreateTournament" class="alv-m fixed inset-0 z-[70] bg-black/45 flex items-end" @click.self="showCreateTournament = false">
      <div class="w-full bg-white rounded-t-3xl px-4 pt-2 max-h-[92vh] overflow-y-auto" role="dialog" aria-modal="true" aria-label="토너먼트 생성" :style="{ paddingBottom: 'calc(16px + env(safe-area-inset-bottom, 0px))' }">
        <div class="w-10 h-1 rounded bg-gray-200 mx-auto mb-3"></div>
        <div class="text-[17px] font-bold text-ink mb-3">토너먼트 생성</div>
        <div class="grid grid-cols-2 gap-1 mb-3 bg-gray-100 rounded-xl p-1" role="group" aria-label="생성 방식">
          <button @click="newTournament.is_schedule = false" :aria-pressed="!newTournament.is_schedule" class="min-h-[44px] rounded-lg text-[15px]" :class="!newTournament.is_schedule ? 'bg-white text-ink font-bold shadow-sm' : 'text-ink-muted'">일회성</button>
          <button @click="newTournament.is_schedule = true" :aria-pressed="newTournament.is_schedule" class="min-h-[44px] rounded-lg text-[15px]" :class="newTournament.is_schedule ? 'bg-white text-ink font-bold shadow-sm' : 'text-ink-muted'">반복 스케줄</button>
        </div>
        <div class="space-y-3">
          <div><label class="block text-[14px] font-bold text-ink mb-1" for="pt-title">제목</label><input id="pt-title" v-model="newTournament.title" placeholder="18:00 데일리 $500" class="w-full min-h-[48px] rounded-xl border border-gray-200 px-3" /></div>
          <div class="grid grid-cols-2 gap-2">
            <div><label class="block text-[14px] font-bold text-ink mb-1" for="pt-type">타입</label><select id="pt-type" v-model="newTournament.type" class="w-full min-h-[48px] rounded-xl border border-gray-200 px-2 bg-white"><option value="freeroll">프리롤</option><option value="micro">마이크로</option><option value="regular">레귤러</option><option value="high_roller">하이롤러</option></select></div>
            <div><label class="block text-[14px] font-bold text-ink mb-1" for="pt-buy">바이인 (칩)</label><input id="pt-buy" v-model.number="newTournament.buy_in" type="number" inputmode="numeric" class="w-full min-h-[48px] rounded-xl border border-gray-200 px-3" /></div>
            <div><label class="block text-[14px] font-bold text-ink mb-1" for="pt-chips">시작 칩</label><input id="pt-chips" v-model.number="newTournament.starting_chips" type="number" inputmode="numeric" class="w-full min-h-[48px] rounded-xl border border-gray-200 px-3" /></div>
            <div><label class="block text-[14px] font-bold text-ink mb-1" for="pt-max">최대 인원</label><input id="pt-max" v-model.number="newTournament.max_players" type="number" inputmode="numeric" class="w-full min-h-[48px] rounded-xl border border-gray-200 px-3" /></div>
          </div>
          <div v-if="!newTournament.is_schedule"><label class="block text-[14px] font-bold text-ink mb-1" for="pt-at">시작 시간</label><input id="pt-at" v-model="newTournament.scheduled_at" type="datetime-local" class="w-full min-h-[48px] rounded-xl border border-gray-200 px-3" /></div>
          <template v-else>
            <div><label class="block text-[14px] font-bold text-ink mb-1" for="pt-time">매일 시작 시간</label><input id="pt-time" v-model="newTournament.schedule_time" type="time" class="w-full min-h-[48px] rounded-xl border border-gray-200 px-3" /></div>
            <div><div class="text-[14px] font-bold text-ink mb-1">반복 요일</div>
              <div class="grid grid-cols-7 gap-1.5">
                <button v-for="d in dayOptions" :key="d.value" @click="toggleDay(d.value)" :aria-pressed="newTournament.schedule_days.includes(d.value)" class="min-h-[46px] rounded-xl text-[15px] font-bold" :class="newTournament.schedule_days.includes(d.value) ? 'bg-amber-500 text-white' : 'bg-gray-100 text-ink-muted'">{{ d.label }}</button>
              </div></div>
            <p class="text-[13px] text-blue-700 bg-blue-50 rounded-xl p-3">매일 자정에 다음 날 토너먼트가 자동 생성되고, 생성 즉시 참가 신청이 열려요.</p>
          </template>
        </div>
        <button @click="mCreate" :disabled="busy || !newTournament.title.trim()" class="mt-4 w-full min-h-[52px] rounded-xl bg-amber-500 text-white text-[16px] font-bold disabled:opacity-40">{{ busy ? '만드는 중...' : (newTournament.is_schedule ? '스케줄 등록' : '생성') }}</button>
        <button @click="showCreateTournament = false" :disabled="busy" class="mt-2 w-full min-h-[50px] rounded-xl bg-gray-100 text-ink text-[16px] font-bold">취소</button>
      </div>
    </div>
    <!-- 토너먼트 취소 확인 (참가자 환불) -->
    <div v-if="mCancel" class="alv-m fixed inset-0 z-[70] bg-black/45 flex items-end" @click.self="mCancel = null">
      <div class="w-full bg-white rounded-t-3xl px-4 pt-2" role="alertdialog" aria-modal="true" :style="{ paddingBottom: 'calc(16px + env(safe-area-inset-bottom, 0px))' }">
        <div class="w-10 h-1 rounded bg-gray-200 mx-auto mb-3"></div>
        <div class="text-[17px] font-bold text-ink mb-1">토너먼트를 취소할까요?</div>
        <p class="text-[15px] text-ink-light mb-1 break-words">{{ mCancel.title }}</p>
        <p class="text-[14px] text-red-600 mb-3">참가자 {{ mCancel.entries_count || 0 }}명 전원에게 바이인 {{ (mCancel.buy_in || 0).toLocaleString() }}칩이 환불돼요. 되돌릴 수 없어요.</p>
        <button @click="mDoCancel" :disabled="busy" class="w-full min-h-[52px] rounded-xl bg-red-500 text-white text-[16px] font-bold disabled:opacity-40">{{ busy ? '처리 중...' : '취소하고 환불하기' }}</button>
        <button @click="mCancel = null" :disabled="busy" class="mt-2 w-full min-h-[50px] rounded-xl bg-gray-100 text-ink text-[16px] font-bold">돌아가기</button>
      </div>
    </div>
    <!-- 칩 잔고 조정 -->
    <div v-if="adjustWallet && isMobile" class="alv-m fixed inset-0 z-[70] bg-black/45 flex items-end" @click.self="adjustWallet = null">
      <div class="w-full bg-white rounded-t-3xl px-4 pt-2" role="dialog" aria-modal="true" aria-label="칩 잔고 조정" :style="{ paddingBottom: 'calc(16px + env(safe-area-inset-bottom, 0px))' }">
        <div class="w-10 h-1 rounded bg-gray-200 mx-auto mb-3"></div>
        <div class="text-[17px] font-bold text-ink">칩 잔고 조정</div>
        <p class="text-[15px] text-ink-light mb-3 break-words">{{ adjustWallet.user?.name || '?' }} · 현재 <b class="text-amber-600 tabular-nums">{{ (adjustWallet.chips_balance || 0).toLocaleString() }}</b></p>
        <label class="block text-[14px] font-bold text-ink mb-1" for="pw-new">새 잔고</label>
        <input id="pw-new" v-model.number="adjustAmount" type="number" inputmode="numeric" min="0" class="w-full min-h-[48px] rounded-xl border border-gray-200 px-3 text-right tabular-nums" />
        <div class="mt-2 rounded-xl px-3 py-2.5 text-[15px] flex items-center justify-between" :class="adjustDiff >= 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-600'">
          <span>변경량</span><b class="tabular-nums">{{ adjustDiff >= 0 ? '+' : '' }}{{ adjustDiff.toLocaleString() }}</b>
        </div>
        <p class="text-[12px] text-ink-faint mt-1.5">변경은 “관리자 수동 조정”으로 거래 내역에 남아요.</p>
        <div v-if="adjustError" class="text-[14px] text-red-600 mt-2">{{ adjustError }}</div>
        <button @click="submitAdjust" :disabled="adjusting || adjustDiff === 0 || adjustAmount < 0 || adjustAmount === '' || adjustAmount === null" class="mt-3 w-full min-h-[52px] rounded-xl bg-amber-500 text-white text-[16px] font-bold disabled:opacity-40">{{ adjusting ? '처리 중...' : '잔고 변경하기' }}</button>
        <button @click="adjustWallet = null" :disabled="adjusting" class="mt-2 w-full min-h-[50px] rounded-xl bg-gray-100 text-ink text-[16px] font-bold">취소</button>
      </div>
    </div>
    <!-- 설정 저장 확인 -->
    <div v-if="mSaveAsk" class="alv-m fixed inset-0 z-[70] bg-black/45 flex items-end" @click.self="mSaveAsk = false">
      <div class="w-full bg-white rounded-t-3xl px-4 pt-2" role="dialog" aria-modal="true" :style="{ paddingBottom: 'calc(16px + env(safe-area-inset-bottom, 0px))' }">
        <div class="w-10 h-1 rounded bg-gray-200 mx-auto mb-3"></div>
        <div class="text-[17px] font-bold text-ink mb-2">포커 설정을 바꿀까요?</div>
        <div class="space-y-1.5 mb-3">
          <div v-for="c in settingChanges" :key="c.key" class="flex items-center justify-between gap-2 bg-gray-50 rounded-xl px-3 py-2 text-[14px]"><span class="text-ink-light">{{ c.label }}</span><span class="font-bold tabular-nums">{{ c.from }} → <span class="text-amber-600">{{ c.to }}</span></span></div>
        </div>
        <button @click="saveSettings" :disabled="saving" class="w-full min-h-[52px] rounded-xl bg-amber-500 text-white text-[16px] font-bold disabled:opacity-40">{{ saving ? '저장 중...' : '바꾸기' }}</button>
        <button @click="mSaveAsk = false" :disabled="saving" class="mt-2 w-full min-h-[50px] rounded-xl bg-gray-100 text-ink text-[16px] font-bold">취소</button>
      </div>
    </div>
    <div v-if="toast" class="alv-m fixed left-1/2 -translate-x-1/2 z-[80] max-w-[90vw] px-4 py-3 rounded-xl text-[15px] font-bold text-white shadow-lg" :class="toast.error ? 'bg-red-600' : 'bg-ink'" :style="{ top: 'calc(70px + env(safe-area-inset-top, 0px))' }" role="status">{{ toast.text }}</div>
  </Teleport>
</div>

<!-- ───────── PC 화면 ───────── -->
<div v-else>
  <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink mb-6">
    <span class="icon-chip w-9 h-9 bg-amber-50 text-amber-600"><AppIcon name="gamepad" :size="20" /></span>
    포커 관리
  </h1>

  <!-- Overview Cards -->
  <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 mb-6">
    <div v-for="s in overviewCards" :key="s.label" class="card p-4">
      <div class="text-ink-muted text-xs mb-1">{{ s.label }}</div>
      <div class="text-lg font-bold" :class="s.color">{{ s.value }}</div>
    </div>
  </div>

  <!-- Wallet Management -->
  <div class="card mb-6 overflow-hidden">
    <div class="px-4 py-3 border-b border-gray-50 flex justify-between items-center">
      <h2 class="flex items-center gap-2 font-bold text-sm text-ink">
        <span class="icon-chip w-7 h-7 bg-amber-50 text-amber-600"><AppIcon name="coins" :size="14" /></span>지갑 관리
      </h2>
      <div class="text-xs text-ink-muted">총 {{ wallets.length }}건</div>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="bg-gray-50 text-ink-muted text-xs">
            <th class="px-4 py-2 text-left">&#50976;&#51200;</th>
            <th class="px-4 py-2 text-left">&#51060;&#47700;&#51068;</th>
            <th class="px-4 py-2 text-right">&#52841; &#51092;&#44256;</th>
            <th class="px-4 py-2 text-right">&#52509; &#51077;&#44552;</th>
            <th class="px-4 py-2 text-right">&#52509; &#52636;&#44552;</th>
            <th class="px-4 py-2 text-center">&#51312;&#51221;</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="w in wallets" :key="w.id" class="border-t border-gray-50 hover:bg-amber-50/40 transition-colors">
            <td class="px-4 py-2 font-semibold text-ink">{{ w.user?.name || w.user?.nickname || '?' }}</td>
            <td class="px-4 py-2 text-ink-muted text-xs">{{ w.user?.email || '-' }}</td>
            <td class="px-4 py-2 text-right font-mono font-bold text-amber-600">{{ (w.chips_balance || 0).toLocaleString() }}</td>
            <td class="px-4 py-2 text-right font-mono text-emerald-600">{{ (w.total_deposited || 0).toLocaleString() }}</td>
            <td class="px-4 py-2 text-right font-mono text-red-500">{{ (w.total_withdrawn || 0).toLocaleString() }}</td>
            <td class="px-4 py-2 text-center">
              <button @click="openAdjust(w)" class="text-xs text-blue-600 hover:underline font-bold">&#51312;&#51221;</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <div v-if="wallets.length === 0" class="p-8 text-center text-ink-muted text-sm">지갑 데이터가 없습니다.</div>
  </div>

  <!-- Tournament Management -->
  <div class="card mb-6 overflow-hidden">
    <div class="px-4 py-3 border-b border-gray-50 flex justify-between items-center">
      <h2 class="flex items-center gap-2 font-bold text-sm text-ink">
        <span class="icon-chip w-7 h-7 bg-amber-50 text-amber-600"><AppIcon name="trophy" :size="14" /></span>토너먼트 관리
      </h2>
      <button @click="showCreateTournament = true" class="btn-primary !px-4 !py-2 !text-xs"><AppIcon name="plus" :size="13" /> 새 토너먼트</button>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead><tr class="bg-gray-50 text-ink-muted text-xs">
          <th class="px-4 py-2 text-left">제목</th>
          <th class="px-4 py-2 text-center">상태</th>
          <th class="px-4 py-2 text-right">바이인</th>
          <th class="px-4 py-2 text-right">참가자</th>
          <th class="px-4 py-2 text-center">시작 시간</th>
          <th class="px-4 py-2 text-center">관리</th>
        </tr></thead>
        <tbody>
          <tr v-for="t in tournaments" :key="t.id" class="border-t border-gray-50 hover:bg-amber-50/40 transition-colors">
            <td class="px-4 py-2 font-semibold text-ink">
              {{ t.title }}
              <span v-if="t.is_template" class="ml-1 inline-flex items-center gap-0.5 text-[11px] bg-blue-50 text-blue-600 px-1.5 py-0.5 rounded-full font-bold"><AppIcon name="refresh" :size="10" /> 반복</span>
            </td>
            <td class="px-4 py-2 text-center">
              <span :class="{'bg-blue-50 text-blue-700': t.status==='scheduled', 'bg-emerald-50 text-emerald-700': t.status==='registering', 'bg-amber-50 text-amber-700': t.status==='running', 'bg-gray-100 text-ink-light': t.status==='finished' || t.status==='cancelled'}" class="text-xs px-2 py-0.5 rounded-full font-bold">{{ t.status }}</span>
            </td>
            <td class="px-4 py-2 text-right font-mono">{{ (t.buy_in || 0).toLocaleString() }}</td>
            <td class="px-4 py-2 text-right font-mono">{{ t.entries_count || 0 }}/{{ t.max_players }}</td>
            <td class="px-4 py-2 text-center text-xs text-ink-muted">{{ formatNY(t.scheduled_at) }}</td>
            <td class="px-4 py-2 text-center">
              <button v-if="t.status !== 'running' && t.status !== 'finished'" @click="cancelTournament(t.id)" class="text-xs text-red-500 hover:underline">취소</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <div v-if="!tournaments.length" class="p-6 text-center text-ink-muted text-sm">토너먼트가 없습니다.</div>
  </div>

  <!-- Create Tournament Modal -->
  <div v-if="showCreateTournament && !isMobile" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="showCreateTournament=false">
    <div class="bg-white rounded-2xl p-6 w-[420px] shadow-xl max-h-[90vh] overflow-y-auto">
      <h3 class="flex items-center gap-2 font-bold text-ink mb-4">
        <span class="icon-chip w-7 h-7 bg-amber-50 text-amber-600"><AppIcon name="trophy" :size="14" /></span>토너먼트 생성
      </h3>

      <!-- 모드 선택 -->
      <div class="flex bg-gray-100 rounded-xl p-1 mb-4">
        <button @click="newTournament.is_schedule = false"
          :class="!newTournament.is_schedule ? 'bg-white text-ink shadow-sm' : 'text-ink-muted'"
          class="flex-1 py-2 rounded-lg text-sm font-semibold transition-colors">일회성</button>
        <button @click="newTournament.is_schedule = true"
          :class="newTournament.is_schedule ? 'bg-white text-ink shadow-sm' : 'text-ink-muted'"
          class="flex-1 py-2 rounded-lg text-sm font-semibold transition-colors inline-flex items-center justify-center gap-1"><AppIcon name="refresh" :size="13" /> 반복 스케줄</button>
      </div>

      <div class="space-y-3">
        <div><label class="input-label">제목</label><input v-model="newTournament.title" class="input-soft" placeholder="18:00 데일리 $500"></div>
        <div class="grid grid-cols-2 gap-3">
          <div><label class="input-label">타입</label><select v-model="newTournament.type" class="input-soft"><option value="freeroll">프리롤</option><option value="micro">마이크로</option><option value="regular">레귤러</option><option value="high_roller">하이롤러</option></select></div>
          <div><label class="input-label">바이인</label><input v-model.number="newTournament.buy_in" type="number" class="input-soft"></div>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div><label class="input-label">시작 칩</label><input v-model.number="newTournament.starting_chips" type="number" class="input-soft"></div>
          <div><label class="input-label">최대 인원</label><input v-model.number="newTournament.max_players" type="number" class="input-soft"></div>
        </div>

        <!-- 일회성: 날짜/시간 선택 -->
        <div v-if="!newTournament.is_schedule">
          <label class="input-label">시작 시간</label>
          <input v-model="newTournament.scheduled_at" type="datetime-local" class="input-soft">
        </div>

        <!-- 반복: 시간 + 요일 선택 -->
        <template v-else>
          <div><label class="input-label">매일 시작 시간</label><input v-model="newTournament.schedule_time" type="time" class="input-soft"></div>
          <div>
            <label class="input-label">반복 요일</label>
            <div class="flex gap-1 flex-wrap">
              <button v-for="d in dayOptions" :key="d.value" @click="toggleDay(d.value)"
                :class="newTournament.schedule_days.includes(d.value) ? 'bg-amber-400 text-white shadow-btn' : 'bg-gray-100 text-ink-muted hover:bg-gray-200'"
                class="px-3 py-1.5 rounded-lg text-xs font-bold transition-colors">{{ d.label }}</button>
            </div>
          </div>
          <div class="flex items-start gap-1.5 bg-blue-50 rounded-xl p-3 text-xs text-blue-700">
            <AppIcon name="info" :size="14" class="mt-0.5" /> 매일 자정에 다음 날 토너먼트가 자동 생성됩니다. 생성 즉시 참가 신청이 열립니다.
          </div>
        </template>
      </div>
      <div class="flex gap-2 mt-4">
        <button @click="createTournament" class="btn-primary flex-1 !py-2">{{ newTournament.is_schedule ? '스케줄 등록' : '생성' }}</button>
        <button @click="showCreateTournament=false" class="btn-secondary flex-1 !py-2">취소</button>
      </div>
    </div>
  </div>

  <!-- Settings -->
  <div class="card">
    <div class="px-4 py-3 border-b border-gray-50">
      <h2 class="flex items-center gap-2 font-bold text-sm text-ink">
        <span class="icon-chip w-7 h-7 bg-blue-50 text-blue-600"><AppIcon name="settings" :size="14" /></span>포커 설정
      </h2>
    </div>
    <div class="p-4 space-y-4 max-w-lg">
      <div v-for="f in settingsFields" :key="f.key" class="flex items-center justify-between">
        <label class="text-sm text-ink-light">{{ f.label }}</label>
        <input v-if="f.type === 'number'" v-model.number="settings[f.key]" type="number"
          class="input-soft w-32 !px-3 !py-1.5 text-right" />
        <label v-else class="relative inline-flex items-center cursor-pointer">
          <input v-model="settings[f.key]" type="checkbox" class="sr-only peer">
          <div class="w-9 h-5 bg-gray-200 peer-checked:bg-amber-500 rounded-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:after:translate-x-full"></div>
        </label>
      </div>
      <div class="pt-2">
        <button @click="saveSettings" :disabled="saving"
          class="btn-primary !px-6 !py-2">
          {{ saving ? '&#51200;&#51109; &#51473;...' : '&#49444;&#51221; &#51200;&#51109;' }}
        </button>
      </div>
    </div>
  </div>

  <!-- Adjust Modal -->
  <div v-if="adjustWallet && !isMobile" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="adjustWallet = null">
    <div class="bg-white rounded-2xl p-6 w-80 shadow-xl">
      <h3 class="font-bold text-ink mb-4">&#52841; &#51092;&#44256; &#51312;&#51221;</h3>
      <div class="text-sm text-ink-light mb-1">{{ adjustWallet.user?.name || '?' }}</div>
      <div class="text-xs text-ink-muted mb-3">&#54788;&#51116; &#51092;&#44256;: <span class="font-bold text-amber-600">{{ (adjustWallet.chips_balance || 0).toLocaleString() }}</span></div>
      <input v-model.number="adjustAmount" type="number"
        class="input-soft mb-2"
        placeholder="&#49352; &#51091;&#44256; &#51077;&#47141;" />
      <div class="text-xs text-ink-muted mb-4">
        &#52264;&#51060;: <span :class="adjustAmount - (adjustWallet.chips_balance || 0) >= 0 ? 'text-emerald-500' : 'text-red-500'" class="font-bold">
          {{ adjustAmount - (adjustWallet.chips_balance || 0) >= 0 ? '+' : '' }}{{ (adjustAmount - (adjustWallet.chips_balance || 0)).toLocaleString() }}
        </span>
      </div>
      <div class="flex gap-2">
        <button @click="submitAdjust" :disabled="adjusting"
          class="btn-primary flex-1 !py-2">
          {{ adjusting ? '&#52376;&#47532; &#51473;...' : '&#51200;&#51109;' }}
        </button>
        <button @click="adjustWallet = null" class="btn-secondary flex-1 !py-2">&#52712;&#49548;</button>
      </div>
      <div v-if="adjustError" class="text-xs text-red-500 mt-2 text-center">{{ adjustError }}</div>
    </div>
  </div>
</div>
</template>

<script setup>
import { ref, computed, watch, inject, onMounted, onBeforeUnmount } from 'vue'
import axios from 'axios'
import AppIcon from '../../components/AppIcon.vue'

// 관리자 휴대폰 화면이면 카드 + 아래에서 올라오는 시트 (AdminLayout 이 알려 줌)
const adminIsMobile = inject('adminIsMobile', ref(false))
const isMobile = computed(() => !!adminIsMobile.value)
const toast = ref(null); let toastTimer = null
function say(text, error = false) { toast.value = { text, error }; clearTimeout(toastTimer); toastTimer = setTimeout(() => { toast.value = null }, 3500) }
const mTab = ref('tour')
const busy = ref(false)
const mCancel = ref(null)
const mSaveAsk = ref(false)
const statusKo = { scheduled: '예정', registering: '접수 중', running: '진행 중', finished: '종료', cancelled: '취소됨' }
const overview = ref({})
const wallets = ref([])
const settings = ref({
  min_deposit: 1000,
  min_withdraw: 1000,
  withdraw_fee_pct: 5,
  max_buy_in: 10000,
  enabled: true,
})
const saving = ref(false)
const adjustWallet = ref(null)
const adjustAmount = ref(0)
const adjusting = ref(false)
const adjustError = ref('')
const tournaments = ref([])
const scheduleTemplates = ref([])
const showCreateTournament = ref(false)
const newTournament = ref({
  title: '', type: 'regular', buy_in: 500, starting_chips: 15000,
  max_players: 90, scheduled_at: '', is_schedule: false,
  schedule_time: '18:00', schedule_days: ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'],
})

const dayOptions = [
  { value: 'mon', label: '월' }, { value: 'tue', label: '화' },
  { value: 'wed', label: '수' }, { value: 'thu', label: '목' },
  { value: 'fri', label: '금' }, { value: 'sat', label: '토' },
  { value: 'sun', label: '일' },
]

function toggleDay(day) {
  const idx = newTournament.value.schedule_days.indexOf(day)
  if (idx >= 0) newTournament.value.schedule_days.splice(idx, 1)
  else newTournament.value.schedule_days.push(day)
}

function formatNY(dt) {
  if (!dt) return ''
  return new Date(dt).toLocaleString('ko-KR', { timeZone: 'America/New_York', year: 'numeric', month: 'numeric', day: 'numeric', hour: '2-digit', minute: '2-digit' })
}

const overviewCards = computed(() => [
  { label: '\uCD1D \uAC8C\uC784', value: (overview.value.total_games || 0).toLocaleString(), color: 'text-blue-600' },
  { label: '\uCD1D \uC9C0\uAC11', value: (overview.value.total_wallets || 0).toLocaleString(), color: 'text-ink' },
  { label: '\uCD1D \uC785\uAE08', value: (overview.value.total_deposited || 0).toLocaleString(), color: 'text-emerald-600' },
  { label: '\uCD1D \uCD9C\uAE08', value: (overview.value.total_withdrawn || 0).toLocaleString(), color: 'text-red-500' },
  { label: '\uC720\uD1B5 \uCE69', value: (overview.value.chips_in_circulation || 0).toLocaleString(), color: 'text-amber-600' },
  { label: '\uD65C\uC131 \uC720\uC800', value: (overview.value.active_players || 0).toLocaleString(), color: 'text-purple-600' },
])

const settingsFields = [
  { key: 'min_deposit', label: '\uCD5C\uC18C \uC785\uAE08', type: 'number' },
  { key: 'min_withdraw', label: '\uCD5C\uC18C \uCD9C\uAE08', type: 'number' },
  { key: 'withdraw_fee_pct', label: '\uCD9C\uAE08 \uC218\uC218\uB8CC (%)', type: 'number' },
  { key: 'max_buy_in', label: '\uCD5C\uB300 \uBC14\uC774\uC778', type: 'number' },
  { key: 'enabled', label: '\uD3EC\uCEE4 \uD65C\uC131\uD654', type: 'toggle' },
]

const adjustDiff = computed(() => (Number(adjustAmount.value) || 0) - (adjustWallet.value?.chips_balance || 0))
const settingsOrig = ref(null)
const settingChanges = computed(() => {
  if (!settingsOrig.value) return []
  return settingsFields.filter(f => settings.value[f.key] !== settingsOrig.value[f.key]).map(f => {
    const fmt = v => f.type === 'number' ? Number(v).toLocaleString() : (v ? '켜짐' : '꺼짐')
    return { key: f.key, label: f.label, from: fmt(settingsOrig.value[f.key]), to: fmt(settings.value[f.key]) }
  })
})
function askSave() { if (settingChanges.value.length) mSaveAsk.value = true }
async function mCreate() {
  if (busy.value) return
  busy.value = true
  try { await createTournament() } finally { busy.value = false }
}
async function mDoCancel() {
  if (busy.value || !mCancel.value) return
  busy.value = true
  try { await cancelTournament(mCancel.value.id, true); mCancel.value = null } finally { busy.value = false }
}
watch(() => isMobile.value && (showCreateTournament.value || !!adjustWallet.value || !!mCancel.value || mSaveAsk.value), locked => { document.body.style.overflow = locked ? 'hidden' : '' })
onBeforeUnmount(() => { document.body.style.overflow = ''; clearTimeout(toastTimer) })

onMounted(async () => {
  try {
    const [ov, wl, st, tn] = await Promise.all([
      axios.get('/api/admin/poker/overview'),
      axios.get('/api/admin/poker/wallets'),
      axios.get('/api/admin/poker/settings'),
      axios.get('/api/admin/poker/tournaments'),
    ])
    if (ov.data.success) overview.value = ov.data.data
    if (wl.data.success) wallets.value = wl.data.data?.data || wl.data.data || []
    if (st.data.success) Object.assign(settings.value, st.data.data)
    settingsOrig.value = { ...settings.value }
    if (tn.data.success) {
      const d = tn.data.data
      if (d?.templates !== undefined) {
        scheduleTemplates.value = d.templates || []
        tournaments.value = d.tournaments?.data || d.tournaments || []
      } else {
        tournaments.value = d?.data || d || []
      }
    }
  } catch (e) {
    console.error('Admin poker load failed', e)
  }
})

function openAdjust(w) {
  adjustWallet.value = w
  adjustAmount.value = w.chips_balance || 0
  adjustError.value = ''
}

async function submitAdjust() {
  adjusting.value = true
  adjustError.value = ''
  try {
    await axios.put(`/api/admin/poker/wallets/${adjustWallet.value.id}`, { chips_balance: adjustAmount.value })
    adjustWallet.value.chips_balance = adjustAmount.value
    adjustWallet.value = null
  } catch (e) {
    adjustError.value = e.response?.data?.message || e.message
  } finally {
    adjusting.value = false
  }
}

async function createTournament() {
  try {
    const payload = { ...newTournament.value }
    const { data } = await axios.post('/api/admin/poker/tournaments', payload)
    if (data.success) {
      // Refresh list
      const tn = await axios.get('/api/admin/poker/tournaments')
      if (tn.data.success) {
      const d = tn.data.data
      if (d?.templates !== undefined) {
        scheduleTemplates.value = d.templates || []
        tournaments.value = d.tournaments?.data || d.tournaments || []
      } else {
        tournaments.value = d?.data || d || []
      }
    }
      showCreateTournament.value = false
      newTournament.value = { title: '', type: 'regular', buy_in: 500, starting_chips: 15000, max_players: 90, scheduled_at: '', is_schedule: false, schedule_time: '18:00', schedule_days: ['mon','tue','wed','thu','fri','sat','sun'] }
      if (isMobile.value) say(data.message || '만들었어요'); else alert(data.message)
    }
  } catch (e) { if (isMobile.value) say(e.response?.data?.message || e.message, true); else alert(e.response?.data?.message || e.message) }
}

async function cancelTournament(id, confirmed = false) {
  if (confirmed !== true && !confirm('이 토너먼트를 취소하시겠습니까? 참가자 전원에게 환불됩니다.')) return
  try {
    await axios.delete(`/api/admin/poker/tournaments/${id}`)
    tournaments.value = tournaments.value.filter(t => t.id !== id)
    if (isMobile.value) say('취소하고 환불했어요')
  } catch (e) { if (isMobile.value) say(e.response?.data?.message || e.message, true); else alert(e.response?.data?.message || e.message) }
}

async function saveSettings() {
  saving.value = true
  try {
    await axios.put('/api/admin/poker/settings', settings.value)
    settingsOrig.value = { ...settings.value }
    if (isMobile.value) { mSaveAsk.value = false; say('설정을 저장했어요') }
    else alert('\uC124\uC815\uC774 \uC800\uC7A5\uB418\uC5C8\uC2B5\uB2C8\uB2E4.')
  } catch (e) {
    if (isMobile.value) say('저장하지 못했어요: ' + (e.response?.data?.message || e.message), true)
    else alert('\uC800\uC7A5 \uC2E4\uD328: ' + (e.response?.data?.message || e.message))
  } finally {
    saving.value = false
  }
}
</script>

<style>
/* 휴대폰 관리자: 입력창 글자가 16px 보다 작으면 iOS 가 화면을 확대해 버림 */
.alv-m input, .alv-m textarea, .alv-m select { font-size: 16px; }
</style>
