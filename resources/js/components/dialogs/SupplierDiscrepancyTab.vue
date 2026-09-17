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

// Detectar si el bot de la droguería tuvo conexión exitosa (extrajo facturas, actualizó o reportó 0)
const isConnectedOrProcessed = computed(() => {
  if (hasDiscrepancies.value) return true;
  if (props.processedDetails && props.processedDetails.length > 0) return true;
  const summary = props.summaryData || {};
  if (summary.success === true) return true;
  if (Number(summary.total_extracted || 0) > 0 || Number(summary.updated || 0) > 0 || Number(summary.created || 0) > 0) return true;
  if (summary.message && (summary.message.toLowerCase().includes('0 factura') || summary.message.toLowerCase().includes('sin factura') || summary.message.toLowerCase().includes('correct'))) return true;
  return false;
});

const isConnectionFailed = computed(() => {
  const summary = props.summaryData || {};
  if (summary.success === false || summary.error) return true;
  return !isConnectedOrProcessed.value;
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
    <!-- ── 1. Tarjetas de Resumen Rápido (Estilo Estándar del ERP con Avatar Tonal e Ícono) ── -->
    <VRow class="mb-4" dense>
      <VCol cols="12" sm="6" md="3">
        <VCard variant="flat" border class="pa-3 rounded-lg bg-surface h-100 shadow-xs">
          <div class="d-flex align-center gap-3">
            <VAvatar color="primary" variant="tonal" size="40" rounded="lg" class="flex-shrink-0">
              <VIcon icon="tabler-files" size="22" />
            </VAvatar>
            <div class="overflow-hidden">
              <span class="text-caption text-medium-emphasis font-weight-medium d-block leading-tight text-truncate">
                En Portal
              </span>
              <div class="text-h6 font-weight-black text-high-emphasis leading-tight mt-1">
                {{ props.summaryData.total_extracted || 0 }}
              </div>
            </div>
          </div>
        </VCard>
      </VCol>

      <VCol cols="12" sm="6" md="3">
        <VCard variant="flat" border class="pa-3 rounded-lg bg-surface h-100 shadow-xs">
          <div class="d-flex align-center gap-3">
            <VAvatar color="success" variant="tonal" size="40" rounded="lg" class="flex-shrink-0">
              <VIcon icon="tabler-refresh" size="22" />
            </VAvatar>
            <div class="overflow-hidden">
              <span class="text-caption text-medium-emphasis font-weight-medium d-block leading-tight text-truncate">
                Actualizadas
              </span>
              <div class="text-h6 font-weight-black text-high-emphasis leading-tight mt-1">
                {{ props.summaryData.updated || 0 }}
              </div>
            </div>
          </div>
        </VCard>
      </VCol>

      <VCol cols="12" sm="6" md="3">
        <VCard variant="flat" border class="pa-3 rounded-lg bg-surface h-100 shadow-xs">
          <div class="d-flex align-center gap-3">
            <VAvatar color="info" variant="tonal" size="40" rounded="lg" class="flex-shrink-0">
              <VIcon icon="tabler-file-plus" size="22" />
            </VAvatar>
            <div class="overflow-hidden">
              <span class="text-caption text-medium-emphasis font-weight-medium d-block leading-tight text-truncate">
                Nuevas Creadas
              </span>
              <div class="text-h6 font-weight-black text-high-emphasis leading-tight mt-1">
                {{ props.summaryData.created || 0 }}
              </div>
            </div>
          </div>
        </VCard>
      </VCol>

      <VCol cols="12" sm="6" md="3">
        <VCard
          variant="flat"
          border
          :class="[
            'pa-3 rounded-lg h-100 shadow-xs',
            hasDiscrepancies ? 'bg-warning-lighten-5 border-warning' : (isConnectionFailed ? 'bg-surface' : 'bg-surface')
          ]"
        >
          <div class="d-flex align-center gap-3">
            <VAvatar
              :color="hasDiscrepancies ? 'warning' : (isConnectionFailed ? 'secondary' : 'success')"
              variant="tonal"
              size="40"
              rounded="lg"
              class="flex-shrink-0"
            >
              <VIcon
                :icon="hasDiscrepancies ? 'tabler-alert-triangle' : (isConnectionFailed ? 'tabler-cloud-off' : 'tabler-circle-check')"
                size="22"
              />
            </VAvatar>
            <div class="overflow-hidden">
              <span :class="['text-caption font-weight-medium d-block leading-tight text-truncate', hasDiscrepancies ? 'text-warning' : 'text-medium-emphasis']">
                {{ isConnectionFailed ? 'Conexión' : 'Diferencias' }}
              </span>
              <div :class="['text-h6 font-weight-black leading-tight mt-1', hasDiscrepancies ? 'text-warning' : (isConnectionFailed ? 'text-medium-emphasis text-body-2' : 'text-high-emphasis')]">
                {{ isConnectionFailed ? 'Sin Conectar' : totalDiscrepancies }}
              </div>
            </div>
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
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in props.paidInErpPending" :key="item.id">
            <td class="font-weight-bold text-primary">{{ item.invoice_number }}</td>
            <td class="text-medium-emphasis">{{ item.control_number || 'N/A' }}</td>
            <td class="text-right text-medium-emphasis">{{ formatNumber(item.amount) }} {{ item.currency }}</td>
            <td class="text-right font-weight-bold text-error">
              {{ formatNumber(item.portal_amount) }} {{ item.currency || 'Bs' }}
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
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in props.pendingInErpPaid" :key="item.id">
            <td class="font-weight-bold text-primary">{{ item.invoice_number }}</td>
            <td class="text-medium-emphasis">{{ item.control_number || 'N/A' }}</td>
            <td class="text-right font-weight-bold">
              {{ formatNumber(item.amount) }} {{ item.currency }}
            </td>
            <td class="text-center">
              <span class="text-caption text-error font-weight-medium">Por Pagar</span>
            </td>
          </tr>
        </tbody>
      </VTable>
    </VCard>

    <!-- ── 4. Estado Sin Discrepancias / Sin Conexión ─────────────── -->
    <div v-if="!hasDiscrepancies && isConnectedOrProcessed" class="text-center py-4 mb-3 border rounded-lg bg-surface">
      <VAvatar color="success" variant="tonal" size="36" class="mb-2">
        <VIcon icon="tabler-check" size="20" color="success" />
      </VAvatar>
      <h3 class="text-subtitle-2 font-weight-bold mb-1">¡Cuentas {{ props.supplierTitle }} Cuadradas!</h3>
      <p class="text-caption text-medium-emphasis mb-0">
        No se encontraron discrepancias. Todas las facturas coinciden entre el portal y tu ERP.
      </p>
    </div>

    <!-- Si no hubo conexión o falló el scraper de este proveedor -->
    <div v-else-if="!hasDiscrepancies && isConnectionFailed" class="text-center py-4 mb-3 border rounded-lg bg-surface">
      <VAvatar color="secondary" variant="tonal" size="36" class="mb-2">
        <VIcon icon="tabler-cloud-off" size="20" color="secondary" />
      </VAvatar>
      <h3 class="text-subtitle-2 font-weight-bold mb-1">Sin Conexión con {{ props.supplierTitle }}</h3>
      <p class="text-caption text-medium-emphasis mb-0">
        No se pudo establecer comunicación con el portal o no se obtuvieron registros en esta sincronización.
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
            <td class="font-weight-bold text-primary">{{ item.invoice_number }}</td>
            <td class="text-medium-emphasis">{{ item.control_number || 'N/A' }}</td>
            <td class="text-center text-medium-emphasis">{{ item.exp_date || 'N/A' }}</td>
            <td class="text-center">
              <VChip
                :color="item.is_indexed ? 'error' : 'success'"
                variant="tonal"
                size="small"
                class="font-weight-black px-2"
              >
                {{ item.is_indexed ? 'Sí' : 'No' }}
              </VChip>
            </td>
            <td class="text-right font-weight-bold text-error">{{ formatNumber(item.total_usd) }} USD</td>
          </tr>
        </tbody>
      </VTable>
    </div>

    <!-- Si no hay facturas procesadas ni discrepancias en droguería conectada con 0 facturas -->
    <div
      v-if="!hasDiscrepancies && isConnectedOrProcessed && (!props.processedDetails || props.processedDetails.length === 0)"
      class="text-center py-4"
    >
      <p class="text-caption text-medium-emphasis mb-0">
        Sincronización ejecutada correctamente. No se reportaron facturas pendientes ni modificaciones adicionales.
      </p>
    </div>
  </div>
</template>
