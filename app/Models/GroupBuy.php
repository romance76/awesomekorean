<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class GroupBuy extends Model
{
    protected $fillable = ['user_id','title','content','images','product_url','original_price','group_price','min_participants','max_participants','current_participants','lat','lng','city','state','status','deadline'];
    protected $casts = ['images'=>'array','deadline'=>'datetime',];
}
