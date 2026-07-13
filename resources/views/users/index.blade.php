@extends('adminlte::page')

@section('title', 'จัดการผู้ใช้งาน')

@section('content_header')
    <h1>จัดการผู้ใช้งาน</h1>
@stop

@section('content')

@if(session('success'))
    <div class="alert alert-success">
        {{ session('success') }}
    </div>
@endif

<div class="card">

    <div class="card-header">
        รายชื่อผู้ใช้งาน
    </div>

    <div class="card-body">

        <table class="table table-bordered">

            <thead>
                <tr>
                    <th>ชื่อ</th>
                    <th>อีเมล</th>
                    <th width="250">สิทธิ์</th>
                    <th width="120">บันทึก</th>
                </tr>
            </thead>

            <tbody>

                @foreach($users as $user)

                    <tr>
                        <td>{{ $user->name }}</td>
                        <td>{{ $user->email }}</td>

                        <td>
                            <form
                                action="{{ route('users.update-role', $user->id) }}"
                                method="POST">

                                @csrf

                                <select
                                    name="role"
                                    class="form-control">

                                    <option value="owner" {{ $user->role == 'owner' ? 'selected' : '' }}>
                                        Owner
                                    </option>

                                    <option value="manager" {{ $user->role == 'manager' ? 'selected' : '' }}>
                                        Manager
                                    </option>

                                    <option value="cashier" {{ $user->role == 'cashier' ? 'selected' : '' }}>
                                        Cashier
                                    </option>

                                </select>
                        </td>

                        <td>
                                <button
                                    type="submit"
                                    class="btn btn-primary btn-sm">

                                    บันทึก

                                </button>

                            </form>
                        </td>
                    </tr>

                @endforeach

            </tbody>

        </table>

    </div>

</div>

@stop
