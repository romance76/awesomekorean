<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Support\AutoSeedPosts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// 관리자 > 소통 > 자동 게시: 켜고 끄기, 하루 개수, 회원 수 상한, 오늘 진행 상황과 최근 올린 글
class AdminAutoSeedController extends Controller
{
    public function show()
    {
        $c = AutoSeedPosts::config();
        $s = AutoSeedPosts::state();
        $names = DB::table('users')->whereIn('id', collect($s['log'] ?? [])->pluck('user_id')->unique())->pluck('nickname', 'id');
        $reason = AutoSeedPosts::blockReason($c);

        return response()->json(['success' => true, 'data' => [
            'config' => $c,
            'member_count' => AutoSeedPosts::memberCount(),
            'author_count' => count(AutoSeedPosts::authorPool()),
            'pool_left' => ['community' => count(AutoSeedPosts::remaining('community')), 'qa' => count(AutoSeedPosts::remaining('qa'))],
            'running' => $reason === null,
            'stopped_reason' => $reason,
            'today' => collect($s['plan'])->map(fn ($p, $kind) => [
                'target' => $p['target'], 'done' => $p['done'], 'due' => AutoSeedPosts::due($s, $kind),
                'next_slot' => collect($p['slots'])->first(fn ($t) => \Carbon\Carbon::parse($t)->gt(now())),
            ]),
            'recent' => collect($s['log'] ?? [])->take(40)->map(fn ($l) => $l + ['author' => $names[$l['user_id']] ?? ('#' . $l['user_id'])])->values(),
        ]]);
    }

    public function update(Request $request)
    {
        $d = $request->validate([
            'enabled' => 'sometimes|boolean',
            'daily_min' => 'sometimes|integer|min:0|max:30',
            'daily_max' => 'sometimes|integer|min:0|max:30',
            'member_cap' => 'sometimes|integer|min:0|max:1000000',
            'start_hour' => 'sometimes|integer|min:0|max:22',
            'end_hour' => 'sometimes|integer|min:1|max:24',
        ]);
        if (isset($d['daily_min'], $d['daily_max']) && $d['daily_max'] < $d['daily_min']) {
            return response()->json(['success' => false, 'message' => '하루 최대 개수는 최소 개수보다 작을 수 없어요.'], 422);
        }
        if (isset($d['start_hour'], $d['end_hour']) && $d['end_hour'] <= $d['start_hour']) {
            return response()->json(['success' => false, 'message' => '끝나는 시각은 시작 시각보다 뒤여야 해요.'], 422);
        }
        AutoSeedPosts::saveConfig($d);
        return $this->show();
    }
}
