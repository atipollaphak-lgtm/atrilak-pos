import assert from "node:assert/strict";
import fs from "node:fs";
import test from "node:test";
import vm from "node:vm";

const sourceUrl = new URL("../../public/js/modules/pos-date.js", import.meta.url);
const source = fs.existsSync(sourceUrl) ? fs.readFileSync(sourceUrl, "utf8") : "";
const sandbox = { globalThis: {} };
sandbox.window = sandbox;
vm.runInNewContext(source, sandbox);
const PosDate = sandbox.PosDate || sandbox.globalThis.PosDate;

assert.ok(PosDate, "POS date helper should be available");

test("formats a valid ISO date for the POS as DD/MM/YYYY", () => {
    assert.equal(PosDate.formatDisplay("2026-08-05"), "05/08/2026");
});

test("parses a valid DD/MM/YYYY input back to an ISO date", () => {
    assert.equal(PosDate.toIso("05/08/2026"), "2026-08-05");
});

test("rejects impossible display dates instead of sending malformed values", () => {
    assert.equal(PosDate.toIso("31/02/2026"), null);
    assert.equal(PosDate.formatDisplay("not-a-date"), "");
});
