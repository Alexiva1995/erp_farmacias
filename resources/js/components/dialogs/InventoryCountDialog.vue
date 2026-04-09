<script setup>
import BarcodeScannerDialog from "@/components/dialogs/BarcodeScannerDialog.vue";
import Swal from "sweetalert2";
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
const isScannerVisible = ref(false);
const barcodeError = ref("");
const allowWithoutBarcode = ref(false);

const canSave = computed(() => {
  const isQuantityValid =
    countedQuantity.value !== "" &&
    !isNaN(Number(countedQuantity.value)) &&
    Number(countedQuantity.value) >= 0;

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
  },
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
    text: `Confirma que está contando la cantidad de ${quantity} ${quantity === 1 ? "unidad" : "unidades"}`,
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
    <VCard class="detail-dialog-card rounded-xl overflow-hidden border-0 shadow-xl bg-surface">
      <!-- Cabecera Premium -->
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-4 d-flex align-center shadow-sm">
          <VAvatar
            color="white"
            variant="flat"
            size="40"
            class="me-3 elevation-1"
          >
            <VIcon icon="tabler-clipboard-check" color="primary" size="24" />
          </VAvatar>
          <div class="d-flex flex-column leading-none text-white">
            <h2 class="text-h6 font-weight-black leading-tight mb-0 uppercase">
              Conteo de Inventario
            </h2>
            <span class="text-super-xs opacity-75 font-weight-bold uppercase letter-spacing-1">
              Validación Física • Auditoría de Stock
            </span>
          </div>
          <VSpacer />
          <VBtn
            icon="tabler-x"
            variant="tonal"
            color="white"
            size="small"
            class="rounded-lg"
            @click="handleCancel"
          />
        </div>
      </VCardTitle>

      <VCardText class="pa-2 pa-sm-4 bg-light d-flex flex-column gap-2 gap-sm-4">
        <!-- Perfil del Producto Compacto -->
        <div class="pa-2 pa-sm-3 bg-white rounded-lg border shadow-xs">
          <div class="d-flex align-center justify-space-between mb-1">
            <VChip
              size="x-small"
              color="primary"
              variant="flat"
              class="font-weight-black px-2 rounded"
            >
              ID: {{ product.id }}
            </VChip>
            <span class="text-super-xs font-weight-black text-disabled uppercase truncate" style="max-inline-size: 150px;">
              {{ product.laboratory?.name || "Sin Lab" }}
            </span>
          </div>

          <h3 class="text-xs font-weight-black text-high-emphasis leading-tight uppercase mb-0">
            {{ product.name }}
          </h3>
        </div>

        <VForm @submit.prevent="handleSave">
          <!-- Modo de Ingreso Compacto -->
          <div
            class="pa-2 mb-2 rounded-lg border shadow-xs d-flex align-center justify-space-between"
            :class="allowWithoutBarcode ? 'bg-warning-lighten-5' : 'bg-primary-lighten-5'"
          >
            <div class="d-flex align-center gap-2">
              <VIcon
                :icon="allowWithoutBarcode ? 'tabler-keyboard' : 'tabler-scan'"
                :color="allowWithoutBarcode ? 'warning' : 'primary'"
                size="18"
              />
              <span class="text-super-xs font-weight-black uppercase">
                {{ allowWithoutBarcode ? "Ingreso Manual" : "Modo Escaneo" }}
              </span>
            </div>
            <VSwitch
              v-model="allowWithoutBarcode"
              :color="allowWithoutBarcode ? 'warning' : 'primary'"
              hide-details
              density="compact"
            />
          </div>

          <!-- Campo de Escaneo Ultra Compacto -->
          <div v-if="!allowWithoutBarcode" class="mb-2">
            <AppTextField
              id="barcode-input"
              v-model="barcodeInput"
              placeholder="ESCANEAR CÓDIGO..."
              :error-messages="barcodeError"
              variant="outlined"
              density="compact"
              hide-details="auto"
              prepend-inner-icon="tabler-barcode"
              class="rounded-lg font-weight-black text-xs"
              @keyup.enter="handleBarcodeEnter"
            >
              <template #append-inner>
                <VBtn
                  icon="tabler-camera"
                  variant="tonal"
                  color="primary"
                  size="x-small"
                  class="rounded"
                  @click="isScannerVisible = true"
                />
              </template>
            </AppTextField>
          </div>

          <!-- Cantidad Auditada Compacta -->
          <div class="pa-2 rounded-lg bg-white border shadow-xs">
            <div class="d-flex align-center gap-2 mb-1">
              <div class="header-indicator secondary" style="block-size: 10px;" />
              <span class="text-super-xs font-weight-black text-high-emphasis uppercase">Auditado</span>
            </div>

            <div class="bg-light rounded border-dashed-2">
              <AppTextField
                id="quantity-input"
                v-model.number="countedQuantity"
                type="number"
                min="0"
                placeholder="0"
                variant="plain"
                class="audit-huge-input font-weight-black"
                density="compact"
                hide-details
                :disabled="!allowWithoutBarcode && (!barcodeInput.trim() || !!barcodeError)"
                @keyup.enter="handleSave"
              />
            </div>
          </div>
        </VForm>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-2 bg-white border-t">
        <VRow dense class="w-100 ma-0">
          <VCol cols="6" class="pa-1">
            <VBtn
              color="secondary"
              variant="tonal"
              height="38"
              block
              class="font-weight-black rounded-lg text-super-xs uppercase"
              @click="handleCancel"
            >
              Cerrar
            </VBtn>
          </VCol>
          <VCol cols="6" class="pa-1">
            <VBtn
              color="primary"
              variant="flat"
              height="38"
              block
              class="font-weight-black rounded-lg shadow-primary text-super-xs uppercase"
              :disabled="!canSave"
              @click="handleSave"
            >
              Guardar
            </VBtn>
          </VCol>
        </VRow>
      </VCardActions>
    </VCard>

    <BarcodeScannerDialog v-model="isScannerVisible" @scan="onBarcodeScanned" />
  </VDialog>
</template>

<style scoped>
.header-gradient {
  background: linear-gradient(
    135deg,
    rgb(var(--v-theme-primary)) 0%,
    #1e5128 100%
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

.header-indicator.secondary {
  background-color: rgb(var(--v-theme-secondary));
}

.shadow-primary {
  box-shadow: 0 4px 14px 0 rgba(var(--v-theme-primary), 0.39) !important;
}

.shadow-xs {
  box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05) !important;
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
  border: 1px dashed rgba(var(--v-border-color), 0.15) !important;
}

.bg-primary-lighten-5 {
  background-color: rgba(var(--v-theme-primary), 0.05) !important;
}

.bg-warning-lighten-5 {
  background-color: rgba(var(--v-theme-warning), 0.05) !important;
}

.audit-huge-input :deep(input) {
  border: none;
  background: transparent;
  color: rgb(var(--v-theme-primary)) !important;
  font-size: 1.5rem !important;
  font-weight: 900 !important;
  inline-size: 100%;
  line-height: 1;
  outline: none;
  text-align: center !important;
}

@media (min-width: 600px) {
  .audit-huge-input :deep(input) {
    font-size: 3rem !important;
  }
}

.audit-huge-input :deep(.v-field__input) {
  padding: 0 !important;
}

.truncate-1-line {
  display: -webkit-box;
  overflow: hidden;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 1;
  line-clamp: 1;
}

.uppercase {
  text-transform: uppercase;
}
</style>
