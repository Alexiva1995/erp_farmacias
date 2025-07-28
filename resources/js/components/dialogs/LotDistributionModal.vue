<!-- components/dialogs/LotDistributionModal.vue -->
<script setup>
import { toast } from "@/plugins/sweetalert"; // Asegúrate de que esta ruta sea correcta
import { computed, ref, watch } from "vue";

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  productName: { type: String, default: "" },
  lots: { type: Array, default: () => [] },
  targetQuantity: { type: Number, required: true },
});

const emit = defineEmits(["update:modelValue", "save"]);

// Estado para los lotes que se muestran y editan en el modal
const distributedLots = ref([]);
// Guardamos una copia del estado original para comparar qué cambió
const originalLots = ref([]);

// Watcher para inicializar el estado cuando se abre el modal
watch(
  () => props.modelValue,
  (isOpening) => {
    if (isOpening && props.lots) {
      // Clonamos los lotes para hacerlos editables
      distributedLots.value = JSON.parse(JSON.stringify(props.lots));
      // Guardamos una copia del estado original para la comparación al guardar
      originalLots.value = JSON.parse(JSON.stringify(props.lots));
    }
  },
  { immediate: true }
);

// Calcula la suma total de las cantidades actuales
const totalDistributed = computed(() => {
  return distributedLots.value.reduce(
    (sum, lot) => sum + (Number(lot.quantity) || 0),
    0
  );
});

// Calcula la diferencia entre el objetivo y el total actual
const discrepancy = computed(() => {
  return props.targetQuantity - totalDistributed.value;
});

// Determina si se puede guardar: el total debe ser exacto
const canSave = computed(() => {
  return discrepancy.value === 0;
});

/**
 * Lógica de guardado que cumple con la regla de "solo un lote modificado".
 */
const handleSave = () => {
  if (!canSave.value) return;

  // 1. Encontrar los lotes que realmente cambiaron
  const changedLots = distributedLots.value.filter((currentLot) => {
    const originalLot = originalLots.value.find(
      (ol) => ol.id === currentLot.id
    );
    // Un lote se considera "cambiado" si su cantidad es diferente a la original.
    return (
      !originalLot ||
      Number(originalLot.quantity) !== Number(currentLot.quantity)
    );
  });

  // 2. Validar según la regla de negocio
  if (changedLots.length > 1) {
    toast.error("Por favor, modifica solo un lote para ajustar la diferencia.");
    return; // No continuar si se editó más de uno
  }

  if (changedLots.length === 0) {
    // El total cuadraba pero el usuario no modificó ninguna cantidad.
    // Esto puede pasar si el stock ya era correcto. Simplemente cerramos.
    toast.info("No se realizaron cambios en las cantidades de los lotes.");
    closeDialog();
    return;
  }

  // 3. Si llegamos aquí, exactamente un lote fue modificado.
  const modifiedLot = changedLots[0];

  // 4. Preparar y emitir el payload con el único lote modificado
  const payload = {
    lot_id: modifiedLot.id,
    quantity: Number(modifiedLot.quantity),
  };

  emit("save", payload);
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
            <span class="mr-4">
              Objetivo:
              <strong class="ml-1">{{ props.targetQuantity }}</strong>
            </span>
            <span class="mr-4">
              Total Actual:
              <strong class="ml-1">{{ totalDistributed }}</strong>
            </span>
            <span>
              Ajuste Requerido:
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
          <div v-if="!canSave" class="mt-2 text-caption">
            Modifica la cantidad de **un solo lote** hasta que el "Total Actual"
            sea igual al "Objetivo".
          </div>
          <div v-else class="mt-2 text-caption text-success">
            ¡Cantidades cuadradas! Puedes guardar el ajuste.
          </div>
        </VAlert>
      </div>

      <VCardText class="pt-0">
        <VDataTable
          :headers="[
            { title: '# Lote', key: 'lot_number', sortable: false },
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
              width: '200px',
              align: 'center',
            },
          ]"
          :items="distributedLots"
          density="compact"
          class="rounded-lg"
          no-data-text="Este producto no tiene lotes registrados."
        >
          <template #item.lot_number="{ item }">
            <div class="d-flex flex-column py-2">
              <span class="font-weight-medium">{{ item.lot_number }}</span>
              <span class="text-caption text-disabled">
                Exp: {{ new Date(item.expiration_date).toLocaleDateString() }}
              </span>
            </div>
          </template>

          <template #item.original_quantity="{ item }">
            <VChip label size="small">
              {{ originalLots.find((l) => l.id === item.id)?.quantity || 0 }}
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
            />
          </template>
        </VDataTable>
      </VCardText>

      <VCardActions class="pa-6 pt-2">
        <VSpacer />
        <VBtn color="secondary" variant="outlined" @click="closeDialog">
          Cancelar
        </VBtn>
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
