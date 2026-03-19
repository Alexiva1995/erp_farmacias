<script setup>
import { computed, watch, ref } from "vue";
import { useDisplay } from "vuetify";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";

const props = defineProps({
  modelValue: {
    type: Boolean,
    required: true,
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

// Producto seleccionado (para mostrar info de precios)
const selectedProduct = computed(() => {
  const pid = localFormData.value.product_id;
  if (!pid) return null;
  const fromList = availableProducts.value.find((p) => p.id === pid);
  return fromList || props.productOfferToEdit?.product || null;
});

// Producto seleccionado en modo edición (usa productOfferToEdit.product)
const selectedProductDisplay = computed(() => {
  if (!localFormData.value.product_id) return "";
  const p = selectedProduct.value || props.productOfferToEdit?.product;
  return p ? `${p.id} - ${p.name}` : `ID: ${localFormData.value.product_id}`;
});

// Precio con descuento (para info debajo)
const priceInfo = computed(() => {
  const product = selectedProduct.value;
  if (!product || product.sale_price == null) return null;
  const salePrice = parseFloat(product.sale_price) || 0;
  const discount = parseFloat(localFormData.value.discount_percent) || 0;
  const finalPrice = salePrice * (1 - discount / 100);
  return { salePrice, finalPrice };
});

// Cargar productos con búsqueda (igual que Pack)
const loadAvailableProducts = async (search = "") => {
  loadingProducts.value = true;
  try {
    const trimmedSearch = String(search ?? "").trim();
    const params = {
      q: trimmedSearch || undefined,
      itemsPerPage: 100,
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
          laboratory: product.laboratory
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
    discount_percent: parseFloat(localFormData.value.discount_percent) || 0
  };
  
  emit("save", dataToSend);
}

const onCancel = () => {
  emit("update:modelValue", false);
  emit("modal-closed");
};

// Resetear formulario cuando se abre el modal
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
        localFormData.value = { ...defaultIndividualOffer };
      }
    }
  },
  { immediate: true }
);

// Sincronizar con formData del padre
watch(
  () => props.formData,
  (newFormData) => {
    if (newFormData) {
      Object.assign(localFormData.value, newFormData);
    }
  },
  { deep: true, immediate: true }
);

// Al abrir, cargar productos
watch(
  () => props.modelValue,
  (isVisible) => {
    if (isVisible) {
      if (props.isEditing && props.productOfferToEdit?.product_id) {
        loadAvailableProducts(String(props.productOfferToEdit.product_id));
      } else if (!props.isEditing) {
        loadAvailableProducts("");
      }
    }
  }
);
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    max-width="700px"
    persistent
    scrollable
    transition="dialog-bottom-transition"
    class="premium-dialog"
    :fullscreen="mobile"
    @click:outside.prevent
    @keydown.esc.prevent="onCancel"
  >
    <VCard v-if="props.modelValue" :class="mobile ? 'rounded-0' : 'rounded-xl overflow-hidden border-0 elevation-24'">
      <!-- Header Premium con Gradiente -->
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-5 d-flex align-center shadow-sm">
          <VAvatar color="white" variant="flat" size="44" class="me-4 elevation-2">
            <VIcon icon="tabler-tag" color="primary" size="26" />
          </VAvatar>
          <div>
            <h2 class="text-h6 font-weight-black text-white leading-tight mb-0">{{ dialogTitle }}</h2>
            <span class="text-super-xs text-white opacity-75 uppercase font-weight-bold">
              Configuración de promociones individuales
            </span>
          </div>
          <VSpacer />
          <VBtn
            icon
            variant="tonal"
            color="white"
            size="small"
            class="rounded-lg"
            @click="onCancel"
            :disabled="props.loading"
          >
            <VIcon>tabler-x</VIcon>
          </VBtn>
        </div>
      </VCardTitle>

      <VCardText class="pa-6 bg-light">
        <VRow dense>
          <!-- Selector de Producto -->
          <VCol cols="12">
            <span class="text-xs font-weight-black text-primary uppercase letter-spacing-1 mb-2 d-block">Producto en Oferta</span>
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
              class="premium-input-compact mb-4"
              @update:search="handleProductSearch"
            >
              <template #item="{ props: itemProps, item: productItem }">
                <VListItem v-bind="{ ...itemProps, title: '' }">
                  <VListItemTitle class="font-weight-black text-sm uppercase">
                    {{ productItem.raw.name }}
                  </VListItemTitle>
                  <VListItemSubtitle class="text-super-xs font-weight-bold uppercase">
                    ID: {{ productItem.raw.id }} | {{ productItem.raw.laboratory?.name || 'S/L' }} | <span class="text-success">${{ productItem.raw.sale_price }}</span>
                  </VListItemSubtitle>
                </VListItem>
              </template>
            </VAutocomplete>
            <VTextField
              v-else
              :model-value="selectedProductDisplay"
              readonly
              variant="outlined"
              density="compact"
              class="premium-input-compact mb-4"
              bg-color="white"
            />
          </VCol>

          <!-- Descuento y Vigencia -->
          <VCol cols="12" md="4">
            <span class="text-xs font-weight-black text-primary uppercase letter-spacing-1 mb-2 d-block">% Descuento</span>
            <AppTextField
              v-model="localFormData.discount_percent"
              type="number"
              min="0"
              max="100"
              step="0.01"
              variant="outlined"
              density="compact"
              hide-details
              prepend-inner-icon="tabler-percentage"
              class="premium-input-compact"
              :error="!!props.formErrors.discount_percent"
              :disabled="props.loading"
            />
          </VCol>

          <VCol cols="12" sm="6" md="4">
            <span class="text-xs font-weight-black text-primary uppercase letter-spacing-1 mb-2 d-block">Fecha Inicio</span>
            <AppDateTimePicker
              v-model="localFormData.start_date"
              placeholder="SELECCIONAR FECHA"
              prepend-inner-icon="tabler-calendar-event"
              density="compact"
              hide-details
              class="premium-input-compact"
              :error="!!props.formErrors.start_date"
              :disabled="props.loading"
              :config="{ altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
            />
          </VCol>

          <VCol cols="12" sm="6" md="4">
            <span class="text-xs font-weight-black text-primary uppercase letter-spacing-1 mb-2 d-block">Fecha Final</span>
            <AppDateTimePicker
              v-model="localFormData.end_date"
              placeholder="SELECCIONAR FECHA"
              prepend-inner-icon="tabler-calendar-off"
              density="compact"
              hide-details
              class="premium-input-compact"
              :error="!!props.formErrors.end_date"
              :disabled="props.loading"
              :config="{ altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
            />
          </VCol>
        </VRow>

        <!-- Resumen de precios Premium -->
        <VExpandTransition>
          <div v-if="priceInfo" class="mt-8 pa-4 rounded-xl bg-primary-lighten-5 border-dashed-2">
            <div class="d-flex align-center justify-space-between mb-4">
              <div class="d-flex align-center gap-3">
                <VAvatar color="primary" size="32" variant="tonal">
                  <VIcon icon="tabler-calculator" size="18" />
                </VAvatar>
                <span class="text-xs font-weight-black text-primary uppercase letter-spacing-1">Simulación de Oferta</span>
              </div>
              <VChip v-if="priceInfo.salePrice > priceInfo.finalPrice" color="success" size="small" class="font-weight-black rounded">
                AHORRO: ${{ (priceInfo.salePrice - priceInfo.finalPrice).toFixed(2) }}
              </VChip>
            </div>

            <VRow no-gutters>
              <VCol cols="6">
                <div class="d-flex flex-column">
                  <span class="text-super-xs font-weight-black text-disabled uppercase mb-1">Precio Actual</span>
                  <span class="text-h6 font-weight-bold text-medium-emphasis text-decoration-line-through">
                    ${{ priceInfo.salePrice.toFixed(2) }}
                  </span>
                </div>
              </VCol>
              <VCol cols="6" class="text-end">
                <div class="d-flex flex-column">
                  <span class="text-super-xs font-weight-black text-success uppercase mb-1">Precio con Oferta</span>
                  <span class="text-h4 font-weight-black text-success leading-none">
                    ${{ priceInfo.finalPrice.toFixed(2) }}
                  </span>
                </div>
              </VCol>
            </VRow>
          </div>
        </VExpandTransition>
      </VCardText>

      <VDivider />

      <!-- Acciones de Modal -->
      <VCardActions class="pa-6 bg-light border-t">
        <VRow dense class="w-100 ma-0">
          <VCol cols="12" sm="6" class="pa-1">
            <VBtn
              color="secondary"
              variant="tonal"
              size="large"
              block
              height="48"
              class="font-weight-black rounded-lg text-button uppercase"
              @click="onCancel"
              :disabled="props.loading"
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
              height="48"
              class="font-weight-black rounded-lg shadow-primary-lg text-button uppercase"
              :loading="props.loading"
              @click="onSave"
            >
              <VIcon icon="tabler-device-floppy" class="me-2" />
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
  background: linear-gradient(135deg, rgb(var(--v-theme-primary)) 0%, #1e5128 100%);
}

.bg-light {
  background-color: #f8fafc !important;
}

.premium-input-compact :deep(.v-field__outline) {
  --v-field-border-opacity: 0.15 !important;

  color: rgba(var(--v-border-color), 1) !important;
}

.premium-input-compact :deep(.v-field--focused .v-field__outline) {
  --v-field-border-opacity: 1 !important;

  color: rgb(var(--v-theme-primary)) !important;
}

.premium-input-compact :deep(.v-field) {
  border-radius: 8px !important;
  background-color: white !important;
  min-block-size: 38px !important;
  padding-inline-start: 12px !important;
}

.premium-input-compact :deep(.v-field__input) {
  display: flex !important;
  align-items: center !important;
  font-size: 0.75rem !important;
  font-weight: 700;
  min-block-size: 38px !important;
  padding-block: 0 !important;
  text-transform: uppercase;
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: normal;
}

.shadow-sm {
  box-shadow: 0 2px 4px rgba(0, 0, 0, 5%) !important;
}

.shadow-primary-lg {
  box-shadow: 0 8px 24px rgba(var(--v-theme-primary), 0.25) !important;
}

.border-dashed-2 {
  border: 1px dashed rgba(var(--v-border-color), 0.3) !important;
}

.letter-spacing-1 {
  letter-spacing: 1.5px !important;
}

.leading-none {
  line-height: 1 !important;
}

.leading-tight {
  line-height: 1.25 !important;
}
</style>
