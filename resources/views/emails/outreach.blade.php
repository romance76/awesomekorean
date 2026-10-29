@php $ed = $edit ?? false; @endphp<!DOCTYPE html>
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
@if($ed)
{{-- 관리자 편집 화면 전용: 눌러서 바로 고칠 수 있는 곳에 점선이 보인다(실제 메일·시험 메일에는 없음) --}}
<style>
  [data-card] { resize:vertical; overflow:auto; }
  [data-ek] { outline:1px dashed rgba(120,120,120,0.45); outline-offset:3px; border-radius:4px; cursor:text; min-height:1em; }
  [data-ek]:hover { outline-color:#FC226B; }
  [data-ek]:focus { outline:2px solid #FC226B; }
  [data-ek=body] p { margin:0 0 14px; font-size:15px; line-height:1.85; color:#3a3a3a; }
  [data-ek=body] h2 { margin:22px 0 10px; font-size:21px; line-height:1.4; color:#111; font-weight:800; }
  [data-ek=body] h3 { margin:18px 0 8px; font-size:17px; line-height:1.45; color:#111; font-weight:800; }
  [data-ek=body] ul, [data-ek=body] ol { margin:0 0 14px; padding:0 0 0 22px; font-size:15px; line-height:1.85; color:#3a3a3a; }
  [data-ek=body] ul { list-style:disc; } [data-ek=body] ol { list-style:decimal; }
  [data-ek=body] a { color:#FC226B; font-weight:700; text-decoration:underline; }
  [data-ek=body] blockquote { margin:0 0 14px; padding:10px 16px; border-left:4px solid #FC226B; background:#fff5f8; color:#555; font-size:15px; line-height:1.8; }
</style>
@endif
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
      <h1 class="hero-h" {!! $ed ? 'data-ek="hero_title"' : '' !!} style="margin:0 0 14px;font-size:29px;line-height:1.4;color:#ffffff;font-weight:800;{{ \App\Support\Outreach::styleCss($t['styles'] ?? [], 'hero_title') }}">{!! nl2br(e($t['hero_title'])) !!}</h1>
      <p {!! $ed ? 'data-ek="hero_sub" data-single="1"' : '' !!} style="margin:0 0 26px;font-size:15px;line-height:1.75;color:#ffffff;opacity:0.95;{{ \App\Support\Outreach::styleCss($t['styles'] ?? [], 'hero_sub') }}">{{ $t['hero_sub'] }}</p>
      <a href="{{ \App\Support\Outreach::resolveLink($t['button_link'] ?? '', $siteUrl, $siteUrl) }}" {!! $ed ? 'data-ek="button" data-single="1"' : '' !!} style="display:inline-block;padding:15px 36px;border-radius:999px;background:#ffffff;color:#FC226B;font-size:16px;font-weight:800;text-decoration:none;">{{ $t['button'] }}</a>
    </div>

    {{-- 본문(에디터로 쓴 글) --}}
    @if($ed || $b['bodyHtml'] !== '')
    <div style="padding:32px 30px 10px;background:#ffffff;"><div {!! $ed ? 'data-ek="body" style="min-height:60px;"' : '' !!}>{!! $b['bodyHtml'] !!}</div></div>
    @endif

    {{-- 기능 카드: 열 수(2~4)는 관리자가 고르고, 카드마다 연결 링크를 걸 수 있다 --}}
    @if(count($t['features']))
    @php
      $cols = in_array(($t['card_cols'] ?? '2'), ['2', '3', '4'], true) ? (int) $t['card_cols'] : 2;
      $small = $cols >= 3;
      $cellW = floor(100 / $cols);
    @endphp
    <div style="padding:14px 22px 10px;background:#ffffff;">
      <div {!! $ed ? 'data-ek="features_title" data-single="1"' : '' !!} style="text-align:center;font-size:21px;font-weight:800;color:#111111;margin:10px 0 0;{{ \App\Support\Outreach::styleCss($t['styles'] ?? [], 'features_title') }}">{{ $t['features_title'] }}</div>
      <div style="width:40px;height:4px;background:#FC226B;margin:10px auto 16px;border-radius:2px;"></div>
      @foreach(array_chunk($t['features'], $cols, true) as $row)
      <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="border-collapse:collapse;table-layout:fixed;">
        <tr>
          @foreach($row as $fi => $f)
          @php $href = $ed ? '' : \App\Support\Outreach::resolveLink($f['link'] ?? '', $siteUrl); @endphp
          <td class="col" width="{{ $cellW }}%" valign="top" style="padding:{{ $small ? '4px' : '6px' }};">
            @if($href !== '')<a href="{{ $href }}" style="display:block;text-decoration:none;color:inherit;">@endif
            <div {!! $ed ? 'data-card="' . $fi . '"' : '' !!} style="box-sizing:border-box;{{ (int) ($f['h'] ?? 0) > 0 ? 'min-height:' . (int) $f['h'] . 'px;' : '' }}background:#fff8f4;border:1px solid #fde4d8;border-radius:{{ $small ? '14px' : '16px' }};padding:{{ $small ? '14px 12px 12px' : '18px 16px 16px' }};">
              @if($f['icon'] !== '' || $ed)
              <div style="width:{{ $small ? '38px' : '46px' }};height:{{ $small ? '38px' : '46px' }};line-height:{{ $small ? '38px' : '46px' }};border-radius:12px;background:#ffffff;border:1px solid #fde4d8;text-align:center;font-size:{{ $small ? '19px' : '23px' }};margin-bottom:{{ $small ? '8px' : '10px' }};"><span {!! $ed ? 'data-ek="f.' . $fi . '.icon" data-single="1"' : '' !!}>{{ $f['icon'] }}</span></div>
              @endif
              <div {!! $ed ? 'data-ek="f.' . $fi . '.title" data-single="1"' : '' !!} style="font-size:{{ $small ? '14px' : '15.5px' }};font-weight:800;color:#111111;margin-bottom:4px;line-height:1.4;{{ \App\Support\Outreach::styleCss($t['styles'] ?? [], 'f.' . $fi . '.title') }}">{{ $f['title'] }}</div>
              @if($f['desc'] !== '' || $ed)<div {!! $ed ? 'data-ek="f.' . $fi . '.desc"' : '' !!} style="font-size:{{ $small ? '12px' : '13px' }};line-height:1.65;color:#666666;{{ \App\Support\Outreach::styleCss($t['styles'] ?? [], 'f.' . $fi . '.desc') }}">{{ $f['desc'] }}</div>@endif
            </div>
            @if($href !== '')</a>@endif
          </td>
          @endforeach
          @for($pad = count($row); $pad < $cols; $pad++)<td class="col" width="{{ $cellW }}%" style="padding:4px;"></td>@endfor
        </tr>
      </table>
      @endforeach
    </div>
    @endif

    {{-- 광고 안내 박스(관리자가 켜고 끌 수 있음) --}}
    @if(($t['promo_show'] ?? '1') === '1')
    <div style="margin:16px 22px 6px;padding:26px 24px 22px;background:#fff9e6;background-image:linear-gradient(135deg,#fff9e6 0%,#ffefd2 100%);border:2px dashed #f5b942;border-radius:18px;text-align:center;">
      <div style="display:inline-block;background:#f5b942;color:#5b3a00;font-size:12px;font-weight:800;padding:5px 14px;border-radius:999px;margin-bottom:12px;"><span {!! $ed ? 'data-ek="promo_badge" data-single="1"' : '' !!}>{{ $t['promo_badge'] }}</span></div>
      <div {!! $ed ? 'data-ek="promo_title" data-single="1"' : '' !!} style="font-size:21px;line-height:1.45;font-weight:800;color:#3b2a00;margin-bottom:10px;{{ \App\Support\Outreach::styleCss($t['styles'] ?? [], 'promo_title') }}">{{ $t['promo_title'] }}</div>
      <div {!! $ed ? 'data-ek="promo_text"' : '' !!} style="font-size:14.5px;line-height:1.8;color:#5c4a1d;{{ \App\Support\Outreach::styleCss($t['styles'] ?? [], 'promo_text') }}">{{ $t['promo_text'] }}</div>
      <a href="{{ \App\Support\Outreach::resolveLink($t['promo_link'] ?? '', $siteUrl, $siteUrl . '/ad-apply') }}" {!! $ed ? 'data-ek="promo_button" data-single="1"' : '' !!} style="display:inline-block;margin-top:16px;padding:13px 30px;border-radius:999px;background:#3b2a00;color:#ffffff;font-size:15px;font-weight:800;text-decoration:none;">{{ $t['promo_button'] }}</a>
      <div {!! $ed ? 'data-ek="promo_note"' : '' !!} style="margin-top:14px;font-size:11.5px;line-height:1.7;color:#8a7440;{{ \App\Support\Outreach::styleCss($t['styles'] ?? [], 'promo_note') }}">{{ $t['promo_note'] }}</div>
    </div>
    @endif

    {{-- 마무리 --}}
    <div style="margin:14px 22px 22px;padding:28px 22px;background:#1f2430;border-radius:18px;text-align:center;">
      <div {!! $ed ? 'data-ek="closing"' : '' !!} style="font-size:17px;line-height:1.85;color:#ffffff;font-weight:700;{{ \App\Support\Outreach::styleCss($t['styles'] ?? [], 'closing') }}">{!! nl2br(e($t['closing'])) !!}</div>
      <a href="{{ \App\Support\Outreach::resolveLink($t['button_link'] ?? '', $siteUrl, $siteUrl) }}" style="display:inline-block;margin-top:20px;padding:13px 32px;border-radius:999px;background:#FC226B;color:#ffffff;font-size:15px;font-weight:800;text-decoration:none;">{{ $t['button'] }}</a>
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
