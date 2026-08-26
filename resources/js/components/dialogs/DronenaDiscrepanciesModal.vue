<script setup>
import { computed } from "vue";

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  discrepancies: {
    type: Object,
    default: () => ({
      paid_in_erp_pending_in_dronena: [],
      pending_in_erp_paid_in_dronena: [],
      total_discrepancies: 0,
    }),
  },
  syncSummary: {
    type: Object,
    default: () => ({
      updated: 0,
      skipped: 0,
      total_extracted: 0,
    }),
  },
});

const emit = defineEmits(["update:modelValue", "close"]);

const paidInErpPendingInPortal = computed(
  () => props.discrepancies?.paid_in_erp_pending_in_dronena || []
);
const pendingInErpPaidInPortal = computed(
  () => props.discrepancies?.pending_in_erp_paid_in_dronena || []
);
const hasDiscrepancies = computed(
  () => (paidInErpPendingInPortal.value.length + pendingInErpPaidInPortal.value.length) > 0
);

const closeDialog = () => {
  emit("update:modelValue", false);
  emit("close");
};
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    max-width="850"
    persistent
    @update:model-value="emit('update:modelValue', $event)"
  >
    <VCard class="rounded-xl overflow-hidden shadow-2xl">
      <!-- Encabezado -->
      <VCardItem class="bg-primary py-4 px-6 text-white">
        <div class="d-flex align-center justify-space-between">
          <div class="d-flex align-center gap-3">
            <VAvatar color="white" variant="tonal" size="42" class="rounded-lg">
              <VIcon icon="tabler-scale" size="24" color="white" />
            </VAvatar>
            <div>
              <VCardTitle class="text-white text-h6 font-weight-bold mb-0">
                Resultado de Sincronización con Dronena
              </VCardTitle>
              <VCardSubtitle class="text-white opacity-80 text-caption">
                Comparativa de estado de facturas entre el ERP y el portal
              </VCardSubtitle>
            </div>
          </div>
          <VBtn icon variant="text" color="white" size="small" @click="closeDialog">
            <VIcon icon="tabler-x" size="20" />
          </VBtn>
        </div>
      </VCardItem>

      <VCardText class="pa-6">
        <!-- Resumen Rápido -->
        <VRow class="mb-4">
          <VCol cols="12" sm="4">
            <VCard variant="tonal" color="info" class="pa-3 rounded-lg text-center">
              <div class="text-caption text-medium-emphasis">Documentos en Portal</div>
              <div class="text-h5 font-weight-bold">{{ props.syncSummary?.total_extracted || 0 }}</div>
            </VCard>
          </VCol>
          <VCol cols="12" sm="4">
            <VCard variant="tonal" color="success" class="pa-3 rounded-lg text-center">
              <div class="text-caption text-medium-emphasis">Actualizadas en ERP</div>
              <div class="text-h5 font-weight-bold">{{ props.syncSummary?.updated || 0 }}</div>
            </VCard>
          </VCol>
          <VCol cols="12" sm="4">
            <VCard
              variant="tonal"
              :color="hasDiscrepancies ? 'warning' : 'success'"
              class="pa-3 rounded-lg text-center"
            >
              <div class="text-caption text-medium-emphasis">Diferencias Detectadas</div>
              <div class="text-h5 font-weight-bold">
                {{ (paidInErpPendingInPortal.length + pendingInErpPaidInPortal.length) }}
              </div>
            </VCard>
          </VCol>
        </VRow>

        <!-- Caso 1: Pagadas en ERP pero aún figuran PENDIENTES en Dronena -->
        <div v-if="paidInErpPendingInPortal.length > 0" class="mb-6">
          <div class="d-flex align-center gap-2 mb-2">
            <VIcon icon="tabler-alert-circle" color="warning" size="20" />
            <h4 class="text-subtitle-1 font-weight-bold text-warning mb-0">
              Pagadas en el ERP pero aún PENDIENTES en Dronena ({{ paidInErpPendingInPortal.length }})
            </h4>
          </div>
          <p class="text-caption text-medium-emphasis mb-3">
            Estas facturas ya las registraste como pagadas en el ERP, pero en el portal de Dronena todavía aparecen con saldo pendiente por cobrar:
          </p>

          <VTable density="compact" class="border rounded-lg">
            <thead>
              <tr class="bg-light">
                <th class="text-left font-weight-bold">N° Factura</th>
                <th class="text-left font-weight-bold">N° Control</th>
                <th class="text-right font-weight-bold">Monto ERP</th>
                <th class="text-right font-weight-bold">Saldo en Dronena</th>
                <th class="text-center font-weight-bold">Estado Portal</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in paidInErpPendingInPortal" :key="item.id">
                <td class="font-weight-medium text-primary">#{{ item.invoice_number }}</td>
                <td>{{ item.control_number || 'N/A' }}</td>
                <td class="text-right">{{ Number(item.amount).toLocaleString('es-VE', { minimumFractionDigits: 2 }) }} {{ item.currency }}</td>
                <td class="text-right font-weight-bold text-error">
                  {{ Number(item.portal_amount).toLocaleString('es-VE', { minimumFractionDigits: 2 }) }} Bs
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

        <!-- Caso 2: Pendientes en ERP pero ya NO figuran pendientes en Dronena -->
        <div v-if="pendingInErpPaidInPortal.length > 0" class="mb-6">
          <div class="d-flex align-center gap-2 mb-2">
            <VIcon icon="tabler-circle-check" color="info" size="20" />
            <h4 class="text-subtitle-1 font-weight-bold text-info mb-0">
              Pendientes en ERP pero LIQUIDADAS en Dronena ({{ pendingInErpPaidInPortal.length }})
            </h4>
          </div>
          <p class="text-caption text-medium-emphasis mb-3">
            Estas facturas están pendientes de pago en tu ERP, pero en Dronena ya no tienen saldo por pagar (ya fueron procesadas o cobradas):
          </p>

          <VTable density="compact" class="border rounded-lg">
            <thead>
              <tr class="bg-light">
                <th class="text-left font-weight-bold">N° Factura</th>
                <th class="text-left font-weight-bold">N° Control</th>
                <th class="text-right font-weight-bold">Monto en ERP</th>
                <th class="text-center font-weight-bold">Estado en ERP</th>
                <th class="text-center font-weight-bold">Estado Portal</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in pendingInErpPaidInPortal" :key="item.id">
                <td class="font-weight-medium text-primary">#{{ item.invoice_number }}</td>
                <td>{{ item.control_number || 'N/A' }}</td>
                <td class="text-right font-weight-bold">
                  {{ Number(item.amount).toLocaleString('es-VE', { minimumFractionDigits: 2 }) }} {{ item.currency }}
                </td>
                <td class="text-center">
                  <VChip size="x-small" color="error" variant="tonal">Por Pagar</VChip>
                </td>
                <td class="text-center">
                  <VChip size="x-small" color="success" variant="tonal">Liquidada en Dronena</VChip>
                </td>
              </tr>
            </tbody>
          </VTable>
        </div>

        <!-- Estado Perfecto Sin Discrepancias -->
        <div v-if="!hasDiscrepancies" class="text-center py-6">
          <VAvatar color="success" variant="tonal" size="56" class="mb-3">
            <VIcon icon="tabler-check" size="32" color="success" />
          </VAvatar>
          <h3 class="text-h6 font-weight-bold mb-1">¡Cuentas Cuadradas al 100%!</h3>
          <p class="text-body-2 text-medium-emphasis mb-0">
            No se encontraron discrepancias. Todas las facturas pagadas en tu ERP coinciden con el estado del portal de Dronena.
          </p>
        </div>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4 px-6 justify-end">
        <VBtn color="primary" variant="flat" class="px-6 rounded-lg" @click="closeDialog">
          Entendido
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
