<script setup>
import BarcodeScannerDialog from "@/components/dialogs/BarcodeScannerDialog.vue";
import axios from "@/plugins/axios";
import { formatDateSimple, formatNumber } from "@/utils/formatters";
import Swal from "sweetalert2";
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
const lastScanTimestamp = ref(0);

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

const counterUserName = computed(() => {
  const u = props.countRecord?.user;
  if (!u) return "Sistema";
  if (u.employee_name && u.employee_last_name) {
    return `${u.employee_name.trim()} ${u.employee_last_name.trim()}`;
  }
  return u.employee_name || u.name || u.username || u.email || "Operador";
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
      if (quantityInput) {
        quantityInput.focus();
        if (typeof quantityInput.select === "function") quantityInput.select();
      }
    });
  }
});

const handleBarcodeEnter = () => {
  lastScanTimestamp.value = Date.now();
  if (!barcodeError.value && barcodeInput.value.trim()) {
    nextTick(() => {
      const quantityInput = document.querySelector("#recounter-quantity-input");
      if (quantityInput) {
        quantityInput.focus();
        if (typeof quantityInput.select === "function") quantityInput.select();
      }
    });
  }
};

const onBarcodeScanned = (scannedBarcode) => {
  lastScanTimestamp.value = Date.now();
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
          if (quantityInput) {
            quantityInput.focus();
            if (typeof quantityInput.select === "function") quantityInput.select();
          }
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

const handleVerify = async () => {
  if (!canVerify.value) return;

  // Prevenir doble enter accidental por escáner
  if (Date.now() - lastScanTimestamp.value < 450) {
    return;
  }

  const systemStock = Number(currentStock.value ?? props.countRecord?.system_quantity ?? 0);
  const qty = Number(newCountedQuantity.value);
  const isZeroWhenHadStock = systemStock > 0 && qty === 0;

  if (isZeroWhenHadStock) {
    const result = await Swal.fire({
      title: "⚠️ ¡ATENCIÓN: Stock en CERO!",
      html: `
        <div class="text-start py-2" style="font-size: 0.95rem; line-height: 1.5;">
          <p class="mb-2 font-weight-bold" style="color: #dc3545;">
            El sistema registra <strong>${systemStock} unidades</strong> y estás auditando <strong>0 unidades</strong> (Pérdida Total: -${systemStock}).
          </p>
          <div style="background-color: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; padding: 10px;">
            <div><strong>Producto:</strong> ${props.countRecord?.product?.name || ''}</div>
            <div><strong>Stock Sistema:</strong> ${systemStock} und</div>
            <div><strong>Conteo Definitivo:</strong> <span style="color: #dc3545; font-weight: bold;">0 und</span></div>
            <div><strong>Ajuste a Aplicar:</strong> <span style="color: #dc3545; font-weight: bold;">-${systemStock} und (Pérdida Total)</span></div>
          </div>
          <p class="mt-2 mb-0 text-muted" style="font-size: 0.85rem;">
            ¿Confirmas como Supervisor que el producto físicamente NO tiene existencias?
          </p>
        </div>
      `,
      icon: "warning",
      showCancelButton: true,
      confirmButtonText: "Sí, Confirmo que hay CERO (0)",
      cancelButtonText: "Cancelar / Rectificar",
      confirmButtonColor: "#dc3545",
      cancelButtonColor: "rgba(var(--v-theme-secondary), 1)",
      reverseButtons: true,
      focusCancel: true,
    });

    if (!result.isConfirmed) return;
  }

  isProcessing.value = true;

  if (difference.value === 0) {
    emit("verify-no-discrepancy", {
      countRecord: props.countRecord,
      newCountedQuantity: newCountedQuantity.value,
      currentStock: currentStock.value,
    });
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
                class="text-white opacity-75 text-uppercase font-weight-bold"
                style="font-size: 0.65rem; letter-spacing: 0.05em;"
              >
                Validación Física • Auditoría de Stock
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
          <!-- 1. Perfil del Producto Estructurado (Jerarquía Limpia y Neutra) -->
          <VCard
            variant="flat"
            class="pa-4 bg-white rounded-xl border shadow-sm d-flex flex-column gap-2"
          >
            <!-- Pastillas ID (Discreto/Gris) + Laboratorio/Marca -->
            <div class="d-flex align-center justify-space-between gap-2">
              <VChip
                color="secondary"
                variant="tonal"
                size="x-small"
                class="font-weight-bold px-2 rounded-md"
              >
                ID: {{ countRecord.product?.id || countRecord.product_id }}
              </VChip>

              <VChip
                color="secondary"
                variant="tonal"
                size="x-small"
                class="font-weight-bold text-uppercase truncate px-2 rounded-md"
                style="max-inline-size: 200px;"
              >
                {{ countRecord.product?.laboratory?.name || 'S/L' }}
              </VChip>
            </div>

            <!-- Nombre de Producto Dominante (Grande y en Negrita) -->
            <div>
              <h3 class="text-subtitle-1 font-weight-black text-high-emphasis text-uppercase leading-tight mb-1" :title="countRecord.product?.name">
                {{ countRecord.product?.name }}
                <span v-if="countRecord.product?.iva == 1 || countRecord.product?.iva === true" class="text-xs text-disabled font-weight-regular"> (G)</span>
                <span v-if="countRecord.product?.is_colombian_origin == 1 || countRecord.product?.is_colombian_origin === true" class="text-xs text-disabled font-weight-regular"> (COL)</span>
              </h3>

              <!-- Principio Activo Directo Debajo del Título -->
              <div v-if="countRecord.product?.active_ingredient" class="text-xs text-disabled text-uppercase font-weight-medium">
                {{ countRecord.product.active_ingredient }}
              </div>
            </div>

            <!-- Footer Balanceado: Operador a la izquierda, Fecha a la derecha -->
            <div class="pt-2 mt-1 border-t d-flex align-center justify-space-between text-caption">
              <div class="d-flex align-center gap-1 text-medium-emphasis">
                <span class="text-disabled text-uppercase font-weight-medium text-super-xs">Contado por:</span>
                <strong class="text-high-emphasis text-capitalize font-weight-bold text-xs">{{ counterUserName }}</strong>
              </div>
              <span v-if="countRecord.created_at" class="text-medium-emphasis text-xs font-weight-medium">
                {{ formatDateSimple(countRecord.created_at) }}
              </span>
            </div>
          </VCard>

          <!-- 2. Campo de Escaneo de Código de Barras Unificado (Fusionado) -->
          <div class="d-flex flex-column gap-1">
            <!-- Si puede bypass/sin código, mostramos switch discreto -->
            <div
              v-if="canBypassBarcode"
              class="d-flex align-center justify-space-between px-1 mb-1"
            >
              <span class="text-super-xs font-weight-bold text-disabled text-uppercase">
                {{ allowWithoutBarcode ? "Modo ingreso manual activo" : "Escaneo de código de barras" }}
              </span>
              <div class="d-flex align-center gap-1">
                <span class="text-super-xs text-medium-emphasis font-weight-bold">Ingreso manual</span>
                <VSwitch
                  v-model="allowWithoutBarcode"
                  color="primary"
                  hide-details
                  density="compact"
                />
              </div>
            </div>

            <!-- Input Principal de Escaneo -->
            <div v-if="!isManualEntryAllowed">
              <VTextField
                id="verify-barcode-input"
                v-model="barcodeInput"
                placeholder="ESCANEAR O INGRESAR CÓDIGO..."
                :error-messages="barcodeError"
                variant="outlined"
                density="compact"
                hide-details="auto"
                bg-color="white"
                prepend-inner-icon="tabler-scan"
                class="rounded-xl font-weight-black text-xs barcode-field"
                @keyup.enter="handleBarcodeEnter"
              >
                <template #append-inner>
                  <VBtn
                    icon="tabler-camera"
                    variant="tonal"
                    color="primary"
                    size="small"
                    class="rounded-lg me-n1"
                    @click="isScannerVisible = true"
                  >
                    <VIcon icon="tabler-camera" size="18" />
                    <VTooltip activator="parent" location="top">Escanear con cámara</VTooltip>
                  </VBtn>
                </template>
              </VTextField>
            </div>
          </div>

          <!-- 3. Auditoría de Existencias (Etiquetas y Tarjetas Integradas) -->
          <VCard
            variant="flat"
            class="pa-4 bg-white rounded-xl border shadow-sm"
          >
            <div class="d-flex align-center gap-2 mb-3">
              <div class="header-indicator primary shadow-sm" />
              <span class="text-xs font-weight-black text-high-emphasis uppercase letter-spacing-1">Auditoría de Existencias</span>
            </div>

            <div class="d-flex justify-space-around align-center gap-2">
              <!-- Tarjeta Sistema -->
              <div class="d-flex flex-column align-center flex-1">
                <div class="stock-box border bg-grey-50 rounded-xl pa-3 w-100 text-center">
                  <span class="text-super-xs font-weight-black text-disabled text-uppercase d-block mb-1">
                    Sistema
                  </span>
                  <span class="text-h6 font-weight-black text-high-emphasis leading-tight">
                    {{ formatNumber(currentStock) }}
                  </span>
                </div>
              </div>

              <!-- Icono de Comparación -->
              <div class="d-flex justify-center px-1">
                <VIcon
                  icon="tabler-arrow-right"
                  color="secondary"
                  size="20"
                  class="opacity-40"
                />
              </div>

              <!-- Tarjeta Operador -->
              <div class="d-flex flex-column align-center flex-1">
                <div
                  class="stock-box rounded-xl pa-3 w-100 text-center border"
                  :class="countRecord.discrepancy !== 0 ? 'bg-error-light border-error' : 'bg-grey-50'"
                >
                  <span
                    class="text-super-xs font-weight-black text-uppercase d-block mb-1"
                    :class="countRecord.discrepancy !== 0 ? 'text-error' : 'text-disabled'"
                  >
                    Operador
                  </span>
                  <span
                    class="text-h6 font-weight-black leading-tight"
                    :class="countRecord.discrepancy !== 0 ? 'text-error' : 'text-high-emphasis'"
                  >
                    {{ formatNumber((countRecord.system_quantity || 0) + (countRecord.discrepancy || 0)) }}
                  </span>
                </div>
              </div>
            </div>
          </VCard>

          <!-- 4. Conteo Definitivo (Input Evidente y Sólido) -->
          <VCard
            variant="flat"
            class="pa-4 rounded-xl border bg-white shadow-sm"
          >
            <div class="d-flex align-center justify-space-between mb-2">
              <div class="d-flex align-center gap-2">
                <VIcon
                  icon="tabler-edit"
                  size="16"
                  color="primary"
                />
                <span class="text-xs font-weight-black text-high-emphasis uppercase letter-spacing-1">Conteo Definitivo</span>
              </div>
              <span class="text-super-xs text-disabled font-weight-bold uppercase">
                Ingrese unidades físicas
              </span>
            </div>

            <!-- Input Destacado -->
            <div class="solid-input-container mt-1">
              <VTextField
                id="recounter-quantity-input"
                v-model.number="newCountedQuantity"
                type="number"
                min="0"
                placeholder="0"
                variant="outlined"
                bg-color="white"
                class="conteo-definitivo-input font-weight-black"
                density="comfortable"
                hide-details
                autofocus
                :disabled="!isBarcodeValid"
                @keyup.enter="handleVerify"
                @focus="$event.target.select()"
              />
            </div>

            <VExpandTransition>
              <div
                v-if="difference !== null"
                class="mt-3 pt-2 border-t d-flex flex-column align-center gap-1 animate__animated animate__fadeIn"
              >
                <div
                  class="d-flex align-center gap-2 px-3 py-1 rounded-pill"
                  :style="{ backgroundColor: `rgba(var(--v-theme-${differenceColor}), 0.15)` }"
                >
                  <VIcon
                    :icon="differenceIcon"
                    size="16"
                    :color="differenceColor"
                  />
                  <span
                    class="text-xs font-weight-black uppercase"
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
      <VCardActions class="pa-3 bg-white border-t px-4">
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
              variant="outlined"
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
                size="18"
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

.stock-box {
  transition: all 0.2s ease;
}

.bg-grey-50 {
  background-color: #f8fafc !important;
}

.bg-error-light {
  background-color: rgba(var(--v-theme-error), 0.08) !important;
}

.border-error {
  border-color: rgba(var(--v-theme-error), 0.3) !important;
}

.flex-1 {
  flex: 1 1 0;
}

.conteo-definitivo-input :deep(input) {
  font-size: 1.75rem !important;
  font-weight: 900 !important;
  text-align: center !important;
  color: rgb(var(--v-theme-primary)) !important;
  letter-spacing: 1px;
}

.conteo-definitivo-input :deep(input::placeholder) {
  color: rgba(var(--v-theme-on-surface), 0.35) !important;
  font-weight: 700;
}

.conteo-definitivo-input :deep(.v-field) {
  border-radius: 12px !important;
  background-color: #ffffff !important;
  box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05) !important;
}

.barcode-field :deep(.v-field) {
  background-color: #ffffff !important;
  border-radius: 10px !important;
}
</style>
