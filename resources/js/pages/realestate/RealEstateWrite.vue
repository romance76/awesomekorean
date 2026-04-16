<template>
<div class="min-h-screen bg-gray-50">
  <div class="max-w-3xl mx-auto px-4 py-5 space-y-4">
    <h1 class="text-xl font-black text-gray-800">🏠 매물 등록</h1>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 space-y-4">
      <div><label class="text-sm font-semibold text-gray-700">제목</label><input v-model="form.title" type="text" placeholder="예: LA 1BR 아파트 렌트" class="w-full border rounded-lg px-3 py-2 mt-1 text-sm focus:ring-2 focus:ring-amber-400 outline-none" /></div>
      <div class="grid grid-cols-2 gap-3">
        <div><label class="text-sm font-semibold text-gray-700">유형</label>
          <select v-model="form.type" class="w-full border rounded-lg px-3 py-2 mt-1 text-sm focus:ring-2 focus:ring-amber-400 outline-none">
            <option value="rent">렌트</option><option value="sale">매매</option><option value="roommate">룸메이트</option>
          </select></div>
        <div><label class="text-sm font-semibold text-gray-700">종류</label>
          <select v-model="form.property_type" class="w-full border rounded-lg px-3 py-2 mt-1 text-sm focus:ring-2 focus:ring-amber-400 outline-none">
            <optgroup label="주거용">
              <option value="studio">스튜디오</option>
              <option value="1br">1BR</option>
              <option value="2br">2BR</option>
              <option value="3br_plus">3BR 이상</option>
              <option value="roommate">룸메이트</option>
              <option value="minbak">민박</option>
              <option value="etc_home">기타</option>
            </optgroup>
            <optgroup label="상업용">
              <option v-if="form.type==='rent'" value="office_rent">오피스</option>
              <option v-if="form.type==='rent'" value="retail_rent">소매</option>
              <option v-if="form.type==='rent'" value="store_rent">상가</option>
              <option v-if="form.type==='rent'" value="building_rent">건물</option>
              <option v-if="form.type==='sale'" value="office_sale">오피스</option>
              <option v-if="form.type==='sale'" value="retail_sale">소매</option>
              <option v-if="form.type==='sale'" value="store_sale">상가</option>
              <option v-if="form.type==='sale'" value="building">건물</option>
              <option value="etc_commercial">기타</option>
            </optgroup>
          </select></div>
      </div>
      <div class="grid grid-cols-4 gap-3">
        <div><label class="text-sm font-semibold text-gray-700">가격 ($)</label><input v-model.number="form.price" type="number" class="w-full border rounded-lg px-3 py-2 mt-1 text-sm focus:ring-2 focus:ring-amber-400 outline-none" /></div>
        <div><label class="text-sm font-semibold text-gray-700">방</label><input v-model.number="form.bedrooms" type="number" min="0" class="w-full border rounded-lg px-3 py-2 mt-1 text-sm focus:ring-2 focus:ring-amber-400 outline-none" /></div>
        <div><label class="text-sm font-semibold text-gray-700">화장실</label><input v-model.number="form.bathrooms" type="number" min="0" class="w-full border rounded-lg px-3 py-2 mt-1 text-sm focus:ring-2 focus:ring-amber-400 outline-none" /></div>
        <div><label class="text-sm font-semibold text-gray-700">면적(sqft)</label><input v-model.number="form.sqft" type="number" class="w-full border rounded-lg px-3 py-2 mt-1 text-sm focus:ring-2 focus:ring-amber-400 outline-none" /></div>
      </div>

      <!-- 매물 주소 -->
      <div class="bg-amber-50/50 border border-amber-200 rounded-lg p-3 space-y-2">
        <label class="text-sm font-semibold text-gray-700">📍 매물 주소</label>
        <input v-model="form.address" type="text" placeholder="예: 123 Main St" class="w-full border rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-amber-400" />
        <div class="grid grid-cols-3 gap-2">
          <input v-model="form.city" type="text" placeholder="City" class="border rounded-lg px-3 py-2 text-sm outline-none" />
          <input v-model="form.state" type="text" maxlength="2" placeholder="State (GA)" class="border rounded-lg px-3 py-2 text-sm outline-none uppercase" />
          <input v-model="form.zipcode" type="text" maxlength="5" placeholder="Zip" @input="onReZipChange" class="border rounded-lg px-3 py-2 text-sm outline-none font-mono" />
        </div>
      </div>

      <!-- 상위노출 (주소 다음, 설명 이전) -->
      <PromotionSection resource="realestate" :is-edit="false"
        :category="form.type" :state="form.state || userState"
        v-model="promotion" ref="promoRef"
        category-label="유형 (렌트/매매/룸메이트)" />

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
const form = reactive({ title:'',type:'rent',property_type:'apt',price:0,bedrooms:1,bathrooms:1,sqft:0,content:'',contact_phone:'',contact_email:'', address:'', city:'', state:'', zipcode:'' })

let reZipTimer = null
function onReZipChange() {
  clearTimeout(reZipTimer)
  reZipTimer = setTimeout(async () => {
    const z = (form.zipcode || '').trim()
    if (!/^\d{5}$/.test(z)) return
    try {
      const r = await fetch(`https://api.zippopotam.us/us/${z}`)
      if (!r.ok) return
      const d = await r.json()
      const p = d.places?.[0]
      if (p) {
        if (!form.city) form.city = p['place name'] || ''
        if (!form.state) form.state = p['state abbreviation'] || ''
      }
    } catch {}
  }, 500)
}
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
