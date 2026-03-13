<script setup>
import BarcodeScannerDialog from "@/components/dialogs/BarcodeScannerDialog.vue";
import { toast } from "@/plugins/sweetalert";
import { formatNumber } from "@/utils/formatters";
import { computed, ref, watch } from "vue";

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  productName: { type: String, default: "" },
  productPhoto: { type: String, default: "" }, // Nueva prop para el avatar
  lots: { type: Array, default: () => [] },
  targetQuantity: { type: Number, required: true },
  mode: { type: String, default: "adjustment", validator: (v) => ["return", "adjustment"].includes(v) },
  locations: { type: Array, default: () => [] },
});

const emit = defineEmits(["update:modelValue", "save"]);

const distributedLots = ref([]);
const originalLots = ref([]);
const isScannerVisible = ref(false);
const activeScannerLot = ref(null);

const isVisible = computed({
  get: () => props.modelValue,
  set: (value) => emit("update:modelValue", value),
});

let tempIdCounter = 0;

const formatDateForInput = (dateString) => {
  if (!dateString) return "";
  try {
    const date = new Date(dateString);
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, "0");
    const day = String(date.getDate()).padStart(2, "0");
    return `${year}-${month}-${day}`;
  } catch (error) {
    return "";
  }
};

watch(
  () => props.modelValue,
  (isOpening) => {
    if (isOpening) {
      const lotsArray = Array.isArray(props.lots) ? props.lots : [];
      distributedLots.value = lotsArray.map(lot => ({
        ...lot,
        location: lot.location || "",
        lot_number: lot.lot_number || "",
        expiration_date: lot.expiration_date ? formatDateForInput(lot.expiration_date) : "",
      }));
      originalLots.value = JSON.parse(JSON.stringify(lotsArray));
      tempIdCounter = 0;
      lotErrors.value = {};
    }
  },
  { immediate: true }
);

const currentLotsStockSum = computed(() => {
  return originalLots.value.reduce((sum, lot) => sum + (Number(lot.quantity) || 0), 0);
});

const objective = computed(() => {
  if (props.mode === "adjustment") {
    return Number(props.targetQuantity) || 0;
  }
  return currentLotsStockSum.value + (Number(props.targetQuantity) || 0);
});

const totalDistributed = computed(() => {
  if (props.mode === "adjustment") {
    return distributedLots.value.reduce((sum, lot) => {
      return sum + (Number(lot.quantity) || 0);
    }, 0);
  }
  return distributedLots.value.reduce((sum, lot) => {
    const qty = Number(lot.quantity) || 0;
    if (lot.isNew) return sum + qty;
    const orig = originalLots.value.find((l) => l.id === lot.id);
    const origQty = Number(orig?.quantity) || 0;
    return sum + Math.max(0, qty - origQty);
  }, 0);
});

const discrepancy = computed(() => {
  if (props.mode === "adjustment") {
    return objective.value - totalDistributed.value;
  }
  return props.targetQuantity - totalDistributed.value;
});

const lotErrors = ref({});

const hasValidationErrors = computed(() => {
  const errors = {};
  let hasErrors = false;

  for (let i = 0; i < distributedLots.value.length; i++) {
    const lot = distributedLots.value[i];
    const lotQuantity = Number(lot.quantity) || 0;
    const currentLotErrors = {};

    if (lotQuantity === 0) continue;

    if (!lot.lot_number || lot.lot_number.trim() === "") {
      currentLotErrors.lot_number = "Requerido";
      hasErrors = true;
    }

    if (!lot.expiration_date || lot.expiration_date.trim() === "") {
      currentLotErrors.expiration_date = "Requerido";
      hasErrors = true;
    }

    if (!lot.location || lot.location.trim() === "") {
      currentLotErrors.location = "Requerido";
      hasErrors = true;
    }

    if (Object.keys(currentLotErrors).length > 0) {
      errors[lot.isNew ? lot.temp_id : lot.id] = currentLotErrors;
    }
  }

  lotErrors.value = errors;
  return hasErrors;
});

const canSave = computed(() => {
  return discrepancy.value === 0 && !hasValidationErrors.value;
});

const handleAddNewLot = () => {
  distributedLots.value.push({
    temp_id: `new_${tempIdCounter++}`,
    isNew: true,
    lot_number: "",
    expiration_date: "",
    location: "",
    quantity: 0,
  });
};

const handleRemoveNewLot = (tempId) => {
  distributedLots.value = distributedLots.value.filter(lot => lot.temp_id !== tempId);
};

const handleClearLotQuantity = (lot) => {
  lot.quantity = 0;
};

const handleSave = () => {
  if (!canSave.value) {
    toast.error("Complete los campos obligatorios y asegúrese de que la diferencia sea 0.");
    return;
  }

  const updatedLots = [];
  const newLots = [];

  for (const lot of distributedLots.value) {
    const lotQuantity = Number(lot.quantity) || 0;
    
    if (lot.isNew) {
      if (lotQuantity > 0) {
        newLots.push({
          lot_number: lot.lot_number,
          expiration_date: lot.expiration_date,
          location: lot.location || "",
          quantity: lotQuantity,
        });
      }
    } else {
      const originalLot = originalLots.value.find(ol => ol.id === lot.id);
      
      if (lotQuantity === 0) {
        if (originalLot && Number(originalLot.quantity) !== 0) {
          updatedLots.push({
            id: lot.id,
            quantity: 0,
            lot_number: originalLot.lot_number || "",
            expiration_date: originalLot.expiration_date || "",
            location: originalLot.location || "",
          });
        }
      } else {
        const hasChanges = 
          (originalLot && Number(originalLot.quantity) !== lotQuantity) ||
          (originalLot && originalLot.lot_number !== lot.lot_number) ||
          (originalLot && originalLot.expiration_date !== lot.expiration_date) ||
          (originalLot && (originalLot.location || "") !== (lot.location || ""));

        if (hasChanges) {
          updatedLots.push({
            id: lot.id,
            quantity: lotQuantity,
            lot_number: lot.lot_number,
            expiration_date: lot.expiration_date,
            location: lot.location || "",
          });
        }
      }
    }
  }

  emit("save", { updatedLots, newLots });
  closeDialog();
};

const closeDialog = () => {
  emit("update:modelValue", false);
};

const openScanner = (lot) => {
  activeScannerLot.value = lot;
  isScannerVisible.value = true;
};

const handleScan = (code) => {
  if (activeScannerLot.value) {
    activeScannerLot.value.lot_number = code;
  }
  isScannerVisible.value = false;
};
</script>

<template>
  <VDialog
    v-model="isVisible"
    max-width="1200"
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
              <VIcon icon="tabler-package" color="primary" size="18" />
            </VAvatar>
            <div>
              <h2 class="text-subtitle-2 font-weight-black text-white leading-tight mb-0">Ajustar Stock por Lotes</h2>
              <span class="text-super-xs text-white opacity-75 uppercase font-weight-bold" style="font-size: 0.6rem;">
                Distribución física
              </span>
            </div>
          </div>
          <VSpacer />
          <VBtn icon variant="tonal" color="white" size="x-small" @click="closeDialog">
            <VIcon size="18">tabler-x</VIcon>
          </VBtn>
        </div>
      </VCardTitle>

      <VCardText class="pa-2 pa-sm-3 bg-light d-flex flex-column gap-2">
        <!-- Perfil del Producto Premium -->
        <VCard variant="flat" class="border pa-4 bg-white rounded-lg elevation-1 transition-all mb-3 border-l-primary">
          <div class="d-flex align-center justify-space-between gap-3">
            <div class="d-flex align-center gap-3 min-width-0">
              <VAvatar size="48" variant="tonal" color="primary" rounded="lg" class="border shadow-sm">
                <VIcon icon="tabler-pill" size="24" />
              </VAvatar>
              <div class="min-width-0">
                <div class="d-flex align-center gap-2 mb-1">
                  <VChip size="x-small" color="primary" variant="flat" class="font-weight-black uppercase px-2 shadow-sm">
                    {{ props.mode === 'adjustment' ? 'MODO CÍCLICO' : 'MODO RETORNO' }}
                  </VChip>
                  <div class="header-indicator primary"></div>
                </div>
                <h3 class="text-h6 font-weight-black text-high-emphasis leading-tight uppercase mb-0 truncate">
                  {{ props.productName }}
                </h3>
              </div>
            </div>
            <VBtn
              color="success"
              variant="elevated"
              size="large"
              class="rounded-lg shadow-primary animate-pulse d-none d-sm-flex"
              @click="handleAddNewLot"
            >
              <VIcon icon="tabler-plus" class="me-2" />
              NUEVO LOTE
            </VBtn>
            <VBtn
              color="success"
              icon
              variant="elevated"
              size="small"
              class="rounded-lg shadow-primary animate-pulse d-flex d-sm-none"
              @click="handleAddNewLot"
            >
              <VIcon icon="tabler-plus" size="20" />
            </VBtn>
          </div>
        </VCard>        <!-- Resumen de Stock Premium Compacto -->
        <VRow dense class="mb-2 flex-shrink-0">
          <VCol cols="4">
            <VCard variant="flat" border class="pa-2 bg-white text-center rounded-lg elevation-1 border-l-primary">
              <span class="text-super-xs font-weight-black text-disabled d-block uppercase mb-1">OBJETIVO</span>
              <div class="text-h6 font-weight-950 text-primary leading-none">
                {{ formatNumber(objective) }}
              </div>
            </VCard>
          </VCol>
          
          <VCol cols="4">
            <VCard variant="flat" border class="pa-2 bg-white text-center rounded-lg elevation-1">
              <span class="text-super-xs font-weight-black text-disabled d-block uppercase mb-1">ASIGNADO</span>
              <div class="text-h6 font-weight-950 text-info leading-none">
                {{ formatNumber(totalDistributed) }}
              </div>
            </VCard>
          </VCol>

          <VCol cols="4">
            <VCard variant="flat" border class="pa-2 bg-white text-center rounded-lg elevation-1">
              <span class="text-super-xs font-weight-black text-disabled d-block uppercase mb-1">DIFERENCIA</span>
              <div 
                class="text-h6 font-weight-950 leading-none"
                :class="discrepancy === 0 ? 'text-success' : 'text-error'"
              >
                {{ discrepancy > 0 ? '+' : '' }}{{ formatNumber(discrepancy) }}
              </div>
            </VCard>
          </VCol>
        </VRow>

        <!-- Cabecera de Lista Premium -->
        <div class="d-flex align-center justify-space-between mb-3 px-1">
          <div class="d-flex align-center gap-2">
            <div class="header-indicator success shadow-sm"></div>
            <span class="text-subtitle-2 font-weight-black text-high-emphasis uppercase letter-spacing-1">Distribución de Lotes</span>
          </div>
          <VChip size="x-small" color="success" variant="tonal" class="font-weight-black px-2">
            {{ distributedLots.length }} REGISTROS
          </VChip>
        </div>

        <!-- Tabla Escritorio con Scroll -->
        <div class="d-none d-md-block flex-grow-1 overflow-y-auto pr-1" style="min-height: 0;">
          <VDataTable
            :headers="[
              { title: 'Información del Lote', key: 'info', sortable: false },
              { title: 'Stock Sistema', key: 'original_stock', sortable: false, align: 'center', width: '150px' },
              { title: 'Nueva Cantidad', key: 'quantity', sortable: false, align: 'center', width: '180px' },
              { title: '', key: 'actions', sortable: false, align: 'center', width: '80px' },
            ]"
            :items="distributedLots"
            :item-value="item => item.isNew ? item.temp_id : item.id"
            density="compact"
            class="premium-table border rounded-lg overflow-hidden bg-white elevation-1 sticky-header"
          >
            <template #item.info="{ item }">
              <VRow dense class="py-1">
                <VCol cols="4">
                  <AppTextField
                    v-model="item.lot_number"
                    label="Lote"
                    density="comfortable"
                    placeholder="Nº Lote"
                    class="premium-textfield shadow-sm"
                    :error-messages="lotErrors[item.isNew ? item.temp_id : item.id]?.lot_number"
                  />
                </VCol>
                <VCol cols="4">
                  <AppTextField
                    v-model="item.expiration_date"
                    label="Vencimiento"
                    density="comfortable"
                    type="date"
                    class="premium-textfield shadow-sm"
                    :error-messages="lotErrors[item.isNew ? item.temp_id : item.id]?.expiration_date"
                  />
                </VCol>
                <VCol cols="4">
                  <VAutocomplete
                    v-model="item.location"
                    label="Ubicación"
                    density="comfortable"
                    placeholder="Seleccionar..."
                    variant="outlined"
                    class="premium-textfield shadow-sm"
                    :items="props.locations"
                    item-title="name"
                    item-value="name"
                    :error-messages="lotErrors[item.isNew ? item.temp_id : item.id]?.location"
                  />
                </VCol>
              </VRow>
            </template>

            <template #item.original_stock="{ item }">
              <VChip v-if="!item.isNew" color="primary" variant="tonal" size="small" class="font-weight-black">
                {{ formatNumber(originalLots.find(l => l.id === item.id)?.quantity || 0) }}
              </VChip>
              <VChip v-else color="success" variant="flat" size="x-small" class="font-weight-black uppercase shadow-sm">
                Nuevo
              </VChip>
            </template>

            <template #item.quantity="{ item }">
              <div class="quantity-input-wrapper border-dashed-2 rounded-lg pa-1 bg-light">
                <AppTextField
                  v-model.number="item.quantity"
                  type="number"
                  min="0"
                  variant="plain"
                  density="compact"
                  class="text-center font-weight-black huge-lot-input-wrapper compact-qty-input"
                  hide-details
                />
              </div>
            </template>

            <template #item.actions="{ item }">
              <IconBtn 
                v-if="item.isNew" 
                color="error" 
                variant="tonal" 
                size="small" 
                @click="handleRemoveNewLot(item.temp_id)"
              >
                <VIcon icon="tabler-trash" />
              </IconBtn>
              <IconBtn 
                v-else 
                color="warning" 
                variant="tonal" 
                size="small" 
                :disabled="item.quantity === 0"
                @click="handleClearLotQuantity(item)"
              >
                <VIcon icon="tabler-trash-x" />
              </IconBtn>
            </template>
          </VDataTable>
        </div>

        <!-- Vista Móvil de Tarjetas con Scroll -->
        <div class="d-block d-md-none flex-grow-1 overflow-y-auto pr-1" style="min-height: 0;">
          <div class="d-flex flex-column gap-2" style="padding-block-end: 80px;">
            <VCard
              v-for="item in distributedLots"
              :key="item.isNew ? item.temp_id : item.id"
              variant="flat"
              border
              class="rounded-lg premium-lot-card overflow-hidden bg-white elevation-1"
            >
            <div class="pa-3">
              <div class="d-flex justify-space-between align-center mb-2">
                <div class="d-flex align-center gap-2">
                  <VAvatar :color="item.isNew ? 'success' : 'primary'" variant="flat" size="20">
                    <VIcon :icon="item.isNew ? 'tabler-plus' : 'tabler-package'" size="12" color="white" />
                  </VAvatar>
                  <span class="text-super-xs font-weight-black uppercase" :class="item.isNew ? 'text-success' : 'text-primary'">
                    {{ item.isNew ? 'Nuevo' : `Stock: ${formatNumber(originalLots.find(l => l.id === item.id)?.quantity || 0)}` }}
                  </span>
                </div>
                <IconBtn 
                  :color="item.isNew ? 'error' : 'warning'" 
                  size="x-small" 
                  variant="tonal"
                  @click="item.isNew ? handleRemoveNewLot(item.temp_id) : handleClearLotQuantity(item)"
                >
                  <VIcon :icon="item.isNew ? 'tabler-trash' : 'tabler-trash-x'" size="16" />
                </IconBtn>
              </div>

              <VRow dense>
                <VCol cols="12">
                  <AppTextField
                    v-model="item.lot_number"
                    label="Nº Lote"
                    density="comfortable"
                    class="premium-textfield shadow-sm"
                    :error-messages="lotErrors[item.isNew ? item.temp_id : item.id]?.lot_number"
                  />
                </VCol>
                <VCol cols="6">
                  <AppTextField
                    v-model="item.expiration_date"
                    label="Vencimiento"
                    density="comfortable"
                    type="date"
                    class="premium-textfield shadow-sm"
                    :error-messages="lotErrors[item.isNew ? item.temp_id : item.id]?.expiration_date"
                  />
                </VCol>
                <VCol cols="6">
                  <VAutocomplete
                    v-model="item.location"
                    label="Ubicación"
                    density="comfortable"
                    placeholder="Ubicación"
                    variant="outlined"
                    class="premium-textfield shadow-sm"
                    :items="props.locations"
                    item-title="name"
                    item-value="name"
                    :error-messages="lotErrors[item.isNew ? item.temp_id : item.id]?.location"
                  />
                </VCol>
                <VCol cols="12">
                  <div class="quantity-input-box mt-1 pa-1 px-3 rounded-lg border-dashed-2 bg-light shadow-inner">
                    <div class="d-flex align-center justify-space-between w-100">
                      <span class="text-super-xs font-weight-black text-disabled uppercase">Cantidad</span>
                      <div class="flex-grow-1 ml-4 text-end">
                        <AppTextField
                          v-model.number="item.quantity"
                          type="number"
                          variant="plain"
                          hide-details
                          class="huge-lot-input font-weight-950"
                        />
                      </div>
                      <span class="text-super-xs font-weight-black text-primary ml-1 uppercase">UNID</span>
                    </div>
                  </div>
                </VCol>
              </VRow>
            </div>
          </VCard>
          <div v-if="distributedLots.length === 0" class="text-center py-10 opacity-50 font-weight-bold uppercase text-xs">
            No hay lotes para mostrar
          </div>
        </div>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4 bg-light border-t">
        <div class="d-flex flex-column flex-sm-row gap-3 w-100">
          <VBtn
            color="secondary"
            variant="tonal"
            size="large"
            block
            height="50"
            class="flex-grow-1 font-weight-black rounded-lg text-button uppercase"
            @click="closeDialog"
          >
            Regresar
          </VBtn>
          <VBtn
            color="primary"
            variant="flat"
            size="large"
            block
            height="50"
            class="flex-grow-1 font-weight-black rounded-lg shadow-primary text-button uppercase"
            :disabled="!canSave"
            @click="handleSave"
          >
            <VIcon icon="tabler-cloud-upload" class="me-2" />
            Finalizar Distribución
          </VBtn>
        </div>
      </VCardActions>
    </VCard>
  </VDialog>

  <BarcodeScannerDialog
    v-model="isScannerVisible"
    @scan="handleScan"
  />
</template>

<style scoped>
.header-gradient {
  background: linear-gradient(135deg, rgb(var(--v-theme-primary)) 0%, #1e5128 100%);
}

.bg-light {
  background-color: #f8fafc !important;
}

.summary-grid {
  display: grid;
  gap: 16px;
  grid-template-columns: repeat(3, 1fr);
}

@media (max-width: 900px) {
  .summary-grid {
    grid-template-columns: 1fr;
  }
}

.premium-lot-card {
  transition: all 0.2s ease;
}

.border-dashed-2 {
  border: 1px dashed rgba(var(--v-border-color), 20%) !important;
}

.huge-lot-input :deep(input) {
  border: none;
  background: transparent;
  block-size: 36px !important;
  color: rgb(var(--v-theme-primary)) !important;
  font-size: 1.25rem !important;
  font-weight: 950 !important;
  text-align: end !important;
}

.premium-textfield :deep(.v-field__outline) {
  --v-field-border-opacity: 0.1 !important;
}

.shadow-inner {
  box-shadow: inset 0 2px 4px 0 rgba(0, 0, 0, 6%) !important;
}

.quantity-input-wrapper {
  min-inline-size: 120px;
  transition: all 0.2s ease;
}

.quantity-input-wrapper:focus-within {
  border-color: rgb(var(--v-theme-primary)) !important;
  background-color: white !important;
  box-shadow: 0 0 0 2px rgba(var(--v-theme-primary), 0.1) !important;
}

.compact-qty-input :deep(input) {
  font-size: 1rem !important;
  font-weight: 950 !important;
  padding-block: 0 !important;
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: normal;
}

.letter-spacing-1 { letter-spacing: 1.5px !important; }
.leading-none { line-height: 1 !important; }
.leading-tight { line-height: 1.25 !important; }

.truncate {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.shadow-lg {
  box-shadow: 0 4px 12px rgba(0, 0, 0, 10%) !important;
}

:deep(.v-btn.v-btn--size-large) {
  font-size: 0.875rem !important;
  letter-spacing: 1px !important;
  text-transform: uppercase;
}

:deep(.v-data-table__td) {
  padding-block: 12px !important;
}

:deep(.huge-lot-input-wrapper input) {
  font-weight: 900 !important;
  text-align: center !important;
}

.header-icon-box {
  display: flex;
  align-items: center;
  justify-content: center;
  block-size: 60px;
  inline-size: 60px;
}
</style>
