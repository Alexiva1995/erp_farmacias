<script setup>
import { computed, watch, ref } from "vue";
import { useDisplay } from "vuetify";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { formatCurrency } from "@/utils/currencyFormatter";

const props = defineProps({
  modelValue: {
    type: Boolean,
    required: true,
  },
  product: {
    type: Object,
    default: null,
  },
  formData: {
    type: Object,
    default: () => ({}),
  },
  loading: { type: Boolean, default: false },
  productsData: {
    type: Array,
    default: () => [],
  },
  formErrors: {
    type: Object,
    default: () => ({}),
  },
  isEditing: { type: Boolean, default: false },
  productOfferToEdit: { type: Object, default: null },
});

const emit = defineEmits(["update:modelValue", "save", "modal-closed"]);

const defaultIndividualOffer = {
  product_id: null,
  discount_percent: null,
  start_date: "",
  end_date: "",
};

const localFormData = ref({ ...defaultIndividualOffer });
const availableProducts = ref([]);
const loadingProducts = ref(false);

const { mobile } = useDisplay();

const dialogTitle = computed(() => {
  return props.isEditing ? "Editar Oferta" : "Nueva Oferta Individual";
});

// Producto seleccionado
const selectedProduct = computed(() => {
  const pid = localFormData.value.product_id;
  if (!pid) return null;
  const fromList = availableProducts.value.find((p) => p.id === pid);
  return fromList || props.product || props.productOfferToEdit?.product || null;
});

// Producto seleccionado en modo edición
const selectedProductDisplay = computed(() => {
  if (!localFormData.value.product_id) return "";
  const p = selectedProduct.value || props.product || props.productOfferToEdit?.product;
  return p ? `${p.id} - ${p.name}` : `ID: ${localFormData.value.product_id}`;
});

// Precio con descuento
const priceInfo = computed(() => {
  const product = selectedProduct.value;
  if (!product || product.sale_price == null) return null;
  const salePrice = parseFloat(product.sale_price) || 0;
  if (salePrice <= 0) return null;
  const discount = parseFloat(localFormData.value.discount_percent) || 0;
  const finalPrice = salePrice * (1 - discount / 100);
  const savings = salePrice - finalPrice;
  return { salePrice, finalPrice, savings };
});

// Cargar productos con búsqueda
const loadAvailableProducts = async (search = "") => {
  const trimmedSearch = String(search ?? "").trim();
  if (trimmedSearch.length > 0 && trimmedSearch.length < 2) return;

  loadingProducts.value = true;
  try {
    const params = {
      q: trimmedSearch || undefined,
      itemsPerPage: 20,
      isStrictSearch: false,
      sortBy: "name",
      orderBy: "asc",
    };
    if (/^\d+$/.test(trimmedSearch)) {
      params.product_id = parseInt(trimmedSearch, 10);
    }
    Object.keys(params).forEach((k) => params[k] === undefined && delete params[k]);

    const response = await axios.get("/products", { params });
    const items = Array.isArray(response.data?.data) ? response.data.data : [];
    if (items.length > 0) {
      const seenIds = new Set();
      availableProducts.value = items
        .filter((product) => {
          if (seenIds.has(product.id)) return false;
          seenIds.add(product.id);
          return true;
        })
        .map((product) => ({
          id: product.id,
          name: product.name,
          active_ingredient: product.active_ingredient,
          stock: product.stock_calculado || product.stock || 0,
          sale_price: product.sale_price,
          barcode: product.barcode,
          laboratory: product.laboratory,
        }));
    } else {
      availableProducts.value = [];
    }
  } catch (error) {
    console.error("Error al cargar productos:", error);
    toast.error("Error al cargar productos");
  } finally {
    loadingProducts.value = false;
  }
};

let searchTimeout;
const handleProductSearch = (search) => {
  const searchStr = String(search ?? "");
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => loadAvailableProducts(searchStr), 300);
};

const formatDateForInput = (dateString) => {
  if (!dateString) return "";
  const date = new Date(dateString);
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, "0");
  const day = String(date.getDate()).padStart(2, "0");
  return `${year}-${month}-${day}`;
};

function onSave() {
  const dataToSend = {
    ...localFormData.value,
    discount_percent: parseFloat(localFormData.value.discount_percent) || 0,
  };

  emit("save", dataToSend);
}

const onCancel = () => {
  emit("update:modelValue", false);
  emit("modal-closed");
};

// Resetear formulario al abrir el modal
watch(
  () => props.modelValue,
  (isVisible) => {
    if (isVisible) {
      if (props.isEditing && props.productOfferToEdit) {
        localFormData.value = {
          id: props.productOfferToEdit.id,
          product_id: props.productOfferToEdit.product_id,
          discount_percent: props.productOfferToEdit.discount_percent,
          start_date: formatDateForInput(props.productOfferToEdit.start_date),
          end_date: formatDateForInput(props.productOfferToEdit.end_date),
        };
      } else {
        localFormData.value = {
          ...defaultIndividualOffer,
          ...(props.formData || {}),
        };
      }

      const targetProduct = props.product || props.productOfferToEdit?.product;
      if (targetProduct) {
        const prodObj = {
          id: targetProduct.id,
          name: targetProduct.name || targetProduct.product_name,
          active_ingredient: targetProduct.active_ingredient,
          stock: targetProduct.current_stock ?? targetProduct.stock ?? 0,
          sale_price: targetProduct.sale_price,
          barcode: targetProduct.barcode,
          laboratory: targetProduct.laboratory || { name: targetProduct.laboratory_name },
        };
        availableProducts.value = [prodObj];
        localFormData.value.product_id = prodObj.id;
      } else {
        const preselectedId = props.formData?.product_id || localFormData.value?.product_id;
        if (preselectedId) {
          loadAvailableProducts(String(preselectedId));
        } else {
          loadAvailableProducts("");
        }
      }
    }
  },
  { immediate: true }
);

// Sincronizar con formData del padre
watch(
  () => props.formData,
  (newFormData) => {
    if (newFormData && Object.keys(newFormData).length > 0) {
      Object.assign(localFormData.value, newFormData);
    }
  },
  { deep: true, immediate: true }
);

const endDateConfig = computed(() => ({
  altFormat: "Y-m-d",
  dateFormat: "Y-m-d",
  minDate: localFormData.value.start_date || undefined,
}));
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    max-width="680px"
    width="680px"
    persistent
    scrollable
    transition="dialog-bottom-transition"
    class="premium-dialog"
    :fullscreen="mobile"
    @click:outside.prevent
    @keydown.esc.prevent="onCancel"
  >
    <VCard :class="mobile ? 'rounded-0' : 'detail-dialog-card rounded-xl border-0 shadow-xl overflow-hidden bg-surface'">
      <!-- Header Premium -->
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-4 d-flex align-center shadow-sm">
          <VAvatar
            color="white"
            variant="flat"
            size="38"
            class="me-3 elevation-1"
          >
            <VIcon
              icon="tabler-tags"
              size="22"
              color="primary"
            />
          </VAvatar>
          <div class="d-flex flex-column leading-none">
            <h2 class="text-h6 font-weight-black text-white leading-tight mb-0">
              {{ dialogTitle }}
            </h2>
            <div class="d-flex align-center gap-2 mt-1">
              <span
                class="text-white opacity-75 uppercase font-weight-bold"
                style="font-size: 0.65rem; letter-spacing: 0.05em;"
              >
                Promoción Individual de Producto
              </span>
            </div>
          </div>
          <VSpacer />
          <VBtn
            icon="tabler-x"
            variant="tonal"
            color="white"
            size="small"
            class="rounded-lg"
            @click="onCancel"
            :disabled="props.loading"
          />
        </div>
      </VCardTitle>

      <VCardText class="pa-4 pa-sm-5 bg-surface">
        <!-- Sección Producto en Oferta -->
        <div class="mb-5">
          <div class="d-flex align-center gap-1-5 mb-2">
            <div class="header-indicator primary" />
            <span class="text-xs font-weight-black text-high-emphasis uppercase letter-spacing-1">Producto en Oferta</span>
          </div>

          <!-- Producto Seleccionado (Edición o con Selección) -->
          <div v-if="props.isEditing || selectedProduct" class="selected-product-box pa-3 rounded-lg border bg-surface">
            <div class="d-flex align-center gap-2 mb-1-5 flex-wrap">
              <span class="text-xs font-weight-black text-primary bg-primary-lighten-5 px-2 py-0-5 rounded">
                ID: #{{ selectedProduct?.id || localFormData.product_id }}
              </span>
              <span v-if="selectedProduct?.laboratory?.name" class="text-xs font-weight-black text-primary text-uppercase">
                LAB: {{ selectedProduct.laboratory.name }}
              </span>
              <VSpacer />
              <VChip
                v-if="selectedProduct?.sale_price"
                size="x-small"
                color="primary"
                variant="tonal"
                class="font-weight-black"
              >
                Precio Actual: {{ formatCurrency(selectedProduct.sale_price, 'USD') }}
              </VChip>
            </div>

            <div class="text-sm font-weight-black text-high-emphasis text-uppercase leading-tight mb-1">
              {{ selectedProduct?.name || selectedProductDisplay }}
            </div>

            <div v-if="selectedProduct?.active_ingredient" class="text-super-xs text-medium-emphasis">
              Principio Activo: <strong class="text-high-emphasis">{{ selectedProduct.active_ingredient }}</strong>
            </div>
          </div>

          <!-- Buscador de Producto -->
          <VAutocomplete
            v-if="!props.isEditing"
            v-model="localFormData.product_id"
            placeholder="BUSCAR POR NOMBRE, ID O CÓDIGO..."
            variant="outlined"
            :items="availableProducts"
            item-title="name"
            item-value="id"
            clearable
            no-data-text="No se encontraron productos"
            :loading="loadingProducts"
            :disabled="props.loading"
            :custom-filter="() => true"
            density="compact"
            hide-details
            class="rounded-lg font-weight-medium mt-2"
            :error="!!props.formErrors.product_id"
            :error-messages="props.formErrors.product_id"
            @update:search="handleProductSearch"
          >
            <template #item="{ props: itemProps, item: productItem }">
              <VListItem v-bind="{ ...itemProps, title: '' }">
                <VListItemTitle class="font-weight-black text-sm uppercase">
                  {{ productItem.raw.name }}
                </VListItemTitle>
                <VListItemSubtitle class="text-super-xs font-weight-bold uppercase">
                  ID: {{ productItem.raw.id }} | {{ productItem.raw.laboratory?.name || 'S/L' }} | <span class="text-success font-weight-black">${{ productItem.raw.sale_price }}</span>
                </VListItemSubtitle>
              </VListItem>
            </template>
          </VAutocomplete>
        </div>

        <!-- Sección Parámetros de la Oferta -->
        <div class="mb-5">
          <div class="d-flex align-center gap-1-5 mb-2">
            <div class="header-indicator primary" />
            <span class="text-xs font-weight-black text-high-emphasis uppercase letter-spacing-1">Parámetros de la Oferta</span>
          </div>

          <VRow dense>
            <!-- Descuento -->
            <VCol cols="12" sm="4">
              <div class="mb-2 mb-sm-0">
                <span class="text-super-xs font-weight-bold text-disabled uppercase mb-1 d-block">% Descuento</span>
                <VTextField
                  v-model="localFormData.discount_percent"
                  type="number"
                  min="0"
                  max="100"
                  step="0.01"
                  placeholder="0.00"
                  variant="outlined"
                  density="compact"
                  hide-details
                  prepend-inner-icon="tabler-percentage"
                  class="rounded-lg font-weight-black"
                  :error="!!props.formErrors.discount_percent"
                  :error-messages="props.formErrors.discount_percent"
                  :disabled="props.loading"
                />
              </div>
            </VCol>

            <!-- Fecha Inicio -->
            <VCol cols="12" sm="4">
              <div class="mb-2 mb-sm-0">
                <span class="text-super-xs font-weight-bold text-disabled uppercase mb-1 d-block">Fecha Inicio</span>
                <AppDateTimePicker
                  v-model="localFormData.start_date"
                  placeholder="SELECCIONAR FECHA"
                  prepend-inner-icon="tabler-calendar-event"
                  density="compact"
                  hide-details
                  class="rounded-lg"
                  :error="!!props.formErrors.start_date"
                  :error-messages="props.formErrors.start_date"
                  :disabled="props.loading"
                  :config="{ altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
                />
              </div>
            </VCol>

            <!-- Fecha Final -->
            <VCol cols="12" sm="4">
              <div>
                <span class="text-super-xs font-weight-bold text-disabled uppercase mb-1 d-block">Fecha Final</span>
                <AppDateTimePicker
                  v-model="localFormData.end_date"
                  placeholder="SELECCIONAR FECHA"
                  prepend-inner-icon="tabler-calendar-off"
                  density="compact"
                  hide-details
                  class="rounded-lg"
                  :error="!!props.formErrors.end_date"
                  :error-messages="props.formErrors.end_date"
                  :disabled="props.loading"
                  :config="endDateConfig"
                />
              </div>
            </VCol>
          </VRow>
        </div>

        <!-- Simulación de Oferta -->
        <VExpandTransition>
          <div
            v-if="priceInfo"
            class="pa-4 rounded-lg border bg-surface elevation-1 simulation-box"
          >
            <div class="d-flex align-center justify-space-between mb-3">
              <div class="d-flex align-center gap-1-5">
                <VIcon
                  icon="tabler-calculator"
                  size="18"
                  color="success"
                />
                <span class="text-xs font-weight-black text-high-emphasis uppercase letter-spacing-1">Simulación de la Oferta</span>
              </div>
              <VChip
                v-if="priceInfo.savings > 0"
                color="success"
                variant="flat"
                size="x-small"
                class="font-weight-black px-2 rounded"
              >
                AHORRO: {{ formatCurrency(priceInfo.savings, 'USD') }}
              </VChip>
            </div>

            <div class="d-flex align-center justify-space-between flex-wrap gap-2">
              <!-- Comparativa de Precios Integrada -->
              <div class="d-flex flex-column">
                <span class="text-super-xs font-weight-bold text-disabled uppercase">Precio Normal</span>
                <span class="text-sm font-weight-bold text-medium-emphasis text-decoration-line-through">
                  {{ formatCurrency(priceInfo.salePrice, 'USD') }}
                </span>
              </div>

              <div class="d-flex flex-column align-end">
                <span class="text-super-xs font-weight-black text-success uppercase">Precio Final de Oferta</span>
                <span class="text-h4 font-weight-950 text-success leading-none">
                  {{ formatCurrency(priceInfo.finalPrice, 'USD') }}
                </span>
              </div>
            </div>
          </div>
        </VExpandTransition>
      </VCardText>

      <VDivider />

      <!-- Acciones del Modal -->
      <VCardActions class="pa-3 pa-sm-4 bg-surface border-t">
        <VRow dense class="w-100 ma-0">
          <VCol cols="6" class="pa-1">
            <VBtn
              color="secondary"
              variant="outlined"
              height="44"
              block
              class="font-weight-bold rounded-lg text-button uppercase"
              @click="onCancel"
              :disabled="props.loading"
            >
              Cancelar
            </VBtn>
          </VCol>
          <VCol cols="6" class="pa-1">
            <VBtn
              color="primary"
              variant="flat"
              height="44"
              block
              class="font-weight-black rounded-lg shadow-primary text-button uppercase"
              :loading="props.loading"
              @click="onSave"
            >
              <VIcon
                start
                icon="tabler-device-floppy"
                size="18"
              />
              {{ props.isEditing ? "Guardar Cambios" : "Crear Oferta" }}
            </VBtn>
          </VCol>
        </VRow>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.header-gradient {
  background: linear-gradient(
    135deg,
    rgb(var(--v-theme-primary)) 0%,
    rgb(var(--v-theme-gradient-end, var(--v-theme-primary))) 100%
  );
}

.detail-dialog-card {
  border-radius: 12px !important;
}

.header-indicator {
  inline-size: 3px;
  block-size: 14px;
  border-radius: 4px;
}

.header-indicator.primary {
  background-color: rgb(var(--v-theme-primary));
}

.selected-product-box {
  background-color: rgba(var(--v-theme-primary), 0.02) !important;
  border-color: rgba(var(--v-theme-primary), 0.2) !important;
}

.simulation-box {
  border-color: rgba(var(--v-theme-success), 0.3) !important;
  background-color: rgba(var(--v-theme-success), 0.02) !important;
}

.shadow-primary {
  box-shadow: 0 4px 14px 0 rgba(var(--v-theme-primary), 0.39) !important;
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: normal;
}

.letter-spacing-1 {
  letter-spacing: 0.5px !important;
}

.leading-none {
  line-height: 1 !important;
}

.mt-0-5 {
  margin-top: 2px !important;
}

.gap-1-5 {
  gap: 6px !important;
}

.bg-primary-lighten-5 {
  background-color: rgba(var(--v-theme-primary), 0.08) !important;
}

.border-t {
  border-block-start: 1px solid rgba(var(--v-border-color), 0.08) !important;
}

.font-weight-950 {
  font-weight: 950 !important;
}
</style>
