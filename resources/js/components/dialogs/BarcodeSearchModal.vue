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

const resetModal = (fullReset = false) => {
  barcode.value = "";
  searchPerformed.value = false;
  showOptions.value = false;
  if (fullReset) {
    productAddedSuccessfully.value = false;
    lastProductAdded.value = null;
  }
};

const handleClose = () => {
  emit("update:modelValue", false);
};

const handleSearch = () => {
  if (!barcode.value.trim()) return;
  productAddedSuccessfully.value = false;
  searchPerformed.value = true;
  emit("search-barcode", barcode.value.trim());
};

const handleKeyPress = (event) => {
  if (event.key === "Enter") {
    handleSearch();
  }
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
            autofocus
          />
          <VBtn
            :loading="loading"
            :disabled="!barcode.trim()"
            color="primary"
            variant="flat"
            block
            class="mt-3"
            @click="handleSearch"
          >
            <VIcon icon="tabler-search" class="me-2" />
            Buscar Producto
          </VBtn>
        </div>

        <VAlert
          v-if="productAddedSuccessfully"
          type="success"
          variant="tonal"
          class="mb-4"
          density="compact"
        >
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
          </div>
        </div>

        <div
          v-if="!searchPerformed && !showOptions"
          class="text-center text-disabled"
        >
          <VIcon icon="tabler-barcode" size="48" class="mb-3" />
          <p>Ingrese o escanee un código de barras</p>
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
