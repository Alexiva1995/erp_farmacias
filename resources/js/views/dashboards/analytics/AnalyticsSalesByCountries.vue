<script setup lang="ts">
import axios from 'axios'
import { onMounted, ref } from 'vue'

const labSummary = ref([])

const fetchAnalytics = async () => {
  try {
    const response = await axios.get('/api/dashboard/analytics-data')
    labSummary.value = response.data.lab_summary_amount
  } catch (error) {
    console.error('Error al cargar ventas por laboratorio:', error)
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
  <VCard
    title="Ventas por Laboratorio"
    subtitle="Ingresos USD y comparativa mensual"
  >
    <VCardText>
      <VList class="card-list">
        <VListItem
          v-for="lab in labSummary"
          :key="lab.name"
        >
          <template #prepend>
            <VAvatar
              size="34"
              color="primary"
              variant="tonal"
              class="me-1"
            >
              <VIcon icon="tabler-building-factory-2" size="20" />
            </VAvatar>
          </template>

          <VListItemTitle class="font-weight-medium">
            {{ formatCurrencyUSD(lab.amount) }}
          </VListItemTitle>
          <VListItemSubtitle>
            {{ lab.name }}
          </VListItemSubtitle>

          <template #append>
            <div :class="`d-flex align-center ${lab.is_positive ? 'text-success' : 'text-error'}`">
              <VIcon
                :icon="lab.is_positive ? 'tabler-chevron-up' : 'tabler-chevron-down'"
                size="20"
                class="me-1"
              />
              <div class="font-weight-medium">
                {{ Math.abs(lab.change_pct) }}%
              </div>
            </div>
          </template>
        </VListItem>
        
        <VListItem v-if="labSummary.length === 0" class="text-center py-4">
          <span class="text-caption text-disabled">Sin ventas registradas este mes</span>
        </VListItem>
      </VList>
    </VCardText>
  </VCard>
</template>

<style lang="scss" scoped>
.card-list {
  --v-card-list-gap: 1rem;
}
</style>
