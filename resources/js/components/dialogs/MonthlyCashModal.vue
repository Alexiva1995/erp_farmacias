<script setup>
import { defineProps, defineEmits, computed, nextTick } from "vue";
import { BASE64_LOGO_DATA } from "@/constants/logo.js";
import TicketHeader from "@/components/TicketHeader.vue";
import axios from "@/plugins/axios";
import SectionDivider from "@/components/SectionDivider.vue";

const props = defineProps({
  isDialogVisible: {
    type: Boolean,
    required: true,
  },
  monthlyCashData: {
    type: Object,
    default: () => ({}),
  },
  originalIds: {
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

const logoSrc = computed(() => {
  return BASE64_LOGO_DATA;
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
    const element = document.getElementById("monthly-cash-report");
    if (!element) {
      console.error("No se encontró el contenido del reporte.");
      return;
    }
    const htmlContent = element.outerHTML;

    const params = {
      html_content: `<style>${ticketStyles}</style>${htmlContent}`,
      filename: "Resumen_Cajas_Mensuales",
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
    let filename = "CierreCajaMensual.pdf";
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
    const element = document.getElementById("monthly-cash-report");
    if (!element) {
      console.error("No se encontró el contenido del reporte.");
      return;
    }
    const htmlContent = element.outerHTML;

    const params = {
      html_content: `<style>${ticketStyles}</style>${htmlContent}`,
      filename: "Resumen_Cajas_Mensuales",
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
        <div id="monthly-cash-report">
          <TicketHeader :logoSrc="BASE64_LOGO_DATA" />
          <div class="container mt-3">
            <div
              :class="{
                
              }"
            >
              <div
                class="row"
                v-if="props.monthlyCashData.summary.length > 0"
                :class="{
                  'd-flex flex-wrap': props.monthlyCashData.summary.length > 1,
                  'mb-4': true,
                  'pdf-row-multi': props.monthlyCashData.summary.length > 1,
                }"
              >
                <div
                  v-for="(cashData, index) in props.monthlyCashData.summary"
                  :key="index"
                  :class="{
                    'col-6 w-50': props.monthlyCashData.summary.length > 1,
                    'col-12': props.monthlyCashData.summary.length === 1,
                    'mb-4': true,
                    'pdf-col-multi': props.monthlyCashData.summary.length > 1,
                  }"
                >
                  <div class="w-100">
                    <SectionDivider
                      :isPdf="true"
                      :text="cashData.seller_name"
                      width="30%"
                      class="center-block"
                    />

                    <div v-if="cashData && cashData.total_sales !== '0.00'">
                      <table
                        class="table table-sm table-borderless"
                        :class="{
                          'w-75 mx-auto center-block': props.monthlyCashData.summary.length === 1,
                          'w-100': props.monthlyCashData.summary.length > 1,
                        }"
                      >
                        <tbody>
                          <tr>
                            <td class="text-left"><span>USD:</span></td>
                            <td class="text-right">
                              <span>{{ cashData.total_usd }}</span>
                            </td>
                            <td class="text-right">
                              <span>{{ cashData.total_usd }}</span>
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
                  </div>
                </div>
              </div>
              <div
                class="mt-3"
              >
                <SectionDivider
                  :isPdf="true"
                  text="TOTAL VENTA"
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
                          <span>{{ props.monthlyCashData.totalSalesUsd }}</span>
                        </td>
                        <td class="text-right">
                          <span>{{ props.monthlyCashData.totalSalesUsd }}</span>
                        </td>
                      </tr>
                      <tr>
                        <td class="text-left"><span>BS:</span></td>
                        <td class="text-right">
                          <span>{{ props.monthlyCashData.totalSalesBs }}</span>
                        </td>
                        <td class="text-right">
                          <span>{{
                            props.monthlyCashData.totalSalesBsInUSD
                          }}</span>
                        </td>
                      </tr>
                      <tr>
                        <td class="text-left"><span>COP:</span></td>
                        <td class="text-right">
                          <span>{{ props.monthlyCashData.totalSalesCop }}</span>
                        </td>
                        <td class="text-right">
                          <span>{{
                            props.monthlyCashData.totalSalesGlobalCopInUsd
                          }}</span>
                        </td>
                      </tr>
                      <tr>
                        <td class="text-start"><span></span></td>
                        <td class="text-right fw-bold"><span>TOTAL</span></td>
                        <td class="text-right fw-bold">
                          <span>{{
                            props.monthlyCashData.totalSalesGlobal
                          }}</span>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
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
