<div class="col-12">

    <div class="card pos-header-panel">

        <div class="card-header pos-header-titlebar">

            <div class="d-flex align-items-center justify-content-between">

                <div class="pos-header-title">

                    <span class="pos-header-title-icon">
                        <i class="fas fa-cash-register"></i>
                    </span>

                    <div>
                        <div class="pos-header-title-text">
                            ข้อมูลการขาย
                        </div>

                        <div class="pos-header-subtitle">
                            ระบุลูกค้า การจัดส่ง ช่าง และวันที่ขาย
                        </div>
                    </div>

                </div>

                <div class="pos-header-ready-status">
                    <i class="fas fa-circle mr-1"></i>
                    พร้อมขาย
                </div>

            </div>

        </div>

        <div class="card-body">

            <div class="row">

                <div class="col-xl-3 col-md-6 mb-3">

                    <div class="pos-header-field">

                        <label for="customer-id">
                            <i class="fas fa-user mr-1"></i>
                            ลูกค้า
                        </label>

                        <select id="customer-id" class="form-control">
                            <option value="">ลูกค้าทั่วไป</option>

                            @foreach ($customers as $customer)
                                <option value="{{ $customer->id }}">
                                    {{ $customer->name }}
                                </option>
                            @endforeach
                        </select>

                        <small class="pos-header-field-help">
                            เลือกลูกค้าที่ต้องการออกบิล
                        </small>

                    </div>

                </div>

                <div class="col-xl-3 col-md-6 mb-3">

                    <div class="pos-header-field pos-delivery-field">

                        <label for="delivery-address-id">
                            <i class="fas fa-truck mr-1"></i>
                            การจัดส่ง
                        </label>

                        <select id="delivery-address-id" class="form-control">
                            <option value="">
                                เลือกลูกค้าก่อน
                            </option>
                        </select>

                        <div class="form-check pos-pickup-option">

                            <input
                                type="checkbox"
                                id="is-pickup"
                                class="form-check-input"
                            >

                            <label
                                for="is-pickup"
                                class="form-check-label"
                            >
                                <i class="fas fa-store mr-1"></i>
                                ลูกค้ารับเอง
                            </label>

                        </div>

                    </div>

                </div>

                <div class="col-xl-2 col-md-6 mb-3">

                    <div class="pos-header-field">

                        <label for="technician-id">
                            <i class="fas fa-user-cog mr-1"></i>
                            ช่าง
                        </label>

                        <select id="technician-id" class="form-control">

                            <option value="">
                                -- ไม่ระบุช่าง --
                            </option>

                            @foreach ($technicians as $technician)
                                <option value="{{ $technician->id }}">
                                    {{ $technician->name }}
                                </option>
                            @endforeach

                        </select>

                        <small class="pos-header-field-help">
                            ใช้คำนวณค่าคอมมิชชั่น
                        </small>

                    </div>

                </div>

                <div class="col-xl-2 col-md-6 mb-3">

                    <div class="pos-header-field">

                        <label for="sale-date">
                            <i class="fas fa-calendar-alt mr-1"></i>
                            วันที่ขาย
                        </label>

                        <input
                            id="sale-date"
                            type="date"
                            class="form-control"
                            value="{{ date('Y-m-d') }}"
                        >

                    </div>

                </div>

                <div class="col-xl-2 col-md-6 mb-3">

                    <div class="pos-header-field">

                        <label for="sale-no">
                            <i class="fas fa-receipt mr-1"></i>
                            เลขที่บิล
                        </label>

                        <input
                            id="sale-no"
                            class="form-control pos-sale-number"
                            value="AUTO"
                            readonly
                        >

                        <small class="pos-header-field-help">
                            สร้างอัตโนมัติเมื่อบันทึก
                        </small>

                    </div>

                </div>

            </div>

            <div
                id="delivery-address-info"
                class="pos-delivery-info"
            >

                <div class="pos-delivery-info-item">
                    <span class="pos-delivery-info-label">
                        <i class="fas fa-user mr-1"></i>
                        ผู้รับ
                    </span>

                    <strong id="delivery-receiver">-</strong>
                </div>

                <div class="pos-delivery-info-item">
                    <span class="pos-delivery-info-label">
                        <i class="fas fa-phone-alt mr-1"></i>
                        เบอร์
                    </span>

                    <strong id="delivery-phone">-</strong>
                </div>

                <div class="pos-delivery-info-item">
                    <span class="pos-delivery-info-label">
                        <i class="fas fa-map-marker-alt mr-1"></i>
                        โซน
                    </span>

                    <strong id="delivery-zone">-</strong>
                </div>

                <div class="pos-delivery-info-item">
                    <span class="pos-delivery-info-label">
                        <i class="fas fa-coins mr-1"></i>
                        ค่าส่ง
                    </span>

                    <strong id="delivery-fee">0 บาท</strong>
                </div>

            </div>

        </div>

    </div>

</div>
