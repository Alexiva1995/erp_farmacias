<script setup lang="js">
// Subcomponente modular para pestaña de discrepancias por droguería
import { computed, ref } from "vue";

const props = defineProps({
  supplierKey: {
    type: String,
    required: true,
  },
  supplierTitle: {
    type: String,
    required: true,
  },
  supplierIcon: {
    type: String,
    default: "tabler-building-warehouse",
  },
  summaryData: {
    type: Object,
    default: () => ({}),
  },
  paidInErpPending: {
    type: Array,
    default: () => [],
  },
  pendingInErpPaid: {
    type: Array,
    default: () => [],
  },
  processedDetails: {
    type: Array,
    default: () => [],
  },
  isMarkingPaid: {
    type: Boolean,
    default: false,
  },
  isMarkingPending: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits([
  "mark-pending-as-paid",
  "mark-paid-as-pending",
]);

const searchFilter = ref("");

const totalDiscrepancies = computed(
  () => (props.paidInErpPending?.length || 0) + (props.pendingInErpPaid?.length || 0)
);

const hasDiscrepancies = computed(() => totalDiscrepancies.value > 0);

const filteredDetails = computed(() => {
  const query = searchFilter.value.trim().toLowerCase();
  if (!query) return props.processedDetails;
  return props.processedDetails.filter(
    (item) =>
      String(item.invoice_number || "").toLowerCase().includes(query) ||
      String(item.control_number || "").toLowerCase().includes(query)
  );
});

const formatNumber = (value) => {
  return Number(value || 0).toLocaleString("es-VE", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
};
</script>

<template>
  <div class="supplier-discrepancy-tab">
    <!-- ── 1. Tarjetas de Resumen Rápido (12 cols responsive) ─────────── -->
    <VRow class="mb-4">
      <VCol cols="12" sm="6" md="3">
        <VCard variant="tonal" color="info" class="pa-3 rounded-lg text-center h-100">
          <div class="text-caption text-medium-emphasis">Documentos en Portal</div>
          <div class="text-h5 font-weight-bold">{{ props.summaryData.total_extracted || 0 }}</div>
        </VCard>
      </VCol>
      <VCol cols="12" sm="6" md="3">
        <VCard variant="tonal" color="success" class="pa-3 rounded-lg text-center h-100">
          <div class="text-caption text-medium-emphasis">Actualizadas en ERP</div>
          <div class="text-h5 font-weight-bold">{{ props.summaryData.updated || 0 }}</div>
        </VCard>
      </VCol>
      <VCol cols="12" sm="6" md="3">
        <VCard variant="tonal" color="primary" class="pa-3 rounded-lg text-center h-100">
          <div class="text-caption text-medium-emphasis">Nuevas Creadas</div>
          <div class="text-h5 font-weight-bold">{{ props.summaryData.created || 0 }}</div>
        </VCard>
      </VCol>
      <VCol cols="12" sm="6" md="3">
        <VCard
          variant="tonal"
          :color="hasDiscrepancies ? 'warning' : 'success'"
          class="pa-3 rounded-lg text-center h-100"
        >
          <div class="text-caption text-medium-emphasis">Diferencias Detectadas</div>
          <div class="text-h5 font-weight-bold">
            {{ totalDiscrepancies }}
          </div>
        </VCard>
      </VCol>
    </VRow>

    <!-- ── 2. Discrepancia Caso 1: Pagadas en ERP pero aún PENDIENTES en Portal ── -->
    <div v-if="props.paidInErpPending.length > 0" class="mb-6">
      <div class="d-flex align-center justify-space-between flex-wrap gap-2 mb-2">
        <div class="d-flex align-center gap-2">
          <VIcon icon="tabler-alert-circle" color="warning" size="22" />
          <h4 class="text-subtitle-1 font-weight-bold text-warning mb-0">
            Pagadas en el ERP pero aún PENDIENTES en {{ props.supplierTitle }} ({{ props.paidInErpPending.length }})
          </h4>
        </div>

        <VBtn
          color="warning"
          variant="elevated"
          size="small"
          prepend-icon="tabler-arrow-back-up"
          class="rounded-lg shadow-sm font-weight-bold"
          :loading="props.isMarkingPending"
          @click="emit('mark-paid-as-pending', { supplierTitle: props.supplierTitle, items: props.paidInErpPending })"
        >
          Pasar a Por Pagar ({{ props.paidInErpPending.length }})
        </VBtn>
      </div>

      <p class="text-caption text-medium-emphasis mb-3">
        Estas facturas ya las registraste como pagadas en el ERP, pero en el portal de {{ props.supplierTitle }} todavía aparecen con saldo pendiente por cobrar:
      </p>

      <VTable density="compact" class="border rounded-lg mb-2 max-h-300 overflow-y-auto">
        <thead>
          <tr class="table-header-row">
            <th class="text-left font-weight-bold">N° Factura</th>
            <th class="text-left font-weight-bold">N° Control</th>
            <th class="text-right font-weight-bold">Monto ERP</th>
            <th class="text-right font-weight-bold">Saldo Portal</th>
            <th class="text-center font-weight-bold">Estado Portal</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in props.paidInErpPending" :key="item.id">
            <td class="font-weight-medium text-primary">#{{ item.invoice_number }}</td>
            <td>{{ item.control_number || 'N/A' }}</td>
            <td class="text-right">{{ formatNumber(item.amount) }} {{ item.currency }}</td>
            <td class="text-right font-weight-bold text-error">
              {{ formatNumber(item.portal_amount) }} {{ item.currency || 'Bs' }}
            </td>
            <td class="text-center">
              <VChip size="x-small" color="warning" variant="tonal" class="font-weight-medium">
                Por Cobrar ({{ item.portal_type || 'FA' }})
              </VChip>
            </td>
          </tr>
        </tbody>
      </VTable>
    </div>

    <!-- ── 3. Discrepancia Caso 2: Pendientes en ERP pero ya LIQUIDADAS en Portal ── -->
    <div v-if="props.pendingInErpPaid.length > 0" class="mb-6">
      <div class="d-flex align-center justify-space-between flex-wrap gap-2 mb-2">
        <div class="d-flex align-center gap-2">
          <VIcon icon="tabler-circle-check" color="info" size="22" />
          <h4 class="text-subtitle-1 font-weight-bold text-info mb-0">
            Pendientes en ERP pero LIQUIDADAS en {{ props.supplierTitle }} ({{ props.pendingInErpPaid.length }})
          </h4>
        </div>

        <VBtn
          color="success"
          variant="elevated"
          size="small"
          prepend-icon="tabler-check-all"
          class="rounded-lg shadow-sm font-weight-bold"
          :loading="props.isMarkingPaid"
          @click="emit('mark-pending-as-paid', { supplierTitle: props.supplierTitle, items: props.pendingInErpPaid })"
        >
          Marcar Todas como Pagadas ({{ props.pendingInErpPaid.length }})
        </VBtn>
      </div>

      <p class="text-caption text-medium-emphasis mb-3">
        Estas facturas están pendientes en tu ERP pero en {{ props.supplierTitle }} ya fueron cobradas/liquidadas. Puedes pasarlas a estado Pagadas directamente:
      </p>

      <VTable density="compact" class="border rounded-lg mb-2 max-h-300 overflow-y-auto">
        <thead>
          <tr class="table-header-row">
            <th class="text-left font-weight-bold">N° Factura</th>
            <th class="text-left font-weight-bold">N° Control</th>
            <th class="text-right font-weight-bold">Monto en ERP</th>
            <th class="text-center font-weight-bold">Estado en ERP</th>
            <th class="text-center font-weight-bold">Estado Portal</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in props.pendingInErpPaid" :key="item.id">
            <td class="font-weight-medium text-primary">#{{ item.invoice_number }}</td>
            <td>{{ item.control_number || 'N/A' }}</td>
            <td class="text-right font-weight-bold">
              {{ formatNumber(item.amount) }} {{ item.currency }}
            </td>
            <td class="text-center">
              <VChip size="x-small" color="error" variant="tonal">Por Pagar</VChip>
            </td>
            <td class="text-center">
              <VChip size="x-small" color="success" variant="tonal">Liquidada en Portal</VChip>
            </td>
          </tr>
        </tbody>
      </VTable>
    </div>

    <!-- ── 4. Estado Sin Discrepancias (Cuentas Cuadradas) ─────────────── -->
    <div v-if="!hasDiscrepancies" class="text-center py-4 mb-4">
      <VAvatar color="success" variant="tonal" size="48" class="mb-2">
        <VIcon icon="tabler-check" size="28" color="success" />
      </VAvatar>
      <h3 class="text-subtitle-1 font-weight-bold mb-1">¡Cuentas {{ props.supplierTitle }} Cuadradas!</h3>
      <p class="text-caption text-medium-emphasis mb-0">
        No se encontraron discrepancias en {{ props.supplierTitle }}. Todas las facturas coinciden entre el portal y tu ERP.
      </p>
    </div>

    <!-- ── 5. Tabla de Facturas Procesadas / Actualizadas ───────────────── -->
    <div v-if="props.processedDetails && props.processedDetails.length > 0" class="mt-2 mb-6">
      <div class="d-flex align-center justify-space-between flex-wrap gap-2 mb-2">
        <h4 class="text-subtitle-1 font-weight-bold mb-0">
          Facturas Procesadas desde {{ props.supplierTitle }} ({{ props.processedDetails.length }})
        </h4>
        <div v-if="props.processedDetails.length > 10" style="max-width: 250px;">
          <VTextField
            v-model="searchFilter"
            density="compact"
            placeholder="Buscar factura o control..."
            prepend-inner-icon="tabler-search"
            hide-details
            clearable
          />
        </div>
      </div>

      <VTable density="compact" class="border rounded-lg mb-2 max-h-350 overflow-y-auto">
        <thead>
          <tr class="table-header-row">
            <th class="text-left font-weight-bold">N° Factura</th>
            <th class="text-left font-weight-bold">N° Control</th>
            <th class="text-center font-weight-bold">Acción</th>
            <th class="text-center font-weight-bold">Vencimiento</th>
            <th class="text-center font-weight-bold">Indexada</th>
            <th class="text-right font-weight-bold">Total USD</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(item, idx) in filteredDetails" :key="idx">
            <td class="font-weight-medium text-primary">#{{ item.invoice_number }}</td>
            <td>{{ item.control_number || 'N/A' }}</td>
            <td class="text-center">
              <VChip size="x-small" :color="item.action === 'created' ? 'success' : 'info'" variant="tonal">
                {{ item.action === 'created' ? 'Nueva Creada' : 'Actualizada' }}
              </VChip>
            </td>
            <td class="text-center">{{ item.exp_date || 'N/A' }}</td>
            <td class="text-center">
              <VChip size="x-small" :color="item.is_indexed ? 'error' : 'secondary'" variant="tonal">
                {{ item.is_indexed ? 'Sí' : 'No' }}
              </VChip>
            </td>
            <td class="text-right font-weight-bold">${{ formatNumber(item.total_usd) }}</td>
          </tr>
        </tbody>
      </VTable>
    </div>

    <!-- Si no hay facturas procesadas ni discrepancias en droguería sin portal de estado de cuenta -->
    <div
      v-if="!hasDiscrepancies && (!props.processedDetails || props.processedDetails.length === 0)"
      class="text-center py-4"
    >
      <p class="text-caption text-medium-emphasis mb-0">
        Sincronización ejecutada correctamente. No se reportaron facturas pendientes ni modificaciones adicionales.
      </p>
    </div>
  </div>
</template>

<style scoped>
.max-h-300 {
  max-height: 300px;
}
.max-h-350 {
  max-height: 350px;
}
</style>
