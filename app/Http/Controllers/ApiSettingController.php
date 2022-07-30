<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Setting;

class ApiSettingController extends Controller
{
    public function getSettingByKey($key) {
        return response()->json(
            Setting::where('key', $key)->first()
        );
    }

    public function setSettingByKey(Request $request, $key) {
        abort_if(Gate::denies('setting_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $setting = Setting::where('key', $key)->first();
        if (!$setting) {
            $setting = new Setting();
            $setting->key = $key;
        }
        $setting->value = $request->value;
        $setting->save();

        return \response()->json($setting);
    }
}
