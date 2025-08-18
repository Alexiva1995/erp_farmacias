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
  exchangeRates: { type: Array, default: () => [] },
  mode: { type: String, default: "editable" },
});
const emit = defineEmits(["back-to-list"]);

const invoice = ref(null);
const invoiceDetails = ref([]);
const formData = ref({});
const loading = ref(true);
const loadingDetails = ref(true);
const isEditMode = ref(false);
const isSaving = ref(false);

const isReadOnly = computed(() => props.mode === "read-only");

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

const getExchangeRate = (currency) => {
  if (!currency || currency === "USD") return 1;

  const currencyMapping = {
    Bs: "BS",
    BS: "BS",
    COP: "COP",
    USD: "USD",
  };

  const mappedCurrency = currencyMapping[currency] || currency;
  const rate = props.exchangeRates.find(
    (rate) => rate.currency_code === mappedCurrency
  );

  return rate ? parseFloat(rate.rate) : 1;
};

const convertAmount = (usdAmount, targetCurrency) => {
  if (!targetCurrency || targetCurrency === "USD") {
    return usdAmount;
  }

  const rate = getExchangeRate(targetCurrency);
  return usdAmount * rate;
};

const processedInvoiceDetails = computed(() => {
  if (!invoice.value) return invoiceDetails.value;

  return invoiceDetails.value.map((detail) => {
    const convertedUnitCost = convertAmount(
      detail.unit_cost,
      invoice.value.currency
    );
    const convertedTotalCost = convertAmount(
      detail.total_cost || detail.quantity * detail.unit_cost,
      invoice.value.currency
    );

    return {
      ...detail,
      converted_unit_cost: convertedUnitCost,
      converted_total_cost: convertedTotalCost,
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
  if (!newVal) {
    cancelEditingDetail();
    isProductSearchVisible.value = false;
  }
});

const fetchInvoiceData = async (id) => {
  loading.value = true;
  try {
    const response = await axios.get(`/invoices/${id}`);
    invoice.value = response.data.data;
    formData.value = JSON.parse(JSON.stringify(invoice.value));
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
    const response = await axios.get(`/invoices/${id}/suggested-details`);
    invoiceDetails.value = response.data.data ?? [];
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
  if (isReadOnly.value) return;

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
      quantity: 0,
      unit_cost: 0,
      lot_number: "",
      expiration_date: null,
      location: "",
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
      headers: {
        "Content-Type": "multipart/form-data",
      },
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
    editedDetailData.value.unit_cost <= 0
  ) {
    toast.error("El costo por unidad debe ser mayor a 0");
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

  if (!editedDetailData.value.location?.trim()) {
    toast.error("La localización es obligatoria");
    return;
  }

  const detailIndex = invoiceDetails.value.findIndex(
    (d) => d.id === editingDetailId.value
  );
  if (detailIndex !== -1) {
    editedDetailData.value.total_cost =
      editedDetailData.value.quantity * editedDetailData.value.unit_cost;
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

    invoice.value = response.data.invoice;
    formData.value = JSON.parse(JSON.stringify(response.data.invoice));
    invoiceDetails.value = response.data.invoice.details;

    isEditMode.value = false;
    emit("back-to-list");
  } catch (error) {
    if (error.response && error.response.status === 422) {
      toast.error("Datos inválidos. Por favor, revisa el formulario.");
    } else {
      toast.error(
        error.response?.data?.message || "No se pudo actualizar la factura."
      );
    }
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

  const currencyMap = {
    BS: "VES",
    Bs: "VES",
    COP: "COP",
    USD: "USD",
  };

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

  const symbolMap = {
    BS: "Bs.",
    Bs: "Bs.",
    USD: "$",
    COP: "COP$",
  };

  return symbolMap[invoice.value.currency] || "Bs.";
};

const getFormattedAmount = (fieldModel, value) => {
  if (!invoice.value) return value;

  if (fieldModel === "exchange_rate") {
    return formatNumber(value);
  } else if (fieldModel === "total_usd") {
    return new Intl.NumberFormat("es-VE", {
      style: "currency",
      currency: "USD",
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    }).format(value);
  } else {
    const convertedValue = convertAmount(value, invoice.value.currency);
    return formatCurrency(convertedValue);
  }
};

const totalLoadedConverted = computed(() => {
  if (!invoice.value) return 0;

  const totalUSD = processedInvoiceDetails.value.reduce(
    (total, item) => total + (item.quantity || 0) * (item.unit_cost || 0),
    0
  );

  return convertAmount(totalUSD, invoice.value.currency);
});

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
    title: "Costo por Unidad",
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
        <template #prepend>
          <VIcon icon="tabler-info-circle" />
        </template>
        <div>
          <strong>Factura en {{ invoice.currency }}</strong>
          <div class="text-caption mt-1">
            Los montos se muestran convertidos desde USD usando el tipo de
            cambio actual
          </div>
        </div>
      </VAlert>

      <VCard class="invoice-detail-card mb-6">
        <VForm @submit.prevent="handleUpdate">
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
                  v-if="!isEditMode && !isReadOnly"
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
                    <span class="text-subtitle-1 font-weight-medium me-2">
                      N° DE CONTROL
                    </span>
                    <span class="text-h6 font-weight-bold text-error">
                      {{ invoice.control_number }}
                    </span>
                  </div>
                </div>
              </VCol>
              <VCol cols="12" md="auto" class="text-md-end">
                <div class="d-flex align-center justify-md-end">
                  <span class="text-subtitle-1 font-weight-medium me-2">
                    FACTURA N°
                  </span>
                  <span
                    class="text-h4 font-weight-bold text-error"
                    style="font-size: 2rem !important"
                  >
                    {{ invoice.invoice_number }}
                  </span>
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
                  {{ formData[dateField] }}
                </p>
              </VCol>
            </VRow>
          </VCardText>

          <VDivider />

          <VCardText class="products-section">
            <div class="d-flex align-center mb-4">
              <span class="text-h6 font-weight-medium">Productos</span>
              <VChip color="primary" variant="outlined" class="ms-2">
                {{ processedInvoiceDetails.length }} productos
              </VChip>
              <VIcon
                icon="tabler-info-circle"
                size="20"
                class="ms-2 text-disabled"
              />
              <VSpacer />

              <VBtn
                v-if="isEditMode"
                color="primary"
                variant="flat"
                size="small"
                class="me-3"
                @click="handleAddProduct"
              >
                <VIcon icon="tabler-plus" class="me-2" />
                Agregar Producto
              </VBtn>

              <span class="text-body-1 me-2 text-error font-weight-medium">
                {{ invoice.currency }}. Total Cargado
              </span>
              <VChip color="error" label>
                {{ formatCurrency(totalLoadedConverted) }}
              </VChip>
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
                  v-if="item.id === editingDetailId"
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
                  v-if="item.id === editingDetailId"
                  v-model="editedDetailData.expiration_date"
                  density="compact"
                  class="editable-cell"
                  placeholder="F. Vencimiento"
                />
                <span v-else>{{ item.expiration_date || "-" }}</span>
              </template>

              <template #item.location="{ item }">
                <VTextField
                  v-if="item.id === editingDetailId"
                  v-model="editedDetailData.location"
                  density="compact"
                  hide-details
                  variant="outlined"
                  class="editable-cell"
                  placeholder="Localización"
                />
                <span v-else>{{ item.location || "-" }}</span>
              </template>

              <template #item.quantity="{ item }">
                <VTextField
                  v-if="item.id === editingDetailId"
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
                  v-if="item.id === editingDetailId"
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
                  <span class="font-weight-medium">
                    {{ formatCurrency(item.converted_unit_cost) }}
                  </span>
                  <span
                    v-if="invoice.currency !== 'USD'"
                    class="text-caption text-medium-emphasis"
                  >
                    {{ formatCurrency(item.unit_cost, "USD") }}
                  </span>
                </div>
              </template>

              <template #item.total_cost="{ item }">
                <div
                  v-if="item.id === editingDetailId"
                  class="d-flex flex-column align-end"
                >
                  <span>{{
                    formatCurrency(
                      convertAmount(
                        (editedDetailData.quantity || 0) *
                          (editedDetailData.unit_cost || 0),
                        invoice.currency
                      )
                    )
                  }}</span>
                </div>
                <div v-else class="d-flex flex-column align-end">
                  <span class="font-weight-medium">
                    {{ formatCurrency(item.converted_total_cost) }}
                  </span>
                  <span
                    v-if="invoice.currency !== 'USD'"
                    class="text-caption text-medium-emphasis"
                  >
                    {{
                      formatCurrency(
                        (item.quantity || 0) * (item.unit_cost || 0),
                        "USD"
                      )
                    }}
                  </span>
                </div>
              </template>

              <template #item.actions="{ item }">
                <div v-if="isEditMode">
                  <div v-if="item.id === editingDetailId" class="d-flex">
                    <IconBtn @click="saveEditingDetail">
                      <VIcon icon="tabler-check" color="success" size="22" />
                    </IconBtn>
                    <IconBtn @click="cancelEditingDetail">
                      <VIcon icon="tabler-x" color="error" size="22" />
                    </IconBtn>
                  </div>
                  <div v-else class="d-flex">
                    <IconBtn @click="removeProductFromInvoice(item.id)">
                      <VIcon icon="tabler-trash" size="20" />
                    </IconBtn>
                    <IconBtn @click="startEditingDetail(item)">
                      <VIcon icon="tabler-edit" size="20" />
                    </IconBtn>
                  </div>
                </div>
              </template>
              <template #bottom></template>
            </VDataTable>
          </VCardText>

          <VDivider />

          <VCardText class="totals-section">
            <div class="totals-list">
              <div
                v-for="field in [
                  {
                    label: 'Monto Total Excento de IVA',
                    model: 'exempt_amount',
                  },
                  {
                    label: 'Base Imponible segun Alicuota 16 %',
                    model: 'taxable_base',
                  },
                  {
                    label: 'Impuesto segun Alicuota 16 %',
                    model: 'tax_amount',
                  },
                  {
                    label: 'Total Factura',
                    model: 'total_amount',
                    class: 'font-weight-bold',
                  },
                  { label: 'Tasa BCV', model: 'exchange_rate' },
                  { label: 'Total USD', model: 'total_usd' },
                ]"
                :key="field.model"
                class="total-item-row"
              >
                <span class="text-subtitle-2 text-disabled"
                  >{{ field.label }}:</span
                >
                <span :class="['text-h6 ms-2', field.class]">
                  {{ getFormattedAmount(field.model, invoice[field.model]) }}
                </span>
              </div>
            </div>
          </VCardText>

          <VDivider />

          <VCardActions class="pa-6">
            <div v-if="!isReadOnly" class="d-flex ga-3 w-100">
              <VBtn
                v-if="isEditMode"
                color="error"
                variant="outlined"
                size="large"
                class="flex-1-1"
                @click="toggleEditMode(false)"
              >
                Cancelar
              </VBtn>
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
              >
                Volver a la Lista
              </VBtn>
            </div>
          </VCardActions>
        </VForm>
      </VCard>

      <div
        v-if="isEditMode && isProductSearchVisible"
        class="product-search-section"
      >
        <div class="d-flex align-center justify-space-between mb-4">
          <h4 class="text-h4">Buscar Productos en Catálogo</h4>
          <VBtn
            variant="text"
            color="error"
            @click="isProductSearchVisible = false"
          >
            <VIcon icon="tabler-x" class="me-2" />
            Cerrar Búsqueda
          </VBtn>
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
    .v-data-table__tr {
      &:not(:last-child) {
        border-bottom: 1px solid rgba(var(--v-theme-on-surface), 0.12);
      }
    }
  }
  .total-item {
    p {
      white-space: nowrap;
    }
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
  align-items: flex-start;
}

.total-item-row {
  display: flex;
  align-items: center;
}
</style>
