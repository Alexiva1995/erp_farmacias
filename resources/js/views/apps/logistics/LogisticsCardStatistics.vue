<script setup lang="ts">
import axios from 'axios'
import { onMounted, ref } from 'vue'

const autoOrdersSummary = ref({
  pending: 0,
  sent: 0,
  completed: 0,
  total: 0,
})

const logisticData = ref([
  { icon: 'tabler-clock', color: 'warning', title: 'Auto Órdenes Pendientes', value: 0, change: 0, isHover: false, key: 'pending' },
  { icon: 'tabler-send', color: 'info', title: 'Auto Órdenes Enviadas', value: 0, change: 0, isHover: false, key: 'sent' },
  { icon: 'tabler-circle-check', color: 'success', title: 'Auto Órdenes Finalizadas', value: 0, change: 0, isHover: false, key: 'completed' },
  { icon: 'tabler-list-details', color: 'primary', title: 'Total Auto Órdenes (Mes)', value: 0, change: 0, isHover: false, key: 'total' },
])

const fetchAutoOrders = async () => {
  try {
    const response = await axios.get('/api/dashboard/analytics-data')
    const data = response.data.auto_orders_summary
    if (data) {
      logisticData.value.forEach(item => {
        item.value = data[item.key] || 0
      })
    }
  } catch (error) {
    console.error('Error al cargar resumen de auto órdenes:', error)
  }
}

onMounted(fetchAutoOrders)
</script>

<template>
  <VRow>
    <VCol
      v-for="(data, index) in logisticData"
      :key="index"
      cols="12"
      md="3"
      sm="6"
    >
      <div>
        <VCard
          class="logistics-card-statistics cursor-pointer"
          :style="data.isHover ? `border-block-end-color: rgb(var(--v-theme-${data.color}))` : `border-block-end-color: rgba(var(--v-theme-${data.color}),0.38)`"
          @mouseenter="data.isHover = true"
          @mouseleave="data.isHover = false"
        >
          <VCardText>
            <div class="d-flex align-center gap-x-4 mb-1">
              <VAvatar
                variant="tonal"
                :color="data.color"
                rounded
              >
                <VIcon
                  :icon="data.icon"
                  size="28"
                />
              </VAvatar>
              <h4 class="text-h4">
                {{ data.value }}
              </h4>
            </div>
            <div class="text-body-1 mb-1">
              {{ data.title }}
            </div>
          </VCardText>
        </VCard>
      </div>
    </VCol>
  </VRow>
</template>

<style lang="scss" scoped>
@use "@core-scss/base/mixins" as mixins;

.logistics-card-statistics {
  border-block-end-style: solid;
  border-block-end-width: 2px;

  &:hover {
    border-block-end-width: 3px;
    margin-block-end: -1px;

    @include mixins.elevation(8);

    transition: all 0.1s ease-out;
  }
}

.skin--bordered {
  .logistics-card-statistics {
    border-block-end-width: 2px;

    &:hover {
      border-block-end-width: 3px;
      margin-block-end: -2px;
      transition: all 0.1s ease-out;
    }
  }
}
</style>
