<!DOCTYPE html>
<html lang="{{ $langs[0] }}">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Awesome Korean</title>
</head>
<body style="margin:0;padding:0;background:#f6f6f4;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Apple SD Gothic Neo','Malgun Gothic',sans-serif;color:#1a1a1a;">
<div style="max-width:600px;margin:0 auto;padding:24px 14px;">
  @if($isTest)
  <div style="background:#fff7e6;border:1px solid #fcd9a0;color:#92400e;font-size:12px;padding:8px 12px;border-radius:8px;margin-bottom:12px;text-align:center;">시험 메일입니다 / Test email — 수신 거부 링크는 이 주소로 실제 연결돼요 / The unsubscribe link below is live for this address.</div>
  @endif
  <div style="background:#ffffff;border:1px solid #eee;border-radius:16px;overflow:hidden;">
    <div style="padding:22px 24px 14px;text-align:center;border-bottom:1px solid #f3f3f3;">
      <a href="{{ $siteUrl }}"><img src="{{ $logoUrl }}" alt="Awesome Korean" style="max-height:38px;max-width:200px;border:0;"></a>
      <div style="font-size:11px;color:#999;margin-top:8px;">광고 / Advertisement</div>
    </div>

    @foreach($blocks as $i => $b)
    <div style="padding:22px 24px {{ $loop->last ? '8px' : '18px' }};{{ $i > 0 ? 'border-top:1px dashed #e5e7eb;' : '' }}">
      @foreach($b['parts'] as $p)
        @if($p['type'] === 'p')
        <p style="font-size:15px;line-height:1.75;margin:0 0 12px;color:#222;">{{ $p['text'] }}</p>
        @else
        <ul style="margin:0 0 14px;padding:0 0 0 20px;font-size:14px;line-height:1.8;color:#444;">
          @foreach($p['items'] as $it)<li style="margin-bottom:4px;">{{ $it }}</li>@endforeach
        </ul>
        @endif
      @endforeach
      <div style="text-align:center;margin:18px 0 6px;">
        <a href="{{ $siteUrl }}" style="display:inline-block;padding:13px 30px;border-radius:999px;background:#FC226B;color:#ffffff;font-size:15px;font-weight:700;text-decoration:none;">{{ $b['texts']['button'] }}</a>
      </div>
    </div>
    @endforeach

    <div style="padding:18px 24px 24px;border-top:1px solid #f3f3f3;font-size:11.5px;line-height:1.7;color:#999;text-align:center;">
      @if(in_array('ko', $langs))이 메일은 어썸코리안 소개를 위해 보내드린 광고 메일입니다. 원하지 않으시면 아래 버튼으로 수신을 거부해 주세요. 다시 보내지 않습니다.<br>@endif
      @if(in_array('en', $langs))This is an advertisement introducing Awesome Korean. If you do not want to hear from us, use the button below and we will not email you again.<br>@endif
      <a href="{{ $unsubscribeUrl }}" style="display:inline-block;margin:12px 0 6px;padding:11px 28px;border:1px solid #d1d5db;border-radius:999px;background:#ffffff;color:#374151;font-size:13px;font-weight:700;text-decoration:none;">{{ in_array('ko', $langs) ? '수신 거부' : '' }}{{ count($langs) > 1 ? ' / ' : '' }}{{ in_array('en', $langs) ? 'Unsubscribe' : '' }}</a><br>
      <span style="display:block;margin-top:8px;">Awesome Korean · {{ $address }}</span>
    </div>
  </div>
</div>
</body>
</html>
