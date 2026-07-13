<table class="table table-bordered table-hover align-middle">
    <thead>
        <tr>
            <th>สินค้า</th>
            <th>หมวดหมู่</th>
            <th class="text-right">ต้นทุนเฉลี่ย</th>
            <th class="text-right">ราคาปัจจุบัน</th>
            <th class="text-right">% กำไร</th>
            <th class="text-right">ราคาก่อนปัด</th>
            <th class="text-right">หลังปัดสตางค์</th>
            <th class="text-right">ราคาสุดท้าย</th>
            <th>สถานะ</th>
            <th style="min-width: 260px;">ตั้งค่า</th>
            <th>Apply</th>
        </tr>
    </thead>

    <tbody>
        @foreach ($products as $product)
            @php
                $preview = $pricingPreviews[$product->id] ?? null;
            @endphp

            @if ($preview)
                <tr class="pricing-product-row" data-product-name="{{ strtolower($product->name) }}"
                    data-product-id="{{ $product->id }}" data-changed="{{ $preview['changed'] ? '1' : '0' }}"
                    data-locked="{{ $preview['price_lock'] ? '1' : '0' }}"
data-auto-pricing="{{ $preview['auto_price_enabled'] ? '1' : '0' }}"
data-auto-off="{{ $preview['auto_price_enabled'] ? '0' : '1' }}"
                    data-override="{{ $product->profit_percent || $product->satang_rounding_mode || $product->baht_rounding_mode ? '1' : '0' }}">
                    <td>
                        <strong>{{ $product->name }}</strong>
                        <br>
                        <small class="text-muted">
                            #{{ $product->id }}
                        </small>
                    </td>

                    <td>
                        {{ $product->category->name ?? '-' }}
                    </td>

                    <td class="text-right">
                        {{ number_format($preview['average_cost'], 2) }}
                    </td>

                    <td class="text-right">
                        {{ number_format($preview['old_price'], 2) }}
                    </td>

                    <td class="text-right">
                        {{ number_format($preview['profit_percent'], 2) }}%
                    </td>

                    <td class="text-right">
                        {{ number_format($preview['price_before_round'], 2) }}
                    </td>

                    <td class="text-right">
                        {{ number_format($preview['satang_rounded_price'], 2) }}
                    </td>

                    <td class="text-right">
                        <strong>
                            {{ number_format($preview['final_price'], 2) }}
                        </strong>
                    </td>

                    <td>
                        @if ($preview['price_lock'])
                            <span class="badge badge-warning">
                                Locked
                            </span>
                        @elseif ($preview['changed'])
                            <span class="badge badge-warning">
                                Need Update
                            </span>
                        @else
                            <span class="badge badge-success">
                                OK
                            </span>
                        @endif

                        <br>

                        @if ($preview['auto_price_enabled'])
                            <small class="text-success">Auto Pricing On</small>
                        @else
                            <small class="text-muted">Auto Pricing Off</small>
                        @endif
                    </td>

                    <td>
                        <form method="POST" action="{{ route('pricing-management.update', $product) }}">
                            @csrf
                            @method('PUT')

                            <div class="form-check mb-1">
                                <input type="hidden" name="auto_price_enabled" value="0">
                                <input class="form-check-input" type="checkbox" name="auto_price_enabled" value="1"
                                    id="auto_price_enabled_{{ $product->id }}"
                                    {{ $preview['auto_price_enabled'] ? 'checked' : '' }}>
                                <label class="form-check-label" for="auto_price_enabled_{{ $product->id }}">
                                    Auto Pricing
                                </label>
                            </div>

                            <div class="form-check mb-2">
                                <input type="hidden" name="price_lock" value="0">
                                <input class="form-check-input" type="checkbox" name="price_lock" value="1"
                                    id="price_lock_{{ $product->id }}" {{ $preview['price_lock'] ? 'checked' : '' }}>
                                <label class="form-check-label" for="price_lock_{{ $product->id }}">
                                    Price Lock
                                </label>
                            </div>

                            <div class="form-group mb-2">
                                <label class="mb-1">Override Profit %</label>
                                <input type="number" step="0.01" name="profit_percent"
                                    class="form-control form-control-sm" value="{{ $product->profit_percent }}">
                            </div>

                            <div class="form-group mb-2">
                                <label class="mb-1">ปัดสตางค์</label>
                                <select name="satang_rounding_mode" class="form-control form-control-sm">
                                    <option value="">ใช้ค่าจากหมวด/ระบบ</option>
                                    <option value="none"
                                        {{ $product->satang_rounding_mode === 'none' ? 'selected' : '' }}>ไม่ปัด
                                    </option>
                                    <option value="ceil_satang_10"
                                        {{ $product->satang_rounding_mode === 'ceil_satang_10' ? 'selected' : '' }}>
                                        ปัดขึ้น 0.10</option>
                                    <option value="ceil_satang_25"
                                        {{ $product->satang_rounding_mode === 'ceil_satang_25' ? 'selected' : '' }}>
                                        ปัดขึ้น 0.25</option>
                                    <option value="ceil_satang_50"
                                        {{ $product->satang_rounding_mode === 'ceil_satang_50' ? 'selected' : '' }}>
                                        ปัดขึ้น 0.50</option>
                                </select>
                            </div>

                            <div class="form-group mb-2">
                                <label class="mb-1">ปัดบาท</label>
                                <select name="baht_rounding_mode" class="form-control form-control-sm">
                                    <option value="">ใช้ค่าจากหมวด/ระบบ</option>
                                    <option value="none"
                                        {{ $product->baht_rounding_mode === 'none' ? 'selected' : '' }}>ไม่ปัด</option>
                                    <option value="ceil_baht"
                                        {{ $product->baht_rounding_mode === 'ceil_baht' ? 'selected' : '' }}>ปัดขึ้นบาท
                                    </option>
                                    <option value="ceil_5"
                                        {{ $product->baht_rounding_mode === 'ceil_5' ? 'selected' : '' }}>ปัดขึ้น 5 บาท
                                    </option>
                                    <option value="ceil_10"
                                        {{ $product->baht_rounding_mode === 'ceil_10' ? 'selected' : '' }}>ปัดขึ้น 10
                                        บาท</option>
                                    <option value="ceil_25"
                                        {{ $product->baht_rounding_mode === 'ceil_25' ? 'selected' : '' }}>ปัดขึ้น 25
                                        บาท</option>
                                    <option value="ceil_50"
                                        {{ $product->baht_rounding_mode === 'ceil_50' ? 'selected' : '' }}>ปัดขึ้น 50
                                        บาท</option>
                                </select>
                            </div>

                            <button type="submit" class="btn btn-sm btn-primary btn-block">
                                บันทึกตั้งค่า
                            </button>
                        </form>
                    </td>

                    <td>
                        <form method="POST" action="{{ route('pricing-management.apply', $product) }}">
                            @csrf

                            <button type="submit" class="btn btn-sm btn-success"
                                {{ $preview['price_lock'] ? 'disabled' : '' }}>
                                Apply Price
                            </button>
                        </form>
                    </td>
                </tr>
            @endif
        @endforeach
    </tbody>
</table>
