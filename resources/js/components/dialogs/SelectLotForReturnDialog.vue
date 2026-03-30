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
    <VCard class="detail-dialog-card overflow-hidden border-0 elevation-12">
      <!-- Cabecera Compacta Premium -->
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-3 d-flex align-center shadow-sm">
          <div class="d-flex align-center">
            <VAvatar
              color="white"
              variant="flat"
              size="32"
              class="me-3 elevation-1"
            >
              <VIcon
                icon="tabler-package-export"
                color="primary"
                size="18"
              />
            </VAvatar>
            <div>
              <h2 class="text-subtitle-2 font-weight-black text-white leading-tight mb-0">
                Selección de Lote
              </h2>
              <span class="text-super-xs text-white opacity-75 uppercase font-weight-bold" style="font-size: 0.6rem">
                Devolución de producto
              </span>
            </div>
          </div>

          <VSpacer />
          <VBtn
            icon
            variant="tonal"
            color="white"
            size="x-small"
            @click="closeDialog"
          >
            <VIcon size="18">tabler-x</VIcon>
          </VBtn>
        </div>
      </VCardTitle>

      <VDivider />

      <VCardText class="pa-4 pa-sm-6 bg-light d-flex flex-column gap-4 overflow-y-auto" style="max-height: 80vh;">
        <!-- Información del Producto -->
        <VCard variant="flat" class="border pa-4 bg-white elevation-1 rounded-lg">
          <div class="d-flex align-center gap-2 mb-3">
            <div class="header-indicator primary"></div>
            <span class="text-xs font-weight-black text-primary uppercase letter-spacing-1">Producto a Devolver</span>
          </div>
          
          <div class="d-flex align-center gap-4">
            <div class="bg-var-theme-background pa-3 rounded-lg border border-primary border-opacity-25">
              <VIcon icon="tabler-pill" color="primary" size="24" />
            </div>
            <div>
              <div class="text-subtitle-2 font-weight-black text-high-emphasis leading-tight">
                {{ props.product?.name }}
              </div>
              <div class="text-xs text-secondary font-italic">
                {{ props.product?.active_ingredient || "Sin ingrediente activo definido" }}
              </div>
            </div>
          </div>
        </VCard>

        <!-- Tabla de Selección de Lotes -->
        <VCard variant="flat" class="border rounded-lg bg-white elevation-1 overflow-hidden">
          <div class="d-flex align-center pa-4 border-b bg-var-theme-background">
            <div class="d-flex align-center gap-2">
              <div class="header-indicator secondary"></div>
              <span class="text-xs font-weight-black text-secondary uppercase letter-spacing-1">Lotes Disponibles</span>
            </div>
          </div>

          <VDataTable
            v-model="selectedLotId"
            :items="lots"
            :loading="loading"
            item-value="id"
            show-select
            single-select
            class="premium-selection-table"
            :headers="[
              { title: 'N° Lote', key: 'lot_number', sortable: false },
              { title: 'Vencimiento', key: 'expiration_date', sortable: false },
              { title: 'Stock', key: 'quantity', sortable: false, align: 'center' },
              { title: 'Costo', key: 'unit_cost', sortable: false, align: 'end' },
              { title: 'Estado', key: 'is_expired', sortable: false, align: 'center' },
            ]"
            no-data-text="No hay lotes disponibles para este producto"
            density="comfortable"
          >
            <template #item.lot_number="{ item }">
              <span class="font-weight-black text-body-2">{{ item.lot_number || "N/A" }}</span>
            </template>

            <template #item.expiration_date="{ item }">
              <span class="text-body-2">{{ formatDate(item.expiration_date) }}</span>
            </template>

            <template #item.quantity="{ item }">
              <VChip size="x-small" label color="primary" variant="tonal" class="font-weight-bold">
                {{ item.quantity || 0 }}
              </VChip>
            </template>

            <template #item.unit_cost="{ item }">
              <span class="font-weight-medium text-body-2">${{ item.unit_cost?.toFixed(2) || "0.00" }}</span>
            </template>

            <template #item.is_expired="{ item }">
              <VChip
                :color="item.is_expired ? 'error' : 'success'"
                size="x-small"
                variant="flat"
                class="font-weight-black px-2 px-1"
                style="min-width: 70px;"
              >
                {{ item.is_expired ? "VENCIDO" : "VIGENTE" }}
              </VChip>
            </template>

            <template #loading>
              <div class="pa-8 text-center">
                <VProgressCircular indeterminate color="primary" size="32" width="2" />
                <div class="text-xs mt-2 text-disabled uppercase font-weight-bold letter-spacing-1">Cargando lotes...</div>
              </div>
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
              @click="handleConfirm"
              :disabled="!selectedLotId || selectedLotId.length === 0"
            >
              Confirmar Selección
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

.bg-light {
  background-color: #f8fafc !important;
}

.detail-dialog-card {
  border-radius: 12px !important;
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

.leading-tight {
  line-height: 1.25 !important;
}

.border-t {
  border-block-start: 1px solid rgba(var(--v-border-color), 0.08) !important;
}

/* Custom Table Styles */
.premium-selection-table :deep(table) {
  border-collapse: separate;
  border-spacing: 0;
}

.premium-selection-table :deep(.v-data-table-header) {
  background-color: #f8fafc;
}

.premium-selection-table :deep(.v-data-table-header th) {
  text-transform: uppercase;
  font-size: 0.65rem !important;
  font-weight: 900 !important;
  color: #64748b !important;
  letter-spacing: 0.5px;
  height: 44px !important;
}

.premium-selection-table :deep(tr:hover) {
  background-color: rgba(var(--v-theme-primary), 0.02) !important;
}
</style>

