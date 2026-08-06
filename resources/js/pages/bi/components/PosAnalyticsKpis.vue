<script setup>
import { computed } from 'vue';

const props = defineProps({
  kpis: { type: Object, default: () => ({}) }
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
      icon: 'tabler-shopping-cart-check', color: 'primary', desc: 'Volumen total' 
    },
    { 
      title: 'Tks. Abandonados', 
      mainValue: formatNumber(k.abandoned_sales), 
      subValue: 'Bajas en caja',
      icon: 'tabler-shopping-cart-off', color: 'error', desc: 'Pérdida operativa' 
    },
    { 
      title: 'Ventas Cruzadas', 
      mainValue: (k.cross_selling_rate || 0) + '%', 
      subValue: formatNumber(k.cross_selling_count) + ' tickets',
      icon: 'tabler-arrows-cross', color: 'info', desc: 'Penetración' 
    },
    { 
      title: 'Cotizaciones', 
      mainValue: formatNumber(k.quotations_generated), 
      subValue: 'Tasa: ' + (k.conversion_rate || 0) + '%',
      icon: 'tabler-file-invoice', color: 'warning', desc: 'Conversión' 
    },
    { 
      title: 'Ticket Medio', 
      mainValue: formatCurrency(k.avg_ticket), 
      subValue: 'Valor por factura',
      icon: 'tabler-cash', color: 'success', desc: 'Ticket Medio' 
    },
    { 
      title: 'Venta Diaria', 
      mainValue: formatCurrency(k.avg_daily_sales), 
      subValue: 'Ticket estimado',
      icon: 'tabler-calendar-stats', color: 'info', desc: 'Ingreso Diario' 
    }
  ];
});
</script>

<template>
  <VRow class="mb-6" dense>
    <VCol cols="12" sm="6" md="4" lg="2" v-for="(kpi, idx) in kpisList" :key="idx">
      <VCard border class="rounded-lg shadow-sm h-100 bg-surface kpi-card">
        <VCardText class="pa-4 d-flex align-center">
          <VAvatar :color="kpi.color" variant="tonal" size="38" rounded="lg" class="me-3 font-weight-bold">
            <VIcon :icon="kpi.icon" size="18" />
          </VAvatar>
          <div class="overflow-hidden">
            <p class="text-[12px] text-disabled mb-0 font-weight-bold truncate">{{ kpi.title }}</p>
            <h3 class="text-h6 font-weight-black leading-tight">{{ kpi.mainValue }}</h3>
            <p class="text-[10px] text-medium-emphasis mb-0 truncate opacity-70">
              {{ kpi.subValue }}
            </p>
          </div>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>

<style scoped>
.font-weight-black { font-weight: 900 !important; }
.truncate { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.kpi-card { transition: transform 0.2s ease; }
.kpi-card:hover { transform: translateY(-2px); }
</style>
