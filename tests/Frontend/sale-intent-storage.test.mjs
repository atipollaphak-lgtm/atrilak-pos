import assert from "node:assert/strict";
import { webcrypto } from "node:crypto";
import { readFileSync } from "node:fs";
import test from "node:test";
import { TextEncoder } from "node:util";
import vm from "node:vm";

const source = readFileSync(
    new URL("../../public/js/modules/sale-intent-storage.js", import.meta.url),
    "utf8"
);
const context = vm.createContext({
    Array,
    Date,
    Error,
    JSON,
    Math,
    Number,
    Object,
    Set,
    String,
    TextEncoder,
    Uint8Array,
    console,
    crypto: webcrypto
});
vm.runInContext(source, context);
const intentStorage = context.SaleIntentStorage;

class MemoryStorage {
    constructor() {
        this.values = new Map();
    }

    getItem(key) {
        return this.values.get(key) ?? null;
    }

    setItem(key, value) {
        this.values.set(key, value);
    }

    removeItem(key) {
        this.values.delete(key);
    }
}

function payload() {
    return {
        customer_id: "7",
        customer_delivery_address_id: "11",
        technician_id: null,
        sale_date: "2026-07-15",
        delivery_type: "delivery",
        delivery_fee: "25.00",
        discount: "5.00",
        items: [{
            product_id: "3",
            product_unit_id: "9",
            qty: "2.00",
            selling_price: "10.00"
        }]
    };
}

test("fingerprint is stable for equivalent payload and object key order", async () => {
    const first = payload();
    const second = {
        items: [{
            selling_price: 10,
            qty: 2,
            product_unit_id: 9,
            product_id: 3
        }],
        discount: 5,
        delivery_fee: 25,
        delivery_type: "delivery",
        sale_date: "2026-07-15",
        technician_id: null,
        customer_delivery_address_id: 11,
        customer_id: 7
    };

    assert.equal(
        await intentStorage.fingerprint(first, webcrypto),
        await intentStorage.fingerprint(second, webcrypto)
    );
});

test("fingerprint changes for every Sale intent field", async () => {
    const original = payload();
    const originalFingerprint = await intentStorage.fingerprint(original, webcrypto);
    const mutations = [
        value => { value.customer_id = "8"; },
        value => { value.customer_delivery_address_id = "12"; },
        value => { value.technician_id = "4"; },
        value => { value.sale_date = "2026-07-16"; },
        value => { value.delivery_type = "pickup"; },
        value => { value.delivery_fee = "30.00"; },
        value => { value.discount = "6.00"; },
        value => { value.items[0].product_id = "4"; },
        value => { value.items[0].product_unit_id = "10"; },
        value => { value.items[0].qty = "3.00"; },
        value => { value.items[0].selling_price = "11.00"; }
    ];

    for (const mutate of mutations) {
        const changed = JSON.parse(JSON.stringify(original));
        mutate(changed);
        assert.notEqual(
            await intentStorage.fingerprint(changed, webcrypto),
            originalFingerprint
        );
    }
});

test("same-page retry reuses the same key", async () => {
    const manager = intentStorage.createManager({
        storage: new MemoryStorage(),
        storageKey: "same-page",
        uuid: () => "same-page-key"
    });

    const first = await manager.keyFor(payload());
    const retried = await manager.keyFor(payload());

    assert.equal(first.key, retried.key);
    assert.equal(retried.reused, true);
});

test("uses secure random bytes when randomUUID is unavailable", async () => {
    let calls = 0;
    const cryptoWithoutRandomUuid = {
        getRandomValues(values) {
            calls++;

            for (let index = 0; index < values.length; index++) {
                values[index] = index;
            }

            return values;
        }
    };
    const manager = intentStorage.createManager({
        crypto: cryptoWithoutRandomUuid,
        storage: new MemoryStorage(),
        storageKey: "get-random-values"
    });

    const intent = await manager.keyFor(payload());

    assert.equal(calls, 1);
    assert.match(intent.key, /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/);
    assert.equal(intent.key, "00010203-0405-4607-8809-0a0b0c0d0e0f");
});

test("fails closed when secure idempotency-key generation is unavailable", async () => {
    const manager = intentStorage.createManager({
        crypto: {},
        storage: new MemoryStorage(),
        storageKey: "no-secure-randomness"
    });

    await assert.rejects(
        manager.keyFor(payload()),
        /Secure idempotency-key generation is unavailable\./
    );
});

test("reload recovery reuses key for unchanged payload", async () => {
    const storage = new MemoryStorage();
    const firstPage = intentStorage.createManager({
        storage,
        storageKey: "reload",
        uuid: () => "persisted-key"
    });
    await firstPage.keyFor(payload());
    const reloadedPage = intentStorage.createManager({
        storage,
        storageKey: "reload",
        uuid: () => "unexpected-new-key"
    });

    const recovered = await reloadedPage.keyFor(payload());

    assert.equal(recovered.key, "persisted-key");
    assert.equal(recovered.reused, true);
});

test("reload recovery creates a new key for changed payload", async () => {
    const storage = new MemoryStorage();
    const firstPage = intentStorage.createManager({
        storage,
        storageKey: "changed",
        uuid: () => "first-key"
    });
    await firstPage.keyFor(payload());
    const changed = payload();
    changed.items[0].qty = "3.00";
    const reloadedPage = intentStorage.createManager({
        storage,
        storageKey: "changed",
        uuid: () => "changed-key"
    });

    const next = await reloadedPage.keyFor(changed);

    assert.equal(next.key, "changed-key");
    assert.equal(next.reused, false);
});

test("expired pending state creates a new key", async () => {
    let now = 1000;
    const storage = new MemoryStorage();
    const manager = intentStorage.createManager({
        storage,
        storageKey: "expired",
        ttlMs: 100,
        now: () => now,
        uuid: () => now === 1000 ? "first-key" : "fresh-key"
    });
    await manager.keyFor(payload());
    now = 1101;

    const fresh = await manager.keyFor(payload());

    assert.equal(fresh.key, "fresh-key");
    assert.equal(fresh.reused, false);
});

test("confirmed success clears only the matching pending key", async () => {
    const storage = new MemoryStorage();
    const manager = intentStorage.createManager({
        storage,
        storageKey: "success",
        uuid: () => "success-key"
    });
    const intent = await manager.keyFor(payload());

    assert.equal(manager.clear("different-key"), false);
    assert.notEqual(manager.current(), null);
    assert.equal(manager.clear(intent.key), true);
    assert.equal(manager.current(), null);
});

test("storage unavailable falls back to same-page memory", async () => {
    const unavailable = {
        getItem() { throw new Error("blocked"); },
        setItem() { throw new Error("blocked"); },
        removeItem() { throw new Error("blocked"); }
    };
    const manager = intentStorage.createManager({
        storage: unavailable,
        storageKey: "unavailable",
        uuid: () => "memory-key"
    });

    const first = await manager.keyFor(payload());
    const retry = await manager.keyFor(payload());

    assert.equal(first.key, "memory-key");
    assert.equal(retry.key, "memory-key");
});

test("V1 and V2 storage namespaces do not collide", async () => {
    const storage = new MemoryStorage();
    const v1 = intentStorage.createManager({
        storage,
        storageKey: "atrilak.pos.v1.pending-sale.v1",
        uuid: () => "v1-key"
    });
    const v2 = intentStorage.createManager({
        storage,
        storageKey: "atrilak.pos.v2.pending-sale.v1",
        uuid: () => "v2-key"
    });

    assert.equal((await v1.keyFor(payload())).key, "v1-key");
    assert.equal((await v2.keyFor(payload())).key, "v2-key");
    assert.equal(storage.values.size, 2);
});

test("submission guard permits only one active request and can be released", () => {
    const guard = intentStorage.createSubmissionGuard();

    assert.equal(guard.start(), true);
    assert.equal(guard.start(), false);
    assert.equal(guard.isActive(), true);
    guard.release();
    assert.equal(guard.isActive(), false);
    assert.equal(guard.start(), true);
});

test("only definitive 4xx responses clear pending state", () => {
    assert.equal(intentStorage.isDefinitiveClientError(400), true);
    assert.equal(intentStorage.isDefinitiveClientError(409), true);
    assert.equal(intentStorage.isDefinitiveClientError(422), true);
    assert.equal(intentStorage.isDefinitiveClientError(500), false);
    assert.equal(intentStorage.isDefinitiveClientError(undefined), false);
});

test("FormData fingerprint excludes CSRF and idempotency key", () => {
    const fakeFormData = {
        entries() {
            return [
                ["_token", "secret"],
                ["idempotency_key", "old-key"],
                ["product_id[]", "1"],
                ["product_id[]", "2"],
                ["qty[]", "3"]
            ][Symbol.iterator]();
        }
    };

    assert.deepEqual(
        JSON.parse(JSON.stringify(intentStorage.formDataPayload(fakeFormData))),
        {
            "product_id[]": ["1", "2"],
            "qty[]": ["3"]
        }
    );
});
