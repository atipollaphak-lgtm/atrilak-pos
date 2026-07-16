<?php

namespace App\Exceptions;

use DomainException;

class StaleSaleRevisionException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            'ใบขายนี้ถูกแก้ไขจากหน้าจออื่นแล้ว กรุณาตรวจสอบข้อมูลล่าสุดและแก้ไขใหม่อีกครั้ง'
        );
    }
}
