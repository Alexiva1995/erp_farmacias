<script setup>
import { formatCurrency } from '@/utils/currencyFormatter';

const props = defineProps({
  marginSummary: {
    type: Object,
    default: () => ({}),
  },
  alerts: {
    type: Array,
    default: () => [],
  },
  search: {
    type: String,
    default: '',
  },
});

const emit = defineEmits(['update:search']);
</script>

<template>
  <div>
    <VRow dense class="mb-4">
      <VCol cols="12" sm="4">
        <VCard class="pa-4 rounded-lg border shadow-sm">
          <span class="text-caption font-weight-bold text-uppercase text-medium-emphasis d-block mb-1">
            Venta con Margen Negativo (<0%)
          </span>
          <h3 class="text-h5 font-weight-black text-error mb-0">
            {{ marginSummary?.negative_margin_count || 0 }} SKUs
          </h3>
          <span class="text-caption text-error font-weight-bold">Productos vendiéndose a pérdida con existencias</span>
        </VCard>
      </VCol>

      <VCol cols="12" sm="4">
        <VCard class="pa-4 rounded-lg border shadow-sm">
          <span class="text-caption font-weight-bold text-uppercase text-medium-emphasis d-block mb-1">
            Margen Bajo Crítico (<15%)
          </span>
          <h3 class="text-h5 font-weight-black text-warning mb-0">
            {{ marginSummary?.low_margin_count || 0 }} SKUs
          </h3>
          <span class="text-caption text-medium-emphasis">Por debajo del umbral mínimo de rentabilidad</span>
        </VCard>
      </VCol>

      <VCol cols="12" sm="4">
        <VCard class="pa-4 rounded-lg border shadow-sm">
          <span class="text-caption font-weight-bold text-uppercase text-medium-emphasis d-block mb-1">
            Márgenes Saludables (>=15%)
          </span>
          <h3 class="text-h5 font-weight-black text-success mb-0">
            {{ marginSummary?.healthy_margin_count || 0 }} SKUs
          </h3>
          <span class="text-caption text-medium-emphasis">Operando dentro de rangos óptimos</span>
        </VCard>
      </VCol>
    </VRow>

    <!-- Tabla de Alertas de Margen -->
    <VCard class="rounded-lg border shadow-sm overflow-hidden bg-surface mb-6">
      <VCardText class="pa-4">
        <VRow align="center" dense class="mb-3">
          <VCol cols="12" md="4">
            <AppTextField
              :model-value="search"
              placeholder="Buscar producto con margen en riesgo..."
              prepend-inner-icon="tabler-search"
              clearable
              density="compact"
              hide-details
              variant="outlined"
              @update:model-value="emit('update:search', $event)"
            />
          </VCol>
        </VRow>

        <VTable density="compact" hover class="premium-table">
          <thead>
            <tr>
              <th class="text-start">ID / PRODUCTO</th>
              <th class="text-start">LABORATORIO</th>
              <th class="text-end">COSTO UNIT. ($)</th>
              <th class="text-end">PRECIO VENTA ($)</th>
              <th class="text-end font-weight-black">MARGEN REAL (%)</th>
              <th class="text-end">STOCK ACTUAL</th>
              <th class="text-end">CAPITAL EN RIESGO ($)</th>
              <th class="text-center">DIAGNÓSTICO</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="alerts.length === 0">
              <td colspan="8" class="text-center py-6 text-success font-weight-bold">
                <VIcon icon="tabler-shield-check" size="24" class="me-2" />
                Excelente: Ningún producto con existencias opera con margen inferior al deseado.
              </td>
            </tr>
            <tr v-for="item in alerts" :key="'margin-' + item.product_id">
              <td>
                <div class="d-flex flex-column py-1">
                  <span class="font-weight-black text-sm text-uppercase text-truncate" style="max-width: 260px;">
                    {{ item.product_name }}
                  </span>
                  <span class="text-caption text-primary">#{{ item.product_id }}</span>
                </div>
              </td>
              <td class="font-weight-medium text-caption">{{ item.laboratory_name }}</td>
              <td class="text-end">{{ formatCurrency(item.unit_cost_usd) }}</td>
              <td class="text-end font-weight-bold">{{ formatCurrency(item.sale_price_usd) }}</td>
              <td class="text-end font-weight-black" :class="item.margin_percentage < 0 ? 'text-error' : 'text-warning'">
                {{ item.margin_percentage.toFixed(2) }}%
              </td>
              <td class="text-end">{{ item.current_stock }}</td>
              <td class="text-end">{{ formatCurrency(item.inventory_value_usd) }}</td>
              <td class="text-center">
                <VChip
                  size="small"
                  :color="item.severity"
                  variant="flat"
                  class="font-weight-bold"
                >
                  {{ item.risk_level }}
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
