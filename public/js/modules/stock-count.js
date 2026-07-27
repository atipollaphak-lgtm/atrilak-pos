(function ($) {
    'use strict';

    const quantity = function (value) {
        const parsed = Number.parseFloat(value);

        return Number.isFinite(parsed) ? parsed : 0;
    };

    const calculateRow = function (row) {
        const systemQty = quantity(row.find('.system-qty').val());
        const actualQty = quantity(row.find('.actual-qty').val());

        row.find('.difference').val((actualQty - systemQty).toFixed(4));
    };

    const selectedProductIds = function (except) {
        return $('.product-select').not(except).map(function () {
            return String($(this).val() || '');
        }).get().filter(Boolean);
    };

    $(document).on('change', '.product-select', function () {
        const select = $(this);
        const productId = String(select.val() || '');

        if (productId && selectedProductIds(select).includes(productId)) {
            window.alert('สินค้าในรายการตรวจนับต้องไม่ซ้ำกัน');
            select.val('');
        }

        const row = select.closest('tr');
        const stock = select.find(':selected').attr('data-stock') || '0.0000';

        row.find('.system-qty').val(stock);
        row.find('.actual-qty').val(stock);
        calculateRow(row);
    });

    $(document).on('input', '.actual-qty', function () {
        calculateRow($(this).closest('tr'));
    });

    $('#add-row').on('click', function () {
        const row = $('#stock-count-table tbody tr:first').clone();

        row.find('select').val('');
        row.find('input').val('');
        row.find('.actual-qty').val('0');
        row.find('.difference').val('0.0000');

        $('#stock-count-table tbody').append(row);
    });

    $(document).on('click', '.remove-row', function () {
        if ($('#stock-count-table tbody tr').length > 1) {
            $(this).closest('tr').remove();
        }
    });

    $('#stock-count-table tbody tr').each(function () {
        calculateRow($(this));
    });

    $('#stock-count-form').on('submit', function (event) {
        const form = $(this);

        if (form.data('submitting')) {
            event.preventDefault();

            return;
        }

        form.data('submitting', true);
        form.find('[type="submit"]').prop('disabled', true);
    });
})(jQuery);
