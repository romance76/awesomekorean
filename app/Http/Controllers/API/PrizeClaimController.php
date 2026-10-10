<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Sweepstakes;
use App\Models\SweepstakesPrizeClaim;
use App\Support\SweepstakesPrizeClaimService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 당첨자 상품 수령용 연락처 확인.
 * 연락처(이메일·전화·주소)는 본인과 최고관리자에게만 노출되며 공개 API 에는 절대 포함하지 않는다.
 */
class PrizeClaimController extends Controller
{
    private function requireSuperAdmin()
    {
        if (auth()->user()->role !== 'super_admin') {
            abort(response()->json(['success' => false, 'message' => '경품 추첨 관리는 사이트 최고관리자만 접근할 수 있습니다'], 403));
        }
    }

    private function ready(): bool
    {
        return Schema::hasTable('sweepstakes_prize_claims');
    }

    /** 내가 아직 연락처 확인을 안 한 당첨 건 */
    public function index()
    {
        if (!$this->ready()) {
            return response()->json(['success' => true, 'data' => []]);
        }
        $user = auth()->user();

        // 내가 당첨자인 종료 추첨에 대해 claim 행을 지연 생성
        $candidateIds = Sweepstakes::where('status', 'winner_selected')
            ->where(function ($q) use ($user) {
                $q->where('winner_user_id', $user->id);
                if (Schema::hasTable('sweepstakes_winners')) {
                    $q->orWhereIn('id', DB::table('sweepstakes_winners')->where('user_id', $user->id)->select('sweepstakes_id'));
                }
            })
            ->pluck('id');
        if ($candidateIds->isNotEmpty()) {
            foreach (Sweepstakes::whereIn('id', $candidateIds)->get() as $s) {
                SweepstakesPrizeClaimService::syncFor($s);
            }
        }

        $claims = SweepstakesPrizeClaim::where('user_id', $user->id)
            ->whereNull('contact_confirmed_at')
            ->with(['sweepstakes.event:id,title'])
            ->orderBy('id')
            ->get();

        $status = SweepstakesPrizeClaimService::contactStatus($user);

        $data = $claims->filter(fn ($c) => $c->sweepstakes && $c->sweepstakes->status === 'winner_selected')
            ->map(function ($c) use ($status) {
                $s = $c->sweepstakes;
                return [
                    'id' => $c->id,
                    'sweepstakes_id' => $s->id,
                    'event_id' => $s->event_id,
                    'event_title' => $s->event->title ?? $s->title,
                    'prize_name' => $c->prize_label ?: $s->prize_name,
                    'rank' => $c->rank,
                    'drawn_at' => $s->winner_selected_at,
                    'dismissed_recently' => $c->popup_dismissed_at && $c->popup_dismissed_at->gt(now()->subHours(12)),
                    'contact_status' => $status,
                ];
            })->values();

        // 상품을 보냈는데 아직 "받았어요"를 안 누른 건 — 작은 카드로 수령 확인을 묻는다(12시간 안에 '아직이요' 누른 건은 빼고)
        $sent = SweepstakesPrizeClaim::where('user_id', $user->id)
            ->where('delivery_status', 'sent')->whereNull('delivery_confirmed_at')
            ->where(fn ($q) => $q->whereNull('popup_dismissed_at')->orWhere('popup_dismissed_at', '<', now()->subHours(12)))
            ->with(['sweepstakes.event:id,title'])->orderBy('sent_at')->limit(5)->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'event_title' => $c->sweepstakes->event->title ?? ($c->sweepstakes->title ?? '경품 추첨'),
                'prize_name' => $c->prize_label ?: ($c->sweepstakes->prize_name ?? '경품'),
                'prize_type' => $c->prize_type ?: 'digital',
                'sent_at' => $c->sent_at,
            ])->values();

        return response()->json(['success' => true, 'data' => $data, 'sent' => $sent]);
    }

    /** 당첨자가 "상품 받았어요"를 누름 — 보낸 상태에서만, 한 번만 기록 */
    public function received($id)
    {
        $claim = SweepstakesPrizeClaim::where('user_id', auth()->id())->findOrFail($id);
        if ($claim->delivery_status === 'confirmed') {
            return response()->json(['success' => true, 'message' => '이미 받으신 걸로 기록돼 있어요.']);
        }
        if ($claim->delivery_status !== 'sent') {
            return response()->json(['success' => false, 'message' => '아직 상품을 보내지 않았어요. 보내 드리면 알려 드릴게요.'], 422);
        }
        DB::transaction(function () use ($claim) {
            $claim->forceFill(['delivery_status' => 'confirmed', 'delivery_confirmed_at' => now()])->save();
            DB::table('sweepstakes_delivery_logs')->insert([
                'claim_id' => $claim->id, 'sweepstakes_id' => $claim->sweepstakes_id, 'user_id' => $claim->user_id,
                'actor_id' => auth()->id(), 'action' => 'received_by_winner', 'note' => '당첨자가 "받았어요"를 눌렀어요',
                'meta' => null, 'created_at' => now(),
            ]);
        });
        return response()->json(['success' => true, 'message' => '확인해 주셔서 고마워요! 다시 한 번 당첨을 축하드려요 🎉']);
    }

    public function dismiss($id)
    {
        $claim = SweepstakesPrizeClaim::where('user_id', auth()->id())->findOrFail($id);
        $claim->forceFill(['popup_dismissed_at' => now()])->save();
        return response()->json(['success' => true]);
    }

    public function confirm($id)
    {
        $user = auth()->user();
        $claim = SweepstakesPrizeClaim::where('user_id', $user->id)->findOrFail($id);

        $status = SweepstakesPrizeClaimService::contactStatus($user);
        if ($status['missing']) {
            $labels = ['email' => '이메일', 'phone' => '전화번호', 'address' => '주소(주소·우편번호)'];
            $names = implode(', ', array_map(fn ($k) => $labels[$k], $status['missing']));
            return response()->json([
                'success' => false,
                'message' => "{$names} 정보가 비어 있습니다. 내 정보에서 입력한 뒤 다시 확인해 주세요.",
                'missing' => $status['missing'],
            ], 422);
        }

        $claim->forceFill([
            'contact_confirmed_at' => now(),
            'contact_snapshot' => [
                'email' => $status['email'],
                'phone' => $status['phone'],
                'address' => $status['address'],
                'name' => $user->real_name ?? $user->name,
            ],
        ])->save();

        return response()->json(['success' => true, 'message' => '확인되었습니다. 상품 발송을 준비해 드릴게요.']);
    }

    /** 관리자: 추첨별 당첨자 연락처/발송 현황 */
    public function adminIndex(Sweepstakes $sweepstakes)
    {
        $this->requireSuperAdmin();
        if (!$this->ready()) {
            return response()->json(['success' => true, 'data' => []]);
        }
        SweepstakesPrizeClaimService::syncFor($sweepstakes);

        $claims = SweepstakesPrizeClaim::where('sweepstakes_id', $sweepstakes->id)
            ->with('user')
            ->orderBy('rank')->get();

        $data = $claims->map(function ($c) {
            $u = $c->user;
            $snap = $c->contact_snapshot;
            $cur = $u ? SweepstakesPrizeClaimService::contactStatus($u) : ['email' => '', 'phone' => '', 'address' => ''];
            return [
                'id' => $c->id,
                'rank' => $c->rank,
                'prize_label' => $c->prize_label,
                'user_id' => $c->user_id,
                'name' => $snap['name'] ?? ($u->real_name ?? $u->name ?? null),
                'email' => $snap['email'] ?? $cur['email'],
                'phone' => $snap['phone'] ?? $cur['phone'],
                'address' => $snap['address'] ?? $cur['address'],
                'contact_source' => $snap ? 'confirmed' : 'profile',
                'notified_at' => $c->notified_at,
                'contact_confirmed_at' => $c->contact_confirmed_at,
                'fulfilled_at' => $c->fulfilled_at,
                'fulfilled_note' => $c->fulfilled_note,
            ];
        })->values();

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function fulfill(Request $request, $id)
    {
        $this->requireSuperAdmin();
        $request->validate(['note' => 'nullable|string|max:255']);
        $claim = SweepstakesPrizeClaim::findOrFail($id);
        $fill = ['fulfilled_at' => now(), 'fulfilled_note' => $request->input('note')];
        // 새 지급 상태(안 보냄/보냄)도 함께 맞춘다 — 옛 화면에서 처리해도 "안 보낸 상품" 건수가 줄어들게
        if (Schema::hasColumn('sweepstakes_prize_claims', 'delivery_status') && (($claim->delivery_status ?? 'pending') === 'pending')) {
            $fill['delivery_status'] = 'sent';
            $fill['sent_at'] = now();
            $fill['sent_by'] = auth()->id();
        }
        $claim->forceFill($fill)->save();
        return response()->json(['success' => true]);
    }
}
