@extends('adminlte::page')

@section('title', 'กฎค่าช่าง')

@section('content_header')
    <h1>กฎค่าช่าง</h1>
@stop

@section('content')

    @if (session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    <div class="card">

        <div class="card-header">
            <a href="{{ route('technician-commission-rules.create') }}" class="btn btn-primary">
                + เพิ่มกฎค่าช่าง
            </a>
        </div>

        <div class="card-body">

            <table class="table table-bordered table-striped">

                <thead>
                    <tr>
                        <th>ชื่อกฎ</th>
                        <th>หมวดสินค้า</th>
                        <th>สินค้าเฉพาะ</th>
                        <th>วิธีคิด</th>
                        <th class="text-right">ค่า</th>
                        <th>สถานะ</th>
                        <th width="180">จัดการ</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($rules as $rule)
                        <tr>
                            <td>{{ $rule->name }}</td>

                            <td>{{ $rule->category->name ?? '-' }}</td>

                            <td>{{ $rule->product->name ?? '-' }}</td>

                            <td>
                                @if ($rule->rule_type === 'percent')
                                    เปอร์เซ็นต์
                                @else
                                    บาทต่อหน่วย
                                @endif
                            </td>

                            <td class="text-right">
                                @if ($rule->rule_type === 'percent')
                                    {{ number_format($rule->rule_value, 2) }} %
                                @else
                                    {{ number_format($rule->rule_value, 2) }} บาท
                                @endif
                            </td>

                            <td>
                                @if ($rule->active)
                                    <span class="badge badge-success">เปิดใช้</span>
                                @else
                                    <span class="badge badge-secondary">ปิดใช้</span>
                                @endif
                            </td>

                            <td>
                                <a href="{{ route('technician-commission-rules.edit', $rule->id) }}"
                                    class="btn btn-warning btn-sm">
                                    แก้ไข
                                </a>

                                <form action="{{ route('technician-commission-rules.destroy', $rule->id) }}"
                                    method="POST" style="display:inline-block;"
                                    onsubmit="return confirm('ยืนยันลบกฎนี้?');">
                                    @csrf
                                    @method('DELETE')

                                    <button class="btn btn-danger btn-sm">
                                        ลบ
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center">
                                ยังไม่มีกฎค่าช่าง
                            </td>
                        </tr>
                    @endforelse
                </tbody>

            </table>

        </div>

    </div>

@stop
