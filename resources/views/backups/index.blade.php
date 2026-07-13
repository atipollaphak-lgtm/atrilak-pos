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
    <div class="card mt-3">

        <div class="card-header">
            Restore Database
        </div>

        <div class="card-body">

            <form action="{{ route('backups.restore') }}" method="POST" enctype="multipart/form-data">

                @csrf

                <input type="file" name="backup_file" class="form-control" accept=".sql" required>

                <button type="submit" class="btn btn-danger mt-2"
                    onclick="return confirm('ยืนยัน Restore Database? ข้อมูลปัจจุบันอาจถูกแทนที่')">
                    Restore Database
                </button>
            </form>

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
@stop
