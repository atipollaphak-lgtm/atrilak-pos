@extends('adminlte::page')

@section('title', 'หน่วยนับ')

@section('content_header')
    <h1>หน่วยนับ</h1>
@endsection

@section('content')

    <div class="mb-3">
        <form method="POST" action="{{ route('units.seed') }}" style="display:inline-block;"
            onsubmit="return confirm('ยืนยันสร้างข้อมูลหน่วยนับมาตรฐาน?');">
            @csrf

            <button type="submit" class="btn btn-info">
                สร้างข้อมูลมาตรฐาน
            </button>
        </form>
    </div>

    @if (session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger">
            {{ session('error') }}
        </div>
    @endif

    <div class="card">
        <div class="card-header">
            <strong>เพิ่มหน่วยนับ</strong>
        </div>

        <div class="card-body">
            <form method="POST" action="{{ route('units.store') }}">
                @csrf

                <div class="row">
                    <div class="col-md-4">
                        <label>ชื่อหน่วย</label>
                        <input type="text" name="name" class="form-control" placeholder="ถุง" required>
                    </div>

                    <div class="col-md-3">
                        <label>ชื่อย่อ</label>
                        <input type="text" name="short_name" class="form-control" placeholder="ถุง" required>
                    </div>

                    <div class="col-md-2">
                        <label>ลำดับ</label>
                        <input type="number" name="sort_order" class="form-control" value="0">
                    </div>

                    <div class="col-md-1">
                        <label>ใช้งาน</label>
                        <div class="form-check mt-2">
                            <input type="checkbox" name="active" value="1" class="form-check-input" checked>
                        </div>
                    </div>

                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            บันทึก
                        </button>
                    </div>
                </div>

                <small class="form-text text-muted mt-2">
                    รหัสหน่วยจะถูกสร้างอัตโนมัติเมื่อบันทึก
                </small>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <strong>รายการหน่วยนับ</strong>
        </div>

        <div class="card-body table-responsive">
            <table class="table table-bordered table-hover">
                <thead>
                    <tr>
                        <th style="width:80px;">ลำดับ</th>
                        <th style="width:150px;">รหัส</th>
                        <th>ชื่อหน่วย</th>
                        <th style="width:150px;">ชื่อย่อ</th>
                        <th style="width:100px;">สถานะ</th>
                        <th style="width:260px;">จัดการ</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($units as $unit)
                        <tr>
                            <form method="POST" action="{{ route('units.update', $unit) }}">
                                @csrf
                                @method('PUT')

                                <td>
                                    <input type="number" name="sort_order" value="{{ $unit->sort_order }}"
                                        class="form-control">
                                </td>

                                <td>
                                    <input type="text" value="{{ $unit->code }}" class="form-control" readonly
                                        aria-label="รหัสหน่วย">
                                </td>

                                <td>
                                    <input type="text" name="name" value="{{ $unit->name }}" class="form-control"
                                        required>
                                </td>

                                <td>
                                    <input type="text" name="short_name" value="{{ $unit->short_name }}"
                                        class="form-control" required>
                                </td>

                                <td class="text-center">
                                    <input type="checkbox" name="active" value="1"
                                        {{ $unit->active ? 'checked' : '' }}>
                                </td>

                                <td>
                                    <button type="submit" class="btn btn-sm btn-success">
                                        บันทึก
                                    </button>
                            </form>

                            <form method="POST" action="{{ route('units.destroy', $unit) }}" style="display:inline-block;"
                                onsubmit="return confirm('ยืนยันการลบหน่วยนับนี้?');">
                                @csrf
                                @method('DELETE')

                                <button type="submit" class="btn btn-sm btn-danger">
                                    ลบ
                                </button>
                            </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted">
                                ยังไม่มีข้อมูลหน่วยนับ
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

@endsection
