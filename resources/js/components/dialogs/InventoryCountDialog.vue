<script setup>
import BarcodeScannerDialog from "@/components/dialogs/BarcodeScannerDialog.vue";
import { useBrandingStore } from "@/stores/useBrandingStore";
import Swal from "sweetalert2";
import { computed, nextTick, ref, watch } from "vue";

// Degradado dinámico igual que el login: secondary (inicio) → primary (fin)
const brandingStore = useBrandingStore();
const headerGradient = computed(() => {
  const start = brandingStore.settings?.secondary_color || '#7A0099';
  const end   = brandingStore.settings?.primary_color   || '#E20074';
  return `linear-gradient(135deg, ${start} 0%, ${end} 100%)`;
});

// Si la configuración global no requiere código de barras, el modo manual es el predeterminado
const barcodeRequiredGlobal = computed(
  () => brandingStore.settings?.cyclic_inventory_barcode_required ?? true
);

// Modo consumo: se muestra el conteo dual (paquetes completos + contenido parcial)
// Se activa cuando la configuración de trazabilidad es 'consumption' (no depende del tipo de negocio)
const isConsumptionMode = computed(
  () => brandingStore.settings?.traceability_mode === 'consumption'
);

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

// Variables para el modo de conteo dual (consumo)
const packagesCount = ref("");
const openedQuantity = ref("");

// Solo se permite bypass / ingreso manual si el producto NO tiene código de barras, si su código es igual a su ID, o si no tiene stock (stock <= 0)
const canBypassBarcode = computed(() => {
  const bc = props.product?.barcode ? String(props.product.barcode).trim() : '';
  const id = props.product?.id ? String(props.product.id).trim() : '';
  const stock = Number(props.product?.stock ?? props.product?.system_quantity ?? props.product?.current_stock ?? 0);
  return !bc || bc === id || stock <= 0;
});

const isManualEntryAllowed = computed(() => {
  return canBypassBarcode.value && (allowWithoutBarcode.value || !barcodeRequiredGlobal.value);
});

// Contenido por envase del producto (presentation) y unidad de medida
const productPresentation = computed(() => Number(props.product?.presentation) || 0);
const productUnit = computed(() => props.product?.unit_of_measure || 'und');

// Modo dual solo cuando trazabilidad es por consumo Y el producto tiene presentación configurada
const isDualCountMode = computed(() => isConsumptionMode.value && productPresentation.value > 0);

// Total calculado en modo restaurante
const dualTotalQuantity = computed(() => {
  const pkgs = Number(packagesCount.value) || 0;
  const opened = Number(openedQuantity.value) || 0;
  return pkgs * productPresentation.value + opened;
});

const canSave = computed(() => {
  const allowManual = isManualEntryAllowed.value;

  if (isDualCountMode.value) {
    const hasValidDual = dualTotalQuantity.value >= 0 &&
      (packagesCount.value !== "" || openedQuantity.value !== "");
    if (allowManual) return hasValidDual;
    return barcodeInput.value.trim() !== "" && hasValidDual && !barcodeError.value;
  }

  const isQuantityValid =
    countedQuantity.value !== "" &&
    !isNaN(Number(countedQuantity.value)) &&
    Number(countedQuantity.value) >= 0;

  if (allowManual) {
    return isQuantityValid;
  }

  return (
    barcodeInput.value.trim() !== "" && isQuantityValid && !barcodeError.value
  );
});

const resetForm = () => {
  barcodeInput.value = "";
  countedQuantity.value = "";
  packagesCount.value = "";
  openedQuantity.value = "";
  barcodeError.value = "";
  isScannerVisible.value = false;
  allowWithoutBarcode.value = canBypassBarcode.value && !barcodeRequiredGlobal.value;
};

const handleCancel = () => {
  isVisible.value = false;
};

watch(
  () => props.modelValue,
  (newVal) => {
    if (newVal) {
      allowWithoutBarcode.value = canBypassBarcode.value && !barcodeRequiredGlobal.value;
      nextTick(() => {
        if (isManualEntryAllowed.value) {
          const quantityInput = document.querySelector("#quantity-input");
          if (quantityInput) quantityInput.focus();
        } else {
          const barcodeInputElement = document.querySelector("#barcode-input");
          if (barcodeInputElement) barcodeInputElement.focus();
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

const lastScanTimestamp = ref(0);

const handleBarcodeEnter = () => {
  lastScanTimestamp.value = Date.now();
  if (!barcodeError.value && barcodeInput.value.trim()) {
    nextTick(() => {
      const quantityInput = document.querySelector("#quantity-input") || document.querySelector("#packages-input");
      if (quantityInput) {
        quantityInput.focus();
        if (typeof quantityInput.select === "function") quantityInput.select();
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
  lastScanTimestamp.value = Date.now();
  barcodeInput.value = scannedBarcode;
  isScannerVisible.value = false;
  handleBarcodeEnter();
};

const handleSave = async () => {
  if (!canSave.value) return;

  // Prevenir que una ráfaga o doble Enter del lector de código de barras dispare el guardado accidentalmente
  if (Date.now() - lastScanTimestamp.value < 450) {
    return;
  }

  // En modo restaurante el total se calcula del modo dual si el producto tiene presentación
  const quantity = isDualCountMode.value
    ? dualTotalQuantity.value
    : Number(countedQuantity.value);

  const systemStock = Number(props.product?.stock ?? props.product?.system_quantity ?? props.product?.stock_calculado ?? 0);
  const difference = quantity - systemStock;
  const isZeroWhenHadStock = systemStock > 0 && quantity === 0;

  let result;
  if (isZeroWhenHadStock) {
    result = await Swal.fire({
      title: "⚠️ ¡ATENCIÓN: Stock en CERO!",
      html: `
        <div class="text-start py-2" style="font-size: 0.95rem; line-height: 1.5;">
          <p class="mb-2 font-weight-bold" style="color: #dc3545;">
            El sistema registra <strong>${systemStock} unidades</strong> y estás a punto de reportar <strong>0 unidades</strong> (Pérdida Total: -${systemStock}).
          </p>
          <div style="background-color: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; padding: 10px;">
            <div><strong>Producto:</strong> ${props.product?.name || ''}</div>
            <div><strong>Stock en Sistema:</strong> ${systemStock} und</div>
            <div><strong>Tu Conteo Físico:</strong> <span style="color: #dc3545; font-weight: bold;">0 und</span></div>
            <div><strong>Diferencia:</strong> <span style="color: #dc3545; font-weight: bold;">-${systemStock} und (Faltante Total)</span></div>
          </div>
          <p class="mt-2 mb-0 text-muted" style="font-size: 0.85rem;">
            ¿Confirmas conscientemente que NO hay existencias físicas de este producto?
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
  } else {
    const diffColor = difference > 0 ? "#0d6efd" : (difference < 0 ? "#dc3545" : "#198754");
    const diffLabel = difference > 0 ? `+${difference} (Sobrante)` : (difference < 0 ? `${difference} (Faltante)` : "0 (Exacto)");

    result = await Swal.fire({
      title: difference === 0 ? "Confirmar Conteo Exacto" : "Confirmar Conteo con Discrepancia",
      html: `
        <div class="text-start py-2" style="font-size: 0.95rem; line-height: 1.5;">
          <div style="background-color: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; padding: 10px; margin-bottom: 8px;">
            <div style="font-weight: bold; margin-bottom: 4px;">${props.product?.name || ''}</div>
            <div style="display: flex; justify-content: space-between;">
              <span>Stock en Sistema:</span>
              <strong>${systemStock} und</strong>
            </div>
            <div style="display: flex; justify-content: space-between;">
              <span>Tu Conteo Físico:</span>
              <strong style="color: ${diffColor};">${quantity} und</strong>
            </div>
            <div style="display: flex; justify-content: space-between; border-top: 1px dashed #ccc; padding-top: 4px; margin-top: 4px;">
              <span>Diferencia:</span>
              <strong style="color: ${diffColor};">${diffLabel}</strong>
            </div>
          </div>
          ${isDualCountMode.value ? `<p class="text-caption text-muted mb-0">${Number(packagesCount.value) || 0} paquete(s) + ${Number(openedQuantity.value) || 0} ${productUnit.value} abierto(s)</p>` : ''}
        </div>
      `,
      icon: difference === 0 ? "question" : "warning",
      showCancelButton: true,
      confirmButtonText: "Confirmar Conteo",
      cancelButtonText: "Cancelar",
      confirmButtonColor: difference === 0 ? "rgba(var(--v-theme-primary), 1)" : "#f59e0b",
      cancelButtonColor: "rgba(var(--v-theme-secondary), 1)",
      reverseButtons: true,
    });
  }

  if (!result || !result.isConfirmed) return;

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
            <VIcon
              icon="tabler-clipboard-check"
              size="24"
              color="primary"
            />
          </VAvatar>
          <div class="d-flex flex-column leading-none">
            <h2 class="text-h6 font-weight-black text-white leading-tight mb-0">
              Conteo de Inventario
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
            @click="handleCancel"
          />
        </div>
      </VCardTitle>

      <VCardText class="pa-3 pa-sm-4 bg-light d-flex flex-column gap-3">
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
              ID: {{ product.id }}
            </VChip>

            <VChip
              color="secondary"
              variant="tonal"
              size="x-small"
              class="font-weight-bold text-uppercase truncate px-2 rounded-md"
              style="max-inline-size: 200px;"
            >
              {{ product.laboratory?.name || 'S/L' }}
            </VChip>
          </div>

          <!-- Nombre de Producto Dominante (Grande y en Negrita) -->
          <div>
            <h3 class="text-subtitle-1 font-weight-black text-high-emphasis text-uppercase leading-tight mb-1" :title="product.name">
              {{ product.name }}
              <span v-if="product.iva == 1 || product.iva === true" class="text-xs text-disabled font-weight-regular"> (G)</span>
              <span v-if="product.is_colombian_origin == 1 || product.is_colombian_origin === true" class="text-xs text-disabled font-weight-regular"> (COL)</span>
            </h3>

            <!-- Principio Activo Directo Debajo del Título -->
            <div v-if="product.active_ingredient" class="text-xs text-disabled text-uppercase font-weight-medium">
              {{ product.active_ingredient }}
            </div>
          </div>
        </VCard>

        <VForm @submit.prevent="handleSave">
          <!-- 2. Campo de Escaneo de Código de Barras Unificado (Fusionado) -->
          <div class="d-flex flex-column gap-1 mb-3">
            <!-- Si puede bypass/sin código, mostramos switch discreto -->
            <div
              v-if="canBypassBarcode && barcodeRequiredGlobal"
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
                id="barcode-input"
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

          <!-- 3. Conteo Físico / Definitivo (Input Evidente y Sólido) -->
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
                <span class="text-xs font-weight-black text-high-emphasis uppercase letter-spacing-1">Conteo Físico</span>
              </div>
              <span class="text-super-xs text-disabled font-weight-bold uppercase">
                Ingrese unidades físicas
              </span>
            </div>

            <!-- Modo Dual (Restaurante): Paquetes Completos + Contenido Destapado -->
            <template v-if="isDualCountMode">
              <div class="d-flex flex-column gap-2 mt-1">
                <!-- Paquetes completos sin destapar -->
                <div class="bg-grey-50 rounded-xl border pa-3">
                  <div class="d-flex align-center gap-1 mb-1">
                    <VIcon icon="tabler-package" size="16" color="primary" />
                    <span class="text-xs font-weight-black text-primary uppercase">
                      Paquetes completos ({{ productPresentation }} {{ productUnit }} c/u)
                    </span>
                  </div>
                  <VTextField
                    id="packages-input"
                    v-model.number="packagesCount"
                    type="number"
                    min="0"
                    step="1"
                    placeholder="0"
                    variant="outlined"
                    bg-color="white"
                    class="conteo-definitivo-input font-weight-black"
                    density="comfortable"
                    hide-details
                    :disabled="barcodeRequiredGlobal && !allowWithoutBarcode && (!barcodeInput.trim() || !!barcodeError)"
                    @keyup.enter="$el.querySelector('#opened-input')?.focus()"
                  />
                </div>

                <!-- Contenido ya destapado -->
                <div class="bg-grey-50 rounded-xl border pa-3">
                  <div class="d-flex align-center gap-1 mb-1">
                    <VIcon icon="tabler-box-seam" size="16" color="warning" />
                    <span class="text-xs font-weight-black text-warning uppercase">
                      Contenido destapado / parcial ({{ productUnit }})
                    </span>
                  </div>
                  <VTextField
                    id="opened-input"
                    v-model.number="openedQuantity"
                    type="number"
                    min="0"
                    step="any"
                    placeholder="0"
                    variant="outlined"
                    bg-color="white"
                    class="conteo-definitivo-input font-weight-black"
                    density="comfortable"
                    hide-details
                    :disabled="barcodeRequiredGlobal && !allowWithoutBarcode && (!barcodeInput.trim() || !!barcodeError)"
                    @keyup.enter="handleSave"
                  />
                </div>

                <!-- Total calculado -->
                <div v-if="packagesCount !== '' || openedQuantity !== ''"
                  class="d-flex align-center justify-space-between rounded-xl pa-3 bg-primary-lighten-5 border mt-1"
                >
                  <span class="text-xs font-weight-black text-primary uppercase">Total calculado</span>
                  <VChip size="small" color="primary" variant="flat" class="font-weight-black">
                    {{ dualTotalQuantity }} {{ productUnit }}
                  </VChip>
                </div>
              </div>
            </template>

            <!-- Modo Normal: Un solo campo -->
            <template v-else>
              <div class="solid-input-container mt-1">
                <VTextField
                  id="quantity-input"
                  v-model.number="countedQuantity"
                  type="number"
                  min="0"
                  step="any"
                  placeholder="0"
                  variant="outlined"
                  bg-color="white"
                  class="conteo-definitivo-input font-weight-black"
                  density="comfortable"
                  hide-details
                  :disabled="barcodeRequiredGlobal && !allowWithoutBarcode && (!barcodeInput.trim() || !!barcodeError)"
                  @keyup.enter="handleSave"
                  @focus="$event.target.select()"
                />
              </div>
            </template>
          </VCard>
        </VForm>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-3 bg-white border-t px-4">
        <VRow dense class="w-100 ma-0">
          <VCol cols="6" class="pa-1">
            <VBtn
              color="secondary"
              variant="tonal"
              height="44"
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
              height="44"
              block
              class="font-weight-black rounded-lg shadow-primary text-button uppercase"
              :disabled="!canSave"
              @click="handleSave"
            >
              <VIcon start icon="tabler-clipboard-check" size="18" />
              Guardar
            </VBtn>
          </VCol>
        </VRow>
      </VCardActions>
    </VCard>

    <BarcodeScannerDialog v-model="isScannerVisible" @scanned="onBarcodeScanned" />
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

.bg-grey-50 {
  background-color: #f8fafc !important;
}

.bg-primary-lighten-5 {
  background-color: rgba(var(--v-theme-primary), 0.05) !important;
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
