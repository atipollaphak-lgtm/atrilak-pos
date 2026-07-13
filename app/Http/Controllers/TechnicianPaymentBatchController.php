<?php

namespace App\Http\Controllers;

use App\Models\Technician;
use App\Models\TechnicianPaymentBatch;
use App\Services\TechnicianPaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TechnicianPaymentBatchController extends Controller
{
    public function index()
    {
        $batches = TechnicianPaymentBatch::latest()
            ->paginate(20);

        return view('technician-payment-batches.index', compact('batches'));
    }

    public function create()
    {
        $technicians = Technician::where('active', true)
            ->orderBy('name')
            ->get();

        return view('technician-payment-batches.create', [
            'technicians' => $technicians,
            'preview' => null,
            'selectedTechnicianIds' => [],
        ]);
    }

    public function preview(Request $request, TechnicianPaymentService $service)
    {
        $request->validate([
            'payment_date' => ['required', 'date'],
            'technician_ids' => ['required', 'array'],
            'technician_ids.*' => ['exists:technicians,id'],
        ]);

        $technicians = Technician::where('active', true)
            ->orderBy('name')
            ->get();

        $selectedTechnicianIds = $request->technician_ids;

        $preview = $service->buildPreview($selectedTechnicianIds);

        return view('technician-payment-batches.create', [
            'technicians' => $technicians,
            'preview' => $preview,
            'selectedTechnicianIds' => $selectedTechnicianIds,
            'paymentDate' => $request->payment_date,
            'remark' => $request->remark,
        ]);
    }
    public function store(Request $request, TechnicianPaymentService $service)
    {


        $request->validate([
            'payment_date' => ['required', 'date'],
            'technician_ids' => ['required', 'array'],
            'technician_ids.*' => ['exists:technicians,id'],
        ]);

        try {
            $batch = $service->confirm(
                $request->technician_ids,
                $request->payment_date,
                $request->remark,
                Auth::check() ? Auth::id() : null
            );


            return redirect()
                ->route('technician-payment-batches.index')
                ->with('success', 'สร้างรอบจ่าย ' . $batch->batch_no . ' สำเร็จ');
        } catch (\Throwable $e) {
            dd($e->getMessage(), $e->getTraceAsString());
        }
    }

    public function show(TechnicianPaymentBatch $batch)
    {
        $batch->load([
            'commissions.technician',
            'commissions.sale',
        ]);

        $groups = $batch->commissions->groupBy('technician_id');

        return view('technician-payment-batches.show', compact(
            'batch',
            'groups'
        ));
    }

    public function print(TechnicianPaymentBatch $batch)
    {
        $batch->load([
            'commissions.technician',
            'commissions.sale',
        ]);

        $groups = $batch->commissions->groupBy('technician_id');

        return view('technician-payment-batches.print', compact(
            'batch',
            'groups'
        ));
    }
}
