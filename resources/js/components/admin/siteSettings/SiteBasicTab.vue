<template>
  <div class="card p-6">
    <h2 class="text-base font-semibold text-ink mb-6">사이트 설정</h2>

    <!-- 회원 설정 -->
    <div class="mb-6">
      <h3 class="text-sm font-semibold text-ink-muted uppercase tracking-wide mb-4">회원 설정</h3>
      <div class="space-y-4">
        <ToggleRow v-model="site.allow_signup" label="회원가입 허용" desc="끄고 저장하면 이메일·구글·아마존으로 새로 가입할 수 없어요. 이미 가입한 분의 로그인은 그대로예요." />
        <ToggleRow v-model="site.require_email_verify" label="이메일 인증 필수" desc="끄고 저장하면 이메일 인증을 안 한 회원도 글쓰기·공개 채팅을 할 수 있어요. 인증 메일은 계속 보내고, 인증 보너스와 'N번째 가입' 집계는 인증을 마친 분만 세요." />
        <ToggleRow v-model="site.auto_approve" label="자동 승인" desc="지금은 가입 승인 절차가 없어서 이 스위치와 상관없이 가입 즉시 사용할 수 있어요." :pending="true" />
        <ToggleRow v-model="site.allow_withdrawal" label="회원 탈퇴 허용" desc="지금은 이 스위치와 상관없이 회원이 스스로 탈퇴할 수 있어요. (개인정보 삭제 요청을 막을 수 없어서 연결하지 않았어요.)" :pending="true" />
        <div>
          <div class="flex items-center gap-4 flex-wrap">
            <label class="text-sm text-ink-light w-48" for="ss-minpw">최소 비밀번호 길이</label>
            <input id="ss-minpw" v-model.number="site.min_password_length" type="number" min="8" max="64" class="input-field w-24" />
            <span class="text-sm text-ink-faint">자 (8~64)</span>
          </div>
          <p class="text-xs text-ink-faint mt-1.5 leading-relaxed">새로 가입하거나 비밀번호를 바꿀 때부터 적용돼요. 이미 쓰는 비밀번호는 그대로예요. 영문 대문자·소문자·숫자를 모두 넣어야 하는 규칙은 항상 적용되고, 8자 아래로는 줄일 수 없어요.</p>
        </div>
      </div>
    </div>

    <div class="border-t border-gray-100 pt-6 mb-6">
      <h3 class="text-sm font-semibold text-ink-muted uppercase tracking-wide mb-2 flex items-center gap-2 flex-wrap">파일 업로드 설정 <span class="normal-case tracking-normal text-[11px] font-bold px-2 py-0.5 rounded-full bg-gray-200 text-gray-600">아직 연결 안 됨</span></h3>
      <p class="text-xs text-ink-faint mb-4 leading-relaxed">이 칸들은 아직 사이트에 연결되지 않았어요. 값을 바꿔도 실제 업로드 제한에는 영향이 없고, 각 기능이 정한 제한을 그대로 써요.</p>
      <div class="flex flex-col gap-4 opacity-60">
        <div class="flex items-center gap-4">
          <label class="text-sm text-ink-light w-48">최대 업로드 파일 크기</label>
          <input v-model.number="site.max_upload_mb" type="number" min="1" max="100" class="input-field w-24" disabled />
          <span class="text-sm text-ink-faint">MB</span>
        </div>
        <div>
          <label class="input-label">허용 파일 형식</label>
          <input v-model="site.allowed_file_types" type="text" class="input-field" placeholder="jpg,png,gif,pdf,doc" disabled />
          <p class="text-xs text-ink-faint mt-1">쉼표로 구분하여 입력하세요</p>
        </div>
      </div>
    </div>

    <div class="border-t border-gray-100 pt-6 mb-6">
      <h3 class="text-sm font-semibold text-ink-muted uppercase tracking-wide mb-4">점검 모드</h3>
      <ToggleRow v-model="site.maintenance_mode" label="점검 모드" desc="켜고 저장하면 관리자·운영자 외에는 사이트를 쓸 수 없고 점검 안내만 보여요. 관리자 화면·로그인은 계속 열려 있어서, 잘못 켜도 여기서 바로 끌 수 있어요." />
      <div v-if="site.maintenance_mode" class="mt-4 space-y-3">
        <div>
          <label class="input-label">점검 안내 문구</label>
          <textarea v-model="site.maintenance_reason" rows="2" maxlength="500" class="input-field resize-none" placeholder="지금은 사이트를 점검하고 있어요. 잠시 후 다시 이용해 주세요."></textarea>
          <p class="text-xs text-ink-faint mt-1">비워 두면 위 기본 문구가 나가요. (최대 500자)</p>
        </div>
        <div>
          <label class="input-label">점검 종료 예정시각 (애틀랜타 시각으로 적어 주세요)</label>
          <input v-model="site.maintenance_until" type="datetime-local" class="input-field" />
          <p class="text-xs text-ink-faint mt-1">회원에게 보여 주는 안내용이에요. 이 시각이 지나도 점검이 자동으로 풀리지는 않아요 — 끝나면 직접 꺼 주세요.</p>
        </div>
      </div>
    </div>

    <div class="flex justify-end">
      <button @click="saveSite" :disabled="saving" class="btn-primary">
        <span v-if="saving" class="inline-block w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin mr-2"></span>
        저장하기
      </button>
    </div>
  </div>
</template>

<script setup>
import ToggleRow from './ToggleRow.js'

// 사이트 설정 탭 — 값(site)은 SiteSettings.vue 가 불러오고 저장한다(점검 모드 확인창 포함). 여기서는 입력 화면만 맡는다.
const props = defineProps({
  site: { type: Object, required: true },
  saving: { type: Boolean, default: false },
})
const emit = defineEmits(['save'])
const site = props.site
const saveSite = () => emit('save')
</script>

<style scoped>
/* 사이트 설정 화면과 동일한 소프트 입력 스타일 (SiteSettings.vue 와 같은 값) */
.input-field {
  @apply w-full bg-[#F4F6F8] border-[1.5px] border-transparent rounded-xl px-3 py-2 text-sm text-ink placeholder:text-ink-faint outline-none transition-all duration-150 focus:bg-white focus:border-amber-400 focus:ring-4 focus:ring-amber-400/10;
}
</style>
