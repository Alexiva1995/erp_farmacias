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
.mb-2 { margin-bottom: 8px; }
.tbody-bordered { border: 1px solid #dfdfdff9; background-color: #f9f8f8; }
.center-block { margin-left: auto; margin-right: auto; }
.single-report-center { width: 50%; margin-left: auto; margin-right: auto; }`;

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
      "/finances/cash-closure/downloadMonthlyReport",
      params,
      {
        responseType: "blob",
      }
    );
    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement("a");
    let filename = "reporte.pdf";
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
      console.warn("Elemento #CashClosurePrint no encontrado.");
      window.print();
      return;
    }
    const printWindow = window.open("", "", "height=600,width=800");
    printWindow.document.write(
      "<html><head><title>Farmacia Barrio Sucre</title>"
    );
    const styleSheets = document.styleSheets;

    for (let i = 0; i < styleSheets.length; i++) {
      const sheet = styleSheets[i];
      try {
        if (sheet.cssRules) {
          let cssText = "";
          for (let j = 0; j < sheet.cssRules.length; j++) {
            cssText += sheet.cssRules[j].cssText;
          }
          printWindow.document.write(`<style>${cssText}</style>`);
        } else if (sheet.href) {
          printWindow.document.write(
            `<link rel="stylesheet" href="${sheet.href}">`
          );
        }
      } catch (e) {
        console.warn(
          "No se pudo acceder a la hoja de estilo:",
          sheet.href || sheet,
          e
        );
      }
    }
    printWindow.document.write("</head><body>");
    printWindow.document.write(element.innerHTML);
    printWindow.document.write("</body></html>");
    printWindow.document.close();
    printWindow.focus();
    printWindow.print();
    printWindow.close();
  } catch (error) {
    console.error("Error al inmprimir el PDF:", error);
  }
};
</script>

<template>
  <VDialog v-model="dialogVisible" max-width="500px">
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
            <div class="row">
              <div
                :class="[
            'mb-1',
            props.monthlyCashData.length === 1 ? 'col-md-6 offset-md-3' : 'col-md-6',
            props.monthlyCashData.length === 1 ? 'single-report-center' : ''
          ]"
                v-for="(cashData, index) in props.monthlyCashData"
                :key="index"
              >

              <div :class="{'mx-auto center-block': props.monthlyCashData.length === 1}">
                <SectionDivider
                  :isPdf="true"
                  :text="cashData.seller_name"
                  width="35%"
                />
                 </div>


                <div class="row">
                  <template
                    v-if="cashData && props.monthlyCashData.length === 1"
                  >
                    <div v-if="cashData.total_sales !== '0.00'">
                      <table class="table table-sm table-borderless w-50">
                        <tbody>
                          <tr>
                            <td class="text-start"><span>USD:</span></td>
                            <td class="text-right">
                              <span>{{ cashData.total_usd }}</span>
                            </td>
                            <td class="text-right">
                              <span>{{ cashData.total_usd }}</span>
                            </td>
                          </tr>
                          <tr>
                            <td class="text-start"><span>BS:</span></td>
                            <td class="text-right">
                              <span>{{ cashData.total_bs }}</span>
                            </td>
                            <td class="text-right">
                              <span>{{ cashData.total_bs }}</span>
                            </td>
                          </tr>
                          <tr>
                            <td class="text-start"><span>COP:</span></td>
                            <td class="text-right">
                              <span>{{ cashData.total_cop }}</span>
                            </td>
                            <td class="text-right">
                              <span>{{ cashData.total_cop }}</span>
                            </td>
                          </tr>
                          <tr>
                            <td class="text-start"><span></span></td>
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
                  </template>
                </div>
              </div>
            </div>
          </div>
        
          <SectionDivider :isPdf="true" text="TOTAL VENTA" width="35%" class="mx-auto center-block"  />
          <div class="container mt-3 w-100">
            <table class="table table-borderless table-sm w-50 mx-auto center-block">
              <tbody>
                <tr>
                  <td class="text-start"><span>USD:</span></td>
                  <td class="text-right">
                    <span>0,00</span>
                  </td>
                  <td class="text-right">
                    <span>0,00</span>
                  </td>
                </tr>
                <tr>
                  <td class="text-start"><span>BS:</span></td>
                  <td class="text-right">
                    <span>0,00</span>
                  </td>
                  <td class="text-right">
                    <span>0,00</span>
                  </td>
                </tr>
                <tr>
                  <td class="text-start"><span>COP:</span></td>
                  <td class="text-right">
                    <span>0,00</span>
                  </td>
                  <td class="text-right">
                    <span>0,00</span>
                  </td>
                </tr>
                <tr>
                  <td class="text-start"><span></span></td>
                  <td class="text-right fw-bold"><span>TOTAL</span></td>
                  <td class="text-right fw-bold">
                    <span>0,00</span>
                  </td>
                </tr>
              </tbody>
            </table>
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
