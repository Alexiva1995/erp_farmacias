<script setup>
import DailyCashReportTemplate from "@/components/dialogs/DailyCashReportTemplate.vue";
import DailyCashSellerTable from "@/components/dialogs/DailyCashSellerTable.vue";
import axios from "@/plugins/axios";
import { formatCurrency } from "@/utils/currencyFormatter";
import { formatDateTime } from "@/utils/formatDateTime";
import { computed, nextTick, ref } from "vue";
import { useDisplay } from "vuetify";

const { mobile } = useDisplay();
const isDownloading = ref(false);

const props = defineProps({
  isDialogVisible: {
    type: Boolean,
    required: true,
  },
  cashData: {
    type: Object,
    default: () => ({}),
  },
  tpvPaymentMethods: {
    type: Object,
    default: () => ({ COP: [], USD: [], BS: [] })
  }
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
        parseFloat(closing.cop_cash || 0) + parseFloat(closing.cop_transfer || 0) + parseFloat(closing.cop_delivered || 0) +
        parseFloat(closing.cop_cash_payment_credit || 0) + parseFloat(closing.cop_transfer_payment_credit || 0);
      
      const usdSum = 
        parseFloat(closing.usd_cash || 0) + parseFloat(closing.usd_transfer || 0) + parseFloat(closing.usd_delivered || 0) +
        parseFloat(closing.usd_paypal || 0) + parseFloat(closing.usd_binance || 0) +
        parseFloat(closing.usd_credit || 0) +
        parseFloat(closing.usd_cash_payment_credit || 0) + parseFloat(closing.usd_paypal_payment_credit || 0) +
        parseFloat(closing.usd_binance_payment_credit || 0) + parseFloat(closing.usd_conversion || 0);

      const bcvRate = parseFloat(props.cashData?.exchange_rate || 0) || 1;
      const copRate = parseFloat(props.cashData?.cop_exchange_rate || 0) || 4000;

      // Calculamos el equivalente real en USD usando las tasas
      const bsInUsd = bcvRate > 0 ? (bsSum / bcvRate) : 0;
      const copInUsd = copRate > 0 ? (copSum / copRate) : 0;
      const totalUsdEq = usdSum + bsInUsd + copInUsd;
      const totalSales = parseFloat(closing.total_sales || 0);

      const hasActivity = bsSum > 0 || copSum > 0 || usdSum > 0 || totalSales > 0 || (closing.orders && closing.orders.length > 0);

      return {
        ...closing,
        real_bs: bsSum,
        real_cop: copSum,
        real_usd: usdSum,
        real_total_usd: totalUsdEq > 0 ? totalUsdEq : totalSales,
        has_activity: hasActivity
      };
    })
    .filter((c) => c.has_activity || c.real_total_usd > 0);
});

const filteredCashClosings = processedClosings;

const totalCreditsUsdGlobal = computed(() => {
  return filteredCashClosings.value.reduce((acc, c) => acc + parseFloat(c.usd_credit || 0), 0);
});

const totalUsdGlobal = computed(() => {
  return filteredCashClosings.value.reduce((acc, c) => acc + parseFloat(c.usd_delivered || 0), 0);
});

const totalCopGlobal = computed(() => {
  return filteredCashClosings.value.reduce((acc, c) => acc + (parseFloat(c.cop_delivered || 0) + parseFloat(c.cop_transfer || 0)), 0);
});

const totalBsGlobal = computed(() => {
  return filteredCashClosings.value.reduce((acc, c) => acc + parseFloat(c.total_bs || 0), 0);
});

const totalSalesGlobal = computed(() => {
  return filteredCashClosings.value.reduce((acc, c) => acc + parseFloat(c.total_sales || 0), 0);
});

const computedBcvRate = computed(() => {
  if (props.cashData?.exchange_rate) return parseFloat(props.cashData.exchange_rate);
  if (filteredCashClosings.value.length > 0) {
    return parseFloat(filteredCashClosings.value[0].exchange_rate || 1);
  }
  return 1;
});

const computedCopRate = computed(() => {
  if (props.cashData?.cop_exchange_rate) return parseFloat(props.cashData.cop_exchange_rate);
  if (filteredCashClosings.value.length > 0) {
    return parseFloat(filteredCashClosings.value[0].cop_exchange_rate || 1);
  }
  return 1;
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
  return filteredCashClosings.value.reduce((acc, c) => acc + parseFloat(c.bs_delivered || 0), 0);
});

const totalCashCopGlobal = computed(() => {
  return filteredCashClosings.value.reduce((acc, c) => acc + parseFloat(c.cop_delivered || 0), 0);
});

const totalCashUsdGlobal = computed(() => {
  return filteredCashClosings.value.reduce((acc, c) => acc + (parseFloat(c.usd_cash || 0) + parseFloat(c.usd_cash_payment_credit || 0)), 0);
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
  .data-table th { font-weight: bold; text-align: left; }
  .data-table thead tr:not([style*="background-color"]) th { background-color: #f8f9fa; }
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
  if (isDownloading.value) return;
  isDownloading.value = true;
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
      html_content: `<!DOCTYPE html><html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"/><style>${ticketStyles}</style></head><body>${htmlContent}</body></html>`,
      filename: "Resumen_Cajas_Diario",
    };

    const response = await axios.post(
      "/finances/cash-closure/downloadReport",
      params,
      {
        responseType: "blob",
      }
    );

    // Si el servidor devolvió un error (ej. JSON de error o respuesta corta)
    if (response.data.size < 200) {
      const text = await response.data.text();
      console.error("Respuesta del servidor no es un PDF válido:", text);
      if (text.includes("message") || text.includes("error")) {
        alert("Error del servidor: " + text);
        return;
      }
    }

    const blob = new Blob([response.data], { type: "application/pdf" });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement("a");
    let filename = `Cierre_Diario_${props.cashData?.id || 'reporte'}.pdf`;
    link.href = url;
    link.setAttribute("download", filename);
    document.body.appendChild(link);
    link.click();
    link.remove();
    setTimeout(() => {
      window.URL.revokeObjectURL(url);
    }, 1000);
    closeModal();
  } catch (error) {
    console.error("Error al descargar el PDF:", error);
  } finally {
    isDownloading.value = false;
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
      html_content: `<!DOCTYPE html><html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"/><style>${ticketStyles}</style></head><body>${htmlContent}</body></html>`,
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
const formatUsername = (username) => {
  if (!username) return "—";
  const parts = username
    .replace(/[._]/g, " ")
    .split(" ")
    .filter(word => word.length > 0);
  return parts.slice(0, 2)
    .map((word) => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase())
    .join(" ");
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

        <!-- Título sección Arqueo (compartido desktop y mobile) -->
        <div class="d-flex align-center gap-2 mb-3">
          <div class="header-indicator secondary shadow-sm" />
          <span class="text-subtitle-2 font-weight-black text-high-emphasis uppercase letter-spacing-1">Arqueo por Cajas</span>
        </div>

        <!-- ── TABLA Y TARJETAS DE ARQUEO POR CAJAS DESACOPLADA ── -->
        <DailyCashSellerTable
          :filtered-cash-closings="filteredCashClosings"
          :tpv-payment-methods="props.tpvPaymentMethods"
          :total-credits-usd-global="totalCreditsUsdGlobal"
          :total-usd-global="totalUsdGlobal"
          :total-cop-global="totalCopGlobal"
          :total-bs-global="totalBsGlobal"
          :total-sales-global="totalSalesGlobal"
        />


        <!-- ESTRUCTURA DESACOPLADA PARA EL REPORTE PDF / IMPRESIÓN -->
        <DailyCashReportTemplate
          :cash-data="props.cashData"
          :filtered-cash-closings="filteredCashClosings"
          :computed-bcv-rate="computedBcvRate"
          :computed-cop-rate="computedCopRate"
          :total-sales-global="totalSalesGlobal"
          :total-credits-usd-global="totalCreditsUsdGlobal"
          :total-usd-global="totalUsdGlobal"
          :total-cop-global="totalCopGlobal"
          :total-bs-global="totalBsGlobal"
          :total-cash-usd-global="totalCashUsdGlobal"
          :total-transfer-usd-global="totalTransferUsdGlobal"
          :total-cash-cop-global="totalCashCopGlobal"
          :total-transfer-cop-global="totalTransferCopGlobal"
          :total-cash-bs-global="totalCashBsGlobal"
          :total-pos-bs-global="totalPosBsGlobal"
          :total-transfer-bs-global="totalTransferBsGlobal"
        />
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
              :loading="isDownloading"
              :disabled="isDownloading"
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
