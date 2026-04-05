<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Report extends Model
{
    protected $fillable = ['reporter_id','reportable_type','reportable_id','reason','content','status','admin_note'];

}
