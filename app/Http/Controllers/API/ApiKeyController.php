<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApiKeyController extends Controller
{
    public function index()
    {
        $keys = DB::table('api_keys')->orderBy('name')->get();
        $keys->transform(function ($key) {
            $key->masked_key = substr($key->api_key, 0, 10) . '...' . substr($key->api_key, -4);
            return $key;
        });
        return response()->json($keys);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'service' => 'required|string|max:100',
            'api_key' => 'required|string',
            'description' => 'nullable|string|max:500',
        ]);

        $id = DB::table('api_keys')->insertGetId([
            'name' => $request->name,
            'service' => $request->service,
            'api_key' => $request->api_key,
            'description' => $request->description,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'API 키가 등록되었습니다.', 'id' => $id], 201);
    }

    public function update(Request $request, $id)
    {
        $data = $request->only(['name', 'service', 'api_key', 'description', 'is_active']);
        $data['updated_at'] = now();
        DB::table('api_keys')->where('id', $id)->update($data);
        return response()->json(['message' => '수정되었습니다.']);
    }

    public function destroy($id)
    {
        DB::table('api_keys')->where('id', $id)->delete();
        return response()->json(['message' => '삭제되었습니다.']);
    }

    public function reveal($id)
    {
        $key = DB::table('api_keys')->where('id', $id)->value('api_key');
        return response()->json(['api_key' => $key]);
    }
}
