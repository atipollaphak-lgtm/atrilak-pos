@extends('adminlte::page')

@section('title', 'ประวัติสต็อก')

@section('content_header')
    <h1>ประวัติการเคลื่อนไหวสต็อก</h1>
@stop

@section('content')

    <div class="card">

        <div class="card-body">

            <table class="table table-bordered table-striped">

                <thead>

                    <tr>
                        <th>วันที่</th>
                        <th>สินค้า</th>
                        <th>ประเภท</th>
                        <th>จำนวน</th>
                        <th>ก่อน</th>
                        <th>หลัง</th>
                        <th>อ้างอิง</th>
                        <th>หมายเหตุ</th>
                    </tr>

                </thead>

                <tbody>

                    @forelse($movements as $movement)
                        <tr>

                            <td>
                                {{ $movement->created_at }}
                            </td>

                            <td>
                                {{ $movement->product->name ?? '-' }}
                            </td>

                            <td>

                                @if ($movement->type == 'IN')
                                    <span class="badge badge-success">
                                        IN
                                    </span>
                                @elseif($movement->type == 'OUT')
                                    <span class="badge badge-danger">
                                        OUT
                                    </span>
                                @else
                                    <span class="badge badge-warning">
                                        ADJUST
                                    </span>
                                @endif

                            </td>

                            <td>
                                {{ number_format($movement->qty, 2) }}
                            </td>

                            <td>
                                {{ number_format($movement->stock_before, 2) }}
                            </td>

                            <td>
                                {{ number_format($movement->stock_after, 2) }}
                            </td>

                            <td>
                                @if ($movement->reference_type && $movement->reference_id)
                                    {{ $movement->reference_type }} #{{ $movement->reference_id }}
                                @else
                                    -
                                @endif
                            </td>

                            <td>
                                {{ $movement->remark }}
                            </td>

                        </tr>

                    @empty

                        <tr>
                            <td colspan="8" class="text-center">
                                ไม่พบข้อมูล
                            </td>
                        </tr>
                    @endforelse

                </tbody>

            </table>

            <br>

            {{ $movements->links() }}

        </div>

    </div>

@stop
