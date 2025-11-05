<script setup>
import { nextTick, ref, watch } from "vue";

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  loading: { type: Boolean, default: false },
});

const emit = defineEmits([
  "update:modelValue",
  "search-barcode",
  "show-product-search",
  "add-new-product",
  "add-product-to-invoice",
]);

const barcode = ref("");
const searchPerformed = ref(false);
const showOptions = ref(false);
const productAddedSuccessfully = ref(false);
const lastProductAdded = ref(null);

const barcodeInputRef = ref(null);

let searchTimer = null;

watch(
  () => props.modelValue,
  (newVal) => {
    if (newVal) {
      resetModal(true);
      nextTick(() => {
        barcodeInputRef.value?.focus();
      });
    }
  }
);

watch(barcode, (newValue, oldValue) => {
  if (!newValue.trim()) {
    clearTimeout(searchTimer);
    return;
  }

  const lengthDifference = Math.abs(newValue.length - (oldValue?.length || 0));

  clearTimeout(searchTimer);

  if (lengthDifference > 1 || newValue.length >= 8) {
    searchTimer = setTimeout(() => {
      handleSearch();
    }, 300);
  }
});

const resetModal = (fullReset = false) => {
  barcode.value = "";
  searchPerformed.value = false;
  showOptions.value = false;
  if (fullReset) {
    productAddedSuccessfully.value = false;
    lastProductAdded.value = null;
  }
  clearTimeout(searchTimer);
};

const handleClose = () => {
  clearTimeout(searchTimer);
  emit("update:modelValue", false);
};

const handleSearch = () => {
  if (!barcode.value.trim()) return;

  if (searchPerformed.value) return;

  productAddedSuccessfully.value = false;
  searchPerformed.value = true;
  emit("search-barcode", barcode.value.trim());
};

const handleKeyPress = (event) => {
  if (event.key === "Enter" && barcode.value.trim()) {
    clearTimeout(searchTimer);
    handleSearch();
  }
};

const handlePaste = () => {
  nextTick(() => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
      if (barcode.value.trim()) {
        handleSearch();
      }
    }, 100);
  });
};

const handleProductNotFound = () => {
  showOptions.value = true;
};

const handleProductFound = (product) => {
  emit("add-product-to-invoice", product);

  lastProductAdded.value = product.name;
  productAddedSuccessfully.value = true;
  setTimeout(() => {
    productAddedSuccessfully.value = false;
  }, 2500);

  resetModal();
  nextTick(() => {
    barcodeInputRef.value?.focus();
  });
};

const handleShowProductSearch = () => {
  emit("show-product-search");
  handleClose();
};

const handleAddNewProduct = () => {
  emit("add-new-product");
  handleClose();
};

defineExpose({
  handleProductNotFound,
  handleProductFound,
});
</script>

<template>
  <VDialog
    :model-value="modelValue"
    @update:model-value="emit('update:modelValue', $event)"
    max-width="500"
    persistent
  >
    <VCard>
      <VCardTitle class="d-flex align-center justify-space-between">
        <span>Agregar Producto por Lector</span>
        <IconBtn @click="handleClose">
          <VIcon icon="tabler-x" />
        </IconBtn>
      </VCardTitle>

      <VCardText>
        <div class="mb-6">
          <VTextField
            ref="barcodeInputRef"
            v-model="barcode"
            label="Código de Barras"
            placeholder="Escanee o escriba el código de barras"
            variant="outlined"
            prepend-inner-icon="tabler-barcode"
            :loading="loading"
            @keypress="handleKeyPress"
            @paste="handlePaste"
            autofocus
          />

          <!-- Información sobre búsqueda automática -->
          <div
            class="text-caption text-medium-emphasis mt-2 d-flex align-center"
          >
            <VIcon icon="tabler-info-circle" size="16" class="me-1" />
            La búsqueda se realiza automáticamente al escanear o pegar
          </div>
        </div>

        <VAlert
          v-if="productAddedSuccessfully"
          type="success"
          variant="tonal"
          class="mb-4"
          density="compact"
        >
          <VIcon icon="tabler-check" class="me-2" />
          Agregado: <strong>{{ lastProductAdded }}</strong>
        </VAlert>

        <div v-if="showOptions" class="text-center">
          <VAlert type="warning" variant="tonal" class="mb-4">
            <VAlertTitle>Producto no encontrado</VAlertTitle>
            <div class="text-body-2 mt-1">
              No se encontró ningún producto con el código "{{ barcode }}"
            </div>
          </VAlert>

          <div class="d-flex flex-column ga-3">
            <VBtn
              color="primary"
              variant="flat"
              size="large"
              @click="handleShowProductSearch"
            >
              <VIcon icon="tabler-search" class="me-2" />
              Buscar en Catálogo
            </VBtn>
            <VBtn
              color="secondary"
              variant="outlined"
              size="large"
              @click="handleAddNewProduct"
            >
              <VIcon icon="tabler-plus" class="me-2" />
              Crear Nuevo Producto
            </VBtn>
            <VBtn variant="text" size="small" @click="resetModal()">
              <VIcon icon="tabler-refresh" class="me-2" />
              Intentar Otro Código
            </VBtn>
          </div>
        </div>

        <div
          v-if="!searchPerformed && !showOptions && !loading"
          class="text-center text-disabled"
        >
          <VIcon icon="tabler-barcode" size="48" class="mb-3" />
          <p>Escanee o pegue un código de barras</p>
          <p class="text-caption">La búsqueda se realiza automáticamente</p>
        </div>

        <div v-if="loading" class="text-center text-primary">
          <VProgressCircular indeterminate size="48" class="mb-3" />
          <p>Buscando producto...</p>
        </div>
      </VCardText>

      <VCardActions class="pa-4">
        <VSpacer />
        <VBtn variant="tonal" color="secondary" @click="handleClose">
          Finalizar
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
