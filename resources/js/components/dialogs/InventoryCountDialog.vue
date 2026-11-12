<script setup>
import BarcodeScannerDialog from "@/components/dialogs/BarcodeScannerDialog.vue";
import { useBrandingStore } from "@/stores/useBrandingStore";
import { formatDateSimple } from "@/utils/formatters";
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

// Solo se permite bypass / ingreso manual si la configuración global lo permite (!barcodeRequiredGlobal), o si el producto NO tiene código de barras, si su código es igual a su ID, o si no tiene stock (stock <= 0)
// Detectar si el producto es una receta producida / sub-receta elaborada
const isProducedRecipe = computed(() => {
  if (!props.product) return false;
  const p = props.product;
  if (p.is_sub_recipe === true || p.is_sub_recipe === 1 || p.is_sub_recipe === '1') return true;
  if (p.is_recipe === true || p.is_recipe === 1) return true;
  if (p.is_dish === true || p.is_dish === 1) return true;
  const barcode = String(p.barcode || '').toUpperCase();
  if (barcode.startsWith('SUB-')) return true;
  const activeIng = String(p.active_ingredient || '').toLowerCase();
  if (activeIng.includes('sub-receta') || activeIng.includes('subreceta') || activeIng.includes('insumo cocina') || activeIng.includes('receta producida')) {
    return true;
  }
  return false;
});

// Etiqueta legible de la unidad para recetas producidas (porciones, gramos, etc.)
const recipeUnitLabel = computed(() => {
  const u = (props.product?.unit_of_measure || '').toLowerCase().trim();
  if (u === 'gr' || u === 'g' || u === 'grm' || u === 'gramos') return 'gramos (g)';
  if (u === 'porción' || u === 'porcion' || u === 'porciones') return 'porciones';
  if (u === 'ml' || u === 'mililitros') return 'mililitros (ml)';
  if (u === 'lt' || u === 'l' || u === 'litros') return 'litros (L)';
  if (u === 'kg' || u === 'kilos' || u === 'kilogramos') return 'kilogramos (kg)';
  return props.product?.unit_of_measure || 'und';
});

// Solo se permite bypass / ingreso manual si la configuración global lo permite (!barcodeRequiredGlobal), o si el producto es receta producida, NO tiene código de barras, su código es igual a su ID, o si no tiene stock (stock <= 0)
const canBypassBarcode = computed(() => {
  if (!barcodeRequiredGlobal.value) return true;
  if (isProducedRecipe.value) return true;
  const bc = props.product?.barcode ? String(props.product.barcode).trim() : '';
  const id = props.product?.id ? String(props.product.id).trim() : '';
  const stock = Number(props.product?.stock ?? props.product?.system_quantity ?? props.product?.current_stock ?? 0);
  return !bc || bc === id || stock <= 0;
});

const isManualEntryAllowed = computed(() => {
  if (!barcodeRequiredGlobal.value) return true;
  if (isProducedRecipe.value) return true;
  return canBypassBarcode.value && allowWithoutBarcode.value;
});

// Contenido por envase del producto (presentation) y unidad de medida
const productPresentation = computed(() => Number(props.product?.presentation) || 0);
const productUnit = computed(() => props.product?.unit_of_measure || 'und');

// Modo dual solo cuando trazabilidad es por consumo Y el producto tiene presentación configurada
// Modo dual solo cuando trazabilidad es por consumo Y el producto tiene presentación configurada Y NO es una receta producida
const isDualCountMode = computed(() => {
  if (isProducedRecipe.value) return false;
  return isConsumptionMode.value && productPresentation.value > 0;
});

// Total calculado en modo restaurante (en unidad de consumo: g o ml)
const dualTotalQuantity = computed(() => {
  const pkgs = Number(packagesCount.value) || 0;
  const opened = Number(openedQuantity.value) || 0;
  return pkgs * productPresentation.value + opened;
});

// Total calculado en unidades de stock del sistema (paquetes / envases)
const dualSystemQuantity = computed(() => {
  if (productPresentation.value <= 0) return dualTotalQuantity.value;
  return parseFloat(((Number(packagesCount.value) || 0) + ((Number(openedQuantity.value) || 0) / productPresentation.value)).toFixed(4));
});

// Modo Farmacia: Desglose por lotes activos y ubicación
const isPharmacyLotsMode = computed(() => {
  if (isDualCountMode.value || isProducedRecipe.value) return false;
  const isRestaurant = Boolean(
    brandingStore.isRestaurant || brandingStore.settings?.enable_dishes
  );
  if (isRestaurant) return false;
  const lots = props.product?.lots;
  // Solo activar modo lotes si hay al menos un lote con stock mayor a cero
  return Array.isArray(lots) && lots.some(l => Number(l.quantity || 0) > 0);
});

const countedLots = ref([]);

const lotsTotalCounted = computed(() => {
  if (!isPharmacyLotsMode.value) return 0;
  return countedLots.value.reduce((sum, item) => {
    const val = Number(item.counted_quantity);
    return sum + (isNaN(val) || item.counted_quantity === "" ? 0 : val);
  }, 0);
});

const hasAnyLotCounted = computed(() => {
  if (!isPharmacyLotsMode.value) return false;
  return countedLots.value.some(
    (item) => item.counted_quantity !== "" && !isNaN(Number(item.counted_quantity))
  );
});

watch(
  countedLots,
  () => {
    if (isPharmacyLotsMode.value) {
      if (hasAnyLotCounted.value) {
        countedQuantity.value = lotsTotalCounted.value;
      } else {
        countedQuantity.value = "";
      }
    }
  },
  { deep: true }
);

const handleLotEnter = (index) => {
  const nextIndex = index + 1;
  const nextInput = document.querySelector(`#lot-input-${nextIndex}`);
  if (nextInput) {
    nextInput.focus();
    if (typeof nextInput.select === "function") nextInput.select();
  } else {
    handleSave();
  }
};

const canSave = computed(() => {
  const allowManual = isManualEntryAllowed.value;

  if (isDualCountMode.value) {
    const hasValidDual = dualTotalQuantity.value >= 0 &&
      (packagesCount.value !== "" || openedQuantity.value !== "");
    if (allowManual) return hasValidDual;
    return barcodeInput.value.trim() !== "" && hasValidDual && !barcodeError.value;
  }

  if (isPharmacyLotsMode.value) {
    const hasLotsFilled =
      hasAnyLotCounted.value &&
      countedQuantity.value !== "" &&
      Number(countedQuantity.value) >= 0;
    if (allowManual) return hasLotsFilled;
    return barcodeInput.value.trim() !== "" && hasLotsFilled && !barcodeError.value;
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
  countedLots.value = [];
  barcodeError.value = "";
  isScannerVisible.value = false;
  allowWithoutBarcode.value = isProducedRecipe.value || (canBypassBarcode.value && !barcodeRequiredGlobal.value);
};

const handleCancel = () => {
  isVisible.value = false;
};

watch(
  () => props.modelValue,
  (newVal) => {
    if (newVal) {
      allowWithoutBarcode.value = isProducedRecipe.value || (canBypassBarcode.value && !barcodeRequiredGlobal.value);
      if (isPharmacyLotsMode.value) {
        // Solo mostrar lotes que tienen unidades en sistema (excluir agotados)
        countedLots.value = (props.product?.lots || []).filter(lot => Number(lot.quantity || 0) > 0).map((lot) => ({
          id: lot.id,
          lot_number: lot.lot_number || "S/L",
          expiration_date: lot.expiration_date || null,
          location: lot.location || props.product?.location || "",
          system_quantity: Number(lot.quantity || 0),
          counted_quantity: "",
        }));
      } else {
        countedLots.value = [];
      }
      nextTick(() => {
        if (isManualEntryAllowed.value) {
          if (isPharmacyLotsMode.value) {
            const firstLotInput = document.querySelector("#lot-input-0");
            if (firstLotInput) {
              firstLotInput.focus();
              if (typeof firstLotInput.select === "function") firstLotInput.select();
            }
          } else {
            const quantityInput = document.querySelector("#quantity-input");
            if (quantityInput) quantityInput.focus();
          }
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

  // En modo restaurante el total se calcula del modo dual convirtiéndolo a unidades del sistema
  const quantity = isDualCountMode.value
    ? dualSystemQuantity.value
    : Number(countedQuantity.value);

  const rawSystemStock = Number(props.product?.stock ?? props.product?.system_quantity ?? props.product?.stock_calculado ?? 0);
  const systemStock = Math.max(0, rawSystemStock);
  const isZeroWhenHadStock = systemStock > 0 && quantity === 0;
  const displayUnit = isProducedRecipe.value ? recipeUnitLabel.value : "und";

  let result;
  if (isZeroWhenHadStock) {
    result = await Swal.fire({
      title: "⚠️ ¡ATENCIÓN: Stock en CERO!",
      html: `
        <div class="text-start py-2" style="font-size: 0.95rem; line-height: 1.5;">
          <p class="mb-2 font-weight-bold" style="color: #dc3545;">
            Estás a punto de reportar <strong>0 ${displayUnit}</strong> para un producto que presenta existencias registradas.
          </p>
          <div style="background-color: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; padding: 10px;">
            <div><strong>Producto:</strong> ${props.product?.name || ''}</div>
            <div><strong>Tu Conteo Físico:</strong> <span style="color: #dc3545; font-weight: bold;">0 ${displayUnit}</span></div>
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
    result = await Swal.fire({
      title: "Confirmar Conteo Físico",
      html: `
        <div class="text-start py-2" style="font-size: 0.95rem; line-height: 1.5;">
          <div style="background-color: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; padding: 10px; margin-bottom: 8px;">
            <div style="font-weight: bold; margin-bottom: 4px;">${props.product?.name || ''}</div>
            <div style="display: flex; justify-content: space-between; border-top: 1px dashed #ccc; padding-top: 4px; margin-top: 4px;">
              <span>Tu Conteo Físico:</span>
              <strong style="color: #0d6efd;">${quantity} ${displayUnit}</strong>
            </div>
          </div>
          ${isDualCountMode.value ? `<p class="text-caption text-muted mb-0">${Number(packagesCount.value) || 0} paquete(s) + ${Number(openedQuantity.value) || 0} ${productUnit.value} abierto(s) (${dualTotalQuantity.value} ${productUnit.value} en total = ${quantity} und)</p>` : ''}
          ${isPharmacyLotsMode.value ? `<div class="mt-2 text-super-xs text-start text-muted border-t pt-1">${countedLots.value.filter(l => l.counted_quantity !== '').map(l => `<div>• Lote ${l.lot_number} (${l.location || 'Sin ubic.'}): <strong>${Number(l.counted_quantity) || 0} und</strong></div>`).join('')}</div>` : ''}
        </div>
      `,
      icon: "question",
      showCancelButton: true,
      confirmButtonText: "Confirmar Conteo",
      cancelButtonText: "Cancelar",
      confirmButtonColor: "rgba(var(--v-theme-primary), 1)",
      cancelButtonColor: "rgba(var(--v-theme-secondary), 1)",
      reverseButtons: true,
    });
  }

  if (!result || !result.isConfirmed) return;

  const isWithoutBarcode = !barcodeRequiredGlobal.value || allowWithoutBarcode.value || isProducedRecipe.value;

  const countData = {
    barcode: isWithoutBarcode ? null : barcodeInput.value.trim(),
    countedQuantity: quantity,
    allowWithoutBarcode: isWithoutBarcode,
    isDualCount: isDualCountMode.value,
    lots_breakdown: isPharmacyLotsMode.value
      ? countedLots.value.map((l) => ({
          lot_id: l.id,
          lot_number: l.lot_number,
          quantity: Number(l.counted_quantity) || 0,
        }))
      : null,
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
                <span class="text-xs font-weight-black text-high-emphasis uppercase letter-spacing-1">
                  {{ isProducedRecipe ? `Conteo de Receta (${recipeUnitLabel})` : 'Conteo Físico' }}
                </span>
              </div>
              <span class="text-super-xs text-disabled font-weight-bold uppercase">
                {{ isProducedRecipe ? `Ingrese cantidad en ${recipeUnitLabel}` : 'Ingrese unidades físicas' }}
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
                    {{ dualTotalQuantity }} {{ productUnit }} ({{ dualSystemQuantity }} und)
                  </VChip>
                </div>
              </div>
            </template>

            <!-- Modo Farmacia: Desglose por Lotes y Ubicación -->
            <template v-else-if="isPharmacyLotsMode">
              <div class="d-flex flex-column gap-2 mt-1">
                <div class="text-caption text-medium-emphasis mb-1">
                  Ingrese las unidades físicas encontradas para cada lote en anaquel:
                </div>

                <div
                  v-for="(lotItem, index) in countedLots"
                  :key="lotItem.id"
                  class="bg-grey-50 rounded-xl border pa-3"
                >
                  <div class="d-flex align-center justify-space-between mb-1">
                    <div class="d-flex align-center gap-1">
                      <VIcon icon="tabler-barcode" size="16" color="primary" />
                      <span class="text-xs font-weight-black text-primary uppercase">
                        Lote: {{ lotItem.lot_number }}
                      </span>
                    </div>
                    <VChip
                      v-if="lotItem.location"
                      size="x-small"
                      variant="tonal"
                      color="secondary"
                      prepend-icon="tabler-map-pin"
                      class="font-weight-bold"
                    >
                      {{ lotItem.location }}
                    </VChip>
                  </div>

                  <div class="d-flex align-center justify-space-between mb-2 text-super-xs text-disabled">
                    <span v-if="lotItem.expiration_date">
                      Vence: {{ formatDateSimple(lotItem.expiration_date) }}
                    </span>
                    <span v-else>Sin vencimiento registrado</span>
                    <span>Sistema: {{ lotItem.system_quantity }} und</span>
                  </div>

                  <VTextField
                    :id="`lot-input-${index}`"
                    v-model.number="lotItem.counted_quantity"
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
                    @keyup.enter="handleLotEnter(index)"
                    @focus="$event.target.select()"
                  />
                </div>

                <!-- Total Sumado de Lotes -->
                <div
                  v-if="hasAnyLotCounted"
                  class="d-flex align-center justify-space-between rounded-xl pa-3 bg-primary-lighten-5 border mt-1"
                >
                  <span class="text-xs font-weight-black text-primary uppercase">Total Físico (Suma de Lotes)</span>
                  <VChip size="small" color="primary" variant="flat" class="font-weight-black">
                    {{ lotsTotalCounted }} {{ productUnit }}
                  </VChip>
                </div>
              </div>
            </template>

            <!-- Modo Normal / Receta Producida: Un solo campo -->
            <template v-else>
              <div class="solid-input-container mt-1">
                <!-- Indicador visual exclusivo de Receta Producida -->
                <div
                  v-if="isProducedRecipe"
                  class="d-flex align-center justify-space-between mb-2 pa-2 rounded-lg bg-primary-lighten-5 border"
                >
                  <div class="d-flex align-center gap-1">
                    <VIcon icon="tabler-tools-kitchen-2" size="16" color="primary" />
                    <span class="text-xs font-weight-black text-primary uppercase">
                      Receta Producida / Preparación
                    </span>
                  </div>
                  <VChip size="x-small" color="primary" variant="flat" class="font-weight-bold uppercase">
                    Conteo en: {{ recipeUnitLabel }}
                  </VChip>
                </div>

                <VTextField
                  id="quantity-input"
                  v-model.number="countedQuantity"
                  type="number"
                  min="0"
                  step="any"
                  :placeholder="isProducedRecipe ? ('0 ' + productUnit) : '0'"
                  :label="isProducedRecipe ? ('Cantidad contada en ' + recipeUnitLabel) : 'Cantidad Contada'"
                  :suffix="productUnit"
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
              variant="outlined"
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
