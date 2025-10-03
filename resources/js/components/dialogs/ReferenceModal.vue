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
  reference: {
    type: Array,
    default: () => [],
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

const groupedReferences = computed(() => {
  if (!Array.isArray(props.reference) || props.reference.length === 0) {
    return {};
  }

  return props.reference.reduce((acc, currentRef) => {
    const currency = currentRef.order_currency;
    const method = currentRef.method;

    if (!acc[currency]) {
      acc[currency] = {};
    }

    if (!acc[currency][method]) {
      acc[currency][method] = [];
    }

    acc[currency][method].push(currentRef);
    return acc;
  }, {});
});

const translateMethod = (methodKey) => {
  const translations = {
    CARD: "Tarjeta",
    BANK_TRANSFER: "Transferencia",
    BANK_TRANSFER_BS: "Transferencia",
    BINANCE: "Binance",
    PAYPAL: "PayPal",
  };
  const upperKey = methodKey.toUpperCase();
  return translations[upperKey] || upperKey.replace(/_/g, " ");
};

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
    const element = document.getElementById("reference");
    if (!element) {
      console.error("No se encontró el contenido de las Referencias.");
      return;
    }
    const htmlContent = element.outerHTML;
    const params = {
      html_content: `<style>${ticketStyles}</style>${htmlContent}`,
      filename: "Referencias",
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
    let filename = "Referencias.pdf";
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
    const element = document.getElementById("reference");
    if (!element) {
      console.error("No se encontró el contenido de la Referencias.");
      return;
    }
    const htmlContent = element.outerHTML;

    const params = {
      html_content: `<style>${ticketStyles}</style>${htmlContent}`,
      filename: "Referencias",
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
      <VCardText id="reference">
        <div>
          <TicketHeader :logoSrc="BASE64_LOGO_DATA" />
        </div>

        <table class="w-100">
          <tr>
            <td class="text-left">
              <span class="font-weight-bold tituloAzulPrint">
                Cierre Diario N° {{ props.cashData.id }}</span
              >
            </td>
            <td class="text-right">
              <span>
                {{ formatDateTime(props.cashData.created_at, "date") }}
                {{ formatDateTime(props.cashData.created_at, "time") }}</span
              >
            </td>
          </tr>
        </table>

        <div class="mt-3">
          <SectionDivider
            :isPdf="true"
            text="REFERENCIAS"
            width="35%"
            class="mx-auto center-block"
          />

          <div
            v-for="(methods, currency) in groupedReferences"
            :key="currency"
            class="mb-4"
          >
            <div v-for="(references, method) in methods" :key="method">
              <h4
                class="text-center font-weight-bold my-2"
                style="font-size: 1rem"
              >
                {{ translateMethod(method) }} ({{ currency }})
              </h4>

              <table
                class="table table-borderless table-sm w-75 mx-auto center-block"
              >
                <tbody>
                  <tr v-for="(ref, refIndex) in references" :key="refIndex">
                    <td class="text-left">
                      <span>Ref: {{ ref.reference }}</span>
                    </td>
                    <td class="text-right">
                      <span>{{ ref.amount }}</span>
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
