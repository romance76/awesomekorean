<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Support\ChatRules;
use App\Support\OpenEvent;
use App\Support\PointRules;
use App\Support\PromotionSettings;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * 관리자 "오픈 이벤트": 소프트 오픈 기간의 포인트 적립 배수 / 유료 항목 할인 / 무료 사진 / 구매 보너스를 한 화면에서 선택해 적용.
 * 원래 설정값은 건드리지 않고(OpenEvent 가 그 위에 덧씌움) 기간이 끝나면 자동으로 원래대로 돌아온다.
 */
class AdminOpenEventController extends Controller
{
    public function show()
    {
        $c = OpenEvent::config();
        return response()->json(['success' => true, 'data' => [
            'config' => $c,
            'state' => OpenEvent::state(),
            'today' => Carbon::now('America/New_York')->toDateString(),
            'items_meta' => collect(OpenEvent::ITEMS)->map(fn ($v, $k) => ['key' => $k, 'label' => $v['label']])->values(),
            'perks' => OpenEvent::perks($c),
            'preview' => $this->preview($c),
            'defaults' => OpenEvent::defaults(),
        ]]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'enabled' => 'required|boolean',
            'starts_on' => 'required|date_format:Y-m-d',
            'ends_on' => 'required|date_format:Y-m-d|after_or_equal:starts_on',
            'earn.multiplier' => 'required|integer|min:1|max:10',
            'items' => 'required|array',
            'items.*.pct' => 'nullable|integer|min:0|max:100',
            'photos.count' => 'nullable|integer|min:0|max:30',
            'purchase_bonus.pct' => 'nullable|integer|min:0|max:100',
            'headline' => 'nullable|string|max:40',
            'subline' => 'nullable|string|max:100',
        ]);

        $before = OpenEvent::config();
        $cfg = OpenEvent::save($request->all());
        \Log::info('Open event settings saved', ['admin_id' => auth()->id(), 'enabled' => $cfg['enabled'], 'from' => $cfg['starts_on'], 'to' => $cfg['ends_on'], 'was_enabled' => $before['enabled']]);

        return response()->json(['success' => true, 'message' => $cfg['enabled'] ? '오픈 이벤트 설정을 저장했어요. 기간이 되면 자동으로 적용돼요.' : '저장했어요. (지금은 꺼져 있어서 아무것도 바뀌지 않아요)', 'data' => [
            'config' => $cfg,
            'state' => OpenEvent::state(),
            'perks' => OpenEvent::perks($cfg),
            'preview' => $this->preview($cfg),
        ]]);
    }

    /** 공개용: 사이트 안내 띠 (진행 중일 때만 내용이 있음) */
    public function publicInfo()
    {
        return response()->json(['success' => true, 'data' => OpenEvent::publicInfo()]);
    }

    // ───────── 미리보기: 원래 값 → 이벤트 값 ─────────

    private function evPct(array $c, string $item): int
    {
        $row = $c['items'][$item] ?? null;
        return ($row && $row['on']) ? (int) $row['pct'] : 0;
    }

    private function evAmount(array $c, string $item, int $amount, int $min = 0): int
    {
        $pct = $this->evPct($c, $item);
        return $pct > 0 && $amount > 0 ? max($min, (int) round($amount * (100 - $pct) / 100)) : $amount;
    }

    private function pt(int $n): string
    {
        return number_format($n) . 'P';
    }

    private function preview(array $c): array
    {
        $rows = [];
        $raw = fn (string $k, int $d) => (int) (PointRules::all()[$k] ?? $d);

        // 포인트 적립
        $mult = ($c['earn']['on'] && $c['earn']['multiplier'] > 1) ? $c['earn']['multiplier'] : 1;
        try {
            $earn = DB::table('point_settings')->where('category', 'earn')->orderBy('key')->get(['key', 'label', 'value']);
        } catch (\Throwable $e) {
            $earn = collect();
        }
        foreach ($earn as $e) {
            if (str_ends_with($e->key, '_daily_max')) continue;
            $v = (int) $e->value;
            if ($v <= 0) continue;
            $rows[] = ['group' => '포인트 적립', 'label' => $e->label ?: $e->key, 'original' => $this->pt($v), 'event' => $this->pt($v * $mult)];
        }

        // 채팅방
        $chat = ChatRules::all();
        foreach ([['create_cost_dm', 50, '1:1 채팅방 개설'], ['create_cost_group', 200, '그룹 채팅방 개설'], ['create_cost_public', 500, '공개 채팅방 개설']] as [$k, $d, $label]) {
            $v = (int) ($chat[$k] ?? $d);
            $rows[] = ['group' => '채팅', 'label' => $label, 'original' => $this->pt($v), 'event' => $this->pt($this->evAmount($c, 'chat_create', $v))];
        }
        $v = (int) ($chat['entry_cost_public'] ?? 10);
        $rows[] = ['group' => '채팅', 'label' => '공개 채팅방 입장 (24시간)', 'original' => $this->pt($v), 'event' => $this->pt($this->evAmount($c, 'chat_entry', $v))];

        // 사진
        $pset = fn (string $k, int $d) => (int) (DB::table('point_settings')->where('key', $k)->value('value') ?? $d);
        foreach ([['market_free_photos', 'market_extra_photo_cost', '장터'], ['realestate_free_photos', 'realestate_extra_photo_cost', '부동산']] as [$fk, $ck, $name]) {
            $free = $pset($fk, 5);
            $cost = $pset($ck, 50);
            $evFree = ($c['photos']['on']) ? max($free, $c['photos']['count']) : $free;
            $rows[] = ['group' => '사진', 'label' => "{$name} 무료 사진 (초과 1장당 {$this->pt($cost)})", 'original' => "{$free}장", 'event' => "{$evFree}장"];
        }

        // 끌어올리기
        $base = $pset('market_bump_base_cost', 100);
        $rows[] = ['group' => '장터', 'label' => '끌어올리기 (1회차)', 'original' => $this->pt($base), 'event' => $this->pt($this->evAmount($c, 'bump', $base))];

        // 상위노출 (전국 하루 가격)
        $all = PromotionSettings::all();
        $labels = ['jobs' => '구인구직', 'market' => '중고장터', 'realestate' => '부동산', 'business' => '업소록', 'businesses' => '업소록', 'clubs' => '동호회'];
        foreach (($all['price_per_day'] ?? []) as $res => $tiers) {
            $p = (int) ($tiers['national'] ?? 0);
            if ($p <= 0) continue;
            $rows[] = ['group' => '상위노출', 'label' => ($labels[$res] ?? $res) . ' 전국 상위노출 (하루)', 'original' => $this->pt($p), 'event' => $this->pt($this->evAmount($c, 'promotion', $p))];
        }

        // 광고
        $rows[] = ['group' => '광고', 'label' => '텍스트 광고 입찰 100P 기준 실제 차감', 'original' => $this->pt(100), 'event' => $this->pt($this->evAmount($c, 'banner', 100))];

        // 달러 전단 광고
        foreach ([['flyer_price_state', 30, '주(State) 전단 광고 (시간당 기본)'], ['flyer_price_national', 60, '전국 전단 광고 (시간당 기본)']] as [$k, $d, $label]) {
            $cents = $raw($k, $d);
            $rows[] = ['group' => '달러 결제', 'label' => $label, 'original' => '$' . number_format($cents / 100, 2), 'event' => '$' . number_format($this->evAmount($c, 'flyer_usd', $cents, 1) / 100, 2)];
        }

        // 포인트 구매
        $bonus = ($c['purchase_bonus']['on'] ? $c['purchase_bonus']['pct'] : 0);
        $rows[] = ['group' => '포인트 구매', 'label' => '구매 시 추가 보너스', 'original' => '기존 구간 보너스', 'event' => $bonus > 0 ? "기존 + {$bonus}%" : '기존 구간 보너스'];

        return $rows;
    }
}
