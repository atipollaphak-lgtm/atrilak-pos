@extends('adminlte::page')

@section('title', 'สำรองข้อมูล')

@section('content_header')
    <h1>สำรองข้อมูล</h1>
@stop

@section('content')

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
            Backup Database
        </div>

        <div class="card-body">

            <p>
                ใช้หน้านี้สำหรับสำรองฐานข้อมูลของระบบ ATRILAK POS
            </p>

            <form action="{{ route('backups.create') }}" method="POST">

                @csrf

                <button type="submit" class="btn btn-primary">
                    สำรองข้อมูล / ดาวน์โหลด Backup
                </button>

            </form>

        </div>

    </div>
    <div class="card card-danger mt-3" data-reset-card>
        <div class="card-header">
            เริ่มต้นข้อมูลธุรกิจใหม่ <span class="small text-muted">(Reset Business Data)</span>
        </div>

        <div class="card-body">
            <div class="alert alert-danger">
                ล้างข้อมูลธุรกรรม สินค้า ลูกค้า และข้อมูลธุรกิจเพื่อเริ่มต้นรอบใหม่
                โดยคงผู้ใช้ สิทธิ์ ตั้งค่าร้าน และโซนจัดส่งไว้
            </div>

            <button
                type="button"
                class="btn btn-danger"
                data-toggle="modal"
                data-target="#business-data-reset-modal"
            >
                ล้างข้อมูลธุรกิจ
            </button>
        </div>
    </div>

    <div
        class="modal fade"
        id="business-data-reset-modal"
        tabindex="-1"
        role="dialog"
        aria-labelledby="business-data-reset-modal-title"
        aria-hidden="true"
        data-backdrop="static"
        data-keyboard="false"
        data-reset-modal
        data-reset-auto-open="{{ $errors->hasAny(['acknowledged', 'confirmation', 'password']) ? '1' : '0' }}"
    >
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content border-danger">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="business-data-reset-modal-title">
                        ยืนยันการเริ่มต้นข้อมูลธุรกิจใหม่
                    </h5>
                </div>

                <form
                    id="business-data-reset-form"
                    action="{{ route('backups.reset-business-data') }}"
                    method="POST"
                    data-reset-form
                    data-reset-phrase="{{ \App\Console\Commands\ResetBusinessDataCommand::CONFIRMATION }}"
                >
                    @csrf

                    <div class="modal-body">
                        <p class="font-weight-bold text-danger">
                            การดำเนินการนี้ล้างข้อมูลธุรกิจถาวร และต้องสำรองข้อมูลก่อนทุกครั้ง
                        </p>

                        <div class="row">
                            <div class="col-md-6">
                                <h6>ข้อมูลธุรกิจที่จะล้าง</h6>
                                <ul class="small pl-3">
                                    <li>สินค้า หมวดหมู่ หน่วยนับ และประวัติราคา</li>
                                    <li>การขาย ใบเสนอราคา ใบพักบิล และการรับสินค้า</li>
                                    <li>สต็อก การเคลื่อนไหวสต็อก และค่าคอมมิชชันช่าง</li>
                                    <li>ลูกค้า ผู้จำหน่าย และข้อมูลธุรกรรมที่เกี่ยวข้อง</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6>ข้อมูลที่จะเก็บไว้</h6>
                                <ul class="small pl-3">
                                    <li>users, roles และ permissions</li>
                                    <li>settings และ pricing_settings</li>
                                    <li>delivery_zones</li>
                                    <li>migrations และโครงสร้างฐานข้อมูล</li>
                                </ul>
                            </div>
                        </div>

                        <div class="form-group mt-3">
                            <div class="custom-control custom-checkbox">
                                <input
                                    class="custom-control-input"
                                    id="business-data-reset-acknowledged"
                                    type="checkbox"
                                    name="acknowledged"
                                    value="1"
                                    data-reset-acknowledged
                                >
                                <label class="custom-control-label" for="business-data-reset-acknowledged">
                                    ฉันเข้าใจว่าข้อมูลธุรกิจจะถูกล้างถาวร และตรวจสอบว่ามี Backup แล้ว
                                </label>
                            </div>
                            @error('acknowledged')
                                <span class="text-danger d-block">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="reset-confirmation">พิมพ์ข้อความยืนยันให้ตรงกันทุกตัวอักษร</label>
                            <code class="d-block mb-2">{{ \App\Console\Commands\ResetBusinessDataCommand::CONFIRMATION }}</code>
                            <input
                                id="reset-confirmation"
                                type="text"
                                name="confirmation"
                                class="form-control"
                                autocomplete="off"
                                required
                                data-reset-confirmation
                            >
                            @error('confirmation')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group mb-0">
                            <label for="reset-password">รหัสผ่าน Owner ปัจจุบัน</label>
                            <input
                                id="reset-password"
                                type="password"
                                name="password"
                                class="form-control"
                                autocomplete="current-password"
                                required
                                data-reset-password
                            >
                            @error('password')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="alert alert-warning mt-3 mb-0 d-none" data-reset-progress>
                            กำลังสำรองและล้างข้อมูล กรุณาอย่าปิดหน้านี้
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="submit" class="btn btn-danger" disabled data-reset-submit>
                            สำรองข้อมูลและล้างข้อมูล
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @if (session('reset_summary'))
        @php
            $resetSummary = session('reset_summary');
        @endphp
        <div class="alert alert-success mt-3" data-reset-summary>
            <strong>ล้างข้อมูลธุรกิจเรียบร้อยแล้ว</strong>
            <div class="small">
                สถานะ: {{ $resetSummary['status'] ?? 'success' }}<br>
                Backup: {{ $resetSummary['backup_file'] ?? '-' }}<br>
                SHA-256: {{ $resetSummary['backup_sha256'] ?? '-' }}<br>
                Users: {{ $resetSummary['users'] ?? '-' }} | Settings: {{ $resetSummary['settings'] ?? '-' }}
            </div>
        </div>
    @endif
    <div class="card mt-3">

        <div class="card-header">
            Restore Database
        </div>

        <div class="card-body">

            <div class="alert alert-warning mb-0">
                Restore ผ่านหน้าเว็บถูกปิดเพื่อความปลอดภัย<br>
                การกู้คืนต้องทำผ่านคำสั่งสำหรับ Owner และคู่มือการกู้คืน
            </div>

        </div>

    </div>
    <div class="card mt-3">

        <div class="card-header">
            รายการไฟล์ Backup
        </div>

        <div class="card-body">

            <table class="table table-bordered">

                <thead>
                    <tr>
                        <th>ชื่อไฟล์</th>
                        <th width="180">ดาวน์โหลด</th>
                    </tr>
                </thead>

                <tbody>

                    @forelse($files as $file)
                        <tr>
                            <td>
                                {{ $file->getFilename() }}
                            </td>

                            <td>
                                <a href="{{ route('backups.download', $file->getFilename()) }}"
                                    class="btn btn-sm btn-success">

                                    ดาวน์โหลด

                                </a>
                            </td>
                        </tr>

                    @empty

                        <tr>
                            <td colspan="2" class="text-center">
                                ยังไม่มีไฟล์ Backup
                            </td>
                        </tr>
                    @endforelse

                </tbody>

            </table>

        </div>

    </div>
@push('js')
    <script src="{{ asset('js/modules/business-data-reset.js') }}"></script>
@endpush
@stop
