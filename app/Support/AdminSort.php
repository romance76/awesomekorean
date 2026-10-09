<?php

namespace App\Support;

use Closure;
use Illuminate\Http\Request;

/**
 * 관리자 목록 정렬 (표 머리글을 눌러 오름차순/내림차순).
 *
 * - 요청의 sort(키)와 dir(asc|desc)를 허용 목록($map)에 있는 것만 받는다. 모르는 키는 무시.
 * - 정렬 지정이 없으면 기본은 "번호(id) 내림차순" = 가장 마지막 번호가 맨 위.
 * - 정렬 값이 같은 행들은 항상 id 내림차순으로 이어서, 페이지를 넘겨도 순서가 흔들리지 않게 한다.
 *
 * $map: ['요청 키' => '실제 컬럼' | function ($query, string $dir) { ... }]
 *       예) ['name' => 'name', 'author' => fn ($q, $dir) => $q->orderBy(User::select('name')->whereColumn('users.id', 'posts.user_id'), $dir)]
 */
class AdminSort
{
    public static function apply($query, Request $request, array $map, string $idColumn = 'id')
    {
        $key = (string) $request->input('sort', '');
        $dir = strtolower((string) $request->input('dir', '')) === 'asc' ? 'asc' : 'desc';

        // 컨트롤러가 먼저 걸어 둔 기본 정렬(최신순 등)은 걷어내고 여기서 정한다
        $query->reorder();

        if ($key !== '' && isset($map[$key])) {
            $col = $map[$key];
            if ($col instanceof Closure) $col($query, $dir);
            else $query->orderBy($col, $dir);
            if ($col !== $idColumn) $query->orderBy($idColumn, 'desc');
        } else {
            $query->orderBy($idColumn, 'desc');
        }
        return $query;
    }
}
