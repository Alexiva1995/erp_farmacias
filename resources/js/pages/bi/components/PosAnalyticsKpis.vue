<script setup>
import { computed } from 'vue';

const props = defineProps({
  kpis: { type: Object, default: () => ({}) },
});

const formatCurrency = (val) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(val || 0);
const formatNumber = (val) => new Intl.NumberFormat('en-US').format(val || 0);

const kpisList = computed(() => {
  const k = props.kpis;
  return [
    { 
      title: 'Ventas Exitosas', 
      mainValue: formatNumber(k.completed_sales), 
      subValue: formatCurrency((k.avg_ticket || 0) * (k.completed_sales || 0)),
      icon: 'tabler-shopping-cart-check',
      color: 'primary',
      desc: 'Volumen total' 
    },
    { 
      title: 'Tks. Abandonados', 
      mainValue: formatNumber(k.abandoned_sales), 
      subValue: 'Bajas en caja',
      icon: 'tabler-shopping-cart-off',
      color: 'error',
      desc: 'Pérdida operativa' 
    },
    { 
      title: 'Ventas Cruzadas', 
      mainValue: `${k.cross_selling_rate || 0}%`, 
      subValue: `${formatNumber(k.cross_selling_count)} tickets`,
      icon: 'tabler-arrows-cross',
      color: 'info',
      desc: 'Penetración' 
    },
    { 
      title: 'Cotizaciones', 
      mainValue: formatNumber(k.quotations_generated), 
      subValue: `Tasa: ${k.conversion_rate || 0}%`,
      icon: 'tabler-file-invoice',
      color: 'warning',
      desc: 'Conversión' 
    },
    { 
      title: 'Ticket Medio', 
      mainValue: formatCurrency(k.avg_ticket), 
      subValue: 'Valor por factura',
      icon: 'tabler-cash',
      color: 'success',
      desc: 'Ticket Medio' 
    },
    { 
      title: 'Venta Diaria', 
      mainValue: formatCurrency(k.avg_daily_sales), 
      subValue: 'Ingreso estimado',
      icon: 'tabler-calendar-stats',
      color: 'secondary',
      desc: 'Ingreso Diario' 
    }
  ];
});
</script>

<template>
  <VRow class="mb-6" dense>
    <VCol cols="12" sm="6" md="4" lg="2" v-for="(kpi, idx) in kpisList" :key="idx">
      <VCard variant="outlined" class="rounded-lg shadow-sm h-100 kpi-card">
        <VCardText class="pa-4 d-flex align-center">
          <VAvatar :color="kpi.color" variant="tonal" size="42" rounded="lg" class="me-3 font-weight-bold">
            <VIcon :icon="kpi.icon" size="20" />
          </VAvatar>
          <div class="overflow-hidden">
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
