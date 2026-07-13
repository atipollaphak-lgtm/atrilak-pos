<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\CustomerDeliveryAddress;
use Illuminate\Support\Facades\DB;
use App\Services\Sales\SaleNumberService;
use App\Services\Sales\SaleItemService;
use App\Services\Sales\StockService;
use App\Services\Sales\CommissionService;
use App\Services\Sales\ProfitGuardService;

class SaleService
{
    protected SaleNumberService $saleNumberService;
    protected SaleItemService $saleItemService;
    protected StockService $stockService;
    protected CommissionService $commissionService;
    protected ProfitGuardService $profitGuardService;

    public function __construct(
        SaleNumberService $saleNumberService,
        SaleItemService $saleItemService,
        StockService $stockService,
        CommissionService $commissionService,
        ProfitGuardService $profitGuardService
    ) {
        $this->saleNumberService = $saleNumberService;
        $this->saleItemService = $saleItemService;
        $this->stockService = $stockService;
$this->commissionService = $commissionService;
$this->profitGuardService = $profitGuardService;
    }
    public function createSale(array $data)
    {
        return DB::transaction(function () use ($data) {

            $saleDate = $data['sale_date'];

            $saleNo = $this->saleNumberService
                ->generate($saleDate);

            $grandTotal = $data['grand_total'] ?? 0;
            $deliveryType = $data['delivery_type'] ?? 'delivery';
            $discount = $data['discount'] ?? 0;

            $deliveryFee = 0;
            $minimumProfit = 0;
            $deliveryZoneId = null;

            if ($deliveryType === 'delivery') {
                $address = CustomerDeliveryAddress::with('deliveryZone')
                    ->find($data['customer_delivery_address_id'] ?? null);

                if ($address && $address->deliveryZone) {
                    $deliveryFee = (float) $address->deliveryZone->base_delivery_fee;
                    $minimumProfit = (float) $address->deliveryZone->minimum_profit;
                    $deliveryZoneId = $address->deliveryZone->id;
                }
            } else {
                $deliveryFee = 0;
            }

            $netTotal = $grandTotal + $deliveryFee - $discount;

            $sale = new Sale();

            $sale->sale_no = $saleNo;
            $sale->customer_id = $data['customer_id'] ?? null;
            $sale->customer_delivery_address_id =
                $data['customer_delivery_address_id'] ?? null;
            $sale->technician_id = $data['technician_id'] ?? null;
            $sale->sale_date = $saleDate;
            $sale->total_amount = $netTotal;
            $sale->delivery_fee = $deliveryFee;
            $sale->delivery_type = $deliveryType;
            $sale->discount = $discount;

            $sale->save();

            $this->saleItemService
                ->createItems($sale, $data['items']);

            $productProfit = $sale->items()->sum('profit');

            $profitGuardResult = $this->profitGuardService->check(
                [
                    'delivery_type' => $deliveryType,
                    'delivery_fee' => $deliveryFee,
                    'delivery_zone_id' => $deliveryZoneId,
                    'minimum_profit' => $minimumProfit,
                ],
                $productProfit
            );


            if (!$profitGuardResult['passed']) {
                throw new \Exception(
                    $profitGuardResult['message']
                );
            }

            $this->stockService
                ->deductFromSale($sale);

            $this->commissionService
                ->createFromSale($sale);

            return $sale;
        });
    }
}
