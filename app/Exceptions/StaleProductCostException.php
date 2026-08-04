<?php

namespace App\Exceptions;

use DomainException;

class StaleProductCostException extends DomainException
{
    public function __construct()
    {
        parent::__construct('ข้อมูลต้นทุนสินค้าเปลี่ยนแล้ว กรุณาโหลดข้อมูลใหม่ก่อนบันทึก');
    }
}
