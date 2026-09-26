<script setup>
import { computed } from 'vue';

const props = defineProps({
  summaryStats: {
    type: Object,
    default: () => ({
      global_margin_real: 0,
      total_discounts: 0,
      total_loss: 0,
      critical_skus: 0
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
const formatMoney = (val) => '$' + Number(val || 0).toFixed(2);

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
    title: 'Impacto Desc.',
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
    desc: 'SKUs en Pérdida',
    hint: 'Filtrar críticos'
  }
]);

const handleCardClick = (key) => {
  emit('filter-click', key);
};
</script>

<template>
  <VRow class="ma-0 mx-n1 mb-5 mt-2" dense>
    <VCol v-for="(kpi, index) in kpis" :key="index" cols="6" md="3" class="pa-1">
      <VCard
        class="stats-card rounded-lg border overflow-hidden h-full position-relative cursor-pointer transition-all"
        :class="[
          activeFilterKey === kpi.key ? 'active-kpi-card shadow-md' : 'shadow-sm',
          loading ? 'pointer-events-none opacity-80' : ''
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

        <VCardText class="pa-5 relative-content">
          <div v-if="loading" class="d-flex flex-column gap-2 py-2">
            <div class="d-flex justify-space-between align-center">
              <div class="w-25 bg-secondary-light animate-pulse rounded" style="height: 32px;"></div>
              <div class="w-50 bg-secondary-light animate-pulse rounded" style="height: 24px;"></div>
            </div>
            <div class="w-100 bg-secondary-light animate-pulse rounded mt-3" style="height: 10px;"></div>
          </div>
          <div v-else>
            <div class="d-flex align-center justify-space-between mb-3">
              <VAvatar :color="kpi.color" variant="tonal" size="48" rounded="lg" class="elevation-1">
                <VIcon :icon="kpi.icon" size="26" />
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
                  <span class="text-overline font-weight-bold text-disabled" style="letter-spacing: 1px !important; line-height: 1.2;">
                    {{ kpi.title }}
                  </span>
                </div>
                <h4 class="text-h4 font-weight-black mt-0">{{ kpi.value }}</h4>
              </div>
            </div>
            <VDivider class="mb-3 opacity-20" />
          </div>

          <div class="d-flex align-center justify-space-between">
            <span class="text-caption font-weight-medium text-medium-emphasis">{{ kpi.desc }}</span>
            <div class="d-flex align-center gap-1">
              <span class="text-super-xs font-weight-bold text-primary opacity-80 d-none d-sm-inline">
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
  transform: translateY(-3px);
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

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: 1;
}

@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: .5; }
}

.animate-pulse {
  animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

.bg-secondary-light {
  background-color: rgba(var(--v-theme-secondary), 0.15);
}
</style>
