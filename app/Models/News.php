<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class News extends Model
{
    protected $fillable = ['title','title_en','content','content_en','summary','source','source_url','is_external','image_url','local_image','category_id','subcategory','view_count','published_at','is_active','ai_summary','ai_status','ai_summarized_at'];
    protected $casts = ['published_at'=>'datetime','ai_summarized_at'=>'datetime','is_active'=>'boolean','is_external'=>'boolean'];

    /**
     * 목록에 보여줄 뉴스: 이미지가 있고 요약이 너무 짧지 않은 것만.
     * (이미지 없음 / "후속기사가 이어집니다" 같은 빈 요약은 목록·NEW 집계에서 제외)
     */
    public function scopeListable($q)
    {
        $min = (int) config('services.news_list.min_summary', 50);
        return $q->where(function ($w) {
                $w->where(fn ($i) => $i->whereNotNull('local_image')->where('local_image', '!=', ''))
                  ->orWhere(fn ($i) => $i->whereNotNull('image_url')->where('image_url', '!=', ''));
            })
            ->whereRaw('CHAR_LENGTH(TRIM(summary)) >= ?', [$min]);
    }
    public function category() { return $this->belongsTo(NewsCategory::class, 'category_id'); }
}
