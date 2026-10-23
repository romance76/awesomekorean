// 광고·소식 메일 수신 동의 문구 — 가입 화면·마이페이지·안내 배너가 같은 말을 쓴다. 사이트 언어(ko|en)에 따라 한국어/영어.
// 문구를 바꾸면 서버 app/Support/EmailConsent.php 의 TEXT_VERSION 도 함께 올릴 것(동의 이력에 어떤 문구였는지 남기기 위함).
// 주의: 메일이 어느 언어로 오는지는 사이트 언어가 아니라 회원 프로필의 언어(users.language) 기준이다.
const KO = {
  title: '어썸코리안 소식·지역 광고 메일 받기',
  body: '매주 월요일 소식 메일(맨 아래에 우리 동네 광고 포함)과, 우리 지역 전면광고 광고주의 소식(주 1통 이하)을 이메일로 보내 드려요. 보내는 주소는 noreply@awesomekorean.com 이고, 메일 맨 아래 "수신 거부"를 누르면 바로 끊겨요. 동의하지 않아도 사이트 이용에는 아무 불이익이 없어요.',
  region: '받는 지역은 내 프로필의 우편번호(주)로 정해져요. 우편번호가 없으면 전국 광고만 받아요.',
  optional: '[선택]', heading: '메일 수신 설정',
  needVerify: ' 이메일 인증을 마친 뒤부터 보내 드려요.',
  on: '메일 수신을 켰어요.', off: '메일 수신을 껐어요.', saveFail: '저장하지 못했어요. 잠시 뒤 다시 해 주세요.',
  yes: '받을게요', no: '안 받아요', later: '나중에', dialog: '메일 수신 안내',
}
const EN = {
  title: 'Receive Awesome Korean news & local ads by email',
  body: 'We will email you a news roundup every Monday (with local ads at the bottom) and news from advertisers in your area (no more than once a week). Emails come from noreply@awesomekorean.com, and the "Unsubscribe" button at the bottom of each email stops them right away. You can use the site normally even if you do not agree.',
  region: 'The ads you receive depend on the ZIP code (state) in your profile. Without a ZIP code you only get nationwide ads.',
  optional: '[Optional]', heading: 'Email settings',
  needVerify: ' Emails start after you verify your email address.',
  on: 'Email turned on.', off: 'Email turned off.', saveFail: 'Could not save. Please try again in a moment.',
  yes: 'Yes, send me', no: 'No thanks', later: 'Later', dialog: 'Email notice',
}

export const mailConsentText = (locale) => (locale === 'en' ? EN : KO)
