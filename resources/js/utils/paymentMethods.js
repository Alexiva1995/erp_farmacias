export const translateMethod = (method) => {
  const options = {
    cash_cop: "Efectivo",
    bank_transfer: "Transferencia",
    mobile_payment: "Pago Móvil",
    bank_transfer_bs: "Transferencia",
    debit_card: "T. Debito",
    credit_card: "T. Crédito",
    cash_bs: "Efectivo",
    cash_usd: "Efectivo",
    cash: "Efectivo",
    binance: "Binance",
    paypal: "PayPal",
    zelle: "Zelle",
    credit: "Crédito",
    balance: "Saldo",
    card: "Tarjeta",
  };

  return options[method] || method;
};
