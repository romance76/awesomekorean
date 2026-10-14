<template>
<!-- 머니 가이드 블록 렌더러 — 글자만 쭉 쓰던 가이드를 히어로·요약·도표·그래프·계산기·링크·다운로드로 보여 준다.
     모든 글자는 {{ }} (이스케이프) 로 출력 — 관리자가 넣은 값이 코드로 실행될 일 없음. 링크는 http(s) 와 /downloads 만 허용. -->
<div class="space-y-5 gb">
  <template v-for="(b, i) in blocks" :key="i">

    <!-- 히어로 -->
    <section v-if="b.type === 'hero'" class="relative overflow-hidden rounded-3xl p-6 md:p-9 text-white" :style="{ background: T.hero }">
      <svg class="absolute inset-0 w-full h-full opacity-[.16]" viewBox="0 0 600 260" preserveAspectRatio="xMaxYMid slice" aria-hidden="true">
        <circle cx="520" cy="40" r="130" fill="#fff"/><circle cx="430" cy="220" r="70" fill="#fff"/>
        <g fill="#fff"><rect x="330" y="150" width="26" height="90" rx="6"/><rect x="368" y="120" width="26" height="120" rx="6"/><rect x="406" y="86" width="26" height="154" rx="6"/></g>
      </svg>
      <div class="relative flex items-start gap-4">
        <div class="hidden sm:flex w-16 h-16 rounded-2xl bg-white/20 backdrop-blur items-center justify-center shrink-0"><AppIcon :name="b.icon || 'sparkles'" :size="32" /></div>
        <div class="min-w-0">
          <div class="text-[12px] font-extrabold tracking-[2px] opacity-90">{{ b.kicker }}</div>
          <h2 class="text-[26px] md:text-[34px] font-black leading-tight mt-1.5">{{ b.title }}</h2>
          <p v-if="b.lead" class="mt-2.5 text-[15px] md:text-[16.5px] opacity-95 leading-relaxed max-w-2xl" v-html="rich(b.lead)"></p>
        </div>
      </div>
      <div v-if="b.meta?.length" class="relative mt-6 grid grid-cols-2 md:grid-cols-4 gap-2.5">
        <div v-for="(m, k) in b.meta" :key="k" class="rounded-2xl bg-white/15 backdrop-blur px-3.5 py-2.5">
          <div class="flex items-center gap-1.5 text-[11.5px] font-bold opacity-85"><AppIcon :name="m.icon || 'info'" :size="13" />{{ m.label }}</div>
          <div class="text-[15px] font-black mt-0.5">{{ m.value }}</div>
        </div>
      </div>
    </section>

    <!-- 한눈에 요약 -->
    <section v-else-if="b.type === 'tldr'" class="rounded-3xl border-2 p-5 md:p-6" :class="T.soft">
      <div class="flex items-center gap-2 font-black text-ink text-[17px]"><span class="w-8 h-8 rounded-xl flex items-center justify-center text-white" :class="T.solid"><AppIcon name="sparkles" :size="17" /></span>{{ b.title || '한눈에 보기' }}</div>
      <ul class="mt-3 grid md:grid-cols-3 gap-3">
        <li v-for="(it, k) in b.items" :key="k" class="rounded-2xl bg-white p-4 shadow-sm">
          <div class="text-[26px] font-black leading-none" :class="T.text">{{ k + 1 }}</div>
          <p class="mt-2 text-[14.5px] text-ink leading-relaxed" v-html="rich(it)"></p>
        </li>
      </ul>
    </section>

    <!-- 소제목 -->
    <header v-else-if="b.type === 'h'" class="pt-3">
      <h3 class="text-[22px] md:text-[24px] font-black text-ink flex items-center gap-2.5"><span class="w-1.5 h-7 rounded-full" :class="T.solid"></span>{{ b.title }}</h3>
      <p v-if="b.sub" class="text-[14px] text-ink-muted mt-1 ml-4">{{ b.sub }}</p>
    </header>

    <!-- 글 -->
    <section v-else-if="b.type === 'text'" class="card p-5 md:p-6 space-y-3 text-[15.5px] text-ink leading-[1.85]">
      <p v-for="(p, k) in b.body" :key="k" v-html="rich(p)"></p>
    </section>

    <!-- 큰 숫자 -->
    <section v-else-if="b.type === 'stats'" class="grid gap-3" :class="b.items.length > 2 ? 'grid-cols-2 md:grid-cols-4' : 'grid-cols-2'">
      <div v-for="(s, k) in b.items" :key="k" class="card p-4 md:p-5 text-center gb-stat">
        <AppIcon v-if="s.icon" :name="s.icon" :size="24" class="mx-auto mb-2" :class="T.text" />
        <div class="gb-stat-v font-black" :class="T.text" :style="{ '--em': statEm(s.value) }">{{ s.value }}</div>
        <div class="mt-2 text-[13.5px] font-extrabold text-ink break-keep">{{ s.label }}</div>
        <div v-if="s.note" class="text-[12px] text-ink-muted mt-0.5 break-keep">{{ s.note }}</div>
      </div>
    </section>

    <!-- 순서(타임라인) -->
    <section v-else-if="b.type === 'steps'" class="card p-5 md:p-6">
      <h4 v-if="b.title" class="font-black text-ink text-[17px] mb-4">{{ b.title }}</h4>
      <ol class="relative">
        <li v-for="(s, k) in b.items" :key="k" class="relative pl-14 pb-6 last:pb-0">
          <span v-if="k < b.items.length - 1" class="absolute left-[19px] top-10 bottom-0 w-0.5" :class="T.line"></span>
          <span class="absolute left-0 top-0 w-10 h-10 rounded-full flex items-center justify-center text-white font-black text-[16px] shadow" :class="T.solid">{{ k + 1 }}</span>
          <div class="font-extrabold text-ink text-[16.5px]" v-html="rich(s.title)"></div>
          <p class="text-[14.5px] text-ink-light leading-relaxed mt-1" v-html="rich(s.body)"></p>
          <div v-if="s.btn" class="mt-2 inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-[13.5px] font-black" :class="[T.soft, T.text]"><AppIcon :name="s.btn.icon || 'check'" :size="16" />누를 곳: {{ s.btn.label }}</div>
          <a v-if="safe(s.link?.url)" :href="s.link.url" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 mt-2 text-[13px] font-bold underline" :class="T.text">{{ s.link.label }} <AppIcon name="external-link" :size="13" /></a>
        </li>
      </ol>
    </section>

    <!-- 비교표 -->
    <section v-else-if="b.type === 'compare'" class="card p-4 md:p-6">
      <h4 v-if="b.title" class="font-black text-ink text-[17px] mb-3">{{ b.title }}</h4>
      <!-- 휴대폰: 옆으로 밀지 않고 항목별 카드로 (첫 칸이 제목, 나머지는 "열 이름 + 내용") -->
      <div class="md:hidden space-y-3">
        <div v-for="(r, ri) in b.rows" :key="ri" class="rounded-2xl border border-gray-100 overflow-hidden">
          <div class="px-4 py-2.5 font-black text-ink text-[15.5px] bg-gray-50" v-html="rich(r[0])"></div>
          <dl class="divide-y divide-gray-50">
            <div v-for="(c, k) in r.slice(1)" :key="k" class="px-4 py-2.5" :class="k + 1 === b.highlight ? T.soft2 : ''">
              <dt class="text-[12.5px] font-extrabold" :class="k + 1 === b.highlight ? T.text : 'text-ink-muted'">{{ b.columns[k + 1] }}</dt>
              <dd class="text-[15px] text-ink leading-relaxed mt-0.5" v-html="rich(c)"></dd>
            </div>
          </dl>
        </div>
      </div>
      <div class="hidden md:block overflow-x-auto -mx-1 px-1">
        <table class="w-full text-[14.5px] border-separate border-spacing-0 min-w-[480px]">
          <thead><tr>
            <th v-for="(c, k) in b.columns" :key="k" class="text-left px-3 py-2.5 font-black text-[13px] first:rounded-tl-xl last:rounded-tr-xl" :class="k === b.highlight ? [T.solid, 'text-white'] : 'bg-gray-100 text-ink'">{{ c }}</th>
          </tr></thead>
          <tbody>
            <tr v-for="(r, ri) in b.rows" :key="ri">
              <td v-for="(c, k) in r" :key="k" class="px-3 py-2.5 border-b border-gray-100 align-top" :class="[k === 0 ? 'font-extrabold text-ink' : 'text-ink-light', k === b.highlight ? T.soft2 : '']" v-html="rich(c)"></td>
            </tr>
          </tbody>
        </table>
      </div>
      <p v-if="b.note" class="text-[12px] text-ink-muted mt-2">{{ b.note }}</p>
    </section>

    <!-- 막대 그래프 -->
    <section v-else-if="b.type === 'bars'" class="card p-5 md:p-6">
      <h4 v-if="b.title" class="font-black text-ink text-[17px]">{{ b.title }}</h4>
      <p v-if="b.sub" class="text-[13px] text-ink-muted mt-0.5">{{ b.sub }}</p>
      <div class="mt-4 space-y-3">
        <div v-for="(it, k) in b.items" :key="k">
          <div class="flex justify-between text-[13.5px] mb-1"><span class="font-bold text-ink">{{ it.label }}</span><span class="font-black" :class="it.highlight ? T.text : 'text-ink-light'">{{ it.display || it.value + (b.unit || '') }}</span></div>
          <div class="h-3.5 rounded-full bg-gray-100 overflow-hidden"><div class="h-full rounded-full transition-all" :class="it.highlight ? T.solid : 'bg-gray-300'" :style="{ width: pct(it.value, b) + '%' }"></div></div>
          <div v-if="it.note" class="text-[12px] text-ink-muted mt-0.5">{{ it.note }}</div>
        </div>
      </div>
      <p v-if="b.source" class="text-[11.5px] text-ink-muted mt-3">{{ b.source }}</p>
    </section>

    <!-- 장단점 -->
    <section v-else-if="b.type === 'proscons'" class="grid md:grid-cols-2 gap-3">
      <div class="rounded-3xl p-5 bg-emerald-50 border border-emerald-100"><div class="font-black text-emerald-800 flex items-center gap-1.5"><AppIcon name="thumbs-up" :size="17" />{{ b.prosTitle || '좋은 점' }}</div>
        <ul class="mt-2.5 space-y-2"><li v-for="(p, k) in b.pros" :key="k" class="flex gap-2 text-[14.5px] text-ink"><AppIcon name="check" :size="16" class="text-emerald-600 mt-1" /><span v-html="rich(p)"></span></li></ul></div>
      <div class="rounded-3xl p-5 bg-rose-50 border border-rose-100"><div class="font-black text-rose-800 flex items-center gap-1.5"><AppIcon name="alert-circle" :size="17" />{{ b.consTitle || '조심할 점' }}</div>
        <ul class="mt-2.5 space-y-2"><li v-for="(p, k) in b.cons" :key="k" class="flex gap-2 text-[14.5px] text-ink"><AppIcon name="minus" :size="16" class="text-rose-500 mt-1" /><span v-html="rich(p)"></span></li></ul></div>
    </section>

    <!-- 알림 상자 -->
    <section v-else-if="b.type === 'callout'" class="rounded-2xl border-l-4 p-4 md:p-5 flex gap-3" :class="CALL[b.tone || 'tip'].box">
      <AppIcon :name="CALL[b.tone || 'tip'].icon" :size="22" class="shrink-0 mt-0.5" :class="CALL[b.tone || 'tip'].ico" />
      <div><div class="font-black text-ink">{{ b.title }}</div><p class="text-[14.5px] text-ink-light leading-relaxed mt-0.5" v-html="rich(b.body)"></p></div>
    </section>

    <!-- 체크리스트 (눌러서 체크 · 인쇄) -->
    <section v-else-if="b.type === 'checklist'" class="card p-5 md:p-6">
      <div class="flex items-center justify-between gap-2 flex-wrap">
        <h4 class="font-black text-ink text-[17px]">{{ b.title || '체크리스트' }}</h4>
        <div class="flex items-center gap-2 text-[12.5px]"><span class="font-bold" :class="T.text">{{ doneCount(i) }} / {{ b.items.length }}</span>
          <button type="button" @click="printPage" class="px-2.5 py-1 rounded-lg bg-gray-100 text-ink-light font-bold hover:bg-gray-200">인쇄</button></div>
      </div>
      <div class="h-2 rounded-full bg-gray-100 mt-3 overflow-hidden"><div class="h-full rounded-full transition-all" :class="T.solid" :style="{ width: (doneCount(i) / b.items.length * 100) + '%' }"></div></div>
      <ul class="mt-3 space-y-1.5">
        <li v-for="(it, k) in b.items" :key="k">
          <label class="flex items-start gap-3 rounded-xl px-3 py-2.5 cursor-pointer hover:bg-gray-50" :class="checks[i + ':' + k] ? 'bg-gray-50' : ''">
            <input type="checkbox" v-model="checks[i + ':' + k]" class="mt-1 w-[18px] h-[18px] accent-emerald-600" />
            <span class="text-[15px] leading-relaxed" :class="checks[i + ':' + k] ? 'line-through text-ink-muted' : 'text-ink'" v-html="rich(it)"></span>
          </label>
        </li>
      </ul>
    </section>

    <!-- 바로가기 링크 -->
    <section v-else-if="b.type === 'links'">
      <h4 v-if="b.title" class="font-black text-ink text-[17px] mb-3 flex items-center gap-2"><AppIcon name="external-link" :size="17" :class="T.text" />{{ b.title }}</h4>
      <div class="grid sm:grid-cols-2 gap-3">
        <template v-for="(l, k) in b.items" :key="k">
          <a v-if="safe(l.url)" :href="l.url" target="_blank" rel="noopener noreferrer" class="card p-4 flex gap-3 items-start hover:shadow-md hover:-translate-y-0.5 transition">
            <span class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0" :class="[T.soft, T.text]"><AppIcon name="globe" :size="19" /></span>
            <span class="min-w-0"><span class="flex items-center gap-1.5 flex-wrap"><b class="text-ink text-[14.5px]">{{ l.title }}</b><i v-if="l.tag" class="not-italic text-[10.5px] font-black px-1.5 py-0.5 rounded-md bg-gray-100 text-ink-light">{{ l.tag }}</i></span>
              <span class="block text-[13px] text-ink-muted mt-0.5 leading-snug">{{ l.desc }}</span>
              <span class="block text-[11.5px] mt-1 truncate" :class="T.text">{{ host(l.url) }}</span></span>
          </a>
        </template>
      </div>
    </section>

    <!-- 내려받기 -->
    <section v-else-if="b.type === 'downloads'" class="rounded-3xl p-5 md:p-6 bg-gray-50 border border-gray-100">
      <h4 class="font-black text-ink text-[17px] mb-3 flex items-center gap-2"><AppIcon name="download" :size="18" :class="T.text" />{{ b.title || '자료 내려받기' }}</h4>
      <div class="grid sm:grid-cols-2 gap-3">
        <template v-for="(d, k) in b.items" :key="k">
          <a v-if="safe(d.file)" :href="d.file" download class="rounded-2xl bg-white border border-gray-100 p-4 flex gap-3 items-center hover:shadow-md transition">
            <span class="w-11 h-11 rounded-xl flex items-center justify-center text-white shrink-0" :class="T.solid"><AppIcon name="download" :size="20" /></span>
            <span class="min-w-0"><b class="block text-ink text-[14.5px]">{{ d.title }}</b><span class="block text-[12.5px] text-ink-muted">{{ d.desc }}</span><span class="block text-[11px] font-black mt-0.5" :class="T.text">{{ d.kind || '엑셀' }}<template v-if="(d.kind || '엑셀') === '엑셀'"> · 엑셀·구글 시트·넘버스에서 열려요 · 노란 칸에 내 숫자를 넣으세요</template><template v-else> · 인쇄하거나 복사해서 쓰세요</template></span></span>
          </a>
        </template>
      </div>
    </section>

    <!-- 자주 묻는 질문 -->
    <section v-else-if="b.type === 'faq'" class="card p-3 md:p-5">
      <h4 class="font-black text-ink text-[17px] px-2 pt-2 pb-1">{{ b.title || '자주 묻는 질문' }}</h4>
      <details v-for="(f, k) in b.items" :key="k" class="group border-b border-gray-100 last:border-0">
        <summary class="flex items-center justify-between gap-3 cursor-pointer list-none px-2 py-3.5 font-extrabold text-ink text-[15px]">{{ f.q }}<AppIcon name="chevron-down" :size="18" class="shrink-0 text-ink-muted transition group-open:rotate-180" /></summary>
        <p class="px-2 pb-4 text-[14.5px] text-ink-light leading-relaxed" v-html="rich(f.a)"></p>
      </details>
    </section>

    <!-- 계산기: 주택 월 납입금 + PMI -->
    <section v-else-if="b.type === 'calc' && b.kind === 'mortgage'" class="card p-5 md:p-6">
      <h4 class="font-black text-ink text-[17px] flex items-center gap-2"><AppIcon name="chart-bar" :size="18" :class="T.text" />{{ b.title || '월 납입금 계산기' }}</h4>
      <p class="text-[13px] text-ink-muted mt-0.5">숫자를 바꿔 보세요. 결과는 예시이고 실제 대출 조건과 다를 수 있어요.</p>
      <div class="grid md:grid-cols-2 gap-5 mt-4">
        <div class="space-y-3">
          <label v-for="f in MORT_FIELDS" :key="f.k" class="block">
            <span class="flex justify-between text-[13px] font-bold text-ink"><span>{{ f.l }}</span><span :class="T.text">{{ f.fmt(m[f.k]) }}</span></span>
            <input type="range" v-model.number="m[f.k]" :min="f.min" :max="f.max" :step="f.step" class="w-full accent-emerald-600" />
          </label>
        </div>
        <div>
          <div class="rounded-2xl p-4 text-white" :style="{ background: T.hero }">
            <div class="text-[12px] font-bold opacity-90">한 달에 내는 돈 (대출 원리금 + PMI)</div>
            <div class="text-[34px] font-black leading-tight">{{ usd(mort.total) }}</div>
          </div>
          <div class="mt-3 h-4 rounded-full overflow-hidden flex bg-gray-100">
            <div :class="T.solid" :style="{ width: mort.pPct + '%' }"></div><div class="bg-sky-400" :style="{ width: mort.iPct + '%' }"></div><div class="bg-amber-400" :style="{ width: mort.mPct + '%' }"></div>
          </div>
          <ul class="mt-2.5 space-y-1 text-[13.5px]">
            <li class="flex justify-between"><span class="flex items-center gap-1.5"><i class="w-2.5 h-2.5 rounded-full" :class="T.solid"></i>원금 상환 (첫 달)</span><b>{{ usd(mort.princ) }}</b></li>
            <li class="flex justify-between"><span class="flex items-center gap-1.5"><i class="w-2.5 h-2.5 rounded-full bg-sky-400"></i>이자 (첫 달)</span><b>{{ usd(mort.int) }}</b></li>
            <li class="flex justify-between"><span class="flex items-center gap-1.5"><i class="w-2.5 h-2.5 rounded-full bg-amber-400"></i>PMI</span><b>{{ mort.pmi ? usd(mort.pmi) : '필요 없음 (20% 이상 선납)' }}</b></li>
          </ul>
          <div v-if="mort.pmi" class="mt-3 rounded-xl bg-amber-50 border border-amber-100 p-3 text-[13.5px] text-ink">
            대출 잔액이 집값의 <b>80%</b> 아래로 내려가는 시점 = 약 <b>{{ mort.m80 }}개월({{ (mort.m80 / 12).toFixed(1) }}년)</b> 뒤. 이때부터 PMI 해지를 <b>요청</b>할 수 있어요. 요청 없이도 <b>78%</b>(약 {{ mort.m78 }}개월 뒤)에는 자동으로 끝나요.
            <div class="text-[12px] text-ink-muted mt-1">PMI 총액(80% 도달까지) 약 {{ usd(mort.pmi * mort.m80) }}</div>
          </div>
        </div>
      </div>
    </section>

    <!-- 계산기: 복리(예금·투자 불어나는 모습) -->
    <section v-else-if="b.type === 'calc' && b.kind === 'compound'" class="card p-5 md:p-6">
      <h4 class="font-black text-ink text-[17px] flex items-center gap-2"><AppIcon name="trending-up" :size="18" :class="T.text" />{{ b.title || '복리 계산기' }}</h4>
      <p class="text-[13px] text-ink-muted mt-0.5">연 이율이 일정하다고 가정한 예시예요. 실제 수익은 달라지고, 투자는 원금을 잃을 수 있어요.</p>
      <div class="grid md:grid-cols-2 gap-5 mt-4">
        <div class="space-y-3">
          <label v-for="f in COMP_FIELDS" :key="f.k" class="block">
            <span class="flex justify-between text-[13px] font-bold text-ink"><span>{{ f.l }}</span><span :class="T.text">{{ f.fmt(c[f.k]) }}</span></span>
            <input type="range" v-model.number="c[f.k]" :min="f.min" :max="f.max" :step="f.step" class="w-full accent-emerald-600" />
          </label>
        </div>
        <div>
          <div class="rounded-2xl p-4 text-white" :style="{ background: T.hero }"><div class="text-[12px] font-bold opacity-90">{{ c.years }}년 뒤 예상 금액</div><div class="text-[34px] font-black leading-tight">{{ usd(comp.fv) }}</div></div>
          <div class="mt-3 h-4 rounded-full overflow-hidden flex bg-gray-100"><div class="bg-gray-400" :style="{ width: comp.contribPct + '%' }"></div><div :class="T.solid" :style="{ width: (100 - comp.contribPct) + '%' }"></div></div>
          <ul class="mt-2.5 space-y-1 text-[13.5px]">
            <li class="flex justify-between"><span class="flex items-center gap-1.5"><i class="w-2.5 h-2.5 rounded-full bg-gray-400"></i>내가 넣은 돈</span><b>{{ usd(comp.contrib) }}</b></li>
            <li class="flex justify-between"><span class="flex items-center gap-1.5"><i class="w-2.5 h-2.5 rounded-full" :class="T.solid"></i>이자·수익(가정)</span><b>{{ usd(comp.gain) }}</b></li>
          </ul>
        </div>
      </div>
    </section>

    <!-- 계산기: 카드 연회비 손익분기 -->
    <section v-else-if="b.type === 'calc' && b.kind === 'cardfee'" class="card p-5 md:p-6">
      <h4 class="font-black text-ink text-[17px] flex items-center gap-2"><AppIcon name="coins" :size="18" :class="T.text" />{{ b.title || '연회비 카드, 본전 뽑을까?' }}</h4>
      <p class="text-[13px] text-ink-muted mt-0.5">연회비 카드의 혜택이, 연회비 없는 2% 현금 환급 카드보다 큰지 비교해요.</p>
      <div class="grid md:grid-cols-2 gap-5 mt-4">
        <div class="space-y-3">
          <label v-for="f in FEE_FIELDS" :key="f.k" class="block">
            <span class="flex justify-between text-[13px] font-bold text-ink"><span>{{ f.l }}</span><span :class="T.text">{{ f.fmt(fee[f.k]) }}</span></span>
            <input type="range" v-model.number="fee[f.k]" :min="f.min" :max="f.max" :step="f.step" class="w-full accent-emerald-600" />
          </label>
        </div>
        <div>
          <div class="rounded-2xl p-4 text-white" :style="{ background: feeCalc.net >= 0 ? T.hero : 'linear-gradient(135deg,#be123c,#f43f5e)' }">
            <div class="text-[12px] font-bold opacity-90">연회비 카드 vs 연회비 없는 2% 카드</div>
            <div class="text-[34px] font-black leading-tight">{{ feeCalc.net >= 0 ? '+' : '-' }}{{ usd(Math.abs(feeCalc.net)) }} / 년</div>
            <div class="text-[13px] font-bold opacity-95">{{ feeCalc.net >= 0 ? '이 조건이면 연회비 카드가 더 이득이에요' : '이 조건이면 2% 카드가 더 이득이에요' }}</div>
          </div>
          <ul class="mt-3 space-y-1 text-[13.5px]">
            <li class="flex justify-between"><span>연회비 카드: 포인트·환급 가치</span><b>{{ usd(feeCalc.reward) }}</b></li>
            <li class="flex justify-between"><span>+ 내가 실제로 쓰는 혜택(크레딧)</span><b>{{ usd(fee.credits) }}</b></li>
            <li class="flex justify-between"><span>− 연회비</span><b>-{{ usd(fee.fee) }}</b></li>
            <li class="flex justify-between border-t border-gray-100 pt-1"><span>= 연회비 카드 순이익</span><b>{{ usd(feeCalc.total) }}</b></li>
            <li class="flex justify-between"><span>2% 카드 환급액</span><b>{{ usd(feeCalc.flat) }}</b></li>
          </ul>
        </div>
      </div>
    </section>

    <!-- 계산기: 50/30/20 가계부 -->
    <section v-else-if="b.type === 'calc' && b.kind === 'budget'" class="card p-5 md:p-6">
      <h4 class="font-black text-ink text-[17px] flex items-center gap-2"><AppIcon name="wallet" :size="18" :class="T.text" />{{ b.title || '50/30/20 가계부 계산기' }}</h4>
      <p class="text-[13px] text-ink-muted mt-0.5">세후 월 수입을 넣으면 필수·선택·저축 비율을 나눠 줘요. 정해진 정답이 아니라 출발점이에요.</p>
      <label class="block mt-4"><span class="flex justify-between text-[13px] font-bold text-ink"><span>세후 월 수입</span><span :class="T.text">{{ usd(bud.income) }}</span></span>
        <input type="range" v-model.number="bud.income" min="1500" max="20000" step="100" class="w-full accent-emerald-600" /></label>
      <div class="mt-3 h-5 rounded-full overflow-hidden flex bg-gray-100"><div class="bg-sky-500" style="width:50%"></div><div class="bg-amber-400" style="width:30%"></div><div :class="T.solid" style="width:20%"></div></div>
      <div class="grid grid-cols-3 gap-2 mt-3 text-center">
        <div class="rounded-2xl bg-sky-50 p-3"><div class="text-[12px] font-bold text-sky-700">필수 50%</div><div class="text-[20px] font-black text-ink">{{ usd(bud.income * 0.5) }}</div><div class="text-[11.5px] text-ink-muted">월세·모기지, 식비, 보험, 교통</div></div>
        <div class="rounded-2xl bg-amber-50 p-3"><div class="text-[12px] font-bold text-amber-700">선택 30%</div><div class="text-[20px] font-black text-ink">{{ usd(bud.income * 0.3) }}</div><div class="text-[11.5px] text-ink-muted">외식, 여가, 구독</div></div>
        <div class="rounded-2xl p-3" :class="T.soft"><div class="text-[12px] font-bold" :class="T.text">저축·빚 20%</div><div class="text-[20px] font-black text-ink">{{ usd(bud.income * 0.2) }}</div><div class="text-[11.5px] text-ink-muted">비상금, 투자, 빚 갚기</div></div>
      </div>
    </section>

    <!-- 휴대폰 화면 위치 그림: 어디를 누르는지 (실제 앱 화면이 아닌 위치 설명용 그림) -->
    <section v-else-if="b.type === 'phone'" class="card p-4 md:p-5">
      <div class="flex flex-col sm:flex-row gap-4 items-center sm:items-start">
        <div class="shrink-0 relative" style="width:176px">
          <svg viewBox="0 0 200 380" class="w-full h-auto" role="img" :aria-label="b.label || '누를 곳'">
            <rect x="6" y="6" width="188" height="368" rx="30" fill="#fff" stroke="#9ca3af" stroke-width="4"/>
            <rect x="78" y="14" width="44" height="8" rx="4" fill="#d1d5db"/>
            <rect x="18" y="28" width="164" height="326" rx="14" fill="#f9fafb"/>
            <!-- 윗줄: 시간·배터리 -->
            <text x="28" y="43" font-size="8" font-weight="700" fill="#6b7280">9:41</text>
            <rect x="152" y="36" width="20" height="9" rx="2.5" fill="none" stroke="#6b7280" stroke-width="1.2"/><rect x="154" y="38" width="13" height="5" rx="1.2" fill="#6b7280"/>
            <!-- 앱 머리글 -->
            <rect x="18" y="50" width="164" height="30" fill="#065f46"/>
            <path d="M30 65 l6 -6 m-6 6 l6 6" stroke="#fff" stroke-width="2.2" fill="none" stroke-linecap="round"/>
            <text x="46" y="69" font-size="10.5" font-weight="800" fill="#fff">{{ b.headline || '앱 화면' }}</text>
            <!-- 큰 배너 -->
            <rect x="26" y="88" width="148" height="46" rx="10" fill="#d1fae5"/>
            <rect x="36" y="98" width="70" height="8" rx="4" fill="#10b981"/><rect x="36" y="112" width="104" height="6" rx="3" fill="#a7f3d0"/><rect x="36" y="122" width="60" height="6" rx="3" fill="#a7f3d0"/>
            <!-- 목록 -->
            <g v-for="r in [144, 178, 212]" :key="r">
              <rect x="26" :y="r" width="148" height="28" rx="9" fill="#fff" stroke="#e5e7eb"/>
              <circle cx="42" :cy="r + 14" r="8" fill="#e5e7eb"/>
              <rect x="56" :y="r + 7" width="74" height="6" rx="3" fill="#d1d5db"/><rect x="56" :y="r + 17" width="46" height="5" rx="2.5" fill="#e5e7eb"/>
              <path :d="'M158 ' + (r + 10) + ' l5 4 l-5 4'" stroke="#9ca3af" stroke-width="1.8" fill="none" stroke-linecap="round"/>
            </g>
            <!-- 아래 탭 -->
            <rect x="18" y="304" width="164" height="50" fill="#fff"/><line x1="18" y1="304" x2="182" y2="304" stroke="#e5e7eb"/>
            <g fill="#d1d5db"><circle cx="46" cy="322" r="8"/><circle cx="90" cy="322" r="8"/><circle cx="134" cy="322" r="8"/><circle cx="170" cy="322" r="8"/></g>
            <g fill="#e5e7eb"><rect x="36" y="336" width="20" height="4" rx="2"/><rect x="80" y="336" width="20" height="4" rx="2"/><rect x="124" y="336" width="20" height="4" rx="2"/><rect x="160" y="336" width="20" height="4" rx="2"/></g>
            <!-- 누를 곳: 진짜 버튼처럼 이름을 크게 써서 보여 줌 -->
            <g v-if="pt(b).rect">
              <rect :x="pt(b).x - 2" :y="pt(b).y - 2" :width="pt(b).w + 4" :height="pt(b).h + 4" rx="11" fill="#fff" stroke="#f59e0b" stroke-width="3.5" stroke-dasharray="7 4"/>
              <text v-for="(ln, li) in lines(b.label)" :key="li" :x="pt(b).x + pt(b).w / 2" :y="pt(b).y + pt(b).h / 2 + 4 + (li - (lines(b.label).length - 1) / 2) * 12" text-anchor="middle" font-size="10.5" font-weight="800" fill="#92400e">{{ ln }}</text>
            </g>
            <g v-else>
              <rect :x="btnX(b)" :y="pt(b).y - 8 - lines(b.label).length * 7" width="112" :height="lines(b.label).length * 14 + 14" rx="14" fill="#f59e0b" stroke="#fff" stroke-width="2"><animate attributeName="opacity" values="1;.8;1" dur="1.6s" repeatCount="indefinite"/></rect>
              <text v-for="(ln, li) in lines(b.label)" :key="li" :x="btnX(b) + 56" :y="pt(b).y + 4 + (li - (lines(b.label).length - 1) / 2) * 13" text-anchor="middle" font-size="10.5" font-weight="800" fill="#fff">{{ ln }}</text>
              <path :d="'M' + (btnX(b) + 78) + ' ' + (pt(b).y + 8 + lines(b.label).length * 7) + ' l8 -6 m-8 6 l2 -10'" stroke="#374151" stroke-width="3" fill="none" stroke-linecap="round"/>
            </g>
          </svg>
        </div>
        <div class="min-w-0 text-center sm:text-left">
          <div class="text-[12px] font-extrabold tracking-[1px]" :class="T.text">{{ b.kicker || '이 화면에서' }}</div>
          <div class="text-[20px] font-black text-ink mt-0.5">{{ b.title }}</div>
          <div v-if="b.label" class="mt-2 inline-flex items-center gap-2 rounded-full border px-3 py-2 text-[15px] font-black" :class="[T.soft, T.text]"><AppIcon :name="b.icon || 'check'" :size="20" />{{ b.label }}</div>
          <p class="text-[15px] text-ink-light leading-relaxed mt-2" v-html="rich(b.caption)"></p>
          <p class="text-[11.5px] text-ink-muted mt-2">※ 그림은 누를 위치를 설명하는 예시예요. 앱이 업데이트되면 실제 화면과 조금 다를 수 있어요.</p>
        </div>
      </div>
    </section>

    <!-- 실제 화면 캡처 + 번호 표시: pins=[{x,y,text}] (x,y 는 사진 안 위치 %) -->
    <section v-else-if="b.type === 'shot'" class="card p-3 md:p-4">
      <div class="text-[12px] font-extrabold tracking-[1px]" :class="T.text">{{ b.kicker || '실제 화면' }}</div>
      <div class="text-[18px] font-black text-ink mt-0.5 mb-2">{{ b.title }}</div>
      <div class="relative rounded-xl overflow-hidden border border-gray-200 bg-gray-50 mx-auto cursor-zoom-in" :style="b.narrow ? 'max-width:300px' : ''" @click="zoom = b" role="button" tabindex="0" @keydown.enter="zoom = b" :aria-label="b.title + ' 크게 보기'">
        <img :src="b.src" :alt="b.title" class="w-full h-auto block" loading="lazy" />
        <span v-for="(p, pi) in (b.pins || [])" :key="pi" class="absolute -translate-x-1/2 -translate-y-1/2 flex items-center justify-center w-8 h-8 rounded-full bg-red-600 text-white text-[16px] font-black shadow-lg ring-4 ring-white/80" :style="{ left: p.x + '%', top: p.y + '%' }">{{ pi + 1 }}</span>
      </div>
      <button type="button" @click="zoom = b" class="mt-2 inline-flex items-center gap-1.5 rounded-full border border-gray-200 bg-white px-3.5 py-2 text-[13.5px] font-bold text-ink hover:bg-gray-50"><AppIcon name="search" :size="15" />눌러서 크게 보기</button>
      <ol v-if="b.pins && b.pins.length" class="mt-3 space-y-2">
        <li v-for="(p, pi) in b.pins" :key="pi" class="flex gap-2.5 items-start">
          <span class="shrink-0 w-7 h-7 rounded-full bg-red-600 text-white text-[14px] font-black flex items-center justify-center">{{ pi + 1 }}</span>
          <span class="text-[15.5px] text-ink leading-relaxed" v-html="rich(p.text)"></span>
        </li>
      </ol>
      <p v-if="b.caption" class="text-[14.5px] text-ink-light leading-relaxed mt-2" v-html="rich(b.caption)"></p>
      <p class="text-[11.5px] text-ink-muted mt-2">※ {{ b.credit || '공식 사이트 화면을 직접 캡처했어요. 사이트가 개편되면 모양이 달라질 수 있어요.' }} <template v-if="b.pins && b.pins.length">빨간 번호는 눌러야 할 곳이에요.</template></p>
    </section>

    <!-- 참고한 자료 -->
    <section v-else-if="b.type === 'sources'" class="rounded-2xl bg-gray-50 border border-gray-100 p-4">
      <div class="font-black text-ink text-[14.5px] flex items-center gap-2"><AppIcon name="book-open" :size="16" :class="T.text" />{{ b.title || '참고한 자료' }}</div>
      <p class="text-[12.5px] text-ink-muted mt-1" v-html="rich(b.note)"></p>
      <ul class="mt-2 space-y-1.5">
        <template v-for="(l, k) in b.items" :key="k">
          <li v-if="safe(l.url)" class="text-[13px]"><a :href="l.url" target="_blank" rel="noopener noreferrer" class="underline font-bold" :class="T.text">{{ l.title }}</a><span class="text-ink-muted"> · {{ l.kind }}</span></li>
        </template>
      </ul>
    </section>

  </template>

  <!-- 캡처 크게 보기: 손가락으로 옆·아래로 밀어 보고, 바깥을 누르면 닫혀요 -->
  <Teleport to="body">
    <div v-if="zoom" class="fixed inset-0 z-[120] bg-black/85 flex flex-col" role="dialog" aria-modal="true" @click.self="zoom = null">
      <div class="flex items-center justify-between gap-3 px-4 py-3 text-white shrink-0">
        <div class="font-bold text-[15px] truncate">{{ zoom.title }}</div>
        <button type="button" @click="zoom = null" class="rounded-full bg-white/20 px-4 py-2 text-[14px] font-bold">닫기</button>
      </div>
      <div class="flex-1 overflow-auto px-3 pb-6" @click.self="zoom = null">
        <div class="relative mx-auto" style="width:1000px;max-width:none">
          <img :src="zoom.src" :alt="zoom.title" class="w-full h-auto block rounded-lg bg-white" />
          <span v-for="(p, pi) in (zoom.pins || [])" :key="pi" class="absolute -translate-x-1/2 -translate-y-1/2 flex items-center justify-center w-9 h-9 rounded-full bg-red-600 text-white text-[18px] font-black shadow-lg ring-4 ring-white/80" :style="{ left: p.x + '%', top: p.y + '%' }">{{ pi + 1 }}</span>
        </div>
        <p class="text-white/80 text-[13px] text-center mt-3">옆으로 밀어서 전체를 볼 수 있어요</p>
      </div>
    </div>
  </Teleport>
</div>
</template>

<script setup>
import { computed, reactive, ref } from 'vue'
import AppIcon from '../AppIcon.vue'

const props = defineProps({ blocks: { type: Array, default: () => [] }, theme: { type: String, default: 'emerald' } })

const THEMES = {
  emerald: { hero: 'linear-gradient(135deg,#047857,#10b981 60%,#34d399)', solid: 'bg-emerald-600', text: 'text-emerald-700', soft: 'bg-emerald-50 border-emerald-100', soft2: 'bg-emerald-50/60', line: 'bg-emerald-200' },
  sky:     { hero: 'linear-gradient(135deg,#0369a1,#0ea5e9 60%,#38bdf8)', solid: 'bg-sky-600', text: 'text-sky-700', soft: 'bg-sky-50 border-sky-100', soft2: 'bg-sky-50/60', line: 'bg-sky-200' },
  amber:   { hero: 'linear-gradient(135deg,#b45309,#f59e0b 60%,#fbbf24)', solid: 'bg-amber-600', text: 'text-amber-700', soft: 'bg-amber-50 border-amber-100', soft2: 'bg-amber-50/60', line: 'bg-amber-200' },
  rose:    { hero: 'linear-gradient(135deg,#be123c,#f43f5e 60%,#fb7185)', solid: 'bg-rose-600', text: 'text-rose-700', soft: 'bg-rose-50 border-rose-100', soft2: 'bg-rose-50/60', line: 'bg-rose-200' },
  violet:  { hero: 'linear-gradient(135deg,#6d28d9,#8b5cf6 60%,#a78bfa)', solid: 'bg-violet-600', text: 'text-violet-700', soft: 'bg-violet-50 border-violet-100', soft2: 'bg-violet-50/60', line: 'bg-violet-200' },
  orange:  { hero: 'linear-gradient(135deg,#c2410c,#f97316 60%,#fb923c)', solid: 'bg-orange-600', text: 'text-orange-700', soft: 'bg-orange-50 border-orange-100', soft2: 'bg-orange-50/60', line: 'bg-orange-200' },
  teal:    { hero: 'linear-gradient(135deg,#0f766e,#14b8a6 60%,#2dd4bf)', solid: 'bg-teal-600', text: 'text-teal-700', soft: 'bg-teal-50 border-teal-100', soft2: 'bg-teal-50/60', line: 'bg-teal-200' },
  indigo:  { hero: 'linear-gradient(135deg,#4338ca,#6366f1 60%,#818cf8)', solid: 'bg-indigo-600', text: 'text-indigo-700', soft: 'bg-indigo-50 border-indigo-100', soft2: 'bg-indigo-50/60', line: 'bg-indigo-200' },
  slate:   { hero: 'linear-gradient(135deg,#334155,#475569 60%,#64748b)', solid: 'bg-slate-600', text: 'text-slate-700', soft: 'bg-slate-50 border-slate-200', soft2: 'bg-slate-50/60', line: 'bg-slate-200' },
  blue:    { hero: 'linear-gradient(135deg,#1d4ed8,#3b82f6 60%,#60a5fa)', solid: 'bg-blue-600', text: 'text-blue-700', soft: 'bg-blue-50 border-blue-100', soft2: 'bg-blue-50/60', line: 'bg-blue-200' },
  yellow:  { hero: 'linear-gradient(135deg,#a16207,#eab308 60%,#facc15)', solid: 'bg-yellow-600', text: 'text-yellow-700', soft: 'bg-yellow-50 border-yellow-100', soft2: 'bg-yellow-50/60', line: 'bg-yellow-200' },
  pink:    { hero: 'linear-gradient(135deg,#be185d,#ec4899 60%,#f472b6)', solid: 'bg-pink-600', text: 'text-pink-700', soft: 'bg-pink-50 border-pink-100', soft2: 'bg-pink-50/60', line: 'bg-pink-200' },
}
// 큰 숫자 칸: 가장 긴 낱말이 칸 너비에 한 줄로 들어가도록 글자 크기를 자동으로 줄인다(낱말 중간에서 끊기지 않게).
// 글자 폭 추정: 한글·한자 1em, 대문자 .68, 소문자 .56, 숫자 .6, 쉼표·점 .3, 그 밖 .65
function statEm(v) {
  const w = (word) => [...word].reduce((a, ch) => a + (/[가-힣ㄱ-ㅎ一-鿿]/.test(ch) ? 1 : /[A-Z]/.test(ch) ? .68 : /[a-z]/.test(ch) ? .56 : /[0-9]/.test(ch) ? .6 : /[,.\-:]/.test(ch) ? .3 : .65), 0)
  const words = String(v).trim().split(/\s+/)
  return Math.max(1.6, Math.max(...words.map(w)) * 1.08).toFixed(2)
}
const T = computed(() => THEMES[props.blocks.find(b => b.type === 'hero')?.theme || props.theme] || THEMES.emerald)

const CALL = {
  tip:  { box: 'bg-emerald-50 border-emerald-500', icon: 'sparkles', ico: 'text-emerald-600' },
  warn: { box: 'bg-amber-50 border-amber-500', icon: 'alert-circle', ico: 'text-amber-600' },
  info: { box: 'bg-sky-50 border-sky-500', icon: 'info', ico: 'text-sky-600' },
}

// 안전한 굵은 글씨: 먼저 전부 이스케이프한 뒤 **글** 만 <b> 로
function rich(s) {
  const e = String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
  return e.replace(/\*\*(.+?)\*\*/g, '<b>$1</b>').replace(/\n/g, '<br>')
}
const safe = (u) => typeof u === 'string' && (/^https:\/\//i.test(u) || /^\/downloads\/money\/[\w\-.]+$/.test(u))
const host = (u) => { try { return new URL(u).hostname.replace(/^www\./, '') } catch { return '' } }
const pct = (v, b) => Math.max(2, Math.min(100, (Number(v) / (Number(b.max) || Math.max(...b.items.map(x => Number(x.value)))) ) * 100))
const usd = (n) => '$' + Math.round(n).toLocaleString('en-US')

const zoom = ref(null)
const checks = reactive({})
const doneCount = (i) => Object.keys(checks).filter(k => k.startsWith(i + ':') && checks[k]).length
const printPage = () => window.print()

// ── 복리 계산기 ──
const c = reactive({ start: 5000, monthly: 300, apy: 4.5, years: 10 })
const COMP_FIELDS = [
  { k: 'start', l: '처음 넣는 돈', min: 0, max: 100000, step: 500, fmt: usd },
  { k: 'monthly', l: '매달 더 넣는 돈', min: 0, max: 3000, step: 50, fmt: usd },
  { k: 'apy', l: '연 이율(가정)', min: 0, max: 12, step: 0.25, fmt: v => v + '%' },
  { k: 'years', l: '기간', min: 1, max: 40, step: 1, fmt: v => v + '년' },
]
const comp = computed(() => {
  const r = c.apy / 100 / 12, n = c.years * 12
  const fv = r === 0 ? c.start + c.monthly * n : c.start * Math.pow(1 + r, n) + c.monthly * (Math.pow(1 + r, n) - 1) / r
  const contrib = c.start + c.monthly * n
  return { fv, contrib, gain: fv - contrib, contribPct: fv ? Math.min(100, contrib / fv * 100) : 100 }
})

// ── 카드 연회비 손익분기 ──
const fee = reactive({ fee: 395, spend: 30000, rate: 3, credits: 200 })
const FEE_FIELDS = [
  { k: 'fee', l: '연회비', min: 0, max: 700, step: 5, fmt: usd },
  { k: 'spend', l: '1년 카드 사용액', min: 5000, max: 100000, step: 1000, fmt: usd },
  { k: 'rate', l: '평균 포인트·환급률(내 소비 기준)', min: 1, max: 6, step: 0.25, fmt: v => v + '%' },
  { k: 'credits', l: '실제로 쓰는 카드 혜택(연)', min: 0, max: 1000, step: 25, fmt: usd },
]
const feeCalc = computed(() => {
  const reward = fee.spend * fee.rate / 100
  const total = reward + fee.credits - fee.fee
  const flat = fee.spend * 0.02
  return { reward, total, flat, net: total - flat }
})

// ── 50/30/20 ──
const bud = reactive({ income: 5000 })

// 휴대폰 그림에서 누를 위치 (0~200 x 0~380 좌표)
const POS = {
  'top-left': { x: 40, y: 50 }, 'top-center': { x: 100, y: 50 }, 'top-right': { x: 160, y: 50 },
  'center': { x: 100, y: 160 }, 'left-center': { x: 50, y: 160 }, 'right-center': { x: 150, y: 160 },
  'bottom-center': { x: 100, y: 322 }, 'bottom-left': { x: 46, y: 327 }, 'bottom-right': { x: 170, y: 327 },
  'bottom-2': { x: 90, y: 327 }, 'bottom-3': { x: 134, y: 327 },
  'bottom-bar': { rect: true, x: 20, y: 304, w: 160, h: 46 }, 'top-bar': { rect: true, x: 22, y: 36, w: 156, h: 30 },
  'list-item': { rect: true, x: 26, y: 140, w: 148, h: 32 }, 'big-card': { rect: true, x: 26, y: 86, w: 148, h: 50 },
}
const pt = (b) => POS[b.where] || POS['center']
const short = (t) => { const x = String(t || '여기를 누르세요').replace(/\*\*/g, '').replace(/^["“]|["”]$/g, ''); return x.length > 15 ? x.slice(0, 14) + '…' : x }
const btnX = (b) => Math.max(24, Math.min(64, pt(b).x - 56))
// 버튼 이름을 두 줄까지 나눠 보여 줌 (한 줄 12자 안팎)
const lines = (t) => {
  const x = String(t || '여기를 누르세요').replace(/\*\*/g, '').replace(/^["“]|["”]$/g, '').trim()
  if (x.length <= 12) return [x]
  const mid = x.lastIndexOf(' ', 13)
  const cut = mid > 4 ? mid : 12
  const a = x.slice(0, cut).trim(), b = x.slice(cut).trim()
  return [a, b.length > 13 ? b.slice(0, 12) + '…' : b]
}

// ── 주택 계산기 ──
const m = reactive({ price: 450000, down: 10, rate: 6.5, years: 30, pmiRate: 0.8 })
const MORT_FIELDS = [
  { k: 'price', l: '집값', min: 150000, max: 1500000, step: 10000, fmt: usd },
  { k: 'down', l: '선납금(다운페이먼트)', min: 3, max: 40, step: 1, fmt: v => v + '%' },
  { k: 'rate', l: '대출 금리(연)', min: 3, max: 10, step: 0.125, fmt: v => v + '%' },
  { k: 'years', l: '대출 기간', min: 10, max: 30, step: 5, fmt: v => v + '년' },
  { k: 'pmiRate', l: 'PMI 연 요율(대출액 대비)', min: 0.3, max: 1.5, step: 0.1, fmt: v => v.toFixed(1) + '%' },
]
const mort = computed(() => {
  const loan = m.price * (1 - m.down / 100)
  const r = m.rate / 100 / 12
  const n = m.years * 12
  const pay = r === 0 ? loan / n : loan * r / (1 - Math.pow(1 + r, -n))
  const int = loan * r
  const princ = pay - int
  const pmi = m.down >= 20 ? 0 : loan * (m.pmiRate / 100) / 12
  // 잔액이 처음 집값(원래 가치)의 80%·78% 이하가 되는 달
  const reach = (ratio) => {
    let bal = loan
    for (let k = 1; k <= n; k++) { bal = bal * (1 + r) - pay; if (bal <= m.price * ratio) return k }
    return n
  }
  const total = pay + pmi
  return { total, princ, int, pmi, m80: pmi ? reach(0.8) : 0, m78: pmi ? reach(0.78) : 0,
    pPct: princ / total * 100, iPct: int / total * 100, mPct: pmi / total * 100 }
})
</script>

<style>
/* 큰 숫자: 칸을 기준으로(cqw) 글자 크기를 정한다. 낱말 단위로만 줄바꿈하고, 균형 있게 나눈다 */
.gb-stat { container-type: inline-size; }
.gb-stat-v { --base: 28px; font-size: max(17px, min(var(--base), calc(100cqw / var(--em, 4)))); line-height: 1.12; word-break: keep-all; overflow-wrap: break-word; text-wrap: balance; }
@media (min-width: 768px) { .gb-stat-v { --base: 34px; } }
@media print { .gb a[download], .gb input[type=range] { display: none } }
</style>
