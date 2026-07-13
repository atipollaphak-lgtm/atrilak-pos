<table class="table table-bordered table-striped">

    <thead>
        <tr>
            <th>ชื่อสถานที่</th>
            <th>ผู้รับ</th>
            <th>โซน</th>
            <th>ค่าเริ่มต้น</th>
            <th width="180">จัดการ</th>
        </tr>
    </thead>

    <tbody>

        @forelse($addresses as $address)
            <tr>

                <td>
                    {{ $address->name }}
                </td>

                <td>
                    {{ $address->receiver_name }}
                </td>

                <td>
                    {{ $address->deliveryZone->name ?? '-' }}
                </td>

                <td>

                    @if ($address->is_default)
                        <span class="badge badge-success">
                            Default
                        </span>
                    @else
                        <span class="badge badge-secondary">
                            -
                        </span>
                    @endif

                </td>

                <td>

                    <a href="{{ route('customers.delivery-addresses.edit', [$customer, $address]) }}"
                        class="btn btn-warning btn-sm">
                        แก้ไข
                    </a>
                    {{-- ⭐ เพิ่มอันนี้ตรงกลาง --}}
                    @if (!$address->is_default)
                        <form action="{{ route('customers.delivery-addresses.update', [$customer, $address]) }}"
                            method="POST" class="d-inline">
                            @csrf
                            @method('PUT')

                            <input type="hidden" name="name" value="{{ $address->name }}">
                            <input type="hidden" name="receiver_name" value="{{ $address->receiver_name }}">
                            <input type="hidden" name="receiver_phone" value="{{ $address->receiver_phone }}">
                            <input type="hidden" name="address" value="{{ $address->address }}">
                            <input type="hidden" name="delivery_zone_id" value="{{ $address->delivery_zone_id }}">
                            <input type="hidden" name="landmark" value="{{ $address->landmark }}">
                            <input type="hidden" name="remark" value="{{ $address->remark }}">
                            <input type="hidden" name="is_default" value="1">

                            <button type="submit" class="btn btn-success btn-sm">
                                ตั้งเป็นหลัก
                            </button>
                        </form>
                    @endif
                    <form action="{{ route('customers.delivery-addresses.destroy', [$customer, $address]) }}"
                        method="POST" class="d-inline">
                        @csrf
                        @method('DELETE')

                        <button type="submit" class="btn btn-danger btn-sm"
                            onclick="return confirm('ลบที่อยู่จัดส่งนี้ใช่หรือไม่?')">
                            ลบ
                        </button>
                    </form>

                </td>

            </tr>

        @empty

            <tr>

                <td colspan="5" class="text-center">

                    ยังไม่มีที่อยู่จัดส่ง

                </td>

            </tr>
        @endforelse

    </tbody>

</table>
