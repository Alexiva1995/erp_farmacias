<script setup>
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { ref, onMounted, watch, computed } from "vue";
import { useBrandingStore } from "@/stores/useBrandingStore";
import ProductFilters from "../ProductFilters.vue";
import AppMobilePagination from "@/components/AppMobilePagination.vue";

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  selectedGroup: { type: Object, required: true },
});

const brandingStore = useBrandingStore();
const isRestaurant = computed(() => false);

const emit = defineEmits(["update:modelValue"]);

const products = ref([]);
const totalProduct = ref(0);
const loading = ref(false);

const selectedProducts = ref(new Set());

const page = ref(1);
const itemsPerPage = ref(5);
const sortBy = ref();
const orderBy = ref();

const filterSearchQuery = ref("");
const selectedLaboratory = ref(null);
const selectedOrigin = ref(null);
const stockStatusFilter = ref(null);
const startDate = ref(null);
const endDate = ref(null);
const isStrictSearch = ref(false);
const isLoadingFilters = ref(false);

const laboratories = ref([]);
const origins = ref([]);

const fetchSelectOptions = async () => {
  isLoadingFilters.value = true;
  try {
    const [labResponse, originResponse] = await Promise.all([
      axios.get("/laboratories"),
      axios.get("/origins"),
    ]);
    laboratories.value = labResponse.data;
    origins.value = originResponse.data;
  } catch (error) {
    console.error("Error al cargar opciones de los selects:", error);
    toast.error("No se pudieron cargar los filtros.");
  } finally {
    isLoadingFilters.value = false;
  }
};

const fetchProducts = async () => {
  loading.value = true;
  const params = {
    q: filterSearchQuery.value,
    laboratoryId: selectedLaboratory.value,
    originId: selectedOrigin.value,
    ...(stockStatusFilter.value !== null && {
      hasStock: stockStatusFilter.value,
    }),
    isStrictSearch: isStrictSearch.value,
    page: page.value,
    itemsPerPage: itemsPerPage.value,
    sortBy: sortBy.value,
    orderBy: orderBy.value,
    startDate: startDate.value,
    endDate: endDate.value,
    // Filtrar productos sin grupo o del grupo actual
    withoutGroupOrCurrentGroup: props.selectedGroup.id,
  };
  Object.keys(params).forEach(
    (key) => (params[key] === null || params[key] === "") && delete params[key]
  );
  try {
    const response = await axios.get("/products", { params });
    products.value = response.data.data;
    totalProduct.value = response.data.total;

    products.value.forEach((product) => {
      if (product.group_id === props.selectedGroup.id) {
        selectedProducts.value.add(product.id);
      }
    });
  } catch (error) {
    console.error("Hubo un error al obtener los productos:", error);
    toast.error("Error al obtener los productos.");
  } finally {
    loading.value = false;
  }
};

const updateTableOptions = (options) => {
  page.value = options.page;
  itemsPerPage.value = options.itemsPerPage;
  sortBy.value = options.sortBy[0]?.key;
  orderBy.value = options.sortBy[0]?.order;
};

let debounceTimer;
watch(
  [
    page,
    itemsPerPage,
    sortBy,
    orderBy,
    filterSearchQuery,
    selectedLaboratory,
    selectedOrigin,
    stockStatusFilter,
    startDate,
    endDate,
    isStrictSearch,
  ],
  () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => fetchProducts(), 300);
  },
  { deep: true }
);

watch(
  [filterSearchQuery, selectedLaboratory, selectedOrigin, stockStatusFilter, startDate, endDate],
  () => {
    page.value = 1;
  }
);

const headers = [
  { 
    title: "ID", 
    key: "id", 
    sortable: true,
    width: "90px",
    cellClass: "font-weight-black text-primary d-none d-sm-table-cell",
    headerClass: "d-none d-sm-table-cell"
  },
  { title: "Producto", key: "name", sortable: true },
  {
    title: "Acciones",
    key: "actions",
    sortable: false,
    align: 'end',
    width: "100px",
  },
];

const handleClearForm = () => {
  selectedProducts.value.clear();
  page.value = 1;
  itemsPerPage.value = 5;

  fetchProducts();
  handleClearFilters();
};

const handleAddProduct = async (product) => {
  selectedProducts.value.add(product.id);
  // No mostramos toast repetitivo en cards moviles para no saturar
};

const handleRemoveProduct = async (product) => {
  selectedProducts.value.delete(product.id);
};

const submitForm = async () => {
  try {
    await axios.post(`/groups/${props.selectedGroup.id}/associate-products`, {
      productIds: [...selectedProducts.value],
    });

    toast.success(
      `Se actualizó el grupo "${props.selectedGroup.name}" con ${selectedProducts.value.size} productos asociados`
    );

    closeDialog();
  } catch (error) {
    console.log("Hubo un error al añadir los productos al grupo: ", error);
    toast.error("Hubo un error al añadir los productos al grupo");
  }
};

const closeDialog = () => {
  emit("update:modelValue", false);
  handleClearForm();
};

const handleClearFilters = () => {
  filterSearchQuery.value = "";
  selectedLaboratory.value = null;
  selectedOrigin.value = null;
  stockStatusFilter.value = null;
  startDate.value = null;
  endDate.value = null;
  sortBy.value = undefined;
  orderBy.value = undefined;
};

const handleSort = (sortOptions) => {
  if (sortOptions.key === undefined && sortOptions.order === undefined) {
    sortBy.value = undefined;
    orderBy.value = undefined;
  } else {
    sortBy.value = sortOptions.key;
    orderBy.value = sortOptions.order;
  }
};

watch(
  () => props.modelValue,
  (isVisible) => {
    if (isVisible) {
      fetchProducts();
    }
  }
);

onMounted(() => {
  fetchSelectOptions();
  fetchProducts();
});
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    max-width="1000px"
    persistent
    :fullscreen="$vuetify.display.xs"
    @update:model-value="closeDialog"
    :scrollable="true"
    content-class="d-flex"
  >
    <VCard class="d-flex flex-column overflow-hidden detail-dialog-card">
      <!-- Header Premium -->
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-4 d-flex align-center shadow-sm">
          <VAvatar color="white" variant="flat" size="44" class="me-4 elevation-2">
            <VIcon icon="tabler-plus" color="primary" size="26" />
          </VAvatar>
          <div class="flex-grow-1">
            <h2 class="text-h6 font-weight-black text-white leading-tight mb-0 text-uppercase">
              Asociar Productos
            </h2>
            <div class="d-flex align-center gap-2 mt-1">
              <span class="text-super-xs text-white opacity-75 uppercase font-weight-bold truncate" style="max-inline-size: 200px;">
                {{ props.selectedGroup.name }}
              </span>
            </div>
          </div>
          <VSpacer />
          <VBtn icon variant="tonal" color="white" size="small" class="rounded-lg ms-3" @click="closeDialog">
            <VIcon>tabler-x</VIcon>
          </VBtn>
        </div>
      </VCardTitle>

      <VDivider />

      <VCardText class="flex-grow-1 pa-2 pa-sm-4 bg-light" style="overflow-y: auto;">
        <ProductFilters
          v-model:searchQuery="filterSearchQuery"
          v-model:selectedLaboratory="selectedLaboratory"
          v-model:selectedOrigin="selectedOrigin"
          v-model:stockStatusFilter="stockStatusFilter"
          v-model:startDate="startDate"
          v-model:endDate="endDate"
          v-model:isStrictSearch="isStrictSearch"
          :laboratories="laboratories"
          :origins="origins"
          :loading="isLoadingFilters"
          mode="minimal"
          :show-add-button="false"
          :flat="true"
          class="mb-4 rounded-lg elevation-1 bg-white border pa-3"
          @clear="handleClearFilters"
          @sort="handleSort"
        />

        <!-- Desktop View -->
        <VCard variant="flat" class="d-none d-sm-block rounded-lg border overflow-hidden bg-white elevation-1">
          <VDataTableServer
            :items-per-page="itemsPerPage"
            :items-per-page-options="[5, 10, 25]"
            :page="page"
            :headers="headers"
            :items="products"
            :items-length="totalProduct"
            :loading="loading"
            density="compact"
            class="text-no-wrap premium-table"
            @update:options="(options) => updateTableOptions(options)"
          >
            <template #item.id="{ item }">
              <a
                :href="'/inventory/traceability?q=' + item.id"
                target="_blank"
                class="text-decoration-none font-weight-black text-primary"
              >
                {{ item.id }}
              </a>
            </template>

            <template #item.name="{ item }">
              <div class="d-flex align-center gap-x-2 py-1">
                <div class="d-flex flex-column min-width-0">
                  <span
                    class="text-sm font-weight-black text-high-emphasis text-uppercase text-truncate"
                    :class="{ 'text-warning': item.psychotropic == 1 || item.psychotropic === true }"
                    style="max-inline-size: 500px;"
                    :title="item.name"
                  >
                    {{ item.name?.toUpperCase() || '—' }}
                    <span v-if="item.iva == 1 || item.iva === true" class="text-xs text-disabled"> (G)</span>
                    <span v-if="item.is_colombian_origin == 1 || item.is_colombian_origin === true" class="text-xs text-disabled"> (COL)</span>
                  </span>
                  <div class="d-flex align-center gap-1 text-super-xs">
                    <span v-if="!isRestaurant" class="text-disabled truncate" style="max-inline-size: 260px;">
                      {{ item.active_ingredient || item.presentation || "Sin Especificación" }}
                    </span>
                    <span v-else class="text-disabled truncate" style="max-inline-size: 260px;">
                      {{ item.presentation || "S/P" }}{{ item.unit_of_measure ? ` (${item.unit_of_measure})` : '' }}
                    </span>
                    <span class="text-disabled mx-1">|</span>
                    <span class="text-primary font-weight-black text-uppercase truncate" style="max-inline-size: 200px;">
                      {{ item.laboratory?.name || 'S/L' }}
                    </span>
                  </div>
                </div>
              </div>
            </template>

            <template #item.actions="{ item }">
              <div class="d-flex gap-1 justify-end">
                <IconBtn
                  v-if="!selectedProducts.has(item.id)"
                  @click="handleAddProduct(item)"
                  color="success"
                  size="small"
                  class="rounded-lg bg-success-light"
                >
                  <VIcon icon="tabler-square-plus" size="18" />
                  <VTooltip activator="parent">Añadir al Grupo</VTooltip>
                </IconBtn>
                <IconBtn
                  v-else
                  @click="handleRemoveProduct(item)"
                  color="error"
                  size="small"
                  class="rounded-lg bg-error-light shadow-soft"
                >
                  <VIcon icon="tabler-square-minus" size="18" />
                  <VTooltip activator="parent">Quitar del Grupo</VTooltip>
                </IconBtn>
              </div>
            </template>
          </VDataTableServer>
        </VCard>

        <!-- Mobile View -->
        <div class="d-block d-sm-none">
          <div v-if="loading && products.length === 0" class="text-center py-10">
            <VProgressCircular indeterminate color="primary" />
          </div>
          <div v-else class="d-flex flex-column gap-3">
            <VCard
              v-for="item in products"
              :key="item.id"
              variant="flat"
              class="rounded-lg border bg-white overflow-hidden shadow-sm"
            >
              <div class="pa-3">
                <div class="d-flex justify-space-between align-start mb-1">
                  <div class="d-flex align-center gap-1 min-width-0">
                    <span class="text-xs font-weight-black text-primary">{{ item.id }}</span>
                    <span class="text-disabled">|</span>
                    <span class="text-xs font-weight-black text-primary uppercase truncate" style="max-inline-size: 150px;">
                      {{ item.laboratory?.name || 'S/L' }}
                    </span>
                  </div>
                  <VChip v-if="selectedProducts.has(item.id)" color="success" size="x-super-small" variant="flat" class="font-weight-black uppercase">Seleccionado</VChip>
                </div>
                <h3 class="text-sm font-weight-black text-high-emphasis text-uppercase truncate leading-tight mt-1">
                  {{ item.name }}
                  <span v-if="item.iva == 1 || item.iva === true" class="text-super-xs text-disabled"> (G)</span>
                  <span v-if="item.is_colombian_origin == 1 || item.is_colombian_origin === true" class="text-super-xs text-disabled"> (COL)</span>
                </h3>
                <div class="text-super-xs text-disabled truncate mt-1">
                  <span v-if="!isRestaurant">{{ item.active_ingredient || "Sin Especificación" }}</span>
                  <span v-else>{{ item.presentation || "S/P" }}{{ item.unit_of_measure ? ` (${item.unit_of_measure})` : '' }}</span>
                </div>
              </div>

              <!-- Button Actions Rectangular -->
              <div class="d-flex border-t border-opacity-10 overflow-hidden">
                <VBtn
                  v-if="!selectedProducts.has(item.id)"
                  color="success"
                  variant="tonal"
                  class="flex-grow-1 rounded-0 font-weight-black"
                  height="44"
                  @click="handleAddProduct(item)"
                >
                  <VIcon icon="tabler-plus" class="me-1" />
                  Añadir
                </VBtn>
                <VBtn
                  v-else
                  color="error"
                  variant="tonal"
                  class="flex-grow-1 rounded-0 font-weight-black"
                  height="44"
                  @click="handleRemoveProduct(item)"
                >
                  <VIcon icon="tabler-minus" class="me-1" />
                  Quitar
                </VBtn>
              </div>
            </VCard>

            <div class="mt-4">
               <AppMobilePagination
                :page="page"
                :items-per-page="itemsPerPage"
                :total-items="totalProduct"
                :loading="loading"
                @change="(options) => updateTableOptions(options)"
              />
            </div>
          </div>
        </div>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4 bg-light border-t">
        <VRow no-gutters class="w-100">
          <VCol cols="12" sm="6" class="pa-1">
            <VBtn
              color="secondary"
              variant="tonal"
              size="large"
              block
              height="50"
              class="font-weight-black rounded-lg text-button uppercase"
              @click="closeDialog"
            >
              Cancelar
            </VBtn>
          </VCol>
          <VCol cols="12" sm="6" class="pa-1">
            <VBtn
              color="primary"
              variant="flat"
              size="large"
              block
              height="50"
              class="font-weight-black rounded-lg shadow-primary text-button uppercase"
              @click="submitForm"
            >
              <VIcon icon="tabler-device-floppy" class="me-2" />
              Guardar Cambios
            </VBtn>
          </VCol>
        </VRow>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.header-gradient {
  background: var(--brand-gradient) !important;
}

.detail-dialog-card {
  border-radius: 12px !important;
}

.bg-light {
  background-color: #f8fafc !important;
}

.shadow-primary {
  box-shadow: 0 4px 14px 0 rgba(var(--v-theme-primary), 0.39) !important;
}

.shadow-soft {
  box-shadow: 0 2px 4px 0 rgba(0,0,0,0.05) !important;
}

.bg-success-light {
  background-color: rgba(var(--v-theme-success), 0.1) !important;
}

.bg-error-light {
  background-color: rgba(var(--v-theme-error), 0.1) !important;
}

.premium-table :deep(th) {
  background-color: #f8fafc !important;
  text-transform: uppercase !important;
  font-size: 0.75rem !important;
  font-weight: 700 !important;
  letter-spacing: 0.5px !important;
}

.premium-table :deep(td) {
  padding-block: 4px !important;
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: normal;
}

.leading-tight {
  line-height: 1.25 !important;
}

.leading-none {
  line-height: 1 !important;
}

.border-t {
  border-block-start: 1px solid rgba(var(--v-border-color), 0.08) !important;
}

.x-super-small {
  height: 14px !important;
  font-size: 0.6rem !important;
  padding: 0 4px !important;
}
</style>
