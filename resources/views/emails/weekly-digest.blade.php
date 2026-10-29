<!DOCTYPE html>
<html lang="{{ $lang }}">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ $t['title_sub'] }}</title>
</head>
<body style="margin:0;padding:0;background:#f6f6f4;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Apple SD Gothic Neo','Malgun Gothic',sans-serif;color:#1a1a1a;">
<div style="max-width:900px;margin:0 auto;padding:24px 14px;">
  @if($isTest)
  <div style="background:#fff7e6;border:1px solid #fcd9a0;color:#92400e;font-size:12px;padding:8px 12px;border-radius:8px;margin-bottom:12px;text-align:center;">{{ $t['test_banner'] }}</div>
  @endif
  <div style="background:#ffffff;border:1px solid #eee;border-radius:16px;overflow:hidden;">
    <div style="padding:22px 24px 14px;text-align:center;border-bottom:1px solid #f3f3f3;">
      <a href="{{ $siteUrl }}"><img src="{{ $logoUrl }}" alt="Awesome Korean" style="max-height:38px;max-width:200px;border:0;"></a>
      <div style="font-size:12px;color:#999;margin-top:8px;">{{ $dateLabel }} · {{ $t['title_sub'] }}</div>
    </div>
    <div style="padding:22px 24px 6px;">
      <p style="font-size:15px;line-height:1.7;margin:0 0 6px;">{{ str_replace(':name', $name, $t['greeting']) }}</p>
      <p style="font-size:14px;line-height:1.7;color:#555;margin:0 0 16px;" {!! $editMode ? 'data-edit-key="intro" data-single="1" data-placeholder="intro"' : '' !!}>{{ $texts['intro'] }}</p>

      @if(!empty($notice) || $editMode)
      {{-- 관리자가 "안내 문구"에 적은 글(공지·알림). 비어 있으면 실제 메일에는 나오지 않고, 미리보기에서만 빈 칸이 보인다 --}}
      <div style="background:#fff7e6;border:1px solid #fcd9a0;border-radius:10px;padding:12px 14px;margin:0 0 6px;font-size:13px;line-height:1.7;color:#7c4a03;white-space:pre-line;min-height:18px;" {!! $editMode ? 'data-edit-key="notice" data-placeholder="안내 문구를 쓰려면 여기를 누르세요 / Click to add a notice (hidden in real emails when empty)"' : '' !!}>{{ $notice }}</div>
      @endif

      @if(count($articles['info']))
      <h2 style="font-size:16px;margin:18px 0 8px;color:#111;">{{ $t['info_h'] }}</h2>
      @foreach($articles['info'] as $a)
      <a href="{{ $a['url'] }}" style="display:block;text-decoration:none;color:inherit;border:1px solid #f0f0f0;border-radius:12px;padding:12px 14px;margin-bottom:8px;">
        <span style="font-size:11px;color:#b45309;background:#fff7e6;border-radius:999px;padding:2px 8px;">{{ $a['tag'] }}</span>
        <div style="font-size:15px;font-weight:700;line-height:1.45;margin-top:6px;color:#111;">{{ $a['title'] }}</div>
        <div style="font-size:13px;line-height:1.55;color:#777;margin-top:4px;">{{ $a['excerpt'] }}</div>
      </a>
      @endforeach
      <div style="text-align:center;margin:6px 0 4px;"><a href="{{ $siteUrl }}/info" style="font-size:13px;color:#FC226B;text-decoration:none;font-weight:700;">{{ $t['info_more'] }}</a></div>
      @endif

      @if(count($articles['boards']))
      <h2 style="font-size:16px;margin:22px 0 4px;color:#111;">{{ $t['boards_h'] }}</h2>
      <div style="font-size:12px;color:#999;margin-bottom:8px;">{{ $t['boards_sub'] }}</div>
      @foreach($articles['boards'] as $b)
      <div style="margin:0 0 12px;">
        <div style="font-size:14px;font-weight:700;color:#111;">{{ $b['icon'] }} {{ $b['label'] }} <span style="font-size:12px;font-weight:400;color:#b45309;">{{ str_replace(':n', $b['count'], $t['new_posts']) }}</span></div>
        <ul style="margin:4px 0 0;padding:0 0 0 18px;font-size:13px;line-height:1.75;color:#555;">
          @foreach($b['items'] as $it)
          <li><a href="{{ $it['url'] }}" style="color:#444;text-decoration:none;">{{ $it['title'] }}</a></li>
          @endforeach
        </ul>
        @if($b['count'] > count($b['items']))<a href="{{ $b['more'] }}" style="font-size:12px;color:#FC226B;text-decoration:none;margin-left:18px;">{{ $t['more'] }}</a>@endif
      </div>
      @endforeach
      @endif
    </div>

    @if(count($ads))
    {{-- 맨 아래: 우리 동네 광고 띠 (정지 배너, 보낼 때마다 무작위 최대 6개) --}}
    <div style="padding:18px 24px 20px;background:#fafaf8;border-top:1px solid #f0f0ee;margin-top:14px;">
      <div style="font-size:12px;color:#888;margin-bottom:10px;">📢 <span {!! $editMode ? 'data-edit-key="ad_title" data-single="1" data-placeholder="title"' : '' !!}>{{ $texts['ad_title'] }}</span> <span style="color:#bbb;">{{ $t['ad_label'] }}</span></div>
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

    <div style="padding:18px 24px 24px;border-top:1px solid #f3f3f3;font-size:11.5px;line-height:1.7;color:#999;text-align:center;">
      {{ $t['footer_consent'] }}<br>
      <a href="{{ $unsubscribeUrl }}" style="display:inline-block;margin:12px 0 6px;padding:11px 28px;border:1px solid #d1d5db;border-radius:999px;background:#ffffff;color:#374151;font-size:13px;font-weight:700;text-decoration:none;">{{ $t['unsub_btn'] }}</a><br>
      <span>{{ $t['unsub_note'] }}</span>@if($unsubIsSample)<br><span style="color:#b45309;">{{ $t['unsub_sample'] }}</span>@endif<br>
      <span style="display:block;margin-top:8px;">Awesome Korean · {{ $address }}</span>
    </div>
  </div>
</div>
</body>
</html>
