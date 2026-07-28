(function (window) {
    function normalizeUnit(value) {
        const numeric = Number.parseFloat(String(value).trim());

        return Number.isFinite(numeric) && numeric > 0 ? numeric : null;
    }

    function selectOption(select, value) {
        const target = normalizeUnit(value);
        const option = Array.from(select.options).find(option => normalizeUnit(option.value) === target);

        select.value = option ? option.value : '';
    }

    function roundPrice(value, direction, unit) {
        const factor = normalizeUnit(unit);
        if (factor === null) return value;

        const quotient = value / factor;
        const rounded = direction === 'up' ? Math.ceil(quotient) : direction === 'down' ? Math.floor(quotient) : Math.round(quotient);

        return rounded * factor;
    }

    window.PricingRounding = { normalizeUnit, selectOption, roundPrice };
})(window);
