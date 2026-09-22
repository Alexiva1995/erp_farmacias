<script setup>
import { computed } from 'vue'

const props = defineProps({
  kpis: {
    type: Object,
    default: () => ({
      total_pending_amount: 0,
      total_pending_count: 0,
      total_expired_amount: 0,
      total_expired_count: 0,
      total_paid_amount: 0,
      total_paid_count: 0,
      next_due_date: null,
    }),
  },
  loading: {
    type: Boolean,
    default: false,
  },
})

const formatCurrency = (value) => {
  return new Intl.NumberFormat('es-VE', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(value || 0)
}

const formatDate = (dateStr) => {
  if (!dateStr) return 'Sin vencimientos próximos'
  const parts = dateStr.split('-')
  if (parts.length === 3) {
    return `${parts[2]}/${parts[1]}/${parts[0]}`
  }
  return dateStr
}

const cards = computed(() => [
  {
    title: 'TOTAL POR PAGAR',
    value: formatCurrency(props.kpis.total_pending_amount),
    subtitle: `${props.kpis.total_pending_count || 0} compromisos pendientes`,
    icon: 'tabler-cash-banknote',
    color: 'warning',
    textColor: 'text-warning',
    bgColor: 'bg-warning-tonal',
  },
  {
    title: 'COMPROMISOS VENCIDOS',
    value: formatCurrency(props.kpis.total_expired_amount),
    subtitle: `${props.kpis.total_expired_count || 0} vencidos sin pagar`,
    icon: 'tabler-alert-triangle',
    color: 'error',
    textColor: 'text-error',
    bgColor: 'bg-error-tonal',
  },
  {
    title: 'TOTAL PAGADO',
    value: formatCurrency(props.kpis.total_paid_amount),
    subtitle: `${props.kpis.total_paid_count || 0} cancelados`,
    icon: 'tabler-checkbox',
    color: 'success',
    textColor: 'text-success',
    bgColor: 'bg-success-tonal',
  },
  {
    title: 'PRÓXIMO VENCIMIENTO',
    value: formatDate(props.kpis.next_due_date),
    subtitle: props.kpis.next_due_date ? 'Atención a fecha límite' : 'Al día',
    icon: 'tabler-calendar-due',
    color: 'info',
    textColor: 'text-info',
    bgColor: 'bg-info-tonal',
    isDate: true,
  },
])
</script>

<template>
  <VRow class="match-height mb-3">
    <VCol
      v-for="(card, index) in cards"
      :key="index"
      cols="12"
      sm="6"
      md="3"
    >
      <VCard
        elevation="0"
        rounded="lg"
        class="kpi-card border shadow-xs transition-all bg-surface"
      >
        <VCardText class="pa-4">
          <div class="d-flex justify-space-between align-center mb-2">
            <span class="text-xs text-disabled uppercase font-weight-bold tracking-wider">
              {{ card.title }}
            </span>
            <VAvatar
              :color="card.color"
              variant="tonal"
              size="36"
              rounded="lg"
            >
              <VIcon :icon="card.icon" size="20" />
            </VAvatar>
          </div>

          <div class="d-flex align-baseline gap-1 my-1">
            <span
              v-if="!card.isDate"
              class="text-xs font-weight-medium text-disabled"
            >
              Bs.
            </span>
            <span
              :class="[
                card.isDate ? 'text-sm font-weight-bold' : 'text-lg font-weight-black',
                card.textColor,
              ]"
            >
              {{ card.value }}
            </span>
          </div>

          <div class="d-flex align-center gap-1 mt-1">
            <span class="text-xs text-disabled">
              {{ card.subtitle }}
            </span>
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
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
}
</style>
