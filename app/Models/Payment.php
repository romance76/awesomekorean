<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Payment extends Model
{
    protected $fillable = ['user_id','stripe_payment_id','amount','currency','points_purchased','status'];
    protected $casts = ['amount'=>'decimal:2',];
}
