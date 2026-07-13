  <table class="payment-summary-table">
      <tr>
          <td class="payment-cell">
              @if (!empty($setting?->qr_image))
                  <div class="payment-title">
                      QR Payment
                  </div>

                  <img src="{{ asset('storage/' . $setting->qr_image) }}" class="qr-image" alt="QR Payment">

                  <div class="payment-note">
                      สแกนเพื่อชำระเงิน
                  </div>

                  <div class="signature-row">

                      <div class="signature-box">
                          <div class="signature-line"></div>
                          ผู้รับสินค้า
                      </div>

                      <div class="signature-box">
                          <div class="signature-line"></div>
                          ผู้ส่งสินค้า
                      </div>

                  </div>
              @endif
          </td>

          <td class="summary-cell">
              <table class="summary-table">
                  <tr>
                      <td>รวมสินค้า</td>
                      <td class="text-end">
                          {{ $formatNumber($subTotal) }}
                      </td>
                  </tr>

                  <tr>
                      <td>ค่าขนส่ง</td>
                      <td class="text-end">
                          {{ $formatNumber($deliveryFee) }}
                      </td>
                  </tr>

                  <tr>
                      <td>ส่วนลด</td>
                      <td class="text-end">
                          {{ $formatNumber($discount) }}
                      </td>
                  </tr>

                  <tr class="grand-total">
                      <td>ยอดสุทธิ</td>
                      <td class="text-end">
                          {{ $formatNumber($grandTotal) }}
                      </td>
                  </tr>
              </table>

              <div class="baht-text">
                  ({{ thaiBahtText($grandTotal) }})
              </div>
          </td>
      </tr>
  </table>
