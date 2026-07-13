<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function index()
    {
        $setting = Setting::first();

        if (!$setting) {
            $setting = Setting::create([]);
        }

        return view(
            'settings.index',
            compact('setting')
        );
    }

    public function update(Request $request)
    {
        $setting = Setting::first();

        if (!$setting) {
            $setting = Setting::create([]);
        }

        $data = [
    'store_name' => $request->store_name,
    'store_address' => $request->store_address,
    'store_phone' => $request->store_phone,
    'tax_number' => $request->tax_number,
    'branch_type' => $request->branch_type,
    'branch_number' => $request->branch_number,
];

        if ($request->hasFile('logo_image')) {
            $data['logo_image'] = $request->file('logo_image')
                ->store('settings', 'public');
        }

        if ($request->hasFile('qr_image')) {
            $data['qr_image'] = $request->file('qr_image')
                ->store('settings', 'public');
        }

        $setting->update($data);

        return back()->with(
            'success',
            'บันทึกตั้งค่าร้านเรียบร้อย'
        );
    }
}
