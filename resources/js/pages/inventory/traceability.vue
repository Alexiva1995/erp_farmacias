<script setup>
import TraceabilityReportFilters from "@/components/TraceabilityReportFilters.vue";
import TraceabilityReportTable from "@/components/TraceabilityReportTable.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { useAuthStore } from "@/stores/auth";
import { computed, onMounted, ref, watch } from "vue";
import { useRoute } from "vue-router";

const sales = ref([]);
const totalSales = ref(0);
const loading = ref(false);
const isExporting = ref(false);

const page = ref(1);
const itemsPerPage = ref(10);
const sortBy = ref(null);
const orderBy = ref(null);

const route = useRoute();
const authStore = useAuthStore();
const isAdmin = computed(() => authStore.user?.role_id === 1);

const startDate = ref(null);
const endDate = ref(null);
const searchQuery = ref(route.query.q ? String(route.query.q) : "");
const movementType = ref(null);

const fetchSales = async () => {
  loading.value = true;
  const params = {
    q: searchQuery.value || undefined,
    page: page.value,
    itemsPerPage: itemsPerPage.value,
    sortBy: sortBy.value || undefined,
    orderBy: orderBy.value || undefined,
    startDate: startDate.value || undefined,
    endDate: endDate.value || undefined,
    movement_type: movementType.value || undefined,
  };

  try {
    const { data } = await axios.get("/sales/report", { params });
    sales.value = data.data || [];
    totalSales.value = data.total || 0;
  } catch (error) {
    console.error("Hubo un error al obtener el reporte de trazabilidad:", error);
    toast.error("Error al obtener el reporte de trazabilidad.");
  } finally {
    loading.value = false;
  }
};

let debounceTimer = null;
const triggerSearch = () => {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => {
    page.value = 1;
    fetchSales();
  }, 350);
};

watch([searchQuery, startDate, endDate, movementType], () => {
  triggerSearch();
});

watch(
  () => route.query.q,
  (newQ) => {
    const val = newQ ? String(newQ) : "";
    if (val !== searchQuery.value) {
      searchQuery.value = val;
      page.value = 1;
      fetchSales();
    }
  }
);

const updateTableOptions = (options) => {
  page.value = options.page;
  itemsPerPage.value = options.itemsPerPage;
  if (options.sortBy && options.sortBy.length > 0) {
    sortBy.value = options.sortBy[0].key;
    orderBy.value = options.sortBy[0].order;
  } else {
    sortBy.value = null;
    orderBy.value = null;
  }
  fetchSales();
};

const handleClearFilters = () => {
  searchQuery.value = "";
  startDate.value = null;
  endDate.value = null;
  movementType.value = null;
};

const handleSelectProduct = (productId) => {
  searchQuery.value = String(productId);
};

const registeringBaseline = ref(false);
const handleRegisterBaseline = async () => {
  registeringBaseline.value = true;
  try {
    const { data } = await axios.post("/sales/report/register-baseline-adjustments");
    toast.success(data.message ?? "Ajustes iniciales registrados correctamente.");
    await fetchSales();
  } catch (error) {
    const msg = error.response?.data?.message ?? error.response?.data?.error ?? "Error al registrar ajustes.";
    toast.error(msg);
  } finally {
    registeringBaseline.value = false;
  }
};

const handleExport = async (format) => {
  isExporting.value = true;
  const params = {
    q: searchQuery.value || undefined,
    startDate: startDate.value || undefined,
    endDate: endDate.value || undefined,
    movement_type: movementType.value || undefined,
    format: format,
  };

  try {
    const response = await axios.get("/sales/report/export", {
      params,
      responseType: "blob",
    });

    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement("a");
    link.href = url;

    const contentDisposition = response.headers["content-disposition"];
    let fileName = `reporte_trazabilidad.${format}`;
    if (contentDisposition) {
      const fileNameMatch = contentDisposition.match(/filename="(.+)"/);
      if (fileNameMatch && fileNameMatch.length === 2) {
        fileName = fileNameMatch[1];
      }
    }

    link.setAttribute("download", fileName);
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(url);
    toast.success("Reporte exportado exitosamente.");
  } catch (error) {
    console.error("Error al exportar los datos:", error);
    toast.error("No se pudo exportar el reporte.");
  } finally {
    isExporting.value = false;
  }
};
</script>

<template>
  <div class="d-flex flex-column gap-y-4">
    <!-- Header de Página de Trazabilidad -->
    <VCard variant="flat" class="pa-4 border rounded-lg">
      <div class="d-flex align-center justify-space-between flex-wrap gap-4">
        <div class="d-flex align-center">
          <VAvatar color="primary" variant="tonal" size="48" class="me-3">
            <VIcon icon="tabler-history" size="28" />
          </VAvatar>
          <div>
            <h1 class="text-h5 font-weight-black text-high-emphasis leading-tight mb-0">
              Kardex y Trazabilidad de Inventario
            </h1>
            <p class="text-caption text-medium-emphasis mb-0">
              Auditoría cronológica de movimientos de inventario, stock inicial, final y responsables.
            </p>
          </div>
        </div>

        <div class="d-flex align-center gap-2 flex-wrap">
          <VBtn
            v-if="isAdmin"
            variant="tonal"
            color="warning"
            prepend-icon="tabler-adjustments-alt"
            :loading="registeringBaseline"
            @click="handleRegisterBaseline"
          >
            Ajuste Inicial Masivo
            <VTooltip activator="parent" location="bottom">
              Registrar baseline inicial de stock actual para trazabilidad
            </VTooltip>
          </VBtn>
        </div>
      </div>
    </VCard>

    <!-- Filtros de Trazabilidad -->
    <TraceabilityReportFilters
      v-model:searchQuery="searchQuery"
      v-model:startDate="startDate"
      v-model:endDate="endDate"
      v-model:selectedMovementType="movementType"
      :is-exporting="isExporting"
      @clear="handleClearFilters"
      @export="handleExport"
    />

    <!-- Tabla y Tarjetas de Trazabilidad -->
    <TraceabilityReportTable
      :sales="sales"
      :loading="loading"
      :total-sales="totalSales"
      :items-per-page="itemsPerPage"
      :page="page"
      @update:options="updateTableOptions"
      @filter-product="handleSelectProduct"
    />
  </div>
</template>
