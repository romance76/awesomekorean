<template>
<div class="min-h-screen bg-gray-50">
  <div class="max-w-3xl mx-auto px-4 py-5">
    <h1 class="text-xl font-black text-gray-800 mb-4">🛒 물품 등록</h1>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 space-y-4">
      <div><label class="text-sm font-semibold text-gray-700">제목</label><input v-model="form.title" type="text" placeholder="예: 아이폰 15 Pro 판매합니다" class="w-full border rounded-lg px-3 py-2 mt-1 text-sm focus:ring-2 focus:ring-amber-400 outline-none" /></div>
      <div class="grid grid-cols-3 gap-3">
        <div><label class="text-sm font-semibold text-gray-700">가격 ($)</label><input v-model.number="form.price" type="number" class="w-full border rounded-lg px-3 py-2 mt-1 text-sm focus:ring-2 focus:ring-amber-400 outline-none" /></div>
        <div>
          <label class="text-sm font-semibold text-gray-700">카테고리</label>
          <select v-model="form.category" class="w-full border rounded-lg px-3 py-2 mt-1 text-sm focus:ring-2 focus:ring-amber-400 outline-none">
            <option v-for="c in ['electronics','furniture','clothing','auto','baby','sports','books','etc']" :key="c" :value="c">{{ {electronics:'전자기기',furniture:'가구',clothing:'의류',auto:'자동차',baby:'유아',sports:'스포츠',books:'도서',etc:'기타'}[c] }}</option>
          </select>
        </div>
        <div>
          <label class="text-sm font-semibold text-gray-700">상태</label>
          <select v-model="form.condition" class="w-full border rounded-lg px-3 py-2 mt-1 text-sm focus:ring-2 focus:ring-amber-400 outline-none">
            <option value="new">새상품</option><option value="like_new">거의 새것</option><option value="good">양호</option><option value="fair">보통</option>
          </select>
        </div>
      </div>
      <div class="flex items-center gap-2"><input v-model="form.is_negotiable" type="checkbox" class="rounded" /><span class="text-sm text-gray-600">가격 협의 가능</span></div>
      <div><label class="text-sm font-semibold text-gray-700">상세 설명</label><textarea v-model="form.content" rows="6" placeholder="상품 상태, 거래 방법 등을 자세히 작성해주세요" class="w-full border rounded-lg px-3 py-2 mt-1 text-sm focus:ring-2 focus:ring-amber-400 outline-none resize-none"></textarea></div>
      <div><label class="text-sm font-semibold text-gray-700">사진 (선택)</label><input type="file" multiple accept="image/*" @change="e => files = Array.from(e.target.files)" class="w-full border rounded-lg px-3 py-2 mt-1 text-sm" /></div>
      <div v-if="error" class="text-red-500 text-sm">{{ error }}</div>
      <div class="flex gap-3 pt-2">
        <button @click="submit" :disabled="submitting" class="bg-amber-400 text-amber-900 font-bold px-6 py-2.5 rounded-lg hover:bg-amber-500 disabled:opacity-50">{{ submitting ? '등록 중...' : '등록하기' }}</button>
        <button @click="$router.back()" class="text-gray-500 px-6 py-2.5">취소</button>
      </div>
    </div>
  </div>
</div>
</template>
<script setup>
import { ref, reactive } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'
const router = useRouter()
const form = reactive({ title:'',price:0,category:'electronics',condition:'good',content:'',is_negotiable:false })
const files = ref([])
const error = ref('')
const submitting = ref(false)
async function submit() {
  if (!form.title || !form.content) { error.value = '제목과 설명을 입력해주세요'; return }
  submitting.value = true; error.value = ''
  try {
    const fd = new FormData()
    Object.keys(form).forEach(k => fd.append(k, form[k]))
    files.value.forEach(f => fd.append('images[]', f))
    const { data } = await axios.post('/api/market', fd, { headers: { 'Content-Type': 'multipart/form-data' } })
    router.push(`/market/${data.data.id}`)
  } catch (e) { error.value = e.response?.data?.message || '등록 실패' }
  submitting.value = false
}
</script>
