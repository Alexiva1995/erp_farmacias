<script setup>
import { BASE64_LOGO_DATA } from "@/constants/logo.js";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { formatCurrency } from "@/utils/currencyFormatter";
import { formatDateTime } from "@/utils/formatDateTime";
import { computed, defineEmits, defineProps, nextTick, ref } from "vue";
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

const emit = defineEmits(["update:isDialogVisible", "refresh"]);

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
        closing_id: closing.id,
        seller_id: sellerId,
        seller_name: sellerName,
        declared_cop: closing.declared_cop,
        declared_cop_transfer: closing.declared_cop_transfer,
        declared_usd: closing.declared_usd,
        declared_credit: closing.declared_credit,
        declared_bs_mobile: closing.declared_bs_mobile,
        declared_bs_card: closing.declared_bs_card,
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
         blind_mismatches: new Set(),
         blind_note: "",
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

     const mismatches = closing.blind_mismatches ? (typeof closing.blind_mismatches === 'string' ? JSON.parse(closing.blind_mismatches) : closing.blind_mismatches) : [];
     if (Array.isArray(mismatches)) {
       mismatches.forEach(m => acc[sellerId].blind_mismatches.add(m));
     }
     if (closing.blind_note) {
       acc[sellerId].blind_note = acc[sellerId].blind_note ? acc[sellerId].blind_note + " | " + closing.blind_note : closing.blind_note;
     }

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

const isEditDialogVisible = ref(false);
const isSavingEdit = ref(false);
const editForm = ref({
  closing_id: null,
  seller_name: "",
  declared_usd: 0,
  declared_cop: 0,
  declared_cop_transfer: 0,
  declared_bs_mobile: 0,
  declared_bs_card: 0,
  declared_credit: 0,
});

const openEditDialog = (seller) => {
  editForm.value = {
    closing_id: seller.closing_id,
    seller_name: seller.seller_name,
    declared_usd: seller.declared_usd || 0,
    declared_cop: seller.declared_cop || 0,
    declared_cop_transfer: seller.declared_cop_transfer || 0,
    declared_bs_mobile: seller.declared_bs_mobile || 0,
    declared_bs_card: seller.declared_bs_card || 0,
    declared_credit: seller.declared_credit || 0,
  };
  isEditDialogVisible.value = true;
};

const saveEdit = async () => {
  if (!editForm.value.closing_id) return;
  isSavingEdit.value = true;
  try {
    const response = await axios.patch("/cash-closure/update-blind-amounts", {
      id: editForm.value.closing_id,
      declared_cop: editForm.value.declared_cop,
      declared_cop_transfer: editForm.value.declared_cop_transfer,
      declared_usd: editForm.value.declared_usd,
      declared_credit: editForm.value.declared_credit,
      declared_bs_mobile: editForm.value.declared_bs_mobile,
      declared_bs_card: editForm.value.declared_bs_card,
    });

    if (response.data.status === "success") {
      toast.fire({
        icon: "success",
        title: "Declaración actualizada correctamente.",
      });
      isEditDialogVisible.value = false;
      emit("refresh");
    } else {
      toast.fire({
        icon: "error",
        title: response.data.message || "Error al actualizar.",
      });
    }
  } catch (error) {
    console.error(error);
    toast.fire({
      icon: "error",
      title: error.response?.data?.message || "Error al actualizar la declaración.",
    });
  } finally {
    isSavingEdit.value = false;
  }
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
    <VCard class="detail-dialog-card rounded-xl border-0 shadow-xl overflow-hidden bg-surface">
      <!-- Header Premium -->
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-4 d-flex align-center shadow-sm">
          <VAvatar
            color="white"
            variant="flat"
            size="40"
            class="me-3 elevation-1"
          >
            <VIcon
              icon="tabler-truck-delivery"
              size="24"
              color="primary"
            />
          </VAvatar>
          <div class="d-flex flex-column leading-none">
            <h2 class="text-h6 font-weight-black text-white leading-tight mb-0">
              Detalle de Entregas
            </h2>
            <div class="d-flex align-center gap-2 mt-1">
              <span
                class="text-white opacity-75 uppercase font-weight-bold"
                style="font-size: 0.6rem; letter-spacing: 0.05em;"
              >
                Reporte N° {{ props.cashData?.id }} • {{ props.cashData?.created_at ? formatDateTime(props.cashData.created_at, "date") : '' }}
              </span>
            </div>
          </div>
          <VSpacer />
          <VBtn
            icon="tabler-x"
            variant="tonal"
            color="white"
            size="small"
            class="rounded-lg"
            @click="closeModal"
          />
        </div>
      </VCardTitle>

      <VCardText class="pa-4 pa-sm-6 bg-light">
        <!-- RESUMEN GLOBAL DE ENTREGAS -->
        <div class="d-flex align-center gap-2 mb-4">
          <div class="header-indicator primary shadow-sm" />
          <span class="text-subtitle-2 font-weight-black text-high-emphasis uppercase letter-spacing-1">Resumen de Valores Entregados</span>
        </div>

        <VCard
          variant="flat"
          :class="mobile ? 'pa-3' : 'pa-5'"
          class="bg-white rounded-xl border shadow-sm mb-6"
        >
          <VRow :class="mobile ? 'row-gap-2' : 'row-gap-3'">
            <VCol
              cols="6"
              sm="6"
              md="3"
            >
              <div class="d-flex flex-column h-100">
                <span class="text-super-xs font-weight-black text-disabled uppercase mb-1">USD Entregado</span>
                <div class="d-flex align-center gap-1">
                  <VIcon
                    icon="tabler-currency-dollar"
                    color="success"
                    size="18"
                  />
                  <h4 class="text-subtitle-2 font-weight-black text-success">
                    {{ formatCurrency(totalUsdGlobal, 'USD') }}
                  </h4>
                </div>
                <span v-if="!mobile" class="text-super-xs text-medium-emphasis mt-1 italic">Efectivo + Electrónico</span>
              </div>
            </VCol>

            <VCol
              cols="6"
              sm="6"
              md="3"
            >
              <div class="d-flex flex-column h-100 text-end text-sm-start">
                <span class="text-super-xs font-weight-black text-disabled uppercase mb-1">BS Entregado</span>
                <div class="d-flex align-center justify-end justify-sm-start gap-1">
                  <VIcon
                    icon="tabler-currency-bolivar"
                    color="warning"
                    size="18"
                  />
                  <h4 class="text-subtitle-2 font-weight-black text-high-emphasis">
                    {{ formatCurrency(totalBsGlobal, 'BS') }}
                  </h4>
                </div>
                <span class="text-super-xs text-warning font-weight-bold mt-1">&asymp; {{ formatCurrency(props.cashData.total_bs_in_usd, 'USD') }}</span>
              </div>
            </VCol>

            <VCol
              cols="6"
              sm="6"
              md="3"
            >
              <div class="d-flex flex-column h-100">
                <span class="text-super-xs font-weight-black text-disabled uppercase mb-1">COP Entregado</span>
                <div class="d-flex align-center gap-1">
                  <VIcon
                    icon="tabler-currency-peso"
                    color="info"
                    size="18"
                  />
                  <h4 class="text-subtitle-2 font-weight-black text-high-emphasis">
                    {{ formatCurrency(totalCopGlobal, 'COP') }}
                  </h4>
                </div>
                <span class="text-super-xs text-info font-weight-bold mt-1">&asymp; {{ formatCurrency(props.cashData.total_cop_in_usd, 'USD') }}</span>
              </div>
            </VCol>

            <VCol
              cols="6"
              sm="6"
              md="3"
            >
              <VCard
                variant="flat"
                color="primary"
                class="rounded-lg pa-3 text-center h-100 d-flex flex-column justify-center shadow-sm"
              >
                <span class="text-super-xs font-weight-black text-white opacity-80 uppercase d-block mb-1">Carga Total (USD)</span>
                <h4 class="text-subtitle-1 font-weight-black text-white leading-none">
                  {{ formatCurrency(totalUsdEquivalentGlobal, 'USD') }}
                </h4>
              </VCard>
            </VCol>
          </VRow>
        </VCard>

        <div class="d-flex align-center gap-2 mb-4">
          <div class="header-indicator secondary shadow-sm" />
          <span class="text-subtitle-2 font-weight-black text-high-emphasis uppercase letter-spacing-1">Entregas por Cajero</span>
        </div>

        <!-- LISTA DE CAJEROS CON ENTREGAS -->
        <VRow>
          <VCol
            v-for="seller in sellersArray.filter(s => Object.values(s).some(v => typeof v === 'number' && v > 0))"
            :key="seller.seller_id"
            cols="12"
            md="6"
          >
            <VCard
              variant="flat"
              class="rounded-xl border shadow-md h-100 overflow-hidden seller-card-premium"
            >
              <div class="pa-4 border-b seller-card-header d-flex justify-space-between align-center">
                <div class="d-flex gap-3 align-center">
                  <VAvatar
                    color="primary"
                    size="40"
                    variant="tonal"
                    class="font-weight-black rounded-lg"
                  >
                    {{ seller.seller_name?.substring(0,2).toUpperCase() }}
                  </VAvatar>
                  <div class="leading-none">
                    <h5 class="text-subtitle-2 font-weight-black mb-1 text-capitalize d-flex align-center gap-1">
                      {{ seller.seller_name }}
                      <VBtn
                        icon="tabler-edit"
                        variant="text"
                        density="compact"
                        size="small"
                        color="primary"
                        class="mt-n1"
                        title="Editar Declaración"
                        @click="openEditDialog(seller)"
                      />
                    </h5>
                    <span class="text-super-xs text-disabled font-weight-bold uppercase">Cajero #{{ seller.seller_id }}</span>
                  </div>
                </div>
                <VChip
                  color="success"
                  size="small"
                  variant="flat"
                  class="font-weight-black shadow-sm"
                >
                  {{ formatCurrency(seller.total_usd_equivalent, 'USD') }}
                </VChip>
              </div>

              <VCardText class="pa-0">
                <VTable
                  density="compact"
                  class="text-caption bg-transparent table-standard"
                >
                  <tbody>
                    <tr v-if="seller.total_bs_card_debito > 0 || seller.total_bs_card_credito > 0">
                      <td class="font-weight-black text-disabled uppercase pl-6 py-2">Tarjetas (BS):</td>
                      <td class="text-right py-2 pr-6 text-warning font-weight-bold">
                        <span v-if="seller.blind_mismatches?.has('bs_card')" class="text-error font-weight-black mr-1" title="Diferencia en Cierre Ciego">*</span>
                        {{ formatCurrency(seller.total_bs_card_debito + seller.total_bs_card_credito, 'BS') }}
                      </td>
                    </tr>
                    <tr v-if="seller.total_bs_transfer > 0 || seller.total_bs_mobile > 0">
                      <td class="font-weight-black text-disabled uppercase pl-6 py-2">Transf./Pago Móvil (BS):</td>
                      <td class="text-right py-2 pr-6 text-warning font-weight-bold">
                        <span v-if="seller.blind_mismatches?.has('bs_mobile')" class="text-error font-weight-black mr-1" title="Diferencia en Cierre Ciego">*</span>
                        {{ formatCurrency(seller.total_bs_transfer + seller.total_bs_mobile, 'BS') }}
                      </td>
                    </tr>
                    <tr v-if="seller.total_cop_transfer > 0">
                      <td class="font-weight-black text-disabled uppercase pl-6 py-2">Transferencia (COP):</td>
                      <td class="text-right py-2 pr-6 text-info font-weight-bold">
                        <span v-if="seller.blind_mismatches?.has('cop_transfer')" class="text-error font-weight-black mr-1" title="Diferencia en Cierre Ciego">*</span>
                        {{ formatCurrency(seller.total_cop_transfer, 'COP') }}
                      </td>
                    </tr>
                    <tr v-if="seller.total_usd_transfer > 0 || seller.total_usd_paypal > 0 || seller.total_usd_binance > 0">
                      <td class="font-weight-black text-disabled uppercase pl-6 py-2">Pagos Electrónicos (USD):</td>
                      <td class="text-right py-2 pr-6 text-success font-weight-bold">
                        {{ formatCurrency(seller.total_usd_transfer + seller.total_usd_paypal + seller.total_usd_binance, 'USD') }}
                      </td>
                    </tr>
                    <tr v-if="seller.total_usd_credit > 0">
                      <td class="font-weight-black text-disabled uppercase pl-6 py-2">Créditos (USD):</td>
                      <td class="text-right py-2 pr-6 font-weight-black italic">
                        <span v-if="seller.blind_mismatches?.has('credit')" class="text-error font-weight-black mr-1" title="Diferencia en Cierre Ciego">*</span>
                        {{ formatCurrency(seller.total_usd_credit, 'USD') }}
                      </td>
                    </tr>
                    <tr v-if="seller.total_bs_cash > 0">
                      <td class="font-weight-black text-disabled uppercase pl-6 py-2 font-weight-bold">Efectivo (BS):</td>
                      <td class="text-right font-weight-black py-2 pr-6 text-warning">
                        {{ formatCurrency(seller.total_bs_cash, 'BS') }}
                      </td>
                    </tr>
                    <tr v-if="seller.total_cop_cash > 0">
                      <td class="font-weight-black text-disabled uppercase pl-6 py-2 font-weight-bold">Efectivo (COP):</td>
                      <td class="text-right font-weight-black py-2 pr-6 text-info">
                        <span v-if="seller.blind_mismatches?.has('cop')" class="text-error font-weight-black mr-1" title="Diferencia en Cierre Ciego">*</span>
                        {{ formatCurrency(seller.total_cop_cash, 'COP') }}
                      </td>
                    </tr>
                    <tr v-if="seller.total_usd_cash > 0">
                      <td class="font-weight-black text-disabled uppercase pl-6 py-2 font-weight-bold">Efectivo (USD):</td>
                      <td class="text-right font-weight-black py-2 pr-6 text-success">
                        <span v-if="seller.blind_mismatches?.has('usd')" class="text-error font-weight-black mr-1" title="Diferencia en Cierre Ciego">*</span>
                        {{ formatCurrency(seller.total_usd_cash, 'USD') }}
                      </td>
                    </tr>
                    <!-- ABONOS (CRÉDITOS PAGADOS) -->
                    <tr v-if="seller.total_bs_card_paymentCredit > 0 || seller.total_bs_transfer_paymentCredit > 0 || seller.total_bs_mobile_paymentCredit > 0 || seller.total_bs_cash_paymentCredit > 0">
                      <td class="font-weight-black text-disabled uppercase pl-6 py-2 italic font-weight-regular">Abonos (BS):</td>
                      <td class="text-right py-2 pr-6 text-warning italic">
                        {{ formatCurrency(seller.total_bs_card_paymentCredit + seller.total_bs_transfer_paymentCredit + seller.total_bs_mobile_paymentCredit + seller.total_bs_cash_paymentCredit, 'BS') }}
                      </td>
                    </tr>
                    <tr v-if="seller.total_cop_transfer_paymentCredit > 0 || seller.total_cop_cash_paymentCredit > 0">
                      <td class="font-weight-black text-disabled uppercase pl-6 py-2 italic font-weight-regular">Abonos (COP):</td>
                      <td class="text-right py-2 pr-6 text-info italic">
                        {{ formatCurrency(seller.total_cop_transfer_paymentCredit + seller.total_cop_cash_paymentCredit, 'COP') }}
                      </td>
                    </tr>
                    <tr v-if="seller.total_usd_paypal_paymentCredit > 0 || seller.total_usd_binance_paymentCredit > 0 || seller.total_usd_cash_paymentCredit > 0">
                      <td class="font-weight-black text-disabled uppercase pl-6 py-2 italic font-weight-regular">Abonos (USD):</td>
                      <td class="text-right py-2 pr-6 text-success italic">
                        {{ formatCurrency(seller.total_usd_paypal_paymentCredit + seller.total_usd_binance_paymentCredit + seller.total_usd_cash_paymentCredit, 'USD') }}
                      </td>
                    </tr>
                    <tr v-if="seller.blind_note">
                      <td colspan="2" class="px-6 py-2 bg-error-opacity-1 text-error text-super-xs italic">
                        Nota Ciego: {{ seller.blind_note }}
                      </td>
                    </tr>
                  </tbody>
                </VTable>
              </VCardText>
            </VCard>
          </VCol>
          
          <VCol
            v-if="sellersArray.filter(s => Object.values(s).some(v => typeof v === 'number' && v > 0)).length === 0"
            cols="12"
          >
            <VAlert
              type="info"
              variant="tonal"
              class="rounded-xl text-button font-weight-black"
              icon="tabler-info-circle"
            >
              NO SE ENCONTRARON ENTREGAS PROCESADAS EN ESTE REPORTE
            </VAlert>
          </VCol>
        </VRow>

        <!-- ESTRUCTURA OCULTA EXCLUSIVA PARA EL REPORTE PDF (ESTILO SOCIAL BENEFITS COMPACTO) -->
        <div
          id="delivery-report"
          class="d-none"
        >
          <div class="header">
            <img
              :src="BASE64_LOGO_DATA"
              alt="Logo"
              class="logo"
            >
            <div class="company-name">
              FARMACIA BARRIO SUCRE 2024 C.A.
            </div>
            <div class="company-rif">
              R.I.F: J-50478962-1
            </div>
            <div class="document-title">
              ACTA DE ENTREGA DE VALORES
            </div>
          </div>

          <div
            class="info-section"
            style="padding: 5px; margin-block-end: 10px;"
          >
            <table class="info-table">
              <tr>
                <td style="inline-size: 40%;">
                  <strong>CORRELATIVO:</strong> #{{ props.cashData.id }}
                </td>
                <td style="inline-size: 30%;">
                  <strong>TASAS:</strong> BCV: {{ props.cashData.exchange_rate }} BS
                </td>
                <td style="inline-size: 30%; text-align: end;">
                  <strong>EMISIÓN:</strong> {{ formatDateTime(new Date(), 'date') }}
                </td>
              </tr>
              <tr>
                <td><strong>CAJA GUARDADA:</strong> {{ formatCurrency(totalUsdEquivalentGlobal, 'USD') }}</td>
                <td>COP: {{ props.cashData.cop_exchange_rate }} COP</td>
                <td style="text-align: end;">
                  <strong>HORA:</strong> {{ getCurrentTime() }}
                </td>
              </tr>
            </table>
          </div>

          <div
            class="section-header"
            style=" font-size: 8pt;margin-block-start: 5px; padding-block: 4px; padding-inline: 8px;"
          >
            DESGLOSE POR PERSONAL
          </div>
          <table class="data-table">
            <thead>
              <tr>
                <th style=" padding: 4px;font-size: 8pt;">
                  RESPONSABLE / MÉTODO
                </th>
                <th style="padding: 4px; font-size: 8pt; text-align: end;">
                  BS
                </th>
                <th style="padding: 4px; font-size: 8pt; text-align: end;">
                  COP
                </th>
                <th style="padding: 4px; font-size: 8pt; text-align: end;">
                  USD
                </th>
                <th style="padding: 4px; font-size: 8pt; text-align: end;">
                  TOTAL USD
                </th>
              </tr>
            </thead>
            <tbody>
              <template
                v-for="(seller, index) in sellersArray.filter(s => Object.values(s).some(v => typeof v === 'number' && v > 0))"
                :key="'pdf-' + seller.seller_id"
              >
                <tr class="total-row">
                  <td
                    colspan="4"
                    style=" padding: 4px;font-size: 9pt;"
                  >
                    {{ index + 1 }}. {{ seller.seller_name.toUpperCase() }}
                  </td>
                  <td style="padding: 4px; font-size: 9pt; text-align: end;">
                    {{ formatCurrency(seller.total_usd_equivalent, 'USD') }}
                  </td>
                </tr>
                
                <!-- POS -->
                <tr v-if="(seller.total_bs_card_debito + seller.total_bs_card_credito + seller.total_bs_card_paymentCredit) > 0">
                  <td style="font-size: 8pt; padding-block: 2px; padding-inline: 15px 0;">
                    POS (TD y TC)
                  </td>
                  <td style="font-size: 8pt; text-align: end;">
                    {{ formatCurrency(seller.total_bs_card_debito + seller.total_bs_card_credito + seller.total_bs_card_paymentCredit, 'BS') }}
                  </td>
                  <td style="font-size: 8pt; text-align: end;">
                    {{ formatCurrency(0, 'COP') }}
                  </td>
                  <td style="font-size: 8pt; text-align: end;">
                    {{ formatCurrency(0, 'USD') }}
                  </td>
                  <td style="font-size: 8pt; text-align: end;">
                    {{ formatCurrency((seller.total_bs_card_debito + seller.total_bs_card_credito + seller.total_bs_card_paymentCredit) / parseFloat(props.cashData.exchange_rate || 1), 'USD') }}
                  </td>
                </tr>

                <!-- Transferencia -->
                <tr v-if="(seller.total_bs_transfer + seller.total_bs_mobile + seller.total_cop_transfer + seller.total_usd_transfer + seller.total_usd_paypal + seller.total_usd_binance + seller.total_bs_transfer_paymentCredit + seller.total_bs_mobile_paymentCredit + seller.total_cop_transfer_paymentCredit + seller.total_usd_paypal_paymentCredit + seller.total_usd_binance_paymentCredit) > 0">
                  <td style="font-size: 8pt; padding-block: 2px; padding-inline: 15px 0;">
                    TRANSFERENCIA (PAGO MÓVIL / BANCOS)
                  </td>
                  <td style="font-size: 8pt; text-align: end;">
                    {{ formatCurrency(seller.total_bs_transfer + seller.total_bs_mobile + seller.total_bs_transfer_paymentCredit + seller.total_bs_mobile_paymentCredit, 'BS') }}
                  </td>
                  <td style="font-size: 8pt; text-align: end;">
                    {{ formatCurrency(seller.total_cop_transfer + seller.total_cop_transfer_paymentCredit, 'COP') }}
                  </td>
                  <td style="font-size: 8pt; text-align: end;">
                    {{ formatCurrency(seller.total_usd_transfer + seller.total_usd_paypal + seller.total_usd_binance + seller.total_usd_paypal_paymentCredit + seller.total_usd_binance_paymentCredit, 'USD') }}
                  </td>
                  <td style="font-size: 8pt; text-align: end;">
                    {{ formatCurrency((seller.total_usd_transfer + seller.total_usd_paypal + seller.total_usd_binance + seller.total_usd_paypal_paymentCredit + seller.total_usd_binance_paymentCredit) + (seller.total_bs_transfer + seller.total_bs_mobile + seller.total_bs_transfer_paymentCredit + seller.total_bs_mobile_paymentCredit) / parseFloat(props.cashData.exchange_rate || 1) + (seller.total_cop_transfer + seller.total_cop_transfer_paymentCredit) / parseFloat(props.cashData.cop_exchange_rate || 1), 'USD') }}
                  </td>
                </tr>

                <!-- Efectivo -->
                <tr v-if="(seller.total_bs_cash + seller.total_cop_cash + seller.total_usd_cash + seller.total_bs_cash_paymentCredit + seller.total_cop_cash_paymentCredit + seller.total_usd_cash_paymentCredit) > 0">
                  <td style="font-size: 8pt; padding-block: 2px; padding-inline: 15px 0;">
                    EN EFECTIVO (FONDO FÍSICO)
                  </td>
                  <td style="font-size: 8pt; text-align: end;">
                    {{ formatCurrency(seller.total_bs_cash + seller.total_bs_cash_paymentCredit, 'BS') }}
                  </td>
                  <td style="font-size: 8pt; text-align: end;">
                    {{ formatCurrency(seller.total_cop_cash + seller.total_cop_cash_paymentCredit, 'COP') }}
                  </td>
                  <td style="font-size: 8pt; text-align: end;">
                    {{ formatCurrency(seller.total_usd_cash + seller.total_usd_cash_paymentCredit, 'USD') }}
                  </td>
                  <td style="font-size: 8pt; text-align: end;">
                    {{ formatCurrency((seller.total_usd_cash + seller.total_usd_cash_paymentCredit) + (seller.total_bs_cash + seller.total_bs_cash_paymentCredit) / parseFloat(props.cashData.exchange_rate || 1) + (seller.total_cop_cash + seller.total_cop_cash_paymentCredit) / parseFloat(props.cashData.cop_exchange_rate || 1), 'USD') }}
                  </td>
                </tr>

                <tr>
                  <td
                    colspan="5"
                    style=" padding: 4px;border-block-end: 1px solid #dee2e6; color: #7f8c8d; font-size: 7pt;"
                  >
                    OBSERVACIONES: ____________________________________________________________________________________________________
                  </td>
                </tr>
              </template>

              <!-- TOTAL GENERAL DE TODOS LOS TRABAJADORES (RESUMEN FINAL) -->
              <tr style="background-color: #2c3e50; color: white; font-weight: bold;">
                <td style="padding: 6px; font-size: 9pt;">
                  TOTAL GENERAL CONSOLIDADO
                </td>
                <td style="padding: 6px; font-size: 9pt; text-align: end;">
                  {{ formatCurrency(totalBsGlobal, 'BS') }}
                </td>
                <td style="padding: 6px; font-size: 9pt; text-align: end;">
                  {{ formatCurrency(totalCopGlobal, 'COP') }}
                </td>
                <td style="padding: 6px; font-size: 9pt; text-align: end;">
                  {{ formatCurrency(totalUsdGlobal, 'USD') }}
                </td>
                <td style="padding: 6px; font-size: 9pt; text-align: end;">
                  {{ formatCurrency(totalUsdEquivalentGlobal, 'USD') }}
                </td>
              </tr>
              
              <!-- Detalles del Total General -->
              <tr style="background-color: #f8f9fa;">
                <td style="font-size: 8pt; font-weight: bold; padding-inline-start: 10px;">
                  DETALLE TOTAL POS
                </td>
                <td style="font-size: 8pt; text-align: end;">
                  {{ formatCurrency(totalPosBsGlobal, 'BS') }}
                </td>
                <td style="font-size: 8pt; text-align: end;">
                  {{ formatCurrency(0, 'COP') }}
                </td>
                <td style="font-size: 8pt; text-align: end;">
                  {{ formatCurrency(0, 'USD') }}
                </td>
                <td style="font-size: 8pt; font-weight: bold; padding-inline-end: 5px; text-align: end;">
                  {{ formatCurrency(totalPosEquivalentUsd, 'USD') }}
                </td>
              </tr>
              <tr style="background-color: #f8f9fa;">
                <td style="font-size: 8pt; font-weight: bold; padding-inline-start: 10px;">
                  DETALLE TOTAL TRANSFERENCIA
                </td>
                <td style="font-size: 8pt; text-align: end;">
                  {{ formatCurrency(totalTransferBsGlobal, 'BS') }}
                </td>
                <td style="font-size: 8pt; text-align: end;">
                  {{ formatCurrency(totalTransferCopGlobal, 'COP') }}
                </td>
                <td style="font-size: 8pt; text-align: end;">
                  {{ formatCurrency(totalTransferUsdGlobal, 'USD') }}
                </td>
                <td style="font-size: 8pt; font-weight: bold; padding-inline-end: 5px; text-align: end;">
                  {{ formatCurrency(totalTransferEquivalentUsd, 'USD') }}
                </td>
              </tr>
              <tr style="background-color: #f8f9fa;">
                <td style="font-size: 8pt; font-weight: bold; padding-inline-start: 10px;">
                  DETALLE TOTAL EFECTIVO
                </td>
                <td style="font-size: 8pt; text-align: end;">
                  {{ formatCurrency(totalCashBsGlobal, 'BS') }}
                </td>
                <td style="font-size: 8pt; text-align: end;">
                  {{ formatCurrency(totalCashCopGlobal, 'COP') }}
                </td>
                <td style="font-size: 8pt; text-align: end;">
                  {{ formatCurrency(totalCashUsdGlobal, 'USD') }}
                </td>
                <td style="font-size: 8pt; font-weight: bold; padding-inline-end: 5px; text-align: end;">
                  {{ formatCurrency(totalCashEquivalentUsd, 'USD') }}
                </td>
              </tr>
            </tbody>
          </table>

          <div
            class="signature-section"
            style="margin-block-start: 50px; text-align: center;"
          >
            <div style="display: block; border-block-start: 1.5pt solid #000; inline-size: 250px; margin-block: 0; margin-inline: auto; padding-block-start: 8px;">
              <div style=" color: #000;font-size: 10pt; font-weight: bold;">
                FIRMA SUPERVISOR
              </div>
              <small style=" color: #666;font-size: 8pt;">CONTROL DE TURNO / VERIFICACIÓN</small>
            </div>
          </div>

          <div
            class="footer-note"
            style=" font-size: 7pt;margin-block-start: 15px;"
          >
            ESTE DOCUMENTO ES UN INSTRUMENTO DE CONTROL INTERNO. HORA CIERRE AUDITABLE.
          </div>
        </div>
      </VCardText>

      <!-- Acciones Premium -->
      <VCardActions class="pa-4 bg-white border-t px-6">
        <VRow
          no-gutters
          class="w-100"
        >
          <VCol
            cols="12"
            sm="6"
            class="pa-1"
          >
            <VBtn
              color="secondary"
              variant="tonal"
              height="50"
              block
              class="font-weight-black rounded-lg text-button uppercase"
              @click="closeModal"
            >
              Cerrar Reporte
            </VBtn>
          </VCol>
          <VCol
            cols="12"
            sm="6"
            class="pa-1"
          >
            <VBtn
              color="primary"
              variant="flat"
              height="50"
              block
              class="font-weight-black rounded-lg shadow-primary text-button uppercase"
              @click="downloadReport"
            >
              <VIcon
                start
                icon="tabler-download"
                size="18"
                class="me-2"
              />
              Descargar Acta PDF
            </VBtn>
          </VCol>
        </VRow>
      </VCardActions>
    </VCard>
  </VDialog>

  <!-- Dialogo para editar declaración del cajero (Cierre Ciego) -->
  <VDialog v-model="isEditDialogVisible" max-width="500px">
    <VCard class="rounded-xl">
      <VCardTitle class="d-flex justify-space-between align-center px-6 py-4">
        <span class="text-h6 font-weight-bold">Editar Declaración: {{ editForm.seller_name }}</span>
        <VBtn icon="tabler-x" variant="text" density="compact" @click="isEditDialogVisible = false" />
      </VCardTitle>
      <VDivider />
      <VCardText class="px-6 py-4">
        <VRow>
          <VCol cols="12" md="6">
            <VTextField
              v-model.number="editForm.declared_usd"
              label="Efectivo USD"
              type="number"
              prefix="$"
              variant="outlined"
              density="comfortable"
            />
          </VCol>
          <VCol cols="12" md="6">
            <VTextField
              v-model.number="editForm.declared_cop"
              label="Efectivo COP"
              type="number"
              prefix="COP"
              variant="outlined"
              density="comfortable"
            />
          </VCol>
          <VCol cols="12" md="6">
            <VTextField
              v-model.number="editForm.declared_cop_transfer"
              label="Transf. COP (Bancolombia)"
              type="number"
              prefix="COP"
              variant="outlined"
              density="comfortable"
            />
          </VCol>
          <VCol cols="12" md="6">
            <VTextField
              v-model.number="editForm.declared_bs_mobile"
              label="Pago Móvil / Transf. BS"
              type="number"
              prefix="Bs"
              variant="outlined"
              density="comfortable"
            />
          </VCol>
          <VCol cols="12" md="6">
            <VTextField
              v-model.number="editForm.declared_bs_card"
              label="Tarjetas BS"
              type="number"
              prefix="Bs"
              variant="outlined"
              density="comfortable"
            />
          </VCol>
          <VCol cols="12">
            <VTextField
              v-model.number="editForm.declared_credit"
              label="Créditos USD"
              type="number"
              prefix="$"
              variant="outlined"
              density="comfortable"
            />
          </VCol>
        </VRow>
      </VCardText>
      <VDivider />
      <VCardActions class="px-6 py-4">
        <VSpacer />
        <VBtn color="grey-darken-1" variant="outlined" @click="isEditDialogVisible = false">
          Cancelar
        </VBtn>
        <VBtn color="primary" variant="flat" :loading="isSavingEdit" @click="saveEdit">
          Guardar Cambios
            </VBtn>
          </VCardActions>
        </VCard>
      </VDialog>
</template>

<style scoped>
.header-gradient {
  background: linear-gradient(
    135deg,
    rgb(var(--v-theme-primary)) 0%,
    rgb(var(--v-theme-gradient-end)) 100%
  );
}

.detail-dialog-card {
  border-radius: 12px !important;
}

.header-indicator {
  inline-size: 4px;
  block-size: 16px;
  border-radius: 10px;
}

.header-indicator.primary {
  background-color: rgb(var(--v-theme-primary));
}

.header-indicator.secondary {
  background-color: rgb(var(--v-theme-secondary));
}

.shadow-primary {
  box-shadow: 0 4px 14px 0 rgba(var(--v-theme-primary), 0.39) !important;
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: normal;
}

.letter-spacing-1 {
  letter-spacing: 1px !important;
}

.leading-none {
  line-height: 1 !important;
}

.border-t {
  border-block-start: 1px solid rgba(var(--v-border-color), 0.08) !important;
}

.table-standard :deep(td) {
  border-block-end: 1px solid rgba(var(--v-border-color), 0.05) !important;
}

.table-standard :deep(tr:last-child td) {
  border-block-end: none !important;
}

.italic {
  font-style: italic;
}

.seller-card-premium {
  background-color: #f8fafc !important;
  border-inline-start: 4px solid rgb(var(--v-theme-primary)) !important;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.seller-card-premium:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 20px rgba(0,0,0,0.08) !important;
}

.seller-card-header {
  background-color: rgba(var(--v-theme-primary), 0.04) !important;
}
</style>
