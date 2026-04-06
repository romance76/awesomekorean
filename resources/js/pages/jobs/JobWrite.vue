<template>
<div class="min-h-screen bg-gray-50">
  <div class="max-w-3xl mx-auto px-4 py-5">
    <h1 class="text-xl font-black text-gray-800 mb-4">💼 채용공고 등록</h1>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 space-y-4">
      <div>
        <label class="text-sm font-semibold text-gray-700">제목</label>
        <input v-model="form.title" type="text" placeholder="예: 한식당 주방보조 구합니다" class="w-full border rounded-lg px-3 py-2 mt-1 text-sm focus:ring-2 focus:ring-amber-400 outline-none" />
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="text-sm font-semibold text-gray-700">회사명</label>
          <input v-model="form.company" type="text" class="w-full border rounded-lg px-3 py-2 mt-1 text-sm focus:ring-2 focus:ring-amber-400 outline-none" />
        </div>
        <div>
          <label class="text-sm font-semibold text-gray-700">카테고리</label>
          <select v-model="form.category" class="w-full border rounded-lg px-3 py-2 mt-1 text-sm focus:ring-2 focus:ring-amber-400 outline-none">
            <option v-for="c in categories" :key="c.value" :value="c.value">{{ c.label }}</option>
          </select>
        </div>
      </div>
      <div class="grid grid-cols-3 gap-3">
        <div>
          <label class="text-sm font-semibold text-gray-700">근무형태</label>
          <select v-model="form.type" class="w-full border rounded-lg px-3 py-2 mt-1 text-sm focus:ring-2 focus:ring-amber-400 outline-none">
            <option value="full">풀타임</option><option value="part">파트타임</option><option value="contract">계약직</option>
          </select>
        </div>
        <div>
          <label class="text-sm font-semibold text-gray-700">최소 급여</label>
          <input v-model.number="form.salary_min" type="number" class="w-full border rounded-lg px-3 py-2 mt-1 text-sm focus:ring-2 focus:ring-amber-400 outline-none" />
        </div>
        <div>
          <label class="text-sm font-semibold text-gray-700">최대 급여</label>
          <input v-model.number="form.salary_max" type="number" class="w-full border rounded-lg px-3 py-2 mt-1 text-sm focus:ring-2 focus:ring-amber-400 outline-none" />
        </div>
      </div>
      <div>
        <label class="text-sm font-semibold text-gray-700">급여 단위</label>
        <select v-model="form.salary_type" class="w-full border rounded-lg px-3 py-2 mt-1 text-sm focus:ring-2 focus:ring-amber-400 outline-none">
          <option value="hourly">시급</option><option value="monthly">월급</option><option value="yearly">연봉</option>
        </select>
      </div>
      <div>
        <label class="text-sm font-semibold text-gray-700">상세 내용</label>
        <textarea v-model="form.content" rows="8" placeholder="근무 조건, 우대사항 등을 자세히 작성해주세요" class="w-full border rounded-lg px-3 py-2 mt-1 text-sm focus:ring-2 focus:ring-amber-400 outline-none resize-none"></textarea>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div><label class="text-sm font-semibold text-gray-700">연락 전화</label><input v-model="form.contact_phone" type="text" class="w-full border rounded-lg px-3 py-2 mt-1 text-sm focus:ring-2 focus:ring-amber-400 outline-none" /></div>
        <div><label class="text-sm font-semibold text-gray-700">연락 이메일</label><input v-model="form.contact_email" type="email" class="w-full border rounded-lg px-3 py-2 mt-1 text-sm focus:ring-2 focus:ring-amber-400 outline-none" /></div>
      </div>
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
const form = reactive({ title:'',company:'',category:'restaurant',type:'full',salary_min:15,salary_max:25,salary_type:'hourly',content:'',contact_phone:'',contact_email:'' })
const categories = [
  {value:'restaurant',label:'요식업'},{value:'it',label:'IT'},{value:'beauty',label:'미용'},
  {value:'driving',label:'운전'},{value:'retail',label:'판매'},{value:'office',label:'사무직'},
  {value:'construction',label:'건설'},{value:'medical',label:'의료'},{value:'education',label:'교육'},{value:'etc',label:'기타'},
]
const error = ref('')
const submitting = ref(false)
async function submit() {
  if (!form.title || !form.company || !form.content) { error.value = '필수 항목을 입력해주세요'; return }
  submitting.value = true; error.value = ''
  try {
    const { data } = await axios.post('/api/jobs', form)
    router.push(`/jobs/${data.data.id}`)
  } catch (e) { error.value = e.response?.data?.message || '등록 실패' }
  submitting.value = false
}
</script>
