<script setup>
import BarcodeScannerDialog from "@/components/dialogs/BarcodeScannerDialog.vue";
import axios from "@/plugins/axios";
import { formatDateSimple, formatNumber } from "@/utils/formatters";
import { computed, nextTick, ref, watch } from "vue";

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  countRecord: { type: Object, default: null },
});

const emit = defineEmits([
  "update:modelValue",
  "verify-no-discrepancy",
  "verify-with-discrepancy",
]);

const isVisible = computed({
  get: () => props.modelValue,
  set: (value) => emit("update:modelValue", value),
});

const isLoading = ref(false);
const isProcessing = ref(false);
const currentStock = ref(null);
const newCountedQuantity = ref(null);
const loadError = ref(null);

// Control de código de barras
const barcodeInput = ref("");
const barcodeError = ref("");
const isScannerVisible = ref(false);
const allowWithoutBarcode = ref(false);

// Solo se permite bypass / ingreso manual si el producto no tiene código, su código es igual a su ID, o si no tiene stock (stock <= 0)
const canBypassBarcode = computed(() => {
  const p = props.countRecord?.product;
  const bc = p?.barcode ? String(p.barcode).trim() : "";
  const id = p?.id ? String(p.id).trim() : (props.countRecord?.product_id ? String(props.countRecord.product_id).trim() : "");
  const stock = Number(currentStock.value ?? props.countRecord?.system_quantity ?? p?.stock ?? 0);
  return !bc || bc === id || stock <= 0;
});

const isManualEntryAllowed = computed(() => {
  return canBypassBarcode.value && allowWithoutBarcode.value;
});

const isBarcodeValid = computed(() => {
  if (isManualEntryAllowed.value) return true;
  const expectedBc = props.countRecord?.product?.barcode ? String(props.countRecord.product.barcode).trim() : "";
  if (!expectedBc && canBypassBarcode.value) return true;
  return barcodeInput.value.trim() !== "" && (!expectedBc || barcodeInput.value.trim() === expectedBc);
});

watch(barcodeInput, (newBarcode) => {
  if (!newBarcode.trim()) {
    barcodeError.value = "";
    return;
  }
  if (isManualEntryAllowed.value) {
    barcodeError.value = "";
    return;
  }
  const expectedBc = props.countRecord?.product?.barcode ? String(props.countRecord.product.barcode).trim() : "";
  if (expectedBc && newBarcode.trim() !== expectedBc) {
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
      const quantityInput = document.querySelector("#recounter-quantity-input");
      if (quantityInput) quantityInput.focus();
    });
  }
});

const handleBarcodeEnter = () => {
  if (!barcodeError.value && barcodeInput.value.trim()) {
    nextTick(() => {
      const quantityInput = document.querySelector("#recounter-quantity-input");
      if (quantityInput) quantityInput.focus();
    });
  }
};

const onBarcodeScanned = (scannedBarcode) => {
  barcodeInput.value = scannedBarcode;
  isScannerVisible.value = false;
  handleBarcodeEnter();
};

watch(
  () => props.modelValue,
  async (isOpening) => {
    if (isOpening && props.countRecord) {
      newCountedQuantity.value = null;
      loadError.value = null;
      currentStock.value = null;
      barcodeInput.value = "";
      barcodeError.value = "";
      isScannerVisible.value = false;
      allowWithoutBarcode.value = canBypassBarcode.value;
      await loadCurrentStock();
      nextTick(() => {
        if (!isManualEntryAllowed.value) {
          const barcodeElement = document.querySelector("#verify-barcode-input");
          if (barcodeElement) barcodeElement.focus();
        } else {
          const quantityInput = document.querySelector("#recounter-quantity-input");
          if (quantityInput) quantityInput.focus();
        }
      });
    }
  },
);

const loadCurrentStock = async () => {
  if (!props.countRecord?.product_id) return;
  isLoading.value = true;
  try {
    const response = await axios.get(
      `/products/${props.countRecord.product_id}/stock`,
    );
    currentStock.value = response.data.stock ?? 0;
  } catch (e) {
    loadError.value = "No se pudo cargar el stock actual.";
    currentStock.value = 0;
  } finally {
    isLoading.value = false;
  }
};

const difference = computed(() => {
  if (newCountedQuantity.value === null || currentStock.value === null)
    return null;
  return newCountedQuantity.value - currentStock.value;
});

const differenceColor = computed(() => {
  if (difference.value === null) return "secondary";
  if (difference.value === 0) return "success";
  return difference.value > 0 ? "info" : "error";
});

const differenceIcon = computed(() => {
  if (difference.value === null) return "tabler-minus";
  if (difference.value === 0) return "tabler-shield-check";
  return difference.value > 0 ? "tabler-trending-up" : "tabler-trending-down";
});

const differenceText = computed(() => {
  if (difference.value === null) return "";
  if (difference.value === 0) return "Stock Correcto";
  const absDiff = Math.abs(difference.value);
  return difference.value > 0
    ? `Sobran ${absDiff} unidades`
    : `Faltan ${absDiff} unidades`;
});

const canVerify = computed(() => {
  return (
    isBarcodeValid.value &&
    newCountedQuantity.value !== null &&
    newCountedQuantity.value >= 0 &&
    !isProcessing.value &&
    !isLoading.value
  );
});

const handleVerify = () => {
  if (!canVerify.value) return;
  isProcessing.value = true;

  if (difference.value === 0) {
    emit("verify-no-discrepancy", { countRecord: props.countRecord });
  } else {
    emit("verify-with-discrepancy", {
      countRecord: props.countRecord,
      newCountedQuantity: newCountedQuantity.value,
      currentStock: currentStock.value,
    });
  }

  isProcessing.value = false;
  isVisible.value = false;
};

const handleClose = () => {
  isVisible.value = false;
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
    <VCard class="detail-dialog-card rounded-xl border-0 shadow-xl overflow-hidden bg-surface">
      <!-- Cabecera Premium -->
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-4 d-flex align-center shadow-sm">
          <VAvatar
            color="white"
            variant="flat"
            size="40"
            class="me-3 elevation-1"
          >
            <VIcon
              icon="tabler-clipboard-check"
              size="24"
              color="primary"
            />
          </VAvatar>
          <div class="d-flex flex-column leading-none">
            <h2 class="text-h6 font-weight-black text-white leading-tight mb-0">
              Verificar Conteo
            </h2>
            <div class="d-flex align-center gap-2 mt-1">
              <span
                class="text-white opacity-75 uppercase font-weight-bold"
                style="font-size: 0.6rem; letter-spacing: 0.05em;"
              >
                Validación Física de Inventario • Barrio Sucre
              </span>
            </div>
          </div>
          <VSpacer />
          <VBtn
            icon="tabler-x"
            variant="tonal"
            color="white"
            size="small"
            class="rounded-lg"
            @click="handleClose"
            :disabled="isProcessing"
          />
        </div>
      </VCardTitle>
      
      <VCardText class="pa-3 pa-sm-4 bg-light d-flex flex-column gap-3">
        <!-- Loader Cargando -->
        <div
          v-if="!countRecord || isLoading"
          class="d-flex flex-column align-center justify-center py-6"
        >
          <VProgressCircular
            indeterminate
            color="primary"
            size="32"
            width="3"
          />
          <p class="mt-3 text-super-xs font-weight-black uppercase text-disabled letter-spacing-1">
            Sincronizando Stock Actual...
          </p>
        </div>

        <template v-else>
          <!-- Perfil del Producto Premium -->
          <VCard
            variant="flat"
            class="pa-3 bg-white rounded-xl border shadow-sm"
          >
            <div class="d-flex align-center justify-space-between mb-2">
              <VChip
                size="x-small"
                color="primary"
                variant="flat"
                class="font-weight-black px-2 rounded-lg"
              >
                ID: {{ countRecord.product?.id || countRecord.product_id }}
              </VChip>
              <div class="d-flex align-center gap-1 text-disabled leading-none">
                <VIcon
                  icon="tabler-user-check"
                  size="12"
                />
                <span class="text-super-xs font-weight-black uppercase truncate" style="max-inline-size: 150px;">
                   {{ countRecord.user?.username || "Sistema" }}
                </span>
              </div>
            </div>
            <h3 class="text-subtitle-1 font-weight-black text-high-emphasis leading-tight uppercase mb-1">
              {{ countRecord.product?.name }}
            </h3>
            <div class="d-flex align-center gap-2">
              <VIcon
                icon="tabler-barcode"
                size="12"
                color="disabled"
              />
              <p class="text-super-xs text-disabled font-weight-bold mb-0 uppercase letter-spacing-05 truncate">
                {{ countRecord.product?.active_ingredient || "Sin principio activo" }}
              </p>
            </div>
          </VCard>

          <!-- Modo de Ingreso / Escaneo de Código de Barras -->
          <div
            v-if="canBypassBarcode"
            class="pa-2 rounded-lg border shadow-xs d-flex align-center justify-space-between"
            :class="allowWithoutBarcode ? 'bg-warning-lighten-5' : 'bg-primary-lighten-5'"
          >
            <div class="d-flex align-center gap-2">
              <VIcon
                :icon="allowWithoutBarcode ? 'tabler-keyboard' : 'tabler-scan'"
                :color="allowWithoutBarcode ? 'warning' : 'primary'"
                size="18"
              />
              <span class="text-super-xs font-weight-black uppercase">
                {{ allowWithoutBarcode ? "Ingreso Manual (Sin Código)" : "Modo Escaneo" }}
              </span>
            </div>
            <VSwitch
              v-model="allowWithoutBarcode"
              :color="allowWithoutBarcode ? 'warning' : 'primary'"
              hide-details
              density="compact"
            />
          </div>

          <div
            v-else
            class="pa-2 rounded-lg border shadow-xs d-flex align-center gap-2 bg-primary-lighten-5"
          >
            <VIcon icon="tabler-scan" color="primary" size="18" />
            <span class="text-super-xs font-weight-black uppercase text-primary">
              Modo Escaneo Obligatorio
            </span>
          </div>

          <!-- Campo de Escaneo de Código de Barras -->
          <div v-if="!isManualEntryAllowed" class="mb-1">
            <VTextField
              id="verify-barcode-input"
              v-model="barcodeInput"
              placeholder="ESCANEAR CÓDIGO DE BARRAS..."
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
            </VTextField>
          </div>

          <!-- Comparativa de Stock Premium -->
          <VCard
            variant="flat"
            class="pa-3 pa-sm-4 bg-white rounded-xl border shadow-sm"
          >
            <div class="d-flex align-center gap-2 mb-3">
              <div class="header-indicator primary shadow-sm" />
              <span class="text-xs font-weight-black text-high-emphasis uppercase letter-spacing-1">Auditoría de Existencias</span>
            </div>

            <div class="d-flex justify-space-around align-center">
              <div class="text-center">
                <span class="text-super-xs font-weight-black text-disabled d-block uppercase mb-1">Sistema</span>
                <VAvatar
                  color="grey-lighten-4"
                  size="52"
                  class="rounded-xl border mb-1"
                >
                  <span class="text-subtitle-1 font-weight-black text-high-emphasis">{{ formatNumber(currentStock) }}</span>
                </VAvatar>
              </div>

              <div class="d-flex flex-column align-center">
                <VIcon
                  icon="tabler-transfer-in"
                  color="primary"
                  size="20"
                  class="opacity-40"
                />
              </div>

              <div class="text-center">
                <span class="text-super-xs font-weight-black text-disabled d-block uppercase mb-1">Operador</span>
                <VAvatar
                  color="warning"
                  variant="tonal"
                  size="52"
                  class="rounded-xl border-warning border-opacity-25 mb-1"
                >
                  <span class="text-subtitle-1 font-weight-black text-warning">{{ formatNumber((countRecord.system_quantity || 0) + (countRecord.discrepancy || 0)) }}</span>
                </VAvatar>
              </div>
            </div>
          </VCard>

          <!-- Sección de Re-conteo Premium -->
          <VCard
            variant="flat"
            class="pa-3 pa-sm-4 rounded-xl border-dashed-2 bg-white text-center shadow-sm"
          >
            <div class="d-flex align-center justify-center gap-2 mb-2">
              <VIcon
                icon="tabler-edit"
                size="14"
                color="primary"
              />
              <span class="text-super-xs font-weight-black text-primary uppercase letter-spacing-1">Conteo Definitivo</span>
            </div>

            <div class="d-flex justify-center align-center">
              <VTextField
                id="recounter-quantity-input"
                v-model.number="newCountedQuantity"
                type="number"
                min="0"
                placeholder="0"
                variant="plain"
                class="ultra-huge-input-text h-auto font-weight-950"
                density="compact"
                hide-details
                autofocus
                :disabled="!isBarcodeValid"
                @keyup.enter="handleVerify"
              />
            </div>

            <VExpandTransition>
              <div
                v-if="difference !== null"
                class="mt-2 pt-2 border-t border-dashed d-flex flex-column align-center gap-1 animate__animated animate__fadeIn"
              >
                <div
                  class="d-flex align-center gap-2 px-3 py-1 rounded-pill"
                  :style="{ backgroundColor: `rgba(var(--v-theme-${differenceColor}), 0.15)` }"
                >
                  <VIcon
                    :icon="differenceIcon"
                    size="14"
                    :color="differenceColor"
                  />
                  <span
                    class="text-super-xs font-weight-black uppercase"
                    :class="`text-${differenceColor}`"
                  >
                    {{ differenceText }}
                  </span>
                </div>
              </div>
            </VExpandTransition>
          </VCard>

          <VAlert
            v-if="loadError"
            type="error"
            variant="flat"
            density="compact"
            icon="tabler-alert-triangle"
            class="rounded-lg font-weight-black text-super-xs shadow-sm mt-1"
          >
            {{ loadError }}
          </VAlert>
        </template>
      </VCardText>

      <VDivider />

      <!-- Acciones de Modal -->
      <VCardActions class="pa-2 bg-white border-t px-4">
        <VRow
          dense
          class="w-100 ma-0"
        >
          <VCol
            cols="6"
            class="pa-1"
          >
            <VBtn
              color="secondary"
              variant="tonal"
              height="44"
              block
              class="font-weight-black rounded-lg text-button uppercase"
              @click="handleClose"
              :disabled="isProcessing"
            >
              Cancelar
            </VBtn>
          </VCol>
          <VCol
            cols="6"
            class="pa-1"
          >
            <VBtn
              :color="difference === 0 ? 'success' : 'primary'"
              variant="flat"
              height="44"
              block
              class="font-weight-black rounded-lg shadow-primary text-button uppercase"
              :disabled="!canVerify"
              :loading="isProcessing"
              @click="handleVerify"
            >
              <VIcon
                start
                :icon="difference === 0 ? 'tabler-circle-check' : 'tabler-adjustments-alt'"
                size="16"
              />
              {{ difference === 0 ? "Aceptar" : "Ajustar" }}
            </VBtn>
          </VCol>
        </VRow>
      </VCardActions>
    </VCard>

    <!-- Dialogo de Cámara para Escaneo -->
    <BarcodeScannerDialog
      v-model="isScannerVisible"
      @scanned="onBarcodeScanned"
    />
  </VDialog>
</template>

<style scoped>
.header-gradient {
  background: linear-gradient(
    135deg,
    rgb(var(--v-theme-gradient-end)) 0%,
    rgb(var(--v-theme-primary)) 100%
  );
}

.detail-dialog-card {
  border-radius: 12px !important;
}

.header-indicator {
  inline-size: 4px;
  block-size: 14px;
  border-radius: 10px;
}

.header-indicator.primary {
  background-color: rgb(var(--v-theme-primary));
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

.border-dashed {
  border: 1px dashed rgba(var(--v-border-color), 0.2) !important;
}

.border-dashed-2 {
  border: 1px dashed rgba(var(--v-border-color), 0.3) !important;
}

.ultra-huge-input-text :deep(input) {
  border: none;
  background: transparent;
  block-size: auto;
  color: rgb(var(--v-theme-primary)) !important;
  font-size: 1.8rem !important;
  font-weight: 950 !important;
  inline-size: 100%;
  line-height: 1;
  outline: none;
  text-align: center !important;
}

@media (min-width: 600px) {
  .ultra-huge-input-text :deep(input) {
    font-size: 2.2rem !important;
  }
}

.ultra-huge-input-text :deep(.v-field__input) {
  padding: 0 !important;
}

.italic {
  font-style: italic;
}
</style>
