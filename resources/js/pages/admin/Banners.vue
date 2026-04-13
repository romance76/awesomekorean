<template>
<div>
  <h1 class="text-xl font-black text-gray-800 mb-4">📢 광고 관리</h1>

  <!-- 전체 요약 -->
  <div class="grid grid-cols-5 gap-2 mb-4">
    <div class="bg-white rounded-xl border p-3 text-center"><div class="text-[10px] text-gray-500">전체</div><div class="text-lg font-black text-gray-800">{{ items.length }}</div></div>
    <div class="bg-white rounded-xl border p-3 text-center"><div class="text-[10px] text-gray-500">대기</div><div class="text-lg font-black text-amber-600">{{ items.filter(i=>i.status==='pending').length }}</div></div>
    <div class="bg-white rounded-xl border p-3 text-center"><div class="text-[10px] text-gray-500">게시중</div><div class="text-lg font-black text-green-600">{{ items.filter(i=>i.status==='active').length }}</div></div>
    <div class="bg-white rounded-xl border p-3 text-center"><div class="text-[10px] text-gray-500">총 수입</div><div class="text-lg font-black text-blue-600">{{ items.reduce((s,i)=>s+(i.bid_amount||i.total_cost||0),0).toLocaleString() }}P</div></div>
    <div class="bg-white rounded-xl border p-3 text-center"><div class="text-[10px] text-gray-500">총 클릭</div><div class="text-lg font-black text-purple-600">{{ items.reduce((s,i)=>s+(i.clicks||0),0).toLocaleString() }}</div></div>
  </div>

  <!-- 페이지 탭 -->
  <div class="bg-white rounded-xl border p-3 mb-4">
    <div class="flex flex-wrap gap-1.5">
      <button @click="activePage='all'" class="px-3 py-1.5 rounded-lg text-xs font-bold border transition"
        :class="activePage==='all'?'bg-amber-400 text-amber-900 border-amber-500':'bg-white text-gray-600 border-gray-200 hover:border-amber-300'">전체 ({{ items.length }})</button>
      <button v-for="pg in pageList" :key="pg.key" @click="activePage=pg.key"
        class="px-2.5 py-1.5 rounded-lg text-[11px] font-bold border transition"
        :class="activePage===pg.key?'bg-amber-400 text-amber-900 border-amber-500':'bg-white text-gray-600 border-gray-200 hover:border-amber-300'">
        {{ pg.icon }} {{ pg.label }} <span :class="pageAdCount(pg.key)?'text-green-600':'text-gray-400'">({{ pageAdCount(pg.key) }})</span>
      </button>
    </div>
  </div>

  <div v-if="loading" class="text-center py-8 text-gray-400">로딩중...</div>

  <!-- ═══ 카테고리 상세 뷰 ═══ -->
  <div v-else-if="activePage!=='all'" class="space-y-4">
    <h2 class="text-sm font-bold text-gray-800">{{ pageIcon(activePage) }} {{ pageLabel(activePage) }} — 슬롯별 입찰 현황</h2>

    <div v-for="side in ['left','right']" :key="side" class="bg-white rounded-xl border overflow-hidden">
      <div class="px-4 py-2 border-b font-bold text-xs" :class="side==='left'?'bg-blue-50 text-blue-800':'bg-orange-50 text-orange-800'">
        📌 {{ side==='left'?'좌측':'우측' }} 사이드바
      </div>

      <div v-for="slot in (side==='left'?leftSlots:rightSlots)" :key="slot.key" class="border-b last:border-0">
        <div class="px-4 py-2 bg-gray-50 flex items-center gap-2">
          <span>{{ slot.icon }}</span>
          <span class="text-xs font-bold text-gray-700">{{ slot.label }}</span>
          <span class="ml-auto text-[10px] font-bold" :class="slotTotalCount(activePage,side,slot.num)?'text-green-600':'text-gray-400'">
            총 {{ slotTotalCount(activePage,side,slot.num) }}건
          </span>
        </div>

        <!-- 지역별 분류: 전국 → 주 → 카운티 -->
        <div v-for="geo in geoTypes" :key="geo.key" class="px-4">
          <div v-if="slotGeoAds(activePage,side,slot.num,geo.key).length" class="py-2">
            <div class="flex items-center gap-2 mb-1.5">
              <span class="text-[10px] font-bold px-2 py-0.5 rounded-full" :class="geo.cls">{{ geo.icon }} {{ geo.label }}</span>
              <span class="text-[10px] text-gray-400">{{ slotGeoAds(activePage,side,slot.num,geo.key).length }}건</span>
            </div>
            <div class="space-y-1 ml-2">
              <div v-for="(ad, idx) in slotGeoAds(activePage,side,slot.num,geo.key)" :key="ad.id"
                class="flex items-center gap-2 p-2 rounded-lg text-xs"
                :class="idx===0?'bg-yellow-50 border border-yellow-200':'bg-gray-50'">
                <span class="font-black w-5 text-center" :class="idx===0?'text-yellow-600':idx===1?'text-gray-500':'text-gray-400'">{{ idx+1 }}</span>
                <!-- 배너 이미지 클릭 → 팝업 -->
                <div class="w-14 h-10 rounded overflow-hidden bg-gray-200 flex-shrink-0 cursor-pointer hover:ring-2 ring-amber-400"
                  @click="previewImage(ad)">
                  <img :src="ad.image_url" class="w-full h-full object-cover" />
                </div>
                <div class="flex-1 min-w-0">
                  <div class="font-bold text-gray-800 truncate">{{ ad.title }}</div>
                  <div class="text-[10px] text-gray-400">
                    {{ ad.user?.name }}
                    <span v-if="ad.geo_value"> · {{ ad.geo_value }}</span>
                    <!-- 링크 클릭 → 팝업 -->
                    <span v-if="ad.link_url" @click="previewLink(ad.link_url)" class="ml-1 text-blue-500 underline cursor-pointer hover:text-blue-700">🔗링크</span>
                  </div>
                </div>
                <div class="font-black text-amber-700">{{ (ad.bid_amount||0).toLocaleString() }}P</div>
                <span class="text-[10px] px-1.5 py-0.5 rounded-full font-bold" :class="stCls[ad.status]">{{ stLbl[ad.status] }}</span>
                <div class="flex gap-0.5 flex-shrink-0">
                  <button v-if="ad.status==='pending'" @click="approve(ad)" class="text-[9px] bg-green-500 text-white px-1.5 py-1 rounded font-bold">승인</button>
                  <button v-if="ad.status==='pending'" @click="reject(ad)" class="text-[9px] bg-red-500 text-white px-1.5 py-1 rounded font-bold">거절</button>
                  <button v-if="ad.status==='active'" @click="pause(ad)" class="text-[9px] bg-gray-200 text-gray-600 px-1.5 py-1 rounded font-bold">중지</button>
                  <button @click="remove(ad)" class="text-[9px] text-red-400 px-1">삭제</button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div v-if="!slotTotalCount(activePage,side,slot.num)" class="px-4 py-3 text-[10px] text-gray-400">입찰 없음</div>
      </div>
    </div>
  </div>

  <!-- ═══ 전체 목록 ═══ -->
  <div v-else-if="!items.length" class="text-center py-8 text-gray-400">광고 신청 없음</div>
  <div v-else class="space-y-2">
    <div v-for="item in items" :key="item.id" class="bg-white rounded-xl border p-3">
      <div class="flex gap-3 items-center">
        <div class="w-20 h-14 rounded-lg overflow-hidden bg-gray-100 flex-shrink-0 cursor-pointer hover:ring-2 ring-amber-400" @click="previewImage(item)">
          <img :src="item.image_url" class="w-full h-full object-cover" />
        </div>
        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-1.5 flex-wrap">
            <span class="text-[10px] px-2 py-0.5 rounded-full font-bold" :class="stCls[item.status]">{{ stLbl[item.status] }}</span>
            <span class="text-[10px] bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded-full">{{ pageLabel(item.page) }}</span>
            <span class="text-[10px] bg-purple-100 text-purple-700 px-1.5 py-0.5 rounded-full">{{ {left:'좌',right:'우'}[item.position] }}{{ item.slot_number }}</span>
            <span class="text-[10px] px-1.5 py-0.5 rounded-full font-bold" :class="geoTypesMap[item.geo_scope]?.cls||'bg-gray-100 text-gray-600'">{{ item.geo_scope==='all'?'전국':item.geo_value }}</span>
            <span class="text-[10px] bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded-full font-bold">{{ (item.bid_amount||0).toLocaleString() }}P</span>
            <span v-if="item.link_url" @click="previewLink(item.link_url)" class="text-[10px] text-blue-500 underline cursor-pointer">🔗</span>
          </div>
          <div class="text-xs font-bold text-gray-800 truncate mt-0.5">{{ item.title }}</div>
          <div class="text-[10px] text-gray-400">{{ item.user?.name }} · {{ (item.target_pages||[]).join(', ')||item.page }}</div>
        </div>
        <div class="flex gap-1 flex-shrink-0">
          <button v-if="item.status==='pending'" @click="approve(item)" class="text-[10px] bg-green-500 text-white px-2 py-1 rounded font-bold">승인</button>
          <button v-if="item.status==='pending'" @click="reject(item)" class="text-[10px] bg-red-500 text-white px-2 py-1 rounded font-bold">거절</button>
          <button v-if="item.status==='active'" @click="pause(item)" class="text-[10px] bg-gray-200 text-gray-600 px-2 py-1 rounded font-bold">중지</button>
          <button @click="remove(item)" class="text-[10px] text-red-400">삭제</button>
        </div>
      </div>
    </div>
  </div>

  <!-- ═══ 이미지 미리보기 팝업 ═══ -->
  <Teleport to="body">
    <div v-if="previewImg" class="fixed inset-0 bg-black/60 z-[9999] flex items-center justify-center" @click.self="previewImg=null">
      <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full mx-4 overflow-hidden">
        <div class="flex items-center justify-between px-4 py-3 border-b">
          <span class="text-sm font-bold text-gray-800">📷 배너 미리보기</span>
          <button @click="previewImg=null" class="text-gray-400 hover:text-gray-600 text-lg">✕</button>
        </div>
        <div class="p-4">
          <img :src="previewImg.image_url" class="w-full rounded-lg border" />
          <div class="mt-3 text-sm font-bold text-gray-800">{{ previewImg.title }}</div>
          <div class="text-xs text-gray-500 mt-1">{{ previewImg.user?.name }} · {{ (previewImg.bid_amount||0).toLocaleString() }}P · {{ previewImg.geo_scope==='all'?'전국':previewImg.geo_value }}</div>
          <div v-if="previewImg.link_url" class="mt-2">
            <span class="text-xs text-gray-500">링크: </span>
            <span @click="previewLink(previewImg.link_url)" class="text-xs text-blue-600 underline cursor-pointer">{{ previewImg.link_url }}</span>
          </div>
        </div>
      </div>
    </div>
  </Teleport>

  <!-- ═══ 링크 미리보기 팝업 ═══ -->
  <Teleport to="body">
    <div v-if="previewUrl" class="fixed inset-0 bg-black/60 z-[9999] flex items-center justify-center" @click.self="previewUrl=null">
      <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full mx-4 overflow-hidden" style="height:70vh">
        <div class="flex items-center justify-between px-4 py-2 border-b bg-gray-50">
          <span class="text-xs font-bold text-gray-700 truncate flex-1">🔗 {{ previewUrl }}</span>
          <div class="flex gap-2 flex-shrink-0">
            <a :href="previewUrl" target="_blank" class="text-[10px] bg-blue-500 text-white px-3 py-1 rounded font-bold">새 탭</a>
            <button @click="previewUrl=null" class="text-gray-400 hover:text-gray-600 text-lg">✕</button>
          </div>
        </div>
        <iframe :src="previewUrl" class="w-full" style="height:calc(70vh - 40px)" frameborder="0"></iframe>
      </div>
    </div>
  </Teleport>
</div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'

const items = ref([])
const loading = ref(true)
const activePage = ref('all')
const previewImg = ref(null)
const previewUrl = ref(null)

const pageList = [
  { key:'home',icon:'🏠',label:'홈' },{ key:'community',icon:'💬',label:'커뮤니티' },
  { key:'qa',icon:'❓',label:'Q&A' },{ key:'jobs',icon:'💼',label:'구인구직' },
  { key:'market',icon:'🛒',label:'장터' },{ key:'realestate',icon:'🏠',label:'부동산' },
  { key:'directory',icon:'🏪',label:'업소록' },{ key:'clubs',icon:'👥',label:'동호회' },
  { key:'news',icon:'📰',label:'뉴스' },{ key:'recipes',icon:'🍳',label:'레시피' },
  { key:'groupbuy',icon:'🤝',label:'공동구매' },{ key:'events',icon:'🎉',label:'이벤트' },
]

const leftSlots = [
  { key:'l1',num:1,icon:'🥇',label:'프리미엄 (고정)',max:1 },
  { key:'l2',num:2,icon:'🥈',label:'스탠다드 (2개 랜덤)',max:2 },
  { key:'l3',num:3,icon:'🥉',label:'이코노미 (5개 랜덤)',max:5 },
]
const rightSlots = [
  { key:'r1',num:1,icon:'🥇',label:'프리미엄 (고정)',max:1 },
  { key:'r2',num:2,icon:'🥉',label:'이코노미 (3개 랜덤)',max:3 },
]

const geoTypes = [
  { key:'all', icon:'🌍', label:'전국', cls:'bg-amber-100 text-amber-700' },
  { key:'state', icon:'🏛️', label:'주별', cls:'bg-blue-100 text-blue-700' },
  { key:'county', icon:'📍', label:'카운티별', cls:'bg-green-100 text-green-700' },
]
const geoTypesMap = { all:geoTypes[0], state:geoTypes[1], county:geoTypes[2] }

const stLbl = { pending:'대기',active:'게시중',rejected:'거절',expired:'만료',paused:'중지' }
const stCls = { pending:'bg-amber-100 text-amber-700',active:'bg-green-100 text-green-700',rejected:'bg-red-100 text-red-700',expired:'bg-gray-200 text-gray-500',paused:'bg-gray-200 text-gray-500' }

function pageLabel(k){ return pageList.find(p=>p.key===k)?.label||k }
function pageIcon(k){ return pageList.find(p=>p.key===k)?.icon||'📄' }
function pageAdCount(pk){ return items.value.filter(i=> i.page===pk||(i.target_pages&&i.target_pages.includes(pk))).length }

function matchPage(ad, pk) {
  return ad.page===pk || (ad.target_pages&&ad.target_pages.includes(pk)) || ad.page==='all'
}

function slotGeoAds(pk, pos, slotNum, geoScope) {
  return items.value
    .filter(i => matchPage(i,pk) && i.position===pos && (i.slot_number||1)===slotNum && i.geo_scope===geoScope)
    .sort((a,b) => (b.bid_amount||0)-(a.bid_amount||0))
}

function slotTotalCount(pk, pos, slotNum) {
  return items.value.filter(i => matchPage(i,pk) && i.position===pos && (i.slot_number||1)===slotNum).length
}

function previewImage(ad) { previewImg.value = ad }
function previewLink(url) { previewUrl.value = url }

async function load() {
  try {
    const{data}=await axios.get('/api/admin/banners')
    items.value=(data.data||[]).map(i=>({...i,target_pages:typeof i.target_pages==='string'?JSON.parse(i.target_pages):(i.target_pages||[])}))
  }catch{}
  loading.value=false
}

async function approve(item){try{await axios.post(`/api/admin/banners/${item.id}/approve`);item.status='active'}catch(e){alert(e.response?.data?.message||'실패')}}
async function reject(item){const r=prompt('거절 사유:');if(!r)return;try{await axios.post(`/api/admin/banners/${item.id}/reject`,{reason:r});item.status='rejected'}catch{}}
async function pause(item){try{await axios.post(`/api/admin/banners/${item.id}/pause`);item.status='paused'}catch{}}
async function remove(item){if(!confirm('삭제?'))return;try{await axios.delete(`/api/admin/banners/${item.id}`);items.value=items.value.filter(i=>i.id!==item.id)}catch{}}

onMounted(load)
</script>
