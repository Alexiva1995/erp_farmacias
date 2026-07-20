<script setup>
import TraceabilityReportFilters from "@/components/TraceabilityReportFilters.vue";
import TraceabilityReportTable from "@/components/TraceabilityReportTable.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { useAuthStore } from "@/stores/auth";
import { computed, onMounted, onUnmounted, ref, watch } from "vue";
import { useRoute } from "vue-router";

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
const movementType = ref(null);

const route = useRoute();
const authStore = useAuthStore();
const isAdmin = computed(() => authStore.user?.role_id === 1);

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
    movement_type: movementType.value,
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
  [page, itemsPerPage, sortBy, orderBy, searchQuery, startDate, endDate, movementType],
  () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => fetchSales(), 300);
  },
  { deep: true }
);

watch([searchQuery, startDate, endDate, movementType], () => {
  page.value = 1;
});

onMounted(() => {
  if (route.query.q) {
    searchQuery.value = route.query.q;
  }
  fetchSales();
});

onUnmounted(() => clearTimeout(debounceTimer));

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
  movementType.value = null;
};

const registeringBaseline = ref(false);
const handleRegisterBaseline = async () => {
  registeringBaseline.value = true;
  try {
    const { data } = await axios.post("/sales/report/register-baseline-adjustments");
    toast.success(data.message ?? "Ajustes iniciales registrados.");
    await fetchSales();
  } catch (error) {
    const msg = error.response?.data?.message ?? error.response?.data?.error ?? "Error al registrar ajustes.";
    toast.error(msg);
  } finally {
    registeringBaseline.value = false;
  }
};

const handleExport = async (format) => {
  const params = {
    q: searchQuery.value,
    startDate: startDate.value,
    endDate: endDate.value,
    movement_type: movementType.value,
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
      v-model:selectedMovementType="movementType"
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
