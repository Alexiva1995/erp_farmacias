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

const totalBsGlobal = computed(() => {
  return filteredCashClosings.value.reduce((acc, c) => acc + parseFloat(c.total_bs || 0), 0);
});

const totalCopGlobal = computed(() => {
  return filteredCashClosings.value.reduce((acc, c) => acc + parseFloat(c.total_cop || 0), 0);
});

const totalUsdGlobal = computed(() => {
  return filteredCashClosings.value.reduce((acc, c) => acc + (parseFloat(c.total_usd || 0) + parseFloat(c.usd_credit || 0)), 0);
});

const totalSalesGlobal = computed(() => {
  return filteredCashClosings.value.reduce((acc, c) => {
    return acc + (parseFloat(c.total_usd || 0) + parseFloat(c.total_bs_in_usd || 0) + parseFloat(c.total_cop_in_usd || 0));
  }, 0);
});

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
.pdf-row-2col {
  width: 100%;
  display: block; 
}
.pdf-col-50,
.pdf-col-multi {
  float: left;
  width: 50%; 
  box-sizing: border-box;
  padding: 0 8px;
  min-height: 1px;
}
.pdf-row-multi:after,
.pdf-row-2col:after {
  content: "";
  display: table;
  clear: both;
}
`;

const downloadReport = async () => {
  try {
    await nextTick();
    const element = document.getElementById("daily-cash-report");
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
    const element = document.getElementById("daily-cash-report");
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
            <h3 class="text-h6 font-weight-bold mb-0">Detalle del Cierre Diario</h3>
            <span class="text-caption text-medium-emphasis">Reporte N° {{ props.cashData?.id }} • {{ props.cashData?.created_at ? formatDateTime(props.cashData.created_at, "date") : '' }}</span>
          </div>
        </div>
        <VBtn icon="tabler-x" variant="text" size="small" color="secondary" @click="closeModal" />
      </VCardTitle>

      <VCardText class="pa-6" style="background-color: #f8f9fa;">
        <!-- RESUMEN GLOBAL DEL DIA -->
        <h4 class="text-subtitle-1 font-weight-bold mb-3 d-flex align-center gap-2">
          <VIcon icon="tabler-report-money" size="20" color="primary" /> Total Diario por Monedas
        </h4>
        <VRow class="mb-6">
          <VCol cols="12" sm="6" md="3">
            <VCard variant="outlined" class="bg-white rounded-lg border h-100">
              <VCardItem class="pa-4">
                <div class="d-flex justify-space-between align-start mb-1">
                  <span class="text-caption font-weight-bold text-medium-emphasis">TOTAL USD</span>
                  <VIcon icon="tabler-currency-dollar" color="success" size="20" />
                </div>
                <h4 class="text-h6 font-weight-bold text-success">{{ formatCurrency(totalUsdGlobal, 'USD') }}</h4>
                <div class="text-caption text-medium-emphasis mt-1">Efectivo + Transferencias</div>
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
                <h4 class="text-h6 font-weight-bold">{{ formatCurrency(totalBsGlobal, 'BS') }}</h4>
                <div class="text-caption text-warning font-weight-medium mt-1">&asymp; {{ formatCurrency(props.cashData.total_bs_in_usd, 'USD') }}</div>
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
                <h4 class="text-h6 font-weight-bold">{{ formatCurrency(totalCopGlobal, 'COP') }}</h4>
                <div class="text-caption text-info font-weight-medium mt-1">&asymp; {{ formatCurrency(props.cashData.total_cop_in_usd, 'USD') }}</div>
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
                  {{ formatCurrency(totalSalesGlobal, 'USD') }}
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
          <VCol cols="12" md="6" v-for="closing in filteredCashClosings" :key="closing.id">
            <VCard variant="outlined" class="bg-white rounded-lg border h-100">
              <VCardItem class="pa-4 pb-0 border-b">
                <div class="d-flex justify-space-between align-start mb-3">
                  <div class="d-flex gap-3 align-center">
                    <VAvatar color="secondary" size="38" variant="tonal" class="font-weight-bold text-body-1">
                      {{ closing.seller?.username?.substring(0,2).toUpperCase() }}
                    </VAvatar>
                    <div style="line-height: 1.2;">
                      <h5 class="text-subtitle-1 font-weight-bold mb-0 text-capitalize">{{ closing.seller?.username }}</h5>
                      <span class="text-caption text-medium-emphasis">Caja #{{ closing.id }}</span>
                    </div>
                  </div>
                  <VChip color="primary" size="small" variant="flat" class="font-weight-bold px-3">
                     Venta: {{ formatCurrency(parseFloat(closing.total_usd || 0) + parseFloat(closing.total_bs_in_usd || 0) + parseFloat(closing.total_cop_in_usd || 0), 'USD') }}
                  </VChip>
                </div>
              </VCardItem>
              
              <VCardText class="pa-0">
                <VTable density="compact" class="text-caption bg-transparent w-100 table-sm">
                  <tbody>
                    <tr>
                      <td class="font-weight-medium text-medium-emphasis py-2 pl-4">USD:</td>
                      <td class="text-right font-weight-bold py-2 pr-4 text-primary">
                        {{ formatCurrency(closing.real_usd, 'USD') }}
                      </td>
                    </tr>
                    <tr>
                      <td class="font-weight-medium text-medium-emphasis py-2 pl-4">BS:</td>
                      <td class="text-right font-weight-bold py-2 pr-4 text-warning">
                        {{ formatCurrency(closing.real_bs, 'BS') }} 
                        <span class="text-medium-emphasis font-weight-regular ml-1">(&asymp; {{ formatCurrency(closing.total_bs_in_usd, 'USD') }})</span>
                      </td>
                    </tr>
                    <tr>
                      <td class="font-weight-medium text-medium-emphasis py-2 pl-4">COP:</td>
                      <td class="text-right font-weight-bold py-2 pr-4 text-info">
                        {{ formatCurrency(closing.real_cop, 'COP') }}
                        <span class="text-medium-emphasis font-weight-regular ml-1">(&asymp; {{ formatCurrency(closing.total_cop_in_usd, 'USD') }})</span>
                      </td>
                    </tr>
                  </tbody>
                </VTable>
              </VCardText>
            </VCard>
          </VCol>
          
          <VCol cols="12" v-if="filteredCashClosings.length === 0">
            <VAlert type="info" variant="tonal" class="rounded-lg text-body-2" icon="tabler-info-circle">
              No hay cajas con ventas registradas en este día.
            </VAlert>
          </VCol>
        </VRow>

        <!-- ESTRUCTURA OCULTA PARA EL REPORTE PDF (ESTILO A4 PREMIUM) -->
        <div id="daily-cash-report" class="d-none">
          <div style="padding: 30px; background-color: white; color: #1a202c; font-family: Roboto, Helvetica, Arial, sans-serif;">
            <!-- Encabezado con Diseño -->
            <table style=" border-block-end: 2px solid #2d3748;inline-size: 100%; margin-block-end: 25px; padding-block-end: 20px;">
              <tr>
                <td style="inline-size: 50%;">
                  <img :src="BASE64_LOGO_DATA" alt="Logo" style="inline-size: 160px;" />
                </td>
                <td style="inline-size: 50%; text-align: end;">
                  <h1 style="margin: 0; color: #2d3748; font-size: 26px; font-weight: 800; letter-spacing: 1px; text-transform: uppercase;">Consolidado de Caja</h1>
                  <p style=" color: #4a5568; font-size: 14px; font-weight: 600;margin-block: 5px; margin-inline: 0;">Reporte N°: <span style="color: #2b6cb0;">{{ props.cashData.id }}</span></p>
                  <p style="margin: 0; color: #718096; font-size: 12px;">Emisión: {{ formatDateTime(props.cashData.created_at, "all") }}</p>
                </td>
              </tr>
            </table>

            <!-- Resumen de Totales Estilo Dashboard -->
            <div style="margin-block-end: 35px;">
              <h2 style=" border-inline-start: 4px solid #3182ce; color: #2d3748;font-size: 18px; margin-block-end: 20px; padding-inline-start: 10px;">Resumen General del Día</h2>
              <table style=" border-collapse: separate; border-spacing: 10px 0;inline-size: 100%;">
                <tr>
                  <td style=" padding: 15px; border: 1px solid #e2e8f0; border-radius: 8px; background-color: #f7fafc;inline-size: 33%; text-align: center;">
                    <div style=" color: #718096;font-size: 10px; font-weight: bold; text-transform: uppercase;">Total BS</div>
                    <div style=" color: #2d3748;font-size: 16px; font-weight: bold; margin-block-start: 5px;">{{ formatCurrency(totalBsGlobal, 'BS') }}</div>
                    <div style=" color: #a0aec0;font-size: 11px;">&asymp; {{ formatCurrency(props.cashData.total_bs_in_usd, 'USD') }}</div>
                  </td>
                  <td style=" padding: 15px; border: 1px solid #e2e8f0; border-radius: 8px; background-color: #f7fafc;inline-size: 33%; text-align: center;">
                    <div style=" color: #718096;font-size: 10px; font-weight: bold; text-transform: uppercase;">Total COP</div>
                    <div style=" color: #2d3748;font-size: 16px; font-weight: bold; margin-block-start: 5px;">{{ formatCurrency(totalCopGlobal, 'COP') }}</div>
                    <div style=" color: #a0aec0;font-size: 11px;">&asymp; {{ formatCurrency(props.cashData.total_cop_in_usd, 'USD') }}</div>
                  </td>
                  <td style=" padding: 15px; border-radius: 8px; background-color: #2b6cb0; color: white;inline-size: 34%; text-align: center;">
                    <div style="font-size: 10px; font-weight: bold; opacity: 0.9; text-transform: uppercase;">Venta Bruta (USD)</div>
                    <div style="font-size: 20px; font-weight: 900; margin-block-start: 5px;">{{ formatCurrency(totalSalesGlobal, 'USD') }}</div>
                    <div style="font-size: 10px; opacity: 0.8;">Consolidado Multimoneda</div>
                  </td>
                </tr>
              </table>
            </div>

            <!-- Listado Detallado de Cajeros -->
            <div>
              <h2 style=" border-inline-start: 4px solid #3182ce; color: #2d3748;font-size: 18px; margin-block-end: 20px; padding-inline-start: 10px;">Desglose Detallado por Cajero</h2>
              <table style=" overflow: hidden; border-radius: 8px; border-collapse: collapse; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 10%);inline-size: 100%;">
                <thead style="background-color: #2d3748; color: white;">
                  <tr>
                    <th style="padding: 15px; font-size: 12px; text-align: start; text-transform: uppercase;">Cajero / Corte</th>
                    <th style="padding: 15px; font-size: 12px; text-align: end; text-transform: uppercase;">USD Real</th>
                    <th style="padding: 15px; font-size: 12px; text-align: end; text-transform: uppercase;">BS Real</th>
                    <th style="padding: 15px; font-size: 12px; text-align: end; text-transform: uppercase;">COP Real</th>
                    <th style="padding: 15px; background-color: #3182ce; font-size: 12px; text-align: end; text-transform: uppercase;">Venta Eq.</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(cash, index) in filteredCashClosings" :key="cash.id" :style="{ backgroundColor: index % 2 === 0 ? '#ffffff' : '#f7fafc' }">
                    <td style="padding: 15px; border-block-end: 1px solid #e2e8f0;">
                      <div style=" color: #2d3748;font-weight: bold;">{{ cash.seller?.username || 'Cajero Desconocido' }}</div>
                      <div style=" color: #718096;font-size: 10px;">Caja ID: #{{ cash.id }}</div>
                    </td>
                    <td style="padding: 15px; border-block-end: 1px solid #e2e8f0; color: #2f855a; font-weight: 600; text-align: end;">
                      {{ formatCurrency(cash.real_usd, 'USD') }}
                    </td>
                    <td style="padding: 15px; border-block-end: 1px solid #e2e8f0; text-align: end;">
                      <div style=" color: #c05621;font-weight: 600;">{{ formatCurrency(cash.real_bs, 'BS') }}</div>
                      <div style=" color: #a0aec0;font-size: 9px;">Eq: {{ formatCurrency(cash.total_bs_in_usd, 'USD') }}</div>
                    </td>
                    <td style="padding: 15px; border-block-end: 1px solid #e2e8f0; text-align: end;">
                      <div style=" color: #2c5282;font-weight: 600;">{{ formatCurrency(cash.real_cop, 'COP') }}</div>
                      <div style=" color: #a0aec0;font-size: 9px;">Eq: {{ formatCurrency(cash.total_cop_in_usd, 'USD') }}</div>
                    </td>
                    <td style="padding: 15px; background-color: rgba(49, 130, 206, 5%); border-block-end: 1px solid #e2e8f0; color: #2b6cb0; font-weight: 800; text-align: end;">
                      {{ formatCurrency(cash.real_total_usd, 'USD') }}
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- Footer con Notas -->
            <div style=" padding: 20px; border: 1px dashed #cbd5e0; border-radius: 8px; background-color: #f7fafc; color: #718096; font-size: 11px;margin-block-start: 50px; text-align: center;">
              <p style="margin: 0; font-weight: bold;">*** Este documento es una constancia digital oficial de cierre de caja ***</p>
              <p style="margin-block: 5px 0;margin-inline: 0;">Verificado y generado por el sistema de gestión el {{ formatDateTime(new Date(), "all") }}</p>
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
