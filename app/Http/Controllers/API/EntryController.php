<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\EntryTransaction;
use App\Support\EntryService;
use App\Support\EntrySettings;
use Illuminate\Http\Request;

class EntryController extends Controller
{
    public function balance()
    {
        $u = auth()->user();
        $required = max(1, EntrySettings::get('checkin_required_count', 5));
        $checkedInToday = \App\Models\EntryCheckin::where('user_id', $u->id)
            ->where('checkin_date', now()->toDateString())
            ->exists();

        return response()->json([
            'success' => true,
            'data' => [
                'entries' => $u->entries,
                'checkin_progress' => $u->entry_checkin_progress,
                'checkin_required' => $required,
                'checked_in_today' => $checkedInToday,
                // 출석 외 Entry 획득 방법 (값이 0 이면 화면에서 숨김)
                'earn' => [
                    'activity_required' => max(0, EntrySettings::get('activity_required_count', 10)),
                    'activity_progress' => (int) $u->entry_activity_progress,
                    'email_verify_bonus' => max(0, EntrySettings::get('email_verify_bonus', 1)),
                    'email_verified' => (bool) $u->email_verified_at,
                    'milestone_bonus' => max(0, EntrySettings::get('milestone_bonus', 1)),
                ],
            ],
        ]);
    }

    public function history()
    {
        $logs = EntryTransaction::where('user_id', auth()->id())
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json(['success' => true, 'data' => $logs]);
    }

    public function checkin(Request $request)
    {
        $result = EntryService::checkin(auth()->user());

        if ($result['already_checked_in_today']) {
            return response()->json([
                'success' => false,
                'message' => '오늘은 이미 출석체크를 완료했습니다',
                'data' => $result,
            ], 400);
        }

        return response()->json(['success' => true, 'data' => $result]);
    }
}
