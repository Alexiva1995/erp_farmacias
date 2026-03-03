<script setup>
import SectionDivider from "@/components/SectionDivider.vue";
import TicketHeader from "@/components/TicketHeader.vue";
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

const filteredCashClosings = computed(() => {
  if (!props.cashData || !props.cashData.cash_closings) {
    return [];
  }
  return props.cashData.cash_closings.filter(
    (closing) => closing.total_sales !== "0.00"
  );
});

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
                <h4 class="text-h6 font-weight-bold text-success">{{ formatCurrency(props.cashData.total_usd, 'USD') }}</h4>
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
                <h4 class="text-h6 font-weight-bold">{{ formatCurrency(props.cashData.total_bs, 'BS') }}</h4>
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
                <h4 class="text-h6 font-weight-bold">{{ formatCurrency(props.cashData.total_cop, 'COP') }}</h4>
                <div class="text-caption text-info font-weight-medium mt-1">&asymp; {{ formatCurrency(props.cashData.total_cop_in_usd, 'USD') }}</div>
              </VCardItem>
            </VCard>
          </VCol>

          <VCol cols="12" sm="6" md="3">
            <VCard variant="flat" class="bg-primary text-white rounded-lg border-0 shadow-sm h-100">
              <VCardItem class="pa-4">
                <div class="d-flex justify-space-between align-start mb-1">
                  <span class="text-caption font-weight-bold opacity-80">VENTA BRUTA GENERAL</span>
                  <VIcon icon="tabler-sum" color="white" size="20" />
                </div>
                <h4 class="text-h5 font-weight-bold text-white mt-2">{{ formatCurrency(props.cashData.total_sales, 'USD') }}</h4>
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
                     Venta: {{ formatCurrency(closing.total_sales, 'USD') }}
                  </VChip>
                </div>
              </VCardItem>
              
              <VCardText class="pa-0">
                <VTable density="compact" class="text-caption bg-transparent w-100 table-sm">
                  <tbody>
                    <tr>
                      <td class="font-weight-medium text-medium-emphasis py-2 pl-4">USD:</td>
                      <td class="text-right font-weight-bold py-2 pr-4 text-primary">
                        {{ formatCurrency(parseFloat(closing.total_usd || 0) + parseFloat(closing.usd_credit || 0), 'USD') }}
                      </td>
                    </tr>
                    <tr>
                      <td class="font-weight-medium text-medium-emphasis py-2 pl-4">BS:</td>
                      <td class="text-right font-weight-bold py-2 pr-4 text-warning">
                        {{ formatCurrency(closing.total_bs, 'BS') }} 
                        <span class="text-medium-emphasis font-weight-regular ml-1">(&asymp; {{ formatCurrency(closing.total_bs_in_usd, 'USD') }})</span>
                      </td>
                    </tr>
                    <tr>
                      <td class="font-weight-medium text-medium-emphasis py-2 pl-4">COP:</td>
                      <td class="text-right font-weight-bold py-2 pr-4 text-info">
                        {{ formatCurrency(closing.total_cop, 'COP') }}
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

        <!-- ESTRUCTURA OCULTA EXCLUSIVA PARA EL REPORTE PDF / TICKETERA -->
        <div id="daily-cash-report" class="d-none">
          <TicketHeader :logoSrc="BASE64_LOGO_DATA" />
          <div class="ticket-header d-flex justify-space-between align-start mt-2">
            <span class="font-weight-bold tituloAzulPrint">Cierre Diario N° {{ props.cashData.id }}</span>
            <div class="text-right d-flex flex-column align-end">
              <p class="text-black font-weight-regular mb-0 textoPrint">
                {{ formatDateTime(props.cashData.created_at, "date") }}
                {{ formatDateTime(props.cashData.created_at, "time") }}
              </p>
            </div>
          </div>
          <div class="container mt-3">
            <div class="w-100">
              <table v-if="groupedClosings.length > 0" style=" border-collapse: separate; border-spacing: 15px 15px;inline-size: 100%;">
                <tbody>
                  <tr v-for="(pair, rowIndex) in groupedClosings" :key="rowIndex">
                    <td
                      v-for="(cash, colIndex) in pair"
                      :key="colIndex"
                      :colspan="isSingleSeller ? '2' : '1'"
                      :style="{ 'vertical-align': 'top', padding: '0', margin: isSingleSeller ? '0 auto' : '0', width: isSingleSeller ? '80%' : '50%' }"
                      :class="{ 'mx-auto': isSingleSeller }"
                    >
                      <div class="w-100" :style="{ padding: '5px', width: isSingleSeller ? '80%' : '100%', 'margin-left': isSingleSeller ? 'auto' : '0', 'margin-right': isSingleSeller ? 'auto' : '0' }">
                        <SectionDivider :isPdf="true" :text="cash.seller?.username" :width="getDividerWidth(cash.seller?.username)" class="center-block" />
                        <table class="table table-sm table-borderless" :class="{ 'w-75 mx-auto center-block': isSingleSeller, 'w-100': !isSingleSeller }">
                          <tbody>
                            <tr>
                              <td class="text-left"><span>ID: {{ cash.id }}</span></td>
                            </tr>
                            <tr>
                              <td class="text-left"><span>USD:</span></td>
                              <td class="text-right">
                                <span>{{ formatCurrency(parseFloat(cash.total_usd || 0) + parseFloat(cash.usd_credit || 0)) }}</span>
                              </td>
                              <td class="text-right">
                                <span>{{ formatCurrency(parseFloat(cash.total_usd || 0) + parseFloat(cash.usd_credit || 0)) }}</span>
                              </td>
                            </tr>
                            <tr>
                              <td class="text-left"><span>BS:</span></td>
                              <td class="text-right"><span>{{ cash.total_bs }}</span></td>
                              <td class="text-right"><span>{{ cash.total_bs_in_usd }}</span></td>
                            </tr>
                            <tr>
                              <td class="text-left"><span>COP:</span></td>
                              <td class="text-right"><span>{{ cash.total_cop }}</span></td>
                              <td class="text-right"><span>{{ cash.total_cop_in_usd }}</span></td>
                            </tr>
                            <tr>
                              <td class="text-left"><span></span></td>
                              <td class="text-right fw-bold"><span>TOTAL VENTA</span></td>
                              <td class="text-right fw-bold"><span>{{ cash.total_sales }}</span></td>
                            </tr>
                          </tbody>
                        </table>
                      </div>
                    </td>
                    <td v-if="pair.length === 1 && !isSingleSeller" style=" padding: 0;inline-size: 50%;"></td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
          <div class="mt-3">
            <SectionDivider :isPdf="true" text="TOTAL VENTA DIA" width="35%" class="mx-auto center-block" />
            <div>
              <table class="table table-borderless table-sm w-75 mx-auto center-block">
                <tbody>
                  <tr>
                    <td class="text-left"><span>USD:</span></td>
                    <td class="text-right"><span>{{ props.cashData.total_usd }}</span></td>
                    <td class="text-right"><span>{{ props.cashData.total_usd }}</span></td>
                  </tr>
                  <tr>
                    <td class="text-left"><span>BS:</span></td>
                    <td class="text-right"><span>{{ props.cashData.total_bs }}</span></td>
                    <td class="text-right"><span>{{ props.cashData.total_bs_in_usd }}</span></td>
                  </tr>
                  <tr>
                    <td class="text-left"><span>COP:</span></td>
                    <td class="text-right"><span>{{ props.cashData.total_cop }}</span></td>
                    <td class="text-right"><span>{{ props.cashData.total_cop_in_usd }}</span></td>
                  </tr>
                  <tr>
                    <td class="text-start"><span></span></td>
                    <td class="text-right fw-bold"><span>TOTAL</span></td>
                    <td class="text-right fw-bold"><span>{{ props.cashData.total_sales }}</span></td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </VCardText>
      
      <VDivider />
      
      <VCardActions class="pa-4 bg-white d-flex justify-end gap-3 px-6">
        <VBtn variant="tonal" color="secondary" @click="closeModal" class="px-5 font-weight-medium">Cerrar</VBtn>
        <VBtn variant="outlined" color="primary" @click="printReport" prepend-icon="tabler-printer" class="px-5 font-weight-medium">Imprimir Ticket</VBtn>
        <VBtn variant="flat" color="primary" @click="downloadReport" prepend-icon="tabler-download" class="px-5 font-weight-medium">Descargar PDF</VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
