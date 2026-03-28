<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReportController extends Controller
{
    // 신고 제출
    public function store(Request $request)
    {
        $request->validate([
            'type'   => 'required|in:post,comment,job_post,market_item,business,user',
            'id'     => 'required|integer',
            'reason' => 'required|in:spam,scam,inappropriate,hate,false_info,other',
            'detail' => 'nullable|string|max:500',
        ]);

        $typeMap = [
            'post'        => \App\Models\Post::class,
            'comment'     => \App\Models\Comment::class,
            'job_post'    => \App\Models\JobPost::class,
            'market_item' => \App\Models\MarketItem::class,
            'business'    => \App\Models\Business::class,
            'user'        => \App\Models\User::class,
        ];

        // 중복 신고 체크
        $exists = Report::where('reporter_id', Auth::id())
            ->where('reportable_type', $typeMap[$request->type])
            ->where('reportable_id', $request->id)
            ->where('status', 'pending')
            ->exists();

        if ($exists) {
            return response()->json(['message' => '이미 신고한 항목입니다.'], 400);
        }

        Report::create([
            'reporter_id'      => Auth::id(),
            'reportable_type'  => $typeMap[$request->type],
            'reportable_id'    => $request->id,
            'reason'           => $request->reason,
            'detail'           => $request->detail,
        ]);

        return response()->json(['message' => '신고가 접수되었습니다. 검토 후 처리하겠습니다.'], 201);
    }
}
