<template>
<div v-if="show" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" @click.self="$emit('close')">
  <div class="bg-white rounded-2xl shadow-xl w-full max-w-5xl max-h-[92vh] overflow-hidden flex flex-col">
    <div class="px-5 py-3 border-b flex items-center justify-between bg-amber-50">
      <div>
        <span class="font-bold text-amber-900">👤 회원 상세 정보</span>
        <span v-if="data?.user" class="ml-2 text-sm text-gray-600">— {{ data.user.name }} ({{ data.user.email }})</span>
      </div>
      <button @click="$emit('close')" class="text-gray-400 hover:text-gray-600 text-xl">✕</button>
    </div>

    <div v-if="loading" class="py-12 text-center text-gray-400">로딩중...</div>
    <div v-else-if="data" class="flex-1 overflow-hidden flex flex-col">
      <!-- 요약 카드 -->
      <div class="px-5 pt-3 grid grid-cols-5 gap-2">
        <div class="bg-blue-50 rounded p-2 text-center">
          <div class="text-[10px] text-gray-500">포인트</div>
          <div class="font-bold text-blue-700">{{ Number(data.user.points||0).toLocaleString() }}P</div>
        </div>
        <div class="bg-green-50 rounded p-2 text-center">
          <div class="text-[10px] text-gray-500">결제 누적</div>
          <div class="font-bold text-green-700">${{ Number(data.summary?.total_spent_usd||0) }}</div>
        </div>
        <div class="bg-purple-50 rounded p-2 text-center">
          <div class="text-[10px] text-gray-500">전체 게시</div>
          <div class="font-bold text-purple-700">{{ data.summary?.posts_total||0 }}</div>
        </div>
        <div class="bg-amber-50 rounded p-2 text-center">
          <div class="text-[10px] text-gray-500">광고(활성)</div>
          <div class="font-bold text-amber-700">{{ data.summary?.ads_active||0 }}</div>
        </div>
        <div class="bg-red-50 rounded p-2 text-center">
          <div class="text-[10px] text-gray-500">상태</div>
          <div class="font-bold" :class="data.user.is_banned?'text-red-700':'text-green-700'">{{ data.user.is_banned?'정지':'정상' }}</div>
        </div>
      </div>

      <!-- 탭 -->
      <div class="px-5 pt-3 flex gap-1 border-b overflow-x-auto">
        <button v-for="t in tabs" :key="t.key" @click="tab=t.key"
          class="px-3 py-2 text-xs font-medium border-b-2 -mb-px whitespace-nowrap transition"
          :class="tab===t.key?'border-amber-500 text-amber-700':'border-transparent text-gray-500 hover:text-gray-800'">
          {{ t.label }}
          <span v-if="count(t.key) > 0" class="ml-1 bg-gray-100 text-gray-600 text-[9px] px-1 rounded">{{ count(t.key) }}</span>
        </button>
      </div>

      <!-- 스크롤 영역 -->
      <div class="flex-1 overflow-y-auto px-5 py-3">
        <!-- 기본정보 -->
        <div v-if="tab==='info'">
          <div class="grid grid-cols-2 gap-3">
            <div><label class="text-xs text-gray-500">이름</label><input v-model="data.user.name" class="w-full border rounded px-2 py-1.5 text-sm mt-0.5" /></div>
            <div><label class="text-xs text-gray-500">닉네임</label><input v-model="data.user.nickname" class="w-full border rounded px-2 py-1.5 text-sm mt-0.5" /></div>
            <div><label class="text-xs text-gray-500">이메일</label><input v-model="data.user.email" class="w-full border rounded px-2 py-1.5 text-sm mt-0.5" /></div>
            <div><label class="text-xs text-gray-500">전화</label><input v-model="data.user.phone" class="w-full border rounded px-2 py-1.5 text-sm mt-0.5" /></div>
            <div><label class="text-xs text-gray-500">도시</label><input v-model="data.user.city" class="w-full border rounded px-2 py-1.5 text-sm mt-0.5" /></div>
            <div><label class="text-xs text-gray-500">주</label><input v-model="data.user.state" class="w-full border rounded px-2 py-1.5 text-sm mt-0.5" /></div>
            <div><label class="text-xs text-gray-500">역할</label>
              <select v-model="data.user.role" class="w-full border rounded px-2 py-1.5 text-sm mt-0.5">
                <option value="user">일반</option><option value="admin">관리자</option><option value="super_admin">슈퍼</option>
              </select>
            </div>
            <div><label class="text-xs text-gray-500">상태</label>
              <select v-model="data.user.is_banned" class="w-full border rounded px-2 py-1.5 text-sm mt-0.5">
                <option :value="false">정상</option><option :value="true">정지</option>
              </select>
            </div>
            <div><label class="text-xs text-gray-500">포인트</label><input v-model.number="data.user.points" type="number" class="w-full border rounded px-2 py-1.5 text-sm mt-0.5" /></div>
            <div><label class="text-xs text-gray-500">게임 포인트</label><input v-model.number="data.user.game_points" type="number" class="w-full border rounded px-2 py-1.5 text-sm mt-0.5" /></div>
          </div>
          <div class="mt-3 text-xs text-gray-400">가입: {{ data.user.created_at?.slice(0,10) }} · 로그인 {{ data.user.login_count||0 }}회 · 최근 {{ data.user.last_login_at?.slice(0,10)||'-' }}</div>
          <button @click="saveUser" class="mt-3 bg-amber-400 text-amber-900 font-bold px-5 py-2 rounded-lg text-sm hover:bg-amber-500">저장</button>
        </div>

        <!-- 결제 -->
        <div v-if="tab==='payments'">
          <div v-if="!data.payments?.length" class="py-6 text-center text-gray-400 text-sm">결제 내역 없음</div>
          <table v-else class="w-full text-xs">
            <thead class="bg-gray-50 text-gray-600"><tr><th class="p-2 text-left">주문#</th><th class="p-2">날짜</th><th class="p-2">금액</th><th class="p-2">포인트</th><th class="p-2">상태</th><th class="p-2">Stripe</th></tr></thead>
            <tbody>
              <tr v-for="p in data.payments" :key="p.id" class="border-t">
                <td class="p-2 font-bold">#{{ p.id }}</td>
                <td class="p-2">{{ p.created_at?.slice(0,10) }}</td>
                <td class="p-2 text-green-700 font-bold">${{ p.amount }}</td>
                <td class="p-2 text-blue-700 font-bold">+{{ p.points_purchased }}P</td>
                <td class="p-2"><span class="px-1.5 py-0.5 rounded text-[10px]" :class="statusClass(p.status)">{{ p.status }}</span></td>
                <td class="p-2 text-[10px] text-gray-400 truncate max-w-[120px]">{{ p.stripe_payment_id }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- 포인트 -->
        <div v-if="tab==='points'">
          <div v-if="!data.points?.length" class="py-6 text-center text-gray-400 text-sm">포인트 내역 없음</div>
          <table v-else class="w-full text-xs">
            <thead class="bg-gray-50 text-gray-600"><tr><th class="p-2 text-left">날짜</th><th class="p-2 text-left">사유</th><th class="p-2">타입</th><th class="p-2">증감</th><th class="p-2">잔액</th></tr></thead>
            <tbody>
              <tr v-for="pt in data.points" :key="pt.id" class="border-t">
                <td class="p-2 text-gray-500">{{ pt.created_at?.slice(0,16).replace('T',' ') }}</td>
                <td class="p-2">{{ pt.reason }}</td>
                <td class="p-2 text-[10px] text-gray-500">{{ pt.type }}</td>
                <td class="p-2 text-right font-bold" :class="pt.amount>0?'text-green-600':'text-red-600'">{{ pt.amount>0?'+':'' }}{{ pt.amount }}P</td>
                <td class="p-2 text-right text-gray-600">{{ pt.balance_after }}P</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- 커뮤니티 -->
        <div v-if="tab==='posts'">
          <PostTable :items="data.posts" icon="💬" empty="커뮤니티 게시글 없음" />
        </div>
        <!-- 댓글 -->
        <div v-if="tab==='comments'">
          <div v-if="!data.comments?.length" class="py-6 text-center text-gray-400 text-sm">댓글 없음</div>
          <div v-for="c in data.comments" :key="c.id" class="py-2 border-b text-xs">
            <div class="text-gray-700">{{ c.content }}</div>
            <div class="text-[10px] text-gray-400 mt-0.5">{{ c.created_at?.slice(0,10) }} · 좋아요 {{ c.like_count||0 }}</div>
          </div>
        </div>
        <!-- 장터 -->
        <div v-if="tab==='market'"><PostTable :items="data.market" icon="🛒" empty="장터 글 없음" :extra="['price','city']" /></div>
        <!-- 구인 -->
        <div v-if="tab==='jobs'"><PostTable :items="data.jobs" icon="💼" empty="구인 글 없음" :extra="['company','city']" /></div>
        <!-- 부동산 -->
        <div v-if="tab==='realestate'"><PostTable :items="data.realestate" icon="🏠" empty="부동산 글 없음" :extra="['price','city']" /></div>
        <!-- 이벤트 -->
        <div v-if="tab==='events'"><PostTable :items="data.events" icon="🎉" empty="이벤트 없음" :extra="['start_date','city']" /></div>
        <!-- 동호회 -->
        <div v-if="tab==='clubs'"><PostTable :items="data.clubs" icon="👥" empty="동호회 없음" :extra="['category','member_count']" title-field="name" /></div>
        <!-- Q&A -->
        <div v-if="tab==='qa'"><PostTable :items="data.qa" icon="❓" empty="Q&A 없음" :extra="['bounty_points','answer_count']" /></div>

        <!-- 광고 -->
        <div v-if="tab==='banners'">
          <div v-if="!data.banners?.length" class="py-6 text-center text-gray-400 text-sm">광고 신청 내역 없음</div>
          <table v-else class="w-full text-xs">
            <thead class="bg-gray-50"><tr><th class="p-2 text-left">제목</th><th class="p-2">페이지</th><th class="p-2">위치</th><th class="p-2">기간</th><th class="p-2">비용</th><th class="p-2">상태</th></tr></thead>
            <tbody>
              <tr v-for="b in data.banners" :key="b.id" class="border-t">
                <td class="p-2">{{ b.title }}</td>
                <td class="p-2 text-[10px]">{{ b.page }}</td>
                <td class="p-2 text-[10px] text-center">{{ b.position }}</td>
                <td class="p-2 text-[10px] text-gray-500">{{ b.start_date }} ~ {{ b.end_date }}</td>
                <td class="p-2 text-right">{{ b.total_cost }}P</td>
                <td class="p-2 text-center"><span class="text-[10px] px-1.5 rounded" :class="statusClass(b.status)">{{ b.status }}</span></td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- 신고한 내역 -->
        <div v-if="tab==='reports'">
          <div v-if="!data.reports_filed?.length" class="py-6 text-center text-gray-400 text-sm">신고 내역 없음</div>
          <div v-for="r in data.reports_filed" :key="r.id" class="py-2 border-b text-xs">
            <div class="flex justify-between">
              <span class="font-medium">{{ r.reportable_type?.split('\\').pop() }} #{{ r.reportable_id }}</span>
              <span class="text-[10px] px-1.5 rounded" :class="statusClass(r.status)">{{ r.status }}</span>
            </div>
            <div class="text-red-600">{{ r.reason }}</div>
            <div class="text-[10px] text-gray-400">{{ r.created_at?.slice(0,10) }}</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
</template>

<script setup>
import { ref, watch, h } from 'vue'
import axios from 'axios'

const props = defineProps({ show: Boolean, userId: [Number, String] })
defineEmits(['close'])

const data = ref(null); const loading = ref(false); const tab = ref('info')

const tabs = [
  { key:'info',       label:'📋 기본정보' },
  { key:'payments',   label:'💳 결제' },
  { key:'points',     label:'💰 포인트' },
  { key:'posts',      label:'💬 커뮤니티' },
  { key:'comments',   label:'💭 댓글' },
  { key:'market',     label:'🛒 장터' },
  { key:'jobs',       label:'💼 구인' },
  { key:'realestate', label:'🏠 부동산' },
  { key:'events',     label:'🎉 이벤트' },
  { key:'clubs',      label:'👥 동호회' },
  { key:'qa',         label:'❓ Q&A' },
  { key:'banners',    label:'📢 광고' },
  { key:'reports',    label:'🚨 신고' },
]

function count(key) {
  if (!data.value) return 0
  const map = { payments:'payments', points:'points', posts:'posts', comments:'comments',
    market:'market', jobs:'jobs', realestate:'realestate', events:'events',
    clubs:'clubs', qa:'qa', banners:'banners', reports:'reports_filed' }
  return data.value[map[key]]?.length || 0
}

function statusClass(s) {
  return {
    active:    'bg-green-100 text-green-700',
    completed: 'bg-green-100 text-green-700',
    resolved:  'bg-green-100 text-green-700',
    pending:   'bg-yellow-100 text-yellow-700',
    paused:    'bg-orange-100 text-orange-700',
    rejected:  'bg-red-100 text-red-700',
    refunded:  'bg-red-100 text-red-700',
    cancelled: 'bg-gray-100 text-gray-500',
    expired:   'bg-gray-100 text-gray-500',
  }[s] || 'bg-gray-100 text-gray-700'
}

// 게시물 공통 리스트 컴포넌트
const PostTable = {
  props: { items: Array, icon: String, empty: String, extra: { type: Array, default: () => [] }, titleField: { type: String, default: 'title' } },
  setup(p) {
    return () => {
      if (!p.items?.length) return h('div', { class: 'py-6 text-center text-gray-400 text-sm' }, p.empty)
      return h('table', { class: 'w-full text-xs' }, [
        h('thead', { class: 'bg-gray-50 text-gray-600' }, h('tr', [
          h('th', { class: 'p-2 text-left' }, '#'),
          h('th', { class: 'p-2 text-left' }, '제목'),
          ...p.extra.map(k => h('th', { class: 'p-2' }, k)),
          h('th', { class: 'p-2' }, '조회'),
          h('th', { class: 'p-2' }, '날짜'),
        ])),
        h('tbody', p.items.map(it =>
          h('tr', { class: 'border-t', key: it.id }, [
            h('td', { class: 'p-2 text-gray-400' }, '#' + it.id),
            h('td', { class: 'p-2 font-medium' }, it[p.titleField] || it.title || it.name || '-'),
            ...p.extra.map(k => h('td', { class: 'p-2 text-center text-[10px]' }, String(it[k] ?? '-'))),
            h('td', { class: 'p-2 text-center text-gray-500' }, it.view_count || 0),
            h('td', { class: 'p-2 text-[10px] text-gray-400' }, (it.created_at||'').slice(0,10)),
          ])
        ))
      ])
    }
  }
}

watch(() => props.userId, async (id) => {
  if (!id) return
  loading.value = true; data.value = null; tab.value = 'info'
  try { const { data: res } = await axios.get(`/api/admin/users/${id}/detail`); data.value = res.data } catch {}
  loading.value = false
}, { immediate: true })

async function saveUser() {
  if (!data.value?.user) return
  try { await axios.put(`/api/admin/users/${data.value.user.id}`, data.value.user); alert('저장되었습니다') } catch (e) { alert(e.response?.data?.message || '저장 실패') }
}
</script>
