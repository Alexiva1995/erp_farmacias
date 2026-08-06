<script setup>
import { BASE64_LOGO_DATA } from "@/constants/logo.js";
import axios from "@/plugins/axios";
import { formatDateTime } from "@/utils/formatDateTime";
import { computed, nextTick } from "vue";
import { useDisplay } from "vuetify";

const { mobile } = useDisplay();

const props = defineProps({
  isDialogVisible: {
    type: Boolean,
    required: true,
  },
  monthlyCashData: {
    type: Object,
    default: () => ({ summary: [] }),
  },
  originalIds: {
    type: Object,
    default: () => ({}),
  },
  tpvPaymentMethods: {
    type: Object,
    default: () => ({ COP: [], USD: [], BS: [] })
  }
});

const emit = defineEmits(["update:isDialogVisible", "modal-closed"]);

const dialogVisible = computed({
  get: () => props.isDialogVisible,
  set: (val) => emit("update:isDialogVisible", val),
});

const closeModal = () => {
  dialogVisible.value = false;
  emit("modal-closed");
};

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
  .signature-section { margin-top: 50px; text-align: center; }
  .signature-box { display: block; border-top: 1.5pt solid #000; width: 250px; margin: 0 auto; padding-top: 8px; }
  .footer-note { margin-top: 30px; text-align: center; font-size: 8pt; color: #7f8c8d; font-style: italic; border-top: 1px solid #eee; padding-top: 10px; }
  .d-none { display: none; }
`;

const downloadReport = async () => {
  try {
    await nextTick();
    const element = document.getElementById("monthly-cash-report");
    if (!element) return;
    
    element.classList.remove("d-none");
    const htmlContent = element.outerHTML;
    element.classList.add("d-none");

    const params = {
      html_content: `<html><head><style>${ticketStyles}</style></head><body>${htmlContent}</body></html>`,
      filename: "Resumen_Cajas_Mensual",
    };

    const response = await axios.post("/finances/cash-closure/downloadReport", params, { responseType: "blob" });
    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement("a");
    link.href = url;
    link.setAttribute("download", `Cierre_Mensual.pdf`);
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
    const element = document.getElementById("monthly-cash-report");
    if (!element) return;
    
    element.classList.remove("d-none");
    const htmlContent = element.outerHTML;
    element.classList.add("d-none");

    const params = {
      html_content: `<html><head><style>${ticketStyles}</style></head><body>${htmlContent}</body></html>`,
      filename: "Resumen_Cajas_Mensual",
    };

    const response = await axios.post("/finances/cash-closure/PrintReport", params, { responseType: "blob" });
    const url = window.URL.createObjectURL(new Blob([response.data], { type: "application/pdf" }));
    const printWindow = window.open(url, "_blank");
    if (printWindow) printWindow.focus();
    window.URL.revokeObjectURL(url);
    closeModal();
  } catch (error) {
    console.error("Error al visualizar el PDF:", error);
  }
};

const getCurrentTime = () => {
  return new Date().toLocaleTimeString('es-VE', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
};

// Helper para mostrar valores que ya vienen formateados del backend
const display = (val, currency = "USD") => {
  if (val === null || val === undefined) return `0,00 ${currency}`;
  return `${val} ${currency}`;
};

const formatUsername = (username) => {
  if (!username) return "—";
  const parts = username
    .replace(/[._]/g, " ")
    .split(" ")
    .filter(word => word.length > 0);
  
  // Tomar solo los dos primeros elementos (Primer Nombre y Primer Apellido)
  return parts.slice(0, 2)
    .map((word) => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase())
    .join(" ");
};

const fmtUsd = (val) => {
  const num = parseFloat(val);
  if (isNaN(num)) return "0,00 USD";
  return new Intl.NumberFormat("es-VE", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(num) + " USD";
};

const fmtCop = (val) => {
  const num = parseFloat(val);
  if (isNaN(num)) return "0 COP";
  return Math.round(num)
    .toString()
    .replace(/\B(?=(\d{3})+(?!\d))/g, ".") + " COP";
};

const fmtBs = (val) => {
  const num = parseFloat(val);
  if (isNaN(num)) return "0,00 Bs.";
  return new Intl.NumberFormat("es-VE", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(num) + " Bs.";
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
            <VIcon icon="tabler-calendar-stats" />
          </VAvatar>
          <div>
            <h3 class="text-h6 font-weight-black mb-0 uppercase leading-none">CONSOLIDADO MENSUAL</h3>
            <span class="text-xs text-disabled font-weight-medium uppercase">Detalle de Operaciones</span>
          </div>
        </div>
        <VBtn icon="tabler-x" variant="text" size="small" color="secondary" @click="closeModal" />
      </VCardTitle>

      <VCardText class="pa-6 pt-4" style="background-color: #f8f9fa;">
        <!-- RESUMEN GLOBAL -->
        <h4 class="text-subtitle-1 font-weight-bold mb-3 d-flex align-center gap-2">
          <VIcon icon="tabler-report-money" size="20" color="primary" /> Total Consolidado por Monedas
        </h4>
        <VRow class="mb-6">
          <VCol cols="12" sm="6" md="3">
            <VCard variant="outlined" class="bg-white rounded-lg border h-100">
              <VCardItem class="pa-4">
                <div class="d-flex justify-space-between align-start mb-1">
                  <span class="text-caption font-weight-bold text-medium-emphasis">TOTAL USD</span>
                  <VIcon icon="tabler-currency-dollar" color="success" size="20" />
                </div>
                <h4 class="text-h6 font-weight-bold text-success">{{ display(props.monthlyCashData.totalSalesUsd, 'USD') }}</h4>
                <div class="text-caption text-medium-emphasis mt-1">Efectivo + Transf.</div>
              </VCardItem>
            </VCard>
          </VCol>

          <VCol cols="12" sm="6" md="3">
            <VCard variant="outlined" class="bg-white rounded-lg border h-100">
              <VCardItem class="pa-4">
                <div class="d-flex justify-space-between align-start mb-1">
                  <span class="text-caption font-weight-bold text-medium-emphasis">TOTAL BS</span>
                  <VIcon icon="tabler-currency-bolivar" color="warning" size="20" />
                </div>
                <h4 class="text-h6 font-weight-bold">{{ display(props.monthlyCashData.totalSalesBs, 'BS') }}</h4>
                <div class="text-caption text-warning font-weight-medium mt-1">&asymp; {{ display(props.monthlyCashData.totalSalesBsInUSD, 'USD') }}</div>
              </VCardItem>
            </VCard>
          </VCol>

          <VCol cols="12" sm="6" md="3">
            <VCard variant="outlined" class="bg-white rounded-lg border h-100">
              <VCardItem class="pa-4">
                <div class="d-flex justify-space-between align-start mb-1">
                  <span class="text-caption font-weight-bold text-medium-emphasis">TOTAL COP</span>
                  <VIcon icon="tabler-currency-peso" color="info" size="20" />
                </div>
                <h4 class="text-h6 font-weight-bold">{{ display(props.monthlyCashData.totalSalesCop, 'COP') }}</h4>
                <div class="text-caption text-info font-weight-medium mt-1">&asymp; {{ display(props.monthlyCashData.totalSalesGlobalCopInUsd, 'USD') }}</div>
              </VCardItem>
            </VCard>
          </VCol>

          <VCol cols="12" sm="6" md="3">
            <VCard variant="flat" class="bg-primary text-white rounded-lg border-0 shadow-sm h-100">
              <VCardItem class="pa-4">
                <div class="d-flex justify-space-between align-start mb-1">
                  <span class="text-caption font-weight-bold text-white opacity-80">VENTA BRUTA GENERAL</span>
                  <VIcon icon="tabler-sum" color="white" size="20" />
                </div>
                <h4 class="text-h5 font-weight-bold text-white mt-2">
                  {{ display(props.monthlyCashData.totalSalesGlobal, 'USD') }}
                </h4>
              </VCardItem>
            </VCard>
          </VCol>
        </VRow>

        <VDivider class="mb-5" />

        <h4 class="text-subtitle-1 font-weight-bold mb-3 d-flex align-center gap-2">
          <VIcon icon="tabler-users" size="20" color="primary" /> Desglose por Vendedores
        </h4>

        <!-- LISTA DE VENDEDORES (ESCRITORIO: TABLA) -->
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
              <tr v-if="!props.monthlyCashData.summary || props.monthlyCashData.summary.length === 0">
                <td colspan="6" class="text-center text-caption text-disabled py-4">No hay ventas consolidadas por vendedor</td>
              </tr>
              <tr v-for="cashData in props.monthlyCashData.summary" :key="cashData.seller_name" class="seller-row">
                <td class="py-2">
                  <div class="d-flex align-center gap-3">
                    <VAvatar size="32" color="secondary" variant="tonal" class="rounded-lg font-weight-black text-xs">
                      {{ (cashData.seller_name || '?').charAt(0).toUpperCase() }}
                    </VAvatar>
                    <div class="d-flex flex-column leading-none">
                      <span class="text-sm font-weight-black text-primary">{{ formatUsername(cashData.seller_name) }}</span>
                      <span class="text-super-xs text-disabled font-weight-bold uppercase">Consolidado</span>
                    </div>
                  </div>
                </td>
                <td class="text-end">
                  <span class="text-sm font-weight-bold text-error">{{ display(cashData.total_credits, 'USD') }}</span>
                </td>
                <td class="text-end">
                  <div class="d-flex align-center justify-end gap-1">
                    <span class="text-sm font-weight-bold">{{ display(cashData.total_usd, 'USD') }}</span>
                  </div>
                </td>
                <td class="text-end">
                  <div class="d-flex align-center justify-end gap-1">
                    <span class="text-sm font-weight-bold text-success">{{ display(cashData.total_cop, 'COP') }}</span>
                  </div>
                </td>
                <td class="text-end">
                  <div class="d-flex align-center justify-end gap-1">
                    <span class="text-sm font-weight-bold text-warning">{{ display(cashData.total_bs, 'Bs.') }}</span>
                  </div>
                </td>
                <td class="text-end">
                  <VChip size="x-small" variant="flat" color="primary" class="font-weight-black rounded px-2">
                    {{ display(cashData.total_sales, 'USD') }}
                  </VChip>
                </td>
              </tr>
              <!-- FILA TOTAL GENERAL CONSOLIDADO -->
              <tr class="bg-grey-lighten-3 font-weight-black" style="border-top: 2px solid #bbb;">
                <td class="py-3 pl-4">
                  <div class="d-flex align-center gap-3">
                    <VAvatar size="32" color="primary" variant="flat" class="rounded-lg font-weight-black text-xs">
                      <VIcon icon="tabler-sum" size="16" />
                    </VAvatar>
                    <div class="d-flex flex-column leading-none">
                      <span class="text-sm font-weight-black text-high-emphasis">TOTAL GENERAL</span>
                      <span class="text-super-xs text-primary font-weight-black uppercase">Consolidado</span>
                    </div>
                  </div>
                </td>
                <td class="text-end text-sm text-error font-weight-black">{{ display(props.monthlyCashData.totalSalesCredits, 'USD') }}</td>
                <td class="text-end text-sm text-primary font-weight-black">{{ display(props.monthlyCashData.totalSalesUsd, 'USD') }}</td>
                <td class="text-end text-sm text-success font-weight-black">{{ display(props.monthlyCashData.totalSalesCop, 'COP') }}</td>
                <td class="text-end text-sm text-warning font-weight-black">{{ display(props.monthlyCashData.totalSalesBs, 'Bs.') }}</td>
                <td class="text-end py-2">
                  <VChip size="small" variant="flat" color="primary" class="font-weight-black rounded px-3">
                    {{ display(props.monthlyCashData.totalSalesGlobal, 'USD') }}
                  </VChip>
                </td>
              </tr>
            </tbody>
          </VTable>
        </VCard>

        <!-- LISTA DE VENDEDORES (MÓVIL: TARJETAS) -->
        <VRow class="d-flex d-sm-none">
          <VCol cols="12" v-for="cashData in props.monthlyCashData.summary" :key="cashData.seller_name">
            <VCard variant="outlined" class="bg-white rounded-lg border h-100" v-if="parseFloat(cashData.total_sales) > 0">
              <VCardItem class="pa-4 pb-0 border-b">
                <div class="d-flex justify-space-between align-start mb-3">
                  <div class="d-flex gap-3 align-center">
                    <VAvatar color="secondary" size="38" variant="tonal" class="font-weight-bold text-body-1">
                      {{ (cashData.seller_name || 'U').substring(0,2).toUpperCase() }}
                    </VAvatar>
                    <div style="line-height: 1.2;">
                      <h5 class="text-subtitle-1 font-weight-bold mb-0 text-capitalize">{{ cashData.seller_name }}</h5>
                      <span class="text-caption text-medium-emphasis">Resumen Mensual</span>
                    </div>
                  </div>
                  <VChip color="primary" size="small" variant="flat" class="font-weight-bold px-3">
                     Venta: {{ display(cashData.total_sales, 'USD') }}
                  </VChip>
                </div>
              </VCardItem>
              
              <VCardText class="pa-0">
                <VTable density="compact" class="text-caption bg-transparent w-100 table-sm">
                  <tbody>
                    <tr>
                      <td class="font-weight-medium text-medium-emphasis py-2 pl-4">Crédito:</td>
                      <td class="text-right font-weight-bold py-2 pr-4 text-error">{{ display(cashData.total_credits, 'USD') }}</td>
                    </tr>
                    <tr>
                      <td class="font-weight-medium text-medium-emphasis py-2 pl-4">USD:</td>
                      <td class="text-right font-weight-bold py-2 pr-4 text-primary">{{ display(cashData.total_usd, 'USD') }}</td>
                    </tr>
                    <tr>
                      <td class="font-weight-medium text-medium-emphasis py-2 pl-4">BS:</td>
                      <td class="text-right font-weight-bold py-2 pr-4 text-warning">
                        <span>{{ display(cashData.total_bs, 'BS') }}</span>
                        <span class="text-medium-emphasis font-weight-regular ml-1">(&asymp; {{ display(cashData.total_bs_in_usd, 'USD') }})</span>
                      </td>
                    </tr>
                    <tr>
                      <td class="font-weight-medium text-medium-emphasis py-2 pl-4">COP:</td>
                      <td class="text-right font-weight-bold py-2 pr-4 text-info">
                        <span>{{ display(cashData.total_cop, 'COP') }}</span>
                        <span class="text-medium-emphasis font-weight-regular ml-1">(&asymp; {{ display(cashData.total_cop_in_usd, 'USD') }})</span>
                      </td>
                    </tr>
                  </tbody>
                </VTable>
              </VCardText>
            </VCard>
          </VCol>
        </VRow>

        <!-- ESTRUCTURA OCULTA PARA EL REPORTE PDF -->
        <div id="monthly-cash-report" class="d-none">
          <div class="header">
            <img :src="BASE64_LOGO_DATA" alt="Logo" class="logo" />
            <div class="company-name">FARMACIA BARRIO SUCRE 2024 C.A.</div>
            <div class="company-rif">R.I.F: J-50478962-1</div>
            <div class="document-title">CONSOLIDADO MENSUAL DE OPERACIONES</div>
          </div>

          <div class="info-section">
            <table class="info-table">
              <tbody>
                <tr>
                  <td style="inline-size: 50%;"><strong>REPORTE:</strong> CONSOLIDADO MENSUAL</td>
                  <td style="inline-size: 50%; text-align: end;"><strong>EMISIÓN:</strong> {{ formatDateTime(new Date(), 'date') }}</td>
                </tr>
                <tr>
                  <td><strong>VENTA TOTAL:</strong> {{ display(props.monthlyCashData.totalSalesGlobal, 'USD') }}</td>
                  <td style="text-align: end;"><strong>HORA:</strong> {{ getCurrentTime() }}</td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="section-header">DESGLOSE POR PERSONAL</div>

          <table class="data-table">
            <thead>
              <tr>
                <th style="font-size: 8pt;">RESPONSABLE</th>
                <th style="font-size: 8pt; text-align: end;">CRÉDITO</th>
                <th style="font-size: 8pt; text-align: end;">USD</th>
                <th style="font-size: 8pt; text-align: end;">COP</th>
                <th style="font-size: 8pt; text-align: end;">BS</th>
                <th style="font-size: 8pt; text-align: end;">TOTAL USD</th>
              </tr>
            </thead>
            <tbody>
              <template v-for="cashData in props.monthlyCashData.summary" :key="cashData.seller_name">
                <tr v-if="parseFloat(cashData.total_sales) > 0">
                  <td style="font-size: 8pt;">{{ formatUsername(cashData.seller_name).toUpperCase() }}</td>
                  <td style="font-size: 8pt; text-align: end; color: #d32f2f;">{{ display(cashData.total_credits, 'USD') }}</td>
                  <td style="font-size: 8pt; text-align: end;">{{ display(cashData.total_usd, 'USD') }}</td>
                  <td style="font-size: 8pt; text-align: end;">{{ display(cashData.total_cop, 'COP') }}</td>
                  <td style="font-size: 8pt; text-align: end;">{{ display(cashData.total_bs, 'BS') }}</td>
                  <td style="font-size: 8pt; font-weight: bold; text-align: end;">{{ display(cashData.total_sales, 'USD') }}</td>
                </tr>
              </template>
              <tr style="background-color: #2c3e50; color: white; font-weight: bold;">
                <td style="font-size: 9pt;">TOTAL GENERAL</td>
                <td style="font-size: 9pt; text-align: end;">{{ display(props.monthlyCashData.totalSalesCredits, 'USD') }}</td>
                <td style="font-size: 9pt; text-align: end;">{{ display(props.monthlyCashData.totalSalesUsd, 'USD') }}</td>
                <td style="font-size: 9pt; text-align: end;">{{ display(props.monthlyCashData.totalSalesCop, 'COP') }}</td>
                <td style="font-size: 9pt; text-align: end;">{{ display(props.monthlyCashData.totalSalesBs, 'BS') }}</td>
                <td style="font-size: 9pt; text-align: end;">{{ display(props.monthlyCashData.totalSalesGlobal, 'USD') }}</td>
              </tr>
            </tbody>
          </table>

          <div class="section-header">DESGLOSE CONSOLIDADO POR MONEDAS Y MÉTODOS</div>

          <table class="data-table" style="margin-top: 5px; width: 100%;">
            <!-- DÓLARES -->
            <thead>
              <tr style="background-color: #2c3e50; color: white;">
                <th colspan="2" style="font-size: 8pt; text-align: center; font-weight: bold; background-color: #34495e; color: white;">DÓLARES (USD)</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td style="font-size: 8pt; width: 70%;">Efectivo (Físico)</td>
                <td style="font-size: 8pt; text-align: right; font-weight: bold; width: 30%;">{{ fmtUsd(props.monthlyCashData.usd_cash) }}</td>
              </tr>
              <tr>
                <td style="font-size: 8pt;">Transferencia / PayPal / Binance</td>
                <td style="font-size: 8pt; text-align: right; font-weight: bold;">{{ fmtUsd(parseFloat(props.monthlyCashData.usd_transfer || 0) + parseFloat(props.monthlyCashData.usd_paypal || 0) + parseFloat(props.monthlyCashData.usd_binance || 0)) }}</td>
              </tr>
              <tr style="background-color: #f8f9fa; font-weight: bold;">
                <td style="font-size: 8pt; padding-left: 10px;">Total Entregado</td>
                <td style="font-size: 8pt; text-align: right; color: #2c3e50;">{{ display(props.monthlyCashData.totalSalesUsd, 'USD') }}</td>
              </tr>
            </tbody>

            <!-- PESOS -->
            <thead>
              <tr style="background-color: #27ae60; color: white;">
                <th colspan="2" style="font-size: 8pt; text-align: center; font-weight: bold; background-color: #27ae60; color: white;">PESOS (COP)</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td style="font-size: 8pt; width: 70%;">Efectivo (Físico)</td>
                <td style="font-size: 8pt; text-align: right; font-weight: bold; width: 30%;">{{ fmtCop(props.monthlyCashData.cop_cash) }}</td>
              </tr>
              <tr>
                <td style="font-size: 8pt;">Transferencia Bancaria</td>
                <td style="font-size: 8pt; text-align: right; font-weight: bold;">{{ fmtCop(props.monthlyCashData.cop_transfer) }}</td>
              </tr>
              <tr v-if="parseFloat(props.monthlyCashData.cop_spare || 0) > 0">
                <td style="font-size: 8pt;">Sobrante (Ajuste Caja)</td>
                <td style="font-size: 8pt; text-align: right; font-weight: bold;">{{ fmtCop(props.monthlyCashData.cop_spare) }}</td>
              </tr>
              <tr style="background-color: #e8f8f5; font-weight: bold;">
                <td style="font-size: 8pt; padding-left: 10px;">Total Entregado</td>
                <td style="font-size: 8pt; text-align: right; color: #27ae60;">{{ display(props.monthlyCashData.totalSalesCop, 'COP') }}</td>
              </tr>
            </tbody>

            <!-- BOLÍVARES -->
            <thead>
              <tr style="background-color: #d35400; color: white;">
                <th colspan="2" style="font-size: 8pt; text-align: center; font-weight: bold; background-color: #d35400; color: white;">BOLÍVARES (BS)</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td style="font-size: 8pt; width: 70%;">Efectivo (Físico)</td>
                <td style="font-size: 8pt; text-align: right; font-weight: bold; width: 30%;">{{ fmtBs(props.monthlyCashData.bs_cash) }}</td>
              </tr>
              <tr>
                <td style="font-size: 8pt;">Puntos POS (Débito / Crédito)</td>
                <td style="font-size: 8pt; text-align: right; font-weight: bold;">{{ fmtBs(parseFloat(props.monthlyCashData.bs_card_debito || 0) + parseFloat(props.monthlyCashData.bs_card_credit || 0)) }}</td>
              </tr>
              <tr>
                <td style="font-size: 8pt;">Pago Móvil / Transferencia</td>
                <td style="font-size: 8pt; text-align: right; font-weight: bold;">{{ fmtBs(parseFloat(props.monthlyCashData.bs_mobile || 0) + parseFloat(props.monthlyCashData.bs_transfer || 0)) }}</td>
              </tr>
              <tr style="background-color: #fdf2e9; font-weight: bold;">
                <td style="font-size: 8pt; padding-left: 10px;">Total Reportado</td>
                <td style="font-size: 8pt; text-align: right; color: #d35400;">{{ display(props.monthlyCashData.totalSalesBs, 'Bs.') }}</td>
              </tr>
            </tbody>
          </table>

          <div class="signature-section">
            <div class="signature-box">
              <div style="font-weight: bold;">FIRMA SUPERVISOR</div>
              <small>REVISIÓN MENSUAL DE OPERACIONES</small>
            </div>
          </div>
        </div>
      </VCardText>
      
      <VDivider />
      
      <VCardActions class="pa-4 bg-white d-flex justify-center gap-3 px-6">
        <VBtn variant="tonal" color="secondary" @click="closeModal" class="flex-grow-1 font-weight-medium" size="large">Cerrar</VBtn>
        <VBtn variant="flat" color="primary" @click="downloadReport" prepend-icon="tabler-download" class="flex-grow-1 font-weight-medium" size="large">Descargar Resumen PDF</VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
