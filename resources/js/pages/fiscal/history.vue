<script setup>
import HistoryFilters from "@/components/HistoryFilters.vue";
import HistoryTable from "@/components/HistoryTable.vue";
import DetailHistoryShowDialog from "@/components/dialogs/DetailHistoryShowDialog.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { onMounted, ref, watch } from "vue";
import { useDisplay } from "vuetify";

const { mobile } = useDisplay();
const histories = ref([]);
const totalHistories = ref(0);
const loading = ref(false);
const page = ref(1);
const itemsPerPage = ref(10);
const sortBy = ref();
const orderBy = ref();
const searchQuery = ref("");
const selectedLaboratory = ref(null);
const selectedOrigin = ref(null);
const stockStatusFilter = ref(null);
const startDate = ref(
  new Date(new Date().getFullYear(), 0, 1).toISOString().split("T")[0],
);
const endDate = ref(
  new Date(new Date().getFullYear(), 11, 31).toISOString().split("T")[0],
);
const origins = ref([]);
const historyNameToEdit = ref("");
const historyIdToEdit = ref(null);
const isEditDialogVisible = ref(false);
const currentProduct = ref({});
const productFormErrors = ref({});
const currentHistoryDetails = ref([]);
const currentHistoryUser = ref({});
const isLoadingFilters = ref(false);

const fetchHistorys = async () => {
  loading.value = true;
  const params = {
    q: searchQuery.value,
    page: page.value,
    itemsPerPage: itemsPerPage.value,
    sortBy: sortBy.value,
    orderBy: orderBy.value,
    startDate: startDate.value,
    endDate: endDate.value,
  };
  Object.keys(params).forEach(
    (key) => (params[key] === null || params[key] === "") && delete params[key],
  );

  try {
    const response = await axios.get("/history", { params });
    histories.value = response.data.data;
    totalHistories.value = response.data.total;
  } catch (error) {
    console.error("Hubo un error al obtener el historial fiscal:", error);
    toast.error("Error al obtener el historial.");
  } finally {
    loading.value = false;
  }
};

let debounceTimer;
watch(
  [
    page,
    itemsPerPage,
    sortBy,
    orderBy,
    searchQuery,
    selectedLaboratory,
    selectedOrigin,
    stockStatusFilter,
    startDate,
    endDate,
  ],
  () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => fetchHistorys(), 300);
  },
  { deep: true },
);

watch(
  [
    searchQuery,
    selectedLaboratory,
    selectedOrigin,
    stockStatusFilter,
    startDate,
    endDate,
  ],
  () => {
    page.value = 1;
  },
);

onMounted(async () => {
  await fetchHistorys();
});

const updateTableOptions = (options) => {
  page.value = options.page;
  itemsPerPage.value = options.itemsPerPage;
  sortBy.value = options.sortBy[0]?.key;
  orderBy.value = options.sortBy[0]?.order;
};

const handleShowDetailHistory = (history) => {
  currentProduct.value = { ...history };
  currentHistoryDetails.value = history.details || [];
  currentHistoryUser.value = history.user || {};
  productFormErrors.value = {};
  isEditDialogVisible.value = true;
  historyIdToEdit.value = history.id;
  historyNameToEdit.value = history.business_name;
};

const handleClearFilters = () => {
  searchQuery.value = "";
  startDate.value = new Date(new Date().getFullYear(), 0, 1)
    .toISOString()
    .split("T")[0];
  endDate.value = new Date(new Date().getFullYear(), 11, 31)
    .toISOString()
    .split("T")[0];
  sortBy.value = undefined;
  orderBy.value = undefined;
};

const handleAddProduct = () => {
  currentProduct.value = {};
  productFormErrors.value = {};
  isEditDialogVisible.value = true;
};

const clearFormErrors = () => {
  productFormErrors.value = {};
};

const handleExport = async (format) => {
  const params = {
    q: searchQuery.value,
    startDate: startDate.value,
    endDate: endDate.value,
    format: format,
  };

  Object.keys(params).forEach((key) => {
    if (params[key] === null || params[key] === "") {
      delete params[key];
    }
  });

  try {
    const response = await axios.get("/history/export", {
      params,
      responseType: "blob",
    });

    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement("a");
    link.href = url;

    const contentDisposition = response.headers["content-disposition"];
    let fileName = `historias.${format}`;
    if (contentDisposition) {
      const fileNameMatch = contentDisposition.match(/filename="(.+)"/);
      if (fileNameMatch && fileNameMatch.length === 2)
        fileName = fileNameMatch[1];
    }

    link.setAttribute("download", fileName);
    document.body.appendChild(link);
    link.click();

    link.remove();
    window.URL.revokeObjectURL(url);
    toast.success("Archivo exportado con éxito.");
  } catch (error) {
    console.error("Error al exportar los datos:", error);
    toast.error("Hubo un error al exportar el archivo.");
  }
};

const handleSort = (sortOptions) => {
  sortBy.value = sortOptions.key;
  orderBy.value = sortOptions.order;
};
</script>

<template>
  <div class="fiscal-history-page pb-12">
    <div class="d-flex flex-column gap-1 mt-1">
      <HistoryFilters
        v-model:searchQuery="searchQuery"
        v-model:startDate="startDate"
        v-model:endDate="endDate"
        :origins="origins"
        :loading="isLoadingFilters"
        @clear="handleClearFilters"
        @export="handleExport"
        @add-product="handleAddProduct"
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
        :errors="productFormErrors"
        :histories="currentProduct"
        @clear-errors="clearFormErrors"
      />
    </div>
  </div>
</template>
