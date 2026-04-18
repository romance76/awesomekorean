<template>
<div>
  <!-- 타이틀 -->
  <h1 v-if="title" class="text-xl font-black text-gray-800 mb-4">{{ icon }} {{ title }}</h1>

  <!-- 검색 + 필터 -->
  <div class="bg-white rounded-xl shadow-sm border p-3 mb-4">
    <div class="flex flex-wrap gap-2 items-center">
      <div v-if="categoryFilter" class="flex items-center gap-1 bg-blue-50 border border-blue-200 text-blue-700 rounded-full px-3 py-1 text-xs">
        📂 카테고리: <strong>{{ categoryFilterLabel || categoryFilter }}</strong>
        <button @click="$emit('clearFilter')" class="ml-1 text-blue-500 hover:text-blue-700">✕</button>
      </div>
      <slot name="filters"></slot>
      <form @submit.prevent="load()" class="flex-1 flex gap-1 min-w-[150px]">
        <input v-model="search" type="text" placeholder="제목/작성자 검색..." class="flex-1 border rounded-lg px-3 py-1.5 text-sm outline-none focus:ring-2 focus:ring-amber-400" />
        <button type="submit" class="bg-amber-400 text-amber-900 font-bold px-3 py-1.5 rounded-lg text-xs">검색</button>
      </form>
    </div>
    <div class="text-[10px] text-gray-400 mt-1">전체 {{ total }}건</div>
  </div>

  <div v-if="loading" class="text-center py-8 text-gray-400">로딩중...</div>
  <div v-else class="flex gap-4">
    <!-- 왼쪽: 목록 -->
    <div :class="activeItem ? 'w-1/2' : 'w-full'">
      <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
        <table class="w-full text-sm">
          <thead class="bg-gray-50 border-b"><tr>
            <th class="px-3 py-2 text-left text-xs text-gray-500 w-8">#</th>
            <th class="px-3 py-2 text-left text-xs text-gray-500">{{ titleCol }}</th>
            <th v-for="col in extraCols" :key="col.key" v-show="!activeItem" class="px-3 py-2 text-left text-xs text-gray-500">{{ col.label }}</th>
            <th class="px-3 py-2 text-left text-xs text-gray-500">작성자</th>
            <th class="px-3 py-2 text-xs text-gray-500">상태</th>
            <th class="px-3 py-2 text-xs text-gray-500">👁</th>
            <th class="px-3 py-2 text-xs text-gray-500">날짜</th>
          </tr></thead>
          <tbody>
            <tr v-for="item in items" :key="item.id"
              class="border-b last:border-0 hover:bg-amber-50/30 cursor-pointer transition"
              :class="activeItem?.id===item.id ? 'bg-amber-50 border-l-2 border-l-amber-500' : ''"
              @click="openItem(item)">
              <td class="px-3 py-2 text-xs text-gray-400">{{ item.id }}</td>
              <td class="px-3 py-2.5 max-w-[250px]">
                <div class="flex items-center gap-1">
                  <span v-if="item.is_pinned" title="고정" class="text-red-500 text-xs">📌</span>
                  <span v-if="item.is_hidden" title="숨김" class="text-gray-400 text-xs">🙈</span>
                  <span v-if="item.is_locked" title="잠김" class="text-orange-500 text-xs">🔒</span>
                  <div class="truncate text-sm font-medium text-gray-800">{{ item.title || item.name }}</div>
                </div>
                <div class="text-[10px] text-gray-400 truncate mt-0.5">{{ (item.content || item.description || '').slice(0, 60) }}{{ (item.content || item.description || '').length > 60 ? '...' : '' }}</div>
              </td>
              <td v-for="col in extraCols" :key="col.key" v-show="!activeItem" class="px-3 py-2.5">
                <span class="text-[10px] bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded-full font-semibold">{{ getNestedVal(item, col.key) }}</span>
              </td>
              <td class="px-3 py-2.5">
                <button @click.stop="$emit('openUser', item.user || {id: item.user_id})" class="text-xs text-blue-600 hover:underline">{{ item.user?.name || '-' }}</button>
              </td>
              <td class="px-3 py-2.5 text-center">
                <span v-if="item.status" class="text-[10px] px-1.5 py-0.5 rounded" :class="statusClass(item.status)">{{ item.status }}</span>
                <span v-else-if="item.is_hidden" class="text-[10px] bg-gray-200 text-gray-600 px-1.5 py-0.5 rounded">숨김</span>
                <span v-else-if="item.is_active === false" class="text-[10px] bg-gray-200 text-gray-600 px-1.5 py-0.5 rounded">비활성</span>
                <span v-else class="text-[10px] bg-green-100 text-green-700 px-1.5 py-0.5 rounded">정상</span>
              </td>
              <td class="px-3 py-2.5 text-center text-xs text-gray-400">{{ item.view_count || 0 }}</td>
              <td class="px-3 py-2.5 text-[10px] text-gray-400">{{ (item.created_at || item.published_at || '')?.slice(0,10) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-if="lastPage > 1" class="flex justify-center gap-1.5 mt-4">
        <button v-for="pg in Math.min(lastPage, 10)" :key="pg" @click="load(pg)"
          class="px-3 py-1 rounded text-sm" :class="pg===page?'bg-amber-400 text-amber-900 font-bold':'bg-white border text-gray-600'">{{ pg }}</button>
      </div>
    </div>

    <!-- 오른쪽: 인라인 상세 + 관리 -->
    <div v-if="activeItem" class="w-1/2">
      <div class="bg-white rounded-xl shadow-sm border overflow-hidden sticky top-4 max-h-[calc(100vh-2rem)] overflow-y-auto">
        <div class="px-4 py-3 border-b flex items-center justify-between bg-amber-50 sticky top-0 z-10">
          <span class="font-bold text-sm text-amber-900">📝 게시글 관리</span>
          <div class="flex gap-1">
            <button @click="editMode = !editMode" class="text-xs bg-white border px-2 py-1 rounded hover:bg-gray-50">
              {{ editMode ? '✓ 완료' : '✏️ 편집' }}
            </button>
            <button @click="activeItem=null; editMode=false" class="text-gray-400 hover:text-gray-600 text-lg">✕</button>
          </div>
        </div>

        <!-- 관리 액션 바 -->
        <div v-if="detailData" class="px-4 py-2 bg-gray-50 border-b flex flex-wrap gap-1">
          <button v-if="actions.pin" @click="toggleField('is_pinned')"
            :class="activeItem.is_pinned ? 'bg-red-100 text-red-700' : 'bg-white border text-gray-600'"
            class="text-[10px] px-2 py-1 rounded hover:bg-red-50">
            📌 {{ activeItem.is_pinned ? '고정해제' : '고정' }}
          </button>
          <button v-if="actions.hide" @click="toggleField('is_hidden')"
            :class="activeItem.is_hidden ? 'bg-gray-200 text-gray-700' : 'bg-white border text-gray-600'"
            class="text-[10px] px-2 py-1 rounded hover:bg-gray-100">
            🙈 {{ activeItem.is_hidden ? '공개' : '숨김' }}
          </button>
          <button v-if="actions.active" @click="toggleField('is_active')"
            :class="activeItem.is_active === false ? 'bg-orange-100 text-orange-700' : 'bg-white border text-gray-600'"
            class="text-[10px] px-2 py-1 rounded hover:bg-orange-50">
            {{ activeItem.is_active === false ? '🔴 비활성' : '🟢 활성' }}
          </button>
          <button v-if="actions.lock_comments" @click="toggleField('is_locked')"
            :class="activeItem.is_locked ? 'bg-orange-100 text-orange-700' : 'bg-white border text-gray-600'"
            class="text-[10px] px-2 py-1 rounded hover:bg-orange-50">
            🔒 {{ activeItem.is_locked ? '잠금해제' : '댓글잠금' }}
          </button>
          <button v-if="actions.approved" @click="toggleField('is_approved')"
            :class="activeItem.is_approved ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'"
            class="text-[10px] px-2 py-1 rounded">
            {{ activeItem.is_approved ? '✅ 승인됨' : '⏳ 대기' }}
          </button>
          <button v-if="actions.verified" @click="toggleField('is_verified')"
            :class="activeItem.is_verified ? 'bg-blue-100 text-blue-700' : 'bg-white border text-gray-600'"
            class="text-[10px] px-2 py-1 rounded">
            {{ activeItem.is_verified ? '✓ 인증됨' : '인증하기' }}
          </button>
          <button v-if="actions.resolved" @click="toggleField('is_resolved')"
            :class="activeItem.is_resolved ? 'bg-green-100 text-green-700' : 'bg-white border text-gray-600'"
            class="text-[10px] px-2 py-1 rounded">
            {{ activeItem.is_resolved ? '✅ 해결됨' : '미해결' }}
          </button>
          <button @click="openPointModal" class="text-[10px] px-2 py-1 rounded bg-purple-100 text-purple-700 hover:bg-purple-200">
            💰 포인트 조정
          </button>
          <button @click="openMoveModal" class="text-[10px] px-2 py-1 rounded bg-blue-100 text-blue-700 hover:bg-blue-200">
            📂 카테고리 변경
          </button>
          <button @click="deleteItem(activeItem)" class="text-[10px] px-2 py-1 rounded bg-red-100 text-red-700 hover:bg-red-200 ml-auto">🗑 삭제</button>
        </div>

        <!-- 제목/내용 (편집 모드) -->
        <div v-if="editMode" class="px-4 py-3 space-y-2">
          <div>
            <label class="text-[10px] text-gray-500">제목</label>
            <input v-model="activeItem.title" class="w-full border rounded px-2 py-1 text-sm" />
          </div>
          <div v-if="activeItem.content !== undefined">
            <label class="text-[10px] text-gray-500">내용</label>
            <textarea v-model="activeItem.content" rows="8" class="w-full border rounded px-2 py-1 text-sm font-mono"></textarea>
          </div>
          <div v-if="activeItem.description !== undefined">
            <label class="text-[10px] text-gray-500">설명</label>
            <textarea v-model="activeItem.description" rows="4" class="w-full border rounded px-2 py-1 text-sm"></textarea>
          </div>
          <div class="grid grid-cols-2 gap-2">
            <div v-if="activeItem.price !== undefined && activeItem.price !== null">
              <label class="text-[10px] text-gray-500">가격</label>
              <input v-model.number="activeItem.price" type="number" class="w-full border rounded px-2 py-1 text-sm" />
            </div>
            <div v-if="activeItem.city !== undefined">
              <label class="text-[10px] text-gray-500">도시</label>
              <input v-model="activeItem.city" class="w-full border rounded px-2 py-1 text-sm" />
            </div>
          </div>
          <button @click="savePost" class="bg-amber-400 text-amber-900 font-bold px-4 py-1.5 rounded text-sm">💾 저장</button>
        </div>

        <!-- 보기 모드 -->
        <div v-else class="px-4 py-3">
          <h2 class="text-lg font-bold text-gray-900">{{ activeItem.title || activeItem.name }}</h2>
          <div class="flex flex-wrap items-center gap-3 mt-2 text-xs text-gray-500">
            <button @click="$emit('openUser', activeItem.user || {id: activeItem.user_id})" class="text-blue-600 hover:underline font-semibold">{{ activeItem.user?.name || '-' }}</button>
            <span v-if="activeItem.company">🏢 {{ activeItem.company }}</span>
            <span v-if="activeItem.city">📍 {{ activeItem.city }}, {{ activeItem.state }}</span>
            <span v-if="activeItem.category">📋 {{ typeof activeItem.category === 'object' ? activeItem.category.name : activeItem.category }}</span>
            <span v-if="activeItem.price !== undefined && activeItem.price !== null">💵 ${{ Number(activeItem.price).toLocaleString() }}</span>
            <span>👁 {{ activeItem.view_count || 0 }}</span>
            <span>{{ (activeItem.created_at || activeItem.published_at || '')?.slice(0,10) }}</span>
          </div>
        </div>

        <!-- 이미지 -->
        <div v-if="!editMode && activeItem.image_url" class="px-4 pb-2">
          <img :src="activeItem.image_url" class="w-full max-h-48 object-cover rounded-lg" @error="e=>e.target.style.display='none'" />
        </div>

        <!-- 본문 -->
        <div v-if="!editMode" class="px-4 py-3 border-t text-sm text-gray-700 leading-relaxed whitespace-pre-wrap max-h-[300px] overflow-y-auto">{{ activeItem.content || activeItem.description || '(내용 없음)' }}</div>

        <!-- 댓글 + 답글 -->
        <div v-if="detailData?.comments" class="px-4 py-3 border-t">
          <div class="text-xs font-bold text-gray-700 mb-2">💬 댓글 {{ commentTotalCount }}개</div>
          <div v-if="!detailData.comments.length" class="text-[10px] text-gray-400 py-2">댓글이 없습니다</div>
          <div v-else class="space-y-2 max-h-[300px] overflow-y-auto">
            <div v-for="c in detailData.comments" :key="c.id" class="border rounded p-2 bg-gray-50">
              <div class="flex items-start justify-between gap-2">
                <div class="flex-1 min-w-0">
                  <div class="flex items-center gap-1 text-[10px] mb-1">
                    <button @click="$emit('openUser', c.user)" class="text-blue-600 hover:underline font-semibold">{{ c.user?.name || '?' }}</button>
                    <span class="text-gray-400">·</span>
                    <span class="text-gray-500">{{ c.created_at?.slice(0,16).replace('T',' ') }}</span>
                    <span v-if="c.like_count" class="text-red-500">♥ {{ c.like_count }}</span>
                    <span v-if="c.is_hidden" class="bg-gray-300 text-gray-700 px-1 rounded">숨김</span>
                  </div>
                  <div class="text-xs text-gray-700 whitespace-pre-wrap" :class="c.is_hidden ? 'line-through text-gray-400' : ''">{{ c.content }}</div>
                </div>
                <div class="flex gap-1 shrink-0">
                  <button @click="toggleCommentHide(c)" class="text-[10px] text-orange-600 hover:underline">{{ c.is_hidden ? '공개' : '숨김' }}</button>
                  <button @click="deleteComment(c)" class="text-[10px] text-red-500 hover:underline">삭제</button>
                </div>
              </div>
              <!-- 답글 -->
              <div v-if="c.replies?.length" class="mt-2 ml-4 space-y-1 border-l-2 border-amber-200 pl-2">
                <div v-for="r in c.replies" :key="r.id" class="text-[11px]">
                  <div class="flex items-start justify-between gap-2">
                    <div class="flex-1">
                      <span class="text-blue-600 font-semibold">↳ {{ r.user?.name || '?' }}</span>
                      <span class="text-gray-400 ml-1">{{ r.created_at?.slice(5,16).replace('T',' ') }}</span>
                      <span v-if="r.is_hidden" class="bg-gray-300 text-gray-700 px-1 rounded ml-1">숨김</span>
                      <div class="text-gray-700 mt-0.5" :class="r.is_hidden ? 'line-through text-gray-400' : ''">{{ r.content }}</div>
                    </div>
                    <div class="flex gap-1 shrink-0">
                      <button @click="toggleCommentHide(r)" class="text-[10px] text-orange-600">{{ r.is_hidden ? '공개' : '숨김' }}</button>
                      <button @click="deleteComment(r)" class="text-[10px] text-red-500">삭제</button>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- 포인트 내역 -->
        <div v-if="detailData?.point_logs?.length" class="px-4 py-3 border-t">
          <div class="text-xs font-bold text-gray-700 mb-2">💰 이 게시글 관련 포인트 ({{ detailData.point_logs.length }})</div>
          <div class="space-y-1 max-h-[120px] overflow-y-auto">
            <div v-for="pl in detailData.point_logs" :key="pl.id" class="flex justify-between text-[10px] py-0.5">
              <span class="text-gray-500">{{ pl.created_at?.slice(0,10) }}</span>
              <span class="text-gray-700 truncate flex-1 mx-2">{{ pl.reason }}</span>
              <span :class="pl.amount>0?'text-green-600':'text-red-600'" class="font-bold">{{ pl.amount>0?'+':'' }}{{ pl.amount }}P</span>
            </div>
          </div>
        </div>

        <!-- 신고 -->
        <div v-if="detailData?.reports?.length" class="px-4 py-3 border-t bg-red-50">
          <div class="text-xs font-bold text-red-700 mb-2">🚨 신고 {{ detailData.reports.length }}건</div>
          <div class="space-y-1 max-h-[100px] overflow-y-auto">
            <div v-for="r in detailData.reports" :key="r.id" class="text-[10px] text-red-700">
              <span class="font-medium">{{ r.reporter?.name || '?' }}</span>: {{ r.reason }}
              <span class="text-gray-500 ml-1">({{ r.status }})</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- 포인트 조정 모달 -->
  <div v-if="showPointModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" @click.self="showPointModal=false">
    <div class="bg-white rounded-xl p-5 w-full max-w-md">
      <h3 class="font-black text-gray-800 mb-3">💰 작성자 포인트 조정</h3>
      <div class="text-xs text-gray-600 mb-3">
        게시글: <strong>{{ activeItem?.title || activeItem?.name }}</strong><br>
        작성자: <strong>{{ activeItem?.user?.name }}</strong>
      </div>
      <div class="space-y-3">
        <div>
          <label class="text-xs text-gray-600">증감 (음수로 차감)</label>
          <input v-model.number="pointAmount" type="number" placeholder="예: 50 또는 -20" class="w-full border rounded px-2 py-1 text-sm" />
        </div>
        <div>
          <label class="text-xs text-gray-600">사유</label>
          <input v-model="pointReason" placeholder="예: 우수 콘텐츠 보상" class="w-full border rounded px-2 py-1 text-sm" />
        </div>
      </div>
      <div class="flex justify-end gap-2 mt-4">
        <button @click="showPointModal=false" class="text-gray-500 px-3 py-1 text-sm">취소</button>
        <button @click="applyPointAdjust" class="bg-amber-400 text-amber-900 font-bold px-4 py-1 rounded text-sm">적용</button>
      </div>
    </div>
  </div>

  <!-- 카테고리 변경 모달 -->
  <div v-if="showMoveModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" @click.self="showMoveModal=false">
    <div class="bg-white rounded-xl p-5 w-full max-w-sm">
      <h3 class="font-black text-gray-800 mb-3">📂 카테고리 변경</h3>
      <input v-model="newCategory" placeholder="카테고리 이름 또는 ID" class="w-full border rounded px-2 py-1 text-sm" />
      <div class="flex justify-end gap-2 mt-4">
        <button @click="showMoveModal=false" class="text-gray-500 px-3 py-1 text-sm">취소</button>
        <button @click="applyCategoryChange" class="bg-amber-400 text-amber-900 font-bold px-4 py-1 rounded text-sm">변경</button>
      </div>
    </div>
  </div>
</div>
</template>

<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import axios from 'axios'

const props = defineProps({
  icon: { type: String, default: '📋' },
  title: { type: String, required: true },
  apiUrl: { type: String, required: true },
  deleteUrl: { type: String, default: null },
  titleCol: { type: String, default: '제목' },
  extraCols: { type: Array, default: () => [] },
  // board-manager 모드: slug 주면 /api/admin/board-manager/{slug}/* 를 사용 (관리 액션 지원)
  boardSlug: { type: String, default: null },
  // 카테고리 필터 (외부에서 주입 — 카테고리 탭에서 "게시글 보기" 클릭 시)
  categoryFilter: { type: [String, Number], default: null },
  categoryFilterLabel: { type: String, default: '' },
})

const emit = defineEmits(['openUser', 'clearFilter'])

const items = ref([]); const loading = ref(true)
const page = ref(1); const lastPage = ref(1); const total = ref(0)
const search = ref(''); const activeItem = ref(null)
const detailData = ref(null)
const editMode = ref(false)

const showPointModal = ref(false)
const pointAmount = ref(0)
const pointReason = ref('관리자 수동 조정')

const showMoveModal = ref(false)
const newCategory = ref('')

const actions = computed(() => detailData.value?.actions || {})
const commentTotalCount = computed(() => {
  const cs = detailData.value?.comments || []
  return cs.reduce((sum, c) => sum + 1 + (c.replies?.length || 0), 0)
})

async function toggleCommentHide(c) {
  if (!props.boardSlug) return
  try {
    const { data } = await axios.post(`/api/admin/board-manager/${props.boardSlug}/comments/${c.id}/toggle`, { field: 'is_hidden' })
    c.is_hidden = data.data.is_hidden
  } catch (e) { alert(e.response?.data?.message || '실패') }
}

async function deleteComment(c) {
  if (!props.boardSlug) return
  if (!confirm('댓글을 삭제하시겠습니까? (답글도 함께 삭제됩니다)')) return
  try {
    await axios.delete(`/api/admin/board-manager/${props.boardSlug}/comments/${c.id}`)
    // 로컬 업데이트
    const comments = detailData.value?.comments || []
    for (let i = comments.length - 1; i >= 0; i--) {
      if (comments[i].id === c.id) { comments.splice(i, 1); return }
      comments[i].replies = (comments[i].replies || []).filter(r => r.id !== c.id)
    }
  } catch (e) { alert(e.response?.data?.message || '실패') }
}

function getNestedVal(obj, key) {
  return key.split('.').reduce((o, k) => o?.[k], obj) || '-'
}

function statusClass(s) {
  const map = {
    active:'bg-green-100 text-green-700', completed:'bg-blue-100 text-blue-700',
    pending:'bg-yellow-100 text-yellow-700', rejected:'bg-red-100 text-red-600',
    recruiting:'bg-green-100 text-green-700', confirmed:'bg-blue-100 text-blue-700',
    cancelled:'bg-gray-200 text-gray-500', sold:'bg-gray-100 text-gray-600',
    hidden:'bg-gray-200 text-gray-600',
  }
  return map[s] || 'bg-gray-100 text-gray-700'
}

async function openItem(item) {
  activeItem.value = item
  editMode.value = false
  detailData.value = null

  // board-manager 모드면 확장 상세 로드
  if (props.boardSlug) {
    try {
      const { data } = await axios.get(`/api/admin/board-manager/${props.boardSlug}/posts/${item.id}`)
      activeItem.value = data.data.item
      detailData.value = data.data
    } catch (e) { console.warn(e) }
  } else {
    // 기존 모드
    try { const { data } = await axios.get(`${props.apiUrl}/${item.id}`); activeItem.value = data.data || item }
    catch {}
  }
}

async function toggleField(field) {
  if (!props.boardSlug) return
  try {
    const { data } = await axios.post(`/api/admin/board-manager/${props.boardSlug}/posts/${activeItem.value.id}/toggle`, { field })
    activeItem.value[field] = data.data[field]
    // 리스트 항목도 갱신
    const idx = items.value.findIndex(x => x.id === activeItem.value.id)
    if (idx >= 0) items.value[idx][field] = data.data[field]
  } catch (e) { alert(e.response?.data?.message || '실패') }
}

async function savePost() {
  if (!props.boardSlug) { editMode.value = false; return }
  try {
    const payload = {
      title: activeItem.value.title,
      name: activeItem.value.name,
      content: activeItem.value.content,
      description: activeItem.value.description,
      price: activeItem.value.price,
      city: activeItem.value.city,
    }
    await axios.put(`/api/admin/board-manager/${props.boardSlug}/posts/${activeItem.value.id}`, payload)
    editMode.value = false
    alert('저장되었습니다')
  } catch (e) { alert(e.response?.data?.message || '저장 실패') }
}

function openPointModal() {
  pointAmount.value = 0
  pointReason.value = '관리자 수동 조정'
  showPointModal.value = true
}

async function applyPointAdjust() {
  if (!props.boardSlug || !pointAmount.value) return
  try {
    const { data } = await axios.post(
      `/api/admin/board-manager/${props.boardSlug}/posts/${activeItem.value.id}/points`,
      { amount: pointAmount.value, reason: pointReason.value }
    )
    alert(`${data.message}. 새 잔액: ${data.new_balance}P`)
    showPointModal.value = false
    openItem(activeItem.value) // 포인트 로그 갱신
  } catch (e) { alert(e.response?.data?.message || '실패') }
}

function openMoveModal() {
  newCategory.value = typeof activeItem.value.category === 'object' ? activeItem.value.category?.id : (activeItem.value.category || '')
  showMoveModal.value = true
}

async function applyCategoryChange() {
  if (!props.boardSlug) return
  try {
    await axios.post(`/api/admin/board-manager/${props.boardSlug}/posts/${activeItem.value.id}/category`, { category: newCategory.value })
    alert('카테고리가 변경되었습니다')
    showMoveModal.value = false
    openItem(activeItem.value)
  } catch (e) { alert(e.response?.data?.message || '실패') }
}

async function load(p=1) {
  loading.value = true; page.value = p
  const params = { page: p, per_page: 20 }
  if (search.value) params.search = search.value
  if (props.categoryFilter !== null && props.categoryFilter !== undefined && props.categoryFilter !== '') {
    params.category = props.categoryFilter
    params.category_id = props.categoryFilter
    params.board_id = props.categoryFilter
  }
  // board-manager 모드면 관리자 posts 엔드포인트 사용 (필터 지원)
  const url = props.boardSlug
    ? `/api/admin/board-manager/${props.boardSlug}/posts`
    : props.apiUrl
  try {
    const { data } = await axios.get(url, { params })
    items.value = data.data?.data || data.data || []
    lastPage.value = data.data?.last_page || 1
    total.value = data.data?.total || items.value.length
  } catch {}
  loading.value = false
}

// 카테고리 필터 변경 시 자동 재로드
watch(() => props.categoryFilter, () => load(1))

async function deleteItem(item) {
  if (!confirm('정말 삭제하시겠습니까?')) return
  const url = props.boardSlug
    ? `/api/admin/board-manager/${props.boardSlug}/posts/${item.id}`
    : `${props.deleteUrl || props.apiUrl}/${item.id}`
  try {
    await axios.delete(url)
    items.value = items.value.filter(x => x.id !== item.id)
    total.value--
    if (activeItem.value?.id === item.id) activeItem.value = null
  } catch (e) {
    alert(e.response?.data?.message || '삭제 실패')
  }
}

onMounted(() => load())
</script>
