<?php

namespace App\Http\Controllers;

use App\Http\Requests\Sales\ResumeHoldBillRequest;
use App\Http\Requests\Sales\StoreHoldBillRequest;
use App\Models\HoldBill;
use App\Services\HoldBillService;
use Illuminate\Http\JsonResponse;
use Throwable;

class HoldBillController extends Controller
{
    public function index(HoldBillService $service): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $service->list()->values(),
        ]);
    }

    public function show(HoldBill $holdBill, ResumeHoldBillRequest $request, HoldBillService $service): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $service->findForResume($holdBill->getKey()),
        ]);
    }

    public function store(StoreHoldBillRequest $request, HoldBillService $service): JsonResponse
    {
        try {
            $holdBill = $service->create($request->validated(), $request->user());

            return response()->json([
                'success' => true,
                'hold_bill' => $holdBill,
            ], 201);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'ไม่สามารถพักบิลได้ กรุณาลองใหม่อีกครั้ง',
            ], 500);
        }
    }

    public function destroy(HoldBill $holdBill, HoldBillService $service): JsonResponse
    {
        $service->delete($holdBill);

        return response()->json(['success' => true]);
    }
}
