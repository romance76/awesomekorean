<?php
namespace App\Http\Controllers\API;
use App\Http\Controllers\Controller;
use App\Models\{MusicCategory, MusicTrack};

class MusicController extends Controller
{
    public function categories() { return response()->json(['success'=>true,'data'=>MusicCategory::orderBy('sort_order')->get()]); }
    public function tracks($categoryId) { return response()->json(['success'=>true,'data'=>MusicTrack::where('category_id',$categoryId)->orderBy('sort_order')->get()]); }
}
