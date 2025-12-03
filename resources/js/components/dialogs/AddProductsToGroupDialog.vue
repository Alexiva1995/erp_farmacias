<script setup>
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { ref } from "vue";
import QuotationFilters from "../QuotationFilters.vue";

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

const barcodeSearchQuery = ref("");
const filterSearchQuery = ref("");
const selectedLaboratory = ref(null);
const selectedOrigin = ref(null);
const stockStatusFilter = ref(null);
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
  };
  Object.keys(params).forEach(
    (key) => (params[key] === null || params[key] === "") && delete params[key]
  );
  try {
    const response = await axios.get("/tpv/quotation", { params });
    products.value = response.data.data;
    totalProduct.value = response.data.total;

    products.value.forEach((product) => {
      if (product.group_id === props.selectedGroup.id) {
        selectedProducts.value.add(product.id);
      }
    });

    console.log(selectedProducts.value);
  } catch (error) {
    console.error("Hubo un error al obtener los productos:", error);
    toast.error("Error al obtener los productos.");
  } finally {
    loading.value = false;
  }
};

const addProductToQuotationByBarcode = async (barcode) => {
  try {
    const response = await axios.get(`/barcode/${barcode}`);
    const productDetails = response.data;
    await addProductToQuotation({ productId: productDetails.id, quantity: 1 });
  } catch (error) {
    console.error(
      "Error al agregar producto por código de barras:",
      error.response ? error.response.data : error.message
    );
    toast.error(
      "Producto no encontrado o error al agregar por código de barras."
    );
  }
};

const addProductToQuotation = async ({ productId, quantity }) => {
  if (quantity <= 0) {
    toast.error("La cantidad a agregar debe ser mayor que cero.");
    return;
  }

  try {
    const response = await axios.get(`/tpv/quotation/${productId}`);
    const productDetails = response.data;
    const availableQuantity = productDetails.valid_stock_sum;
    if (quantity > availableQuantity) {
      toast.error(
        `No hay suficiente stock para "${productDetails.name}". Disponible: ${availableQuantity}. Solicitado: ${quantity}.`
      );
      return;
    }

    const existingItemIndex = quotationItems.value.findIndex(
      (item) => item.id === productId
    );
    if (existingItemIndex !== -1) {
      const currentSelectedQuantity =
        quotationItems.value[existingItemIndex].selectedQuantity;
      const newTotalSelectedQuantity = currentSelectedQuantity + quantity;

      if (newTotalSelectedQuantity > availableQuantity) {
        toast.warning(
          `Ya se agrego la cantidad maxima disponible de "${productDetails.name}"`
        );
        quotationItems.value[existingItemIndex].selectedQuantity =
          availableQuantity;
      } else {
        quotationItems.value[existingItemIndex].selectedQuantity =
          newTotalSelectedQuantity;
        toast.success(
          `Cantidad de "${productDetails.name}" incrementada a ${newTotalSelectedQuantity}.`
        );
      }
    } else {
      const itemToAdd = {
        id: productDetails.id,
        title: productDetails.name,
        active_ingredient: productDetails.active_ingredient,
        itemCode: productDetails.barcode,
        price: productDetails.sale_price,
        price_bs: productDetails.price_bs,
        price_cop: productDetails.price_cop,
        availableQuantity: availableQuantity,
        selectedQuantity: quantity,
        laboratory: productDetails.laboratory
          ? productDetails.laboratory.name
          : "N/A",
        taxRate: productDetails.iva == 1 ? 0.16 : 0,
      };
      quotationItems.value.push(itemToAdd);
      toast.success(`"${itemToAdd.title}" agregado a la cotización.`);
    }
  } catch (error) {
    console.error(
      "Error al obtener o agregar el producto a la cotización:",
      error.response ? error.response.data : error.message
    );
    toast.error(
      "Error al agregar el producto a la cotización. Inténtalo de nuevo."
    );
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
    isStrictSearch,
  ],
  () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => fetchProducts(), 300);
  },
  { deep: true }
);

watch(
  [filterSearchQuery, selectedLaboratory, selectedOrigin, stockStatusFilter],
  () => {
    page.value = 1;
  }
);

watch(barcodeSearchQuery, (newValue) => {
  clearTimeout(barcodeInputTimer);
  if (!newValue) {
    return;
  }
  if (newValue.length >= BARCODE_LENGTH_THRESHOLD) {
    barcodeInputTimer = setTimeout(async () => {
      await addProductToQuotationByBarcode(newValue);
      barcodeSearchQuery.value = "";
    }, 300);
  }
});

const headers = [
  { title: "ID", key: "id", sortable: true },
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
    toast.error("Hubo un error al añadir los productos al gruopo");
  }
};

const closeDialog = () => {
  emit("update:modelValue", false);
  handleClearForm();
};

const getRowColor = (item) => {
  const productId = item.item.id;

  if (selectedProducts.value.has(productId)) {
    return { class: "bg-primary" };
  }
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
  sortBy.value = undefined;
  orderBy.value = undefined;
};

const handleSort = (sortOptions) => {
  sortBy.value = sortOptions.key;
  orderBy.value = sortOptions.order;
};

onMounted(() => {
  fetchSelectOptions();
  fetchProducts();
});
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    :scrollable="true"
    max-width="900px"
    persistent
    @update:model-value="closeDialog"
  >
    <VCard>
      <VCardTitle class="d-flex align-center">
        <span class="text-h5 font-weight-bold"> Añadir productos </span>
        <VSpacer />
        <VBtn icon variant="text" @click="closeDialog">
          <VIcon>tabler-x</VIcon>
        </VBtn>
      </VCardTitle>

      <VDivider />

      <VCardText>
        <QuotationFilters
          v-model:searchQuery="filterSearchQuery"
          v-model:selectedLaboratory="selectedLaboratory"
          v-model:selectedOrigin="selectedOrigin"
          v-model:stockStatusFilter="stockStatusFilter"
          v-model:isStrictSearch="isStrictSearch"
          :laboratories="laboratories"
          :origins="origins"
          :loading="isLoadingFilters"
          @clear="handleClearFilters"
          @sort="handleSort"
          @clear-sort="handleClearSortOrder"
        />

        <VDataTableServer
          :items-per-page="itemsPerPage"
          :page="page"
          :headers="headers"
          :items="products"
          :items-length="totalProduct"
          :loading="loading"
          :row-props="getRowColor"
          class="text-no-wrap"
          fixed-header
          height="auto"
          @update:options="(options) => updateTableOptions(options)"
        >
          <template #item.id="{ item }">
            <span class="font-weight-medium">{{ item.id }}</span>
          </template>

          <template #item.name="{ item }">
            <div class="d-flex align-center gap-x-4">
              <div class="d-flex flex-column">
                <span class="text-body-1 font-weight-medium text-high-emphasis">
                  {{ item.name }}</span
                >
                <span class="text-sm text-disabled">
                  {{ item.active_ingredient }}
                  {{ item.laboratory ? " - " + item.laboratory?.name : "" }}
                </span>
              </div>
            </div>
          </template>

          <template #item.actions="{ item }">
            <IconBtn
              :disabled="selectedProducts.has(item.id)"
              @click="handleAddProduct(item)"
            >
              <VIcon icon="tabler-plus" />
            </IconBtn>
            <IconBtn
              :disabled="!selectedProducts.has(item.id)"
              @click="handleRemoveProduct(item)"
            >
              <VIcon icon="tabler-trash" />
            </IconBtn>
          </template>
        </VDataTableServer>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4">
        <VBtn
          color="secondary"
          variant="outlined"
          @click="closeDialog"
          class="flex-grow-1 w-0 mr-4"
        >
          Cancelar
        </VBtn>
        <VBtn
          color="primary"
          variant="flat"
          @click="submitForm"
          class="flex-grow-1 w-0"
        >
          Guardar
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
