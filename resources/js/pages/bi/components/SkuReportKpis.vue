<script setup>
import { computed } from 'vue';

const props = defineProps({
  summaryStats: {
    type: Object,
    default: () => ({
      global_margin_real: 0,
      global_margin_net: 0,
      total_discounts: 0,
      total_loss: 0,
      critical_skus: 0,
      gmroi: 0
    })
  },
  loading: Boolean,
  activeFilterKey: {
    type: String,
    default: null
  }
});

const emit = defineEmits(['filter-click']);

const formatPercent = (val) => Number(val || 0).toFixed(2) + '%';
const formatMoney = (val) => '$' + Number(val || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const kpis = computed(() => [
  {
    key: 'global',
    title: 'Margen Real Global',
    value: formatPercent(props.summaryStats.global_margin_real),
    color: props.summaryStats.global_margin_real > 0 ? 'success' : 'error',
    icon: 'tabler-percentage',
    desc: 'Neto - Mermas',
    hint: 'Ver todos'
  },
  {
    key: 'discounts',
    title: 'Impacto Descuentos',
    value: formatMoney(props.summaryStats.total_discounts),
    color: 'warning',
    icon: 'tabler-tag',
    desc: 'Dinero cedido en ofertas',
    hint: 'Filtrar con descuento'
  },
  {
    key: 'losses',
    title: 'Pérdida por Mermas',
    value: formatMoney(props.summaryStats.total_loss),
    color: props.summaryStats.total_loss > 0 ? 'error' : 'secondary',
    icon: 'tabler-trash',
    desc: 'Costo total vencidos',
    hint: 'Filtrar con mermas'
  },
  {
    key: 'critical',
    title: 'Alertas Críticas',
    value: props.summaryStats.critical_skus || 0,
    color: props.summaryStats.critical_skus > 0 ? 'error' : 'success',
    icon: 'tabler-alert-triangle',
    desc: 'SKUs en Peligro/Pérdida',
    hint: 'Filtrar críticos'
  }
]);

const handleCardClick = (key) => {
  emit('filter-click', key);
};
</script>

<template>
  <VRow class="mb-5 mt-1" dense>
    <VCol v-for="(kpi, index) in kpis" :key="index" cols="12" sm="6" md="3">
      <VCard
        class="stats-card rounded-lg border overflow-hidden h-100 position-relative cursor-pointer transition-all bg-surface"
        :class="[
          activeFilterKey === kpi.key ? 'active-kpi-card shadow-md' : 'shadow-sm',
          loading ? 'pointer-events-none' : ''
        ]"
        tabindex="0"
        role="button"
        :aria-pressed="activeFilterKey === kpi.key"
        @click="handleCardClick(kpi.key)"
      >
        <div
          class="card-bg-decoration"
          :style="{ background: `linear-gradient(45deg, rgba(var(--v-theme-${kpi.color}), ${activeFilterKey === kpi.key ? 0.2 : 0.08}), transparent)` }"
        ></div>

        <VCardText class="pa-4 relative-content">
          <VSkeletonLoader
            v-if="loading"
            type="article"
            height="90"
            class="bg-transparent"
          />
          <div v-else>
            <div class="d-flex align-center justify-space-between mb-3">
              <VAvatar :color="kpi.color" variant="tonal" size="44" rounded="lg" class="elevation-1">
                <VIcon :icon="kpi.icon" size="24" />
              </VAvatar>
              <div class="text-right">
                <div class="d-flex align-center justify-end gap-1 mb-1">
                  <VChip
                    v-if="activeFilterKey === kpi.key"
                    size="x-small"
                    :color="kpi.color"
                    variant="flat"
                    class="font-weight-bold px-1 text-uppercase"
                  >
                    Activo
                  </VChip>
                  <span class="text-overline font-weight-bold text-disabled">
                    {{ kpi.title }}
                  </span>
                </div>
                <h4 class="text-h4 font-weight-black mt-0">{{ kpi.value }}</h4>
              </div>
            </div>
            <VDivider class="mb-3 opacity-20" />
            
            <div class="d-flex align-center justify-space-between">
              <span class="text-caption font-weight-medium text-medium-emphasis">{{ kpi.desc }}</span>
              <div class="d-flex align-center gap-1">
                <span class="text-caption font-weight-bold text-primary d-none d-sm-inline">
                  {{ activeFilterKey === kpi.key ? 'Quitar filtro' : kpi.hint }}
                </span>
                <VIcon
                  :icon="activeFilterKey === kpi.key ? 'tabler-x' : 'tabler-filter'"
                  size="14"
                  :color="kpi.color"
                  class="opacity-75"
                />
              </div>
            </div>
          </div>
        </VCardText>

        <div
          class="accent-border"
          :style="{
            backgroundColor: `rgb(var(--v-theme-${kpi.color}))`,
            height: activeFilterKey === kpi.key ? '4px' : '2px'
          }"
        ></div>
      </VCard>
    </VCol>
  </VRow>
</template>

<style scoped>
.stats-card {
  transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
}

.stats-card:hover {
  transform: translateY(-2px);
  border-color: rgba(var(--v-theme-primary), 0.35) !important;
}

.active-kpi-card {
  border-color: rgb(var(--v-theme-primary)) !important;
  box-shadow: 0 4px 18px 0 rgba(var(--v-theme-primary), 0.18) !important;
}

.cursor-pointer {
  cursor: pointer;
}

.transition-all {
  transition: all 0.2s ease-in-out;
}
</style>
