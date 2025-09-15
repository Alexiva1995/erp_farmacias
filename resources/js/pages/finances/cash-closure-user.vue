<script setup>
import CashSummary from "@/components/CashSummary.vue";
import axios from "@/plugins/axios";
import { ref, onMounted } from "vue";
import ClosedCashClosure from "@/components/dialogs/ClosedCashClosure.vue";
import ClosingHistoryTable from "@/components/ClosingHistoryTable.vue";
import { toast } from "@/plugins/sweetalert";
import CashClosureTicke from "@/components/CashClosureTicke.vue";

const loading = ref(false);
const cashClosure = ref([]);
const isCloseCashModalVisible = ref(false);

const closing = ref([]);
const totalClosing = ref(0);
const loadingClosing = ref(false);
const page = ref(1);
const itemsPerPage = ref(10);
const sortBy = ref();
const orderBy = ref();

const isPrinting = ref(false);
const cashData = ref(null);

const fetchCashClosure = async () => {
  try {
    loading.value = true;
    const response = await axios.get("/finances/cash-closure/");
    cashClosure.value = response.data;
  } catch (error) {
    console.error("Hubo un error al obtener el resumen de caja:", error);
    toast.error("Error al obtener el resumen de caja.");
    cashClosure.value = null;
  } finally {
    loading.value = false;
  }
};

onMounted(() => {
  fetchCashClosure();
  fetchClosingHistory();
});

const confirmCloseCash = async () => {
  console.log("Caja cerrada confirmada desde el padre!");
  try {
    //const response = await axios.post(`/finances/cash-closure/close/${cashClosure.value.id}`);
    toast.success("Caja cerrada con éxito.");
    isCloseCashModalVisible.value = false;
    fetchCashClosure();
  } catch (error) {
    console.error("Error al cerrar la caja:", error);
    toast.error(
      "Error al cerrar la caja: " +
        (error.response?.data?.message || error.message)
    );
  }
};

const handleRequestCloseCash = () => {
  isCloseCashModalVisible.value = true;
};

const fetchClosingHistory = async () => {
  loadingClosing.value = true;
  const params = {
    page: page.value,
    itemsPerPage: itemsPerPage.value,
    sortBy: sortBy.value,
    orderBy: orderBy.value,
  };
  Object.keys(params).forEach(
    (key) => (params[key] === null || params[key] === "") && delete params[key]
  );
  try {
    const response = await axios.get("/finances/cash-closure/closingHistory", { params });
    closing.value = response.data.data;
    totalClosing.value = response.data.total;
  } catch (error) {
    console.error("Hubo un error al obtener los cierres:", error);
    toast.error("Error al obtener los cierres.");
  } finally {
    loadingClosing.value = false;
  }
};

const updateTableOptions = (options) => {
  page.value = options.page;
  itemsPerPage.value = options.itemsPerPage;
  sortBy.value = options.sortBy[0]?.key;
  orderBy.value = options.sortBy[0]?.order;
};

const printCash = async (cash) => {
  try {
    cashData.value=cash;
    isPrinting.value = true;
    await nextTick();
    const printContents = document.getElementById("CashClosurePrint");

    if (printContents) {
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
        printWindow.document.write(printContents.innerHTML);
        printWindow.document.write("</body></html>");
        printWindow.document.close();
        printWindow.focus();
        printWindow.print();
        printWindow.close();

    }else {
        console.warn(
          "Elemento #CashClosurePrint no encontrado para impresión tipo ticket. Imprimiendo toda la página."
        );
        window.print();
      }


      setTimeout(() => {
      isPrinting.value = false;
      cashData.value = null;
    }, 500);

  } catch (error) {
    console.error("Error al imprimir los detalles del cierre de caja:", error);
    toast.error("No se pudo cargar los detalles del cierre de caja.");
      isPrinting.value = false;
      cashData.value = null;
  }
}

const ticketStyles = `
/* Estilos de Vuetify */
.pa-2 { padding: 8px; }
.text-start { text-align: left; }
.text-center { text-align: center; }
.align-start { align-items: flex-start; }
.mt-2 { margin-top: 8px; }
.mb-2 { margin-bottom: 8px; }
.align-end { align-items: flex-end; }
.text-right { text-align: right; }
.font-weight-bold { font-weight: 700; }
.font-weight-regular { font-weight: 400; }
.font-weight-black { font-weight: 900; }
.text-h6 { font-size: 1.25rem; }
.my-1 { margin-top: 4px; margin-bottom: 4px; }

/* Tus clases personalizadas */`;

const downloadcash = async (cash) => {
    try {
        cashData.value = { ...cash, isPdf: true };
        isPrinting.value = true;
        await nextTick();

        const printContents = document.getElementById("CashClosurePrint");

        if (printContents) {
            const htmlContent = printContents.innerHTML;

            const response = await axios.post("/finances/cash-closure/generate-pdf", {
                html: `<style>${ticketStyles}</style>${htmlContent}`,
                filename: `Cierre-Caja-${cash.id}.pdf`,
            }, {
                responseType: 'blob',
            });

            // 1. Obtén la URL del archivo
            const url = window.URL.createObjectURL(new Blob([response.data]));
            
            // 2. Crea un enlace temporal para la descarga
            const link = document.createElement('a');
            link.href = url;
            link.setAttribute('download', `Cierre-Caja-${cash.id}.pdf`);
            
            // 3. Simula un clic para descargar y luego limpia
            document.body.appendChild(link);
            link.click();
            link.remove();
            window.URL.revokeObjectURL(url);
            
            toast.success("PDF generado y descargado con éxito.");
        }
    } catch (error) {
        console.error("Error al descargar el PDF:", error);
        toast.error("Hubo un error al generar y descargar el PDF.");
    } finally {
        isPrinting.value = false;
        cashData.value = null;
    }
};

</script>

<template>
  <div>
    <p v-if="loading">Cargando resumen de caja...</p>
    <p v-else-if="!cashClosure">No hay datos de cierre de caja disponibles.</p>
    <CashSummary
      v-else
      :cashClosureData="cashClosure"
      @requestCloseCash="handleRequestCloseCash"
    />

    <ClosedCashClosure
      v-model:isDialogVisible="isCloseCashModalVisible"
      :cashClosureData="cashClosure"
    />
  </div>
  <div class="mb-5"></div>
  <VCard title="Histórico de cierre">
    <div class="mb-2"></div>
    <ClosingHistoryTable
      :closing="closing"
      :loading="loadingClosing"
      :total-closing="totalClosing"
      :items-per-page="itemsPerPage"
      :page="page"
      @update:options="updateTableOptions"
      @print-cash="printCash"
      @download-cash="downloadcash"
    />

    <div
    id="CashClosurePrint"
    :class="{ 'd-none': !isPrinting, 'print-container': true }"
  >
    <CashClosureTicke
      v-if="isPrinting && cashData"
      :cash-data="cashData"
    />
  </div>

  </VCard>
</template>
