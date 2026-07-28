@extends('adminlte::page')

@section('title', 'หมวดหมู่สินค้า')

@section('css')
    <link rel="stylesheet" href="{{ asset('css/categories.css') }}">
@stop

@section('content_header')
    <div class="category-page-heading d-flex align-items-center justify-content-between">
        <div>
            <h1 class="mb-1">หมวดหมู่สินค้า</h1>
            <p class="text-muted mb-0">จัดการหมวดหมู่และ Prefix สำหรับข้อมูลสินค้าใหม่</p>
        </div>
        <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#categoryModal" data-category-mode="create">
            <i class="fas fa-plus mr-1"></i> เพิ่มหมวดหมู่
        </button>
    </div>
@stop

@section('content')
    <div class="card category-workspace-card">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="text-muted">ทั้งหมด <span id="category-count">{{ $categories->count() }}</span> รายการ</div>
                <span class="badge badge-light">Prefix ใช้กับสินค้าใหม่เท่านั้น</span>
            </div>

            <div class="category-toolbar mb-3">
                <label for="category-search">ค้นหา</label>
                <div class="input-group">
                    <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-search"></i></span></div>
                    <input id="category-search" type="search" class="form-control" placeholder="ชื่อหมวดหมู่, Code Prefix หรือ Barcode Prefix">
                </div>
            </div>

            <div class="table-responsive">
                <table class="table category-table align-middle">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Code Prefix</th>
                            <th>Barcode Prefix</th>
                            <th>Product Count</th>
                            <th>Rounding by Zone</th>
                            <th>Status</th>
                            <th class="text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody id="category-table-body">
                        @forelse ($categories as $category)
                            <tr data-category-row data-product-count="{{ $category->products_count }}" data-search="{{ strtolower($category->name.' '.$category->code_prefix.' '.$category->barcode_prefix) }}">
                                <td>
                                    <div class="font-weight-bold">{{ $category->name }}</div>
                                    @if ($category->description)
                                        <div class="small text-muted">{{ $category->description }}</div>
                                    @endif
                                </td>
                                <td><span class="badge badge-light">{{ $category->code_prefix ?: '—' }}</span></td>
                                <td><span class="badge badge-light">{{ $category->barcode_prefix ?: '—' }}</span></td>
                                <td>{{ number_format($category->products_count) }}</td>
                                <td>{{ $category->rounding_override ? number_format($category->rounding_override, 2).' บาท' : 'ใช้ค่าของโซน' }}</td>
                                <td><span class="badge {{ $category->active ? 'badge-success' : 'badge-secondary' }}">{{ $category->active ? 'เปิดใช้งาน' : 'ปิดใช้งาน' }}</span></td>
                                <td class="text-right text-nowrap">
                                    <button type="button" class="btn btn-outline-primary btn-sm" data-toggle="modal" data-target="#categoryModal" data-category-mode="edit" data-category='@json($category)'>
                                        <i class="fas fa-edit"></i> แก้ไข
                                    </button>
                                    <button type="button" class="btn btn-outline-danger btn-sm" data-category-delete data-url="{{ route('categories.destroy', $category) }}" data-name="{{ $category->name }}" data-product-count="{{ $category->products_count }}">
                                        <i class="fas fa-trash"></i> ลบ
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr id="category-empty-row"><td colspan="7" class="text-center text-muted py-5">ยังไม่มีหมวดหมู่สินค้า</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="modal fade category-modal" id="categoryModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <div><h5 class="modal-title" id="categoryModalTitle">เพิ่มหมวดหมู่</h5><small class="text-muted">ข้อมูลพื้นฐานของหมวดหมู่</small></div>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <form id="categoryModalForm" method="POST" action="{{ route('categories.store') }}">
                    @csrf
                    <input type="hidden" id="categoryModalMethod" name="_method" value="POST">
                    <div class="modal-body">
                        <div id="categoryModalErrors" class="alert alert-danger d-none"></div>
                        <div class="row">
                            <div class="col-md-6 form-group"><label for="categoryModalName">ชื่อหมวดหมู่</label><input id="categoryModalName" name="name" class="form-control" required maxlength="255"></div>
                            <div class="col-md-6 form-group"><label for="categoryModalCode">Code Prefix</label><input id="categoryModalCode" name="code_prefix" class="form-control text-uppercase" maxlength="20" pattern="[A-Z]+"><small class="form-text text-muted">สร้างอัตโนมัติจากชื่อ แก้ไขได้</small></div>
                            <div class="col-md-6 form-group"><label for="categoryModalBarcode">Barcode Prefix</label><input id="categoryModalBarcode" name="barcode_prefix" class="form-control" maxlength="3" inputmode="numeric" pattern="[0-9]{3}"><small class="form-text text-muted">เลข 3 หลักและต้องไม่ซ้ำ</small></div>
                            <div class="col-md-6 form-group"><label for="categoryModalActive">สถานะ</label><div class="custom-control custom-switch mt-2"><input type="checkbox" class="custom-control-input" id="categoryModalActive" name="active" value="1" checked><label class="custom-control-label" for="categoryModalActive">เปิดใช้งาน</label></div></div>
                            <div class="col-12 form-group"><label for="categoryModalDescription">คำอธิบาย</label><textarea id="categoryModalDescription" name="description" class="form-control" rows="3" maxlength="5000"></textarea></div>
                            <div class="col-12 form-group"><label for="categoryModalRounding">การปัดเศษตามโซน</label><select id="categoryModalRounding" name="rounding_override" class="form-control"><option value="">ใช้ค่าของโซน</option>@foreach ($roundingOverrides as $increment)<option value="{{ $increment }}">{{ number_format($increment, 2) }} บาท</option>@endforeach</select></div>
                        </div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-light" data-dismiss="modal">ยกเลิก</button><button type="submit" class="btn btn-primary" id="categoryModalSubmit"><i class="fas fa-save mr-1"></i> บันทึก</button></div>
                </form>
            </div>
        </div>
    </div>
@stop

@section('js')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="{{ asset('js/modules/category-management.js') }}"></script>
@stop
