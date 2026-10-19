<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * 결제 정리(삭제) — 시험 결제를 지울 때 쓴다. 최고관리자 + 재확인(reauth) 전용.
 *
 *  preview : 지우면 무엇이 같이 사라지는지(인보이스·연결된 전단/배너/공동구매 등)를 미리 보여 준다. 아무것도 바꾸지 않는다.
 *  purge   : 지울 결제의 모든 연결 기록을 JSON 으로 백업한 뒤(storage/app/…/purge_backups) 한 트랜잭션으로 지운다.
 *
 * 안전장치: 카드 보류가 걸린 결제·게시 중인 광고가 연결된 결제는 막는다 / "삭제" 라고 직접 입력해야 한다 / 한 번에 50건까지.
 * Stripe 쪽 결제는 건드리지 않는다(환불·취소는 따로). 올려 둔 이미지 파일도 지우지 않는다.
 */
class AdminPaymentPurgeController extends Controller
{
    private const MAX = 50;

    private function has(string $table, ?string $col = null): bool
    {
        return Schema::hasTable($table) && ($col === null || Schema::hasColumn($table, $col));
    }

    private function ids(Request $r): array
    {
        $d = $r->validate(['ids' => 'required|array|min:1|max:' . self::MAX, 'ids.*' => 'integer|min:1']);
        return array_values(array_unique(array_map('intval', $d['ids'])));
    }

    /** 결제 한 건에 달린 기록 번호들 */
    private function linked(object $p): array
    {
        $flyers = []; $banners = []; $groupBuys = [];
        if ($this->has('flyer_ads', 'payment_id')) $flyers = DB::table('flyer_ads')->where('payment_id', $p->id)->pluck('id')->all();
        if ($this->has('flyer_ads') && $p->kind === 'flyer' && $p->ref_id && str_ends_with((string) $p->ref_type, 'FlyerAd')) $flyers[] = (int) $p->ref_id;
        if ($flyers) $flyers = DB::table('flyer_ads')->whereIn('id', array_unique($flyers))->pluck('id')->all();

        if ($this->has('banner_ads', 'payment_id')) $banners = DB::table('banner_ads')->where('payment_id', $p->id)->pluck('id')->all();

        if ($this->has('group_buys')) {
            $groupBuys = DB::table('group_buys')->where(function ($w) use ($p) {
                if ($this->has('group_buys', 'deposit_payment_id')) $w->orWhere('deposit_payment_id', $p->id);
                if ($this->has('group_buys', 'fee_payment_id')) $w->orWhere('fee_payment_id', $p->id);
                if ($p->kind === 'groupbuy_deposit' && $p->ref_id && str_ends_with((string) $p->ref_type, 'GroupBuy')) $w->orWhere('id', (int) $p->ref_id);
            })->pluck('id')->all();
        }

        // 상위노출 결제는 글(장터·구인구직)을 지우지 않고, 이 결제로 켜진 상위노출 표시만 되돌린다
        $promo = null;
        if ($p->kind === 'promotion' && $p->ref_id && $p->ref_type && class_exists($p->ref_type) && is_subclass_of($p->ref_type, \Illuminate\Database\Eloquent\Model::class)) {
            $table = (new ($p->ref_type))->getTable();
            if ($this->has($table, 'promotion_tier') && DB::table($table)->where('id', $p->ref_id)->exists()) {
                $promo = ['table' => $table, 'id' => (int) $p->ref_id, 'class' => class_basename($p->ref_type)];
            }
        }
        return ['flyers' => array_values(array_unique($flyers)), 'banners' => array_values(array_unique($banners)), 'group_buys' => array_values(array_unique($groupBuys)), 'promo' => $promo];
    }

    /** 미리보기용 계획 — preview 와 purge 가 같은 계산을 쓴다 */
    private function plan(array $ids): array
    {
        $payments = DB::table('payments')->whereIn('id', $ids)->get()->keyBy('id');
        $users = DB::table('users')->whereIn('id', $payments->pluck('user_id')->filter()->unique())->get(['id', 'name', 'email'])->keyBy('id');
        $invoices = $this->has('invoices') ? DB::table('invoices')->whereIn('payment_id', $ids)->get(['id', 'payment_id', 'invoice_no', 'status'])->keyBy('payment_id') : collect();
        $rows = [];
        foreach ($ids as $id) {
            $p = $payments->get($id);
            if (!$p) { $rows[] = ['id' => $id, 'missing' => true, 'blocked' => '이미 없는 결제예요', 'warn' => [], 'links' => [], '_lk' => ['flyers' => [], 'banners' => [], 'group_buys' => [], 'promo' => null]]; continue; }
            $lk = $this->linked($p);
            $links = []; $blocked = null; $warn = [];

            foreach ($lk['flyers'] as $fid) {
                $ad = DB::table('flyer_ads')->where('id', $fid)->first(['id', 'title', 'status']);
                $slots = $this->has('flyer_slots') ? DB::table('flyer_slots')->where('flyer_ad_id', $fid)->count() : 0;
                $links[] = ['type' => '전단 광고', 'id' => $fid, 'label' => ($ad->title ?? '') . ' (' . ($ad->status ?? '?') . ')', 'note' => "예약 시간 {$slots}칸도 같이 삭제"];
                if (in_array($ad->status ?? '', ['pending', 'approved'], true)) $blocked = "승인 대기·게시 중인 전단 광고 #{$fid}가 연결돼 있어요. 먼저 거절하거나 게시를 중단해 주세요.";
            }
            foreach ($lk['banners'] as $bid) {
                $b = DB::table('banner_ads')->where('id', $bid)->first();
                $st = $b->status ?? '?';
                $links[] = ['type' => '배너·텍스트 광고', 'id' => $bid, 'label' => ($b->title ?? $b->name ?? '') . " ({$st})", 'note' => '같이 삭제'];
                if (in_array($st, ['pending', 'approved', 'active'], true)) $blocked = "승인 대기·게시 중인 광고 #{$bid}가 연결돼 있어요. 먼저 거절하거나 중단해 주세요.";
            }
            foreach ($lk['group_buys'] as $gid) {
                $g = DB::table('group_buys')->where('id', $gid)->first(['id', 'title']);
                $part = $this->has('group_buy_participants') ? DB::table('group_buy_participants')->where('group_buy_id', $gid)->count() : 0;
                $cp = $this->has('group_buy_coupons') ? DB::table('group_buy_coupons')->where('group_buy_id', $gid)->count() : 0;
                $links[] = ['type' => '공동구매', 'id' => $gid, 'label' => (string) ($g->title ?? ''), 'note' => "참가자 {$part}명·쿠폰 {$cp}장도 같이 삭제"];
            }
            if ($lk['promo']) $links[] = ['type' => '상위노출', 'id' => $lk['promo']['id'], 'label' => $lk['promo']['class'] . ' #' . $lk['promo']['id'], 'note' => '글은 그대로 두고 상위노출 표시만 해제'];
            if (isset($invoices[$id])) $links[] = ['type' => '인보이스', 'id' => $invoices[$id]->id, 'label' => $invoices[$id]->invoice_no . ' (' . $invoices[$id]->status . ')', 'note' => '삭제 — 이 번호는 비게 돼요'];

            if ($p->status === 'authorized') $blocked = '카드에 보류가 걸려 있어요(아직 청구 전). 먼저 광고를 거절·취소해서 보류를 풀어 주세요.';
            if (in_array($p->status, ['captured', 'completed'], true) && $p->stripe_payment_id) $warn[] = '카드로 실제 청구된 결제일 수 있어요. Stripe 에는 그대로 남아요(환불은 따로). 시험 결제가 맞는지 확인하세요.';
            if ($p->kind === 'points' && in_array($p->status, ['completed', 'captured'], true)) $warn[] = '포인트 구매 완료 건이에요. 이미 지급된 포인트는 회수되지 않아요.';

            $rows[] = [
                'id' => (int) $p->id, 'kind' => $p->kind, 'status' => $p->status, 'amount' => (float) $p->amount,
                'user' => ($users[$p->user_id]->name ?? '-') . ' (' . ($users[$p->user_id]->email ?? '-') . ')',
                'stripe' => $p->stripe_payment_id ? substr($p->stripe_payment_id, 0, 16) . '…' : null,
                'created_at' => $p->created_at, 'links' => $links, 'warn' => $warn, 'blocked' => $blocked, '_lk' => $lk,
            ];
        }
        return $rows;
    }

    public function preview(Request $request)
    {
        $rows = $this->plan($this->ids($request));
        foreach ($rows as &$r) unset($r['_lk']);
        return response()->json(['success' => true, 'data' => $rows, 'blocked' => collect($rows)->whereNotNull('blocked')->count()]);
    }

    public function purge(Request $request)
    {
        $ids = $this->ids($request);
        $request->validate(['confirm' => 'required|string', 'delete_linked' => 'nullable|boolean']);
        if (trim((string) $request->input('confirm')) !== '삭제') {
            return response()->json(['success' => false, 'message' => '확인 칸에 "삭제"라고 정확히 입력해 주세요.'], 422);
        }
        $deleteLinked = $request->boolean('delete_linked', true);
        $rows = $this->plan($ids);
        $blocked = collect($rows)->filter(fn ($r) => !empty($r['blocked']));
        if ($blocked->isNotEmpty()) {
            return response()->json(['success' => false, 'message' => '지울 수 없는 결제가 있어요: ' . $blocked->map(fn ($r) => "#{$r['id']} — {$r['blocked']}")->implode(' / ')], 422);
        }

        $flyers = collect($rows)->pluck('_lk.flyers')->flatten()->unique()->values()->all();
        $banners = collect($rows)->pluck('_lk.banners')->flatten()->unique()->values()->all();
        $groupBuys = collect($rows)->pluck('_lk.group_buys')->flatten()->unique()->values()->all();
        $promos = collect($rows)->pluck('_lk.promo')->filter()->values()->all();

        $couponIds = ($groupBuys && $this->has('group_buy_coupons')) ? DB::table('group_buy_coupons')->whereIn('group_buy_id', $groupBuys)->pluck('id')->all() : [];
        $creditLogs = ($flyers && $this->has('ad_credit_logs')) ? DB::table('ad_credit_logs')->where('related_type', 'like', '%FlyerAd')->whereIn('related_id', $flyers)->get() : collect();
        $invoiceIds = $this->has('invoices') ? DB::table('invoices')->whereIn('payment_id', $ids)->pluck('id')->all() : [];

        // 1) 백업 — 지우기 전에 연결 기록을 통째로 JSON 으로 저장한다
        $take = fn (string $t, string $col, array $in) => ($in && $this->has($t, $col)) ? DB::table($t)->whereIn($col, $in)->get()->all() : [];
        $backup = [
            'purged_at' => now()->toDateTimeString(), 'by_admin' => auth()->id(), 'delete_linked' => $deleteLinked,
            'payments' => $take('payments', 'id', $ids), 'invoices' => $take('invoices', 'payment_id', $ids),
            'invoice_deliveries' => $take('invoice_deliveries', 'invoice_id', $invoiceIds),
            'flyer_ads' => $take('flyer_ads', 'id', $flyers), 'flyer_slots' => $take('flyer_slots', 'flyer_ad_id', $flyers),
            'banner_ads' => $take('banner_ads', 'id', $banners),
            'group_buys' => $take('group_buys', 'id', $groupBuys), 'group_buy_participants' => $take('group_buy_participants', 'group_buy_id', $groupBuys),
            'group_buy_coupons' => $take('group_buy_coupons', 'id', $couponIds), 'group_buy_coupon_events' => $take('group_buy_coupon_events', 'coupon_id', $couponIds),
            'ad_credit_logs' => $creditLogs->all(),
        ];
        $file = 'purge_backups/payments_' . now()->format('Ymd_His') . '_u' . auth()->id() . '.json';
        Storage::disk('local')->put($file, json_encode($backup, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE));

        // 2) 한 트랜잭션으로 삭제 (중간에 실패하면 전부 되돌아간다)
        try {
            DB::transaction(function () use ($ids, $deleteLinked, $flyers, $banners, $groupBuys, $couponIds, $promos, $creditLogs, $invoiceIds) {
                if ($deleteLinked) {
                    if ($couponIds && $this->has('group_buy_coupon_events')) DB::table('group_buy_coupon_events')->whereIn('coupon_id', $couponIds)->delete();
                    if ($couponIds) DB::table('group_buy_coupons')->whereIn('id', $couponIds)->delete();
                    if ($groupBuys && $this->has('group_buy_participants')) DB::table('group_buy_participants')->whereIn('group_buy_id', $groupBuys)->delete();
                    if ($groupBuys) DB::table('group_buys')->whereIn('id', $groupBuys)->delete();
                    if ($flyers && $this->has('flyer_slots')) DB::table('flyer_slots')->whereIn('flyer_ad_id', $flyers)->delete();
                    if ($creditLogs->isNotEmpty()) {
                        // 이 전단으로 생긴 광고 잔액 적립·사용을 되돌린다 (잔액은 0 아래로 내려가지 않게)
                        foreach ($creditLogs->groupBy('user_id') as $uid => $logs) {
                            $sum = (int) $logs->sum('delta_cents');
                            DB::table('users')->where('id', $uid)->update(['ad_credit_cents' => DB::raw('GREATEST(CAST(ad_credit_cents AS SIGNED) - (' . $sum . '), 0)')]);
                        }
                        DB::table('ad_credit_logs')->whereIn('id', $creditLogs->pluck('id'))->delete();
                    }
                    if ($flyers) DB::table('flyer_ads')->whereIn('id', $flyers)->delete();
                    if ($banners) DB::table('banner_ads')->whereIn('id', $banners)->delete();
                    foreach ($promos as $pr) {
                        $set = ['promotion_tier' => 'none'];
                        if ($this->has($pr['table'], 'promotion_expires_at')) $set['promotion_expires_at'] = null;
                        if ($this->has($pr['table'], 'promotion_states')) $set['promotion_states'] = null;
                        DB::table($pr['table'])->where('id', $pr['id'])->update($set);
                    }
                } else {
                    // 연결된 글은 두고, 결제 번호만 떼어 낸다
                    if ($flyers && $this->has('flyer_ads', 'payment_id')) DB::table('flyer_ads')->whereIn('id', $flyers)->whereIn('payment_id', $ids)->update(['payment_id' => null]);
                    if ($banners && $this->has('banner_ads', 'payment_id')) DB::table('banner_ads')->whereIn('id', $banners)->whereIn('payment_id', $ids)->update(['payment_id' => null]);
                    if ($groupBuys) {
                        foreach (['deposit_payment_id', 'fee_payment_id'] as $c) {
                            if ($this->has('group_buys', $c)) DB::table('group_buys')->whereIn('id', $groupBuys)->whereIn($c, $ids)->update([$c => null]);
                        }
                    }
                }
                if ($invoiceIds && $this->has('invoice_deliveries')) DB::table('invoice_deliveries')->whereIn('invoice_id', $invoiceIds)->delete();
                if ($invoiceIds) DB::table('invoices')->whereIn('id', $invoiceIds)->delete();
                DB::table('payments')->whereIn('id', $ids)->delete();
            });
        } catch (\Throwable $e) {
            Log::error('[결제 정리] 실패: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => '삭제하지 못했어요. 아무것도 지워지지 않았어요. (' . mb_substr($e->getMessage(), 0, 120) . ')'], 500);
        }

        Log::warning('[결제 정리] 최고관리자 ' . auth()->id() . ' 가 결제 ' . implode(',', $ids) . ' 삭제 (연결 삭제=' . ($deleteLinked ? 'Y' : 'N') . ', 백업=' . $file . ')');
        return response()->json([
            'success' => true,
            'message' => count($ids) . '건을 지웠어요.' . ($deleteLinked ? ' 연결된 기록도 정리했어요.' : ''),
            'data' => ['deleted' => count($ids), 'backup' => $file, 'flyers' => count($flyers), 'banners' => count($banners), 'group_buys' => count($groupBuys), 'invoices' => count($invoiceIds)],
        ]);
    }
}
