<script setup>
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { ref, onMounted, watch } from "vue";
import ProductFilters from "../ProductFilters.vue";
import AppMobilePagination from "@/components/AppMobilePagination.vue";

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  selectedGroup: { type: Object, required: true },
});

const emit = defineEmits(["update:modelValue"]);

const products = ref([]);
const totalProduct = ref(0);
const loading = ref(false);

const selectedProducts = ref(new Set());

const page = ref(1);
const itemsPerPage = ref(10);
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
    cellClass: "font-weight-black text-primary d-none d-sm-table-cell",
    headerClass: "d-none d-sm-table-cell"
  },
  { title: "Producto", key: "name", sortable: true, width: "450px" },
  {
    title: "Acciones",
    key: "actions",
    sortable: false,
    align: 'end',
    width: "120px",
  },
];

const handleClearForm = () => {
  selectedProducts.value.clear();
  page.value = 1;
  itemsPerPage.value = 10;

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
            :page="page"
            :headers="headers"
            :items="products"
            :items-length="totalProduct"
            :loading="loading"
            class="text-no-wrap premium-table"
            @update:options="(options) => updateTableOptions(options)"
          >
            <template #item.id="{ item }">
              <a
                :href="'/inventory/traceability?q=' + item.id"
                target="_blank"
                class="text-decoration-none font-weight-black text-primary"
              >
                #{{ item.id }}
              </a>
            </template>

            <template #item.name="{ item }">
              <div class="d-flex align-center gap-x-3 py-2">
                <VAvatar
                  v-if="item.photo_url"
                  size="38"
                  variant="tonal"
                  rounded
                  :image="item.photo_url"
                  class="border flex-shrink-0"
                />
                <VAvatar v-else size="38" variant="tonal" color="primary" rounded class="flex-shrink-0">
                  <VIcon icon="tabler-package" size="20" />
                </VAvatar>
                <div class="d-flex flex-column truncate" style="max-inline-size: 400px;">
                  <span
                    class="text-sm font-weight-black text-high-emphasis leading-tight text-uppercase truncate"
                    :class="{ 'text-warning': item.psychotropic == 1 }"
                  >
                    {{ item.name || 'N/A' }}
                    <VChip v-if="item.iva == 1" size="x-small" color="success" variant="flat" density="compact" class="ms-1 font-weight-black">G</VChip>
                  </span>
                  <div class="d-flex align-center gap-x-1 text-super-xs mt-1">
                    <span class="text-disabled truncate">{{ item.active_ingredient || "Sin Componente" }}</span>
                    <span class="text-disabled">|</span>
                    <span class="text-primary font-weight-black text-uppercase truncate">
                      {{ item.laboratory?.name || 'S/L' }}
                    </span>
                  </div>
                </div>
              </div>
            </template>

            <template #item.actions="{ item }">
              <div class="d-flex gap-2 justify-end">
                <IconBtn
                  v-if="!selectedProducts.has(item.id)"
                  @click="handleAddProduct(item)"
                  color="success"
                  class="rounded-lg bg-success-light"
                >
                  <VIcon icon="tabler-square-plus" />
                </IconBtn>
                <IconBtn
                  v-else
                  @click="handleRemoveProduct(item)"
                  color="error"
                  class="rounded-lg bg-error-light shadow-soft"
                >
                  <VIcon icon="tabler-square-minus" />
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
              <div class="pa-3 d-flex align-center gap-3">
                <VAvatar
                  v-if="item.photo_url"
                  size="50"
                  variant="tonal"
                  rounded
                  :image="item.photo_url"
                  class="border"
                />
                <VAvatar v-else size="50" variant="tonal" color="primary" rounded>
                    <VIcon icon="tabler-package" size="24" />
                </VAvatar>
                
                <div class="flex-grow-1 overflow-hidden">
                  <div class="d-flex justify-space-between align-start">
                    <span class="text-xs font-weight-black text-primary mb-1">#{{ item.id }}</span>
                    <VChip v-if="selectedProducts.has(item.id)" color="success" size="x-super-small" variant="flat" class="font-weight-black uppercase">Seleccionado</VChip>
                  </div>
                  <h3 class="text-sm font-weight-black text-high-emphasis text-uppercase truncate leading-none">
                    {{ item.name }}
                  </h3>
                  <div class="text-super-xs text-disabled truncate mt-1 uppercase font-weight-bold">
                    {{ item.laboratory?.name || 'S/L' }} | {{ item.active_ingredient || "S/C" }}
                  </div>
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
  background: linear-gradient(135deg, rgb(var(--v-theme-primary)) 0%, #1e5128 100%);
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
  padding-block: 10px !important;
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
