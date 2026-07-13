@extends('adminlte::page')

@section('title', 'ค่าช่างค้างจ่าย')

@section('content_header')
<h1>ค่าช่างค้างจ่าย</h1>
@stop

@section('content')

<div class="card">

    <div class="card-header">

        <form method="GET">

            <div class="row">

                <div class="col-md-4">

                    <label>ช่าง</label>

                    <select
                        name="technician_id"
                        class="form-control"
                        onchange="this.form.submit()">

                        <option value="">
                            ทั้งหมด
                        </option>

                        @foreach($technicians as $technician)

                        <option
                            value="{{ $technician->id }}"
                            {{ request('technician_id') == $technician->id ? 'selected' : '' }}>

                            {{ $technician->name }}

                        </option>

                        @endforeach

                    </select>

                </div>

            </div>

        </form>

    </div>

    <div class="card-body">

        <table class="table table-bordered">

            <thead>

                <tr>

                    <th width="40">
                        ✓
                    </th>

                    <th>
                        เลขที่บิล
                    </th>

                    <th>
                        ลูกค้า
                    </th>

                    <th>
                        ช่าง
                    </th>

                    <th class="text-end">
                        ค่าช่าง
                    </th>

                </tr>

            </thead>

            <tbody>

                @forelse($commissions as $commission)

                <tr>

                    <td>

                        <input
                            type="checkbox"
                            name="commission_ids[]"
                            value="{{ $commission->id }}">

                    </td>

                    <td>

                        {{ $commission->sale->sale_no }}

                    </td>

                    <td>

                        {{ optional($commission->sale->customer)->name }}

                    </td>

                    <td>

                        {{ $commission->technician->name }}

                    </td>

                    <td class="text-end">

                        {{ number_format($commission->payable_amount,2) }}

                    </td>

                </tr>

                @empty

                <tr>

                    <td colspan="5"
                        class="text-center">

                        ไม่มีข้อมูล

                    </td>

                </tr>

                @endforelse

            </tbody>

            <tfoot>

                <tr>

                    <th colspan="4"
                        class="text-end">

                        รวม

                    </th>

                    <th class="text-end">

                        {{ number_format($total,2) }}

                    </th>

                </tr>

            </tfoot>

        </table>

    </div>

</div>

@stop
