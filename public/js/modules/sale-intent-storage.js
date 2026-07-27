(function exposeSaleIntentStorage(root, factory) {
    const api = factory(root);

    if (typeof module === "object" && module.exports) {
        module.exports = api;
    }

    root.SaleIntentStorage = api;
})(typeof globalThis !== "undefined" ? globalThis : window, function createApi(root) {
    const RECORD_VERSION = 1;
    const DEFAULT_TTL_MS = 2 * 60 * 60 * 1000;
    const EXCLUDED_FORM_FIELDS = new Set(["_token", "idempotency_key"]);

    function normalizeNumericString(value) {
        const match = String(value).match(/^(-?)(\d+)(?:\.(\d+))?$/);

        if (!match) {
            return String(value);
        }

        const integer = match[2].replace(/^0+(?=\d)/, "") || "0";
        const fraction = (match[3] || "").replace(/0+$/, "");
        const sign = match[1] === "-" && (integer !== "0" || fraction) ? "-" : "";

        return sign + integer + (fraction ? "." + fraction : "");
    }

    function canonicalize(value) {
        if (value === null || value === undefined) {
            return null;
        }

        if (Array.isArray(value)) {
            return value.map(canonicalize);
        }

        if (typeof value === "object") {
            return Object.keys(value)
                .sort()
                .reduce(function (result, key) {
                    result[key] = canonicalize(value[key]);
                    return result;
                }, {});
        }

        if (typeof value === "number" || typeof value === "string") {
            return normalizeNumericString(value);
        }

        return value;
    }

    function stableSerialize(payload) {
        return JSON.stringify(canonicalize(payload));
    }

    function fallbackHash(value) {
        let first = 2166136261;
        let second = 2246822519;

        for (let index = 0; index < value.length; index++) {
            const code = value.charCodeAt(index);
            first = Math.imul(first ^ code, 16777619);
            second = Math.imul(second ^ code, 3266489917);
        }

        return [first, second]
            .map(function (part) {
                return (part >>> 0).toString(16).padStart(8, "0");
            })
            .join("");
    }

    async function fingerprint(payload, cryptoImplementation) {
        const serialized = stableSerialize(payload);
        const cryptoApi = cryptoImplementation || root.crypto;

        if (cryptoApi?.subtle && typeof root.TextEncoder === "function") {
            const encoded = new root.TextEncoder().encode(serialized);
            const digest = await cryptoApi.subtle.digest("SHA-256", encoded);

            return Array.from(new Uint8Array(digest))
                .map(function (byte) {
                    return byte.toString(16).padStart(2, "0");
                })
                .join("");
        }

        return fallbackHash(serialized);
    }

    function formDataPayload(formData) {
        const payload = {};

        for (const [name, value] of formData.entries()) {
            if (EXCLUDED_FORM_FIELDS.has(name)) {
                continue;
            }

            if (!payload[name]) {
                payload[name] = [];
            }

            payload[name].push(String(value));
        }

        return payload;
    }

    function availableSessionStorage() {
        try {
            return root.sessionStorage || null;
        } catch (error) {
            return null;
        }
    }

    function secureUuid(cryptoApi) {
        if (typeof cryptoApi?.randomUUID === "function") {
            return cryptoApi.randomUUID();
        }

        if (typeof cryptoApi?.getRandomValues === "function") {
            const bytes = cryptoApi.getRandomValues(new Uint8Array(16));

            bytes[6] = (bytes[6] & 0x0f) | 0x40;
            bytes[8] = (bytes[8] & 0x3f) | 0x80;

            const hex = Array.from(bytes, function (byte) {
                return byte.toString(16).padStart(2, "0");
            }).join("");

            return hex.slice(0, 8) + "-"
                + hex.slice(8, 12) + "-"
                + hex.slice(12, 16) + "-"
                + hex.slice(16, 20) + "-"
                + hex.slice(20);
        }

        throw new Error("Secure idempotency-key generation is unavailable.");
    }

    function createManager(options) {
        const settings = options || {};
        const storage = settings.storage === undefined
            ? availableSessionStorage()
            : settings.storage;
        const storageKey = settings.storageKey;
        const ttlMs = settings.ttlMs || DEFAULT_TTL_MS;
        const now = settings.now || Date.now;
        const cryptoApi = settings.crypto || root.crypto;
        const uuid = settings.uuid || function () {
            return secureUuid(cryptoApi);
        };
        let memoryRecord = null;

        if (!storageKey) {
            throw new Error("A pending Sale storage key is required.");
        }

        function readRecord() {
            try {
                const stored = storage?.getItem(storageKey);

                if (stored) {
                    memoryRecord = JSON.parse(stored);
                }
            } catch (error) {
                // Storage can be blocked by browser privacy settings.
            }

            return memoryRecord;
        }

        function writeRecord(record) {
            memoryRecord = record;

            try {
                storage?.setItem(storageKey, JSON.stringify(record));
            } catch (error) {
                // The in-memory record preserves same-page retry behavior.
            }
        }

        function removeRecord() {
            memoryRecord = null;

            try {
                storage?.removeItem(storageKey);
            } catch (error) {
                // Nothing else is required for the in-memory fallback.
            }
        }

        function isCurrent(record) {
            const age = now() - Number(record?.createdAt || 0);

            return record?.version === RECORD_VERSION
                && typeof record.key === "string"
                && typeof record.fingerprint === "string"
                && age >= 0
                && age <= ttlMs;
        }

        return {
            async keyFor(payload) {
                const payloadFingerprint = await fingerprint(payload, cryptoApi);
                const record = readRecord();

                if (isCurrent(record) && record.fingerprint === payloadFingerprint) {
                    return {
                        key: record.key,
                        fingerprint: payloadFingerprint,
                        reused: true
                    };
                }

                const next = {
                    version: RECORD_VERSION,
                    key: uuid(),
                    fingerprint: payloadFingerprint,
                    createdAt: now()
                };
                writeRecord(next);

                return {
                    key: next.key,
                    fingerprint: payloadFingerprint,
                    reused: false
                };
            },

            clear(expectedKey) {
                const record = readRecord();

                if (expectedKey && record?.key !== expectedKey) {
                    return false;
                }

                removeRecord();
                return true;
            },

            current() {
                const record = readRecord();
                return isCurrent(record) ? record : null;
            }
        };
    }

    function createSubmissionGuard() {
        let active = false;

        return {
            start() {
                if (active) {
                    return false;
                }

                active = true;
                return true;
            },

            release() {
                active = false;
            },

            isActive() {
                return active;
            }
        };
    }

    function isDefinitiveClientError(status) {
        return Number(status) >= 400 && Number(status) < 500;
    }

    return {
        DEFAULT_TTL_MS,
        createManager,
        createSubmissionGuard,
        fingerprint,
        formDataPayload,
        isDefinitiveClientError,
        stableSerialize
    };
});
