<template>
  <div class="max-w-[1200px] mx-auto px-4 py-6">
    <h1 class="text-xl font-bold text-gray-800 mb-5">업소 등록</h1>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
      <div class="space-y-5">
        <!-- 업소명 -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">업소명 *</label>
          <input v-model="form.name" type="text" placeholder="업소명 입력" maxlength="100"
            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-500" />
        </div>

        <!-- 카테고리 / 지역 -->
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">카테고리 *</label>
            <select v-model="form.category" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-500">
              <option value="">선택</option>
              <option>식당</option><option>미용</option><option>의료</option><option>법률</option>
              <option>부동산</option><option>쇼핑</option><option>교육</option><option>기타</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">지역</label>
            <select v-model="form.region" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-500">
              <option value="">선택</option>
              <option>Atlanta</option><option>New York</option><option>Los Angeles</option>
              <option>Dallas</option><option>Chicago</option>
            </select>
          </div>
        </div>

        <!-- 사진 업로드 (로고 + 내/외부 사진) -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">업소 사진</label>
          <p class="text-xs text-gray-500 mb-3">첫 번째 사진은 로고/대표 이미지로 사용됩니다.</p>
          <div class="grid grid-cols-4 sm:grid-cols-5 lg:grid-cols-6 gap-2">
            <div v-for="i in 6" :key="i" class="relative">
              <div v-if="photos[i-1]" class="h-24 rounded-xl overflow-hidden relative group">
                <img :src="photos[i-1].preview" class="w-full h-full object-cover" />
                <button @click="removePhoto(i-1)" class="absolute top-1 right-1 w-6 h-6 bg-red-500 text-white rounded-full text-xs opacity-0 group-hover:opacity-100 transition-opacity">✕</button>
                <span v-if="i === 1" class="absolute bottom-1 left-1 bg-blue-600 text-white text-[10px] px-1.5 py-0.5 rounded-full font-medium">로고</span>
              </div>
              <label v-else class="h-24 rounded-xl border-2 border-dashed flex flex-col items-center justify-center cursor-pointer transition-colors"
                :class="i === 1 ? 'border-blue-300 hover:border-blue-400 hover:bg-blue-50' : 'border-gray-300 hover:border-blue-400 hover:bg-blue-50'">
                <span class="text-2xl" :class="i === 1 ? 'text-blue-400' : 'text-gray-400'">+</span>
                <span class="text-[10px] mt-1" :class="i === 1 ? 'text-blue-500 font-medium' : 'text-gray-400'">
                  {{ i === 1 ? '로고/대표' : (i <= 3 ? '내부 사진' : '외부 사진') }}
                </span>
                <input type="file" accept="image/*" class="hidden" @change="addPhoto($event, i-1)" />
              </label>
            </div>
          </div>
        </div>

        <!-- 주소 -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">주소 *</label>
          <AddressInput v-model="form.addressObj" />
        </div>

        <!-- 연락처 -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">연락처 정보</label>
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
              <label class="block text-xs text-gray-500 mb-1">전화번호</label>
              <input v-model="form.phone" type="tel" placeholder="000-000-0000"
                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-500" />
            </div>
            <div>
              <label class="block text-xs text-gray-500 mb-1">이메일</label>
              <input v-model="form.email" type="email" placeholder="example@email.com"
                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-500" />
            </div>
            <div>
              <label class="block text-xs text-gray-500 mb-1">웹사이트</label>
              <input v-model="form.website" type="url" placeholder="https://"
                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-500" />
            </div>
          </div>
        </div>

        <!-- 영업시간 -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">영업시간</label>
          <div class="space-y-2 bg-gray-50 rounded-xl p-4 border border-gray-100">
            <div v-for="day in days" :key="day.key" class="flex items-center gap-2">
              <span class="w-8 text-sm font-bold text-gray-700">{{ day.label }}</span>
              <input type="time" v-model="hours[day.key].open" :disabled="hours[day.key].closed"
                class="border border-gray-300 rounded-lg px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-500 disabled:bg-gray-200 disabled:text-gray-400" />
              <span class="text-gray-400 text-sm">~</span>
              <input type="time" v-model="hours[day.key].close" :disabled="hours[day.key].closed"
                class="border border-gray-300 rounded-lg px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-500 disabled:bg-gray-200 disabled:text-gray-400" />
              <label class="flex items-center gap-1 text-sm text-gray-500 ml-2 cursor-pointer">
                <input type="checkbox" v-model="hours[day.key].closed" class="rounded text-red-600" />
                휴무
              </label>
            </div>
          </div>
        </div>

        <!-- 업소 소개 -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">업소 소개</label>
          <textarea v-model="form.description" rows="5" placeholder="업소 소개, 특징, 주요 서비스 등을 입력하세요"
            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-500 resize-none"></textarea>
        </div>

        <!-- 에러 -->
        <div v-if="error" class="text-red-600 text-sm bg-red-50 p-3 rounded-lg">{{ error }}</div>

        <!-- 버튼 -->
        <div class="flex justify-end space-x-3">
          <button @click="$router.back()" class="px-5 py-2.5 border border-gray-300 text-gray-600 rounded-lg text-sm hover:bg-gray-50">취소</button>
          <button @click="submit" :disabled="loading" class="px-5 py-2.5 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700 disabled:opacity-50">
            {{ loading ? '등록 중...' : '업소 등록' }}
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
  name: '', category: '', region: '', address: '',
  phone: '', email: '', website: '', description: '',
  addressObj: { address1: '', address2: '', city: '', state: '', zip: '', full: '' }
});

// Days of week
const days = [
  { key: 'mon', label: '월' },
  { key: 'tue', label: '화' },
  { key: 'wed', label: '수' },
  { key: 'thu', label: '목' },
  { key: 'fri', label: '금' },
  { key: 'sat', label: '토' },
  { key: 'sun', label: '일' },
];

const hours = reactive({
  mon: { open: '09:00', close: '18:00', closed: false },
  tue: { open: '09:00', close: '18:00', closed: false },
  wed: { open: '09:00', close: '18:00', closed: false },
  thu: { open: '09:00', close: '18:00', closed: false },
  fri: { open: '09:00', close: '18:00', closed: false },
  sat: { open: '10:00', close: '15:00', closed: false },
  sun: { open: '', close: '', closed: true },
});

// Photos (max 6: logo + interior/exterior)
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

async function submit() {
  if (!form.value.name.trim()) { error.value = '업소명을 입력하세요.'; return; }
  if (!form.value.category) { error.value = '카테고리를 선택하세요.'; return; }
  if (!form.value.addressObj.address1?.trim()) { error.value = '주소를 입력하세요.'; return; }
  loading.value = true; error.value = '';
  try {
    const fd = new FormData();
    form.value.address = form.value.addressObj.full || '';
    Object.keys(form.value).forEach(key => {
      if (key === 'addressObj') return;
      if (form.value[key] !== null && form.value[key] !== '') {
        fd.append(key, form.value[key]);
      }
    });
    // Append operating hours as JSON
    fd.append('operating_hours', JSON.stringify(hours));
    // Append photos
    photos.forEach((photo) => {
      if (photo?.file) fd.append('photos[]', photo.file);
    });

    const { data } = await axios.post('/api/businesses', fd, {
      headers: { 'Content-Type': 'multipart/form-data' }
    });
    router.push(`/directory/${data.business.id}`);
  } catch(e) {
    error.value = e.response?.data?.message || '오류가 발생했습니다.';
  } finally { loading.value = false; }
}
</script>
