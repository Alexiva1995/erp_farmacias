<script setup>
import { computed, nextTick, ref, watch } from "vue";
import Swal from "sweetalert2";
import BarcodeScannerDialog from "@/components/dialogs/BarcodeScannerDialog.vue";

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
const isScannerVisible = ref(false);
const barcodeError = ref("");
const allowWithoutBarcode = ref(false);

const canSave = computed(() => {
  const isQuantityValid =
    countedQuantity.value !== "" && !isNaN(Number(countedQuantity.value)) && Number(countedQuantity.value) >= 0;
  
  if (allowWithoutBarcode.value) {
    return isQuantityValid;
  }
  
  return (
    barcodeInput.value.trim() !== "" && isQuantityValid && !barcodeError.value
  );
});

const resetForm = () => {
  barcodeInput.value = "";
  countedQuantity.value = "";
  barcodeError.value = "";
  isScannerVisible.value = false;
  allowWithoutBarcode.value = false;
};

const handleCancel = () => {
  isVisible.value = false;
};

watch(
  () => props.modelValue,
  (newVal) => {
    if (newVal) {
      nextTick(() => {
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

  if (allowWithoutBarcode.value) {
    barcodeError.value = "";
    return;
  }

  if (props.product.barcode && newBarcode.trim() !== props.product.barcode) {
    barcodeError.value = "El código de barras no coincide con este producto";
  } else {
    barcodeError.value = "";
  }
});

watch(allowWithoutBarcode, (newValue) => {
  if (newValue) {
    barcodeError.value = "";
    barcodeInput.value = "";
    nextTick(() => {
      const quantityInput = document.querySelector("#quantity-input");
      if (quantityInput) {
        quantityInput.focus();
      }
    });
  }
});

const handleBarcodeEnter = () => {
  if (!barcodeError.value && barcodeInput.value.trim()) {
    nextTick(() => {
      const quantityInput = document.querySelector("#quantity-input");
      if (quantityInput) {
        quantityInput.focus();
      }
    });
  }
};

const onBarcodeScanned = (scannedBarcode) => {
  barcodeInput.value = scannedBarcode;
  isScannerVisible.value = false;
  handleBarcodeEnter();
};

const handleSave = async () => {
  if (!canSave.value) return;

  const quantity = Number(countedQuantity.value);

  const result = await Swal.fire({
    title: "Confirmar Conteo",
    text: `Confirma que está contando la cantidad de ${quantity} ${quantity === 1 ? 'unidad' : 'unidades'}`,
    icon: "question",
    showCancelButton: true,
    confirmButtonText: "Confirmar",
    cancelButtonText: "Cancelar",
    reverseButtons: true,
    confirmButtonColor: "rgba(var(--v-theme-primary), 1)",
    cancelButtonColor: "rgba(var(--v-theme-secondary), 1)",
  });

  if (!result.isConfirmed) return;

  const countData = {
    barcode: allowWithoutBarcode.value ? null : barcodeInput.value.trim(),
    countedQuantity: quantity,
    allowWithoutBarcode: allowWithoutBarcode.value,
  };

  emit("save", countData);
};
</script>

<template>
  <div>
    <VDialog v-model="isVisible" max-width="500" persistent>
      <VCard class="inventory-count-dialog">
        <VCardTitle class="d-flex align-center gap-2 pa-4 bg-primary">
          <VAvatar size="32" variant="tonal" color="white" class="me-2">
            <VIcon icon="tabler-scan" size="18" />
          </VAvatar>
          <span class="text-h6 text-white font-weight-bold">Conteo de Inventario</span>
          <VSpacer />
          <VBtn icon variant="text" color="white" size="small" @click="handleCancel">
            <VIcon>tabler-x</VIcon>
          </VBtn>
        </VCardTitle>

        <VCardText class="pa-6">
          <!-- Info del Producto -->
          <VCard variant="flat" border class="mb-6 pa-4 bg-var-theme-background border-dashed-thin">
            <div class="d-flex align-start gap-3">
              <VAvatar
                v-if="product.photo_url"
                size="64"
                variant="tonal"
                rounded
                :image="product.photo_url"
                class="flex-shrink-0"
              />
              <VAvatar
                v-else
                size="64"
                variant="tonal"
                rounded
                color="primary"
                class="flex-shrink-0"
              >
                <VIcon icon="tabler-package" size="32" />
              </VAvatar>
              <div class="d-flex flex-column min-width-0">
                <span class="text-h6 font-weight-black text-high-emphasis text-uppercase line-height-1 mb-1 truncate-2-lines">
                  {{ product.name }}
                </span>
                <span class="text-sm text-primary font-weight-bold mb-1">
                  {{ product.laboratory?.name || 'S/L' }}
                </span>
                <div class="d-flex align-center gap-1 text-super-xs text-disabled">
                  <VIcon icon="tabler-flask" size="12" />
                  <span class="text-truncate">{{ product.active_ingredient }}</span>
                </div>
              </div>
            </div>
          </VCard>

          <VForm @submit.prevent="handleSave">
            <!-- Selección de Modo sin Código -->
            <VCard
              variant="tonal"
              :color="allowWithoutBarcode ? 'warning' : 'info'"
              class="pa-3 mb-6 border-dashed-thin"
              :class="{ 'opacity-100': allowWithoutBarcode, 'opacity-70': !allowWithoutBarcode }"
            >
              <VCheckbox
                v-model="allowWithoutBarcode"
                color="primary"
                hide-details
                density="compact"
              >
                <template #label>
                  <div class="d-flex align-center gap-2">
                    <VIcon 
                      :icon="allowWithoutBarcode ? 'tabler-barcode-off' : 'tabler-barcode'" 
                      size="20" 
                      :color="allowWithoutBarcode ? 'warning' : 'info'" 
                    />
                    <span class="text-sm font-weight-bold">
                      Permitir conteo sin código de barras
                    </span>
                  </div>
                </template>
              </VCheckbox>
              <div v-if="allowWithoutBarcode" class="text-super-xs font-weight-medium mt-2 ms-8 d-flex align-center gap-1">
                <VIcon icon="tabler-alert-triangle" size="14" class="text-warning" />
                <span>El sistema permitirá registrar el conteo sin validación de código.</span>
              </div>
            </VCard>
            
            <!-- Campo de Código de Barras -->
            <div class="mb-4">
              <AppTextField
                id="barcode-input"
                v-model="barcodeInput"
                label="Validación de Código"
                placeholder="Ingresa el código de barras manualmente"
                :error-messages="barcodeError"
                :disabled="allowWithoutBarcode"
                @keyup.enter="handleBarcodeEnter"
              >
                <template #append-inner>
                  <div class="d-flex align-center">
                    <VTooltip location="top">
                      <template #activator="{ props: tooltipProps }">
                        <VIcon 
                          v-bind="tooltipProps"
                          icon="tabler-keyboard" 
                          size="20" 
                          class="text-disabled me-1" 
                        />
                      </template>
                      Entrada manual activa
                    </VTooltip>
                    
                    <VDivider vertical class="mx-2 my-1" />
                    
                    <VBtn
                      icon
                      variant="text"
                      size="small"
                      color="primary"
                      :disabled="allowWithoutBarcode"
                      @click="isScannerVisible = true"
                    >
                      <VIcon icon="tabler-camera-selfie" size="22" />
                      <VTooltip activator="parent" location="top">Escaneado con cámara</VTooltip>
                    </VBtn>
                  </div>
                </template>
              </AppTextField>
            </div>

            <!-- Campo de Cantidad -->
            <div>
              <AppTextField
                id="quantity-input"
                v-model="countedQuantity"
                label="Cantidad Física Contada"
                type="number"
                min="0"
                placeholder="0"
                :disabled="!allowWithoutBarcode && (!barcodeInput.trim() || !!barcodeError)"
                class="text-h6 font-weight-black"
                @keyup.enter="handleSave"
              >
                <template #prepend-inner>
                  <VIcon icon="tabler-hash" class="text-disabled" />
                </template>
              </AppTextField>
            </div>
          </VForm>
        </VCardText>

        <VDivider />

        <VCardActions class="pa-4 bg-var-theme-background">
          <VRow class="w-100 mx-0">
            <VCol cols="6" class="ps-0">
              <VBtn
                color="secondary"
                variant="outlined"
                block
                @click="handleCancel"
              >
                Cancelar
              </VBtn>
            </VCol>
            <VCol cols="6" class="pe-0">
              <VBtn
                color="primary"
                variant="flat"
                block
                :disabled="!canSave"
                class="font-weight-black"
                @click="handleSave"
              >
                GUARDAR CONTEO
              </VBtn>
            </VCol>
          </VRow>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Scanner Dialog Integrado -->
    <BarcodeScannerDialog
      v-model="isScannerVisible"
      @scan="onBarcodeScanned"
    />
  </div>
</template>

<style scoped>
.inventory-count-dialog {
  border-radius: 12px !important;
  overflow: hidden;
}

.border-dashed-thin {
  border: 1px dashed rgba(var(--v-border-color), 0.3) !important;
}

.bg-var-theme-background {
  background-color: rgba(var(--v-border-color), 0.05);
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: 1.2;
}

.truncate-2-lines {
  display: -webkit-box;
  overflow: hidden;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
}

.line-height-1 {
  line-height: 1.1 !important;
}

.gap-1 { gap: 4px !important; }
.gap-2 { gap: 8px !important; }
.gap-3 { gap: 12px !important; }

:deep(.v-field__input) {
  font-size: 1rem;
}

#quantity-input :deep(.v-field__input) {
  font-size: 1.25rem !important;
  font-weight: 800;
  color: rgb(var(--v-theme-primary));
}

.opacity-70 {
  opacity: 0.7;
}

.opacity-100 {
  opacity: 1;
}
</style>
