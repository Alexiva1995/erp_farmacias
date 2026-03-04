<script setup>
import { BASE64_LOGO_DATA } from "@/constants/logo.js";
import axios from "@/plugins/axios";
import { formatCurrency } from "@/utils/currencyFormatter";
import { formatDateTime } from "@/utils/formatDateTime";
import { computed, defineEmits, defineProps, nextTick } from "vue";

const props = defineProps({
  isDialogVisible: {
    type: Boolean,
    required: true,
  },
  cashData: {
    type: Object,
    default: () => ({}),
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

const ticketStyles = `
.pa-2 { padding: 8px; }
.text-center { text-align: center; }
.text-right { text-align: right; }
.text-left { text-align: left; }
.mb-2 { margin-bottom: 8px; }
.tbody-bordered { border: 1px solid #dfdfdff9; background-color: #f9f8f8; }
.center-block { margin-left: auto; margin-right: auto; }
.single-report-center { width: 50%; margin-left: auto; margin-right: auto; }
.w-75 {width: 75% !important;}
.w-100 {width: 100% !important;}
.mx-auto { margin-left: auto !important; margin-right: auto !important; }

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


const downloadReport = async () => {
  try {
    await nextTick();
    const element = document.getElementById("delivery-report");
    if (!element) {
      console.error("No se encontró el contenido del reporte.");
      return;
    }
    const htmlContent = element.outerHTML;
    const params = {
      html_content: `<style>${ticketStyles}</style>${htmlContent}`,
      filename: "Entregas_Diario",
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
    let filename = "EntregasDiario.pdf";
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
    const element = document.getElementById("delivery-report");
    if (!element) {
      console.error("No se encontró el contenido del reporte.");
      return;
    }
    const htmlContent = element.outerHTML;

    const params = {
      html_content: `<style>${ticketStyles}</style>${htmlContent}`,
      filename: "Entregas_Diario",
    };

    const response = await axios.post(
      "/finances/cash-closure/PrintReport",
      params,
      {
        responseType: "blob",
      }
    );
    const url = window.URL.createObjectURL(new Blob([response.data], { type: 'application/pdf' }));
    const printWindow = window.open(url, '_blank');
    if (printWindow) {
            printWindow.focus();
        }
    window.URL.revokeObjectURL(url); 
     closeModal();
  } catch (error) {
    console.error("Error al visualizar el PDF:", error);
  }
};


const groupedCardTotals = computed(() => {
  const closings = props.cashData.cash_closings;

  if (!closings || !Array.isArray(closings)) {
    return {};
  }

  return closings.reduce((acc, closing) => {
    const sellerId = closing.seller_id;
    const sellerName = closing.seller?.username || `Vendedor ${sellerId}`;
    const bsCarDebitoAmount = parseFloat(closing.bs_card_debito) || 0;
    const bsCarCreditoAmount = parseFloat(closing.bs_card_credit) || 0;
    const bsTransferAmount = parseFloat(closing.bs_transfer) || 0;
    const bsMobileAmount = parseFloat(closing.bs_mobile) || 0;
    const copTransferAmount = parseFloat(closing.cop_transfer) || 0;
    const usdTransferAmount = parseFloat(closing.usd_transfer) || 0;
    const usdPaypalAmount = parseFloat(closing.usd_paypal) || 0;
    const usdBinanceAmount = parseFloat(closing.usd_binance) || 0;
    const usdCreditAmount = parseFloat(closing.usd_credit) || 0;

    const usdCashAmount =  (parseFloat(closing.usd_cash) || 0) + (parseFloat(closing.usd_conversion) || 0);
    const bsCashAmount = parseFloat(closing.bs_cash) || 0;
    const copCashAmount = parseFloat(closing.cop_cash) || 0;

    const usdCashAmountCredit = parseFloat(closing.usd_cash_payment_credit) || 0;
    const usdPaypalAmountCredit = parseFloat(closing.usd_paypal_payment_credit) || 0;
    const usdBinanceAmountCredit = parseFloat(closing.usd_binance_payment_credit) || 0;

    const copTransferAmountCredit = parseFloat(closing.cop_transfer_payment_credit) || 0;
    const copCashAmountCredit = parseFloat(closing.cop_cash_payment_credit) || 0;

    const bsCardAmountCredit = parseFloat(closing.bs_card_payment_credit) || 0;
    const bsCashAmountCredit = parseFloat(closing.bs_cash_payment_credit) || 0;
    const bsTransferAmountCredit = parseFloat(closing.bs_transfer_payment_credit) || 0;
    const bsMobileAmountCredit = parseFloat(closing.bs_mobile_payment_credit) || 0;


    if (!acc[sellerId]) {
      acc[sellerId] = {
        seller_id: sellerId,
        seller_name: sellerName,
         total_bs_card_debito: 0,
         total_bs_card_credito: 0,
         total_bs_transfer: 0,
         total_bs_mobile: 0,
         total_cop_transfer: 0,
         total_usd_transfer: 0,
         total_usd_paypal: 0,
         total_usd_binance: 0,
         total_usd_credit: 0,
         total_usd_cash: 0,
         total_bs_cash: 0,
         total_cop_cash: 0,

         total_bs_card_paymentCredit: 0,
         total_bs_transfer_paymentCredit: 0,
         total_bs_mobile_paymentCredit: 0,
         total_cop_transfer_paymentCredit: 0,
         total_usd_paypal_paymentCredit: 0,
         total_usd_binance_paymentCredit: 0,
         total_usd_cash_paymentCredit: 0,
         total_bs_cash_paymentCredit: 0,
         total_cop_cash_paymentCredit: 0,
      };
    }
     acc[sellerId].total_bs_card_debito += bsCarDebitoAmount;
     acc[sellerId].total_bs_card_credito += bsCarCreditoAmount;
     acc[sellerId].total_bs_transfer += bsTransferAmount;
     acc[sellerId].total_bs_mobile += bsMobileAmount;
     acc[sellerId].total_cop_transfer += copTransferAmount;
     acc[sellerId].total_usd_transfer += usdTransferAmount;
     acc[sellerId].total_usd_paypal += usdPaypalAmount;
     acc[sellerId].total_usd_binance += usdBinanceAmount; 
     acc[sellerId].total_usd_credit += usdCreditAmount;
     acc[sellerId].total_usd_cash += usdCashAmount;
     acc[sellerId].total_bs_cash += bsCashAmount;
     acc[sellerId].total_cop_cash += copCashAmount;

    acc[sellerId].total_bs_card_paymentCredit += bsCardAmountCredit;
    acc[sellerId].total_bs_transfer_paymentCredit += bsTransferAmountCredit;
    acc[sellerId].total_bs_mobile_paymentCredit += bsMobileAmountCredit;
     acc[sellerId].total_cop_transfer_paymentCredit += copTransferAmountCredit;
     acc[sellerId].total_usd_paypal_paymentCredit += usdPaypalAmountCredit;
     acc[sellerId].total_usd_binance_paymentCredit += usdBinanceAmountCredit; 
     acc[sellerId].total_usd_cash_paymentCredit += usdCashAmountCredit;
     acc[sellerId].total_bs_cash_paymentCredit += bsCashAmountCredit;
     acc[sellerId].total_cop_cash_paymentCredit += copCashAmountCredit;

    return acc;
  }, {});
});


const chunkArray = (array, size) => {
    if (!array || !array.length) return [];
    const chunkedArr = [];
    for (let i = 0; i < array.length; i += size) {
        chunkedArr.push(array.slice(i, i + size));
    }
    return chunkedArr;
};

const sellersArray = computed(() => {
    return Object.values(groupedCardTotals.value);
});

const groupedSellers = computed(() => {
    return chunkArray(sellersArray.value, 2);
});

const isSingleSeller = computed(() => {
    return sellersArray.value.length === 1;
});


const getDividerWidth = (name) => {
    if (!name) return '40%';
    const length = name.length;
    if (length > 20) {
        return '10%'; 
    } else if (length > 15) {
        return '25%'; 
    } else if (length > 6){
        return '35%'; 
    } else {
        return '40%'; 
    }
};
const totalBsGlobal = computed(() => {
  return sellersArray.value.reduce((acc, s) => acc + (s.total_bs_card_debito + s.total_bs_card_credito + s.total_bs_transfer + s.total_bs_mobile + s.total_bs_cash + s.total_bs_card_paymentCredit + s.total_bs_transfer_paymentCredit + s.total_bs_mobile_paymentCredit + s.total_bs_cash_paymentCredit), 0);
});

const totalCopGlobal = computed(() => {
  return sellersArray.value.reduce((acc, s) => acc + (s.total_cop_transfer + s.total_cop_cash + s.total_cop_transfer_paymentCredit + s.total_cop_cash_paymentCredit), 0);
});

const totalUsdGlobal = computed(() => {
  return sellersArray.value.reduce((acc, s) => acc + (s.total_usd_transfer + s.total_usd_paypal + s.total_usd_binance + s.total_usd_cash + s.total_usd_paypal_paymentCredit + s.total_usd_binance_paymentCredit + s.total_usd_cash_paymentCredit), 0);
});

const totalUsdEquivalentGlobal = computed(() => {
  return parseFloat(props.cashData.total_usd || 0) + parseFloat(props.cashData.total_bs_in_usd || 0) + parseFloat(props.cashData.total_cop_in_usd || 0);
});

</script>
<template>
  <VDialog v-model="dialogVisible" max-width="950px" scrollable>
    <VCard class="rounded-xl border shadow-sm">
      <VCardTitle class="d-flex justify-space-between align-center px-6 py-4 border-b">
        <div class="d-flex align-center gap-3">
          <VAvatar color="primary" variant="tonal" rounded>
            <VIcon icon="tabler-truck-delivery" />
          </VAvatar>
          <div>
            <h3 class="text-h6 font-weight-bold mb-0">Detalle de Entregas</h3>
            <span class="text-caption text-medium-emphasis">Reporte N° {{ props.cashData?.id }} • {{ props.cashData?.created_at ? formatDateTime(props.cashData.created_at, "date") : '' }}</span>
          </div>
        </div>
        <VBtn icon="tabler-x" variant="text" size="small" color="secondary" @click="closeModal" />
      </VCardTitle>

      <VCardText class="pa-6" style="background-color: #f8f9fa;">
        <!-- RESUMEN GLOBAL DE ENTREGAS -->
        <h4 class="text-subtitle-1 font-weight-bold mb-3 d-flex align-center gap-2">
          <VIcon icon="tabler-report-money" size="20" color="primary" /> Total Entregas por Monedas
        </h4>
        <VRow class="mb-6">
          <VCol cols="12" sm="6" md="3">
            <VCard variant="outlined" class="bg-white rounded-lg border h-100">
              <VCardItem class="pa-4">
                <div class="d-flex justify-space-between align-start mb-1">
                  <span class="text-caption font-weight-bold text-medium-emphasis">USD ENTREGADO</span>
                  <VIcon icon="tabler-currency-dollar" color="success" size="20" />
                </div>
                <h4 class="text-h6 font-weight-bold text-success">{{ formatCurrency(totalUsdGlobal, 'USD') }}</h4>
                <div class="text-caption text-medium-emphasis mt-1">Efectivo + Electrónico</div>
              </VCardItem>
            </VCard>
          </VCol>

          <VCol cols="12" sm="6" md="3">
            <VCard variant="outlined" class="bg-white rounded-lg border h-100">
              <VCardItem class="pa-4">
                <div class="d-flex justify-space-between align-start mb-1">
                  <span class="text-caption font-weight-bold text-medium-emphasis">BS ENTREGADO</span>
                  <VIcon icon="tabler-currency-bolivar" color="warning" size="20" />
                </div>
                <h4 class="text-h6 font-weight-bold">{{ formatCurrency(totalBsGlobal, 'BS') }}</h4>
                <div class="text-caption text-warning font-weight-medium mt-1">&asymp; {{ formatCurrency(props.cashData.total_bs_in_usd, 'USD') }}</div>
              </VCardItem>
            </VCard>
          </VCol>

          <VCol cols="12" sm="6" md="3">
            <VCard variant="outlined" class="bg-white rounded-lg border h-100">
              <VCardItem class="pa-4">
                <div class="d-flex justify-space-between align-start mb-1">
                  <span class="text-caption font-weight-bold text-medium-emphasis">COP ENTREGADO</span>
                  <VIcon icon="tabler-currency-peso" color="info" size="20" />
                </div>
                <h4 class="text-h6 font-weight-bold">{{ formatCurrency(totalCopGlobal, 'COP') }}</h4>
                <div class="text-caption text-info font-weight-medium mt-1">&asymp; {{ formatCurrency(props.cashData.total_cop_in_usd, 'USD') }}</div>
              </VCardItem>
            </VCard>
          </VCol>

          <VCol cols="12" sm="6" md="3">
            <VCard variant="flat" class="bg-primary text-white rounded-lg border-0 shadow-sm h-100">
              <VCardItem class="pa-4">
                <div class="d-flex justify-space-between align-start mb-1">
                  <span class="text-caption font-weight-bold text-white opacity-80">CARGA TOTAL (USD)</span>
                  <VIcon icon="tabler-sum" color="white" size="20" />
                </div>
                <h4 class="text-h5 font-weight-bold text-white mt-2">
                  {{ formatCurrency(totalUsdEquivalentGlobal, 'USD') }}
                </h4>
              </VCardItem>
            </VCard>
          </VCol>
        </VRow>

        <VDivider class="mb-5" />

        <h4 class="text-subtitle-1 font-weight-bold mb-3 d-flex align-center gap-2">
          <VIcon icon="tabler-users" size="20" color="primary" /> Entregas por Cajero
        </h4>

        <!-- LISTA DE CAJEROS CON ENTREGAS -->
        <VRow>
          <VCol cols="12" md="6" v-for="seller in sellersArray.filter(s => Object.values(s).some(v => typeof v === 'number' && v > 0))" :key="seller.seller_id">
            <VCard variant="outlined" class="bg-white rounded-lg border h-100">
              <VCardItem class="pa-4 pb-0 border-b">
                <div class="d-flex justify-space-between align-start mb-3">
                  <div class="d-flex gap-3 align-center">
                    <VAvatar color="secondary" size="38" variant="tonal" class="font-weight-bold text-body-1">
                      {{ seller.seller_name?.substring(0,2).toUpperCase() }}
                    </VAvatar>
                    <div style="line-height: 1.2;">
                      <h5 class="text-subtitle-1 font-weight-bold mb-0 text-capitalize">{{ seller.seller_name }}</h5>
                      <span class="text-caption text-medium-emphasis">Cajero #{{ seller.seller_id }}</span>
                    </div>
                  </div>
                  <VChip color="primary" size="small" variant="flat" class="font-weight-bold px-3">
                    Total Entregado: {{ formatCurrency(seller.total_usd_equivalent, 'USD') }}
                  </VChip>
                </div>
              </VCardItem>
              
              <VCardText class="pa-0">
                <VTable density="compact" class="text-caption bg-transparent w-100 table-sm">
                  <tbody>
                    <tr v-if="seller.total_bs_card_debito > 0 || seller.total_bs_card_credito > 0">
                      <td class="font-weight-medium text-medium-emphasis py-2 pl-4">Tarjetas (BS):</td>
                      <td class="text-right py-2 pr-4 text-warning">
                        {{ formatCurrency(seller.total_bs_card_debito + seller.total_bs_card_credito, 'BS') }}
                      </td>
                    </tr>
                    <tr v-if="seller.total_bs_transfer > 0 || seller.total_bs_mobile > 0">
                      <td class="font-weight-medium text-medium-emphasis py-2 pl-4">Transf./Pago Móvil (BS):</td>
                      <td class="text-right py-2 pr-4 text-warning">
                        {{ formatCurrency(seller.total_bs_transfer + seller.total_bs_mobile, 'BS') }}
                      </td>
                    </tr>
                    <tr v-if="seller.total_cop_transfer > 0">
                      <td class="font-weight-medium text-medium-emphasis py-2 pl-4">Transferencia (COP):</td>
                      <td class="text-right py-2 pr-4 text-info">
                        {{ formatCurrency(seller.total_cop_transfer, 'COP') }}
                      </td>
                    </tr>
                    <tr v-if="seller.total_usd_transfer > 0 || seller.total_usd_paypal > 0 || seller.total_usd_binance > 0">
                      <td class="font-weight-medium text-medium-emphasis py-2 pl-4">Pagos Electrónicos (USD):</td>
                      <td class="text-right py-2 pr-4 text-success">
                        {{ formatCurrency(seller.total_usd_transfer + seller.total_usd_paypal + seller.total_usd_binance, 'USD') }}
                      </td>
                    </tr>
                    <tr v-if="seller.total_usd_credit > 0">
                      <td class="font-weight-medium text-medium-emphasis py-2 pl-4">Créditos (USD):</td>
                      <td class="text-right py-2 pr-4 font-weight-bold font-italic">
                        {{ formatCurrency(seller.total_usd_credit, 'USD') }}
                      </td>
                    </tr>
                    <tr v-if="seller.total_bs_cash > 0">
                      <td class="font-weight-medium text-medium-emphasis py-2 pl-4">Efectivo (BS):</td>
                      <td class="text-right font-weight-bold py-2 pr-4 text-warning">
                        {{ formatCurrency(seller.total_bs_cash, 'BS') }}
                      </td>
                    </tr>
                    <tr v-if="seller.total_cop_cash > 0">
                      <td class="font-weight-medium text-medium-emphasis py-2 pl-4">Efectivo (COP):</td>
                      <td class="text-right font-weight-bold py-2 pr-4 text-info">
                        {{ formatCurrency(seller.total_cop_cash, 'COP') }}
                      </td>
                    </tr>
                    <tr v-if="seller.total_usd_cash > 0">
                      <td class="font-weight-medium text-medium-emphasis py-2 pl-4">Efectivo (USD):</td>
                      <td class="text-right font-weight-bold py-2 pr-4 text-success">
                        {{ formatCurrency(seller.total_usd_cash, 'USD') }}
                      </td>
                    </tr>
                    <!-- ABONOS (CRÉDITOS PAGADOS) -->
                    <tr v-if="seller.total_bs_card_paymentCredit > 0 || seller.total_bs_transfer_paymentCredit > 0 || seller.total_bs_mobile_paymentCredit > 0 || seller.total_bs_cash_paymentCredit > 0">
                      <td class="font-weight-medium text-medium-emphasis py-2 pl-4">Abonos (BS):</td>
                      <td class="text-right py-2 pr-4 text-warning font-italic">
                        {{ formatCurrency(seller.total_bs_card_paymentCredit + seller.total_bs_transfer_paymentCredit + seller.total_bs_mobile_paymentCredit + seller.total_bs_cash_paymentCredit, 'BS') }}
                      </td>
                    </tr>
                    <tr v-if="seller.total_cop_transfer_paymentCredit > 0 || seller.total_cop_cash_paymentCredit > 0">
                      <td class="font-weight-medium text-medium-emphasis py-2 pl-4">Abonos (COP):</td>
                      <td class="text-right py-2 pr-4 text-info font-italic">
                        {{ formatCurrency(seller.total_cop_transfer_paymentCredit + seller.total_cop_cash_paymentCredit, 'COP') }}
                      </td>
                    </tr>
                    <tr v-if="seller.total_usd_paypal_paymentCredit > 0 || seller.total_usd_binance_paymentCredit > 0 || seller.total_usd_cash_paymentCredit > 0">
                      <td class="font-weight-medium text-medium-emphasis py-2 pl-4">Abonos (USD):</td>
                      <td class="text-right py-2 pr-4 text-success font-italic">
                        {{ formatCurrency(seller.total_usd_paypal_paymentCredit + seller.total_usd_binance_paymentCredit + seller.total_usd_cash_paymentCredit, 'USD') }}
                      </td>
                    </tr>
                  </tbody>
                </VTable>
              </VCardText>
            </VCard>
          </VCol>
          
          <VCol cols="12" v-if="sellersArray.filter(s => Object.values(s).some(v => typeof v === 'number' && v > 0)).length === 0">
            <VAlert type="info" variant="tonal" class="rounded-lg text-body-2" icon="tabler-info-circle">
              No hay entregas procesadas en este reporte.
            </VAlert>
          </VCol>
        </VRow>

        <!-- ESTRUCTURA OCULTA EXCLUSIVA PARA EL REPORTE PDF (ESTILO A4 PREMIUM) -->
        <div id="delivery-report" class="d-none">
          <div style="padding: 30px; background-color: white; color: #1a202c; font-family: Roboto, Helvetica, Arial, sans-serif;">
            <!-- Encabezado con Diseño -->
            <table style=" border-block-end: 2px solid #2d3748;inline-size: 100%; margin-block-end: 25px; padding-block-end: 20px;">
              <tr>
                <td style="inline-size: 50%;">
                  <img :src="BASE64_LOGO_DATA" alt="Logo" style="inline-size: 160px;" />
                </td>
                <td style="inline-size: 50%; text-align: end;">
                  <h1 style="margin: 0; color: #2d3748; font-size: 26px; font-weight: 800; letter-spacing: 1px; text-transform: uppercase;">Reporte de Entregas</h1>
                  <p style=" color: #4a5568; font-size: 14px; font-weight: 600;margin-block: 5px; margin-inline: 0;">Resumen N°: <span style="color: #2b6cb0;">{{ props.cashData.id }}</span></p>
                  <p style="margin: 0; color: #718096; font-size: 12px;">Emisión: {{ formatDateTime(props.cashData.created_at, "all") }}</p>
                </td>
              </tr>
            </table>

            <!-- Resumen de Totales Estilo Dashboard -->
            <div style="margin-block-end: 35px;">
              <h2 style=" border-inline-start: 4px solid #4a5568; color: #2d3748;font-size: 18px; margin-block-end: 20px; padding-inline-start: 10px;">Consolidado de Entrega</h2>
              <table style=" border-collapse: separate; border-spacing: 10px 0;inline-size: 100%;">
                <tr>
                  <td style=" padding: 15px; border: 1px solid #e2e8f0; border-radius: 8px; background-color: #f7fafc;inline-size: 33%; text-align: center;">
                    <div style=" color: #718096;font-size: 10px; font-weight: bold; text-transform: uppercase;">BS Entregado</div>
                    <div style=" color: #2d3748;font-size: 16px; font-weight: bold; margin-block-start: 5px;">{{ formatCurrency(totalBsGlobal, 'BS') }}</div>
                    <div style=" color: #a0aec0;font-size: 11px;">&asymp; {{ formatCurrency(props.cashData.total_bs_in_usd, 'USD') }}</div>
                  </td>
                  <td style=" padding: 15px; border: 1px solid #e2e8f0; border-radius: 8px; background-color: #f7fafc;inline-size: 33%; text-align: center;">
                    <div style=" color: #718096;font-size: 10px; font-weight: bold; text-transform: uppercase;">COP Entregado</div>
                    <div style=" color: #2d3748;font-size: 16px; font-weight: bold; margin-block-start: 5px;">{{ formatCurrency(totalCopGlobal, 'COP') }}</div>
                    <div style=" color: #a0aec0;font-size: 11px;">&asymp; {{ formatCurrency(props.cashData.total_cop_in_usd, 'USD') }}</div>
                  </td>
                  <td style=" padding: 15px; border-radius: 8px; background-color: #4a5568; color: white;inline-size: 34%; text-align: center;">
                    <div style="font-size: 10px; font-weight: bold; opacity: 0.9; text-transform: uppercase;">Carga Total (USD)</div>
                    <div style="font-size: 20px; font-weight: 900; margin-block-start: 5px;">{{ formatCurrency(totalUsdEquivalentGlobal, 'USD') }}</div>
                    <div style="font-size: 10px; opacity: 0.8;">Cierre de Caja Final</div>
                  </td>
                </tr>
              </table>
            </div>

            <!-- Listado Detallado por Cajero -->
            <div style="margin-block-end: 20px;">
              <h2 style=" border-inline-start: 4px solid #4a5568; color: #2d3748;font-size: 18px; margin-block-end: 20px; padding-inline-start: 10px;">Desglose por Cajero</h2>
              <table style=" overflow: hidden; border-radius: 8px; border-collapse: collapse; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 10%);inline-size: 100%;">
                <thead style="background-color: #4a5568; color: white;">
                  <tr>
                    <th style="padding: 15px; font-size: 12px; text-align: start; text-transform: uppercase;">Cajero / Métodos</th>
                    <th style="padding: 15px; font-size: 12px; text-align: end; text-transform: uppercase;">Entrega BS</th>
                    <th style="padding: 15px; font-size: 12px; text-align: end; text-transform: uppercase;">Entrega COP</th>
                    <th style="padding: 15px; font-size: 12px; text-align: end; text-transform: uppercase;">Entrega USD</th>
                  </tr>
                </thead>
                <tbody>
                  <template v-for="(seller, index) in sellersArray.filter(s => Object.values(s).some(v => typeof v === 'number' && v > 0))" :key="'pdf-' + seller.seller_id">
                    <tr style="background-color: #f1f3f5;">
                      <td style=" border-block-end: 2px solid #dee2e6; color: #1a202c; font-size: 14px; font-weight: 800;padding-block: 12px; padding-inline: 15px;">
                        {{ index + 1 }}. {{ seller.seller_name }} <span style=" color: #718096; font-size: 11px;font-weight: 400; margin-inline-start: 10px;">ID: #{{ seller.seller_id }}</span>
                      </td>
                      <td style=" border-block-end: 2px solid #dee2e6; color: #c05621; font-weight: 800;padding-block: 12px; padding-inline: 15px; text-align: end;">{{ formatCurrency(seller.total_bs_card_debito + seller.total_bs_card_credito + seller.total_bs_transfer + seller.total_bs_mobile + seller.total_bs_cash + seller.total_bs_card_paymentCredit + seller.total_bs_transfer_paymentCredit + seller.total_bs_mobile_paymentCredit + seller.total_bs_cash_paymentCredit, 'BS') }}</td>
                      <td style=" border-block-end: 2px solid #dee2e6; color: #2c5282; font-weight: 800;padding-block: 12px; padding-inline: 15px; text-align: end;">{{ formatCurrency(seller.total_cop_transfer + seller.total_cop_cash + seller.total_cop_transfer_paymentCredit + seller.total_cop_cash_paymentCredit, 'COP') }}</td>
                      <td style=" background-color: rgba(43, 108, 176, 5%); border-block-end: 2px solid #dee2e6; color: #2b6cb0; font-weight: 900;padding-block: 12px; padding-inline: 15px; text-align: end;">{{ formatCurrency(seller.total_usd_equivalent, 'USD') }}</td>
                    </tr>
                    
                    <tr v-if="seller.total_bs_card_debito + seller.total_bs_card_credito > 0" style="background-color: white;">
                      <td style=" border-block-end: 1px solid #edf2f7; color: #4a5568; font-size: 11px;padding-block: 6px; padding-inline: 15px; padding-inline-start: 30px;">&bull; Tarjetas POS (BS)</td>
                      <td style=" border-block-end: 1px solid #edf2f7; color: #c05621; font-size: 11px;padding-block: 6px; padding-inline: 15px; text-align: end;">{{ formatCurrency(seller.total_bs_card_debito + seller.total_bs_card_credito, 'BS') }}</td>
                      <td style=" border-block-end: 1px solid #edf2f7;padding-block: 6px; padding-inline: 15px;"></td>
                      <td style=" border-block-end: 1px solid #edf2f7;padding-block: 6px; padding-inline: 15px;"></td>
                    </tr>
                    <tr v-if="seller.total_bs_transfer + seller.total_bs_mobile > 0" style="background-color: white;">
                      <td style=" border-block-end: 1px solid #edf2f7; color: #4a5568; font-size: 11px;padding-block: 6px; padding-inline: 15px; padding-inline-start: 30px;">&bull; Transferencia/Pago Móvil (BS)</td>
                      <td style=" border-block-end: 1px solid #edf2f7; color: #c05621; font-size: 11px;padding-block: 6px; padding-inline: 15px; text-align: end;">{{ formatCurrency(seller.total_bs_transfer + seller.total_bs_mobile, 'BS') }}</td>
                      <td style=" border-block-end: 1px solid #edf2f7;padding-block: 6px; padding-inline: 15px;"></td>
                      <td style=" border-block-end: 1px solid #edf2f7;padding-block: 6px; padding-inline: 15px;"></td>
                    </tr>
                    <tr v-if="seller.total_bs_cash > 0 || seller.total_cop_cash > 0 || seller.total_usd_cash > 0" style="background-color: #fcfcfc;">
                      <td style=" border-block-end: 1px solid #edf2f7; color: #1a202c; font-size: 11px; font-weight: 600;padding-block: 8px; padding-inline: 15px; padding-inline-start: 30px;">&bull; Efectivo Entregado (Físico)</td>
                      <td style=" border-block-end: 1px solid #edf2f7; color: #c05621; font-size: 11px; font-weight: 600;padding-block: 8px; padding-inline: 15px; text-align: end;">{{ seller.total_bs_cash > 0 ? formatCurrency(seller.total_bs_cash, 'BS') : '' }}</td>
                      <td style=" border-block-end: 1px solid #edf2f7; color: #2c5282; font-size: 11px; font-weight: 600;padding-block: 8px; padding-inline: 15px; text-align: end;">{{ seller.total_cop_cash > 0 ? formatCurrency(seller.total_cop_cash, 'COP') : '' }}</td>
                      <td style=" border-block-end: 1px solid #edf2f7; color: #2f855a; font-size: 11px; font-weight: 600;padding-block: 8px; padding-inline: 15px; text-align: end;">{{ seller.total_usd_cash > 0 ? formatCurrency(seller.total_usd_cash, 'USD') : '' }}</td>
                    </tr>
                    <tr v-if="
                      seller.total_bs_card_paymentCredit + seller.total_bs_transfer_paymentCredit + seller.total_bs_mobile_paymentCredit + seller.total_bs_cash_paymentCredit > 0 ||
                      seller.total_cop_transfer_paymentCredit + seller.total_cop_cash_paymentCredit > 0 ||
                      seller.total_usd_paypal_paymentCredit + seller.total_usd_binance_paymentCredit + seller.total_usd_cash_paymentCredit > 0
                    " style="background-color: #fffaf0;">
                      <td style=" border-block-end: 1px solid #edf2f7; color: #718096; font-size: 11px; font-style: italic;padding-block: 6px; padding-inline: 15px; padding-inline-start: 30px;">&bull; Abonos de Créditos (Cobradores)</td>
                      <td style=" border-block-end: 1px solid #edf2f7; color: #a0522d; font-size: 11px;padding-block: 6px; padding-inline: 15px; text-align: end;">
                        {{ formatCurrency(seller.total_bs_card_paymentCredit + seller.total_bs_transfer_paymentCredit + seller.total_bs_mobile_paymentCredit + seller.total_bs_cash_paymentCredit, 'BS') }}
                      </td>
                      <td style=" border-block-end: 1px solid #edf2f7; color: #2c5282; font-size: 11px;padding-block: 6px; padding-inline: 15px; text-align: end;">
                        {{ formatCurrency(seller.total_cop_transfer_paymentCredit + seller.total_cop_cash_paymentCredit, 'COP') }}
                      </td>
                      <td style=" border-block-end: 1px solid #edf2f7; color: #2f855a; font-size: 11px;padding-block: 6px; padding-inline: 15px; text-align: end;">
                        {{ formatCurrency(seller.total_usd_paypal_paymentCredit + seller.total_usd_binance_paymentCredit + seller.total_usd_cash_paymentCredit, 'USD') }}
                      </td>
                    </tr>
                  </template>

                  <tr v-if="sellersArray.filter(s => Object.values(s).some(v => typeof v === 'number' && v > 0)).length === 0">
                    <td colspan="4" style="padding: 30px; background-color: white; color: #718096; font-style: italic; text-align: center;">No se registraron entregas en el presente reporte.</td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- Footer con Notas -->
            <div style=" padding: 20px; border: 1px dashed #cbd5e0; border-radius: 8px; background-color: #f7fafc; color: #718096; font-size: 11px;margin-block-start: 50px; text-align: center;">
              <p style="margin: 0; font-weight: bold;">*** Este documento certifica la recepción y entrega de valores por parte de los cajeros ***</p>
              <p style="margin-block: 5px 0;margin-inline: 0;">Generado por el sistema de gestión el {{ formatDateTime(new Date(), "all") }}</p>
            </div>
          </div>
        </div>
      </VCardText>
      
      <VDivider />
      
      <VCardActions class="pa-4 bg-white d-flex justify-center gap-3 px-6">
        <VBtn variant="tonal" color="secondary" @click="closeModal" class="flex-grow-1 font-weight-medium" size="large">Cerrar</VBtn>
        <VBtn variant="flat" color="primary" @click="downloadReport" prepend-icon="tabler-download" class="flex-grow-1 font-weight-medium" size="large">Descargar Reporte PDF</VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
