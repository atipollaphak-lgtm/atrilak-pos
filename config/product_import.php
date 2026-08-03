<?php

return [
    'max_rows' => 500,
    'max_file_size_kb' => 5120,
    'token_ttl_minutes' => 30,
    'allowed_extensions' => ['xlsx'],
    'sheet_name' => 'สินค้า',
    'headers' => [
        'product_name' => 'ชื่อสินค้า',
        'category' => 'หมวดหมู่',
        'base_unit' => 'หน่วยหลัก',
        'cost_price' => 'ต้นทุน',
        'selling_price' => 'ราคาขาย',
        'opening_stock' => 'จำนวนเริ่มต้น',
        'product_code' => 'รหัสสินค้า',
        'barcode' => 'บาร์โค้ด',
        'status' => 'สถานะ',
        'price_locked' => 'ล็อกราคาขาย',
        'description' => 'รายละเอียด',
    ],
    'required_headers' => [
        'product_name',
        'category',
        'base_unit',
        'cost_price',
        'selling_price',
    ],
    'reference_sheets' => [
        'categories' => 'หมวดหมู่',
        'units' => 'หน่วย',
        'instructions' => 'คำแนะนำ',
    ],
];
