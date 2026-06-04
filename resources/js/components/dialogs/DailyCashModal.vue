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

const processedClosings = computed(() => {
  if (!props.cashData || !props.cashData.cash_closings) {
    return [];
  }
  return props.cashData.cash_closings
    .map((closing) => {
      const bsSum = 
        parseFloat(closing.bs_cash || 0) + parseFloat(closing.bs_card_debito || 0) + parseFloat(closing.bs_card_credit || 0) +
        parseFloat(closing.bs_transfer || 0) + parseFloat(closing.bs_mobile || 0) +
        parseFloat(closing.bs_cash_payment_credit || 0) + parseFloat(closing.bs_card_payment_credit || 0) +
        parseFloat(closing.bs_transfer_payment_credit || 0) + parseFloat(closing.bs_mobile_payment_credit || 0);
      
      const copSum = 
        parseFloat(closing.cop_cash || 0) + parseFloat(closing.cop_transfer || 0) +
        parseFloat(closing.cop_cash_payment_credit || 0) + parseFloat(closing.cop_transfer_payment_credit || 0);
      
      const usdSum = 
        parseFloat(closing.usd_cash || 0) + parseFloat(closing.usd_transfer || 0) +
        parseFloat(closing.usd_paypal || 0) + parseFloat(closing.usd_binance || 0) +
        parseFloat(closing.usd_credit || 0) +
        parseFloat(closing.usd_cash_payment_credit || 0) + parseFloat(closing.usd_paypal_payment_credit || 0) +
        parseFloat(closing.usd_binance_payment_credit || 0) + parseFloat(closing.usd_conversion || 0);

      const totalUsdEq = parseFloat(closing.total_usd || 0) + parseFloat(closing.total_bs_in_usd || 0) + parseFloat(closing.total_cop_in_usd || 0);

      return {
        ...closing,
        real_bs: bsSum,
        real_cop: copSum,
        real_usd: usdSum,
        real_total_usd: totalUsdEq
      };
    })
    .filter((c) => c.real_total_usd > 0);
});

const filteredCashClosings = processedClosings;

const chunkArray = (array, size) => {
  if (!array || !array.length) return [];
  const chunkedArr = [];
  for (let i = 0; i < array.length; i += size) {
    chunkedArr.push(array.slice(i, i + size));
  }
  return chunkedArr;
};

const groupedClosings = computed(() => {
  return chunkArray(filteredCashClosings.value, 2);
});

const isSingleSeller = computed(() => {
  return filteredCashClosings.value.length === 1;
});

const totalCreditsUsdGlobal = computed(() => {
  return filteredCashClosings.value.reduce((acc, c) => acc + parseFloat(c.usd_credit || 0), 0);
});

const totalUsdGlobal = computed(() => {
  // Ahora total_usd ya incluye los créditos según la nueva lógica del backend
  return filteredCashClosings.value.reduce((acc, c) => acc + parseFloat(c.total_usd || 0), 0);
});


const totalCopGlobal = computed(() => {
  return filteredCashClosings.value.reduce((acc, c) => acc + parseFloat(c.total_cop || 0), 0);
});

const totalBsGlobal = computed(() => {
  return filteredCashClosings.value.reduce((acc, c) => acc + parseFloat(c.total_bs || 0), 0);
});

const totalSalesGlobal = computed(() => {
  return filteredCashClosings.value.reduce((acc, c) => {
    return acc + (parseFloat(c.total_usd || 0) + parseFloat(c.total_bs_in_usd || 0) + parseFloat(c.total_cop_in_usd || 0));
  }, 0);
});

// Desgloses Globales por Método (Cierre Diario)
const totalPosBsGlobal = computed(() => {
  return filteredCashClosings.value.reduce((acc, c) => acc + (parseFloat(c.bs_card_debito || 0) + parseFloat(c.bs_card_credit || 0) + parseFloat(c.bs_card_payment_credit || 0)), 0);
});

const totalTransferBsGlobal = computed(() => {
  return filteredCashClosings.value.reduce((acc, c) => acc + (parseFloat(c.bs_transfer || 0) + parseFloat(c.bs_mobile || 0) + parseFloat(c.bs_transfer_payment_credit || 0) + parseFloat(c.bs_mobile_payment_credit || 0)), 0);
});

const totalTransferCopGlobal = computed(() => {
  return filteredCashClosings.value.reduce((acc, c) => acc + (parseFloat(c.cop_transfer || 0) + parseFloat(c.cop_transfer_payment_credit || 0)), 0);
});

const totalTransferUsdGlobal = computed(() => {
  return filteredCashClosings.value.reduce((acc, c) => acc + (parseFloat(c.usd_transfer || 0) + parseFloat(c.usd_paypal || 0) + parseFloat(c.usd_binance || 0) + parseFloat(c.usd_paypal_payment_credit || 0) + parseFloat(c.usd_binance_payment_credit || 0)), 0);
});

const totalCashBsGlobal = computed(() => {
  return filteredCashClosings.value.reduce((acc, c) => acc + (parseFloat(c.bs_cash || 0) + parseFloat(c.bs_cash_payment_credit || 0)), 0);
});

const totalCashCopGlobal = computed(() => {
  return filteredCashClosings.value.reduce((acc, c) => acc + (parseFloat(c.cop_cash || 0) + parseFloat(c.cop_cash_payment_credit || 0)), 0);
});

const totalCashUsdGlobal = computed(() => {
  return filteredCashClosings.value.reduce((acc, c) => acc + (parseFloat(c.usd_cash || 0) + parseFloat(c.usd_cash_payment_credit || 0) + parseFloat(c.usd_conversion || 0)), 0);
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

const ticketStyles = `
  body { font-family: 'Roboto', sans-serif; font-size: 10pt; color: #1a1a1a; margin: 0; padding: 0; }
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
  .signature-section { margin-top: 50px; text-align: center; }
  .signature-box { display: block; border-top: 1.5pt solid #000; width: 250px; margin: 0 auto; padding-top: 8px; }
  .footer-note { margin-top: 30px; text-align: center; font-size: 8pt; color: #7f8c8d; font-style: italic; border-top: 1px solid #eee; padding-top: 10px; }
  .d-none { display: none; }
`;

const downloadReport = async () => {
  try {
    await nextTick();
    const element = document.getElementById("daily-cash-report");
    if (!element) {
      console.error("No se encontró el contenido del reporte.");
      return;
    }

    // Mostrar temporalmente para capturar el HTML con estilos completos
    element.classList.remove("d-none");
    const htmlContent = element.outerHTML;
    element.classList.add("d-none");

    const params = {
      html_content: `<html><head><style>${ticketStyles}</style></head><body>${htmlContent}</body></html>`,
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
    let filename = `Cierre_Diario_${props.cashData.id}.pdf`;
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
    const element = document.getElementById("daily-cash-report");
    if (!element) {
      console.error("No se encontró el contenido del reporte.");
      return;
    }
    
    element.classList.remove("d-none");
    const htmlContent = element.outerHTML;
    element.classList.add("d-none");

    const params = {
      html_content: `<html><head><style>${ticketStyles}</style></head><body>${htmlContent}</body></html>`,
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
  }
};

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
              icon="tabler-calendar-stats"
              size="24"
              color="primary"
            />
          </VAvatar>
          <div class="d-flex flex-column leading-none">
            <h2 class="text-h6 font-weight-black text-white leading-tight mb-0">
              Detalle de Cierre Diario
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
        <!-- RESUMEN GLOBAL DEL DIA -->
        <div class="d-flex align-center gap-2 mb-4">
          <div class="header-indicator primary shadow-sm" />
          <span class="text-subtitle-2 font-weight-black text-high-emphasis uppercase letter-spacing-1">Resumen Consolidado</span>
        </div>

        <VCard
          variant="flat"
          :class="mobile ? 'pa-3' : 'pa-5'"
          class="bg-white rounded-xl border shadow-sm mb-6"
        >
          <VRow :class="mobile ? 'row-gap-2' : 'row-gap-3'">
            <VCol
              cols="6"
              sm="4"
              md="2"
            >
              <div class="d-flex flex-column h-100 justify-center">
                <span class="text-super-xs font-weight-black text-disabled uppercase mb-1">Créditos (CUSD)</span>
                <div class="d-flex align-center gap-1">
                  <VIcon
                    icon="tabler-file-invoice"
                    color="error"
                    size="18"
                  />
                  <h4 class="text-subtitle-2 font-weight-black text-error mb-0">
                    {{ formatCurrency(totalCreditsUsdGlobal, 'USD') }}
                  </h4>
                </div>
              </div>
            </VCol>

            <VCol
              cols="6"
              sm="4"
              md="2"
            >
              <div class="d-flex flex-column h-100 justify-center text-end text-sm-start">
                <span class="text-super-xs font-weight-black text-disabled uppercase mb-1">Dólares (USD)</span>
                <div class="d-flex align-center justify-end justify-sm-start gap-1">
                  <VIcon
                    icon="tabler-currency-dollar"
                    color="primary"
                    size="18"
                  />
                  <h4 class="text-subtitle-2 font-weight-black text-primary mb-0">
                    {{ formatCurrency(totalUsdGlobal, 'USD') }}
                  </h4>
                </div>
              </div>
            </VCol>

            <VCol
              cols="6"
              sm="4"
              md="2"
            >
              <div class="d-flex flex-column h-100 justify-center">
                <span class="text-super-xs font-weight-black text-disabled uppercase mb-1">Pesos (COP)</span>
                <div class="d-flex align-center gap-1">
                  <VIcon
                    icon="tabler-currency-peso"
                    color="success"
                    size="18"
                  />
                  <h4 class="text-subtitle-2 font-weight-black text-success mb-0">
                    {{ formatCurrency(totalCopGlobal, 'COP') }}
                  </h4>
                </div>
              </div>
            </VCol>

            <VCol
              cols="6"
              sm="6"
              md="3"
            >
              <div class="d-flex flex-column h-100 justify-center text-end text-sm-start">
                <span class="text-super-xs font-weight-black text-disabled uppercase mb-1">Bolívares (BS)</span>
                <div class="d-flex align-center justify-end justify-sm-start gap-1">
                  <VIcon
                    icon="tabler-coin"
                    color="warning"
                    size="18"
                  />
                  <h4 class="text-subtitle-2 font-weight-black text-warning mb-0">
                    {{ formatCurrency(totalBsGlobal, 'BS') }}
                  </h4>
                </div>
              </div>
            </VCol>

            <VCol
              cols="12"
              sm="6"
              md="3"
            >
              <VCard
                variant="flat"
                color="primary"
                class="rounded-lg pa-3 text-center h-100 d-flex flex-column justify-center shadow-sm"
              >
                <span class="text-super-xs font-weight-black text-white opacity-80 uppercase d-block mb-1">Venta Total (USD)</span>
                <h4 class="text-subtitle-1 font-weight-black text-white leading-none mb-0">
                  {{ formatCurrency(totalSalesGlobal, 'USD') }}
                </h4>
              </VCard>
            </VCol>
          </VRow>
        </VCard>

        <!-- LISTA DE VENDEDORES -->
        <div class="d-flex align-center gap-2 mb-4">
          <div class="header-indicator secondary shadow-sm" />
          <span class="text-subtitle-2 font-weight-black text-high-emphasis uppercase letter-spacing-1">Arqueo por Cajas</span>
        </div>

        <VRow>
          <VCol
            v-for="closing in filteredCashClosings"
            :key="closing.id"
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
                    {{ closing.seller?.username?.substring(0,2).toUpperCase() }}
                  </VAvatar>
                  <div class="leading-none">
                    <h5 class="text-subtitle-2 font-weight-black mb-1 text-capitalize">
                      {{ closing.seller?.username }}
                    </h5>
                    <span class="text-super-xs text-disabled font-weight-bold uppercase">Caja #{{ closing.id }}</span>
                  </div>
                </div>
                <VChip
                  color="success"
                  size="small"
                  variant="flat"
                  class="font-weight-black shadow-sm"
                >
                  {{ formatCurrency(parseFloat(closing.total_usd || 0) + parseFloat(closing.total_bs_in_usd || 0) + parseFloat(closing.total_cop_in_usd || 0), 'USD') }}
                </VChip>
              </div>

              <VCardText class="pa-0">
                <VTable
                  density="compact"
                  class="text-caption bg-transparent table-standard"
                >
                  <tbody>
                    <tr>
                      <td class="font-weight-black text-disabled uppercase pl-6 py-1">Créditos (CUSD):</td>
                      <td class="text-right font-weight-black pr-6 py-1 text-error">
                        {{ formatCurrency(closing.usd_credit, 'USD') }}
                      </td>
                    </tr>
                    <tr>
                      <td class="font-weight-black text-disabled uppercase pl-6 py-1">USD (Venta):</td>
                      <td class="text-right font-weight-black pr-6 py-1 text-primary">
                        {{ formatCurrency(closing.total_usd, 'USD') }}
                      </td>
                    </tr>
                    <tr>
                      <td class="font-weight-black text-disabled uppercase pl-6 py-1">COP (Venta):</td>
                      <td class="text-right font-weight-black pr-6 py-1 text-success">
                        {{ formatCurrency(closing.total_cop, 'COP') }}
                      </td>
                    </tr>
                    <tr>
                      <td class="font-weight-black text-disabled uppercase pl-6 py-1">Bolívares:</td>
                      <td class="text-right font-weight-bold pr-6 py-1">
                        <span class="text-high-emphasis">{{ formatCurrency(closing.total_bs, 'BS') }}</span>
                      </td>
                    </tr>
                  </tbody>
                </VTable>
              </VCardText>
            </VCard>
          </VCol>

          <VCol
            v-if="filteredCashClosings.length === 0"
            cols="12"
          >
            <VAlert
              type="info"
              variant="tonal"
              class="rounded-xl text-button font-weight-black"
              icon="tabler-info-circle"
            >
              NO SE ENCONTRARON CAJAS CON VENTAS REGISTRADAS
            </VAlert>
          </VCol>
        </VRow>

        <!-- ESTRUCTURA OCULTA PARA EL REPORTE PDF (ESTILO SOCIAL BENEFITS) -->
        <div
          id="daily-cash-report"
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
              CIERRE CONSOLIDADO DE OPERACIONES
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
                  <strong>TASAS:</strong> BCV: {{ props.cashData.exchange_rate }} Bs
                </td>
                <td style="inline-size: 30%; text-align: end;">
                  <strong>EMISIÓN:</strong> {{ formatDateTime(new Date(), 'date') }}
                </td>
              </tr>
              <tr>
                <td><strong>VENTA TOTAL:</strong> {{ formatCurrency(totalSalesGlobal, 'USD') }}</td>
                <td>COP: {{ props.cashData.cop_exchange_rate }} COP</td>
                <td style="text-align: end;">
                  <strong>HORA:</strong> {{ getCurrentTime() }}
                </td>
              </tr>
            </table>
          </div>

          <div class="section-header">
            DESGLOSE POR PERSONAL
          </div>

          <table class="data-table">
            <thead>
              <tr>
                <th style="padding: 4px; font-size: 8pt;">RESPONSABLE</th>
                <th style="padding: 4px; font-size: 8pt; text-align: end;">CUSD</th>
                <th style="padding: 4px; font-size: 8pt; text-align: end;">USD</th>
                <th style="padding: 4px; font-size: 8pt; text-align: end;">COP</th>
                <th style="padding: 4px; font-size: 8pt; text-align: end;">Bs.</th>
                <th style="padding: 4px; font-size: 8pt; text-align: end;">TOTAL USD</th>
              </tr>
            </thead>
            <tbody>
              <template
                v-for="(cash, index) in filteredCashClosings"
                :key="cash.id"
              >
                <tr class="total-row">
                  <td style="padding: 4px; font-size: 8pt;">
                    {{ index + 1 }}. {{ (cash.seller?.username || 'Sin Nombre').toUpperCase() }}
                  </td>
                  <td style="padding: 4px; font-size: 8pt; text-align: end;">
                    {{ formatCurrency(cash.usd_credit, 'USD') }}
                  </td>
                  <td style="padding: 4px; font-size: 8pt; text-align: end;">
                    {{ formatCurrency(cash.total_usd, 'USD') }}
                  </td>
                  <td style="padding: 4px; font-size: 8pt; text-align: end;">
                    {{ formatCurrency(cash.total_cop, 'COP') }}
                  </td>
                  <td style="padding: 4px; font-size: 8pt; text-align: end;">
                    {{ formatCurrency(cash.total_bs, 'BS') }}
                  </td>
                  <td style="padding: 4px; font-size: 8pt; text-align: end;">
                    {{ formatCurrency(cash.real_total_usd, 'USD') }}
                  </td>
                </tr>
                
                <!-- POS -->
                <tr v-if="(parseFloat(cash.bs_card_debito || 0) + parseFloat(cash.bs_card_credit || 0) + parseFloat(cash.bs_card_payment_credit || 0)) > 0">
                  <td style="font-size: 7pt; padding-block: 2px; padding-inline: 15px 0;">
                    POS (TD y TC)
                  </td>
                  <td style="font-size: 7pt; text-align: end;">{{ formatCurrency(0, 'USD') }}</td> <!-- CUSD -->
                  <td style="font-size: 7pt; text-align: end;">{{ formatCurrency(0, 'USD') }}</td> <!-- USD -->
                  <td style="font-size: 7pt; text-align: end;">{{ formatCurrency(0, 'COP') }}</td> <!-- COP -->
                  <td style="font-size: 7pt; text-align: end;">
                    {{ formatCurrency(parseFloat(cash.bs_card_debito || 0) + parseFloat(cash.bs_card_credit || 0) + parseFloat(cash.bs_card_payment_credit || 0), 'BS') }}
                  </td>
                  <td style="font-size: 7pt; text-align: end;">
                    {{ formatCurrency((parseFloat(cash.bs_card_debito || 0) + parseFloat(cash.bs_card_credit || 0) + parseFloat(cash.bs_card_payment_credit || 0)) / parseFloat(props.cashData.exchange_rate || 1), 'USD') }}
                  </td>
                </tr>

                <!-- Transferencia -->
                <tr v-if="(parseFloat(cash.bs_transfer || 0) + parseFloat(cash.bs_mobile || 0) + parseFloat(cash.cop_transfer || 0) + parseFloat(cash.usd_transfer || 0) + parseFloat(cash.usd_paypal || 0) + parseFloat(cash.usd_binance || 0) + parseFloat(cash.bs_transfer_payment_credit || 0) + parseFloat(cash.bs_mobile_payment_credit || 0) + parseFloat(cash.cop_transfer_payment_credit || 0) + parseFloat(usd_paypal_payment_credit || 0) + parseFloat(usd_binance_payment_credit || 0)) > 0">
                  <td style="font-size: 7pt; padding-block: 2px; padding-inline: 15px 0;">
                    TRANSFERENCIA (PAGO MÓVIL / BANCOS)
                  </td>
                  <td style="font-size: 7pt; text-align: end;">{{ formatCurrency(0, 'USD') }}</td> <!-- CUSD -->
                  <td style="font-size: 7pt; text-align: end;">
                    {{ formatCurrency(parseFloat(cash.usd_transfer || 0) + parseFloat(cash.usd_paypal || 0) + parseFloat(cash.usd_binance || 0) + parseFloat(cash.usd_paypal_payment_credit || 0) + parseFloat(cash.usd_binance_payment_credit || 0), 'USD') }}
                  </td>
                  <td style="font-size: 7pt; text-align: end;">
                    {{ formatCurrency(parseFloat(cash.cop_transfer || 0) + parseFloat(cash.cop_transfer_payment_credit || 0), 'COP') }}
                  </td>
                  <td style="font-size: 7pt; text-align: end;">
                    {{ formatCurrency(parseFloat(cash.bs_transfer || 0) + parseFloat(cash.bs_mobile || 0) + parseFloat(cash.bs_transfer_payment_credit || 0) + parseFloat(cash.bs_mobile_payment_credit || 0), 'BS') }}
                  </td>
                  <td style="font-size: 7pt; text-align: end;">
                    {{ formatCurrency((parseFloat(cash.usd_transfer || 0) + parseFloat(cash.usd_paypal || 0) + parseFloat(cash.usd_binance || 0) + parseFloat(cash.usd_paypal_payment_credit || 0) + parseFloat(cash.usd_binance_payment_credit || 0)) + (parseFloat(cash.bs_transfer || 0) + parseFloat(cash.bs_mobile || 0) + parseFloat(cash.bs_transfer_payment_credit || 0) + parseFloat(cash.bs_mobile_payment_credit || 0)) / parseFloat(props.cashData.exchange_rate || 1) + (parseFloat(cash.cop_transfer || 0) + parseFloat(cash.cop_transfer_payment_credit || 0)) / parseFloat(props.cashData.cop_exchange_rate || 1), 'USD') }}
                  </td>
                </tr>

                <!-- Efectivo -->
                <tr v-if="(parseFloat(cash.bs_cash || 0) + parseFloat(cash.cop_cash || 0) + parseFloat(cash.usd_cash || 0) + parseFloat(cash.bs_cash_payment_credit || 0) + parseFloat(cash.cop_cash_payment_credit || 0) + parseFloat(cash.usd_cash_payment_credit || 0) + parseFloat(cash.usd_conversion || 0)) > 0">
                  <td style="font-size: 7pt; padding-block: 2px; padding-inline: 15px 0;">
                    EN EFECTIVO (FONDO FÍSICO)
                  </td>
                  <td style="font-size: 7pt; text-align: end;">{{ formatCurrency(0, 'USD') }}</td> <!-- CUSD -->
                  <td style="font-size: 7pt; text-align: end;">
                    {{ formatCurrency(parseFloat(cash.usd_cash || 0) + parseFloat(cash.usd_cash_payment_credit || 0) + parseFloat(cash.usd_conversion || 0), 'USD') }}
                  </td>
                  <td style="font-size: 7pt; text-align: end;">
                    {{ formatCurrency(parseFloat(cash.cop_cash || 0) + parseFloat(cash.cop_cash_payment_credit || 0), 'COP') }}
                  </td>
                  <td style="font-size: 7pt; text-align: end;">
                    {{ formatCurrency(parseFloat(cash.bs_cash || 0) + parseFloat(cash.bs_cash_payment_credit || 0), 'BS') }}
                  </td>
                  <td style="font-size: 7pt; text-align: end;">
                    {{ formatCurrency((parseFloat(cash.usd_cash || 0) + parseFloat(cash.usd_cash_payment_credit || 0) + parseFloat(cash.usd_conversion || 0)) + (parseFloat(cash.bs_cash || 0) + parseFloat(cash.bs_cash_payment_credit || 0)) / parseFloat(props.cashData.exchange_rate || 1) + (parseFloat(cash.cop_cash || 0) + parseFloat(cash.cop_cash_payment_credit || 0)) / parseFloat(props.cashData.cop_exchange_rate || 1), 'USD') }}
                  </td>
                </tr>
                <tr>
                  <td
                    colspan="6"
                    style="padding: 4px; border-block-end: 1px solid #dee2e6; color: #7f8c8d; font-size: 7pt;"
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
                  {{ formatCurrency(totalCreditsUsdGlobal, 'USD') }}
                </td>
                <td style="padding: 6px; font-size: 9pt; text-align: end;">
                  {{ formatCurrency(totalUsdGlobal, 'USD') }}
                </td>
                <td style="padding: 6px; font-size: 9pt; text-align: end;">
                  {{ formatCurrency(totalCopGlobal, 'COP') }}
                </td>
                <td style="padding: 6px; font-size: 9pt; text-align: end;">
                  {{ formatCurrency(totalBsGlobal, 'BS') }}
                </td>
                <td style="padding: 6px; font-size: 9pt; text-align: end;">
                  {{ formatCurrency(totalSalesGlobal, 'USD') }}
                </td>
              </tr>

              <!-- Detalles del Total General -->
              <tr style="background-color: #f8f9fa;">
                <td style="font-size: 8pt; font-weight: bold; padding-inline-start: 10px;">
                  DETALLE TOTAL POS
                </td>
                <td style="font-size: 8pt; text-align: end;">{{ formatCurrency(0, 'USD') }}</td> <!-- CUSD -->
                <td style="font-size: 8pt; text-align: end;">{{ formatCurrency(0, 'USD') }}</td> <!-- USD -->
                <td style="font-size: 8pt; text-align: end;">{{ formatCurrency(0, 'COP') }}</td> <!-- COP -->
                <td style="font-size: 8pt; text-align: end;">
                  {{ formatCurrency(totalPosBsGlobal, 'BS') }}
                </td>
                <td style="font-size: 8pt; font-weight: bold; padding-inline-end: 5px; text-align: end;">
                  {{ formatCurrency(totalPosEquivalentUsd, 'USD') }}
                </td>
              </tr>
              <tr style="background-color: #f8f9fa;">
                <td style="font-size: 8pt; font-weight: bold; padding-inline-start: 10px;">
                  DETALLE TOTAL TRANSFERENCIA
                </td>
                <td style="font-size: 8pt; text-align: end;">{{ formatCurrency(0, 'USD') }}</td> <!-- CUSD -->
                <td style="font-size: 8pt; text-align: end;">
                  {{ formatCurrency(totalTransferUsdGlobal, 'USD') }}
                </td>
                <td style="font-size: 8pt; text-align: end;">
                  {{ formatCurrency(totalTransferCopGlobal, 'COP') }}
                </td>
                <td style="font-size: 8pt; text-align: end;">
                  {{ formatCurrency(totalTransferBsGlobal, 'BS') }}
                </td>
                <td style="font-size: 8pt; font-weight: bold; padding-inline-end: 5px; text-align: end;">
                  {{ formatCurrency(totalTransferEquivalentUsd, 'USD') }}
                </td>
              </tr>
              <tr style="background-color: #f8f9fa;">
                <td style="font-size: 8pt; font-weight: bold; padding-inline-start: 10px;">
                  DETALLE TOTAL EFECTIVO
                </td>
                <td style="font-size: 8pt; text-align: end;">{{ formatCurrency(0, 'USD') }}</td> <!-- CUSD -->
                <td style="font-size: 8pt; text-align: end;">
                  {{ formatCurrency(totalCashUsdGlobal, 'USD') }}
                </td>
                <td style="font-size: 8pt; text-align: end;">
                  {{ formatCurrency(totalCashCopGlobal, 'COP') }}
                </td>
                <td style="font-size: 8pt; text-align: end;">
                  {{ formatCurrency(totalCashBsGlobal, 'BS') }}
                </td>
                <td style="font-size: 8pt; font-weight: bold; padding-inline-end: 5px; text-align: end;">
                  {{ formatCurrency(totalCashEquivalentUsd, 'USD') }}
                </td>
              </tr>
            </tbody>
          </table>

          <div class="signature-section">
            <div class="signature-box">
              <div style=" color: #000;font-size: 10pt; font-weight: bold;">
                FIRMA SUPERVISOR
              </div>
              <small style=" color: #666;font-size: 8pt;">CONTROL DE TURNO / VERIFICACIÓN</small>
            </div>
          </div>

          <div class="footer-note">
            ESTE DOCUMENTO ES UN INSTRUMENTO DE CONTROL FINANCIERO INSTITUCIONAL. LA HORA DE CIERRE REGISTRADA ES AUDITABLE.
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
              Descargar Resumen PDF
            </VBtn>
          </VCol>
        </VRow>
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
  background-color: #f8fafc !important; /* Un gris azulado muy suave que contrasta con el blanco y el gris claro */
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
