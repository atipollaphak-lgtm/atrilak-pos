import assert from "node:assert/strict";
import { existsSync, readFileSync } from "node:fs";
import test from "node:test";
import vm from "node:vm";

const roundingUrl = new URL(
    "../../public/js/modules/pricing-rounding.js",
    import.meta.url
);

function roundingApi() {
    const context = vm.createContext({
        Number,
        String,
        window: {}
    });

    vm.runInContext(readFileSync(roundingUrl, "utf8"), context);

    return context.window.PricingRounding;
}

test("pricing rounding helper is available as a browser module", () => {
    assert.equal(existsSync(roundingUrl), true);
});

for (const [backendValue, optionValue] of [
    ["0.01", "0.01"],
    ["0.05", "0.05"],
    ["0.10", "0.10"],
    ["0.1", "0.10"],
    ["0.50", "0.50"],
    ["0.5", "0.50"],
    ["1.00", "1"],
    ["1", "1"],
    ["5.00", "5"],
    ["10.00", "10"],
    ["100.00", "100"]
]) {
    test(`selects the ${optionValue} rounding option for backend value ${backendValue}`, () => {
        const api = roundingApi();
        const select = {
            options: [
                { value: "0.01" },
                { value: "0.05" },
                { value: "0.10" },
                { value: "0.50" },
                { value: "1" },
                { value: "5" },
                { value: "10" },
                { value: "100" }
            ],
            value: ""
        };

        api.selectOption(select, backendValue);

        assert.equal(select.value, optionValue);
    });
}

test("rounding uses a normalized unit for the production bug fixture", () => {
    const api = roundingApi();

    assert.equal(api.roundPrice(13.43, "up", "1.00"), 14);
    assert.equal(api.roundPrice(13.43, "up", "1"), 14);
});
