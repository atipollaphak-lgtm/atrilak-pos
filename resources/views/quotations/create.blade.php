@extends('adminlte::page')

@section('title', 'สร้างใบเสนอราคา')

@section('content_header')
    <h1>สร้างใบเสนอราคา</h1>
@stop

@section('content')

<div class="card">

    <div class="card-body">

        <form
            action="{{ route('quotations.store') }}"
            method="POST">

            @csrf

            <div class="row">

                <div class="col-md-4">
                    <label>ลูกค้า</label>

                    <select name="customer_id" class="form-control">
                        <option value="">ลูกค้าทั่วไป</option>

                        @foreach($customers as $customer)
                            <option value="{{ $customer->id }}">
                                {{ $customer->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-4">
                    <label>วันที่</label>

                    <input
                        type="date"
                        name="quotation_date"
                        class="form-control"
                        value="{{ date('Y-m-d') }}"
                        required>
                </div>

                <div class="col-md-4">
                    <label>หมายเหตุ</label>

                    <input
                        type="text"
                        name="remark"
                        class="form-control">
                </div>

            </div>

            <hr>

            <table class="table table-bordered" id="quotation-table">

                <thead>
                    <tr>
                        <th>สินค้า</th>
                        <th width="120">จำนวน</th>
                        <th width="150">ราคา</th>
                        <th width="150">รวม</th>
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
                                        data-price="{{ $product->selling_price }}">

                                        {{ $product->name }}

                                    </option>
                                @endforeach

                            </select>
                        </td>

                        <td>
                            <input
                                type="number"
                                name="qty[]"
                                class="form-control qty"
                                value="1"
                                min="1"
                                required>
                        </td>

                        <td>
                            <input
                                type="number"
                                name="selling_price[]"
                                class="form-control price"
                                step="0.01"
                                value="0"
                                required>
                        </td>

                        <td>
                            <input
                                type="number"
                                class="form-control line-total"
                                value="0"
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

            <hr>

            <h3 class="text-right">
                รวมทั้งหมด:
                <span id="grand-total">0.00</span>
                บาท
            </h3>

            <button
                type="submit"
                class="btn btn-primary">
                บันทึกใบเสนอราคา
            </button>

            <a
                href="{{ route('quotations.index') }}"
                class="btn btn-secondary">
                กลับ
            </a>

        </form>

    </div>

</div>

@stop

@section('js')
<script>
    function calculateRow(row) {
        let qty = parseFloat(row.find('.qty').val()) || 0;
        let price = parseFloat(row.find('.price').val()) || 0;
        let total = qty * price;

        row.find('.line-total').val(total.toFixed(2));

        calculateGrandTotal();
    }

    function calculateGrandTotal() {
        let grandTotal = 0;

        $('.line-total').each(function () {
            grandTotal += parseFloat($(this).val()) || 0;
        });

        $('#grand-total').text(grandTotal.toFixed(2));
    }

    $(document).on('change', '.product-select', function () {
        let row = $(this).closest('tr');
        let price = $(this).find(':selected').data('price') || 0;

        row.find('.price').val(price);

        calculateRow(row);
    });

    $(document).on('input', '.qty, .price', function () {
        let row = $(this).closest('tr');

        calculateRow(row);
    });

    $('#add-row').on('click', function () {
        let row = $('#quotation-table tbody tr:first').clone();

        row.find('select').val('');
        row.find('.qty').val(1);
        row.find('.price').val(0);
        row.find('.line-total').val(0);

        $('#quotation-table tbody').append(row);

        calculateGrandTotal();
    });

    $(document).on('click', '.remove-row', function () {
        if ($('#quotation-table tbody tr').length > 1) {
            $(this).closest('tr').remove();
            calculateGrandTotal();
        }
    });
</script>
@stop
