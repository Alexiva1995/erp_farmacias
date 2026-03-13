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

const fillBarcode = () => {
  if (props.product.barcode) {
    barcodeInput.value = props.product.barcode;
    handleBarcodeEnter();
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
  <VDialog
    v-model="isVisible"
    max-width="550"
    persistent
    class="premium-dialog"
  >
    <VCard class="detail-dialog-card overflow-hidden rounded-xl border-0 elevation-24">
      <!-- Cabecera Premium Estilo Trazabilidad -->
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-4 d-flex align-center shadow-lg">
          <div class="d-flex align-center">
            <VAvatar color="white" variant="flat" size="40" class="me-3 elevation-2">
              <VIcon icon="tabler-scan" color="primary" />
            </VAvatar>
            <div>
              <h2 class="text-h6 font-weight-black text-white leading-tight mb-0">Conteo de Inventario</h2>
              <span class="text-caption text-white opacity-75 letter-spacing-1 uppercase font-weight-bold" style="font-size: 0.65rem;">
                Validación física de existencias
              </span>
            </div>
          </div>
          <VSpacer />
          <VBtn icon variant="tonal" color="white" size="small" @click="handleCancel">
            <VIcon>tabler-x</VIcon>
          </VBtn>
        </div>
      </VCardTitle>

      <VCardText class="pa-4 pa-sm-6 bg-light inventory-count-content">
        <!-- Perfil del Producto Estilo Trazabilidad -->
        <VCard variant="flat" class="border pa-4 mb-4 bg-white elevation-1 rounded-xl">
          <div class="d-flex flex-column min-width-0">
            <div class="d-flex align-center gap-2 mb-1">
              <VChip size="x-small" color="primary" variant="tonal" class="font-weight-black uppercase">
                {{ product.laboratory?.name || 'S/L' }}
              </VChip>
              <span class="text-super-xs font-weight-bold text-disabled uppercase">Laboratorio</span>
            </div>
            <h3 class="text-h6 font-weight-black text-high-emphasis leading-tight uppercase truncate-2-lines mb-1">
              {{ product.name }}
            </h3>
            <div class="d-flex align-center gap-1 text-super-xs text-disabled font-weight-medium">
              <VIcon icon="tabler-flask" size="12" />
              <span class="text-truncate">{{ product.active_ingredient }}</span>
            </div>
          </div>
        </VCard>

        <VForm @submit.prevent="handleSave">
          <!-- Banner de Selección de Modo -->
          <VCard
            variant="flat"
            :color="allowWithoutBarcode ? 'warning' : 'primary'"
            class="pa-3 mb-6 rounded-xl border-dashed-2 transition-all"
            :class="allowWithoutBarcode ? 'bg-warning-lighten-5' : 'bg-primary-lighten-5'"
          >
            <VCheckbox
              v-model="allowWithoutBarcode"
              :color="allowWithoutBarcode ? 'warning' : 'primary'"
              hide-details
              density="compact"
            >
              <template #label>
                <div class="d-flex align-center gap-2">
                  <VIcon 
                    :icon="allowWithoutBarcode ? 'tabler-barcode-off' : 'tabler-barcode'" 
                    size="20" 
                    :color="allowWithoutBarcode ? 'warning' : 'primary'" 
                  />
                  <span class="text-sm font-weight-black uppercase letter-spacing-05" :class="allowWithoutBarcode ? 'text-warning' : 'text-primary'">
                    Permitir conteo sin código
                  </span>
                </div>
              </template>
            </VCheckbox>
            <VExpandTransition>
              <div v-if="allowWithoutBarcode" class="text-super-xs font-weight-bold mt-2 ms-8 d-flex align-center gap-1 text-warning uppercase">
                <VIcon icon="tabler-alert-triangle" size="14" />
                <span>Saltando validación de código de barras</span>
              </div>
            </VExpandTransition>
          </VCard>
          
          <!-- Campo de Código de Barras Premium -->
          <div class="mb-4">
            <AppTextField
              id="barcode-input"
              v-model="barcodeInput"
              label="Escanear Código"
              placeholder="Escanea el código de barras"
              :error-messages="barcodeError"
              :disabled="allowWithoutBarcode"
              class="premium-input"
              @keyup.enter="handleBarcodeEnter"
            >
              <template #append-inner>
                <div class="d-flex align-center gap-1 pe-1">
                  <VBtn
                    icon
                    variant="tonal"
                    size="small"
                    color="info"
                    :disabled="allowWithoutBarcode || !product.barcode"
                    @click="fillBarcode"
                  >
                    <VIcon icon="tabler-wand" size="18" />
                    <VTooltip activator="parent" location="top">Cargar sugerencia</VTooltip>
                  </VBtn>
                  
                  <VBtn
                    icon
                    variant="flat"
                    size="small"
                    color="primary"
                    :disabled="allowWithoutBarcode"
                    class="shadow-sm"
                    @click="isScannerVisible = true"
                  >
                    <VIcon icon="tabler-camera" size="20" />
                    <VTooltip activator="parent" location="top">Usar Cámara</VTooltip>
                  </VBtn>
                </div>
              </template>
            </AppTextField>
          </div>

          <!-- Input de Cantidad Inspirado en Trazabilidad -->
          <VCard variant="flat" border class="pa-4 rounded-xl mb-2 bg-white border-dashed-2">
            <div class="d-flex align-center justify-space-between mb-2">
              <span class="text-super-xs font-weight-black text-disabled uppercase">Cantidad Contada</span>
              <VChip 
                size="x-small" 
                color="primary" 
                variant="flat" 
                class="font-weight-black shadow-sm"
              >
                UNIDADES
              </VChip>
            </div>
            
            <div class="d-flex justify-center align-center">
              <AppTextField
                id="quantity-input"
                v-model.number="countedQuantity"
                type="number"
                min="0"
                placeholder="0"
                variant="plain"
                class="ultra-huge-input-text"
                :disabled="!allowWithoutBarcode && (!barcodeInput.trim() || !!barcodeError)"
                @keyup.enter="handleSave"
              />
            </div>
          </VCard>
        </VForm>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4 pa-sm-6 bg-light border-t">
        <VRow dense class="w-100">
          <VCol cols="12" sm="6">
            <VBtn
              color="secondary"
              variant="tonal"
              size="large"
              block
              class="font-weight-black rounded-lg"
              @click="handleCancel"
            >
              CANCELAR
            </VBtn>
          </VCol>
          <VCol cols="12" sm="6">
            <VBtn
              color="primary"
              variant="flat"
              size="large"
              block
              class="font-weight-black rounded-lg shadow-primary elevation-2"
              :disabled="!canSave"
              @click="handleSave"
            >
              CONFIRMAR
            </VBtn>
          </VCol>
        </VRow>
      </VCardActions>
    </VCard>

    <BarcodeScannerDialog
      v-model="isScannerVisible"
      @scan="onBarcodeScanned"
    />
  </VDialog>
</template>

<style scoped>
.header-gradient {
  background: linear-gradient(135deg, rgb(var(--v-theme-primary)) 0%, #1e5128 100%);
}

.bg-light {
  background-color: #f8fafc !important;
}

.border-dashed-2 {
  border: 2px dashed rgba(var(--v-border-color), 15%) !important;
}

.bg-primary-lighten-5 {
  background-color: rgba(var(--v-theme-primary), 0.05) !important;
}

.bg-warning-lighten-5 {
  background-color: rgba(var(--v-theme-warning), 0.05) !important;
}

.ultra-huge-input-text :deep(input) {
  inline-size: 100%;
  border: none;
  background: transparent;
  color: rgb(var(--v-theme-primary)) !important;
  font-size: 3rem !important;
  font-weight: 900 !important;
  line-height: 1;
  outline: none;
  text-align: center !important;
}

.ultra-huge-input-text :deep(.v-field__input) {
  padding: 0 !important;
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: normal;
}

.letter-spacing-1 { letter-spacing: 1.5px !important; }
.letter-spacing-05 { letter-spacing: 0.5px !important; }
.leading-tight { line-height: 1.25 !important; }

.truncate-2-lines {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.shadow-primary {
  box-shadow: 0 4px 14px 0 rgba(var(--v-theme-primary), 0.39) !important;
}

.border-t {
  border-block-start: 1px solid rgba(var(--v-border-color), 0.08) !important;
}
</style>
