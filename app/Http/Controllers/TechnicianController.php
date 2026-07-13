<?php

namespace App\Http\Controllers;

use App\Models\Technician;
use Illuminate\Http\Request;

class TechnicianController extends Controller
{
    public function index()
    {
        $technicians = Technician::latest()
            ->get();

        return view(
            'technicians.index',
            compact('technicians')
        );
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required',
        ]);

        Technician::create([
            'code' => $request->code,
            'name' => $request->name,
            'phone' => $request->phone,
            'address' => $request->address,
            'bank_name' => $request->bank_name,
            'bank_account_name' => $request->bank_account_name,
            'bank_account_no' => $request->bank_account_no,
            'active' => $request->active ?? true,
            'remark' => $request->remark,
        ]);

        return back()->with(
            'success',
            'เพิ่มช่างเรียบร้อย'
        );
    }

    public function update(Request $request, Technician $technician)
    {
        $request->validate([
            'name' => 'required',
        ]);

        $technician->update([
            'code' => $request->code,
            'name' => $request->name,
            'phone' => $request->phone,
            'address' => $request->address,
            'bank_name' => $request->bank_name,
            'bank_account_name' => $request->bank_account_name,
            'bank_account_no' => $request->bank_account_no,
            'active' => $request->active ?? true,
            'remark' => $request->remark,
        ]);

        return back()->with(
            'success',
            'แก้ไขช่างเรียบร้อย'
        );
    }

    public function destroy(Technician $technician)
    {
        $technician->active = false;
        $technician->save();

        return back()->with(
            'success',
            'ปิดใช้งานช่างเรียบร้อย'
        );
    }
}
