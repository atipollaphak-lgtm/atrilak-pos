@if ($sale->isVoided())
    <div class="void-document-marker" role="status"
        style="border: 3px solid #b91c1c; color: #b91c1c; font-size: 20px; font-weight: bold; margin: 0 0 12px; padding: 8px 12px; text-align: center;">
        <strong>ยกเลิก / VOID</strong>
        <span style="font-size: 13px; font-weight: normal; margin-left: 8px;">เอกสารฉบับนี้ถูกยกเลิกแล้ว</span>
    </div>
@endif
