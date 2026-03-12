<script setup>
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { ref, onMounted, watch } from "vue";
import ProductFilters from "../ProductFilters.vue";

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
    cellClass: "d-none d-sm-table-cell",
    headerClass: "d-none d-sm-table-cell"
  },
  { title: "Producto", key: "name", sortable: true },
  {
    title: "Acciones",
    key: "actions",
    sortable: false,
    width: "150px",
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

  toast.info(`El producto "${product.name}" ha sido agregado a la lista`);
};

const handleRemoveProduct = async (product) => {
  selectedProducts.value.delete(product.id);

  toast.warning(`El producto "${product.name}" ha sido removido de la lista`);
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

const handleClearSortOrder = () => {
  sortBy.value = undefined;
  orderBy.value = undefined;
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
    @update:model-value="closeDialog"
    :scrollable="true"
    content-class="d-flex"
  >
    <VCard class="d-flex flex-column">
      <VCardTitle class="d-flex align-center pa-4 pb-3 bg-primary">
        <VIcon 
          icon="tabler-plus" 
          size="24" 
          color="white" 
          class="me-2" 
        />
        <span class="text-h5 font-weight-bold text-white">Añadir productos al grupo</span>
        <VSpacer />
        <VBtn icon variant="text" color="white" size="small" @click="closeDialog">
          <VIcon>tabler-x</VIcon>
        </VBtn>
      </VCardTitle>

      <VDivider />

      <VCardText class="flex-grow-1 pa-4" style="overflow-y: auto;">
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
          @clear="handleClearFilters"
          @sort="handleSort"
        />

        <VDataTableServer
          :items-per-page="itemsPerPage"
          :page="page"
          :headers="headers"
          :items="products"
          :items-length="totalProduct"
          :loading="loading"
          class="text-no-wrap"
          @update:options="(options) => updateTableOptions(options)"
        >
          <template #item.id="{ item }">
            <span class="font-weight-medium">{{ item.id }}</span>
          </template>

          <template #item.name="{ item }">
            <div class="d-flex align-center gap-x-4">
              <VAvatar
                v-if="item.photo_url"
                size="38"
                variant="tonal"
                rounded
                :image="item.photo_url"
              />
              <div class="d-flex flex-column">
                <span
                  class="text-body-1 font-weight-medium text-high-emphasis"
                  :class="{
                    'text-warning font-weight-bold': item.psychotropic == 1 || item.psychotropic === true
                  }"
                >
                  <span class="d-inline d-sm-none text-primary font-weight-bold">[{{ item.id }}] </span>
                  {{ item.name.toUpperCase() }}
                  <span v-if="item.iva == 1 || item.iva === true"> (G)</span>
                  <span v-if="item.is_colombian_origin == 1 || item.is_colombian_origin === true"> (COL)</span>
                  <div v-if="item.laboratory" class="d-block d-md-none text-xs text-secondary italic">
                    {{ item.laboratory.name }}
                  </div>
                </span>
                <span class="text-sm text-disabled">
                  {{ item.active_ingredient }}
                </span>
              </div>
            </div>
          </template>

          <template #item.actions="{ item }">
            <div class="d-flex gap-2">
              <IconBtn
                :disabled="selectedProducts.has(item.id)"
                @click="handleAddProduct(item)"
                color="success"
              >
                <VIcon icon="tabler-plus" />
              </IconBtn>
              <IconBtn
                :disabled="!selectedProducts.has(item.id)"
                @click="handleRemoveProduct(item)"
                color="error"
              >
                <VIcon icon="tabler-trash" />
              </IconBtn>
            </div>
          </template>
        </VDataTableServer>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4 d-flex gap-2">
        <VBtn
          color="secondary"
          variant="outlined"
          @click="closeDialog"
          class="flex-grow-1"
          style=" flex: 1 1 50%;max-inline-size: 50%;"
        >
          Cancelar
        </VBtn>
        <VBtn
          color="primary"
          variant="flat"
          @click="submitForm"
          class="flex-grow-1"
          style=" flex: 1 1 50%;max-inline-size: 50%;"
        >
          Guardar
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
