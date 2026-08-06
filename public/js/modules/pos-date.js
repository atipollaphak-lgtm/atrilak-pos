(function (global) {
    function partsFromIso(value) {
        const match = String(value || '').match(/^(\d{4})-(\d{2})-(\d{2})$/);
        if (!match) return null;
        const year = Number(match[1]);
        const month = Number(match[2]);
        const day = Number(match[3]);
        const date = new Date(Date.UTC(year, month - 1, day));
        if (date.getUTCFullYear() !== year || date.getUTCMonth() !== month - 1 || date.getUTCDate() !== day) return null;
        return { year, month, day };
    }

    function toIso(value) {
        const match = String(value || '').trim().match(/^(\d{2})\/(\d{2})\/(\d{4})$/);
        if (!match) return null;
        const day = Number(match[1]);
        const month = Number(match[2]);
        const year = Number(match[3]);
        const iso = `${year.toString().padStart(4, '0')}-${month.toString().padStart(2, '0')}-${day.toString().padStart(2, '0')}`;
        return partsFromIso(iso) ? iso : null;
    }

    function formatDisplay(value) {
        const parts = partsFromIso(value);
        return parts ? `${String(parts.day).padStart(2, '0')}/${String(parts.month).padStart(2, '0')}/${parts.year}` : '';
    }

    global.PosDate = { formatDisplay, toIso, isValidDisplay: (value) => toIso(value) !== null };
})(typeof window !== 'undefined' ? window : globalThis);
