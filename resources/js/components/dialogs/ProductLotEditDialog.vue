<script setup>
import { computed, ref, watch } from "vue";

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  productName: { type: String, default: "" },
  lots: { type: Array, default: () => [] },
  productId: { type: Number, required: true },
  productStock: { type: Number, default: 0 },
});

const emit = defineEmits(["update:modelValue", "save"]);

const editableLots = ref([]);
const tempIdCounter = ref(-1);
const errors = ref({});

watch(
  () => props.lots,
  (newLots) => {
    if (!newLots || newLots.length === 0) {
      editableLots.value = [];
      return;
    }
    editableLots.value = newLots.map((lot) => {
      let formattedDate = "";
      if (lot.expiration_date) {
        const date = new Date(lot.expiration_date);
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, "0");
        const day = String(date.getDate()).padStart(2, "0");
        formattedDate = `${year}-${month}-${day}`;
      }
      return {
        ...lot,
        expiration_date: formattedDate,
        location: lot.location || "",
        // Marcar lotes con cantidad 0 como eliminados
        _markedForDeletion: parseInt(lot.quantity) === 0,
      };
    });
  },
  { deep: true, immediate: true }
);

const currentLotsSum = computed(() => {
  return editableLots.value.reduce((sum, lot) => {
    // No contar lotes marcados para eliminación o con cantidad 0
    if (lot._markedForDeletion || parseInt(lot.quantity) === 0) {
      return sum;
    }
    const quantity = parseInt(lot.quantity) || 0;
    return sum + quantity;
  }, 0);
});

const missingStock = computed(() => {
  const difference = props.productStock - currentLotsSum.value;
  return difference > 0 ? difference : 0;
});

const hasStockDiscrepancy = computed(() => {
  return currentLotsSum.value !== props.productStock;
});

const availableStock = computed(() => {
  return Math.max(0, props.productStock - currentLotsSum.value);
});

const canSave = computed(() => {
  // Verificar que todos los lotes activos (no marcados para eliminación y con cantidad > 0) tengan datos válidos
  const activeLots = editableLots.value.filter(
    (lot) => !lot._markedForDeletion && parseInt(lot.quantity) > 0
  );

  const allLotsValid = activeLots.every((lot) => {
    return (
      lot.lot_number &&
      lot.quantity &&
      parseInt(lot.quantity) > 0 &&
      lot.expiration_date &&
      lot.unit_cost &&
      parseFloat(lot.unit_cost) >= 0
    );
  });

  // El stock debe coincidir exactamente
  const stockValid = currentLotsSum.value === props.productStock;

  return allLotsValid && stockValid;
});

const validateLotQuantity = (lot, index) => {
  const quantity = parseInt(lot.quantity);

  // Permitir cantidad 0 (se considera como marcado para eliminación)
  if (quantity === 0) {
    lot._markedForDeletion = true;
    delete errors.value[`quantity_${index}`];
    return true;
  } else {
    lot._markedForDeletion = false;
  }

  // Si no es 0, debe ser mayor que 0
  if (isNaN(quantity) || quantity < 0) {
    errors.value[`quantity_${index}`] =
      "La cantidad debe ser mayor o igual a 0";
    return false;
  }

  // Validar que no exceda el stock disponible
  if (hasStockDiscrepancy.value) {
    const otherLotsSum = editableLots.value.reduce(
      (sum, otherLot, otherIndex) => {
        if (otherIndex !== index && !otherLot._markedForDeletion) {
          return sum + (parseInt(otherLot.quantity) || 0);
        }
        return sum;
      },
      0
    );

    const maxAllowed = props.productStock - otherLotsSum;

    if (quantity > maxAllowed) {
      errors.value[
        `quantity_${index}`
      ] = `Máximo ${maxAllowed} unidades disponibles`;
      return false;
    }
  }

  delete errors.value[`quantity_${index}`];
  return true;
};

const validateLot = (lot, index) => {
  // Si el lote está marcado para eliminación (cantidad 0), no validar otros campos
  if (lot._markedForDeletion || parseInt(lot.quantity) === 0) {
    // Limpiar errores para lotes marcados para eliminación
    delete errors.value[`lot_number_${index}`];
    delete errors.value[`expiration_date_${index}`];
    delete errors.value[`unit_cost_${index}`];
    delete errors.value[`location_${index}`];
    return true;
  }

  let isValid = true;

  if (!lot.lot_number || lot.lot_number.trim() === "") {
    errors.value[`lot_number_${index}`] = "El número de lote es requerido";
    isValid = false;
  } else {
    delete errors.value[`lot_number_${index}`];
  }

  if (!validateLotQuantity(lot, index)) {
    isValid = false;
  }

  if (!lot.expiration_date) {
    errors.value[`expiration_date_${index}`] =
      "La fecha de expiración es requerida";
    isValid = false;
  } else {
    delete errors.value[`expiration_date_${index}`];
  }

  const cost = parseFloat(lot.unit_cost);
  if (!lot.unit_cost || isNaN(cost) || cost < 0) {
    errors.value[`unit_cost_${index}`] =
      "El costo debe ser un número válido mayor o igual a 0";
    isValid = false;
  } else {
    delete errors.value[`unit_cost_${index}`];
  }

  if (lot.location && lot.location.trim() !== "") {
    const locationPattern = /^[A-Za-z0-9\-_\s]+$/;
    if (!locationPattern.test(lot.location.trim())) {
      errors.value[`location_${index}`] =
        "La ubicación solo puede contener letras, números, guiones y espacios";
      isValid = false;
    } else {
      delete errors.value[`location_${index}`];
    }
  } else {
    delete errors.value[`location_${index}`];
  }

  return isValid;
};

const onQuantityChange = (lot, index) => {
  validateLotQuantity(lot, index);
};

const addNewLotRow = () => {
  editableLots.value.push({
    id: tempIdCounter.value,
    lot_number: "",
    quantity: null,
    expiration_date: "",
    unit_cost: null,
    location: "",
    _markedForDeletion: false,
  });
  tempIdCounter.value--;
};

const removeLot = (index) => {
  const lot = editableLots.value[index];

  if (lot.id > 0) {
    // Lote existente: marcar para "eliminación" (quantity = 0)
    lot._markedForDeletion = true;
    lot.quantity = 0;
  } else {
    // Lote nuevo: eliminar completamente del array
    editableLots.value.splice(index, 1);
  }

  // Limpiar errores relacionados
  Object.keys(errors.value).forEach((key) => {
    if (key.endsWith(`_${index}`)) {
      delete errors.value[key];
    }
  });
};

const restoreLot = (index) => {
  const lot = editableLots.value[index];
  lot._markedForDeletion = false;
  // Si el lote tenía quantity = 0, restaurarlo con cantidad 1
  if (parseInt(lot.quantity) === 0) {
    lot.quantity = 1;
  }
};

const onSave = () => {
  let allValid = true;
  errors.value = {};

  editableLots.value.forEach((lot, index) => {
    if (!validateLot(lot, index)) {
      allValid = false;
    }
  });

  // Validar que el stock total coincida exactamente
  if (currentLotsSum.value !== props.productStock) {
    errors.value.total_stock = `La cantidad total (${currentLotsSum.value}) debe ser igual al stock del producto (${props.productStock})`;
    allValid = false;
  }

  if (allValid) {
    // Preparar datos para envío
    const lotsToSave = editableLots.value.map((lot) => ({
      ...lot,
      // Convertir cantidad 0 a null para lotes marcados para eliminación
      quantity: lot._markedForDeletion ? 0 : lot.quantity,
    }));

    emit("save", lotsToSave);
  }
};

const closeDialog = () => {
  errors.value = {};
  emit("update:modelValue", false);
};

const getFieldError = (field, index) => {
  return errors.value[`${field}_${index}`];
};
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    max-width="1000px"
    persistent
    @update:model-value="closeDialog"
    :scrollable="true"
    content-class="d-flex"
  >
    <VCard class="detail-dialog-card overflow-hidden border-0 elevation-12">
      <!-- Cabecera Premium -->
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-4 d-flex align-center shadow-sm">
          <div class="d-flex align-center">
            <VAvatar
              color="white"
              variant="flat"
              size="40"
              class="me-3 elevation-2"
            >
              <VIcon
                icon="tabler-edit"
                color="primary"
                size="24"
              />
            </VAvatar>
            <div>
              <h2 class="text-h6 font-weight-black text-white leading-tight mb-0">
                Edición de Lotes
              </h2>
              <span class="text-super-xs text-white opacity-75 uppercase font-weight-bold letter-spacing-1">
                ID: {{ props.productId }} — {{ props.productName }}
              </span>
            </div>
          </div>

          <VChip
            v-if="hasStockDiscrepancy"
            :color="missingStock > 0 ? 'warning' : 'error'"
            variant="flat"
            size="small"
            class="ml-4 font-weight-black elevation-1"
          >
            <VIcon :icon="missingStock > 0 ? 'tabler-alert-triangle' : 'tabler-alert-circle'" size="14" class="me-1" />
            {{
              missingStock > 0
                ? `Faltan: ${missingStock}`
                : `Exceso: ${currentLotsSum - props.productStock}`
            }}
          </VChip>

          <VSpacer />
          <VBtn
            icon
            variant="tonal"
            color="white"
            size="small"
            @click="closeDialog"
            class="rounded-lg"
          >
            <VIcon size="20">tabler-x</VIcon>
          </VBtn>
        </div>
      </VCardTitle>

      <VDivider />

      <VCardText class="pa-4 pa-sm-6 bg-light d-flex flex-column gap-4 overflow-y-auto" style="max-height: 75vh;">
        <!-- Alertas de Discrepancia -->
        <VCard 
          v-if="hasStockDiscrepancy || errors.total_stock"
          variant="flat" 
          class="border-s-4 rounded-lg overflow-hidden mb-2"
          :class="currentLotsSum > props.productStock || errors.total_stock ? 'border-error bg-error-lighten-5' : 'border-warning bg-warning-lighten-5'"
        >
          <VCardText class="pa-3">
            <div class="d-flex align-center gap-3 mb-2">
              <VIcon 
                :icon="currentLotsSum > props.productStock || errors.total_stock ? 'tabler-circle-x' : 'tabler-alert-triangle'" 
                :color="currentLotsSum > props.productStock || errors.total_stock ? 'error' : 'warning'"
              />
              <span class="font-weight-black text-body-2 uppercase letter-spacing-1">
                Atención: Discrepancia de Inventario
              </span>
            </div>
            
            <div class="d-flex flex-wrap gap-4 text-xs font-weight-medium px-8">
              <div class="d-flex align-center gap-1">
                <span class="text-disabled">Stock Maestro:</span>
                <VChip size="x-small" label color="primary" variant="tonal" class="font-weight-bold">{{ props.productStock }}</VChip>
              </div>
              <div class="d-flex align-center gap-1">
                <span class="text-disabled">Total Lotes:</span>
                <VChip size="x-small" label :color="hasStockDiscrepancy ? 'error' : 'success'" variant="tonal" class="font-weight-bold">{{ currentLotsSum }}</VChip>
              </div>
              <div class="d-flex align-center gap-1" v-if="missingStock > 0">
                <span class="text-disabled">Requerido:</span>
                <span class="text-warning font-weight-black">+{{ missingStock }} unidades</span>
              </div>
            </div>

            <div v-if="errors.total_stock" class="mt-2 text-error text-xs font-weight-bold px-8 italic">
              * {{ errors.total_stock }}
            </div>
          </VCardText>
        </VCard>

        <!-- Tabla de Lotes Premium -->
        <VCard variant="flat" class="border rounded-lg bg-white elevation-1 overflow-hidden">
          <div class="d-flex align-center pa-4 border-b bg-var-theme-background">
            <div class="d-flex align-center gap-2">
              <div class="header-indicator primary"></div>
              <span class="text-xs font-weight-black text-primary uppercase letter-spacing-1">Desglose de Lotes</span>
            </div>
            <VSpacer />
            <VBtn
              prepend-icon="tabler-plus"
              color="primary"
              variant="flat"
              size="small"
              class="font-weight-black rounded-lg shadow-primary"
              @click="addNewLotRow"
            >
              Agregar Lote
            </VBtn>
          </div>

          <VDataTable
            :headers="[
              { title: '# Lote', key: 'lot_number', sortable: false },
              { title: 'Stock', key: 'quantity', sortable: false, width: '100px' },
              { title: 'Expira', key: 'expiration_date', sortable: false, width: '150px' },
              { title: 'Costo', key: 'unit_cost', sortable: false, width: '120px' },
              { title: 'Ubicación', key: 'location', sortable: false },
              { title: '', key: 'actions', sortable: false, align: 'end', width: '60px' },
            ]"
            :items="editableLots"
            density="comfortable"
            class="premium-lot-table"
            no-data-text="No hay lotes registrados para este producto."
          >
            <template #item="{ item, index }">
              <tr :class="{ 'bg-grey-lighten-4 opacity-70': item._markedForDeletion, 'row-error': Object.keys(errors).some(k => k.endsWith(`_${index}`)) }">
                <td class="px-2">
                  <VTextField
                    v-model="item.lot_number"
                    variant="plain"
                    density="compact"
                    :error-messages="getFieldError('lot_number', index)"
                    hide-details="auto"
                    class="font-weight-bold text-body-2"
                    placeholder="# Lote"
                    :disabled="item._markedForDeletion"
                  />
                </td>
                <td class="px-2">
                  <VTextField
                    v-model="item.quantity"
                    type="number"
                    variant="plain"
                    density="compact"
                    :error-messages="getFieldError('quantity', index)"
                    hide-details="auto"
                    @input="onQuantityChange(item, index)"
                    min="0"
                    class="font-weight-black text-primary"
                    :disabled="item._markedForDeletion"
                  />
                </td>
                <td class="px-2">
                  <VTextField
                    v-model="item.expiration_date"
                    type="date"
                    variant="plain"
                    density="compact"
                    :error-messages="getFieldError('expiration_date', index)"
                    hide-details="auto"
                    class="text-body-2"
                    :disabled="item._markedForDeletion"
                  />
                </td>
                <td class="px-2">
                  <VTextField
                    v-model="item.unit_cost"
                    type="number"
                    step="0.01"
                    prefix="$"
                    variant="plain"
                    density="compact"
                    :error-messages="getFieldError('unit_cost', index)"
                    hide-details="auto"
                    class="text-body-2"
                    :disabled="item.id > 0 || item._markedForDeletion"
                  />
                </td>
                <td class="px-2">
                  <VTextField
                    v-model="item.location"
                    variant="plain"
                    density="compact"
                    :error-messages="getFieldError('location', index)"
                    hide-details="auto"
                    placeholder="Ejem: A1"
                    class="text-body-2 uppercase font-weight-medium"
                    :disabled="item._markedForDeletion"
                  />
                </td>
                <td class="px-2 text-end">
                  <VBtn
                    v-if="!item._markedForDeletion"
                    icon="tabler-trash"
                    variant="text"
                    color="error"
                    size="x-small"
                    class="rounded-lg"
                    @click="removeLot(index)"
                  />
                  <VBtn
                    v-else-if="item.id > 0"
                    icon="tabler-restore"
                    variant="tonal"
                    color="success"
                    size="x-small"
                    class="rounded-lg"
                    @click="restoreLot(index)"
                  />
                </td>
              </tr>
            </template>
          </VDataTable>
        </VCard>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4 bg-light border-t">
        <VRow dense class="w-100 ma-0">
          <VCol cols="6" class="pa-1">
            <VBtn
              color="secondary"
              variant="tonal"
              size="large"
              block
              height="50"
              class="font-weight-black rounded-lg text-button uppercase"
              @click="closeDialog"
            >
              Cancelar
            </VBtn>
          </VCol>
          <VCol cols="6" class="pa-1">
            <VBtn
              color="primary"
              variant="flat"
              size="large"
              block
              height="50"
              class="font-weight-black rounded-lg shadow-primary text-button uppercase"
              @click="onSave"
              :disabled="!canSave"
            >
              <VIcon icon="tabler-device-floppy" size="18" class="me-2" />
              Guardar Cambios
            </VBtn>
          </VCol>
        </VRow>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.header-gradient {
  background: linear-gradient(135deg, rgb(var(--v-theme-primary)) 0%, #1e5128 100%);
}

.detail-dialog-card {
  border-radius: 16px !important;
}

.bg-var-theme-background {
  background-color: rgba(var(--v-theme-primary), 0.03);
}

.header-indicator {
  inline-size: 4px;
  block-size: 16px;
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

.leading-tight {
  line-height: 1.25 !important;
}

.border-t {
  border-block-start: 1px solid rgba(var(--v-border-color), 0.08) !important;
}

/* Custom Table Styles */
.premium-lot-table :deep(table) {
  border-collapse: separate;
  border-spacing: 0;
}

.premium-lot-table :deep(.v-data-table-header) {
  background-color: #f1f5f9;
}

.premium-lot-table :deep(.v-data-table-header th) {
  text-transform: uppercase;
  font-size: 0.7rem !important;
  font-weight: 900 !important;
  color: #64748b !important;
  letter-spacing: 0.5px;
  height: 40px !important;
}

.premium-lot-table :deep(td) {
  height: 48px !important;
}

.row-error {
  background-color: rgba(var(--v-theme-error), 0.05) !important;
}

@keyframes pulse-border {
  0% { opacity: 0.6; }
  50% { opacity: 1; }
  100% { opacity: 0.6; }
}

.border-error {
  border: 1px solid rgb(var(--v-theme-error)) !important;
}

.border-warning {
  border: 1px solid rgb(var(--v-theme-warning)) !important;
}
</style>
