<?php

namespace App\Http\Controllers;

use App\Models\Technician;
use App\Models\TechnicianCommission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\TechnicianPaymentBatch;

class TechnicianPaymentController extends Controller
{
    public function index(Request $request)
    {
        $month = $request->month ?? date('Y-m');

        $technicians = Technician::where('active', true)->get();

        $summaries = TechnicianCommission::with('technician')
            ->selectRaw('technician_id, SUM(commission_amount) as total_commission')
            ->where('status', 'pending')
            ->whereHas('sale', fn ($query) => $query->active())
            ->whereYear('commission_date', substr($month, 0, 4))
            ->whereMonth('commission_date', substr($month, 5, 2))
            ->groupBy('technician_id')
            ->get();

        return view('technician-payments.index', compact(
            'month',
            'technicians',
            'summaries'
        ));
    }

    public function pay(Request $request)
    {
        $request->validate([
            'technician_id' => 'required|exists:technicians,id',
            'month' => 'required',
        ]);

        DB::transaction(function () use ($request) {
            TechnicianCommission::where('technician_id', $request->technician_id)
                ->where('status', 'pending')
                ->whereHas('sale', fn ($query) => $query->active())
                ->whereYear('commission_date', substr($request->month, 0, 4))
                ->whereMonth('commission_date', substr($request->month, 5, 2))
                ->update([
                    'status' => 'paid',
                    'paid_at' => now(),
                    'paid_by' => Auth::id(),
                ]);
        });

        return redirect()
            ->route('technician-payments.index', ['month' => $request->month])
            ->with('success', 'จ่ายค่าช่างเรียบร้อยแล้ว');
    }

}
