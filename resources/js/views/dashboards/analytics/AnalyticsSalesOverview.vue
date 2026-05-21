<script setup lang="ts">
import axios from 'axios'
import { onMounted, ref } from 'vue'

const totalMonthlySales = ref(0)
const ordersSummary = ref({
  completed: 0,
  cancelled: 0,
  total: 0,
  completed_pct: 0,
  cancelled_pct: 0,
})

const fetchAnalytics = async () => {
  try {
    const response = await axios.get('/api/dashboard/analytics-data')
    totalMonthlySales.value = response.data.total_monthly_sales
    ordersSummary.value = response.data.orders_summary
  } catch (error) {
    console.error('Error al cargar datos de resumen de ventas:', error)
  }
}

onMounted(fetchAnalytics)

const formatCurrencyUSD = (value) => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
  }).format(value)
}
</script>

<template>
  <VCard>
    <VCardText class="pb-1">
      <div class="d-flex align-center justify-space-between">
        <div class="text-caption font-weight-medium">
          Resumen de Ventas
        </div>
        <div class="text-success text-caption font-weight-bold">
          {{ formatCurrencyUSD(totalMonthlySales) }}
        </div>
      </div>
    </VCardText>

    <VCardText class="pt-0">
      <VRow no-gutters>
        <VCol cols="5">
          <div class="py-1">
            <div class="d-flex align-center mb-1">
              <VAvatar color="success" variant="tonal" :size="20" rounded class="me-1">
                <VIcon size="14" icon="tabler-circle-check" />
              </VAvatar>
              <span class="text-caption">Efectivas</span>
            </div>
            <h6 class="text-h6 mb-0">{{ ordersSummary.completed_pct }}%</h6>
            <div class="text-caption text-disabled">{{ ordersSummary.completed }} p.</div>
          </div>
        </VCol>

        <VCol cols="2" class="d-flex align-center justify-center">
          <div class="text-overline text-disabled">VS</div>
        </VCol>

        <VCol cols="5" class="text-end">
          <div class="py-1">
            <div class="d-flex align-center justify-end mb-1">
              <span class="me-1 text-caption">Cancel.</span>
              <VAvatar color="error" variant="tonal" :size="20" rounded>
                <VIcon size="14" icon="tabler-circle-x" />
              </VAvatar>
            </div>
            <h6 class="text-h6 mb-0">{{ ordersSummary.cancelled_pct }}%</h6>
            <div class="text-caption text-disabled">{{ ordersSummary.cancelled }} p.</div>
          </div>
        </VCol>
      </VRow>

      <div class="mt-2">
        <VProgressLinear
          :model-value="ordersSummary.completed_pct"
          color="success"
          height="6"
          bg-color="error"
          rounded
        />
      </div>
    </VCardText>
  </VCard>
</template>
