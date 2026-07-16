<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Supplier;
use App\Services\Sales\SaleFinancialSnapshotService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(SaleFinancialSnapshotService $financialSnapshots)
    {
        $today = Carbon::today();

        $totalProducts = Product::count();
        $totalCustomers = Customer::count();

        $totalSuppliers = Supplier::count();

        $todaySaleCount = Sale::whereDate(
            'sale_date',
            $today
        )->count();

        $outOfStockCount = Product::where(
            'stock_qty',
            0
        )->where(
            'active',
            true
        )->count();

        $lowStockProducts = Product::whereColumn(
            'stock_qty',
            '<=',
            'minimum_stock'
        )
            ->where('active', true)
            ->orderBy('stock_qty')
            ->get();

        $lowStockCount = $lowStockProducts->count();

        $todaySales = Sale::whereDate('sale_date', $today)
            ->sum('total_amount');

        $todayProfit = $financialSnapshots->sumProfit(Sale::with('items')
            ->whereDate('sale_date', $today)
            ->get());

        $month = date('m');
        $year = date('Y');

        $items = SaleItem::with('product.unitRelation', 'sale')
            ->whereHas('sale', function ($query) use ($month, $year) {
                $query->whereMonth('sale_date', $month)
                    ->whereYear('sale_date', $year);
            })
            ->get();

        $bestProducts = [];

        foreach ($items as $item) {
            $productId = $item->product_id;

            if (! isset($bestProducts[$productId])) {
                $bestProducts[$productId] = [
                    'name' => $item->product->name ?? '-',
                    'unit' => $item->product->unitRelation->name
    ?? $item->product->unit
    ?? '',
                    'qty' => 0,
                ];
            }

            $bestProducts[$productId]['qty'] += $item->qty;
        }

        usort($bestProducts, function ($a, $b) {
            return $b['qty'] <=> $a['qty'];
        });

        $bestProducts = array_slice($bestProducts, 0, 10);

        $salesChart = Sale::select(
            DB::raw('DATE(sale_date) as sale_day'),
            DB::raw('SUM(total_amount) as total_sales')
        )
            ->whereDate(
                'sale_date',
                '>=',
                now()->subDays(6)
            )
            ->groupBy('sale_day')
            ->orderBy('sale_day')
            ->get();

        $chartLabels = [];
        $chartSales = [];

        foreach ($salesChart as $row) {

            $chartLabels[] =
                date(
                    'd/m',
                    strtotime($row->sale_day)
                );

            $chartSales[] =
                (float) $row->total_sales;
        }

        $monthSales = Sale::whereMonth(
            'sale_date',
            date('m')
        )->whereYear(
            'sale_date',
            date('Y')
        )->sum('total_amount');

        $monthProfit = $financialSnapshots->sumProfit(Sale::with('items')
            ->whereMonth('sale_date', date('m'))
            ->whereYear('sale_date', date('Y'))
            ->get());
        $stockValue = Product::where('active', true)
            ->get()
            ->sum(function ($product) {
                return $product->stock_qty * $product->cost_price;
            });

        return view(

            'dashboard',
            compact(
                'totalProducts',
                'lowStockProducts',
                'lowStockCount',
                'todaySales',
                'todayProfit',
                'totalCustomers',
                'totalSuppliers',
                'todaySaleCount',
                'outOfStockCount',
                'bestProducts',
                'chartLabels',
                'chartSales',
                'monthSales',
                'monthProfit',
                'stockValue',
            )
        );
    }
}
