@extends('adminlte::page')

@section('title', 'สินค้าขายบ่อย')

@section('css')
    <link rel="stylesheet" href="{{ asset('css/frequent-products.css') }}">
@stop

@section('content_header')
    <div class="frequent-page-heading d-flex align-items-center justify-content-between">
        <div>
            <h1 class="mb-1">สินค้าขายบ่อย</h1>
            <p class="text-muted mb-0">กำหนดแท็บแรกของ POS V3 จากสินค้าเดิม โดยไม่สร้างข้อมูลสินค้าใหม่</p>
        </div>
        <a href="{{ route('categories.index') }}" class="btn btn-outline-secondary">
            <i class="fas fa-layer-group mr-1"></i> จัดการหมวดหมู่
        </a>
    </div>
@stop

@section('content')
    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div id="frequent-products-page" data-order-url="{{ route('frequent-products.order') }}">
        <div class="card frequent-workspace-card mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3 frequent-section-heading">
                    <div>
                        <h5 class="mb-1">รายการที่ปักหมุด</h5>
                        <div class="text-muted small">แท็บ “สินค้าขายบ่อย” จะแสดงตามลำดับนี้</div>
                    </div>
                    <div class="frequent-order-actions">
                        <span class="badge badge-light">{{ $frequentProducts->count() }} รายการ</span>
                        <button id="frequent-order-save" type="button" class="btn btn-outline-primary btn-sm" disabled>
                            <i class="fas fa-save mr-1"></i> บันทึกลำดับ
                        </button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table frequent-table align-middle">
                        <thead>
                            <tr>
                                <th class="frequent-order-column">ลำดับ</th>
                                <th>สินค้า</th>
                                <th>หมวดหมู่</th>
                                <th>สถานะ</th>
                                <th class="text-right">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody id="frequent-product-table-body">
                            @forelse ($frequentProducts as $frequentProduct)
                                @php($product = $frequentProduct->product)
                                @if ($product)
                                    <tr data-frequent-row draggable="true" data-frequent-id="{{ $frequentProduct->id }}">
                                        <td class="frequent-order-cell">
                                            <button type="button" class="frequent-drag-handle" aria-label="ลากเพื่อเรียง {{ $product->name }}" title="ลากเพื่อเรียงลำดับ">
                                                <i class="fas fa-grip-vertical" aria-hidden="true"></i>
                                            </button>
                                            <span class="frequent-order-number">{{ $loop->iteration }}</span>
                                        </td>
                                        <td>
                                            <div class="font-weight-bold">{{ $product->name }}</div>
                                            <div class="small text-muted">{{ $product->product_code ?: ($product->sku ?: 'ไม่มีรหัสสินค้า') }}</div>
                                        </td>
                                        <td>{{ $product->category?->name ?: 'ไม่ระบุหมวดหมู่' }}</td>
                                        <td>
                                            @if ($product->active)
                                                <span class="badge badge-success">เปิดใช้งาน</span>
                                            @else
                                                <span class="badge badge-secondary">ปิดใช้งาน</span>
                                            @endif
                                        </td>
                                        <td class="text-right">
                                            <button type="button" class="btn btn-outline-danger btn-sm" data-frequent-unpin data-url="{{ route('frequent-products.unpin', $frequentProduct) }}">
                                                <i class="fas fa-star-half-alt mr-1"></i> เอาออก
                                            </button>
                                        </td>
                                    </tr>
                                @endif
                            @empty
                                <tr id="frequent-product-empty-row"><td colspan="5" class="text-center text-muted py-5">ยังไม่มีสินค้าที่ปักหมุด</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card frequent-workspace-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3 frequent-section-heading">
                    <div>
                        <h5 class="mb-1">เลือกสินค้าจากข้อมูลเดิม</h5>
                        <div class="text-muted small">ค้นหาและปักหมุดสินค้าได้เฉพาะสินค้าที่เปิดใช้งาน</div>
                    </div>
                </div>

                <div class="frequent-product-search mb-3">
                    <label for="frequent-product-search">ค้นหา</label>
                    <div class="input-group">
                        <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-search"></i></span></div>
                        <input id="frequent-product-search" type="search" class="form-control" placeholder="ชื่อสินค้า, รหัสสินค้า หรือหมวดหมู่">
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table frequent-table align-middle">
                        <thead>
                            <tr>
                                <th>สินค้า</th>
                                <th>หมวดหมู่</th>
                                <th>สถานะ</th>
                                <th class="text-right">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody id="frequent-product-catalog">
                            @forelse ($products as $product)
                                <tr data-product-row data-product-search="{{ strtolower($product->name.' '.$product->product_code.' '.$product->sku.' '.$product->barcode.' '.($product->category?->name ?? '')) }}">
                                    <td>
                                        <div class="font-weight-bold">{{ $product->name }}</div>
                                        <div class="small text-muted">{{ $product->product_code ?: ($product->sku ?: 'ไม่มีรหัสสินค้า') }}</div>
                                    </td>
                                    <td>{{ $product->category?->name ?: 'ไม่ระบุหมวดหมู่' }}</td>
                                    <td>
                                        @if ($product->active)
                                            <span class="badge badge-success">เปิดใช้งาน</span>
                                        @else
                                            <span class="badge badge-secondary">ปิดใช้งาน</span>
                                        @endif
                                    </td>
                                    <td class="text-right">
                                        @if ($product->frequentProduct)
                                            <span class="badge badge-primary">ปักหมุดแล้ว</span>
                                        @elseif ($product->active)
                                            <button type="button" class="btn btn-outline-primary btn-sm" data-frequent-pin data-url="{{ route('frequent-products.pin', $product) }}">
                                                <i class="fas fa-star mr-1"></i> ปักหมุด
                                            </button>
                                        @else
                                            <button type="button" class="btn btn-outline-secondary btn-sm" disabled>ปิดใช้งาน</button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted py-5">ยังไม่มีสินค้า</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@stop

@section('js')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="{{ asset('js/modules/frequent-product-management.js') }}"></script>
@stop
