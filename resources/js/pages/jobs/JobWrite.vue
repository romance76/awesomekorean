<template>
  <div class="max-w-[1200px] mx-auto px-4 py-6">
    <h1 class="text-xl font-bold text-gray-800 mb-5">구인구직 공고 등록</h1>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
      <div class="space-y-5">
        <!-- 제목 -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">제목 *</label>
          <input v-model="form.title" type="text" placeholder="예: 한식당 홀서빙 직원 구합니다" maxlength="200"
            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-500" />
        </div>

        <!-- 회사명 / 직종 -->
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">회사명</label>
            <input v-model="form.company_name" type="text" placeholder="비공개 가능"
              class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-500" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">직종</label>
            <select v-model="form.job_type" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-500">
              <option value="">선택</option>
              <option>정규직</option><option>파트타임</option><option>인턴</option><option>계약직</option><option>프리랜서</option>
            </select>
          </div>
        </div>

        <!-- 지역 / 급여 -->
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">지역</label>
            <select v-model="form.region" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-500">
              <option value="">선택</option>
              <option>Atlanta</option><option>New York</option><option>Los Angeles</option>
              <option>Dallas</option><option>Chicago</option><option>Seattle</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">급여</label>
            <input v-model="form.salary_range" type="text" placeholder="예: $15/hr, $3,000/월"
              class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-500" />
          </div>
        </div>

        <!-- 주소 -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">주소</label>
          <AddressInput v-model="form.addressObj" />
        </div>

        <!-- 사진 업로드 (최대 5장) -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">사진 첨부 (최대 5장)</label>
          <div class="grid grid-cols-4 sm:grid-cols-5 lg:grid-cols-6 gap-2">
            <div v-for="i in 5" :key="i" class="relative">
              <div v-if="photos[i-1]" class="h-24 rounded-xl overflow-hidden relative group">
                <img :src="photos[i-1].preview" class="w-full h-full object-cover" />
                <button @click="removePhoto(i-1)" class="absolute top-1 right-1 w-6 h-6 bg-red-500 text-white rounded-full text-xs opacity-0 group-hover:opacity-100 transition-opacity">✕</button>
              </div>
              <label v-else class="h-24 rounded-xl border-2 border-dashed border-gray-300 flex flex-col items-center justify-center cursor-pointer hover:border-blue-400 hover:bg-blue-50 transition-colors">
                <span class="text-2xl text-gray-400">+</span>
                <span class="text-xs text-gray-400 mt-1">사진 {{ i }}</span>
                <input type="file" accept="image/*" class="hidden" @change="addPhoto($event, i-1)" />
              </label>
            </div>
          </div>
        </div>

        <!-- 파일 첨부 (이력서/서류) -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">파일 첨부 (이력서/서류)</label>
          <div v-if="attachment" class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg border border-gray-200">
            <svg class="w-5 h-5 text-gray-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" /></svg>
            <span class="text-sm text-gray-700 truncate flex-1">{{ attachment.name }}</span>
            <button @click="removeAttachment" class="text-red-500 text-xs hover:underline shrink-0">삭제</button>
          </div>
          <label v-else class="flex items-center gap-2 p-3 rounded-lg border-2 border-dashed border-gray-300 cursor-pointer hover:border-blue-400 hover:bg-blue-50 transition-colors">
            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" /></svg>
            <span class="text-sm text-gray-400">PDF, DOC, DOCX 파일 첨부</span>
            <input type="file" accept=".pdf,.doc,.docx" class="hidden" @change="addAttachment" />
          </label>
        </div>

        <!-- 외부 링크 -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">외부 링크</label>
          <input v-model="form.external_link" type="url" placeholder="https:// 회사 웹사이트 또는 지원 링크"
            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-500" />
        </div>

        <!-- 상세 내용 -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">상세 내용 *</label>
          <textarea v-model="form.content" rows="10" placeholder="업무 내용, 자격 요건, 복리후생 등을 자세히 입력해주세요"
            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-500 resize-none"></textarea>
        </div>

        <!-- 연락처 정보 -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">연락처 정보</label>
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
              <label class="block text-xs text-gray-500 mb-1">전화번호</label>
              <input v-model="form.contact_phone" type="tel" placeholder="000-000-0000"
                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-500" />
            </div>
            <div>
              <label class="block text-xs text-gray-500 mb-1">이메일</label>
              <input v-model="form.contact_email" type="email" placeholder="example@email.com"
                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-500" />
            </div>
            <div>
              <label class="block text-xs text-gray-500 mb-1">웹사이트</label>
              <input v-model="form.contact_website" type="url" placeholder="https://"
                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-500" />
            </div>
          </div>
        </div>

        <!-- 에러 -->
        <div v-if="error" class="text-red-600 text-sm bg-red-50 p-3 rounded-lg">{{ error }}</div>

        <!-- 버튼 -->
        <div class="flex justify-end space-x-3">
          <button @click="$router.back()" class="px-5 py-2.5 border border-gray-300 text-gray-600 rounded-lg text-sm hover:bg-gray-50">취소</button>
          <button @click="submit" :disabled="loading" class="px-5 py-2.5 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700 disabled:opacity-50">
            {{ loading ? '등록 중...' : '공고 등록' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';
import AddressInput from '../../components/AddressInput.vue';

const router = useRouter();
const loading = ref(false);
const error = ref('');

const form = ref({
  title: '', company_name: '', job_type: '', region: '', salary_range: '',
  address: '', content: '', contact_email: '', contact_phone: '',
  contact_website: '', external_link: '',
  addressObj: { address1: '', address2: '', city: '', state: '', zip: '', full: '' }
});

// Photos (max 5)
const photos = reactive([]);
function addPhoto(event, index) {
  const file = event.target.files[0];
  if (!file) return;
  photos[index] = { file, preview: URL.createObjectURL(file) };
}
function removePhoto(index) {
  if (photos[index]?.preview) URL.revokeObjectURL(photos[index].preview);
  photos[index] = null;
}

// File attachment
const attachment = ref(null);
function addAttachment(event) {
  const file = event.target.files[0];
  if (!file) return;
  attachment.value = file;
}
function removeAttachment() {
  attachment.value = null;
}

async function submit() {
  if (!form.value.title.trim()) { error.value = '제목을 입력하세요.'; return; }
  if (!form.value.content.trim()) { error.value = '내용을 입력하세요.'; return; }
  loading.value = true; error.value = '';
  try {
    const fd = new FormData();
    // Set address from addressObj
    form.value.address = form.value.addressObj.full || '';
    // Append form fields
    Object.keys(form.value).forEach(key => {
      if (key === 'addressObj') return;
      if (form.value[key] !== null && form.value[key] !== '') {
        fd.append(key, form.value[key]);
      }
    });
    // Append photos
    photos.forEach((photo, i) => {
      if (photo?.file) fd.append('photos[]', photo.file);
    });
    // Append attachment
    if (attachment.value) {
      fd.append('attachment', attachment.value);
    }

    const { data } = await axios.post('/api/jobs', fd, {
      headers: { 'Content-Type': 'multipart/form-data' }
    });
    router.push(`/jobs/${data.job.id}`);
  } catch(e) {
    error.value = e.response?.data?.message || '오류가 발생했습니다.';
  } finally { loading.value = false; }
}
</script>
