<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class BusinessClaim extends Model
{
    protected $fillable = ['business_id','user_id','document_url','status','notes'];
    public function business() { return $this->belongsTo(Business::class); }
    public function user() { return $this->belongsTo(User::class); }
}
