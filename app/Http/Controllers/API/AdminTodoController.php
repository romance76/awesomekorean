<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** 관리자 "할 일 목록" — 앞으로 만들거나 보강할 것들을 적어 두고 상태/우선순위를 직접 고친다 */
class AdminTodoController extends Controller
{
    private function rules(bool $partial): array
    {
        $req = $partial ? 'sometimes|required' : 'required';
        return [
            'title' => "$req|string|max:200",
            'detail' => 'sometimes|nullable|string|max:5000',
            'category' => 'sometimes|required|string|max:30',
            'priority' => 'sometimes|in:high,medium,low',
            'status' => 'sometimes|in:todo,doing,done',
            'sort_order' => 'sometimes|integer|min:0|max:1000000',
        ];
    }

    public function index()
    {
        $rows = DB::table('admin_todos')->orderByRaw("FIELD(status,'doing','todo','done')")
            ->orderByRaw("FIELD(priority,'high','medium','low')")->orderBy('sort_order')->orderBy('id')->get();
        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function store(Request $request)
    {
        $d = $request->validate($this->rules(false));
        $max = (int) DB::table('admin_todos')->max('sort_order');
        $id = DB::table('admin_todos')->insertGetId([
            'title' => $d['title'], 'detail' => $d['detail'] ?? null, 'category' => $d['category'] ?? '기능',
            'priority' => $d['priority'] ?? 'medium', 'status' => $d['status'] ?? 'todo',
            'sort_order' => $d['sort_order'] ?? $max + 10,
            'done_at' => ($d['status'] ?? 'todo') === 'done' ? now() : null,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return response()->json(['success' => true, 'data' => DB::table('admin_todos')->find($id)]);
    }

    public function update(Request $request, $id)
    {
        $row = DB::table('admin_todos')->find($id);
        if (!$row) return response()->json(['success' => false, 'message' => '항목을 찾을 수 없습니다'], 404);
        $d = $request->validate($this->rules(true));
        if (isset($d['status'])) $d['done_at'] = $d['status'] === 'done' ? ($row->done_at ?: now()) : null;
        $d['updated_at'] = now();
        DB::table('admin_todos')->where('id', $id)->update($d);
        return response()->json(['success' => true, 'data' => DB::table('admin_todos')->find($id)]);
    }

    public function destroy($id)
    {
        DB::table('admin_todos')->where('id', $id)->delete();
        return response()->json(['success' => true]);
    }
}
