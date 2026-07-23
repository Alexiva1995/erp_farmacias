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
  tpvPaymentMethods: {
    type: Object,
    default: () => ({ COP: [], USD: [], BS: [] })
  }
});

const emit = defineEmits(["update:isDialogVisible"]);

const fmtUsd = (val) => {
  try {
    const num = parseFloat(val);
    if (isNaN(num)) return "0,00 USD";
    return new Intl.NumberFormat("es-VE", {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    }).format(num) + " USD";
  } catch (e) {
    return "0,00 USD";
  }
};

const fmtCop = (val) => {
  try {
    const num = parseFloat(val);
    if (isNaN(num)) return "0 COP";
    return Math.round(num)
      .toString()
      .replace(/\B(?=(\d{3})+(?!\d))/g, ".") + " COP";
  } catch (e) {
    return "0 COP";
  }
};

const fmtBs = (val) => {
  try {
    const num = parseFloat(val);
    if (isNaN(num)) return "0,00 Bs.";
    return new Intl.NumberFormat("es-VE", {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    }).format(num) + " Bs.";
  } catch (e) {
    return "0,00 Bs.";
  }
};

const getPaymentDetail = (item, currency) => {
  if (!item) return [];
  const details = [];
  
  let rawMethods = props.tpvPaymentMethods;
  if (typeof rawMethods === 'string') {
    try {
      rawMethods = JSON.parse(rawMethods);
    } catch (e) {
      rawMethods = {};
    }
  }
  const methods = rawMethods || {};
  
  if (currency === 'USD') {
    const currencyObj = methods.USD || {};
    const activeMethods = Array.isArray(currencyObj.methods) ? currencyObj.methods : [];
    
    activeMethods.forEach(m => {
      let val = 0;
      if (m.value === 'cash') val = parseFloat(item.usd_cash || 0);
      else if (m.value === 'bank_transfer') val = parseFloat(item.usd_transfer || 0);
      else if (m.value === 'paypal') val = parseFloat(item.usd_paypal || 0);
      else if (m.value === 'binance') val = parseFloat(item.usd_binance || 0);
      else if (m.value === 'credit') val = parseFloat(item.usd_credit || 0);
      
      if (val > 0) {
        details.push({ label: m.label || m.value, value: fmtUsd(val) });
      }
    });
  } else if (currency === 'COP') {
    const currencyObj = methods.COP || {};
    const activeMethods = Array.isArray(currencyObj.methods) ? currencyObj.methods : [];
    
    activeMethods.forEach(m => {
      let val = 0;
      if (m.value === 'cash') val = parseFloat(item.cop_cash || 0);
      else if (m.value === 'bank_transfer') val = parseFloat(item.cop_transfer || 0);
      
      if (val > 0) {
        details.push({ label: m.label || m.value, value: fmtCop(val) });
      }
    });

    if (parseFloat(item.cop_cash_payment_credit || 0) > 0) {
      details.push({ label: "Abono Crédito", value: fmtCop(item.cop_cash_payment_credit) });
    }
    if (parseFloat(item.cop_spare || 0) > 0) {
      details.push({ label: "Sobrante", value: fmtCop(item.cop_spare) });
    }
  } else if (currency === 'BS') {
    const currencyObj = methods.BS || {};
    const activeMethods = Array.isArray(currencyObj.methods) ? currencyObj.methods : [];
    
    activeMethods.forEach(m => {
      let val = 0;
      if (m.value === 'cash_bs') val = parseFloat(item.bs_cash || 0);
      else if (m.value === 'mobile_payment') val = parseFloat(item.bs_mobile || 0);
      else if (m.value === 'debit_card') val = parseFloat(item.bs_card_debito || 0);
      else if (m.value === 'credit_card') val = parseFloat(item.bs_card_credit || 0);
      else if (m.value === 'bank_transfer_bs') val = parseFloat(item.bs_transfer || 0);
      
      if (val > 0) {
        details.push({ label: m.label || m.value, value: fmtBs(val) });
      }
    });
  }
  return details;
};

const hasMismatchForCurrency = (closing, currency) => {
  if (!closing || !closing.blind_mismatches) return false;
  const mismatches = Array.isArray(closing.blind_mismatches) 
    ? closing.blind_mismatches 
    : (typeof closing.blind_mismatches === 'string' ? JSON.parse(closing.blind_mismatches) : []);
  
  if (currency === 'USD') {
    return mismatches.some(m => m.includes('usd') || m === 'declared_usd');
  }
  if (currency === 'COP') {
    // Solo es descuadre si hay un faltante (declarado menor que teórico)
    const sysCop = parseFloat(closing.cop_cash || 0) + parseFloat(closing.cop_cash_payment_credit || 0);
    const decCop = parseFloat(closing.declared_cop || 0);
    return decCop < sysCop && mismatches.some(m => m.includes('cop') || m === 'declared_cop');
  }
  if (currency === 'BS') {
    return mismatches.some(m => m.includes('bs') || m.includes('card') || m.includes('mobile'));
  }
  if (currency === 'CREDIT') {
    return mismatches.some(m => m.includes('credit') || m === 'declared_credit');
  }
  return false;
};

const getMismatchText = (closing, currency) => {
  if (!closing || !closing.blind_note) return "Sin detalles del descuadre.";
  // Separamos las notas por tubería si es que las hay
  const notes = closing.blind_note.split('|');
  const matchedNotes = notes.filter(note => {
    const noteUpper = note.toUpperCase();
    if (currency === 'USD') return noteUpper.includes('USD') || noteUpper.includes('DÓLAR') || noteUpper.includes('DOLAR');
    if (currency === 'COP') return noteUpper.includes('COP') || noteUpper.includes('PESO');
    if (currency === 'BS') return noteUpper.includes('BS') || noteUpper.includes('BOLÍVAR') || noteUpper.includes('BOLIVAR') || noteUpper.includes('TARJETA') || noteUpper.includes('PAGO MÓVIL') || noteUpper.includes('PAGO MOVIL');
    if (currency === 'CREDIT') return noteUpper.includes('CRÉDITO') || noteUpper.includes('CREDITO');
    return false;
  });
  
  return matchedNotes.length > 0 ? matchedNotes.join(' | ').trim() : closing.blind_note;
};


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

      const bcvRate = parseFloat(props.cashData?.exchange_rate || 1);
      const copRate = parseFloat(props.cashData?.cop_exchange_rate || 1);

      // Calculamos el equivalente real en USD usando las tasas
      const bsInUsd = bcvRate > 0 ? (bsSum / bcvRate) : 0;
      const copInUsd = copRate > 0 ? (copSum / copRate) : 0;
      const totalUsdEq = usdSum + bsInUsd + copInUsd;

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
  return filteredCashClosings.value.reduce((acc, c) => acc + parseFloat(c.usd_delivered || 0), 0);
});

const totalCopGlobal = computed(() => {
  return filteredCashClosings.value.reduce((acc, c) => acc + parseFloat(c.cop_delivered || 0), 0);
});

const totalBsGlobal = computed(() => {
  return filteredCashClosings.value.reduce((acc, c) => acc + parseFloat(c.total_bs || 0), 0);
});

const totalSalesGlobal = computed(() => {
  return filteredCashClosings.value.reduce((acc, c) => acc + parseFloat(c.total_sales || 0), 0);
});

// Tasas computadas seguras para el reporte impreso
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

        <!-- ── DESKTOP: Tabla Arqueo de Cajas (Misma estructura de SellerBoxTable) ────────────────────── -->
        <VCard variant="flat" class="rounded-xl border shadow-sm mb-5 overflow-hidden d-none d-sm-block">
          <VTable density="compact" class="text-no-wrap premium-table">
            <thead>
              <tr class="bg-grey-lighten-4">
                <th class="text-uppercase text-caption font-weight-black text-disabled">Vendedor</th>
                <th class="text-uppercase text-caption font-weight-black text-disabled text-end">Crédito</th>
                <th class="text-uppercase text-caption font-weight-black text-disabled text-end">USD</th>
                <th class="text-uppercase text-caption font-weight-black text-disabled text-end">COP</th>
                <th class="text-uppercase text-caption font-weight-black text-disabled text-end">Bs.</th>
                <th class="text-uppercase text-caption font-weight-black text-disabled text-end">Total</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="filteredCashClosings.length === 0">
                <td colspan="6" class="text-center text-caption text-disabled py-4">No hay cajas con ventas registradas</td>
              </tr>
              <tr v-for="c in filteredCashClosings" :key="c.id" class="seller-row">
                <td class="py-2">
                  <div class="d-flex align-center gap-3">
                    <VAvatar size="32" color="primary" variant="tonal" class="rounded-lg font-weight-black text-xs">
                      {{ (c.seller?.username || '?').charAt(0).toUpperCase() }}
                    </VAvatar>
                    <div class="d-flex flex-column leading-none">
                      <span class="text-sm font-weight-black text-primary text-capitalize">{{ formatUsername(c.seller?.username) }}</span>
                      <span class="text-super-xs text-disabled font-weight-bold uppercase">ID: {{ c.id }}</span>
                    </div>
                  </div>
                </td>
                <td class="text-end">
                  <span class="text-sm font-weight-bold text-error">{{ fmtUsd(c.usd_credit) }}</span>
                </td>
                <td class="text-end">
                  <div class="d-flex align-center justify-end gap-1">
                    <span class="text-sm font-weight-bold">{{ fmtUsd(c.usd_delivered) }}</span>
                    <!-- Icono de Descuadre en Rojo si aplica -->
                    <VMenu v-if="hasMismatchForCurrency(c, 'USD')" close-on-content-click location="top">
                      <template #activator="{ props: menuProps }">
                        <VIcon v-bind="menuProps" icon="tabler-alert-triangle" size="15" color="error" class="cursor-pointer me-1 animate-pulse" />
                      </template>
                      <VCard class="pa-3 text-xs bg-error-lighten-5 border-error" min-width="220">
                        <div class="font-weight-black text-error mb-1 d-flex align-center gap-1">
                          <VIcon icon="tabler-alert-triangle" size="14" />
                          <span>DESCUADRE USD DETECTADO:</span>
                        </div>
                        <div class="text-disabled font-weight-medium" style="line-height: 1.3;">{{ getMismatchText(c, 'USD') }}</div>
                      </VCard>
                    </VMenu>

                    <!-- Icono de Detalles de Método (Info) -->
                    <VMenu v-if="getPaymentDetail(c, 'USD').length > 1" open-on-hover close-on-content-click location="top">
                      <template #activator="{ props: menuProps }">
                        <VIcon v-bind="menuProps" icon="tabler-info-circle" size="14" class="text-disabled cursor-pointer" />
                      </template>
                      <VCard class="pa-2 text-xs" min-width="160">
                        <div v-for="det in getPaymentDetail(c, 'USD')" :key="det.label" class="d-flex justify-space-between py-1 border-bottom">
                          <span class="font-weight-medium me-4 uppercase text-disabled text-super-xs">{{ det.label }}:</span>
                          <span class="font-weight-black">{{ det.value }}</span>
                        </div>
                      </VCard>
                    </VMenu>
                  </div>
                </td>
                <td class="text-end">
                  <div class="d-flex align-center justify-end gap-1">
                    <span class="text-sm font-weight-bold text-success">{{ fmtCop(c.cop_delivered) }}</span>
                    <!-- Icono de Descuadre en Rojo si aplica -->
                    <VMenu v-if="hasMismatchForCurrency(c, 'COP')" close-on-content-click location="top">
                      <template #activator="{ props: menuProps }">
                        <VIcon v-bind="menuProps" icon="tabler-alert-triangle" size="15" color="error" class="cursor-pointer me-1 animate-pulse" />
                      </template>
                      <VCard class="pa-3 text-xs bg-error-lighten-5 border-error" min-width="220">
                        <div class="font-weight-black text-error mb-1 d-flex align-center gap-1">
                          <VIcon icon="tabler-alert-triangle" size="14" />
                          <span>DESCUADRE COP DETECTADO:</span>
                        </div>
                        <div class="text-disabled font-weight-medium" style="line-height: 1.3;">{{ getMismatchText(c, 'COP') }}</div>
                      </VCard>
                    </VMenu>

                    <!-- Icono de Detalles de Método (Info) -->
                    <VMenu v-if="getPaymentDetail(c, 'COP').length > 1" open-on-hover close-on-content-click location="top">
                      <template #activator="{ props: menuProps }">
                        <VIcon v-bind="menuProps" icon="tabler-info-circle" size="14" class="text-disabled cursor-pointer" />
                      </template>
                      <VCard class="pa-2 text-xs" min-width="160">
                        <div v-for="det in getPaymentDetail(c, 'COP')" :key="det.label" class="d-flex justify-space-between py-1 border-bottom">
                          <span class="font-weight-medium me-4 uppercase text-disabled text-super-xs">{{ det.label }}:</span>
                          <span class="font-weight-black">{{ det.value }}</span>
                        </div>
                      </VCard>
                    </VMenu>
                  </div>
                </td>
                <td class="text-end">
                  <div class="d-flex align-center justify-end gap-1">
                    <span class="text-sm font-weight-bold text-warning">{{ fmtBs(c.total_bs) }}</span>
                    <!-- Icono de Descuadre en Rojo si aplica -->
                    <VMenu v-if="hasMismatchForCurrency(c, 'BS')" close-on-content-click location="top">
                      <template #activator="{ props: menuProps }">
                        <VIcon v-bind="menuProps" icon="tabler-alert-triangle" size="15" color="error" class="cursor-pointer me-1 animate-pulse" />
                      </template>
                      <VCard class="pa-3 text-xs bg-error-lighten-5 border-error" min-width="220">
                        <div class="font-weight-black text-error mb-1 d-flex align-center gap-1">
                          <VIcon icon="tabler-alert-triangle" size="14" />
                          <span>DESCUADRE BS DETECTADO:</span>
                        </div>
                        <div class="text-disabled font-weight-medium" style="line-height: 1.3;">{{ getMismatchText(c, 'BS') }}</div>
                      </VCard>
                    </VMenu>

                    <!-- Icono de Detalles de Método (Info) -->
                    <VMenu v-if="getPaymentDetail(c, 'BS').length > 1" open-on-hover close-on-content-click location="top">
                      <template #activator="{ props: menuProps }">
                        <VIcon v-bind="menuProps" icon="tabler-info-circle" size="14" class="text-disabled cursor-pointer" />
                      </template>
                      <VCard class="pa-2 text-xs" min-width="160">
                        <div v-for="det in getPaymentDetail(c, 'BS')" :key="det.label" class="d-flex justify-space-between py-1 border-bottom">
                          <span class="font-weight-medium me-4 uppercase text-disabled text-super-xs">{{ det.label }}:</span>
                          <span class="font-weight-black">{{ det.value }}</span>
                        </div>
                      </VCard>
                    </VMenu>
                  </div>
                </td>
                <td class="text-end">
                  <VChip size="small" variant="flat" color="primary" class="font-weight-black rounded px-2">
                    {{ fmtUsd(c.total_sales) }}
                  </VChip>
                </td>
              </tr>
              <!-- Fila de totales (Consolidado más visible y grande) -->
              <tr v-if="filteredCashClosings.length > 0" class="bg-grey-lighten-3 font-weight-black" style="font-size: 0.95rem;">
                <td class="uppercase py-3 pl-3 text-primary text-subtitle-2 font-weight-black">TOTAL CONSOLIDADO</td>
                <td class="text-end text-error">{{ fmtUsd(totalCreditsUsdGlobal) }}</td>
                <td class="text-end">{{ fmtUsd(totalUsdGlobal) }}</td>
                <td class="text-end text-success">{{ fmtCop(totalCopGlobal) }}</td>
                <td class="text-end text-warning">{{ fmtBs(totalBsGlobal) }}</td>
                <td class="text-end">
                  <VChip size="medium" variant="flat" color="primary" class="font-weight-black rounded px-3 text-subtitle-2 py-1">
                    {{ fmtUsd(totalSalesGlobal) }}
                  </VChip>
                </td>
              </tr>
            </tbody>
          </VTable>
        </VCard>

        <!-- ── MOBILE: Tarjetas Arqueo por Cajas ─────────────────── -->
        <VRow class="d-flex d-sm-none mb-4 row-gap-3">
          <VCol v-if="filteredCashClosings.length === 0" cols="12">
            <VAlert type="info" variant="tonal" class="rounded-xl text-button font-weight-black" icon="tabler-info-circle">
              NO SE ENCONTRARON CAJAS CON VENTAS REGISTRADAS
            </VAlert>
          </VCol>
          <VCol v-for="c in filteredCashClosings" :key="'m-' + c.id" cols="12">
            <VCard variant="flat" class="rounded-xl border shadow-md overflow-hidden">
              <div class="pa-3 border-b d-flex justify-space-between align-center">
                <div class="d-flex gap-2 align-center">
                  <VAvatar color="primary" size="36" variant="tonal" class="font-weight-black rounded-lg text-sm">
                    {{ (c.seller?.username || '?').charAt(0).toUpperCase() }}
                  </VAvatar>
                  <div class="leading-none">
                    <div class="text-subtitle-2 font-weight-black text-capitalize">{{ formatUsername(c.seller?.username) }}</div>
                    <span class="text-super-xs text-disabled font-weight-bold uppercase">Caja #{{ c.id }}</span>
                  </div>
                </div>
                <VChip color="primary" size="small" variant="flat" class="font-weight-black">
                  {{ fmtUsd(c.real_total_usd) }}
                </VChip>
              </div>
              <VTable density="compact" class="text-caption bg-transparent">
                <tbody>
                  <tr>
                    <td class="font-weight-black text-disabled uppercase pl-4 py-1">Créditos:</td>
                    <td class="text-right font-weight-black pr-4 py-1 text-error">{{ fmtUsd(c.usd_credit) }}</td>
                  </tr>
                  <tr>
                    <td class="font-weight-black text-disabled uppercase pl-4 py-1">USD:</td>
                    <td class="text-right font-weight-black pr-4 py-1 text-primary">{{ fmtUsd(c.total_usd) }}</td>
                  </tr>
                  <tr>
                    <td class="font-weight-black text-disabled uppercase pl-4 py-1">COP:</td>
                    <td class="text-right font-weight-black pr-4 py-1 text-success">{{ fmtCop(c.total_cop) }}</td>
                  </tr>
                  <tr>
                    <td class="font-weight-black text-disabled uppercase pl-4 py-1">Bolívares:</td>
                    <td class="text-right font-weight-bold pr-4 py-1 text-warning">{{ fmtBs(c.total_bs) }}</td>
                  </tr>
                </tbody>
              </VTable>
            </VCard>
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
              <tbody>
                <tr>
                  <td style="inline-size: 40%;">
                    <strong>CORRELATIVO:</strong> #{{ props.cashData.id }}
                  </td>
                  <td style="inline-size: 30%;">
                    <strong>TASAS:</strong> BCV: {{ computedBcvRate }} Bs.
                  </td>
                  <td style="inline-size: 30%; text-align: end;">
                    <strong>EMISIÓN:</strong> {{ formatDateTime(new Date(), 'date') }}
                  </td>
                </tr>
                <tr>
                  <td><strong>VENTA TOTAL:</strong> {{ formatCurrency(totalSalesGlobal, 'USD') }}</td>
                  <td>COP: {{ computedCopRate }} COP</td>
                  <td style="text-align: end;">
                    <strong>HORA:</strong> {{ getCurrentTime() }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="section-header">
            DESGLOSE POR PERSONAL
          </div>

          <table class="data-table">
            <thead>
              <tr>
                <th style="padding: 4px; font-size: 8pt;">VENDEDOR</th>
                <th style="padding: 4px; font-size: 8pt; text-align: end;">CRÉDITO</th>
                <th style="padding: 4px; font-size: 8pt; text-align: end;">USD</th>
                <th style="padding: 4px; font-size: 8pt; text-align: end;">COP</th>
                <th style="padding: 4px; font-size: 8pt; text-align: end;">Bs.</th>
                <th style="padding: 4px; font-size: 8pt; text-align: end;">TOTAL</th>
              </tr>
            </thead>
            <tbody>
              <template
                v-for="(cash, index) in filteredCashClosings"
                :key="cash.id"
              >
                <tr class="total-row">
                  <td style="padding: 6px; font-size: 8pt; font-weight: bold;">
                    {{ index + 1 }}. {{ (cash.seller?.username || 'Sin Nombre').toUpperCase() }} (ID: {{ cash.id }})
                  </td>
                  <td style="padding: 6px; font-size: 8pt; text-align: end; color: #d32f2f;">
                    {{ formatCurrency(cash.usd_credit, 'USD') }}
                  </td>
                  <td style="padding: 6px; font-size: 8pt; text-align: end;">
                    {{ formatCurrency(cash.usd_delivered, 'USD') }}
                  </td>
                  <td style="padding: 6px; font-size: 8pt; text-align: end; color: #2e7d32;">
                    {{ formatCurrency(cash.cop_delivered, 'COP') }}
                  </td>
                  <td style="padding: 6px; font-size: 8pt; text-align: end; color: #ed6c02;">
                    {{ formatCurrency(cash.total_bs, 'BS') }}
                  </td>
                  <td style="padding: 6px; font-size: 8pt; text-align: end; font-weight: bold; background-color: #f1f3f5;">
                    {{ formatCurrency(cash.total_sales, 'USD') }}
                  </td>
                </tr>
                <tr>
                  <td
                    colspan="6"
                    style="padding: 4px; border-block-end: 1px solid #dee2e6; color: #7f8c8d; font-size: 7.5pt; background-color: #fafafa; line-height: 1.2;"
                  >
                    <strong>OBSERVACIONES / DESCUADRES:</strong> 
                    <span v-if="cash.blind_note || (cash.blind_mismatches && cash.blind_mismatches.length > 0)" style="color: #d32f2f; font-weight: bold;">
                      {{ cash.blind_note || 'Presenta descuadre en: ' + cash.blind_mismatches.join(', ') }}
                    </span>
                    <span v-else style="color: #2e7d32; font-weight: bold;">
                      Caja cuadrada sin observaciones.
                    </span>
                  </td>
                </tr>
              </template>

              <!-- TOTAL GENERAL DE TODOS LOS TRABAJADORES (RESUMEN FINAL) -->
              <tr style="background-color: #2c3e50; color: white; font-weight: bold;">
                <td style="padding: 8px; font-size: 9pt;">
                  TOTAL GENERAL CONSOLIDADO
                </td>
                <td style="padding: 8px; font-size: 9pt; text-align: end;">
                  {{ formatCurrency(totalCreditsUsdGlobal, 'USD') }}
                </td>
                <td style="padding: 8px; font-size: 9pt; text-align: end;">
                  {{ formatCurrency(totalUsdGlobal, 'USD') }}
                </td>
                <td style="padding: 8px; font-size: 9pt; text-align: end;">
                  {{ formatCurrency(totalCopGlobal, 'COP') }}
                </td>
                <td style="padding: 8px; font-size: 9pt; text-align: end;">
                  {{ formatCurrency(totalBsGlobal, 'BS') }}
                </td>
                <td style="padding: 8px; font-size: 9pt; text-align: end; background-color: #1a252f;">
                  {{ formatCurrency(totalSalesGlobal, 'USD') }}
                </td>
              </tr>
            </tbody>
          </table>

          <!-- ── DESGLOSE CONSOLIDADO POR MONEDA ── -->
          <div class="section-header" style="margin-top: 20px; background-color: #1a252f;">
            DESGLOSE CONSOLIDADO POR MONEDAS Y MÉTODOS
          </div>

          <div style="display: flex; gap: 15px; margin-top: 10px;">
            <!-- Tabla USD -->
            <div style="flex: 1;">
              <table class="data-table" style="width: 100%;">
                <thead>
                  <tr>
                    <th colspan="2" style="font-size: 8pt; padding: 6px; text-align: center; color: #ffffff !important; font-weight: bold; background-color: #34495e !important;">DÓLARES (USD)</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td style="font-size: 7.5pt; padding: 4px;">Efectivo (Físico)</td>
                    <td style="font-size: 7.5pt; padding: 4px; text-align: end; font-weight: bold;">{{ formatCurrency(totalCashUsdGlobal, 'USD') }}</td>
                  </tr>
                  <tr>
                    <td style="font-size: 7.5pt; padding: 4px;">Transferencia / PayPal / Binance</td>
                    <td style="font-size: 7.5pt; padding: 4px; text-align: end; font-weight: bold;">{{ formatCurrency(totalTransferUsdGlobal, 'USD') }}</td>
                  </tr>
                  <tr style="background-color: #f1f3f5; font-weight: bold;">
                    <td style="font-size: 7.5pt; padding: 4px;">Total Entregado</td>
                    <td style="font-size: 7.5pt; padding: 4px; text-align: end;">{{ formatCurrency(totalUsdGlobal, 'USD') }}</td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- Tabla COP -->
            <div style="flex: 1;">
              <table class="data-table" style="width: 100%;">
                <thead>
                  <tr>
                    <th colspan="2" style="font-size: 8pt; padding: 6px; text-align: center; color: #ffffff !important; font-weight: bold; background-color: #27ae60 !important;">PESOS (COP)</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td style="font-size: 7.5pt; padding: 4px;">Efectivo (Físico)</td>
                    <td style="font-size: 7.5pt; padding: 4px; text-align: end; font-weight: bold;">{{ formatCurrency(totalCashCopGlobal, 'COP') }}</td>
                  </tr>
                  <tr>
                    <td style="font-size: 7.5pt; padding: 4px;">Transferencia Bancaria</td>
                    <td style="font-size: 7.5pt; padding: 4px; text-align: end; font-weight: bold;">{{ formatCurrency(totalTransferCopGlobal, 'COP') }}</td>
                  </tr>
                  <tr style="background-color: #e8f5e9; font-weight: bold; color: #2e7d32;">
                    <td style="font-size: 7.5pt; padding: 4px;">Total Entregado</td>
                    <td style="font-size: 7.5pt; padding: 4px; text-align: end;">{{ formatCurrency(totalCopGlobal, 'COP') }}</td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- Tabla BS -->
            <div style="flex: 1;">
              <table class="data-table" style="width: 100%;">
                <thead>
                  <tr>
                    <th colspan="2" style="font-size: 8pt; padding: 6px; text-align: center; color: #ffffff !important; font-weight: bold; background-color: #d35400 !important;">BOLÍVARES (BS)</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td style="font-size: 7.5pt; padding: 4px;">Efectivo (Físico)</td>
                    <td style="font-size: 7.5pt; padding: 4px; text-align: end; font-weight: bold;">{{ formatCurrency(totalCashBsGlobal, 'BS') }}</td>
                  </tr>
                  <tr>
                    <td style="font-size: 7.5pt; padding: 4px;">Puntos POS (Debito / Credito)</td>
                    <td style="font-size: 7.5pt; padding: 4px; text-align: end; font-weight: bold;">{{ formatCurrency(totalPosBsGlobal, 'BS') }}</td>
                  </tr>
                  <tr>
                    <td style="font-size: 7.5pt; padding: 4px;">Pago Móvil / Transferencia</td>
                    <td style="font-size: 7.5pt; padding: 4px; text-align: end; font-weight: bold;">{{ formatCurrency(totalTransferBsGlobal, 'BS') }}</td>
                  </tr>
                  <tr style="background-color: #fff3e0; font-weight: bold; color: #e65100;">
                    <td style="font-size: 7.5pt; padding: 4px;">Total Reportado</td>
                    <td style="font-size: 7.5pt; padding: 4px; text-align: end;">{{ formatCurrency(totalBsGlobal, 'BS') }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <div class="signature-section" style="margin-top: 35px;">
            <div class="signature-box">
              <div style=" color: #000;font-size: 10pt; font-weight: bold;">
                FIRMA SUPERVISOR
              </div>
              <small style=" color: #666;font-size: 8pt;">CONTROL DE TURNO / VERIFICACIÓN</small>
            </div>
          </div>

          <div class="footer-note" style="margin-top: 20px;">
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
