<script setup>
import { computed } from 'vue'
import { formatCurrency } from '@/utils/currencyFormatter'

const props = defineProps({
  kpis: {
    type: Object,
    default: () => ({}),
  },
})

const kpiItems = computed(() => [
  {
    title: 'ERI (Precisión)',
    value: `${props.kpis.eri || 0}%`,
    icon: 'tabler-target',
    color: (props.kpis.eri || 0) >= 95 ? 'success' : 'warning',
    desc: 'Precisión de registro físico',
  },
  {
    title: 'Pérdida Neta',
    value: formatCurrency(props.kpis.net_loss || 0),
    icon: 'tabler-currency-dollar',
    color: (props.kpis.net_loss || 0) > 0 ? 'error' : 'success',
    desc: 'Ajuste consolidado',
  },
  {
    title: 'Tasa de Error',
    value: `${props.kpis.error_rate || 0}%`,
    icon: 'tabler-alert-circle',
    color: (props.kpis.error_rate || 0) > 5 ? 'error' : 'warning',
    desc: 'SKUs con discrepancia',
  },
  {
    title: 'Unid. Faltantes',
    value: (props.kpis.total_missing_units || 0).toLocaleString(),
    icon: 'tabler-trending-down',
    color: 'error',
    desc: 'Faltante físico detectado',
  },
  {
    title: 'Unid. Sobrantes',
    value: (props.kpis.total_surplus_units || 0).toLocaleString(),
    icon: 'tabler-trending-up',
    color: 'success',
    desc: 'Sobrante físico detectado',
  },
])
</script>

<template>
  <VRow class="mb-6" dense>
    <VCol
      v-for="(kpi, idx) in kpiItems"
      :key="idx"
      cols="12"
      sm="6"
      md
    >
      <VCard class="rounded-lg border shadow-sm h-100">
        <VCardText class="pa-4 d-flex align-center">
          <VAvatar
            :color="kpi.color"
            variant="tonal"
            size="46"
            rounded="lg"
            class="me-3"
          >
            <VIcon :icon="kpi.icon" size="24" />
          </VAvatar>
          <div class="min-width-0">
            <span class="text-caption text-medium-emphasis font-weight-bold d-block text-truncate">
              {{ kpi.title }}
            </span>
            <h4 class="text-h5 font-weight-black my-0 text-truncate">
              {{ kpi.value }}
            </h4>
            <span class="text-caption text-disabled d-block text-truncate">
              {{ kpi.desc }}
            </span>
          </div>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>
