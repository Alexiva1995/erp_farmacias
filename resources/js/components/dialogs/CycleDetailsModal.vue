<script setup>
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { computed, ref, watch } from "vue";

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
  { title: "Estado", key: "status", align: "center", sortable: true },
  {
    title: "Procesado Por",
    key: "supervisor.email",
    align: "center",
    sortable: false,
  },
  {
    title: "Fecha Proceso",
    key: "updated_at",
    align: "center",
    sortable: true,
  },
]);

const isOpen = computed({
  get: () => props.modelValue,
  set: (value) => emit("update:modelValue", value),
});

const fetchCycleProducts = async () => {
  if (!props.cycleId) return;

  loading.value = true;

  const params = {
    cycleId: props.cycleId,
    page: page.value,
    itemsPerPage: itemsPerPage.value,
    q: searchQuery.value,
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

const getStatusColor = (status) => {
  if (status === "confirmed") return "success";
  if (status === "rejected") return "error";
  return "grey";
};

const getStatusText = (status) => {
  if (status === "confirmed") return "Confirmado";
  if (status === "rejected") return "Rechazado";
  return "Pendiente";
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
  }, 300);
};

watch(
  () => props.cycleId,
  (newCycleId) => {
    if (newCycleId && props.modelValue) {
      products.value = [];
      cycleInfo.value = null;
      page.value = 1;
      fetchCycleProducts();
    }
  }
);

watch(
  () => props.modelValue,
  (isOpen) => {
    if (isOpen && props.cycleId) {
      fetchCycleProducts();
    }
  }
);

let debounceTimer;
watch([page, itemsPerPage, searchQuery], () => {
  if (props.modelValue && props.cycleId) {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => fetchCycleProducts(), 300);
  }
});

watch(searchQuery, () => {
  page.value = 1;
});
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
        <!-- Filtro de búsqueda -->
        <div class="pa-4 pb-0">
          <VTextField
            v-model="searchQuery"
            placeholder="Buscar por producto, ingrediente activo..."
            prepend-inner-icon="tabler-search"
            clearable
            density="compact"
            variant="outlined"
            hide-details
          />
        </div>

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
            <div class="d-flex align-center gap-x-3 py-2">
              <VAvatar
                v-if="count.product.photo_url"
                size="32"
                variant="tonal"
                rounded
                :image="count.product.photo_url"
              />
              <VAvatar
                v-else
                size="32"
                variant="tonal"
                rounded
                color="grey-lighten-2"
              >
                <VIcon icon="tabler-package" size="16" />
              </VAvatar>
              <div class="d-flex flex-column">
                <span
                  class="text-body-2 font-weight-medium text-high-emphasis"
                  :class="{ 'text-primary': count.product.psychotropic == 1 }"
                >
                  {{ count.product.name }}
                  <span v-if="count.product.iva == 1"> (G)</span>
                  <span v-if="count.product.is_colombian_origin == 1">
                    (COL)</span
                  >
                </span>
                <span class="text-xs text-disabled">
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

          <template #item.status="{ item: count }">
            <VChip :color="getStatusColor(count.status)" size="small" label>
              {{ getStatusText(count.status) }}
            </VChip>
          </template>

          <template #item.supervisor.email="{ item: count }">
            <span class="text-sm">
              {{ count.supervisor?.email || "N/A" }}
            </span>
          </template>

          <template #item.updated_at="{ item: count }">
            <span class="text-sm">{{ formatDate(count.updated_at) }}</span>
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
