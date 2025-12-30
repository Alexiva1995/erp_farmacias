<script setup>
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { ref, watch } from "vue";

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  product: { type: Object, default: () => ({}) },
});

const emit = defineEmits(["update:modelValue", "select-lot"]);

const lots = ref([]);
const selectedLotId = ref([]);
const loading = ref(false);

const fetchLots = async () => {
  if (!props.product?.id) return;

  loading.value = true;
  try {
    const response = await axios.get(`/tpv/returns/product/${props.product.id}/lots`);
    lots.value = response.data.lots || [];
  } catch (error) {
    console.error("Error al cargar los lotes:", error);
    toast.error("Error al cargar los lotes del producto.");
  } finally {
    loading.value = false;
  }
};

watch(
  () => props.modelValue,
  (isVisible) => {
    if (isVisible) {
      selectedLotId.value = [];
      fetchLots();
    }
  }
);

const closeDialog = () => {
  emit("update:modelValue", false);
  selectedLotId.value = [];
};

const handleConfirm = () => {
  if (!selectedLotId.value || selectedLotId.value.length === 0) {
    toast.warning("Por favor, seleccione un lote.");
    return;
  }

  // Con single-select, selectedLotId es un array con un solo elemento
  const lotId = Array.isArray(selectedLotId.value) ? selectedLotId.value[0] : selectedLotId.value;
  const selectedLot = lots.value.find((lot) => lot.id === lotId);
  
  if (!selectedLot) {
    toast.error("No se pudo encontrar el lote seleccionado.");
    return;
  }
  
  emit("select-lot", selectedLot);
  closeDialog();
};

const formatDate = (dateString) => {
  if (!dateString) return "Sin fecha";
  return new Date(dateString).toLocaleDateString("es-ES");
};
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    max-width="700px"
    persistent
    @update:model-value="closeDialog"
  >
    <VCard>
      <VCardTitle class="d-flex align-center pa-6">
        <span class="text-h5 font-weight-bold">
          Seleccionar Lote para Devolución
        </span>
        <VSpacer />
        <VBtn icon variant="text" @click="closeDialog">
          <VIcon>tabler-x</VIcon>
        </VBtn>
      </VCardTitle>

      <VDivider />

      <VCardText class="pa-6">
        <div class="mb-4">
          <p class="text-body-1 font-weight-medium mb-1">
            Producto: {{ props.product?.name }}
          </p>
          <p class="text-body-2 text-medium-emphasis">
            {{ props.product?.active_ingredient }}
          </p>
        </div>

        <VProgressCircular v-if="loading" indeterminate color="primary" />

        <VDataTable
          v-else
          v-model="selectedLotId"
          :items="lots"
          item-value="id"
          show-select
          single-select
          class="elevation-1"
          :headers="[
            { title: 'N° Lote', key: 'lot_number' },
            { title: 'Fecha Expiración', key: 'expiration_date' },
            { title: 'Cantidad', key: 'quantity' },
            { title: 'Costo Unitario', key: 'unit_cost' },
            { title: 'Estado', key: 'is_expired' },
          ]"
          no-data-text="No hay lotes disponibles para este producto"
        >
          <template #item.lot_number="{ item }">
            <span class="font-weight-medium">{{ item.lot_number || "N/A" }}</span>
          </template>

          <template #item.expiration_date="{ item }">
            <span>{{ formatDate(item.expiration_date) }}</span>
          </template>

          <template #item.quantity="{ item }">
            <span class="font-weight-medium">{{ item.quantity || 0 }}</span>
          </template>

          <template #item.unit_cost="{ item }">
            <span>${{ item.unit_cost?.toFixed(2) || "0.00" }}</span>
          </template>

          <template #item.is_expired="{ item }">
            <VChip
              :color="item.is_expired ? 'error' : 'success'"
              size="small"
              variant="tonal"
            >
              {{ item.is_expired ? "Vencido" : "Vigente" }}
            </VChip>
          </template>
        </VDataTable>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-6">
        <VBtn
          color="secondary"
          variant="outlined"
          @click="closeDialog"
          class="flex-grow-1 w-0 mr-4"
          size="large"
        >
          Cancelar
        </VBtn>
        <VBtn
          color="primary"
          variant="flat"
          @click="handleConfirm"
          class="flex-grow-1 w-0"
          size="large"
          :disabled="!selectedLotId || selectedLotId.length === 0"
        >
          Confirmar
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

