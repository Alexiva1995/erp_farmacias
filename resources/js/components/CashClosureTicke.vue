<script setup>
import PaymentTable from "@/components/PaymentTable.vue";
import SectionDivider from "@/components/SectionDivider.vue";
import TicketHeader from "@/components/TicketHeader.vue";
import { BASE64_LOGO_DATA } from "@/constants/logo.js";
import { formatDateTime } from "@/utils/formatDateTime";
import { computed, defineProps } from "vue";

const props = defineProps({
  cashData: { type: Object, required: true },
  isPdf: { type: Boolean, default: false },
});

const logoSrc = computed(() => BASE64_LOGO_DATA);

const getValue = (key) => parseFloat(props.cashData[key] || 0);

const usdPayments = computed(() => [
  { label: "Efectivo", amount: getValue("usd_cash"), currency: "USD" },
  { label: "Binance", amount: getValue("usd_binance"), currency: "USD" },
  { label: "Paypal", amount: getValue("usd_paypal"), currency: "USD" },
  { label: "Conversión de COP", amount: getValue("usd_conversion"), currency: "USD",},
]);

const bsPayments = computed(() => [
  { label: "Efectivo", amount: getValue("bs_cash"), currency: "BS" },
  { label: "T. Débito", amount: getValue("bs_card_debito"), currency: "BS" },
  { label: "T. Crédito", amount: getValue("bs_card_credit"), currency: "BS" },
  { label: "Transferencia", amount: getValue("bs_transfer"), currency: "BS" },
  { label: "Pago Móvil", amount: getValue("bs_mobile"), currency: "BS" },
]);

const copPayments = computed(() => [
  { label: "Efectivo", amount: getValue("cop_cash"), currency: "COP" },
  { label: "Transferencia", amount: getValue("cop_transfer"), currency: "COP" },
  { label: "Sobrante", amount: getValue("cop_spare"), currency: "COP" },
  {
    label: "Conversión de USD",
    amount: -getValue("cop_conversion"),
    currency: "COP",
  },
]);

const creditAmount = computed(() => [
  { label: "Créditos", amount: getValue("usd_credit"), currency: "USD" },
]);

const creditPayments = computed(() => [
  {
    label: "Efectivo (USD)",
    amount: getValue("usd_cash_payment_credit"),
    currency: "USD",
  },
  {
    label: "Binance (USD)",
    amount: getValue("usd_binance_payment_credit"),
    currency: "USD",
  },
  {
    label: "Paypal (USD)",
    amount: getValue("usd_paypal_payment_credit"),
    currency: "USD",
  },
  {
    label: "Efectivo (Bs)",
    amount: getValue("bs_cash_payment_credit"),
    currency: "BS",
  },
  {
    label: "Tarjeta (Bs)",
    amount: getValue("bs_card_payment_credit"),
    currency: "BS",
  },
  {
    label: "Transferencia (Bs)",
    amount: getValue("bs_transfer_payment_credit"),
    currency: "BS",
  },
  {
    label: "Pago Móvil (Bs)",
    amount: getValue("bs_mobile_payment_credit"),
    currency: "BS",
  },
  {
    label: "Efectivo (COP)",
    amount: getValue("cop_cash_payment_credit"),
    currency: "COP",
  },
  {
    label: "Transferencia (COP)",
    amount: getValue("cop_transfer_payment_credit"),
    currency: "COP",
  },
]);

const allReferences = computed(() => {
  const references = {
    BINANCE: [],
    PAYPAL: [],
    TARJETA: [],
    "TARJETA DEBITO": [],
    "TARJETA CREDITO": [],
    TRANSFERENCIA: [],
    "PAGO MOVIL": [],
    TRANSFERENCIACOP: [],
  };

  if (props.cashData.orders && Array.isArray(props.cashData.orders)) {
    props.cashData.orders.forEach((order) => {
      if (order.payment_methods && Array.isArray(order.payment_methods)) {
        order.payment_methods.forEach((payment) => {
          if (payment.reference) {
            const method = payment.method ? payment.method.toUpperCase() : "";
            const currency = payment.currency
              ? payment.currency.toUpperCase()
              : "";
            const referenceData = {
              referencia: payment.reference,
              monto: parseFloat(payment.amount),
              currency: currency,
            };
              console.log(method);
            if (method === "BINANCE" && currency === "USD") {
              references.BINANCE.push(referenceData);
            } else if (method === "PAYPAL" && currency === "USD") {
              references.PAYPAL.push(referenceData);
            } else if (method === "CARD" && currency === "BS") {
              references.TARJETA.push(referenceData);
            }  else if (method === "DEBIT_CARD" && currency === "BS") {
              references["TARJETA DEBITO"].push(referenceData);
            } else if (method === "CREDIT_CARD" && currency === "BS") {
              references["TARJETA CREDITO"].push(referenceData);
            } else if (method === "BANK_TRANSFER" && currency === "BS") {
              references.TRANSFERENCIA.push(referenceData);
            } else if (method === "MOBILE_PAYMENT" && currency === "BS") {
              references["PAGO MOVIL"].push(referenceData);
            } else if (method === "ZELLE" && currency === "USD") {
              references.ZELLE.push(referenceData);
            } else if (method === "BANK_TRANSFER" && currency === "COP") {
              references.TRANSFERENCIACOP.push(referenceData);
            }
          }
        });
      }
    });
  }
  return references;
});

const binanceReferences = computed(() => allReferences.value.BINANCE);
const paypalReferences = computed(() => allReferences.value.PAYPAL);
const tarjetaReferencesBs = computed(() => allReferences.value.TARJETA);
const tarjetaDebitoReferencesBs = computed(() => allReferences.value["TARJETA DEBITO"]);
const tarjetaCreditoReferencesBs = computed(() => allReferences.value["TARJETA CREDITO"]);
const transferenciaReferencesBs = computed(
  () => allReferences.value.TRANSFERENCIA
);
const pagoMovilReferencesBs = computed(() => allReferences.value["PAGO MOVIL"]);
const tarjetaReferencesCop = computed(
  () => allReferences.value["TRANSFERENCIACOP"]
);

const getValueDelivery = (key1, key2) => {
  const value1 = parseFloat(props.cashData[key1] || 0);
  const value2 = parseFloat(props.cashData[key2] || 0);
  return value1 + value2;
};

const delivery = computed(() => [
  {
    label: "Efectivo (USD)",
    amount: getValueDelivery("usd_delivered", "usd_cash_payment_credit"),
    currency: "USD",
  },
  {
    label: "Binance (USD)",
    amount: getValueDelivery("usd_binance", "usd_binance_payment_credit"),
    currency: "USD",
  },
  {
    label: "Paypal (USD)",
    amount: getValueDelivery("usd_paypal", "usd_paypal_payment_credit"),
    currency: "USD",
  },
  {
    label: "Efectivo (Bs)",
    amount: getValueDelivery("bs_cash", "bs_cash_payment_credit"),
    currency: "BS",
  },
  {
    label: "Tarjeta Débito(Bs)",
    amount: getValueDelivery("bs_card_debito"),
    currency: "BS",
  },
  {
    label: "Tarjeta Crédito(Bs)",
    amount: getValueDelivery("bs_card_credit"),
    currency: "BS",
  },
  {
    label: "Tarjeta (Bs)",
    amount: getValueDelivery("bs_card_payment_credit"),
    currency: "BS",
  },
  {
    label: "Transferencia (Bs)",
    amount: getValueDelivery("bs_transfer", "bs_transfer_payment_credit"),
    currency: "BS",
  },
  {
    label: "Pago Móvil (Bs)",
    amount: getValueDelivery("bs_mobile", "bs_mobile_payment_credit"),
    currency: "BS",
  },
  {
    label: "Efectivo (COP)",
    amount: getValueDelivery("cop_delivered", "cop_cash_payment_credit"),
    currency: "COP",
  },
  {
    label: "Transferencia (COP)",
    amount: getValueDelivery("cop_transfer", "cop_transfer_payment_credit"),
    currency: "COP",
  },
]);

const totalCreditPayments = computed(() => {
  return creditPayments.value.reduce((sum, payment) => sum + payment.amount, 0);
});

const totalDelivery = computed(() => {
  return delivery.value.reduce((sum, item) => sum + item.amount, 0);
});

const totalEfectivoUsd = computed(() => getValueDelivery("usd_delivered", "usd_cash_payment_credit"));
const totalEfectivoBs = computed(() => getValueDelivery("bs_cash", "bs_cash_payment_credit"));
const totalEfectivoCop = computed(() => getValueDelivery("cop_delivered", "cop_cash_payment_credit"));


const hasAnyReference = computed(() => {
  return (
    binanceReferences.value.length > 0 ||
    paypalReferences.value.length > 0 ||
    tarjetaReferencesBs.value.length > 0 ||
    tarjetaDebitoReferencesBs.value.length > 0 ||
    tarjetaCreditoReferencesBs.value.length > 0 ||
    transferenciaReferencesBs.value.length > 0 ||
    pagoMovilReferencesBs.value.length > 0 ||
    tarjetaReferencesCop.value.length > 0
  );
});
</script>
<template>
  <div style=" font-family: monospace; font-size: 14px;inline-size: 100%; line-height: 1.4;">
    <VCard variant="outlined" class="pa-4 text-start ticket-bold" style="border: 2px solid #000; background: #fff;">
      <TicketHeader :logoSrc="logoSrc" />

      <table style="inline-size: 100%; margin-block: 15px; margin-inline: 0;">
        <tbody>
          <tr>
            <td style=" font-size: 15px; font-weight: bold;text-align: start;">
              <span>Cierre de caja N°: {{ props.cashData.id }}</span>
            </td>
            <td style="text-align: end;">
              <span>{{ formatDateTime(props.cashData.closing_date, "date") }}</span>
            </td>
          </tr>
          <tr>
            <td colspan="2">
              <hr style="border-block-start: 2px solid #000; margin-block: 8px; margin-inline: 0;" />
            </td>
          </tr>
          <tr>
            <td style="text-align: start;" colspan="2">
              <span style=" font-size: 16px;font-weight: bold;">Cajero: {{ props.cashData.seller?.username }}</span>
            </td>
          </tr>
        </tbody>
      </table>

      <div v-if="props.cashData.total_usd > 0">
        <SectionDivider :isPdf="props.isPdf" text="INGRESO USD" width="55%" />
        <PaymentTable :payments="usdPayments" />
      </div>
      <div v-if="props.cashData.total_bs > 0">
        <SectionDivider :isPdf="props.isPdf" text="INGRESO BS" width="50%" />
        <PaymentTable :payments="bsPayments" />
      </div>
      <div v-if="props.cashData.total_cop > 0">
        <SectionDivider :isPdf="props.isPdf" text="INGRESO COP" width="55%" />
        <PaymentTable :payments="copPayments" />
      </div>
      <div v-if="props.cashData.usd_credit > 0">
        <SectionDivider :isPdf="props.isPdf" text="CRÉDITOS" width="45%" />
        <PaymentTable :payments="creditAmount" />
      </div>
      <div v-if="totalCreditPayments > 0">
        <SectionDivider :isPdf="props.isPdf" text="PAGOS A CRÉDITO" width="65%" />
        <PaymentTable :payments="creditPayments" />
      </div>
      <div>
        <SectionDivider :isPdf="props.isPdf" text="DETALLE ENTREGA" width="60%" />
        <PaymentTable :payments="delivery" />
      </div>
      
      <!-- RESUMEN NETO A ENTREGAR (SÓLO EFECTIVO) -->
      <div style=" padding: 10px; border: 2px solid #000; margin-block: 20px;">
        <div style=" font-size: 15px; font-weight: bold; margin-block-end: 5px;text-align: center;">
          TOTAL EFECTIVO A ENTREGAR
        </div>
        <hr style="border-block-start: 1px dashed #000; margin-block-end: 5px;"/>
        <table style=" font-size: 15px; font-weight: bold;inline-size: 100%;">
          <tr>
            <td style="text-align: start;">USD:</td>
            <td style="text-align: end;">{{ formatCurrency(totalEfectivoUsd, 'USD') }}</td>
          </tr>
          <tr>
            <td style="text-align: start;">BS:</td>
            <td style="text-align: end;">{{ formatCurrency(totalEfectivoBs, 'BS') }}</td>
          </tr>
           <tr>
            <td style="text-align: start;">COP:</td>
            <td style="text-align: end;">{{ formatCurrency(totalEfectivoCop, 'COP') }}</td>
          </tr>
        </table>
      </div>

      <div v-if="hasAnyReference">
        <SectionDivider :isPdf="props.isPdf" text="REFERENCIAS MÓVILES" width="70%" />
        <ReferenceTable title="BINANCE (USD)" :references="binanceReferences" />
        <ReferenceTable title="PAYPAL (USD)" :references="paypalReferences" />
        <ReferenceTable title="TARJETA (Bs)" :references="tarjetaReferencesBs" />
        <ReferenceTable title="TARJETA DEBITO (Bs)" :references="tarjetaDebitoReferencesBs" />
        <ReferenceTable title="TARJETA CREDITO (Bs)" :references="tarjetaCreditoReferencesBs" />
        <ReferenceTable
          title="TRANSFERENCIA (Bs)"
          :references="transferenciaReferencesBs"
        />
        <ReferenceTable
          title="PAGO MOVIL (Bs)"
          :references="pagoMovilReferencesBs"
        />
        <ReferenceTable
          title="TRANSFERENCIA (COP)"
          :references="tarjetaReferencesCop"
        />
      </div>

      <!-- SECCIÓN DE FIRMAS -->
      <div style="margin-block-start: 50px; text-align: center;">
         <hr style="border-block-start: 1px solid #000; inline-size: 80%; margin-block: 0; margin-inline: auto;" />
         <div style="font-weight: bold; margin-block: 5px 30px;">Firma del Cajero</div>

         <hr style="border-block-start: 1px solid #000; inline-size: 80%; margin-block: 0; margin-inline: auto;" />
         <div style="font-weight: bold; margin-block-start: 5px;">Firma del Supervisor</div>
      </div>
      <div style=" font-size: 12px; margin-block-start: 20px;text-align: center;">
        *** FIN DEL REPORTE ***
      </div>
    </VCard>
  </div>
</template>
