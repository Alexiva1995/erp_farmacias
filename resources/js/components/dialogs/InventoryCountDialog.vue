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
    max-width="500"
    persistent
    :fullscreen="$vuetify.display.xs"
    transition="dialog-bottom-transition"
    class="premium-dialog"
  >
    <VCard class="detail-dialog-card overflow-hidden border-0 elevation-12">
      <!-- Cabecera Compacta Premium -->
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-3 d-flex align-center shadow-sm">
          <div class="d-flex align-center">
            <VAvatar color="white" variant="flat" size="32" class="me-3 elevation-1">
              <VIcon icon="tabler-scan" color="primary" size="18" />
            </VAvatar>
            <div>
              <h2 class="text-subtitle-2 font-weight-black text-white leading-tight mb-0">Conteo de Inventario</h2>
              <span class="text-super-xs text-white opacity-75 uppercase font-weight-bold" style="font-size: 0.6rem;">
                Validación física
              </span>
            </div>
          </div>
          <VSpacer />
          <VBtn icon variant="tonal" color="white" size="x-small" @click="handleCancel">
            <VIcon size="18">tabler-x</VIcon>
          </VBtn>
        </div>
      </VCardTitle>

      <VCardText class="pa-4 bg-light inventory-count-content h-100">
        <!-- Perfil del Producto Premium Elevado -->
        <VCard variant="flat" class="border pa-4 mb-4 bg-white elevation-2 rounded-lg transition-all border-l-primary">
          <div class="d-flex flex-column min-width-0">
            <div class="d-flex align-center justify-space-between mb-3">
              <div class="d-flex align-center gap-2">
                <VChip size="small" color="primary" variant="flat" class="font-weight-black uppercase px-3 shadow-sm">
                  ID: {{ product.id }}
                </VChip>
                <VChip size="small" color="secondary" variant="tonal" class="font-weight-black uppercase px-3">
                  {{ product.category?.name || 'General' }}
                </VChip>
              </div>
              <div class="header-indicator primary"></div>
            </div>
            
            <h3 class="text-h6 font-weight-black text-high-emphasis leading-tight uppercase mb-2">
              {{ product.name }}
            </h3>

            <div class="d-flex flex-wrap align-center gap-x-6 gap-y-3 mt-1">
              <div class="d-flex align-center gap-2">
                <VAvatar size="24" color="primary-lighten-5" class="border">
                  <VIcon icon="tabler-building-factory" size="14" color="primary" />
                </VAvatar>
                <div class="d-flex flex-column">
                  <span class="text-super-xs font-weight-black text-disabled uppercase leading-none mb-1">Laboratorio</span>
                  <span class="text-xs font-weight-bold text-high-emphasis uppercase">
                    {{ product.laboratory?.name || 'S/L' }}
                  </span>
                </div>
              </div>

              <div class="d-flex align-center gap-2">
                <VAvatar size="24" color="primary-lighten-5" class="border">
                  <VIcon icon="tabler-flask" size="14" color="primary" />
                </VAvatar>
                <div class="d-flex flex-column">
                  <span class="text-super-xs font-weight-black text-disabled uppercase leading-none mb-1">P. Activo</span>
                  <span class="text-xs font-weight-bold text-high-emphasis uppercase text-truncate" style="max-inline-size: 150px;">
                    {{ product.active_ingredient || 'Sin registrar' }}
                  </span>
                </div>
              </div>
            </div>
          </div>
        </VCard>

        <VForm @submit.prevent="handleSave">
          <!-- Banner de Selección de Modo Llamativo -->
          <VCard
            variant="flat"
            :color="allowWithoutBarcode ? 'warning' : 'primary'"
            class="pa-3 mb-4 rounded-lg border-dashed-2 transition-all shadow-sm"
            :class="allowWithoutBarcode ? 'bg-warning-lighten-5' : 'bg-primary-lighten-5'"
          >
            <VCheckbox
              v-model="allowWithoutBarcode"
              :color="allowWithoutBarcode ? 'warning' : 'primary'"
              hide-details
              density="comfortable"
              class="ma-0 pa-0"
            >
              <template #label>
                <div class="d-flex align-center gap-3">
                  <VAvatar :color="allowWithoutBarcode ? 'warning' : 'primary'" size="32" variant="tonal" class="elevation-1">
                    <VIcon 
                      :icon="allowWithoutBarcode ? 'tabler-barcode-off' : 'tabler-barcode'" 
                      size="20" 
                    />
                  </VAvatar>
                  <div>
                    <span class="text-subtitle-2 font-weight-black uppercase d-block" :class="allowWithoutBarcode ? 'text-warning' : 'text-primary'">
                      {{ allowWithoutBarcode ? 'Conteo Manual Activo' : 'Modo Escaneo Activo' }}
                    </span>
                    <span class="text-super-xs font-weight-bold opacity-70 uppercase">
                      {{ allowWithoutBarcode ? 'Ajuste de stock sin código de barras' : 'Validación obligatoria por scanner' }}
                    </span>
                  </div>
                </div>
              </template>
            </VCheckbox>
          </VCard>
          
          <!-- Campo de Escaneo Premium -->
          <div v-if="!allowWithoutBarcode" class="mb-4">
            <AppTextField
              id="barcode-input"
              v-model="barcodeInput"
              label="Escanear Producto"
              placeholder="Apunta el lector aquí..."
              :error-messages="barcodeError"
              density="comfortable"
              class="shadow-sm premium-textfield"
              @keyup.enter="handleBarcodeEnter"
            >
              <template #append-inner>
                    @click="isScannerVisible = true"
                  >
                    <VIcon icon="tabler-camera" size="18" />
                  </VBtn>
                </div>
              </template>
            </AppTextField>
          </div>

          <!-- Input de Cantidad Premium -->
          <VCard variant="flat" border class="pa-4 rounded-lg mb-4 bg-white border-dashed-2 shadow-sm">
            <div class="d-flex align-center justify-space-between mb-3">
              <span class="text-subtitle-2 font-weight-black text-disabled uppercase letter-spacing-1">Cantidad Auditada</span>
              <VChip 
                size="small" 
                color="primary" 
                variant="tonal" 
                class="font-weight-black px-2"
              >
                UNIDADES
              </VChip>
            </div>
            
            <div class="d-flex justify-center align-center py-2">
              <AppTextField
                id="quantity-input"
                v-model.number="countedQuantity"
                type="number"
                min="0"
                placeholder="0"
                variant="plain"
                class="ultra-huge-input-text h-auto font-weight-950"
                density="compact"
                hide-details
                :disabled="!allowWithoutBarcode && (!barcodeInput.trim() || !!barcodeError)"
                @keyup.enter="handleSave"
              />
            </div>
          </VCard>
        </VForm>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4 bg-light border-t">
        <VRow dense class="w-100 ma-0">
          <VCol cols="6" class="pa-1">
            <VBtn
              color="secondary"
              variant="tonal"
              size="large"
              block
              height="50"
              class="font-weight-black rounded-lg text-button uppercase"
              @click="handleCancel"
            >
              Cancelar
            </VBtn>
          </VCol>
          <VCol cols="6" class="pa-1">
            <VBtn
              color="primary"
              variant="flat"
              size="large"
              block
              height="50"
              class="font-weight-black rounded-lg shadow-primary text-button uppercase"
              :disabled="!canSave"
              @click="handleSave"
            >
              Guardar
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
.detail-dialog-card {
  border-radius: 16px !important;
}

.premium-dialog-top {
  align-items: start !important;
  margin-block-start: 1.5rem !important;
}

.header-gradient {
  background: linear-gradient(135deg, rgb(var(--v-theme-primary)) 0%, #173b22 100%);
}

.bg-light {
  background-color: #f8fafc !important;
}

.border-dashed-1 {
  border: 1px dashed rgba(var(--v-border-color), 20%) !important;
}

.bg-primary-lighten-5 {
  background-color: rgba(var(--v-theme-primary), 0.05) !important;
}

.bg-warning-lighten-5 {
  background-color: rgba(var(--v-theme-warning), 0.05) !important;
}

.ultra-huge-input-text :deep(input) {
  border: none;
  background: transparent;
  color: rgb(var(--v-theme-primary)) !important;
  font-size: 2rem !important;
  font-weight: 950 !important;
  inline-size: 100%;
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
  overflow: hidden;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
}

.shadow-primary {
  box-shadow: 0 4px 14px 0 rgba(var(--v-theme-primary), 0.39) !important;
}

.border-t {
  border-block-start: 1px solid rgba(var(--v-border-color), 0.08) !important;
}
</style>
