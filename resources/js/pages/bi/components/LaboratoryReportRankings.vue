<script setup>
import { useCurrencyConverter } from '@/components/useCurrencyConverter';

const props = defineProps({
  rankings: {
    type: Object,
    required: true
  },
  pageUnits: {
    type: Number,
    default: 1
  },
  pageRevenue: {
    type: Number,
    default: 1
  },
  pageStock: {
    type: Number,
    default: 1
  },
  loading: {
    type: Boolean,
    default: false
  },
  loadingUnits: {
    type: Boolean,
    default: false
  },
  loadingRevenue: {
    type: Boolean,
    default: false
  },
  loadingStock: {
    type: Boolean,
    default: false
  }
});

const emit = defineEmits([
  'fetchRankings',
  'selectLab'
]);

const { formatCurrency } = useCurrencyConverter();
</script>

<template>
  <VRow class="match-height mb-4">
    <!-- TOP UNIDADES VENDIDAS -->
    <VCol cols="12" md="4">
      <VCard border class="rounded-lg overflow-hidden shadow-sm h-100">
        <VCardTitle class="pa-4 border-b bg-light-primary d-flex align-center">
          <VIcon icon="tabler-shopping-cart" class="me-2 text-primary" />
          <span class="text-subtitle-2 font-weight-black">Top Ventas (Units)</span>
        </VCardTitle>

        <VCardText class="pa-0">
          <VSkeletonLoader v-if="loading || loadingUnits" type="list-item-avatar-two-line@5" />
          <template v-else>
            <VList lines="one" v-if="rankings.by_units?.data?.length">
              <VListItem 
                v-for="(lab, idx) in rankings.by_units.data" 
                :key="lab.aggregation_id || idx" 
                class="border-b px-3 hover-bg" 
                @click="emit('selectLab', lab.aggregation_id)"
              >
                <template #prepend>
                  <VAvatar color="primary" variant="tonal" size="28" class="font-weight-black text-xs">
                    {{ ((pageUnits - 1) * 10) + idx + 1 }}
                  </VAvatar>
                </template>
                <VListItemTitle class="font-weight-bold text-caption text-uppercase">
                  {{ lab.name }}
                </VListItemTitle>
                <template #append>
                  <div class="text-right">
                    <div class="text-caption font-weight-black text-primary">
                      {{ Math.round(lab.total_units) }} Unds
                    </div>
                  </div>
                </template>
              </VListItem>
            </VList>
            <VEmptyState
              v-else
              icon="tabler-database-off"
              title="Sin registros"
              text="No se encontraron datos para la métrica seleccionada"
              class="py-6"
            />
          </template>

          <div class="pa-2 d-flex justify-space-between align-center bg-light-primary border-t">
            <span class="text-caption font-weight-black opacity-60">PÁG {{ pageUnits }}</span>
            <div class="d-flex gap-1">
              <VBtn 
                icon="tabler-chevron-left" 
                variant="text" 
                size="x-small" 
                :disabled="pageUnits <= 1 || loading || loadingUnits" 
                @click="emit('fetchRankings', 'total_units', pageUnits - 1)" 
              />
              <VBtn 
                icon="tabler-chevron-right" 
                variant="text" 
                size="x-small" 
                :disabled="(rankings.by_units?.data?.length || 0) < 10 || loading || loadingUnits" 
                @click="emit('fetchRankings', 'total_units', pageUnits + 1)" 
              />
            </div>
          </div>
        </VCardText>
      </VCard>
    </VCol>

    <!-- TOP VENTA BRUTA -->
    <VCol cols="12" md="4">
      <VCard border class="rounded-lg overflow-hidden shadow-sm h-100">
        <VCardTitle class="pa-4 border-b bg-light-success d-flex align-center">
          <VIcon icon="tabler-currency-dollar" class="me-2 text-success" />
          <span class="text-subtitle-2 font-weight-black">Top Ventas (USD)</span>
        </VCardTitle>

        <VCardText class="pa-0">
          <VSkeletonLoader v-if="loading || loadingRevenue" type="list-item-avatar-two-line@5" />
          <template v-else>
            <VList lines="one" v-if="rankings.by_revenue?.data?.length">
              <VListItem 
                v-for="(lab, idx) in rankings.by_revenue.data" 
                :key="lab.aggregation_id || idx" 
                class="border-b px-3 hover-bg" 
                @click="emit('selectLab', lab.aggregation_id)"
              >
                <template #prepend>
                  <VAvatar color="success" variant="tonal" size="28" class="font-weight-black text-xs">
                    {{ ((pageRevenue - 1) * 10) + idx + 1 }}
                  </VAvatar>
                </template>
                <VListItemTitle class="font-weight-bold text-caption text-uppercase">
                  {{ lab.name }}
                </VListItemTitle>
                <template #append>
                  <div class="text-right">
                    <div class="text-caption font-weight-black text-success">
                      {{ formatCurrency(lab.total_revenue) }}
                    </div>
                  </div>
                </template>
              </VListItem>
            </VList>
            <div v-else class="pa-10 text-center text-medium-emphasis text-caption">
              Sin datos registrados
            </div>
          </template>

          <div class="pa-2 d-flex justify-space-between align-center bg-light-success border-t">
            <span class="text-caption font-weight-black opacity-60">PÁG {{ pageRevenue }}</span>
            <div class="d-flex gap-1">
              <VBtn 
                icon="tabler-chevron-left" 
                variant="text" 
                size="x-small" 
                :disabled="pageRevenue <= 1 || loading || loadingRevenue" 
                @click="emit('fetchRankings', 'total_revenue', pageRevenue - 1)" 
              />
              <VBtn 
                icon="tabler-chevron-right" 
                variant="text" 
                size="x-small" 
                :disabled="(rankings.by_revenue?.data?.length || 0) < 10 || loading || loadingRevenue" 
                @click="emit('fetchRankings', 'total_revenue', pageRevenue + 1)" 
              />
            </div>
          </div>
        </VCardText>
      </VCard>
    </VCol>

    <!-- TOP UNIDADES EN STOCK -->
    <VCol cols="12" md="4">
      <VCard border class="rounded-lg overflow-hidden shadow-sm h-100">
        <VCardTitle class="pa-4 border-b bg-light-warning d-flex align-center">
          <VIcon icon="tabler-package" class="me-2 text-warning" />
          <span class="text-subtitle-2 font-weight-black">Top Unidades en Stock</span>
        </VCardTitle>

        <VCardText class="pa-0">
          <VSkeletonLoader v-if="loading || loadingStock" type="list-item-avatar-two-line@5" />
          <template v-else>
            <VList lines="one" v-if="rankings.by_stock?.data?.length">
              <VListItem 
                v-for="(lab, idx) in rankings.by_stock.data" 
                :key="lab.aggregation_id || idx" 
                class="border-b px-3 hover-bg" 
                @click="emit('selectLab', lab.aggregation_id)"
              >
                <template #prepend>
                  <VAvatar color="warning" variant="tonal" size="28" class="font-weight-black text-xs">
                    {{ ((pageStock - 1) * 10) + idx + 1 }}
                  </VAvatar>
                </template>
                <VListItemTitle class="font-weight-bold text-caption text-uppercase">
                  {{ lab.name }}
                </VListItemTitle>
                <template #append>
                  <div class="text-right">
                    <div class="text-caption font-weight-black text-warning">
                      {{ Math.round(lab.total_units) }} Unds
                    </div>
                  </div>
                </template>
              </VListItem>
            </VList>
            <div v-else class="pa-10 text-center text-medium-emphasis text-caption">
              Sin datos registrados
            </div>
          </template>

          <div class="pa-2 d-flex justify-space-between align-center bg-light-warning border-t">
            <span class="text-caption font-weight-black opacity-60">PÁG {{ pageStock }}</span>
            <div class="d-flex gap-1">
              <VBtn 
                icon="tabler-chevron-left" 
                variant="text" 
                size="x-small" 
                :disabled="pageStock <= 1 || loading || loadingStock" 
                @click="emit('fetchRankings', 'total_stock', pageStock - 1)" 
              />
              <VBtn 
                icon="tabler-chevron-right" 
                variant="text" 
                size="x-small" 
                :disabled="(rankings.by_stock?.data?.length || 0) < 10 || loading || loadingStock" 
                @click="emit('fetchRankings', 'total_stock', pageStock + 1)" 
              />
            </div>
          </div>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>

<style scoped>
.bg-light-primary { background-color: rgba(115, 103, 240, 0.12); }
.bg-light-success { background-color: rgba(40, 199, 111, 0.12); }
.bg-light-warning { background-color: rgba(255, 159, 67, 0.12); }

.hover-bg:hover {
  background-color: rgba(var(--v-theme-primary), 0.04);
  cursor: pointer;
}
.gap-1 { gap: 4px; }
</style>
