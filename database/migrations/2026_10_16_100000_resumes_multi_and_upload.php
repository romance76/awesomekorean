<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 이력서를 여러 개 두고(직접 쓴 것 / 파일로 올린 것), 지원할 때 골라 보낼 수 있게.
// 지원 시점의 직접 쓴 이력서 내용은 job_applications.resume_snapshot 에 복사해 두어, 나중에 이력서를 고쳐도 이미 낸 지원서는 그대로 보이게 한다.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resumes', function (Blueprint $table) {
            if (!Schema::hasColumn('resumes', 'kind')) $table->string('kind', 10)->default('written')->after('user_id');   // written | uploaded
            if (!Schema::hasColumn('resumes', 'file_path')) $table->string('file_path', 255)->nullable();
            if (!Schema::hasColumn('resumes', 'file_name')) $table->string('file_name', 200)->nullable();
            if (!Schema::hasColumn('resumes', 'file_mime')) $table->string('file_mime', 60)->nullable();
            if (!Schema::hasColumn('resumes', 'file_size')) $table->unsignedInteger('file_size')->nullable();
            if (!Schema::hasColumn('resumes', 'desired_pay')) $table->string('desired_pay', 100)->nullable();   // 예: 시급 $18 이상
        });
        Schema::table('job_applications', function (Blueprint $table) {
            if (!Schema::hasColumn('job_applications', 'resume_snapshot')) $table->json('resume_snapshot')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('job_applications', function (Blueprint $table) {
            if (Schema::hasColumn('job_applications', 'resume_snapshot')) $table->dropColumn('resume_snapshot');
        });
        Schema::table('resumes', function (Blueprint $table) {
            foreach (['kind', 'file_path', 'file_name', 'file_mime', 'file_size', 'desired_pay'] as $c) {
                if (Schema::hasColumn('resumes', $c)) $table->dropColumn($c);
            }
        });
    }
};
