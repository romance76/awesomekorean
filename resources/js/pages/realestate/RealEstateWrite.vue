<template>
<div class="min-h-screen bg-gray-50">
  <div class="max-w-3xl mx-auto px-4 py-5 space-y-4">
    <h1 class="text-xl font-black text-gray-800">🏠 매물 등록</h1>

    <!-- 상위노출 (최상단) -->
    <PromotionSection resource="realestate" :is-edit="false"
      :category="form.type" :state="userState"
      v-model="promotion" ref="promoRef"
      category-label="유형 (렌트/매매/룸메이트)" />

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 space-y-4">
      <div><label class="text-sm font-semibold text-gray-700">제목</label><input v-model="form.title" type="text" placeholder="예: LA 1BR 아파트 렌트" class="w-full border rounded-lg px-3 py-2 mt-1 text-sm focus:ring-2 focus:ring-amber-400 outline-none" /></div>
      <div class="grid grid-cols-2 gap-3">
        <div><label class="text-sm font-semibold text-gray-700">유형</label>
          <select v-model="form.type" class="w-full border rounded-lg px-3 py-2 mt-1 text-sm focus:ring-2 focus:ring-amber-400 outline-none">
            <option value="rent">렌트</option><option value="sale">매매</option><option value="roommate">룸메이트</option>
          </select></div>
        <div><label class="text-sm font-semibold text-gray-700">종류</label>
          <select v-model="form.property_type" class="w-full border rounded-lg px-3 py-2 mt-1 text-sm focus:ring-2 focus:ring-amber-400 outline-none">
            <option value="apt">아파트</option><option value="house">하우스</option><option value="condo">콘도</option><option value="studio">스튜디오</option><option value="office">오피스</option>
          </select></div>
      </div>
      <div class="grid grid-cols-4 gap-3">
        <div><label class="text-sm font-semibold text-gray-700">가격 ($)</label><input v-model.number="form.price" type="number" class="w-full border rounded-lg px-3 py-2 mt-1 text-sm focus:ring-2 focus:ring-amber-400 outline-none" /></div>
        <div><label class="text-sm font-semibold text-gray-700">방</label><input v-model.number="form.bedrooms" type="number" min="0" class="w-full border rounded-lg px-3 py-2 mt-1 text-sm focus:ring-2 focus:ring-amber-400 outline-none" /></div>
        <div><label class="text-sm font-semibold text-gray-700">화장실</label><input v-model.number="form.bathrooms" type="number" min="0" class="w-full border rounded-lg px-3 py-2 mt-1 text-sm focus:ring-2 focus:ring-amber-400 outline-none" /></div>
        <div><label class="text-sm font-semibold text-gray-700">면적(sqft)</label><input v-model.number="form.sqft" type="number" class="w-full border rounded-lg px-3 py-2 mt-1 text-sm focus:ring-2 focus:ring-amber-400 outline-none" /></div>
      </div>
      <div><label class="text-sm font-semibold text-gray-700">상세 설명</label><textarea v-model="form.content" rows="6" placeholder="위치, 시설, 조건 등을 자세히 작성해주세요" class="w-full border rounded-lg px-3 py-2 mt-1 text-sm focus:ring-2 focus:ring-amber-400 outline-none resize-none"></textarea></div>
      <div class="grid grid-cols-2 gap-3">
        <div><label class="text-sm font-semibold text-gray-700">연락 전화</label><input v-model="form.contact_phone" type="text" class="w-full border rounded-lg px-3 py-2 mt-1 text-sm focus:ring-2 focus:ring-amber-400 outline-none" /></div>
        <div><label class="text-sm font-semibold text-gray-700">연락 이메일</label><input v-model="form.contact_email" type="email" class="w-full border rounded-lg px-3 py-2 mt-1 text-sm focus:ring-2 focus:ring-amber-400 outline-none" /></div>
      </div>
      <div v-if="error" class="text-red-500 text-sm">{{ error }}</div>
      <div class="flex gap-3 pt-2">
        <button @click="submit" :disabled="submitting" class="bg-amber-400 text-amber-900 font-bold px-6 py-2.5 rounded-lg hover:bg-amber-500 disabled:opacity-50">{{ submitting ? '등록 중...' : '매물 등록' }}</button>
        <button @click="$router.back()" class="text-gray-500 px-6 py-2.5">취소</button>
      </div>
    </div>
  </div>
</div>
</template>
<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import axios from 'axios'
import PromotionSection from '../../components/PromotionSection.vue'
import { useAuthStore } from '../../stores/auth'
const router = useRouter()
const route = useRoute()
const auth = useAuthStore()
const form = reactive({ title:'',type:'rent',property_type:'apt',price:0,bedrooms:1,bathrooms:1,sqft:0,content:'',contact_phone:'',contact_email:'' })
const promotion = reactive({ tier: 'none', days: 7 })
const promoRef = ref(null)
const userState = computed(() => auth.user?.state || '')
const error = ref('')
const submitting = ref(false)
const isEdit = ref(false)
const editId = ref(null)

async function submit() {
  if (!form.title || !form.content || !form.price) { error.value = '필수 항목을 입력해주세요'; return }
  if (['state_plus','national'].includes(promotion.tier) && promoRef.value?.isSlotFull) {
    const t = promoRef.value?.nextSlotTimeFmt
    error.value = t ? `상위노출 슬롯 만석. ${t} 이후 가능합니다.` : '상위노출 슬롯 만석.'
    return
  }
  submitting.value = true; error.value = ''
  try {
    if (isEdit.value) {
      await axios.put(`/api/realestate/${editId.value}`, form)
      router.push(`/realestate/${editId.value}`)
    } else {
      const { data } = await axios.post('/api/realestate', form)
      const createdId = data?.data?.id
      if (createdId && promotion.tier !== 'none') {
        try { await axios.post(`/api/realestate/${createdId}/promote`, { tier: promotion.tier, days: promotion.days }) } catch {}
      }
      router.push(`/realestate/${createdId}`)
    }
  } catch (e) { error.value = e.response?.data?.message || '등록 실패' }
  submitting.value = false
}

onMounted(async () => {
  if (route.query.edit) {
    editId.value = route.query.edit; isEdit.value = true
    try {
      const { data } = await axios.get(`/api/realestate/${editId.value}`)
      const r = data.data; Object.keys(form).forEach(k => { if (r[k] !== undefined) form[k] = r[k] })
    } catch {}
  }
})
</script>
