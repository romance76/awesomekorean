<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\ApiKeyManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * /admin/v2/integrations/api-keys 관리 API (Phase 2-C 묶음 6).
 */
class AdminApiKeysController extends Controller
{
    public function index()
    {
        $rows = DB::table('api_keys')->orderBy('service')->get()
            ->map(function ($r) {
                $plain = $r->api_key;
                try { $plain = Crypt::decryptString($r->api_key); } catch (\Throwable $e) {}
                $r->api_key_masked = ApiKeyManager::mask($plain);
                unset($r->api_key);
                return $r;
            });

        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function usage(Request $request)
    {
        $days = min((int) $request->query('days', 7), 90);
        $since = now()->subDays($days)->toDateString();

        $rows = DB::table('api_usage')
            ->where('date', '>=', $since)
            ->orderBy('service')->orderBy('date')
            ->get();

        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function logs(Request $request)
    {
        $service = $request->query('service');
        $level   = $request->query('level');
        $q = DB::table('api_logs');
        if ($service) $q->where('service', $service);
        if ($level)   $q->where('level', $level);
        return response()->json([
            'success' => true,
            'data' => $q->orderByDesc('created_at')->limit(100)->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'service'      => 'required|string|max:100',
            'name'         => 'required|string|max:255',
            'api_key'      => 'required|string',
            'description'  => 'nullable|string',
            'docs_url'     => 'nullable|url',
            'feature_list' => 'nullable|string',
            'quota_limit'  => 'nullable|integer',
            'quota_period' => 'nullable|in:daily,monthly',
            'test_mode'    => 'boolean',
            'is_active'    => 'boolean',
        ]);
        $encrypted = Crypt::encryptString($data['api_key']);
        $id = DB::table('api_keys')->insertGetId([
            'service'      => $data['service'],
            'name'         => $data['name'],
            'api_key'      => $encrypted,
            'description'  => $data['description'] ?? null,
            'docs_url'     => $data['docs_url'] ?? null,
            'feature_list' => $data['feature_list'] ?? null,
            'quota_limit'  => $data['quota_limit'] ?? 0,
            'quota_period' => $data['quota_period'] ?? 'daily',
            'test_mode'    => $data['test_mode'] ?? false,
            'is_active'    => $data['is_active'] ?? true,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
        $this->auditLog($data['service'], 'created', null, ApiKeyManager::mask($data['api_key']));
        Cache::forget("apikey.{$data['service']}");
        return response()->json(['success' => true, 'id' => $id]);
    }

    public function update(Request $request, $id)
    {
        $row = DB::table('api_keys')->where('id', $id)->first();
        if (!$row) return response()->json(['success' => false, 'message' => 'Not found'], 404);

        $data = $request->only(['name','description','docs_url','feature_list','quota_limit','quota_period','test_mode','is_active']);
        $data['updated_at'] = now();

        if ($request->filled('api_key')) {
            $oldPlain = $row->api_key;
            try { $oldPlain = Crypt::decryptString($row->api_key); } catch (\Throwable $e) {}
            $data['api_key'] = Crypt::encryptString($request->input('api_key'));
            $this->auditLog($row->service, 'rotated', ApiKeyManager::mask($oldPlain), ApiKeyManager::mask($request->input('api_key')));
        } else {
            $this->auditLog($row->service, 'updated');
        }

        DB::table('api_keys')->where('id', $id)->update($data);
        Cache::forget("apikey.{$row->service}");
        return response()->json(['success' => true]);
    }

    public function destroy($id)
    {
        $row = DB::table('api_keys')->where('id', $id)->first();
        if (!$row) return response()->json(['success' => false], 404);
        $this->auditLog($row->service, 'deleted');
        DB::table('api_keys')->where('id', $id)->delete();
        Cache::forget("apikey.{$row->service}");
        return response()->json(['success' => true]);
    }

    public function reveal($id)
    {
        // super_admin 만 허용 — 라우트에서 permission 체크
        $row = DB::table('api_keys')->where('id', $id)->first();
        if (!$row) return response()->json(['success' => false], 404);
        $plain = $row->api_key;
        try { $plain = Crypt::decryptString($row->api_key); } catch (\Throwable $e) {}
        $this->auditLog($row->service, 'revealed');
        return response()->json(['success' => true, 'api_key' => $plain]);
    }

    public function test($id)
    {
        $row = DB::table('api_keys')->where('id', $id)->first();
        if (!$row) return response()->json(['success' => false], 404);

        // 실제 테스트 호출은 서비스별 구현 필요. 여기서는 키 존재·활성 여부만 반환.
        $this->auditLog($row->service, 'tested');
        $plain = $row->api_key;
        try { $plain = Crypt::decryptString($row->api_key); } catch (\Throwable $e) {}

        return response()->json([
            'success'      => true,
            'service'      => $row->service,
            'is_active'    => (bool) $row->is_active,
            'test_mode'    => (bool) ($row->test_mode ?? false),
            'key_present'  => !empty($plain),
            'key_length'   => strlen($plain),
            'last_verified_at' => $row->last_verified_at ?? null,
            'last_error_message' => $row->last_error_message ?? null,
            'note'         => '서비스별 상세 테스트 엔드포인트는 별도 구현 필요',
        ]);
    }

    public function history(Request $request)
    {
        $service = $request->query('service');
        $q = DB::table('api_key_history')
            ->leftJoin('users', 'users.id', '=', 'api_key_history.changed_by')
            ->select('api_key_history.*', 'users.nickname as changed_by_name');
        if ($service) $q->where('service', $service);
        return response()->json([
            'success' => true,
            'data' => $q->orderByDesc('api_key_history.created_at')->limit(100)->get(),
        ]);
    }

    protected function auditLog(string $service, string $action, ?string $oldMasked = null, ?string $newMasked = null): void
    {
        DB::table('api_key_history')->insert([
            'service'          => $service,
            'changed_by'       => auth()->id(),
            'action'           => $action,
            'old_value_masked' => $oldMasked,
            'new_value_masked' => $newMasked,
            'ip'               => request()->ip(),
            'created_at'       => now(),
        ]);
    }
}
