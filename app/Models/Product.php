<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    protected $fillable = [
        'category_id',
        'unit_id',
        'barcode',
        'sku',
        'product_code',
        'name',
        'image_path',
        'unit',
        'cost_price',
        'selling_price',
        'pricing_method',
        'pricing_source',
        'pricing_value',
        'rounding_direction',
        'rounding_unit',
        'pricing_reviewed_cost',
        'pricing_reviewed_at',
        'pricing_reviewed_by',
        'stock_qty',
        'minimum_stock',
        'vat_enabled',
        'active',
        'remark',
        'auto_price_enabled',
        'price_lock',
        'profit_percent',
        'satang_rounding_mode',
        'baht_rounding_mode',
    ];

    protected $casts = [
        'cost_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'pricing_value' => 'decimal:2',
        'rounding_unit' => 'decimal:2',
        'pricing_reviewed_cost' => 'decimal:2',
        'pricing_reviewed_at' => 'datetime',
        'stock_qty' => 'decimal:4',
        'minimum_stock' => 'decimal:4',
    ];

    public function getImageUrlAttribute(): ?string
    {
        $rawPath = trim((string) $this->image_path);

        if ($rawPath === '') {
            return null;
        }

        if (preg_match('/^[A-Za-z]:[\\\\\/]/', $rawPath) === 1
            || str_starts_with($rawPath, '\\\\')) {
            return null;
        }

        if (preg_match('#^https?://#i', $rawPath) === 1) {
            return $rawPath;
        }

        $path = str_replace('\\', '/', $rawPath);

        if (str_starts_with($path, '/')) {
            if (! str_starts_with($path, '/storage/')) {
                return null;
            }

            $path = substr($path, strlen('/storage/'));
        }

        $path = ltrim($path, '/');

        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));
        }

        if ($path === ''
            || $path === '..'
            || str_starts_with($path, '../')
            || str_contains($path, '/../')
            || preg_match('/^[a-z][a-z0-9+.-]*:/i', $path) === 1) {
            return null;
        }

        return Storage::disk('public')->url($path);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    public function unitRelation()
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function priceHistories()
    {
        return $this->hasMany(
            ProductPriceHistory::class
        );
    }

    public function priceTiers()
    {
        return $this->hasMany(ProductPriceTier::class, 'product_unit_id', 'id');
    }

    public function productUnits()
    {
        return $this->hasMany(ProductUnit::class);
    }

    public function barcodes()
    {
        return $this->hasMany(ProductBarcode::class);
    }

    public function purchaseItems()
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function saleItems()
    {
        return $this->hasMany(SaleItem::class);
    }
}
