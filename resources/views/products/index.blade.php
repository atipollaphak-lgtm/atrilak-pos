@extends('adminlte::page')

@section('title', 'สินค้า')

@section('css')
    <link rel="stylesheet" href="{{ asset('css/products.css') }}">
@stop

@section('content_header')
    <div class="product-page-heading d-flex align-items-center justify-content-between">
        <div>
            <h1 class="mb-1">สินค้า</h1>
            <p class="text-muted mb-0">จัดการข้อมูลสินค้าและสถานะการใช้งาน</p>
        </div>
        <div class="d-flex align-items-center">
            <a href="{{ route('products.import.index') }}" class="btn btn-outline-success mr-2">
                <i class="fas fa-file-excel mr-1"></i> นำเข้าจาก Excel
            </a>
        <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#productModal" data-product-mode="create">
            <i class="fas fa-plus mr-1"></i> เพิ่มสินค้า
        </button>
        </div>
    </div>
@stop

@section('content')
    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <strong>กรุณาตรวจสอบข้อมูล</strong>
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card product-workspace-card">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="text-muted">ทั้งหมด {{ $products instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator ? $products->total() : $products->count() }} รายการ</div>
                <span class="badge badge-light">Selling Rule: ยังไม่ได้กำหนด</span>
            </div>

            <form method="GET" action="{{ route('products.index') }}" class="product-filters">
                <div class="form-group product-search-field">
                    <label for="product-search">ค้นหา</label>
                    <div class="input-group">
                        <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-search"></i></span></div>
                        <input id="product-search" type="search" name="search" value="{{ request('search') }}" class="form-control" placeholder="ชื่อสินค้า, รหัสสินค้า หรือ Barcode">
                    </div>
                </div>
                <div class="form-group">
                    <label for="product-category">หมวดหมู่</label>
                    <select id="product-category" name="category_id" class="form-control">
                        <option value="">ทั้งหมด</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected((string) request('category_id') === (string) $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label for="product-status">สถานะ</label>
                    <select id="product-status" name="status" class="form-control">
                        <option value="">ทั้งหมด</option>
                        <option value="active" @selected(request('status') === 'active')>เปิดใช้งาน</option>
                        <option value="inactive" @selected(request('status') === 'inactive')>ปิดใช้งาน</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="product-selling-rule">กฎการขาย</label>
                    <select id="product-selling-rule" class="form-control" disabled>
                        <option>ยังไม่ได้กำหนด</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="product-sort">เรียงลำดับ</label>
                    <select id="product-sort" name="sort" class="form-control">
                        @foreach ([
                            'category_name' => 'หมวดหมู่ → ชื่อสินค้า',
                            'name' => 'ชื่อสินค้า',
                            'cost_price' => 'ต้นทุนเฉลี่ย',
                            'selling_price' => 'ราคาขาย',
                            'profit' => 'กำไร',
                            'stock_qty' => 'สต็อก',
                            'created_at' => 'วันที่สร้าง',
                            'updated_at' => 'แก้ไขล่าสุด',
                        ] as $value => $label)
                            <option value="{{ $value }}" @selected(request('sort', 'category_name') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label for="product-per-page">แสดง</label>
                    <select id="product-per-page" name="per_page" class="form-control">
                        @foreach ([10, 20, 50, 100] as $value)
                            <option value="{{ $value }}" @selected((string) request('per_page', 50) === (string) $value)>{{ $value }}</option>
                        @endforeach
                        <option value="all" @selected(request('per_page') === 'all')>ทั้งหมด</option>
                    </select>
                </div>
                <div class="form-group align-self-end">
                    <button type="submit" class="btn btn-outline-primary">ใช้ตัวกรอง</button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table product-table align-middle">
                    <thead>
                        <tr>
                            <th>รูป</th>
                            <th>ชื่อสินค้า</th>
                            <th>ต้นทุนเฉลี่ย</th>
                            <th>ราคาขาย</th>
                            <th>คงเหลือ</th>
                            <th>กำไร %</th>
                            <th>กฎการขาย</th>
                            <th class="text-right">รายละเอียด</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($products as $product)
                            @php
                                $profitPercent = (float) $product->cost_price > 0
                                    ? (((float) $product->selling_price - (float) $product->cost_price) / (float) $product->cost_price) * 100
                                    : 0;
                                $profitClass = $profitPercent > 20 ? 'text-success' : ($profitPercent > 0 ? 'text-warning' : 'text-danger');
                                $stockClass = (float) $product->stock_qty <= (float) $product->minimum_stock ? 'badge-danger' : 'badge-success';
                                $productData = [
                                    'id' => $product->id,
                                    'name' => $product->name,
                                    'product_code' => $product->product_code,
                                    'sku' => $product->sku,
                                    'barcode' => $product->barcode,
                                    'category_id' => $product->category_id,
                                    'unit_id' => $product->unit_id,
                                    'remark' => $product->remark,
                                    'active' => (bool) $product->active,
                                    'image_path' => $product->image_path,
                                    'cost_price' => $product->cost_price,
                                    'selling_price' => $product->selling_price,
                                    'stock_qty' => $product->stock_qty,
                                    'stock_value' => (float) $product->stock_qty * (float) $product->cost_price,
                                    'profit_percent' => $profitPercent,
                                    'created_at' => optional($product->created_at)->format('d/m/Y H:i'),
                                    'updated_at' => optional($product->updated_at)->format('d/m/Y H:i'),
                                    'purchase_count' => $product->purchase_items_count,
                                    'sale_count' => $product->sale_items_count,
                                ];
                            @endphp
                            <tr class="{{ $product->active ? '' : 'product-row-inactive' }}" data-product-id="{{ $product->id }}">
                                <td>
                                    <img class="product-thumb" src="{{ $product->image_path ? asset('storage/' . $product->image_path) : asset('images/product-placeholder.svg') }}" alt="รูป {{ $product->name }}">
                                </td>
                                <td>
                                    <div class="font-weight-bold">{{ $product->name }}</div>
                                    <div class="small text-muted">{{ $product->product_code ?: ($product->sku ?: 'ไม่มีรหัสสินค้า') }}</div>
                                    @unless ($product->active)
                                        <span class="badge badge-secondary">ปิดใช้งาน</span>
                                    @endunless
                                </td>
                                <td>{{ number_format((float) $product->cost_price, 2) }}</td>
                                <td>{{ number_format((float) $product->selling_price, 2) }}</td>
                                <td><span class="badge {{ $stockClass }}">{{ number_format((float) $product->stock_qty, 2) }}</span></td>
                                <td class="font-weight-bold {{ $profitClass }}">{{ number_format($profitPercent, 1) }}%</td>
                                <td class="text-muted">—</td>
                                <td class="text-right">
                                    <button type="button" class="btn btn-outline-primary btn-sm" data-toggle="modal" data-target="#productModal" data-product-mode="details" data-product='@json($productData)'>รายละเอียด</button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center text-muted py-5">ไม่พบสินค้า</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($products instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator)
                <div class="mt-3">{{ $products->links() }}</div>
            @endif
        </div>
    </div>

    @include('products.partials._product_modal')
@stop

@section('js')
    <script src="{{ asset('js/modules/product-management.js') }}"></script>
@stop
