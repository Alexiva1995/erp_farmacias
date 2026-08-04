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

// Configuración dinámica para la fecha de fin (no permitir antes de la de inicio)
const endDateConfig = computed(() => ({
  altFormat: "Y-m-d",
  dateFormat: "Y-m-d",
  minDate: localFormData.value.start_date || undefined,
}));

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
    <VCard :class="mobile ? 'rounded-0' : 'detail-dialog-card rounded-xl border-0 shadow-xl overflow-hidden bg-surface'">
      <!-- Header Premium con Gradiente -->
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-4 d-flex align-center shadow-sm">
          <VAvatar
            color="white"
            variant="flat"
            size="40"
            class="me-3 elevation-1"
          >
            <VIcon
              icon="tabler-tag"
              size="24"
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
                style="font-size: 0.6rem; letter-spacing: 0.05em;"
              >
                Promociones Individuales de Productos • Barrio Sucre
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

      <VCardText class="pa-4 pa-sm-6 bg-light">
        <div class="d-flex align-center gap-2 mb-4">
          <div class="header-indicator primary shadow-sm" />
          <span class="text-subtitle-2 font-weight-black text-high-emphasis uppercase letter-spacing-1">Configuración de la Oferta</span>
        </div>

        <VCard
          variant="flat"
          class="pa-5 bg-white rounded-xl border shadow-sm mb-0"
        >
          <VRow dense>
            <!-- Selector de Producto -->
            <VCol cols="12">
              <div class="mb-4">
                <span class="text-super-xs font-weight-black text-disabled uppercase mb-2 d-block">Producto en Oferta</span>
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
                  density="comfortable"
                  hide-details
                  class="rounded-lg"
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
                <VTextField
                  v-else
                  :model-value="selectedProductDisplay"
                  readonly
                  variant="flat"
                  density="comfortable"
                  bg-color="grey-lighten-4"
                  class="rounded-lg font-weight-bold"
                  hide-details
                />
              </div>
            </VCol>

            <!-- Descuento y Vigencia -->
            <VCol
              cols="12"
              md="4"
            >
              <div class="mb-4 mb-md-0">
                <span class="text-super-xs font-weight-black text-disabled uppercase mb-2 d-block">% Descuento</span>
                <VTextField
                  v-model="localFormData.discount_percent"
                  type="number"
                  min="0"
                  max="100"
                  step="0.01"
                  variant="outlined"
                  density="comfortable"
                  hide-details
                  prepend-inner-icon="tabler-percentage"
                  class="rounded-lg font-weight-black"
                  :error="!!props.formErrors.discount_percent"
                  :error-messages="props.formErrors.discount_percent"
                  :disabled="props.loading"
                />
              </div>
            </VCol>

            <VCol
              cols="12"
              sm="6"
              md="4"
            >
              <div class="mb-4 mb-md-0">
                <span class="text-super-xs font-weight-black text-disabled uppercase mb-2 d-block">Fecha Inicio</span>
                <AppDateTimePicker
                  v-model="localFormData.start_date"
                  placeholder="SELECCIONAR FECHA"
                  prepend-inner-icon="tabler-calendar-event"
                  density="comfortable"
                  hide-details
                  class="rounded-lg"
                  :error="!!props.formErrors.start_date"
                  :error-messages="props.formErrors.start_date"
                  :disabled="props.loading"
                  :config="{ altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
                />
              </div>
            </VCol>

            <VCol
              cols="12"
              sm="6"
              md="4"
            >
              <div>
                <span class="text-super-xs font-weight-black text-disabled uppercase mb-2 d-block">Fecha Final</span>
                <AppDateTimePicker
                  v-model="localFormData.end_date"
                  placeholder="SELECCIONAR FECHA"
                  prepend-inner-icon="tabler-calendar-off"
                  density="comfortable"
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
        </VCard>

        <!-- Resumen de precios Premium -->
        <VExpandTransition>
          <div
            v-if="priceInfo"
            class="mt-6 pa-5 rounded-xl bg-var-theme-background border border-dashed rounded-lg animate__animated animate__fadeIn"
            style="background-color: rgba(var(--v-theme-primary), 0.05);"
          >
            <div class="d-flex align-center justify-space-between mb-4">
              <div class="d-flex align-center gap-2">
                <VAvatar
                  color="primary"
                  size="32"
                  variant="tonal"
                  class="rounded-lg"
                >
                  <VIcon
                    icon="tabler-calculator"
                    size="18"
                  />
                </VAvatar>
                <span class="text-xs font-weight-black text-primary uppercase letter-spacing-1">Simulación de la Oferta</span>
              </div>
              <VChip
                v-if="priceInfo.salePrice > priceInfo.finalPrice"
                color="success"
                variant="flat"
                size="small"
                class="font-weight-black px-3 shadow-sm rounded-lg"
              >
                AHORRO: ${{ (priceInfo.salePrice - priceInfo.finalPrice).toFixed(2) }}
              </VChip>
            </div>

            <VRow no-gutters>
              <VCol cols="6">
                <div class="d-flex flex-column leading-none">
                  <span class="text-super-xs font-weight-black text-high-emphasis opacity-70 uppercase mb-1">Precio Actual (Lista)</span>
                  <span class="text-h6 font-weight-bold text-high-emphasis text-decoration-line-through">
                    ${{ priceInfo.salePrice.toFixed(2) }}
                  </span>
                </div>
              </VCol>
              <VCol
                cols="6"
                class="text-end"
              >
                <div class="d-flex flex-column leading-none">
                  <span class="text-super-xs font-weight-black text-success uppercase mb-1">Precio Final Oferta</span>
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
      <VCardActions class="pa-4 bg-white border-t px-6">
        <VRow
          dense
          class="w-100 ma-0"
        >
          <VCol
            cols="12"
            sm="6"
            class="pa-1"
          >
            <VBtn
              color="secondary"
              variant="tonal"
              height="50"
              block
              class="font-weight-black rounded-lg text-button uppercase"
              @click="onCancel"
              :disabled="props.loading"
            >
              Cancelar
            </VBtn>
          </VCol>
          <VCol
            cols="12"
            sm="6"
            class="pa-1"
          >
            <VBtn
              color="primary"
              variant="flat"
              height="50"
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
    rgb(var(--v-theme-gradient-end)) 100%
  );
}

.detail-dialog-card {
  border-radius: 12px !important;
}

.header-indicator {
  inline-size: 4px;
  block-size: 16px;
  border-radius: 10px;
}

.header-indicator.primary {
  background-color: rgb(var(--v-theme-primary));
}

.shadow-primary {
  box-shadow: 0 4px 14px 0 rgba(var(--v-theme-primary), 0.39) !important;
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: normal;
}

.letter-spacing-1 {
  letter-spacing: 1px !important;
}

.leading-none {
  line-height: 1 !important;
}

.border-t {
  border-block-start: 1px solid rgba(var(--v-border-color), 0.08) !important;
}

.border-dashed-2 {
  border: 1px dashed rgba(var(--v-border-color), 0.3) !important;
}

.italic {
  font-style: italic;
}
</style>
