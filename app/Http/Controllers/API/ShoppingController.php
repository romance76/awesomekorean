<?php
namespace App\Http\Controllers\API;
use App\Http\Controllers\Controller;
use App\Models\ShoppingDeal;
use App\Support\ThumbHelper;

class ShoppingController extends Controller
{
    public function index() {
        $deals = ShoppingDeal::with('store:id,name,logo')->where('is_active', true)->orderByDesc('discount_percent')->paginate(20);
        $deals->getCollection()->transform(function ($d) {
            $d->thumbnail_url = ThumbHelper::url($d->image_url, 320);
            return $d;
        });
        return response()->json(['success' => true, 'data' => $deals]);
    }
}
