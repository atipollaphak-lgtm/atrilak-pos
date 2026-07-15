(function ($) {
    'use strict';

    function calculateLineTotals() {
        var grandTotal = 0;

        $('#purchase-items tr').each(function () {
            var row = $(this);
            var quantity = Number.parseFloat(row.find('.qty').val()) || 0;
            var cost = Number.parseFloat(row.find('.cost-price').val()) || 0;
            var lineTotal = quantity * cost;

            row.find('.line-total').text(lineTotal.toFixed(2));
            grandTotal += lineTotal;
        });

        $('#grand-total').text(grandTotal.toFixed(2));
    }

    $(function () {
        calculateLineTotals();

        $('#addRow').on('click', function () {
            var row = $('#purchase-items tr').first();

            if (row.length === 0) {
                return;
            }

            var clone = row.clone(false);
            clone.find('select').val('');
            clone.find('input').val('');
            clone.find('.line-total').text('0.00');
            $('#purchase-items').append(clone);
            calculateLineTotals();
        });

        $(document).on('click', '.remove-row', function () {
            if ($('#purchase-items tr').length > 1) {
                $(this).closest('tr').remove();
                calculateLineTotals();
            }
        });

        $(document).on('input', '.qty, .cost-price', calculateLineTotals);

        $('.purchase-delete-form').on('submit', function (event) {
            var message = $(this).data('confirm-message');

            if (message && !window.confirm(message)) {
                event.preventDefault();
            }
        });

        $('.purchase-form').on('submit', function (event) {
            var form = $(this);

            if (form.data('submitting')) {
                event.preventDefault();

                return;
            }

            form.data('submitting', true);
            form.find('button[type="submit"]').prop('disabled', true);
        });
    });
})(jQuery);
