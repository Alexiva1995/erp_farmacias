<script setup>
import { computed, watch, ref } from "vue";
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

const dialogTitle = computed(() => {
  return props.isEditing ? "Editar Oferta" : "Crear Oferta";
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
          photo_url: product.photo_url,
          barcode: product.barcode,
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
  // Preparar datos para enviar
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
        // Modo edición
        localFormData.value = {
          id: props.productOfferToEdit.id,
          product_id: props.productOfferToEdit.product_id,
          discount_percent: props.productOfferToEdit.discount_percent,
          start_date: formatDateForInput(props.productOfferToEdit.start_date),
          end_date: formatDateForInput(props.productOfferToEdit.end_date),
        };
      } else {
        // Modo creación
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

// Al abrir, cargar productos (en edición por ID para obtener sale_price)
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
    :retain-focus="false"
    @click:outside.prevent
    @keydown.esc.prevent="onCancel"
  >
    <VCard :loading="props.loading">
      <VCardTitle class="d-flex align-center justify-space-between pa-5 bg-primary">
        <div class="d-flex align-center gap-3">
          <VIcon icon="tabler-tag" size="24" color="white" />
          <span class="text-h6 text-white">{{ dialogTitle }}</span>
        </div>
        <VBtn icon variant="text" color="white" size="small" @click="onCancel" :disabled="props.loading">
          <VIcon>tabler-x</VIcon>
        </VBtn>
      </VCardTitle>

      <VCardText class="pa-5">
        <VRow>
          <VCol cols="12">
            <VAutocomplete
              v-if="!props.isEditing"
              v-model="localFormData.product_id"
              label="Seleccionar Producto"
              variant="outlined"
              :items="availableProducts"
              item-title="name"
              item-value="id"
              placeholder="Buscar por ID, Producto, C. Activo..."
              :error="!!props.formErrors.product_id"
              :error-messages="props.formErrors.product_id"
              clearable
              no-data-text="Escriba para buscar productos"
              :loading="loadingProducts"
              :disabled="props.loading"
              :custom-filter="() => true"
              @update:search="handleProductSearch"
            >
              <template #item="{ props: itemProps, item: productItem }">
                <VListItem v-bind="{ ...itemProps, title: '' }">
                  <template v-if="productItem.raw.photo_url" #prepend>
                    <VAvatar size="40" :image="productItem.raw.photo_url" variant="tonal" />
                  </template>
                  <VListItemTitle>{{ productItem.raw.name }}</VListItemTitle>
                  <VListItemSubtitle>
                    ID: {{ productItem.raw.id }} | Stock: {{ productItem.raw.stock }} | Precio: ${{ productItem.raw.sale_price }}
                    <span v-if="productItem.raw.barcode"> | Código: {{ productItem.raw.barcode }}</span>
                  </VListItemSubtitle>
                </VListItem>
              </template>
            </VAutocomplete>
            <VTextField
              v-else
              :model-value="selectedProductDisplay"
              label="Producto"
              readonly
              variant="outlined"
              bg-color="grey-lighten-5"
            />
          </VCol>
          <VCol cols="12" sm="4" md="4">
            <VTextField
              v-model="localFormData.discount_percent"
              label="% Descuento"
              variant="outlined"
              type="number"
              min="0"
              max="100"
              step="0.01"
              :error="!!props.formErrors.discount_percent"
              :error-messages="props.formErrors.discount_percent"
              :disabled="props.loading"
            />
          </VCol>
          <VCol cols="12" sm="4" md="4">
            <VTextField
              v-model="localFormData.start_date"
              label="Fecha Inicio"
              variant="outlined"
              type="date"
              :error="!!props.formErrors.start_date"
              :error-messages="props.formErrors.start_date"
              :disabled="props.loading"
            />
          </VCol>
          <VCol cols="12" sm="4" md="4">
            <VTextField
              v-model="localFormData.end_date"
              label="Fecha Final"
              variant="outlined"
              type="date"
              :error="!!props.formErrors.end_date"
              :error-messages="props.formErrors.end_date"
              :disabled="props.loading"
            />
          </VCol>
        </VRow>

        <!-- Resumen de precios - minimalista -->
        <div v-if="priceInfo" class="price-summary mt-5">
          <div class="price-summary-row">
            <span class="text-body-2 text-medium-emphasis">Precio actual</span>
            <span class="text-body-2 text-decoration-line-through text-medium-emphasis">${{ priceInfo.salePrice.toFixed(2) }}</span>
          </div>
          <div class="price-summary-row price-final">
            <span class="text-body-2 font-weight-medium">Tu precio</span>
            <span class="text-h6 font-weight-bold text-success">${{ priceInfo.finalPrice.toFixed(2) }}</span>
          </div>
          <div v-if="priceInfo.salePrice > priceInfo.finalPrice" class="price-summary-savings">
            Ahorras ${{ (priceInfo.salePrice - priceInfo.finalPrice).toFixed(2) }}
          </div>
        </div>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4 px-5">
        <VRow class="w-100 ma-0">
          <VCol cols="6" class="pa-2">
            <VBtn
              color="secondary"
              variant="outlined"
              prepend-icon="tabler-x"
              block
              @click="onCancel"
              :disabled="props.loading"
            >
              Cancelar
            </VBtn>
          </VCol>
          <VCol cols="6" class="pa-2">
            <VBtn
              color="primary"
              variant="flat"
              prepend-icon="tabler-check"
              block
              @click="onSave"
              :disabled="props.loading"
            >
              {{ props.isEditing ? "Actualizar" : "Guardar" }}
            </VBtn>
          </VCol>
        </VRow>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.price-summary {
  border-inline-start: 3px solid rgb(var(--v-theme-success));
  padding-inline-start: 16px;
}

.price-summary-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding-block: 4px;
  padding-inline: 0;
}

.price-final {
  border-block-start: 1px solid rgba(var(--v-border-color), 0.2);
  margin-block-start: 8px;
  padding-block-start: 8px;
}

.price-summary-savings {
  color: rgb(var(--v-theme-success));
  font-size: 0.75rem;
  font-weight: 500;
  margin-block-start: 12px;
}
</style>
