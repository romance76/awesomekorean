<template>
<div>
  <div class="mb-4">
    <div class="text-xs text-ink-muted">관리자 › 광고·매출 › 손님 메일</div>
    <h1 class="flex items-center gap-2.5 text-xl font-bold text-ink mt-1">
      <span class="icon-chip w-9 h-9 bg-amber-50 text-amber-600"><AppIcon name="mail" :size="18" /></span>
      손님 메일 (소개 메일)
    </h1>
    <p class="text-xs text-ink-faint mt-0.5">이메일 주소를 붙여 넣으면 {{ st ? st.from : 'noreply@awesomekorean.com' }} 로 어썸코리안 소개 메일을 보냅니다. 수신 거부한 주소는 영구 제외되고, 같은 주소는 한 번만 보냅니다.</p>
  </div>

  <div v-if="msg" class="mb-3 rounded-lg px-3 py-2 text-sm" :class="msgOk ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'">{{ msg }}</div>

  <!-- 잠금 상태 -->
  <div v-if="st" class="card p-4 mb-4 border" :class="st.live ? 'border-green-200 bg-green-50/40' : 'border-amber-200 bg-amber-50/50'">
    <div class="flex items-center gap-2 font-bold" :class="st.live ? 'text-green-700' : 'text-amber-800'">
      <AppIcon :name="st.live ? 'check-circle' : 'lock'" :size="16" />
      {{ st.live ? '실제 발송이 켜져 있어요 — 대기열이 자동으로 나가고 있어요' : '실제 발송은 잠겨 있어요' }}
    </div>
    <p v-if="st.lock_reason" class="text-sm text-ink-light mt-1">{{ st.lock_reason }}</p>
    <div class="flex flex-wrap items-center gap-2 mt-3">
      <button class="act-btn" :class="st.live_switch ? 'btn-secondary' : 'btn-primary'" :disabled="busy || (!st.postal_address && !st.live_switch)" @click="toggleLive">{{ st.live_switch ? '실제 발송 끄기' : '실제 발송 켜기' }}</button>
      <span v-if="!st.postal_address" class="text-xs text-amber-700">우편 주소가 없어요 — <RouterLink to="/admin/email-digest" class="underline font-bold">메일 발송</RouterLink> 화면에서 먼저 저장하세요 (두 메일이 같은 주소를 써요)</span>
      <span v-else class="text-xs text-ink-muted">메일 맨 아래 주소: {{ st.postal_address }}</span>
    </div>
  </div>

  <!-- 통계 -->
  <div v-if="st" class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
    <div class="card p-3"><div class="text-xs text-ink-muted">대기 중</div><div class="text-xl font-bold text-amber-600">{{ st.counts.queued }}</div></div>
    <div class="card p-3"><div class="text-xs text-ink-muted">보냄</div><div class="text-xl font-bold text-green-600">{{ st.counts.sent }}</div></div>
    <div class="card p-3"><div class="text-xs text-ink-muted">실패</div><div class="text-xl font-bold text-red-600">{{ st.counts.failed }}</div></div>
    <div class="card p-3"><div class="text-xs text-ink-muted">수신 거부(영구 제외)</div><div class="text-xl font-bold text-ink">{{ st.suppressed_total }}</div></div>
  </div>

  <!-- 하루 발송 개수 -->
  <div v-if="st" class="card p-4 mb-4 border border-amber-200">
    <h2 class="font-bold text-ink text-sm mb-1">📮 하루에 몇 통 보낼까요?</h2>
    <p class="text-xs text-ink-muted mb-3">하루 한도에 닿으면 그날은 멈추고, 다음 날(뉴욕 시각 자정 이후) 이어서 보내요. 새 도메인은 적게 시작해서 서서히 늘리는 게 스팸함에 안 가는 방법이에요.</p>
    <div class="flex flex-wrap items-center gap-2">
      <input v-model.number="cap" type="number" min="1" max="2000" class="input-soft w-28 text-center text-lg font-bold" />
      <span class="text-sm text-ink-muted">통 / 하루</span>
      <button v-for="n in [30, 50, 100, 200, 300]" :key="n" type="button" class="px-3 py-1.5 rounded-lg text-[13px] font-semibold border" :class="cap === n ? 'bg-amber-50 border-amber-300 text-amber-800' : 'border-gray-200 text-ink-muted'" @click="cap = n">{{ n }}</button>
      <button class="btn-primary act-btn" :disabled="busy || !cap || cap === st.daily_cap" @click="saveCap">저장</button>
    </div>
    <div class="mt-3 text-sm text-ink-light leading-relaxed">
      오늘 <b>{{ st.sent_today }}</b>통 보냄 · 오늘 남은 한도 <b>{{ Math.max(0, st.daily_cap - st.sent_today) }}</b>통
      <template v-if="st.counts.queued"> · 대기 {{ st.counts.queued }}통을 다 보내려면 약 <b>{{ Math.ceil(st.counts.queued / st.daily_cap) }}일</b></template>
    </div>
    <p class="text-[11px] text-ink-faint mt-1">참고: Resend 무료 요금제는 하루 100통까지예요. 그보다 많이 보내려면 요금제를 올려야 해요.</p>
  </div>

  <!-- 주소 붙여 넣기 -->
  <div class="card p-4 mb-4">
    <h2 class="font-bold text-ink text-sm mb-1">이메일 주소 붙여 넣기</h2>
    <p class="text-xs text-ink-muted mb-2">한 줄에 하나씩(쉼표·공백으로 구분해도 돼요). 중복·잘못된 주소·수신 거부한 주소·이미 보낸 주소는 자동으로 걸러져요.</p>
    <textarea v-model="raw" rows="6" class="input-soft w-full font-mono text-sm" placeholder="info@example.org&#10;owner@example.com"></textarea>
    <div class="flex flex-wrap items-center gap-3 mt-2">
      <button class="btn-primary act-btn" :disabled="busy || !raw.trim()" @click="addEmails">대기열에 넣기</button>
      <label class="text-xs text-ink-muted flex items-center gap-1.5"><input v-model="resend" type="checkbox" class="accent-amber-600" /> 이미 보낸 주소도 다시 보내기</label>
    </div>
    <div v-if="result" class="mt-3 text-sm rounded-lg bg-gray-50 p-3 leading-relaxed">
      <b>주소 {{ result.found }}개 확인</b> — 새로 넣음 <b class="text-green-700">{{ result.added }}</b>
      <template v-if="result.requeued"> · 다시 대기 {{ result.requeued }}</template>
      <template v-if="result.already_queued"> · 이미 대기 중 {{ result.already_queued }}</template>
      <template v-if="result.already_sent"> · 이미 보냄 {{ result.already_sent }}</template>
      <template v-if="result.suppressed"> · <span class="text-red-600">수신 거부라 제외 {{ result.suppressed }}</span></template>
      <template v-if="result.member_opted_out"> · 수신을 끈 회원이라 제외 {{ result.member_opted_out }}</template>
      <div v-if="result.invalid.length" class="text-xs text-red-600 mt-1">잘못된 주소 {{ result.invalid.length }}개: {{ result.invalid.join(', ') }}</div>
    </div>
  </div>

  <!-- 발송 기록 + 수신 거부 목록 (나란히) -->
  <div v-if="st" class="grid lg:grid-cols-2 gap-4 mb-4">
    <div class="card p-4 min-w-0">
      <div class="flex items-center justify-between mb-2">
        <h2 class="font-bold text-ink text-sm">대기열 · 발송 기록 <span class="text-ink-faint font-normal">({{ filteredRecent.length }}/{{ st.recent.length }})</span></h2>
        <button v-if="st.counts.queued" class="text-xs text-red-600 underline" :disabled="busy" @click="clearQueue">대기 중 모두 비우기</button>
      </div>
      <div class="flex flex-wrap items-center gap-1.5 mb-2">
        <button v-for="f in [['all', '전체'], ['queued', '대기'], ['sent', '보냄'], ['failed', '실패']]" :key="f[0]" type="button" class="px-2.5 py-1 rounded-lg text-[12px] font-semibold border" :class="recentFilter === f[0] ? 'bg-amber-50 border-amber-300 text-amber-800' : 'border-gray-200 text-ink-muted'" @click="recentFilter = f[0]">{{ f[1] }} {{ f[0] === 'all' ? st.recent.length : st.recent.filter(r => r.status === f[0]).length }}</button>
        <input v-model="recentQ" placeholder="주소 검색" class="input-soft text-xs w-32 ml-auto" />
      </div>
      <div v-if="!st.recent.length" class="text-sm text-ink-faint py-2">아직 넣은 주소가 없어요.</div>
      <div v-else class="overflow-auto max-h-[520px]">
        <table class="w-full text-sm">
          <thead class="sticky top-0 bg-white"><tr class="text-left text-xs text-ink-muted border-b"><th class="py-1.5 pr-3">주소</th><th class="pr-3">상태</th><th class="pr-3">보낸 시각</th><th></th></tr></thead>
          <tbody>
            <tr v-for="r in filteredRecent" :key="r.id" class="border-b border-gray-50">
              <td class="py-1.5 pr-3 break-all">{{ r.email }}</td>
              <td class="pr-3 whitespace-nowrap">
                <span class="text-xs rounded-full px-2 py-0.5" :class="{ 'bg-amber-50 text-amber-700': r.status === 'queued', 'bg-green-50 text-green-700': r.status === 'sent', 'bg-red-50 text-red-700': r.status === 'failed' }">{{ { queued: '대기', sent: '보냄', failed: '실패' }[r.status] }}</span>
                <span v-if="r.error" class="text-[11px] text-red-600 ml-1" :title="r.error">!</span>
              </td>
              <td class="pr-3 text-xs text-ink-muted whitespace-nowrap">{{ fmt(r.last_sent_at) }}</td>
              <td class="text-right"><button v-if="r.status === 'queued'" class="text-xs text-ink-muted underline" :disabled="busy" @click="removeOne(r.id)">빼기</button></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card p-4 min-w-0 border border-red-100">
      <div class="flex items-center justify-between mb-1">
        <h2 class="font-bold text-ink text-sm">🚫 수신 거부 목록 <span class="text-red-600">{{ st.suppressed_total }}</span></h2>
        <input v-model="supFilter" placeholder="주소 검색" class="input-soft text-xs w-32" />
      </div>
      <p class="text-xs text-ink-muted mb-2">여기 있는 주소는 다시 붙여 넣어도 자동으로 제외돼요. 전화·답장으로 거부한 주소는 아래에서 직접 추가하세요.</p>
      <div class="flex gap-2 mb-2">
        <input v-model="supRaw" placeholder="제외할 주소 (여러 개는 공백·쉼표로 구분)" class="input-soft flex-1 min-w-0 text-sm" />
        <button class="btn-secondary act-btn" :disabled="busy || !supRaw.trim()" @click="addSuppress">추가</button>
      </div>
      <div v-if="!filteredSup.length" class="text-sm text-ink-faint py-2">{{ st.suppressed.length ? '검색 결과가 없어요.' : '아직 수신 거부한 주소가 없어요.' }}</div>
      <div v-else class="overflow-auto max-h-[300px]">
        <table class="w-full text-sm">
          <thead class="sticky top-0 bg-white"><tr class="text-left text-xs text-ink-muted border-b"><th class="py-1.5 pr-3">주소</th><th class="pr-3">거부한 날</th><th>방법</th></tr></thead>
          <tbody>
            <tr v-for="s in filteredSup" :key="s.email" class="border-b border-gray-50">
              <td class="py-1.5 pr-3 break-all">{{ s.email }}</td>
              <td class="pr-3 text-xs text-ink-muted whitespace-nowrap">{{ fmt(s.created_at, true) }}</td>
              <td class="text-xs text-ink-muted whitespace-nowrap">{{ s.source === 'admin' ? '직접 추가' : '손님이 거부' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <p v-if="st.suppressed_total > st.suppressed.length" class="text-[11px] text-ink-faint mt-2">최근 {{ st.suppressed.length }}개만 보여요 (전체 {{ st.suppressed_total }}개는 모두 제외 중).</p>
    </div>
  </div>

  <!-- 메일 편집 + 실시간 미리보기 -->
  <div v-if="st" class="card p-4 mb-4">
    <h2 class="font-bold text-ink text-sm mb-1">메일 꾸미기</h2>
    <p class="text-xs text-ink-muted mb-3">아래 메일 화면에서 <b>점선이 보이는 글자를 직접 눌러 고치세요</b>(제목·설명·본문·카드·광고 안내·마무리 글 모두). 본문 글은 도구 막대로 굵게·색·목록·링크를 넣을 수 있어요. 제목 앞의 "(광고)", 맨 아래 수신 거부 버튼·우편 주소는 항상 자동으로 들어가요.</p>

    <div class="flex flex-wrap items-center gap-3 mb-3">
      <div class="flex gap-1 bg-gray-100 rounded-xl p-1 w-fit">
        <span class="text-[11px] text-ink-muted px-2 self-center">나가는 언어</span>
        <button v-for="l in langs" :key="l.key" type="button" class="px-3 py-1.5 rounded-lg text-[13px] font-semibold" :class="form.lang === l.key ? 'bg-white text-ink shadow-sm' : 'text-ink-muted'" @click="pickSendLang(l.key)">{{ l.label }}</button>
      </div>
      <div class="flex gap-1 bg-gray-100 rounded-xl p-1 w-fit">
        <span class="text-[11px] text-ink-muted px-2 self-center">고치는 글</span>
        <button v-for="l in [{key:'ko',label:'한국어'},{key:'en',label:'English'}]" :key="l.key" type="button" class="px-3 py-1.5 rounded-lg text-[13px] font-semibold" :class="editLang === l.key ? 'bg-white text-ink shadow-sm' : 'text-ink-muted'" @click="editLang = l.key">{{ l.label }}</button>
      </div>
    </div>

    <div class="mb-3"><label class="lbl">메일 제목 (받는 사람의 메일함에 보이는 제목)</label><input v-model="cur.subject" maxlength="150" class="input-soft w-full" /></div>

    <!-- 도구 막대 (본문 글에 적용) -->
    <div class="sticky top-0 z-10 flex flex-wrap items-center gap-1 px-2 py-1.5 border border-gray-200 rounded-t-xl bg-gray-50">
      <button type="button" class="tb" title="굵게" @mousedown.prevent="fexec('bold')"><b>B</b></button>
      <button type="button" class="tb" title="기울임" @mousedown.prevent="fexec('italic')"><i>I</i></button>
      <button type="button" class="tb" title="밑줄" @mousedown.prevent="fexec('underline')"><u>U</u></button>
      <span class="tb-sep"></span>
      <button type="button" class="tb font-bold" title="큰 제목" @mousedown.prevent="fexec('formatBlock', 'h2')">제목</button>
      <button type="button" class="tb font-bold text-[12px]" title="작은 제목" @mousedown.prevent="fexec('formatBlock', 'h3')">소제목</button>
      <button type="button" class="tb" title="일반 글" @mousedown.prevent="fexec('formatBlock', 'p')">본문</button>
      <button type="button" class="tb" title="강조 박스" @mousedown.prevent="fexec('formatBlock', 'blockquote')">❝</button>
      <span class="tb-sep"></span>
      <button v-for="c in colors" :key="c.v" type="button" class="tb-color" :style="{ background: c.v }" :title="c.t" @mousedown.prevent="fexec('foreColor', c.v)"></button>
      <span class="tb-sep"></span>
      <button type="button" class="tb" title="글머리표 목록" @mousedown.prevent="fexec('insertUnorderedList')">• 목록</button>
      <button type="button" class="tb" title="번호 목록" @mousedown.prevent="fexec('insertOrderedList')">1. 목록</button>
      <button type="button" class="tb" title="왼쪽 정렬" @mousedown.prevent="fexec('justifyLeft')">⇤</button>
      <button type="button" class="tb" title="가운데 정렬" @mousedown.prevent="fexec('justifyCenter')">↔</button>
      <span class="tb-sep"></span>
      <button type="button" class="tb" title="링크 넣기" @mousedown.prevent="addLink">🔗 링크</button>
      <button type="button" class="tb" title="서식 지우기" @mousedown.prevent="fexec('removeFormat')">지우기</button>
      <span class="ml-auto text-[11px] text-ink-faint">{{ previewBusy ? '갱신 중…' : '메일 화면 · 직접 고치기' }}</span>
    </div>
    <div class="border-x border-gray-200 bg-white px-3 py-2 text-sm">
      <div v-if="!selKey" class="text-[12px] text-ink-faint">💡 메일 화면의 글상자(제목·설명·카드 글 등)를 한 번 누르면 여기서 <b>정렬·위아래·좌우 간격·글자 크기</b>를 조절할 수 있어요. (간격은 0 이상만 — Gmail 은 음수 간격을 무시해서, 아이콘 옆에 제목을 놓는 건 "아이콘 위치" 옵션을 쓰세요) <button type="button" class="text-xs text-red-600 underline ml-2" @click="resetAllBoxes">모든 글상자 조절 초기화</button></div>
      <div v-else class="flex flex-wrap items-center gap-x-4 gap-y-1.5">
        <b class="text-ink">✏️ {{ keyLabel(selKey) }}</b>
        <span class="flex items-center gap-1">정렬
          <button v-for="a in [['left','⇤ 왼쪽'],['center','↔ 가운데'],['right','⇥ 오른쪽']]" :key="a[0]" type="button" class="tb" :class="selVals.align === a[0] ? '!bg-amber-50 !border-amber-300' : ''" @click="setAlign(a[0])">{{ a[1] }}</button>
        </span>
        <span v-for="s in [['mt','위 간격'],['mb','아래 간격'],['ml','왼쪽 간격'],['mr','오른쪽 간격'],['fs','글자 크기']]" :key="s[0]" class="flex items-center gap-1">
          {{ s[1] }}
          <button type="button" class="tb" @click="nudge(s[0], s[0] === 'fs' ? -1 : -4)">−</button>
          <b class="w-8 text-center">{{ selVals[s[0]] }}</b>
          <button type="button" class="tb" @click="nudge(s[0], s[0] === 'fs' ? 1 : 4)">+</button>
        </span>
        <button type="button" class="text-xs text-red-600 underline" @click="resetBox">이 글상자 되돌리기</button>
      </div>
    </div>
    <iframe ref="frame" :srcdoc="previewHtml" class="w-full h-[820px] rounded-b-xl border border-t-0 border-gray-200 bg-white" sandbox="allow-same-origin" @load="wireFrame"></iframe>

    <!-- 카드·안내 박스 관리 -->
    <div class="grid md:grid-cols-2 gap-4 mt-4">
      <div class="rounded-xl border border-gray-200 p-3">
        <div class="font-bold text-ink text-sm mb-2">기능 카드 관리 <span class="text-ink-faint font-normal">({{ cur.features.length }}/8)</span></div>
        <div class="flex items-center gap-2 mb-2 text-sm">
          <span class="text-ink-muted text-xs">한 줄에 카드</span>
          <button v-for="n in ['2', '3', '4']" :key="n" type="button" class="px-3 py-1 rounded-lg text-[13px] font-semibold border" :class="(cur.card_cols || '2') === n ? 'bg-amber-50 border-amber-300 text-amber-800' : 'border-gray-200 text-ink-muted'" @click="cur.card_cols = n">{{ n }}개</button>
          <span class="text-[11px] text-ink-faint">(3·4개면 카드가 작아져요)</span>
        </div>
        <div class="flex items-center gap-2 mb-2 text-sm">
          <span class="text-ink-muted text-xs">아이콘 위치</span>
          <button v-for="o in [['inline', '제목 옆'], ['top', '제목 위']]" :key="o[0]" type="button" class="px-3 py-1 rounded-lg text-[13px] font-semibold border" :class="(cur.icon_pos || 'inline') === o[0] ? 'bg-amber-50 border-amber-300 text-amber-800' : 'border-gray-200 text-ink-muted'" @click="cur.icon_pos = o[0]">{{ o[1] }}</button>
        </div>
        <div v-for="(f, i) in cur.features" :key="i" class="py-1.5 border-b border-gray-50 text-sm">
          <div class="flex items-center gap-2">
            <span class="w-6 text-center">{{ f.icon }}</span>
            <span class="flex-1 min-w-0 truncate">{{ f.title }}</span>
            <button type="button" class="text-xs text-ink-muted px-1" :disabled="i === 0" @click="move(i, -1)">▲</button>
            <button type="button" class="text-xs text-ink-muted px-1" :disabled="i === cur.features.length - 1" @click="move(i, 1)">▼</button>
            <button type="button" class="text-xs text-red-600 px-1" @click="removeCard(i)">삭제</button>
          </div>
          <div class="flex gap-1.5 mt-1">
            <input v-model="f.link" maxlength="200" placeholder="눌렀을 때 이동할 주소 예) /directory (비우면 링크 없음)" class="input-soft flex-1 min-w-0 text-xs" />
            <input v-model.number="f.h" type="number" min="0" max="600" step="10" placeholder="높이 자동" title="카드 높이(px) — 비우면 글 길이에 맞춰요" class="input-soft w-24 text-xs" />
          </div>
        </div>
        <button v-if="cur.features.length < 8" type="button" class="btn-secondary act-btn mt-2" @click="cur.features.push({ icon: '✨', title: '새 카드', desc: '설명을 입력하세요', link: '' })">+ 카드 추가</button>
        <p class="text-[11px] text-ink-faint mt-2">카드 높이는 위 메일 화면에서 <b>카드 오른쪽 아래 모서리를 끌어서</b> 조절하거나, 위 칸에 숫자(px)를 넣으세요(가로 폭은 "한 줄에 카드 N개"로 정해져요). 카드 글자는 위 메일 화면에서 눌러 고치세요. 주소는 <code>/directory</code> 처럼 사이트 안 주소나 https:// 주소를 쓸 수 있어요. 카드를 모두 지우면 기본 카드가 다시 나와요.</p>
        <div class="mt-3 pt-3 border-t border-gray-100">
          <div class="font-bold text-ink text-sm mb-1">버튼 링크</div>
          <label class="lbl">큰 버튼 ("{{ cur.button }}") — 비우면 홈페이지</label>
          <input v-model="cur.button_link" maxlength="200" placeholder="예) /directory" class="input-soft w-full text-sm mb-2" />
          <label class="lbl">광고 안내 박스 버튼 — 비우면 광고 신청 페이지</label>
          <input v-model="cur.promo_link" maxlength="200" placeholder="/ad-apply" class="input-soft w-full text-sm" />
        </div>
      </div>
      <div class="rounded-xl border border-gray-200 p-3">
        <div class="font-bold text-ink text-sm mb-2">광고 안내 박스 (노란 점선 박스)</div>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" class="accent-amber-600" :checked="cur.promo_show !== '0'" @change="cur.promo_show = $event.target.checked ? '1' : '0'" /> 메일에 "광고 무료 · 미리 자리 잡기" 박스 넣기</label>
        <p class="text-[11px] text-ink-faint mt-2 leading-relaxed">박스 안 문구는 위 메일 화면에서 직접 고쳐요. 버튼은 광고 신청 페이지(/ad-apply)로 연결돼요. 나중에 유료로 바뀔 때 부담이 없도록, 기본 문구는 "오픈 기념 · 기간 한정"으로 쓰고 맨 아래에 "기간·조건이 달라지거나 종료될 수 있다"는 안내를 넣어 두었어요.</p>
      </div>
    </div>

    <div class="flex flex-wrap gap-2 pt-4">
      <button class="btn-primary act-btn" :disabled="busy" @click="saveContent">저장</button>
      <button class="btn-secondary act-btn" :disabled="busy" @click="resetText">이 언어 기본 문구로 되돌리기</button>
    </div>
  </div>

  <!-- 시험 발송 -->
  <div class="card p-4 mb-8">
    <h2 class="font-bold text-ink text-sm mb-1">시험 발송</h2>
    <p class="text-xs text-ink-muted mb-2">내 주소로 먼저 한 통 보내서 실제 메일 앱에서의 모양과 수신 거부 화면을 확인하세요 (잠겨 있어도 가능, 대기열·하루 한도에 포함되지 않아요). <b>저장한 문구</b>로 나가니 먼저 저장하세요.</p>
    <div class="flex gap-2 flex-wrap">
      <input v-model="testEmail" type="email" placeholder="내 이메일 주소" class="input-soft act-input flex-1 min-w-[220px]" />
      <button class="btn-primary act-btn" :disabled="busy || !testEmail" @click="sendTest">시험 메일 보내기</button>
    </div>
  </div>
</div>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import axios from 'axios'
import AppIcon from '../../components/AppIcon.vue'

const st = ref(null)
const busy = ref(false)
const msg = ref(''); const msgOk = ref(true)
const raw = ref(''); const resend = ref(false); const result = ref(null)
const testEmail = ref(''); const supRaw = ref(''); const supFilter = ref('')
const cap = ref(100)
const editLang = ref('ko')
const previewHtml = ref(''); const previewSubject = ref(''); const previewBusy = ref(false)
const empty = () => ({ subject: '', hero_title: '', hero_sub: '', body: '', features_title: '', features: [], button: '', closing: '', card_cols: '2', button_link: '', promo_link: '', promo_show: '1', promo_badge: '', promo_title: '', promo_text: '', promo_button: '', promo_note: '', icon_pos: 'inline', styles: {} })
const form = reactive({ lang: 'ko', texts: { ko: empty(), en: empty() } })
const langs = [{ key: 'ko', label: '한국어' }, { key: 'en', label: 'English' }, { key: 'both', label: '두 언어 모두' }]
const cur = computed(() => form.texts[editLang.value])
const recentFilter = ref('all'); const recentQ = ref('')
const filteredRecent = computed(() => {
  const q = recentQ.value.trim().toLowerCase()
  return (st.value?.recent || []).filter(r => (recentFilter.value === 'all' || r.status === recentFilter.value) && (!q || r.email.includes(q)))
})
const filteredSup = computed(() => {
  const q = supFilter.value.trim().toLowerCase()
  return (st.value?.suppressed || []).filter(s => !q || s.email.includes(q))
})

function say(t, ok = true) { msg.value = t; msgOk.value = ok; setTimeout(() => { if (msg.value === t) msg.value = '' }, 6000) }
function fmt(v, dateOnly = false) { if (!v) return '-'; const s = String(v).replace('T', ' '); return dateOnly ? s.slice(0, 10) : s.slice(0, 16) }
const clone = (o) => JSON.parse(JSON.stringify(o))

function fill(d, keepEdit = false) {
  st.value = d
  cap.value = d.daily_cap
  if (!keepEdit) {
    form.lang = d.lang
    for (const l of ['ko', 'en']) { form.texts[l] = clone(d.texts[l]); if (!form.texts[l].styles || Array.isArray(form.texts[l].styles)) form.texts[l].styles = {} }
  }
}
async function run(fn, okMsg) {
  busy.value = true
  try { await fn(); if (okMsg) say(okMsg) }
  catch (e) { say(e?.response?.data?.message || '처리하지 못했어요.', false) }
  finally { busy.value = false }
}
async function load() { await run(async () => { const { data } = await axios.get('/api/admin/email-outreach'); fill(data.data); refreshPreview() }) }

const addEmails = () => run(async () => {
  const { data } = await axios.post('/api/admin/email-outreach/add', { emails: raw.value, resend: resend.value })
  result.value = data.result; fill(data.data, true); raw.value = ''
})
const saveCap = () => run(async () => {
  const { data } = await axios.put('/api/admin/email-outreach/settings', { daily_cap: cap.value }); fill(data.data, true)
}, '하루 발송 개수를 저장했어요.')
const saveContent = () => run(async () => {
  const { data } = await axios.put('/api/admin/email-outreach/settings', { lang: form.lang, texts: form.texts }); fill(data.data, true)
}, '메일 내용을 저장했어요.')
function pickSendLang(k) { form.lang = k; if (k !== 'both') editLang.value = k; axios.put('/api/admin/email-outreach/settings', { lang: k }).catch(() => {}) }
const toggleLive = () => run(async () => {
  const turnOn = !st.value.live_switch
  if (turnOn && !window.confirm('실제 발송을 켜면 대기열의 주소로 바로 메일이 나가기 시작해요 (하루 한도 ' + st.value.daily_cap + '통). 시험 발송으로 모양을 확인하셨나요?')) return
  const { data } = await axios.put('/api/admin/email-outreach/live', { live: turnOn }); fill(data.data, true)
  say(turnOn ? '실제 발송을 켰어요.' : '실제 발송을 껐어요.')
})
const clearQueue = () => run(async () => {
  if (!window.confirm('대기 중인 주소를 모두 비울까요? (이미 보낸 기록은 그대로예요)')) return
  const { data } = await axios.post('/api/admin/email-outreach/clear-queue'); fill(data.data, true)
}, '비웠어요.')
const removeOne = (id) => run(async () => { const { data } = await axios.post('/api/admin/email-outreach/remove/' + id); fill(data.data, true) })
const addSuppress = () => run(async () => {
  const { data } = await axios.post('/api/admin/email-outreach/suppress', { emails: supRaw.value }); fill(data.data, true); supRaw.value = ''
}, '제외 목록에 추가했어요.')
const sendTest = () => run(async () => {
  const { data } = await axios.post('/api/admin/email-outreach/test', { email: testEmail.value, lang: form.lang }); say(data.message)
})
function resetText() {
  if (!st.value || !window.confirm('이 언어의 문구를 기본 문구로 되돌릴까요? (저장 버튼을 눌러야 확정돼요)')) return
  form.texts[editLang.value] = clone(st.value.defaults[editLang.value])
}
// 카드 순서·삭제에 맞춰 카드별 글상자 조절값(f.번호.title/desc)도 같이 옮긴다
function remapStyles(fn) {
  const st = cur.value.styles || {}; const out = {}
  for (const [k, v] of Object.entries(st)) {
    const m = k.match(/^f\.(\d+)\.(.+)$/)
    if (!m) { out[k] = v; continue }
    const ni = fn(+m[1]); if (ni !== null) out['f.' + ni + '.' + m[2]] = v
  }
  cur.value.styles = out
}
function move(i, d) {
  const a = cur.value.features; const j = i + d
  if (j < 0 || j >= a.length) return
  ;[a[i], a[j]] = [a[j], a[i]]
  remapStyles(n => (n === i ? j : n === j ? i : n))
}
function removeCard(i) {
  cur.value.features.splice(i, 1)
  remapStyles(n => (n === i ? null : n > i ? n - 1 : n))
}

// ── 글상자 위치·간격·정렬·글자 크기 조절 ──
// 메일 앱에서 확실히 먹는 방식(정렬·margin·글자 크기)만 쓴다. 눌러 선택하면 위 조절 막대가 열리고, 바꾸는 즉시 화면에 반영·저장 대기.
const STYLABLE = /^(hero_title|hero_sub|features_title|closing|promo_title|promo_text|promo_note|f\.\d+\.(title|desc))$/
const KEY_NAMES = { hero_title: '큰 제목', hero_sub: '큰 제목 아래 설명', features_title: '카드 영역 제목', closing: '마무리 글', promo_title: '광고 안내 제목', promo_text: '광고 안내 설명', promo_note: '광고 안내 작은 글' }
const selKey = ref('')
const selVals = reactive({ align: '', mt: 0, mb: 0, ml: 0, mr: 0, fs: 14 })
let selEl = null
function keyLabel(k) {
  const m = k.match(/^f\.(\d+)\.(title|desc)$/)
  return m ? '카드 ' + (+m[1] + 1) + (m[2] === 'title' ? ' 제목' : ' 설명') : (KEY_NAMES[k] || k)
}
function selectBox(node) {
  const key = node.dataset.ek
  if (!STYLABLE.test(key)) { selKey.value = ''; selEl = null; return }
  selEl = node; selKey.value = key
  const cs = node.ownerDocument.defaultView.getComputedStyle(node)
  const saved = (cur.value.styles && cur.value.styles[key]) || {}
  const al = cs.textAlign === 'start' ? 'left' : cs.textAlign
  selVals.align = saved.align || (['left', 'center', 'right'].includes(al) ? al : 'left')
  selVals.mt = saved.mt ?? (parseInt(cs.marginTop) || 0)
  selVals.mb = saved.mb ?? (parseInt(cs.marginBottom) || 0)
  selVals.ml = saved.ml ?? (parseInt(cs.marginLeft) || 0)
  selVals.mr = saved.mr ?? (parseInt(cs.marginRight) || 0)
  selVals.fs = saved.fs ?? Math.round(parseFloat(cs.fontSize) || 14)
}
const CSS_PROP = { mt: 'marginTop', mb: 'marginBottom', ml: 'marginLeft', mr: 'marginRight', fs: 'fontSize' }
const RANGE = { mt: [0, 120], mb: [0, 120], ml: [0, 200], mr: [0, 200], fs: [9, 60] }
function saveStyle(prop, value) {   // 바꾼 항목만 저장한다(안 건드린 항목은 기본 모양 그대로)
  if (!cur.value.styles || Array.isArray(cur.value.styles)) cur.value.styles = {}
  cur.value.styles[selKey.value] = { ...(cur.value.styles[selKey.value] || {}), [prop]: value }
  renderedSig = sig()   // 이미 화면에 반영했으므로 다시 그리지 않는다
}
function nudge(prop, d) {
  const [lo, hi] = RANGE[prop]
  const v = Math.max(lo, Math.min(hi, (+selVals[prop] || 0) + d))
  selVals[prop] = v
  if (selEl) selEl.style[CSS_PROP[prop]] = v + 'px'
  saveStyle(prop, v)
}
function setAlign(a) {
  selVals.align = a
  if (selEl) selEl.style.textAlign = a
  saveStyle('align', a)
}
function resetAllBoxes() {
  if (!window.confirm('이 언어의 모든 글상자 위치·간격·정렬·크기 조절을 기본 모양으로 되돌릴까요?')) return
  cur.value.styles = {}; selKey.value = ''; selEl = null
  clearTimeout(timer); refreshPreview()
}
function resetBox() {
  if (cur.value.styles) delete cur.value.styles[selKey.value]
  selKey.value = ''; selEl = null
  clearTimeout(timer); refreshPreview()   // 다시 그려서 기본 모양으로
}

// ── 메일 화면 안에서 바로 고치기 ──
// 미리보기를 서버가 "편집 모드"(data-ek 표시)로 그려 주고, iframe 안의 글자를 눌러 고치면 그 값이 바로 form 에 들어간다.
const frame = ref(null)
const colors = [
  { v: '#111111', t: '검정' }, { v: '#FC226B', t: '핑크(브랜드)' }, { v: '#666666', t: '회색' },
  { v: '#2563eb', t: '파랑' }, { v: '#16a34a', t: '초록' }, { v: '#d97706', t: '주황' },
]
const sig = () => JSON.stringify([editLang.value, cur.value])
let renderedSig = ''   // 지금 화면에 그려진 내용의 지문 — iframe 안에서 고친 변경은 다시 그리지 않아 커서가 튀지 않는다

function setByKey(key, val) {
  const m = key.match(/^f\.(\d+)\.(icon|title|desc)$/)
  if (m) { const f = cur.value.features[+m[1]]; if (f) f[m[2]] = val }
  else cur.value[key] = val
}
function onFrameInput(node) {
  const key = node.dataset.ek
  let val = key === 'body' ? node.innerHTML : node.innerText.replace(/ /g, ' ').replace(/\n+$/, '')
  if (key === 'body' && val === '<br>') val = ''
  setByKey(key, val)
  renderedSig = sig()
}
function wireFrame() {
  const doc = frame.value?.contentDocument
  if (!doc) return
  doc.querySelectorAll('a').forEach(a => a.addEventListener('click', e => e.preventDefault()))
  // 카드 오른쪽 아래 모서리를 끌어 높이를 늘이고 줄인다 — 놓는 순간의 높이를 저장
  doc.addEventListener('mouseup', () => {
    doc.querySelectorAll('[data-card]').forEach(el => {
      if (!el.style.height) return
      const f = cur.value.features[+el.dataset.card]
      if (f) { f.h = Math.round(el.offsetHeight); renderedSig = sig() }
      el.style.minHeight = el.offsetHeight + 'px'; el.style.height = ''
    })
  })
  doc.querySelectorAll('[data-ek]').forEach(node => {
    node.contentEditable = 'true'
    node.spellcheck = false
    node.addEventListener('input', () => onFrameInput(node))
    node.addEventListener('focus', () => selectBox(node))
    node.addEventListener('paste', e => {   // 붙여 넣을 때는 서식 없이 글자만
      e.preventDefault()
      const t = (e.clipboardData || window.clipboardData).getData('text/plain')
      doc.execCommand('insertText', false, node.dataset.single ? t.replace(/\s+/g, ' ') : t)
    })
    if (node.dataset.single) node.addEventListener('keydown', e => { if (e.key === 'Enter') e.preventDefault() })
  })
}
function fexec(cmd, arg = null) {
  const doc = frame.value?.contentDocument
  if (!doc) return
  frame.value.contentWindow.focus()
  try { doc.execCommand('styleWithCSS', false, true) } catch { /* 일부 브라우저는 지원하지 않음 */ }
  doc.execCommand(cmd, false, arg)
  const n = doc.activeElement?.closest?.('[data-ek]')
  if (n) onFrameInput(n)
}
function addLink() {
  const url = window.prompt('연결할 주소(https:// 로 시작)를 입력하세요')
  if (!url) return
  if (!/^(https?:\/\/|mailto:)/i.test(url)) { window.alert('https:// 또는 http:// 로 시작하는 주소만 넣을 수 있어요.'); return }
  fexec('createLink', url)
}

// 실시간 미리보기: 고치는 글 기준(두 언어 모두 선택 시에도 지금 고치는 언어 하나만 보여 준다)
let timer = null
async function refreshPreview() {
  previewBusy.value = true
  renderedSig = sig()
  try {
    const { data } = await axios.post('/api/admin/email-outreach/preview', { lang: editLang.value, edit: true, texts: { [editLang.value]: form.texts[editLang.value] } })
    previewHtml.value = data.html; previewSubject.value = data.subject
  } catch { /* 미리보기는 실패해도 편집을 막지 않는다 */ }
  finally { previewBusy.value = false }
}
watch(sig, (s) => { if (s === renderedSig) return; clearTimeout(timer); timer = setTimeout(refreshPreview, 400) })

onMounted(load)
</script>

<style scoped>
.tb { min-width: 30px; height: 30px; padding: 0 8px; border-radius: 8px; font-size: 13px; color: #374151; background: #fff; border: 1px solid #e5e7eb; }
.tb:hover { background: #fff7e6; border-color: #fcd9a0; }
.tb-sep { width: 1px; height: 20px; background: #e5e7eb; margin: 0 4px; }
.tb-color { width: 20px; height: 20px; border-radius: 999px; border: 2px solid #fff; box-shadow: 0 0 0 1px #d1d5db; }
.lbl { display: block; font-size: 12px; color: #6b7280; margin-bottom: 4px; }
</style>
