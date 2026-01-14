<script setup>
import { toast } from "@/plugins/sweetalert";
import { computed, ref, watch } from "vue";

const locations = [
  "E-001",
  "E-002",
  "E-003",
  "E-004",
  "E-005",
  "E-006",
  "E-007",
  "E-008",
  "E-009",
  "E-010",
  "G-001",
  "G-002",
  "G-003",
  "G-004",
  "G-005",
  "G-006",
  "G-007",
  "G-008",
  "G-009",
  "G-010",
  "I-001",
  "I-002",
  "I-003",
  "I-004",
  "I-005",
  "I-006",
  "I-007",
  "I-008",
  "I-009",
  "I-010",
  "N-001",
  "N-002",
  "P-001",
  "P-002",
  "P-003",
  "P-004",
  "P-005",
  "P-006",
  "P-007",
  "P-008",
  "P-009",
  "P-010",
  "D-001",
  "D-002",
  "D-003",
  "D-004",
  "D-005",
  "D-006",
  "D-007",
  "D-008",
  "D-009",
  "D-010",
].sort();

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  productName: { type: String, default: "" },
  lots: { type: Array, default: () => [] },
  targetQuantity: { type: Number, required: true },
});

const emit = defineEmits(["update:modelValue", "save"]);

const distributedLots = ref([]);
const originalLots = ref([]);

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

const totalDistributed = computed(() => {
  return distributedLots.value.reduce(
    (sum, lot) => sum + (Number(lot.quantity) || 0),
    0
  );
});

const discrepancy = computed(() => {
  return props.targetQuantity - totalDistributed.value;
});

const lotErrors = ref({});

const hasValidationErrors = computed(() => {
  const errors = {};
  let hasErrors = false;

  for (let i = 0; i < distributedLots.value.length; i++) {
    const lot = distributedLots.value[i];
    const lotQuantity = Number(lot.quantity) || 0;
    const lotErrors = {};

    // Si la cantidad es 0, no se requieren los demás campos
    if (lotQuantity === 0) {
      continue;
    }

    // Validar lot_number solo si la cantidad no es 0
    if (!lot.lot_number || lot.lot_number.trim() === "") {
      lotErrors.lot_number = "El número de lote es requerido";
      hasErrors = true;
    }

    // Validar expiration_date solo si la cantidad no es 0
    if (!lot.expiration_date || lot.expiration_date.trim() === "") {
      lotErrors.expiration_date = "La fecha de vencimiento es requerida";
      hasErrors = true;
    }

    // Validar location solo si la cantidad no es 0
    if (!lot.location || lot.location.trim() === "") {
      lotErrors.location = "La ubicación es requerida";
      hasErrors = true;
    }

    if (Object.keys(lotErrors).length > 0) {
      errors[lot.isNew ? lot.temp_id : lot.id] = lotErrors;
    }
  }

  lotErrors.value = errors;
  return hasErrors;
});

const canSave = computed(() => {
  if (discrepancy.value !== 0) return false;
  if (hasValidationErrors.value) return false;
  return true;
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
  distributedLots.value = distributedLots.value.filter(
    (lot) => lot.temp_id !== tempId
  );
};

const handleSave = () => {
  if (!canSave.value) {
    toast.error("Por favor, complete todos los campos requeridos (número de lote, fecha de vencimiento y ubicación) para los lotes con cantidad mayor a 0.");
    return;
  }

  const updatedLots = [];
  const newLots = [];

  for (const lot of distributedLots.value) {
    const lotQuantity = Number(lot.quantity) || 0;
    
    if (lot.isNew) {
      // Solo agregar nuevos lotes si la cantidad es mayor a 0
      if (lotQuantity > 0) {
        newLots.push({
          lot_number: lot.lot_number,
          expiration_date: lot.expiration_date,
          location: lot.location || "",
          quantity: lotQuantity,
        });
      }
    } else {
      const originalLot = originalLots.value.find(
        (ol) => ol.id === lot.id
      );
      
      // Si la cantidad es 0, solo actualizar la cantidad (para eliminar el lote)
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
        // Si la cantidad no es 0, verificar si hay cambios
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

  if (updatedLots.length === 0 && newLots.length === 0) {
    toast.info("No se realizaron cambios en las cantidades de los lotes.");
    closeDialog();
    return;
  }

  emit("save", { updatedLots, newLots });
  closeDialog();
};

const closeDialog = () => {
  emit("update:modelValue", false);
};
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    max-width="900px"
    persistent
    scrollable
  >
    <VCard>
      <VCardTitle class="d-flex align-center justify-space-between pa-5 bg-primary">
        <div class="d-flex align-center gap-3">
          <VIcon icon="tabler-package" size="24" color="white" />
          <span class="text-h6 text-white">Ajustar Cantidad en Lotes</span>
        </div>
        <VBtn icon variant="text" color="white" size="small" @click="closeDialog">
          <VIcon>tabler-x</VIcon>
        </VBtn>
      </VCardTitle>

      <VCardText class="pa-5">
        <!-- Producto Info y Botón Añadir en la misma línea -->
        <div class="d-flex align-center justify-space-between mb-4">
          <div class="d-flex align-center gap-2">
            <VIcon icon="tabler-pill" size="20" color="primary" />
            <span class="text-h6 font-weight-medium text-high-emphasis">
              {{ props.productName }}
            </span>
          </div>
          <VBtn 
            color="success" 
            prepend-icon="tabler-plus" 
            size="small"
            variant="tonal"
            @click="handleAddNewLot"
          >
            Añadir Nuevo Lote
          </VBtn>
        </div>

        <!-- Resumen Visual -->
        <VRow class="mb-4">
          <VCol cols="12" md="4">
            <VCard variant="tonal" color="primary" class="pa-3">
              <div class="d-flex align-center gap-2 mb-1">
                <VIcon icon="tabler-target" size="20" />
                <span class="text-sm text-medium-emphasis">Objetivo</span>
              </div>
              <p class="text-h6 font-weight-bold mb-0">{{ props.targetQuantity }}</p>
            </VCard>
          </VCol>
          <VCol cols="12" md="4">
            <VCard variant="tonal" :color="discrepancy === 0 ? 'success' : 'warning'" class="pa-3">
              <div class="d-flex align-center gap-2 mb-1">
                <VIcon :icon="discrepancy === 0 ? 'tabler-check' : 'tabler-alert-circle'" size="20" />
                <span class="text-sm text-medium-emphasis">Total Distribuido</span>
              </div>
              <p class="text-h6 font-weight-bold mb-0">{{ totalDistributed }}</p>
            </VCard>
          </VCol>
          <VCol cols="12" md="4">
            <VCard 
              variant="tonal" 
              :color="discrepancy === 0 ? 'success' : discrepancy > 0 ? 'info' : 'error'" 
              class="pa-3"
            >
              <div class="d-flex align-center gap-2 mb-1">
                <VIcon 
                  :icon="discrepancy === 0 ? 'tabler-check' : discrepancy > 0 ? 'tabler-arrow-up' : 'tabler-arrow-down'" 
                  size="20" 
                />
                <span class="text-sm text-medium-emphasis">Diferencia</span>
              </div>
              <p 
                class="text-h6 font-weight-bold mb-0"
                :class="{
                  'text-success': discrepancy === 0,
                  'text-info': discrepancy > 0,
                  'text-error': discrepancy < 0,
                }"
              >
                {{ discrepancy >= 0 ? "+" : "" }}{{ discrepancy }}
              </p>
            </VCard>
          </VCol>
        </VRow>
      </VCardText>

      <VDivider />

      <VCardText class="pa-5 pt-3">
        <VDataTable
          :headers="[
            {
              title: 'Información del Lote',
              key: 'info',
              sortable: false,
              width: '45%',
            },
            {
              title: 'Stock Sistema',
              key: 'original_quantity',
              sortable: false,
              align: 'center',
              width: '18%',
            },
            {
              title: 'Cantidad Ajustada',
              key: 'quantity',
              sortable: false,
              width: '22%',
              align: 'center',
            },
            {
              title: 'Acciones',
              key: 'actions',
              sortable: false,
              width: '15%',
              align: 'center',
            },
          ]"
          :items="distributedLots"
          :item-value="(item) => (item.isNew ? item.temp_id : item.id)"
          density="comfortable"
          class="rounded-lg"
          no-data-text="Este producto no tiene lotes registrados."
        >
          <template #item.info="{ item }">
            <div class="d-flex flex-column ga-2 py-1">
              <VTextField
                v-model="item.lot_number"
                label="Número de Lote"
                variant="outlined"
                density="compact"
                :error-messages="lotErrors[item.isNew ? item.temp_id : item.id]?.lot_number"
                placeholder="Ej: LOTE-001"
                prepend-inner-icon="tabler-barcode"
                class="mb-2"
                :required="(Number(item.quantity) || 0) > 0 && (!item.lot_number || item.lot_number.trim() === '')"
                :disabled="(Number(item.quantity) || 0) === 0"
              />
              <VTextField
                v-model="item.expiration_date"
                label="Fecha de Vencimiento"
                type="date"
                variant="outlined"
                density="compact"
                :error-messages="lotErrors[item.isNew ? item.temp_id : item.id]?.expiration_date"
                prepend-inner-icon="tabler-calendar"
                class="mb-2"
                :required="(Number(item.quantity) || 0) > 0 && (!item.expiration_date || item.expiration_date.trim() === '')"
                :disabled="(Number(item.quantity) || 0) === 0"
              />
              <VAutocomplete
                v-model="item.location"
                label="Ubicación"
                variant="outlined"
                density="compact"
                :items="locations"
                :error-messages="lotErrors[item.isNew ? item.temp_id : item.id]?.location"
                placeholder="Busca una ubicación"
                prepend-inner-icon="tabler-map-pin"
                clearable
                :required="(Number(item.quantity) || 0) > 0 && (!item.location || item.location.trim() === '')"
                :disabled="(Number(item.quantity) || 0) === 0"
              />
            </div>
          </template>

          <template #item.original_quantity="{ item }">
            <VChip 
              v-if="!item.isNew" 
              label 
              size="small"
              variant="tonal"
              color="primary"
            >
              {{ originalLots.find((l) => l.id === item.id)?.quantity || 0 }}
            </VChip>
            <VChip 
              v-else 
              label 
              size="small" 
              color="success"
              variant="tonal"
            >
              <VIcon icon="tabler-plus" size="14" class="me-1" />
              NUEVO
            </VChip>
          </template>

          <template #item.quantity="{ item }">
            <VTextField
              v-model.number="item.quantity"
              type="number"
              variant="outlined"
              density="compact"
              hide-details
              min="0"
              style="max-width: 130px"
              class="mx-auto"
            />
          </template>

          <template #item.actions="{ item }">
            <IconBtn
              v-if="item.isNew"
              @click="handleRemoveNewLot(item.temp_id)"
              color="error"
              size="small"
              variant="text"
            >
              <VIcon icon="tabler-trash" size="18" />
              <VTooltip activator="parent" location="top">Eliminar lote</VTooltip>
            </IconBtn>
            <span v-else class="text-disabled text-caption">—</span>
          </template>
        </VDataTable>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4 px-5">
        <VRow class="w-100 ma-0">
          <VCol cols="6" class="pa-2">
            <VBtn 
              color="secondary" 
              variant="outlined" 
              prepend-icon="tabler-x"
              block
              @click="closeDialog"
            >
              Cancelar
            </VBtn>
          </VCol>
          <VCol cols="6" class="pa-2">
            <VBtn
              color="primary"
              variant="flat"
              prepend-icon="tabler-check"
              block
              @click="handleSave"
              :disabled="!canSave"
            >
              Guardar Cambios
            </VBtn>
          </VCol>
        </VRow>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
:deep(.v-data-table) {
  border-radius: 8px;
}

:deep(.v-data-table th) {
  font-weight: 600;
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

:deep(.v-data-table td) {
  padding: 12px 16px;
}

:deep(.v-text-field) {
  font-size: 0.875rem;
}

:deep(.v-chip) {
  font-weight: 600;
}
</style>
