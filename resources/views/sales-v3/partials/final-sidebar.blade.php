<header class="final-pos-header">
    <div class="final-pos-brand">
        <img src="{{ asset('vendor/adminlte/dist/img/AdminLTELogo.png') }}" alt="ATRILAK">
        <div><strong>ATRILAK POS</strong><small>ร้านค้าวัสดุก่อสร้าง</small></div>
    </div>
    <div class="final-pos-search"><i class="fas fa-search" aria-hidden="true"></i><input id="v3-product-search" autocomplete="off" aria-label="ค้นหาสินค้าหรือสแกนบาร์โค้ด" placeholder="ค้นหาสินค้า / สแกนบาร์โค้ด"></div>
    <nav aria-label="เมนูหน้าขาย">
        <button type="button" data-final-action="history" aria-label="ประวัติการขาย" title="ประวัติการขาย"><i class="fas fa-file-invoice" aria-hidden="true"></i><span>ประวัติ</span></button>
        <button id="v3-hold-bill" type="button" data-final-action="holds" aria-label="พักบิล" title="พักบิล"><i class="fas fa-pause-circle" aria-hidden="true"></i><span>พักบิล</span></button>
        <button type="button" disabled aria-label="ใบเสนอราคา (ยังไม่พร้อมใช้งาน)" title="ใบเสนอราคา (ยังไม่พร้อมใช้งาน)"><i class="fas fa-file-alt" aria-hidden="true"></i><span>ใบเสนอราคา</span></button>
        <button type="button" aria-label="ตั้งค่าร้าน" title="ตั้งค่าร้าน"><i class="fas fa-cog" aria-hidden="true"></i><span>ตั้งค่าร้าน</span></button>
        <a href="{{ route('logout') }}" aria-label="ออกจากระบบ" title="ออกจากระบบ" onclick="event.preventDefault();document.getElementById('logout-form').submit();"><i class="fas fa-power-off" aria-hidden="true"></i><span>ออกจากระบบ</span></a>
    </nav>
</header>
<form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">@csrf</form>
