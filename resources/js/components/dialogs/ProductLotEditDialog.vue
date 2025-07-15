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
      };
    });
  },
  { deep: true, immediate: true }
);

const currentLotsSum = computed(() => {
  return editableLots.value.reduce((sum, lot) => {
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
  const allLotsValid = editableLots.value.every((lot) => {
    return (
      lot.lot_number &&
      lot.quantity &&
      parseInt(lot.quantity) > 0 &&
      lot.expiration_date &&
      lot.unit_cost &&
      parseFloat(lot.unit_cost) >= 0
    );
  });

  const stockValid =
    !hasStockDiscrepancy.value || currentLotsSum.value <= props.productStock;

  return allLotsValid && stockValid && editableLots.value.length > 0;
});

const validateLotQuantity = (lot, index) => {
  const quantity = parseInt(lot.quantity) || 0;

  if (quantity <= 0) {
    errors.value[`quantity_${index}`] = "La cantidad debe ser mayor a 0";
    return false;
  }

  if (hasStockDiscrepancy.value) {
    const otherLotsSum = editableLots.value.reduce(
      (sum, otherLot, otherIndex) => {
        if (otherIndex !== index) {
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
  });
  tempIdCounter.value--;
};

const removeLot = (index) => {
  editableLots.value.splice(index, 1);
  Object.keys(errors.value).forEach((key) => {
    if (key.endsWith(`_${index}`)) {
      delete errors.value[key];
    }
  });
};

const onSave = () => {
  let allValid = true;
  errors.value = {};

  editableLots.value.forEach((lot, index) => {
    if (!validateLot(lot, index)) {
      allValid = false;
    }
  });

  if (hasStockDiscrepancy.value && currentLotsSum.value > props.productStock) {
    errors.value.total_stock = `La cantidad total (${currentLotsSum.value}) excede el stock del producto (${props.productStock})`;
    allValid = false;
  }

  if (allValid) {
    console.log("Datos de lotes a enviar:", editableLots.value);
    emit("save", editableLots.value);
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
    <VCard class="d-flex flex-column">
      <VCardTitle class="d-flex align-center">
        <span class="text-h5 font-weight-bold"
          >Edicion de Lotes: {{ props.productId }} -
          {{ props.productName }}</span
        >

        <VChip
          v-if="hasStockDiscrepancy"
          :color="missingStock > 0 ? 'warning' : 'success'"
          variant="outlined"
          size="small"
          class="ml-4"
        >
          {{ missingStock > 0 ? `Faltan: ${missingStock}` : "Stock Completo" }}
        </VChip>

        <VSpacer />
        <VBtn icon variant="text" @click="closeDialog">
          <VIcon>tabler-x</VIcon>
        </VBtn>
      </VCardTitle>

      <VDivider />

      <VCardText class="flex-grow-1" style="overflow-y: auto">
        <VAlert
          v-if="hasStockDiscrepancy"
          :type="missingStock > 0 ? 'warning' : 'info'"
          variant="tonal"
          class="mb-4"
        >
          <div>
            <strong>Stock del producto:</strong> {{ props.productStock }} |
            <strong>Total en lotes:</strong> {{ currentLotsSum }} |
            <strong>Disponible:</strong> {{ availableStock }}
          </div>
          <div v-if="missingStock > 0" class="mt-1">
            Puedes agregar hasta {{ availableStock }} unidades más en los lotes.
          </div>
        </VAlert>

        <VAlert
          v-if="errors.total_stock"
          type="error"
          variant="tonal"
          class="mb-4"
        >
          {{ errors.total_stock }}
        </VAlert>
        <div class="d-flex align-center mb-4">
          <VSpacer />
          <VBtn
            prepend-icon="tabler-plus"
            color="primary"
            variant="flat"
            @click="addNewLotRow"
            :disabled="hasStockDiscrepancy && availableStock <= 0"
          >
            Agregar Lote
          </VBtn>
        </div>
        <VDataTable
          :headers="[
            { title: '# Lote', key: 'lot_number', sortable: false },
            { title: 'Stock', key: 'quantity', sortable: false },
            {
              title: 'Exp',
              key: 'expiration_date',
              sortable: false,
            },
            { title: 'Costo', key: 'unit_cost', sortable: false },
            { title: 'Ubicación', key: 'location', sortable: false },
            { title: 'Accion', key: 'actions', sortable: false },
          ]"
          :items="editableLots"
          density="compact"
          class="rounded-lg"
          no-data-text="No hay lotes registrados para este producto."
        >
          <template #item.lot_number="{ item, index }">
            <VTextField
              v-model="item.lot_number"
              variant="plane"
              :error-messages="getFieldError('lot_number', index)"
              hide-details="auto"
              density="compact"
            />
          </template>

          <template #item.quantity="{ item, index }">
            <VTextField
              v-model="item.quantity"
              type="number"
              variant="plane"
              :error-messages="getFieldError('quantity', index)"
              hide-details="auto"
              density="compact"
              @input="onQuantityChange(item, index)"
            />
          </template>

          <template #item.expiration_date="{ item, index }">
            <VTextField
              v-model="item.expiration_date"
              type="date"
              variant="plane"
              :error-messages="getFieldError('expiration_date', index)"
              hide-details="auto"
              density="compact"
            />
          </template>

          <template #item.unit_cost="{ item, index }">
            <VTextField
              v-model="item.unit_cost"
              type="number"
              step="0.01"
              prefix="$"
              variant="plane"
              :error-messages="getFieldError('unit_cost', index)"
              hide-details="auto"
              density="compact"
            />
          </template>

          <template #item.location="{ item, index }">
            <VTextField
              v-model="item.location"
              variant="plane"
              :error-messages="getFieldError('location', index)"
              hide-details="auto"
              density="compact"
              placeholder="Ej: A1-B2"
            />
          </template>

          <template #item.actions="{ item, index }">
            <VBtn
              v-if="item.id < 0"
              icon="tabler-trash"
              variant="text"
              color="error"
              size="small"
              @click="removeLot(index)"
            />
          </template>
        </VDataTable>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4">
        <VBtn
          color="secondary"
          variant="outlined"
          @click="closeDialog"
          class="flex-grow-1 w-0 mr-4"
        >
          Cancelar
        </VBtn>
        <VBtn
          color="primary"
          variant="flat"
          @click="onSave"
          :disabled="!canSave"
          class="flex-grow-1 w-0"
        >
          Guardar Cambios
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
