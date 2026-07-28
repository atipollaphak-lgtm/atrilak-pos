(function (window) {
    const SCALE = 1000000n;
    const INCREMENTS = ["0.25", "0.50", "1.00", "5.00", "10.00"];

    function scaled(value) {
        const text = String(value ?? "0").trim();
        const negative = text.startsWith("-");
        const normalized = negative ? text.slice(1) : text;
        const [whole, fraction = ""] = normalized.split(".");
        const micros = BigInt(whole || "0") * SCALE + BigInt((fraction + "000000").slice(0, 6));
        return negative ? -micros : micros;
    }

    function decimal(value, places = 2) {
        const micros = BigInt(value);
        const negative = micros < 0n;
        const absolute = negative ? -micros : micros;
        const unit = 10n ** BigInt(6 - places);
        const rounded = (absolute + unit / 2n) / unit;
        const whole = rounded / (10n ** BigInt(places));
        const fraction = String(rounded % (10n ** BigInt(places))).padStart(places, "0");
        return `${negative ? "-" : ""}${whole}.${fraction}`;
    }

    function ceilAfterMarkup(basePrice, markupPercent, increment) {
        const base = scaled(basePrice);
        const markupBasisPoints = scaled(markupPercent) / 10000n;
        const marked = (base * (10000n + markupBasisPoints) + 9999n) / 10000n;
        const incrementMicros = scaled(increment);
        return decimal(((marked + incrementMicros - 1n) / incrementMicros) * incrementMicros, 2);
    }

    window.ZonePricingMath = {
        INCREMENTS,
        scaled,
        decimal,
        ceilAfterMarkup,
    };
})(window);
