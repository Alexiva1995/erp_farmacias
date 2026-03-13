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
    class="premium-dialog"
  >
    <VCard class="overflow-hidden rounded-xl border-0 elevation-24">
      <!-- Header Premium -->
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-5 d-flex align-center justify-space-between text-white">
          <div class="d-flex align-center gap-3">
            <div class="header-icon-box shadow-sm">
              <VIcon icon="tabler-package" size="24" />
            </div>
            <div>
              <div class="text-h6 font-weight-black leading-tight">Ajustar Stock por Lotes</div>
              <div class="text-super-xs font-weight-bold uppercase opacity-80">Distribución de inventario físico</div>
            </div>
          </div>
          <IconBtn color="white" variant="tonal" size="small" @click="closeDialog">
            <VIcon icon="tabler-x" />
          </IconBtn>
        </div>
      </VCardTitle>

      <VCardText class="pa-4 pa-sm-6 bg-surface lot-distribution-content">
        <!-- Perfil del Producto Premium -->
        <div class="product-profile-box pa-4 rounded-lg mb-6 border d-flex flex-column flex-sm-row align-center gap-4">
          <div class="d-flex align-center gap-4 flex-grow-1">
            <VAvatar
              v-if="props.productPhoto"
              size="64"
              variant="tonal"
              rounded="lg"
              class="border elevation-2"
              :image="props.productPhoto"
            />
            <VAvatar v-else size="64" variant="tonal" border color="primary" rounded="lg">
              <VIcon icon="tabler-pill" size="32" />
            </VAvatar>
            <div class="min-width-0">
              <h3 class="text-h6 font-weight-black text-high-emphasis leading-tight uppercase truncate">
                {{ props.productName }}
              </h3>
              <div class="text-super-xs font-weight-bold text-disabled uppercase mt-1">
                Ajuste en modo {{ props.mode === 'adjustment' ? 'Cíclico' : 'Retorno' }}
              </div>
            </div>
          </div>
          <VBtn
            color="success"
            prepend-icon="tabler-plus"
            variant="flat"
            class="font-weight-black rounded-lg w-100 w-sm-auto shadow-sm"
            @click="handleAddNewLot"
          >
            AÑADIR LOTE
          </VBtn>
        </div>

        <!-- Grid de Sumario Premium -->
        <div class="d-grid summary-grid gap-4 mb-6">
          <VCard variant="flat" border class="pa-4 rounded-xl bg-light-primary border-opacity-20 text-center">
            <div class="d-flex align-center justify-center gap-2 mb-2">
              <VIcon icon="tabler-target" size="18" class="text-primary" />
              <span class="text-super-xs font-weight-black text-primary uppercase">Stock Objetivo</span>
            </div>
            <div class="text-h4 font-weight-black text-primary leading-none">
              {{ formatNumber(objective) }}
              <span class="text-xs font-weight-bold opacity-70">UNDS</span>
            </div>
          </VCard>

          <VCard variant="flat" border class="pa-4 rounded-xl bg-light-info border-opacity-20 text-center">
            <div class="d-flex align-center justify-center gap-2 mb-2">
              <VIcon icon="tabler-package-import" size="18" class="text-info" />
              <span class="text-super-xs font-weight-black text-info uppercase">Total Distribuido</span>
            </div>
            <div class="text-h4 font-weight-black text-info leading-none">
              {{ formatNumber(totalDistributed) }}
              <span class="text-xs font-weight-bold opacity-70">UNDS</span>
            </div>
          </VCard>

          <VCard 
            variant="flat" 
            border 
            class="pa-4 rounded-xl border-opacity-20 text-center"
            :class="discrepancy === 0 ? 'bg-light-success' : 'bg-light-error'"
          >
            <div class="d-flex align-center justify-center gap-2 mb-2">
              <VIcon :icon="discrepancy === 0 ? 'tabler-circle-check' : 'tabler-scale'" size="18" :class="discrepancy === 0 ? 'text-success' : 'text-error'" />
              <span class="text-super-xs font-weight-black uppercase" :class="discrepancy === 0 ? 'text-success' : 'text-error'">Diferencia</span>
            </div>
            <div class="text-h4 font-weight-black leading-none" :class="discrepancy === 0 ? 'text-success' : 'text-error'">
              {{ discrepancy > 0 ? '+' : '' }}{{ formatNumber(discrepancy) }}
              <span class="text-xs font-weight-bold opacity-70">UNDS</span>
            </div>
          </VCard>
        </div>

        <!-- Tabla Escritorio -->
        <div class="d-none d-md-block">
          <VDataTable
            :headers="[
              { title: 'Información del Lote', key: 'info', sortable: false },
              { title: 'Stock Sistema', key: 'original_stock', sortable: false, align: 'center', width: '150px' },
              { title: 'Nueva Cantidad', key: 'quantity', sortable: false, align: 'center', width: '180px' },
              { title: '', key: 'actions', sortable: false, align: 'center', width: '80px' },
            ]"
            :items="distributedLots"
            :item-value="item => item.isNew ? item.temp_id : item.id"
            density="comfortable"
            class="premium-table border rounded-xl overflow-hidden"
          >
            <template #item.info="{ item }">
              <VRow dense class="py-2">
                <VCol cols="4">
                  <AppTextField
                    v-model="item.lot_number"
                    label="Nº Lote"
                    placeholder="Lote"
                    append-inner-icon="tabler-camera"
                    @click:append-inner="openScanner(item)"
                    :error-messages="lotErrors[item.isNew ? item.temp_id : item.id]?.lot_number"
                  />
                </VCol>
                <VCol cols="4">
                  <AppTextField
                    v-model="item.expiration_date"
                    label="Vencimiento"
                    type="date"
                    :error-messages="lotErrors[item.isNew ? item.temp_id : item.id]?.expiration_date"
                  />
                </VCol>
                <VCol cols="4">
                  <VAutocomplete
                    v-model="item.location"
                    label="Ubicación"
                    placeholder="Ubicación"
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
              <VChip v-else color="success" variant="flat" size="x-small" class="font-weight-black uppercase">
                Nuevo
              </VChip>
            </template>

            <template #item.quantity="{ item }">
              <AppTextField
                v-model.number="item.quantity"
                type="number"
                min="0"
                class="text-center font-weight-black"
                hide-details
              />
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

        <!-- Vista Móvil de Tarjetas Premium -->
        <div class="d-block d-md-none d-flex flex-column gap-3 overflow-y-auto" style="max-block-size: 50vh;">
          <VCard
            v-for="item in distributedLots"
            :key="item.isNew ? item.temp_id : item.id"
            variant="flat"
            border
            class="rounded-xl premium-lot-card overflow-hidden"
          >
            <div class="pa-4">
              <div class="d-flex justify-space-between align-center mb-4">
                <div class="d-flex align-center gap-2">
                  <VIcon :icon="item.isNew ? 'tabler-plus' : 'tabler-package'" size="18" :class="item.isNew ? 'text-success' : 'text-primary'" />
                  <span class="text-super-xs font-weight-black uppercase" :class="item.isNew ? 'text-success' : 'text-primary'">
                    {{ item.isNew ? 'Lote Nuevo' : `Stock Sistema: ${formatNumber(originalLots.find(l => l.id === item.id)?.quantity || 0)}` }}
                  </span>
                </div>
                <IconBtn 
                  :color="item.isNew ? 'error' : 'warning'" 
                  size="small" 
                  variant="tonal"
                  @click="item.isNew ? handleRemoveNewLot(item.temp_id) : handleClearLotQuantity(item)"
                >
                  <VIcon :icon="item.isNew ? 'tabler-trash' : 'tabler-trash-x'" />
                </IconBtn>
              </div>

              <VRow dense>
                <VCol cols="12">
                  <AppTextField
                    v-model="item.lot_number"
                    label="Nº Lote"
                    append-inner-icon="tabler-camera"
                    @click:append-inner="openScanner(item)"
                    :error-messages="lotErrors[item.isNew ? item.temp_id : item.id]?.lot_number"
                  />
                </VCol>
                <VCol cols="6">
                  <AppTextField
                    v-model="item.expiration_date"
                    label="Vencimiento"
                    type="date"
                    :error-messages="lotErrors[item.isNew ? item.temp_id : item.id]?.expiration_date"
                  />
                </VCol>
                <VCol cols="6">
                  <VAutocomplete
                    v-model="item.location"
                    label="Ubicación"
                    placeholder="Ubicación"
                    :items="props.locations"
                    item-title="name"
                    item-value="name"
                    :error-messages="lotErrors[item.isNew ? item.temp_id : item.id]?.location"
                  />
                </VCol>
                <VCol cols="12">
                  <div class="quantity-input-box mt-2 pa-2 rounded-lg border-dashed-2">
                    <div class="d-flex align-center justify-space-between w-100">
                      <span class="text-super-xs font-weight-black text-disabled uppercase">Nueva Cantidad</span>
                      <div class="flex-grow-1 ml-4">
                        <AppTextField
                          v-model.number="item.quantity"
                          type="number"
                          variant="plain"
                          hide-details
                          class="huge-lot-input"
                        />
                      </div>
                      <span class="text-xs font-weight-black text-primary ml-2 uppercase">UNDS</span>
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

      <VCardActions class="pa-4 pa-sm-6 pt-0 bg-surface">
        <div class="d-flex flex-column flex-sm-row gap-3 w-100">
          <VBtn
            color="secondary"
            variant="tonal"
            size="large"
            block
            height="48"
            class="flex-grow-1 font-weight-black rounded-lg"
            @click="closeDialog"
          >
            CANCELAR
          </VBtn>
          <VBtn
            color="primary"
            variant="flat"
            size="large"
            block
            height="48"
            class="flex-grow-1 font-weight-black rounded-lg shadow-sm"
            :disabled="!canSave"
            @click="handleSave"
          >
            GUARDAR CAMBIOS
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
  background: linear-gradient(135deg, rgb(var(--v-theme-primary)), rgb(var(--v-theme-primary-darken-1)));
}

.header-icon-box {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 8px;
  border-radius: 12px;
  background: rgba(255, 255, 255, 20%);
}

.product-profile-box {
  display: flex;
  align-items: center;
  padding: 16px;
  gap: 16px;
  border: 1px solid rgba(var(--v-border-color), 12%) !important;
  background-color: rgba(var(--v-theme-surface-variant), 3%);
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

.bg-light-primary { background-color: rgba(var(--v-theme-primary), 5%) !important; }
.bg-light-info { background-color: rgba(var(--v-theme-info), 5%) !important; }
.bg-light-success { background-color: rgba(var(--v-theme-success), 5%) !important; }
.bg-light-error { background-color: rgba(var(--v-theme-error), 5%) !important; }

.premium-lot-card {
  transition: transform 0.2s ease;
}

.premium-lot-card:active {
  transform: scale(0.98);
}

.border-dashed-2 {
  border: 2px dashed rgba(var(--v-border-color), 15%) !important;
}

.huge-lot-input :deep(input) {
  height: 40px !important;
  font-size: 1.5rem !important;
  font-weight: 900 !important;
  text-align: right !important;
  color: rgb(var(--v-theme-primary)) !important;
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: normal;
}

.leading-none { line-height: 1 !important; }
.leading-tight { line-height: 1.25 !important; }
.truncate { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

:deep(.v-btn.v-btn--size-large) {
  font-size: 0.875rem !important;
  letter-spacing: 0.5px !important;
}

:deep(.v-data-table__td) {
  padding-block: 12px !important;
}

@media (max-width: 600px) {
  .lot-distribution-content {
    padding: 16px !important;
  }
}
</style>
