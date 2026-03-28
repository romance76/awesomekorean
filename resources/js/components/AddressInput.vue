<template>
  <div class="space-y-2">
    <div>
      <label class="block text-xs text-gray-500 mb-1">주소 (Address Line 1) *</label>
      <input v-model="addr.address1" type="text" placeholder="123 Main Street"
        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-blue-400" />
    </div>
    <div>
      <label class="block text-xs text-gray-500 mb-1">상세주소 (Address Line 2)</label>
      <input v-model="addr.address2" type="text" placeholder="Apt 4B, Suite 200..."
        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-blue-400" />
    </div>
    <div class="grid grid-cols-3 gap-2">
      <div class="relative">
        <label class="block text-xs text-gray-500 mb-1">도시 (City) *</label>
        <input v-model="addr.city" type="text" placeholder="Atlanta"
          @input="onCityInput"
          @focus="onCityInput"
          @blur="hideCitySuggestions"
          autocomplete="off"
          class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-blue-400" />
        <div v-if="citySuggestions.length" class="absolute z-50 bg-white border border-gray-200 rounded-lg shadow-lg max-h-40 overflow-y-auto w-full mt-1">
          <div v-for="s in citySuggestions" :key="s.city + s.state"
            @mousedown.prevent="selectCity(s)"
            class="px-3 py-2 text-sm hover:bg-blue-50 cursor-pointer">
            {{ s.city }}, {{ s.state }}
          </div>
        </div>
      </div>
      <div>
        <label class="block text-xs text-gray-500 mb-1">주 (State) *</label>
        <select v-model="addr.state"
          class="w-full border border-gray-200 rounded-lg px-2 py-2 text-sm focus:outline-none focus:border-blue-400">
          <option value="">선택</option>
          <option v-for="s in states" :key="s" :value="s">{{ s }}</option>
        </select>
      </div>
      <div>
        <label class="block text-xs text-gray-500 mb-1">우편번호 (ZIP)</label>
        <input v-model="addr.zip" type="text" placeholder="30301" maxlength="10"
          class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-blue-400" />
      </div>
    </div>
  </div>
</template>

<script setup>
import { reactive, ref, watch } from 'vue';

const props = defineProps({
  modelValue: {
    type: Object,
    default: () => ({ address1: '', address2: '', city: '', state: '', zip: '', full: '' })
  }
});

const emit = defineEmits(['update:modelValue']);

const states = [
  'AL','AK','AZ','AR','CA','CO','CT','DE','DC','FL',
  'GA','HI','ID','IL','IN','IA','KS','KY','LA','ME',
  'MD','MA','MI','MN','MS','MO','MT','NE','NV','NH',
  'NJ','NM','NY','NC','ND','OH','OK','OR','PA','RI',
  'SC','SD','TN','TX','UT','VT','VA','WA','WV','WI','WY'
];

const addr = reactive({
  address1: props.modelValue?.address1 || '',
  address2: props.modelValue?.address2 || '',
  city: props.modelValue?.city || '',
  state: props.modelValue?.state || '',
  zip: props.modelValue?.zip || '',
});

const citySuggestions = ref([]);

const majorCities = [
  // Korean-American major areas
  { city: 'Los Angeles', state: 'CA' },
  { city: 'Koreatown', state: 'CA' },
  { city: 'Garden Grove', state: 'CA' },
  { city: 'Fullerton', state: 'CA' },
  { city: 'Irvine', state: 'CA' },
  { city: 'Torrance', state: 'CA' },
  { city: 'Cerritos', state: 'CA' },
  { city: 'Buena Park', state: 'CA' },
  { city: 'San Francisco', state: 'CA' },
  { city: 'San Jose', state: 'CA' },
  { city: 'San Diego', state: 'CA' },
  { city: 'Oakland', state: 'CA' },
  { city: 'Sacramento', state: 'CA' },
  { city: 'Riverside', state: 'CA' },
  { city: 'Anaheim', state: 'CA' },
  { city: 'Long Beach', state: 'CA' },
  { city: 'Santa Clara', state: 'CA' },
  { city: 'Fremont', state: 'CA' },
  { city: 'New York', state: 'NY' },
  { city: 'Flushing', state: 'NY' },
  { city: 'Bayside', state: 'NY' },
  { city: 'Fort Lee', state: 'NJ' },
  { city: 'Palisades Park', state: 'NJ' },
  { city: 'Leonia', state: 'NJ' },
  { city: 'Edgewater', state: 'NJ' },
  { city: 'Ridgefield', state: 'NJ' },
  { city: 'Bergen County', state: 'NJ' },
  { city: 'Edison', state: 'NJ' },
  { city: 'Cherry Hill', state: 'NJ' },
  { city: 'Atlanta', state: 'GA' },
  { city: 'Duluth', state: 'GA' },
  { city: 'Suwanee', state: 'GA' },
  { city: 'Johns Creek', state: 'GA' },
  { city: 'Lawrenceville', state: 'GA' },
  { city: 'Doraville', state: 'GA' },
  { city: 'Norcross', state: 'GA' },
  { city: 'Annandale', state: 'VA' },
  { city: 'Centreville', state: 'VA' },
  { city: 'Fairfax', state: 'VA' },
  { city: 'Vienna', state: 'VA' },
  { city: 'Arlington', state: 'VA' },
  { city: 'Washington', state: 'DC' },
  { city: 'Ellicott City', state: 'MD' },
  { city: 'Columbia', state: 'MD' },
  { city: 'Rockville', state: 'MD' },
  { city: 'Bethesda', state: 'MD' },
  // Major US cities
  { city: 'Chicago', state: 'IL' },
  { city: 'Houston', state: 'TX' },
  { city: 'Dallas', state: 'TX' },
  { city: 'Austin', state: 'TX' },
  { city: 'San Antonio', state: 'TX' },
  { city: 'Fort Worth', state: 'TX' },
  { city: 'Plano', state: 'TX' },
  { city: 'Carrollton', state: 'TX' },
  { city: 'Seattle', state: 'WA' },
  { city: 'Bellevue', state: 'WA' },
  { city: 'Federal Way', state: 'WA' },
  { city: 'Tacoma', state: 'WA' },
  { city: 'Lynnwood', state: 'WA' },
  { city: 'Boston', state: 'MA' },
  { city: 'Cambridge', state: 'MA' },
  { city: 'Miami', state: 'FL' },
  { city: 'Orlando', state: 'FL' },
  { city: 'Tampa', state: 'FL' },
  { city: 'Jacksonville', state: 'FL' },
  { city: 'Philadelphia', state: 'PA' },
  { city: 'Pittsburgh', state: 'PA' },
  { city: 'Phoenix', state: 'AZ' },
  { city: 'Scottsdale', state: 'AZ' },
  { city: 'Tucson', state: 'AZ' },
  { city: 'Denver', state: 'CO' },
  { city: 'Colorado Springs', state: 'CO' },
  { city: 'Aurora', state: 'CO' },
  { city: 'Las Vegas', state: 'NV' },
  { city: 'Henderson', state: 'NV' },
  { city: 'Portland', state: 'OR' },
  { city: 'Beaverton', state: 'OR' },
  { city: 'Nashville', state: 'TN' },
  { city: 'Memphis', state: 'TN' },
  { city: 'Minneapolis', state: 'MN' },
  { city: 'St. Paul', state: 'MN' },
  { city: 'Detroit', state: 'MI' },
  { city: 'Ann Arbor', state: 'MI' },
  { city: 'Charlotte', state: 'NC' },
  { city: 'Raleigh', state: 'NC' },
  { city: 'Durham', state: 'NC' },
  { city: 'Indianapolis', state: 'IN' },
  { city: 'Columbus', state: 'OH' },
  { city: 'Cleveland', state: 'OH' },
  { city: 'Cincinnati', state: 'OH' },
  { city: 'Kansas City', state: 'MO' },
  { city: 'St. Louis', state: 'MO' },
  { city: 'New Orleans', state: 'LA' },
  { city: 'Salt Lake City', state: 'UT' },
  { city: 'Honolulu', state: 'HI' },
  { city: 'Anchorage', state: 'AK' },
  { city: 'Albuquerque', state: 'NM' },
  { city: 'Oklahoma City', state: 'OK' },
  { city: 'Omaha', state: 'NE' },
  { city: 'Milwaukee', state: 'WI' },
  { city: 'Louisville', state: 'KY' },
  { city: 'Providence', state: 'RI' },
  { city: 'Hartford', state: 'CT' },
  { city: 'Buffalo', state: 'NY' },
  { city: 'Rochester', state: 'NY' },
  { city: 'Newark', state: 'NJ' },
  { city: 'Jersey City', state: 'NJ' },
  { city: 'Baltimore', state: 'MD' },
  { city: 'Richmond', state: 'VA' },
  { city: 'Norfolk', state: 'VA' },
  { city: 'Savannah', state: 'GA' },
  { city: 'Birmingham', state: 'AL' },
  { city: 'Charleston', state: 'SC' },
  { city: 'Boise', state: 'ID' },
];

function onCityInput() {
  const q = addr.city.trim().toLowerCase();
  if (q.length < 2) {
    citySuggestions.value = [];
    return;
  }
  citySuggestions.value = majorCities
    .filter(c => c.city.toLowerCase().includes(q))
    .slice(0, 10);
}

function selectCity(s) {
  addr.city = s.city;
  addr.state = s.state;
  citySuggestions.value = [];
}

function hideCitySuggestions() {
  setTimeout(() => { citySuggestions.value = []; }, 150);
}

function buildFull() {
  const parts = [addr.address1];
  if (addr.address2) parts.push(addr.address2);
  if (addr.city) parts.push(addr.city);
  const stateZip = [addr.state, addr.zip].filter(Boolean).join(' ');
  if (stateZip) parts.push(stateZip);
  return parts.filter(Boolean).join(', ');
}

watch(addr, () => {
  emit('update:modelValue', {
    address1: addr.address1,
    address2: addr.address2,
    city: addr.city,
    state: addr.state,
    zip: addr.zip,
    full: buildFull()
  });
}, { deep: true });
</script>
