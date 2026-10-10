<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

// 신고 처리 이력 한 줄 (누가 언제 무엇을 했는지). created_at 만 쓰고 수정·삭제하지 않는다.
class ReportLog extends Model
{
    public $timestamps = false;
    protected $fillable = ['report_id','action','from_status','to_status','admin_id','actor_name','note','estimated','created_at'];
    protected $casts = ['estimated' => 'boolean', 'created_at' => 'datetime'];

    public function report() { return $this->belongsTo(Report::class); }
}