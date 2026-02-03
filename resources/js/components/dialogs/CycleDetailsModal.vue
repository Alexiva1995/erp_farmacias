<script setup>
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { computed, onMounted, ref, watch } from "vue";

const props = defineProps({
  modelValue: {
    type: Boolean,
    required: true,
  },
  cycleId: {
    type: [Number, String, null],
    default: null,
  },
});

const emit = defineEmits(["update:modelValue"]);

const products = ref([]);
const cycleInfo = ref(null);
const loading = ref(false);
const totalProducts = ref(0);
const page = ref(1);
const itemsPerPage = ref(15);
const searchQuery = ref("");
const selectedLaboratory = ref(null);
const discrepancyFilter = ref(null);
const sortBy = ref();
const orderBy = ref();
const laboratories = ref([]);
const isLoadingFilters = ref(false);

const headers = ref([
  { title: "Producto", key: "product.name", sortable: true, width: "300px" },
  {
    title: "Stock Sistema",
    key: "system_quantity",
    align: "center",
    sortable: true,
  },
  {
    title: "Conteo Final",
    key: "final_quantity",
    align: "center",
    sortable: true,
  },
  {
    title: "Discrepancia",
    key: "discrepancy",
    align: "center",
    sortable: true,
  },
  {
    title: "Usuario Conteo",
    key: "user.email",
    align: "center",
    sortable: true,
  },
  {
    title: "Supervisor Aprobación",
    key: "supervisor.email",
    align: "center",
    sortable: true,
  },
]);

const isOpen = computed({
  get: () => props.modelValue,
  set: (value) => emit("update:modelValue", value),
});

const fetchSelectOptions = async () => {
  isLoadingFilters.value = true;
  try {
    const labResponse = await axios.get("/laboratories");
    laboratories.value = labResponse.data;
  } catch (error) {
    console.error("Error al cargar opciones de los selects:", error);
    toast.error("No se pudieron cargar los filtros.");
  } finally {
    isLoadingFilters.value = false;
  }
};

const fetchCycleProducts = async () => {
  if (!props.cycleId) return;

  loading.value = true;

  const params = {
    cycleId: props.cycleId,
    page: page.value,
    itemsPerPage: itemsPerPage.value,
    q: searchQuery.value,
    laboratoryId: selectedLaboratory.value,
    discrepancyFilter: discrepancyFilter.value,
    sortBy: sortBy.value,
    orderBy: orderBy.value,
  };

  Object.keys(params).forEach(
    (key) => (params[key] === null || params[key] === "") && delete params[key]
  );

  try {
    const response = await axios.get("/products/count", { params });
    console.log(response);

    products.value = response.data.data;
    totalProducts.value = response.data.total;

    if (page.value === 1 && !cycleInfo.value) {
      await fetchCycleInfo();
    }
  } catch (error) {
    console.error("Error al obtener productos del ciclo:", error);
    toast.error("No se pudieron cargar los productos del ciclo.");
  } finally {
    loading.value = false;
  }
};

const handleClearFilters = () => {
  searchQuery.value = "";
  selectedLaboratory.value = null;
  discrepancyFilter.value = null;
};

const fetchCycleInfo = async () => {
  try {
    const response = await axios.get(`/inventory/cycle/${props.cycleId}`);
    cycleInfo.value = response.data.data;
  } catch (error) {
    console.error("Error al obtener información del ciclo:", error);
  }
};

const formatDate = (dateString) => {
  if (!dateString) return "N/A";
  try {
    const date = new Date(dateString);
    const year = date.getFullYear();
    const month = (date.getMonth() + 1).toString().padStart(2, "0");
    const day = date.getDate().toString().padStart(2, "0");
    return `${year}-${month}-${day}`;
  } catch (error) {
    return "N/A";
  }
};


const updateOptions = (options) => {
  page.value = options.page;
  itemsPerPage.value = options.itemsPerPage;

  if (options.sortBy && options.sortBy.length > 0) {
    const sort = options.sortBy[0];
    const sortKeyMap = {
      product: 'product.name',
      'product.name': 'product.name',
      'product.laboratory.name': 'laboratory.name',
      count_final_quantity: 'final_quantity',
      final_quantity: 'final_quantity',
      count_system_quantity: 'system_quantity',
      system_quantity: 'system_quantity',
      discrepancy: 'discrepancy',
      user: 'user.email',
      'user.email': 'user.email',
      processed_at: 'processed_at',
      updated_at: 'updated_at',
    };

    sortBy.value = sortKeyMap[sort.key] || sort.key;
    orderBy.value = sort.order;
  } else {
    sortBy.value = undefined;
    orderBy.value = undefined;
  }
};

const closeModal = () => {
    isOpen.value = false;
  setTimeout(() => {
    products.value = [];
    cycleInfo.value = null;
    page.value = 1;
    searchQuery.value = "";
    selectedLaboratory.value = null;
    discrepancyFilter.value = null;
  }, 300);
};

watch(
  () => props.cycleId,
  (newCycleId) => {
    if (newCycleId && props.modelValue) {
      products.value = [];
      cycleInfo.value = null;
      page.value = 1;
      fetchSelectOptions();
      fetchCycleProducts();
    }
  }
);

let debounceTimer;
watch([page, itemsPerPage, searchQuery, selectedLaboratory, discrepancyFilter, sortBy, orderBy], () => {
  if (props.modelValue && props.cycleId) {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => fetchCycleProducts(), 300);
  }
});

watch([searchQuery, selectedLaboratory, discrepancyFilter], () => {
  page.value = 1;
});

watch(
  () => props.modelValue,
  (isOpen) => {
    if (isOpen && props.cycleId) {
      fetchSelectOptions();
      fetchCycleProducts();
    }
  }
);
</script>

<template>
  <VDialog v-model="isOpen" max-width="1400px" scrollable persistent>
    <VCard>
      <VCardTitle class="d-flex align-center justify-space-between pa-6 bg-primary">
        <div class="d-flex flex-column">
          <h3 class="text-h4 text-white mb-2">Detalles del Ciclo #{{ cycleId }}</h3>
          <div v-if="cycleInfo" class="d-flex align-center gap-2">
            <VChip
              :color="cycleInfo.status === 'active' ? 'success' : 'info'"
              size="small"
              class="text-white"
            >
              <VIcon :icon="cycleInfo.status === 'active' ? 'tabler-circle-check' : 'tabler-circle-check-filled'" size="16" class="me-1" />
              {{ cycleInfo.status === "active" ? "Activo" : "Cerrado" }}
            </VChip>
            <span class="text-white text-sm">
              <VIcon icon="tabler-calendar" size="16" class="me-1" />
              {{ formatDate(cycleInfo.start_date) }} - {{ formatDate(cycleInfo.end_date) }}
            </span>
          </div>
        </div>
        <VBtn icon="tabler-x" variant="text" size="small" color="white" @click="closeModal" />
      </VCardTitle>

      <VDivider />

      <VCardText class="pa-0">
        <!-- Filtros -->
        <div class="pa-4 bg-surface">
          <VRow class="align-center">
            <VCol cols="12" sm="6" md="3">
              <AppTextField
                v-model="searchQuery"
                placeholder="Buscar por Producto..."
                clearable
                prepend-inner-icon="tabler-search"
                @update:model-value="searchQuery = $event"
              />
            </VCol>
            <VCol cols="12" sm="6" md="3">
              <VAutocomplete
                v-model="selectedLaboratory"
                :items="laboratories"
                :loading="isLoadingFilters"
                label="Laboratorio"
                placeholder="Buscar un laboratorio"
                item-title="name"
                item-value="id"
                clearable
                prepend-inner-icon="tabler-building"
                @update:model-value="selectedLaboratory = $event"
              />
            </VCol>
            <VCol cols="12" sm="6" md="2">
              <VSelect
                v-model="discrepancyFilter"
                :items="[
                  { title: 'Con Discrepancia', value: 'with_discrepancy' },
                  { title: 'Sobrantes', value: 'surplus' },
                  { title: 'Faltantes', value: 'shortage' },
                  { title: 'Sin Discrepancia', value: 'exact' }
                ]"
                label="Filtrar por Discrepancia"
                placeholder="Todas"
                clearable
                prepend-inner-icon="tabler-filter"
                @update:model-value="discrepancyFilter = $event"
              />
            </VCol>
            <VCol cols="12" sm="6" md="2">
              <VBtn 
                color="secondary" 
                variant="outlined" 
                block
                prepend-icon="tabler-x"
                @click="handleClearFilters"
              >
                Limpiar Filtros
              </VBtn>
            </VCol>
          </VRow>
        </div>

        <VDivider />

        <!-- Tabla de productos -->
        <VDataTableServer
          :page="page"
          :items-per-page="itemsPerPage"
          :headers="headers"
          :items="products"
          :items-length="totalProducts"
          :loading="loading"
          class="text-no-wrap"
          @update:options="updateOptions"
          item-value="id"
          hover
          density="compact"
          :items-per-page-options="[
            { value: 10, title: '10' },
            { value: 15, title: '15' },
            { value: 25, title: '25' },
            { value: 50, title: '50' },
          ]"
        >
          <template #item.product.name="{ item: count }">
            <div class="d-flex align-start gap-x-3" style="max-width: 300px; width: 100%;">
              <VAvatar
                v-if="count.product?.photo_url"
                size="32"
                variant="tonal"
                rounded
                :image="count.product.photo_url"
                style="flex-shrink: 0;"
              />
              <div class="d-flex flex-column" style="min-width: 0; flex: 1; word-wrap: break-word; overflow-wrap: break-word;">
                <span
                  class="text-sm font-weight-medium text-high-emphasis"
                  :class="{ 
                    'text-primary': count.product.psychotropic == 1,
                    'text-warning font-weight-bold': count.product.psychotropic == 1 || count.product.psychotropic === true
                  }"
                  style="word-wrap: break-word; overflow-wrap: break-word; line-height: 1.3; white-space: normal;"
                >
                  {{ count.product.name?.toUpperCase() || 'N/A' }}
                  <span v-if="count.product.iva == 1"> (G)</span>
                  <span v-if="count.product.is_colombian_origin == 1"> (COL)</span>
                </span>
                <span class="text-xs text-disabled" v-if="count.product.laboratory?.name" style="word-wrap: break-word; overflow-wrap: break-word; line-height: 1.2; white-space: normal;">
                  {{ count.product.laboratory.name }}
                </span>
              </div>
            </div>
          </template>

          <template #item.final_quantity="{ item: count }">
            <span class="text-sm font-weight-medium">
              {{ count.final_quantity ?? count.counted_quantity }}
            </span>
          </template>

          <template #item.discrepancy="{ item: count }">
            <span
              v-if="count.discrepancy !== null"
              :class="{
                'text-success': count.discrepancy > 0,
                'text-error': count.discrepancy < 0,
                'text-medium-emphasis': count.discrepancy === 0,
              }"
              class="text-sm font-weight-bold"
            >
              {{
                count.discrepancy > 0
                  ? `+${count.discrepancy}`
                  : count.discrepancy
              }}
            </span>
            <span v-else class="text-xs text-disabled">N/A</span>
          </template>

          <template #item.user.email="{ item: count }">
            <span class="text-xs">
              {{ count.user?.email || "N/A" }}
            </span>
          </template>

          <template #item.supervisor.email="{ item: count }">
            <span class="text-xs">
              {{ count.supervisor?.email || "N/A" }}
            </span>
          </template>

        </VDataTableServer>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4">
        <VSpacer />
        <VBtn color="secondary" variant="outlined" @click="closeModal">
          Cerrar
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
:deep(.v-data-table) {
  font-size: 0.875rem;
}

:deep(.v-data-table td) {
  padding: 8px 16px !important;
  height: auto !important;
}

:deep(.v-data-table th) {
  padding: 10px 16px !important;
  font-size: 0.75rem !important;
  font-weight: 600 !important;
}

:deep(.v-data-table td:nth-child(1)) {
  white-space: normal !important;
  word-wrap: break-word !important;
  overflow-wrap: break-word !important;
  max-width: 300px !important;
  width: 300px !important;
  vertical-align: top !important;
  padding-top: 8px !important;
  padding-bottom: 8px !important;
  overflow: hidden !important;
}

:deep(.v-data-table th:nth-child(1)) {
  max-width: 300px !important;
  width: 300px !important;
  white-space: normal !important;
}

:deep(.v-data-table__wrapper) {
  overflow-x: auto;
}

:deep(.v-avatar) {
  width: 32px !important;
  height: 32px !important;
}

:deep(.v-chip) {
  height: 20px !important;
  font-size: 0.75rem !important;
}
</style>
