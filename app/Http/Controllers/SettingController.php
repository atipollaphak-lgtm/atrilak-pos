<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSettingRequest;
use App\Models\Setting;

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

    public function update(UpdateSettingRequest $request)
    {
        $setting = Setting::firstOrCreate([], ['branch_type' => 'head_office']);

        $data = $request->safe()->only([
            'store_name',
            'store_address',
            'store_phone',
            'tax_number',
            'branch_type',
            'branch_number',
        ]);

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
