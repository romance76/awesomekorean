<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
  body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Apple SD Gothic Neo', 'Malgun Gothic', sans-serif; background:#f6f6f4; margin:0; padding:0; color:#1a1a1a; }
  .wrapper { max-width:560px; margin:0 auto; padding:32px 20px; }
  .card { background:#ffffff; border-radius:16px; overflow:hidden; border:1px solid #eee; }
  .header { background:#ffffff; padding:28px 32px 20px; text-align:center; border-bottom:1px solid #f3f3f3; }
  .body { padding:32px; }
  .body p { font-size:14px; line-height:1.7; color:#444; margin:0 0 16px; }
  .btn { display:inline-block; background:linear-gradient(135deg,#FF8A4D,#FC226B); color:#fff !important; padding:13px 28px; border-radius:999px; text-decoration:none; font-weight:700; font-size:14px; margin:8px 0 20px; }
  .expiry { font-size:13px; color:#b45309; background:#fffbeb; border-radius:8px; padding:10px 14px; margin:20px 0; }
  .divider { border:none; border-top:1px solid #eee; margin:24px 0; }
  .security { font-size:13px; color:#888; line-height:1.7; }
  .footer { text-align:center; padding:20px 32px 28px; font-size:12px; color:#aaa; }
  .footer a { color:#FC226B; text-decoration:none; }
</style>
</head>
<body>
<div class="wrapper">
  <div class="card">
    <div class="header">
      <img src="{{ $logoUrl }}" alt="AwesomeKorean" style="max-height:40px;max-width:200px;">
    </div>
    <div class="body">
      <p>안녕하세요, {{ $userName }}님!</p>
      <p>AwesomeKorean 가입을 환영합니다. 아래 버튼을 눌러 이메일 주소를 인증해주세요.</p>
      <a href="{{ $verifyUrl }}" class="btn">이메일 인증하기</a>
      <div class="expiry">⏱ 이 링크는 <strong>7일간</strong> 유효합니다.</div>
      <hr class="divider">
      <p class="security">
        <strong>본인이 가입하지 않으셨나요?</strong><br>
        이 이메일은 무시하셔도 됩니다. 추가 조치가 필요하지 않습니다.
      </p>
    </div>
    <div class="footer">
      본 메일은 발신 전용입니다 · <a href="https://awesomekorean.com">awesomekorean.com</a>
    </div>
  </div>
</div>
</body>
</html>
