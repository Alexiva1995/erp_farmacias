<script setup>
import BarcodeSearchModal from "@/components/dialogs/BarcodeSearchModal.vue";
import ProductEditDialog from "@/components/dialogs/ProductEditDialog.vue";
import ProductFilters from "@/components/ProductFilters.vue";
import ProductTable from "@/components/ProductTable.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { computed, onMounted, ref, watch } from "vue";

const props = defineProps({
  invoiceId: { type: [Number, String], required: true },
  mode: { type: String, default: "editable" },
});
const emit = defineEmits(["back-to-list"]);

const invoice = ref(null);
const invoiceDetails = ref([]);
const formData = ref({});
const locationData = ref([]);
const loading = ref(true);
const loadingDetails = ref(true);
const isSaving = ref(false);

const isEditableMode = computed(() => props.mode === "editable");
const isLocationMode = computed(() => props.mode === "location");
const isReadOnly = computed(() => props.mode === "read-only");

const isEditMode = ref(false);

const editingDetailId = ref(null);
const editedDetailData = ref({});

const products = ref([]);
const totalProducts = ref(0);
const loadingProducts = ref(false);
const productSearchQuery = ref("");
const productPage = ref(1);
const productItemsPerPage = ref(10);
const productSortBy = ref();
const productOrderBy = ref();
const laboratories = ref([]);
const origins = ref([]);
const categories = ref([]);
const isLoadingFilters = ref(false);

const isEditDialogVisible = ref(false);
const isBarcodeModalVisible = ref(false);
const isProductSearchVisible = ref(false);
const searchingBarcode = ref(false);
const currentProduct = ref({});
const productFormErrors = ref({});
const barcodeModalRef = ref(null);

const processedInvoiceDetails = computed(() => {
  if (!invoice.value || !invoiceDetails.value) return [];

  return invoiceDetails.value.map((detail) => {
    const unitCost = detail.unit_cost || 0;
    const totalCost = detail.total_cost || (detail.quantity || 0) * unitCost;

    const rate = parseFloat(invoice.value.exchange_rate);
    const isUsd = invoice.value.currency === "USD";
    const hasValidRate = rate && rate > 0;

    const unitCostUsd = isUsd || !hasValidRate ? unitCost : unitCost / rate;
    const totalCostUsd = isUsd || !hasValidRate ? totalCost : totalCost / rate;

    return {
      ...detail,
      unit_cost: unitCost,
      total_cost: totalCost,
      unit_cost_usd: unitCostUsd,
      total_cost_usd: totalCostUsd,
    };
  });
});

onMounted(async () => {
  await fetchInvoiceData(props.invoiceId);
  if (invoice.value) {
    await fetchInvoiceDetails(props.invoiceId);
  }
});

watch(isEditMode, (newVal) => {
  if (isEditableMode.value && !newVal) {
    cancelEditingDetail();
    isProductSearchVisible.value = false;
  }
});

const fetchInvoiceData = async (id) => {
  loading.value = true;
  try {
    const response = await axios.get(`/invoices/${id}`);
    invoice.value = response.data.data;
    if (isEditableMode.value) {
      formData.value = JSON.parse(JSON.stringify(invoice.value));
    }
  } catch (error) {
    console.error("Error al cargar la factura:", error);
    toast.error("No se pudo cargar la información de la factura.");
    emit("back-to-list");
  } finally {
    loading.value = false;
  }
};

const fetchInvoiceDetails = async (id) => {
  loadingDetails.value = true;
  try {
    const response = await axios.get(`/invoices/${id}/details`);
    invoiceDetails.value = response.data.data ?? [];
    if (isLocationMode.value) {
      locationData.value = invoiceDetails.value.map((d) => ({
        id: d.id,
        location: d.location,
      }));
    }
  } catch (error) {
    console.error("Error al cargar los detalles de la factura:", error);
    toast.error("No se pudieron cargar los productos de la factura.");
    invoiceDetails.value = [];
  } finally {
    loadingDetails.value = false;
  }
};

const fetchProductSelectOptions = async () => {
  isLoadingFilters.value = true;
  try {
    const [labResponse, originResponse, categoryResponse] = await Promise.all([
      axios.get("/laboratories"),
      axios.get("/origins"),
      axios.get("/categories"),
    ]);
    laboratories.value = labResponse.data.data ?? labResponse.data ?? [];
    origins.value = originResponse.data.data ?? originResponse.data ?? [];
    categories.value = categoryResponse.data ?? [];
  } catch (error) {
    console.error("Error al cargar opciones de filtros de productos:", error);
    toast.error("No se pudieron cargar los filtros de productos.");
  } finally {
    isLoadingFilters.value = false;
  }
};

const fetchProducts = async () => {
  loadingProducts.value = true;
  const params = {
    q: productSearchQuery.value,
    page: productPage.value,
    itemsPerPage: productItemsPerPage.value,
    sortBy: productSortBy.value,
    orderBy: productOrderBy.value,
  };
  Object.keys(params).forEach(
    (key) => (params[key] === null || params[key] === "") && delete params[key]
  );

  try {
    const response = await axios.get("/products", { params });
    products.value = response.data.data ?? response.data ?? [];
    totalProducts.value = response.data.total ?? 0;
  } catch (error) {
    console.error("Hubo un error al obtener los productos:", error);
    toast.error("Error al obtener los productos.");
  } finally {
    loadingProducts.value = false;
  }
};

let productDebounceTimer;
watch(
  [
    productPage,
    productItemsPerPage,
    productSortBy,
    productOrderBy,
    productSearchQuery,
  ],
  () => {
    clearTimeout(productDebounceTimer);
    productDebounceTimer = setTimeout(() => fetchProducts(), 300);
  },
  { deep: true }
);

watch([productSearchQuery], () => {
  if (productPage.value !== 1) productPage.value = 1;
});

const updateProductTableOptions = (options) => {
  productPage.value = options.page;
  productItemsPerPage.value = options.itemsPerPage;
  productSortBy.value = options.sortBy[0]?.key;
  productOrderBy.value = options.sortBy[0]?.order;
};

const toggleEditMode = (enable) => {
  if (isReadOnly.value || isLocationMode.value) return;

  isEditMode.value = enable;
  if (!enable) {
    formData.value = JSON.parse(JSON.stringify(invoice.value));
    fetchInvoiceDetails(props.invoiceId);
  }
};

const addProductToInvoice = (product) => {
  const existingDetail = invoiceDetails.value.find(
    (detail) => detail.product.id === product.id
  );

  if (existingDetail) {
    existingDetail.quantity += 1;
    startEditingDetail(existingDetail);
    toast.info(
      `Producto "${product.name}" ya existe. Editando para completar campos.`
    );
  } else {
    const newDetail = {
      id: -Math.floor(Math.random() * 1000),
      product: { id: product.id, name: product.name },
      quantity: 1,
      unit_cost: 0,
      lot_number: "",
      expiration_date: null,
      location: "Por Asignar",
    };
    invoiceDetails.value.push(newDetail);
    startEditingDetail(newDetail);
    toast.success(
      `Producto "${product.name}" agregado. Complete los campos obligatorios.`
    );
  }
};

const handleAddProduct = () => {
  isBarcodeModalVisible.value = true;
};

const handleSearchBarcode = async (barcode) => {
  searchingBarcode.value = true;
  try {
    const response = await axios.get(`/products/search-by-barcode`, {
      params: { barcode },
    });
    if (response.data.data) {
      barcodeModalRef.value?.handleProductFound(response.data.data);
    } else {
      barcodeModalRef.value?.handleProductNotFound();
    }
  } catch (error) {
    console.error("Error al buscar producto por código de barras:", error);
    barcodeModalRef.value?.handleProductNotFound();
  } finally {
    searchingBarcode.value = false;
  }
};

const handleShowProductSearch = () => {
  isProductSearchVisible.value = true;
  if (laboratories.value.length === 0) fetchProductSelectOptions();
  fetchProducts();
};

const handleAddNewProduct = () => {
  currentProduct.value = {};
  productFormErrors.value = {};
  isEditDialogVisible.value = true;
};

const handleSaveProduct = async (productFormData) => {
  const url = "/products";
  try {
    await axios.post(url, productFormData, {
      headers: { "Content-Type": "multipart/form-data" },
    });
    toast.success("Producto creado con éxito");
    isEditDialogVisible.value = false;
    if (isProductSearchVisible.value) {
      await fetchProducts();
    }
  } catch (error) {
    if (error.response && error.response.status === 422) {
      productFormErrors.value = error.response.data.errors;
      toast.error("Por favor, corrige los errores en el formulario.");
    } else {
      console.error("Error al guardar el producto:", error);
      toast.error("Hubo un error al guardar el producto.");
    }
  }
};

const removeProductFromInvoice = (detailId) => {
  invoiceDetails.value = invoiceDetails.value.filter(
    (detail) => detail.id !== detailId
  );
};

const startEditingDetail = (detail) => {
  editedDetailData.value = { ...detail };
  editingDetailId.value = detail.id;
};

const saveEditingDetail = () => {
  if (
    !editedDetailData.value.quantity ||
    editedDetailData.value.quantity <= 0
  ) {
    toast.error("La cantidad debe ser mayor a 0");
    return;
  }
  if (
    !editedDetailData.value.unit_cost ||
    editedDetailData.value.unit_cost < 0
  ) {
    toast.error("El costo por unidad debe ser 0 o mayor");
    return;
  }
  if (!editedDetailData.value.lot_number?.trim()) {
    toast.error("El número de lote es obligatorio");
    return;
  }
  if (!editedDetailData.value.expiration_date) {
    toast.error("La fecha de vencimiento es obligatoria");
    return;
  }

  const detailIndex = invoiceDetails.value.findIndex(
    (d) => d.id === editingDetailId.value
  );
  if (detailIndex !== -1) {
    editedDetailData.value.total_cost =
      (editedDetailData.value.quantity || 0) *
      (editedDetailData.value.unit_cost || 0);
    invoiceDetails.value[detailIndex] = { ...editedDetailData.value };
    toast.success("Producto actualizado correctamente");
  }
  cancelEditingDetail();
};

const cancelEditingDetail = () => {
  editingDetailId.value = null;
  editedDetailData.value = {};
};

const handleUpdate = async () => {
  isSaving.value = true;
  const payload = {
    invoice: formData.value,
    details: invoiceDetails.value,
  };

  try {
    const response = await axios.put(`/invoices/${props.invoiceId}`, payload);
    toast.success(response.data.message || "Factura actualizada con éxito.");
    emit("back-to-list");
  } catch (error) {
    toast.error(
      error.response?.data?.message || "No se pudo actualizar la factura."
    );
  } finally {
    isSaving.value = false;
  }
};

const handleSaveLocations = async () => {
  const hasEmptyLocation = locationData.value.some(
    (d) =>
      !d.location ||
      d.location.trim() === "" ||
      d.location.trim() === "Por Asignar"
  );
  if (hasEmptyLocation) {
    toast.error("Por favor, asigne una localización a todos los productos.");
    return;
  }

  isSaving.value = true;
  const payload = { details: locationData.value };

  try {
    const response = await axios.put(
      `/invoices/${props.invoiceId}/locations`,
      payload
    );
    toast.success(response.data.message || "Ubicaciones guardadas con éxito.");
    emit("back-to-list");
  } catch (error) {
    toast.error(
      error.response?.data?.message || "No se pudieron guardar las ubicaciones."
    );
  } finally {
    isSaving.value = false;
  }
};

const formatNumber = (value) => {
  if (typeof value !== "number") return value;
  return new Intl.NumberFormat("es-VE", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(value);
};

const formatCurrency = (value, currency = null) => {
  if (typeof value !== "number") return value;
  const targetCurrency = currency || invoice.value?.currency;
  const currencyMap = { BS: "VES", Bs: "VES", COP: "COP", USD: "USD" };
  const mappedCurrency = currencyMap[targetCurrency] || "VES";
  return new Intl.NumberFormat("es-VE", {
    style: "currency",
    currency: mappedCurrency,
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(value);
};

const getCurrencySymbol = () => {
  if (!invoice.value?.currency) return "Bs.";
  const symbolMap = { BS: "Bs.", Bs: "Bs.", USD: "$", COP: "COP$" };
  return symbolMap[invoice.value.currency] || "Bs.";
};

const detailsHeaders = [
  { title: "Descripción", key: "product.name", sortable: false, width: "25%" },
  {
    title: "N° Lote",
    key: "lot_number",
    align: "center",
    sortable: false,
    width: "12%",
  },
  {
    title: "F. Vencimiento",
    key: "expiration_date",
    align: "center",
    sortable: false,
    width: "12%",
  },
  {
    title: "Localización",
    key: "location",
    align: "center",
    sortable: false,
    width: "12%",
  },
  {
    title: "Unidades",
    key: "quantity",
    align: "end",
    sortable: false,
    width: "10%",
  },
  {
    title: "Costo Unitario",
    key: "unit_cost",
    align: "end",
    sortable: false,
    width: "12%",
  },
  {
    title: "Costo Total",
    key: "total_cost",
    align: "end",
    sortable: false,
    width: "12%",
  },
  {
    title: "Acciones",
    key: "actions",
    sortable: false,
    align: "center",
    width: "5%",
  },
];
</script>

<template>
  <div>
    <div v-if="loading" class="text-center pa-10">
      <VProgressCircular indeterminate color="primary" size="64" />
      <p class="mt-4 text-h6">Cargando datos de la factura...</p>
    </div>

    <div v-else-if="invoice">
      <VAlert
        v-if="invoice.currency !== 'USD'"
        type="info"
        variant="tonal"
        density="compact"
        class="mb-4"
      >
        <template #prepend><VIcon icon="tabler-info-circle" /></template>
        <div>
          <strong>Factura en {{ invoice.currency }}</strong>
          <div class="text-caption mt-1">
            Se muestra el equivalente en USD calculado con la tasa de la factura
            ({{ formatNumber(invoice.exchange_rate) }})
          </div>
        </div>
      </VAlert>

      <VCard class="invoice-detail-card mb-6">
        <VForm
          @submit.prevent="
            isLocationMode ? handleSaveLocations() : handleUpdate()
          "
        >
          <VCardText class="header-section">
            <VRow align="center" justify="space-between" class="mb-4">
              <VCol cols="auto">
                <VBtn
                  icon="tabler-arrow-left"
                  variant="text"
                  @click="emit('back-to-list')"
                />
              </VCol>
              <VCol cols="auto">
                <VBtn
                  v-if="isEditableMode && !isEditMode"
                  @click="toggleEditMode(true)"
                  color="primary"
                  variant="tonal"
                >
                  Editar
                </VBtn>
              </VCol>
            </VRow>
            <VRow align="start" justify="space-between">
              <VCol cols="12" md="auto">
                <div>
                  <h1 class="font-weight-bold text-primary">
                    {{ invoice.supplier.name }}
                  </h1>
                  <div class="d-flex align-center mt-2">
                    <span class="text-subtitle-1 font-weight-medium me-2"
                      >N° DE CONTROL</span
                    >
                    <span class="text-h6 font-weight-bold text-error">{{
                      invoice.control_number
                    }}</span>
                  </div>
                </div>
              </VCol>
              <VCol cols="12" md="auto" class="text-md-end">
                <div class="d-flex align-center justify-md-end">
                  <span class="text-subtitle-1 font-weight-medium me-2"
                    >FACTURA N°</span
                  >
                  <span
                    class="text-h4 font-weight-bold text-error"
                    style="font-size: 2rem !important"
                    >{{ invoice.invoice_number }}</span
                  >
                </div>
              </VCol>
            </VRow>
          </VCardText>
          <VDivider />

          <VCardText class="dates-section">
            <VRow>
              <VCol
                v-for="(dateField, index) in [
                  'exp_date',
                  'payment_date',
                  'received_date',
                ]"
                :key="dateField"
                cols="12"
                md="4"
                :class="{
                  'text-start': index === 0,
                  'text-center': index === 1,
                  'text-end': index === 2,
                }"
              >
                <p class="text-subtitle-2 text-disabled">
                  {{
                    {
                      exp_date: "Fecha de Vencimiento",
                      payment_date: "Fecha Límite Pago",
                      received_date: "Fecha de Recibo",
                    }[dateField]
                  }}
                </p>
                <p class="text-body-1 font-weight-medium mt-1">
                  {{ invoice[dateField] }}
                </p>
              </VCol>
            </VRow>
          </VCardText>
          <VDivider />

          <VCardText class="products-section">
            <div class="d-flex align-center mb-4">
              <span class="text-h6 font-weight-medium">Productos</span>
              <VChip color="primary" variant="outlined" class="ms-2"
                >{{ invoiceDetails.length }} productos</VChip
              >
              <VSpacer />
              <VBtn
                v-if="isEditableMode && isEditMode"
                color="primary"
                variant="flat"
                size="small"
                class="me-3"
                @click="handleAddProduct"
              >
                <VIcon icon="tabler-plus" class="me-2" />
                Agregar Producto
              </VBtn>
            </div>

            <VDataTable
              :headers="detailsHeaders"
              :items="processedInvoiceDetails"
              :loading="loadingDetails"
              :hide-default-footer="true"
              class="invoice-products-table"
            >
              <template #item.lot_number="{ item }">
                <VTextField
                  v-if="isEditableMode && item.id === editingDetailId"
                  v-model="editedDetailData.lot_number"
                  density="compact"
                  hide-details
                  variant="outlined"
                  class="editable-cell"
                  placeholder="Ingrese lote"
                />
                <span v-else>{{ item.lot_number || "-" }}</span>
              </template>

              <template #item.expiration_date="{ item }">
                <AppDateTimePicker
                  v-if="isEditableMode && item.id === editingDetailId"
                  v-model="editedDetailData.expiration_date"
                  density="compact"
                  class="editable-cell"
                  placeholder="F. Vencimiento"
                />
                <span v-else>{{ item.expiration_date || "-" }}</span>
              </template>

              <template #item.location="{ item, index }">
                <VTextField
                  v-if="isLocationMode"
                  v-model="locationData[index].location"
                  density="compact"
                  hide-details
                  variant="outlined"
                  class="editable-cell"
                  placeholder="Ej: A-01-B"
                />
                <span v-else>{{ item.location || "-" }}</span>
              </template>

              <template #item.quantity="{ item }">
                <VTextField
                  v-if="isEditableMode && item.id === editingDetailId"
                  v-model.number="editedDetailData.quantity"
                  type="number"
                  density="compact"
                  hide-details
                  variant="outlined"
                  class="editable-cell"
                  min="1"
                />
                <span v-else>{{ item.quantity }}</span>
              </template>

              <template #item.unit_cost="{ item }">
                <VTextField
                  v-if="isEditableMode && item.id === editingDetailId"
                  v-model.number="editedDetailData.unit_cost"
                  type="number"
                  step="0.01"
                  density="compact"
                  hide-details
                  variant="outlined"
                  class="editable-cell"
                  min="0"
                  :prefix="getCurrencySymbol()"
                />
                <div v-else class="d-flex flex-column align-end">
                  <span class="font-weight-medium">{{
                    formatCurrency(item.unit_cost, invoice.currency)
                  }}</span>
                  <span
                    v-if="invoice.currency !== 'USD'"
                    class="text-caption text-medium-emphasis"
                    >{{ formatCurrency(item.unit_cost_usd, "USD") }}</span
                  >
                </div>
              </template>

              <template #item.total_cost="{ item }">
                <div class="d-flex flex-column align-end">
                  <span class="font-weight-medium">{{
                    formatCurrency(item.total_cost, invoice.currency)
                  }}</span>
                  <span
                    v-if="invoice.currency !== 'USD'"
                    class="text-caption text-medium-emphasis"
                    >{{ formatCurrency(item.total_cost_usd, "USD") }}</span
                  >
                </div>
              </template>

              <template #item.actions="{ item }">
                <div v-if="isEditableMode && isEditMode">
                  <div v-if="item.id === editingDetailId" class="d-flex">
                    <IconBtn @click="saveEditingDetail"
                      ><VIcon icon="tabler-check" color="success" size="22"
                    /></IconBtn>
                    <IconBtn @click="cancelEditingDetail"
                      ><VIcon icon="tabler-x" color="error" size="22"
                    /></IconBtn>
                  </div>
                  <div v-else class="d-flex">
                    <IconBtn @click="removeProductFromInvoice(item.id)"
                      ><VIcon icon="tabler-trash" size="20"
                    /></IconBtn>
                    <IconBtn @click="startEditingDetail(item)"
                      ><VIcon icon="tabler-edit" size="20"
                    /></IconBtn>
                  </div>
                </div>
              </template>
              <template #bottom></template>
            </VDataTable>
          </VCardText>
          <VDivider />

          <VCardText class="totals-section">
            <div class="totals-list">
              <div class="total-item-row">
                <span class="text-subtitle-2 text-disabled"
                  >Monto Total Excento de IVA:</span
                ><span class="text-h6 ms-2">{{
                  formatCurrency(invoice.exempt_amount)
                }}</span>
              </div>
              <div class="total-item-row">
                <span class="text-subtitle-2 text-disabled"
                  >Base Imponible segun Alicuota 16 %:</span
                ><span class="text-h6 ms-2">{{
                  formatCurrency(invoice.taxable_base)
                }}</span>
              </div>
              <div class="total-item-row">
                <span class="text-subtitle-2 text-disabled"
                  >Impuesto segun Alicuota 16 %:</span
                ><span class="text-h6 ms-2">{{
                  formatCurrency(invoice.tax_amount)
                }}</span>
              </div>
              <div class="total-item-row">
                <span class="text-subtitle-2 text-disabled">Total Factura:</span
                ><span class="text-h6 ms-2 font-weight-bold">{{
                  formatCurrency(invoice.total_amount)
                }}</span>
              </div>
              <div class="total-item-row">
                <span class="text-subtitle-2 text-disabled">Tasa BCV:</span
                ><span class="text-h6 ms-2">{{
                  formatNumber(invoice.exchange_rate)
                }}</span>
              </div>
              <div class="total-item-row">
                <span class="text-subtitle-2 text-disabled">Total USD:</span
                ><span class="text-h6 ms-2">{{
                  formatCurrency(invoice.total_usd, "USD")
                }}</span>
              </div>
            </div>
          </VCardText>
          <VDivider />

          <VCardActions class="pa-6">
            <div v-if="isLocationMode" class="d-flex w-100">
              <VBtn
                :loading="isSaving"
                @click="handleSaveLocations"
                size="large"
                color="primary"
                variant="flat"
                class="w-100"
              >
                <VIcon icon="tabler-device-floppy" class="me-2" />Guardar
                Ubicaciones
              </VBtn>
            </div>
            <div v-else-if="isEditableMode" class="d-flex ga-3 w-100">
              <VBtn
                v-if="isEditMode"
                color="error"
                variant="outlined"
                size="large"
                class="flex-1-1"
                @click="toggleEditMode(false)"
                >Cancelar</VBtn
              >
              <VBtn
                :loading="isSaving"
                @click="handleUpdate"
                size="large"
                color="primary"
                variant="flat"
                :class="isEditMode ? 'flex-1-1' : 'w-100'"
              >
                {{ isEditMode ? "Guardar Productos" : "Finalizar Factura" }}
              </VBtn>
            </div>
            <div v-else class="d-flex w-100">
              <VBtn
                @click="emit('back-to-list')"
                size="large"
                color="primary"
                variant="tonal"
                class="w-100"
                >Volver a la Lista</VBtn
              >
            </div>
          </VCardActions>
        </VForm>
      </VCard>

      <template v-if="isEditableMode">
        <div
          v-if="isEditMode && isProductSearchVisible"
          class="product-search-section mt-6"
        >
          <div class="d-flex align-center justify-space-between mb-4">
            <h4 class="text-h4">Buscar Productos en Catálogo</h4>
            <VBtn
              variant="text"
              color="error"
              @click="isProductSearchVisible = false"
              ><VIcon icon="tabler-x" class="me-2" />Cerrar Búsqueda</VBtn
            >
          </div>
          <ProductFilters
            v-model:searchQuery="productSearchQuery"
            :laboratories="laboratories"
            :origins="origins"
            :loading="isLoadingFilters"
            mode="minimal"
            @clear="productSearchQuery = ''"
            @add-product="handleAddNewProduct"
          />
          <ProductTable
            :products="products"
            :loading="loadingProducts"
            :total-product="totalProducts"
            :items-per-page="productItemsPerPage"
            :page="productPage"
            mode="add-to-invoice"
            @update:options="updateProductTableOptions"
            @add-product-to-invoice="addProductToInvoice"
          />
        </div>
        <BarcodeSearchModal
          ref="barcodeModalRef"
          v-model="isBarcodeModalVisible"
          :loading="searchingBarcode"
          @search-barcode="handleSearchBarcode"
          @show-product-search="handleShowProductSearch"
          @add-new-product="handleAddNewProduct"
          @add-product-to-invoice="addProductToInvoice"
        />
        <ProductEditDialog
          v-model="isEditDialogVisible"
          :product="currentProduct"
          :laboratories="laboratories"
          :origins="origins"
          :categories="categories"
          :errors="productFormErrors"
          @save="handleSaveProduct"
          @clear-errors="productFormErrors = {}"
        />
      </template>
    </div>
  </div>
</template>

<style lang="scss">
.invoice-detail-card {
  .header-section {
    background-color: rgba(var(--v-theme-on-surface), 0.02);
    border-bottom: 1px solid rgba(var(--v-theme-on-surface), 0.12);
  }
  .editable-field {
    max-width: 150px;
  }
  .editable-field-large {
    max-width: 200px;
  }
  .dates-section,
  .totals-section {
    padding-top: 24px;
    padding-bottom: 24px;
  }
  .invoice-products-table {
    border: 1px solid rgba(var(--v-theme-on-surface), 0.12);
    border-radius: 6px;
    .v-data-table__tr:not(:last-child) {
      border-bottom: 1px solid rgba(var(--v-theme-on-surface), 0.12);
    }
  }
  .total-item p {
    white-space: nowrap;
  }
}
.editable-cell {
  min-width: 120px;
}
.flex-1-1 {
  flex: 1 1 50%;
}
.w-100 {
  width: 100%;
}
.totals-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
  align-items: flex-end;
}
.total-item-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: 100%;
  max-width: 400px;
}
</style>
