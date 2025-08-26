<script setup>
import { ref, watch } from "vue";

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  loading: { type: Boolean, default: false },
});

const emit = defineEmits([
  "update:modelValue",
  "search-barcode",
  "show-product-search",
  "add-new-product",
]);

const barcode = ref("");
const searchPerformed = ref(false);
const productFound = ref(null);
const showOptions = ref(false);

watch(
  () => props.modelValue,
  (newVal) => {
    if (newVal) {
      resetModal();
    }
  }
);

const resetModal = () => {
  barcode.value = "";
  searchPerformed.value = false;
  productFound.value = null;
  showOptions.value = false;
};

const handleClose = () => {
  emit("update:modelValue", false);
};

const handleSearch = () => {
  if (!barcode.value.trim()) return;

  searchPerformed.value = true;
  emit("search-barcode", barcode.value.trim());
};

const handleKeyPress = (event) => {
  if (event.key === "Enter") {
    handleSearch();
  }
};

const handleProductNotFound = () => {
  productFound.value = null;
  showOptions.value = true;
};

const handleProductFound = (product) => {
  productFound.value = product;
  showOptions.value = false;
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
        <span>Agregar Producto</span>
        <IconBtn @click="handleClose">
          <VIcon icon="tabler-x" />
        </IconBtn>
      </VCardTitle>

      <VCardText>
        <div class="mb-6">
          <VTextField
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

        <div v-if="productFound" class="mb-4">
          <VAlert type="success" variant="tonal" class="mb-4">
            <VAlertTitle>¡Producto encontrado!</VAlertTitle>
            <div class="mt-2">
              <strong>{{ productFound.name }}</strong
              ><br />
              <span class="text-body-2">
                Precio: {{ formatCurrency(productFound.unit_cost) }}
              </span>
            </div>
          </VAlert>

          <VBtn
            color="success"
            variant="flat"
            block
            @click="
              $emit('add-product-to-invoice', productFound);
              handleClose();
            "
          >
            <VIcon icon="tabler-plus" class="me-2" />
            Agregar a la Factura
          </VBtn>
        </div>

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
          v-if="!searchPerformed && !productFound && !showOptions"
          class="text-center text-disabled"
        >
          <VIcon icon="tabler-barcode" size="48" class="mb-3" />
          <p>Ingrese el código de barras del producto</p>
        </div>
      </VCardText>

      <VCardActions class="pa-4">
        <VSpacer />
        <VBtn variant="text" @click="handleClose"> Cancelar </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<script>
export default {
  methods: {
    formatCurrency(value) {
      return new Intl.NumberFormat("es-VE", {
        style: "currency",
        currency: "VES",
        minimumFractionDigits: 2,
      }).format(value);
    },
  },
};
</script>
