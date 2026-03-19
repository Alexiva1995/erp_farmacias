<script setup>
import { BASE64_LOGO_DATA } from "@/constants/logo.js";
import axios from "@/plugins/axios";
import { formatCurrency } from "@/utils/currencyFormatter";
import { formatDateTime } from "@/utils/formatDateTime";
import { computed, defineEmits, defineProps, nextTick } from "vue";
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
  body { font-family: 'Roboto', sans-serif; font-size: 10pt; color: #1a1a1a; }
  .header { text-align: center; margin-bottom: 15px; border-bottom: 2px solid #2c3e50; padding-bottom: 5px; }
  .logo { width: 100px; height: auto; margin-bottom: 5px; }
  .company-name { font-size: 13pt; font-weight: bold; color: #2c3e50; }
  .company-rif { font-size: 8pt; color: #7f8c8d; }
  .document-title { font-size: 11pt; font-weight: bold; margin-top: 8px; text-transform: uppercase; text-align: center; color: #2c3e50; }
  .info-section { width: 100%; margin-bottom: 15px; background: #f9f9f9; padding: 10px; border: 1px solid #eee; box-sizing: border-box; }
  .info-table { width: 100%; border-collapse: collapse; }
  .info-table td { padding: 3px 5px; font-size: 9pt; }
  .section-header { background: #2c3e50; color: white; font-weight: bold; padding: 6px 10px; margin-top: 15px; font-size: 9pt; text-transform: uppercase; }
  .data-table { width: 100%; border-collapse: collapse; margin-top: 0; }
  .data-table th, .data-table td { border: 1px solid #dee2e6; padding: 8px 10px; font-size: 9pt; }
  .data-table th { background-color: #f8f9fa; font-weight: bold; text-align: left; }
  .text-right { text-align: right; }
  .text-center { text-align: center; }
  .total-row { font-weight: bold; background: #f1f3f5; }
  .totals-summary { margin-top: 15px; padding: 10px; background: #fff; border: 2px solid #2c3e50; text-align: right; }
  .net-amount { font-size: 11pt; font-weight: bold; color: #2c3e50; }
  .signature-section { margin-top: 40px; width: 100%; }
  .signature-box { width: 33%; text-align: center; vertical-align: bottom; }
  .signature-line { border-top: 1px solid #000; margin-top: 45px; padding-top: 5px; font-weight: bold; font-size: 9pt; text-transform: uppercase; }
  .footer-note { margin-top: 30px; text-align: center; font-size: 8pt; color: #7f8c8d; font-style: italic; border-top: 1px solid #eee; padding-top: 10px; }
  .d-none { display: none; }
`;


const downloadReport = async () => {
  try {
    await nextTick();
    const element = document.getElementById("delivery-report");
    if (!element) {
      console.error("No se encontró el contenido del reporte.");
      return;
    }

    // Mostrar temporalmente para que el backend capture el HTML con estilos visibles
    element.classList.remove("d-none");
    const htmlContent = element.outerHTML;
    element.classList.add("d-none");

    const params = {
      html_content: `<html><head><style>${ticketStyles}</style></head><body>${htmlContent}</body></html>`,
      filename: "Acta_Entrega_Valores",
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
    let filename = `Acta_Entrega_${props.cashData.id}.pdf`;
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


    const total_bs = bsCarDebitoAmount + bsCarCreditoAmount + bsTransferAmount + bsMobileAmount + bsCashAmount + bsCardAmountCredit + bsCashAmountCredit + bsTransferAmountCredit + bsMobileAmountCredit;
    const total_cop = copTransferAmount + copCashAmount + copTransferAmountCredit + copCashAmountCredit;
    const total_usd = usdTransferAmount + usdPaypalAmount + usdBinanceAmount + usdCashAmount + usdPaypalAmountCredit + usdBinanceAmountCredit + usdCashAmountCredit;

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
         total_usd_equivalent: 0,
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

     acc[sellerId].total_usd_equivalent += total_usd + (total_bs / parseFloat(props.cashData.exchange_rate || 1)) + (total_cop / parseFloat(props.cashData.cop_exchange_rate || 1));

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

// Desgloses Globales por Método
const totalPosBsGlobal = computed(() => {
  return sellersArray.value.reduce((acc, s) => acc + (s.total_bs_card_debito + s.total_bs_card_credito + s.total_bs_card_paymentCredit), 0);
});

const totalTransferBsGlobal = computed(() => {
  return sellersArray.value.reduce((acc, s) => acc + (s.total_bs_transfer + s.total_bs_mobile + s.total_bs_transfer_paymentCredit + s.total_bs_mobile_paymentCredit), 0);
});

const totalTransferCopGlobal = computed(() => {
  return sellersArray.value.reduce((acc, s) => acc + (s.total_cop_transfer + s.total_cop_transfer_paymentCredit), 0);
});

const totalTransferUsdGlobal = computed(() => {
  return sellersArray.value.reduce((acc, s) => acc + (s.total_usd_transfer + s.total_usd_paypal + s.total_usd_binance + s.total_usd_paypal_paymentCredit + s.total_usd_binance_paymentCredit), 0);
});

const totalCashBsGlobal = computed(() => {
  return sellersArray.value.reduce((acc, s) => acc + (s.total_bs_cash + s.total_bs_cash_paymentCredit), 0);
});

const totalCashCopGlobal = computed(() => {
  return sellersArray.value.reduce((acc, s) => acc + (s.total_cop_cash + s.total_cop_cash_paymentCredit), 0);
});

const totalCashUsdGlobal = computed(() => {
  return sellersArray.value.reduce((acc, s) => acc + (s.total_usd_cash + s.total_usd_cash_paymentCredit), 0);
});

// Equivalentes USD por Método
const totalPosEquivalentUsd = computed(() => {
  const bcv = parseFloat(props.cashData.exchange_rate || 1);
  return totalPosBsGlobal.value / bcv;
});

const totalTransferEquivalentUsd = computed(() => {
  const bcv = parseFloat(props.cashData.exchange_rate || 1);
  const cop = parseFloat(props.cashData.cop_exchange_rate || 1);
  return totalTransferUsdGlobal.value + (totalTransferBsGlobal.value / bcv) + (totalTransferCopGlobal.value / cop);
});

const totalCashEquivalentUsd = computed(() => {
  const bcv = parseFloat(props.cashData.exchange_rate || 1);
  const cop = parseFloat(props.cashData.cop_exchange_rate || 1);
  return totalCashUsdGlobal.value + (totalCashBsGlobal.value / bcv) + (totalCashCopGlobal.value / cop);
});

const totalUsdEquivalentGlobal = computed(() => {
  const bcv = parseFloat(props.cashData.exchange_rate || 1);
  const cop = parseFloat(props.cashData.cop_exchange_rate || 1);
  const totalBs = totalBsGlobal.value;
  const totalCop = totalCopGlobal.value;
  const totalUsd = totalUsdGlobal.value;

  return totalUsd + (totalBs / bcv) + (totalCop / cop);
});

const capitalize = (str) => {
  if (!str) return '';
  return str.toLowerCase().replace(/\b\w/g, c => c.toUpperCase());
};

const getCurrentTime = () => {
  return new Date().toLocaleTimeString('es-VE', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
};

defineExpose({ printReport });
</script>
<template>
  <VDialog
    v-model="dialogVisible"
    max-width="950px"
    scrollable
    :fullscreen="mobile"
    :transition="mobile ? 'dialog-bottom-transition' : 'dialog-transition'"
  >
    <VCard class="rounded-xl border shadow-sm">
      <VCardTitle class="d-flex justify-space-between align-center px-6 py-4 border-b bg-surface">
        <div class="d-flex align-center gap-3">
          <VAvatar color="primary" variant="tonal" rounded class="rounded-lg">
            <VIcon icon="tabler-truck-delivery" />
          </VAvatar>
          <div>
            <h3 class="text-h6 font-weight-black mb-0 uppercase leading-none">DETALLE DE ENTREGAS</h3>
            <span class="text-xs text-disabled font-weight-medium uppercase">Reporte N° {{ props.cashData?.id }} • {{ props.cashData?.created_at ? formatDateTime(props.cashData.created_at, "date") : '' }}</span>
          </div>
        </div>
        <VBtn icon="tabler-x" variant="text" size="small" color="secondary" @click="closeModal" />
      </VCardTitle>

      <VCardText class="pa-6 pt-4" style="background-color: #f8f9fa;">
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

        <!-- ESTRUCTURA OCULTA EXCLUSIVA PARA EL REPORTE PDF (ESTILO SOCIAL BENEFITS COMPACTO) -->
        <div id="delivery-report" class="d-none">
          <div class="header">
            <img :src="BASE64_LOGO_DATA" alt="Logo" class="logo" />
            <div class="company-name">FARMACIA BARRIO SUCRE 2024 C.A.</div>
            <div class="company-rif">R.I.F: J-50478962-1</div>
            <div class="document-title">ACTA DE ENTREGA DE VALORES</div>
          </div>

          <div class="info-section" style="padding: 5px; margin-block-end: 10px;">
            <table class="info-table">
              <tr>
                <td style="inline-size: 40%;"><strong>CORRELATIVO:</strong> #{{ props.cashData.id }}</td>
                <td style="inline-size: 30%;"><strong>TASAS:</strong> BCV: {{ props.cashData.exchange_rate }} BS</td>
                <td style="inline-size: 30%; text-align: end;"><strong>EMISIÓN:</strong> {{ formatDateTime(new Date(), 'date') }}</td>
              </tr>
              <tr>
                <td><strong>CAJA GUARDADA:</strong> {{ formatCurrency(totalUsdEquivalentGlobal, 'USD') }}</td>
                <td>COP: {{ props.cashData.cop_exchange_rate }} COP</td>
                <td style="text-align: end;"><strong>HORA:</strong> {{ getCurrentTime() }}</td>
              </tr>
            </table>
          </div>

          <div class="section-header" style=" font-size: 8pt;margin-block-start: 5px; padding-block: 4px; padding-inline: 8px;">DESGLOSE POR PERSONAL</div>
           <table class="data-table">
            <thead>
              <tr>
                <th style=" padding: 4px;font-size: 8pt;">RESPONSABLE / MÉTODO</th>
                <th style="padding: 4px; font-size: 8pt; text-align: end;">BS</th>
                <th style="padding: 4px; font-size: 8pt; text-align: end;">COP</th>
                <th style="padding: 4px; font-size: 8pt; text-align: end;">USD</th>
                <th style="padding: 4px; font-size: 8pt; text-align: end;">TOTAL USD</th>
              </tr>
            </thead>
            <tbody>
              <template v-for="(seller, index) in sellersArray.filter(s => Object.values(s).some(v => typeof v === 'number' && v > 0))" :key="'pdf-' + seller.seller_id">
                <tr class="total-row">
                  <td colspan="4" style=" padding: 4px;font-size: 9pt;">{{ index + 1 }}. {{ seller.seller_name.toUpperCase() }}</td>
                  <td style="padding: 4px; font-size: 9pt; text-align: end;">{{ formatCurrency(seller.total_usd_equivalent, 'USD') }}</td>
                </tr>
                
                <!-- POS -->
                <tr v-if="(seller.total_bs_card_debito + seller.total_bs_card_credito + seller.total_bs_card_paymentCredit) > 0">
                  <td style="font-size: 8pt; padding-block: 2px; padding-inline: 15px 0;">POS (TD y TC)</td>
                  <td style="font-size: 8pt; text-align: end;">{{ formatCurrency(0, 'COP') }}</td>
                  <td style="font-size: 8pt; text-align: end;">{{ formatCurrency(0, 'USD') }}</td>
                  <td style="font-size: 8pt; text-align: end;">{{ formatCurrency((seller.total_bs_card_debito + seller.total_bs_card_credito + seller.total_bs_card_paymentCredit) / parseFloat(props.cashData.exchange_rate || 1), 'USD') }}</td>
                </tr>

                <!-- Transferencia -->
                <tr v-if="(seller.total_bs_transfer + seller.total_bs_mobile + seller.total_cop_transfer + seller.total_usd_transfer + seller.total_usd_paypal + seller.total_usd_binance + seller.total_bs_transfer_paymentCredit + seller.total_bs_mobile_paymentCredit + seller.total_cop_transfer_paymentCredit + seller.total_usd_paypal_paymentCredit + seller.total_usd_binance_paymentCredit) > 0">
                  <td style="font-size: 8pt; padding-block: 2px; padding-inline: 15px 0;">TRANSFERENCIA (PAGO MÓVIL / BANCOS)</td>
                  <td style="font-size: 8pt; text-align: end;">{{ formatCurrency(seller.total_bs_transfer + seller.total_bs_mobile + seller.total_bs_transfer_paymentCredit + seller.total_bs_mobile_paymentCredit, 'BS') }}</td>
                  <td style="font-size: 8pt; text-align: end;">{{ formatCurrency(seller.total_cop_transfer + seller.total_cop_transfer_paymentCredit, 'COP') }}</td>
                  <td style="font-size: 8pt; text-align: end;">{{ formatCurrency(seller.total_usd_transfer + seller.total_usd_paypal + seller.total_usd_binance + seller.total_usd_paypal_paymentCredit + seller.total_usd_binance_paymentCredit, 'USD') }}</td>
                  <td style="font-size: 8pt; text-align: end;">{{ formatCurrency((seller.total_usd_transfer + seller.total_usd_paypal + seller.total_usd_binance + seller.total_usd_paypal_paymentCredit + seller.total_usd_binance_paymentCredit) + (seller.total_bs_transfer + seller.total_bs_mobile + seller.total_bs_transfer_paymentCredit + seller.total_bs_mobile_paymentCredit) / parseFloat(props.cashData.exchange_rate || 1) + (seller.total_cop_transfer + seller.total_cop_transfer_paymentCredit) / parseFloat(props.cashData.cop_exchange_rate || 1), 'USD') }}</td>
                </tr>

                <!-- Efectivo -->
                <tr v-if="(seller.total_bs_cash + seller.total_cop_cash + seller.total_usd_cash + seller.total_bs_cash_paymentCredit + seller.total_cop_cash_paymentCredit + seller.total_usd_cash_paymentCredit) > 0">
                  <td style="font-size: 8pt; padding-block: 2px; padding-inline: 15px 0;">EN EFECTIVO (FONDO FÍSICO)</td>
                  <td style="font-size: 8pt; text-align: end;">{{ formatCurrency(seller.total_bs_cash + seller.total_bs_cash_paymentCredit, 'BS') }}</td>
                  <td style="font-size: 8pt; text-align: end;">{{ formatCurrency(seller.total_cop_cash + seller.total_cop_cash_paymentCredit, 'COP') }}</td>
                  <td style="font-size: 8pt; text-align: end;">{{ formatCurrency(seller.total_usd_cash + seller.total_usd_cash_paymentCredit, 'USD') }}</td>
                  <td style="font-size: 8pt; text-align: end;">{{ formatCurrency((seller.total_usd_cash + seller.total_usd_cash_paymentCredit) + (seller.total_bs_cash + seller.total_bs_cash_paymentCredit) / parseFloat(props.cashData.exchange_rate || 1) + (seller.total_cop_cash + seller.total_cop_cash_paymentCredit) / parseFloat(props.cashData.cop_exchange_rate || 1), 'USD') }}</td>
                </tr>

                <tr>
                  <td colspan="5" style=" padding: 4px;border-block-end: 1px solid #2c3e50; color: #7f8c8d; font-size: 7pt;">
                    OBSERVACIONES: ____________________________________________________________________________________________________
                  </td>
                </tr>
              </template>

              <!-- TOTAL GENERAL DE TODOS LOS TRABAJADORES (RESUMEN FINAL) -->
              <tr style="background-color: #2c3e50; color: white; font-weight: bold;">
                <td style="padding: 6px; font-size: 9pt;">TOTAL GENERAL CONSOLIDADO</td>
                <td style="padding: 6px; font-size: 9pt; text-align: end;">{{ formatCurrency(totalBsGlobal, 'BS') }}</td>
                <td style="padding: 6px; font-size: 9pt; text-align: end;">{{ formatCurrency(totalCopGlobal, 'COP') }}</td>
                <td style="padding: 6px; font-size: 9pt; text-align: end;">{{ formatCurrency(totalUsdGlobal, 'USD') }}</td>
                <td style="padding: 6px; font-size: 9pt; text-align: end;">{{ formatCurrency(totalUsdEquivalentGlobal, 'USD') }}</td>
              </tr>
              
              <!-- Detalles del Total General -->
              <tr style="background-color: #f8f9fa;">
                <td style="font-size: 8pt; font-weight: bold; padding-inline-start: 10px;">DETALLE TOTAL POS</td>
                <td style="font-size: 8pt; text-align: end;">{{ formatCurrency(totalPosBsGlobal, 'BS') }}</td>
                <td style="font-size: 8pt; text-align: end;">{{ formatCurrency(0, 'COP') }}</td>
                <td style="font-size: 8pt; text-align: end;">{{ formatCurrency(0, 'USD') }}</td>
                <td style="font-size: 8pt; font-weight: bold; padding-inline-end: 5px; text-align: end;">{{ formatCurrency(totalPosEquivalentUsd, 'USD') }}</td>
              </tr>
              <tr style="background-color: #f8f9fa;">
                <td style="font-size: 8pt; font-weight: bold; padding-inline-start: 10px;">DETALLE TOTAL TRANSFERENCIA</td>
                <td style="font-size: 8pt; text-align: end;">{{ formatCurrency(totalTransferBsGlobal, 'BS') }}</td>
                <td style="font-size: 8pt; text-align: end;">{{ formatCurrency(totalTransferCopGlobal, 'COP') }}</td>
                <td style="font-size: 8pt; text-align: end;">{{ formatCurrency(totalTransferUsdGlobal, 'USD') }}</td>
                <td style="font-size: 8pt; font-weight: bold; padding-inline-end: 5px; text-align: end;">{{ formatCurrency(totalTransferEquivalentUsd, 'USD') }}</td>
              </tr>
              <tr style="background-color: #f8f9fa;">
                <td style="font-size: 8pt; font-weight: bold; padding-inline-start: 10px;">DETALLE TOTAL EFECTIVO</td>
                <td style="font-size: 8pt; text-align: end;">{{ formatCurrency(totalCashBsGlobal, 'BS') }}</td>
                <td style="font-size: 8pt; text-align: end;">{{ formatCurrency(totalCashCopGlobal, 'COP') }}</td>
                <td style="font-size: 8pt; text-align: end;">{{ formatCurrency(totalCashUsdGlobal, 'USD') }}</td>
                <td style="font-size: 8pt; font-weight: bold; padding-inline-end: 5px; text-align: end;">{{ formatCurrency(totalCashEquivalentUsd, 'USD') }}</td>
              </tr>
            </tbody>
          </table>

          <div class="signature-section" style="margin-block-start: 50px; text-align: center;">
            <div style="display: block; border-block-start: 1.5pt solid #000; inline-size: 250px; margin-block: 0; margin-inline: auto; padding-block-start: 8px;">
              <div style=" color: #000;font-size: 10pt; font-weight: bold;">FIRMA SUPERVISOR</div>
              <small style=" color: #666;font-size: 8pt;">CONTROL DE TURNO / VERIFICACIÓN</small>
            </div>
          </div>

          <div class="footer-note" style=" font-size: 7pt;margin-block-start: 15px;">
            ESTE DOCUMENTO ES UN INSTRUMENTO DE CONTROL INTERNO. HORA CIERRE AUDITABLE.
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
