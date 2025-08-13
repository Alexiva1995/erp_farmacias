<script setup>
import ProductFilters from "@/components/ProductFilters.vue";
import ProductTable from "@/components/ProductTable.vue";
import ProductEditDialog from "@/components/dialogs/ProductEditDialog.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { onMounted, ref, watch } from "vue";

const props = defineProps({
  invoiceId: { type: [Number, String], required: true },
});
const emit = defineEmits(["back-to-list"]);

const invoice = ref(null);
const invoiceDetails = ref([]);
const formData = ref({});
const loading = ref(true);
const loadingDetails = ref(true);
const isEditMode = ref(false);
const isSaving = ref(false);

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
const isLoadingFilters = ref(false);

const isEditDialogVisible = ref(false);
const currentProduct = ref({});
const productFormErrors = ref({});
const categories = ref([]);

onMounted(async () => {
  await fetchInvoiceData(props.invoiceId);
  if (invoice.value) {
    await fetchInvoiceDetails(props.invoiceId);
  }
});

watch(isEditMode, (newVal) => {
  if (newVal) {
    if (laboratories.value.length === 0) fetchProductSelectOptions();
    fetchProducts();
  } else {
    cancelEditingDetail();
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
  } else {
    invoiceDetails.value.push({
      id: -Math.floor(Math.random() * 1000),
      product: { id: product.id, name: product.name },
      quantity: 1,
      unit_cost: product.unit_cost,
    });
  }
};

const handleAddProduct = () => {
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
    await fetchProducts();
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
  const detailIndex = invoiceDetails.value.findIndex(
    (d) => d.id === editingDetailId.value
  );
  if (detailIndex !== -1) {
    invoiceDetails.value[detailIndex] = { ...editedDetailData.value };
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

const detailsHeaders = [
  { title: "Descripción", key: "product.name", sortable: false, width: "50%" },
  { title: "Unidades", key: "quantity", align: "end", sortable: false },
  {
    title: "Costo por Unidad",
    key: "unit_cost",
    align: "end",
    sortable: false,
  },
  { title: "Costo Total", key: "total_cost", align: "end", sortable: false },
  {
    title: "Acciones",
    key: "actions",
    sortable: false,
    align: "center",
    width: "120px",
  },
];
</script>

<template>
  <div>
    <!-- Pantalla de Carga -->
    <div v-if="loading" class="text-center pa-10">
      <VProgressCircular indeterminate color="primary" size="64" />
      <p class="mt-4 text-h6">Cargando datos de la factura...</p>
    </div>

    <div v-else-if="invoice">
      <!-- 1. VCard Principal de la Factura -->
      <VCard class="invoice-detail-card mb-6">
        <VForm @submit.prevent="handleUpdate">
          <!-- Cabecera -->
          <VCardText class="header-section">
            <VRow align="start" justify="space-between">
              <VCol cols="12" md="auto">
                <div class="d-flex align-center">
                  <VBtn
                    icon="tabler-arrow-left"
                    variant="text"
                    class="me-2"
                    @click="emit('back-to-list')"
                  />
                  <div>
                    <h1 class="font-weight-bold text-primary">
                      {{ invoice.supplier.name }}
                    </h1>
                    <div class="d-flex align-center mt-2">
                      <span class="text-subtitle-1 font-weight-medium me-2"
                        >N° DE CONTROL</span
                      >
                      <span
                        v-if="!isEditMode"
                        class="text-h6 font-weight-bold text-error"
                        >{{ invoice.control_number }}</span
                      >
                      <VTextField
                        v-else
                        v-model="formData.control_number"
                        density="compact"
                        hide-details
                        variant="outlined"
                        class="editable-field"
                      />
                    </div>
                  </div>
                </div>
              </VCol>
              <VCol cols="12" md="auto" class="text-md-end mt-4 mt-md-0">
                <div class="d-flex justify-end mb-4">
                  <VBtn
                    v-if="!isEditMode"
                    @click="toggleEditMode(true)"
                    color="primary"
                    variant="tonal"
                    >Editar</VBtn
                  >
                </div>
                <div class="d-flex align-center justify-md-end">
                  <span class="text-h6 font-weight-medium me-2"
                    >FACTURA N°</span
                  >
                  <span
                    v-if="!isEditMode"
                    class="text-h5 font-weight-bold text-error"
                    >{{ invoice.invoice_number }}</span
                  >
                  <VTextField
                    v-else
                    v-model="formData.invoice_number"
                    density="compact"
                    hide-details
                    variant="outlined"
                    class="editable-field"
                  />
                </div>
              </VCol>
            </VRow>
          </VCardText>
          <!-- Sección de Fechas -->
          <VCardText class="dates-section">
            <VRow>
              <VCol
                v-for="dateField in [
                  'exp_date',
                  'payment_date',
                  'received_date',
                ]"
                :key="dateField"
                cols="12"
                md="4"
                class="text-center"
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
                <p
                  v-if="!isEditMode"
                  class="text-body-1 font-weight-medium mt-1"
                >
                  {{ formData[dateField] }}
                </p>
                <AppDateTimePicker
                  v-else
                  v-model="formData[dateField]"
                  density="compact"
                  class="mt-1"
                />
              </VCol>
            </VRow>
          </VCardText>
          <!-- Tabla de Productos de la Factura -->
          <VCardText class="products-section">
            <div class="d-flex align-center mb-4">
              <span class="text-h6 font-weight-medium">Productos</span>
              <VIcon
                icon="tabler-info-circle"
                size="20"
                class="ms-2 text-disabled"
              />
              <VSpacer />
              <span class="text-body-1 me-2">Bs. Total Cargado</span>
              <VChip color="error" label>{{
                formatNumber(invoice.total_amount)
              }}</VChip>
            </div>
            <VDataTable
              :headers="detailsHeaders"
              :items="invoiceDetails"
              :loading="loadingDetails"
              :hide-default-footer="true"
              class="invoice-products-table"
            >
              <template #item.quantity="{ item }">
                <VTextField
                  v-if="item.id === editingDetailId"
                  v-model.number="editedDetailData.quantity"
                  type="number"
                  density="compact"
                  hide-details
                  variant="outlined"
                  class="editable-cell"
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
                />
                <span v-else>{{ formatNumber(item.unit_cost) }}</span>
              </template>
              <template #item.total_cost="{ item }">
                <span v-if="item.id === editingDetailId">{{
                  formatNumber(
                    editedDetailData.quantity * editedDetailData.unit_cost
                  )
                }}</span>
                <span v-else>{{
                  formatNumber(item.quantity * item.unit_cost)
                }}</span>
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
          <!-- Sección de Totales -->
          <VCardText class="totals-section">
            <VDivider v-if="!isEditMode" class="mb-6" />
            <VRow>
              <VCol
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
                ]"
                :key="field.model"
                cols="12"
                md="3"
                class="text-center total-item"
              >
                <p class="text-subtitle-2 text-disabled">{{ field.label }}</p>
                <p v-if="!isEditMode" :class="field.class" class="text-h6 mt-1">
                  {{ formatNumber(invoice[field.model]) }}
                </p>
                <VTextField
                  v-else
                  v-model.number="formData[field.model]"
                  type="number"
                  density="compact"
                  variant="outlined"
                  class="mt-1"
                />
              </VCol>
            </VRow>
            <VRow class="justify-center mt-4">
              <VCol
                v-for="field in [
                  { label: 'Tasa BCV', model: 'exchange_rate' },
                  { label: 'Total USD', model: 'total_usd' },
                ]"
                :key="field.model"
                cols="12"
                md="3"
                class="text-center total-item"
              >
                <p class="text-subtitle-2 text-disabled">{{ field.label }}</p>
                <p v-if="!isEditMode" :class="field.class" class="text-h6 mt-1">
                  {{ formatNumber(invoice[field.model]) }}
                </p>
                <VTextField
                  v-else
                  v-model.number="formData[field.model]"
                  type="number"
                  density="compact"
                  variant="outlined"
                  class="mt-1"
                />
              </VCol>
            </VRow>
          </VCardText>
          <!-- Botón de Acción Final -->
          <VCardActions class="pa-6">
            <VSpacer />
            <VBtn
              v-if="isEditMode"
              color="error"
              variant="text"
              @click="toggleEditMode(false)"
              >Cancelar</VBtn
            >
            <VBtn
              :loading="isSaving"
              @click="handleUpdate"
              size="large"
              color="primary"
            >
              {{ isEditMode ? "Guardar Cambios" : "Finalizar Factura" }}
            </VBtn>
          </VCardActions>
        </VForm>
      </VCard>

      <!-- 2. Sección de Búsqueda de Productos (condicional y externa) -->
      <div v-if="isEditMode" class="product-search-section">
        <h4 class="text-h4 mb-4">Añadir Productos a la Factura</h4>
        <ProductFilters
          v-model:searchQuery="productSearchQuery"
          :laboratories="laboratories"
          :origins="origins"
          :loading="isLoadingFilters"
          mode="minimal"
          @clear="productSearchQuery = ''"
          @add-product="handleAddProduct"
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
  min-width: 100px;
}
</style>
