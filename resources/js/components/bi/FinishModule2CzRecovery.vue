<script setup>
import { formatCurrency } from '@/utils/currencyFormatter';

const props = defineProps({
  czSummary: {
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
</script>

<template>
  <div>
    <!-- Hero Card Métrica Automática de Dinero Liberado -->
    <VCard class="pa-5 mb-4 rounded-lg border shadow-sm bg-surface">
      <VRow align="center" dense>
        <VCol cols="12" md="4" class="text-center text-md-start">
          <span class="text-caption font-weight-bold text-uppercase text-medium-emphasis d-block mb-1">
            Métrica Automática de Desinmovilización
          </span>
          <div class="d-flex align-baseline gap-2 justify-center justify-md-start">
            <h2 class="text-h4 font-weight-black text-success mb-0">
              {{ formatCurrency(czSummary?.total_cash_released || 0) }}
            </h2>
            <VChip size="small" color="success" class="font-weight-black">
              {{ czSummary?.recovery_percentage || 0 }}% Liberado
            </VChip>
          </div>
          <span class="text-caption text-success font-weight-bold">
            [Capital Inicial CZ: {{ formatCurrency(czSummary?.total_initial_cz_capital || 0) }}] - [Capital Actual CZ: {{ formatCurrency(czSummary?.total_current_cz_capital || 0) }}] = Dinero Liberado a Caja
          </span>
        </VCol>

        <VCol cols="12" sm="6" md="4">
          <div class="pa-3 bg-light-primary rounded-lg border">
            <span class="text-caption font-weight-bold text-medium-emphasis d-block">Unidades Descongeladas Vendidas</span>
            <span class="text-h6 font-weight-black text-primary">{{ Number(czSummary?.total_units_released || 0).toLocaleString() }} unidades</span>
            <span class="text-super-xs text-medium-emphasis d-block">Reducción efectiva de inventario parado</span>
          </div>
        </VCol>

        <VCol cols="12" sm="6" md="4">
          <div class="pa-3 bg-light-warning rounded-lg border">
            <span class="text-caption font-weight-bold text-medium-emphasis d-block">Capital Pendiente por Liberar</span>
            <span class="text-h6 font-weight-black text-warning">{{ formatCurrency(czSummary?.total_current_cz_capital || 0) }}</span>
            <span class="text-super-xs text-medium-emphasis d-block">En {{ czSummary?.items_count || 0 }} SKUs CZ monitoreados</span>
          </div>
        </VCol>
      </VRow>
    </VCard>

    <!-- Tabla Comparativa de Productos CZ -->
    <VCard class="rounded-lg border shadow-sm overflow-hidden bg-surface mb-6">
      <VCardText class="pa-4">
        <VRow align="center" dense class="mb-3">
          <VCol cols="12" md="4">
            <AppTextField
              :model-value="search"
              placeholder="Buscar producto CZ o laboratorio..."
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
              <th class="text-end">STOCK FOTO</th>
              <th class="text-end">VALOR FOTO ($)</th>
              <th class="text-end">STOCK ACTUAL</th>
              <th class="text-end">VALOR ACTUAL ($)</th>
              <th class="text-end font-weight-black text-success">DINERO LIBERADO ($)</th>
              <th class="text-center">ESTADO</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="items.length === 0">
              <td colspan="8" class="text-center py-6 text-medium-emphasis">
                No se encontraron productos CZ registrados en esta Foto Finish.
              </td>
            </tr>
            <tr v-for="item in items" :key="'cz-' + item.product_id">
              <td>
                <div class="d-flex flex-column py-1">
                  <span class="font-weight-black text-sm text-uppercase text-truncate" style="max-width: 260px;">
                    {{ item.product_name }}
                  </span>
                  <span class="text-caption text-primary">#{{ item.product_id }}</span>
                </div>
              </td>
              <td class="font-weight-medium text-caption">{{ item.laboratory_name }}</td>
              <td class="text-end font-weight-bold">{{ item.snapshot_stock }}</td>
              <td class="text-end">{{ formatCurrency(item.snapshot_value_usd) }}</td>
              <td class="text-end font-weight-bold" :class="item.current_stock < item.snapshot_stock ? 'text-success' : ''">
                {{ item.current_stock }}
              </td>
              <td class="text-end">{{ formatCurrency(item.current_value_usd) }}</td>
              <td class="text-end font-weight-black" :class="item.cash_released_usd > 0 ? 'text-success' : 'text-medium-emphasis'">
                {{ item.cash_released_usd > 0 ? + : formatCurrency(item.cash_released_usd) }}
              </td>
              <td class="text-center">
                <VChip
                  size="x-small"
                  :color="item.cash_released_usd > 0 ? 'success' : (item.current_stock > item.snapshot_stock ? 'warning' : 'secondary')"
                  variant="flat"
                  class="font-weight-bold"
                >
                  {{ item.status }}
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
