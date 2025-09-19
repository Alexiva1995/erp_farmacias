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

const isDownloadingPdf = ref(false);

const orders = ref([]);
const totalOrders = ref(0);
const loadingOrders = ref(false);
const pageOrders = ref(1);
const itemsPerPageOrders = ref(10);
const sortByOrders = ref();
const orderByOrders = ref();
const startDateFilter = ref(null);
const endDateFilter = ref(null);



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
    isDownloadingPdf.value = false;
    const cashToPrint = cash;
    cashData.value = cashToPrint;
    isPrinting.value = true;
    await nextTick();
    const printContents = document.getElementById("CashClosurePrint");

 if (!printContents) {
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
        printWindow.document.write(printContents.innerHTML);
        printWindow.document.write("</body></html>");
        printWindow.document.close();
        printWindow.focus();
        printWindow.print();
        printWindow.close();
  } catch (error) {
    console.error("Error al imprimir los detalles del cierre de caja:", error);
    toast.error("No se pudo cargar los detalles del cierre de caja.");
      isPrinting.value = false;
      cashData.value = null;
      isDownloadingPdf.value = false;
  } finally {
    setTimeout(() => {
      isPrinting.value = false;
      cashData.value = null;
      isDownloadingPdf.value = false;
    }, 500);
  }
}

const ticketStyles = `
.pa-2 { padding: 8px; }
.text-center { text-align: center; }
.text-right { text-align: right; }
.mb-2 { margin-bottom: 8px; }`;

const downloadcash = async (cash) => {
    try {
    isDownloadingPdf.value = true;
    const cashToDownload = cash;
    cashData.value = cashToDownload;
        isPrinting.value = true;
        await nextTick();
        const printContents = document.getElementById("CashClosurePrint");
          if (!printContents) {
            console.error("Elemento 'CashClosurePrint' no encontrado.");
            toast.error("Hubo un error al generar el PDF. Contenido no disponible.");
            return;
          }
            const htmlContent = printContents.innerHTML;

            const response = await axios.post("/finances/cash-closure/generate-pdf", {
                html: `<style>${ticketStyles}</style>${htmlContent}`,
                filename: `Cierre-Caja-${cash.id}.pdf`,
            }, {
                responseType: 'blob',
            });
            const url = window.URL.createObjectURL(new Blob([response.data]));
            const link = document.createElement('a');
            link.href = url;
            link.setAttribute('download', `Cierre-Caja-${cash.id}.pdf`);
            document.body.appendChild(link);
            link.click();
            link.remove();
            window.URL.revokeObjectURL(url);

            toast.success("PDF generado y descargado con éxito.");
        } catch (error) {
        console.error("Error al descargar el PDF:", error);
        toast.error("Hubo un error al generar y descargar el PDF.");
        isDownloadingPdf.value = false;
      } finally {
        isPrinting.value = false;
        cashData.value = null;
      }
  };

const handleCompleteClosure = async (data) => {
try {
 const payload = {
      id: data.cierre_id,
      total_cop: data.total_cop,
      sobrante_en_peso: data.sobrante_en_peso,
      entregar_efectivo_cop: data.entregar_efectivo_cop,
    };
    const response = await axios.post('/finances/cash-closure/close', payload);
    toast.success("Cierre de caja completado con éxito:");
    isCloseCashModalVisible.value = false;
    const completedCashData = response.data.cash_closure_data;
    await printCash(completedCashData);
    fetchCashClosure();
    fetchClosingHistory();
} catch (error) {
    console.error("Error al completar el cierre de caja:", error);
    if (error.response && error.response.data && error.response.data.message) {
      toast.error(error.response.data.message);
    } else {
      toast.error("Error al completar el cierre de caja.");
    }
  }
}




const updateTableOptionsOrders = (options) => {
  pageOrders.value = options.page;
  itemsPerPageOrders.value = options.itemsPerPage;
  if (options.sortBy && options.sortBy.length > 0) {
    sortByOrders.value = options.sortBy[0].key;
    orderByOrders.value = options.sortBy[0].order;
  } else {
    sortByOrders.value = null;
    orderByOrders.value = null;
  }
};

</script>

<template>
  <div>
    <p v-if="loading">Cargando resumen de caja...</p>
    <p v-else-if="!cashClosure">No hay datos de cierre de caja disponibles.</p>
    <CashSummary
      v-else
      :cash-closure-data="cashClosure"
      @requestCloseCash="handleRequestCloseCash"
    />

    <ClosedCashClosure
      v-model:isDialogVisible="isCloseCashModalVisible"
      :cash-closure-data="cashClosure"
      @complete-cash-closure="handleCompleteClosure"
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
      <CashClosureTicke v-if="isPrinting && cashData" :cash-data="cashData" :isPdf="isDownloadingPdf" />
    </div>
  </VCard>
  <div class="mb-5"></div>
  <VCard title="Lista de Ordenes">
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

 

  </VCard>
</template>
