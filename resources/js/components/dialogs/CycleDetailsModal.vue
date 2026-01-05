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
const startDate = ref(null);
const endDate = ref(null);
const laboratories = ref([]);
const isLoadingFilters = ref(false);

const headers = ref([
  { title: "Producto", key: "product.name", sortable: true },
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
    sortable: false,
  },
  {
    title: "Supervisor Aprobación",
    key: "supervisor.email",
    align: "center",
    sortable: false,
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
    startDate: startDate.value,
    endDate: endDate.value,
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
  startDate.value = null;
  endDate.value = null;
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
};

const closeModal = () => {
  isOpen.value = false;
  setTimeout(() => {
    products.value = [];
    cycleInfo.value = null;
    page.value = 1;
    searchQuery.value = "";
    selectedLaboratory.value = null;
    startDate.value = null;
    endDate.value = null;
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
watch([page, itemsPerPage, searchQuery, selectedLaboratory, startDate, endDate], () => {
  if (props.modelValue && props.cycleId) {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => fetchCycleProducts(), 300);
  }
});

watch([searchQuery, selectedLaboratory, startDate, endDate], () => {
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
      <VCardTitle class="d-flex align-center justify-space-between">
        <div>
          <h3>Detalles del Ciclo #{{ cycleId }}</h3>
          <div v-if="cycleInfo" class="text-sm text-medium-emphasis mt-1">
            {{ formatDate(cycleInfo.start_date) }} -
            {{ formatDate(cycleInfo.end_date) }}
            <VChip
              :color="cycleInfo.status === 'active' ? 'success' : 'info'"
              size="x-small"
              class="ms-2"
            >
              {{ cycleInfo.status === "active" ? "Activo" : "Cerrado" }}
            </VChip>
          </div>
        </div>
        <VBtn icon="tabler-x" variant="text" size="small" @click="closeModal" />
      </VCardTitle>

      <VDivider />

      <VCardText class="pa-0">
        <!-- Filtros -->
        <div class="pa-4">
          <VRow>
            <VCol cols="12" sm="6" md="3">
              <AppTextField
                v-model="searchQuery"
                placeholder="Buscar por Producto, C. Activo..."
                clearable
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
                @update:model-value="selectedLaboratory = $event"
              />
            </VCol>
            <VCol cols="12" sm="6" md="3">
              <AppDateTimePicker
                v-model="startDate"
                placeholder="Desde"
                clearable
                :config="{
                  altInput: true,
                  altFormat: 'Y-m-d',
                  dateFormat: 'Y-m-d',
                }"
                @update:model-value="startDate = $event"
              />
            </VCol>
            <VCol cols="12" sm="6" md="3">
              <AppDateTimePicker
                v-model="endDate"
                placeholder="Hasta"
                clearable
                :config="{
                  altInput: true,
                  altFormat: 'Y-m-d',
                  dateFormat: 'Y-m-d',
                }"
                @update:model-value="endDate = $event"
              />
            </VCol>
          </VRow>
          <div class="d-flex justify-end mt-3">
            <VBtn color="secondary" variant="outlined" size="small" @click="handleClearFilters">
              Limpiar Filtros
            </VBtn>
          </div>
        </div>

        <VDivider />

        <!-- Tabla de productos -->
        <VDataTableServer
          v-model:page="page"
          v-model:items-per-page="itemsPerPage"
          :headers="headers"
          :items="products"
          :items-length="totalProducts"
          :loading="loading"
          class="text-no-wrap"
          @update:options="updateOptions"
          item-value="id"
          hover
          :items-per-page-options="[
            { value: 10, title: '10' },
            { value: 15, title: '15' },
            { value: 25, title: '25' },
            { value: 50, title: '50' },
          ]"
        >
          <template #item.product.name="{ item: count }">
            <div class="d-flex align-center gap-x-4">
              <div class="d-flex flex-column">
                <span
                  class="text-body-1 font-weight-medium text-high-emphasis"
                  :class="{ 'text-primary': count.product.psychotropic == 1 }"
                >
                  {{ count.product.name }}
                  <span v-if="count.product.iva == 1"> (G)</span>
                  <span v-if="count.product.is_colombian_origin == 1">
                    (COL)</span
                  >
                </span>
                <span class="text-sm text-disabled">
                  {{ count.product.active_ingredient }}
                </span>
              </div>
            </div>
          </template>

          <template #item.final_quantity="{ item: count }">
            <span class="font-weight-medium">
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
              class="font-weight-bold"
            >
              {{
                count.discrepancy > 0
                  ? `+${count.discrepancy}`
                  : count.discrepancy
              }}
            </span>
            <span v-else class="text-disabled">N/A</span>
          </template>

          <template #item.user.email="{ item: count }">
            <span class="text-sm">
              {{ count.user?.email || "N/A" }}
            </span>
          </template>

          <template #item.supervisor.email="{ item: count }">
            <span class="text-sm">
              {{ count.supervisor?.email || "N/A" }}
            </span>
          </template>

          <template #bottom>
            <VDivider />
            <div class="d-flex align-center justify-space-between pa-3">
              <div class="text-sm text-disabled">
                Mostrando {{ products.length }} de {{ totalProducts }} productos
              </div>
            </div>
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
