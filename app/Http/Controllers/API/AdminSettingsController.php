<?php
namespace App\Http\Controllers\API;
use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use Illuminate\Http\Request;

class AdminSettingsController extends Controller
{
    public function index() {
        $settings = SiteSetting::all()->pluck('value', 'key');
        return response()->json(['success'=>true,'data'=>$settings]);
    }

    public function getPublic() {
        $settings = SiteSetting::whereIn('key', ['site_name','logo_url','primary_color'])->pluck('value','key');
        return response()->json(['success'=>true,'data'=>$settings]);
    }

    public function update(Request $request) {
        foreach ($request->all() as $key => $value) {
            SiteSetting::updateOrCreate(['key'=>$key], ['value'=>$value]);
        }
        return response()->json(['success'=>true]);
    }

    public function uploadLogo(Request $request) {
        $request->validate(['logo'=>'required|image']);
        $path = $request->file('logo')->storeAs('public', 'logo_00.jpg');
        copy(storage_path('app/' . $path), public_path('images/logo_00.jpg'));
        return response()->json(['success'=>true,'data'=>['url'=>'/images/logo_00.jpg']]);
    }
}
