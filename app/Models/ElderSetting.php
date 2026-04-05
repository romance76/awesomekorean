<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ElderSetting extends Model
{
    protected $fillable = ['user_id','guardian_id','checkin_interval','sos_contacts','medications','health_notes'];
    protected $casts = ['sos_contacts'=>'array','medications'=>'array',];
}
