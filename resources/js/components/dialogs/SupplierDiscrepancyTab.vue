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

const totalDiscrepancies = computed(
  () => (props.paidInErpPending?.length || 0) + (props.pendingInErpPaid?.length || 0)
);

const hasDiscrepancies = computed(() => totalDiscrepancies.value > 0);

const formatNumber = (value) => {
  return Number(value || 0).toLocaleString("es-VE", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
};
</script>

<template>
  <div class="supplier-discrepancy-tab">
    <!-- ── 1. Tarjetas de Resumen Rápido (Diseño Limpio y Neutro) ─────────── -->
    <VRow class="mb-4" dense>
      <VCol cols="6" sm="3">
        <VCard variant="outlined" class="pa-3 rounded-lg text-center h-100 bg-surface border">
          <div class="text-caption text-medium-emphasis">Documentos en Portal</div>
          <div class="text-h6 font-weight-bold text-high-emphasis mt-1">{{ props.summaryData.total_extracted || 0 }}</div>
        </VCard>
      </VCol>
      <VCol cols="6" sm="3">
        <VCard variant="outlined" class="pa-3 rounded-lg text-center h-100 bg-surface border">
          <div class="text-caption text-medium-emphasis">Actualizadas en ERP</div>
          <div class="text-h6 font-weight-bold text-high-emphasis mt-1">{{ props.summaryData.updated || 0 }}</div>
        </VCard>
      </VCol>
      <VCol cols="6" sm="3">
        <VCard variant="outlined" class="pa-3 rounded-lg text-center h-100 bg-surface border">
          <div class="text-caption text-medium-emphasis">Nuevas Creadas</div>
          <div class="text-h6 font-weight-bold text-high-emphasis mt-1">{{ props.summaryData.created || 0 }}</div>
        </VCard>
      </VCol>
      <VCol cols="6" sm="3">
        <VCard
          variant="outlined"
          :class="['pa-3 rounded-lg text-center h-100 border', hasDiscrepancies ? 'bg-warning-lighten-5 border-warning' : 'bg-surface']"
        >
          <div :class="['text-caption', hasDiscrepancies ? 'text-warning font-weight-medium' : 'text-medium-emphasis']">
            Diferencias Detectadas
          </div>
          <div :class="['text-h6 font-weight-bold mt-1', hasDiscrepancies ? 'text-warning' : 'text-high-emphasis']">
            {{ totalDiscrepancies }}
          </div>
        </VCard>
      </VCol>
    </VRow>

    <!-- ── 2. Discrepancia Caso 1: Pagadas en ERP pero aún PENDIENTES en Portal ── -->
    <VCard
      v-if="props.paidInErpPending.length > 0"
      variant="outlined"
      class="mb-4 rounded-lg border-warning bg-surface overflow-hidden shadow-xs"
    >
      <div class="d-flex align-center justify-space-between flex-wrap gap-2 px-4 py-2 bg-warning-lighten-5 border-b border-warning">
        <div class="d-flex align-center gap-2">
          <VIcon icon="tabler-alert-triangle" color="warning" size="18" />
          <span class="text-subtitle-2 font-weight-bold text-warning">
            Pagadas en ERP pero PENDIENTES en {{ props.supplierTitle }} ({{ props.paidInErpPending.length }})
          </span>
        </div>

        <VBtn
          color="warning"
          variant="flat"
          size="x-small"
          prepend-icon="tabler-arrow-back-up"
          class="rounded-lg shadow-sm font-weight-bold"
          :loading="props.isMarkingPending"
          @click="emit('mark-paid-as-pending', { supplierTitle: props.supplierTitle, items: props.paidInErpPending })"
        >
          Pasar a Por Pagar ({{ props.paidInErpPending.length }})
        </VBtn>
      </div>

      <VTable density="compact" class="bg-surface">
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
            <td class="font-weight-medium">#{{ item.invoice_number }}</td>
            <td class="text-medium-emphasis">{{ item.control_number || 'N/A' }}</td>
            <td class="text-right text-medium-emphasis">{{ formatNumber(item.amount) }} {{ item.currency }}</td>
            <td class="text-right font-weight-bold text-error">
              {{ formatNumber(item.portal_amount) }} {{ item.currency || 'Bs' }}
            </td>
            <td class="text-center">
              <span class="text-caption font-weight-medium text-warning">
                Por Cobrar ({{ item.portal_type || 'FA' }})
              </span>
            </td>
          </tr>
        </tbody>
      </VTable>
    </VCard>

    <!-- ── 3. Discrepancia Caso 2: Pendientes en ERP pero ya LIQUIDADAS en Portal ── -->
    <VCard
      v-if="props.pendingInErpPaid.length > 0"
      variant="outlined"
      class="mb-4 rounded-lg border-success bg-surface overflow-hidden shadow-xs"
    >
      <div class="d-flex align-center justify-space-between flex-wrap gap-2 px-4 py-2 bg-success-lighten-5 border-b border-success">
        <div class="d-flex align-center gap-2">
          <VIcon icon="tabler-circle-check" color="success" size="18" />
          <span class="text-subtitle-2 font-weight-bold text-success">
            Pendientes en ERP pero LIQUIDADAS en {{ props.supplierTitle }} ({{ props.pendingInErpPaid.length }})
          </span>
        </div>

        <VBtn
          color="success"
          variant="flat"
          size="x-small"
          prepend-icon="tabler-check-all"
          class="rounded-lg shadow-sm font-weight-bold"
          :loading="props.isMarkingPaid"
          @click="emit('mark-pending-as-paid', { supplierTitle: props.supplierTitle, items: props.pendingInErpPaid })"
        >
          Marcar Todas como Pagadas ({{ props.pendingInErpPaid.length }})
        </VBtn>
      </div>

      <VTable density="compact" class="bg-surface">
        <thead>
          <tr class="table-header-row">
            <th class="text-left font-weight-bold">N° Factura</th>
            <th class="text-left font-weight-bold">N° Control</th>
            <th class="text-right font-weight-bold">Monto ERP</th>
            <th class="text-center font-weight-bold">Estado ERP</th>
            <th class="text-center font-weight-bold">Estado Portal</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in props.pendingInErpPaid" :key="item.id">
            <td class="font-weight-medium">#{{ item.invoice_number }}</td>
            <td class="text-medium-emphasis">{{ item.control_number || 'N/A' }}</td>
            <td class="text-right font-weight-bold">
              {{ formatNumber(item.amount) }} {{ item.currency }}
            </td>
            <td class="text-center">
              <span class="text-caption text-error font-weight-medium">Por Pagar</span>
            </td>
            <td class="text-center">
              <span class="text-caption text-success font-weight-medium">Liquidada en Portal</span>
            </td>
          </tr>
        </tbody>
      </VTable>
    </VCard>

    <!-- ── 4. Estado Sin Discrepancias (Cuentas Cuadradas) ─────────────── -->
    <div v-if="!hasDiscrepancies" class="text-center py-4 mb-3 border rounded-lg bg-surface">
      <VAvatar color="success" variant="tonal" size="36" class="mb-2">
        <VIcon icon="tabler-check" size="20" color="success" />
      </VAvatar>
      <h3 class="text-subtitle-2 font-weight-bold mb-1">¡Cuentas {{ props.supplierTitle }} Cuadradas!</h3>
      <p class="text-caption text-medium-emphasis mb-0">
        No se encontraron discrepancias. Todas las facturas coinciden entre el portal y tu ERP.
      </p>
    </div>

    <!-- ── 5. Tabla de Facturas Procesadas / Actualizadas ───────────────── -->
    <div v-if="props.processedDetails && props.processedDetails.length > 0" class="mt-4 mb-2">
      <div class="d-flex align-center justify-space-between flex-wrap gap-2 mb-2">
        <h4 class="text-subtitle-2 font-weight-bold text-high-emphasis mb-0">
          Facturas Procesadas desde {{ props.supplierTitle }} ({{ props.processedDetails.length }})
        </h4>
      </div>

      <VTable density="compact" class="border rounded-lg bg-surface">
        <thead>
          <tr class="table-header-row">
            <th class="text-left font-weight-bold">N° Factura</th>
            <th class="text-left font-weight-bold">N° Control</th>
            <th class="text-center font-weight-bold">Vencimiento</th>
            <th class="text-center font-weight-bold">Index</th>
            <th class="text-right font-weight-bold">Total USD</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(item, idx) in props.processedDetails" :key="idx">
            <td class="font-weight-medium">#{{ item.invoice_number }}</td>
            <td class="text-medium-emphasis">{{ item.control_number || 'N/A' }}</td>
            <td class="text-center text-medium-emphasis">{{ item.exp_date || 'N/A' }}</td>
            <td class="text-center">
              <span class="text-caption font-weight-bold" :class="item.is_indexed ? 'text-primary' : 'text-disabled'">
                {{ item.is_indexed ? 'Sí' : 'No' }}
              </span>
            </td>
            <td class="text-right font-weight-bold">{{ formatNumber(item.total_usd) }} USD</td>
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
