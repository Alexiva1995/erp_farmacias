<script setup>
import { defineProps, defineEmits, computed, nextTick } from "vue";
import { BASE64_LOGO_DATA } from "@/constants/logo.js";
import TicketHeader from "@/components/TicketHeader.vue";
import axios from "@/plugins/axios";
import SectionDivider from "@/components/SectionDivider.vue";
import { formatDateTime } from "@/utils/formatDateTime";
import PaymentTable from "@/components/PaymentTable.vue";
import PaymentTableTotales from "@/components/PaymentTableTotales.vue";

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

const emit = defineEmits(["update:isDialogVisible"]);

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
  const closings = props.cashData.cash_closings;
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
    total_bs_card: 0,
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
    acc.total_bs_card += parseFloat(closing.bs_card) || 0;
    acc.total_bs_transfer += parseFloat(closing.bs_transfer) || 0;
    acc.total_bs_mobile += parseFloat(closing.bs_mobile) || 0;

    acc.total_cop_cash += parseFloat(closing.cop_cash) || 0;
    acc.total_cop_transfer += parseFloat(closing.cop_transfer) || 0;
    acc.total_cop_spare += parseFloat(closing.cop_spare) || 0;
    acc.total_cop_conversion += parseFloat(closing.cop_conversion) || 0;

    acc.total_usd_credit += parseFloat(closing.usd_credit) || 0;

    acc.total_usd_cash_payment_credit +=
      parseFloat(closing.usd_cash_payment_credit) || 0;
    acc.total_usd_binance_payment_credit +=
      parseFloat(closing.usd_binance_payment_credit) || 0;
    acc.total_usd_paypal_payment_credit +=
      parseFloat(closing.usd_paypal_payment_credit) || 0;
    acc.total_bs_cash_payment_credit +=
      parseFloat(closing.bs_cash_payment_credit) || 0;
    acc.total_bs_card_payment_credit +=
      parseFloat(closing.bs_card_payment_credit) || 0;
    acc.total_bs_transfer_payment_credit +=
      parseFloat(closing.bs_transfer_payment_credit) || 0;
    acc.total_bs_mobile_payment_credit +=
      parseFloat(closing.bs_mobile_payment_credit) || 0;
    acc.total_cop_cash_payment_credit +=
      parseFloat(closing.cop_cash_payment_credit) || 0;
    acc.total_cop_transfer_payment_credit +=
      parseFloat(closing.cop_transfer_payment_credit) || 0;

    acc.total_usd_cash_delivery +=
      (parseFloat(closing.usd_delivered) || 0) +
      (parseFloat(closing.usd_cash_payment_credit) || 0);
    acc.total_usd_binance_delivery +=
      (parseFloat(closing.usd_binance) || 0) +
      (parseFloat(closing.usd_binance_payment_credit) || 0);
    acc.total_usd_paypal_delivery +=
      (parseFloat(closing.usd_paypal) || 0) +
      (parseFloat(closing.usd_paypal_payment_credit) || 0);
    acc.total_bs_cash_delivery +=
      (parseFloat(closing.bs_cash) || 0) +
      (parseFloat(closing.bs_cash_payment_credit) || 0);
    acc.total_bs_card_delivery +=
      (parseFloat(closing.bs_card) || 0) +
      (parseFloat(closing.bs_card_payment_credit) || 0);
    acc.total_bs_transfer_delivery +=
      (parseFloat(closing.bs_transfer) || 0) +
      (parseFloat(closing.bs_transfer_payment_credit) || 0);
    acc.total_bs_mobile_delivery +=
      (parseFloat(closing.bs_mobile) || 0) +
      (parseFloat(closing.bs_mobile_payment_credit) || 0);
    acc.total_cop_cash_delivery +=
      (parseFloat(closing.cop_delivered) || 0) +
      (parseFloat(closing.cop_cash_payment_credit) || 0);
    acc.total_cop_transfer_delivery +=
      (parseFloat(closing.cop_transfer) || 0) +
      (parseFloat(closing.cop_transfer_payment_credit) || 0);

    return acc;
  }, initialTotals);
});

const usdPayments = computed(() => {
  const totals = globalTotals.value;
  const paymentsList = [
    { label: "Efectivo", amount: totals.total_usd_cash, currency: "USD" },
    { label: "Paypal", amount: totals.total_usd_paypal, currency: "USD" },
    { label: "Binance", amount: totals.total_usd_binance, currency: "USD" },
    {
      label: "Diferencia por cambio",
      amount: totals.total_usd_cash_conversion,
      currency: "USD",
    },
  ];
  return paymentsList.filter((p) => p.amount > 0);
});

const usdPaymentsTotales = computed(() => {
  const totals = globalTotals.value;
  const paymentsList = [
    { label: "Total USD", amount: totals.total_usd, currency: "USD" },
  ];
  return paymentsList.filter((p) => p.amount != 0);
});

const bsPayments = computed(() => {
  const totals = globalTotals.value;
  const paymentsList = [
    { label: "Efectivo", amount: totals.total_bs_cash, currency: "BS" },
    { label: "Tarjeta", amount: totals.total_bs_card, currency: "BS" },
    {
      label: "Transferencia",
      amount: totals.total_bs_transfer,
      currency: "BS",
    },
    { label: "Pago Móvil", amount: totals.total_bs_mobile, currency: "BS" },
  ];
  return paymentsList.filter((p) => p.amount > 0);
});

const bsPaymentsTotales = computed(() => {
  const totals = globalTotals.value;
  const paymentsList = [
    { label: "Total BS", amount: totals.total_bs, currency: "BS" },
  ];
  return paymentsList.filter((p) => p.amount != 0);
});

const copPayments = computed(() => {
  const totals = globalTotals.value;
  const conversionNegative = -(parseFloat(totals.total_cop_conversion) || 0);
  const paymentsList = [
    { label: "Efectivo", amount: totals.total_cop_cash, currency: "COP" },
    {
      label: "Transferencia",
      amount: totals.total_cop_transfer,
      currency: "COP",
    },
    { label: "Sobrante", amount: totals.total_cop_spare, currency: "COP" },
    {
      label: "Diferencia por cambio",
      amount: conversionNegative,
      currency: "COP",
    },
  ];
  return paymentsList.filter((p) => p.amount != 0);
});

const copPaymentsTotales = computed(() => {
  const totals = globalTotals.value;
  console.log(totals);
  const paymentsList = [
    {
      label: "Total COP",
      amount: totals.total_cop,
      currency: "COP",
    },
  ];
  return paymentsList.filter((p) => p.amount != 0);
});

const creditAmount = computed(() => {
  const totals = globalTotals.value;
  const paymentsList = [
    { label: "Créditos", amount: totals.total_usd_credit, currency: "USD" },
  ];
  return paymentsList.filter((p) => p.amount > 0);
});

const creditPayments = computed(() => {
  const totals = globalTotals.value;
  const paymentsList = [
    {
      label: "Efectivo (usd)",
      amount: totals.total_usd_cash_payment_credit,
      currency: "USD",
    },
    {
      label: "Binance",
      amount: totals.total_usd_binance_payment_credit,
      currency: "USD",
    },
    {
      label: "Paypal",
      amount: totals.total_usd_paypal_payment_credit,
      currency: "USD",
    },
    {
      label: "Efectivo (BS)",
      amount: totals.total_bs_cash_payment_credit,
      currency: "BS",
    },
    {
      label: "Tarjeta",
      amount: totals.total_bs_card_payment_credit,
      currency: "BS",
    },
    {
      label: "Transferencia",
      amount: totals.total_bs_transfer_payment_credit,
      currency: "BS",
    },
    {
      label: "Pago Móvil",
      amount: totals.total_bs_mobile_payment_credit,
      currency: "BS",
    },
    {
      label: "Efectivo (COP)",
      amount: totals.total_cop_cash_payment_credit,
      currency: "COP",
    },
    {
      label: "Transferencia (COP)",
      amount: totals.total_cop_transfer_payment_credit,
      currency: "COP",
    },
  ];
  return paymentsList.filter((p) => p.amount > 0);
});

const delivery = computed(() => {
  const totals = globalTotals.value;
  const paymentsList = [
    {
      label: "Efectivo (usd)",
      amount: totals.total_usd_cash_delivery,
      currency: "USD",
    },
    {
      label: "Binance",
      amount: totals.total_usd_binance_delivery,
      currency: "USD",
    },
    {
      label: "Paypal",
      amount: totals.total_usd_paypal_delivery,
      currency: "USD",
    },
    {
      label: "Efectivo (BS)",
      amount: totals.total_bs_cash_delivery,
      currency: "BS",
    },
    { label: "Tarjeta", amount: totals.total_bs_card_delivery, currency: "BS" },
    {
      label: "Transferencia",
      amount: totals.total_bs_transfer_delivery,
      currency: "BS",
    },
    {
      label: "Pago Móvil",
      amount: totals.total_bs_mobile_delivery,
      currency: "BS",
    },
    {
      label: "Efectivo (COP)",
      amount: totals.total_cop_cash_delivery,
      currency: "COP",
    },
    {
      label: "Transferencia (COP)",
      amount: totals.total_cop_transfer_delivery,
      currency: "COP",
    },
  ];
  return paymentsList.filter((p) => p.amount > 0);
});

const downloadReport = async () => {
  try {
    await nextTick();
    const element = document.getElementById("closing-report");
    if (!element) {
      console.error("No se encontró el contenido del reporte.");
      return;
    }
    const htmlContent = element.outerHTML;

    const fullHtml = `
      <!DOCTYPE html>
      <html>
      <head>
        <meta charset="UTF-8">
        <style>${ticketStyles}</style>
      </head>
      <body>
        ${htmlContent}
      </body>
      </html>
    `;

    const params = {
      html_content: fullHtml,
      filename: "Resumen_Cajas_Diario",
    };

    const response = await axios.post(
      "/finances/cash-closure/downloadReport",
      params,
      {
        responseType: "blob",
      }
    );
    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement("a");
    let filename = "CierreDiario.pdf";
    link.href = url;
    link.setAttribute("download", filename);
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(url);
    closeModal();
  } catch (error) {
    console.error("Error al descargar el PDF:", error);
  }
};

const printReport = async () => {
  try {
    await nextTick();
    const element = document.getElementById("closing-report");
    if (!element) {
      console.error("No se encontró el contenido del reporte.");
      return;
    }
    const htmlContent = element.outerHTML;

    const params = {
      html_content: `<style>${ticketStyles}</style>${htmlContent}`,
      filename: "Resumen_Cajas_Diario",
    };

    const response = await axios.post(
      "/finances/cash-closure/PrintReport",
      params,
      {
        responseType: "blob",
      }
    );
    const url = window.URL.createObjectURL(
      new Blob([response.data], { type: "application/pdf" })
    );
    const printWindow = window.open(url, "_blank");
    if (printWindow) {
      printWindow.focus();
    }
    window.URL.revokeObjectURL(url);
    closeModal();
  } catch (error) {
    console.error("Error al visualizar el PDF:", error);
  } finally {
    setTimeout(() => {}, 500);
  }
};

const ticketStyles = `
.tituloAzulPrint {
   font-family: 'Poppins'!important;
        font-weight: 600!important;
        font-size: 18px !important;
        line-height: 18px !important;
        color: #044C94!important;
        letter-spacing: 0.9px;
}
.pa-2 { padding: 8px; }
.text-center { text-align: center; }
.text-right { text-align: right; }
.text-start{ text-align: start; }
.text-left { text-align: left; }
.mb-2 { margin-bottom: 8px; }
.tbody-bordered { border: 1px solid #dfdfdff9; background-color: #f9f8f8; }
.center-block { margin-left: auto; margin-right: auto; }
.single-report-center { width: 50%; margin-left: auto; margin-right: auto; }
.w-75 {width: 75% !important;}
.w-100 {width: 100% !important;}
.mx-auto { margin-left: auto !important; margin-right: auto !important; }
.font-weight-bold{font-weight: 700 !important;}
.right-align-cell {
  text-align: right; 
  font-weight: bold;
}
.pdf-row-multi {
width: 100%;
display: block; 
}
.pdf-row-multi:after {
content: "";
display: table;
 clear: both;
}
.pdf-col-multi {
float: left;
width: 48%; 
box-sizing: border-box;
 padding: 0 5px;
margin-right: 2%;
min-height: 1px;
}
`;

const groupedReferences = computed(() => {
  if (!Array.isArray(props.reference) || props.reference.length === 0) {
    return {};
  }

  return props.reference.reduce((acc, currentRef) => {
    const currency = currentRef.order_currency;
    const method = currentRef.method;

    if (!acc[currency]) {
      acc[currency] = {};
    }

    if (!acc[currency][method]) {
      acc[currency][method] = [];
    }

    acc[currency][method].push(currentRef);
    return acc;
  }, {});
});


const translateMethod = (methodKey) => {
  const translations = {
    CARD: "Tarjeta",
    BANK_TRANSFER: "Transferencia",
    BANK_TRANSFER_BS: "Transferencia",
    BINANCE: "Binance",
    PAYPAL: "PayPal",
    MOBILE_PAYMENT: "Pago Móvil",
  };
  const upperKey = methodKey.toUpperCase();
  return translations[upperKey] || upperKey.replace(/_/g, " ");
};
</script>
<template>
  <VDialog v-model="dialogVisible" max-width="700px">
    <VCard>
      <VCardTitle class="d-flex align-center">
        <span class="headline"></span>
        <VSpacer />
        <VBtn icon variant="text" @click="closeModal">
          <VIcon>tabler-x</VIcon>
        </VBtn>
      </VCardTitle>
      <VCardText>
        <div id="closing-report">
          <TicketHeader :logoSrc="BASE64_LOGO_DATA" />

           <table style="width: 100%;" class='mt-2'>
                <tbody>
                  <tr>
                    <td class="text-left font-weight-bold tituloAzulPrint">
                      <span>Cierre Diario N. {{ props.cashData.id }}</span>
                    </td>
                    <td class="text-right font-weight-bold">
                      <span>Fecha: {{ formatDateTime(props.cashData.created_at, "date") }}
                {{ formatDateTime(props.cashData.created_at, "time") }}</span>
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
          </div>

          <div v-if="creditPayments.length > 0">
            <SectionDivider :isPdf="true" text="PAGOS" width="42%" />
            <PaymentTable :payments="creditPayments" />
          </div>

          <div v-if="delivery.length > 0">
            <SectionDivider :isPdf="true" text="ENTREGA" width="40%" />
            <PaymentTable :payments="delivery" />
          </div>

        <div v-if="props.cashData.total_sales > 0">  
          <SectionDivider :isPdf="true" text="TOTAL DE VENTA" width="38%" />
           <div >
              <table
                class="table table-borderless table-sm w-100 mx-auto center-block"
              >
                <tbody>
                  <tr>
                    <td class="text-left"><span>USD:</span></td>
                    <td class="text-right">
                      <span>{{ props.cashData.total_usd }}</span>
                    </td>
                    <td class="text-right" style="width:150px;">
                      <span>{{ props.cashData.total_usd }}</span>
                    </td>
                  </tr>
                  <tr>
                    <td class="text-left"><span>BS:</span></td>
                    <td class="text-right">
                      <span>{{ props.cashData.total_bs }}</span>
                    </td>
                    <td class="text-right" style="width:150px;">
                      <span>{{ props.cashData.total_bs_in_usd }}</span>
                    </td>
                  </tr>
                  <tr>
                    <td class="text-left"><span>COP:</span></td>
                    <td class="text-right" style="width:150px;">
                      <span>{{ props.cashData.total_cop }}</span>
                    </td>
                    <td class="text-right">
                      <span>{{ props.cashData.total_cop_in_usd }}</span>
                    </td>
                  </tr>
                  <tr>
                    <td class="text-start"><span></span></td>
                    <td class="text-right fw-bold"><span>TOTAL</span></td>
                    <td class="text-right fw-bold" style="width:150px;">
                      <span>{{ props.cashData.total_sales }}</span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          
          <div class="mt-3" v-if="Object.keys(groupedReferences || {}).length > 0">
          <SectionDivider
            :isPdf="true"
            text="REFERENCIAS"
            width="40%"
            class="mx-auto center-block"
          />

          <div
            v-for="(methods, currency) in groupedReferences"
            :key="currency"
            class="mb-4"
          >
            <div v-for="(references, method) in methods" :key="method">
              <h4
                class="text-center font-weight-bold my-2"
                style="font-size: 1rem"
              >
                {{ translateMethod(method) }} ({{ references[0].currency }})
              </h4>

              <table
                class="table table-borderless table-sm w-75 mx-auto center-block"
              >
                <tbody>
                  <tr v-for="(ref, refIndex) in references" :key="refIndex">
                    <td class="text-left">
                      <span>Ref: {{ ref.reference }}</span>
                    </td>
                    <td class="text-right">
                      <span>{{ ref.amount }}</span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        </div>
      </VCardText>
      <VCardActions class="p-2 d-flex justify-space-between w-100 mx-auto">
        <VBtn
          color="secondary"
          variant="outlined"
          @click="printReport"
          class="w-50"
        >
          Imprimir
        </VBtn>
        <VBtn
          color="primary"
          variant="flat"
          @click="downloadReport"
          class="w-50"
        >
          Descargar
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
<style scoped>
.tituloAzulPrint{
   font-family: 'Poppins'!important;
        font-weight: 600!important;
        font-size: 18px !important;
        line-height: 18px !important;
        color: #044C94!important;
        letter-spacing: 0.9px;
}
</style>
