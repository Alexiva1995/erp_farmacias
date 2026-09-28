<script setup>
import { computed } from 'vue';
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

// Cálculo de páginas totales por cada métrica
const totalPagesUnits = computed(() => {
  const total = props.rankings.by_units?.total || props.rankings.by_units?.data?.length || 0;
  const perPage = props.rankings.by_units?.per_page || 10;
  return Math.max(1, Math.ceil(total / perPage));
});

const totalPagesRevenue = computed(() => {
  const total = props.rankings.by_revenue?.total || props.rankings.by_revenue?.data?.length || 0;
  const perPage = props.rankings.by_revenue?.per_page || 10;
  return Math.max(1, Math.ceil(total / perPage));
});

const totalPagesStock = computed(() => {
  const total = props.rankings.by_stock?.total || props.rankings.by_stock?.data?.length || 0;
  const perPage = props.rankings.by_stock?.per_page || 10;
  return Math.max(1, Math.ceil(total / perPage));
});
</script>

<template>
  <VRow class="match-height mb-4">
    <!-- TOP UNIDADES VENDIDAS -->
    <VCol cols="12" md="4">
      <VCard border class="rounded-lg overflow-hidden h-100 d-flex flex-column">
        <VCardTitle class="pa-3 border-b d-flex align-center justify-space-between">
          <div class="d-flex align-center">
            <VAvatar color="primary" variant="tonal" size="32" class="me-2">
              <VIcon icon="tabler-shopping-cart" size="18" />
            </VAvatar>
            <span class="text-subtitle-2 font-weight-bold">Top Ventas (Unidades)</span>
          </div>
          <VChip size="x-small" color="primary" variant="tonal">
            {{ rankings.by_units?.total || rankings.by_units?.data?.length || 0 }} Labs
          </VChip>
        </VCardTitle>

        <VCardText class="pa-0 flex-grow-1 d-flex flex-column justify-space-between">
          <div>
            <VSkeletonLoader v-if="loading || loadingUnits" type="list-item-avatar-two-line@5" />
            <template v-else>
              <VList lines="one" v-if="rankings.by_units?.data?.length" class="py-0">
                <VListItem
                  v-for="(lab, idx) in rankings.by_units.data"
                  :key="lab.aggregation_id || idx"
                  class="border-b px-3 list-hover-item"
                  @click="emit('selectLab', lab.aggregation_id)"
                >
                  <template #prepend>
                    <VAvatar color="primary" variant="tonal" size="26" class="font-weight-bold text-xs me-2">
                      {{ ((pageUnits - 1) * 10) + idx + 1 }}
                    </VAvatar>
                  </template>
                  <VListItemTitle class="font-weight-medium text-caption text-uppercase text-truncate">
                    {{ lab.name }}
                  </VListItemTitle>
                  <template #append>
                    <span class="text-caption font-weight-black text-primary">
                      {{ Math.round(lab.total_units).toLocaleString() }} Unds
                    </span>
                  </template>
                </VListItem>
              </VList>
              <VEmptyState
                v-else
                icon="tabler-database-off"
                title="Sin registros"
                text="No se encontraron unidades vendidas"
                class="py-6"
              />
            </template>
          </div>

          <div class="pa-2 d-flex justify-space-between align-center border-t bg-surface">
            <span class="text-caption font-weight-medium text-medium-emphasis">
              Página {{ pageUnits }} de {{ totalPagesUnits }}
            </span>
            <div class="d-flex align-center gap-1">
              <VBtn
                icon="tabler-chevron-left"
                variant="text"
                density="compact"
                size="small"
                :disabled="pageUnits <= 1 || loading || loadingUnits"
                @click="emit('fetchRankings', 'total_units', pageUnits - 1)"
              />
              <VBtn
                icon="tabler-chevron-right"
                variant="text"
                density="compact"
                size="small"
                :disabled="pageUnits >= totalPagesUnits || (rankings.by_units?.data?.length || 0) < 10 || loading || loadingUnits"
                @click="emit('fetchRankings', 'total_units', pageUnits + 1)"
              />
            </div>
          </div>
        </VCardText>
      </VCard>
    </VCol>

    <!-- TOP VENTA BRUTA (USD) -->
    <VCol cols="12" md="4">
      <VCard border class="rounded-lg overflow-hidden h-100 d-flex flex-column">
        <VCardTitle class="pa-3 border-b d-flex align-center justify-space-between">
          <div class="d-flex align-center">
            <VAvatar color="success" variant="tonal" size="32" class="me-2">
              <VIcon icon="tabler-currency-dollar" size="18" />
            </VAvatar>
            <span class="text-subtitle-2 font-weight-bold">Top Venta Bruta (USD)</span>
          </div>
          <VChip size="x-small" color="success" variant="tonal">
            {{ rankings.by_revenue?.total || rankings.by_revenue?.data?.length || 0 }} Labs
          </VChip>
        </VCardTitle>

        <VCardText class="pa-0 flex-grow-1 d-flex flex-column justify-space-between">
          <div>
            <VSkeletonLoader v-if="loading || loadingRevenue" type="list-item-avatar-two-line@5" />
            <template v-else>
              <VList lines="one" v-if="rankings.by_revenue?.data?.length" class="py-0">
                <VListItem
                  v-for="(lab, idx) in rankings.by_revenue.data"
                  :key="lab.aggregation_id || idx"
                  class="border-b px-3 list-hover-item"
                  @click="emit('selectLab', lab.aggregation_id)"
                >
                  <template #prepend>
                    <VAvatar color="success" variant="tonal" size="26" class="font-weight-bold text-xs me-2">
                      {{ ((pageRevenue - 1) * 10) + idx + 1 }}
                    </VAvatar>
                  </template>
                  <VListItemTitle class="font-weight-medium text-caption text-uppercase text-truncate">
                    {{ lab.name }}
                  </VListItemTitle>
                  <template #append>
                    <span class="text-caption font-weight-black text-success">
                      {{ formatCurrency(lab.total_revenue) }}
                    </span>
                  </template>
                </VListItem>
              </VList>
              <VEmptyState
                v-else
                icon="tabler-database-off"
                title="Sin registros"
                text="No se encontraron ingresos para este periodo"
                class="py-6"
              />
            </template>
          </div>

          <div class="pa-2 d-flex justify-space-between align-center border-t bg-surface">
            <span class="text-caption font-weight-medium text-medium-emphasis">
              Página {{ pageRevenue }} de {{ totalPagesRevenue }}
            </span>
            <div class="d-flex align-center gap-1">
              <VBtn
                icon="tabler-chevron-left"
                variant="text"
                density="compact"
                size="small"
                :disabled="pageRevenue <= 1 || loading || loadingRevenue"
                @click="emit('fetchRankings', 'total_revenue', pageRevenue - 1)"
              />
              <VBtn
                icon="tabler-chevron-right"
                variant="text"
                density="compact"
                size="small"
                :disabled="pageRevenue >= totalPagesRevenue || (rankings.by_revenue?.data?.length || 0) < 10 || loading || loadingRevenue"
                @click="emit('fetchRankings', 'total_revenue', pageRevenue + 1)"
              />
            </div>
          </div>
        </VCardText>
      </VCard>
    </VCol>

    <!-- TOP UNIDADES EN STOCK -->
    <VCol cols="12" md="4">
      <VCard border class="rounded-lg overflow-hidden h-100 d-flex flex-column">
        <VCardTitle class="pa-3 border-b d-flex align-center justify-space-between">
          <div class="d-flex align-center">
            <VAvatar color="warning" variant="tonal" size="32" class="me-2">
              <VIcon icon="tabler-package" size="18" />
            </VAvatar>
            <span class="text-subtitle-2 font-weight-bold">Top Unidades en Stock</span>
          </div>
          <VChip size="x-small" color="warning" variant="tonal">
            {{ rankings.by_stock?.total || rankings.by_stock?.data?.length || 0 }} Labs
          </VChip>
        </VCardTitle>

        <VCardText class="pa-0 flex-grow-1 d-flex flex-column justify-space-between">
          <div>
            <VSkeletonLoader v-if="loading || loadingStock" type="list-item-avatar-two-line@5" />
            <template v-else>
              <VList lines="one" v-if="rankings.by_stock?.data?.length" class="py-0">
                <VListItem
                  v-for="(lab, idx) in rankings.by_stock.data"
                  :key="lab.aggregation_id || idx"
                  class="border-b px-3 list-hover-item"
                  @click="emit('selectLab', lab.aggregation_id)"
                >
                  <template #prepend>
                    <VAvatar color="warning" variant="tonal" size="26" class="font-weight-bold text-xs me-2">
                      {{ ((pageStock - 1) * 10) + idx + 1 }}
                    </VAvatar>
                  </template>
                  <VListItemTitle class="font-weight-medium text-caption text-uppercase text-truncate">
                    {{ lab.name }}
                  </VListItemTitle>
                  <template #append>
                    <span class="text-caption font-weight-black text-warning">
                      {{ Math.round(lab.total_units).toLocaleString() }} Unds
                    </span>
                  </template>
                </VListItem>
              </VList>
              <VEmptyState
                v-else
                icon="tabler-database-off"
                title="Sin registros"
                text="No se encontraron datos de stock"
                class="py-6"
              />
            </template>
          </div>

          <div class="pa-2 d-flex justify-space-between align-center border-t bg-surface">
            <span class="text-caption font-weight-medium text-medium-emphasis">
              Página {{ pageStock }} de {{ totalPagesStock }}
            </span>
            <div class="d-flex align-center gap-1">
              <VBtn
                icon="tabler-chevron-left"
                variant="text"
                density="compact"
                size="small"
                :disabled="pageStock <= 1 || loading || loadingStock"
                @click="emit('fetchRankings', 'total_stock', pageStock - 1)"
              />
              <VBtn
                icon="tabler-chevron-right"
                variant="text"
                density="compact"
                size="small"
                :disabled="pageStock >= totalPagesStock || (rankings.by_stock?.data?.length || 0) < 10 || loading || loadingStock"
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
.list-hover-item {
  cursor: pointer;
  transition: background-color 0.2s ease;
}

.list-hover-item:hover {
  background-color: rgba(var(--v-theme-primary), 0.05);
}

.gap-1 {
  gap: 4px;
}
</style>
