<header class="final-pos-header">
    <div class="final-pos-brand" aria-label="ATRILAK POS">
        <strong>ATRILAK POS</strong>
        <small>ร้านค้าวัสดุก่อสร้าง</small>
    </div>
    <div class="final-pos-search">
        <i class="fas fa-search" aria-hidden="true"></i>
        <input id="v3-product-search" autocomplete="off" aria-label="ค้นหาสินค้าหรือสแกนบาร์โค้ด" placeholder="ค้นหาสินค้า / สแกนบาร์โค้ด">
    </div>
    <nav aria-label="เมนูหน้าขาย">
        <button type="button" data-final-action="history" aria-label="ประวัติการขาย" title="ประวัติการขาย"><i class="fas fa-history" aria-hidden="true"></i><span>ประวัติ</span></button>
        <button id="v3-hold-bill" type="button" data-final-action="holds" aria-label="พักบิล" title="พักบิล"><i class="fas fa-pause-circle" aria-hidden="true"></i><span>พักบิล</span></button>
        <div class="dropdown final-pos-more">
            <button id="v3-more-menu" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" aria-label="เพิ่มเติม" title="เพิ่มเติม"><i class="fas fa-ellipsis-h" aria-hidden="true"></i><span>เพิ่มเติม</span></button>
            <div class="dropdown-menu dropdown-menu-right" aria-labelledby="v3-more-menu">
                <button type="button" class="dropdown-item" disabled aria-disabled="true"><i class="far fa-file-alt" aria-hidden="true"></i>ใบเสนอราคา <small>ยังไม่พร้อมใช้งาน</small></button>
                @if (in_array(auth()->user()?->role, ['manager', 'owner'], true))
                    <a class="dropdown-item" href="{{ route('frequent-products.index') }}"><i class="fas fa-star" aria-hidden="true"></i>จัดการสินค้าขายบ่อย</a>
                @endif
                @if (auth()->user()?->role === 'owner')
                    <a class="dropdown-item" href="{{ route('settings.index') }}"><i class="fas fa-store" aria-hidden="true"></i>ตั้งค่าร้าน</a>
                @endif
                <div class="dropdown-divider"></div>
                <a class="dropdown-item text-danger" href="{{ route('logout') }}" onclick="event.preventDefault();document.getElementById('logout-form').submit();"><i class="fas fa-sign-out-alt" aria-hidden="true"></i>ออกจากระบบ</a>
            </div>
        </div>
    </nav>
</header>
<form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">@csrf</form>
