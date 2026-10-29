<!DOCTYPE html>
<html lang="{{ $langs[0] }}">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Awesome Korean</title>
<style>
  @media only screen and (max-width:520px) {
    .col { display:block !important; width:100% !important; }
    .hero-h { font-size:23px !important; }
  }
</style>
</head>
<body style="margin:0;padding:0;background:#f4f1ec;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Apple SD Gothic Neo','Malgun Gothic',sans-serif;color:#1a1a1a;">
<div style="display:none;max-height:0;overflow:hidden;opacity:0;font-size:1px;line-height:1px;color:#f4f1ec;">{{ $blocks[0]['t']['hero_sub'] }}</div>
<div style="max-width:900px;margin:0 auto;padding:22px 12px 28px;">
  @if($isTest)
  <div style="background:#fff7e6;border:1px solid #fcd9a0;color:#92400e;font-size:12px;padding:8px 12px;border-radius:8px;margin-bottom:12px;text-align:center;">시험 메일입니다 / Test email — 수신 거부 링크는 이 주소로 실제 연결돼요 / The unsubscribe link below is live for this address.</div>
  @endif

  <div style="background:#ffffff;border-radius:22px;overflow:hidden;border:1px solid #ece7df;">

    <div style="padding:20px 24px 16px;text-align:center;background:#ffffff;">
      <a href="{{ $siteUrl }}"><img src="{{ $logoUrl }}" alt="Awesome Korean" style="max-height:40px;max-width:210px;border:0;"></a>
    </div>

    @foreach($blocks as $b)
    @php $t = $b['t']; $en = $b['lang'] === 'en'; @endphp

    {{-- 히어로 --}}
    <div style="background:#FC226B;background-image:linear-gradient(135deg,#FC226B 0%,#FF5C7A 55%,#FF8A4C 100%);padding:46px 28px 44px;text-align:center;{{ $loop->index > 0 ? 'border-top:6px solid #ffffff;' : '' }}">
      <div style="display:inline-block;background:rgba(255,255,255,0.22);color:#ffffff;font-size:12px;font-weight:800;letter-spacing:1.5px;padding:6px 16px;border-radius:999px;margin-bottom:18px;">{{ $en ? 'NOW OPEN' : '새로 오픈했어요' }}</div>
      <h1 class="hero-h" style="margin:0 0 14px;font-size:29px;line-height:1.4;color:#ffffff;font-weight:800;">{!! nl2br(e($t['hero_title'])) !!}</h1>
      <p style="margin:0 0 26px;font-size:15px;line-height:1.75;color:#ffffff;opacity:0.95;">{{ $t['hero_sub'] }}</p>
      <a href="{{ $siteUrl }}" style="display:inline-block;padding:15px 36px;border-radius:999px;background:#ffffff;color:#FC226B;font-size:16px;font-weight:800;text-decoration:none;">{{ $t['button'] }} →</a>
    </div>

    {{-- 본문(에디터로 쓴 글) --}}
    @if($b['bodyHtml'] !== '')
    <div style="padding:32px 30px 10px;background:#ffffff;">{!! $b['bodyHtml'] !!}</div>
    @endif

    {{-- 기능 카드 --}}
    @if(count($t['features']))
    <div style="padding:14px 18px 10px;background:#ffffff;">
      <div style="text-align:center;font-size:21px;font-weight:800;color:#111111;margin:10px 0 0;">{{ $t['features_title'] }}</div>
      <div style="width:40px;height:4px;background:#FC226B;margin:10px auto 16px;border-radius:2px;"></div>
      @foreach(array_chunk($t['features'], 2) as $row)
      <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="border-collapse:collapse;">
        <tr>
          @foreach($row as $f)
          <td class="col" width="50%" valign="top" style="padding:6px;">
            <div style="background:#fff8f4;border:1px solid #fde4d8;border-radius:16px;padding:18px 16px 16px;">
              @if($f['icon'] !== '')
              <div style="width:46px;height:46px;line-height:46px;border-radius:14px;background:#ffffff;border:1px solid #fde4d8;text-align:center;font-size:23px;margin-bottom:10px;">{{ $f['icon'] }}</div>
              @endif
              <div style="font-size:15.5px;font-weight:800;color:#111111;margin-bottom:5px;line-height:1.4;">{{ $f['title'] }}</div>
              @if($f['desc'] !== '')<div style="font-size:13px;line-height:1.7;color:#666666;">{{ $f['desc'] }}</div>@endif
            </div>
          </td>
          @endforeach
          @if(count($row) === 1)<td class="col" width="50%" style="padding:6px;"></td>@endif
        </tr>
      </table>
      @endforeach
    </div>
    @endif

    {{-- 마무리 --}}
    <div style="margin:14px 18px 22px;padding:28px 22px;background:#1f2430;border-radius:18px;text-align:center;">
      <div style="font-size:17px;line-height:1.85;color:#ffffff;font-weight:700;">{!! nl2br(e($t['closing'])) !!}</div>
      <a href="{{ $siteUrl }}" style="display:inline-block;margin-top:20px;padding:13px 32px;border-radius:999px;background:#FC226B;color:#ffffff;font-size:15px;font-weight:800;text-decoration:none;">{{ $t['button'] }} →</a>
    </div>
    @endforeach

    <div style="padding:20px 26px 26px;border-top:1px solid #f1ede6;font-size:11.5px;line-height:1.75;color:#999999;text-align:center;background:#fbfaf8;">
      @if(in_array('ko', $langs))이 메일은 어썸코리안 소개를 위해 보내드린 <b>광고</b> 메일입니다. 원하지 않으시면 아래 버튼으로 수신을 거부해 주세요. 다시 보내지 않습니다.<br>@endif
      @if(in_array('en', $langs))This is an <b>advertisement</b> introducing Awesome Korean. If you do not want to hear from us, use the button below and we will not email you again.<br>@endif
      <a href="{{ $unsubscribeUrl }}" style="display:inline-block;margin:12px 0 6px;padding:11px 28px;border:1px solid #d1d5db;border-radius:999px;background:#ffffff;color:#374151;font-size:13px;font-weight:700;text-decoration:none;">{{ in_array('ko', $langs) ? '수신 거부' : '' }}{{ count($langs) > 1 ? ' / ' : '' }}{{ in_array('en', $langs) ? 'Unsubscribe' : '' }}</a><br>
      <span style="display:block;margin-top:8px;">Awesome Korean · {{ $address }}</span>
    </div>
  </div>
</div>
</body>
</html>
