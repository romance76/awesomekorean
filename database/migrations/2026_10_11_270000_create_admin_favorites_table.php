<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 관리자 즐겨찾기(자주 쓰는 메뉴) 서버 저장 — 새 표 하나만 추가한다(할 일 #91).
 * 계정마다 한 줄(user_id unique), items 는 메뉴 주소 목록(JSON 글자). 그래서 PC 와 휴대폰이 같은 구성을 본다.
 * 여러 번 돌려도 안전, down() 은 데이터를 지우지 않는다(표 삭제 금지 규칙).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('admin_favorites')) return;
        Schema::create('admin_favorites', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->text('items')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // 데이터 보호: 표를 지우지 않는다.
    }
};
