<?php

namespace App\Http\Controllers;

use App\Models\ProductUnit;
use App\Services\ProductPriceTierService;
use Illuminate\Http\Request;

class PriceController extends Controller
{
    protected ProductPriceTierService $priceTierService;

    public function __construct(
        ProductPriceTierService $priceTierService
    ) {
        $this->priceTierService = $priceTierService;
    }

    public function calculate(Request $request)
    {
        $validated = $request->validate([
            'product_unit_id' => 'required|exists:product_units,id',
            'qty' => 'required|numeric|min:0.0001',
        ]);

        $productUnit = ProductUnit::findOrFail(
            $validated['product_unit_id']
        );

        $pricing = $this->priceTierService->getPricing(
            $productUnit,
            (float) $validated['qty']
        );

        return response()->json([
            'success' => true,
            'data' => $pricing,
        ]);
    }
}
