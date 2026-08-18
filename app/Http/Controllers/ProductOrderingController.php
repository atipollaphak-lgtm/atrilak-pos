<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Services\ProductOrderingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ProductOrderingController extends Controller
{
    public function updateOrder(
        Request $request,
        Category $category,
        ProductOrderingService $orderingService
    ): JsonResponse {
        $validator = Validator::make($request->all(), [
            'product_ids' => ['present', 'array'],
            'product_ids.*' => ['integer', 'distinct'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'ลำดับสินค้าไม่ถูกต้อง',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $orderingService->reorder($category, $validator->validated()['product_ids']);
        } catch (ValidationException $exception) {
            return response()->json([
                'message' => 'ลำดับสินค้าไม่ถูกต้อง',
                'errors' => $exception->errors(),
            ], 422);
        }

        return response()->json(['message' => 'บันทึกลำดับสินค้าแล้ว']);
    }
}
