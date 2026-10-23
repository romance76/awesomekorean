<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ $title }}</title>
</head>
<body style="margin:0;padding:0;background:#f6f6f4;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Apple SD Gothic Neo','Malgun Gothic',sans-serif;color:#1a1a1a;">
<div style="max-width:600px;margin:0 auto;padding:24px 14px;">
  @if($isTest)
  <div style="background:#fff7e6;border:1px solid #fcd9a0;color:#92400e;font-size:12px;padding:8px 12px;border-radius:8px;margin-bottom:12px;text-align:center;">시험 발송 메일입니다 (회원에게 나가는 메일이 아니에요){{ $sample ? ' · 내용은 샘플입니다' : '' }}</div>
  @endif
  <div style="background:#ffffff;border:1px solid #eee;border-radius:16px;overflow:hidden;">
    <div style="padding:22px 24px 14px;text-align:center;border-bottom:1px solid #f3f3f3;">
      <a href="{{ $siteUrl }}"><img src="{{ $logoUrl }}" alt="어썸코리안" style="max-height:38px;max-width:200px;border:0;"></a>
      <div style="font-size:12px;color:#999;margin-top:8px;">우리 동네 광고 소식 · {{ $dateLabel }}</div>
    </div>

    <div style="padding:22px 24px 8px;">
      <p style="font-size:15px;line-height:1.7;margin:0 0 4px;">{{ $name }}님, 안녕하세요!</p>
      <p style="font-size:13.5px;line-height:1.7;color:#666;margin:0 0 14px;"><span@if($editMode) data-edit-key="intro" data-single="1" data-raw="{{ $texts['intro'] }}" data-placeholder="소개 문구" @endif>{{ str_replace(['{지역}', '{종류}'], [$region, $kindLabel], $texts['intro']) }}</span> <span style="color:#aaa;">(광고)</span></p>

      @if($image)
      @if($linkUrl)<a href="{{ $linkUrl }}" style="text-decoration:none;">@endif
      <img src="{{ $image }}" alt="{{ $title }}" width="552" style="width:100%;max-width:100%;height:auto;border:0;border-radius:12px;display:block;">
      @if($linkUrl)</a>@endif
      @endif

      <h1 style="font-size:19px;line-height:1.4;margin:14px 0 6px;color:#111;">{{ $title }}</h1>
      <div style="font-size:12px;color:#b45309;margin-bottom:8px;">📅 {{ $period }}</div>
      @if($description)<p style="font-size:14px;line-height:1.75;color:#444;margin:0 0 12px;white-space:pre-line;">{{ $description }}</p>@endif
      @if($phone)<p style="font-size:14px;margin:0 0 14px;">📞 <a href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}" style="color:#111;font-weight:700;text-decoration:none;">{{ $phone }}</a></p>@endif
      @if($linkUrl)<div style="text-align:center;margin:6px 0 14px;"><a href="{{ $linkUrl }}" style="display:inline-block;background:#FC226B;color:#ffffff;padding:12px 26px;border-radius:999px;text-decoration:none;font-weight:700;font-size:14px;">자세히 보기</a></div>@endif

      @if(!empty($notice) || $editMode)
      <div style="background:#f8f7f4;border:1px solid #eeeae0;border-radius:10px;padding:12px 14px;margin:6px 0 14px;font-size:12.5px;line-height:1.7;color:#666;white-space:pre-line;min-height:18px;"@if($editMode) data-edit-key="notice" data-placeholder="안내 문구를 쓰려면 여기를 누르세요 (비워 두면 실제 메일에는 나오지 않아요)" @endif>{{ $notice }}</div>
      @endif
    </div>

    <div style="padding:16px 24px 22px;border-top:1px solid #f3f3f3;font-size:11.5px;line-height:1.7;color:#999;text-align:center;">
      이 메일은 어썸코리안에서 <b>광고·소식 메일 수신에 동의</b>하신 회원께 보내는 광고성 정보입니다.<br>
      @if($unsubscribeUrl)<a href="{{ $unsubscribeUrl }}" style="color:#FC226B;">수신 거부</a> (누르면 바로 끊어져요)<br>@else(수신 거부 링크는 실제 회원 메일에만 들어가요)<br>@endif
      어썸코리안 · {{ $address }}
    </div>
  </div>
</div>
</body>
</html>
