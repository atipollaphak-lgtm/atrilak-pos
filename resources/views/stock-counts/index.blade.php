@extends('adminlte::page')

@section('title', 'ตรวจนับสต็อก')

@section('content_header')
    <h1>ตรวจนับสต็อก</h1>
@stop

@section('content')

@if(session('success'))
    <div class="alert alert-success">
        {{ session('success') }}
    </div>
@endif

<div class="card">

    <div class="card-header">
        บันทึกตรวจนับสต็อก
    </div>

    <div class="card-body">

        <form
            action="{{ route('stock-counts.store') }}"
            method="POST">

            @csrf

            <div class="row">

                <div class="col-md-3">
                    <label>วันที่ตรวจนับ</label>

                    <input
                        type="date"
                        name="count_date"
                        class="form-control"
                        value="{{ date('Y-m-d') }}"
                        required>
                </div>

                <div class="col-md-9">
                    <label>หมายเหตุ</label>

                    <input
                        type="text"
                        name="remark"
                        class="form-control"
                        placeholder="เช่น ตรวจนับสิ้นเดือน">
                </div>

            </div>

            <hr>

            <table class="table table-bordered" id="stock-count-table">

                <thead>
                    <tr>
                        <th>สินค้า</th>
                        <th width="150">สต็อกในระบบ</th>
                        <th width="150">นับจริง</th>
                        <th width="150">ผลต่าง</th>
                        <th width="80">ลบ</th>
                    </tr>
                </thead>

                <tbody>
                    <tr>
                        <td>
                            <select
                                name="product_id[]"
                                class="form-control product-select"
                                required>

                                <option value="">-- เลือกสินค้า --</option>

                                @foreach($products as $product)
                                    <option
                                        value="{{ $product->id }}"
                                        data-stock="{{ $product->stock_qty }}">

                                        {{ $product->name }}

                                    </option>
                                @endforeach

                            </select>
                        </td>

                        <td>
                            <input
                                type="number"
                                name="system_qty[]"
                                class="form-control system-qty"
                                readonly>
                        </td>

                        <td>
                            <input
                                type="number"
                                name="actual_qty[]"
                                class="form-control actual-qty"
                                value="0"
                                required>
                        </td>

                        <td>
                            <input
                                type="number"
                                class="form-control difference"
                                readonly>
                        </td>

                        <td>
                            <button
                                type="button"
                                class="btn btn-danger btn-sm remove-row">
                                ลบ
                            </button>
                        </td>
                    </tr>
                </tbody>

            </table>

            <button
                type="button"
                class="btn btn-secondary"
                id="add-row">

                + เพิ่มสินค้า

            </button>

            <button
                type="submit"
                class="btn btn-primary">

                บันทึกตรวจนับ

            </button>

        </form>

    </div>

</div>

<div class="card mt-3">

    <div class="card-header">
        รายการตรวจนับล่าสุด
    </div>

    <div class="card-body">

        <table class="table table-bordered">

            <thead>
                <tr>
                    <th>เลขที่</th>
                    <th>วันที่</th>
                    <th>หมายเหตุ</th>
                </tr>
            </thead>

            <tbody>
                @forelse($stockCounts as $stockCount)
                    <tr>
                        <td>{{ $stockCount->count_no }}</td>
                        <td>{{ $stockCount->count_date }}</td>
                        <td>{{ $stockCount->remark }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="text-center">
                            ยังไม่มีรายการตรวจนับ
                        </td>
                    </tr>
                @endforelse
            </tbody>

        </table>

    </div>

</div>

@stop

@section('js')

<script>
    function calculateRow(row) {
        let systemQty = parseInt(row.find('.system-qty').val()) || 0;
        let actualQty = parseInt(row.find('.actual-qty').val()) || 0;
        let difference = actualQty - systemQty;

        row.find('.difference').val(difference);
    }

    $(document).on('change', '.product-select', function () {
        let row = $(this).closest('tr');
        let stock = $(this).find(':selected').data('stock') || 0;

        row.find('.system-qty').val(stock);
        row.find('.actual-qty').val(stock);

        calculateRow(row);
    });

    $(document).on('input', '.actual-qty', function () {
        let row = $(this).closest('tr');

        calculateRow(row);
    });

    $('#add-row').on('click', function () {
        let row = $('#stock-count-table tbody tr:first').clone();

        row.find('select').val('');
        row.find('input').val('');
        row.find('.actual-qty').val(0);

        $('#stock-count-table tbody').append(row);
    });

    $(document).on('click', '.remove-row', function () {
        if ($('#stock-count-table tbody tr').length > 1) {
            $(this).closest('tr').remove();
        }
    });
</script>

@stop
