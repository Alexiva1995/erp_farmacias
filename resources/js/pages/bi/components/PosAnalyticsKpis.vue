<script setup>
import { computed } from 'vue';

const props = defineProps({
  kpis: { type: Object, default: () => ({}) },
});

const formatCurrency = (val) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(val || 0);
const formatNumber = (val) => new Intl.NumberFormat('en-US').format(val || 0);

const kpisList = computed(() => {
  const k = props.kpis;
  const totalCompleted = k.completed_sales || 0;
  const totalAbandoned = k.abandoned_sales || 0;
  const totalInteractions = totalCompleted + totalAbandoned;
  const abandonmentRate = totalInteractions > 0 ? ((totalAbandoned / totalInteractions) * 100).toFixed(1) : '0.0';

  return [
    { 
      title: 'Facturación Total', 
      mainValue: formatCurrency(k.total_revenue), 
      subValue: `${formatNumber(totalCompleted)} ventas exitosas`,
      icon: 'tabler-coin',
      color: 'primary',
      tooltip: 'Monto total facturado en ventas completadas durante el periodo seleccionado.'
    },
    { 
      title: 'Ticket Medio', 
      mainValue: formatCurrency(k.avg_ticket), 
      subValue: 'Promedio por factura',
      icon: 'tabler-receipt',
      color: 'success',
      tooltip: 'Promedio de ingreso generado por cada transacción completada (Facturación / Ventas).'
    },
    { 
      title: 'Unidades por Ticket (UPT)', 
      mainValue: (k.units_per_transaction || 0).toFixed(2), 
      subValue: `${formatNumber(k.total_units)} unidades totales`,
      icon: 'tabler-packages',
      color: 'secondary',
      tooltip: 'Cantidad promedio de unidades/artículos despachados por cada transacción.'
    },
    { 
      title: 'Ventas Cruzadas', 
      mainValue: `${k.cross_selling_rate || 0}%`, 
      subValue: `${formatNumber(k.cross_selling_count)} tickets multi-ítem`,
      icon: 'tabler-arrows-cross',
      color: 'info',
      tooltip: 'Porcentaje de ventas que incluyeron 2 o más productos distintos.'
    },
    { 
      title: 'Venta Diaria Prom.', 
      mainValue: formatCurrency(k.avg_daily_sales), 
      subValue: `${k.operational_days || 0} días operativos`,
      icon: 'tabler-calendar-stats',
      color: 'primary',
      tooltip: 'Ingreso promedio generado por día con transacciones registradas.'
    },
    { 
      title: 'Cotizaciones Generadas', 
      mainValue: formatNumber(k.quotations_generated), 
      subValue: `Conversión: ${k.conversion_rate || 0}%`,
      icon: 'tabler-file-invoice',
      color: 'warning',
      tooltip: 'Total de presupuestos emitidos y porcentaje de los que se concretaron en ventas.'
    },
    { 
      title: 'Tickets Abandonados', 
      mainValue: formatNumber(k.abandoned_sales), 
      subValue: `Tasa abandono: ${abandonmentRate}%`,
      icon: 'tabler-shopping-cart-off',
      color: 'error',
      tooltip: 'Carritos o compras canceladas o abandonadas antes de concretar el pago.'
    },
    { 
      title: 'Descuentos Otorgados', 
      mainValue: formatCurrency(k.discount_total), 
      subValue: 'Rebajas aplicadas en caja',
      icon: 'tabler-discount-2',
      color: 'warning',
      tooltip: 'Suma acumulada del valor de descuentos y promociones concedidas en el POS.'
    },
  ];
});
</script>

<template>
  <VRow class="mb-6" dense>
    <VCol cols="12" sm="6" md="3" lg="3" v-for="(kpi, idx) in kpisList" :key="idx">
      <VCard variant="outlined" class="rounded-lg h-100 kpi-card">
        <VTooltip activator="parent" location="top" max-width="300">
          {{ kpi.tooltip }}
        </VTooltip>
        <VCardText class="pa-4 d-flex align-center">
          <VAvatar :color="kpi.color" variant="tonal" size="44" rounded="lg" class="me-3 font-weight-bold">
            <VIcon :icon="kpi.icon" size="22" />
          </VAvatar>
          <div class="overflow-hidden flex-grow-1">
            <p class="text-caption text-medium-emphasis mb-0 font-weight-bold text-truncate">{{ kpi.title }}</p>
            <h3 class="text-h6 font-weight-bold leading-tight">{{ kpi.mainValue }}</h3>
            <p class="text-caption text-disabled mb-0 text-truncate">
              {{ kpi.subValue }}
            </p>
          </div>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>

<style scoped>
.kpi-card {
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.kpi-card:hover {
  transform: translateY(-2px);
}
</style>
