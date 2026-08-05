<script setup>
import { computed } from "vue";

const props = defineProps({
  performanceData: { type: Object, required: true },
  crossSellingRate: { type: Number, default: 0 },
  mobile: { type: Boolean, default: false },
});

const formatCurrency = (value) => {
  const n = Number(value);
  return Number.isFinite(n) ? n.toLocaleString("es-VE", { style: "currency", currency: "USD" }) : "—";
};

const historicalCrossSellingRate = computed(() => {
  const historical = props.performanceData.salesMetrics.historical;
  if (!historical.totalOrders || historical.totalOrders === 0) return 0;
  const single = historical.ordersWithSingleProduct || 0;
  const multiple = historical.totalOrders - single;
  return (Math.max(0, multiple) / historical.totalOrders) * 100;
});
</script>

<template>
  <div class="employee-performance-tab">
    <div class="d-flex align-center gap-3 mb-6">
      <h2 :class="mobile ? 'text-h6' : 'text-h5'" class="font-weight-black text-high-emphasis tracking-tight uppercase">
        Dashboard Operativo
      </h2>
      <VChip color="primary" variant="tonal" size="x-small" class="font-weight-black">MÉTRICAS MES VS HISTÓRICO</VChip>
    </div>

    <!-- KPIs Principales con comparación del histórico incorporada -->
    <VRow class="mb-6" :dense="mobile">
      <VCol
        v-for="kpi in [
          { 
            label: 'VENTAS USD', 
            current: performanceData.salesMetrics.currentMonth.totalAmount, 
            historical: performanceData.salesMetrics.historical.totalAmount,
            icon: 'tabler-cash', 
            color: 'primary', 
            format: 'currency',
            histLabel: 'Total Hist.'
          },
          { 
            label: 'UNIDADES', 
            current: performanceData.salesMetrics.currentMonth.totalUnits, 
            historical: performanceData.salesMetrics.historical.totalUnits,
            icon: 'tabler-package', 
            color: 'success', 
            format: 'number',
            histLabel: 'Total Hist.'
          },
          { 
            label: 'TICKET PROM', 
            current: performanceData.salesMetrics.currentMonth.ticketAverage, 
            historical: performanceData.salesMetrics.historical.ticketAverage,
            icon: 'tabler-receipt', 
            color: 'warning', 
            format: 'currency',
            histLabel: 'Prom. Hist.'
          },
          { 
            label: 'CROSS-SELLING', 
            current: crossSellingRate, 
            historical: historicalCrossSellingRate,
            icon: 'tabler-trending-up', 
            color: 'info', 
            format: 'percent',
            histLabel: 'Prom. Hist.'
          }
        ]"
        :key="kpi.label"
        cols="12"
        sm="6"
        lg="3"
      >
        <VCard class="rounded-lg border shadow-sm kpi-card overflow-hidden h-100">
          <div :class="`kpi-glow bg-${kpi.color}`" />
          <VCardText :class="mobile ? 'pa-3' : 'pa-4'">
            <div class="d-flex justify-space-between align-start mb-3">
              <div>
                <span class="text-super-xs font-weight-black text-disabled uppercase d-block mb-1">
                  {{ kpi.label }}
                </span>
                <div :class="mobile ? 'text-h6' : 'text-h5'" class="font-weight-black text-high-emphasis tabular-nums leading-none">
                  {{ kpi.format === 'currency' ? formatCurrency(kpi.current) : kpi.format === 'percent' ? kpi.current.toFixed(1) + '%' : Math.round(Number(kpi.current) || 0).toLocaleString() }}
                </div>
              </div>
              <VAvatar :color="kpi.color" variant="tonal" :size="mobile ? 36 : 42" class="rounded-lg">
                <VIcon :icon="kpi.icon" :size="mobile ? 20 : 24" />
              </VAvatar>
            </div>

            <!-- Fila del Histórico dentro de la Card -->
            <VDivider class="my-2 border-dashed" />
            <div class="d-flex align-center justify-space-between text-caption pt-1">
              <span class="text-super-xs text-medium-emphasis font-weight-bold uppercase">
                {{ kpi.histLabel }}:
              </span>
              <span class="text-super-xs font-weight-black text-high-emphasis tabular-nums">
                {{ kpi.format === 'currency' ? formatCurrency(kpi.historical) : kpi.format === 'percent' ? kpi.historical.toFixed(1) + '%' : Math.round(Number(kpi.historical) || 0).toLocaleString() }}
              </span>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Top Productos y Laboratorios -->
    <VRow>
      <VCol cols="12" md="6">
        <VCard class="rounded-lg border shadow-sm h-100">
          <VCardItem title="Top 10 Productos Vendidos">
            <template #append>
              <VIcon icon="tabler-pill" class="text-primary" />
            </template>
          </VCardItem>
          <VDivider />
          <VCardText class="pa-0">
            <VList v-if="performanceData.topProducts.length > 0">
              <VListItem
                v-for="(prod, idx) in performanceData.topProducts.slice(0, 10)"
                :key="prod.id || idx"
                class="py-2"
              >
                <template #prepend>
                  <VChip size="x-small" color="primary" variant="tonal" class="font-weight-bold me-2">
                    #{{ idx + 1 }}
                  </VChip>
                </template>
                <VListItemTitle class="font-weight-bold text-sm">
                  {{ prod.name || prod.product_name }}
                </VListItemTitle>
                <template #append>
                  <span class="text-caption font-weight-black text-high-emphasis">
                    {{ Math.round(prod.units || prod.total_units || 0) }} unids.
                  </span>
                </template>
              </VListItem>
            </VList>
            <div v-else class="pa-6 text-center text-disabled">
              No hay registros este mes
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12" md="6">
        <VCard class="rounded-lg border shadow-sm h-100">
          <VCardItem title="Top 10 Laboratorios">
            <template #append>
              <VIcon icon="tabler-building-factory-2" class="text-success" />
            </template>
          </VCardItem>
          <VDivider />
          <VCardText class="pa-0">
            <VList v-if="performanceData.topLaboratories.length > 0">
              <VListItem
                v-for="(lab, idx) in performanceData.topLaboratories.slice(0, 10)"
                :key="lab.id || idx"
                class="py-2"
              >
                <template #prepend>
                  <VChip size="x-small" color="success" variant="tonal" class="font-weight-bold me-2">
                    #{{ idx + 1 }}
                  </VChip>
                </template>
                <VListItemTitle class="font-weight-bold text-sm">
                  {{ lab.name || lab.laboratory }}
                </VListItemTitle>
                <template #append>
                  <span class="text-caption font-weight-black text-high-emphasis">
                    {{ Math.round(lab.units || lab.total_units || 0) }} unids.
                  </span>
                </template>
              </VListItem>
            </VList>
            <div v-else class="pa-6 text-center text-disabled">
              No hay registros este mes
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </div>
</template>

<style scoped>
.kpi-card {
  position: relative;
  background: rgb(var(--v-theme-surface));
}
.kpi-glow {
  position: absolute;
  top: 0;
  left: 0;
  width: 4px;
  height: 100%;
}
.text-super-xs {
  font-size: 0.68rem !important;
}
.border-dashed {
  border-style: dashed !important;
}
</style>
