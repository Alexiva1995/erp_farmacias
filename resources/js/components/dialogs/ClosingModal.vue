<script setup>
import { defineProps, defineEmits, computed, nextTick } from "vue";
import { BASE64_LOGO_DATA } from "@/constants/logo.js";
import TicketHeader from "@/components/TicketHeader.vue";
import axios from "@/plugins/axios";
import SectionDivider from "@/components/SectionDivider.vue";
import { formatDateTime } from "@/utils/formatDateTime";
import PaymentTable from "@/components/PaymentTable.vue";
import PaymentTableTotales from "@/components/PaymentTableTotales.vue";
import { useDisplay } from "vuetify";

const { mobile } = useDisplay();

const props = defineProps({
  isDialogVisible: {
    type: Boolean,
    required: true,
  },
  cashData: {
    type: Object,
    default: () => ({}),
  },
  reference: {
    type: Array,
    default: () => [],
  },
});

const emit = defineEmits(["update:isDialogVisible", "modal-closed"]);

const dialogVisible = computed({
  get() {
    return props.isDialogVisible;
  },
  set(value) {
    emit("update:isDialogVisible", value);
  },
});

const closeModal = () => {
  emit("update:isDialogVisible", false);
  emit("modal-closed");
};

const globalTotals = computed(() => {
  const closings = props.cashData.cash_closings || [];
  const initialTotals = {
    total_usd: 0,
    total_bs: 0,
    total_cop: 0,
    total_usd_cash: 0,
    total_bs_cash: 0,
    total_cop_cash: 0,
    total_usd_binance: 0,
    total_usd_paypal: 0,
    total_usd_conversion: 0,
    total_bs_card_debito: 0,
    total_bs_card_credito: 0,
    total_bs_transfer: 0,
    total_bs_mobile: 0,
    total_cop_transfer: 0,
    total_cop_spare: 0,
    total_usd_credit: 0,
    total_usd_cash_payment_credit: 0,
    total_usd_binance_payment_credit: 0,
    total_usd_paypal_payment_credit: 0,
    total_bs_cash_payment_credit: 0,
    total_bs_card_payment_credit: 0,
    total_bs_transfer_payment_credit: 0,
    total_bs_mobile_payment_credit: 0,
    total_cop_cash_payment_credit: 0,
    total_cop_transfer_payment_credit: 0,
    total_usd_cash_delivery: 0,
    total_usd_binance_delivery: 0,
    total_usd_paypal_delivery: 0,
    total_bs_cash_delivery: 0,
    total_bs_card_delivery: 0,
    total_bs_card_debito_delivery: 0,
    total_bs_card_credito_delivery: 0,
    total_bs_transfer_delivery: 0,
    total_bs_mobile_delivery: 0,
    total_cop_cash_delivery: 0,
    total_cop_transfer_delivery: 0,
    total_usd_cash_conversion: 0,
    total_cop_conversion: 0,
  };
  return closings.reduce((acc, closing) => {
    acc.total_usd += parseFloat(closing.total_usd) || 0;
    acc.total_bs += parseFloat(closing.total_bs) || 0;
    acc.total_cop += parseFloat(closing.total_cop) || 0;
    acc.total_usd_cash_conversion += parseFloat(closing.usd_conversion) || 0;

    acc.total_usd_cash += parseFloat(closing.usd_cash) || 0;
    acc.total_usd_binance += parseFloat(closing.usd_binance) || 0;
    acc.total_usd_paypal += parseFloat(closing.usd_paypal) || 0;
    acc.total_usd_conversion += parseFloat(closing.usd_conversion) || 0;

    acc.total_bs_cash += parseFloat(closing.bs_cash) || 0;
    acc.total_bs_card_debito += parseFloat(closing.bs_card_debito) || 0;
    acc.total_bs_card_credito += parseFloat(closing.bs_card_credit) || 0;
    acc.total_bs_transfer += parseFloat(closing.bs_transfer) || 0;
    acc.total_bs_mobile += parseFloat(closing.bs_mobile) || 0;

    acc.total_cop_cash += parseFloat(closing.cop_cash) || 0;
    acc.total_cop_transfer += parseFloat(closing.cop_transfer) || 0;
    acc.total_cop_spare += parseFloat(closing.cop_spare) || 0;
    acc.total_cop_conversion += parseFloat(closing.cop_conversion) || 0;

    acc.total_usd_credit += parseFloat(closing.usd_credit) || 0;

    acc.total_usd_cash_payment_credit += parseFloat(closing.usd_cash_payment_credit) || 0;
    acc.total_usd_binance_payment_credit += parseFloat(closing.usd_binance_payment_credit) || 0;
    acc.total_usd_paypal_payment_credit += parseFloat(closing.usd_paypal_payment_credit) || 0;
    acc.total_bs_cash_payment_credit += parseFloat(closing.bs_cash_payment_credit) || 0;
    acc.total_bs_card_payment_credit += parseFloat(closing.bs_card_payment_credit) || 0;
    acc.total_bs_transfer_payment_credit += parseFloat(closing.bs_transfer_payment_credit) || 0;
    acc.total_bs_mobile_payment_credit += parseFloat(closing.bs_mobile_payment_credit) || 0;
    acc.total_cop_cash_payment_credit += parseFloat(closing.cop_cash_payment_credit) || 0;
    acc.total_cop_transfer_payment_credit += parseFloat(closing.cop_transfer_payment_credit) || 0;

    acc.total_usd_cash_delivery += (parseFloat(closing.usd_delivered) || 0) + (parseFloat(closing.usd_cash_payment_credit) || 0);
    acc.total_usd_binance_delivery += (parseFloat(closing.usd_binance) || 0) + (parseFloat(closing.usd_binance_payment_credit) || 0);
    acc.total_usd_paypal_delivery += (parseFloat(closing.usd_paypal) || 0) + (parseFloat(closing.usd_paypal_payment_credit) || 0);
    acc.total_bs_cash_delivery += (parseFloat(closing.bs_cash) || 0) + (parseFloat(closing.bs_cash_payment_credit) || 0);
    acc.total_bs_card_delivery += (parseFloat(closing.bs_card_payment_credit) || 0);
    acc.total_bs_card_debito_delivery += (parseFloat(closing.bs_card_debito) || 0);
    acc.total_bs_card_credito_delivery += (parseFloat(closing.bs_card_credit) || 0);
    acc.total_bs_transfer_delivery += (parseFloat(closing.bs_transfer) || 0) + (parseFloat(closing.bs_transfer_payment_credit) || 0);
    acc.total_bs_mobile_delivery += (parseFloat(closing.bs_mobile) || 0) + (parseFloat(closing.bs_mobile_payment_credit) || 0);
    acc.total_cop_cash_delivery += (parseFloat(closing.cop_delivered) || 0) + (parseFloat(closing.cop_cash_payment_credit) || 0);
    acc.total_cop_transfer_delivery += (parseFloat(closing.cop_transfer) || 0) + (parseFloat(closing.cop_transfer_payment_credit) || 0);

    return acc;
  }, initialTotals);
});

const usdPayments = computed(() => {
  const totals = globalTotals.value;
  const paymentsList = [
    { label: "Efectivo", amount: totals.total_usd_cash, currency: "USD" },
    { label: "Paypal", amount: totals.total_usd_paypal, currency: "USD" },
    { label: "Binance", amount: totals.total_usd_binance, currency: "USD" },
    { label: "Diferencia por cambio", amount: totals.total_usd_cash_conversion, currency: "USD" },
  ];
  return paymentsList.filter((p) => p.amount > 0);
});

const usdPaymentsTotales = computed(() => {
  const totals = globalTotals.value;
  const paymentsList = [{ label: "Total USD", amount: totals.total_usd, currency: "USD" }];
  return paymentsList.filter((p) => p.amount != 0);
});

const bsPayments = computed(() => {
  const totals = globalTotals.value;
  const paymentsList = [
    { label: "Efectivo", amount: totals.total_bs_cash, currency: "BS" },
    { label: "T. Débito", amount: totals.total_bs_card_debito, currency: "BS" },
    { label: "T. Crédito", amount: totals.total_bs_card_credito, currency: "BS" },
    { label: "Transferencia", amount: totals.total_bs_transfer, currency: "BS" },
    { label: "Pago Móvil", amount: totals.total_bs_mobile, currency: "BS" },
  ];
  return paymentsList.filter((p) => p.amount > 0);
});

const bsPaymentsTotales = computed(() => {
  const totals = globalTotals.value;
  const paymentsList = [{ label: "Total BS", amount: totals.total_bs, currency: "BS" }];
  return paymentsList.filter((p) => p.amount != 0);
});

const copPayments = computed(() => {
  const totals = globalTotals.value;
  const conversionNegative = -(parseFloat(totals.total_cop_conversion) || 0);
  const paymentsList = [
    { label: "Efectivo", amount: totals.total_cop_cash, currency: "COP" },
    { label: "Transferencia", amount: totals.total_cop_transfer, currency: "COP" },
    { label: "Sobrante", amount: totals.total_cop_spare, currency: "COP" },
    { label: "Diferencia por cambio", amount: conversionNegative, currency: "COP" },
  ];
  return paymentsList.filter((p) => p.amount != 0);
});

const copPaymentsTotales = computed(() => {
  const totals = globalTotals.value;
  const paymentsList = [{ label: "Total COP", amount: totals.total_cop, currency: "COP" }];
  return paymentsList.filter((p) => p.amount != 0);
});

const creditAmount = computed(() => {
  const totals = globalTotals.value;
  const paymentsList = [{ label: "Créditos", amount: totals.total_usd_credit, currency: "USD" }];
  return paymentsList.filter((p) => p.amount > 0);
});

const creditsTotales = computed(() => {
  const totals = parseFloat(props.cashData.total_credits || 0);
  return [{ label: "Total Créditos", amount: totals, currency: "USD" }];
});

const creditPayments = computed(() => {
  const totals = globalTotals.value;
  const paymentsList = [
    { label: "Efectivo (usd)", amount: totals.total_usd_cash_payment_credit, currency: "USD" },
    { label: "Binance", amount: totals.total_usd_binance_payment_credit, currency: "USD" },
    { label: "Paypal", amount: totals.total_usd_paypal_payment_credit, currency: "USD" },
    { label: "Efectivo (BS)", amount: totals.total_bs_cash_payment_credit, currency: "BS" },
    { label: "Tarjeta", amount: totals.total_bs_card_payment_credit, currency: "BS" },
    { label: "Transferencia", amount: totals.total_bs_transfer_payment_credit, currency: "BS" },
    { label: "Pago Móvil", amount: totals.total_bs_mobile_payment_credit, currency: "BS" },
    { label: "Efectivo (COP)", amount: totals.total_cop_cash_payment_credit, currency: "COP" },
    { label: "Transferencia (COP)", amount: totals.total_cop_transfer_payment_credit, currency: "COP" },
  ];
  return paymentsList.filter((p) => p.amount > 0);
});

const creditPaymentsTotales = computed(() => {
  const totals = parseFloat(props.cashData.total_payment_credit || 0);
  return [{ label: "Total Pagos", amount: totals, currency: "USD" }];
});

const delivery = computed(() => {
  const totals = globalTotals.value;
  const paymentsList = [
    { label: "Efectivo (usd)", amount: totals.total_usd_cash_delivery, currency: "USD" },
    { label: "Binance", amount: totals.total_usd_binance_delivery, currency: "USD" },
    { label: "Paypal", amount: totals.total_usd_paypal_delivery, currency: "USD" },
    { label: "Efectivo (BS)", amount: totals.total_bs_cash_delivery, currency: "BS" },
    { label: "Tarjeta", amount: totals.total_bs_card_delivery, currency: "BS" },
    { label: "Tarjeta Débito", amount: totals.total_bs_card_debito_delivery, currency: "BS" },
    { label: "Tarjeta Crédito", amount: totals.total_bs_card_credito_delivery, currency: "BS" },
    { label: "Transferencia", amount: totals.total_bs_transfer_delivery, currency: "BS" },
    { label: "Pago Móvil", amount: totals.total_bs_mobile_delivery, currency: "BS" },
    { label: "Efectivo (COP)", amount: totals.total_cop_cash_delivery, currency: "COP" },
    { label: "Transferencia (COP)", amount: totals.total_cop_transfer_delivery, currency: "COP" },
  ];
  return paymentsList.filter((p) => p.amount > 0);
});

const deliveryTotales = computed(() => {
  const totals = parseFloat(props.cashData.total_delivery || 0);
  return [{ label: "Total Entregas", amount: totals, currency: "USD" }];
});

const ticketStyles = `
  .tituloAzulPrint { font-family: 'Poppins', sans-serif !important; font-weight: 600 !important; font-size: 18px !important; line-height: 18px !important; color: #044C94 !important; letter-spacing: 0.9px; text-transform: uppercase; }
  .pa-2 { padding: 8px; }
  .text-center { text-align: center; }
  .text-right { text-align: right; }
  .text-start { text-align: start; }
  .text-left { text-align: left; }
  .mb-2 { margin-bottom: 8px; }
  .tbody-bordered { border: 1px solid #dfdfdff9; background-color: #f9f8f8; }
  .center-block { margin-left: auto; margin-right: auto; }
  .single-report-center { width: 50%; margin-left: auto; margin-right: auto; }
  .w-75 { width: 75% !important; }
  .w-100 { width: 100% !important; }
  .mx-auto { margin-left: auto !important; margin-right: auto !important; }
  .font-weight-bold { font-weight: 700 !important; }
  .right-align-cell { text-align: right; font-weight: bold; }
  .pdf-row-multi { width: 100%; display: block; }
  .pdf-row-multi:after { content: ""; display: table; clear: both; }
  .pdf-col-multi { float: left; width: 48%; box-sizing: border-box; padding: 0 5px; margin-right: 2%; min-height: 1px; }
`;

const downloadReport = async () => {
  try {
    await nextTick();
    const element = document.getElementById("closing-report");
    if (!element) return;
    const htmlContent = element.outerHTML;
    const fullHtml = `<html><head><meta charset="UTF-8"><style>${ticketStyles}</style></head><body>${htmlContent}</body></html>`;
    const params = { html_content: fullHtml, filename: "Resumen_Cajas_Diario" };
    const response = await axios.post("/finances/cash-closure/downloadReport", params, { responseType: "blob" });
    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement("a");
    link.href = url;
    link.setAttribute("download", "CierreDiario.pdf");
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(url);
    closeModal();
  } catch (error) {
    console.error("Error al descargar el PDF:", error);
  }
};

const groupedReferences = computed(() => {
  if (!Array.isArray(props.reference) || props.reference.length === 0) return {};
  return props.reference.reduce((acc, currentRef) => {
    const currency = currentRef.order_currency;
    const method = currentRef.method;
    if (!acc[currency]) acc[currency] = {};
    if (!acc[currency][method]) acc[currency][method] = [];
    acc[currency][method].push(currentRef);
    return acc;
  }, {});
});

const translateMethod = (methodKey) => {
  const translations = { CARD: "Tarjeta", BANK_TRANSFER: "Transferencia", BANK_TRANSFER_BS: "Transferencia", BINANCE: "Binance", PAYPAL: "PayPal", MOBILE_PAYMENT: "Pago Móvil" };
  const upperKey = String(methodKey).toUpperCase();
  return translations[upperKey] || upperKey.replace(/_/g, " ");
};
</script>

<template>
  <VDialog
    v-model="dialogVisible"
    max-width="800px"
    scrollable
    :fullscreen="mobile"
    :transition="mobile ? 'dialog-bottom-transition' : 'dialog-transition'"
  >
    <VCard class="rounded-xl border shadow-sm">
      <VCardTitle class="d-flex justify-space-between align-center px-6 py-4 border-b bg-surface">
        <div class="d-flex align-center gap-3">
          <VAvatar color="primary" variant="tonal" rounded class="rounded-lg shadow-sm">
            <VIcon icon="tabler-report-analytics" />
          </VAvatar>
          <div>
            <h3 class="text-h6 font-weight-black mb-0 uppercase leading-none">REPORTE DE CIERRE FINAL</h3>
            <span class="text-xs text-disabled font-weight-medium uppercase">Arqueo Diario N° {{ props.cashData.id }}</span>
          </div>
        </div>
        <VBtn icon="tabler-x" variant="text" size="small" color="secondary" @click="closeModal" />
      </VCardTitle>

      <VCardText class="pa-6 pt-4" style="background-color: #f8f9fa;">
        <div id="closing-report" class="bg-white pa-4 rounded-lg shadow-sm border">
          <TicketHeader :logoSrc="BASE64_LOGO_DATA" />

          <table style="inline-size: 100%;" class="mt-4 mb-4">
            <tbody>
              <tr>
                <td class="text-left font-weight-bold">
                  <div class="text-h6 font-weight-black text-primary uppercase leading-tight">CIERRE DIARIO</div>
                  <div class="text-caption text-medium-emphasis">ID OPERACIÓN: #{{ props.cashData.id }}</div>
                </td>
                <td class="text-right">
                  <div class="text-body-2 font-weight-bold">{{ formatDateTime(props.cashData.created_at, "date") }}</div>
                  <div class="text-caption text-medium-emphasis">{{ formatDateTime(props.cashData.created_at, "time") }}</div>
                </td>
              </tr>
            </tbody>
          </table>

          <div v-if="globalTotals.total_usd > 0">
            <SectionDivider :isPdf="true" text="USD" width="45%" />
            <PaymentTable :payments="usdPayments" />
            <PaymentTableTotales :payments="usdPaymentsTotales" />
          </div>
          <div v-if="globalTotals.total_bs > 0">
            <SectionDivider :isPdf="true" text="BS" width="45%" />
            <PaymentTable :payments="bsPayments" />
            <PaymentTableTotales :payments="bsPaymentsTotales" />
          </div>
          <div v-if="globalTotals.total_cop > 0">
            <SectionDivider :isPdf="true" text="COP" width="45%" />
            <PaymentTable :payments="copPayments" />
            <PaymentTableTotales :payments="copPaymentsTotales" />
          </div>
          <div v-if="creditAmount.length > 0">
            <SectionDivider :isPdf="true" text="CREDITOS" width="40%" />
            <PaymentTable :payments="creditAmount" />
            <PaymentTableTotales :payments="creditsTotales" />
          </div>
          <div v-if="creditPayments.length > 0">
            <SectionDivider :isPdf="true" text="PAGOS" width="42%" />
            <PaymentTable :payments="creditPayments" />
            <PaymentTableTotales :payments="creditPaymentsTotales" />
          </div>
          <div v-if="delivery.length > 0">
            <SectionDivider :isPdf="true" text="ENTREGA" width="40%" />
            <PaymentTable :payments="delivery" />
            <PaymentTableTotales :payments="deliveryTotales" />
          </div>

          <div v-if="props.cashData.total_sales > 0">  
            <SectionDivider :isPdf="true" text="TOTAL DE VENTA" width="38%" />
            <table class="table table-borderless table-sm w-100 mx-auto center-block">
              <tbody>
                <tr>
                  <td class="text-left font-weight-medium">USD:</td>
                  <td class="text-right">{{ props.cashData.total_usd }} USD</td>
                  <td class="text-right font-weight-bold" style="inline-size: 150px;">{{ props.cashData.total_usd }} USD</td>
                </tr>
                <tr>
                  <td class="text-left font-weight-medium">BS:</td>
                  <td class="text-right">{{ props.cashData.total_bs }} BS</td>
                  <td class="text-right font-weight-bold" style="inline-size: 150px;">{{ props.cashData.total_bs_in_usd }} USD</td>
                </tr>
                <tr>
                  <td class="text-left font-weight-medium">COP:</td>
                  <td class="text-right">{{ props.cashData.total_cop }} COP</td>
                  <td class="text-right font-weight-bold" style="inline-size: 150px;">{{ props.cashData.total_cop_in_usd }} USD</td>
                </tr>
                <tr>
                  <td colspan="2" class="text-right font-weight-black uppercase text-primary pt-4">TOTAL GENERAL</td>
                  <td class="text-right font-weight-black text-primary pt-4" style="font-size: 1.1rem; inline-size: 150px;">
                    {{ props.cashData.total_sales }} USD
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="mt-6" v-if="Object.keys(groupedReferences || {}).length > 0">
            <SectionDivider :isPdf="true" text="REFERENCIAS" width="40%" />
            <div v-for="(methods, currency) in groupedReferences" :key="currency" class="mb-4">
              <div v-for="(references, method) in methods" :key="method">
                <h4 class="text-center font-weight-bold my-2 text-subtitle-2 uppercase text-medium-emphasis">
                  {{ translateMethod(method) }} ({{ references[0].currency }})
                </h4>
                <table class="table table-borderless table-sm w-75 mx-auto center-block">
                  <tbody>
                    <tr v-for="(ref, refIndex) in references" :key="refIndex" class="border-b">
                      <td class="text-left py-1 text-caption">Ref: {{ ref.reference }}</td>
                      <td class="text-right py-1 font-weight-bold text-caption">{{ ref.amount }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </VCardText>
      
      <VDivider />
      
      <VCardActions class="pa-4 bg-white d-flex justify-center gap-3 px-6">
        <VBtn variant="tonal" color="secondary" @click="closeModal" class="flex-grow-1 font-weight-medium" size="large">Cerrar</VBtn>
        <VBtn variant="flat" color="primary" @click="downloadReport" prepend-icon="tabler-download" class="flex-grow-1 font-weight-medium" size="large">Descargar PDF</VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.tituloAzulPrint {
  color: #044c94 !important;
  font-family: Poppins, sans-serif !important;
  font-size: 1.125rem !important;
  font-weight: 600 !important;
  letter-spacing: 0.05em;
  line-height: 1.2 !important;
  text-transform: uppercase;
}

.table {
  border-collapse: collapse;
  inline-size: 100%;
}

.table-sm td {
  padding-block: 0.25rem;
  padding-inline: 0.5rem;
}

.border-b {
  border-block-end: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}
</style>
