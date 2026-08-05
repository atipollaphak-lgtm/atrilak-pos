<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSettingRequest;
use App\Models\Setting;
use Illuminate\Support\Facades\Storage;

class SettingController extends Controller
{
    public function index()
    {
        $setting = Setting::first();

        if (! $setting) {
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
        $oldLogoPath = $setting->logo_image;

        $data = $request->safe()->only([
            'store_name',
            'store_address',
            'store_phone',
            'tax_number',
            'branch_type',
            'branch_number',
            'receipt_footer',
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

        if (
            array_key_exists('logo_image', $data)
            && $oldLogoPath
            && $oldLogoPath !== $data['logo_image']
            && $oldLogoPath !== $setting->qr_image
        ) {
            Storage::disk('public')->delete($oldLogoPath);
        }

        return back()->with(
            'success',
            'บันทึกตั้งค่าร้านเรียบร้อย'
        );
    }
}
