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

// Modo restaurante: se muestra el conteo dual (paquetes + destapado)
const isRestaurantMode = computed(
  () => (brandingStore.settings.business_type === 'restaurant' || brandingStore.settings.business_type === 'minimarket')
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

// Variables para el modo de conteo dual (restaurante)
const packagesCount = ref("");
const openedQuantity = ref("");

// Contenido por envase del producto (presentation) y unidad de medida
const productPresentation = computed(() => Number(props.product?.presentation) || 0);
const productUnit = computed(() => props.product?.unit_of_measure || 'und');

// Determina si se debe usar el modo de conteo dual (paquetes + unidades abiertas)
const isDualCountMode = computed(() => isRestaurantMode.value && productPresentation.value > 0);

// Total calculado en modo restaurante
const dualTotalQuantity = computed(() => {
  const pkgs = Number(packagesCount.value) || 0;
  const opened = Number(openedQuantity.value) || 0;
  return pkgs * productPresentation.value + opened;
});

const canSave = computed(() => {
  // En modo restaurante/minimarket se valida el modo dual solo si el producto tiene presentación
  if (isDualCountMode.value) {
    const hasValidDual = dualTotalQuantity.value >= 0 &&
      (packagesCount.value !== "" || openedQuantity.value !== "");
    if (allowWithoutBarcode.value) return hasValidDual;
    return barcodeInput.value.trim() !== "" && hasValidDual && !barcodeError.value;
  }

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
  packagesCount.value = "";
  openedQuantity.value = "";
  barcodeError.value = "";
  isScannerVisible.value = false;
  // Si la configuración global no requiere barcode, el modo manual es el predeterminado
  allowWithoutBarcode.value = !barcodeRequiredGlobal.value;
};

const handleCancel = () => {
  isVisible.value = false;
};

watch(
  () => props.modelValue,
  (newVal) => {
    if (newVal) {
      // Sincronizar el modo al abrir según la configuración global
      allowWithoutBarcode.value = !barcodeRequiredGlobal.value;
      nextTick(() => {
        // Si no se requiere barcode, enfocar directamente el campo de cantidad
        if (!barcodeRequiredGlobal.value) {
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

  // En modo restaurante el total se calcula del modo dual si el producto tiene presentación
  const quantity = isDualCountMode.value
    ? dualTotalQuantity.value
    : Number(countedQuantity.value);

  const confirmText = isDualCountMode.value
    ? `${Number(packagesCount.value) || 0} paquete(s) completo(s) + ${Number(openedQuantity.value) || 0} ${productUnit.value} abierto(s) = ${quantity} ${productUnit.value} en total`
    : `Confirma que está contando la cantidad de ${quantity} ${quantity === 1 ? "unidad" : "unidades"}`;

  const result = await Swal.fire({
    title: "Confirmar Conteo",
    text: confirmText,
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
        <div class="header-gradient pa-4 d-flex align-center">
          <div class="d-flex align-center">
            <VAvatar color="white" variant="flat" size="40" class="me-3 elevation-2">
              <VIcon icon="tabler-clipboard-check" color="primary" size="24" />
            </VAvatar>
            <div>
              <h2 class="text-h6 font-weight-black leading-tight mb-0" style="color: white !important;">
                Conteo de Inventario
              </h2>
              <span class="text-caption opacity-75" style="color: white !important;">
                Validación Física • Auditoría de Stock
              </span>
            </div>
          </div>
          <VSpacer />
          <VBtn icon variant="tonal" color="white" size="small" @click="handleCancel">
            <VIcon>tabler-x</VIcon>
          </VBtn>
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
          <!-- Modo de Ingreso Compacto (solo visible si el escaneo es requerido por configuración) -->
          <div
            v-if="barcodeRequiredGlobal"
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

          <!-- Campo de Escaneo Ultra Compacto (solo si la config global lo requiere y el usuario no eligió ingreso manual) -->
          <div v-if="barcodeRequiredGlobal && !allowWithoutBarcode" class="mb-2">
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

          <!-- Cantidad Auditada -->
          <div class="pa-2 rounded-lg bg-white border shadow-xs">
            <div class="d-flex align-center gap-2 mb-1">
              <div class="header-indicator secondary" style="block-size: 10px;" />
              <span class="text-super-xs font-weight-black text-high-emphasis uppercase">Auditado</span>
            </div>

            <!-- Modo Dual (Restaurante): Paquetes Completos + Contenido Destapado -->
            <template v-if="isDualCountMode">
              <div class="d-flex flex-column gap-2">
                <!-- Paquetes completos sin destapar -->
                <div class="bg-light rounded border-dashed-2 pa-1">
                  <div class="d-flex align-center gap-1 mb-1 px-1">
                    <VIcon icon="tabler-package" size="14" color="primary" />
                    <span class="text-super-xs font-weight-black text-primary uppercase">
                      Paquetes completos ({{ productPresentation }} {{ productUnit }} c/u)
                    </span>
                  </div>
                  <AppTextField
                    id="packages-input"
                    v-model.number="packagesCount"
                    type="number"
                    min="0"
                    step="1"
                    placeholder="0"
                    variant="plain"
                    class="audit-huge-input font-weight-black"
                    density="compact"
                    hide-details
                    :disabled="barcodeRequiredGlobal && !allowWithoutBarcode && (!barcodeInput.trim() || !!barcodeError)"
                    @keyup.enter="$el.querySelector('#opened-input')?.focus()"
                  />
                </div>

                <!-- Contenido ya destapado -->
                <div class="bg-light rounded border-dashed-2 pa-1">
                  <div class="d-flex align-center gap-1 mb-1 px-1">
                    <VIcon icon="tabler-box-seam" size="14" color="warning" />
                    <span class="text-super-xs font-weight-black text-warning uppercase">
                      Contenido destapado / parcial ({{ productUnit }})
                    </span>
                  </div>
                  <AppTextField
                    id="opened-input"
                    v-model.number="openedQuantity"
                    type="number"
                    min="0"
                    step="any"
                    placeholder="0"
                    variant="plain"
                    class="audit-huge-input font-weight-black"
                    density="compact"
                    hide-details
                    :disabled="barcodeRequiredGlobal && !allowWithoutBarcode && (!barcodeInput.trim() || !!barcodeError)"
                    @keyup.enter="handleSave"
                  />
                </div>

                <!-- Total calculado -->
                <div v-if="packagesCount !== '' || openedQuantity !== ''"
                  class="d-flex align-center justify-space-between rounded pa-2 bg-primary-lighten-5 border"
                >
                  <span class="text-super-xs font-weight-black text-primary uppercase">Total calculado</span>
                  <VChip size="small" color="primary" variant="flat" class="font-weight-black">
                    {{ dualTotalQuantity }} {{ productUnit }}
                  </VChip>
                </div>
              </div>
            </template>

            <!-- Modo Normal: Un solo campo -->
            <template v-else>
              <div class="bg-light rounded border-dashed-2">
                <AppTextField
                  id="quantity-input"
                  v-model.number="countedQuantity"
                  type="number"
                  min="0"
                  step="any"
                  placeholder="0"
                  variant="plain"
                  class="audit-huge-input font-weight-black"
                  density="compact"
                  hide-details
                  :disabled="barcodeRequiredGlobal && !allowWithoutBarcode && (!barcodeInput.trim() || !!barcodeError)"
                  @keyup.enter="handleSave"
                />
              </div>
            </template>
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
/* El fondo del header es dinámico via CSS vars del branding store */
.header-gradient {
  background: var(--brand-gradient) !important;
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
