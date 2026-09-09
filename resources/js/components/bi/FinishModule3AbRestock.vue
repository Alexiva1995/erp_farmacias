<script setup>
import { defineProps } from 'vue';

const props = defineProps({
  restockSummary: {
    type: Object,
    default: () => ({}),
  },
  items: {
    type: Array,
    default: () => [],
  },
  search: {
    type: String,
    default: '',
  },
});

const emit = defineEmits(['update:search']);

const getClassColor = (c) => {
  if (c === 'A') return 'success';
  if (c === 'B') return 'warning';
  if (c === 'C') return 'secondary';
  return 'error';
};
</script>

<template>
  <div>
    <VCard class="pa-5 mb-4 rounded-lg border shadow-sm bg-surface">
      <VRow align="center" dense>
        <VCol cols="12" md="4">
          <span class="text-caption font-weight-bold text-uppercase text-medium-emphasis d-block mb-1">
            Efectividad de Reabastecimiento A/B
          </span>
          <div class="d-flex align-baseline gap-2">
            <h2 class="text-h4 font-weight-black mb-0" :class="restockSummary?.effectiveness_rate >= 80 ? 'text-success' : 'text-warning'">
              {{ restockSummary?.effectiveness_rate || 0 }}%
            </h2>
            <span class="text-caption font-weight-bold">Tasa de Cumplimiento</span>
          </div>
          <span class="text-caption text-medium-emphasis">
            {{ restockSummary?.restocked_count || 0 }} de {{ restockSummary?.total_critical_items || 0 }} productos críticos reabastecidos.
          </span>
        </VCol>

        <VCol cols="12" sm="6" md="4">
          <div class="pa-3 bg-light-success rounded-lg border">
            <span class="text-caption font-weight-bold text-medium-emphasis d-block">SKUs Reabastecidos con Éxito</span>
            <span class="text-h6 font-weight-black text-success">{{ restockSummary?.restocked_count || 0 }} productos</span>
            <span class="text-super-xs text-medium-emphasis d-block">Stock actual recuperado y saludable</span>
          </div>
        </VCol>

        <VCol cols="12" sm="6" md="4">
          <div class="pa-3 bg-light-error rounded-lg border">
            <span class="text-caption font-weight-bold text-medium-emphasis d-block">SKUs Aún en Quiebre Crítico</span>
            <span class="text-h6 font-weight-black text-error">{{ restockSummary?.still_stockout_count || 0 }} productos</span>
            <span class="text-super-xs text-error font-weight-bold d-block">Requieren seguimiento urgente con compras</span>
          </div>
        </VCol>
      </VRow>
    </VCard>

    <!-- Tabla de Seguimiento de Compras A/B -->
    <VCard class="rounded-lg border shadow-sm overflow-hidden bg-surface mb-6">
      <VCardText class="pa-4">
        <VRow align="center" dense class="mb-3">
          <VCol cols="12" md="4">
            <AppTextField
              :model-value="search"
              placeholder="Buscar producto A/B en riesgo..."
              prepend-inner-icon="tabler-search"
              clearable
              density="compact"
              hide-details
              variant="outlined"
              @update:model-value="emit('update:search', )"
            />
          </VCol>
        </VRow>

        <VTable density="compact" hover class="premium-table">
          <thead>
            <tr>
              <th class="text-start">ID / PRODUCTO</th>
              <th class="text-start">LABORATORIO</th>
              <th class="text-center">CLASIFICACIÓN</th>
              <th class="text-end">STOCK EN FOTO</th>
              <th class="text-end">COBERTURA CORTE</th>
              <th class="text-end font-weight-black text-primary">STOCK ACTUAL</th>
              <th class="text-center">ESTADO DE REABASTECIMIENTO</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="items.length === 0">
              <td colspan="7" class="text-center py-6 text-medium-emphasis">
                No hubo productos A/B en quiebre o riesgo en la fecha de corte evaluada.
              </td>
            </tr>
            <tr v-for="item in items" :key="'restock-' + item.product_id">
              <td>
                <div class="d-flex flex-column py-1">
                  <span class="font-weight-black text-sm text-uppercase text-truncate" style="max-width: 260px;">
                    {{ item.product_name }}
                  </span>
                  <span class="text-caption text-primary">#{{ item.product_id }}</span>
                </div>
              </td>
              <td class="font-weight-medium text-caption">{{ item.laboratory_name }}</td>
              <td class="text-center">
                <VChip size="x-small" :color="getClassColor(item.sales_class)" class="font-weight-bold">
                  Clase {{ item.sales_class }}
                </VChip>
              </td>
              <td class="text-end font-weight-bold" :class="item.snapshot_stock <= 0 ? 'text-error' : ''">
                {{ item.snapshot_stock <= 0 ? '0 (Agotado)' : ${item.snapshot_stock} unds }}
              </td>
              <td class="text-end text-caption">
                {{ item.snapshot_coverage_days < 10 ? ${item.snapshot_coverage_days} días (Riesgo) : ${item.snapshot_coverage_days}d }}
              </td>
              <td class="text-end font-weight-black text-primary">
                {{ item.current_stock }} unds
              </td>
              <td class="text-center">
                <VChip
                  size="small"
                  :color="item.status_color"
                  variant="flat"
                  class="font-weight-bold"
                >
                  {{ item.restock_status }}
                </VChip>
              </td>
            </tr>
          </tbody>
        </VTable>
      </VCardText>
    </VCard>
  </div>
</template>

<style scoped>
.text-super-xs {
  font-size: 0.6875rem !important;
  line-height: 0.875rem !important;
}
</style>
