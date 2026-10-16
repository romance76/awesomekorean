<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Resume extends Model
{
    public const MAX_PER_USER = 5;
    /** 직접 쓴 이력서가 지원서에 복사해 두는 항목 */
    public const SNAPSHOT_FIELDS = ['title', 'name', 'phone', 'email', 'summary', 'category', 'desired_type', 'desired_pay', 'city', 'state', 'experience', 'education', 'skills', 'certifications'];

    protected $fillable = [
        'user_id', 'kind', 'title', 'name', 'phone', 'email', 'summary',
        'category', 'desired_type', 'desired_salary', 'desired_salary_type', 'desired_pay',
        'city', 'state', 'lat', 'lng',
        'experience', 'education', 'skills', 'certifications',
        'file_path', 'file_name', 'file_mime', 'file_size',
        'is_public', 'is_active', 'view_count',
    ];

    protected $hidden = ['file_path'];   // 저장 위치는 밖으로 내보내지 않는다 (파일은 권한을 확인하는 주소로만 내려줌)

    protected $casts = [
        'is_public' => 'boolean',
        'is_active' => 'boolean',
        'lat' => 'decimal:7',
        'lng' => 'decimal:7',
    ];

    public function user() { return $this->belongsTo(User::class); }
    public function scopeActive($q) { return $q->where('is_active', true); }
    public function scopePublic($q) { return $q->where('is_public', true); }
    public function isUploaded(): bool { return $this->kind === 'uploaded'; }

    /** 지원서에 복사해 둘 내용 (파일로 올린 이력서는 파일 정보만) */
    public function snapshot(): array
    {
        if ($this->isUploaded()) {
            return ['kind' => 'uploaded', 'title' => $this->title, 'name' => $this->name, 'file_name' => $this->file_name, 'file_mime' => $this->file_mime, 'file_size' => $this->file_size];
        }
        $out = ['kind' => 'written'];
        foreach (self::SNAPSHOT_FIELDS as $f) $out[$f] = $this->{$f};
        return $out;
    }
}
