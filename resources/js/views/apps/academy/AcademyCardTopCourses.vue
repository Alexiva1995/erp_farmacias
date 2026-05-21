<script setup lang="ts">
import axios from 'axios'
import { onMounted, ref } from 'vue'

const exchangeRates = ref([])
const systemProfitability = ref(0)
const isUpdating = ref(false)

const fetchRates = async () => {
  try {
    const response = await axios.get('/api/dashboard/analytics-data')
    exchangeRates.value = response.data.exchange_rates || []
    systemProfitability.value = response.data.system_profitability || 0
  } catch (error) {
    console.error('Error al cargar tasas de cambio:', error)
  }
}

const updateRate = async (id: number) => {
  isUpdating.value = true
  try {
    // Simulación de actualización, aquí iría tu lógica real de sync
    await new Promise(resolve => setTimeout(resolve, 1000))
    await fetchRates()
  } finally {
    isUpdating.value = false
  }
}

const applyProfitability = async () => {
  isUpdating.value = true
  try {
    // Simulación de aplicación de rentabilidad
    await new Promise(resolve => setTimeout(resolve, 1000))
  } finally {
    isUpdating.value = false
  }
}

onMounted(fetchRates)
</script>

<template>
  <VCard>
    <VCardItem title="Tasas De Cambio">
      <template #append>
        <VIcon icon="tabler-currency-dollar" size="24" class="text-disabled" />
      </template>
    </VCardItem>

    <VCardText>
      <VList class="card-list">
        <VListItem
          v-for="(rate, index) in exchangeRates"
          :key="index"
          class="px-0"
        >
          <template #prepend>
            <VAvatar
              rounded
              variant="tonal"
              :color="['info', 'success', 'warning', 'error'][index % 4]"
              size="34"
            >
              <span class="text-xs font-weight-bold">{{ rate.currency }}</span>
            </VAvatar>
          </template>

          <VListItemTitle class="ms-2">
            <div class="d-flex align-center justify-space-between w-100">
              <div class="d-flex flex-column">
                <h6 class="text-h6 mb-0">{{ rate.currency }}</h6>
                <div class="text-caption text-success font-weight-bold">
                  {{ rate.rate }}
                </div>
              </div>
              <VBtn
                icon
                variant="text"
                size="small"
                color="primary"
                :loading="isUpdating"
                @click="updateRate(rate.id)"
              >
                <VIcon icon="tabler-refresh" size="20" />
              </VBtn>
            </div>
          </VListItemTitle>
        </VListItem>

        <VDivider class="my-4" />

        <VListItem class="px-0">
          <template #prepend>
            <VAvatar
              rounded
              variant="tonal"
              color="primary"
              size="34"
            >
              <VIcon icon="tabler-chart-pie" size="20" />
            </VAvatar>
          </template>

          <VListItemTitle class="ms-2">
            <div class="d-flex align-center justify-space-between w-100">
              <div class="d-flex flex-column">
                <h6 class="text-h6 mb-0">Rentabilidad</h6>
                <div class="text-caption text-primary font-weight-bold">
                  {{ systemProfitability }}% Sistema
                </div>
              </div>
              <VBtn
                icon
                variant="text"
                size="small"
                color="success"
                :loading="isUpdating"
                @click="applyProfitability"
              >
                <VIcon icon="tabler-check" size="20" />
              </VBtn>
            </div>
          </VListItemTitle>
        </VListItem>
      </VList>
    </VCardText>
  </VCard>
</template>

<style lang="scss" scoped>
.card-list {
  --v-card-list-gap: 16px;
}
</style>
