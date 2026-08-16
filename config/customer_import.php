<?php

return [
    'max_rows' => 1000,
    'max_file_size_kb' => 5120,
    'token_ttl_minutes' => 30,
    'allowed_extensions' => ['xlsx'],
    'sources' => [
        'C2M' => [
            'required_headers' => ['external_id', 'name'],
            'headers' => [
                'external_id' => ['ลำดับ', 'ลำดับที่', 'id', 'รหัสสมาชิก'],
                'contact' => ['การติดต่อ'],
                'name' => ['ชื่อ', 'ชื่อลูกค้า', 'ชื่อสมาชิก'],
                'customer_group' => ['กลุ่มลูกค้า'],
                'address' => ['ที่อยู่', 'ที่อยู่จัดส่ง'],
                'phone' => ['เบอร์โทร', 'เบอร์โทรศัพท์', 'โทรศัพท์', 'โทร'],
                'email' => ['อีเมล์', 'อีเมล', 'email'],
                'action' => ['จัดการ'],
                'tax_number' => ['เลขผู้เสียภาษี', 'เลขประจำตัวผู้เสียภาษี', 'เลขภาษี'],
                'remark' => ['หมายเหตุ', 'รายละเอียด'],
            ],
        ],
        'ATRILAK_TEMPLATE' => [
            'required_headers' => ['external_id', 'name'],
            'headers' => [
                'external_id' => ['external_id', 'รหัสอ้างอิงภายนอก'],
                'name' => ['name', 'ชื่อ'],
                'phone' => ['phone', 'เบอร์โทร'],
                'tax_number' => ['tax_id', 'tax_number', 'เลขผู้เสียภาษี'],
                'branch_type' => ['branch_type', 'ประเภทสาขา'],
                'branch_number' => ['branch_number', 'เลขสาขา'],
                'address' => ['address', 'ที่อยู่'],
                'remark' => ['remark', 'หมายเหตุ'],
            ],
        ],
    ],
    'default_branch_type' => 'สำนักงานใหญ่',
    'template_headers' => [
        'external_id',
        'name',
        'phone',
        'tax_id',
        'branch_type',
        'branch_number',
        'address',
        'remark',
    ],
];
