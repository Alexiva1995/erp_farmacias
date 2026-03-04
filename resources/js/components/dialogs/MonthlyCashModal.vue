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
  monthlyCashData: {
    type: Object,
    default: () => ({ summary: [] }),
  },
  originalIds: {
    type: Object,
    default: () => ({}),
  },
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

</script>
<template>
  <VDialog v-model="dialogVisible" max-width="950px" scrollable>
    <VCard class="rounded-xl border shadow-sm">
      <VCardTitle class="d-flex justify-space-between align-center px-6 py-4 border-b">
        <div class="d-flex align-center gap-3">
          <VAvatar color="primary" variant="tonal" rounded>
            <VIcon icon="tabler-calendar-stats" />
          </VAvatar>
          <div>
            <h3 class="text-h6 font-weight-bold mb-0">Detalle del Consolidado Mensual</h3>
            <span class="text-caption text-medium-emphasis">Reporte Consolidado</span>
          </div>
        </div>
        <VBtn icon="tabler-x" variant="text" size="small" color="secondary" @click="closeModal" />
      </VCardTitle>

      <VCardText class="pa-6" style="background-color: #f8f9fa;">
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
                <h4 class="text-h6 font-weight-bold text-success">{{ formatCurrency(props.monthlyCashData.totalSalesUsd, 'USD') }}</h4>
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
                <h4 class="text-h6 font-weight-bold">{{ formatCurrency(props.monthlyCashData.totalSalesBs, 'BS') }}</h4>
                <div class="text-caption text-warning font-weight-medium mt-1">&asymp; {{ formatCurrency(props.monthlyCashData.totalSalesBsInUSD, 'USD') }}</div>
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
                <h4 class="text-h6 font-weight-bold">{{ formatCurrency(props.monthlyCashData.totalSalesCop, 'COP') }}</h4>
                <div class="text-caption text-info font-weight-medium mt-1">&asymp; {{ formatCurrency(props.monthlyCashData.totalSalesGlobalCopInUsd, 'USD') }}</div>
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
                  {{ formatCurrency(props.monthlyCashData.totalSalesGlobal, 'USD') }}
                </h4>
              </VCardItem>
            </VCard>
          </VCol>
        </VRow>

        <VDivider class="mb-5" />

        <h4 class="text-subtitle-1 font-weight-bold mb-3 d-flex align-center gap-2">
          <VIcon icon="tabler-users" size="20" color="primary" /> Desglose por Vendedores
        </h4>

        <!-- LISTA DE VENDEDORES -->
        <VRow>
          <VCol cols="12" md="6" v-for="cashData in props.monthlyCashData.summary" :key="cashData.seller_name">
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
                     Venta: {{ formatCurrency(cashData.total_sales, 'USD') }}
                  </VChip>
                </div>
              </VCardItem>
              
              <VCardText class="pa-0">
                <VTable density="compact" class="text-caption bg-transparent w-100 table-sm">
                  <tbody>
                    <tr>
                      <td class="font-weight-medium text-medium-emphasis py-2 pl-4">USD:</td>
                      <td class="text-right font-weight-bold py-2 pr-4 text-primary">
                        {{ formatCurrency(cashData.total_usd, 'USD') }}
                      </td>
                    </tr>
                    <tr>
                      <td class="font-weight-medium text-medium-emphasis py-2 pl-4">BS:</td>
                      <td class="text-right font-weight-bold py-2 pr-4 text-warning">
                        {{ formatCurrency(cashData.total_bs, 'BS') }} 
                        <span class="text-medium-emphasis font-weight-regular ml-1">(&asymp; {{ formatCurrency(cashData.total_bs_in_usd, 'USD') }})</span>
                      </td>
                    </tr>
                    <tr>
                      <td class="font-weight-medium text-medium-emphasis py-2 pl-4">COP:</td>
                      <td class="text-right font-weight-bold py-2 pr-4 text-info">
                        {{ formatCurrency(cashData.total_cop, 'COP') }}
                        <span class="text-medium-emphasis font-weight-regular ml-1">(&asymp; {{ formatCurrency(cashData.total_cop_in_usd, 'USD') }})</span>
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
              <tr>
                <td style="inline-size: 50%;"><strong>REPORTE:</strong> CONSOLIDADO MENSUAL</td>
                <td style="inline-size: 50%; text-align: end;"><strong>EMISIÓN:</strong> {{ formatDateTime(new Date(), 'date') }}</td>
              </tr>
              <tr>
                <td><strong>VENTA TOTAL:</strong> {{ formatCurrency(props.monthlyCashData.totalSalesGlobal, 'USD') }}</td>
                <td style="text-align: end;"><strong>HORA:</strong> {{ getCurrentTime() }}</td>
              </tr>
            </table>
          </div>

          <div class="section-header">DESGLOSE POR PERSONAL</div>

          <table class="data-table">
            <thead>
              <tr>
                <th style="font-size: 8pt;">RESPONSABLE</th>
                <th style="font-size: 8pt; text-align: end;">BS</th>
                <th style="font-size: 8pt; text-align: end;">COP</th>
                <th style="font-size: 8pt; text-align: end;">USD</th>
                <th style="font-size: 8pt; text-align: end;">TOTAL USD</th>
              </tr>
            </thead>
            <tbody>
              <template v-for="cashData in props.monthlyCashData.summary" :key="cashData.seller_name">
                <tr v-if="parseFloat(cashData.total_sales) > 0">
                  <td style="font-size: 8pt;">{{ (cashData.seller_name || 'Sin Nombre').toUpperCase() }}</td>
                  <td style="font-size: 8pt; text-align: end;">{{ formatCurrency(cashData.total_bs, 'BS') }}</td>
                  <td style="font-size: 8pt; text-align: end;">{{ formatCurrency(cashData.total_cop, 'COP') }}</td>
                  <td style="font-size: 8pt; text-align: end;">{{ formatCurrency(cashData.total_usd, 'USD') }}</td>
                  <td style="font-size: 8pt; font-weight: bold; text-align: end;">{{ formatCurrency(cashData.total_sales, 'USD') }}</td>
                </tr>
              </template>
              <tr style="background-color: #2c3e50; color: white; font-weight: bold;">
                <td style="font-size: 9pt;">TOTAL GENERAL</td>
                <td style="font-size: 9pt; text-align: end;">{{ formatCurrency(props.monthlyCashData.totalSalesBs, 'BS') }}</td>
                <td style="font-size: 9pt; text-align: end;">{{ formatCurrency(props.monthlyCashData.totalSalesCop, 'COP') }}</td>
                <td style="font-size: 9pt; text-align: end;">{{ formatCurrency(props.monthlyCashData.totalSalesUsd, 'USD') }}</td>
                <td style="font-size: 9pt; text-align: end;">{{ formatCurrency(props.monthlyCashData.totalSalesGlobal, 'USD') }}</td>
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
