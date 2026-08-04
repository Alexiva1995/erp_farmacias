<script setup>
import HistoryFilters from "@/components/HistoryFilters.vue";
import HistoryTable from "@/components/HistoryTable.vue";
import DetailHistoryShowDialog from "@/components/dialogs/DetailHistoryShowDialog.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { onMounted, ref, watch } from "vue";

// --- Estados ---
const histories = ref([]);
const totalHistories = ref(0);
const loading = ref(false);
const exportLoading = ref(false);
const page = ref(1);
const itemsPerPage = ref(10);
const sortBy = ref(undefined);
const orderBy = ref(undefined);
const searchQuery = ref("");

// Fechas predeterminadas: Año actual
const currentYear = new Date().getFullYear();
const startDate = ref(`${currentYear}-01-01`);
const endDate = ref(`${currentYear}-12-31`);

// Diálogo de detalle
const isEditDialogVisible = ref(false);
const currentProduct = ref({});
const currentHistoryDetails = ref([]);
const currentHistoryUser = ref({});
const historyIdToEdit = ref(null);
const historyNameToEdit = ref("");

// --- Métodos ---
const fetchHistories = async () => {
  loading.value = true;
  const params = {
    q: searchQuery.value || undefined,
    page: page.value,
    itemsPerPage: itemsPerPage.value,
    sortBy: sortBy.value || undefined,
    orderBy: orderBy.value || undefined,
    startDate: startDate.value || undefined,
    endDate: endDate.value || undefined,
  };

  try {
    const response = await axios.get("/history", { params });
    histories.value = response.data.data || [];
    totalHistories.value = response.data.total || 0;
  } catch (error) {
    console.error("Error al obtener el historial fiscal:", error);
    toast.error("Error al cargar el historial fiscal.");
  } finally {
    loading.value = false;
  }
};

// Debounce para evitar llamadas repetitivas al escribir en los filtros
let debounceTimer = null;
const triggerDebouncedFetch = () => {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => {
    fetchHistories();
  }, 300);
};

// Reajuste de página al cambiar filtros de búsqueda o fecha
watch([searchQuery, startDate, endDate], () => {
  page.value = 1;
  triggerDebouncedFetch();
});

// Reactividad a paginación y ordenamiento
watch([page, itemsPerPage, sortBy, orderBy], () => {
  triggerDebouncedFetch();
});

onMounted(() => {
  fetchHistories();
});

const updateTableOptions = (options) => {
  page.value = options.page;
  itemsPerPage.value = options.itemsPerPage;
  if (options.sortBy && options.sortBy.length > 0) {
    sortBy.value = options.sortBy[0].key;
    orderBy.value = options.sortBy[0].order;
  } else {
    sortBy.value = undefined;
    orderBy.value = undefined;
  }
};

const handleShowDetailHistory = (history) => {
  currentProduct.value = { ...history };
  currentHistoryDetails.value = history.details || [];
  currentHistoryUser.value = history.user || {};
  isEditDialogVisible.value = true;
  historyIdToEdit.value = history.id;
  historyNameToEdit.value = history.business_name || "";
};

const handleClearFilters = () => {
  searchQuery.value = "";
  startDate.value = `${currentYear}-01-01`;
  endDate.value = `${currentYear}-12-31`;
  sortBy.value = undefined;
  orderBy.value = undefined;
  page.value = 1;
};

const handleExport = async (format) => {
  if (exportLoading.value) return;

  exportLoading.value = true;
  const params = {
    q: searchQuery.value || undefined,
    startDate: startDate.value || undefined,
    endDate: endDate.value || undefined,
    format: format,
  };

  try {
    const response = await axios.get("/history/export", {
      params,
      responseType: "blob",
    });

    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement("a");
    link.href = url;

    const contentDisposition = response.headers["content-disposition"];
    let fileName = `HistoriaFiscal_${startDate.value}_${endDate.value}.${format}`;
    if (contentDisposition) {
      const fileNameMatch = contentDisposition.match(/filename="?([^"]+)"?/);
      if (fileNameMatch && fileNameMatch[1]) {
        fileName = fileNameMatch[1];
      }
    }

    link.setAttribute("download", fileName);
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(url);

    toast.success("Archivo exportado con éxito.");
  } catch (error) {
    console.error("Error al exportar el historial fiscal:", error);
    toast.error("No se pudo exportar el reporte fiscal.");
  } finally {
    exportLoading.value = false;
  }
};

const handleSort = (sortOptions) => {
  sortBy.value = sortOptions.key;
  orderBy.value = sortOptions.order;
};
</script>

<template>
  <div class="fiscal-history-page pb-12">
    <div class="d-flex flex-column gap-2 mt-1">
      <HistoryFilters
        v-model:searchQuery="searchQuery"
        v-model:startDate="startDate"
        v-model:endDate="endDate"
        :loading="loading || exportLoading"
        @clear="handleClearFilters"
        @export="handleExport"
        @sort="handleSort"
      />

      <HistoryTable
        :histories="histories"
        :loading="loading"
        :total-histories="totalHistories"
        :items-per-page="itemsPerPage"
        :page="page"
        @update:options="updateTableOptions"
        @show-detailHistory="handleShowDetailHistory"
      />

      <DetailHistoryShowDialog
        v-model="isEditDialogVisible"
        :history-name="historyNameToEdit"
        :history-id="historyIdToEdit"
        :details="currentHistoryDetails"
        :user="currentHistoryUser"
        :histories="currentProduct"
      />
    </div>
  </div>
</template>
