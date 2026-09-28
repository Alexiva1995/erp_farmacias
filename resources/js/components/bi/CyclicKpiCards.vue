<script setup>
import { computed } from 'vue'
import { formatCurrency } from '@/utils/currencyFormatter'

const props = defineProps({
  kpis: {
    type: Object,
    default: () => ({}),
  },
  loading: {
    type: Boolean,
    default: false,
  },
})

const kpiItems = computed(() => [
  {
    title: 'ERI (Precisión)',
    value: `${props.kpis.eri ?? 100}%`,
    icon: 'tabler-target',
    color: (props.kpis.eri ?? 100) >= 95 ? 'success' : 'warning',
    desc: 'Precisión de registro físico',
    subtitle: (props.kpis.eri ?? 100) >= 95 ? 'Meta alcanzada (≥95%)' : 'Por debajo de meta (<95%)',
  },
  {
    title: 'Pérdida Neta',
    value: formatCurrency(props.kpis.net_loss ?? 0),
    icon: 'tabler-currency-dollar',
    color: (props.kpis.net_loss ?? 0) > 0 ? 'error' : 'success',
    desc: 'Ajuste neto consolidado',
    subtitle: `Faltante: ${formatCurrency(props.kpis.missing_loss_value ?? 0)}`,
  },
  {
    title: 'Tasa de Error',
    value: `${props.kpis.error_rate ?? 0}%`,
    icon: 'tabler-alert-circle',
    color: (props.kpis.error_rate ?? 0) > 5 ? 'error' : 'warning',
    desc: 'SKUs con discrepancia',
    subtitle: `Auditados: ${(props.kpis.total_counted_skus ?? 0).toLocaleString()} SKUs`,
  },
  {
    title: 'Unid. Faltantes',
    value: (props.kpis.total_missing_units ?? 0).toLocaleString(),
    icon: 'tabler-trending-down',
    color: 'error',
    desc: 'Faltante físico detectado',
    subtitle: 'Merma o descuadre operativo',
  },
  {
    title: 'Unid. Sobrantes',
    value: (props.kpis.total_surplus_units ?? 0).toLocaleString(),
    icon: 'tabler-trending-up',
    color: 'success',
    desc: 'Sobrante físico detectado',
    subtitle: 'Excedente o ingreso no registrado',
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
        <VCardText class="pa-4">
          <div v-if="loading" class="d-flex align-center">
            <VSkeletonLoader type="avatar" class="me-3" />
            <div class="flex-grow-1">
              <VSkeletonLoader type="text" width="60%" class="mb-1" />
              <VSkeletonLoader type="heading" width="80%" class="mb-1" />
              <VSkeletonLoader type="text" width="40%" />
            </div>
          </div>
          <div v-else class="d-flex align-center">
            <VAvatar
              :color="kpi.color"
              variant="tonal"
              size="46"
              rounded="lg"
              class="me-3 flex-shrink-0"
            >
              <VIcon :icon="kpi.icon" size="24" />
            </VAvatar>
            <div class="min-width-0 flex-grow-1">
              <span class="text-caption text-medium-emphasis font-weight-bold d-block text-truncate">
                {{ kpi.title }}
              </span>
              <h4 class="text-h5 font-weight-black my-0 text-truncate">
                {{ kpi.value }}
              </h4>
              <span class="text-caption text-disabled d-block text-truncate" :title="kpi.subtitle || kpi.desc">
                {{ kpi.subtitle || kpi.desc }}
              </span>
            </div>
          </div>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>
