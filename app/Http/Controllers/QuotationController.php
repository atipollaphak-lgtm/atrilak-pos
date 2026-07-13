<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\QuotationItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockMovement;

class QuotationController extends Controller
{
    public function index()
    {
        $quotations = Quotation::with('customer')
            ->latest()
            ->take(30)
            ->get();

        return view(
            'quotations.index',
            compact('quotations')
        );
    }

    public function create()
    {
        $customers = Customer::where('active', true)
            ->orderBy('name')
            ->get();

        $products = Product::where('active', true)
            ->orderBy('name')
            ->get();

        return view(
            'quotations.create',
            compact(
                'customers',
                'products'
            )
        );
    }

    public function store(Request $request)
    {
        $request->validate([
            'quotation_date' => 'required|date',
            'product_id' => 'required|array',
            'qty' => 'required|array',
            'selling_price' => 'required|array',
        ]);

        DB::transaction(function () use ($request) {

            $date = $request->quotation_date;

            $running = Quotation::whereDate(
                'quotation_date',
                $date
            )->count() + 1;

            $quotationNo = 'QT-' .
                date('Ymd', strtotime($date)) .
                '-' .
                str_pad($running, 4, '0', STR_PAD_LEFT);

            $totalAmount = 0;

            foreach ($request->product_id as $index => $productId) {
                if (!$productId) {
                    continue;
                }

                $qty = (int) ($request->qty[$index] ?? 0);
                $price = (float) ($request->selling_price[$index] ?? 0);

                $totalAmount += $qty * $price;
            }

            $quotation = Quotation::create([
                'quotation_no' => $quotationNo,
                'customer_id' => $request->customer_id,
                'quotation_date' => $date,
                'total_amount' => $totalAmount,
                'remark' => $request->remark,
                'status' => 'draft',
            ]);

            foreach ($request->product_id as $index => $productId) {
                if (!$productId) {
                    continue;
                }

                $qty = (int) ($request->qty[$index] ?? 0);
                $price = (float) ($request->selling_price[$index] ?? 0);
                $total = $qty * $price;

                QuotationItem::create([
                    'quotation_id' => $quotation->id,
                    'product_id' => $productId,
                    'qty' => $qty,
                    'selling_price' => $price,
                    'total' => $total,
                ]);
            }
        });

        return redirect()
            ->route('quotations.index')
            ->with('success', 'สร้างใบเสนอราคาสำเร็จ');
    }

    public function show(Quotation $quotation)
    {
        $quotation->load([
            'customer',
            'items.product',
        ]);

        return view(
            'quotations.show',
            compact('quotation')
        );
    }

    public function print(Quotation $quotation)
    {
        $quotation->load([
            'customer',
            'items.product',
        ]);

        return view(
            'quotations.print',
            compact('quotation')
        );
    }

    public function destroy(Quotation $quotation)
    {
        $quotation->delete();

        return redirect()
            ->route('quotations.index')
            ->with('success', 'ลบใบเสนอราคาเรียบร้อย');
    }

    public function convertToSale(Quotation $quotation)
    {
        DB::transaction(function () use ($quotation, &$sale) {

            $running = Sale::whereDate(
                'sale_date',
                now()
            )->count() + 1;

            $saleNo =
                'SAL-' .
                now()->format('Ymd') .
                '-' .
                str_pad($running, 4, '0', STR_PAD_LEFT);

            $sale = Sale::create([
                'sale_no'      => $saleNo,
                'customer_id'  => $quotation->customer_id,
                'sale_date'    => now()->toDateString(),
                'total_amount' => $quotation->total_amount,
            ]);

            foreach ($quotation->items as $item) {

                $product = $item->product;

                SaleItem::create([
                    'sale_id'       => $sale->id,
                    'product_id'    => $product->id,
                    'qty'           => $item->qty,
                    'selling_price' => $item->selling_price,
                    'cost_price'    => $product->cost_price,
                    'profit'        => ($item->selling_price - $product->cost_price)
                        * $item->qty,
                    'total'         => $item->total,
                ]);

                $before = $product->stock_qty;
                $after  = $before - $item->qty;

                $product->update([
                    'stock_qty' => $after,
                ]);

                StockMovement::create([
                    'product_id'     => $product->id,
                    'type'           => 'OUT',
                    'qty'            => $item->qty,
                    'stock_before'   => $before,
                    'stock_after'    => $after,
                    'reference_type' => Sale::class,
                    'reference_id'   => $sale->id,
                    'remark'         => 'Quotation Convert',
                ]);
            }

            $quotation->update([
                'status' => 'converted',
            ]);
        });

        return redirect()
            ->route('sales.show', $sale)
            ->with(
                'success',
                'แปลงเป็นใบขายเรียบร้อย'
            );
    }
}
