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
  .code-box { background:#fff1f5; border:1.5px dashed #FC226B; border-radius:12px; text-align:center; padding:22px 16px; margin:24px 0; }
  .code { font-size:32px; font-weight:800; letter-spacing:8px; color:#FC226B; font-family:'Courier New',monospace; }
  .code-label { font-size:12px; color:#999; margin-top:6px; }
  .expiry { font-size:13px; color:#b45309; background:#fffbeb; border-radius:8px; padding:10px 14px; margin:20px 0; }
  .divider { border:none; border-top:1px solid #eee; margin:24px 0; }
  .security { font-size:13px; color:#888; line-height:1.7; }
  .security strong { color:#555; }
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
      <p>안녕하세요,</p>
      <p>비밀번호 재설정을 요청하셨습니다. 아래 인증 코드를 입력해주세요.</p>
      <div class="code-box">
        <div class="code">{{ $code }}</div>
        <div class="code-label">6자리 인증 코드</div>
      </div>
      <div class="expiry">⏱ 이 코드는 <strong>30분간</strong> 유효합니다.</div>
      <hr class="divider">
      <p class="security">
        <strong>본인이 요청하지 않으셨나요?</strong><br>
        이 이메일은 무시하셔도 됩니다. 비밀번호는 변경되지 않으며, 계정도 안전하게 보호됩니다.<br><br>
        <strong>안전 안내</strong><br>
        AwesomeKorean은 전화나 메시지로 이 코드를 절대 요청하지 않습니다. 타인에게 공유하지 마세요.
      </p>
    </div>
    <div class="footer">
      본 메일은 발신 전용입니다 · <a href="https://awesomekorean.com">awesomekorean.com</a>
    </div>
  </div>
</div>
</body>
</html>
