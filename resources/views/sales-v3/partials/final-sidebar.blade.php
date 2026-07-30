<header class="final-pos-header">
    <div class="final-pos-brand">
        <img src="{{ asset('vendor/adminlte/dist/img/AdminLTELogo.png') }}" alt="ATRILAK">
        <div><strong>ATRILAK POS</strong><small>ร้านค้าวัสดุก่อสร้าง</small></div>
    </div>
    <div class="final-pos-search"><i class="fas fa-search"></i><input id="v3-product-search" autocomplete="off" placeholder="ค้นหาสินค้า / สแกนบาร์โค้ด"></div>
    <nav>
        <button type="button" data-final-action="history"><i class="fas fa-file-invoice"></i><span>ประวัติ</span></button>
        <button id="v3-hold-bill" type="button" data-final-action="holds"><i class="fas fa-pause-circle"></i><span>พักบิล</span></button>
        <button type="button" disabled><i class="fas fa-file-alt"></i><span>ใบเสนอราคา</span></button>
        <button type="button"><i class="fas fa-cog"></i><span>ตั้งค่าร้าน</span></button>
        <a href="{{ route('logout') }}" onclick="event.preventDefault();document.getElementById('logout-form').submit();"><i class="fas fa-power-off"></i><span>ออกจากระบบ</span></a>
    </nav>
</header>
<form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">@csrf</form>
