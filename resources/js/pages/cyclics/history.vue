<script setup>
import CycleSummaryFilters from "@/components/CycleSummaryFilters.vue";
import CycleSummaryTable from "@/components/CycleSummaryTable.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { onMounted, onUnmounted, ref, watch } from "vue";
import { useRouter } from "vue-router/auto";

const cycles = ref([]);
const totalCycles = ref(0);
const loading = ref(false);
const hasError = ref(false);
const page = ref(1);
const itemsPerPage = ref(10);
const sortBy = ref();
const orderBy = ref();

const startDate = ref(null);
const endDate = ref(null);
const cycleStatus = ref(null);
const isLoadingFilters = ref(false);

const router = useRouter();
let debounceTimer = null;

const fetchCycles = async () => {
  loading.value = true;
  hasError.value = false;

  const params = {
    page: page.value,
    itemsPerPage: itemsPerPage.value,
    sortBy: sortBy.value,
    orderBy: orderBy.value,
    startDate: startDate.value,
    endDate: endDate.value,
    cycleStatus: cycleStatus.value,
  };

  // Remover parámetros null o vacíos
  Object.keys(params).forEach(
    (key) => (params[key] === null || params[key] === "" || params[key] === undefined) && delete params[key]
  );

  try {
    const response = await axios.get("/inventory/cycle/summary", { params });
    cycles.value = response.data.data || [];
    totalCycles.value = response.data.total || 0;
  } catch (error) {
    console.error("Hubo un error al obtener el resumen de ciclos:", error);
    hasError.value = true;
    toast.error("No se pudo cargar el resumen de ciclos.");
  } finally {
    loading.value = false;
  }
};

watch(
  [page, itemsPerPage, sortBy, orderBy, startDate, endDate, cycleStatus],
  () => {
    if (debounceTimer) clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => fetchCycles(), 300);
  }
);

// Resetear página cuando se cambian los filtros
watch([startDate, endDate, cycleStatus], () => {
  page.value = 1;
});

onMounted(() => {
  fetchCycles();
});

onUnmounted(() => {
  if (debounceTimer) clearTimeout(debounceTimer);
});

const updateTableOptions = (options) => {
  page.value = options.page;
  itemsPerPage.value = options.itemsPerPage;
  if (options.sortBy && options.sortBy.length > 0) {
    sortBy.value = options.sortBy[0]?.key;
    orderBy.value = options.sortBy[0]?.order;
  } else {
    sortBy.value = undefined;
    orderBy.value = undefined;
  }
};

const handleClearFilters = () => {
  startDate.value = null;
  endDate.value = null;
  cycleStatus.value = null;
  sortBy.value = undefined;
  orderBy.value = undefined;
};

const handleSort = (sortOptions) => {
  sortBy.value = sortOptions.key;
  orderBy.value = sortOptions.order;
};

const viewCycleDetails = (cycleId) => {
  if (!cycleId) return;
  router.push(`/cyclics/details?id=${cycleId}`);
};
</script>

<template>
  <div>
    <CycleSummaryFilters
      v-model:startDate="startDate"
      v-model:endDate="endDate"
      v-model:cycleStatus="cycleStatus"
      :loading="isLoadingFilters"
      @clear="handleClearFilters"
      @sort="handleSort"
    />

    <VAlert
      v-if="hasError"
      type="error"
      variant="tonal"
      class="mt-4"
      closable
      title="Error de Carga"
      text="No se pudo obtener el historial de ciclos. Ocurrió un problema de conexión con el servidor."
    >
      <template #append>
        <VBtn
          color="error"
          size="small"
          variant="outlined"
          prepend-icon="tabler-refresh"
          @click="fetchCycles"
        >
          Reintentar
        </VBtn>
      </template>
    </VAlert>

    <CycleSummaryTable
      :cycles="cycles"
      :loading="loading"
      :total-cycles="totalCycles"
      :items-per-page="itemsPerPage"
      :page="page"
      @update:options="updateTableOptions"
      @view-cycle-details="viewCycleDetails"
    />
  </div>
</template>
