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

      <VCardText class="pa-4 pa-sm-6 bg-light d-flex flex-column gap-6">
        <!-- Perfil del Producto Premium -->
        <VCard
          variant="flat"
          class="pa-5 bg-white rounded-xl border shadow-sm"
        >
          <div class="d-flex flex-column">
            <div class="d-flex align-center justify-space-between mb-4">
              <div class="d-flex align-center gap-2">
                <VChip
                  size="small"
                  color="primary"
                  variant="flat"
                  class="font-weight-black uppercase px-3 shadow-sm rounded-lg"
                >
                  ID: {{ product.id }}
                </VChip>
                <VChip
                  size="small"
                  color="secondary"
                  variant="tonal"
                  class="font-weight-black uppercase px-3 rounded-lg"
                >
                  {{ product.category?.name || "General" }}
                </VChip>
              </div>
              <div class="header-indicator primary shadow-sm" />
            </div>

            <h3 class="text-h6 font-weight-black text-high-emphasis leading-tight uppercase mb-4">
              {{ product.name }}
            </h3>

            <VRow dense>
              <VCol cols="12" sm="6">
                <div class="d-flex align-center gap-3 pa-3 bg-light rounded-lg border-dashed-2">
                  <VAvatar size="32" color="white" class="shadow-sm border">
                    <VIcon icon="tabler-building-factory" size="16" color="primary" />
                  </VAvatar>
                  <div class="d-flex flex-column">
                    <span class="text-super-xs font-weight-black text-disabled uppercase mb-1">Laboratorio</span>
                    <span class="text-xs font-weight-black text-high-emphasis uppercase">
                      {{ product.laboratory?.name || "SIN REGISTRO" }}
                    </span>
                  </div>
                </div>
              </VCol>

              <VCol cols="12" sm="6">
                <div class="d-flex align-center gap-3 pa-3 bg-light rounded-lg border-dashed-2">
                  <VAvatar size="32" color="white" class="shadow-sm border">
                    <VIcon icon="tabler-flask" size="16" color="primary" />
                  </VAvatar>
                  <div class="d-flex flex-column">
                    <span class="text-super-xs font-weight-black text-disabled uppercase mb-1">Principio Activo</span>
                    <span class="text-xs font-weight-black text-high-emphasis uppercase truncate-1-line">
                      {{ product.active_ingredient || "SIN REGISTRO" }}
                    </span>
                  </div>
                </div>
              </VCol>
            </VRow>
          </div>
        </VCard>

        <VForm @submit.prevent="handleSave">
          <!-- Banner de Selección de Modo Premium -->
          <VCard
            variant="flat"
            :color="allowWithoutBarcode ? 'warning' : 'primary'"
            class="pa-4 mb-6 rounded-xl border shadow-sm transition-all overflow-hidden"
            :class="allowWithoutBarcode ? 'bg-warning-lighten-5' : 'bg-primary-lighten-5'"
          >
            <div
              class="position-absolute"
              style="right: -10px; top: -10px; opacity: 0.1;"
            >
              <VIcon
                :icon="allowWithoutBarcode ? 'tabler-barcode-off' : 'tabler-barcode'"
                size="80"
              />
            </div>

            <VCheckbox
              v-model="allowWithoutBarcode"
              :color="allowWithoutBarcode ? 'warning' : 'primary'"
              hide-details
              density="comfortable"
              class="ma-0 pa-0"
            >
              <template #label>
                <div class="d-flex align-center gap-4">
                  <VAvatar
                    :color="allowWithoutBarcode ? 'warning' : 'primary'"
                    size="44"
                    variant="flat"
                    class="shadow-sm border border-white"
                  >
                    <VIcon
                      :icon="allowWithoutBarcode ? 'tabler-keyboard' : 'tabler-scan'"
                      size="24"
                      color="white"
                    />
                  </VAvatar>
                  <div class="d-flex flex-column leading-none">
                    <span class="text-subtitle-1 font-weight-black uppercase d-block letter-spacing-1">
                      {{ allowWithoutBarcode ? "CONTEO MANUAL" : "MODO ESCANEO" }}
                    </span>
                    <span class="text-super-xs font-weight-bold opacity-75 uppercase mt-1">
                      {{ allowWithoutBarcode ? "Ingreso directo por teclado" : "Validación obligatoria por scanner" }}
                    </span>
                  </div>
                </div>
              </template>
            </VCheckbox>
          </VCard>

          <!-- Campo de Escaneo Premium -->
          <div v-if="!allowWithoutBarcode" class="mb-6">
            <span class="text-super-xs font-weight-black text-disabled uppercase mb-2 d-block ms-1">Validación de Código de Barras</span>
            <AppTextField
              id="barcode-input"
              v-model="barcodeInput"
              placeholder="APUNTA EL LECTOR AQUÍ..."
              :error-messages="barcodeError"
              variant="outlined"
              density="comfortable"
              prepend-inner-icon="tabler-barcode"
              class="rounded-lg font-weight-black shadow-sm"
              @keyup.enter="handleBarcodeEnter"
            >
              <template #append-inner>
                <VBtn
                  icon="tabler-camera"
                  variant="tonal"
                  color="primary"
                  size="small"
                  class="rounded-lg"
                  @click="isScannerVisible = true"
                />
              </template>
            </AppTextField>
          </div>

          <!-- Input de Cantidad Auditada -->
          <VCard
            variant="flat"
            class="pa-5 rounded-xl bg-white border shadow-sm"
          >
            <div class="d-flex align-center justify-space-between mb-4">
              <div class="d-flex align-center gap-2">
                <div class="header-indicator secondary shadow-sm" />
                <span class="text-subtitle-2 font-weight-black text-high-emphasis uppercase letter-spacing-1">Cantidad Auditada</span>
              </div>
              <VChip
                size="small"
                color="secondary"
                variant="flat"
                class="font-weight-black px-3 rounded-lg shadow-sm"
              >
                UNIDADES FÍSICAS
              </VChip>
            </div>

            <div class="pa-4 bg-light rounded-xl border-dashed-2 d-flex justify-center align-center">
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
          </VCard>
        </VForm>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4 pa-sm-6 bg-white border-t">
        <VRow dense class="w-100 ma-0">
          <VCol cols="6" class="pa-1">
            <VBtn
              color="secondary"
              variant="tonal"
              height="50"
              block
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
              height="50"
              block
              class="font-weight-black rounded-lg shadow-primary text-button uppercase"
              :disabled="!canSave"
              @click="handleSave"
            >
              <VIcon start icon="tabler-check" size="18" />
              Guardar Conteo
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

.bg-light {
  background-color: #f8faff !important;
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
  font-size: 3.5rem !important;
  font-weight: 900 !important;
  inline-size: 100%;
  line-height: 1;
  outline: none;
  text-align: center !important;
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

.text-button {
  font-size: 0.875rem !important;
  letter-spacing: 1px !important;
}

.uppercase {
  text-transform: uppercase;
}
</style>
