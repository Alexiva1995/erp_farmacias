<script setup>
import { toast } from "@/plugins/sweetalert";
import { computed, ref, watch } from "vue";

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

watch(
  () => props.modelValue,
  (isOpening) => {
    if (isOpening && props.lots) {
      distributedLots.value = JSON.parse(JSON.stringify(props.lots));
      originalLots.value = JSON.parse(JSON.stringify(props.lots));
      tempIdCounter = 0;
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

const canSave = computed(() => {
  return discrepancy.value === 0;
});

const handleAddNewLot = () => {
  distributedLots.value.push({
    temp_id: `new_${tempIdCounter++}`,
    isNew: true,
    lot_number: "",
    expiration_date: "",
    quantity: 0,
  });
};

const handleRemoveNewLot = (tempId) => {
  distributedLots.value = distributedLots.value.filter(
    (lot) => lot.temp_id !== tempId
  );
};

const handleSave = () => {
  if (!canSave.value) return;

  const updatedLots = [];
  const newLots = [];

  for (const lot of distributedLots.value) {
    if (lot.isNew) {
      if (!lot.lot_number || !lot.expiration_date) {
        toast.error(
          "Por favor, complete el número de lote y la fecha de vencimiento para los nuevos lotes."
        );
        return;
      }
      newLots.push({
        lot_number: lot.lot_number,
        expiration_date: lot.expiration_date,
        quantity: Number(lot.quantity) || 0,
      });
    }
  }

  for (const currentLot of distributedLots.value) {
    if (!currentLot.isNew) {
      const originalLot = originalLots.value.find(
        (ol) => ol.id === currentLot.id
      );
      if (
        originalLot &&
        Number(originalLot.quantity) !== Number(currentLot.quantity)
      ) {
        updatedLots.push({
          id: currentLot.id,
          quantity: Number(currentLot.quantity),
        });
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
    max-width="800px"
    persistent
    scrollable
  >
    <VCard>
      <VCardTitle class="d-flex align-center pa-6 pb-4">
        <span class="text-h6">Ajustar Cantidad en Lotes</span>
        <VSpacer />
        <VBtn icon variant="text" @click="closeDialog">
          <VIcon>tabler-x</VIcon>
        </VBtn>
      </VCardTitle>

      <div class="px-6">
        <p class="text-body-1">
          Producto:
          <span class="font-weight-medium">{{ props.productName }}</span>
        </p>

        <VAlert
          :type="discrepancy !== 0 ? 'warning' : 'success'"
          variant="tonal"
          class="mb-4"
        >
          <div class="d-flex justify-space-between text-body-1 flex-wrap">
            <span class="mr-4"
              >Objetivo:
              <strong class="ml-1">{{ props.targetQuantity }}</strong></span
            >
            <span class="mr-4"
              >Total Actual:
              <strong class="ml-1">{{ totalDistributed }}</strong></span
            >
            <span
              >Ajuste Requerido:
              <strong
                class="ml-1"
                :class="{
                  'text-success': discrepancy > 0,
                  'text-error': discrepancy < 0,
                }"
              >
                {{ discrepancy >= 0 ? "+" : "" }}{{ discrepancy }}
              </strong>
            </span>
          </div>
        </VAlert>

        <div class="d-flex justify-end mb-4">
          <VBtn color="primary" variant="tonal" @click="handleAddNewLot">
            <VIcon icon="tabler-plus" start />
            Añadir Nuevo Lote
          </VBtn>
        </div>
      </div>

      <VCardText class="pt-0">
        <VDataTable
          :headers="[
            {
              title: 'Lote / Vencimiento',
              key: 'info',
              sortable: false,
              width: '45%',
            },
            {
              title: 'Stock Sistema',
              key: 'original_quantity',
              sortable: false,
              align: 'center',
            },
            {
              title: 'Cantidad Ajustada',
              key: 'quantity',
              sortable: false,
              width: '150px',
              align: 'center',
            },
            {
              title: 'Acciones',
              key: 'actions',
              sortable: false,
              align: 'center',
            },
          ]"
          :items="distributedLots"
          :item-value="(item) => (item.isNew ? item.temp_id : item.id)"
          density="compact"
          class="rounded-lg"
          no-data-text="Este producto no tiene lotes registrados."
        >
          <template #item.info="{ item }">
            <div v-if="!item.isNew" class="d-flex flex-column py-2">
              <span class="font-weight-medium">{{ item.lot_number }}</span>
              <span class="text-caption text-disabled">
                Exp: {{ new Date(item.expiration_date).toLocaleDateString() }}
              </span>
            </div>
            <div v-else class="d-flex flex-column ga-2 py-2">
              <VTextField
                v-model="item.lot_number"
                label="Número de Lote"
                variant="outlined"
                density="compact"
                hide-details="auto"
                placeholder="Lote123"
              />
              <VTextField
                v-model="item.expiration_date"
                label="Vencimiento"
                type="date"
                variant="outlined"
                density="compact"
                hide-details="auto"
              />
            </div>
          </template>

          <template #item.original_quantity="{ item }">
            <VChip v-if="!item.isNew" label size="small">
              {{ originalLots.find((l) => l.id === item.id)?.quantity || 0 }}
            </VChip>
            <VChip v-else label size="small" color="info">NUEVO</VChip>
          </template>

          <template #item.quantity="{ item }">
            <VTextField
              v-model.number="item.quantity"
              type="number"
              variant="outlined"
              density="compact"
              hide-details
              min="0"
              style="max-width: 120px"
            />
          </template>

          <template #item.actions="{ item }">
            <IconBtn
              v-if="item.isNew"
              @click="handleRemoveNewLot(item.temp_id)"
              color="error"
            >
              <VIcon icon="tabler-trash" />
              <VTooltip activator="parent" location="top">Quitar</VTooltip>
            </IconBtn>
          </template>
        </VDataTable>
      </VCardText>

      <VCardActions class="pa-6 pt-2">
        <VSpacer />
        <VBtn color="secondary" variant="outlined" @click="closeDialog"
          >Cancelar</VBtn
        >
        <VBtn
          color="primary"
          variant="elevated"
          @click="handleSave"
          :disabled="!canSave"
        >
          Guardar Ajuste
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
