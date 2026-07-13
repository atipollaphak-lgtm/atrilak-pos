<?php

namespace App\Http\Controllers;

use App\Models\Product;

class BarcodeController extends Controller
{
    public function index()
    {
        $products = Product::where('active', true)
            ->orderBy('name')
            ->get();

        return view(
            'barcodes.index',
            compact('products')
        );
    }
}
