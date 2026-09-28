<script setup>
import { computed } from 'vue';

const props = defineProps({
  hourlyDistribution: { type: Object, default: () => ({ series: [] }) },
  completedSales: { type: Number, default: 0 },
  totalRevenue: { type: Number, default: 0 },
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
      <VCard variant="outlined" class="rounded-lg shadow-sm overflow-hidden h-100">
        <VCardItem class="py-3 border-b">
          <template #prepend>
            <VAvatar color="primary" variant="tonal" size="32" class="me-2 rounded">
              <VIcon icon="tabler-clock-up" size="18" />
            </VAvatar>
          </template>
          <VCardTitle class="text-subtitle-2 font-weight-bold text-uppercase">
            Top Tráfico (Frecuencia)
          </VCardTitle>
        </VCardItem>
        <VTable density="compact" class="text-no-wrap">
          <thead>
            <tr>
              <th class="text-uppercase text-caption font-weight-bold">Hora</th>
              <th class="text-uppercase text-caption font-weight-bold text-center">Tks</th>
              <th class="text-uppercase text-caption font-weight-bold text-center">% Part.</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="slot in trafficHourlyData" :key="slot.x">
              <td class="font-weight-bold text-primary">{{ slot.x }}</td>
              <td class="text-center font-weight-medium">{{ Math.round((slot.y * completedSales) / 100) }}</td>
              <td class="text-center">
                <VChip size="x-small" label color="primary" variant="tonal" class="font-weight-bold">{{ slot.y }}%</VChip>
              </td>
            </tr>
          </tbody>
        </VTable>
      </VCard>
    </VCol>

    <!-- Tabla 2: Facturación -->
    <VCol cols="12" md="4">
      <VCard variant="outlined" class="rounded-lg shadow-sm overflow-hidden h-100">
        <VCardItem class="py-3 border-b">
          <template #prepend>
            <VAvatar color="success" variant="tonal" size="32" class="me-2 rounded">
              <VIcon icon="tabler-cash-banknote" size="18" />
            </VAvatar>
          </template>
          <VCardTitle class="text-subtitle-2 font-weight-bold text-uppercase">
            Mayor Facturación (USD)
          </VCardTitle>
        </VCardItem>
        <VTable density="compact" class="text-no-wrap">
          <thead>
            <tr>
              <th class="text-uppercase text-caption font-weight-bold">Hora</th>
              <th class="text-uppercase text-caption font-weight-bold text-right">Monto</th>
              <th class="text-uppercase text-caption font-weight-bold text-center">% Part.</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="slot in revenueHourlyData" :key="slot.x">
              <td class="font-weight-bold text-success">{{ slot.x }}</td>
              <td class="text-right font-weight-bold text-success">{{ formatCurrency(slot.revenue) }}</td>
              <td class="text-center">
                <VChip size="x-small" label color="success" variant="tonal" class="font-weight-bold">
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
      <VCard variant="outlined" class="rounded-lg shadow-sm overflow-hidden h-100">
        <VCardItem class="py-3 border-b">
          <template #prepend>
            <VAvatar color="info" variant="tonal" size="32" class="me-2 rounded">
              <VIcon icon="tabler-users" size="18" />
            </VAvatar>
          </template>
          <VCardTitle class="text-subtitle-2 font-weight-bold text-uppercase">
            Vendedor Estrella por Hora
          </VCardTitle>
        </VCardItem>
        <VTable density="compact" class="text-no-wrap">
          <thead>
            <tr>
              <th class="text-uppercase text-caption font-weight-bold">Hora</th>
              <th class="text-uppercase text-caption font-weight-bold">Vendedor</th>
              <th class="text-uppercase text-caption font-weight-bold text-right">Venta USD</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="slot in sellersHourlyData" :key="slot.x">
              <td class="font-weight-bold text-info">{{ slot.x }}</td>
              <td>
                <div class="d-flex align-center" v-if="slot.top_seller">
                  <span class="text-caption font-weight-medium text-truncate">{{ slot.top_seller.seller_name }}</span>
                </div>
                <span v-else class="text-disabled text-caption">Sin ventas</span>
              </td>
              <td class="text-right font-weight-bold text-info" v-if="slot.top_seller">
                {{ formatCurrency(slot.top_seller.revenue) }}
              </td>
              <td v-else class="text-right text-disabled text-caption">-</td>
            </tr>
          </tbody>
        </VTable>
      </VCard>
    </VCol>
  </VRow>
</template>
