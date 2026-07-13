<?php

namespace App\Http\Controllers;

use App\Models\Technician;
use App\Models\TechnicianCommission;
use Illuminate\Http\Request;

class TechnicianCommissionController extends Controller
{
    public function index(Request $request)
    {
        $month = $request->month ?? date('Y-m');

        $commissions = TechnicianCommission::with([
            'technician',
            'sale',
        ])
            ->whereYear('commission_date', substr($month, 0, 4))
            ->whereMonth('commission_date', substr($month, 5, 2))
            ->orderByDesc('commission_date')
            ->orderByDesc('sale_id')
            ->get();

        $summaryByTechnician = TechnicianCommission::with('technician')
            ->selectRaw('technician_id, SUM(sale_total) as total_sales, SUM(commission_amount) as total_commission')
            ->whereYear('commission_date', substr($month, 0, 4))
            ->whereMonth('commission_date', substr($month, 5, 2))
            ->groupBy('technician_id')
            ->get();

        return view('technician_commissions.index', compact(
            'commissions',
            'summaryByTechnician',
            'month'
        ));
    }
}
