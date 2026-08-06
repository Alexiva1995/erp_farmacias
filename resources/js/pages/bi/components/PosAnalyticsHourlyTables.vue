<script setup>
import { computed } from 'vue';

const props = defineProps({
  hourlyDistribution: { type: Object, default: () => ({ series: [] }) },
  completedSales: { type: Number, default: 0 },
  totalRevenue: { type: Number, default: 0 }
});

const formatCurrency = (val) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(val || 0);

const trafficHourlyData = computed(() => {
  const data = props.hourlyDistribution?.series?.[0]?.data || [];
  return [...data].sort((a, b) => b.y - a.y);
});

const revenueHourlyData = computed(() => {
  const data = props.hourlyDistribution?.series?.[0]?.data || [];
  return [...data].sort((a, b) => b.revenue - a.revenue);
});

const sellersHourlyData = computed(() => {
  const data = props.hourlyDistribution?.series?.[0]?.data || [];
  return [...data].sort((a, b) => parseInt(a.x) - parseInt(b.x));
});
</script>

<template>
  <VRow dense>
    <!-- Tabla 1: Tráfico -->
    <VCol cols="12" md="4">
      <VCard class="rounded-lg border shadow-sm overflow-hidden h-100 bg-surface">
        <VCardItem class="py-3 border-b bg-light-primary">
          <VCardTitle class="d-flex align-center text-subtitle-2 font-weight-black text-uppercase">
            <VIcon icon="tabler-clock-up" class="me-2 text-primary" size="20" />
            Top Tráfico (Frecuencia)
          </VCardTitle>
        </VCardItem>
        <VTable density="compact" class="text-no-wrap analytics-table">
          <thead>
            <tr>
              <th class="text-uppercase text-[10px] font-weight-black">Hora</th>
              <th class="text-uppercase text-[10px] font-weight-black text-center">Tks</th>
              <th class="text-uppercase text-[10px] font-weight-black text-center">% Part.</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="slot in trafficHourlyData" :key="slot.x">
              <td class="font-weight-black text-primary">{{ slot.x }}</td>
              <td class="text-center font-weight-bold">{{ Math.round((slot.y * completedSales) / 100) }}</td>
              <td class="text-center">
                <VChip size="x-small" label color="primary" variant="tonal" class="font-weight-black">{{ slot.y }}%</VChip>
              </td>
            </tr>
          </tbody>
        </VTable>
      </VCard>
    </VCol>

    <!-- Tabla 2: Facturación -->
    <VCol cols="12" md="4">
      <VCard class="rounded-lg border shadow-sm overflow-hidden h-100 bg-surface">
        <VCardItem class="py-3 border-b bg-light-success">
          <VCardTitle class="d-flex align-center text-subtitle-2 font-weight-black text-uppercase">
            <VIcon icon="tabler-cash-banknote" class="me-2 text-success" size="20" />
            Mayor Facturación (USD)
          </VCardTitle>
        </VCardItem>
        <VTable density="compact" class="text-no-wrap analytics-table">
          <thead>
            <tr>
              <th class="text-uppercase text-[10px] font-weight-black">Hora</th>
              <th class="text-uppercase text-[10px] font-weight-black text-right">Monto</th>
              <th class="text-uppercase text-[10px] font-weight-black text-center">% Part.</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="slot in revenueHourlyData" :key="slot.x">
              <td class="font-weight-black text-success">{{ slot.x }}</td>
              <td class="text-right font-weight-black text-success">{{ formatCurrency(slot.revenue) }}</td>
              <td class="text-center">
                <VChip size="x-small" label color="success" variant="tonal" class="font-weight-black">
                  {{ ((slot.revenue / (totalRevenue || 1)) * 100).toFixed(1) }}%
                </VChip>
              </td>
            </tr>
          </tbody>
        </VTable>
      </VCard>
    </VCol>

    <!-- Tabla 3: Vendedores por Hora -->
    <VCol cols="12" md="4">
      <VCard class="rounded-lg border shadow-sm overflow-hidden h-100 bg-surface">
        <VCardItem class="py-3 border-b bg-light-info">
          <VCardTitle class="d-flex align-center text-subtitle-2 font-weight-black text-uppercase">
            <VIcon icon="tabler-users" class="me-2 text-info" size="20" />
            Vendedor Estrella por Hora
          </VCardTitle>
        </VCardItem>
        <VTable density="compact" class="text-no-wrap analytics-table">
          <thead>
            <tr>
              <th class="text-uppercase text-[10px] font-weight-black">Hora</th>
              <th class="text-uppercase text-[10px] font-weight-black">Vendedor</th>
              <th class="text-uppercase text-[10px] font-weight-black text-right">Venta USD</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="slot in sellersHourlyData" :key="slot.x">
              <td class="font-weight-black text-info">{{ slot.x }}</td>
              <td>
                <div class="d-flex align-center" v-if="slot.top_seller">
                  <span class="text-[11px] font-weight-bold truncate">{{ slot.top_seller.seller_name }}</span>
                </div>
                <span v-else class="text-disabled text-[10px]">Sin ventas</span>
              </td>
              <td class="text-right font-weight-black text-info" v-if="slot.top_seller">
                {{ formatCurrency(slot.top_seller.revenue) }}
              </td>
              <td v-else class="text-right text-disabled">-</td>
            </tr>
          </tbody>
        </VTable>
      </VCard>
    </VCol>
  </VRow>
</template>

<style scoped>
.font-weight-black { font-weight: 900 !important; }
.bg-light-primary { background-color: #fff0f6; }
.bg-light-success { background-color: #f0fdf4; }
.bg-light-info { background-color: #f0f9ff; }

.analytics-table :deep(th) {
  background-color: #f8fafc !important;
  color: #64748b !important;
  border-bottom: 2px solid #e2e8f0 !important;
}

.analytics-table :deep(td) {
  font-size: 0.75rem !important;
  border-bottom: 1px solid #f1f5f9 !important;
}
</style>
