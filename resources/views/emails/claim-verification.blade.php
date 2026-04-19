<!DOCTYPE html><html><head><meta charset="UTF-8"><style>body{font-family:sans-serif;max-width:600px;margin:0 auto;padding:20px}.btn{background:#2563eb;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;display:inline-block;margin:16px 0}</style></head>
<body>
<h2>업소 이메일 인증</h2>
<p>안녕하세요, {{ $userName }}님!</p>
<p><strong>{{ $businessName }}</strong> 업소의 소유권 신청을 위한 이메일 인증입니다.</p>
<a href="{{ $verifyUrl }}" class="btn">이메일 인증하기</a>
<p style="color:#666;font-size:14px">이 링크는 24시간 후 만료됩니다.</p>
<p style="color:#666;font-size:12px">AwesomeKorean - 미국 한인 커뮤니티</p>
</body></html>
