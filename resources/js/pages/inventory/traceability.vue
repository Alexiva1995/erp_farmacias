<script setup>
import TraceabilityReportFilters from "@/components/TraceabilityReportFilters.vue";
import TraceabilityReportTable from "@/components/TraceabilityReportTable.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { onMounted, ref, watch } from "vue";

const sales = ref([]);
const totalSales = ref(0);
const loading = ref(false);

const page = ref(1);
const itemsPerPage = ref(10);
const sortBy = ref();
const orderBy = ref();

const startDate = ref(null);
const endDate = ref(null);
const searchQuery = ref("");

const fetchSales = async () => {
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
    (key) => (params[key] === null || params[key] === "") && delete params[key]
  );

  try {
    const response = await axios.get("/sales/report", { params });
    sales.value = response.data.data;
    totalSales.value = response.data.total;
  } catch (error) {
    console.error("Hubo un error al obtener el reporte de ventas:", error);
    toast.error("Error al obtener el reporte.");
  } finally {
    loading.value = false;
  }
};

let debounceTimer;
watch(
  [page, itemsPerPage, sortBy, orderBy, searchQuery, startDate, endDate],
  () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => fetchSales(), 300);
  },
  { deep: true }
);

watch([searchQuery, startDate, endDate], () => {
  page.value = 1;
});

onMounted(() => {
  fetchSales();
});

const updateTableOptions = (options) => {
  page.value = options.page;
  itemsPerPage.value = options.itemsPerPage;
  sortBy.value = options.sortBy[0]?.key;
  orderBy.value = options.sortBy[0]?.order;
};

const handleClearFilters = () => {
  searchQuery.value = "";
  startDate.value = null;
  endDate.value = null;
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
    const response = await axios.get("/sales/report/export", {
      params,
      responseType: "blob",
    });

    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement("a");
    link.href = url;

    const contentDisposition = response.headers["content-disposition"];
    let fileName = `reporte_ventas.${format}`;
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
  } catch (error) {
    console.error("Error al exportar los datos:", error);
    toast.error("No se pudo exportar el reporte.");
  }
};
</script>

<template>
  <div>
    <TraceabilityReportFilters
      v-model:searchQuery="searchQuery"
      v-model:startDate="startDate"
      v-model:endDate="endDate"
      @clear="handleClearFilters"
      @export="handleExport"
    />

    <TraceabilityReportTable
      :sales="sales"
      :loading="loading"
      :total-sales="totalSales"
      :items-per-page="itemsPerPage"
      :page="page"
      @update:options="updateTableOptions"
    />
  </div>
</template>
