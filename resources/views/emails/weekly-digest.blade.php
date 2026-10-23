<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>어썸코리안 이번 주 소식</title>
</head>
<body style="margin:0;padding:0;background:#f6f6f4;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Apple SD Gothic Neo','Malgun Gothic',sans-serif;color:#1a1a1a;">
<div style="max-width:600px;margin:0 auto;padding:24px 14px;">
  @if($isTest)
  <div style="background:#fff7e6;border:1px solid #fcd9a0;color:#92400e;font-size:12px;padding:8px 12px;border-radius:8px;margin-bottom:12px;text-align:center;">시험 발송 메일입니다 (회원에게 나가는 메일이 아니에요)</div>
  @endif
  <div style="background:#ffffff;border:1px solid #eee;border-radius:16px;overflow:hidden;">
    <div style="padding:22px 24px 14px;text-align:center;border-bottom:1px solid #f3f3f3;">
      <a href="{{ $siteUrl }}"><img src="{{ $logoUrl }}" alt="어썸코리안" style="max-height:38px;max-width:200px;border:0;"></a>
      <div style="font-size:12px;color:#999;margin-top:8px;">{{ $dateLabel }} · 이번 주 한인 소식</div>
    </div>
    <div style="padding:22px 24px 6px;">
      <p style="font-size:15px;line-height:1.7;margin:0 0 6px;">{{ $name }}님, 안녕하세요!</p>
      <p style="font-size:14px;line-height:1.7;color:#555;margin:0 0 16px;">이번 주에 올라온 미국 한인 생활 정보를 모았어요.</p>

      @if(count($articles['info']))
      <h2 style="font-size:16px;margin:18px 0 8px;color:#111;">📘 이번 주 생활정보</h2>
      @foreach($articles['info'] as $a)
      <a href="{{ $a['url'] }}" style="display:block;text-decoration:none;color:inherit;border:1px solid #f0f0f0;border-radius:12px;padding:12px 14px;margin-bottom:8px;">
        <span style="font-size:11px;color:#b45309;background:#fff7e6;border-radius:999px;padding:2px 8px;">{{ $a['tag'] }}</span>
        <div style="font-size:15px;font-weight:700;line-height:1.45;margin-top:6px;color:#111;">{{ $a['title'] }}</div>
        <div style="font-size:13px;line-height:1.55;color:#777;margin-top:4px;">{{ $a['excerpt'] }}</div>
      </a>
      @endforeach
      <div style="text-align:center;margin:6px 0 4px;"><a href="{{ $siteUrl }}/info" style="font-size:13px;color:#FC226B;text-decoration:none;font-weight:700;">생활정보 더 보기 →</a></div>
      @endif

      @if(count($articles['money']))
      <h2 style="font-size:16px;margin:22px 0 8px;color:#111;">💰 MONEY+ 새 글</h2>
      <ul style="margin:0 0 6px;padding:0 0 0 18px;font-size:14px;line-height:1.8;">
        @foreach($articles['money'] as $m)
        <li><a href="{{ $m['url'] }}" style="color:#111;text-decoration:none;">{{ $m['title'] }}</a></li>
        @endforeach
      </ul>
      @endif
    </div>

    @if(count($ads))
    {{-- 맨 아래: 우리 동네 광고 띠 (정지 배너, 보낼 때마다 무작위 3~5개) --}}
    <div style="padding:18px 24px 20px;background:#fafaf8;border-top:1px solid #f0f0ee;margin-top:14px;">
      <div style="font-size:12px;color:#888;margin-bottom:10px;">📢 우리 동네 광고 <span style="color:#bbb;">(광고)</span></div>
      <div style="text-align:center;font-size:0;">
        @foreach($ads as $ad)
        <div style="display:inline-block;width:48%;min-width:240px;max-width:100%;vertical-align:top;margin:0 0 10px;box-sizing:border-box;padding:0 4px;">
          @if($ad['href'])<a href="{{ $ad['href'] }}" style="text-decoration:none;">@endif
          <img src="{{ $ad['img'] }}" alt="{{ $ad['title'] }}" width="260" style="width:100%;max-width:100%;height:auto;border:0;border-radius:8px;display:block;">
          <span style="display:block;font-size:12px;color:#555;margin-top:4px;text-align:left;">{{ $ad['title'] }}</span>
          @if($ad['href'])</a>@endif
        </div>
        @endforeach
      </div>
    </div>
    @endif

    <div style="padding:16px 24px 22px;border-top:1px solid #f3f3f3;font-size:11.5px;line-height:1.7;color:#999;text-align:center;">
      이 메일은 어썸코리안에서 <b>광고·소식 메일 수신에 동의</b>하신 회원께 보내는 광고성 정보입니다.<br>
      @if($unsubscribeUrl)<a href="{{ $unsubscribeUrl }}" style="color:#FC226B;">수신 거부</a> (누르면 바로 끊어져요)<br>@else(수신 거부 링크는 실제 회원 메일에만 들어가요)<br>@endif
      어썸코리안 · {{ $address }}
    </div>
  </div>
</div>
</body>
</html>
