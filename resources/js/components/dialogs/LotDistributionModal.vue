<script setup>
import BarcodeScannerDialog from "@/components/dialogs/BarcodeScannerDialog.vue";
import { toast } from "@/plugins/sweetalert";
import { formatDateSimple } from "@/utils/formatters";
import { computed, ref, watch } from "vue";
import { useBrandingStore } from "@/stores/useBrandingStore";

const brandingStore = useBrandingStore();
const isMiniMarket = computed(() => brandingStore.settings?.business_type === 'minimarket');

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  productName: { type: String, default: "" },
  productId: { type: [String, Number], default: "" },
  laboratory: { type: String, default: "" },
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
        location: lot.location || (isMiniMarket.value ? "LOCAL" : ""),
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
    location: isMiniMarket.value ? "LOCAL" : "",
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
            <VIcon icon="tabler-truck-delivery" color="primary" size="24" />
          </VAvatar>
          <div class="d-flex flex-column leading-none text-white">
            <h2 class="text-h6 font-weight-black leading-tight mb-0 uppercase">
              Ajustar Stock por Lotes
            </h2>
            <span class="text-super-xs opacity-75 font-weight-bold uppercase letter-spacing-1">
              Logística Interna • Distribución de Existencias
            </span>
          </div>
          <VSpacer />
          <VBtn
            icon="tabler-x"
            variant="tonal"
            color="white"
            size="small"
            class="rounded-lg"
            @click="closeDialog"
          />
        </div>
      </VCardTitle>

      <VCardText class="pa-4 pa-sm-6 bg-light overflow-y-auto" style="max-height: 75vh;">
        <!-- Perfil del Producto Premium -->
        <VCard variant="flat" class="pa-5 bg-white rounded-xl border shadow-sm mb-6">
          <div class="d-flex align-center justify-space-between gap-4">
            <div class="d-flex align-center gap-4 min-width-0">
              <div class="min-width-0">
                <div class="d-flex align-center gap-2 mb-2">
                  <VChip
                    size="small"
                    color="primary"
                    variant="flat"
                    class="font-weight-black uppercase px-3 shadow-sm rounded-lg"
                  >
                    {{ props.mode === 'adjustment' ? 'MODO CÍCLICO' : 'MODO RETORNO' }}
                  </VChip>
                  <VChip
                    v-if="props.productId"
                    size="small"
                    color="secondary"
                    variant="tonal"
                    class="font-weight-black px-3 rounded-lg"
                  >
                    ID: {{ props.productId }}
                  </VChip>
                </div>
                <h3 class="text-h6 font-weight-black text-high-emphasis leading-tight uppercase mb-1 truncate">
                  {{ props.productName }}
                </h3>
                <div v-if="props.laboratory" class="d-flex align-center gap-1 opacity-75">
                  <VIcon icon="tabler-building-factory" size="14" color="primary" />
                  <span class="text-super-xs font-weight-black text-disabled uppercase letter-spacing-1">
                    {{ props.laboratory }}
                  </span>
                </div>
              </div>
            </div>
            <VBtn
              color="primary"
              icon="tabler-plus"
              variant="flat"
              size="large"
              class="rounded-lg shadow-primary"
              @click="handleAddNewLot"
            />
          </div>
        </VCard>

        <!-- Resumen de Stock Premium -->
        <VRow dense class="mb-6">
          <VCol cols="12" sm="4">
            <VCard variant="flat" class="pa-4 bg-white text-center rounded-xl border shadow-sm border-l-primary">
              <span class="text-super-xs font-weight-black text-disabled d-block uppercase mb-1 letter-spacing-1">Stock Objetivo</span>
              <div class="text-h4 font-weight-black text-primary leading-none">
                {{ formatNumber(objective) }}
              </div>
            </VCard>
          </VCol>
          
          <VCol cols="12" sm="4">
            <VCard variant="flat" class="pa-4 bg-white text-center rounded-xl border shadow-sm">
              <span class="text-super-xs font-weight-black text-disabled d-block uppercase mb-1 letter-spacing-1">Total Asignado</span>
              <div class="text-h4 font-weight-black text-info leading-none">
                {{ formatNumber(totalDistributed) }}
              </div>
            </VCard>
          </VCol>

          <VCol cols="12" sm="4">
            <VCard variant="flat" class="pa-4 bg-white text-center rounded-xl border shadow-sm">
              <span class="text-super-xs font-weight-black text-disabled d-block uppercase mb-1 letter-spacing-1">Diferencia</span>
              <div 
                class="text-h4 font-weight-black leading-none"
                :class="discrepancy === 0 ? 'text-success' : 'text-error'"
              >
                {{ discrepancy > 0 ? '+' : '' }}{{ formatNumber(discrepancy) }}
              </div>
            </VCard>
          </VCol>
        </VRow>

        <div class="d-flex align-center gap-2 mb-4">
          <div class="header-indicator secondary shadow-sm" />
          <span class="text-subtitle-2 font-weight-black text-high-emphasis uppercase letter-spacing-1">
            Desglose de Lotes y Ubicaciones
          </span>
        </div>

        <!-- Tabla Escritorio Premium -->
        <div class="d-none d-md-block">
          <VCard variant="flat" class="bg-white rounded-xl border shadow-sm overflow-hidden">
            <VDataTable
              :headers="[
                { title: 'Información del Lote', key: 'info', sortable: false },
                { title: 'Sist.', key: 'original_stock', sortable: false, align: 'center', width: '100px' },
                { title: 'Cantidad', key: 'quantity', sortable: false, align: 'center', width: '150px' },
                { title: '', key: 'actions', sortable: false, align: 'center', width: '70px' },
              ]"
              :items="distributedLots"
              :item-value="item => item.isNew ? item.temp_id : item.id"
              density="comfortable"
              class="table-standard"
              hide-default-footer
            >
              <template #item.info="{ item }">
                <VRow dense class="py-2">
                  <VCol :cols="isMiniMarket ? 6 : 4">
                    <span class="text-super-xs font-weight-black text-disabled uppercase mb-1 d-block">Lote</span>
                    <AppTextField
                      v-model="item.lot_number"
                      placeholder="Nº..."
                      variant="outlined"
                      density="comfortable"
                      class="rounded-lg font-weight-black"
                      hide-details="auto"
                      :error-messages="lotErrors[item.isNew ? item.temp_id : item.id]?.lot_number"
                    />
                  </VCol>
                  <VCol :cols="isMiniMarket ? 6 : 4">
                    <span class="text-super-xs font-weight-black text-disabled uppercase mb-1 d-block">Vencimiento</span>
                    <AppTextField
                      v-model="item.expiration_date"
                      type="date"
                      variant="outlined"
                      density="comfortable"
                      class="rounded-lg font-weight-black"
                      hide-details="auto"
                      :error-messages="lotErrors[item.isNew ? item.temp_id : item.id]?.expiration_date"
                    />
                  </VCol>
                  <VCol v-if="!isMiniMarket" cols="4">
                    <span class="text-super-xs font-weight-black text-disabled uppercase mb-1 d-block">Ubicación</span>
                    <VAutocomplete
                      v-model="item.location"
                      :items="props.locations"
                      item-title="name"
                      item-value="name"
                      placeholder="SEL..."
                      variant="outlined"
                      density="comfortable"
                      class="rounded-lg font-weight-black"
                      hide-details="auto"
                      :error-messages="lotErrors[item.isNew ? item.temp_id : item.id]?.location"
                    />
                  </VCol>
                </VRow>
              </template>

              <template #item.original_stock="{ item }">
                <VChip v-if="!item.isNew" color="primary" variant="tonal" size="small" class="font-weight-black rounded-lg">
                  {{ formatNumber(originalLots.find(l => l.id === item.id)?.quantity || 0) }}
                </VChip>
                <VChip v-else color="success" variant="flat" size="x-small" class="font-weight-black uppercase shadow-sm rounded-lg">
                  NUEVO
                </VChip>
              </template>

              <template #item.quantity="{ item }">
                <div class="pa-1 bg-light rounded-lg border-dashed-2">
                  <AppTextField
                    v-model.number="item.quantity"
                    type="number"
                    min="0"
                    variant="plain"
                    density="compact"
                    class="text-center font-weight-950 table-input-huge"
                    hide-details
                  />
                </div>
              </template>

              <template #item.actions="{ item }">
                <VBtn 
                  v-if="item.isNew" 
                  icon="tabler-trash"
                  color="error" 
                  variant="tonal" 
                  size="small" 
                  class="rounded-lg"
                  @click="handleRemoveNewLot(item.temp_id)"
                />
                <VBtn 
                  v-else 
                  icon="tabler-trash-x"
                  color="warning" 
                  variant="tonal" 
                  size="small" 
                  class="rounded-lg"
                  :disabled="item.quantity === 0"
                  @click="handleClearLotQuantity(item)"
                />
              </template>
            </VDataTable>
          </VCard>
        </div>

        <!-- Vista Móvil Premium -->
        <div class="d-block d-md-none">
          <div class="d-flex flex-column gap-3">
            <VCard
              v-for="item in distributedLots"
              :key="item.isNew ? item.temp_id : item.id"
              variant="flat"
              class="pa-4 bg-white rounded-xl border shadow-sm"
            >
              <div class="d-flex justify-space-between align-center mb-4">
                <div class="d-flex align-center gap-2">
                  <VAvatar :color="item.isNew ? 'success' : 'primary'" variant="flat" size="24" class="shadow-sm">
                    <VIcon :icon="item.isNew ? 'tabler-plus' : 'tabler-package'" size="14" color="white" />
                  </VAvatar>
                  <span class="text-xs font-weight-black uppercase letter-spacing-1" :class="item.isNew ? 'text-success' : 'text-primary'">
                    {{ item.isNew ? 'Nuevo Ingreso' : `Sistema: ${formatNumber(originalLots.find(l => l.id === item.id)?.quantity || 0)}` }}
                  </span>
                </div>
                <VBtn 
                  :color="item.isNew ? 'error' : 'warning'" 
                  size="small" 
                  variant="tonal"
                  class="rounded-lg"
                  icon="tabler-trash"
                  @click="item.isNew ? handleRemoveNewLot(item.temp_id) : handleClearLotQuantity(item)"
                />
              </div>

              <VRow dense>
                <VCol cols="12">
                  <span class="text-super-xs font-weight-black text-disabled uppercase mb-1 d-block">Número de Lote</span>
                  <AppTextField
                    v-model="item.lot_number"
                    placeholder="LOTE..."
                    variant="outlined"
                    density="comfortable"
                    class="rounded-lg font-weight-black"
                    :error-messages="lotErrors[item.isNew ? item.temp_id : item.id]?.lot_number"
                  />
                </VCol>
                <VCol :cols="isMiniMarket ? 12 : 6">
                  <span class="text-super-xs font-weight-black text-disabled uppercase mb-1 d-block">Vencimiento</span>
                  <AppTextField
                    v-model="item.expiration_date"
                    type="date"
                    variant="outlined"
                    density="comfortable"
                    class="rounded-lg font-weight-black"
                    :error-messages="lotErrors[item.isNew ? item.temp_id : item.id]?.expiration_date"
                  />
                </VCol>
                <VCol v-if="!isMiniMarket" cols="6">
                  <span class="text-super-xs font-weight-black text-disabled uppercase mb-1 d-block">Ubicación</span>
                  <VAutocomplete
                    v-model="item.location"
                    :items="props.locations"
                    item-title="name"
                    item-value="name"
                    placeholder="SEL..."
                    variant="outlined"
                    density="comfortable"
                    class="rounded-lg font-weight-black"
                    :error-messages="lotErrors[item.isNew ? item.temp_id : item.id]?.location"
                  />
                </VCol>
                <VCol cols="12">
                  <div class="pa-3 bg-light rounded-xl border-dashed-2 d-flex align-center justify-space-between mt-2">
                    <span class="text-super-xs font-weight-black text-high-emphasis uppercase letter-spacing-1">Cantidad Física</span>
                    <div class="d-flex align-center gap-2">
                      <AppTextField
                        v-model.number="item.quantity"
                        type="number"
                        variant="plain"
                        hide-details
                        class="mobile-lot-input font-weight-black"
                      />
                      <span class="text-xs font-weight-black text-primary">UNID.</span>
                    </div>
                  </div>
                </VCol>
              </VRow>
            </VCard>
          </div>
          <div v-if="distributedLots.length === 0" class="text-center py-12 pa-6 bg-white rounded-xl border mt-4">
            <VIcon icon="tabler-package-off" size="48" color="disabled" class="mb-2 opacity-25" />
            <div class="text-xs font-weight-black text-disabled uppercase letter-spacing-1">No hay lotes registrados</div>
          </div>
        </div>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4 pa-sm-6 bg-white border-t">
        <VRow dense class="w-100 ma-0">
          <VCol cols="12" sm="6" class="pa-1">
            <VBtn
              color="secondary"
              variant="tonal"
              height="50"
              block
              class="font-weight-black rounded-lg text-button uppercase"
              @click="closeDialog"
            >
              Regresar
            </VBtn>
          </VCol>
          <VCol cols="12" sm="6" class="pa-1">
            <VBtn
              color="primary"
              variant="flat"
              height="50"
              block
              class="font-weight-black rounded-lg shadow-primary text-button uppercase"
              :disabled="!canSave"
              @click="handleSave"
            >
              <VIcon icon="tabler-check" class="me-2" size="18" />
              Finalizar Distribución
            </VBtn>
          </VCol>
        </VRow>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.header-gradient {
  background: linear-gradient(
    135deg,
    rgb(var(--v-theme-primary)) 0%,
    rgb(var(--v-theme-gradient-end)) 100%
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

.leading-tight {
  line-height: 1.25 !important;
}

.border-t {
  border-block-start: 1px solid rgba(var(--v-border-color), 0.08) !important;
}

.border-dashed-2 {
  border: 1px dashed rgba(var(--v-border-color), 0.3) !important;
}

.table-standard :deep(.v-data-table-header) {
  background-color: #f1f5f9;
}

.table-standard :deep(.v-data-table-header th) {
  color: #64748b !important;
  font-size: 0.65rem !important;
  font-weight: 800 !important;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  border-bottom: 2px solid #e2e8f0 !important;
}

.table-standard :deep(td) {
  padding-block: 8px !important;
  border-bottom: 1px solid rgba(var(--v-border-color), 0.05) !important;
}

.table-input-huge :deep(input) {
  font-size: 1.25rem !important;
  font-weight: 900 !important;
  text-align: center !important;
  color: rgb(var(--v-theme-primary)) !important;
}

.mobile-lot-input :deep(input) {
  font-size: 1.5rem !important;
  font-weight: 950 !important;
  text-align: end !important;
  color: rgb(var(--v-theme-primary)) !important;
}

.text-button {
  font-size: 0.875rem !important;
  letter-spacing: 1px !important;
}

.uppercase {
  text-transform: uppercase;
}

.truncate {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
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

.truncate {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.header-icon-box {
  display: flex;
  align-items: center;
  justify-content: center;
  block-size: 60px;
  inline-size: 60px;
}
</style>
