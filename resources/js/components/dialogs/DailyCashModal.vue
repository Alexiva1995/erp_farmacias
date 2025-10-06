<script setup>
import { defineProps, defineEmits, computed, nextTick } from "vue";
import { BASE64_LOGO_DATA } from "@/constants/logo.js";
import TicketHeader from "@/components/TicketHeader.vue";
import axios from "@/plugins/axios";
import SectionDivider from "@/components/SectionDivider.vue";
import { formatDateTime } from "@/utils/formatDateTime";
import { formatCurrency } from "@/utils/currencyFormatter";

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
  <VDialog v-model="dialogVisible" max-width="700px">
    <VCard>
      <VCardTitle class="d-flex align-center">
        <span class="headline"></span>
        <VSpacer />
        <VBtn icon variant="text" @click="closeModal">
          <VIcon>tabler-x</VIcon>
        </VBtn>
      </VCardTitle>
      <VCardText>
        <div id="daily-cash-report">
          <TicketHeader :logoSrc="BASE64_LOGO_DATA" />
          <div
            class="ticket-header d-flex justify-space-between align-start mt-2"
          >
            <span class="font-weight-bold tituloAzulPrint"
              >Cierre Diario N° {{ props.cashData.id }}</span
            >
            <div class="text-right d-flex flex-column align-end">
              <p class="text-black font-weight-regular mb-0 textoPrint">
                {{ formatDateTime(props.cashData.created_at, "date") }}
                {{ formatDateTime(props.cashData.created_at, "time") }}
              </p>
            </div>
          </div>
          <div class="container mt-3">
            <div class="w-100">
              <table
                v-if="groupedClosings.length > 0"
                style="
                  width: 100%;
                  border-collapse: separate;
                  border-spacing: 15px 15px;
                "
              >
                <tbody>
                  <tr
                    v-for="(pair, rowIndex) in groupedClosings"
                    :key="rowIndex"
                  >
                    <td
                      v-for="(cashData, colIndex) in pair"
                      :key="colIndex"
                      :colspan="isSingleSeller ? '2' : '1'"
                      :style="{
                        'vertical-align': 'top',
                        padding: '0',
                        margin: isSingleSeller ? '0 auto' : '0',
                        width: isSingleSeller ? '80%' : '50%',
                      }"
                      :class="{ 'mx-auto': isSingleSeller }"
                    >
                      <div
                        class="w-100"
                        :style="{
                          padding: '5px',
                          width: isSingleSeller ? '80%' : '100%',
                          'margin-left': isSingleSeller ? 'auto' : '0',
                          'margin-right': isSingleSeller ? 'auto' : '0',
                        }"
                      >
                        <SectionDivider
                          :isPdf="true"
                          :text="cashData.seller.username"
                          :width="getDividerWidth(cashData.seller.username)"
                          class="center-block"
                        />

                        <table
                          class="table table-sm table-borderless"
                          :class="{
                            'w-75 mx-auto center-block': isSingleSeller, // Centrar tabla interna
                            'w-100': !isSingleSeller,
                          }"
                        >
                          <tbody>
                            <tr>
                              <td class="text-left">
                                <span>ID: {{ cashData.id }}</span>
                              </td>
                            </tr>
                            <tr>
                              <td class="text-left"><span>USD:</span></td>
                              <td class="text-right">
                                <span
                                  >{{
                                    formatCurrency(
                                      parseFloat(cashData.total_usd || 0) +
                                        parseFloat(cashData.usd_credit || 0)
                                    )
                                  }}
                                </span>
                              </td>
                              <td class="text-right">
                                <span>{{
                                  formatCurrency(
                                    parseFloat(cashData.total_usd || 0) +
                                      parseFloat(cashData.usd_credit || 0)
                                  )
                                }}</span>
                              </td>
                            </tr>
                            <tr>
                              <td class="text-left"><span>BS:</span></td>
                              <td class="text-right">
                                <span>{{ cashData.total_bs }}</span>
                              </td>
                              <td class="text-right">
                                <span>{{ cashData.total_bs_in_usd }}</span>
                              </td>
                            </tr>
                            <tr>
                              <td class="text-left"><span>COP:</span></td>
                              <td class="text-right">
                                <span>{{ cashData.total_cop }}</span>
                              </td>
                              <td class="text-right">
                                <span>{{ cashData.total_cop_in_usd }}</span>
                              </td>
                            </tr>
                            <tr>
                              <td class="text-left"><span></span></td>
                              <td class="text-right fw-bold">
                                <span>TOTAL VENTA</span>
                              </td>
                              <td class="text-right fw-bold">
                                <span>{{ cashData.total_sales }}</span>
                              </td>
                            </tr>
                          </tbody>
                        </table>
                      </div>
                    </td>

                    <td
                      v-if="pair.length === 1 && !isSingleSeller"
                      style="width: 50%; padding: 0"
                    ></td>
                    
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
          <div class="mt-3">
            <SectionDivider
              :isPdf="true"
              text="TOTAL VENTA DIA"
              width="35%"
              class="mx-auto center-block"
            />
            <div>
              <table
                class="table table-borderless table-sm w-75 mx-auto center-block"
              >
                <tbody>
                  <tr>
                    <td class="text-left"><span>USD:</span></td>
                    <td class="text-right">
                      <span>{{ props.cashData.total_usd }}</span>
                    </td>
                    <td class="text-right">
                      <span>{{ props.cashData.total_usd }}</span>
                    </td>
                  </tr>
                  <tr>
                    <td class="text-left"><span>BS:</span></td>
                    <td class="text-right">
                      <span>{{ props.cashData.total_bs }}</span>
                    </td>
                    <td class="text-right">
                      <span>{{ props.cashData.total_bs_in_usd }}</span>
                    </td>
                  </tr>
                  <tr>
                    <td class="text-left"><span>COP:</span></td>
                    <td class="text-right">
                      <span>{{ props.cashData.total_cop }}</span>
                    </td>
                    <td class="text-right">
                      <span>{{ props.cashData.total_cop_in_usd }}</span>
                    </td>
                  </tr>
                  <tr>
                    <td class="text-start"><span></span></td>
                    <td class="text-right fw-bold"><span>TOTAL</span></td>
                    <td class="text-right fw-bold">
                      <span>{{ props.cashData.total_sales }}</span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </VCardText>
      <VCardActions class="p-2 d-flex justify-space-between w-100 mx-auto">
        <VBtn
          color="secondary"
          variant="outlined"
          @click="printReport"
          class="w-50"
        >
          Imprimir
        </VBtn>
        <VBtn
          color="primary"
          variant="flat"
          @click="downloadReport"
          class="w-50"
        >
          Descargar
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
