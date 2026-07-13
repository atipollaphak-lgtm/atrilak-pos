<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function sales()
    {
        return view('reports.sales');
    }

    public function profit()
    {
        return view('reports.profit');
    }

    public function stock()
    {
        return view('reports.stock');
    }

    public function dailyProfit(Request $request)
    {
        $date = $request->input('date', date('Y-m-d'));

        $sales = Sale::with(['customer', 'items'])
            ->whereDate('sale_date', $date)
            ->orderBy('sale_date')
            ->get();

        $totalSales = $sales->sum('total_amount');

        $totalCost = $sales->sum(function ($sale) {
            return $sale->items->sum(function ($item) {
                return $item->cost_price * $item->qty;
            });
        });

        $totalProfit = $sales->sum(function ($sale) {
            return $sale->items->sum('profit');
        });

        return view('reports.daily-profit', compact(
            'date',
            'sales',
            'totalSales',
            'totalCost',
            'totalProfit'
        ));
    }

    public function monthlyProfit(Request $request)
    {
        $month = $request->input('month', date('m'));
        $year = $request->input('year', date('Y'));

        $sales = Sale::with(['customer', 'items'])
            ->whereMonth('sale_date', $month)
            ->whereYear('sale_date', $year)
            ->orderBy('sale_date')
            ->get();

        $totalSales = $sales->sum('total_amount');

        $totalCost = $sales->sum(function ($sale) {
            return $sale->items->sum(function ($item) {
                return $item->cost_price * $item->qty;
            });
        });

        $totalProfit = $sales->sum(function ($sale) {
            return $sale->items->sum('profit');
        });

        return view('reports.monthly-profit', compact(
            'month',
            'year',
            'sales',
            'totalSales',
            'totalCost',
            'totalProfit'
        ));
    }

    public function yearlyProfit(Request $request)
    {
        $year = $request->input('year', date('Y'));

        $sales = Sale::with(['customer', 'items'])
            ->whereYear('sale_date', $year)
            ->orderBy('sale_date')
            ->get();

        $totalSales = $sales->sum('total_amount');

        $totalCost = $sales->sum(function ($sale) {
            return $sale->items->sum(function ($item) {
                return $item->cost_price * $item->qty;
            });
        });

        $totalProfit = $sales->sum(function ($sale) {
            return $sale->items->sum('profit');
        });

        return view('reports.yearly-profit', compact(
            'year',
            'sales',
            'totalSales',
            'totalCost',
            'totalProfit'
        ));
    }
    public function productSales()
    {
        $month = request('month', date('m'));
        $year = request('year', date('Y'));

        $items = SaleItem::with('product', 'sale')
            ->whereHas('sale', function ($query) use ($month, $year) {
                $query->whereMonth('sale_date', $month)
                    ->whereYear('sale_date', $year);
            })
            ->get();

        $products = [];

        foreach ($items as $item) {
            $productId = $item->product_id;

            if (!isset($products[$productId])) {
                $products[$productId] = [
                    'name' => $item->product->name ?? '-',
                    'unit' => $item->product->unit ?? '',
                    'qty' => 0,
                    'sales' => 0,
                ];
            }

            $products[$productId]['qty'] += $item->qty;
            $products[$productId]['sales'] += $item->total;
        }

        return view('reports.product_sales', compact(
            'month',
            'year',
            'products'
        ));
    }
    public function bestSeller()
    {
        $month = request('month', date('m'));
        $year = request('year', date('Y'));

        $items = \App\Models\SaleItem::with('product', 'sale')
            ->whereHas('sale', function ($query) use ($month, $year) {
                $query->whereMonth('sale_date', $month)
                    ->whereYear('sale_date', $year);
            })
            ->get();

        $products = [];

        foreach ($items as $item) {

            $productId = $item->product_id;

            if (!isset($products[$productId])) {

                $products[$productId] = [
                    'name' => $item->product->name ?? '-',
                    'qty' => 0,
                ];
            }

            $products[$productId]['qty'] += $item->qty;
        }

        usort($products, function ($a, $b) {
            return $b['qty'] <=> $a['qty'];
        });

        $products = array_slice($products, 0, 10);

        return view(
            'reports.best_seller',
            compact(
                'month',
                'year',
                'products'
            )
        );
    }

    public function exportDailyProfit(Request $request)
    {
        $date = $request->input('date', date('Y-m-d'));

        $sales = Sale::with(['customer', 'items.product'])
            ->whereDate('sale_date', $date)
            ->orderBy('sale_date')
            ->get();

        $fileName = 'daily-profit-' . $date . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ];

        $callback = function () use ($sales) {
            $file = fopen('php://output', 'w');

            // UTF-8 BOM สำหรับ Excel ภาษาไทย
            fwrite($file, "\xEF\xBB\xBF");

            fputcsv($file, [
                'เลขที่บิล',
                'วันที่ขาย',
                'ลูกค้า',
                'สินค้า',
                'จำนวน',
                'ราคาขาย',
                'ต้นทุน',
                'ยอดขาย',
                'กำไร',
            ]);

            foreach ($sales as $sale) {
                foreach ($sale->items as $item) {
                    $costPrice = $item->cost_price ?? $item->product->cost_price ?? 0;
                    $sellingPrice = $item->selling_price ?? 0;
                    $qty = $item->qty ?? 0;

                    $total = $sellingPrice * $qty;
                    $profit = ($sellingPrice - $costPrice) * $qty;

                    fputcsv($file, [
                        $sale->sale_no,
                        $sale->sale_date,
                        $sale->customer->name ?? 'ลูกค้าทั่วไป',
                        $item->product->name ?? '-',
                        $qty,
                        $sellingPrice,
                        $costPrice,
                        $total,
                        $profit,
                    ]);
                }
            }

            fclose($file);
        };

        return response()->stream(
            $callback,
            200,
            $headers
        );
    }

    public function exportMonthlyProfit(Request $request)
    {
        $month = $request->input('month', date('m'));
        $year = $request->input('year', date('Y'));

        $sales = Sale::with(['customer', 'items.product'])
            ->whereMonth('sale_date', $month)
            ->whereYear('sale_date', $year)
            ->orderBy('sale_date')
            ->get();

        $fileName = 'monthly-profit-' . $year . '-' . $month . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ];

        $callback = function () use ($sales) {
            $file = fopen('php://output', 'w');

            fwrite($file, "\xEF\xBB\xBF");

            fputcsv($file, [
                'เลขที่บิล',
                'วันที่ขาย',
                'ลูกค้า',
                'สินค้า',
                'จำนวน',
                'ราคาขาย',
                'ต้นทุน',
                'ยอดขาย',
                'กำไร',
            ]);

            foreach ($sales as $sale) {
                foreach ($sale->items as $item) {
                    $costPrice = $item->cost_price ?? $item->product->cost_price ?? 0;
                    $sellingPrice = $item->selling_price ?? 0;
                    $qty = $item->qty ?? 0;

                    $total = $sellingPrice * $qty;
                    $profit = ($sellingPrice - $costPrice) * $qty;

                    fputcsv($file, [
                        $sale->sale_no,
                        $sale->sale_date,
                        $sale->customer->name ?? 'ลูกค้าทั่วไป',
                        $item->product->name ?? '-',
                        $qty,
                        $sellingPrice,
                        $costPrice,
                        $total,
                        $profit,
                    ]);
                }
            }

            fclose($file);
        };

        return response()->stream(
            $callback,
            200,
            $headers
        );
    }

    public function exportYearlyProfit(Request $request)
    {
        $year = $request->input('year', date('Y'));

        $sales = Sale::with(['customer', 'items.product'])
            ->whereYear('sale_date', $year)
            ->orderBy('sale_date')
            ->get();

        $fileName = 'yearly-profit-' . $year . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ];

        $callback = function () use ($sales) {

            $file = fopen('php://output', 'w');

            fwrite($file, "\xEF\xBB\xBF");

            fputcsv($file, [
                'เลขที่บิล',
                'วันที่ขาย',
                'ลูกค้า',
                'สินค้า',
                'จำนวน',
                'ราคาขาย',
                'ต้นทุน',
                'ยอดขาย',
                'กำไร',
            ]);

            foreach ($sales as $sale) {

                foreach ($sale->items as $item) {

                    $costPrice = $item->cost_price
                        ?? $item->product->cost_price
                        ?? 0;

                    $sellingPrice = $item->selling_price ?? 0;
                    $qty = $item->qty ?? 0;

                    $total = $sellingPrice * $qty;

                    $profit =
                        ($sellingPrice - $costPrice)
                        * $qty;

                    fputcsv($file, [
                        $sale->sale_no,
                        $sale->sale_date,
                        $sale->customer->name ?? 'ลูกค้าทั่วไป',
                        $item->product->name ?? '-',
                        $qty,
                        $sellingPrice,
                        $costPrice,
                        $total,
                        $profit,
                    ]);
                }
            }

            fclose($file);
        };

        return response()->stream(
            $callback,
            200,
            $headers
        );
    }
    public function exportProductSales(Request $request)
    {
        $month = $request->input('month', date('m'));
        $year = $request->input('year', date('Y'));

        $items = SaleItem::with('product', 'sale')
            ->whereHas('sale', function ($query) use ($month, $year) {
                $query->whereMonth('sale_date', $month)
                    ->whereYear('sale_date', $year);
            })
            ->get();

        $products = [];

        foreach ($items as $item) {
            $productId = $item->product_id;

            if (!isset($products[$productId])) {
                $products[$productId] = [
                    'name' => $item->product->name ?? '-',
                    'unit' => $item->product->unit ?? '',
                    'qty' => 0,
                    'sales' => 0,
                ];
            }

            $products[$productId]['qty'] += $item->qty;
            $products[$productId]['sales'] += $item->total;
        }

        $fileName = 'product-sales-' . $year . '-' . $month . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ];

        $callback = function () use ($products) {
            $file = fopen('php://output', 'w');

            fwrite($file, "\xEF\xBB\xBF");

            fputcsv($file, [
                'สินค้า',
                'หน่วย',
                'จำนวนขาย',
                'ยอดขายรวม',
            ]);

            foreach ($products as $product) {
                fputcsv($file, [
                    $product['name'],
                    $product['unit'],
                    $product['qty'],
                    $product['sales'],
                ]);
            }

            fclose($file);
        };

        return response()->stream(
            $callback,
            200,
            $headers
        );
    }

    public function exportBestSeller(Request $request)
    {
        $month = $request->input('month', date('m'));
        $year = $request->input('year', date('Y'));

        $items = SaleItem::with('product', 'sale')
            ->whereHas('sale', function ($query) use ($month, $year) {
                $query->whereMonth('sale_date', $month)
                    ->whereYear('sale_date', $year);
            })
            ->get();

        $products = [];

        foreach ($items as $item) {
            $productId = $item->product_id;

            if (!isset($products[$productId])) {
                $products[$productId] = [
                    'name' => $item->product->name ?? '-',
                    'unit' => $item->product->unit ?? '',
                    'qty' => 0,
                ];
            }

            $products[$productId]['qty'] += $item->qty;
        }

        usort($products, function ($a, $b) {
            return $b['qty'] <=> $a['qty'];
        });

        $products = array_slice($products, 0, 10);

        $fileName = 'best-seller-' . $year . '-' . $month . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ];

        $callback = function () use ($products) {
            $file = fopen('php://output', 'w');

            fwrite($file, "\xEF\xBB\xBF");

            fputcsv($file, [
                'อันดับ',
                'สินค้า',
                'หน่วย',
                'จำนวนขาย',
            ]);

            $rank = 1;

            foreach ($products as $product) {
                fputcsv($file, [
                    $rank,
                    $product['name'],
                    $product['unit'],
                    $product['qty'],
                ]);

                $rank++;
            }

            fclose($file);
        };

        return response()->stream(
            $callback,
            200,
            $headers
        );
    }
}
