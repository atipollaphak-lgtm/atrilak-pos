(function () {
    const root = document.getElementById("zone-pricing-preview");
    if (!root || !window.ZonePricingMath) return;

    const input = document.getElementById("zone-preview-price");
    const markup = document.querySelector('[name="price_markup_percent"]');
    const increment = document.getElementById("zone-rounding-increment");
    const text = (selector) => document.querySelector(selector);

    function update() {
        const base = input.value || "0";
        const baseMicros = window.ZonePricingMath.scaled(base);
        const markupMicros = window.ZonePricingMath.scaled(markup.value || "0");
        const incrementValue = increment.value || "0.25";
        const markedMicros = (baseMicros * (10000n + markupMicros / 10000n) + 9999n) / 10000n;
        const final = window.ZonePricingMath.ceilAfterMarkup(base, markup.value || "0", incrementValue);
        text("#zone-preview-base").textContent = `${window.ZonePricingMath.decimal(baseMicros)} บาท`;
        text("#zone-preview-markup-amount").textContent = `${window.ZonePricingMath.decimal(markedMicros - baseMicros, 3)} บาท`;
        text("#zone-preview-before-rounding").textContent = `${window.ZonePricingMath.decimal(markedMicros, 3)} บาท`;
        text("#zone-preview-rounding").textContent = `ปัดขึ้น ${Number(incrementValue).toFixed(2)} บาท`;
        text("#zone-preview-final").textContent = `${final} บาท`;
    }

    [input, markup, increment].forEach((element) => element?.addEventListener("input", update));
    [increment].forEach((element) => element?.addEventListener("change", update));
    update();
})();
