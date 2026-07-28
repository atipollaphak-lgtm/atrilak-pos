import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import test from "node:test";
import vm from "node:vm";

const source = readFileSync(new URL("../../public/js/modules/zone-pricing.js", import.meta.url), "utf8");

function api() {
    const context = vm.createContext({ BigInt, String, window: {} });
    vm.runInContext(source, context);
    return context.window.ZonePricingMath;
}

test("zone pricing supports the five ceiling increments", () => {
    const zone = api();

    assert.deepEqual(Array.from(zone.INCREMENTS), ["0.25", "0.50", "1.00", "5.00", "10.00"]);
    assert.equal(zone.ceilAfterMarkup("6.30", "3.00", "0.25"), "6.50");
    assert.equal(zone.ceilAfterMarkup("6.30", "3.00", "0.50"), "6.50");
    assert.equal(zone.ceilAfterMarkup("6.30", "3.00", "1.00"), "7.00");
    assert.equal(zone.ceilAfterMarkup("6.30", "3.00", "5.00"), "10.00");
    assert.equal(zone.ceilAfterMarkup("6.30", "3.00", "10.00"), "10.00");
});
