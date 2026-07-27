<div class="row">

    <input type="hidden" id="customer-phone" value="{{ $customer->phone }}">

    <div class="col-md-4">
        <label>ชื่อสถานที่</label>
        <input type="text" name="name" class="form-control" value="{{ old('name', $address->name ?? '') }}">
    </div>

    <div class="col-md-12 mt-3">
        <div class="form-check">
            <input type="checkbox" name="use_customer_phone" value="1" class="form-check-input" id="use-customer-phone"
                @checked(old('use_customer_phone', ($address->receiver_phone ?? null) === $customer->phone))>
            <label class="form-check-label" for="use-customer-phone">ใช้เบอร์ลูกค้าเป็นเบอร์ผู้รับของ</label>
        </div>
    </div>

    <div class="col-md-4">
        <label>ชื่อผู้รับ</label>
        <input type="text" name="receiver_name" class="form-control"
            value="{{ old('receiver_name', $address->receiver_name ?? '') }}">
    </div>

    <div class="col-md-4">
        <label>เบอร์ผู้รับ</label>
        <input type="text" id="receiver-phone" name="receiver_phone" class="form-control"
            value="{{ old('receiver_phone', $address->receiver_phone ?? '') }}">
    </div>

    <div class="col-md-12 mt-3">
        <label>ที่อยู่จัดส่ง</label>
        <textarea name="address" class="form-control" rows="3">{{ old('address', $address->address ?? '') }}</textarea>
    </div>

    <div class="col-md-6 mt-3">
        <label>โซนจัดส่ง</label>
        <select name="delivery_zone_id" class="form-control">
            <option value="">-- เลือกโซน --</option>

            @foreach ($deliveryZones as $zone)
                <option value="{{ $zone->id }}" @selected(old('delivery_zone_id', $address->delivery_zone_id ?? '') == $zone->id)>
                    {{ $zone->name }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="col-md-6 mt-3">
        <label>จุดสังเกต</label>
        <input type="text" name="landmark" class="form-control"
            value="{{ old('landmark', $address->landmark ?? '') }}">
    </div>

    <div class="col-md-12 mt-3">
        <label>หมายเหตุ</label>
        <textarea name="remark" class="form-control" rows="2">{{ old('remark', $address->remark ?? '') }}</textarea>
    </div>

    <div class="col-md-12 mt-3">
        <div class="form-check">
            <input type="checkbox" name="is_default" value="1" class="form-check-input" id="is_default"
                @checked(old('is_default', $address->is_default ?? false))>
            <label class="form-check-label" for="is_default">
                ตั้งเป็นที่อยู่จัดส่งหลัก
            </label>
        </div>
    </div>

</div>

<hr>

