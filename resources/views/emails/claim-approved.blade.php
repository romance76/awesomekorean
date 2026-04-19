<!DOCTYPE html><html><head><meta charset="UTF-8"><style>body{font-family:sans-serif;max-width:600px;margin:0 auto;padding:20px}.btn{background:#16a34a;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;display:inline-block;margin:16px 0}</style></head>
<body>
<h2>업소 소유권 승인!</h2>
<p>안녕하세요, {{ $userName }}님!</p>
<p><strong>{{ $businessName }}</strong> 업소의 소유권 신청이 승인되었습니다!</p>
<p>이제 업주 대시보드에서 업소 정보를 직접 관리하실 수 있습니다.</p>
<a href="{{ $dashboardUrl }}" class="btn">내 업소 관리하기</a>
<p style="color:#666;font-size:12px">AwesomeKorean - 미국 한인 커뮤니티</p>
</body></html>
