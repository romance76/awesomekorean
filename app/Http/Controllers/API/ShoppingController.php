<?php
namespace App\Http\Controllers\API;
use App\Http\Controllers\Controller;
use App\Models\ShoppingDeal;

class ShoppingController extends Controller
{
    public function index() {
        $deals = ShoppingDeal::with('store:id,name,logo')->where('is_active', true)->orderByDesc('discount_percent')->paginate(20);
        return response()->json(['success' => true, 'data' => $deals]);
    }
}
