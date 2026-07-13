function formatMoney(amount) {

    return Number(amount).toLocaleString("th-TH", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

}
