@extends('adminlte::page')

@section('title', 'ใบเสนอราคา')

@section('content_header')
    <h1>ใบเสนอราคา</h1>
@stop

@section('content')

@if(session('success'))
    <div class="alert alert-success">
        {{ session('success') }}
    </div>
@endif

<div class="card">

    <div class="card-header">
        <a
            href="{{ route('quotations.create') }}"
            class="btn btn-primary">
            + สร้างใบเสนอราคา
        </a>
    </div>

    <div class="card-body">

        <table class="table table-bordered">

            <thead>
                <tr>
                    <th>เลขที่</th>
                    <th>วันที่</th>
                    <th>ลูกค้า</th>
                    <th>ยอดรวม</th>
                    <th>สถานะ</th>
                    <th width="220">จัดการ</th>
                </tr>
            </thead>

            <tbody>
                @forelse($quotations as $quotation)
                    <tr>
                        <td>{{ $quotation->quotation_no }}</td>
                        <td>{{ $quotation->quotation_date }}</td>
                        <td>{{ $quotation->customer->name ?? '-' }}</td>
                        <td>{{ number_format($quotation->total_amount, 2) }}</td>
                        <td>{{ $quotation->status }}</td>
                        <td>
                            <a
                                href="{{ route('quotations.show', $quotation) }}"
                                class="btn btn-info btn-sm">
                                ดู
                            </a>

                            <a
                                href="{{ route('quotations.print', $quotation) }}"
                                target="_blank"
                                class="btn btn-success btn-sm">
                                พิมพ์
                            </a>

                            <form
                                action="{{ route('quotations.destroy', $quotation) }}"
                                method="POST"
                                style="display:inline-block;"
                                onsubmit="return confirm('ยืนยันลบใบเสนอราคา?')">

                                @csrf
                                @method('DELETE')

                                <button
                                    type="submit"
                                    class="btn btn-danger btn-sm">
                                    ลบ
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center">
                            ยังไม่มีใบเสนอราคา
                        </td>
                    </tr>
                @endforelse
            </tbody>

        </table>

    </div>

</div>

@stop
