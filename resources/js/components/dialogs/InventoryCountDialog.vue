<script setup>
import { computed, nextTick, ref, watch } from "vue";

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  product: { type: Object, required: true },
});

const emit = defineEmits(["update:modelValue", "save"]);

const isVisible = computed({
  get: () => props.modelValue,
  set: (value) => emit("update:modelValue", value),
});

const barcodeInput = ref("");
const countedQuantity = ref("");
const isScanning = ref(false);
const barcodeError = ref("");

const systemStock = computed(() => Number(props.product?.stock) || 0);

const discrepancy = computed(() => {
  const counted = Number(countedQuantity.value) || 0;
  return counted - systemStock.value;
});

const discrepancyText = computed(() => {
  const diff = discrepancy.value;
  if (diff === 0) return "Sin discrepancia";
  if (diff > 0) return `Sobrante: +${diff}`;
  return `Faltante: ${diff}`;
});

const discrepancyColor = computed(() => {
  const diff = discrepancy.value;
  if (diff === 0) return "success";
  if (diff > 0) return "info";
  return "error";
});

const canSave = computed(() => {
  const isQuantityValid =
    countedQuantity.value !== "" && !isNaN(Number(countedQuantity.value));
  return (
    barcodeInput.value.trim() !== "" && isQuantityValid && !barcodeError.value
  );
});

const resetForm = () => {
  barcodeInput.value = "";
  countedQuantity.value = "";
  barcodeError.value = "";
  isScanning.value = false;
};

watch(
  () => props.modelValue,
  (newVal) => {
    if (newVal) {
      nextTick(() => {
        // Focus en el input del código de barras al abrir el modal
        const barcodeInputElement = document.querySelector("#barcode-input");
        if (barcodeInputElement) {
          barcodeInputElement.focus();
        }
      });
    } else {
      resetForm();
    }
  }
);

watch(barcodeInput, (newBarcode) => {
  if (!newBarcode.trim()) {
    barcodeError.value = "";
    return;
  }

  // Verificar si el código de barras coincide con el producto
  if (props.product.barcode && newBarcode.trim() !== props.product.barcode) {
    barcodeError.value = "El código de barras no coincide con este producto";
  } else {
    barcodeError.value = "";
  }
});

const handleBarcodeEnter = () => {
  if (!barcodeError.value && barcodeInput.value.trim()) {
    // Enfocar en el input de cantidad después de escanear
    nextTick(() => {
      const quantityInput = document.querySelector("#quantity-input");
      if (quantityInput) {
        quantityInput.focus();
      }
    });
  }
};

const handleSave = () => {
  if (!canSave.value) return;

  const countData = {
    barcode: barcodeInput.value.trim(),
    countedQuantity: Number(countedQuantity.value),
    system_quantity: systemStock.value,
    discrepancy: discrepancy.value,
  };

  emit("save", countData);
};

const startScanning = () => {
  isScanning.value = true;
  // Aquí podrías implementar integración con una librería de escaneo como QuaggaJS
  // Por ahora simulamos el escaneo
  setTimeout(() => {
    isScanning.value = false;
    if (props.product.barcode) {
      barcodeInput.value = props.product.barcode;
    }
  }, 2000);
};
</script>

<template>
  <VDialog v-model="isVisible" max-width="600" persistent>
    <VCard>
      <VCardTitle class="d-flex align-center gap-2 pa-6 pb-4">
        <VIcon icon="tabler-scan" />
        Conteo de Inventario
      </VCardTitle>

      <VDivider />

      <VCardText class="pa-6">
        <!-- Información del producto -->
        <div class="mb-6">
          <div class="d-flex align-center gap-4 mb-4">
            <VAvatar
              v-if="product.photo_url"
              size="48"
              variant="tonal"
              rounded
              :image="product.photo_url"
            />
            <VAvatar v-else size="48" variant="tonal" color="primary" rounded>
              <VIcon icon="tabler-pill" />
            </VAvatar>
            <div class="d-flex flex-column">
              <span class="text-h6 font-weight-medium">{{ product.name }}</span>
              <span class="text-body-2 text-disabled">{{
                product.active_ingredient
              }}</span>
              <span
                v-if="product.laboratory"
                class="text-body-2 text-medium-emphasis"
              >
                {{ product.laboratory.name }}
              </span>
            </div>
          </div>

          <!-- Stock actual -->
          <VAlert type="info" variant="tonal" class="mb-4">
            <template #prepend>
              <VIcon icon="tabler-package" />
            </template>
            <div class="d-flex justify-space-between align-center">
              <span>Stock actual en sistema:</span>
              <span class="font-weight-bold">{{ systemStock }} unidades</span>
            </div>
          </VAlert>
        </div>

        <VForm @submit.prevent="handleSave">
          <!-- Escaneo de código de barras -->
          <div class="mb-4">
            <label class="text-body-1 font-weight-medium mb-2 d-block">
              Código de Barras *
            </label>
            <div class="d-flex gap-2">
              <VTextField
                id="barcode-input"
                v-model="barcodeInput"
                placeholder="Escanea o ingresa el código de barras"
                variant="outlined"
                :error-messages="barcodeError"
                @keyup.enter="handleBarcodeEnter"
              >
                <template #prepend-inner>
                  <VIcon icon="tabler-barcode" />
                </template>
              </VTextField>
              <VBtn
                color="primary"
                variant="tonal"
                :loading="isScanning"
                @click="startScanning"
              >
                <VIcon icon="tabler-camera" />
              </VBtn>
            </div>
            <div v-if="product.barcode" class="text-caption text-disabled mt-1">
              Código esperado: {{ product.barcode }}
            </div>
          </div>

          <!-- Cantidad contada -->
          <div class="mb-4">
            <label class="text-body-1 font-weight-medium mb-2 d-block">
              Cantidad Contada *
            </label>
            <VTextField
              id="quantity-input"
              v-model="countedQuantity"
              type="number"
              min="0"
              placeholder="Ingresa la cantidad contada"
              variant="outlined"
              :disabled="!barcodeInput.trim() || !!barcodeError"
            >
              <template #prepend-inner>
                <VIcon icon="tabler-hash" />
              </template>
            </VTextField>
          </div>

          <!-- Discrepancia -->
          <div v-if="countedQuantity !== ''" class="mb-4">
            <VAlert :type="discrepancyColor" variant="tonal">
              <template #prepend>
                <VIcon
                  :icon="
                    discrepancy === 0
                      ? 'tabler-check'
                      : discrepancy > 0
                      ? 'tabler-plus'
                      : 'tabler-minus'
                  "
                />
              </template>
              <div class="d-flex justify-space-between align-center">
                <span>{{ discrepancyText }}</span>
                <span v-if="discrepancy !== 0" class="font-weight-bold">
                  {{ Math.abs(discrepancy) }} unidades
                </span>
              </div>
            </VAlert>
          </div>
        </VForm>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-6 pt-4">
        <VSpacer />
        <VBtn variant="outlined" @click="handleCancel"> Cancelar </VBtn>
        <VBtn color="primary" :disabled="!canSave" @click="handleSave">
          <VIcon icon="tabler-check" start />
          Guardar Conteo
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
:deep(.v-field--focused) {
  box-shadow: 0 0 0 2px rgba(var(--v-theme-primary), 0.2);
}
</style>
